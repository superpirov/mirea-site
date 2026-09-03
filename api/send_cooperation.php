<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$input = file_get_contents('php://input');
$data = json_decode($input, true);
if (!$data) { http_response_code(400); echo json_encode(['error'=>'Invalid JSON']); exit; }

$to = 'cooperation@mireacare.ru';
$from = 'noreply@mireacare.ru';
$subject = 'Запрос оптовых закупок MIREA — ' . htmlspecialchars($data['name'] ?? '');

$name = htmlspecialchars($data['name'] ?? '—');
$phone = htmlspecialchars($data['phone'] ?? '—');
$email = htmlspecialchars($data['email'] ?? '—');
$company = htmlspecialchars($data['company'] ?? '—');
$message = nl2br(htmlspecialchars($data['message'] ?? ''));
$date = htmlspecialchars($data['date'] ?? date('c'));

$bodyHtml = "
<html><body style='font-family:Arial,sans-serif;color:#1e3e35'>
<div style='max-width:600px;margin:0 auto;border:1px solid #e5e7eb;border-radius:16px;overflow:hidden'>
<div style='background:#e8b44f;color:#1e3e35;padding:20px'>
  <div style='font-size:11px;letter-spacing:0.14em;opacity:0.7;font-weight:700'>ОПТОВЫЙ ЗАПРОС</div>
  <div style='font-size:22px;font-weight:800;margin-top:6px'>Новый запрос сотрудничества</div>
  <div style='opacity:0.7;font-size:13px;margin-top:4px'>{$date}</div>
</div>
<div style='padding:20px'>
  <table style='width:100%;font-size:14px;line-height:1.7'>
    <tr><td style='color:#6b7280;width:140px'>Имя</td><td><b>{$name}</b></td></tr>
    <tr><td style='color:#6b7280'>Телефон</td><td><a href='tel:{$phone}'>{$phone}</a></td></tr>
    <tr><td style='color:#6b7280'>Email</td><td>{$email}</td></tr>
    <tr><td style='color:#6b7280'>Компания / Город</td><td>{$company}</td></tr>
  </table>
  <div style='margin-top:16px;background:#f4f7f5;border-radius:12px;padding:14px'>
    <div style='font-size:12px;letter-spacing:0.1em;font-weight:700;color:#7a9d8f'>СООБЩЕНИЕ</div>
    <div style='font-size:14px;line-height:1.6;margin-top:6px'>{$message}</div>
  </div>
  <p style='font-size:12px;color:#6b7280;margin-top:16px'>Ответьте клиенту на {$email} или {$phone} • Заявка с mireacare.ru</p>
</div>
</div>
</body></html>
";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/html; charset=utf-8\r\n";
$headers .= "From: MIREA <{$from}>\r\n";
$headers .= "Reply-To: {$email}\r\n";

$sent = @mail($to, '=?UTF-8?B?'.base64_encode($subject).'?=', $bodyHtml, $headers);

@file_put_contents(__DIR__ . '/../data/leads.log', date('Y-m-d H:i:s') . " | {$name} | {$phone} | {$email} | {$company}\n", FILE_APPEND);
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) @mkdir($dataDir, 0755, true);
$leadsFile = $dataDir . '/leads.json';
$leads = [];
if (file_exists($leadsFile)) { $leads = json_decode(file_get_contents($leadsFile), true) ?: []; }
$leads[] = $data;
@file_put_contents($leadsFile, json_encode($leads, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

echo json_encode(['ok'=>true, 'sent'=>$sent]);
