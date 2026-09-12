<?php
require 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// ============ LIST (both roles) ============
if ($method === 'GET' && $action === 'list') {
    if (!currentRole()) json(['ok' => false, 'error' => 'Not authenticated'], 401);

    $rows = db()->query("
        SELECT id, amount, exp_date, category, description, photo_url, created_at
        FROM expenses
        ORDER BY exp_date DESC, id DESC
    ")->fetchAll();

    $total = db()->query("SELECT COALESCE(SUM(amount),0) FROM expenses")->fetchColumn();

    json([
        'ok' => true,
        'role' => currentRole(),
        'expenses' => $rows,
        'total' => (float)$total
    ]);
}

// ============ CREATE (owner OR driver — both allowed) ============
if ($method === 'POST' && $action === 'create') {
    if (!currentRole()) json(['ok' => false, 'error' => 'Not authenticated'], 401);

    $input = json_decode(file_get_contents('php://input'), true);
    $amount = (float)($input['amount'] ?? 0);
    $date   = $input['date'] ?? '';
    $cat    = trim($input['category'] ?? 'other');
    $desc   = trim($input['description'] ?? '');
    $photo  = trim($input['photo_url'] ?? '');

    if ($amount <= 0) json(['ok' => false, 'error' => 'Invalid amount']);
    if ($amount > 100000) json(['ok' => false, 'error' => 'Amount too large']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json(['ok' => false, 'error' => 'Invalid date']);
    if (strlen($desc) > 255) json(['ok' => false, 'error' => 'Description too long']);
    if (!in_array($cat, ['service','tyres','fuel','repairs','licence','insurance','other'])) {
        $cat = 'other';
    }
    if ($photo && !preg_match('#^uploads/[a-zA-Z0-9_\.\-]+$#', $photo)) {
        json(['ok' => false, 'error' => 'Invalid photo path']);
    }

    $stmt = db()->prepare("
        INSERT INTO expenses (amount, exp_date, category, description, photo_url)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$amount, $date, $cat, $desc, $photo]);

    json(['ok' => true, 'id' => db()->lastInsertId()]);
}

// ============ UPDATE (owner only) ============
if ($method === 'POST' && $action === 'update') {
    requireRole('owner');

    $input = json_decode(file_get_contents('php://input'), true);
    $id     = (int)($input['id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $date   = $input['date'] ?? '';
    $cat    = trim($input['category'] ?? 'other');
    $desc   = trim($input['description'] ?? '');
    $photo  = trim($input['photo_url'] ?? '');

    if (!$id) json(['ok' => false, 'error' => 'Missing id']);
    if ($amount <= 0) json(['ok' => false, 'error' => 'Invalid amount']);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json(['ok' => false, 'error' => 'Invalid date']);
    if (!in_array($cat, ['service','tyres','fuel','repairs','licence','insurance','other'])) {
        $cat = 'other';
    }

    if ($photo) {
        if (!preg_match('#^uploads/[a-zA-Z0-9_\.\-]+$#', $photo)) {
            json(['ok' => false, 'error' => 'Invalid photo path']);
        }
        $stmt = db()->prepare("
            UPDATE expenses
            SET amount = ?, exp_date = ?, category = ?, description = ?, photo_url = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $date, $cat, $desc, $photo, $id]);
    } else {
        $stmt = db()->prepare("
            UPDATE expenses
            SET amount = ?, exp_date = ?, category = ?, description = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $date, $cat, $desc, $id]);
    }

    json(['ok' => true]);
}

// ============ DELETE (owner only) ============
if ($method === 'POST' && $action === 'delete') {
    requireRole('owner');
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) json(['ok' => false, 'error' => 'Missing id']);

    // Also delete the photo file if exists
    $stmt = db()->prepare("SELECT photo_url FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row && $row['photo_url'] && preg_match('#^uploads/([a-zA-Z0-9_\.\-]+)$#', $row['photo_url'], $m)) {
        $file = __DIR__ . '/uploads/' . $m[1];
        if (file_exists($file)) @unlink($file);
    }

    $stmt = db()->prepare("DELETE FROM expenses WHERE id = ?");
    $stmt->execute([$id]);
    json(['ok' => true]);
}

json(['ok' => false, 'error' => 'Unknown action'], 400);