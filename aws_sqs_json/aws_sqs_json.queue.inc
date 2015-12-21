<?php

/**
 * @file
 * Amazon SQS queue handler that encodes data using JSON.
 */

/**
 * Class for adding/retrieving JSON encoded data to/from SQS Queues.
 */
class AwsSqsQueueJson extends AwsSqsQueue {


  /**
   * Returns the queue object for a given name.
   *
   * @return object
   */
  static public function get($name) {
    return new AwsSqsQueueJson($name);
  }

  /**
   * Serializes data using JSON before it is added to the queue.
   *
   * @param mixed $data
   *   The data to serialize as JSON. Only encodes public properties of objects.
   *   Don't use for complex objects as data will be lost.
   *
   * @return string
   *   The JSON encoded data.
   */
  protected function serialize($data) {
    return json_encode($data);
  }

  /**
   * Unserializes the JSON encoded data that has been retrieved from the queue.
   *
   * @param string $data
   *   The JSON data to unserialize.
   *
   * @return mixed
   *   The unserialized data, objects are always stdClass.
   */
  protected function unserialize($data) {
    return json_decode($data);
  }
}
