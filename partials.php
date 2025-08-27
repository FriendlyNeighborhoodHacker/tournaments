<?php
require_once __DIR__.'/auth.php';

function h($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

function header_html($title) {
  $u = current_user();
  $nav = '';
  if ($u) {
    $cur = basename($_SERVER['SCRIPT_NAME'] ?? '');
    $L = function(string $path, string $label) use ($cur) {
      $active = ($cur === basename($path));
      $a = '<a href="'.h($path).'">'.h($label).'</a>';
      return $active ? '<strong>'.$a.'</strong>' : $a;
    };
    $nav .= $L('/index.php','Home').' | ';
    $nav .= $L('/upcoming_tournaments.php','Upcoming Tournaments').' | ';
    if ($u['is_admin']) { $nav .= $L('/admin_tournaments.php','Tournaments').' | '; }
    $nav .= $L('/judges.php','Judges').' | ';
    if ($u['is_admin']) $nav .= $L('/admin_users.php','Users').' | '.$L('/admin_settings.php','Settings').' | ';
    $nav .= $L('/change_password.php','Change Password').' | '.$L('/logout.php','Log out');
  }
  echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
  echo '<title>'.h($title).' - '.h(APP_NAME).'</title>';
  $cssVer = @filemtime(__DIR__.'/styles.css');
  if (!$cssVer) { $cssVer = date('Ymd'); }
  echo '<link rel="stylesheet" href="/styles.css?v='.h($cssVer).'">';
  echo '</head><body><header><h1>'.h(APP_NAME).'</h1><nav>'.$nav.'</nav></header><main>';
}

function footer_html() {
  $jsVer = @filemtime(__DIR__.'/main.js');
  if (!$jsVer) { $jsVer = date('Ymd'); }
  echo '</main><script src="/main.js?v='.h($jsVer).'"></script></body></html>';
}
