<?php

namespace Drupal\aws_sqs\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The settings form.
 */
class AwsSqsSettingsForm extends ConfigFormBase {

  /**
   * The config directory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * State interface.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $stateInterface;

  /**
   * Link generator.
   *
   * @var \Drupal\Core\Utility\LinkGenerator
   */
  protected $linkGenerator;

  /**
   * {@inheritdoc}
   */
  public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state_interface, LinkGenerator $link_generator) {
    $this->configFactory = $config_factory;
    $this->stateInterface = $state_interface;
    $this->linkGenerator = $link_generator;
    parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('state'),
      $container->get('link_generator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'aws_sqs_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['aws_sqs.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $default_queue = $this->stateInterface->get('queue_default');
    $config = $this->config('aws_sqs.settings');

    $aws_credentials_url = Url::fromUri('http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/AWSCredentials.html');
    $form['credentials'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('AWS credentials'),
      '#description' => $this->t('Follow the instructions to set up your AWS credentials @here.',
        [
          '@here' => $this->linkGenerator->generate($this->t('here'), $aws_credentials_url),
        ]),
    ];
    $form['credentials']['aws_sqs_aws_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Access Key ID'),
      '#default_value' => $config->get('aws_sqs_aws_key'),
      '#required' => TRUE,
      '#description' => $this->t('Amazon Web Services Key.'),
    ];
    $form['credentials']['aws_sqs_aws_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Secret Access Key'),
      '#default_value' => $config->get('aws_sqs_aws_secret'),
      '#required' => TRUE,
      '#description' => $this->t('Amazon Web Services Secret Key.'),
    ];
    $long_polling_url = Url::fromUri('http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-long-polling.html#sqs-long-polling-query-api');
    $seconds = range(0, 20);
    $t_args = [
      '@more' => $this->linkGenerator->generate($this->t('Read more about long polling here.'), $long_polling_url),
    ];
    $form['aws_sqs_waittimeseconds'] = [
      '#type' => 'select',
      '#title' => $this->t('Wait Time'),
      '#default_value' => $config->get('aws_sqs_waittimeseconds'),
      '#options' => $seconds,
      '#description' => $this->t(
        "How long do you want to stay connected to AWS waiting for a response (seconds)? If a queue
        is empty, the connection will stay open for up to 20 seconds. If something arrives in the queue, it is
        returned as soon as it is received. AWS SQS charges per request. Long connections that stay open waiting for
        data to arrive are cheaper than polling SQS constantly to check for data. Long polling can also consume more
        resources on your server (think about the difference between running a task every minute that takes a second
        to complete versus running a task every minute that stays connected for up to 20 seconds every time waiting for
        jobs to come in). @more", $t_args),
    ];
    $visibility_timeout_url = Url::fromUri('http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/AboutVT.html');
    $t_args = [
      '@more' => $this->linkGenerator->generate($this->t('Read more about visibility timeouts here.'), $visibility_timeout_url),
    ];
    $form['aws_sqs_claimtimeout'] = [
      '#type' => 'textfield',
      '#title' => $this->t("Claim Timeout / Visibility Timeout"),
      '#default_value' => $config->get('aws_sqs_claimtimeout'),
      '#size' => 15,
      '#description' => $this->t(
        "When an item is claimed from the queue by a worker, how long should the item be hidden from
        other workers (seconds)? Note: If the item is not deleted before the end of this time, it will become visible
        to other workers and available to be claimed again. Note also: 12 hours (43,200 seconds) is the maximum amount
        of time for which an item can be claimed. @more", $t_args),
    ];

    $form['aws_sqs_region'] = [
      '#type' => 'select',
      '#title' => $this->t('Region'),
      '#default_value' => $config->get('aws_sqs_region'),
      '#options' => [
        'us-east-1' => 'US East (N. Virginia)',
        'us-east-2' => 'US East (Ohio)',
        'us-west-1' => 'US West (N. California)',
        'us-west-2' => 'US West (Oregon)',
        'ca-central-1' => 'Canada (Central)',
        'eu-west-1' => 'EU (Ireland)',
        'eu-central-1' => 'EU (Frankfurt)',
        'eu-west-2' => 'EU (London)',
        'ap-southeast-1' => 'Asia Pacific (Singapore)',
        'ap-southeast-2' => 'Asia Pacific (Sydney)',
        'ap-northeast-2' => 'Asia Pacific (Seoul)',
        'ap-northeast-1' => 'Asia Pacific (Tokyo)',
        'ap-south-1' => 'Asia Pacific (Mumbai)',
        'sa-east-1' => 'South America (São Paulo)',
      ],
      '#required' => TRUE,
      '#description' => $this->t('AWS Region where to store the Queue.'),
    ];

    $form['version'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Version'),
      '#default_value' => $config->get('aws_sqs_version'),
      '#required' => TRUE,
      '#description' => $this->t("Amazon Web Services Version. 'latest' recommended"),
    ];

    if (!$default_queue) {
      $default_queue = 'queue.database';
    }

    $form['queue_default_class'] = [
      '#title' => $this->t('Default Queue'),
      '#markup' => $this->t("The default queue class is <strong>@default_queue</strong>.", ['@default_queue' => $default_queue]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('aws_sqs.settings');
    $config->set('aws_sqs_aws_key', $form_state->getValue('aws_sqs_aws_key'))
      ->save();
    $config->set('aws_sqs_aws_secret', $form_state->getValue('aws_sqs_aws_secret'))
      ->save();
    $config->set('aws_sqs_waittimeseconds', $form_state->getValue('aws_sqs_waittimeseconds'))
      ->save();
    $config->set('aws_sqs_claimtimeout', $form_state->getValue('aws_sqs_claimtimeout'))
      ->save();
    $config->set('aws_sqs_region', $form_state->getValue('aws_sqs_region'))
      ->save();

    $config->save();
    parent::submitForm($form, $form_state);
  }

}
