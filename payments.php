<?php
require 'config.php';

const MIN_PAYMENT = 600;

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? 'list';

// ============ LIST ============
if ($method === 'GET' && $action === 'list') {
    if (!currentRole()) json(['ok' => false, 'error' => 'Not authenticated'], 401);

    $rows = db()->query("
        SELECT id, amount, pay_date, note, status, owner_note, reject_reason,
               created_at, decided_at, edited_at, edit_reason, photo_url
        FROM payments
        ORDER BY pay_date DESC, id DESC
    ")->fetchAll();

    $accepted = db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='accepted'")->fetchColumn();
    $pending  = db()->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE status='pending'")->fetchColumn();

    json([
        'ok' => true,
        'role' => currentRole(),
        'payments' => $rows,
        'total_accepted' => (float)$accepted,
        'total_pending'  => (float)$pending,
        'min_payment'    => MIN_PAYMENT
    ]);
}

// ============ CREATE (driver only) ============
if ($method === 'POST' && $action === 'create') {
    requireRole('driver');

    $input = json_decode(file_get_contents('php://input'), true);
    $amount = (float)($input['amount'] ?? 0);
    $date   = $input['date'] ?? '';
    $note   = trim($input['note'] ?? '');
    $photo  = trim($input['photo_url'] ?? '');

    if ($amount < MIN_PAYMENT) json(['ok' => false, 'error' => 'Minimum payment is R' . MIN_PAYMENT]);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json(['ok' => false, 'error' => 'Invalid date']);
    if (strlen($note) > 255) json(['ok' => false, 'error' => 'Note too long']);
    if ($photo && !preg_match('#^uploads/[a-zA-Z0-9_\.\-]+$#', $photo)) {
        json(['ok' => false, 'error' => 'Invalid photo path']);
    }

    $stmt = db()->prepare("
        INSERT INTO payments (amount, pay_date, note, status, driver_id, photo_url)
        VALUES (?, ?, ?, 'pending', 'pfungwa', ?)
    ");
    $stmt->execute([$amount, $date, $note, $photo]);

    json(['ok' => true, 'id' => db()->lastInsertId()]);
}

// ============ DECIDE (owner only) ============
if ($method === 'POST' && $action === 'decide') {
    requireRole('owner');

    $input = json_decode(file_get_contents('php://input'), true);
    $id        = (int)($input['id'] ?? 0);
    $status    = $input['status'] ?? '';
    $ownerNote = trim($input['owner_note'] ?? '');
    $reason    = trim($input['reject_reason'] ?? '');

    if (!in_array($status, ['accepted', 'rejected'])) json(['ok' => false, 'error' => 'Invalid status']);
    if (!$id) json(['ok' => false, 'error' => 'Missing id']);

    $stmt = db()->prepare("
        UPDATE payments
        SET status = ?, owner_note = ?, reject_reason = ?, decided_at = NOW()
        WHERE id = ? AND status = 'pending'
    ");
    $stmt->execute([$status, $ownerNote, $reason, $id]);

    if ($stmt->rowCount() === 0) json(['ok' => false, 'error' => 'Payment not found or already decided']);
    json(['ok' => true]);
}

// ============ EDIT (owner only) ============
if ($method === 'POST' && $action === 'edit') {
    requireRole('owner');

    $input = json_decode(file_get_contents('php://input'), true);
    $id     = (int)($input['id'] ?? 0);
    $amount = (float)($input['amount'] ?? 0);
    $date   = $input['date'] ?? '';
    $note   = trim($input['note'] ?? '');
    $reason = trim($input['edit_reason'] ?? 'Correction');
    $photo  = trim($input['photo_url'] ?? '');

    if (!$id) json(['ok' => false, 'error' => 'Missing id']);
    if ($amount < MIN_PAYMENT) json(['ok' => false, 'error' => 'Amount below R' . MIN_PAYMENT]);
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) json(['ok' => false, 'error' => 'Invalid date']);

    // If photo is empty, keep existing
    if ($photo) {
        if (!preg_match('#^uploads/[a-zA-Z0-9_\.\-]+$#', $photo)) {
            json(['ok' => false, 'error' => 'Invalid photo path']);
        }
        $stmt = db()->prepare("
            UPDATE payments
            SET amount = ?, pay_date = ?, note = ?, photo_url = ?, edited_at = NOW(), edit_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $date, $note, $photo, $reason, $id]);
    } else {
        $stmt = db()->prepare("
            UPDATE payments
            SET amount = ?, pay_date = ?, note = ?, edited_at = NOW(), edit_reason = ?
            WHERE id = ?
        ");
        $stmt->execute([$amount, $date, $note, $reason, $id]);
    }

    json(['ok' => true]);
}

// ============ DELETE (owner only) ============
if ($method === 'POST' && $action === 'delete') {
    requireRole('owner');
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) json(['ok' => false, 'error' => 'Missing id']);
    $stmt = db()->prepare("DELETE FROM payments WHERE id = ?");
    $stmt->execute([$id]);
    json(['ok' => true]);
}

// ============ CANCEL OWN PENDING (driver) ============
if ($method === 'POST' && $action === 'cancel') {
    requireRole('driver');
    $input = json_decode(file_get_contents('php://input'), true);
    $id = (int)($input['id'] ?? 0);
    if (!$id) json(['ok' => false, 'error' => 'Missing id']);
    $stmt = db()->prepare("DELETE FROM payments WHERE id = ? AND status = 'pending'");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) json(['ok' => false, 'error' => 'Cannot cancel — already decided']);
    json(['ok' => true]);
}

json(['ok' => false, 'error' => 'Unknown action'], 400);