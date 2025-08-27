<?php
require_once __DIR__.'/auth.php';
require_login();
require_csrf();

$pdo = pdo();
$u = current_user();
$action = $_POST['action'] ?? '';

if ($action === 'self_set') {
  $tournament_id = (int)($_POST['tournament_id'] ?? 0);
  $val = $_POST['has_ride'] ?? '';
  $has_ride = null;
  if ($val === '1') $has_ride = 1;
  elseif ($val === '0') $has_ride = 0;

  // Must be a member in this tournament
  $st = $pdo->prepare("SELECT id FROM signup_members WHERE tournament_id=? AND user_id=?");
  $st->execute([$tournament_id, $u['id']]);
  $row = $st->fetch();
  if (!$row) { header('Location: /index.php?error='.rawurlencode('You are not signed up for that tournament.')); exit; }

  $upd = $pdo->prepare("UPDATE signup_members SET has_ride=? WHERE tournament_id=? AND user_id=?");
  $upd->execute([$has_ride, $tournament_id, $u['id']]);

  header('Location: /index.php'); exit;
}

if ($action === 'admin_bulk_set') {
  if (!$u['is_admin']) { http_response_code(403); exit('Admins only'); }
  $tournament_id = (int)($_POST['tournament_id'] ?? 0);
  $rides = $_POST['ride'] ?? []; // ride[user_id] => '1' | '0' | '' (unspecified)
  if (!is_array($rides)) $rides = [];

  // Update each user row
  $upd = $pdo->prepare("UPDATE signup_members SET has_ride=? WHERE tournament_id=? AND user_id=?");
  foreach ($rides as $uid => $v) {
    $uid = (int)$uid;
    if ($uid <= 0) continue;
    $val = null;
    if ($v === '1') $val = 1;
    elseif ($v === '0') $val = 0;
    $upd->execute([$val, $tournament_id, $uid]);
  }

  // Redirect back to referrer or index
  $ref = $_POST['ref'] ?? ($_SERVER['HTTP_REFERER'] ?? '/index.php');
  header('Location: '.$ref); exit;
}

http_response_code(400);
echo 'Unknown action';
