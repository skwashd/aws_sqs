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
  public function __construct($name, $region = self::REGION_EU_WEST_1) {
    composer_manager_register_autoloader();
    $this->name = $name;
    $this->awsKey = variable_get("aws_sqs_aws_key", "");
    $this->awsSecret = variable_get("aws_sqs_aws_secret", "");
    // Check if the keys are there
    if (empty($this->awsKey) || empty($this->awsSecret)) {
      throw new Exception("AWS Credentials not found");
    }
    // Initialize the objects
    $this->manager = new Manager($this->awsKey, $this->awsSecret, "us-east-1");
    $this->queue = new Queue();

    // This is important to support blocking calls to the queue system
    $waitTimeSeconds = variable_get("aws_sqs_poll_seconds", 0);
    $this->queue->setReceiveMessageWaitTimeSeconds($waitTimeSeconds);

    // And last but not least, give it a name
    $this->queue->setName($name);
  }

  /**
   * Send a "message to the AWS Queue.
   *
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
    return $this->getApproximateNumberOfMessages();
  }

  /**
   * Fetch an item
   * @param type $lease_time
   * @return boolean
   */
  public function claimItem($lease_time = 30) {
    // Create our stub item
    $item = new stdClass();

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
    $message->setBody($serialized_data);
    $message->setMessageId($item->item_id);
    if (!isset($item->item_handle)) {
      throw new Exception("An item that needs to be deleted requires a handle ID");
    }
    $message->setReceiptHandle($item->item_handle);

    // Do the real delete
    $this->manager->deleteMessage($message);
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
}