<?php

namespace Drupal\stitchlyn_vendor\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\user\Entity\User;
use Drupal\profile\Entity\Profile;
use Drupal\node\Entity\Node;

/**
 * Returns vendor and item info for AJAX.
 */
class VendorInfoController {

  /**
   * Vendor info by UID.
   */
  public function vendorInfo() {
    $uid = \Drupal::request()->query->get('uid');
    if (empty($uid) || !is_numeric($uid)) {
      return new JsonResponse(['error' => 'Invalid UID'], 400);
    }

    $account = User::load($uid);
    if (!$account) {
      return new JsonResponse(['error' => 'User not found'], 404);
    }

    // ✅ Load vendor profile.
    $profile_storage = \Drupal::entityTypeManager()->getStorage('profile');
    $profiles = $profile_storage->loadByProperties([
      'uid' => $uid,
      'type' => 'vendor',
    ]);
    $profile = reset($profiles);

    if (!$profile) {
      return new JsonResponse(['error' => 'Vendor profile not found'], 404);
    }

    // ✅ Get vendor profile fields safely.
    $data = [
      'vendor_name' => $profile->get('field_vendor_name')->value ?? '',
      'contact_person' => $profile->get('field_contact_person')->value ?? '',
      'phone_number' => ($profile->hasField('field_phone_number') && !$profile->get('field_phone_number')->isEmpty()) ? $profile->get('field_phone_number')->getString():'',
      'gst_number' => $profile->get('field_gst_number')->value ?? '',
      'billing_address' => $profile->get('field_billing_address')->value ?? '',
    ];

    return new JsonResponse($data);
  }

  /**
   * Item info by NID.
   */
  public function itemInfo() {
    $nid = \Drupal::request()->query->get('nid');
    if (empty($nid) || !is_numeric($nid)) {
      return new JsonResponse(['error' => 'Invalid NID'], 400);
    }

    $node = Node::load($nid);
    if (!$node || $node->bundle() !== 'inventory_item') {
      return new JsonResponse(['error' => 'Invalid Item'], 404);
    }

    $rate = $node->get('field_cost_price')->value ?? 0;
    return new JsonResponse(['rate' => (float) $rate]);
  }

}