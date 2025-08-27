<?php
require_once __DIR__.'/partials.php';
require_admin();
$u = current_user();
$isAdmin = (bool)$u['is_admin'];

$pdo = pdo();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!$isAdmin) { http_response_code(403); exit('Admins only'); }
  require_csrf();
  if (isset($_POST['create'])) {
    $max = trim($_POST['max_teams'] ?? '') === '' ? null : (int)$_POST['max_teams'];
    $deadline = trim($_POST['signup_deadline'] ?? '') === '' ? null : $_POST['signup_deadline'];
    $st = $pdo->prepare("INSERT INTO tournaments (name,location,start_date,end_date,max_teams,signup_deadline) VALUES (?,?,?,?,?,?)");
    $st->execute([trim($_POST['name']), trim($_POST['location']), $_POST['start_date'], $_POST['end_date'], $max, $deadline]);
    $msg = 'Tournament created.';
  } elseif (isset($_POST['update'])) {
    $max = trim($_POST['max_teams'] ?? '') === '' ? null : (int)$_POST['max_teams'];
    $deadline = trim($_POST['signup_deadline'] ?? '') === '' ? null : $_POST['signup_deadline'];
    $st = $pdo->prepare("UPDATE tournaments SET name=?, location=?, start_date=?, end_date=?, max_teams=?, signup_deadline=? WHERE id=?");
    $st->execute([trim($_POST['name']), trim($_POST['location']), $_POST['start_date'], $_POST['end_date'], $max, $deadline, (int)$_POST['id']]);
    $msg = 'Tournament updated.';
  } elseif (isset($_POST['delete'])) {
    $st = $pdo->prepare("DELETE FROM tournaments WHERE id=?");
    $st->execute([(int)$_POST['id']]);
    $msg = 'Tournament deleted.';
  }
}

$rows = $pdo->query("SELECT * FROM tournaments ORDER BY start_date")->fetchAll();
header_html('Tournaments');
?>
<h2>Tournaments</h2>
<?php if($msg):?><p class="flash"><?=$msg?></p><?php endif; ?>
<div class="grid">
  <?php if($isAdmin): ?>
  <div class="card">
    <h3>Create</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>Name<input name="name" required></label>
      <label>Location<input name="location" required></label>
      <label>Start date<input type="date" name="start_date" required></label>
      <label>End date<input type="date" name="end_date" required></label>
      <label>Max teams (leave blank for unlimited)<input type="number" name="max_teams" min="1" inputmode="numeric"></label>
      <label>Signup deadline (optional)<input type="date" name="signup_deadline"></label>
      <button name="create" class="primary">Add</button>
    </form>
  </div>
  <?php endif; ?>
  <div class="card">
    <h3>Existing</h3>
    <table class="list">
      <thead><tr><th>Name</th><th>Dates</th><th>Location</th><?php if($isAdmin):?><th>Actions</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?=h($r['name'])?></td>
          <td><?=h($r['start_date'])?> → <?=h($r['end_date'])?></td>
          <td><?=h($r['location'])?></td>
          <?php if($isAdmin): ?>
          <td>
            <details>
              <summary>Edit/Delete</summary>
              <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="id" value="<?=$r['id']?>">
                <label>Name<input name="name" value="<?=h($r['name'])?>" required></label>
                <label>Location<input name="location" value="<?=h($r['location'])?>" required></label>
                <label>Start<input type="date" name="start_date" value="<?=$r['start_date']?>" required></label>
                <label>End<input type="date" name="end_date" value="<?=$r['end_date']?>" required></label>
                <label>Max teams (blank = unlimited)<input type="number" name="max_teams" min="1" value="<?=h($r['max_teams'])?>"></label>
                <label>Signup deadline<input type="date" name="signup_deadline" value="<?=$r['signup_deadline']?>"></label>
                <div class="actions">
                  <button name="update" class="primary">Save</button>
                  <button type="button" class="danger" onclick="openDeleteTournament(this.form, '<?=h($r['name'])?>')">Delete</button>
                </div>
              </form>
            </details>
          </td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Delete Tournament Modal -->
<div id="delTournamentModal" class="modal hidden" aria-hidden="true">
  <div class="modal-content">
    <button class="close" onclick="closeDelModal()">×</button>
    <h3>Delete Tournament</h3>
    <p>Are you sure you want to delete "<span id="delTournamentName"></span>"? This will also remove all sign-ups.</p>
    <div class="actions">
      <button class="danger" onclick="confirmDeleteTournament()">Delete</button>
      <button type="button" onclick="closeDelModal()">Cancel</button>
    </div>
  </div>
</div>

<script>
let __delTournamentForm = null;

function openDeleteTournament(form, name) {
  __delTournamentForm = form;
  const span = document.getElementById('delTournamentName');
  if (span) span.textContent = name || '';
  const m = document.getElementById('delTournamentModal');
  if (m) {
    m.classList.remove('hidden');
    m.setAttribute('aria-hidden', 'false');
  }
}

function closeDelModal() {
  const m = document.getElementById('delTournamentModal');
  if (m) {
    m.classList.add('hidden');
    m.setAttribute('aria-hidden', 'true');
  }
  __delTournamentForm = null;
}

function confirmDeleteTournament() {
  if (!__delTournamentForm) { closeDelModal(); return; }
  // Ensure server sees the "delete" action when submitting programmatically
  const hidden = document.createElement('input');
  hidden.type = 'hidden';
  hidden.name = 'delete';
  hidden.value = '1';
  __delTournamentForm.appendChild(hidden);
  __delTournamentForm.submit();
}
</script>

<?php footer_html(); ?>
