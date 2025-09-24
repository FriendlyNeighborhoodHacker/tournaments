<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib/Signups.php';
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

  // Enforce tournament capacity and signup deadline (if set)
  $tinfo = $pdo->prepare("SELECT max_teams, signup_deadline FROM tournaments WHERE id=?");
  $tinfo->execute([$tournament_id]);
  $tournament = $tinfo->fetch();

  if (!$tournament) { header('Location: /index.php?error='.rawurlencode('Tournament not found')); exit; }

  // Deadline: allowed through the entire deadline date (until 23:59), then blocked starting next day
  if (!empty($tournament['signup_deadline'])) {
    $today = date('Y-m-d');
    if ($today > $tournament['signup_deadline']) {
      header('Location: /index.php?error='.rawurlencode('The deadline for sign ups for this tournament has passed. Please email to your coaches about signing up for this this tournament.')); exit;
    }
  }

  // Capacity: if max_teams is set and reached, block new signups
  if ($tournament['max_teams'] !== null) {
    $cur = $pdo->prepare("SELECT COUNT(*) FROM signups WHERE tournament_id=?");
    $cur->execute([$tournament_id]);
    $currentTeams = (int)$cur->fetchColumn();
    if ($currentTeams >= (int)$tournament['max_teams']) {
      header('Location: /index.php?error='.rawurlencode('The maximum number of sign ups for this tournament have been reached. Please email to your coaches about signing up for this this tournament.')); exit;
    }
  }

  // Build full member list
  if ($go_maverick) {
    // Maverick: creator only, even for admins
    $member_ids = [$u['id']];
  } elseif ($u['is_admin']) {
    // Admin may create signups for anyone (1–3 total)
    $member_ids = array_values(array_unique($partner_ids));
  } else {
    // Regular user must include themselves
    $member_ids = array_values(array_unique(array_merge([$u['id']], $partner_ids)));
  }

  // Disallow coaches in teams
  if (!empty($member_ids)) {
    $in = implode(',', array_fill(0, count($member_ids), '?'));
    $stc = $pdo->prepare("SELECT first_name,last_name FROM users WHERE is_coach=1 AND id IN ($in)");
    $stc->execute($member_ids);
    $coaches = $stc->fetchAll();
    if ($coaches) {
      $names = array_map(fn($r)=>$r['first_name'].' '.$r['last_name'], $coaches);
      $__err = 'Coaches cannot be added to teams: '.implode(', ', $names);
      header('Location: /index.php?error='.rawurlencode($__err)); exit;
    }
  }

  // Use Signups class to create the team (handles all validation and database operations)
  try {
    Signups::createTeam($tournament_id, $u['id'], $member_ids, (bool)$go_maverick, $comment);
  } catch (DomainException $e) {
    header('Location: /index.php?error='.rawurlencode($e->getMessage())); exit;
  } catch (Throwable $e) {
    header('Location: /index.php?error='.rawurlencode('Failed to create signup.')); exit;
  }

  header('Location: /index.php'); exit;
}

if ($action === 'delete') {
  $signup_id = (int)($_POST['signup_id'] ?? 0);
  // Use Signups class to delete the team (handles authorization and database operations)
  try {
    $success = Signups::deleteTeamIfAllowed($signup_id, $u['id'], (bool)$u['is_admin']);
    if (!$success) {
      http_response_code(404); exit('Not found or not allowed');
    }
  } catch (Throwable $e) {
    http_response_code(500); exit('Failed to delete signup');
  }
  header('Location: '.($_SERVER['HTTP_REFERER'] ?? '/index.php')); exit;
}

http_response_code(400);
echo 'Unknown action';
