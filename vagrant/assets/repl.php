#!/usr/bin/env php
<?php

//
// PHP REPL with initialized Magento environment
//
// Thanks to https://github.com/d11wtq/boris
//
// Drop this script in your $PATH and run it anywhere in a Magento directory tree to start the REPL
//

$mageFile = 'app/Mage.php';
for ($i = 0, $d = './'; ! file_exists($d.$mageFile) && ++$i < 25; $d .= '../');
if (! file_exists($d.$mageFile)) {
    echo "Unable to find $mageFile" . PHP_EOL;
    exit(2);
}

chdir($d); // Magento needs the pwd to be the magento base dir

// Search for boris in PHP's default include path and Magento's lib directory
set_include_path(get_include_path().':'.getcwd().'/lib');
require_once '/home/vagrant/.composer/vendor/d11wtq/boris/lib/autoload.php';

umask(0);
require_once $mageFile;
Mage::setIsDeveloperMode(true);
Mage::app('admin');

$boris = new \Boris\Boris('mage> ');
$boris->start();
