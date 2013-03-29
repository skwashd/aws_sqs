<?php

function aws_sqs_settings_form() {
   $form['aws_sqs_aws_key'] = array(
    '#type' => 'textfield',
    '#title' => t('Amazon Web Services Key'),
    '#default_value' => variable_get('aws_sqs_aws_key', ''),
    '#required' => TRUE,
    '#description' => t('Amazon Web Services Key. Found in the AWS Security Credentials.'),
  );
  $form['aws_sqs_aws_secret'] = array(
    '#type' => 'textfield',
    '#title' => t('Amazon Web Services Secret Key'),
    '#default_value' => variable_get('aws_sqs_aws_secret', ''),
    '#required' => TRUE,
    '#description' => t('Amazon Web Services Secret Key. Found in the AWS Security Credentials.'),
  );

  $seconds = range(0, 20);
  $form['aws_sqs_waittimeseconds'] = array(
    '#type' => 'select',
    '#title' => t('How long can the fetchItem call wait before it times out? (seconds)'),
    '#default_value' => variable_get('aws_sqs_waittimeseconds', ''),
    '#options' => $seconds,
    '#description' => t('This is important if you want to reduce the amount of polls to the AWS service. It is comparable with a blocking select call.'),
  );

  $form['aws_sqs_enable'] = array(
    '#type' => 'checkbox',
    '#title' => t('Send all Queues to the amazon queue system'),
    '#default_value' => variable_get('aws_sqs_enable', ''),
    '#description' => t('Switches the default queue class to the aws queue class.'),
  );
  $form['#submit'][] = 'aws_sqs_settings_form_submit';
  return system_settings_form($form);
}

function aws_sqs_settings_form_submit($form, &$form_state) {
  if (!empty($form_state['values']['aws_sqs_enable'])) {
    variable_set('queue_default_class', 'awsSqsQueue');
  }
}