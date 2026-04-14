<?php
/**
 * webhook_handler.php
 * يستقبل الـ Webhook من Evolution API ويحوّل الرسائل لطلبات في قاعدة البيانات
 */
header('Content-Type: application/json');
require_once 'config.php';

// ─── قراءة البيانات الواردة ──────────────────────────────────
$inputJSON = file_get_contents('php://input');
$input     = json_decode($inputJSON, true);

// دعم صيغتَين: Evolution API والصيغة القديمة (للتوافقية)
$from    = null;
$message = null;
$name    = 'عميل واتساب';
$location_url = null;

// ─── صيغة Evolution API ───────────────────────────────────────
if (isset($input['event']) && $input['event'] === 'messages.upsert') {
    $data = $input['data'] ?? [];

    // تجاهل رسائل النظام وما أرسله البوت نفسه
    if (($data['key']['fromMe'] ?? false) === true) {
        echo json_encode(['success' => false, 'reason' => 'fromMe']); exit;
    }

    // رقم المُرسِل
    $jid  = $data['key']['remoteJid'] ?? '';
    $from = preg_replace('/[^0-9]/', '', str_replace('@s.whatsapp.net', '', $jid));

    // تجاهل المجموعات
    if (str_contains($jid, '@g.us')) {
        echo json_encode(['success' => false, 'reason' => 'group']); exit;
    }

    $name    = $data['pushName'] ?? 'عميل واتساب';
    $msgObj  = $data['message'] ?? [];
    $message = $msgObj['conversation']
        ?? $msgObj['extendedTextMessage']['text']
        ?? $msgObj['imageMessage']['caption']
        ?? '';

    // موقع جغرافي
    if (isset($msgObj['locationMessage'])) {
        $lat = $msgObj['locationMessage']['degreesLatitude'];
        $lng = $msgObj['locationMessage']['degreesLongitude'];
        $location_url = "https://maps.google.com/?q=$lat,$lng";
    }
}
// ─── الصيغة القديمة (Baileys custom bot) للتوافقية ───────────
elseif (isset($input['from'])) {
    $from         = preg_replace('/[^0-9]/', '', $input['from']);
    $message      = trim($input['message'] ?? '');
    $location_url = $input['location_url'] ?? null;
    $name         = $input['name'] ?? 'عميل واتساب';
}

// ─── تحقق من وجود البيانات الأساسية ─────────────────────────
if (!$from || !$message) {
    echo json_encode(['success' => false, 'error' => 'بيانات غير مكتملة']); exit;
}

$timestamp = $input['timestamp'] ?? time();

try {
    $pdo->beginTransaction();

    // 1. العميل: ابحث أو أنشئ
    $stmt_cust = $pdo->prepare("SELECT id FROM customers WHERE whatsapp_number = ?");
    $stmt_cust->execute([$from]);
    $customer_id = $stmt_cust->fetchColumn();

    if (!$customer_id) {
        $ins_cust = $pdo->prepare(
            "INSERT INTO customers (whatsapp_number, name, default_location_label) VALUES (?, ?, ?) RETURNING id"
        );
        $ins_cust->execute([$from, $name, 'من الواتساب']);
        $customer_id = $ins_cust->fetchColumn();
    } elseif ($location_url) {
        $pdo->prepare("UPDATE customers SET google_maps_url = ?, default_location_label = 'موقع محدث' WHERE id = ?")
            ->execute([$location_url, $customer_id]);
    }

    // 2. تحليل الرسالة لتحديد عدد المحلات وسعر التوصيل
    $stores = 1;
    if (preg_match('/مكانين|محلين|\b2\b/u', $message))       { $stores = 2; }
    elseif (preg_match('/ثلاث|ثلاثة|\b3\b/u', $message))    { $stores = 3; }
    elseif (preg_match('/أربع|اربع|\b4\b/u', $message))      { $stores = 4; }

    $fee = match(true) {
        $stores >= 4 => 20.00,
        $stores == 3 => 15.00,
        default      => 10.00,
    };

    // 3. إنشاء الطلب
    $location_label = $location_url ? 'موقع مرسل (انظر الرابط)' : 'يحتاج تحديد';
    $notes          = 'رسالة العميل: ' . mb_substr($message, 0, 200);

    $ins_order = $pdo->prepare("
        INSERT INTO orders
            (customer_id, raw_messages, special_notes, delivery_location_label,
             delivery_maps_url, stores_count, delivery_fee, status, source)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new', 'whatsapp')
        RETURNING order_number
    ");
    $ins_order->execute([
        $customer_id, $message, $notes,
        $location_label, $location_url, $stores, $fee
    ]);
    $order_number = $ins_order->fetchColumn();

    $pdo->commit();

    // 4. رد تلقائي للعميل عبر Evolution API
    $EVO_URL  = rtrim(getenv('EVO_URL') ?: 'http://evolution:8080', '/');
    $EVO_KEY  = getenv('EVO_KEY') ?: 'dlv-evo-K9x2mP8nQ4rT7wJ3vL5';
    $INSTANCE = getenv('EVO_INSTANCE') ?: 'delivery';

    $reply = "✅ *تم استلام طلبك!*\n\n"
           . "📦 رقم الطلب: *$order_number*\n"
           . "💰 رسوم التوصيل: *$fee ر.س*\n\n"
           . "سيتواصل معك المندوب قريباً 🛵";

    $ch = curl_init("$EVO_URL/message/sendText/$INSTANCE");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', "apikey: $EVO_KEY"],
        CURLOPT_POSTFIELDS     => json_encode([
            'number'      => $from,
            'options'     => ['delay' => 1500, 'presence' => 'composing'],
            'textMessage' => ['text' => $reply],
        ]),
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    curl_exec($ch);
    curl_close($ch);

    echo json_encode([
        'success'      => true,
        'order_number' => $order_number,
        'delivery_fee' => $fee,
        'stores_count' => $stores,
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
