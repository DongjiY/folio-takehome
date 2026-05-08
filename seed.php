<?php

require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/migrate.php';

$dbPath = __DIR__ . '/db.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = db();
$pdo->exec(file_get_contents(__DIR__ . '/schema.sql'));
migrate_up($pdo);

$pdo->exec("
    INSERT INTO staff (email, name) VALUES
        ('freddy@folio.example', 'Freddy Folio')
");

$stmt = $pdo->prepare('
    INSERT INTO documents (title, body, created_by)
    VALUES (?, ?, 1)
');
$stmt->execute([
    'Welcome Packet',
    "Welcome to Folio!\n\nThis is the body of your welcome packet.",
]);
$docId = (int) $pdo->lastInsertId();

$share = create_share($docId, 'recipient@example.com', utc_datetime_string(), date_default_timezone_get());

echo "Seeded db.sqlite.\n";
echo "Admin:        http://localhost:8000/admin.php\n";
echo "Sample share: http://localhost:8000/view.php?s={$share['shorthand_id']}\n";
echo "Legacy share: http://localhost:8000/view.php?token={$share['token']}\n";
