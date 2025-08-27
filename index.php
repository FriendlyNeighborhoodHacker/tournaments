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
require_once __DIR__.'/lib/Judges.php';
require_login();
$u = current_user();

// Upcoming tournaments
$tournaments = Tournaments::upcoming();
$tournaments_by_id = array();
foreach ($tournaments as $t) {
	$tournaments_by_id[$t['id']] = $t;
}
$tournament_ids = array_keys($tournaments_by_id);
$my_has_ride = [];
if (!empty($tournament_ids)) {
  // Map of tournament_id => has_ride (NULL/0/1) for current user
  if (method_exists('Signups', 'hasRideByTournamentForUser')) {
    $my_has_ride = Signups::hasRideByTournamentForUser($tournament_ids, $u['id']);
  } else {
    $my_has_ride = [];
  }
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
$__err = trim($_GET['error'] ?? '');
if ($__err !== '') { echo '<p class="error">'.h($__err).'</p>'; }
echo '<div class="welcome-banner">Welcome back, '.h($u['first_name']).'!</div>';
$__announcement = Settings::get('announcement', '');
if ($__announcement !== '') { 
  echo '
    <div class="card" style="background:#fff3cd;border:1px solid #ffeeba;">
    <h2><strong>Announcement</strong></h2>
    <p>' . nl2br(h($__announcement)) . '</p></div>'; 
}
?>

<h2>Upcoming Tournaments</h2>
<?php if(empty($tournaments)): // IF EMPTY TOURNAMENTS ?>
  <p>No upcoming tournaments yet.</p>
<?php else: // IF EMPTY TOURNAMENTS ?>
  <div class="grid">
<?php foreach($tournaments_by_id as $tournament_id => $tournament): // FOREACH TOURNAMENT
	$t = $tournament;
	$mine = $my_by_t[$tournament_id] ?? null; 
?>
    <div class="card">
      <h3><?=h($t['name'])?></h3>
      <p><strong>Location:</strong> <?=h($t['location'])?></p>
      <p><strong>Dates:</strong> <?=h($t['start_date'])?> → <?=h($t['end_date'])?></p>
      <?php
        $allTeams = Signups::teamsForTournament($t['id']);
        $judgesBySignup = Judges::judgesBySignupForTournament($t['id']);
        $allJudges = $u['is_admin'] ? Judges::listAll() : [];
      ?>
      <?php if (empty($allTeams)): ?>
        <p><em>No sign-ups yet.</em></p>
      <?php else: ?>
        <p><strong>Teams:</strong></p>
        <ul>
          <?php foreach ($allTeams as $r): ?>
            <li>
              <?php if ($mine && isset($mine['id']) && $r['id'] == $mine['id']): ?>
                <strong><?=h($r['members'])?></strong>
              <?php else: ?>
                <?=h($r['members'])?>
              <?php endif; ?>
              (signed-up by <?=h($r['cb_fn'].' '.$r['cb_ln'])?>)
              <?php
                $isMine = ($mine && isset($mine['id']) && $r['id'] == $mine['id']);
                $js = $judgesBySignup[$r['id']] ?? [];
                if (!empty($js)) {
                  $jn = array_map(function($j){ return $j['first_name'].' '.$j['last_name']; }, $js);
                  echo ' — bringing '.h(implode(', ', $jn)).' to judge';
                  if ($u['is_admin']) {
                    echo ' <a href="#" class="small" onclick="openEditJudgesModal(\'editJudges_'.$r['id'].'\'); return false;">edit judges</a>';
                  } elseif ($isMine) {
                    echo ' <a href="#" class="small" onclick="openEditJudgesModal(\'editJudges_'.$r['id'].'\'); return false;">edit judges</a>';
                  }
                } else {
                  echo ' — bringing none to judge';
                  if ($u['is_admin']) {
                    echo ' <a href="#" class="small" onclick="openEditJudgesModal(\'editJudges_'.$r['id'].'\'); return false;">edit judges</a>';
                  } elseif ($isMine) {
                    echo ' <a href="#" class="small" onclick="openEditJudgesModal(\'editJudges_'.$r['id'].'\'); return false;">edit judges</a>';
                  }
                }
              ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <?php if ($u['is_admin']): ?>
        <?php foreach ($allTeams as $rr): 
          $sid = (int)$rr['id'];
          $attached = $judgesBySignup[$sid] ?? [];
          $attachedIds = array_map(function($j){ return (int)$j['judge_id']; }, $attached);
          $modalId = 'editJudges_'.$sid;
          $memberIds = Signups::membersForSignup($sid);
          $teamJudges = Judges::listBySponsors($memberIds);
        ?>
        <div id="<?=h($modalId)?>" class="modal hidden" aria-hidden="true">
          <div class="modal-content">
            <button class="close" onclick="closeEditJudgesModal('<?=h($modalId)?>')">×</button>
            <h3>Edit judges — <?=h($rr['members'])?></h3>
            <form method="post" action="/judge_actions.php" class="stack">
              <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
              <input type="hidden" name="action" value="bulk_set">
              <input type="hidden" name="signup_id" value="<?=h($sid)?>">
              <input type="hidden" name="ref" value="/index.php">
              <div style="max-height:260px; overflow:auto; border:1px solid #e8e8ef; border-radius:8px; padding:8px;">
                <?php foreach ($teamJudges as $j): ?>
                  <?php $checked = in_array((int)$j['id'], $attachedIds, true) ? 'checked' : ''; ?>
                  <label style="display:block;">
                    <input type="checkbox" name="judge_ids[]" value="<?=$j['id']?>" <?=$checked?>> <?=h($j['last_name'].', '.$j['first_name'])?>
                  </label>
                <?php endforeach; ?>
              </div>
              <div class="actions">
                <button class="primary">Save</button>
                <button type="button" onclick="closeEditJudgesModal('<?=h($modalId)?>')">Cancel</button>
              </div>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>

      <?php if (!$u['is_admin'] && $mine): 
        $sid = (int)$mine['id'];
        $attached = $judgesBySignup[$sid] ?? [];
        $attachedIds = array_map(function($j){ return (int)$j['judge_id']; }, $attached);
        $modalId = 'editJudges_'.$sid;
        $memberIds = Signups::membersForSignup($sid);
        $teamJudges = Judges::listBySponsors($memberIds);
      ?>
      <div id="<?=h($modalId)?>" class="modal hidden" aria-hidden="true">
        <div class="modal-content">
          <button class="close" onclick="closeEditJudgesModal('<?=h($modalId)?>')">×</button>
          <h3>Edit judges — <?=h($my_by_t[$tournament_id]['members'] ?? 'Your team')?></h3>
          <form method="post" action="/judge_actions.php" class="stack">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="action" value="bulk_set">
            <input type="hidden" name="signup_id" value="<?=h($sid)?>">
            <input type="hidden" name="ref" value="/index.php">
            <div style="max-height:260px; overflow:auto; border:1px solid #e8e8ef; border-radius:8px; padding:8px;">
              <?php foreach ($teamJudges as $j): ?>
                <?php $checked = in_array((int)$j['id'], $attachedIds, true) ? 'checked' : ''; ?>
                <label style="display:block;">
                  <input type="checkbox" name="judge_ids[]" value="<?=$j['id']?>" <?=$checked?>> <?=h($j['last_name'].', '.$j['first_name'])?>
                </label>
              <?php endforeach; ?>
            </div>
            <div class="actions">
              <button class="primary">Save</button>
              <button type="button" onclick="closeEditJudgesModal('<?=h($modalId)?>')">Cancel</button>
            </div>
          </form>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($mine): // IF MINE?>
        <div class="badge success">You’re signed up</div><br>
        <?php if (!empty($mine['comment'])): ?><p><strong>Comment:</strong> <?=nl2br(h($mine['comment']))?></p><?php endif; ?>
        <form class="inline" method="post" action="/signup_actions.php" onsubmit="return confirm('Un-sign your team from this tournament?')">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="signup_id" value="<?=h($mine['id'])?>">
          <input type="submit" id="withdrawSubmit_<?=h($mine['id'])?>" hidden>
          <a href="#" onclick="document.getElementById('withdrawSubmit_<?=h($mine['id'])?>').click(); return false;">Withdraw Team</a><br>
        </form>
        <?php
          $rideState = $my_has_ride[$tournament_id] ?? null;
          if ($rideState === null) : // IF RIDE NOT SET
        ?>
          <button type="button" onclick="openRideModal(<?=h($tournament_id)?>)">Do you have a ride?</button>
          <?php elseif ($rideState == 0) : // ELSE NEEDS RIDE ?>
            Ride status: <a href="#" onclick="openRideModal(<?=h($tournament_id)?>); return false;">Needs Ride</a><br>
          <?php else: // ELSE (HAS RIDE) ?>
            Ride status: <a href="#" onclick="openRideModal(<?=h($tournament_id)?>); return false;">Has Ride</a><br>
        <?php endif; // RIDE SET?>
        <?php if ($u['is_admin']): ?>
          <button class="primary" onclick='openSignupModal(<?=json_encode([
            "tournament_id"=>$t["id"],
            "tournament_name"=>$t["name"]
          ])?>)'>Sign up another team</button>
        <?php endif; ?> 
      <?php else: // ELSE MINE ?>
        <button class="primary" onclick='openSignupModal(<?=json_encode([
          "tournament_id"=>$t["id"],
          "tournament_name"=>$t["name"]
        ])?>)'>Sign up</button>
      <?php endif; // ENDIF MINE ?>

      <?php if ($u['is_admin']): ?>
        <button type="button" onclick="openAdminRidesModal('ridesModal_<?=h($t['id'])?>')">See rides</button>
      <?php endif; ?>



    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; // IF EMPTY TOURNAMENTS ?>

  <?php if ($u['is_admin']): ?>
  <?php foreach($tournaments_by_id as $tournament_id => $t):
    $members = Signups::membersWithRideForTournament($t['id']);
    $modalId = 'ridesModal_'.$t['id'];
    $modalTitle = $t['name'];
    $tournamentId = (int)$t['id'];
    $ref = '/index.php';
    include __DIR__.'/rides_modal.php';
  endforeach; ?>
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

<!-- Ride Self Modal -->
<div id="rideModal" class="modal hidden" aria-hidden="true">
  <div class="modal-content">
    <button class="close" onclick="closeRideModal()">×</button>
    <h3>Do you have a ride?</h3>
    <form method="post" action="/ride_actions.php" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <input type="hidden" name="action" value="self_set">
      <input type="hidden" name="tournament_id" id="ride_tournament_id">
      <label class="inline"><input type="radio" name="has_ride" value="1"> Yes</label>
      <label class="inline"><input type="radio" name="has_ride" value="0"> No</label>
      <div class="actions">
        <button class="primary">Save</button>
        <button type="button" onclick="closeRideModal()">Cancel</button>
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

function openRideModal(tournamentId) {
  var m = document.getElementById('rideModal');
  var hid = document.getElementById('ride_tournament_id');
  if (hid) hid.value = tournamentId;
  if (m) {
    m.classList.remove('hidden');
    m.setAttribute('aria-hidden','false');
  }
}
function closeRideModal() {
  var m = document.getElementById('rideModal');
  if (m) {
    m.classList.add('hidden');
    m.setAttribute('aria-hidden','true');
  }
}
function openAdminRidesModal(id) {
  var m = document.getElementById(id);
  if (m) {
    m.classList.remove('hidden');
    m.setAttribute('aria-hidden','false');
  }
}
function closeAdminRidesModal(id) {
  var m = document.getElementById(id);
  if (m) {
    m.classList.add('hidden');
    m.setAttribute('aria-hidden','true');
  }
}
function openEditJudgesModal(id) {
  var m = document.getElementById(id);
  if (m) {
    m.classList.remove('hidden');
    m.setAttribute('aria-hidden','false');
  }
}
function closeEditJudgesModal(id) {
  var m = document.getElementById(id);
  if (m) {
    m.classList.add('hidden');
    m.setAttribute('aria-hidden','true');
  }
}
</script>
<?php footer_html(); ?>
