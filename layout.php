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
    <link rel="stylesheet" href="style.css">
    <script>
        // Theme Management
        const savedTheme = localStorage.getItem('delivery-theme') || 'light';
        
        function applyTheme(theme) {
            document.body.setAttribute('data-theme', theme);
            localStorage.setItem('delivery-theme', theme);
            updateThemeIcon(theme);
        }

        function toggleTheme() {
            const current = document.body.getAttribute('data-theme') || 'light';
            const next = current === 'dark' ? 'light' : 'dark';
            applyTheme(next);
        }

        function updateThemeIcon(theme) {
            const icon = document.getElementById('theme-icon');
            if(icon) icon.textContent = theme === 'dark' ? '🌙' : '☀️';
        }

        function toggleSidebar() {
            document.querySelector('.sidebar').classList.toggle('open');
        }

        window.addEventListener('DOMContentLoaded', () => {
            const theme = localStorage.getItem('delivery-theme') || 'light';
            applyTheme(theme);
        });
    </script>
</head>
<body>
    <!-- SIDEBAR -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div style="display:flex;align-items:center;justify-content:space-between;width:100%">
                <div>
                    <div class="logo-text">🚀 ديلفرو برو</div>
                    <div class="logo-sub">نظام التوصيل الذكي المتكامل</div>
                </div>
                <button class="menu-toggle" onclick="toggleSidebar()">☰</button>
            </div>
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
            <div style="display:flex;align-items:center;gap:1.5rem">
                <button class="menu-toggle" onclick="toggleSidebar()" style="display:none">☰</button>
                <div class="topbar-title">
                    <span><?= $page_title ?? 'لوحة التحكم' ?></span>
                </div>
            </div>
            <div class="topbar-actions">
                <button class="theme-toggle" onclick="toggleTheme()" title="تغيير الثيم">
                    <span id="theme-icon">🌙</span>
                </button>
                <div style="display:flex;align-items:center;gap:.5rem;color:var(--muted);font-size:.8rem" class="status-indicator">
                    <span class="status-dot"></span> <span class="status-text">PostgreSQL</span>
                </div>
                <div class="topbar-profile">
                    <div class="profile-avatar">م</div>
                    <span style="font-size:.85rem">المدير</span>
                </div>
            </div>
        </div>
        
        <div class="page-content">
