<?php
namespace Drupal\aws_sqs\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\LinkGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

class AwsSqsSettingsForm extends ConfigFormBase {

    /**
     *
     * @var Drupal\Core\Config\ConfigFactoryInterface
     */
    protected $config_factory;

    /**
     * @var Drupal\Core\State\StateInterface
     */
    protected $state_interface;

    /**
     * @var Drupal\Core\Utility\LinkGenerator
     */
    protected $link_generator;

    public function __construct(ConfigFactoryInterface $config_factory, StateInterface $state_interface, LinkGenerator $link_generator) {
        $this->config_factory = $config_factory;
        $this->state_interface = $state_interface;
        $this->link_generator = $link_generator;
        parent::__construct($config_factory);
    }

    /**
     * Factory method for dependency injection container.
     *
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @return static
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
        $default_queue = $this->state_interface->get('queue_default');
        $config = $this->config('aws_sqs.settings');

        $aws_credentials_url = Url::fromUri('http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/AWSCredentials.html');
        $form['credentials'] = array(
            '#type' => 'fieldset',
            '#title' => t('AWS credentials'),
            '#description' => t('Follow the instructions to set up your AWS credentials !here.',
                array('!here' => $this->link_generator->generate(t('here'), $aws_credentials_url),
                )));
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
        $long_polling_url = Url::fromUri('http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/sqs-long-polling.html#sqs-long-polling-query-api');
        $seconds = range(0, 20);
        $t_args = array(
            '!more' => $this->link_generator->generate(t('Read more about long polling here.'), $long_polling_url),
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
        $visibility_timeout_url = Url::fromUri('http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSDeveloperGuide/AboutVT.html');
        $t_args = array(
            '!more' => $this->link_generator->generate(t('Read more about visibility timeouts here.'), $visibility_timeout_url)
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

        $form['version'] = array(
            '#type' => 'textfield',
            '#title' => t('Version'),
            '#default_value' => $config->get('aws_sqs_version'),
            '#required' => TRUE,
            '#description' => t("Amazon Web Services Version. 'latest' recommended"),
        );

        if(!$default_queue) {
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
