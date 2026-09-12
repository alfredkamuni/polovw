<?php
require 'config.php';

$input = json_decode(file_get_contents('php://input'), true);
$role = $input['role'] ?? '';
$pass = $input['password'] ?? '';

if (!in_array($role, ['owner', 'driver'])) {
    json(['ok' => false, 'error' => 'Invalid role']);
}

$stmt = db()->prepare("SELECT password_hash FROM users WHERE role = ?");
$stmt->execute([$role]);
$row = $stmt->fetch();

if (!$row || !password_verify($pass, $row['password_hash'])) {
    json(['ok' => false, 'error' => 'Wrong password']);
}

$_SESSION['role'] = $role;
$_SESSION['logged_in_at'] = time();

json(['ok' => true, 'role' => $role]);