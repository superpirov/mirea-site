<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$file = __DIR__ . '/../socials.json';

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($file)) {
        respond(['ok' => true, 'exists' => false, 'socials' => []]);
    }
    $raw = @file_get_contents($file);
    $list = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($list)) {
        respond(['ok' => false, 'error' => 'Файл соцсетей повреждён'], 500);
    }
    respond(['ok' => true, 'exists' => true, 'socials' => array_values($list)]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['ok' => false, 'error' => 'Метод не поддерживается'], 405);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['socials']) || !is_array($data['socials'])) {
    respond(['ok' => false, 'error' => 'Некорректные данные'], 400);
}

$list = [];
foreach ($data['socials'] as $item) {
    if (!is_array($item)) {
        continue;
    }
    $name = trim(strip_tags((string)($item['name'] ?? '')));
    $url = trim((string)($item['url'] ?? ''));
    if ($url !== '' && !preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    if ($url === '' || strlen($url) > 500 || !preg_match('~^https?://~i', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
        respond(['ok' => false, 'error' => 'Некорректная ссылка соцсети'], 400);
    }
    $name = $name !== '' ? $name : (parse_url($url, PHP_URL_HOST) ?: 'Соцсеть');
    $name = function_exists('mb_substr') ? mb_substr($name, 0, 60, 'UTF-8') : substr($name, 0, 60);
    $list[] = ['name' => $name, 'url' => $url];
    if (count($list) >= 20) {
        break;
    }
}

$json = json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($json === false || @file_put_contents($file, $json, LOCK_EX) === false) {
    respond(['ok' => false, 'error' => 'Не удалось сохранить соцсети на сервере'], 500);
}
@chmod($file, 0644);

respond(['ok' => true, 'exists' => true, 'socials' => $list]);
