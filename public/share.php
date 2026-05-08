<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$staff = current_staff();
$docId = (int) ($_GET['doc'] ?? 0);
$stmt = db()->prepare('SELECT * FROM documents WHERE id = ?');
$stmt->execute([$docId]);
$doc = $stmt->fetch();

if (!$doc) {
    http_response_code(404);
    render_header('Not found', $staff);
    ?>
    <div class="banner banner-error">Document not found.</div>
    <p><a href="/admin.php" class="back-link">← back to admin</a></p>
    <?php
    render_footer();
    exit;
}

$error = null;
$created_shorthand_id = null;
$created_available_message = null;
$email = '';
$available_at_input = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $available_at_input = trim($_POST['available_at'] ?? '');
    $timezone_input = trim($_POST['timezone'] ?? '');
    $availability = normalize_share_available_at($available_at_input, $timezone_input);

    if ($email === '') {
        $error = 'Recipient email is required.';
    } elseif ($availability['error'] !== null) {
        $error = $availability['error'];
    } else {
        $share = create_share((int) $doc['id'], $email, $availability['available_at'], $availability['timezone']);
        $created_shorthand_id = $share['shorthand_id'];
        $created_available_message = $available_at_input === ''
            ? 'Available immediately.'
            : 'Available at ' . str_replace('T', ' ', $available_at_input) . ' ' . $availability['timezone'] . '.';
        $email = '';
        $available_at_input = '';
    }
}

render_header('Share · ' . $doc['title'], $staff);
?>

<a href="/admin.php" class="back-link">← back to admin</a>

<h1 class="page-title">Share "<?= h($doc['title']) ?>"</h1>
<p class="page-subtitle">Generate a one-time link for a recipient.</p>

<?php if ($error): ?>
    <div class="banner banner-error"><?= h($error) ?></div>
<?php endif ?>

<?php if ($created_shorthand_id): ?>
    <div class="banner banner-success">
        Share link ready:
        <code>http://<?= h($_SERVER['HTTP_HOST']) ?>/view.php?s=<?= h($created_shorthand_id) ?></code>
        <br>
        <?= h($created_available_message) ?>
    </div>
<?php endif ?>

<section class="card">
    <h2 class="card-title">Create share link</h2>
    <form method="post">
        <div class="form-field">
            <label for="email">Recipient email</label>
            <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
        </div>
        <div class="form-field">
            <label for="available_at">Available at</label>
            <input type="datetime-local" id="available_at" name="available_at" value="<?= h($available_at_input) ?>">
            <p class="form-help">Leave blank to make this link available immediately.</p>
        </div>
        <input type="hidden" id="timezone" name="timezone" value="">
        <button type="submit" class="btn">Generate link</button>
    </form>
</section>

<script>
    const timezoneInput = document.getElementById('timezone');
    if (timezoneInput && typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
        timezoneInput.value = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
    }
</script>

<?php render_footer(); ?>
