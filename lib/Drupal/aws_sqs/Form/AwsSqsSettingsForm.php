<?php
namespace Drupal\aws_sqs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Cache\Cache;

class AwsSqsSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aws_sqs_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, array &$form_state) {
    global $conf;
    $variables = variable_initialize($conf);

    $config = $this->config('aws_sqs.settings');

    $form['credentials'] = array(
      '#type' => 'fieldset',
      '#title' => t('AWS credentials'),
      '#description' => t('Follow the instructions to set up your AWS credentials !here.',
        array('!here' => l('here', 'http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/AWSCredentials.html'))),
    );
    $form['credentials']['aws_sqs_aws_key'] = array(
      '#type' => 'textfield',
      '#title' => t('Access Key ID'),
      '#default_value' => $config->get('aws_sqs_aws_key'),
      '#required' => TRUE,
      '#description' => t('Amazon Web Services Key.'),
    );
    $form['credentials']['aws_sqs_aws_secret'] = array(
      '#type' => 'textfield',
      '#title' => t('Secret Access Key'),
      '#default_value' => $config->get('aws_sqs_aws_secret'),
      '#required' => TRUE,
      '#description' => t('Amazon Web Services Secret Key.'),
    );

    $seconds = range(0, 20);
    $t_args = array(
      '!more' => l('Read more about long polling here.', 'http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-long-polling.html#sqs-long-polling-query-api'),
    );
    $form['aws_sqs_waittimeseconds'] = array(
      '#type' => 'select',
      '#title' => t('Wait Time'),
      '#default_value' => $config->get('aws_sqs_waittimeseconds'),
      '#options' => $seconds,
      '#description' => t(
        "How long do you want to stay connected to AWS waiting for a response (seconds)? If a queue
        is empty, the connection will stay open for up to 20 seconds. If something arrives in the queue, it is
        returned as soon as it is received. AWS SQS charges per request. Long connections that stay open waiting for
        data to arrive are cheaper than polling SQS constantly to check for data. Long polling can also consume more
        resources on your server (think about the difference between running a task every minute that takes a second
        to complete versus running a task every minute that stays connected for up to 20 seconds every time waiting for
        jobs to come in). !more", $t_args),
    );
    $t_args = array(
      '!more' => l('Read more about visibility timeouts here.', 'http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/AboutVT.html'),
    );
    $form['aws_sqs_claimtimeout'] = array(
      '#type' => 'textfield',
      '#title' => t("Claim Timeout / Visibility Timeout"),
      '#default_value' => $config->get('aws_sqs_claimtimeout'),
      '#size' => 15,
      '#description' => t(
        "When an item is claimed from the queue by a worker, how long should the item be hidden from
        other workers (seconds)? Note: If the item is not deleted before the end of this time, it will become visible
        to other workers and available to be claimed again. Note also: 12 hours (43,200 seconds) is the maximum amount
        of time for which an item can be claimed. !more", $t_args),
    );

    $form['aws_sqs_region'] = array(
      '#type' => 'select',
      '#title' => t('AWS Queue Region'),
      '#default_value' => $config->get('aws_sqs_region'),
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
      '#description' => t('AWS Region where to store the Queue.'),
    );



    // in theory this should work
    // $default_queue = settings()->get('queue_default', 'queue.database');
    if (isset($variables['queue_default'])) {
      $default_queue = $variables['queue_default'];
    }
    else {
      $default_queue = 'queue.database';
    }

    $form['queue_default_class'] = array(
      '#title' => t('Default Queue'),
      '#markup' => t("The default queue class is <strong>!default_queue</strong>.", array('!default_queue' => $default_queue)),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    $config = $this->config('aws_sqs.settings');
    $config->set('aws_sqs_aws_key', $form_state['values']['aws_sqs_aws_key']);
    $config->set('aws_sqs_aws_secret', $form_state['values']['aws_sqs_aws_secret']);
    $config->set('aws_sqs_waittimeseconds', $form_state['values']['aws_sqs_waittimeseconds']);
    $config->set('aws_sqs_claimtimeout', $form_state['values']['aws_sqs_claimtimeout']);
    $config->set('aws_sqs_region', $form_state['values']['aws_sqs_region']);

    $config->save();
    parent::submitForm($form, $form_state);

    // @todo Decouple from form: http://drupal.org/node/2040135.
    Cache::invalidateTags(array('config' => 'aws_sqs.settings'));
  }
}
?>