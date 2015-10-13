<?php

/**
 * @file
 * Definition of AwsSqsQueue.
 * Contains \Drupal\aws_sqs\Queue\AwsSqsQueue.
 */

/**
 * Use SQS Client provided by AWS SDK PHP version 2.
 *
 * More info:
 *
 *  http://aws.amazon.com/php
 *  https://github.com/aws/aws-sdk-php
 *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/
 *  http://docs.aws.amazon.com/aws-sdk-php-2/guide/latest/service-sqs.html
 *
 * Responses to HTTP requests made through SqsClient are returned as Guzzle
 * objects. More info about Guzzle here:
 *
 *  http://guzzlephp.org/
 */

namespace Drupal\aws_sqs\Queue;

use Aws\Sqs\SqsClient;
use Aws\AwsClientInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Queue\ReliableQueueInterface;


/**
 * Amazon queue.
 */
class AwsSqsQueue implements ReliableQueueInterface {

    /**
     * The name of the queue this instance is working with.
     *
     * @var string
     */
    protected $claimTimeout;
    protected $client;          // SqsClient provided by AWS as interface to SQS.
    protected $name;            // Queue name.
    protected $queueUrl;        // Uniqueue identifier for queue.
    protected $waitTimeSeconds;

    /**
     * @var Drupal\Core\Logger\LoggerChannelInterface
     */
    protected $logger;

    /**
     * AwsSqsQueue constructor.
     * @param $name
     * @param $client AwsClientInterface
     * @param $logger LoggerChannelInterface
     */
    public function __construct($name, AwsClientInterface $client, LoggerChannelInterface $logger) {
        $this->name = $name;
        $this->client = $client;
        $this->logger = $logger;

        // Ensure the the queue exists and that we have a queue URL so that we
        // aren't checking for the queueUrl everywhere.
        $this->createQueue();
    }


    /**
     * Send an item to the AWS Queue.
     *
     * Careful, you can only store data up to 64kb.
     *  @todo Add link to documentation here. I think this info is out of date.
     *    I believe now you can store more. But you get charged as if it's an additional
     *    request.
     *
     * Invokes SqsClient::sendMessage().
     *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_sendMessage
     *
     * @param $data
     *   Caller should be sending serialized data. If an item retreived from the queue is
     *   being re-submitted to the queue (if is_object($item) && $item->data &&
     *   item->item_id), only $item->data will be stored.
     *
     * @return bool
     */
    public function createItem($data) {

        // Check to see if someone is trying to save an item originally retrieved
        // from the queue. If so, this really should have been submitted as
        // $item->data, not $item. Reformat this so we don't save metadata or
        // confuse item_ids downstream.
        if (is_object($data) && property_exists($data, 'data') && property_exists($data, 'item_id')) {
            $text = t('Do not re-queue whole items retrieved from the SQS queue. This included metadata, like the item_id. Pass $item->data to createItem() as a parameter, rather than passing the entire $item. $item->data is being saved. The rest is being ignored.');
            $data = $data->data;
            $this->logger->error($text);
        }

        // @todo Add a check here for message size? Log it?

        // Create a new message object
        $result = $this->client->sendMessage(array(
            'QueueUrl'    => $this->queueUrl,
            'MessageBody' => $data,
        ));

        return (bool) $result;
    }

    /**
     * Return the amount of items in the queue
     *
     * Invokes SqsClient::getQueueAttributes().
     *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_getQueueAttributes
     *
     * @return integer
     *   Approximate Number of messages in the aws queue. Returns FALSE if SQS is
     *   not available.
     */
    public function numberOfItems() {
        // Request attributes of queue from AWS. The response is returned as a Guzzle
        // resource model object:
        // http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Guzzle.Service.Resource.Model.html
        $args = array(
            'QueueUrl' => $this->queueUrl,
            'AttributeNames' => array('ApproximateNumberOfMessages'),
        );
        $response = $this->client->getQueueAttributes($args);

        $attributes = $response->get('Attributes');
        if (!empty($attributes['ApproximateNumberOfMessages'])) {
            $return = $attributes['ApproximateNumberOfMessages'];
        }
        else {
            $return = FALSE;
        }

        return $return;
    }

    /**
     * Fetch a single item from the AWS SQS queue.
     *
     * Invokes SqsClient::receiveMessage().
     *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_receiveMessage
     *  http://docs.aws.amazon.com/aws-sdk-php-2/guide/latest/service-sqs.html#receiving-messages
     *
     * @param int $lease_time
     *   Drupal's "lease time" is the same as AWS's "Visibility Timeout". It's the
     *   amount of time for which an item is being claimed. If a user passes in a
     *   value for $lease_time here, override the default claimTimeout.
     *
     * @return
     *   On success we return an item object. If the queue is unable to claim an
     *   item it returns false. This implies a best effort to retrieve an item
     *   and either the queue is empty or there is some other non-recoverable
     *   problem.
     */
    public function claimItem($lease_time = 0) {
        // This is important to support blocking calls to the queue system
        $waitTimeSeconds = $this->getWaitTimeSeconds();
        $claimTimeout = ($lease_time) ? $lease_time : $this->getClaimTimeout();
        // if our given claimTimeout is smaller than the allowed waiting seconds
        // set the waitTimeSeconds to this value. This is to avoid a long call when
        // the worker that called claimItem only has a finite amount of time to wait
        // for an item
        // if $waitTimeSeconds is set to 0, it will never use the blocking
        // logic (which is intended)
        if ($claimTimeout < $waitTimeSeconds) {
            $waitTimeSeconds = $claimTimeout;
        }

        // Fetch the queue item.
        // @todo See usage of $lease_time. Should we use lease_time or other timeout below?
        // $message = $this->manager->receiveMessage($this->queue, $lease_time, true);

        // Retrieve item from AWS. See documentation about method and response here:
        $response = $this->client->receiveMessage(array(
            'QueueUrl' => $this->queueUrl,
            'MaxNumberOfMessages' => 1,
            'VisibilityTimeout' => $claimTimeout,
            'WaitTimeSeconds' => $waitTimeSeconds,
        ));

        // @todo Add error handling, in case service becomes unavailable.

        $item = new \stdClass();
        $messageBody = $response->toArray()['Messages']['0'];
        $item->data = $messageBody['Body'];
        $item->item_id = $messageBody['ReceiptHandle'];
        if(!empty($item->item_id)) {
            return $item;
        }
        return FALSE;
    }

    /**
     * Release claim on item in the queue.
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
     *  Item retrieved from queue. This property is required: $item->item_id.
     *
     * @return bool
     *   TRUE for success.
     */
    public function releaseItem($item) {
        $result = $this->client->changeMessageVisibility(array(
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $item->item_id,
            'VisibilityTimeout' => 0,
        ));

        // If $result is the type of object we expect, everything went okay.
        // (Typically SqsClient would have thrown an error before here if anything
        // went wrong. This check is really just for good measure.)
        return self::isGuzzleServiceResourceModel($result);
    }

    /**
     * Deletes an item from the queue with deleteMessage method.
     *
     * Invokes SqsClient::deleteMessage().
     *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_deleteMessage
     *
     * @param Message $item
     *   The item to be deleted.
     *
     * @return
     *  DrupalQueueInterface::deleteItem() returns nothing. Don't return anything here.
     */
    public function deleteItem($item) {
        if (!isset($item->item_id)) {
            throw new \Exception("An item that needs to be deleted requires a handle ID");
        }

        $result = $this->client->deleteMessage(array(
            'QueueUrl' => $this->queueUrl,
            'ReceiptHandle' => $item->item_id,
        ));
    }

    /**
     * Create the Amazon Queue.
     *
     * Store queueUrl when queue is created. This is the queue's unique
     * identifier.
     *
     * Invokes SqsClient::createQueue().
     *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_createQueue
     *
     * @return
     *  DrupalQueueInterface::createQueue() returns nothing. Don't return anything here.
     */
    public function createQueue() {
        $result = $this->client->createQueue(array('QueueName' => $this->name));
        $queueUrl = $result->get('QueueUrl');
        $this->setQueueUrl($queueUrl);
    }

    /**
     * Deletes an SQS queue.
     *
     * Invokes SqsClient::deleteQueue().
     *  http://docs.aws.amazon.com/aws-sdk-php-2/latest/class-Aws.Sqs.SqsClient.html#_deleteQueue
     *
     * @return
     *  DrupalQueueInterface::deleteQueue() returns nothing. Don't return anything here.
     */
    public function deleteQueue() {
        $result = $this->client->deleteQueue(array('QueueUrl' => $this->queueUrl));
    }

    /**
     * Determine whether an object is an instance of
     * Guzzle\Service\Resource\Model.
     *
     * @param obj $object
     *
     * @return bool
     */
    static protected function isGuzzleServiceResourceModel($object) {
        return (is_object($object) && get_class($object) == 'Guzzle\Service\Resource\Model') ? TRUE : FALSE;
    }

    /**
     * PHP's native serialize() isn't very portable. This method enables people to
     * extend this class and support other serialization formats (so that
     * something other than PHP can potentially process the data in the queue, as
     * per discussion here: https://drupal.org/node/1956190).
     *
     * @todo: Depend on the Drupal serialization module for this.
     */
    protected static function serialize($data) {
        return serialize($data);
    }

    /**
     * PHP's native serialize() isn't very portable. This method enables people to
     * extend this class and support other serialization formats (so that
     * something other than PHP can potentially process the data in the queue, as
     * per discussion here: https://drupal.org/node/1956190).
     *
     * @todo: Depend on the Drupal serialization module for this.
     */
    protected static function unserialize($data) {
        return unserialize($data);
    }

    /*******************************************************
     * Getters and setters
     *******************************************************/

    public function getClaimTimeout() {
        return $this->claimTimeout;
    }

    public function setClaimTimeout($timeout) {
        $this->claimTimeout = $timeout;
    }

    public function getClient() {
        return $this->client;
    }

    public function setClient($client) {
        $this->client = $client;
    }

    public function getName() {
        return $this->name;
    }

    protected function getQueueUrl() {
        return $this->queueUrl;
    }

    protected function setQueueUrl($queueUrl) {
        $this->queueUrl = $queueUrl;
    }

    public function getWaitTimeSeconds() {
        return $this->waitTimeSeconds;
    }

    public function setWaitTimeSeconds($seconds) {
        $this->waitTimeSeconds = $seconds;
    }
}
