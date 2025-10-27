<?php
if (file_exists(__DIR__ . '/config.local.php')) {
  require(__DIR__ . '/config.local.php');
} else {
  echo 'Needs config.local.php file';
  exit();
}

// Simple fatal error handler - prints errors to screen in addition to normal logging
function simple_fatal_error_handler() {
  $error = error_get_last();
  
  if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
    $error_types = [
      E_ERROR => 'Fatal Error',
      E_PARSE => 'Parse Error',
      E_CORE_ERROR => 'Core Error',
      E_COMPILE_ERROR => 'Compile Error',
    ];
    
    $error_type = $error_types[$error['type']] ?? 'Fatal Error';
    
    // Print error to browser
    echo "\n\n";
    echo "=================================================\n";
    echo strtoupper($error_type) . "\n";
    echo "=================================================\n";
    echo "Message: " . $error['message'] . "\n";
    echo "File:    " . $error['file'] . "\n";
    echo "Line:    " . $error['line'] . "\n";
    echo "=================================================\n";
  }
}

// Register shutdown function to catch fatal errors
register_shutdown_function('simple_fatal_error_handler');

session_start();

function pdo() {
  static $pdo;
  if (!$pdo) {
    $dsn = 'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  }
  return $pdo;
}

function csrf_token() {
  if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
  return $_SESSION['csrf'];
}
function require_csrf() {
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ok = isset($_POST['csrf']) && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
    if (!$ok) { http_response_code(400); exit('Bad CSRF'); }
  }
}
