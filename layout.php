<?php
// تحديد الصفحة الحالية
$current_page = basename($_SERVER['PHP_SELF'], '.php');
require_once __DIR__ . '/config.php';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $page_title ?? 'ديلفرو برو' ?> | نظام التوصيل الذكي</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #00f2fe; --secondary: #4facfe; --accent: #a855f7;
            --dark-bg: #070d1a; --surface: #0f172a; --surface-2: #1e293b;
            --glass: rgba(255,255,255,0.04); --glass-hover: rgba(255,255,255,0.07);
            --border: rgba(255,255,255,0.08);
            --text: #f1f5f9; --muted: #64748b;
            --success: #10b981; --warning: #f59e0b; --danger: #ef4444; --info: #3b82f6;
            --sidebar-w: 270px;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html { scroll-behavior: smooth; }
        body { font-family:'Tajawal',sans-serif; background:var(--dark-bg); color:var(--text); display:flex; min-height:100vh; overflow-x:hidden; }
        body::before {
            content:''; position:fixed; inset:0; pointer-events:none; z-index:0;
            background:
                radial-gradient(ellipse 80% 50% at 0% 0%, rgba(79,172,254,0.12) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 100% 100%, rgba(168,85,247,0.1) 0%, transparent 60%);
        }

        /* ====== SIDEBAR ====== */
        .sidebar {
            width: var(--sidebar-w); background: rgba(15,23,42,0.95); backdrop-filter:blur(24px);
            border-left: 1px solid var(--border); display:flex; flex-direction:column;
            position:sticky; top:0; height:100vh; z-index:100; transition:0.3s;
        }
        .sidebar-logo {
            padding: 2rem 1.5rem 1.5rem; border-bottom: 1px solid var(--border);
        }
        .logo-text { font-size:1.6rem; font-weight:900; background:linear-gradient(135deg,var(--primary),var(--secondary),var(--accent)); -webkit-background-clip:text; -webkit-text-fill-color:transparent; }
        .logo-sub { font-size:0.78rem; color:var(--muted); margin-top:0.25rem; }

        .sidebar-nav { flex:1; padding:1.5rem 1rem; display:flex; flex-direction:column; gap:0.4rem; overflow-y:auto; }
        .nav-section-title { font-size:0.7rem; text-transform:uppercase; letter-spacing:1.5px; color:var(--muted); padding:1rem 0.75rem 0.5rem; }
        
        .nav-item { display:flex; align-items:center; gap:0.85rem; padding:0.85rem 1rem; border-radius:12px; text-decoration:none; color:var(--muted); font-weight:500; transition:0.25s; position:relative; font-size:1rem; }
        .nav-item:hover { background:var(--glass-hover); color:var(--text); }
        .nav-item.active { background:linear-gradient(135deg,rgba(0,242,254,0.15),rgba(79,172,254,0.1)); color:var(--primary); border:1px solid rgba(0,242,254,0.2); }
        .nav-item.active::before { content:''; position:absolute; right:0; top:20%; height:60%; width:3px; background:linear-gradient(var(--primary),var(--secondary)); border-radius:2px; }
        .nav-icon { font-size:1.2rem; width:24px; text-align:center; }
        .nav-badge { margin-right:auto; background:var(--danger); color:#fff; font-size:0.7rem; padding:0.15rem 0.5rem; border-radius:50px; }

        .sidebar-bottom { padding:1.5rem; border-top:1px solid var(--border); }
        .sidebar-footer { font-size:0.8rem; color:var(--muted); text-align:center; line-height:1.6; }

        /* ====== MAIN CONTENT ====== */
        .main { flex:1; display:flex; flex-direction:column; position:relative; z-index:1; }
        .topbar {
            display:flex; justify-content:space-between; align-items:center;
            padding: 1.25rem 3rem; background:rgba(7,13,26,0.8); backdrop-filter:blur(16px);
            border-bottom:1px solid var(--border); position:sticky; top:0; z-index:50;
        }
        .topbar-title { font-size:1.4rem; font-weight:700; }
        .topbar-title span { color:var(--primary); }
        .topbar-actions { display:flex; align-items:center; gap:1rem; }
        .status-dot { width:10px; height:10px; background:var(--success); border-radius:50%; box-shadow:0 0 8px var(--success); animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { box-shadow:0 0 6px var(--success); } 50% { box-shadow:0 0 14px var(--success); } }
        .topbar-profile { display:flex; align-items:center; gap:0.75rem; background:var(--glass); border:1px solid var(--border); padding:0.5rem 1rem; border-radius:50px; cursor:pointer; }
        .profile-avatar { width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--primary),var(--accent)); display:grid; place-items:center; font-weight:bold; font-size:0.9rem; }

        .page-content { flex:1; padding:2rem 3rem; }

        /* ====== COMPONENTS ====== */
        .page-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .page-header h1 { font-size:1.8rem; font-weight:800; }
        .page-header p { color:var(--muted); margin-top:0.25rem; }

        .btn { display:inline-flex; align-items:center; gap:0.5rem; padding:0.75rem 1.5rem; border-radius:12px; border:none; cursor:pointer; font-family:'Tajawal'; font-weight:600; font-size:1rem; transition:0.25s; text-decoration:none; }
        .btn-primary { background:linear-gradient(135deg,var(--primary),var(--secondary)); color:#000; box-shadow:0 4px 15px rgba(0,242,254,0.3); }
        .btn-primary:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(0,242,254,0.45); }
        .btn-danger { background:rgba(239,68,68,0.15); color:var(--danger); border:1px solid rgba(239,68,68,0.3); }
        .btn-danger:hover { background:rgba(239,68,68,0.25); }
        .btn-ghost { background:var(--glass); color:var(--text); border:1px solid var(--border); }
        .btn-ghost:hover { background:var(--glass-hover); }
        .btn-sm { padding:0.45rem 1rem; font-size:0.85rem; }

        .card { background:var(--glass); backdrop-filter:blur(16px); border:1px solid var(--border); border-radius:20px; padding:2rem; }
        .card:hover { border-color:rgba(255,255,255,0.12); }

        table { width:100%; border-collapse:collapse; }
        thead th { padding:1rem 1.5rem; text-align:right; color:var(--muted); font-weight:500; font-size:0.85rem; border-bottom:1px solid var(--border); }
        tbody td { padding:1rem 1.5rem; border-bottom:1px solid rgba(255,255,255,0.04); font-size:0.95rem; }
        tbody tr:last-child td { border-bottom:none; }
        tbody tr { transition:0.2s; }
        tbody tr:hover { background:rgba(255,255,255,0.02); }

        .badge { display:inline-flex; align-items:center; gap:0.35rem; padding:0.3rem 0.85rem; border-radius:50px; font-size:0.8rem; font-weight:500; }
        .badge-success { background:rgba(16,185,129,0.15); color:var(--success); border:1px solid rgba(16,185,129,0.3); }
        .badge-warning { background:rgba(245,158,11,0.15); color:var(--warning); border:1px solid rgba(245,158,11,0.3); }
        .badge-danger  { background:rgba(239,68,68,0.15); color:var(--danger); border:1px solid rgba(239,68,68,0.3); }
        .badge-info    { background:rgba(59,130,246,0.15); color:var(--info); border:1px solid rgba(59,130,246,0.3); }
        .badge-muted   { background:rgba(100,116,139,0.15); color:var(--muted); border:1px solid rgba(100,116,139,0.3); }

        /* ====== MODAL ====== */
        .modal-overlay { display:none; position:fixed; inset:0; background:rgba(7,13,26,0.85); backdrop-filter:blur(8px); z-index:1000; place-items:center; }
        .modal-box { background:var(--surface); border:1px solid var(--border); border-radius:24px; padding:2.5rem; width:100%; max-width:520px; box-shadow:0 20px 60px rgba(0,0,0,0.6); animation:modalIn 0.35s ease; }
        @keyframes modalIn { from{opacity:0;transform:translateY(20px) scale(0.97)} to{opacity:1;transform:translateY(0) scale(1)} }
        .modal-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem; }
        .modal-title { font-size:1.4rem; font-weight:700; }
        .modal-close { background:none; border:none; color:var(--muted); font-size:1.5rem; cursor:pointer; line-height:1; transition:0.2s; }
        .modal-close:hover { color:var(--danger); }

        .form-group { margin-bottom:1.5rem; }
        .form-group label { display:block; margin-bottom:0.6rem; color:var(--muted); font-size:0.9rem; }
        .form-control {
            width:100%; padding:0.9rem 1.1rem; border-radius:12px; border:1px solid var(--border);
            background:rgba(0,0,0,0.25); color:var(--text); font-family:'Tajawal'; transition:0.25s;
        }
        .form-control:focus { outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(0,242,254,0.1); }
        .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }

        .alert { padding:1rem 1.5rem; border-radius:12px; margin-bottom:1.5rem; font-weight:500; }
        .alert-success { background:rgba(16,185,129,0.1); color:var(--success); border:1px solid rgba(16,185,129,0.3); }
        .alert-error { background:rgba(239,68,68,0.1); color:var(--danger); border:1px solid rgba(239,68,68,0.3); }

        @media(max-width:768px) { .sidebar{width:100%;height:auto;position:relative} body{flex-direction:column} .page-content{padding:1.5rem} .topbar{padding:1rem 1.5rem} }
    </style>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-text">🚀 ديلفرو برو</div>
            <div class="logo-sub">نظام التوصيل الذكي المتكامل</div>
        </div>
        <nav class="sidebar-nav">
            <span class="nav-section-title">الرئيسية</span>
            <a href="dashboard.php" class="nav-item <?= $current_page=='dashboard'?'active':'' ?>">
                <span class="nav-icon">📊</span> لوحة التحكم
            </a>
            
            <span class="nav-section-title">إدارة البيانات</span>
            <a href="orders.php" class="nav-item <?= $current_page=='orders'?'active':'' ?>">
                <span class="nav-icon">📦</span> الطلبات
            </a>
            <a href="customers.php" class="nav-item <?= $current_page=='customers'?'active':'' ?>">
                <span class="nav-icon">👥</span> العملاء
            </a>
            <a href="drivers.php" class="nav-item <?= $current_page=='drivers'?'active':'' ?>">
                <span class="nav-icon">🛵</span> المناديب
            </a>
            
            <span class="nav-section-title">النظام</span>
            <a href="whatsapp.php" class="nav-item <?= $current_page=='whatsapp'?'active':'' ?>">
                <span class="nav-icon">💬</span> ربط الواتساب
            </a>
            <a href="settings.php" class="nav-item <?= $current_page=='settings'?'active':'' ?>">
                <span class="nav-icon">⚙️</span> الإعدادات
            </a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-footer">
                <div style="display:flex;align-items:center;justify-content:center;gap:.5rem;margin-bottom:.5rem">
                    <span class="status-dot"></span>
                    <span>النظام يعمل بكفاءة</span>
                </div>
                النسخة 1.0 &nbsp;·&nbsp; جميع الحقوق محفوظة
            </div>
        </div>
    </aside>

    <div class="main">
        <!-- TOPBAR -->
        <div class="topbar">
            <div class="topbar-title">
                <span><?= $page_title ?? 'لوحة التحكم' ?></span>
            </div>
            <div class="topbar-actions">
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--muted);font-size:.85rem">
                    <span class="status-dot"></span> متصل بـ PostgreSQL
                </div>
                <div class="topbar-profile">
                    <div class="profile-avatar">م</div>
                    <span style="font-size:.9rem">المدير</span>
                </div>
            </div>
        </div>
        
        <div class="page-content">
