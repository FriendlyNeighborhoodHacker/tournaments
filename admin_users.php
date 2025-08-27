<?php
require_once __DIR__.'/partials.php';
require_admin();

require_once __DIR__.'/lib/UserManagement.php';
$pdo = pdo();
$msg = null;
$err = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  if (isset($_POST['create'])) {
    UserManagement::createAdmin($_POST);
    $msg = 'User created.';
  } elseif (isset($_POST['update'])) {
    $id = (int)$_POST['id'];
    if (!empty($_POST['password'])) {
      UserManagement::updateWithPassword($id, $_POST, $_POST['password']);
    } else {
      UserManagement::updateWithoutPassword($id, $_POST);
    }
    $msg = 'User updated.';
  } elseif (isset($_POST['delete'])) {
    try {
      UserManagement::delete((int)$_POST['id']);
      $msg = 'User deleted.';
    } catch (PDOException $e) {
      $err = 'Cannot delete user who is part of a signup (or referenced elsewhere). Remove their signups first.';
    }
  }
}

$rows = $pdo->query("SELECT * FROM users ORDER BY last_name, first_name")->fetchAll();
header_html('Manage Users');
?>
<h2>Manage Users</h2>
<?php if($msg):?><p class="flash"><?=$msg?></p><?php endif; ?>
<?php if($err):?><p class="error"><?=$err?></p><?php endif; ?>
  <div class="card">
    <h3>Create</h3>
    <div style="text-align:center;margin-bottom:8px;">
      <button id="toggleCreateBtn" type="button" class="button primary" onclick="toggleCreateUser()">Create New User</button>
    </div>
    <form id="createUserForm" method="post" class="stack" style="display:none">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>First name<input name="first_name" required></label>
      <label>Last name<input name="last_name" required></label>
      <label>Email<input type="email" name="email" required></label>
      <label>Phone<input name="phone"></label>
      <label>Password<input type="password" name="password" required></label>
      <label><input type="checkbox" name="is_coach" value="1"> Coach</label>
      <label><input type="checkbox" name="is_admin" value="1"> Admin</label>
      <button name="create" class="primary">Add User</button>
    </form>
  </div>
  <div class="card">
    <h3>Roster</h3>
    <table class="list">
      <thead><tr><th>Name</th><th>Email / Phone</th><th>Roles</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach($rows as $r): ?>
        <tr>
          <td><?=h($r['first_name'].' '.$r['last_name'])?></td>
          <td><?=h($r['email'])?><?php if($r['phone']) echo ' / '.h($r['phone']);?></td>
          <td><?= $r['is_admin']?'Admin ':'' ?><?= $r['is_coach']?'Coach':'' ?></td>
          <td>
            <details>
              <summary>Edit/Delete</summary>
              <form method="post" class="stack">
                <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
                <input type="hidden" name="id" value="<?=$r['id']?>">
                <label>First<input name="first_name" value="<?=h($r['first_name'])?>" required></label>
                <label>Last<input name="last_name" value="<?=h($r['last_name'])?>" required></label>
                <label>Email<input type="email" name="email" value="<?=h($r['email'])?>" required></label>
                <label>Phone<input name="phone" value="<?=h($r['phone'])?>"></label>
                <label>New password (optional)<input type="password" name="password"></label>
                <label><input type="checkbox" name="is_coach" value="1" <?= $r['is_coach']?'checked':'' ?>> Coach</label>
                <label><input type="checkbox" name="is_admin" value="1" <?= $r['is_admin']?'checked':'' ?>> Admin</label>
                <div class="actions">
                  <button name="update" class="primary">Save</button>
                  <button name="delete" class="danger" onclick="return confirm('Delete user?')">Delete</button>
                </div>
              </form>
            </details>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<script>
function toggleCreateUser() {
  const f = document.getElementById('createUserForm');
  const btn = document.getElementById('toggleCreateBtn');
  if (!f) return;
  const opening = (f.style.display === 'none' || !f.style.display);
  f.style.display = opening ? 'block' : 'none';
  if (btn) btn.textContent = opening ? 'Hide Create User Form' : 'Create New User';
}
</script>
<?php footer_html(); ?>
