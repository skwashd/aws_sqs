<?php

/**
 * Definition of AwsSqsQueue.
 */

use AmazonSQS\Manager;
use AmazonSQS\Model\Queue;
use AmazonSQS\Model\Message;

/**
 * Amazon queue.
 */
class awsSqsQueue implements DrupalReliableQueueInterface {
  /**
   * The name of the queue this instance is working with.
   *
   * @var string
   */
  protected $name;
  protected $queue;
  protected $manager;
  protected $awsKey;
  protected $awsSecret;
  protected $claimTimeout = 0;
  protected $waitTimeSeconds = 0;

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
   *   Name of the queue, will also be this name in Amazon
   *
   * @param string $region
   *   Region where you want to create the Queue
   *
   * @throws Exception
   */
  public function __construct($name) {
    composer_manager_register_autoloader();
    $this->name = $name;
    $options = self::getOptions($name);

    // Check if the keys are there
    if (empty($options['key']) || empty($options['secret'])) {
      throw new Exception("AWS Credentials not found");
    }

    // Initialize the objects
    $this->manager = new Manager($options['key'], $options['secret'], $options['region']);
    $this->queue = $this->manager->getQueueByName($name);
  }

  /**
   * Get the queue's options;
   *
   * @param type $name
   * @return type
   */
  static function getOptions($name) {
    $options = variable_get('aws_sqs_' . $name, array());
    $defaults = variable_get('aws_sqs_default_queue', array()) + array(
      'key' => variable_get('aws_sqs_aws_key', ''),
      'secret' => variable_get('aws_sqs_aws_secret', ''),
      'region' => variable_get('aws_sqs_region', self::REGION_EU_WEST_1),
      'waitTimeSeconds' => variable_get('aws_sqs_waittimeseconds', 0),
    );
    $options += $defaults;

    return $options;
  }

  /**
   * Send a "message to the AWS Queue.
   *
   * Careful, you can only store data up to 64kb.
   * @param $data
   *   Can be of any type, mostly array or object. Will be stored
   *   serialized in the queue sytem.
   *
   * @return boolean
   *   True if it succesfully added the item, false if not.
   */
  public function createItem($data) {
    // Encapsulate our data
    $serialized_data = serialize($data);

    // Create a new message object
    $message = new Message();
    $message->setBody($serialized_data);

    // Returns true if it succeeded
    return $this->manager->sendMessage($this->queue, $message);
  }

  /**
   * Return the amount of items in the queue
   *
   * @return integer
   *   Approximate Number of messages in the aws queue
   */
  public function numberOfItems() {
    // Check if this operation succeeds, and if so, assign it to the queue
    // object from this class
    if($queue = $this->manager->loadQueueAttributes($this->queue)) {
      $this->queue = $queue;
    }
    return $this->queue->getApproximateNumberOfMessages();
  }

  /**
   * Fetch an item
   * @param type $lease_time
   * @return boolean
   */
  public function claimItem($lease_time = 30) {
    // Create our stub item
    $item = new stdClass();

    // This is important to support blocking calls to the queue system
    $waitTimeSeconds = $this->getWaitTimeSeconds();
    $claimTimeout = $this->getClaimTimeout();
    // if our given claimTimeout is smaller than the allowed waiting seconds
    // set the waitTimeSeconds to this value. This is to avoid a long call when
    // the worker that called claimItem only has a finite amount of time to wait
    // for an item
    // if $waitTimeSeconds is set to 0, it will never use the blocking
    // logic (which is intended)
    if ($claimTimeout < $waitTimeSeconds) {
      $this->queue->setReceiveMessageWaitTimeSeconds($claimTimeout);
    }
    else {
      $this->queue->setReceiveMessageWaitTimeSeconds($waitTimeSeconds);
    }

    // Fetch the queue item
    $message = $this->manager->receiveMessage($this->queue, $lease_time, true);
    // Return the value of the body if possible, if not, return false
    if ($item->data = unserialize($message->getBody())) {
      $item->item_id = $message->getMessageId();
      // Useful for when we need to delete the message. We cannot delete
      // messages using the item_id
      $item->item_handle = $message->getReceiptHandle();
      return $item;
    }

    return FALSE;
  }

  /**
   * We add it to the queue again as amazon does not support a release
   *
   * @param object $item
   */
  public function releaseItem($item) {
    // Encapsulate our data
    $serialized_data = serialize($data);

    // Create a new message object
    $message = new Message();
    $message->setBody($serialized_data);
    $message->setMessageId($item->item_id);

    // Returns true if it succeeded
    return $this->manager->sendMessage($this->queue, $message);
  }

  public function deleteItem($item) {
    // Create a new message object
    $message = new Message();
    $message->setQueue($this->queue);
    $message->setBody($serialized_data);
    $message->setMessageId($item->item_id);
    if (!isset($item->item_handle)) {
      throw new Exception("An item that needs to be deleted requires a handle ID");
    }
    $message->setReceiptHandle($item->item_handle);

    // Do the real delete
    return $this->manager->deleteMessage($message);
  }

  /**
   * Create the Amazon Queue
   *
   * @param type $name
   */
  public function createQueue() {
    $this->manager->createQueue($this->queue);
  }

  public function deleteQueue() {
    $this->manager->deleteQueue($this->queue);
  }

  /**
   * Gets the protected claimTimeout value
   */
  public function getClaimTimeout() {
    return $this->claimTimeout;
  }

  /**
   * Sets the protected claimTimeout value
   */
  public function setClaimTimeout($claimTimeout) {
    $this->claimTimeout = $claimTimeout;
  }

  /**
   * Gets the protected waitTimeSeconds value
   */
  public function getWaitTimeSeconds() {
    $options =  self::getOptions($this->name);
    return $options['waitTimeSeconds'];
  }
}
