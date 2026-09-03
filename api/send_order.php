<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }

// Config
$to = 'order@mireacare.ru';
$from = 'noreply@mireacare.ru'; // должен совпадать с доменом на Timeweb
$subject = 'Новый заказ MIREA — ' . ($data['id'] ?? date('Y-m-d H:i'));

$name = htmlspecialchars($data['name'] ?? '—');
$phone = htmlspecialchars($data['phone'] ?? '—');
$email = htmlspecialchars($data['email'] ?? '—');
$address = htmlspecialchars($data['address'] ?? '—');
$comment = htmlspecialchars($data['comment'] ?? '');
$total = intval($data['total'] ?? 0);
$orderId = htmlspecialchars($data['id'] ?? uniqid('ORD-'));
$date = htmlspecialchars($data['date'] ?? date('c'));

$cartHtml = '';
$cartText = '';
if (!empty($data['cart']) && is_array($data['cart'])) {
    foreach ($data['cart'] as $item) {
        $iname = htmlspecialchars($item['name'] ?? $item['id'] ?? 'Товар');
        $qty = intval($item['qty'] ?? 1);
        $price = intval($item['price'] ?? 0);
        $sum = $qty * $price;
        $cartHtml .= "<tr><td style='padding:8px;border:1px solid #e5e7eb'>{$iname}</td><td style='padding:8px;border:1px solid #e5e7eb;text-align:center'>{$qty}</td><td style='padding:8px;border:1px solid #e5e7eb;text-align:right'>{$price} ₽</td><td style='padding:8px;border:1px solid #e5e7eb;text-align:right'><b>{$sum} ₽</b></td></tr>";
        $cartText .= "- {$iname} × {$qty} = {$sum} ₽\n";
    }
}

$bodyHtml = "
<html><body style='font-family:Arial,sans-serif;color:#1e3e35'>
<div style='max-width:600px;margin:0 auto;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden'>
<div style='background:#1e3e35;color:#fff;padding:20px'>
  <div style='font-size:11px;letter-spacing:0.14em;opacity:0.6'>MIREA CARE & CHEMISTRY</div>
  <div style='font-size:22px;font-weight:700;margin-top:6px'>Новый заказ {$orderId}</div>
  <div style='opacity:0.7;font-size:13px;margin-top:4px'>{$date}</div>
</div>
<div style='padding:20px'>
  <h3 style='margin:0 0 12px'>Клиент</h3>
  <table style='width:100%;font-size:14px;line-height:1.6'>
    <tr><td style='color:#6b7280;width:120px'>Имя</td><td><b>{$name}</b></td></tr>
    <tr><td style='color:#6b7280'>Телефон</td><td><a href='tel:{$phone}'>{$phone}</a></td></tr>
    <tr><td style='color:#6b7280'>Email</td><td>{$email}</td></tr>
    <tr><td style='color:#6b7280'>Адрес</td><td>{$address}</td></tr>
    <tr><td style='color:#6b7280'>Комментарий</td><td>{$comment}</td></tr>
  </table>
  <h3 style='margin:20px 0 12px'>Состав заказа</h3>
  <table style='width:100%;border-collapse:collapse;font-size:13px'>
    <tr style='background:#f4f7f5'><th style='padding:8px;border:1px solid #e5e7eb;text-align:left'>Товар</th><th style='padding:8px;border:1px solid #e5e7eb'>Кол-во</th><th style='padding:8px;border:1px solid #e5e7eb'>Цена</th><th style='padding:8px;border:1px solid #e5e7eb'>Сумма</th></tr>
    {$cartHtml}
    <tr><td colspan='3' style='padding:8px;border:1px solid #e5e7eb;text-align:right'><b>Итого</b></td><td style='padding:8px;border:1px solid #e5e7eb;text-align:right;background:#fdf8ed'><b>{$total} ₽</b></td></tr>
  </table>
  <p style='font-size:12px;color:#6b7280;margin-top:16px'>Письмо отправлено с сайта mireacare.ru • Ответить клиенту можно напрямую на {$email} или по телефону {$phone}</p>
</div>
</div>
</body></html>
";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=utf-8\r\n";
$headers .= "From: MIREA <{$from}>\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$sent = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $bodyHtml, $headers);

// Also save to file for admin
@file_put_contents(__DIR__ . '/../data/orders.log', date('Y-m-d H:i:s') . " | {$orderId} | {$name} | {$phone} | {$total} ₽\n", FILE_APPEND);
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
$ordersFile = $dataDir . '/orders.json';
$orders = [];
if (file_exists($ordersFile)) { $orders = json_decode(file_get_contents($ordersFile), true) ?: []; }
$orders[] = $data;
@file_put_contents($ordersFile, json_encode($orders, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

echo json_encode(['ok'=>true, 'sent'=>$sent, 'id'=>$orderId]);
