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

/**
 * Restricts /dashboard* pages for non-admin users, with whitelist support.
 */
class DashboardAccessSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  protected $currentUser;
  protected $messenger;
  protected $currentPath;
  protected $pathMatcher;

  /**
   * Paths that will NOT be blocked (safe allow list).
   *
   * Add any URL path exactly as in URL.
   *
   * Example:
   *   '/dashboard/profile'
   *   '/dashboard/vendor-list'
   *   '/dashboard/api/*'
   *
   * You can add wildcard using '*' as suffix.
   */
  protected $whitelist = [
    // '/dashboard',
    // '/dashboard/vendor-list',
    // '/dashboard/open-access/*',
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

    // Allow admins fully.
    if ($this->currentUser->hasRole('administrator')) {
      return;
    }

    $path = $this->currentPath->getPath();

    // Allow front page redirect loop prevention.
    $front_url = Url::fromRoute('<front>')->toString();

    // 🔥 Step 1: Check whitelist
    foreach ($this->whitelist as $allowed_path) {
      if ($this->pathMatcher->matchPath($path, $allowed_path)) {
        return;  // allowed
      }
    }

    // 🔥 Step 2: Block all /dashboard and /dashboard/* pages
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