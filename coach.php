<?php
// Legacy redirect: coach.php -> upcoming_tournaments.php (preserve query string)
$qs = isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'] !== '' ? ('?'.$_SERVER['QUERY_STRING']) : '';
header('Location: /upcoming_tournaments.php'.$qs, true, 302);
exit;
