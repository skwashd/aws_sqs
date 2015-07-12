# AWS Simple Queue Service (7.x-3.x)

This module uses AWS SQS as a backend for Drupal's queue system. You can use AWS SQS as a full replacement for your Drupal queues, or use it for certain queues.

## Dependencies

* [composer_manager](https://drupal.org/project/composer_manager) (module)
* [composer](https://drupal.org/project/composer) (use 8.x-1.x) (drush extension)


## Installation & Set-up

### Download and Install Dependencies

    drush dl aws_sqs composer_manager composer-8.x-1.x
    drush en aws_sqs
    drush composer-rebuild-file
    drush composer-manager update --no-dev --optimize-autoloader

### Set up your Amazon Account

* Create an Amazon account.
* Create a group, user, and granting that user access to SQS.
* For more information please read the [Amazon SQS "Getting Set Up Guide"](http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/GettingSetUp.html)

### Configure the Module

* Visit admin/config/system/aws-queue in your site
* Enter your creds
* Set AwsSqsQueue as your default queue. If you don't want SQS to be your backend by default, leave it set to SystemQueue and instantiate SQS queues manually with AwsSqsQueue::get().

### Test Drive with example_queue Module (Optional)

* Download and install the queue_example module from the [examples](https://drupal.org/project/examples):

        drush dl examples
        drush en queue_example

* Visit queue\_example/insert\_remove in your site
* Add some items to a queue.
* Visit AWS SQS Console and watch your queued items appear.
* Try removing, claiming, releasing, and deleting items from the queue.
* These changes should be reflected in the AWS Console.

## Resources

* [AWS SDK for PHP Documentation](https://docs.aws.amazon.com/aws-sdk-php/v3/guide/index.html)
