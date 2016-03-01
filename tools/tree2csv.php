<?php

$fileName = $argv[1];
$file = file_get_contents($fileName);
$tree = json_decode($file, true);

echo "SET NAMES utf8;" . PHP_EOL;
echo "TRUNCATE fyndiq_fyndiq_category ;" . PHP_EOL;

foreach($tree['categories'] as $cat) {
	$sql = sprintf(
		'INSERT INTO fyndiq_fyndiq_category (id, name_se, name_de) VALUES (%s, "%s", "%s");',
		$cat['id'],
		mysql_escape_string($cat['path']['sv']),
		mysql_escape_string($cat['path']['de'])
	);
	echo $sql . PHP_EOL;
}
