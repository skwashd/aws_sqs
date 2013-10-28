<?php
namespace Drupal\aws_sqs\EventSubscriber;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AwsSqsSubscriber implements EventSubscriberInterface {

  public function addAutoload(GetResponseEvent $event) {
    require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/vendor/autoload.php';
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