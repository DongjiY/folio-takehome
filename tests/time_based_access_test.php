<?php

$runningDirectly = realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;
require_once __DIR__ . '/bootstrap.php';

if ($runningDirectly) {
    echo "\nRunning time-based access tests:\n";
}

test('seeded share link resolves to the seeded document', function () {
    $stmt = db()->prepare('
        SELECT d.title, s.available_at
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        LIMIT 1
    ');
    $stmt->execute();
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected the seeded share to resolve');
    assert_equals('Welcome Packet', $row['title'], 'unexpected title');
    assert_true($row['available_at'] !== null, 'expected seeded share to have availability');
    assert_true(share_is_available($row['available_at']), 'expected seeded share to be immediately available');
});

test('blank availability stores the current UTC time', function () {
    $now = new DateTimeImmutable('2026-05-08 12:34:56', new DateTimeZone('UTC'));
    $result = normalize_share_available_at('', 'America/Los_Angeles', $now);

    assert_equals('2026-05-08 12:34:56', $result['available_at']);
    assert_equals('America/Los_Angeles', $result['timezone']);
    assert_equals(null, $result['error']);
});

test('availability input is converted from browser timezone to UTC', function () {
    $result = normalize_share_available_at('2026-05-08T20:00', 'America/Los_Angeles');

    assert_equals('2026-05-09 03:00:00', $result['available_at']);
    assert_equals('America/Los_Angeles', $result['timezone']);
    assert_equals(null, $result['error']);
});

test('invalid timezone falls back to app timezone', function () {
    $result = normalize_share_available_at('2026-05-08T20:00', 'Not/A_Real_Zone');

    assert_equals('2026-05-09 01:00:00', $result['available_at']);
    assert_equals('America/Chicago', $result['timezone']);
    assert_equals(null, $result['error']);
});

test('invalid availability input returns a validation error', function () {
    $result = normalize_share_available_at('not-a-date', 'America/Los_Angeles');

    assert_equals(null, $result['available_at']);
    assert_equals('America/Los_Angeles', $result['timezone']);
    assert_equals('Enter a valid availability date and time.', $result['error']);
});

test('availability gate allows past times and blocks future times', function () {
    $now = new DateTimeImmutable('2026-05-08 12:00:00', new DateTimeZone('UTC'));

    assert_true(share_is_available('2026-05-08 11:59:59', $now), 'expected past time to be available');
    assert_true(share_is_available('2026-05-08 12:00:00', $now), 'expected exact time to be available');
    assert_false(share_is_available('2026-05-08 12:00:01', $now), 'expected future time to be blocked');
    assert_false(share_is_available(null, $now), 'expected null availability to be reserved and blocked');
});

test('share creation stores availability and audit details', function () {
    $doc = db()->query('SELECT id FROM documents LIMIT 1')->fetch();
    assert_true($doc !== false, 'expected a document');

    $share = create_share((int) $doc['id'], 'scheduled@example.com', '2026-05-09 03:00:00', 'America/Los_Angeles');

    $stmt = db()->prepare('SELECT available_at, shorthand_id FROM shares WHERE id = ?');
    $stmt->execute([$share['id']]);
    $row = $stmt->fetch();
    assert_true($row !== false, 'expected created share');
    assert_equals('2026-05-09 03:00:00', $row['available_at']);
    assert_equals($share['shorthand_id'], $row['shorthand_id']);

    $stmt = db()->prepare('SELECT details FROM audit_log WHERE entity_type = ? AND entity_id = ? ORDER BY id DESC LIMIT 1');
    $stmt->execute(['share', $share['id']]);
    $audit = $stmt->fetch();
    assert_true($audit !== false, 'expected audit row');

    $details = json_decode($audit['details'], true);
    assert_equals($share['shorthand_id'], $details['shorthand_id'] ?? null);
    assert_equals('2026-05-09 03:00:00', $details['available_at'] ?? null);
    assert_equals('America/Los_Angeles', $details['timezone'] ?? null);
});

if ($runningDirectly) {
    finish_tests();
}
