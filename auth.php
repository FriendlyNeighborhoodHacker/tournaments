<?php
require_once __DIR__.'/config.php';

function current_user() {
  if (!empty($_SESSION['uid'])) {
    // If marked as public computer, enforce 30-minute inactivity timeout.
    $isPublic = !empty($_SESSION['public_computer']);
    if ($isPublic) {
      $last = $_SESSION['last_activity'] ?? 0;
      $now = time();
      $timeout = 1800; // 30 minutes
      if ($last && ($now - (int)$last) > $timeout) {
        // Expire session and require fresh login
        unset($_SESSION['uid'], $_SESSION['last_activity'], $_SESSION['public_computer']);
        if (session_status() === PHP_SESSION_ACTIVE) {
          session_regenerate_id(true);
        }
        return null;
      }
      // Refresh sliding inactivity window on any interaction for public sessions
      $_SESSION['last_activity'] = $now;
    }
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
