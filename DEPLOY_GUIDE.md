# 🚀 دليل تحديث نظام ديلفرو برو (Delivery Pro)

هذا الدليل يشرح كيفية تحديث النظام ورفعه إلى السيرفر (VPS) بعد الانتقال إلى **Evolution API**.

## 📁 معلومات البيئة (Current State)

| البيان | التفاصيل |
| :--- | :--- |
| **رابط النظام** | [https://delivery.3ezit.com](https://delivery.3ezit.com) |
| **واجهة الإدارة** | [https://delivery.3ezit.com/whatsapp.php](https://delivery.3ezit.com/whatsapp.php) |
| **مسار المشروع** | `/root/delivery` |
| **المنافذ الخارجية** | PHP: 8090, DB: 5433, Evolution: 8088 |
| **نوع النشر** | Docker Compose (PHP + Evolution API + PostgreSQL) |

---

## 🛠️ خطوات التحديث (Deployment Workflow)

### 1. الرفع إلى GitHub (من جهازك)
```powershell
git add .
git commit -m "توضيح التعديلات"
git push origin main
```

### 2. سحب التحديثات على السيرفر (VPS)
```bash
cd /root/delivery
git pull origin main
# إذا تم تعديل docker-compose.yml قم بتحديث الحاويات:
docker compose up -d --remove-orphans
```

## 📱 إدارة الواتساب (Evolution API)
تم استبدال البوت القديم بـ **Evolution API** لضمان استقرار خارق.
- **الإعداد الأول:** بمجرد الدخول لصفحة `whatsapp.php` والضغط على "توليد QR"، سيقوم النظام تلقائياً بإنشاء الـ Instance.
- **الربط:** امسح الـ QR من هاتفك كما تفعل عادة في WhatsApp Web.
- **التنبيهات:** النظام مبرمج لإرسال التنبيهات فورياً عند تغيير حالة الطلب.

---

## 🔒 ملاحظات هامة
- **البيانات الحساسة:** يتم تخزين جلسات الواتساب داخل Docker Volumes (`evolution_instances`) لضمان عدم ضياع الاتصال عند تحديث الكود.
- **قاعدة البيانات:** تم إنشاء قاعدة بيانات منفصلة `evolution_db` لإدارة الجلسات بشكل احترافي.

---
**آخر تحديث:** 2026-04-14 (Migrated to Evolution API)
**بواسطة:** Antigravity AI
