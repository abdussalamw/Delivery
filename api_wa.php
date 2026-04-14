<?php
/**
 * api_wa.php — Evolution API Bridge
 * يتواصل مع Evolution API مباشرة بدلاً من bot.js
 */
header('Content-Type: application/json');

$action   = $_GET['action'] ?? 'status';
$EVO_URL  = rtrim(getenv('EVO_URL') ?: 'http://evolution:8080', '/');
$EVO_KEY  = getenv('EVO_KEY') ?: 'dlv-evo-K9x2mP8nQ4rT7wJ3vL5';
$INSTANCE = getenv('EVO_INSTANCE') ?: 'delivery';

/**
 * دالة مساعدة للتواصل مع Evolution API
 */
function evo_call(string $endpoint, string $method = 'GET', ?array $data = null, int $timeout = 8): array {
    global $EVO_URL, $EVO_KEY;
    $ch = curl_init("$EVO_URL$endpoint");
    $headers = [
        'Content-Type: application/json',
        "apikey: $EVO_KEY",
    ];
    $opts = [
        CURLOPT_RETURNTRANSFER  => true,
        CURLOPT_TIMEOUT         => $timeout,
        CURLOPT_CONNECTTIMEOUT  => $timeout,
        CURLOPT_SSL_VERIFYPEER  => false,
        CURLOPT_HTTPHEADER      => $headers,
        CURLOPT_CUSTOMREQUEST   => $method,
    ];
    if ($data !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);
    return ['body' => $body ? json_decode($body, true) : null, 'code' => $code, 'error' => $err];
}

switch ($action) {

    // ─── حالة الاتصال ───────────────────────────────────────────
    case 'status':
        $r = evo_call("/instance/connectionState/$INSTANCE");
        if ($r['code'] === 200 && isset($r['body']['instance'])) {
            $state = $r['body']['instance']['state'] ?? 'close';
            $connected = ($state === 'open');
            echo json_encode([
                'connected' => $connected,
                'state'     => $state,
                'phone'     => $r['body']['instance']['profileName'] ?? null,
            ]);
        } else {
            echo json_encode(['connected' => false, 'error' => 'Evolution غير متاح أو الـ Instance غير موجود']);
        }
        break;

    // ─── توليد QR Code ─────────────────────────────────────────
    case 'qr':
        // أولاً تحقق من الحالة
        $statusR = evo_call("/instance/connectionState/$INSTANCE");
        $state   = $statusR['body']['instance']['state'] ?? 'close';

        if ($state === 'open') {
            echo json_encode(['connected' => true]);
            break;
        }

        // طلب QR
        $r = evo_call("/instance/connect/$INSTANCE", 'GET', null, 15);
        if ($r['code'] === 200) {
            $qrBase64 = $r['body']['base64'] ?? null;
            if ($qrBase64) {
                echo json_encode(['qr' => $qrBase64]);
            } else {
                echo json_encode(['error' => 'لم يُولَّد QR بعد، انتظر ثوانٍ وأعد المحاولة']);
            }
        } else {
            // قد يكون الـ Instance غير موجود، أنشئه أولاً
            $create = evo_call('/instance/create', 'POST', [
                'instanceName' => $INSTANCE,
                'integration'  => 'WHATSAPP-BAILEYS',
                'webhookUrl'   => getenv('WEBHOOK_PUBLIC') ?: '',
                'webhookByEvents' => false,
                'events'       => ['messages.upsert'],
            ]);
            if ($create['code'] === 201) {
                echo json_encode(['error' => 'تم إنشاء الـ Instance! اضغط "توليد QR" مجدداً']);
            } else {
                echo json_encode(['error' => 'تعذّر الاتصال بـ Evolution API']);
            }
        }
        break;

    // ─── طلب Pairing Code ───────────────────────────────────────
    case 'pair':
        $phone = preg_replace('/[^0-9]/', '', $_GET['phone'] ?? '');
        if (strlen($phone) < 9) {
            echo json_encode(['error' => 'رقم الهاتف غير صحيح']); break;
        }
        $r = evo_call("/instance/connect/$INSTANCE?number=$phone", 'GET', null, 20);
        if ($r['code'] === 200) {
            $code = $r['body']['code'] ?? null;
            echo $code
                ? json_encode(['code' => $code])
                : json_encode(['error' => 'لم يُستلم كود، جرّب طريقة QR بدلاً من ذلك']);
        } else {
            echo json_encode(['error' => 'تعذّر الاتصال بـ Evolution']);
        }
        break;

    // ─── قطع الاتصال ────────────────────────────────────────────
    case 'disconnect':
        $r = evo_call("/instance/logout/$INSTANCE", 'DELETE');
        echo json_encode(['ok' => in_array($r['code'], [200, 201, 204])]);
        break;

    // ─── إرسال رسالة ────────────────────────────────────────────
    case 'send':
        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $phone   = preg_replace('/[^0-9]/', '', $body['phone'] ?? '');
        $message = trim($body['message'] ?? '');
        if (!$phone || !$message) {
            echo json_encode(['error' => 'phone و message مطلوبان']); break;
        }
        $r = evo_call("/message/sendText/$INSTANCE", 'POST', [
            'number'  => $phone,
            'options' => ['delay' => 1200, 'presence' => 'composing'],
            'textMessage' => ['text' => $message],
        ]);
        echo json_encode(['ok' => in_array($r['code'], [200, 201]), 'data' => $r['body']]);
        break;

    // ─── حالة Evolution نفسها (لصفحة الإعدادات) ─────────────────
    case 'evo_info':
        $r = evo_call('/instance/fetchInstances');
        echo json_encode(['instances' => $r['body'] ?? [], 'code' => $r['code']]);
        break;

    default:
        echo json_encode(['error' => 'إجراء غير معروف']);
}
?>
