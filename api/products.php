<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Mirea-Products-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$file = __DIR__ . '/../products.json';
$writeKey = 'mirea_catalog_9f4c2a7e1b6d';

function respond($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function textValue($value, $maxLength) {
    $value = trim(strip_tags((string)$value));
    return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength, 'UTF-8') : substr($value, 0, $maxLength);
}

function moneyValue($value) {
    if (is_string($value)) {
        $value = str_replace(array(' ', ','), array('', '.'), trim($value));
    }
    if (!is_numeric($value)) {
        return null;
    }
    $number = round((float)$value, 2);
    if ($number < 0 || $number > 10000000) {
        return null;
    }
    return $number;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!file_exists($file)) {
        respond(array('ok' => true, 'exists' => false, 'products' => array()));
    }
    $raw = @file_get_contents($file);
    $list = $raw !== false ? json_decode($raw, true) : null;
    if (!is_array($list)) {
        respond(array('ok' => false, 'error' => 'Файл каталога повреждён'), 500);
    }
    respond(array('ok' => true, 'exists' => true, 'products' => array_values($list)));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(array('ok' => false, 'error' => 'Метод не поддерживается'), 405);
}

$providedKey = isset($_SERVER['HTTP_X_MIREA_PRODUCTS_KEY']) ? (string)$_SERVER['HTTP_X_MIREA_PRODUCTS_KEY'] : '';
if (!hash_equals($writeKey, $providedKey)) {
    respond(array('ok' => false, 'error' => 'Нет доступа к публикации каталога'), 403);
}

$raw = file_get_contents('php://input');
if (strlen($raw) > 1048576) {
    respond(array('ok' => false, 'error' => 'Слишком большой каталог'), 413);
}
$data = json_decode($raw, true);
if (!is_array($data) || !isset($data['products']) || !is_array($data['products'])) {
    respond(array('ok' => false, 'error' => 'Некорректные данные каталога'), 400);
}
if (count($data['products']) > 200) {
    respond(array('ok' => false, 'error' => 'В каталоге может быть не больше 200 товаров'), 400);
}

$list = array();
$seen = array();
foreach ($data['products'] as $item) {
    if (!is_array($item)) {
        continue;
    }
    $id = textValue($item['id'] ?? '', 80);
    if ($id === '' || !preg_match('/^[a-z0-9_-]+$/i', $id) || isset($seen[$id])) {
        continue;
    }
    $line = textValue($item['line'] ?? 'body', 20);
    if ($line !== 'body' && $line !== 'auto') {
        continue;
    }
    $name = textValue($item['name'] ?? '', 100);
    $subtitle = textValue($item['subtitle'] ?? '', 160);
    if ($name === '' || $subtitle === '') {
        continue;
    }
    $price = moneyValue($item['price'] ?? null);
    if ($price === null) {
        continue;
    }
    $oldPrice = moneyValue($item['oldPrice'] ?? 0);
    if ($oldPrice === null) {
        $oldPrice = 0;
    }
    $color = textValue($item['color'] ?? '#1e3e35', 20);
    $accent = textValue($item['accent'] ?? '#dfa94d', 20);
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
        $color = '#1e3e35';
    }
    if (!preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent)) {
        $accent = '#dfa94d';
    }
    $img = textValue($item['img'] ?? '', 200);
    if ($img !== '' && (!preg_match('/^[a-zA-Z0-9._\/-]+$/', $img) || strpos($img, '..') !== false)) {
        $img = '';
    }
    $product = array(
        'id' => $id,
        'sku' => textValue($item['sku'] ?? $id, 60),
        'line' => $line,
        'category' => textValue($item['category'] ?? '', 40),
        'name' => $name,
        'subtitle' => $subtitle,
        'desc' => textValue($item['desc'] ?? '', 1200),
        'volume' => textValue($item['volume'] ?? '', 40),
        'price' => $price,
        'oldPrice' => $oldPrice,
        'badge' => textValue($item['badge'] ?? '', 20),
        'color' => $color,
        'accent' => $accent,
        'scent' => textValue($item['scent'] ?? '', 200),
        'img' => $img,
        'extra' => textValue($item['extra'] ?? '', 120)
    );
    $list[] = $product;
    $seen[$id] = true;
}

if (count($list) === 0 && count($data['products']) > 0) {
    respond(array('ok' => false, 'error' => 'В каталоге нет корректных товаров'), 400);
}

$json = json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if ($json === false || @file_put_contents($file, $json, LOCK_EX) === false) {
    respond(array('ok' => false, 'error' => 'Не удалось сохранить каталог на сервере'), 500);
}
@chmod($file, 0644);

respond(array('ok' => true, 'exists' => true, 'products' => $list));
