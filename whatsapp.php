<?php
$page_title = 'ربط الواتساب — Evolution API';
require 'layout.php';
?>

<div class="page-header">
    <div>
        <h1>💬 ربط الواتساب — Evolution API</h1>
        <p>إدارة اتصال الواتساب عبر Evolution API — اتصال مستقر وتلقائي</p>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem">
        <span class="badge" id="global_badge" style="font-size:.8rem;padding:.4rem 1rem">
            <span class="status-dot" style="width:8px;height:8px;display:inline-block;border-radius:50%;background:var(--warning);margin-left:.4rem"></span>
            جاري الفحص...
        </span>
        <button class="btn btn-ghost btn-sm" onclick="checkStatus()">🔄 تحديث</button>
    </div>
</div>

<!-- ─── بطاقات الحالة ─────────────────────────────────────── -->
<div class="kpi-grid" style="--cols:3;margin-bottom:1.5rem">
    <div class="kpi" id="kpi_evo">
        <div class="kpi-icon">⚡</div>
        <div class="kpi-label">Evolution API</div>
        <div class="kpi-val" id="evo_state">فحص...</div>
        <div class="kpi-sub" id="evo_sub" style="color:var(--muted)">الخدمة الأساسية</div>
    </div>
    <div class="kpi" id="kpi_wa">
        <div class="kpi-icon">📱</div>
        <div class="kpi-label">حالة واتساب</div>
        <div class="kpi-val" id="wa_state">فحص...</div>
        <div class="kpi-sub" id="wa_phone" style="color:var(--muted)">رقم الاتصال</div>
    </div>
    <div class="kpi" id="kpi_db">
        <div class="kpi-icon">🗄️</div>
        <div class="kpi-label">قاعدة البيانات</div>
        <div class="kpi-val" style="color:var(--success)">✅ متصلة</div>
        <div class="kpi-sub" style="color:var(--muted)">PostgreSQL</div>
    </div>
</div>

<!-- ─── المحتوى الرئيسي ───────────────────────────────────── -->
<div class="wa-grid">

    <!-- === 1. توليد QR Code === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(0,242,254,.1);color:var(--primary)">📷</div>
                <div>
                    <div>ربط بـ QR Code</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">امسح الرمز من تطبيق واتساب → الأجهزة المرتبطة</div>
                </div>
            </div>
            <span class="badge badge-muted" id="qr_badge">غير مُهيّأ</span>
        </div>

        <div class="qr-box" id="qr_box">
            <div>
                <div style="font-size:3rem;margin-bottom:1rem">📷</div>
                <div style="color:var(--muted);margin-bottom:1.5rem;font-size:.9rem">اضغط الزر أدناه لتوليد رمز الربط</div>
                <button class="btn btn-primary" onclick="generateQR()">🔄 توليد QR Code</button>
            </div>
        </div>

        <div id="qr_steps" style="display:none;margin-top:1.25rem">
            <div style="display:flex;flex-direction:column;gap:.6rem">
                <?php foreach([
                    ['1','افتح واتساب على هاتف المحل'],
                    ['2','اضغط على النقاط الثلاث ← الأجهزة المرتبطة'],
                    ['3','اضغط ربط جهاز ← امسح رمز QR أعلاه'],
                ] as [$n,$t]): ?>
                <div style="display:flex;align-items:center;gap:.75rem">
                    <div class="step-badge"><?= $n ?></div>
                    <div style="font-size:.88rem;color:var(--muted)"><?= $t ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- === 2. Pairing Code === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(168,85,247,.1);color:var(--accent)">🔢</div>
                <div>
                    <div>ربط برمز هاتفي</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">بديل QR — أدخل رقمك واستقبل كود الربط</div>
                </div>
            </div>
            <span class="badge badge-muted" id="pair_badge">جاهز</span>
        </div>

        <div style="text-align:center;padding:1.5rem 0">
            <div style="color:var(--muted);font-size:.88rem;margin-bottom:1rem">أدخل رقم واتساب المحل (مع كود الدولة)</div>
            <div style="display:flex;gap:.75rem;justify-content:center;flex-wrap:wrap">
                <input type="text" id="pair_phone" class="form-control"
                    style="max-width:220px;text-align:center;font-size:1rem;letter-spacing:.05rem"
                    placeholder="966500000000">
                <button class="btn btn-primary" onclick="requestPairCode()">📨 إرسال الكود</button>
            </div>
            <div id="pair_code_display" style="display:none;margin-top:1.5rem">
                <div style="color:var(--muted);font-size:.85rem;margin-bottom:.5rem">كود الربط (صالح 60 ثانية):</div>
                <div class="pairing-code" id="pair_code_val">----</div>
                <div id="pair_timer" style="color:var(--warning);font-size:.85rem;margin-top:.5rem">⏱ 60 ثانية متبقية</div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:.6rem;margin-top:1rem">
            <?php foreach([
                ['1','افتح واتساب → الأجهزة المرتبطة'],
                ['2','اختر "ربط برمز هاتفي"'],
                ['3','أدخل الكود المكوّن من 8 أرقام'],
            ] as [$n,$t]): ?>
            <div style="display:flex;align-items:center;gap:.75rem">
                <div class="step-badge"><?= $n ?></div>
                <div style="font-size:.88rem;color:var(--muted)"><?= $t ?></div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top:1.25rem;text-align:center">
            <button class="btn btn-danger btn-sm" onclick="disconnect()" id="btn_disconnect" style="display:none">
                🔌 قطع الاتصال
            </button>
        </div>
    </div>

    <!-- === 3. اختبار الإرسال === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(16,185,129,.1);color:var(--success)">📤</div>
                <div>
                    <div>اختبار إرسال رسالة</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">تحقق من عمل الإرسال بعد ربط الواتساب</div>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label>رقم الهاتف (مع كود الدولة)</label>
            <input type="text" id="test_phone" class="form-control" placeholder="966500000000">
        </div>
        <div class="form-group">
            <label>نص الرسالة</label>
            <textarea id="test_msg" class="form-control" rows="3" style="resize:vertical">✅ اختبار نظام ديلفرو برو — الرسالة وصلت بنجاح 🚀</textarea>
        </div>
        <button class="btn btn-primary" onclick="testSend()" style="width:100%">📨 إرسال اختباري</button>
        <div id="test_result" style="margin-top:.75rem;font-size:.85rem"></div>
    </div>

    <!-- === 4. سجل الأحداث === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(59,130,246,.1);color:var(--info)">📋</div>
                <div>
                    <div>سجل الأحداث</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">آخر العمليات المنفّذة</div>
                </div>
            </div>
            <button class="btn btn-ghost btn-sm" onclick="clearLog()">🗑 مسح</button>
        </div>
        <div id="activity_log" style="max-height:220px;overflow-y:auto">
            <div class="log-entry">
                <div class="log-dot" style="background:var(--info)"></div>
                <span style="color:var(--muted);min-width:75px"><?= date('H:i:s') ?></span>
                <span>تم تحميل صفحة إدارة الواتساب</span>
            </div>
        </div>
    </div>

</div>

<!-- ─── معلومات الإعداد ─────────────────────────────────────── -->
<div class="card" style="margin-top:1.5rem">
    <div class="section-title">
        <span>⚙️ إعدادات Evolution API</span>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:1.25rem;margin-top:1rem">
        <div>
            <div style="font-size:.75rem;color:var(--muted);margin-bottom:.35rem">رابط API الداخلي</div>
            <div class="terminal-code-block" style="font-size:.85rem">http://evolution:8080</div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--muted);margin-bottom:.35rem">Instance Name</div>
            <div class="terminal-code-block" style="font-size:.85rem">delivery</div>
        </div>
        <div>
            <div style="font-size:.75rem;color:var(--muted);margin-bottom:.35rem">Webhook URL (للضبط على السيرفر)</div>
            <div class="terminal-code-block" style="font-size:.85rem">http://delivery_php/webhook_handler.php</div>
        </div>
    </div>

    <div style="margin-top:1.5rem">
        <div style="font-size:.85rem;font-weight:600;margin-bottom:.75rem;color:var(--text)">🚀 أوامر الإعداد الأولي (على السيرفر)</div>
        <div class="terminal-code-block">
            <span style="color:var(--muted)"># 1. إنشاء Instance</span><br>
            curl -X POST http://localhost:8080/instance/create \<br>
            &nbsp;&nbsp;-H "apikey: dlv-evo-K9x2mP8nQ4rT7wJ3vL5" \<br>
            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
            &nbsp;&nbsp;-d '{"instanceName":"delivery","integration":"WHATSAPP-BAILEYS"}'<br><br>
            <span style="color:var(--muted)"># 2. إعداد Webhook</span><br>
            curl -X POST http://localhost:8080/webhook/set/delivery \<br>
            &nbsp;&nbsp;-H "apikey: dlv-evo-K9x2mP8nQ4rT7wJ3vL5" \<br>
            &nbsp;&nbsp;-H "Content-Type: application/json" \<br>
            &nbsp;&nbsp;-d '{"url":"http://delivery_php/webhook_handler.php","events":["messages.upsert"]}'
        </div>
    </div>
</div>

<script>
// ─── Logging ─────────────────────────────────────────────────
function addLog(msg, type='info') {
    const colors = {info:'var(--info)',ok:'var(--success)',err:'var(--danger)',warn:'var(--warning)'};
    const log = document.getElementById('activity_log');
    const now = new Date().toLocaleTimeString('ar-SA',{hour12:false});
    log.insertAdjacentHTML('afterbegin', `
        <div class="log-entry">
            <div class="log-dot" style="background:${colors[type]||colors.info}"></div>
            <span style="color:var(--muted);min-width:75px">${now}</span>
            <span>${msg}</span>
        </div>`);
}
function clearLog() { document.getElementById('activity_log').innerHTML = ''; }

// ─── فحص الحالة ──────────────────────────────────────────────
async function checkStatus() {
    addLog('جاري فحص الحالة...');
    try {
        const r = await fetch('api_wa.php?action=status');
        const d = await r.json();

        const badge   = document.getElementById('global_badge');
        const waState = document.getElementById('wa_state');
        const waPhone = document.getElementById('wa_phone');
        const evoState= document.getElementById('evo_state');
        const evoSub  = document.getElementById('evo_sub');
        const btnDisc = document.getElementById('btn_disconnect');

        if (d.error && d.error.includes('Evolution')) {
            // Evolution نفسه لا يعمل
            evoState.textContent = '❌ متوقف';
            evoState.style.color = 'var(--danger)';
            evoSub.textContent   = 'تحقق من Docker';
            waState.textContent  = '—';
            badge.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--danger);margin-left:.4rem"></span> Evolution غير متاح';
            addLog('Evolution API غير متاح — تحقق من Docker', 'err');
        } else if (d.connected) {
            evoState.textContent = '✅ يعمل';
            evoState.style.color = 'var(--success)';
            evoSub.textContent   = 'الخدمة نشطة';
            waState.textContent  = '🟢 متصل';
            waState.style.color  = 'var(--success)';
            waPhone.textContent  = d.phone || 'مُتّصل';
            badge.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--success);animation:pulse 2s infinite;margin-left:.4rem"></span> واتساب متصل ✅';
            badge.style.cssText  = 'font-size:.8rem;padding:.4rem 1rem;background:rgba(16,185,129,.15);color:var(--success);border:1px solid rgba(16,185,129,.3);border-radius:50px';
            btnDisc.style.display = 'inline-flex';
            addLog('واتساب متصل — ' + (d.phone || ''), 'ok');

            // تحديث بطاقة QR
            document.getElementById('qr_box').innerHTML = `
                <div>
                    <div style="font-size:4rem;margin-bottom:.75rem">✅</div>
                    <div style="color:var(--success);font-size:1.1rem;font-weight:700">واتساب متصل بنجاح!</div>
                    <div style="color:var(--muted);font-size:.85rem;margin-top:.5rem">الجلسة نشطة — البوت يستقبل الطلبات</div>
                </div>`;
            document.getElementById('qr_badge').textContent = '🟢 متصل';
            document.getElementById('qr_badge').className = 'badge badge-success';
        } else {
            evoState.textContent = '✅ يعمل';
            evoState.style.color = 'var(--success)';
            evoSub.textContent   = 'الخدمة نشطة';
            waState.textContent  = '🔴 غير متصل';
            waState.style.color  = 'var(--danger)';
            waPhone.textContent  = 'بانتظار ربط الواتساب';
            badge.innerHTML = '<span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--warning);margin-left:.4rem"></span> بانتظار ربط الواتساب';
            badge.style.cssText  = 'font-size:.8rem;padding:.4rem 1rem;';
            addLog('Evolution يعمل لكن الواتساب غير مرتبط', 'warn');
        }
    } catch(e) {
        addLog('خطأ في الفحص: ' + e.message, 'err');
    }
}

// ─── توليد QR ────────────────────────────────────────────────
async function generateQR() {
    const box   = document.getElementById('qr_box');
    const badge = document.getElementById('qr_badge');
    const steps = document.getElementById('qr_steps');

    badge.textContent = '⏳ جاري التوليد...';
    badge.className   = 'badge badge-warning';
    box.innerHTML     = `<div>
        <div style="font-size:3rem;margin-bottom:1rem">⏳</div>
        <div style="color:var(--muted)">جاري طلب QR من Evolution API...</div>
    </div>`;
    addLog('طلب QR Code...');

    try {
        const r = await fetch('api_wa.php?action=qr');
        const d = await r.json();

        if (d.qr) {
            box.innerHTML = `<img src="${d.qr}" class="qr-img" alt="QR Code">`;
            badge.textContent = '📷 انتظار المسح';
            badge.className   = 'badge badge-warning';
            steps.style.display = 'block';
            addLog('QR Code جاهز — امسحه الآن', 'ok');
            startQRTimer();
        } else if (d.connected) {
            checkStatus();
        } else {
            box.innerHTML = `<div>
                <div style="font-size:2.5rem;margin-bottom:.75rem">⚠️</div>
                <div style="color:var(--warning);font-weight:600;margin-bottom:.5rem">${d.error || 'تعذّر التوليد'}</div>
                <button class="btn btn-ghost btn-sm" onclick="generateQR()">↺ إعادة المحاولة</button>
            </div>`;
            badge.textContent = '❌ فشل';
            badge.className   = 'badge badge-danger';
            addLog(d.error || 'فشل توليد QR', 'err');
        }
    } catch(e) {
        box.innerHTML = `<div><div style="color:var(--danger)">❌ خطأ في الاتصال بـ Evolution</div></div>`;
        addLog('خطأ: ' + e.message, 'err');
    }
}

let qrInterval;
function startQRTimer() {
    let sec = 60;
    clearInterval(qrInterval);
    qrInterval = setInterval(() => {
        sec--;
        if (sec <= 0) {
            clearInterval(qrInterval);
            generateQR(); // تحديث تلقائي
        }
    }, 1000);
}

// ─── Pairing Code ─────────────────────────────────────────────
let pairTimer;
async function requestPairCode() {
    const phone   = document.getElementById('pair_phone').value.trim();
    const display = document.getElementById('pair_code_display');
    const codeEl  = document.getElementById('pair_code_val');
    const timerEl = document.getElementById('pair_timer');
    const badge   = document.getElementById('pair_badge');

    if (!phone) { alert('أدخل رقم الهاتف'); return; }

    badge.textContent = '⏳ جاري...';
    badge.className   = 'badge badge-warning';
    addLog(`طلب Pairing Code للرقم ${phone}`);

    try {
        const r = await fetch(`api_wa.php?action=pair&phone=${encodeURIComponent(phone)}`);
        const d = await r.json();

        if (d.code) {
            display.style.display = 'block';
            codeEl.textContent    = d.code;
            badge.textContent     = '✅ الكود جاهز';
            badge.className       = 'badge badge-success';
            addLog('تم استلام كود الربط: ' + d.code, 'ok');

            let sec = 60;
            clearInterval(pairTimer);
            pairTimer = setInterval(() => {
                sec--;
                timerEl.textContent = `⏱ ${sec} ثانية متبقية`;
                if (sec <= 0) {
                    clearInterval(pairTimer);
                    timerEl.textContent = '⛔ انتهت صلاحية الكود';
                    timerEl.style.color = 'var(--danger)';
                    codeEl.style.opacity = '.3';
                }
            }, 1000);
        } else {
            alert(d.error || 'تعذّر جلب الكود. جرّب طريقة QR.');
            badge.textContent = '❌ فشل';
            badge.className   = 'badge badge-danger';
        }
    } catch(e) {
        addLog('خطأ: ' + e.message, 'err');
    }
}

// ─── قطع الاتصال ─────────────────────────────────────────────
async function disconnect() {
    if (!confirm('هل أنت متأكد من قطع الاتصال بالواتساب؟')) return;
    addLog('جاري قطع الاتصال...');
    const r = await fetch('api_wa.php?action=disconnect');
    const d = await r.json();
    if (d.ok) {
        addLog('تم قطع الاتصال بنجاح', 'warn');
        document.getElementById('btn_disconnect').style.display = 'none';
        checkStatus();
    } else {
        addLog('فشل قطع الاتصال', 'err');
    }
}

// ─── اختبار الإرسال ──────────────────────────────────────────
async function testSend() {
    const phone = document.getElementById('test_phone').value.trim();
    const msg   = document.getElementById('test_msg').value.trim();
    const res   = document.getElementById('test_result');

    if (!phone || !msg) { res.innerHTML = '<span style="color:var(--danger)">أدخل الرقم والرسالة</span>'; return; }

    res.innerHTML = '<span style="color:var(--muted)">⏳ جاري الإرسال...</span>';
    addLog(`إرسال اختباري إلى ${phone}`);

    try {
        const r = await fetch('api_wa.php?action=send', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({phone, message: msg})
        });
        const d = await r.json();
        if (d.ok) {
            res.innerHTML = '<span style="color:var(--success)">✅ تم الإرسال بنجاح!</span>';
            addLog('تم إرسال الرسالة الاختبارية بنجاح', 'ok');
        } else {
            res.innerHTML = `<span style="color:var(--danger)">❌ ${d.error || 'فشل الإرسال'}</span>`;
            addLog('فشل الإرسال: ' + (d.error || ''), 'err');
        }
    } catch(e) {
        res.innerHTML = `<span style="color:var(--danger)">❌ ${e.message}</span>`;
    }
}

// ─── تشغيل عند التحميل ───────────────────────────────────────
window.addEventListener('load', () => setTimeout(checkStatus, 500));
</script>

<?php require 'layout_end.php'; ?>
