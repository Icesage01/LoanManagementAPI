<?php

$config = parse_ini_file('config.ini', true)['mysql'] ?? [];
return [
    'paths' => [
        'migrations' => 'migrations',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_database' => 'development',
        'development' => [
            'adapter' => 'mysql',
            'host' => $config['host'],
            'name' => $config['database'],
            'user' => $config['user'],
            'pass' => $config['pass'],
            'port' => $config['port'] ?? 3306,
            'charset' => 'utf8',
        ],
    ],
];

?>