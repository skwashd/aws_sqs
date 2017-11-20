AWS Simple Queue Service
========================

This module uses AWS SQS as a backend for Drupal's queue system.

Dependencies
-------------

- [Amazon SQS SDK](http://docs.aws.amazon.com/aws-sdk-php/v2/guide/installation.html).
  It is recommended to install it via composer.


Installation and setup
----------------------

1. Download and [install Amazon SQS SDK](http://docs.aws.amazon.com/aws-sdk-php/v2/guide/installation.html).
1. [Set up your Amazon account](http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/GettingSetUp.html) and sign up for SQS.
   - Create an Amazon account.
   - Creating a group, user, and granting that user access to SQS.
   - Get set up to submit requests to AWS SQS with PHP.
   
   You may also be interested in [documentation on AWS SDK for PHP](http://docs.aws.amazon.com/aws-sdk-php-2/guide/latest/index.html).
1. Enter your AWS credentials.
   - Go here: admin/config/system/aws-queue
   - Enter your credentials.

Usage
-----

```
/** @var \Drupal\aws_sqs\AwsSqsClientFactory $factory */
$factory = \Drupal::service('aws_sqs.client_factory');
$client = $factory->getQueue('drupal_queue');
$client->createItem(['foo' => 'bar']);
$data = $client->claimItem();
```
