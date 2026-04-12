<?php
header('Content-Type: application/json');
$action = $_GET['action'] ?? 'status';
$NODE_URL = getenv('BOT_URL') ?: 'http://localhost:3000';

function call_bot($endpoint, $method='GET', $data=null, $timeout=5) {
    $ch = curl_init($endpoint);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ];
    if($method === 'POST' && $data) {
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body, 'code' => $code, 'error' => $err];
}

switch($action) {

    case 'status':
        $r = call_bot("$NODE_URL/wa/status");
        if($r['code'] === 200) {
            $d = json_decode($r['body'], true);
            echo json_encode(['connected' => ($d['connected'] ?? false), 'phone' => ($d['phone'] ?? null)]);
        } else {
            echo json_encode(['connected' => false, 'error' => 'البوت غير متاح']);
        }
        break;

    case 'qr':
        $r = call_bot("$NODE_URL/wa/qr", 'GET', null, 10);
        if($r['code'] === 200) {
            $d = json_decode($r['body'], true);
            if(!empty($d['qr'])) {
                echo json_encode(['qr' => $d['qr']]);
            } elseif(!empty($d['connected'])) {
                echo json_encode(['connected' => true]);
            } else {
                echo json_encode(['error' => 'البوت لا يزال في طور البدء، انتظر قليلاً وأعد المحاولة']);
            }
        } else {
            echo json_encode(['error' => 'تعذر توليد QR — تأكد من تشغيل PM2']);
        }
        break;

    case 'pair':
        $phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
        if(strlen($phone) < 9) {
            echo json_encode(['error' => 'رقم الهاتف غير صحيح']); break;
        }
        $r = call_bot("$NODE_URL/wa/pair", 'POST', ['phone' => $phone], 15);
        if($r['code'] === 200) {
            $d = json_decode($r['body'], true);
            if(!empty($d['code'])) {
                echo json_encode(['code' => $d['code']]);
            } else {
                echo json_encode(['error' => $d['error'] ?? 'تعذر الحصول على الكود']);
            }
        } else {
            echo json_encode(['error' => 'تعذر التواصل مع البوت — تأكد من تشغيل PM2']);
        }
        break;

    case 'disconnect':
        $r = call_bot("$NODE_URL/wa/disconnect", 'POST');
        echo json_encode(['ok' => $r['code'] === 200]);
        break;
}
?>
