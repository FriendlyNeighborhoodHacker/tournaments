<?php
require_once __DIR__.'/partials.php';
require_admin();

$pdo = pdo();
$err = null;

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM signups WHERE id=?");
$st->execute([$id]);
$signup = $st->fetch();
if (!$signup) { http_response_code(404); exit('Not found'); }

if ($_SERVER['REQUEST_METHOD']==='POST') {
  require_csrf();
  $go_mav = !empty($_POST['go_maverick']) ? 1 : 0;
  $comment = trim($_POST['comment'] ?? '');
  $member_ids = array_map('intval', $_POST['member_ids'] ?? []);
  $member_ids = array_values(array_unique($member_ids));
  // Disallow coaches in teams
  if (empty($err) && !empty($member_ids)) {
    $in = implode(',', array_fill(0, count($member_ids), '?'));
    $stc = $pdo->prepare("SELECT first_name,last_name FROM users WHERE is_coach=1 AND id IN ($in)");
    $stc->execute($member_ids);
    $coaches = $stc->fetchAll();
    if ($coaches) {
      $err = 'Coaches cannot be added to teams: '.implode(', ', array_map(fn($r)=>$r['first_name'].' '.$r['last_name'], $coaches));
    }
  }
  // Validation: 1 if maverick else 2–3
  $cnt = count($member_ids);
  if ($go_mav ? $cnt!==1 : ($cnt<2 || $cnt>3)) { $err = 'Invalid team size'; }
  // Check conflicts: someone booked in a different signup for same tournament?
  if (empty($err)) {
    $in = implode(',', array_fill(0, $cnt, '?'));
    $params = array_merge([$signup['tournament_id'], $id], $member_ids);
    $stx = $pdo->prepare("
      SELECT u.first_name,u.last_name
      FROM signup_members sm 
      JOIN users u ON u.id=sm.user_id
      WHERE sm.tournament_id=? AND sm.signup_id<>? AND sm.user_id IN ($in)
    ");
    $stx->execute($params);
    $conf = $stx->fetchAll();
    if ($conf) $err = 'Already signed: '.implode(', ', array_map(fn($r)=>$r['first_name'].' '.$r['last_name'], $conf));
  }
  if (empty($err)) {
    $pdo->beginTransaction();
    try {
      $pdo->prepare("UPDATE signups SET go_maverick=?, comment=? WHERE id=?")->execute([$go_mav, $comment, $id]);
      // Replace members
      $pdo->prepare("DELETE FROM signup_members WHERE signup_id=?")->execute([$id]);
      $stm = $pdo->prepare("INSERT INTO signup_members (signup_id,tournament_id,user_id) VALUES (?,?,?)");
      foreach ($member_ids as $uid) $stm->execute([$id, $signup['tournament_id'], $uid]);
      $pdo->commit();
      header('Location: /upcoming_tournaments.php'); exit;
    } catch (Throwable $e) {
      $pdo->rollBack();
      $err = 'Failed to save changes.';
    }
  }
}

$roster = $pdo->query("SELECT id,first_name,last_name,is_coach FROM users ORDER BY last_name,first_name")->fetchAll();
$members = $pdo->prepare("SELECT user_id FROM signup_members WHERE signup_id=?");
$members->execute([$id]);
$currentMembers = array_column($members->fetchAll(), 'user_id');

header_html('Edit Signup');
?>
<h2>Edit Signup</h2>
<?php if(!empty($err)):?><p class="error"><?=h($err)?></p><?php endif; ?>
<p><strong>Tournament:</strong>
<?php
$tt = $pdo->prepare("SELECT name FROM tournaments WHERE id=?");
$tt->execute([$signup['tournament_id']]); echo h($tt->fetch()['name']);
?></p>

<form method="post" class="stack">
  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
  <label><input type="checkbox" name="go_maverick" value="1" <?= $signup['go_maverick']?'checked':'' ?>> Go Maverick</label>
  <label>Team members (hold Ctrl/Cmd to pick 1–3)
    <select name="member_ids[]" multiple size="10" required>
      <?php foreach($roster as $r): ?>
        <option value="<?=$r['id']?>" <?= in_array($r['id'], $currentMembers)?'selected':'' ?>>
          <?=h($r['last_name'].', '.$r['first_name'])?><?= $r['is_coach']?' (Coach)':'' ?>
        </option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Comment<textarea name="comment" rows="3"><?=h($signup['comment'])?></textarea></label>
  <div class="actions">
    <button class="primary">Save</button>
    <a class="button" href="/upcoming_tournaments.php">Cancel</a>
  </div>
</form>
<?php footer_html(); ?>
