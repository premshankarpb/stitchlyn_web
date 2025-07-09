<?php

namespace Drupal\stitchlyn_basic\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;

class LoginRedirectSubscriber implements EventSubscriberInterface {

  protected $currentUser;
  protected $urlGenerator;

  public function __construct(AccountProxyInterface $current_user, $url_generator) {
    $this->currentUser = $current_user;
    $this->urlGenerator = $url_generator;
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onKernelRequest', 31],
    ];
  }

  public function onKernelRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Only act on /user/login redirect
    if ($request->getPathInfo() === '/user/login' || $request->getPathInfo() === '/user') {
      if ($this->currentUser->isAuthenticated()) {
        $roles = $this->currentUser->getRoles();
        if (in_array('administrator', $roles)) {
          $url = Url::fromRoute('stitchlyn_basic.dashboard')->toString();
          $response = new RedirectResponse($url);
          $event->setResponse($response);
        }
      }
    }
  }
}
