<?php
require_once __DIR__.'/auth.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function header_html($title) {
  $u = current_user();
  $nav = '';
  if ($u) {
    $nav .= '<a href="/index.php">Home</a> | ';
    $nav .= '<a href="/upcoming_tournaments.php">Upcoming Tournaments</a> | ';
    $nav .= '<a href="/admin_tournaments.php">Tournaments</a> | <a href="/judges.php">Judges</a> | ';
    if ($u['is_admin']) $nav .= '<a href="/admin_users.php">Users</a> | <a href="/admin_settings.php">Settings</a> | ';
    $nav .= '<a href="/change_password.php">Change Password</a> | <a href="/logout.php">Log out</a>';
  }
  echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<title>'.h($title).' - '.h(APP_NAME).'</title>';
  echo '<link rel="stylesheet" href="/styles.css">';
  echo '</head><body><header><h1>'.h(APP_NAME).'</h1><nav>'.$nav.'</nav></header><main>';
}

function footer_html() {
  echo '</main><script src="/main.js"></script></body></html>';
}
