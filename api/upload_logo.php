<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$uploadDir = __DIR__ . '/../';
$targetFile = $uploadDir . 'logo.png';

if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    if (file_exists($targetFile)) {
        @unlink($targetFile);
    }
    echo json_encode(['ok'=>true]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false, 'error'=>'Метод не поддерживается']);
    exit;
}

if (!isset($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Файл не загружен или ошибка загрузки']);
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $_FILES['logo']['tmp_name']);
finfo_close($finfo);
$allowed = ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'];
if (!in_array($mime, $allowed)) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Недопустимый формат. Только PNG, JPEG, WebP, SVG']);
    exit;
}
if ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok'=>false, 'error'=>'Файл слишком большой. Максимум 2 МБ']);
    exit;
}

if (!move_uploaded_file($_FILES['logo']['tmp_name'], $targetFile)) {
    http_response_code(500);
    echo json_encode(['ok'=>false, 'error'=>'Не удалось сохранить файл на сервере']);
    exit;
}

echo json_encode(['ok'=>true, 'url'=>'logo.png?v='.time()]);