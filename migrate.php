<?php

require_once __DIR__ . '/lib/bootstrap.php';

const MIGRATIONS_DIR = __DIR__ . '/migrations';

function ensure_schema_migrations(PDO $pdo): void {
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS schema_migrations (
            version TEXT PRIMARY KEY,
            applied_at TEXT NOT NULL DEFAULT (datetime(\'now\'))
        )
    ');
}

function migration_files(string $direction): array {
    $files = glob(MIGRATIONS_DIR . '/*.' . $direction . '.sql') ?: [];
    sort($files, SORT_STRING);

    $migrations = [];
    foreach ($files as $file) {
        $suffix = '.' . $direction . '.sql';
        $basename = basename($file);
        $version = substr($basename, 0, -strlen($suffix));
        $migrations[$version] = $file;
    }

    return $migrations;
}

function applied_migrations(PDO $pdo): array {
    ensure_schema_migrations($pdo);
    $rows = $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN);
    return $rows ?: [];
}

function apply_migration(PDO $pdo, string $version, string $file): void {
    $sql = trim(file_get_contents($file));
    if ($sql === '') {
        throw new RuntimeException("Migration {$version} is empty.");
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
        $stmt->execute([$version]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function rollback_migration(PDO $pdo, string $version, string $file): void {
    $sql = trim(file_get_contents($file));
    if ($sql === '') {
        throw new RuntimeException("Rollback migration {$version} is empty.");
    }

    $pdo->beginTransaction();
    try {
        $pdo->exec($sql);
        $stmt = $pdo->prepare('DELETE FROM schema_migrations WHERE version = ?');
        $stmt->execute([$version]);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function migrate_up(PDO $pdo): array {
    ensure_schema_migrations($pdo);

    $applied = array_flip(applied_migrations($pdo));
    $ran = [];

    foreach (migration_files('up') as $version => $file) {
        if (isset($applied[$version])) {
            continue;
        }

        apply_migration($pdo, $version, $file);
        $ran[] = $version;
    }

    return $ran;
}

function migrate_down(PDO $pdo, int $steps = 1): array {
    if ($steps < 1) {
        throw new InvalidArgumentException('Rollback steps must be at least 1.');
    }

    ensure_schema_migrations($pdo);
    $applied = applied_migrations($pdo);
    rsort($applied, SORT_STRING);

    $downFiles = migration_files('down');
    $rolledBack = [];

    foreach (array_slice($applied, 0, $steps) as $version) {
        if (!isset($downFiles[$version])) {
            throw new RuntimeException("Missing down migration for {$version}.");
        }

        rollback_migration($pdo, $version, $downFiles[$version]);
        $rolledBack[] = $version;
    }

    return $rolledBack;
}

function migration_status(PDO $pdo): array {
    $applied = array_flip(applied_migrations($pdo));
    $versions = array_unique(array_merge(
        array_keys(migration_files('up')),
        array_keys($applied)
    ));
    sort($versions, SORT_STRING);

    $status = [];
    foreach ($versions as $version) {
        $status[$version] = isset($applied[$version]) ? 'applied' : 'pending';
    }

    return $status;
}

function print_lines(array $versions, string $verb): void {
    if ($versions === []) {
        echo "No migrations to {$verb}.\n";
        return;
    }

    foreach ($versions as $version) {
        echo ucfirst($verb) . " {$version}.\n";
    }
}

function run_migrations_cli(array $argv): int {
    $command = $argv[1] ?? 'up';
    $pdo = db();

    try {
        if ($command === 'up') {
            print_lines(migrate_up($pdo), 'apply');
            return 0;
        }

        if ($command === 'down') {
            $steps = isset($argv[2]) ? (int) $argv[2] : 1;
            print_lines(migrate_down($pdo, $steps), 'rollback');
            return 0;
        }

        if ($command === 'status') {
            $status = migration_status($pdo);
            if ($status === []) {
                echo "No migrations found.\n";
                return 0;
            }

            foreach ($status as $version => $state) {
                echo "{$state} {$version}\n";
            }
            return 0;
        }

        fwrite(STDERR, "Usage: php migrate.php [up|down [steps]|status]\n");
        return 1;
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . "\n");
        return 1;
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    exit(run_migrations_cli($argv));
}
