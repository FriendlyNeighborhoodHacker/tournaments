<?php
require_once __DIR__.'/partials.php';
require_admin();

$pdo = pdo();
$msg = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  if (isset($_POST['create'])) {
    $st = $pdo->prepare("INSERT INTO tournaments (name,location,start_date,end_date) VALUES (?,?,?,?)");
    $st->execute([trim($_POST['name']), trim($_POST['location']), $_POST['start_date'], $_POST['end_date']]);
    $msg = 'Tournament created.';
  } elseif (isset($_POST['update'])) {
    $st = $pdo->prepare("UPDATE tournaments SET name=?, location=?, start_date=?, end_date=? WHERE id=?");
    $st->execute([trim($_POST['name']), trim($_POST['location']), $_POST['start_date'], $_POST['end_date'], (int)$_POST['id']]);
    $msg = 'Tournament updated.';
  } elseif (isset($_POST['delete'])) {
    $st = $pdo->prepare("DELETE FROM tournaments WHERE id=?");
    $st->execute([(int)$_POST['id']]);
    $msg = 'Tournament deleted.';
  }
}

$rows = $pdo->query("SELECT * FROM tournaments ORDER BY start_date DESC")->fetchAll();
header_html('Manage Tournaments');
?>
<h2>Manage Tournaments</h2>
<?php if($msg):?><p class="flash"><?=$msg?></p><?php endif; ?>
<div class="grid">
  <div class="card">
    <h3>Create</h3>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>Name<input name="name" required></label>
      <label>Location<input name="location" required></label>
      <label>Start date<input type="date" name="start_date" required></label>
      <label>End date<input type="date" name="end_date" required></label>
      <button name="create" class="primary">Add</button>
    </form>
  </div>
  <div class="card">
    <h3>Existing</h3>
    <table class="list">
      <thead><tr><th>Name</th><th>Dates</th><th>Location</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?=h($r['name'])?></td>
          <td><?=h($r['start_date'])?> → <?=h($r['end_date'])?></td>
          <td><?=h($r['location'])?></td>
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
                <div class="actions">
                  <button name="update" class="primary">Save</button>
                  <button name="delete" class="danger" onclick="return confirm('Delete tournament (+ all signups)?')">Delete</button>
                </div>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php footer_html(); ?>
