<?php

function echo_r($o) {
  echo('<pre>');
  print_r($o);
  echo('</pre>');
}
require_once __DIR__.'/partials.php';
require_once __DIR__.'/settings.php';
require_login();
$u = current_user();

// Upcoming tournaments
$tournaments = pdo()->query("SELECT * FROM tournaments WHERE start_date >= CURDATE() ORDER BY start_date ASC")->fetchAll();
$tournaments_by_id = array();
foreach ($tournaments as $t) {
	$tournaments_by_id[$t['id']] = $t;
}

$st = pdo()->prepare('select s.* from tournaments t
		inner join signups s on s.tournament_id = t.id
		inner join signup_members sm on sm.signup_id = s.id
		where start_date >= CURDATE() and sm.user_id = ?');
$st->execute([$u['id']]);
$signups = $st->fetchAll();
$signup_ids = array();
$signups_by_id = array();
foreach ($signups as $signup) {
	$signup_id = $signup['id'];
	$signups_by_id[$signup_id] = $signup;
	$signup_ids[$signup_id] = 1;
}
$signup_ids = array_keys($signup_ids);

// For client-side: roster (non-admins can only sign themselves + 1–2 partners; admins can pick anyone)
$roster = pdo()->query("SELECT id, first_name, last_name, email, is_coach, is_admin FROM users ORDER BY last_name, first_name")->fetchAll(PDO::FETCH_ASSOC);

$my_by_t = array();
if (empty($signup_ids)) {
} else {
// Map of my signups by tournament (single query, not inside a loop)
$st = pdo()->prepare("
SELECT s.*, sm.tournament_id, cb.first_name cb_fn, cb.last_name cb_ln,
       GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name) ORDER BY u.last_name SEPARATOR ', ') AS members
FROM signups s
  JOIN users cb ON cb.id = s.created_by_user_id
  JOIN signup_members sm ON sm.signup_id = s.id
  JOIN users u ON u.id = sm.user_id
where s.id in (" . join(',', $signup_ids) . ")
GROUP BY s.id
ORDER BY s.created_at ASC
");
$st->execute();
$rows = $st->fetchAll();
foreach ($rows as $row) {
  $my_by_t[$row['tournament_id']] = $row;
}
}

header_html('Home');
$__announcement = Settings::get('announcement', '');
if ($__announcement !== '') { echo '<div class="card"><p>'.nl2br(h($__announcement)).'</p></div>'; }
$__welcome = Settings::get('welcome_message', '');
if (!$u['is_admin'] && !$u['is_coach'] && $__welcome !== '') {
  $st0 = pdo()->prepare("SELECT 1 FROM signup_members WHERE user_id=? LIMIT 1");
  $st0->execute([$u['id']]);
  $has_any = (bool)$st0->fetchColumn();
  if (!$has_any) { echo '<div class="card"><p>'.nl2br(h($__welcome)).'</p></div>'; }
}
?>
<h2>Upcoming Tournaments</h2>
<?php if(empty($tournaments)): ?>
  <p>No upcoming tournaments yet.</p>
<?php else: ?>
  <div class="grid">
<?php foreach($tournaments_by_id as $tournament_id => $tournament):
	$t = $tournament;
	$mine = $my_by_t[$tournament_id] ?? null; 
?>
    <div class="card">
      <h3><?=h($t['name'])?></h3>
      <p><strong>Location:</strong> <?=h($t['location'])?></p>
      <p><strong>Dates:</strong> <?=h($t['start_date'])?> → <?=h($t['end_date'])?></p>

      <?php if ($mine): ?>
        <div class="badge success">You’re signed up</div>
        <p><strong>Team:</strong> <?=h($mine['members'])?></p>
        <p><strong>Signed by:</strong> <?=h($mine['cb_fn'].' '.$mine['cb_ln'])?> (<?=h($mine['created_at'])?>)</p>
        <?php if (!empty($mine['comment'])): ?><p><strong>Comment:</strong> <?=nl2br(h($mine['comment']))?></p><?php endif; ?>
        <form class="inline" method="post" action="/signup_actions.php">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="signup_id" value="<?=h($mine['id'])?>">
          <button class="danger" onclick="return confirm('Un-sign your team from this tournament?')">Un-sign Team</button>
        </form>
      <?php else: ?>
        <button class="primary" onclick='openSignupModal(<?=json_encode([
          "tournament_id"=>$t["id"],
          "tournament_name"=>$t["name"]
        ])?>)'>Sign up</button>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- Signup Modal -->
<div id="signupModal" class="modal hidden" aria-hidden="true">
  <div class="modal-content">
    <button class="close" onclick="closeSignupModal()">×</button>
    <h3 id="modalTitle">Sign up</h3>
    <form id="signupForm" method="post" action="/signup_actions.php" onsubmit="return submitSignupForm(this)">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="tournament_id" id="m_tournament_id">
      <label><input type="checkbox" id="m_go_maverick" name="go_maverick" value="1" onchange="toggleMaverick()"> Go Maverick (solo)</label>
      <div id="partnerWrap">
        <label>Choose partner(s) (1–2 other people)
          <select id="m_partners" name="partner_ids[]" multiple size="8" required></select>
        </label>
        <small class="small" id="partnersHelp">You’ll be included automatically; choose 1–2 partners (2–3 total).</small>
      </div>
      <label>Comment (optional)
        <textarea name="comment" rows="3" maxlength="500"></textarea>
      </label>
      <div class="actions">
        <button type="submit" class="primary">Create Team</button>
        <button type="button" onclick="closeSignupModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
window.APP = {
  currentUserId: <?=json_encode($u['id'])?>,
  isAdmin: <?=json_encode((bool)$u['is_admin'])?>,
  roster: <?=json_encode($roster)?>
};
</script>
<?php footer_html(); ?>
