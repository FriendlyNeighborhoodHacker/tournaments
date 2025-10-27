<?php
require_once __DIR__.'/auth.php';
require_once __DIR__.'/lib/Users.php';
require_login();

header('Content-Type: application/json');

$u = current_user();
$query = trim($_GET['q'] ?? '');
$excludeIds = array_map('intval', $_GET['exclude'] ?? []);

// Return empty array if no search query
if ($query === '') {
    echo json_encode([]);
    exit;
}

// Tokenize the search query (split by spaces)
$tokens = array_filter(array_map('trim', explode(' ', $query)));

if (empty($tokens)) {
    echo json_encode([]);
    exit;
}

// Build WHERE clause for tokenized search
// Each token must match in either first_name OR last_name
$whereConditions = [];
$params = [];

foreach ($tokens as $token) {
    $whereConditions[] = "(first_name LIKE ? OR last_name LIKE ?)";
    $params[] = "%{$token}%";
    $params[] = "%{$token}%";
}

$whereClauses = implode(' AND ', $whereConditions);

// Exclude coaches always
$sql = "SELECT id, first_name, last_name, is_admin 
        FROM users 
        WHERE is_coach = 0 
        AND ({$whereClauses})";

// For non-admins, exclude current user (they're auto-included)
if (!$u['is_admin']) {
    $sql .= " AND id != ?";
    $params[] = $u['id'];
}

// Exclude already selected users
if (!empty($excludeIds)) {
    $placeholders = implode(',', array_fill(0, count($excludeIds), '?'));
    $sql .= " AND id NOT IN ({$placeholders})";
    $params = array_merge($params, $excludeIds);
}

$sql .= " ORDER BY last_name, first_name LIMIT 10";

$st = pdo()->prepare($sql);
$st->execute($params);
$results = $st->fetchAll();

// Format results for JSON response
$output = array_map(function($user) {
    return [
        'id' => (int)$user['id'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
        'is_admin' => (bool)$user['is_admin'],
        'display' => $user['last_name'] . ', ' . $user['first_name'] . ($user['is_admin'] ? ' (Admin)' : '')
    ];
}, $results);

echo json_encode($output);
