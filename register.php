<?php // register.php
require_once __DIR__.'/partials.php';

// If already logged in, go home
if (current_user()) { header('Location: /index.php'); exit; }

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  require_csrf();
  $first = trim($_POST['first_name'] ?? '');
  $last  = trim($_POST['last_name'] ?? '');
  $email = strtolower(trim($_POST['email'] ?? ''));
  $phone = trim($_POST['phone'] ?? '');
  $pass  = $_POST['password'] ?? '';

  // Validation
  if ($first === '') $errors[] = 'First name is required.';
  if ($last === '')  $errors[] = 'Last name is required.';
  if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid email is required.';
  } else {
    // Must end with hackleyschool.org (supports subdomains like students.hackleyschool.org)
    if (!preg_match('/@([^.@]+\.)*hackleyschool\.org\z/i', $email)) {
      $errors[] = 'Email must be a hackleyschool.org address.';
    }
  }
  if (strlen($pass) < 8) $errors[] = 'Password must be at least 8 characters.';

  if (!$errors) {
    try {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $st = pdo()->prepare('INSERT INTO users (first_name,last_name,email,phone,password_hash,is_coach,is_admin) VALUES (?,?,?,?,?,?,?)');
      $st->execute([$first, $last, $email, $phone !== '' ? $phone : null, $hash, 0, 0]);
      header('Location: /login.php?created=1'); exit;
    } catch (PDOException $e) {
      // Likely duplicate email (unique constraint)
      $errors[] = 'An account with that email already exists.';
    } catch (Throwable $e) {
      $errors[] = 'Failed to create account.';
    }
  }
}
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Register - <?=h(APP_NAME)?></title><link rel="stylesheet" href="/styles.css"></head>
<body class="auth">
  <div class="card">
    <h1>Create Account</h1>
    <?php if(!empty($errors)): ?>
      <p class="error"><?=nl2br(h(implode("\n", $errors)))?></p>
    <?php endif; ?>
    <form method="post" class="stack">
      <input type="hidden" name="csrf" value="<?=h(csrf_token())?>">
      <label>First name<input name="first_name" value="<?=h($_POST['first_name'] ?? '')?>" required></label>
      <label>Last name<input name="last_name" value="<?=h($_POST['last_name'] ?? '')?>" required></label>
      <label>Email<input type="email" name="email" value="<?=h($_POST['email'] ?? '')?>" required></label>
      <label>Phone<input type="tel" name="phone" value="<?=h($_POST['phone'] ?? '')?>"></label>
      <label>Password<input type="password" name="password" required minlength="8" placeholder="At least 8 characters"></label>
      <div class="actions">
        <button type="submit" class="primary">Create Account</button>
        <a class="button" href="/login.php">Cancel</a>
      </div>
    </form>
    <p class="small" style="margin-top:0.75rem;">Already have an account? <a href="/login.php">Sign in</a></p>
  </div>
</body></html>
