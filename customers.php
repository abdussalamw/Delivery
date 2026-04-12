<?php
$page_title = 'إدارة العملاء';
require_once 'config.php';

$msg = ''; $error = '';

// CRUD Operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO customers (name, whatsapp_number, default_location_label, google_maps_url, notes) VALUES (?,?,?,?,?)");
        try {
            $stmt->execute([$_POST['name'], $_POST['whatsapp'], $_POST['location'], $_POST['maps_url'], $_POST['notes']]);
            $msg = "✅ تم إضافة العميل بنجاح!";
        } catch(\PDOException $e) { $error = "❌ " . $e->getMessage(); }
    }

    elseif ($_POST['action'] === 'edit') {
        $stmt = $pdo->prepare("UPDATE customers SET name=?, whatsapp_number=?, default_location_label=?, google_maps_url=?, notes=?, is_blocked=? WHERE id=?");
        try {
            $stmt->execute([$_POST['name'], $_POST['whatsapp'], $_POST['location'], $_POST['maps_url'], $_POST['notes'], isset($_POST['is_blocked'])?'true':'false', $_POST['id']]);
            $msg = "✅ تم تحديث بيانات العميل!";
        } catch(\PDOException $e) { $error = "❌ " . $e->getMessage(); }
    }

    elseif ($_POST['action'] === 'delete') {
        try {
            $pdo->prepare("DELETE FROM customers WHERE id=?")->execute([$_POST['id']]);
            $msg = "✅ تم حذف العميل!";
        } catch(\PDOException $e) { $error = "❌ لا يمكن الحذف، لأن العميل مرتبط بطلبات مسجّلة."; }
    }
}

// Search & filter
$search = trim($_GET['q'] ?? '');
$where = $search ? "WHERE name ILIKE ? OR whatsapp_number ILIKE ?" : "";
$sql = "SELECT c.*, z.name AS zone_name FROM customers c LEFT JOIN zones z ON c.zone_id=z.id $where ORDER BY c.created_at DESC";
$stmt = $pdo->prepare($sql);
if($search) $stmt->execute(["%$search%", "%$search%"]);
else $stmt->execute();
$customers = $stmt->fetchAll();

// Total stats
$total_c = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn();
$blocked_c = $pdo->query("SELECT COUNT(*) FROM customers WHERE is_blocked=true")->fetchColumn();
$new_today = $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE(created_at)=CURRENT_DATE")->fetchColumn();

require 'layout.php';
?>

<div class="page-header">
    <div>
        <h1>👥 إدارة العملاء</h1>
        <p>عرض وإدارة كامل قاعدة العملاء المسجّلين في النظام</p>
    </div>
    <button class="btn btn-primary" onclick="openModal('addModal')">+ إضافة عميل</button>
</div>

<?php if($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-error"><?=$error?></div><?php endif; ?>

<!-- Stats -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1rem;margin-bottom:2rem">
    <div class="card" style="text-align:center;padding:1.5rem">
        <div style="font-size:2rem;font-weight:800;color:var(--primary)"><?=$total_c?></div>
        <div style="color:var(--muted);font-size:.9rem">إجمالي العملاء</div>
    </div>
    <div class="card" style="text-align:center;padding:1.5rem">
        <div style="font-size:2rem;font-weight:800;color:var(--success)"><?=$new_today?></div>
        <div style="color:var(--muted);font-size:.9rem">جدد اليوم</div>
    </div>
    <div class="card" style="text-align:center;padding:1.5rem">
        <div style="font-size:2rem;font-weight:800;color:var(--danger)"><?=$blocked_c?></div>
        <div style="color:var(--muted);font-size:.9rem">محجوبون</div>
    </div>
</div>

<!-- Search -->
<form method="GET" style="margin-bottom:1.5rem;display:flex;gap:1rem">
    <input type="text" name="q" value="<?=htmlspecialchars($search)?>" class="form-control" style="max-width:400px" placeholder="🔍 البحث بالاسم أو رقم الواتساب...">
    <button type="submit" class="btn btn-ghost">بحث</button>
    <?php if($search): ?><a href="customers.php" class="btn btn-ghost">× مسح</a><?php endif; ?>
</form>

<!-- Table -->
<div class="card" style="padding:0;overflow:hidden">
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>الاسم</th>
                <th>واتساب</th>
                <th>الموقع</th>
                <th>المنطقة</th>
                <th>الطلبات</th>
                <th>الإنفاق</th>
                <th>الحالة</th>
                <th>الإجراءات</th>
            </tr>
        </thead>
        <tbody>
        <?php if(empty($customers)): ?>
            <tr><td colspan="9" style="text-align:center;padding:3rem;color:var(--muted)">لا يوجد عملاء حتى الآن.</td></tr>
        <?php else: ?>
        <?php foreach($customers as $c): ?>
            <tr>
                <td style="color:var(--muted);font-size:.85rem"><?=$c['id']?></td>
                <td>
                    <div style="font-weight:600"><?=htmlspecialchars($c['name']??'مجهول')?></div>
                    <div style="font-size:.8rem;color:var(--muted)"><?=htmlspecialchars(date('Y-m-d', strtotime($c['created_at'])))?></div>
                </td>
                <td>
                    <a href="https://wa.me/<?=htmlspecialchars($c['whatsapp_number'])?>" target="_blank" style="color:var(--success);text-decoration:none">
                        📱 <?=htmlspecialchars($c['whatsapp_number'])?>
                    </a>
                </td>
                <td>
                    <?php if($c['google_maps_url']): ?>
                        <a href="<?=htmlspecialchars($c['google_maps_url'])?>" target="_blank" class="btn btn-ghost btn-sm">📍 عرض الخريطة</a>
                    <?php else: ?>
                        <span style="color:var(--muted);font-size:.85rem"><?=htmlspecialchars($c['default_location_label']??'غير محدد')?></span>
                    <?php endif; ?>
                </td>
                <td><span class="badge badge-info"><?=htmlspecialchars($c['zone_name']??'—')?></span></td>
                <td style="font-weight:700;color:var(--primary)"><?=$c['total_orders']?></td>
                <td style="color:var(--success)"><?=number_format($c['total_spent'],0)?> ر.س</td>
                <td>
                    <?php if($c['is_blocked']): ?>
                        <span class="badge badge-danger">محجوب 🚫</span>
                    <?php else: ?>
                        <span class="badge badge-success">نشط ✅</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div style="display:flex;gap:.5rem">
                        <button class="btn btn-ghost btn-sm" onclick='openEdit(<?=json_encode($c)?>)'>✏️ تعديل</button>
                        <form method="POST" onsubmit="return confirm('هل أنت متأكد من الحذف؟')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?=$c['id']?>">
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
            <div class="modal-title">👤 إضافة عميل جديد</div>
            <button class="modal-close" onclick="closeModal('addModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="form-row">
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="name" class="form-control" required placeholder="أحمد الشمري">
                </div>
                <div class="form-group">
                    <label>رقم الواتساب</label>
                    <input type="text" name="whatsapp" class="form-control" required placeholder="966500000000">
                </div>
            </div>
            <div class="form-group">
                <label>وصف الموقع</label>
                <input type="text" name="location" class="form-control" placeholder="حي النزهة، قرب مسجد الفاروق">
            </div>
            <div class="form-group">
                <label>رابط جوجل ماب (اختياري)</label>
                <input type="text" name="maps_url" class="form-control" placeholder="https://maps.google.com/...">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <input type="text" name="notes" class="form-control" placeholder="ملاحظات خاصة...">
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">💾 حفظ العميل</button>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editModal" onclick="closeOnOutside(event,'editModal')">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">✏️ تعديل بيانات العميل</div>
            <button class="modal-close" onclick="closeModal('editModal')">×</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-row">
                <div class="form-group">
                    <label>الاسم الكامل</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>رقم الواتساب</label>
                    <input type="text" name="whatsapp" id="edit_whatsapp" class="form-control" required>
                </div>
            </div>
            <div class="form-group">
                <label>وصف الموقع</label>
                <input type="text" name="location" id="edit_location" class="form-control">
            </div>
            <div class="form-group">
                <label>رابط جوجل ماب</label>
                <input type="text" name="maps_url" id="edit_maps_url" class="form-control">
            </div>
            <div class="form-group">
                <label>ملاحظات</label>
                <input type="text" name="notes" id="edit_notes" class="form-control">
            </div>
            <div class="form-group" style="display:flex;align-items:center;gap:.75rem">
                <input type="checkbox" name="is_blocked" id="edit_blocked" style="width:18px;height:18px;accent-color:var(--danger)">
                <label for="edit_blocked" style="cursor:pointer;color:var(--danger)">🚫 حجب هذا العميل من الطلبات</label>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%">💾 حفظ التعديلات</button>
        </form>
    </div>
</div>

<script>
function openModal(id) { document.getElementById(id).style.display='grid'; }
function closeModal(id) { document.getElementById(id).style.display='none'; }
function closeOnOutside(e,id) { if(e.target.id===id) closeModal(id); }
function openEdit(c) {
    document.getElementById('edit_id').value = c.id;
    document.getElementById('edit_name').value = c.name||'';
    document.getElementById('edit_whatsapp').value = c.whatsapp_number||'';
    document.getElementById('edit_location').value = c.default_location_label||'';
    document.getElementById('edit_maps_url').value = c.google_maps_url||'';
    document.getElementById('edit_notes').value = c.notes||'';
    document.getElementById('edit_blocked').checked = (c.is_blocked === 'true' || c.is_blocked === true);
    openModal('editModal');
}
</script>

<?php require 'layout_end.php'; ?>
