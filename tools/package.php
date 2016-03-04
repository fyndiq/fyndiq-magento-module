<?php

set_time_limit(0);
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Stockholm');
require __DIR__ . '/../vendor/autoload.php';

use Aquilax\Magento\PackageConfig;
use Aquilax\Magento\Generator;

$basePath = $argv[1];
$moduleVersion = $argv[2];
$changelog = $argv[3];

if (empty($basePath) || empty($moduleVersion) || empty($changelog)) {
    throw new Exception('Base path, module version and CHANGELOG must be provided as parameters');
    exit(1);
}

$handle = fopen($changelog, 'r');
$notes = '';
while (($line = fgets($handle, 4096)) !== false) {
    if (trim($line) == ''){
        break;
    }
    $notes .= $line;
}
fclose($handle);

$versionLabel = 'Version '. $moduleVersion;
if (strpos($notes, $versionLabel) === false) {
    throw new Exception('Version information not found at the top of the CHANGELOG. Looking for: ' . $versionLabel);
    exit(2);
}

$pc = new PackageConfig();
$pc->setName('Fyndiq');
$pc->setVersion($moduleVersion);
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
    $basePath . '/app/etc/modules',
    PackageConfig::TYPE_DIRECTORY
);
$pc->addContent(
    PackageConfig::TARGET_USER_INTERFACE,
    $basePath . '/app/design/adminhtml/default/default/layout/Fyndiq_Fyndiq.xml',
    PackageConfig::TYPE_FILE
);
$pc->addContent(
    PackageConfig::TARGET_THEME_SKIN,
    $basePath . '/skin/adminhtml/base',
    PackageConfig::TYPE_DIRECTORY
);

$generator = new Generator();
$xml = $generator->getPackageXML($pc);
print($xml);
