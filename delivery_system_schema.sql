-- ============================================================
-- نظام إدارة التوصيل الذكي
-- Delivery Management System — PostgreSQL Schema
-- ============================================================
-- تشغيل محلي: psql -U postgres -d delivery_db -f schema.sql
-- ============================================================

-- إنشاء قاعدة البيانات (شغّلها منفصلة أول مرة)
-- CREATE DATABASE delivery_db ENCODING 'UTF8';

-- ============================================================
-- 1. جدول المناطق (يُملأ مسبقاً بالمناطق الجغرافية)
-- ============================================================
CREATE TABLE IF NOT EXISTS zones (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,           -- اسم المنطقة (السيل الكبير)
    name_en         VARCHAR(100),                    -- الاسم بالإنجليزي للخرائط
    -- مركز المنطقة الجغرافي (للحساب التقريبي)
    center_lat      DECIMAL(10, 7) NOT NULL,
    center_lng      DECIMAL(10, 7) NOT NULL,
    -- حدود المنطقة (Bounding Box)
    bounds_ne_lat   DECIMAL(10, 7),                  -- الحد الشمالي الشرقي
    bounds_ne_lng   DECIMAL(10, 7),
    bounds_sw_lat   DECIMAL(10, 7),                  -- الحد الجنوبي الغربي
    bounds_sw_lng   DECIMAL(10, 7),
    -- التسعير
    base_delivery_fee DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    is_active       BOOLEAN DEFAULT TRUE,
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 2. جدول العملاء
-- ============================================================
CREATE TABLE IF NOT EXISTS customers (
    id              SERIAL PRIMARY KEY,
    -- بيانات التواصل
    whatsapp_number VARCHAR(20) UNIQUE NOT NULL,     -- رقم الواتساب (المفتاح الرئيسي للتعرف)
    name            VARCHAR(150),                    -- الاسم (يُستخرج من الذكاء الاصطناعي)
    -- الموقع الافتراضي (من أول طلب)
    default_lat     DECIMAL(10, 7),
    default_lng     DECIMAL(10, 7),
    default_location_label VARCHAR(255),             -- وصف الموقع نصياً (حي النزهة، قرب مسجد...)
    google_maps_url TEXT,                            -- رابط جوجل ماب المرسل من العميل
    zone_id         INTEGER REFERENCES zones(id),   -- المنطقة المحسوبة تلقائياً
    -- إحصائيات
    total_orders    INTEGER DEFAULT 0,
    total_spent     DECIMAL(10,2) DEFAULT 0.00,
    -- مصدر الإضافة
    source          VARCHAR(20) DEFAULT 'whatsapp'   -- whatsapp / manual
        CHECK (source IN ('whatsapp', 'manual')),
    is_blocked      BOOLEAN DEFAULT FALSE,
    notes           TEXT,
    first_order_at  TIMESTAMP,
    last_order_at   TIMESTAMP,
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_customers_whatsapp ON customers(whatsapp_number);
CREATE INDEX idx_customers_zone     ON customers(zone_id);

-- ============================================================
-- 3. جدول المناديب (الموصّلين)
-- ============================================================
CREATE TABLE IF NOT EXISTS drivers (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(150) NOT NULL,
    whatsapp_number VARCHAR(20) UNIQUE NOT NULL,
    national_id     VARCHAR(20),
    -- بيانات المركبة
    vehicle_type    VARCHAR(50),                     -- نوع السيارة (سيدان، دباب، إلخ)
    vehicle_plate   VARCHAR(20),                     -- لوحة السيارة
    -- الراتب والنسب
    base_salary     DECIMAL(8,2) DEFAULT 0.00,       -- الراتب الثابت
    commission_rate DECIMAL(5,4) DEFAULT 0.00,       -- نسبة العمولة (0.1 = 10%)
    target_orders   INTEGER DEFAULT 50,              -- التارجت اليومي للعمولة
    -- الحالة الحالية
    status          VARCHAR(20) DEFAULT 'offline'
        CHECK (status IN ('online', 'busy', 'break', 'offline')),
    current_orders  INTEGER DEFAULT 0,               -- عدد الطلبات النشطة الآن
    -- الموقع الحالي (يُحدَّث من الجوال)
    current_lat     DECIMAL(10, 7),
    current_lng     DECIMAL(10, 7),
    last_location_at TIMESTAMP,
    -- إحصائيات عامة
    total_orders_completed INTEGER DEFAULT 0,
    total_earnings  DECIMAL(10,2) DEFAULT 0.00,
    rating          DECIMAL(3,2) DEFAULT 5.00,       -- التقييم من 5
    is_active       BOOLEAN DEFAULT TRUE,
    notes           TEXT,
    joined_at       DATE DEFAULT CURRENT_DATE,
    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 4. جدول ورديات المناديب (سجل الحضور والانصراف)
-- ============================================================
CREATE TABLE IF NOT EXISTS driver_shifts (
    id              SERIAL PRIMARY KEY,
    driver_id       INTEGER NOT NULL REFERENCES drivers(id),
    shift_date      DATE NOT NULL DEFAULT CURRENT_DATE,
    check_in_at     TIMESTAMP NOT NULL DEFAULT NOW(),
    check_out_at    TIMESTAMP,                       -- NULL = لا يزال في الوردية
    -- الإحصائيات المحسوبة عند الانصراف
    total_minutes   INTEGER,                         -- إجمالي دقائق العمل
    orders_completed INTEGER DEFAULT 0,
    total_earned    DECIMAL(8,2) DEFAULT 0.00,
    -- مصدر تسجيل الدخول
    check_in_source VARCHAR(20) DEFAULT 'whatsapp'
        CHECK (check_in_source IN ('whatsapp', 'manual', 'dashboard')),
    notes           TEXT,
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_shifts_driver_date ON driver_shifts(driver_id, shift_date);

-- ============================================================
-- 5. جدول المتاجر / المطاعم (اختياري — للتحليل مستقبلاً)
-- ============================================================
CREATE TABLE IF NOT EXISTS stores (
    id              SERIAL PRIMARY KEY,
    name            VARCHAR(200) NOT NULL,           -- اسم المتجر
    name_aliases    TEXT[],                          -- أسماء بديلة (هرفي، هرفيز، harfy)
    zone_id         INTEGER REFERENCES zones(id),
    lat             DECIMAL(10, 7),
    lng             DECIMAL(10, 7),
    google_maps_url TEXT,
    category        VARCHAR(50),                     -- مطعم / بقالة / صيدلية ...
    is_active       BOOLEAN DEFAULT TRUE,
    created_at      TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 6. جدول الطلبات (القلب الأساسي)
-- ============================================================
CREATE TABLE IF NOT EXISTS orders (
    id              SERIAL PRIMARY KEY,
    order_number    VARCHAR(20) UNIQUE NOT NULL,     -- ORD-20240412-001 (للعرض)

    -- ===== العميل والموقع =====
    customer_id     INTEGER REFERENCES customers(id),
    -- موقع التسليم (قد يختلف عن الموقع الافتراضي)
    delivery_lat    DECIMAL(10, 7),
    delivery_lng    DECIMAL(10, 7),
    delivery_location_label VARCHAR(255),
    delivery_maps_url TEXT,
    zone_id         INTEGER REFERENCES zones(id),

    -- ===== المندوب =====
    driver_id       INTEGER REFERENCES drivers(id),
    assigned_at     TIMESTAMP,                       -- وقت الإسناد للمندوب

    -- ===== تفاصيل الطلب =====
    raw_messages    TEXT,                            -- الرسائل الخام من العميل (للمراجعة)
    ai_summary      TEXT,                            -- ملخص الذكاء الاصطناعي
    special_notes   TEXT,                            -- ملاحظات خاصة (بدون شطة، مستعجل)

    -- ===== المتاجر =====
    stores_count    INTEGER DEFAULT 1,               -- عدد المتاجر
    stores_details  JSONB,                           -- تفاصيل كل متجر وطلباته
    -- مثال JSONB:
    -- [
    --   {"store_name": "أحلى وجبة", "items": ["برقر دجاج x2 بدون شطة", "حمضيات x2"]},
    --   {"store_name": "صنوان", "items": ["سبانش بارد x1"]}
    -- ]

    -- ===== التسعير =====
    -- قاعدة التسعير: متجر أو متجران = 10, ثلاثة = 15, أربعة = 20
    delivery_fee    DECIMAL(6,2) NOT NULL DEFAULT 10.00,
    items_cost      DECIMAL(8,2),                    -- قيمة المشتريات (يُدخلها المندوب)
    total_amount    DECIMAL(8,2),                    -- delivery_fee + items_cost
    -- هل العميل سدّد؟
    payment_status  VARCHAR(20) DEFAULT 'pending'
        CHECK (payment_status IN ('pending', 'paid', 'partial', 'refunded')),
    payment_method  VARCHAR(20) DEFAULT 'cash'
        CHECK (payment_method IN ('cash', 'transfer', 'other')),
    amount_paid     DECIMAL(8,2) DEFAULT 0.00,

    -- ===== حالة الطلب =====
    status          VARCHAR(30) DEFAULT 'new'
        CHECK (status IN (
            'new',           -- طلب جديد
            'confirmed',     -- تأكيد (إن وُجد)
            'assigned',      -- تم إسناده لمندوب
            'picking_up',    -- المندوب في المتجر
            'on_the_way',    -- في الطريق للعميل
            'delivered',     -- تم التسليم
            'cancelled',     -- ملغي
            'returned'       -- مرتجع
        )),
    cancellation_reason TEXT,

    -- ===== الأوقات =====
    ordered_at      TIMESTAMP NOT NULL DEFAULT NOW(),   -- وقت إنشاء الطلب
    confirmed_at    TIMESTAMP,
    picked_up_at    TIMESTAMP,                          -- استلم من المتجر
    delivered_at    TIMESTAMP,                          -- سلّم للعميل
    cancelled_at    TIMESTAMP,
    -- الأوقات المحسوبة (تُملأ عند إغلاق الطلب)
    pickup_duration_min  INTEGER,                       -- من الإسناد حتى الاستلام
    delivery_duration_min INTEGER,                      -- من الاستلام حتى التسليم
    total_duration_min   INTEGER,                       -- إجمالي وقت الطلب

    -- ===== صورة الفاتورة =====
    invoice_image_url TEXT,                             -- رابط صورة الفاتورة

    -- ===== مصدر الطلب =====
    source          VARCHAR(20) DEFAULT 'whatsapp'
        CHECK (source IN ('whatsapp', 'manual', 'dashboard')),

    -- ===== تقييم العميل =====
    customer_rating INTEGER CHECK (customer_rating BETWEEN 1 AND 5),
    customer_feedback TEXT,

    created_at      TIMESTAMP DEFAULT NOW(),
    updated_at      TIMESTAMP DEFAULT NOW()
);

-- فهارس الطلبات
CREATE INDEX idx_orders_customer    ON orders(customer_id);
CREATE INDEX idx_orders_driver      ON orders(driver_id);
CREATE INDEX idx_orders_status      ON orders(status);
CREATE INDEX idx_orders_date        ON orders(ordered_at);
CREATE INDEX idx_orders_zone        ON orders(zone_id);
CREATE INDEX idx_orders_number      ON orders(order_number);

-- ============================================================
-- 7. جدول سجل أحداث الطلب (Timeline)
-- ============================================================
CREATE TABLE IF NOT EXISTS order_events (
    id              SERIAL PRIMARY KEY,
    order_id        INTEGER NOT NULL REFERENCES orders(id) ON DELETE CASCADE,
    event_type      VARCHAR(50) NOT NULL,
    -- أمثلة: created, assigned, driver_confirmed, picked_up,
    --        on_the_way, delivered, cancelled, payment_received,
    --        invoice_uploaded, note_added
    description     TEXT,
    performed_by    VARCHAR(20) DEFAULT 'system'
        CHECK (performed_by IN ('system', 'driver', 'customer', 'admin')),
    performer_id    INTEGER,                         -- id المندوب أو المدير
    metadata        JSONB,                           -- بيانات إضافية مرنة
    created_at      TIMESTAMP DEFAULT NOW()
);

CREATE INDEX idx_events_order ON order_events(order_id);

-- ============================================================
-- 8. جدول التقارير اليومية (يُملأ تلقائياً بـ cron job في n8n)
-- ============================================================
CREATE TABLE IF NOT EXISTS daily_reports (
    id              SERIAL PRIMARY KEY,
    report_date     DATE NOT NULL UNIQUE,
    -- إجماليات اليوم
    total_orders        INTEGER DEFAULT 0,
    completed_orders    INTEGER DEFAULT 0,
    cancelled_orders    INTEGER DEFAULT 0,
    total_delivery_fees DECIMAL(10,2) DEFAULT 0.00,
    total_items_cost    DECIMAL(10,2) DEFAULT 0.00,
    avg_delivery_time   INTEGER,                     -- بالدقائق
    new_customers       INTEGER DEFAULT 0,
    -- ملخص كل مندوب (JSONB لتسهيل القراءة في Dashboard)
    drivers_summary     JSONB,
    -- مثال:
    -- [{"driver_id":1,"name":"محمد","orders":15,"earned":150,"avg_time":22}]
    generated_at    TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 9. جدول الإعدادات العامة للنظام
-- ============================================================
CREATE TABLE IF NOT EXISTS settings (
    key             VARCHAR(100) PRIMARY KEY,
    value           TEXT NOT NULL,
    description     TEXT,
    updated_at      TIMESTAMP DEFAULT NOW()
);

-- ============================================================
-- 10. جدول رسوم التوصيل حسب عدد المتاجر
-- ============================================================
CREATE TABLE IF NOT EXISTS delivery_fee_rules (
    id              SERIAL PRIMARY KEY,
    stores_count    INTEGER NOT NULL UNIQUE,         -- عدد المتاجر
    fee             DECIMAL(6,2) NOT NULL,           -- الرسوم
    description     TEXT
);

-- ============================================================
-- بيانات أولية — Initial Data
-- ============================================================

-- قواعد التسعير
INSERT INTO delivery_fee_rules (stores_count, fee, description) VALUES
(1, 10.00, 'متجر واحد'),
(2, 10.00, 'متجران'),
(3, 15.00, 'ثلاثة متاجر'),
(4, 20.00, 'أربعة متاجر فأكثر')
ON CONFLICT (stores_count) DO NOTHING;

-- الإعدادات الافتراضية
INSERT INTO settings (key, value, description) VALUES
('business_name',         'نظام التوصيل الذكي',   'اسم المنصة'),
('whatsapp_number',       '',                       'رقم واتساب المحل'),
('buffer_wait_seconds',   '45',                     'ثواني الانتظار لتجميع رسائل العميل'),
('max_active_orders',     '5',                      'الحد الأقصى للطلبات النشطة لكل مندوب'),
('commission_per_order',  '1.00',                   'ريال عمولة النظام لكل طلب مكتمل'),
('driver_target_daily',   '15',                     'تارجت الطلبات اليومي للمندوب'),
('driver_bonus_rate',     '0.10',                   'نسبة الحافز بعد تحقيق التارجت (10%)'),
('currency',              'ريال سعودي',             'العملة'),
('timezone',              'Asia/Riyadh',             'المنطقة الزمنية')
ON CONFLICT (key) DO NOTHING;

-- مثال مناطق (عدّلها حسب مناطقك الفعلية)
INSERT INTO zones (name, center_lat, center_lng, base_delivery_fee) VALUES
('المنطقة الأولى',  21.4858, 39.1925, 10.00),
('المنطقة الثانية', 21.5000, 39.2100, 10.00),
('المنطقة الثالثة', 21.4700, 39.1800, 15.00),
('المنطقة الرابعة', 21.5200, 39.2300, 15.00),
('المنطقة الخامسة', 21.4500, 39.1600, 20.00)
ON CONFLICT DO NOTHING;

-- مناديب تجريبيون
INSERT INTO drivers (name, whatsapp_number, target_orders, commission_rate) VALUES
('محمد العمري',    '966501234567', 15, 0.10),
('خالد السالم',    '966509876543', 15, 0.10),
('عبدالله الزهراني','966555111222', 15, 0.10),
('سعد القحطاني',   '966544333444', 15, 0.10)
ON CONFLICT (whatsapp_number) DO NOTHING;

-- ============================================================
-- FUNCTIONS — دوال مساعدة
-- ============================================================

-- دالة توليد رقم الطلب التلقائي
CREATE OR REPLACE FUNCTION generate_order_number()
RETURNS TRIGGER AS $$
BEGIN
    NEW.order_number := 'ORD-' ||
        TO_CHAR(NOW(), 'YYYYMMDD') || '-' ||
        LPAD(NEW.id::TEXT, 4, '0');
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- تريغر لتوليد رقم الطلب
CREATE OR REPLACE TRIGGER set_order_number
    BEFORE INSERT ON orders
    FOR EACH ROW
    WHEN (NEW.order_number IS NULL OR NEW.order_number = '')
    EXECUTE FUNCTION generate_order_number();

-- دالة حساب رسوم التوصيل تلقائياً حسب عدد المتاجر
CREATE OR REPLACE FUNCTION calculate_delivery_fee(p_stores_count INTEGER)
RETURNS DECIMAL AS $$
DECLARE
    v_fee DECIMAL(6,2);
BEGIN
    SELECT fee INTO v_fee
    FROM delivery_fee_rules
    WHERE stores_count = LEAST(p_stores_count, 4)  -- 4+ = نفس سعر 4
    ORDER BY stores_count DESC
    LIMIT 1;

    RETURN COALESCE(v_fee, 10.00);
END;
$$ LANGUAGE plpgsql;

-- دالة اختيار أفضل مندوب (أقل طلبات نشطة + متاح)
CREATE OR REPLACE FUNCTION get_best_driver(p_zone_id INTEGER DEFAULT NULL)
RETURNS TABLE(driver_id INTEGER, driver_name VARCHAR, current_orders INTEGER) AS $$
BEGIN
    RETURN QUERY
    SELECT
        d.id,
        d.name,
        d.current_orders
    FROM drivers d
    WHERE d.status = 'online'
      AND d.is_active = TRUE
      AND d.current_orders < (
          SELECT value::INTEGER FROM settings WHERE key = 'max_active_orders'
      )
    ORDER BY
        d.current_orders ASC,    -- الأقل طلبات أولاً
        d.rating DESC,           -- الأعلى تقييماً في حالة التساوي
        RANDOM()                 -- عشوائي إذا تساوى كل شيء
    LIMIT 1;
END;
$$ LANGUAGE plpgsql;

-- دالة إغلاق الطلب وحساب الأوقات والعمولات
CREATE OR REPLACE FUNCTION close_order(p_order_id INTEGER, p_items_cost DECIMAL DEFAULT NULL)
RETURNS JSONB AS $$
DECLARE
    v_order     orders%ROWTYPE;
    v_result    JSONB;
    v_total     DECIMAL(8,2);
    v_duration  INTEGER;
BEGIN
    SELECT * INTO v_order FROM orders WHERE id = p_order_id;

    IF NOT FOUND THEN
        RETURN '{"success": false, "error": "الطلب غير موجود"}'::JSONB;
    END IF;

    -- حساب الأوقات
    v_duration := EXTRACT(EPOCH FROM (NOW() - v_order.assigned_at)) / 60;

    -- تحديث الطلب
    UPDATE orders SET
        status                = 'delivered',
        delivered_at          = NOW(),
        items_cost            = COALESCE(p_items_cost, items_cost),
        total_amount          = delivery_fee + COALESCE(p_items_cost, items_cost, 0),
        total_duration_min    = v_duration,
        delivery_duration_min = EXTRACT(EPOCH FROM (NOW() - picked_up_at)) / 60,
        updated_at            = NOW()
    WHERE id = p_order_id
    RETURNING total_amount INTO v_total;

    -- تحديث إحصائيات المندوب
    UPDATE drivers SET
        current_orders         = GREATEST(current_orders - 1, 0),
        total_orders_completed = total_orders_completed + 1,
        updated_at             = NOW()
    WHERE id = v_order.driver_id;

    -- تحديث إحصائيات العميل
    UPDATE customers SET
        total_orders  = total_orders + 1,
        total_spent   = total_spent + v_total,
        last_order_at = NOW(),
        updated_at    = NOW()
    WHERE id = v_order.customer_id;

    -- تسجيل الحدث
    INSERT INTO order_events (order_id, event_type, description, performed_by)
    VALUES (p_order_id, 'delivered', 'تم التسليم وإغلاق الطلب', 'driver');

    RETURN jsonb_build_object(
        'success', true,
        'order_id', p_order_id,
        'total_amount', v_total,
        'duration_minutes', v_duration
    );
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- VIEWS — مشاهدات جاهزة للـ Dashboard
-- ============================================================

-- مشاهدة: الطلبات النشطة الآن (لوحة التحكم اللحظية)
CREATE OR REPLACE VIEW v_active_orders AS
SELECT
    o.id,
    o.order_number,
    o.status,
    c.name         AS customer_name,
    c.whatsapp_number AS customer_whatsapp,
    o.delivery_location_label,
    o.delivery_lat,
    o.delivery_lng,
    d.name         AS driver_name,
    d.whatsapp_number AS driver_whatsapp,
    o.stores_count,
    o.delivery_fee,
    o.stores_details,
    o.special_notes,
    o.ordered_at,
    o.assigned_at,
    EXTRACT(EPOCH FROM (NOW() - o.ordered_at)) / 60 AS minutes_since_order,
    z.name         AS zone_name
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.id
LEFT JOIN drivers   d ON o.driver_id   = d.id
LEFT JOIN zones     z ON o.zone_id     = z.id
WHERE o.status NOT IN ('delivered', 'cancelled', 'returned')
ORDER BY o.ordered_at;

-- مشاهدة: أداء المناديب اليوم
CREATE OR REPLACE VIEW v_drivers_today AS
SELECT
    d.id,
    d.name,
    d.status,
    d.current_orders,
    d.whatsapp_number,
    -- الوردية الحالية
    ds.check_in_at,
    EXTRACT(EPOCH FROM (COALESCE(ds.check_out_at, NOW()) - ds.check_in_at)) / 60
        AS shift_minutes,
    -- طلبات اليوم
    COUNT(o.id) FILTER (WHERE o.status = 'delivered')   AS completed_today,
    COUNT(o.id) FILTER (WHERE o.status = 'cancelled')   AS cancelled_today,
    -- الأوقات
    AVG(o.total_duration_min) FILTER (WHERE o.status = 'delivered')
        AS avg_delivery_time,
    -- المكاسب
    SUM(o.delivery_fee) FILTER (WHERE o.status = 'delivered')
        AS earned_today,
    -- التارجت
    d.target_orders,
    d.commission_rate,
    -- هل حقق التارجت؟
    COUNT(o.id) FILTER (WHERE o.status = 'delivered') >= d.target_orders
        AS target_achieved
FROM drivers d
LEFT JOIN driver_shifts ds ON ds.driver_id = d.id
    AND ds.shift_date = CURRENT_DATE
LEFT JOIN orders o ON o.driver_id = d.id
    AND DATE(o.ordered_at) = CURRENT_DATE
WHERE d.is_active = TRUE
GROUP BY d.id, d.name, d.status, d.current_orders, d.whatsapp_number,
         ds.check_in_at, ds.check_out_at, d.target_orders, d.commission_rate;

-- مشاهدة: ملخص كل طلب (للتقارير)
CREATE OR REPLACE VIEW v_orders_full AS
SELECT
    o.id,
    o.order_number,
    o.status,
    o.source,
    o.ordered_at,
    o.delivered_at,
    o.total_duration_min,
    -- العميل
    c.name           AS customer_name,
    c.whatsapp_number AS customer_whatsapp,
    -- المنطقة
    z.name           AS zone_name,
    o.delivery_location_label,
    -- المندوب
    d.name           AS driver_name,
    -- التسعير
    o.stores_count,
    o.delivery_fee,
    o.items_cost,
    o.total_amount,
    o.payment_status,
    o.amount_paid,
    (o.total_amount - o.amount_paid) AS balance_due,
    -- التقييم
    o.customer_rating,
    o.special_notes,
    o.ai_summary
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.id
LEFT JOIN drivers   d ON o.driver_id   = d.id
LEFT JOIN zones     z ON o.zone_id     = z.id;

-- مشاهدة: التقرير الشهري للمناديب
CREATE OR REPLACE VIEW v_driver_monthly_report AS
SELECT
    d.id             AS driver_id,
    d.name           AS driver_name,
    DATE_TRUNC('month', o.ordered_at) AS month,
    COUNT(o.id) FILTER (WHERE o.status = 'delivered')   AS total_completed,
    COUNT(o.id) FILTER (WHERE o.status = 'cancelled')   AS total_cancelled,
    SUM(o.delivery_fee) FILTER (WHERE o.status = 'delivered')
        AS total_delivery_fees,
    AVG(o.total_duration_min) FILTER (WHERE o.status = 'delivered')
        AS avg_duration_minutes,
    AVG(o.customer_rating) FILTER (WHERE o.customer_rating IS NOT NULL)
        AS avg_rating,
    -- حساب العمولة: نسبة من الطلبات بعد التارجت
    CASE
        WHEN COUNT(o.id) FILTER (WHERE o.status = 'delivered') > d.target_orders
        THEN (COUNT(o.id) FILTER (WHERE o.status = 'delivered') - d.target_orders)
             * (SUM(o.delivery_fee) FILTER (WHERE o.status = 'delivered') /
                NULLIF(COUNT(o.id) FILTER (WHERE o.status = 'delivered'), 0))
             * d.commission_rate
        ELSE 0
    END              AS bonus_earned,
    -- إجمالي ساعات العمل من الورديات
    SUM(ds_agg.total_shift_minutes) AS total_work_minutes
FROM drivers d
LEFT JOIN orders o ON o.driver_id = d.id
LEFT JOIN (
    SELECT driver_id,
           DATE_TRUNC('month', shift_date) AS month,
           SUM(total_minutes)              AS total_shift_minutes
    FROM driver_shifts
    GROUP BY driver_id, DATE_TRUNC('month', shift_date)
) ds_agg ON ds_agg.driver_id = d.id
    AND ds_agg.month = DATE_TRUNC('month', o.ordered_at)
WHERE d.is_active = TRUE
GROUP BY d.id, d.name, DATE_TRUNC('month', o.ordered_at),
         d.target_orders, d.commission_rate;

-- بيانات تجريبية للعملاء
INSERT INTO customers (whatsapp_number, name, default_location_label, zone_id) VALUES
('966500111222', 'أحمد المزارع', 'حي النزهة', 1),
('966500333444', 'خالد الشمري', 'حي الريان', 2),
('966500555666', 'فيصل العبدالله', 'السيل الكبير', 3),
('966500777888', 'أسامة محمد', 'الحوية', 4)
ON CONFLICT DO NOTHING;

-- بيانات تجريبية للطلبات النشطة (مباشرة)
INSERT INTO orders (customer_id, driver_id, delivery_location_label, stores_count, delivery_fee, status) VALUES
(1, 1, 'حي النزهة', 3, 15.00, 'on_the_way'),
(2, 2, 'حي الريان', 2, 10.00, 'picking_up'),
(3, 3, 'السيل الكبير', 4, 20.00, 'on_the_way'),
(4, NULL, 'الحوية', 1, 10.00, 'new')
ON CONFLICT DO NOTHING;

-- تحديث حالة المناديب بناءً على الطلبات 
UPDATE drivers SET status = 'busy', current_orders = 1 WHERE id IN (1, 2, 3);
UPDATE drivers SET status = 'offline' WHERE id = 4;

-- ============================================================
-- END OF SCHEMA
-- ============================================================
