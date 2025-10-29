<?php
/**
 * Test Error Page
 * This file generates various types of fatal errors for testing error handling
 */

// Include config to test error handler
require_once __DIR__.'/config.php';

// Get the error type from query parameter
$errorType = $_GET['type'] ?? 'fatal';

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Test Error Page</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .error-types { margin: 20px 0; }
        .error-types a { display: block; margin: 5px 0; padding: 10px; background: #f0f0f0; text-decoration: none; color: #333; }
        .error-types a:hover { background: #e0e0e0; }
    </style>
</head>
<body>
    <h1>Error Handling Test Page</h1>
    <p>Click a link below to trigger different types of errors:</p>
    
    <div class="error-types">
        <a href="?type=fatal">Fatal Error (undefined function)</a>
        <a href="?type=exception">Uncaught Exception</a>
        <a href="?type=division">Division by Zero Warning</a>
        <a href="?type=undefined">Undefined Variable Notice</a>
        <a href="?type=db">Database Error</a>
    </div>

<?php

echo "<h2>Testing: " . htmlspecialchars($errorType) . "</h2>";

switch ($errorType) {
    case 'fatal':
        echo "<p>About to call an undefined function...</p>";
        // This will cause a fatal error
        this_function_does_not_exist();
        break;
        
    case 'exception':
        echo "<p>About to throw an uncaught exception...</p>";
        throw new Exception("This is a test exception with detailed message");
        break;
        
    case 'division':
        echo "<p>About to divide by zero...</p>";
        $result = 10 / 0;
        echo "Result: $result";
        break;
        
    case 'undefined':
        echo "<p>About to use an undefined variable...</p>";
        echo $this_variable_is_not_defined;
        break;
        
    case 'db':
        echo "<p>About to cause a database error...</p>";
        $pdo = pdo();
        $pdo->query("SELECT * FROM nonexistent_table_xyz");
        break;
        
    default:
        echo "<p>No error triggered. Select an error type from the links above.</p>";
}

?>

</body>
</html>
