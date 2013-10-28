<?php

/**
 * @file
 * Contains \Drupal\Core\Queue\QueueDatabaseFactory.
 */

namespace Drupal\aws_sqs\Queue;

/**
 * Defines the key/value store factory for the database backend.
 */
class QueueAwsSqsFactory {

  /**
   * Constructs a new queue object for a given name.
   *
   * @param string $name
   *   The name of the collection holding key and value pairs.
   * @param \Drupal\Core\Database\Connection $connection
   *   The connection to run against.
   *
   * @return \Drupal\Core\Queue\DatabaseQueue
   *   A key/value store implementation for the given $collection.
   */
  public function get($name) {
    return new AwsSqsQueue($name);
  }
}
