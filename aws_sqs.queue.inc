<?php

/**
 * @file
 * Definition of AwsSqsQueue.
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
 *
 * Responses to HTTP requests made through SqsClient are returned as Guzzle
 * objects. More info about Guzzle here:
 *
 *  http://guzzlephp.org/
 *
 *
 * Public v. private properties and methods: Drupal coding standards discourage
 * use of private properties. AwsSqs properties here are private to encourage
 * clients to use the DrupalQueue interface, and to hide and harden SQS-specific
 * logic. "Getters" are public to give people access to property data, but use
 * these sparingly. Anywhere a getter is called, code should check
 * `if(module_exists('aws_sqs'))` and/or `if ($queue instanceof AwsSqsQueue)`.
 * If either of these checks returns FALSE, do not call AwsSqsQueue-specific
 * methods, otherwise your module will be tightly coupled with SQS and you will
 * be unable to swap the queue backend out for SystemQueue or other queues.
 * Setters are not public. If you have a use case that requires this (rather
 * than letting property setting be handled by __construct upon instantiation),
 * please post an issue in the issue queue for consideration. This seems like a
 * slippery slope, so we're avoiding it right now.
 */
use Aws\Sqs\SqsClient;

/**
 * Amazon queue.
 */
class AwsSqsQueue implements DrupalReliableQueueInterface {
  /**
   * The name of the queue this instance is working with.
   *
   * @var string
   */
  private $awsKey;          // This is the key that gets sent to AWS with your requests.
  private $awsSecret;       // Your secret. (This one doesn't get sent.)
  private $awsRegion;       // Location of AWS data center. (See constants below.)
  private $claimTimeout;
  private $client;          // SqsClient provided by AWS as interface to SQS.
  private $name;            // Queue name.
  private $queueUrl;        // Uniqueue identifier for queue.
  private $waitTimeSeconds;

  // Constants for AWS regions.
  const REGION_US_EAST_1      = 'us-east-1';
  const REGION_US_WEST_1      = 'us-west-1';
  const REGION_US_WEST_2      = 'us-west-2';
  const REGION_EU_WEST_1      = 'eu-west-1';
  const REGION_AP_SOUTHEAST_1 = 'ap-southeast-1';
  const REGION_AP_NORTHEAST_1 = 'ap-northeast-1';
  const REGION_SA_EAST_1      = 'sa-east-1';

  /**
   * Initialize the Queue Class
   *
   * @param string $name
   *   Name of the queue, will also be this name in Amazon. You will be able to
   *   see it in the AWS console here:
   *   https://console.aws.amazon.com/sqs
   *
   * @param string $region
   *   Region where you want to create the Queue
   *
   * @throws Exception
   */
  public function __construct($name) {
    composer_manager_register_autoloader();

    // Set up the object.
    $this->setName($name);
    $this->setAwsKey();
    $this->setAwsSecret();
    $this->setAwsRegion();
    $this->setClient();

    // Drush queue-list and queue-run commands expect queue to exist without
    // explicitly calling createQueue method, so we go ahead and create it here.
    // (see drush_queue_run and drush_queue_list functions in queue.drush.inc).
    $this->createQueue();

    // Check if keys are available.
    if (!$this->getAwsKey() || !$this->getAwsSecret()) {
      throw new Exception("AWS Credentials not found");
    }
  }

  /**
   * Returns the queue object for a given name.
   *
   * @return object
   */
  static public function get($name) {
    return new AwsSqsQueue($name);
  }

  /**
   * Get the queue's options;
   *
   * @param type $name
   * @return type
   *
   * @todo What do these two settings do? Can we get rid of them or move them
   * to getters/setters?
   */
  static private function getOptions($name) {
    $options = variable_get('aws_sqs_' . $name, array());
    $defaults = variable_get('aws_sqs_default_queue', array());
    $options += $defaults;

    return $options;
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
   *   Can be of any type, mostly array or object. Will be stored
   *   serialized in the queue sytem. If an item retreived from the queue is
   *   being re-submitted to the queue (if is_object($item) && $item->data &&
   *   item->item_id), only $item->data will be stored.
   *
   * @return bool
   */
  public function createItem($data) {

    // Encapsulate our data
    $serialized_data = $this->serialize($data);

    // Check to see if someone is trying to save an item originally retrieved
    // from the queue. If so, this really should have been submitted as
    // $item->data, not $item. Reformat this so we don't save metadata or
    // confuse item_ids downstream.
    if (is_object($data) && property_exists($data, 'data') && property_exists($data, 'item_id')) {
      $text = t('Do not re-queue whole items retrieved from the SQS queue. This included metadata, like the item_id. Pass $item->data to createItem() as a parameter, rather than passing the entire $item. $item->data is being saved. The rest is being ignored.');
      $data = $data->data;
      watchdog('aws_sqs', $text, array(), WATCHDOG_ERROR);
    }

    // @todo Add a check here for message size? Log it?

    // Create a new message object
    $result = $this->client->sendMessage(array(
      'QueueUrl'    => $this->queueUrl,
      'MessageBody' => $serialized_data,
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

    $item = new stdClass();
    $message = $response->getPath('Messages/*');
    $item->data = $this->unserialize($message['Body']);
    $item->item_id = $message['ReceiptHandle'];

    if (!empty($item->item_id)) {
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
      throw new Exception("An item that needs to be deleted requires a handle ID");
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
  static private function isGuzzleServiceResourceModel($object) {
    return (is_object($object) && get_class($object) == 'Guzzle\Service\Resource\Model') ? TRUE : FALSE;
  }

  /**
   * PHP's native serialize() isn't very portable. This method enables people to
   * extend this class and support other serialization formats (so that
   * something other than PHP can potentially process the data in the queue, as
   * per discussion here: https://drupal.org/node/1956190).
   */
  protected static function serialize($data) {
    return serialize($data);
  }

  /**
   * PHP's native serialize() isn't very portable. This method enables people to
   * extend this class and support other serialization formats (so that
   * something other than PHP can potentially process the data in the queue, as
   * per discussion here: https://drupal.org/node/1956190).
   */
  protected static function unserialize($data) {
    return unserialize($data);
  }

  /*******************************************************
   * Getters and setters
   *******************************************************/

  public function getAwsKey() {
    if (!isset($this->awsKey)) $this->setAwsKey();
    return $this->awsKey;
  }

  private function setAwsKey() {
    $this->awsKey = variable_get('aws_sqs_aws_key', '');
  }

  public function getAwsSecret() {
    if (!isset($this->awsSecret)) $this->setAwsSecret();
    return $this->awsSecret;
  }

  private function setAwsSecret() {
    $this->awsSecret = variable_get('aws_sqs_aws_secret', '');
  }

  public function getAwsRegion() {
    if (!isset($this->awsRegion)) $this->setAwsRegion();
    return $this->awsRegion;
  }

  private function setAwsRegion() {
    $this->awsRegion = variable_get('aws_sqs_region', self::REGION_EU_WEST_1);
  }

  public function getClaimTimeout() {
    if (!isset($this->claimTimeout)) $this->setClaimTimeout();
    return $this->claimTimeout;
  }

  private function setClaimTimeout() {
    $this->claimTimeout = variable_get('aws_sqs_claimtimeout', 60);
  }

  public function getClient() {
    if (!isset($this->client)) $this->setClient();
    return $this->client;
  }

  private function setClient() {
    $client = SqsClient::factory(array(
      'key'    => $this->getAwsKey(),
      'secret' => $this->getAwsSecret(),
      'region' => $this->getAwsRegion(),
    ));
    $this->client = $client;
  }

  /**
   * $name is a required, user-defined param in __construct. It is set there.
   */
  public function getName() {
    return $this->name;
  }

  private function setName($name) {
    $prefix = (string) variable_get('aws_sqs_queue_name_prefix', '');
    $this->name = $prefix . $name;
  }

  public function getQueueUrl() {
    if (!isset($this->queueUrl)) {
      $text = t("You have to create a queue before you can get its URL. Use createQueue(). See AwsSqsQueue::createQueue().");
      watchdog('aws_sqs', $text, array(), WATCHDOG_WARNING);
      return FALSE;
    }
    else {
      return $this->queueUrl;
    }
  }

  /**
   * @see createQueue().
   */
  private function setQueueUrl($queueUrl) {
    $this->queueUrl = $queueUrl;
  }

  public function getWaitTimeSeconds() {
    if (!isset($this->waitTimeSeconds)) $this->setWaitTimeSeconds();
    return $this->waitTimeSeconds;
  }

  private function setWaitTimeSeconds() {
    $this->waitTimeSeconds = variable_get('aws_sqs_waittimeseconds', 1);
  }
}
