<?php
require_once __DIR__.'/config.php';

$token = $_GET['token'] ?? '';
$token = is_string($token) ? trim($token) : '';

if ($token === '') {
  header('Location: /login.php?verify_error=1'); exit;
}

$pdo = pdo();

// Find user by token (only if not yet verified)
$st = $pdo->prepare('SELECT id FROM users WHERE email_verify_token = ? LIMIT 1');
$st->execute([$token]);
$row = $st->fetch();

if (!$row) {
  header('Location: /login.php?verify_error=1'); exit;
}

// Mark verified
$upd = $pdo->prepare('UPDATE users SET email_verified_at = NOW(), email_verify_token = NULL WHERE id = ?');
$upd->execute([$row['id']]);

header('Location: /login.php?verified=1'); exit;
