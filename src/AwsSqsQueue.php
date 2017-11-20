<?php

namespace Drupal\aws_sqs;

use Aws\Common\Client\AwsClientInterface;
use Drupal\aws_sqs\Model\QueueItem;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\ReliableQueueInterface;

/**
 * Amazon SQS client.
 *
 * Uses SQS Client provided by AWS SDK PHP version 3.
 *
 * More info:
 *   - http://aws.amazon.com/php
 *   - https://github.com/aws/aws-sdk-php
 *   - http://docs.aws.amazon.com/aws-sdk-php-2/latest/
 *   - http://docs.aws.amazon.com/aws-sdk-php-2/guide/latest/service-sqs.html
 *
 * Responses to HTTP requests made through SqsClient are returned as Guzzle
 * objects. More info about Guzzle here:
 *   - http://guzzlephp.org/
 */
class AwsSqsQueue implements ReliableQueueInterface {

  /**
   * The name of the queue this instance is working with.
   *
   * @var string
   */
  protected $claimTimeout;

  /**
   * SqsClient provided by AWS as interface to SQS.
   *
   * @var \Aws\Common\Client\AwsClientInterface
   */
  protected $client;

  /**
   * Queue name.
   *
   * @var string
   */
  protected $name;

  /**
   * Unique identifier for queue.
   *
   * @var string
   */
  protected $queueUrl;

  /**
   * Wait time in seconds.
   *
   * @var int
   */
  protected $waitTimeSeconds;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * AwsSqsQueue constructor.
   *
   * @param string $name
   *   The queue name.
   * @param AwsClientInterface $client
   *   The AWS Client.
   * @param LoggerChannelInterface $logger
   *   The logger.
   */
  public function __construct($name, AwsClientInterface $client, LoggerChannelInterface $logger) {
    $this->name = $name;
    $this->client = $client;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   *
   * Invokes SqsClient::sendMessage()
   * http://docs.aws.amazon.com/aws-sdk-php/v3/api/api-sqs-2012-11-05.html#sendmessage.
   *
   * @param mixed $data
   *   Arbitrary data to be associated with the new task in the queue. It will
   *   be serialized before sending.
   *
   * @return string|bool
   *   A unique ID if the item was successfully created and was (best effort)
   *   added to the queue, otherwise FALSE.
   */
  public function createItem($data) {
    // @todo Check if data size limit is 64kb (Validate, link to documentation).
    /** @var \Guzzle\Service\Resource\Model $result */
    $result = $this->client->sendMessage([
      'QueueUrl' => $this->getQueueUrl(),
      'MessageBody' => $this->serialize($data),
    ]);

    return $result->get('MessageId') ?: FALSE;
  }

  /**
   * {@inheritdoc}
   *
   * Invokes SqsClient::getQueueAttributes().
   *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_getQueueAttributes.
   */
  public function numberOfItems() {
    /** @var \Guzzle\Service\Resource\Model $response */
    $response = $this->client->getQueueAttributes([
      'QueueUrl' => $this->getQueueUrl(),
      'AttributeNames' => ['ApproximateNumberOfMessages'],
    ]);

    $attributes = $response->get('Attributes');
    if (!empty($attributes['ApproximateNumberOfMessages'])) {
      $return = $attributes['ApproximateNumberOfMessages'];
    }
    else {
      $return = 0;
    }

    return $return;
  }

  /**
   * {@inheritdoc}
   *
   * Invokes SqsClient::receiveMessage().
   *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_receiveMessage
   *  http://docs.aws.amazon.com/aws-sdk-php-2/guide/latest/service-sqs.html#receiving-messages.
   *
   * @param int $lease_time
   *   (optional) How long the processing is expected to take in seconds.
   *   0 by default.
   *
   * @return \Drupal\aws_sqs\Model\QueueItem|bool
   *   On success we return an item object. If the queue is unable to claim an
   *   item it returns false.
   */
  public function claimItem($lease_time = 0) {
    // This is important to support blocking calls to the queue system.
    $waitTimeSeconds = $this->getWaitTimeSeconds();
    $claimTimeout = ($lease_time) ? $lease_time : $this->getClaimTimeout();
    // If our given claimTimeout is smaller than the allowed waiting seconds
    // set the waitTimeSeconds to this value. This is to avoid a long call when
    // the worker that called claimItem only has a finite amount of time to wait
    // for an item
    // if $waitTimeSeconds is set to 0, it will never use the blocking
    // logic (which is intended)
    if ($claimTimeout < $waitTimeSeconds) {
      $waitTimeSeconds = $claimTimeout;
    }

    // @todo Add error handling, in case service becomes unavailable.
    // Fetch the queue item.
    /** @var \Guzzle\Service\Resource\Model $response */
    $response = $this->client->receiveMessage([
      'QueueUrl' => $this->getQueueUrl(),
      'MaxNumberOfMessages' => 1,
      'VisibilityTimeout' => $claimTimeout,
      'WaitTimeSeconds' => $waitTimeSeconds,
    ], $lease_time);

    // If the response does not contain 'Messages', return false.
    $messages = $response->get('Messages');
    if (!$messages) {
      return FALSE;
    }

    $message = reset($messages);

    // If the item id is not set, return false.
    if (empty($message['MessageId'])) {
      return FALSE;
    }

    $item = new QueueItem();
    $item->setData($this->unserialize($message['Body']));
    $item->setItemId($message['MessageId']);
    $item->setReceiptHandle($message['ReceiptHandle']);

    return $item;
  }

  /**
   * {@inheritdoc}
   *
   * In AWS lingo, you release a claim on an item in the queue by "terminating
   * its visibility timeout". (Similarly, you can extend the amount of time for
   * which an item is claimed by extending its visibility timeout. The maximum
   * visibility timeout for any item in any queue is 12 hours, including all
   * extensions.)
   *
   * Invokes SqsClient::ChangeMessageVisibility().
   *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_changeMessageVisibility
   *  http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/AboutVT.html
   *
   * @param object $item
   *   \Drupal\aws_sqs\Model\QueueItem, item retrieved from queue.
   *
   * @return bool
   *   TRUE if the item has been released, FALSE otherwise.
   */
  public function releaseItem($item) {
    /** @var \Guzzle\Service\Resource\Model $response */
    $response = $this->client->changeMessageVisibility([
      'QueueUrl' => $this->getQueueUrl(),
      'ReceiptHandle' => $item->getReceiptHandle(),
      'VisibilityTimeout' => 0,
    ]);

    return self::isGuzzleServiceResourceModel($response);
  }

  /**
   * Deletes an item from the queue with deleteMessage method.
   *
   * Invokes SqsClient::deleteMessage().
   *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_deleteMessage.
   *
   * @param object $item
   *   \Drupal\aws_sqs\Model\QueueItem , item retrieved from queue.
   *
   * @throws \Exception
   */
  public function deleteItem($item) {
    if ($item->getReceiptHandle()) {
      throw new \Exception("An item that needs to be deleted requires a handle ID");
    }

    $this->client->deleteMessage([
      'QueueUrl' => $this->getQueueUrl(),
      'ReceiptHandle' => $item->getReceiptHandle(),
    ]);
  }

  /**
   * {@inheritdoc}
   *
   * Invokes SqsClient::createQueue().
   *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_createQueue.
   */
  public function createQueue() {
    /** @var \Guzzle\Service\Resource\Model $response */
    $response = $this->client->createQueue(['QueueName' => $this->name]);
    $this->setQueueUrl($response->get('QueueUrl'));
  }

  /**
   * {@inheritdoc}
   *
   * Invokes SqsClient::deleteQueue().
   *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_deleteQueue.
   */
  public function deleteQueue() {
    $this->client->deleteQueue(['QueueUrl' => $this->getQueueUrl()]);
  }

  /**
   * Determine whether object is an instance of Guzzle\Service\Resource\Model.
   *
   * @param object $object
   *   The object.
   *
   * @return bool
   */
  protected static function isGuzzleServiceResourceModel($object) {
    // If $object is the type of object we expect, everything went okay.
    return (is_object($object) && get_class($object) == 'Guzzle\Service\Resource\Model') ? TRUE : FALSE;
  }

  /**
   * Serializes data.
   *
   * @param mixed $data
   *   The un-serialized data.
   *
   * @return string
   *   The serialized data.
   */
  protected static function serialize($data) {
    // @todo: Integrate Drupal serialization.
    return base64_encode(serialize($data));
  }

  /**
   * Un-serializes data.
   *
   * @param string $data
   *   The serialized data.
   *
   * @return string
   *   The un-serialized data.
   */
  protected static function unserialize($data) {
    // @todo: Integrate Drupal serialization.
    return unserialize(base64_decode($data));
  }

  /**
   * Gets timeout.
   *
   * @return string
   *   The timeout.
   */
  public function getClaimTimeout() {
    return $this->claimTimeout;
  }

  /**
   * Sets timeout.
   *
   * @param int $timeout
   *   The timeout.
   */
  public function setClaimTimeout($timeout) {
    $this->claimTimeout = $timeout;
  }

  /**
   * Gets client.
   *
   * @return \Aws\Common\Client\AwsClientInterface
   *   The client.
   */
  public function getClient() {
    return $this->client;
  }

  /**
   * Sets client.
   *
   * @param \Aws\Common\Client\AwsClientInterface $client
   *   The client.
   */
  public function setClient(AwsClientInterface $client) {
    $this->client = $client;
  }

  /**
   * Gets name.
   *
   * @return string
   *   The name.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Gets URL.
   *
   * @return string
   *   The URL.
   */
  protected function getQueueUrl() {
    return $this->queueUrl;
  }

  /**
   * Sets URL.
   *
   * @param string $queueUrl
   *   The URL.
   */
  protected function setQueueUrl($queueUrl) {
    $this->queueUrl = $queueUrl;
  }

  /**
   * Gets wait time.
   *
   * @return int
   *   The wait time.
   */
  public function getWaitTimeSeconds() {
    return $this->waitTimeSeconds;
  }

  /**
   * Sets wait time.
   *
   * @param int $seconds
   *   The wait time.
   */
  public function setWaitTimeSeconds($seconds) {
    $this->waitTimeSeconds = $seconds;
  }

}
