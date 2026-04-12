<?php
header('Content-Type: application/json');
$action = $_GET['action'] ?? 'check';
$NODE_PORT = 3000;
$NODE_HOST = getenv('BOT_HOST') ?: 'localhost';

function check_node($host, $port) {
    $fp = @fsockopen($host, $port, $errno, $errstr, 3);
    if($fp) {
        fclose($fp);
        return true;
    }
    return false;
}

function curl_get($url, $timeout=3) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['body' => $body, 'code' => $code];
}

switch($action) {

    case 'version':
        if (getenv('BOT_URL')) {
            echo json_encode(['ok'=>true, 'message'=>'✅ يعمل بنظام Docker — Node.js 22 (Alpine)']);
            break;
        }
        $v = shell_exec('node --version 2>&1');
        if(empty(trim($v)) || str_contains(strtolower($v),(string)'not recognized')) {
            echo json_encode(['ok'=>false, 'message'=>'❌ Node.js غير مُثبَّت. قم بتحميله من nodejs.org']);
        } else {
            echo json_encode(['ok'=>true, 'message'=>'✅ Node.js مُثبَّت — الإصدار: '.trim($v)]);
        }
        break;

    case 'ping':
        $alive = check_node($NODE_HOST, $NODE_PORT);
        if($alive) {
            $r = curl_get("http://$NODE_HOST:$NODE_PORT/ping");
            echo json_encode(['ok'=>true, 'message'=>"✅ المنفذ $NODE_PORT يستجيب — HTTP {$r['code']}"]);
        } else {
            echo json_encode(['ok'=>false, 'message'=>"❌ لا يوجد خدمة تعمل على المنفذ $NODE_PORT"]);
        }
        break;

    case 'check':
    default:
        $r = curl_get("http://$NODE_HOST:$NODE_PORT/status");
        if($r['code'] >= 200 && $r['code'] < 400) {
            $data = json_decode($r['body'], true);
            $extra = $data ? (' — ' . ($data['status'] ?? 'ok')) : '';
            echo json_encode(['ok'=>true, 'message'=>"✅ Node.js يستجيب على المنفذ $NODE_PORT$extra"]);
        } else {
            // جرب TCP فقط
            $tcp = check_node($NODE_HOST, $NODE_PORT);
            if($tcp) {
                echo json_encode(['ok'=>true, 'message'=>"⚠️ المنفذ $NODE_PORT مفتوح لكن الـ API لم يرد — تحقق من بوت.js"]);
            } else {
                echo json_encode(['ok'=>false, 'message'=>"❌ فشل الاتصال بـ localhost:$NODE_PORT — تأكد من تشغيل البوت عبر PM2"]);
            }
        }
        break;
}
?>
