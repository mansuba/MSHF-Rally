<?php

return array(
    'driver'    => 'mysql',
    'host'      => getenv('MYSQL_HOST') ?: '127.0.0.1',
    'database'  => getenv('MYSQL_DATABASE') ?: 'store_test',
    'username'  => getenv('MYSQL_USER') ?: 'root',
    'password'  => getenv('MYSQL_PASSWORD') ?: 'root',
    'charset'   => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix'    => 'exp_',
);
