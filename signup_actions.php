<?php
require_once __DIR__.'/auth.php';
require_login();
require_csrf();

$pdo = pdo();
$u = current_user();

$action = $_POST['action'] ?? '';
if ($action === 'create') {
  $tournament_id = (int)($_POST['tournament_id'] ?? 0);
  $go_maverick   = !empty($_POST['go_maverick']) ? 1 : 0;
  $comment       = trim($_POST['comment'] ?? '');
  $partner_ids   = array_map('intval', $_POST['partner_ids'] ?? []);

  // Build full member list
  if ($u['is_admin']) {
    // Admin may create signups for anyone (1–3 total)
    $member_ids = array_values(array_unique($partner_ids));
  } else {
    // Regular user must include themselves
    $member_ids = array_values(array_unique(array_merge([$u['id']], $partner_ids)));
  }

  // Validation
  $count = count($member_ids);
  if ($go_maverick) {
    if ($count !== 1) { http_response_code(400); exit('Maverick signup must be exactly 1 person.'); }
  } else {
    if ($count < 2 || $count > 3) { http_response_code(400); exit('Team signups must be 2 or 3 people total.'); }
  }

  // Check conflicts: any member already signed up for this tournament?
  $in = implode(',', array_fill(0, $count, '?'));
  $params = array_merge([$tournament_id], $member_ids);
  $st = $pdo->prepare("SELECT u.first_name, u.last_name FROM signup_members sm JOIN users u ON u.id=sm.user_id WHERE sm.tournament_id=? AND sm.user_id IN ($in)");
  $st->execute($params);
  $conf = $st->fetchAll();
  if ($conf) {
    $names = array_map(fn($r)=>$r['first_name'].' '.$r['last_name'], $conf);
    http_response_code(400); exit('Already signed up: '.implode(', ', $names));
  }

  // Create signup (wrap in a transaction for safety)
  $pdo->beginTransaction();
  try {
    $st = $pdo->prepare("INSERT INTO signups (tournament_id, created_by_user_id, go_maverick, comment) VALUES (?,?,?,?)");
    $st->execute([$tournament_id, $u['id'], $go_maverick, $comment]);
    $signup_id = (int)$pdo->lastInsertId();

    $stm = $pdo->prepare("INSERT INTO signup_members (signup_id, tournament_id, user_id) VALUES (?,?,?)");
    foreach ($member_ids as $uid) $stm->execute([$signup_id, $tournament_id, $uid]);

    $pdo->commit();
  } catch (Throwable $e) {
    $pdo->rollBack();
    http_response_code(500); exit('Failed to create signup.');
  }

  header('Location: /index.php'); exit;
}

if ($action === 'delete') {
  $signup_id = (int)($_POST['signup_id'] ?? 0);
  // Authorization: admin OR member of signup
  $st = $pdo->prepare("SELECT s.*, EXISTS(SELECT 1 FROM signup_members sm WHERE sm.signup_id=s.id AND sm.user_id=?) AS am_member FROM signups s WHERE s.id=?");
  $st->execute([$u['id'], $signup_id]);
  $s = $st->fetch();
  if (!$s) { http_response_code(404); exit('Not found'); }
  if (!$u['is_admin'] && !$s['am_member']) { http_response_code(403); exit('Not allowed'); }
  $pdo->prepare("DELETE FROM signups WHERE id=?")->execute([$signup_id]); // cascades
  header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/index.php')); exit;
}

http_response_code(400);
echo 'Unknown action';
