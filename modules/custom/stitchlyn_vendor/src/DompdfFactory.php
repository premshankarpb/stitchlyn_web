<?php

namespace Drupal\stitchlyn_vendor;

use Dompdf\Dompdf;
use Dompdf\Options;

class DompdfFactory {
  public static function create() {
    $options = new Options();
    $options->set('isRemoteEnabled', TRUE); // allow images/css
    $options->set('defaultFont', 'Helvetica');
    return new Dompdf($options);
  }
}