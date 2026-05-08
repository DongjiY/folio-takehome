<?php

require __DIR__ . '/../lib/bootstrap.php';
require __DIR__ . '/../lib/layout.php';

$token = $_GET['token'] ?? '';
$shorthandId = trim($_GET['s'] ?? '');
$email = trim($_POST['email'] ?? '');

function render_email_check(string $email, ?string $error = null): void {
    render_header('Verify email');
    ?>
    <h1 class="page-title">View shared document</h1>
    <p class="page-subtitle">Enter the recipient email address for this share.</p>

    <?php if ($error): ?>
        <div class="banner banner-error"><?= h($error) ?></div>
    <?php endif ?>

    <section class="card">
        <h2 class="card-title">Email check</h2>
        <form method="post">
            <div class="form-field">
                <label for="email">Recipient email</label>
                <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
            </div>
            <button type="submit" class="btn">View document</button>
        </form>
    </section>
    <?php
    render_footer();
}

if ($shorthandId !== '') {
    $doc = find_share_by_shorthand_id($shorthandId);

    if (!$doc) {
        http_response_code(404);
        render_header('Not found');
        ?>
        <div class="centered-message">
            <h1>Share link not found</h1>
            <p>The link you used is invalid or the email does not match this share.</p>
        </div>
        <?php
        render_footer();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $email === '') {
        render_email_check($email);
        exit;
    }

    $doc = find_share_by_shorthand_id_and_email($shorthandId, $email);
    if ($doc === null) {
        render_email_check($email, 'That email does not match this share.');
        exit;
    }
} else {
    $doc = find_share_by_token($token);
}

if (!$doc) {
    http_response_code(404);
    render_header('Not found');
    ?>
    <div class="centered-message">
        <h1>Share link not found</h1>
        <p>The link you used is invalid or has been removed.</p>
    </div>
    <?php
    render_footer();
    exit;
}

if (!share_is_available($doc['available_at'])) {
    render_header('Not yet available');
    ?>
    <div class="centered-message">
        <h1>Document not yet available</h1>
        <p>This document will be available at the scheduled time.</p>
    </div>
    <?php
    render_footer();
    exit;
}

render_header($doc['title']);
?>

<h1 class="page-title"><?= h($doc['title']) ?></h1>
<p class="meta">Shared with <?= h($doc['recipient_email']) ?></p>

<pre class="doc-body"><?= h($doc['body']) ?></pre>

<?php render_footer(); ?>
