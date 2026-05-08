<?php

$runningDirectly = realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__;
require_once __DIR__ . '/bootstrap.php';

if ($runningDirectly) {
    echo "\nRunning document search tests:\n";
}

function create_document_for_search_test(string $title, string $body): int {
    $stmt = db()->prepare('
        INSERT INTO documents (title, body, created_by)
        VALUES (?, ?, 1)
    ');
    $stmt->execute([$title, $body]);

    return (int) db()->lastInsertId();
}

function document_search_titles(?string $query): array {
    return array_map(fn ($row) => $row['title'], find_documents_for_admin($query));
}

test('blank document search returns the seeded document', function () {
    $titles = document_search_titles('');

    assert_true(in_array('Welcome Packet', $titles, true), 'expected blank search to include seeded document');
});

test('document search matches titles case-insensitively', function () {
    $titles = document_search_titles('welcome');

    assert_true(in_array('Welcome Packet', $titles, true), 'expected lowercase query to match title');
});

test('document search matches substrings within titles', function () {
    $titles = document_search_titles('come Pack');

    assert_true(in_array('Welcome Packet', $titles, true), 'expected middle title substring to match');
});

test('document search does not match document body text', function () {
    create_document_for_search_test('Release Notes', 'This body mentions a private launch keyword.');

    $titles = document_search_titles('private launch keyword');

    assert_false(in_array('Release Notes', $titles, true), 'expected body-only text not to match');
});

test('document search treats LIKE wildcard characters literally', function () {
    create_document_for_search_test('100% Real Packet', 'Percent sign in the title.');
    create_document_for_search_test('Ordinary Packet', 'No wildcard characters here.');

    $percentTitles = document_search_titles('%');
    assert_true(in_array('100% Real Packet', $percentTitles, true), 'expected literal percent title to match');
    assert_false(in_array('Ordinary Packet', $percentTitles, true), 'expected percent query not to match every title');

    create_document_for_search_test('Internal_Memo', 'Underscore in the title.');
    $underscoreTitles = document_search_titles('_');
    assert_true(in_array('Internal_Memo', $underscoreTitles, true), 'expected literal underscore title to match');
    assert_false(in_array('Ordinary Packet', $underscoreTitles, true), 'expected underscore query not to match every title');
});

test('unmatched document search returns an empty list', function () {
    $titles = document_search_titles('definitely-not-a-document-title');

    assert_equals([], $titles);
});

if ($runningDirectly) {
    finish_tests();
}
