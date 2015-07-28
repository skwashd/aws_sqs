<?php
/**
 * @file
 * Hooks provided by aws_sqs module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Informs the queue classes that the modules expose.
 */
function hook_aws_sqs_queue_class_info() {
  $classes = array(
    'QueueClass',
    'OtherQueueClass',
  );

  return $classes;
}

/**
 * @} End of "addtogroup hooks".
 */
