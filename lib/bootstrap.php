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

function random_token(int $bytes = 16): string {
    return bin2hex(random_bytes($bytes));
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

function create_share(int $document_id, string $recipient_email, string $available_at, string $timezone): array {
    $token = random_token();
    $stmt = db()->prepare('
        INSERT INTO shares (document_id, token, recipient_email, available_at)
        VALUES (?, ?, ?, ?)
    ');
    $stmt->execute([$document_id, $token, $recipient_email, $available_at]);
    $shareId = (int) db()->lastInsertId();

    audit_log('create', 'share', $shareId, [
        'document_id' => $document_id,
        'recipient_email' => $recipient_email,
        'available_at' => $available_at,
        'timezone' => $timezone,
    ]);

    return [
        'id' => $shareId,
        'token' => $token,
    ];
}

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
