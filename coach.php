<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/settings.php';
require_once __DIR__.'/lib/Tournaments.php';
require_once __DIR__.'/lib/Signups.php';

$showAll = !empty($_GET['all']);
$tournaments = $showAll ? Tournaments::allAsc() : Tournaments::upcoming();
header_html('Upcoming Tournaments');
$__announcement = Settings::get('announcement', '');
if ($__announcement !== '') { echo '<h2><strong>Announcement</strong></h2><div class="card" style="background:#fff3cd;border:1px solid #ffeeba;"><p>'.nl2br(h($__announcement)).'</p></div>'; }
?>
<h2><?= $showAll ? 'All tournaments' : 'Upcoming tournaments' ?></h2>
<?php foreach($tournaments as $t): ?>
  <section class="coach-section">
    <h3><?=h($t['name'])?> — <?=h($t['start_date'])?> → <?=h($t['end_date'])?> (<?=h($t['location'])?>)</h3>
    <?php
      $rows = Signups::teamsForTournament($t['id']);
    ?>
    <?php if (empty($rows)): ?>
      <p><em>No sign-ups yet.</em></p>
    <?php else: ?>
      <table class="list">
        <thead><tr><th>Team</th><th>Comment</th><th>Created by</th><th>Created</th><th>Updated</th><?php if(current_user()['is_admin']):?><th>Admin</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=h($r['members'])?></td>
            <td><?=nl2br(h($r['comment']))?></td>
            <td><?=h($r['cb_fn'].' '.$r['cb_ln'])?></td>
            <td><?=h($r['created_at'])?></td>
            <td><?=h($r['updated_at'])?></td>
            <?php if(current_user()['is_admin']):?>
              <td>
                <form class="inline" method="post" action="/signup_actions.php" onsubmit="return confirm('Delete this team signup?')">
                  <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="signup_id" value="<?=h($r['id'])?>">
                  <button class="danger">Delete</button>
                </form>
                <a class="button" href="/signup_edit.php?id=<?=h($r['id'])?>">Edit</a>
    <?php if(current_user()['is_admin']): ?>
      <button type="button" onclick="openAdminRidesModal('ridesModal_<?=h($t['id'])?>')">See rides</button>
      <?php
        $members = Signups::membersWithRideForTournament($t['id']);
        $modalId = 'ridesModal_'.$t['id'];
        $modalTitle = $t['name'];
        $tournamentId = (int)$t['id'];
        $ref = '/coach.php'.($showAll ? '?all=1' : '');
        include __DIR__.'/rides_modal.php';
      ?>
    <?php endif; ?>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
<?php endforeach; ?>
<p class="small" style="margin-top: 1rem;">
  <?php if ($showAll): ?>
    <a href="/coach.php">Show upcoming only</a>
  <?php else: ?>
    <a href="/coach.php?all=1">Show all tournaments (including past)</a>
  <?php endif; ?>
</p>
<script>
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
</script>
<?php footer_html(); ?>
