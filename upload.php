<?php
require 'config.php';

// Must be logged in
if (!currentRole()) {
    json(['ok' => false, 'error' => 'Not authenticated'], 401);
}

// Config
const MAX_SIZE = 2 * 1024 * 1024;        // 2 MB
const UPLOAD_DIR = __DIR__ . '/uploads/';
const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/heic', 'image/heif'];

// Ensure uploads folder exists
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json(['ok' => false, 'error' => 'Use POST'], 405);
}

if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
    $code = $_FILES['photo']['error'] ?? 'none';
    json(['ok' => false, 'error' => 'Upload failed (code ' . $code . ')']);
}

$f = $_FILES['photo'];

// Size check
if ($f['size'] > MAX_SIZE) {
    json(['ok' => false, 'error' => 'Photo too large. Max 2 MB.']);
}
if ($f['size'] < 100) {
    json(['ok' => false, 'error' => 'File too small']);
}

// Type check via finfo (trust the actual file, not the extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $f['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_TYPES)) {
    json(['ok' => false, 'error' => 'Only images allowed (JPG, PNG, WEBP)']);
}

// Build safe filename
$ext = match($mime) {
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/heic', 'image/heif' => 'heic',
    default      => 'jpg'
};

$name = 'ph_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
$target = UPLOAD_DIR . $name;

if (!move_uploaded_file($f['tmp_name'], $target)) {
    json(['ok' => false, 'error' => 'Could not save file']);
}

// Return relative URL
json(['ok' => true, 'url' => 'uploads/' . $name]);