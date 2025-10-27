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
      <p><strong>Dates:</strong> <?=Settings::formatDateRange($t['start_date'], $t['end_date'])?></p>
      <?php
        $allTeams = Signups::teamsForTournament($t['id']);
        $judgesBySignup = Judges::judgesBySignupForTournament($t['id']);
        $tournamentJudges = Judges::tournamentJudgesForTournament($t['id']);
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

      <?php if (!empty($tournamentJudges)): ?>
        <p><strong>Other Tournament Judges:</strong> <?= h(implode(', ', array_map(function($j){ return $j['first_name'].' '.$j['last_name']; }, $tournamentJudges))) ?></p>
      <?php endif; ?>

      <?php if (($u['is_admin']) && (count($allTeams) > 0)): ?>
        <a href="#" class="small" onclick="openEditJudgesModal('tjModal_<?=h($t['id'])?>'); return false;">Manage tournament judges</a>
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

      <?php if ($u['is_admin']): 
        $tjModalId = 'tjModal_'.$t['id'];
      ?>
      <div id="<?=h($tjModalId)?>" class="modal hidden" aria-hidden="true">
        <div class="modal-content">
          <button class="close" onclick="closeEditJudgesModal('<?=h($tjModalId)?>')">×</button>
          <h3>Manage tournament judges — <?=h($t['name'])?></h3>
          <?php if (empty($tournamentJudges)): ?>
            <p class="small">No tournament-only judges yet.</p>
          <?php else: ?>
            <ul>
              <?php foreach ($tournamentJudges as $j): ?>
                <li><?=h($j['first_name'].' '.$j['last_name'])?>
                  <form class="inline" method="post" action="/judge_actions.php" onsubmit="return confirm('Remove this tournament judge?')">
                    <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                    <input type="hidden" name="action" value="tournament_remove">
                    <input type="hidden" name="tournament_id" value="<?=h($t['id'])?>">
                    <input type="hidden" name="judge_id" value="<?=h($j['id'])?>">
                    <input type="hidden" name="ref" value="/index.php">
                    <button class="button danger" style="padding:2px 6px;font-size:12px;">Remove</button>
                  </form>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <hr>
          <form method="post" action="/judge_actions.php" class="stack">
            <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
            <input type="hidden" name="action" value="tournament_add">
            <input type="hidden" name="tournament_id" value="<?=h($t['id'])?>">
            <input type="hidden" name="ref" value="/index.php">
            <label>Select judge to add
              <select name="judge_id" required>
                <?php foreach ($allJudges as $j): ?>
                  <option value="<?=$j['id']?>"><?=h($j['last_name'].', '.$j['first_name'])?></option>
                <?php endforeach; ?>
              </select>
            </label>
            <div class="actions">
              <button class="primary">Add judge</button>
              <button type="button" onclick="closeEditJudgesModal('<?=h($tjModalId)?>')">Close</button>
            </div>
          </form>
        </div>
      </div>
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
        <form class="inline" method="post" action="/signup_actions.php" onsubmit="return confirm('Un-sign your team from this tournament?')">
          <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="signup_id" value="<?=h($mine['id'])?>">
          <input type="submit" id="withdrawSubmit_<?=h($mine['id'])?>" hidden>
        </form>
          <p><strong>You’re signed up.</strong></p>
        <ul>
        <?php if (!empty($mine['comment'])): ?><li><strong>Comment:</strong> <?=nl2br(h($mine['comment']))?></li><?php endif; ?>
          <li>Your ride status:
        <?php
          $rideState = $my_has_ride[$tournament_id] ?? null;
          if ($rideState === null) : // IF RIDE NOT SET
        ?>
          <a href="#" onclick="openRideModal(<?=h($tournament_id)?>); return false;">Not set</a>
          <?php elseif ($rideState == 0) : // ELSE NEEDS RIDE ?>
            <a href="#" onclick="openRideModal(<?=h($tournament_id)?>); return false;">Needs Ride</a>
          <?php else: // ELSE (HAS RIDE) ?>
            <a href="#" onclick="openRideModal(<?=h($tournament_id)?>); return false;">Has Ride</a>
        <?php endif; // RIDE SET?>
          </li>
      <?php if ($mine): // IF MINE?>
          <li><a href="#" onclick="document.getElementById('withdrawSubmit_<?=h($mine['id'])?>').click(); return false;">Withdraw team</a></li>
      <?php endif; ?>

        </ul>
      <?php else: // ELSE MINE ?>
        <button class="primary" onclick='openSignupModal(<?=json_encode([
          "tournament_id"=>$t["id"],
          "tournament_name"=>$t["name"]
        ])?>)'>Sign up</button>
      <?php endif; // ENDIF MINE ?>

      <?php if (!empty($allTeams)): // Rides section ?>
        <?php
          $members = Signups::membersWithRideForTournament($t['id']);
          $has_names = []; $needs_names = []; $unspec_names = [];
          foreach ($members as $mm) {
            $nm = h($mm['first_name'].' '.$mm['last_name']);
            if ($mm['has_ride'] === null) { $unspec_names[] = $nm; }
            elseif ((int)$mm['has_ride'] === 1) { $has_names[] = $nm; }
            else { $needs_names[] = $nm; }
          }
        ?>
        <p><strong>Rides:</strong></p>
        <ul>
          <li>Has ride: <?= empty($has_names) ? '<em>none</em>' : implode(', ', $has_names) ?></li>
          <li>Needs ride: <?= empty($needs_names) ? '<em>none</em>' : implode(', ', $needs_names) ?></li>
          <?php if (!empty($unspec_names)): ?>
          <li>Unspecified ride: <?= implode(', ', $unspec_names) ?></li>
          <?php endif; ?>
        </ul>
      <?php endif; ?>

      <?php if ($u['is_admin']): ?>
          <p><strong>Admin Actions:</strong></p>
          <ul>
            <?php if (!empty($allTeams)): ?>
            <li><a href="#" onclick="openAdminRidesModal('ridesModal_<?=h($t['id'])?>'); return false;">Ride statuses: edit</a></li>
            <?php endif; ?>
          <li><a href="#" onclick='openSignupModal(<?=json_encode([
            "tournament_id"=>$t["id"],
            "tournament_name"=>$t["name"]
          ])?>); return false;'>
          <?php if ($mine): ?>
            Sign up another team
          <?php else: ?>
            Sign up a team
          <?php endif; ?>
          </a></li>
        </ul>
      <?php endif; ?>



    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; // IF EMPTY TOURNAMENTS ?>

  <?php
    // Previous Tournaments (most recent 3)
    $previous = Tournaments::previousRecent(3);
    if (!empty($previous)):
  ?>
  <br>
  <h2>Previous Tournaments</h2>
  <div class="grid">
    <?php foreach ($previous as $pt): ?>
      <div class="card">
        <h3><?=h($pt['name'])?></h3>
        <p><strong>Location:</strong> <?=h($pt['location'])?></p>
        <p><strong>Dates:</strong> <?=Settings::formatDateRange($pt['start_date'], $pt['end_date'])?></p>
        <?php
          $prevTeams = Signups::teamsForTournament($pt['id']);
        ?>
        <?php if (empty($prevTeams)): ?>
          <p><em>No sign-ups recorded.</em></p>
        <?php else: ?>
          <p><strong>Teams:</strong></p>
          <ul>
            <?php foreach ($prevTeams as $r): ?>
              <li>
                <?=h($r['members'])?>
                (signed-up by <?=h($r['cb_fn'].' '.$r['cb_ln'])?>; created <?=h(Settings::formatDateTime($r['created_at']))?>)
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>

        <div class="summary-lines">
          <?php
            // Combined judges (team-attached + tournament-attached)
            $pJudges = Judges::judgesCombinedForTournament($pt['id']);
            if (!empty($pJudges)) {
              $names = array_map(function($j){ return $j['first_name'].' '.$j['last_name']; }, $pJudges);
              echo '<p><strong>Judges ('.count($pJudges).'):</strong> '.h(implode(', ', $names)).'</p>';
            } else {
              echo '<p><strong>Judges (0):</strong> none</p>';
            }
          ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="small"><a href="/previous_tournaments.php">See all previous tournaments</a></p>
  <?php endif; ?>

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
      <input type="hidden" id="m_team_size_max" value="">
      
      <p  id="partnersHelp" style="margin:8px 0 16px;color:#555;"></p>
      
      <div id="teamSelectionWrap">
        <label>Search for team members
          <input type="text" id="m_search_input" placeholder="Type to search..." autocomplete="off">
        </label>
        <div id="m_search_results" class="search-results hidden"></div>
        
        <div id="m_selected_members" class="selected-members">
          <div id="m_members_list"></div>
          <small class="small" id="membersCount"></small>
        </div>
      </div>
      
      <div id="maverickWrap" class="hidden">
        <label><input type="checkbox" id="m_go_maverick" name="go_maverick" value="1"> Go Maverick (I want to compete solo)</label>
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
  roster: <?=json_encode($roster)?>,
  tournaments_by_id: <?=json_encode($tournaments_by_id)?>
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
