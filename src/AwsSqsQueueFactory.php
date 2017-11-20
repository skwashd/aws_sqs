<?php

namespace Drupal\aws_sqs;

use Aws\Common\Credentials\Credentials;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Aws\Sqs\SqsClient;

/**
 * Aws SQS queue factory.
 */
class AwsSqsQueueFactory {

  /**
   * The config.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The logger.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * The SQS client.
   *
   * @var \Aws\Sqs\SqsClient
   */
  protected $client;

  /**
   * The list with initialized queues.
   *
   * @var \Drupal\aws_sqs\AwsSqsQueue[]
   */
  protected $initializedQueues;

  /**
   * Constructs a AwsSqsQueue object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory, LoggerChannelFactoryInterface $logger_factory) {
    $this->config = $config_factory->get('aws_sqs.settings');
    $this->logger = $logger_factory->get('aws_sqs');
  }

  /**
   * Gets SQS client.
   *
   * @return \Aws\Sqs\SqsClient
   *   The SQS client.
   */
  protected function getClient() {
    if (!isset($this->client)) {
      $credentials = new Credentials($this->config->get('aws_sqs_aws_key'), $this->config->get('aws_sqs_aws_secret'));
      $this->client = SqsClient::factory([
        'credentials' => $credentials,
        'region' => $this->config->get('aws_sqs_region', 'us-east-1'),
        'version' => $this->config->get('aws_sqs_version', 'latest'),
      ]);
    }
    return $this->client;
  }

  /**
   * Constructs a new queue object for a given name.
   *
   * @param string $name
   *   The name of the SQS queue to use.
   * @param bool $force_reinitialize
   *   (optional) Whether to force queue re-initialization.
   *
   * @return \Drupal\aws_sqs\AwsSqsQueue
   *   Queue object for a given name.
   */
  public function getQueue($name, $force_reinitialize = FALSE) {
    if (empty($this->initializedQueues[$name]) || $force_reinitialize) {
      $queue = new AwsSqsQueue($name, $this->getClient(), $this->logger);
      // Ensure that the queue exists.
      $queue->createQueue();
      $queue->setClaimTimeout($this->config->get('aws_sqs_claimtimeout'));
      $queue->setWaitTimeSeconds($this->config->get('aws_sqs_waittimeseconds'));
      $this->initializedQueues[$name] = $queue;
    }

    return $this->initializedQueues[$name];
  }

}
