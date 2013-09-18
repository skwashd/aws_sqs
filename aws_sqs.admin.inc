<?php
/**
 * @file
 * AWS SQS module admin functions.
 */

/**
 * Menu callback for admin form.
 */
function aws_sqs_settings_form() {
  $form['credentials'] = array(
    '#type' => 'fieldset',
    '#title' => t('AWS credentials'),
    '#description' => t('Follow the instructions to set up your AWS credentials !here.',
      array('!here' => l('here', 'http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/AWSCredentials.html'))),
  );
  $form['credentials']['aws_sqs_aws_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Access Key ID'),
    '#default_value' => variable_get('aws_sqs_aws_key', ''),
    '#required' => TRUE,
    '#description' => t('Amazon Web Services Key.'),
  );
  $form['credentials']['aws_sqs_aws_secret'] = array(
    '#type' => 'textfield',
    '#title' => t('Secret Access Key'),
    '#default_value' => variable_get('aws_sqs_aws_secret', ''),
    '#required' => TRUE,
    '#description' => t('Amazon Web Services Secret Key.'),
  );

  $seconds = range(0, 20);
  $form['aws_sqs_waittimeseconds'] = array(
    '#type' => 'select',
    '#title' => t('Wait Time'),
    '#default_value' => variable_get('aws_sqs_waittimeseconds', 1),
    '#options' => $seconds,
    '#description' => t('How long do you want to stay connected to AWS waiting for a response (seconds)? If a queue is empty, the connection will stay open for up to 20 seconds. If something arrives in the queue, it is returned as soon as it is received. AWS SQS charges per request. Long connections that stay open waiting for data to arrive are cheaper than polling SQS constantly to check for data. Long polling can also consume more resources on your server (think about the difference between running a task every minute that takes a second to complete versus running a task every minute that stays connected for up to 20 seconds every time waiting for jobs to come in). !more', array('!more' => l('Read more about long polling here.', 'http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-long-polling.html#sqs-long-polling-query-api'))),
  );
  $form['aws_sqs_claimtimeout'] = array(
    '#type' => 'textfield',
    '#title' => t("Claim Timeout / Visibility Timeout"),
    '#default_value' => variable_get('aws_sqs_waittimeseconds', 60),
    '#size' => 15,
    '#description' => t("When an item is claimed from the queue by a worker, how long should the item be hidden from other workers (seconds)? Note: If the item is not deleted before the end of this time, it will become visible to other workers and available to be claimed again. Note also: 12 hours (43,200 seconds) is the maximum amount of time for which an item can be claimed. !more", array('!more' => l('Read more about visibility timeouts here.', 'http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/AboutVT.html'))),
  );

  $form['aws_sqs_region'] = array(
    '#type' => 'select',
    '#title' => t('Secret Access Key'),
    '#default_value' => variable_get('aws_sqs_aws_secret', ''),
    '#options' => array(
      'us-east-1' => 'us-east-1',
      'us-west-1' => 'us-west-1',
      'us-west-2' => 'us-west-2',
      'eu-west-1' => 'eu-west-1',
      'ap-southeast-1' => 'ap-southeast-1',
      'ap-northeast-1' => 'ap-northeast-1',
      'sa-east-1' => 'sa-east-1',
    ),
    '#required' => TRUE,
    '#description' => t('Amazon Web Services Secret Key.'),
  );

  $form['queue_default_class'] = array(
    '#type' => 'select',
    '#title' => t('Default Queue'),
    '#default_value' => variable_get('queue_default_class', 'SystemQueue'),
    '#description' => t('Set the default queue class. If you select AwsSqsQueue here, AWS SQS will be used anytime a queue is instantiated via DrupalQueue:get($name).'),
    '#options' => array(
      'AwsSqsQueue' => 'AwsSqsQueue',
      'SystemQueue' => 'SystemQueue',
      'MemoryQueue' => 'MemoryQueue',
    ),
  );
  return system_settings_form($form);
}
