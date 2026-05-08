<?php

date_default_timezone_set('America/Chicago');

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $path = __DIR__ . '/../db.sqlite';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function current_staff(): array {
    $stmt = db()->prepare('SELECT * FROM staff WHERE id = 1');
    $stmt->execute();
    $row = $stmt->fetch();
    if (!$row) {
        throw new RuntimeException('No staff row #1 found. Did you run `php seed.php`?');
    }
    return $row;
}

function audit_log(string $action, string $entity_type, int $entity_id, array $details = []): void {
    $staff = current_staff();
    $stmt = db()->prepare('
        INSERT INTO audit_log (staff_id, action, entity_type, entity_id, details)
        VALUES (?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $staff['id'],
        $action,
        $entity_type,
        $entity_id,
        json_encode($details),
    ]);
}

function escape_like_pattern(string $value): string {
    return strtr($value, [
        '\\' => '\\\\',
        '%' => '\\%',
        '_' => '\\_',
    ]);
}

function find_documents_for_admin(?string $query = null): array {
    $query = trim($query ?? '');

    $sql = '
        SELECT d.*, s.name AS creator_name
        FROM documents d
        JOIN staff s ON s.id = d.created_by
    ';
    $params = [];

    if ($query !== '') {
        $sql .= "
            WHERE d.title LIKE ? ESCAPE '\\'
        ";
        $params[] = '%' . escape_like_pattern($query) . '%';
    }

    $sql .= '
        ORDER BY d.created_at DESC
    ';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}

function random_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
}

function random_shorthand_suffix(int $length = 4): string {
    $alphabet = '0123456789abcdefghijklmnopqrstuvwxyz';
    $suffix = '';

    for ($i = 0; $i < $length; $i++) {
        $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $suffix;
}

function share_shorthand_prefix(string $title): string {
    $words = preg_split('/[^a-z0-9]+/', strtolower($title), -1, PREG_SPLIT_NO_EMPTY);
    $prefixWords = array_slice($words ?: [], 0, 2);

    if ($prefixWords === []) {
        return 'document';
    }

    return implode('-', $prefixWords);
}

function generate_share_shorthand_id(string $title, ?callable $suffixGenerator = null): string {
    $suffixGenerator = $suffixGenerator ?? 'random_shorthand_suffix';
    return share_shorthand_prefix($title) . '-' . $suffixGenerator();
}

function utc_datetime_string(?DateTimeImmutable $time = null): string {
    $time = $time ?? new DateTimeImmutable('now');
    return $time->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
}

function timezone_from_input(string $timezone): DateTimeZone {
    $timezone = trim($timezone);
    if ($timezone === '') {
        return new DateTimeZone(date_default_timezone_get());
    }

    try {
        return new DateTimeZone($timezone);
    } catch (Throwable $e) {
        return new DateTimeZone(date_default_timezone_get());
    }
}

function normalize_share_available_at(string $available_at, string $timezone, ?DateTimeImmutable $now = null): array {
    $tz = timezone_from_input($timezone);
    $available_at = trim($available_at);

    if ($available_at === '') {
        return [
            'available_at' => utc_datetime_string($now),
            'timezone' => $tz->getName(),
            'error' => null,
        ];
    }

    foreach (['!Y-m-d\TH:i:s', '!Y-m-d\TH:i'] as $format) {
        $parsed = DateTimeImmutable::createFromFormat($format, $available_at, $tz);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $parsed instanceof DateTimeImmutable
            && ($errors === false || ((int) $errors['warning_count'] === 0 && (int) $errors['error_count'] === 0))
        ) {
            return [
                'available_at' => utc_datetime_string($parsed),
                'timezone' => $tz->getName(),
                'error' => null,
            ];
        }
    }

    return [
        'available_at' => null,
        'timezone' => $tz->getName(),
        'error' => 'Enter a valid availability date and time.',
    ];
}

function share_is_available(?string $available_at, ?DateTimeImmutable $now = null): bool {
    if ($available_at === null || trim($available_at) === '') {
        return false;
    }

    $available = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $available_at, new DateTimeZone('UTC'));
    $errors = DateTimeImmutable::getLastErrors();
    if (
        !$available instanceof DateTimeImmutable
        || ($errors !== false && ((int) $errors['warning_count'] > 0 || (int) $errors['error_count'] > 0))
    ) {
        return false;
    }

    $now = $now ?? new DateTimeImmutable('now');
    return $now->setTimezone(new DateTimeZone('UTC')) >= $available;
}

function share_email_matches(string $submitted_email, string $recipient_email): bool {
    return strtolower(trim($submitted_email)) === strtolower(trim($recipient_email));
}

function find_share_by_token(string $token): ?array {
    $stmt = db()->prepare('
        SELECT d.*, s.recipient_email, s.available_at
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE s.token = ?
    ');
    $stmt->execute([$token]);
    $share = $stmt->fetch();

    return $share ?: null;
}

function find_share_by_shorthand_id(string $shorthand_id): ?array {
    $stmt = db()->prepare('
        SELECT d.*, s.recipient_email, s.available_at, s.shorthand_id
        FROM shares s
        JOIN documents d ON d.id = s.document_id
        WHERE s.shorthand_id = ?
    ');
    $stmt->execute([trim($shorthand_id)]);
    $share = $stmt->fetch();

    return $share ?: null;
}

function find_share_by_shorthand_id_and_email(string $shorthand_id, string $email): ?array {
    $share = find_share_by_shorthand_id($shorthand_id);

    if ($share === null || !share_email_matches($email, $share['recipient_email'])) {
        return null;
    }

    return $share;
}

function create_share(int $document_id, string $recipient_email, string $available_at, string $timezone, ?callable $suffixGenerator = null): array {
    $docStmt = db()->prepare('SELECT title FROM documents WHERE id = ?');
    $docStmt->execute([$document_id]);
    $doc = $docStmt->fetch();
    if (!$doc) {
        throw new RuntimeException('Document not found.');
    }

    $token = random_token();
    $shareId = null;
    $shorthandId = null;

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $shorthandId = generate_share_shorthand_id($doc['title'], $suffixGenerator);
        $stmt = db()->prepare('
            INSERT INTO shares (document_id, token, shorthand_id, recipient_email, available_at)
            VALUES (?, ?, ?, ?, ?)
        ');

        try {
            $stmt->execute([$document_id, $token, $shorthandId, $recipient_email, $available_at]);
            $shareId = (int) db()->lastInsertId();
            break;
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'shares.shorthand_id') === false && strpos($e->getMessage(), 'shorthand_id') === false) {
                throw $e;
            }
        }
    }

    if ($shareId === null || $shorthandId === null) {
        throw new RuntimeException('Could not generate a unique readable share ID.');
    }

    audit_log('create', 'share', $shareId, [
        'document_id' => $document_id,
        'shorthand_id' => $shorthandId,
        'recipient_email' => $recipient_email,
        'available_at' => $available_at,
        'timezone' => $timezone,
    ]);

    return [
        'id' => $shareId,
        'token' => $token,
        'shorthand_id' => $shorthandId,
    ];
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
