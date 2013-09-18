AWS Simple Queue Service (7.x-1.x)
===================================

This module uses AWS SQS as a backend for Drupal's queue system. You can use AWS SQS as a full replacement for your Drupal queues, or use it for certain queues.

Dependencies
-------------

  - composer_manager (module)
  - composer (drush extension)


Installation & Set-up
----------------------

  1. Download and install required projects.

        drush dl aws_sqs composer_manager composer
        drush en composer_manager
        drush en aws_sqs
        drush composer-rebuild-file
        drush composer-execute update

  2. Set up your Amazon account and sign up for SQS.

        Instructions here:
        http://docs.aws.amazon.com/AWSSimpleQueueService/latest/SQSGettingStartedGuide/GettingSetUp.html

        - Create an Amazon account.
        - Creating a group, user, and granting that user access to SQS.
        - Get set up to submit requests to AWS SQS with PHP.
        
        You may also be interested in documentation on AWS SDK for PHP:
        http://docs.aws.amazon.com/aws-sdk-php-2/guide/latest/index.html

  3. Enter your AWS credentials.

        - Go here: admin/config/system/aws-queue
        - Enter your creds
        - Set AwsSqsQueue as your default queue. If you don't want SQS to be your
          backend by default, leave it set to SystemQueue and instantiate SQS
          queues manually with AwsSqsQueue::get().

  4. (Optional) Test drive with example_queue module.

        drush dl examples
        drush en queue_example
        
        - Go here: queue_example/insert_remove
        - Add some items to a queue.
        - Toggle over to your AWS Console and watch your queued items appear.
        - Try removing, claiming, releasing, and deleting items from the queue.
          Watch how these changes are all reflected in the AWS Console.
