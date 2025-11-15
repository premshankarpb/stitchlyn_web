<?php

namespace Drupal\stitchlyn_basic\EventSubscriber;

use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Path\PathMatcherInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DashboardAccessSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  protected $currentUser;
  protected $messenger;
  protected $currentPath;
  protected $pathMatcher;

  /**
   * Add any path or wildcard pattern here.
   */
  protected $whitelist = [
    '/dashboard/po/*/pdf',
    '/dashboard/quotation/*/pdf',
  ];

  public function __construct(
    AccountProxyInterface $current_user,
    MessengerInterface $messenger,
    CurrentPathStack $current_path,
    PathMatcherInterface $path_matcher
  ) {
    $this->currentUser = $current_user;
    $this->messenger = $messenger;
    $this->currentPath = $current_path;
    $this->pathMatcher = $path_matcher;
  }

  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['onKernelRequest', 0];
    return $events;
  }

  public function onKernelRequest(RequestEvent $event) {
    if (!$event->isMainRequest()) {
      return;
    }

    // Admins bypass everything.
    if ($this->currentUser->hasRole('administrator')) {
      return;
    }

    $path = $this->currentPath->getPath();
    $front_url = Url::fromRoute('<front>')->toString();

    // STEP 1 — Whitelist check
    foreach ($this->whitelist as $allowed) {
      if ($this->pathMatcher->matchPath($path, $allowed)) {
        return; // allowed
      }
    }

    // STEP 2 — Block all dashboard paths unless whitelisted
    if ($this->pathMatcher->matchPath($path, '/dashboard*')) {

      if ($path !== $front_url) {
        $this->messenger->addError(
          $this->t('You are not authorised to access the backend pages.')
        );
      }

      $event->setResponse(new RedirectResponse($front_url));
    }
  }
}