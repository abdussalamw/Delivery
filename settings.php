<?php
$page_title = 'إعدادات النظام';
require_once 'config.php';

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'save_settings') {
        try {
            $keys = ['business_name','whatsapp_number','buffer_wait_seconds','max_active_orders','commission_per_order','driver_target_daily','currency'];
            foreach($keys as $k) {
                if(isset($_POST[$k])) {
                    $pdo->prepare("UPDATE settings SET value=?, updated_at=NOW() WHERE key=?")->execute([$_POST[$k], $k]);
                }
            }
            // Handle fee rules
            foreach([1,2,3,4] as $sc) {
                if(isset($_POST["fee_$sc"])) {
                    $pdo->prepare("UPDATE delivery_fee_rules SET fee=? WHERE stores_count=?")->execute([$_POST["fee_$sc"], $sc]);
                }
            }
            $msg = "✅ تم حفظ الإعدادات بنجاح!";
        } catch(\PDOException $e) { $error = "❌ " . $e->getMessage(); }
    }
}

try {
    $settings_rows = $pdo->query("SELECT key, value FROM settings")->fetchAll(PDO::FETCH_KEY_PAIR);
    $fee_rules = $pdo->query("SELECT stores_count, fee FROM delivery_fee_rules ORDER BY stores_count")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch(\PDOException $e) {
    $settings_rows = []; $fee_rules = [];
    $error = "❌ تعذر جلب الإعدادات. تأكد من استيراد ملف الإعداد.";
}

require 'layout.php';
?>

<div class="page-header">
    <div>
        <h1>⚙️ إعدادات النظام</h1>
        <p>تخصيص جميع إعدادات نظام التوصيل</p>
    </div>
</div>

<?php if($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

<form method="POST">
    <input type="hidden" name="action" value="save_settings">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:2rem">
        
        <!-- Left Column -->
        <div style="display:flex;flex-direction:column;gap:2rem">
            
            <!-- معلومات المنصة -->
            <div class="card">
                <h2 style="font-size:1.2rem;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border)">🏪 معلومات المنصة</h2>
                <div class="form-group">
                    <label>اسم المنصة / المحل</label>
                    <input type="text" name="business_name" class="form-control" value="<?=htmlspecialchars($settings_rows['business_name']??'')?>">
                </div>
                <div class="form-group">
                    <label>رقم واتساب المحل الرئيسي</label>
                    <input type="text" name="whatsapp_number" class="form-control" placeholder="966500000000" value="<?=htmlspecialchars($settings_rows['whatsapp_number']??'')?>">
                </div>
                <div class="form-group">
                    <label>العملة</label>
                    <input type="text" name="currency" class="form-control" value="<?=htmlspecialchars($settings_rows['currency']??'ريال سعودي')?>">
                </div>
            </div>

            <!-- إعدادات المناديب -->
            <div class="card">
                <h2 style="font-size:1.2rem;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border)">🛵 إعدادات المناديب</h2>
                <div class="form-group">
                    <label>الحد الأقصى للطلبات النشطة لكل مندوب</label>
                    <input type="number" name="max_active_orders" class="form-control" value="<?=htmlspecialchars($settings_rows['max_active_orders']??'5')?>">
                </div>
                <div class="form-group">
                    <label>التارجت اليومي الافتراضي للمندوب</label>
                    <input type="number" name="driver_target_daily" class="form-control" value="<?=htmlspecialchars($settings_rows['driver_target_daily']??'15')?>">
                </div>
                <div class="form-group">
                    <label>عمولة النظام لكل طلب مكتمل (ر.س)</label>
                    <input type="number" step="0.01" name="commission_per_order" class="form-control" value="<?=htmlspecialchars($settings_rows['commission_per_order']??'1.00')?>">
                </div>
            </div>
        </div>

        <!-- Right Column -->
        <div style="display:flex;flex-direction:column;gap:2rem">

            <!-- رسوم التوصيل -->
            <div class="card">
                <h2 style="font-size:1.2rem;margin-bottom:.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border)">💰 رسوم التوصيل حسب الأماكن</h2>
                <p style="color:var(--muted);font-size:.85rem;margin-bottom:1.5rem">الرسوم المطبّقة تلقائياً عند إنشاء الطلبات</p>
                <?php foreach([1=>'مكان واحد',2=>'مكانين',3=>'ثلاثة أماكن',4=>'أربعة أماكن أو أكثر'] as $sc=>$label): ?>
                <div class="form-group">
                    <label><?=$label?></label>
                    <div style="display:flex;align-items:center;gap:1rem">
                        <input type="number" step="0.01" name="fee_<?=$sc?>" class="form-control" value="<?=htmlspecialchars($fee_rules[$sc]??10)?>">
                        <span style="color:var(--muted);white-space:nowrap">ر.س</span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- إعدادات الأتمتة -->
            <div class="card">
                <h2 style="font-size:1.2rem;margin-bottom:1.5rem;padding-bottom:1rem;border-bottom:1px solid var(--border)">🤖 إعدادات الأتمتة</h2>
                <div class="form-group">
                    <label>وقت انتظار تجميع رسائل العميل (ثانية)</label>
                    <input type="number" name="buffer_wait_seconds" class="form-control" value="<?=htmlspecialchars($settings_rows['buffer_wait_seconds']??'45')?>">
                    <div style="color:var(--muted);font-size:.8rem;margin-top:.5rem">⏱ النظام ينتظر هذا الوقت قبل معالجة رسائل العميل كطلب واحد</div>
                </div>

                <!-- DB Info -->
                <div style="background:rgba(16,185,129,.08);border:1px solid rgba(16,185,129,.2);border-radius:12px;padding:1.25rem;margin-top:1rem">
                    <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:.75rem">
                        <span style="color:var(--success);font-size:1.1rem">●</span>
                        <span style="font-weight:600">قاعدة البيانات متصلة</span>
                    </div>
                    <div style="font-size:.85rem;color:var(--muted);line-height:1.8">
                        <div>⚙️ نوع قاعدة البيانات: <strong style="color:var(--text)">PostgreSQL</strong></div>
                        <div>🗄️ اسم القاعدة: <strong style="color:var(--text)">delivery_db</strong></div>
                        <div>🖥️ الخادم: <strong style="color:var(--text)">localhost:5432</strong></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="margin-top:2rem;text-align:center">
        <button type="submit" class="btn btn-primary" style="padding:1rem 4rem;font-size:1.1rem">💾 حفظ جميع الإعدادات</button>
    </div>
</form>

<?php require 'layout_end.php'; ?>
