<?php

require __DIR__ . '/vendor/autoload.php';

// Завантажуємо змінні оточення так само, як в index.php
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

$dbPath = $_ENV['DB_FILENAME'] ?? 'db/database.sqlite';

return [
    'paths' => [
        'migrations' => '%%PHINX_CONFIG_DIR%%/db/migrations',
        'seeds' => '%%PHINX_CONFIG_DIR%%/db/seeds'
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment' => 'development',
        'development' => [
            'adapter' => 'sqlite',
            'name' => __DIR__ . '/' . $dbPath,
        ]
    ],
    'version_order' => 'creation'
];
