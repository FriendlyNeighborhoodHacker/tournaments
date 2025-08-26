<?php
require_once __DIR__.'/config.php';

function current_user() {
  if (!empty($_SESSION['uid'])) {
    $stmt = pdo()->prepare("SELECT * FROM users WHERE id=?");
    $stmt->execute([$_SESSION['uid']]);
    return $stmt->fetch();
  }
  return null;
}
function require_login() {
  if (!current_user()) { header('Location: /login.php'); exit; }
}
function require_admin() {
  $u = current_user();
  if (!$u || !$u['is_admin']) { http_response_code(403); exit('Admins only'); }
}
function require_coach_or_admin() {
  $u = current_user();
  if (!$u || !($u['is_coach'] || $u['is_admin'])) { http_response_code(403); exit('Coaches/Admins only'); }
}
