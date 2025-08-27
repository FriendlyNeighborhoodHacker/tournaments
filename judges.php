<?php
require_once __DIR__.'/partials.php';
require_once __DIR__.'/lib/Judges.php';
require_once __DIR__.'/lib/JudgeManagement.php';
require_login();

$pdo = pdo();
$u = current_user();
$isAdmin = (bool)$u['is_admin'];

$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  try {
    if (isset($_POST['create'])) {
      $first = trim($_POST['first_name'] ?? '');
      $last  = trim($_POST['last_name'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      if ($first === '' || $last === '') throw new RuntimeException('First and last name are required.');
      $sponsorId = $isAdmin ? (int)($_POST['sponsor_id'] ?? 0) : (int)$u['id'];
      if ($sponsorId <= 0) throw new RuntimeException('Sponsor is required.');
      JudgeManagement::create([
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => ($email !== '' ? $email : null),
        'phone'      => ($phone !== '' ? $phone : null),
        'sponsor_id' => $sponsorId
      ]);
      $msg = 'Judge created.';
    } elseif (isset($_POST['update'])) {
      $id    = (int)($_POST['id'] ?? 0);
      $first = trim($_POST['first_name'] ?? '');
      $last  = trim($_POST['last_name'] ?? '');
      $email = trim($_POST['email'] ?? '');
      $phone = trim($_POST['phone'] ?? '');
      if ($first === '' || $last === '') throw new RuntimeException('First and last name are required.');
      // Authorization: admin OR sponsor of judge
      $j = Judges::find($id);
      if (!$j) throw new RuntimeException('Judge not found.');
      if (!$isAdmin && (int)$j['sponsor_id'] !== (int)$u['id']) throw new RuntimeException('Not allowed.');
      $data = [
        'first_name' => $first,
        'last_name'  => $last,
        'email'      => ($email !== '' ? $email : null),
        'phone'      => ($phone !== '' ? $phone : null),
      ];
      if ($isAdmin) {
        $data['sponsor_id'] = (int)($_POST['sponsor_id'] ?? $j['sponsor_id']);
      }
      JudgeManagement::update($id, $data);
      $msg = 'Judge updated.';
    } elseif (isset($_POST['delete'])) {
      $id = (int)($_POST['id'] ?? 0);
      $j = Judges::find($id);
      if (!$j) throw new RuntimeException('Judge not found.');
      if (!$isAdmin && (int)$j['sponsor_id'] !== (int)$u['id']) throw new RuntimeException('Not allowed.');
      JudgeManagement::delete($id);
      $msg = 'Judge deleted.';
    }
  } catch (Throwable $e) {
    $err = $e->getMessage();
  }
}

$rows = Judges::listAll();
$sponsors = $pdo->query("SELECT id, first_name, last_name FROM users ORDER BY last_name, first_name")->fetchAll();

header_html('Judges');
?>
<h2>Judges</h2>
<?php if($msg):?><p class="flash"><?=h($msg)?></p><?php endif; ?>
<?php if($err):?><p class="error"><?=h($err)?></p><?php endif; ?>

<div class="grid">
  <div class="card">
    <h3>Create</h3>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>First name<input name="first_name" required></label>
      <label>Last name<input name="last_name" required></label>
      <label>Email (optional)<input type="email" name="email"></label>
      <label>Phone (optional)<input name="phone"></label>
      <?php if ($isAdmin): ?>
        <label>Sponsor
          <select name="sponsor_id" required>
            <?php foreach($sponsors as $sp): ?>
              <option value="<?=$sp['id']?>"><?=h($sp['last_name'].', '.$sp['first_name'])?></option>
            <?php endforeach; ?>
          </select>
        </label>
      <?php else: ?>
        <input type="hidden" name="sponsor_id" value="<?=h($u['id'])?>">
        <small class="small">Sponsor: <?=h($u['first_name'].' '.$u['last_name'])?></small>
      <?php endif; ?>
      <button name="create" class="primary">Add Judge</button>
    </form>
  </div>

  <div class="card">
    <h3>All Judges</h3>
    <table class="list">
      <thead><tr><th>Name</th><th>Email / Phone</th><th>Sponsor</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): 
        $canEdit = $isAdmin || ((int)$r['sponsor_id'] === (int)$u['id']);
      ?>
        <tr>
          <td><?=h($r['last_name'].', '.$r['first_name'])?></td>
          <td><?=h($r['email'] ?? '')?><?php if(!empty($r['phone'])) echo ' / '.h($r['phone']); ?></td>
          <td><?=h(($r['s_ln'] ?? '').', '.($r['s_fn'] ?? ''))?></td>
          <td>
            <?php if($canEdit): ?>
            <details>
              <summary>Edit / Delete</summary>
              <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="id" value="<?=$r['id']?>">
                <label>First<input name="first_name" value="<?=h($r['first_name'])?>" required></label>
                <label>Last<input name="last_name" value="<?=h($r['last_name'])?>" required></label>
                <label>Email<input type="email" name="email" value="<?=h($r['email'])?>"></label>
                <label>Phone<input name="phone" value="<?=h($r['phone'])?>"></label>
                <?php if ($isAdmin): ?>
                  <label>Sponsor
                    <select name="sponsor_id" required>
                      <?php foreach($sponsors as $sp): ?>
                        <option value="<?=$sp['id']?>" <?= ((int)$sp['id']===(int)$r['sponsor_id'])?'selected':'' ?>>
                          <?=h($sp['last_name'].', '.$sp['first_name'])?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </label>
                <?php endif; ?>
                <div class="actions">
                  <button name="update" class="primary">Save</button>
                  <button name="delete" class="danger" onclick="return confirm('Delete judge (and their signup links)?')">Delete</button>
                </div>
              </form>
            </details>
            <?php else: ?>
              <small class="small">No actions</small>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php footer_html(); ?>
