<?php
namespace Drupal\awssqs\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Cache\Cache;

class AwsSqsSettingsForm implements ConfigFormBase {

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
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, array &$form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, array &$form_state) {
    parent::submitForm($form, $form_state);
  }
}
?>