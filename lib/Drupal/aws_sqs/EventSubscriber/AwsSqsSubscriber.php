<?php
namespace Drupal\aws_sqs\EventSubscriber;

use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AwsSqsSubscriber implements EventSubscriberInterface {

  public function addAutoload(GetResponseEvent $event) {
    // If we did not have composer_manager, we'd have to do it ourselves here
  }

  /**
   * {@inheritdoc}
   */
  static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = array('addAutoload');
    return $events;
  }
}
?>