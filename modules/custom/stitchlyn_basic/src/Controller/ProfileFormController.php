<?php

namespace Drupal\stitchlyn_basic\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\UserInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class ProfileFormController extends ControllerBase {

  public function build(UserInterface $user, string $profile_type) {
    // Allow self or admins. Adjust as needed.
    $current = $this->currentUser();
    if ($current->id() != $user->id() && !$current->hasPermission('administer users')) {
      throw new AccessDeniedHttpException();
    }

    $storage = $this->entityTypeManager()->getStorage('profile');
    $profile = $storage->loadByUser($user, $profile_type, TRUE);

    if (!$profile) {
      $profile = $storage->create([
        'type' => $profile_type,
        'uid'  => $user->id(),
      ]);
    }

    return [
      '#title' => $this->t('Manage @type profile for @name', [
        '@type' => $profile_type,
        '@name' => $user->getDisplayName(),
      ]),
      'form' => $this->entityFormBuilder()->getForm($profile),
    ];
  }
}