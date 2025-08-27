<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/mailer.php';
require_once __DIR__.'/lib/UserManagement.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: /login.php'); exit; }
require_csrf();

$email = strtolower(trim($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  header('Location: /login.php?resent=1'); exit;
}

$pdo = pdo();
try {
  // Find user by email
  $st = $pdo->prepare('SELECT id, first_name, last_name, email_verified_at FROM users WHERE email=? LIMIT 1');
  $st->execute([$email]);
  $u = $st->fetch();

  if ($u && empty($u['email_verified_at'])) {
    // Generate a fresh token and save
    $token = bin2hex(random_bytes(32));
    UserManagement::setEmailVerifyToken((int)$u['id'], $token);

    // Build and send verification email
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $verifyUrl = $scheme.'://'.$host.'/verify_email.php?token='.urlencode($token);
    $name = ($u['first_name'] ?? '').' '.($u['last_name'] ?? '');
    $safeName = htmlspecialchars(trim($name), ENT_QUOTES, 'UTF-8');
    $safeUrl = htmlspecialchars($verifyUrl, ENT_QUOTES, 'UTF-8');
    $html = '<p>Hello '.($safeName ?: htmlspecialchars($email, ENT_QUOTES, 'UTF-8')).',</p>'
          . '<p>Click the link below to verify your email and activate your account:</p>'
          . '<p><a href="'.$safeUrl.'">'.$safeUrl.'</a></p>'
          . '<p>If you did not request this, you can ignore this email.</p>';

    @send_email($email, 'Confirm your '.APP_NAME.' account', $html, $safeName ?: $email);
  }
} catch (Throwable $e) {
  // swallow, do not enumerate
}

// Always redirect with generic notice
header('Location: /login.php?resent=1'); exit;
