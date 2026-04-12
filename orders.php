<?php
$page_title = 'إدارة الطلبات';
require_once 'config.php';

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        try {
            $pdo->beginTransaction();
            $cust_stmt = $pdo->prepare("SELECT id FROM customers WHERE whatsapp_number=?");
            $cust_stmt->execute([$_POST['customer_whatsapp']]);
            $customer_id = $cust_stmt->fetchColumn();
            if (!$customer_id) {
                $ic = $pdo->prepare("INSERT INTO customers (whatsapp_number, name, default_location_label) VALUES (?,?,?) RETURNING id");
                $ic->execute([$_POST['customer_whatsapp'], $_POST['customer_name'], $_POST['location']]);
                $customer_id = $ic->fetchColumn();
            }
            $stores = (int)$_POST['stores_count'];
            $fee = $stores >= 4 ? 20.00 : ($stores == 3 ? 15.00 : 10.00);
            $driver_id = !empty($_POST['driver_id']) ? $_POST['driver_id'] : null;
            $io = $pdo->prepare("INSERT INTO orders (customer_id, driver_id, delivery_location_label, stores_count, delivery_fee, special_notes) VALUES (?,?,?,?,?,?)");
            $io->execute([$customer_id, $driver_id, $_POST['location'], $stores, $fee, $_POST['notes']]);
            if($driver_id) { $pdo->prepare("UPDATE drivers SET current_orders=current_orders+1 WHERE id=?")->execute([$driver_id]); }
            $pdo->commit();
            $msg = "✅ تم تسجيل الطلب بنجاح بتكلفة توصيل $fee ر.س!";
        } catch(\PDOException $e) { $pdo->rollBack(); $error = "❌ " . $e->getMessage(); }
    }

    elseif ($_POST['action'] === 'update_status') {
        try {
            $pdo->prepare("UPDATE orders SET status=? WHERE id=?")->execute([$_POST['status'], $_POST['id']]);
            if($_POST['status'] === 'delivered') {
                try { $pdo->query("SELECT close_order(".(int)$_POST['id'].")"); } catch(\Exception $e2) {}
            }
            $msg = "✅ تم تحديث حالة الطلب!";
        } catch(\PDOException $e) { $error = "❌ " . $e->getMessage(); }
    }

    elseif ($_POST['action'] === 'delete') {
        try {
            $pdo->prepare("DELETE FROM order_events WHERE order_id=?")->execute([$_POST['id']]);
            $pdo->prepare("DELETE FROM orders WHERE id=?")->execute([$_POST['id']]);
            $msg = "✅ تم حذف الطلب!";
        } catch(\PDOException $e) { $error = "❌ " . $e->getMessage(); }
    }
}

// Filters
$status_filter = $_GET['status'] ?? '';
$where = $status_filter ? "WHERE o.status=?" : "";
$sql = "SELECT o.*, c.name AS customer_name, c.whatsapp_number AS customer_wa,
        d.name AS driver_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id=c.id
        LEFT JOIN drivers d ON o.driver_id=d.id
        $where ORDER BY o.ordered_at DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
if($status_filter) $stmt->execute([$status_filter]);
else $stmt->execute();
$orders = $stmt->fetchAll();

$drivers_list = $pdo->query("SELECT id, name FROM drivers WHERE is_active=true ORDER BY name")->fetchAll();

$status_labels = [
    'new'=>['label'=>'جديد','badge'=>'badge-info'],
    'assigned'=>['label'=>'مسند','badge'=>'badge-warning'],
    'picking_up'=>['label'=>'في المتجر','badge'=>'badge-warning'],
    'on_the_way'=>['label'=>'في الطريق','badge'=>'badge-success'],
    'delivered'=>['label'=>'تم التسليم','badge'=>'badge-success'],
    'cancelled'=>['label'=>'ملغي','badge'=>'badge-danger'],
    'returned'=>['label'=>'مرتجع','badge'=>'badge-danger'],
];

require 'layout.php';
?>

<div class="page-header">
    <div>
        <h1>📦 إدارة الطلبات</h1>
        <p>تتبع جميع الطلبات وإدارتها بشكل كامل</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ طلب جديد</button>
</div>

<?php if($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

<!-- Filter Tabs -->
<div style="display:flex;gap:.5rem;margin-bottom:1.5rem;flex-wrap:wrap">
    <a href="orders.php" class="btn btn-sm <?= !$status_filter ? 'btn-primary' : 'btn-ghost' ?>">الكل</a>
    <?php foreach($status_labels as $k=>$v): ?>
    <a href="?status=<?=$k?>" class="btn btn-sm <?= $status_filter===$k ? 'btn-primary' : 'btn-ghost' ?>"><?=$v['label']?></a>
    <?php endforeach; ?>
</div>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
    <table>
        <thead>
            <tr>
                <th>رقم الطلب</th>
                <th>العميل</th>
                <th>الموقع</th>
                <th>المندوب</th>
                <th>الأماكن</th>
                <th>التوصيل</th>
                <th>الحالة</th>
                <th>الوقت</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($orders)): ?>
            <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--muted)">لا توجد طلبات.</td></tr>
        <?php else: ?>
        <?php foreach($orders as $o):
            $sl = $status_labels[$o['status']] ?? ['label'=>$o['status'],'badge'=>'badge-muted'];
        ?>
            <tr>
                <td style="font-weight:700;color:var(--primary)"><?=htmlspecialchars($o['order_number']??'#')?></td>
                <td>
                    <div style="font-weight:600"><?=htmlspecialchars($o['customer_name']??'مجهول')?></div>
                    <?php if($o['customer_wa']): ?>
                    <a href="https://wa.me/<?=$o['customer_wa']?>" target="_blank" style="font-size:.8rem;color:var(--success);text-decoration:none">📱 <?=$o['customer_wa']?></a>
                    <?php endif; ?>
                </td>
                <td style="font-size:.9rem;color:var(--muted)">📍 <?=htmlspecialchars($o['delivery_location_label']??'—')?></td>
                <td><?=htmlspecialchars($o['driver_name']??'—')?></td>
                <td style="text-align:center;font-weight:700"><?=$o['stores_count']?></td>
                <td style="font-weight:700;color:var(--success)"><?=number_format($o['delivery_fee'],0)?> ر.س</td>
                <td>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="id" value="<?=$o['id']?>">
                        <select name="status" class="form-control" style="padding:.4rem;font-size:.82rem" onchange="this.form.submit()">
                            <?php foreach($status_labels as $k=>$v): ?>
                            <option value="<?=$k?>" <?=$o['status']===$k?'selected':''?>><?=$v['label']?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td style="font-size:.82rem;color:var(--muted)"><?=date('d/m H:i',strtotime($o['ordered_at']))?></td>
                <td>
                    <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?=$o['id']?>">
                        <button class="btn btn-danger btn-sm">🗑️</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addModal" onclick="closeOnOutside(event,'addModal')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">📦 تسجيل طلب جديد</div>
            <button class="modal-close" onclick="closeModal('addModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group"><label>واتساب العميل</label><input type="text" name="customer_whatsapp" class="form-control" required placeholder="966500000000"></div>
                <div class="form-group"><label>اسم العميل (جديد)</label><input type="text" name="customer_name" class="form-control" required placeholder="اسم العميل"></div>
            </div>
            <div class="form-group"><label>موقع التسليم</label><input type="text" name="location" class="form-control" required placeholder="حي النزهة، شارع الملك عبدالعزيز"></div>
            <div class="form-row">
                <div class="form-group">
                    <label>عدد الأماكن</label>
                    <select name="stores_count" class="form-control" onchange="updateFee(this.value)">
                        <option value="1">مكان واحد — 10 ر.س</option>
                        <option value="2">مكانين — 10 ر.س</option>
                        <option value="3">3 أماكن — 15 ر.س</option>
                        <option value="4">4 أماكن+ — 20 ر.س</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>إسناد مندوب (اختياري)</label>
                    <select name="driver_id" class="form-control">
                        <option value="">— آلي —</option>
                        <?php foreach($drivers_list as $dr): ?>
                        <option value="<?=$dr['id']?>"><?=htmlspecialchars($dr['name'])?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group"><label>ملاحظات خاصة</label><input type="text" name="notes" class="form-control" placeholder="بدون شطة، مستعجل..."></div>
            <div style="background:rgba(0,242,254,.08);border:1px solid rgba(0,242,254,.2);border-radius:12px;padding:1rem;margin-bottom:1rem;text-align:center">
                <span style="color:var(--muted);font-size:.9rem">تكلفة التوصيل:</span>
                <span id="fee_display" style="font-size:1.5rem;font-weight:800;color:var(--primary);margin-right:.5rem">10 ر.س</span>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">✅ تسجيل الطلب</button>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).style.display='grid';}
function closeModal(id){document.getElementById(id).style.display='none';}
function closeOnOutside(e,id){if(e.target.id===id)closeModal(id);}
function updateFee(v){
    const f={1:10,2:10,3:15,4:20};
    document.getElementById('fee_display').textContent=(f[v]||10)+' ر.س';
}
</script>

<?php require 'layout_end.php'; ?>
