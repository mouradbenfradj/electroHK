<?php
require_once __DIR__ . '/../core/vendor/autoload.php';

use Thelia\Core\Thelia;
use Thelia\Model\ConfigQuery;

$thelia = new Thelia('prod', false);
$thelia->boot();

$mode = ConfigQuery::read('original_image_delivery_mode', 'symlink');
echo "Current mode: " . $mode . "\n";

if ($mode !== 'copy') {
    echo "Updating to 'copy'...\n";
    $config = ConfigQuery::create()->filterByName('original_image_delivery_mode')->findOne();
    if (!$config) {
        $config = new \Thelia\Model\Config();
        $config->setName('original_image_delivery_mode');
    }
    $config->setValue('copy');
    $config->save();
    echo "Done.\n";
} else {
    echo "Already set to 'copy'.\n";
}
