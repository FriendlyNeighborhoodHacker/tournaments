<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/settings.php';
require_coach_or_admin();

$showAll = !empty($_GET['all']);
$query = $showAll
  ? "SELECT * FROM tournaments ORDER BY start_date ASC"
  : "SELECT * FROM tournaments WHERE start_date >= CURDATE() ORDER BY start_date ASC";
$tournaments = pdo()->query($query)->fetchAll();
header_html('Coach View');
$__announcement = Settings::get('announcement', '');
if ($__announcement !== '') { echo '<div class="card"><p>'.nl2br(h($__announcement)).'</p></div>'; }
?>
<h2>Coach View — <?= $showAll ? 'All tournaments' : 'Upcoming tournaments' ?></h2>
<?php foreach($tournaments as $t): ?>
  <section class="coach-section">
    <h3><?=h($t['name'])?> — <?=h($t['start_date'])?> → <?=h($t['end_date'])?> (<?=h($t['location'])?>)</h3>
    <?php
      $st = pdo()->prepare("
        SELECT s.*, cb.first_name cb_fn, cb.last_name cb_ln,
               GROUP_CONCAT(CONCAT(u.first_name,' ',u.last_name) ORDER BY u.last_name SEPARATOR ', ') AS members
        FROM signups s
          JOIN users cb ON cb.id = s.created_by_user_id
          JOIN signup_members sm ON sm.signup_id = s.id
          JOIN users u ON u.id = sm.user_id
        WHERE s.tournament_id = ?
        GROUP BY s.id
        ORDER BY s.created_at ASC
      ");
      $st->execute([$t['id']]);
      $rows = $st->fetchAll();
    ?>
    <?php if (empty($rows)): ?>
      <p><em>No sign-ups yet.</em></p>
    <?php else: ?>
      <table class="list">
        <thead><tr><th>Team</th><th>Maverick</th><th>Comment</th><th>Created by</th><th>Created</th><th>Updated</th><?php if(current_user()['is_admin']):?><th>Admin</th><?php endif; ?></tr></thead>
        <tbody>
        <?php foreach($rows as $r): ?>
          <tr>
            <td><?=h($r['members'])?></td>
            <td><?= $r['go_maverick'] ? 'Yes' : 'No' ?></td>
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
<?php footer_html(); ?>
