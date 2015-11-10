#!/usr/bin/env php
<?php

error_reporting(-1);

use Illuminate\Container\Container;
use Illuminate\Database\Connectors\ConnectionFactory;

require __DIR__.'/../autoload.php';
$config = require __DIR__.'/config.php';
$db_name = $config['database'];
$initial_config = $config;
$initial_config['database'] = null;

$factory = new ConnectionFactory(new Container);
$db = $factory->make($initial_config);

// create test database
echo "Creating database '$db_name'\n";
$db->getPdo()->query("DROP DATABASE IF EXISTS `$db_name`");
$db->getPdo()->query("CREATE DATABASE `$db_name`");

// reconnect
$db = $factory->make($config);

// load db schema
foreach (array('ee.sql', '../config/schema.sql') as $filename) {
    echo "Loading schema '$filename'\n";
    $schema = file_get_contents(__DIR__.'/'.$filename);
    foreach (array_filter(array_map('trim', explode(';', $schema))) as $sql) {
        $db->getPdo()->query($sql);
    }
}

echo "Done!\n";
