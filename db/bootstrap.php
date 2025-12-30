<?php

return function() {
    $dbPath = DIR_ROOT . '/' . ($_ENV['DB_FILENAME'] ?? 'db/database.sqlite3');
    $dbDir = dirname($dbPath);

    // Note: 0777 is used here for Docker container compatibility.
    // In a real production environment, use 0755 or strict ACLs.
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }

    db()->connect([
        'dbtype' => 'sqlite',
        'dbname' => $dbPath,
    ]);
};
