<?php
$page_title = 'إدارة المناديب';
require_once 'config.php';

$msg = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO drivers (name, whatsapp_number, national_id, vehicle_type, vehicle_plate, base_salary, target_orders, commission_rate) VALUES (?,?,?,?,?,?,?,?)");
        try {
            $stmt->execute([$_POST['name'], $_POST['whatsapp'], $_POST['national_id'], $_POST['vehicle_type'], $_POST['vehicle_plate'], $_POST['base_salary']??0, $_POST['target_orders']??15, $_POST['commission_rate']??0.10]);
            $msg = "✅ تم إضافة المندوب بنجاح!";
        } catch(\PDOException $e) { $error = "❌ " . $e->getMessage(); }
    }

    elseif ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE drivers SET name=?, whatsapp_number=?, national_id=?, vehicle_type=?, vehicle_plate=?, base_salary=?, target_orders=?, commission_rate=?, status=?, is_active=? WHERE id=?");
        try {
            $stmt->execute([$_POST['name'],$_POST['whatsapp'],$_POST['national_id'],$_POST['vehicle_type'],$_POST['vehicle_plate'],$_POST['base_salary']??0,$_POST['target_orders']??15,$_POST['commission_rate']??0.10,$_POST['status'],isset($_POST['is_active'])?'true':'false',$_POST['id']]);
            $msg = "✅ تم تحديث بيانات المندوب!";
        } catch(\PDOException $e) { $error = "❌ " . $e->getMessage(); }
    }

    elseif ($_POST['action'] === 'delete') {
        try {
            $pdo->prepare("DELETE FROM drivers WHERE id=?")->execute([$_POST['id']]);
            $msg = "✅ تم حذف المندوب!";
        } catch(\PDOException $e) { $error = "❌ لا يمكن الحذف، المندوب مرتبط بطلبات مسجّلة."; }
    }
}

$search = trim($_GET['q'] ?? '');
$where = $search ? "WHERE d.name ILIKE ? OR d.whatsapp_number ILIKE ?" : "";
$sql = "SELECT d.*,
    COUNT(o.id) FILTER(WHERE DATE(o.ordered_at)=CURRENT_DATE AND o.status='delivered') AS today_orders,
    COALESCE(SUM(o.delivery_fee) FILTER(WHERE DATE(o.ordered_at)=CURRENT_DATE AND o.status='delivered'),0) AS today_earned
    FROM drivers d LEFT JOIN orders o ON o.driver_id=d.id $where GROUP BY d.id ORDER BY d.is_active DESC, d.name";
$stmt = $pdo->prepare($sql);
if($search) $stmt->execute(["%$search%", "%$search%"]);
else $stmt->execute();
$drivers = $stmt->fetchAll();

$total_d = $pdo->query("SELECT COUNT(*) FROM drivers WHERE is_active=true")->fetchColumn();
$online_d = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='online'")->fetchColumn();
$busy_d = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status='busy'")->fetchColumn();

require 'layout.php';
?>

<div class="page-header">
    <div>
        <h1>🛵 إدارة المناديب</h1>
        <p>تتبع حالة وأداء جميع مناديب التوصيل</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ إضافة مندوب</button>
</div>

<?php if($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem">
    <div class="card" style="text-align:center;padding:1.5rem">
        <div style="font-size:2rem;font-weight:800;color:var(--primary)"><?=$total_d?></div>
        <div style="color:var(--muted);font-size:.9rem">إجمالي المناديب النشطين</div>
    </div>
    <div class="card" style="text-align:center;padding:1.5rem">
        <div style="font-size:2rem;font-weight:800;color:var(--success)"><?=$online_d?></div>
        <div style="color:var(--muted);font-size:.9rem">متاحون الآن</div>
    </div>
    <div class="card" style="text-align:center;padding:1.5rem">
        <div style="font-size:2rem;font-weight:800;color:var(--warning)"><?=$busy_d?></div>
        <div style="color:var(--muted);font-size:.9rem">مشغولون حالياً</div>
    </div>
</div>

<!-- Search -->
<form method="GET" style="margin-bottom:1.5rem;display:flex;gap:1rem">
    <input type="text" name="q" value="<?=htmlspecialchars($search)?>" class="form-control" style="max-width:400px" placeholder="🔍 البحث بالاسم أو الواتساب...">
    <button type="submit" class="btn btn-ghost">بحث</button>
    <?php if($search): ?><a href="drivers.php" class="btn btn-ghost">× مسح</a><?php endif; ?>
</form>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
    <table>
        <thead>
            <tr>
                <th>المندوب</th>
                <th>واتساب</th>
                <th>المركبة</th>
                <th>الحالة</th>
                <th>طلبات اليوم</th>
                <th>أرباح اليوم</th>
                <th>التقييم</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($drivers)): ?>
            <tr><td colspan="8" style="text-align:center;padding:3rem;color:var(--muted)">لا يوجد مناديب مسجّلين.</td></tr>
        <?php else: ?>
        <?php foreach($drivers as $d): ?>
            <?php
            $status_label = ['online'=>'🟢 متاح','busy'=>'🟠 مشغول','break'=>'☕ استراحة','offline'=>'🔴 غير متصل'];
            $status_badge = ['online'=>'badge-success','busy'=>'badge-warning','break'=>'badge-muted','offline'=>'badge-danger'];
            ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:.85rem">
                        <div style="width:44px;height:44px;border-radius:12px;background:var(--glass);border:1px solid var(--border);display:grid;place-items:center;font-size:1.2rem;color:var(--secondary)">
                            <?=mb_substr($d['name'],0,1)?>
                        </div>
                        <div>
                            <div style="font-weight:600"><?=htmlspecialchars($d['name'])?></div>
                            <div style="font-size:.8rem;color:var(--muted)"><?= $d['is_active'] ? 'مفعّل' : 'موقوف' ?></div>
                        </div>
                    </div>
                </td>
                <td>
                    <a href="https://wa.me/<?=htmlspecialchars($d['whatsapp_number'])?>" target="_blank" style="color:var(--success);text-decoration:none">
                        📱 <?=htmlspecialchars($d['whatsapp_number'])?>
                    </a>
                </td>
                <td>
                    <div style="font-weight:500"><?=htmlspecialchars($d['vehicle_type']??'—')?></div>
                    <div style="font-size:.8rem;color:var(--muted)"><?=htmlspecialchars($d['vehicle_plate']??'')?></div>
                </td>
                <td>
                    <form method="POST" style="margin:0">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="id" value="<?=$d['id']?>">
                        <input type="hidden" name="name" value="<?=htmlspecialchars($d['name'])?>">
                        <input type="hidden" name="whatsapp" value="<?=htmlspecialchars($d['whatsapp_number'])?>">
                        <input type="hidden" name="national_id" value="<?=htmlspecialchars($d['national_id']??'')?>">
                        <input type="hidden" name="vehicle_type" value="<?=htmlspecialchars($d['vehicle_type']??'')?>">
                        <input type="hidden" name="vehicle_plate" value="<?=htmlspecialchars($d['vehicle_plate']??'')?>">
                        <input type="hidden" name="base_salary" value="<?=$d['base_salary']?>">
                        <input type="hidden" name="target_orders" value="<?=$d['target_orders']?>">
                        <input type="hidden" name="commission_rate" value="<?=$d['commission_rate']?>">
                        <input type="hidden" name="is_active" value="1">
                        <select name="status" class="form-control" style="padding:.4rem;font-size:.85rem" onchange="this.form.submit()">
                            <?php foreach($status_label as $k=>$v): ?>
                                <option value="<?=$k?>" <?=$d['status']===$k?'selected':''?>><?=$v?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </td>
                <td style="font-weight:700;color:var(--primary)"><?=$d['today_orders']?> / <?=$d['target_orders']?></td>
                <td style="color:var(--success)"><?=number_format($d['today_earned'],0)?> ر.س</td>
                <td>
                    <div style="display:flex;align-items:center;gap:.4rem">
                        <span style="color:#f59e0b">★</span>
                        <span><?=number_format($d['rating'],1)?></span>
                    </div>
                </td>
                <td>
                    <div style="display:flex;gap:.5rem">
                        <button class="btn btn-ghost btn-sm" onclick='openEdit(<?=json_encode($d)?>)'>✏️ تعديل</button>
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?=$d['id']?>">
                            <button class="btn btn-danger btn-sm">🗑️</button>
                        </form>
                    </div>
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
            <div class="modal-title">🛵 إضافة مندوب جديد</div>
            <button class="modal-close" onclick="closeModal('addModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group"><label>الاسم الكامل</label><input type="text" name="name" class="form-control" required placeholder="محمد الشمري"></div>
                <div class="form-group"><label>رقم الواتساب</label><input type="text" name="whatsapp" class="form-control" required placeholder="966500000000"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>رقم الهوية</label><input type="text" name="national_id" class="form-control" placeholder="1xxxxxxxxx"></div>
                <div class="form-group">
                    <label>نوع المركبة</label>
                    <select name="vehicle_type" class="form-control">
                        <option>سيدان</option><option>دباب</option><option>نقل خفيف</option><option>دفع رباعي</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>لوحة المركبة</label><input type="text" name="vehicle_plate" class="form-control" placeholder="أ ب ج 123"></div>
                <div class="form-group"><label>الراتب الأساسي</label><input type="number" name="base_salary" class="form-control" placeholder="0" value="0"></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>التارجت اليومي</label><input type="number" name="target_orders" class="form-control" value="15"></div>
                <div class="form-group"><label>نسبة العمولة (0.10=10%)</label><input type="number" step="0.01" name="commission_rate" class="form-control" value="0.10"></div>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">💾 حفظ المندوب</button>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal" onclick="closeOnOutside(event,'editModal')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">✏️ تعديل بيانات المندوب</div>
            <button class="modal-close" onclick="closeModal('editModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="e_id">
            <div class="form-row">
                <div class="form-group"><label>الاسم</label><input type="text" name="name" id="e_name" class="form-control" required></div>
                <div class="form-group"><label>واتساب</label><input type="text" name="whatsapp" id="e_whatsapp" class="form-control" required></div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>رقم الهوية</label><input type="text" name="national_id" id="e_national" class="form-control"></div>
                <div class="form-group">
                    <label>نوع المركبة</label>
                    <select name="vehicle_type" id="e_vehicle" class="form-control">
                        <option>سيدان</option><option>دباب</option><option>نقل خفيف</option><option>دفع رباعي</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>لوحة المركبة</label><input type="text" name="vehicle_plate" id="e_plate" class="form-control"></div>
                <div class="form-group">
                    <label>الحالة</label>
                    <select name="status" id="e_status" class="form-control">
                        <option value="online">🟢 متاح</option>
                        <option value="busy">🟠 مشغول</option>
                        <option value="break">☕ استراحة</option>
                        <option value="offline">🔴 غير متصل</option>
                    </select>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group"><label>التارجت اليومي</label><input type="number" name="target_orders" id="e_target" class="form-control"></div>
                <div class="form-group"><label>الراتب الأساسي</label><input type="number" name="base_salary" id="e_salary" class="form-control"></div>
            </div>
            <input type="hidden" name="commission_rate" id="e_commission" value="0.10">
            <div class="form-group" style="display:flex;align-items:center;gap:.75rem">
                <input type="checkbox" name="is_active" id="e_active" checked style="width:18px;height:18px;accent-color:var(--success)">
                <label for="e_active" style="cursor:pointer;color:var(--success)">✅ المندوب مفعّل ونشط</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">💾 حفظ التعديلات</button>
        </form>
    </div>
</div>

<script>
function openModal(id){document.getElementById(id).style.display='grid';}
function closeModal(id){document.getElementById(id).style.display='none';}
function closeOnOutside(e,id){if(e.target.id===id)closeModal(id);}
function openEdit(d){
    document.getElementById('e_id').value=d.id;
    document.getElementById('e_name').value=d.name||'';
    document.getElementById('e_whatsapp').value=d.whatsapp_number||'';
    document.getElementById('e_national').value=d.national_id||'';
    document.getElementById('e_vehicle').value=d.vehicle_type||'سيدان';
    document.getElementById('e_plate').value=d.vehicle_plate||'';
    document.getElementById('e_status').value=d.status||'offline';
    document.getElementById('e_target').value=d.target_orders||15;
    document.getElementById('e_salary').value=d.base_salary||0;
    document.getElementById('e_commission').value=d.commission_rate||0.10;
    document.getElementById('e_active').checked=(d.is_active==='true'||d.is_active===true);
    openModal('editModal');
}
</script>

<?php require 'layout_end.php'; ?>
