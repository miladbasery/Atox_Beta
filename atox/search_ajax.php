<?php
require 'db.php'; 

header('Content-Type: application/json');

$q = $_GET['q'] ?? '';

if(empty(trim($q))) {
    echo json_encode([]);
    exit;
}

$search = "%{$q}%";

$stmt = $pdo->prepare("SELECT id,name , username, avatar FROM users WHERE name LIKE ? OR username LIKE ? LIMIT 10");
$stmt->execute([$search, $search]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($users);
?>
