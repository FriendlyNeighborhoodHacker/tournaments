<?php // login.php
require_once __DIR__.'/partials.php';

// If already logged in, go home
if (current_user()) { header('Location: /index.php'); exit; }

$error = null;
$created = !empty($_GET['created']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass  = $_POST['password'] ?? '';
  $st = pdo()->prepare("SELECT * FROM users WHERE email=?");
  $st->execute([$email]);
  $u = $st->fetch();
  if ($u && password_verify($pass, $u['password_hash'])) {
    $_SESSION['uid'] = $u['id'];
    header('Location: /index.php'); exit;
  } else $error = 'Invalid email or password.';
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - <?=h(APP_NAME)?></title><link rel="stylesheet" href="/styles.css"></head>
<body class="auth">
  <div class="card">
    <h1>Login</h1>
    <?php if (!empty($created)): ?><p class="flash">Account created. Please sign in.</p><?php endif; ?>
    <?php if($error): ?><p class="error"><?=h($error)?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>School Email:<input type="email" name="email" required></label>
      <label>Password<input type="password" name="password" required></label>
      <button type="submit">Sign in</button>
    </form>
    <p class="small" style="margin-top:0.75rem;">Don&#39;t have an account? <a href="/register.php">Create one</a></p>
  </div>
</body></html>
