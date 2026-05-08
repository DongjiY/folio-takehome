<?php

$runningDirectly = realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;
require_once __DIR__ . '/bootstrap.php';

if ($runningDirectly) {
    echo "\nRunning migration tests:\n";
}

test('migrations can apply and roll back one version', function () {
    require_once __DIR__ . '/../migrate.php';

    $version = '999_test_migration_runner';
    $upFile = __DIR__ . "/../migrations/{$version}.up.sql";
    $downFile = __DIR__ . "/../migrations/{$version}.down.sql";

    file_put_contents($upFile, 'CREATE TABLE migration_runner_check (id INTEGER PRIMARY KEY);');
    file_put_contents($downFile, 'DROP TABLE migration_runner_check;');

    try {
        $status = migration_status(db());
        assert_true(($status[$version] ?? null) === 'pending', 'expected test migration to start pending');

        $ran = migrate_up(db());
        assert_true(in_array($version, $ran, true), 'expected test migration to run');

        $exists = db()->query("
            SELECT name
            FROM sqlite_master
            WHERE type = 'table' AND name = 'migration_runner_check'
        ")->fetch();
        assert_true($exists !== false, 'expected migrated table to exist');

        $rolledBack = migrate_down(db());
        assert_true($rolledBack === [$version], 'expected test migration to roll back first');

        $exists = db()->query("
            SELECT name
            FROM sqlite_master
            WHERE type = 'table' AND name = 'migration_runner_check'
        ")->fetch();
        assert_false($exists !== false, 'expected migrated table to be removed');
    } finally {
        if (file_exists($upFile)) {
            unlink($upFile);
        }
        if (file_exists($downFile)) {
            unlink($downFile);
        }
    }
});

if ($runningDirectly) {
    finish_tests();
}
