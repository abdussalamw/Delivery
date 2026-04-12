<?php
$page_title = 'لوحة التحكم';
require_once 'config.php';

// جلب البيانات الحية
try {
    $today_orders     = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(ordered_at)=CURRENT_DATE")->fetchColumn() ?: 0;
    $active_orders    = $pdo->query("SELECT COUNT(*) FROM orders WHERE status NOT IN ('delivered','cancelled','returned')")->fetchColumn() ?: 0;
    $today_earnings   = $pdo->query("SELECT COALESCE(SUM(delivery_fee),0) FROM orders WHERE DATE(ordered_at)=CURRENT_DATE AND status='delivered'")->fetchColumn() ?: 0;
    $avg_time         = round($pdo->query("SELECT COALESCE(AVG(total_duration_min),0) FROM orders WHERE DATE(ordered_at)=CURRENT_DATE AND status='delivered'")->fetchColumn() ?: 0);
    $total_customers  = $pdo->query("SELECT COUNT(*) FROM customers")->fetchColumn() ?: 0;
    $total_drivers    = $pdo->query("SELECT COUNT(*) FROM drivers WHERE is_active=true")->fetchColumn() ?: 0;
    $online_drivers   = $pdo->query("SELECT COUNT(*) FROM drivers WHERE status IN ('online','busy')")->fetchColumn() ?: 0;
    $new_customers    = $pdo->query("SELECT COUNT(*) FROM customers WHERE DATE(created_at)=CURRENT_DATE")->fetchColumn() ?: 0;
    $cancelled_today  = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(ordered_at)=CURRENT_DATE AND status='cancelled'")->fetchColumn() ?: 0;
    $delivered_today  = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(ordered_at)=CURRENT_DATE AND status='delivered'")->fetchColumn() ?: 0;

    // الطلبات النشطة للعرض
    $stmt_orders = $pdo->query("
        SELECT o.id, o.order_number, o.status, o.delivery_fee, o.stores_count, o.ordered_at,
               o.delivery_location_label,
               c.name AS customer_name, c.whatsapp_number AS customer_wa,
               d.name AS driver_name
        FROM orders o
        LEFT JOIN customers c ON o.customer_id=c.id
        LEFT JOIN drivers d ON o.driver_id=d.id
        WHERE o.status NOT IN ('delivered','cancelled','returned')
        ORDER BY o.ordered_at DESC LIMIT 8
    ");
    $live_orders = $stmt_orders->fetchAll();

    // أداء المناديب اليوم
    $stmt_drivers = $pdo->query("
        SELECT d.id, d.name, d.status, d.current_orders, d.rating, d.vehicle_type,
               COUNT(o.id) FILTER(WHERE DATE(o.ordered_at)=CURRENT_DATE AND o.status='delivered') AS done_today,
               COALESCE(SUM(o.delivery_fee) FILTER(WHERE DATE(o.ordered_at)=CURRENT_DATE AND o.status='delivered'),0) AS earned_today,
               d.target_orders
        FROM drivers d
        LEFT JOIN orders o ON o.driver_id=d.id
        WHERE d.is_active=true
        GROUP BY d.id
        ORDER BY done_today DESC
    ");
    $drivers_perf = $stmt_drivers->fetchAll();

    // آخر العملاء
    $recent_customers = $pdo->query("SELECT name, whatsapp_number, total_orders, created_at FROM customers ORDER BY created_at DESC LIMIT 5")->fetchAll();

    // إحصائيات الأسبوع للرسم البياني
    $week_stats = $pdo->query("
        SELECT DATE(ordered_at) AS day,
               COUNT(*) AS total,
               COUNT(*) FILTER(WHERE status='delivered') AS delivered,
               COALESCE(SUM(delivery_fee) FILTER(WHERE status='delivered'),0) AS revenue
        FROM orders
        WHERE ordered_at >= CURRENT_DATE - INTERVAL '6 days'
        GROUP BY DATE(ordered_at)
        ORDER BY day
    ")->fetchAll();

} catch(\PDOException $e) {
    $err = $e->getMessage();
    $today_orders=$active_orders=$today_earnings=$avg_time=$total_customers=$total_drivers=$online_drivers=$new_customers=$cancelled_today=$delivered_today=0;
    $live_orders=$drivers_perf=$recent_customers=$week_stats=[];
}

$status_ar = [
    'new'=>['🆕 جديد','badge-info'],
    'assigned'=>['📋 مسند','badge-warning'],
    'picking_up'=>['🏪 في المتجر','badge-warning'],
    'on_the_way'=>['🛵 في الطريق','badge-success'],
    'delivered'=>['✅ مُسلّم','badge-success'],
    'cancelled'=>['❌ ملغي','badge-danger'],
];

require 'layout.php';
?>

<style>
.kpi-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:1.25rem; margin-bottom:2rem; }
.kpi { background:var(--glass); border:1px solid var(--border); border-radius:20px; padding:1.5rem; position:relative; overflow:hidden; transition:.3s; }
.kpi:hover { border-color:rgba(255,255,255,.15); transform:translateY(-3px); box-shadow:0 12px 30px rgba(0,0,0,.25); }
.kpi::after { content:''; position:absolute; inset:0; background:linear-gradient(135deg,transparent 60%,rgba(255,255,255,.02)); pointer-events:none; }
.kpi-icon { font-size:2rem; position:absolute; left:1.5rem; top:1.25rem; opacity:.15; }
.kpi-label { font-size:.8rem; color:var(--muted); margin-bottom:.5rem; text-transform:uppercase; letter-spacing:.5px; }
.kpi-val { font-size:2.2rem; font-weight:900; line-height:1; }
.kpi-sub { font-size:.8rem; margin-top:.4rem; }

.dash-grid { display:grid; grid-template-columns:1fr 380px; gap:1.5rem; margin-bottom:1.5rem; }
.dash-3col { display:grid; grid-template-columns:1fr 1fr 1fr; gap:1.5rem; }
@media(max-width:1200px) { .kpi-grid{grid-template-columns:repeat(2,1fr)} .dash-grid{grid-template-columns:1fr} .dash-3col{grid-template-columns:1fr} }

.section-title { font-size:1rem; font-weight:700; margin-bottom:1.25rem; display:flex; align-items:center; justify-content:space-between; }
.section-title a { font-size:.8rem; color:var(--secondary); text-decoration:none; font-weight:400; }
.section-title a:hover { color:var(--primary); }

.order-row { display:flex; align-items:center; gap:1rem; padding:.85rem 0; border-bottom:1px solid rgba(255,255,255,.04); }
.order-row:last-child { border-bottom:none; }
.order-num { font-size:.75rem; color:var(--muted); min-width:80px; font-family:monospace; }
.order-info { flex:1; }
.order-name { font-size:.9rem; font-weight:600; }
.order-loc { font-size:.75rem; color:var(--muted); }

.driver-row { display:flex; align-items:center; gap:1rem; padding:.85rem 0; border-bottom:1px solid rgba(255,255,255,.04); }
.driver-row:last-child { border-bottom:none; }
.drv-avatar { width:40px; height:40px; border-radius:10px; background:var(--glass); border:1px solid var(--border); display:grid; place-items:center; font-weight:700; color:var(--secondary); flex-shrink:0; }
.drv-bar-track { height:5px; background:rgba(255,255,255,.08); border-radius:3px; margin-top:4px; }
.drv-bar-fill { height:5px; border-radius:3px; background:linear-gradient(90deg,var(--primary),var(--secondary)); }

.status-dot-sm { width:8px; height:8px; border-radius:50%; display:inline-block; }
.dot-online { background:var(--success); box-shadow:0 0 6px var(--success); }
.dot-busy { background:var(--warning); box-shadow:0 0 6px var(--warning); }
.dot-break { background:var(--muted); }
.dot-offline { background:var(--danger); }

.chart-bar-wrap { display:flex; align-items:flex-end; gap:.5rem; height:80px; }
.chart-bar { flex:1; border-radius:6px 6px 0 0; background:linear-gradient(to top,var(--primary),var(--secondary)); opacity:.7; transition:.3s; cursor:pointer; }
.chart-bar:hover { opacity:1; }

.quick-action { display:flex; align-items:center; gap:.75rem; padding:1rem; border-radius:14px; background:rgba(255,255,255,.03); border:1px solid var(--border); text-decoration:none; color:var(--text); transition:.25s; }
.quick-action:hover { background:var(--glass-hover); border-color:var(--primary); transform:translateX(-4px); }
.quick-action-icon { width:42px; height:42px; border-radius:10px; display:grid; place-items:center; font-size:1.3rem; flex-shrink:0; }
</style>

<!-- KPIs -->
<div class="kpi-grid">
    <div class="kpi" style="border-color:rgba(0,242,254,.2)">
        <div class="kpi-icon">📦</div>
        <div class="kpi-label">طلبات اليوم</div>
        <div class="kpi-val" style="color:var(--primary)"><?=$today_orders?></div>
        <div class="kpi-sub" style="color:var(--success)">✅ <?=$delivered_today?> مكتمل &nbsp;·&nbsp; <span style="color:var(--info)"><?=$active_orders?> نشط</span></div>
    </div>
    <div class="kpi" style="border-color:rgba(16,185,129,.2)">
        <div class="kpi-icon">💰</div>
        <div class="kpi-label">إيرادات التوصيل</div>
        <div class="kpi-val" style="color:var(--success)"><?=number_format($today_earnings,0)?></div>
        <div class="kpi-sub" style="color:var(--muted)">ريال سعودي اليوم</div>
    </div>
    <div class="kpi" style="border-color:rgba(168,85,247,.2)">
        <div class="kpi-icon">🛵</div>
        <div class="kpi-label">المناديب</div>
        <div class="kpi-val" style="color:var(--accent)"><?=$online_drivers?> / <?=$total_drivers?></div>
        <div class="kpi-sub" style="color:var(--muted)">متصل / إجمالي</div>
    </div>
    <div class="kpi" style="border-color:rgba(245,158,11,.2)">
        <div class="kpi-icon">⏱️</div>
        <div class="kpi-label">متوسط التوصيل</div>
        <div class="kpi-val" style="color:var(--warning)"><?=$avg_time?></div>
        <div class="kpi-sub" style="color:var(--muted)">دقيقة للطلب</div>
    </div>
</div>

<!-- Second Row KPIs -->
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;margin-bottom:2rem">
    <div class="kpi">
        <div class="kpi-icon">👥</div>
        <div class="kpi-label">إجمالي العملاء</div>
        <div class="kpi-val" style="font-size:1.8rem"><?=$total_customers?></div>
        <div class="kpi-sub" style="color:var(--success)">+<?=$new_customers?> جديد اليوم</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">❌</div>
        <div class="kpi-label">ملغيات اليوم</div>
        <div class="kpi-val" style="font-size:1.8rem;color:var(--danger)"><?=$cancelled_today?></div>
        <div class="kpi-sub" style="color:var(--muted)">من <?=$today_orders?> طلب</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">📊</div>
        <div class="kpi-label">معدل الإتمام</div>
        <div class="kpi-val" style="font-size:1.8rem;color:var(--success)"><?=$today_orders>0?round(($delivered_today/$today_orders)*100):0?>%</div>
        <div class="kpi-sub" style="color:var(--muted)">نسبة النجاح</div>
    </div>
    <div class="kpi">
        <div class="kpi-icon">💵</div>
        <div class="kpi-label">متوسط قيمة الطلب</div>
        <div class="kpi-val" style="font-size:1.8rem;color:var(--secondary)"><?=$delivered_today>0?number_format($today_earnings/$delivered_today,1):0?></div>
        <div class="kpi-sub" style="color:var(--muted)">ريال / طلب</div>
    </div>
</div>

<div class="dash-grid">

    <!-- Live Orders -->
    <div class="card">
        <div class="section-title">
            <span>⚡ الطلبات النشطة مباشرة</span>
            <a href="orders.php">عرض الكل →</a>
        </div>
        <?php if(empty($live_orders)): ?>
            <div style="text-align:center;padding:3rem;color:var(--muted)">
                <div style="font-size:3rem;margin-bottom:.75rem">📭</div>
                لا توجد طلبات نشطة حالياً
            </div>
        <?php else: ?>
        <?php foreach($live_orders as $o):
            $sl = $status_ar[$o['status']] ?? [$o['status'],'badge-muted'];
        ?>
        <div class="order-row">
            <div class="order-num"><?=htmlspecialchars($o['order_number']??'#')?></div>
            <div class="order-info">
                <div class="order-name"><?=htmlspecialchars($o['customer_name']??'مجهول')?></div>
                <div class="order-loc">📍 <?=htmlspecialchars($o['delivery_location_label']??'—')?> &nbsp;·&nbsp; 🛵 <?=htmlspecialchars($o['driver_name']??'غير مسند')?></div>
            </div>
            <div style="text-align:left;min-width:100px">
                <div><span class="badge <?=$sl[1]?>" style="font-size:.72rem"><?=$sl[0]?></span></div>
                <div style="font-size:.8rem;color:var(--success);margin-top:.3rem;font-weight:700"><?=$o['delivery_fee']?> ر.س</div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <div style="margin-top:1.5rem">
            <a href="orders.php?action=new" class="btn btn-primary" style="width:100%;justify-content:center">+ تسجيل طلب جديد</a>
        </div>
    </div>

    <!-- Drivers Performance -->
    <div class="card">
        <div class="section-title">
            <span>🛵 أداء المناديب اليوم</span>
            <a href="drivers.php">الكل →</a>
        </div>
        <?php if(empty($drivers_perf)): ?>
            <div style="text-align:center;padding:2rem;color:var(--muted)">لا يوجد مناديب</div>
        <?php else: ?>
        <?php foreach($drivers_perf as $d):
            $pct = $d['target_orders']>0 ? min(100, round(($d['done_today']/$d['target_orders'])*100)) : 0;
            $dots = ['online'=>'dot-online','busy'=>'dot-busy','break'=>'dot-break','offline'=>'dot-offline'];
        ?>
        <div class="driver-row">
            <div class="drv-avatar"><?=mb_substr($d['name'],0,1)?></div>
            <div style="flex:1;min-width:0">
                <div style="display:flex;align-items:center;gap:.5rem">
                    <span style="font-weight:600;font-size:.9rem"><?=htmlspecialchars($d['name'])?></span>
                    <span class="status-dot-sm <?=$dots[$d['status']]??'dot-offline'?>"></span>
                </div>
                <div style="font-size:.75rem;color:var(--muted);margin-bottom:4px"><?=$d['done_today']?> / <?=$d['target_orders']?> طلب · <?=number_format($d['earned_today'],0)?> ر.س</div>
                <div class="drv-bar-track">
                    <div class="drv-bar-fill" style="width:<?=$pct?>%"></div>
                </div>
            </div>
            <div style="font-size:.75rem;color:<?=$pct>=100?'var(--success)':'var(--muted)'?>;font-weight:700;min-width:35px;text-align:left"><?=$pct?>%</div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <div style="margin-top:1.5rem">
            <a href="drivers.php" class="btn btn-ghost" style="width:100%;justify-content:center">+ إضافة مندوب</a>
        </div>
    </div>
</div>

<!-- Bottom Row -->
<div class="dash-3col">

    <!-- Chart -->
    <div class="card">
        <div class="section-title"><span>📈 الطلبات – آخر 7 أيام</span></div>
        <?php
        $max_rev = max(array_column($week_stats,'total') ?: [1]);
        ?>
        <?php if(!empty($week_stats)): ?>
        <div class="chart-bar-wrap" style="margin-bottom:.75rem">
            <?php foreach($week_stats as $ws):
                $h = $max_rev > 0 ? max(6, round(($ws['total']/$max_rev)*80)) : 6;
            ?>
            <div style="flex:1;display:flex;flex-direction:column;align-items:center;gap:.4rem">
                <div style="font-size:.7rem;color:var(--muted)"><?=$ws['total']?></div>
                <div class="chart-bar" style="height:<?=$h?>px;width:100%" title="<?=$ws['day']?>: <?=$ws['total']?> طلب"></div>
                <div style="font-size:.65rem;color:var(--muted)"><?=date('d/m',strtotime($ws['day']))?></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="text-align:center;color:var(--muted);padding:2rem">لا توجد بيانات كافية</div>
        <?php endif; ?>
    </div>

    <!-- Recent Customers -->
    <div class="card">
        <div class="section-title">
            <span>👥 آخر العملاء</span>
            <a href="customers.php">الكل →</a>
        </div>
        <?php if(empty($recent_customers)): ?>
            <div style="text-align:center;color:var(--muted);padding:2rem">لا يوجد عملاء</div>
        <?php else: ?>
        <?php foreach($recent_customers as $c): ?>
        <div style="display:flex;align-items:center;gap:.75rem;padding:.75rem 0;border-bottom:1px solid rgba(255,255,255,.04)">
            <div style="width:36px;height:36px;border-radius:9px;background:var(--glass);border:1px solid var(--border);display:grid;place-items:center;color:var(--secondary);font-weight:700;flex-shrink:0">
                <?=mb_substr($c['name']??'؟',0,1)?>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.9rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?=htmlspecialchars($c['name']??'مجهول')?></div>
                <div style="font-size:.75rem;color:var(--muted)"><?=$c['total_orders']?> طلب · <?=date('d/m',strtotime($c['created_at']))?></div>
            </div>
            <a href="https://wa.me/<?=$c['whatsapp_number']?>" target="_blank" style="font-size:1.1rem;text-decoration:none">💬</a>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="section-title"><span>⚡ الإجراءات السريعة</span></div>
        <div style="display:flex;flex-direction:column;gap:.75rem">
            <a href="orders.php" class="quick-action">
                <div class="quick-action-icon" style="background:rgba(0,242,254,.1);color:var(--primary)">📦</div>
                <div><div style="font-weight:600">إدارة الطلبات</div><div style="font-size:.8rem;color:var(--muted)">تتبع وتعديل الطلبات</div></div>
            </a>
            <a href="customers.php" class="quick-action">
                <div class="quick-action-icon" style="background:rgba(16,185,129,.1);color:var(--success)">👥</div>
                <div><div style="font-weight:600">قاعدة العملاء</div><div style="font-size:.8rem;color:var(--muted)">إضافة وتعديل العملاء</div></div>
            </a>
            <a href="drivers.php" class="quick-action">
                <div class="quick-action-icon" style="background:rgba(168,85,247,.1);color:var(--accent)">🛵</div>
                <div><div style="font-weight:600">المناديب</div><div style="font-size:.8rem;color:var(--muted)">إدارة المناديب والحالات</div></div>
            </a>
            <a href="whatsapp.php" class="quick-action">
                <div class="quick-action-icon" style="background:rgba(245,158,11,.1);color:var(--warning)">💬</div>
                <div><div style="font-weight:600">ربط الواتساب</div><div style="font-size:.8rem;color:var(--muted)">إدارة بوت الاستقبال</div></div>
            </a>
            <a href="settings.php" class="quick-action">
                <div class="quick-action-icon" style="background:rgba(59,130,246,.1);color:var(--info)">⚙️</div>
                <div><div style="font-weight:600">الإعدادات</div><div style="font-size:.8rem;color:var(--muted)">ضبط النظام والرسوم</div></div>
            </a>
        </div>
    </div>
</div>

<?php require 'layout_end.php'; ?>
