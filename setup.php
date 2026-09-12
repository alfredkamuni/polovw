<?php
require 'config.php';

// Only allow if no users exist yet
$count = db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
if ($count > 0) {
    json(['ok' => false, 'error' => 'Already set up. Delete users table to reset.']);
}

$input = json_decode(file_get_contents('php://input'), true);
$owner  = trim($input['owner'] ?? '');
$driver = trim($input['driver'] ?? '');

if (strlen($owner) < 4 || strlen($driver) < 4) {
    json(['ok' => false, 'error' => 'Passwords must be at least 4 characters']);
}
if ($owner === $driver) {
    json(['ok' => false, 'error' => 'Owner and driver passwords must be different']);
}

$stmt = db()->prepare("INSERT INTO users (role, password_hash) VALUES (?, ?)");
$stmt->execute(['owner',  password_hash($owner, PASSWORD_DEFAULT)]);
$stmt->execute(['driver', password_hash($driver, PASSWORD_DEFAULT)]);

json(['ok' => true, 'message' => 'Accounts created']);