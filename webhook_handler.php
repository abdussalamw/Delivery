<?php
header('Content-Type: application/json');
require_once 'config.php';

// قراءة البيانات المرسلة من البوت (JSON)
$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

if (!$input || !isset($input['from']) || !isset($input['message'])) {
    echo json_encode(['success' => false, 'error' => 'بيانات غير مكتملة']);
    exit;
}

$from = preg_replace('/[^0-9]/', '', $input['from']);
$message = trim($input['message']);
$location_url = $input['location_url'] ?? null;
$timestamp = $input['timestamp'] ?? time();

try {
    $pdo->beginTransaction();

    // 1. استخراج أو إضافة العميل
    $name = $input['name'] ?? 'عميل واتساب';
    $stmt_cust = $pdo->prepare("SELECT id FROM customers WHERE whatsapp_number = ?");
    $stmt_cust->execute([$from]);
    $customer_id = $stmt_cust->fetchColumn();

    if (!$customer_id) {
        $ins_cust = $pdo->prepare("INSERT INTO customers (whatsapp_number, name, default_location_label) VALUES (?, ?, ?) RETURNING id");
        $ins_cust->execute([$from, $name, 'من الواتساب']);
        $customer_id = $ins_cust->fetchColumn();
    } elseif ($location_url) {
        // تحديث الموقع إذا أرسل العميل موقعًا جديدًا
        $upd_cust = $pdo->prepare("UPDATE customers SET google_maps_url = ?, default_location_label = 'موقع محدث' WHERE id = ?");
        $upd_cust->execute([$location_url, $customer_id]);
    }

    // 2. تحليل مبسط للرسالة لتحديد عدد الأماكن (التكلفة)
    $stores = 1; // افتراضيًا متجر واحد
    if (strpos($message, 'مكانين') !== false || strpos($message, 'محلين') !== false || strpos($message, '2') !== false) {
        $stores = 2;
    } elseif (strpos($message, '3') !== false || strpos($message, 'ثلاث') !== false) {
        $stores = 3;
    } elseif (strpos($message, '4') !== false || strpos($message, 'اربع') !== false) {
        $stores = 4;
    }

    $fee = ($stores >= 4) ? 20.00 : (($stores == 3) ? 15.00 : 10.00);

    // 3. إنشاء الطلب
    $ins_order = $pdo->prepare("
        INSERT INTO orders (customer_id, raw_messages, special_notes, delivery_location_label, delivery_maps_url, stores_count, delivery_fee, status, source) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'new', 'whatsapp') RETURNING order_number
    ");

    $location_label = $location_url ? 'موقع مرسل (انظر الرابط)' : 'يحتاج تحديد';
    $notes = "رسالة العميل: " . mb_substr($message, 0, 100);

    $ins_order->execute([$customer_id, $message, $notes, $location_label, $location_url, $stores, $fee]);
    $order_number = $ins_order->fetchColumn();

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'order_number' => $order_number,
        'delivery_fee' => $fee,
        'stores_count' => $stores
    ]);

} catch (\PDOException $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
