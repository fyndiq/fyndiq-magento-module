<?php

set_time_limit(0);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Stockholm');
require __DIR__ . '/../vendor/autoload.php';

use Aquilax\Magento\PackageConfig;
use Aquilax\Magento\Generator;

$basePath = $argv[1];

$notes = <<<NOTES
Version 1.0.8
- Feature: Improved export performance
- Feature: Orders which cannot be imported will be skipped and reported
- Bugfix: hide Fydniq shipping method from checkout
- Bugfix: Better mulitostore support
- Bugfix: Settings are cleared on module disconnect
- Bugfix: Promotions are handled correctly
- Bugfix: Better image export
NOTES;

$pc = new PackageConfig();
$pc->setName('Fyndiq');
$pc->setVersion('1.0.8');
$pc->setStability('stable');
$pc->setLicense('Copyright');
$pc->setChannel('community');
$pc->setSummary('Import orders and export products to Fyndiq marketplace');
$pc->setDescription('Import orders and export products to Fyndiq marketplace');
$pc->setNotes($notes);
$pc->addAuthor('Håkan Nylén', 'confact', 'hakan.nylen@fyndiq.com');
$pc->addAuthor('Evgeniy Vasilev', 'aquilax', 'evgeniy.vasilev@fyndiq.com');
$pc->setTime(time());
$pc->addContent(
    PackageConfig::TARGET_COMMUNITY_MODULE_FILE,
    $basePath . '/app/code/community/Fyndiq',
    PackageConfig::TYPE_DIRECTORY
);
$pc->addContent(
    PackageConfig::TARGET_GLOBAL_CONFIGURATION,
    $basePath . '/app/etc/modules/Fyndiq_Fyndiq.xml',
    PackageConfig::TYPE_FILE
);
$generator = new Generator();
$xml = $generator->getPackageXML($pc);
print($xml);
