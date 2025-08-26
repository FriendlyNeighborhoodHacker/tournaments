<?php

function echo_r($o) {
  echo('<pre>');
  print_r($o);
  echo('</pre>');
}
require_once __DIR__.'/partials.php';
require_once __DIR__.'/settings.php';
require_once __DIR__.'/lib/Tournaments.php';
require_once __DIR__.'/lib/Users.php';
require_once __DIR__.'/lib/Signups.php';
require_login();
$u = current_user();

// Upcoming tournaments
$tournaments = Tournaments::upcoming();
$tournaments_by_id = array();
foreach ($tournaments as $t) {
	$tournaments_by_id[$t['id']] = $t;
}

$signups = Signups::myUpcomingSignups($u['id']);
$signup_ids = array();
$signups_by_id = array();
foreach ($signups as $signup) {
	$signup_id = $signup['id'];
	$signups_by_id[$signup_id] = $signup;
	$signup_ids[$signup_id] = 1;
}
$signup_ids = array_keys($signup_ids);

// For client-side: roster (non-admins can only sign themselves + 1–2 partners; admins can pick anyone)
$roster = Users::roster();

$my_by_t = array();
if (empty($signup_ids)) {
} else {
$my_by_t = Signups::aggregateByTournament($signup_ids);
}

header_html('Home');
echo '<div class="welcome-banner">Welcome back, '.h($u['first_name']).'!</div>';
$__announcement = Settings::get('announcement', '');
if ($__announcement !== '') { echo '
  <div class="card" style="background:#fff3cd;border:1px solid #ffeeba;">
  <h2><strong>Announcement</strong></h2>
  <p>'.nl2br(h($__announcement)).'</p></div>'; }
$__new_user_msg = Settings::get('new_user_message', Settings::get('welcome_message', ''));
if (!$u['is_admin'] && !$u['is_coach'] && $__new_user_msg !== '') {
  $has_any = Signups::userHasAny($u['id']);
  if (!$has_any) { echo '<div class="card"><p>'.nl2br(h($__new_user_msg)).'</p></div>'; }
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
        <label>Choose partner(s) (1–2 other people)</label>
        <div id="m_partners_box" class="partners-box" style="max-height:220px; overflow:auto; border:1px solid #e8e8ef; border-radius:8px; padding:8px;"></div>
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
