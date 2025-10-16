<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\user\Entity\User;

class VendorInfoController extends ControllerBase {

  /**
   * Returns vendor profile details as JSON.
   */
  public function getVendorInfo() {
    $uid = \Drupal::request()->query->get('uid');
    if (!$uid) {
      return new JsonResponse(['error' => 'No vendor ID provided'], 400);
    }

    $user = User::load($uid);
    if (!$user) {
      return new JsonResponse(['error' => 'Vendor not found'], 404);
    }

    // Basic user info.
    $data = [
      'name' => $user->getDisplayName(),
      'email' => $user->getEmail(),
    ];

    // Load profile of type "vendor".
    if (\Drupal::moduleHandler()->moduleExists('profile')) {
      $profiles = \Drupal::entityTypeManager()
        ->getStorage('profile')
        ->loadByProperties([
          'type' => 'vendor',
          'uid' => $uid,
        ]);

      if (!empty($profiles)) {
        /** @var \Drupal\profile\Entity\Profile $profile */
        $profile = reset($profiles);

        // Safely extract fields.
        $data['vendor_name'] = $profile->hasField('field_vendor_name') ? $profile->get('field_vendor_name')->value : '';
        $data['contact_person'] = $profile->hasField('field_contact_person') ? $profile->get('field_contact_person')->value : '';
        $data['phone_number'] = $profile->hasField('field_phone_number') ? $profile->get('field_phone_number')->value : '';
        $data['gst_number'] = $profile->hasField('field_gst_number') ? $profile->get('field_gst_number')->value : '';
        $data['billing_address'] = $profile->hasField('field_billing_address') ? $profile->get('field_billing_address')->value : '';
        $data['remarks'] = $profile->hasField('field_remarks') ? $profile->get('field_remarks')->value : '';

        // Load Status term label.
        if ($profile->hasField('field_status') && !$profile->get('field_status')->isEmpty()) {
          $term = $profile->get('field_status')->entity;
          $data['status'] = $term ? $term->label() : '';
        }
      }
    }

    return new JsonResponse($data);
  }

}