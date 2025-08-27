<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/settings.php';
require_once __DIR__.'/lib/Tournaments.php';
require_once __DIR__.'/lib/Signups.php';
require_once __DIR__.'/lib/Judges.php';

$previous = Tournaments::previousDesc();

header_html('Previous Tournaments');
?>
<h2>Previous Tournaments</h2>
<?php if (empty($previous)): ?>
  <p>No previous tournaments.</p>
<?php else: ?>
  <div class="grid">
  <?php foreach ($previous as $t): ?>
    <div class="card">
      <h3><?=h($t['name'])?></h3>
      <p><strong>Location:</strong> <?=h($t['location'])?></p>
      <p><strong>Dates:</strong> <?=h($t['start_date'])?> → <?=h($t['end_date'])?></p>
      <?php
        $allTeams = Signups::teamsForTournament($t['id']);
      ?>
      <?php if (empty($allTeams)): ?>
        <p><em>No sign-ups recorded.</em></p>
      <?php else: ?>
        <p><strong>Teams:</strong></p>
        <ul>
          <?php foreach ($allTeams as $r): ?>
            <li>
              <?=h($r['members'])?>
              (signed-up by <?=h($r['cb_fn'].' '.$r['cb_ln'])?>; created <?=h(Settings::formatDateTime($r['created_at']))?>)
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="summary-lines">
      <?php
        $tJudges = Judges::judgesCombinedForTournament($t['id']);
        if (!empty($tJudges)) {
          $names = array_map(function($j){ return $j['first_name'].' '.$j['last_name']; }, $tJudges);
          echo '<p><strong>Judges ('.count($tJudges).'):</strong> '.h(implode(', ', $names)).'</p>';
        } else {
          echo '<p><strong>Judges (0):</strong> none</p>';
        }
      ?>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
<?php footer_html(); ?>
