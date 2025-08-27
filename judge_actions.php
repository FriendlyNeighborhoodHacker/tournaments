<?php
require_once __DIR__.'/auth.php';
require_login();
require_csrf();
require_once __DIR__.'/lib/Signups.php';
require_once __DIR__.'/lib/JudgeManagement.php';

$pdo = pdo();
$u = current_user();
$action = $_POST['action'] ?? '';

function back_to($default = '/index.php') {
  $ref = $_POST['ref'] ?? ($_SERVER['HTTP_REFERER'] ?? $default);
  header('Location: '.$ref);
  exit;
}

// Admin-only helper
function require_admin_user(array $u) {
  if (empty($u['is_admin'])) { http_response_code(403); exit('Admins only'); }
}

if ($action === 'detach_one') {
  require_admin_user($u);
  $signup_id = (int)($_POST['signup_id'] ?? 0);
  $judge_id  = (int)($_POST['judge_id'] ?? 0);
  if ($signup_id > 0 && $judge_id > 0) {
    JudgeManagement::detachFromSignup($signup_id, $judge_id);
  }
  back_to();
}

if ($action === 'bulk_set') {
  $signup_id = (int)($_POST['signup_id'] ?? 0);
  $judge_ids = $_POST['judge_ids'] ?? [];
  if (!is_array($judge_ids)) $judge_ids = [];
  $judge_ids = array_values(array_unique(array_map('intval', $judge_ids)));

  if ($signup_id > 0) {
    $is_admin = !empty($u['is_admin']);
    if (!$is_admin) {
      // Must be a member of the signup to edit its judges
      $st = $pdo->prepare('SELECT 1 FROM signup_members WHERE signup_id=? AND user_id=? LIMIT 1');
      $st->execute([$signup_id, $u['id']]);
      if (!$st->fetchColumn()) { http_response_code(403); exit('Forbidden'); }

      // Restrict to judges sponsored by members of this team
      $memberIds = Signups::membersForSignup($signup_id);
      if (!empty($judge_ids) && !empty($memberIds)) {
        $inJudges = implode(',', array_fill(0, count($judge_ids), '?'));
        $inSponsors = implode(',', array_fill(0, count($memberIds), '?'));
        $params = array_merge($judge_ids, $memberIds);
        $q = $pdo->prepare("SELECT id FROM judges WHERE id IN ($inJudges) AND sponsor_id IN ($inSponsors)");
        $q->execute($params);
        $allowed = array_map('intval', array_column($q->fetchAll(), 'id'));
        $judge_ids = array_values(array_unique($allowed));
      } else {
        $judge_ids = [];
      }
    }

    try {
      JudgeManagement::setJudgesForSignup($signup_id, $judge_ids);
    } catch (Throwable $e) {
      // swallow to keep UX simple
    }
  }
  back_to();
}

if ($action === 'tournament_add') {
  require_admin_user($u);
  $tournament_id = (int)($_POST['tournament_id'] ?? 0);
  $judge_id = (int)($_POST['judge_id'] ?? 0);
  if ($tournament_id > 0 && $judge_id > 0) {
    JudgeManagement::attachTournamentJudge($tournament_id, $judge_id);
  }
  back_to();
}

if ($action === 'tournament_remove') {
  require_admin_user($u);
  $tournament_id = (int)($_POST['tournament_id'] ?? 0);
  $judge_id = (int)($_POST['judge_id'] ?? 0);
  if ($tournament_id > 0 && $judge_id > 0) {
    JudgeManagement::detachTournamentJudge($tournament_id, $judge_id);
  }
  back_to();
}

http_response_code(400);
echo 'Unknown action';
