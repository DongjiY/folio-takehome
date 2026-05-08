<?php

$runningDirectly = realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;
require_once __DIR__ . '/bootstrap.php';

if ($runningDirectly) {
    echo "\nRunning readable share ID tests:\n";
}

test('share shorthand prefix uses up to two lowercase title words', function () {
    assert_equals('welcome-packet', share_shorthand_prefix('Welcome Packet'));
    assert_equals('onboarding-packet', share_shorthand_prefix('  Onboarding: Packet 2026  '));
    assert_equals('folio', share_shorthand_prefix('FOLIO'));
    assert_equals('document', share_shorthand_prefix('!!!'));
});

test('generated share shorthand appends a four character suffix', function () {
    $shorthand = generate_share_shorthand_id('Welcome Packet', fn () => '7qx4');

    assert_equals('welcome-packet-7qx4', $shorthand);
});

test('two shares for the same document share a prefix and get different suffixes', function () {
    $doc = db()->query('SELECT id FROM documents LIMIT 1')->fetch();
    assert_true($doc !== false, 'expected a document');

    $suffixes = ['7qx4', '9ab2'];
    $shareA = create_share((int) $doc['id'], 'first@example.com', utc_datetime_string(), 'America/Chicago', function () use (&$suffixes) {
        return array_shift($suffixes);
    });
    $shareB = create_share((int) $doc['id'], 'second@example.com', utc_datetime_string(), 'America/Chicago', function () use (&$suffixes) {
        return array_shift($suffixes);
    });

    assert_equals('welcome-packet-7qx4', $shareA['shorthand_id']);
    assert_equals('welcome-packet-9ab2', $shareB['shorthand_id']);
});

test('share creation retries shorthand collisions', function () {
    $doc = db()->query('SELECT id FROM documents LIMIT 1')->fetch();
    assert_true($doc !== false, 'expected a document');

    $stmt = db()->prepare('
        INSERT INTO shares (document_id, token, shorthand_id, recipient_email, available_at)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([(int) $doc['id'], random_token(), 'welcome-packet-abcd', 'existing@example.com', utc_datetime_string()]);

    $suffixes = ['abcd', 'wxyz'];
    $share = create_share((int) $doc['id'], 'retry@example.com', utc_datetime_string(), 'America/Chicago', function () use (&$suffixes) {
        return array_shift($suffixes);
    });

    assert_equals('welcome-packet-wxyz', $share['shorthand_id']);
});

test('seeded shares have readable IDs with a unique index', function () {
    $row = db()->query('SELECT shorthand_id FROM shares ORDER BY id LIMIT 1')->fetch();
    assert_true($row !== false, 'expected seeded share');
    assert_true((bool) preg_match('/^welcome-packet-[a-z0-9]{4}$/', $row['shorthand_id']), 'expected readable share ID');

    $index = db()->query("
        SELECT name
        FROM sqlite_master
        WHERE type = 'index' AND name = 'idx_shares_shorthand_id'
    ")->fetch();
    assert_true($index !== false, 'expected shorthand unique index');
});

test('readable share email checks normalize case and whitespace', function () {
    assert_true(share_email_matches(' RECIPIENT@example.com ', 'recipient@example.com'));
    assert_false(share_email_matches('wrong@example.com', 'recipient@example.com'));
});

test('readable share lookup requires the matching recipient email', function () {
    $share = db()->query('SELECT shorthand_id FROM shares ORDER BY id LIMIT 1')->fetch();
    assert_true($share !== false, 'expected share');

    $matched = find_share_by_shorthand_id_and_email($share['shorthand_id'], ' RECIPIENT@example.com ');
    assert_true($matched !== null, 'expected matching email to resolve share');
    assert_equals('Welcome Packet', $matched['title']);

    $wrongEmail = find_share_by_shorthand_id_and_email($share['shorthand_id'], 'wrong@example.com');
    assert_equals(null, $wrongEmail);

    $unknown = find_share_by_shorthand_id_and_email('missing-share-0000', 'recipient@example.com');
    assert_equals(null, $unknown);
});

test('readable share lookup preserves availability gating', function () {
    $doc = db()->query('SELECT id FROM documents LIMIT 1')->fetch();
    assert_true($doc !== false, 'expected a document');

    $share = create_share((int) $doc['id'], 'future@example.com', '2026-05-08 12:00:01', 'America/Chicago', fn () => 'futr');
    $matched = find_share_by_shorthand_id_and_email($share['shorthand_id'], 'future@example.com');

    assert_true($matched !== null, 'expected matching future share');
    assert_false(share_is_available($matched['available_at'], new DateTimeImmutable('2026-05-08 12:00:00', new DateTimeZone('UTC'))));
});

test('legacy token lookup still resolves shares', function () {
    $share = db()->query('SELECT token FROM shares ORDER BY id LIMIT 1')->fetch();
    assert_true($share !== false, 'expected share');

    $matched = find_share_by_token($share['token']);
    assert_true($matched !== null, 'expected token share to resolve');
    assert_equals('Welcome Packet', $matched['title']);
});

if ($runningDirectly) {
    finish_tests();
}
