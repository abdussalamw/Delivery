<?php
$page_title = 'ربط الواتساب — Baileys';
require 'layout.php';
?>

<!-- WhatsApp specific styles are now in style.css -->

<div class="page-header">
    <div>
        <h1>💬 ربط الواتساب — Baileys</h1>
        <p>إدارة بوت الواتساب باستخدام Node.js + Baileys لاستقبال الطلبات آلياً</p>
    </div>
    <div style="display:flex;align-items:center;gap:.75rem">
        <span class="indicator" id="global_status_badge" style="background:rgba(100,116,139,.15);color:var(--muted)">
            <span class="pulse-dot pulse-yellow"></span> جاري الفحص...
        </span>
        <button class="btn btn-ghost" onclick="checkAll()">🔄 تحديث الكل</button>
    </div>
</div>

<!-- Top Cards -->
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:1.25rem;margin-bottom:2rem" id="status_cards">
    <div class="card" style="padding:1.25rem;display:flex;align-items:center;gap:1rem">
        <div class="service-icon" style="background:rgba(59,130,246,.1);color:var(--info)">⚙️</div>
        <div>
            <div style="font-size:.8rem;color:var(--muted)">PM2 Process Manager</div>
            <div id="pm2_card_status" style="font-weight:700">جاري الفحص...</div>
        </div>
        <div style="margin-right:auto" id="pm2_card_dot"><div class="pulse-dot pulse-yellow"></div></div>
    </div>
    <div class="card" style="padding:1.25rem;display:flex;align-items:center;gap:1rem">
        <div class="service-icon" style="background:rgba(16,185,129,.1);color:var(--success)">🟢</div>
        <div>
            <div style="font-size:.8rem;color:var(--muted)">Node.js منفذ 3000</div>
            <div id="node_card_status" style="font-weight:700">جاري الفحص...</div>
        </div>
        <div style="margin-right:auto" id="node_card_dot"><div class="pulse-dot pulse-yellow"></div></div>
    </div>
    <div class="card" style="padding:1.25rem;display:flex;align-items:center;gap:1rem">
        <div class="service-icon" style="background:rgba(37,211,102,.15);color:#25d366)">📱</div>
        <div>
            <div style="font-size:.8rem;color:var(--muted)">حالة الواتساب</div>
            <div id="wa_card_status" style="font-weight:700">جاري الفحص...</div>
        </div>
        <div style="margin-right:auto" id="wa_card_dot"><div class="pulse-dot pulse-yellow"></div></div>
    </div>
</div>

<div class="wa-grid">

    <!-- === 1. PM2 === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(59,130,246,.12);color:var(--info)">⚙️</div>
                <div>
                    <div>PM2 — مدير العمليات</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">يُبقي البوت يعمل تلقائياً ويُعيد تشغيله عند الانهيار</div>
                </div>
            </div>
            <span class="indicator ind-check" id="pm2_badge">
                <span class="pulse-dot pulse-yellow"></span> فحص...
            </span>
        </div>

        <div class="terminal" id="pm2_log">
            <span class="line-info">$ جاري فحص PM2...</span>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap">
            <button class="btn btn-run btn-sm" onclick="pm2Action('start')">▶ تشغيل (pm2 start)</button>
            <button class="btn btn-stop btn-sm" onclick="pm2Action('stop')">■ إيقاف</button>
            <button class="btn btn-ghost btn-sm" onclick="pm2Action('restart')">↺ إعادة تشغيل</button>
            <button class="btn btn-ghost btn-sm" onclick="pm2Action('status')">📋 الحالة</button>
        </div>

        <div class="terminal-code-block" style="margin-top:1.25rem">
            <div style="color:var(--muted);margin-bottom:.5rem">أوامر PM2 المرجعية:</div>
            pm2 start bot.js --name "delivery-bot"<br>
            pm2 status<br>
            pm2 logs delivery-bot<br>
            pm2 save && pm2 startup
        </div>
    </div>

    <!-- === 2. Node.js === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(16,185,129,.12);color:var(--success)">🟢</div>
                <div>
                    <div>Node.js — الخادم</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">التحقق من استجابة الخدمة على المنفذ 3000</div>
                </div>
            </div>
            <span class="indicator ind-check" id="node_badge">
                <span class="pulse-dot pulse-yellow"></span> فحص...
            </span>
        </div>

        <div class="terminal" id="node_log">
            <span class="line-info">$ جاري فحص Node.js على localhost:3000...</span>
        </div>

        <div style="display:flex;gap:.75rem;margin-top:1.25rem;flex-wrap:wrap">
            <button class="btn btn-run btn-sm" onclick="nodeAction('check')">🔍 فحص الاتصال</button>
            <button class="btn btn-ghost btn-sm" onclick="nodeAction('version')">📌 إصدار Node</button>
            <button class="btn btn-ghost btn-sm" onclick="nodeAction('ping')">📡 Ping الخادم</button>
        </div>

        <div class="terminal-code-block" style="margin-top:1.25rem">
            <div style="font-size:.82rem;color:var(--muted);margin-bottom:.5rem">الرابط الداخلي للاتصال:</div>
            <code style="color:var(--primary);font-size:.85rem;">http://localhost:3000/status</code>
        </div>
    </div>

    <!-- === 3. QR Code === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(0,242,254,.12);color:var(--primary)">📷</div>
                <div>
                    <div>ربط بـ QR Code</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">امسح الرمز من تطبيق واتساب المرتبط بالمحل</div>
                </div>
            </div>
            <span class="indicator ind-check" id="qr_badge">
                <span class="pulse-dot pulse-yellow"></span> غير مُهيّأ
            </span>
        </div>

        <div class="qr-box" id="qr_box">
            <div>
                <div style="font-size:3rem;margin-bottom:1rem">📷</div>
                <div style="color:var(--muted);margin-bottom:1.5rem;font-size:.9rem">اضغط "توليد QR" لبدء عملية الربط<br>ثم افتح واتساب وأمسح الرمز</div>
                <button class="btn btn-primary" onclick="generateQR()">🔄 توليد QR Code</button>
            </div>
        </div>

        <div id="qr_steps" style="display:none;margin-top:1.25rem">
            <div style="display:flex;flex-direction:column;gap:.75rem">
                <div style="display:flex;align-items:flex-start;gap:.75rem">
                    <div class="step-badge">1</div>
                    <div style="font-size:.88rem;color:var(--muted)">افتح <strong style="color:var(--text)">واتساب</strong> على هاتف المحل</div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.75rem">
                    <div class="step-badge">2</div>
                    <div style="font-size:.88rem;color:var(--muted)">اضغط على <strong style="color:var(--text)">النقاط الثلاث</strong> ثم الأجهزة المرتبطة</div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.75rem">
                    <div class="step-badge">3</div>
                    <div style="font-size:.88rem;color:var(--muted)">اختر <strong style="color:var(--text)">"ربط جهاز"</strong> ثم اقرأ رمز QR أعلاه</div>
                </div>
            </div>
        </div>
    </div>

    <!-- === 4. Pairing Code === -->
    <div class="service-card">
        <div class="service-header">
            <div class="service-title">
                <div class="service-icon" style="background:rgba(168,85,247,.12);color:var(--accent)">🔢</div>
                <div>
                    <div>ربط بكود الرسالة</div>
                    <div style="font-size:.75rem;color:var(--muted);font-weight:400">بديل QR — ادخل رقمك واستقبل كود الربط</div>
                </div>
            </div>
            <span class="indicator ind-check" id="pair_badge">
                <span class="pulse-dot" style="background:var(--muted)"></span> جاهز
            </span>
        </div>

        <div class="pairing-box" id="pair_box">
            <div style="font-size:.9rem;color:var(--muted);margin-bottom:1rem">أدخل رقم واتساب المحل لإرسال كود الربط</div>
            <div style="display:flex;gap:.75rem;margin-bottom:1rem;justify-content:center">
                <input type="text" id="pair_phone" class="form-control" style="max-width:220px;text-align:center;font-size:1rem;letter-spacing:.05rem" placeholder="966500000000">
                <button class="btn btn-primary" onclick="requestPairCode()">📨 إرسال الكود</button>
            </div>
            <div id="pair_code_display" style="display:none">
                <div style="color:var(--muted);font-size:.85rem;margin-bottom:.5rem">كود الربط (صالح لمدة 60 ثانية):</div>
                <div class="pairing-code" id="pair_code_val">----</div>
                <div id="pair_timer" style="color:var(--warning);font-size:.85rem">⏱ 60 ثانية متبقية</div>
            </div>
        </div>

        <div style="margin-top:1.25rem">
            <div style="display:flex;flex-direction:column;gap:.6rem">
                <div style="display:flex;align-items:flex-start;gap:.75rem">
                    <div class="step-badge">1</div>
                    <div style="font-size:.88rem;color:var(--muted)">أدخل رقم واتساب المحل أعلاه</div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.75rem">
                    <div class="step-badge">2</div>
                    <div style="font-size:.88rem;color:var(--muted)">اضغط "إرسال الكود" وانتظر ظهور الرقم</div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.75rem">
                    <div class="step-badge">3</div>
                    <div style="font-size:.88rem;color:var(--muted)">في واتساب: الأجهزة المرتبطة ← ربط جهاز ← ربط برمز هاتفي</div>
                </div>
                <div style="display:flex;align-items:flex-start;gap:.75rem">
                    <div class="step-badge">4</div>
                    <div style="font-size:.88rem;color:var(--muted)">أدخل الكود المكوّن من 8 أرقام في واتساب</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Activity Log -->
<div class="service-card" style="margin-top:1.5rem">
    <div class="section-title" style="margin-bottom:1rem">
        <span>📋 سجل الأحداث</span>
        <button class="btn btn-ghost btn-sm" onclick="clearLog()">🗑 مسح</button>
    </div>
    <div id="activity_log" style="max-height:200px;overflow-y:auto">
        <div class="log-entry">
            <div class="log-dot" style="background:var(--info)"></div>
            <span style="color:var(--muted);min-width:80px"><?=date('H:i:s')?></span>
            <span>تم تحميل صفحة إدارة الواتساب</span>
        </div>
    </div>
</div>

<!-- Install Guide -->
<div style="margin-top:1.5rem;background:var(--glass);border:1px solid var(--border);border-radius:20px;padding:2rem">
    <h2 style="font-size:1.1rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:.75rem">
        <span>📖</span> دليل التثبيت السريع
    </h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:1.5rem">
        <div>
            <div style="color:var(--primary);font-weight:700;margin-bottom:.75rem">① تثبيت Node.js و PM2</div>
            <div class="terminal-code-block">
                # تحميل Node.js من nodejs.org<br>
                npm install -g pm2<br>
                pm2 --version
            </div>
        </div>
        <div>
            <div style="color:var(--success);font-weight:700;margin-bottom:.75rem">② تثبيت بوت Baileys</div>
            <div class="terminal-code-block">
                cd C:\xampp\htdocs\Delivery\bot<br>
                npm install<br>
                # تأكد من إعداد ملف .env أولاً
            </div>
        </div>
        <div>
            <div style="color:var(--accent);font-weight:700;margin-bottom:.75rem">③ تشغيل مع PM2</div>
            <div class="terminal-code-block">
                pm2 start bot.js --name delivery-bot<br>
                pm2 save<br>
                pm2 startup
            </div>
        </div>
    </div>
</div>

<script>
// ==== Logging ====
function addLog(msg, type='info') {
    const colors = {info:'var(--info)',ok:'var(--success)',err:'var(--danger)',warn:'var(--warning)'};
    const log = document.getElementById('activity_log');
    const now = new Date().toLocaleTimeString('ar-SA',{hour12:false});
    log.insertAdjacentHTML('afterbegin', `
        <div class="log-entry">
            <div class="log-dot" style="background:${colors[type]||colors.info}"></div>
            <span style="color:var(--muted);min-width:80px">${now}</span>
            <span>${msg}</span>
        </div>
    `);
}

function clearLog(){document.getElementById('activity_log').innerHTML='';}

// ==== PM2 Actions ====
async function pm2Action(action) {
    const log = document.getElementById('pm2_log');
    const badge = document.getElementById('pm2_badge');
    const cardSt = document.getElementById('pm2_card_status');
    
    log.innerHTML = `<span class="line-info">$ pm2 ${action}... جاري التنفيذ</span>`;
    addLog(`تم إرسال أمر PM2: ${action}`);
    
    try {
        const r = await fetch('api_pm2.php?action='+action);
        const d = await r.json();
        
        log.innerHTML = d.output.split('\n').map(l =>
            l.includes('online') ? `<span class="line-ok">${l}</span>` :
            l.includes('error')||l.includes('ERROR') ? `<span class="line-err">${l}</span>` :
            l ? `<span>${l}</span>` : ''
        ).join('<br>');
        
        if(d.status === 'online') {
            badge.innerHTML = '<span class="pulse-dot pulse-green"></span> online';
            badge.className = 'indicator ind-online';
            cardSt.textContent = '✅ يعمل';
            document.getElementById('pm2_card_dot').innerHTML = '<div class="pulse-dot pulse-green" style="background:var(--success);animation:pulseGreen 1.5s infinite"></div>';
            addLog('PM2 يعمل بنجاح','ok');
        } else {
            badge.innerHTML = '<span class="pulse-dot pulse-red"></span> متوقف';
            badge.className = 'indicator ind-offline';
            cardSt.textContent = '❌ متوقف';
            document.getElementById('pm2_card_dot').innerHTML = '<div class="pulse-dot" style="background:var(--danger)"></div>';
            addLog('PM2 متوقف أو غير مُثبَّت','warn');
        }
    } catch(e) {
        log.innerHTML = '<span class="line-err">❌ تعذر التواصل مع خادم PM2. تأكد من وجود ملف api_pm2.php</span>';
        badge.innerHTML = '<span class="pulse-dot pulse-red"></span> خطأ';
        badge.className = 'indicator ind-offline';
        cardSt.textContent = '❓ تعذر الفحص';
        addLog('فشل التواصل مع PM2 API','err');
    }
}

// ==== Node.js Actions ====
async function nodeAction(action) {
    const log = document.getElementById('node_log');
    const badge = document.getElementById('node_badge');
    const cardSt = document.getElementById('node_card_status');
    
    log.innerHTML = '<span class="line-info">$ جاري الفحص على localhost:3000...</span>';
    
    try {
        const r = await fetch('api_node.php?action='+action);
        const d = await r.json();
        
        log.innerHTML = `<span class="${d.ok?'line-ok':'line-err'}">${d.message||'—'}</span>`;
        
        if(d.ok) {
            badge.innerHTML = '<span class="pulse-dot pulse-green"></span> متصل';
            badge.className = 'indicator ind-online';
            cardSt.textContent = '✅ يستجيب';
            document.getElementById('node_card_dot').innerHTML = '<div class="pulse-dot" style="background:var(--success)"></div>';
            addLog('Node.js يستجيب بنجاح على منفذ 3000','ok');
        } else {
            badge.innerHTML = '<span class="pulse-dot pulse-red"></span> فشل الاتصال';
            badge.className = 'indicator ind-offline';
            cardSt.textContent = '❌ لا يستجيب';
            document.getElementById('node_card_dot').innerHTML = '<div class="pulse-dot" style="background:var(--danger)"></div>';
            addLog('Node.js لا يستجيب على منفذ 3000','err');
        }
    } catch(e) {
        log.innerHTML = '<span class="line-err">❌ فشل الاتصال بالخادم</span>';
        badge.innerHTML = '<span class="pulse-dot pulse-red"></span> فشل';
        badge.className = 'indicator ind-offline';
        addLog('خطأ في فحص Node.js','err');
    }
}

// ==== QR Code ====
function generateQR() {
    const box = document.getElementById('qr_box');
    const badge = document.getElementById('qr_badge');
    const steps = document.getElementById('qr_steps');
    
    badge.innerHTML = '<span class="pulse-dot pulse-yellow"></span> جاري التوليد...';
    badge.className = 'indicator ind-warn';
    box.innerHTML = `
        <div>
            <div style="font-size:3rem;animation:spin 1s linear infinite;display:inline-block">⏳</div>
            <div style="color:var(--muted);margin-top:1rem">جاري الاتصال بالخادم وتوليد QR Code...</div>
        </div>`;
    box.classList.add('active');
    addLog('طلب توليد QR Code بدء');
    
    // محاولة جلب QR من الـ API
    fetch('api_wa.php?action=qr')
        .then(r => r.json())
        .then(d => {
            if(d.qr) {
                box.innerHTML = `<img src="${d.qr}" class="qr-img" alt="QR Code" onerror="qrFallback()">`;
                badge.innerHTML = '<span class="pulse-dot pulse-green"></span> انتظار المسح';
                badge.className = 'indicator ind-warn';
                steps.style.display = 'block';
                addLog('تم توليد QR Code — انتظار المسح','ok');
                startQRTimer();
            } else if(d.connected) {
                box.innerHTML = showConnectedState();
                badge.innerHTML = '<span class="pulse-dot pulse-green"></span> متصل ✅';
                badge.className = 'indicator ind-online';
                document.getElementById('wa_card_status').textContent = '✅ متصل';
                addLog('واتساب متصل بالفعل','ok');
            } else {
                qrFallback();
            }
        })
        .catch(() => qrFallback());
}

function qrFallback() {
    const box = document.getElementById('qr_box');
    box.innerHTML = `
        <div>
            <div style="font-size:2.5rem;margin-bottom:.75rem">⚠️</div>
            <div style="color:var(--warning);font-weight:600;margin-bottom:.5rem">تعذر توليد QR Code</div>
            <div style="color:var(--muted);font-size:.85rem;margin-bottom:1rem">تأكد من تشغيل بوت الواتساب (PM2) أولاً</div>
            <button class="btn btn-ghost btn-sm" onclick="generateQR()">↺ إعادة المحاولة</button>
        </div>`;
    const badge = document.getElementById('qr_badge');
    badge.innerHTML = '<span class="pulse-dot pulse-red"></span> فشل';
    badge.className = 'indicator ind-offline';
    addLog('فشل توليد QR — تأكد من تشغيل البوت','err');
}

function showConnectedState() {
    return `
        <div>
            <div style="font-size:4rem;margin-bottom:.75rem">✅</div>
            <div style="color:var(--success);font-size:1.1rem;font-weight:700">واتساب متصل بنجاح!</div>
            <div style="color:var(--muted);font-size:.85rem;margin-top:.5rem">الجلسة نشطة والبوت يعمل</div>
        </div>`;
}

let qrInterval;
function startQRTimer() {
    let sec = 60;
    clearInterval(qrInterval);
    qrInterval = setInterval(() => {
        sec--;
        if(sec <= 0) {
            clearInterval(qrInterval);
            const box = document.getElementById('qr_box');
            box.innerHTML = `
                <div>
                    <div style="font-size:2rem;margin-bottom:.5rem">⏰</div>
                    <div style="color:var(--warning)">انتهت صلاحية الرمز</div>
                    <button class="btn btn-primary btn-sm" style="margin-top:1rem" onclick="generateQR()">🔄 توليد جديد</button>
                </div>`;
        }
    }, 1000);
}

// ==== Pairing Code ====
let pairTimer;
function requestPairCode() {
    const phone = document.getElementById('pair_phone').value.trim();
    if (!phone) { alert('أدخل رقم الواتساب'); return; }
    
    const display = document.getElementById('pair_code_display');
    const codeEl = document.getElementById('pair_code_val');
    const timerEl = document.getElementById('pair_timer');
    const badge = document.getElementById('pair_badge');
    
    badge.innerHTML = '<span class="pulse-dot pulse-yellow"></span> جاري الإرسال...';
    badge.className = 'indicator ind-warn';
    addLog(`طلب pairing code للرقم ${phone}`);
    
    fetch('api_wa.php?action=pair&phone='+encodeURIComponent(phone))
        .then(r => r.json())
        .then(d => {
            if(d.code) {
                display.style.display = 'block';
                codeEl.textContent = d.code;
                badge.innerHTML = '<span class="pulse-dot pulse-green"></span> الكود جاهز';
                badge.className = 'indicator ind-warn';
                addLog('تم استلام كود الربط: ' + d.code,'ok');
                
                let sec = 60;
                clearInterval(pairTimer);
                pairTimer = setInterval(() => {
                    sec--;
                    timerEl.textContent = `⏱ ${sec} ثانية متبقية`;
                    if(sec <= 0) {
                        clearInterval(pairTimer);
                        timerEl.textContent = '⛔ انتهت صلاحية الكود';
                        timerEl.style.color = 'var(--danger)';
                        codeEl.style.opacity = '.3';
                    }
                }, 1000);
            } else {
                alert(d.error || 'تعذر الحصول على الكود. تأكد من تشغيل البوت.');
                badge.innerHTML = '<span class="pulse-dot pulse-red"></span> فشل';
                badge.className = 'indicator ind-offline';
                addLog('فشل طلب pairing code','err');
            }
        })
        .catch(() => {
            alert('تعذر التواصل مع خادم البوت. تأكد من تشغيل PM2.');
            addLog('خطأ شبكي عند طلب الكود','err');
        });
}

// ==== Check All ====
function checkAll() {
    addLog('تشغيل فحص شامل للخدمات...');
    pm2Action('status');
    setTimeout(() => nodeAction('check'), 800);
    setTimeout(() => {
        // فحص حالة واتساب
        fetch('api_wa.php?action=status')
            .then(r=>r.json())
            .then(d=>{
                const cardSt = document.getElementById('wa_card_status');
                const cardDot = document.getElementById('wa_card_dot');
                if(d.connected) {
                    cardSt.textContent = '✅ متصل';
                    cardDot.innerHTML = '<div class="pulse-dot" style="background:var(--success);animation:pulseGreen 1.5s infinite"></div>';
                    addLog('واتساب متصل','ok');
                } else {
                    cardSt.textContent = '❌ غير متصل';
                    cardDot.innerHTML = '<div class="pulse-dot" style="background:var(--danger)"></div>';
                    addLog('واتساب غير متصل','warn');
                }
            })
            .catch(()=>{
                document.getElementById('wa_card_status').textContent = '❓ غير متاح';
                addLog('تعذر فحص واتساب','err');
            });
        
        // تحديث البادج الرئيسي
        const globalBadge = document.getElementById('global_status_badge');
        globalBadge.innerHTML = '<span class="pulse-dot pulse-yellow"></span> جاري الفحص...';
        setTimeout(()=>{
            // تقييم عام
            const pm2ok = document.getElementById('pm2_badge').className.includes('online');
            const nodeok = document.getElementById('node_badge').className.includes('online');
            if(pm2ok && nodeok) {
                globalBadge.innerHTML = '<span class="pulse-dot pulse-green" style="background:var(--success)"></span> جميع الخدمات تعمل';
                globalBadge.style.cssText = 'background:rgba(16,185,129,.15);color:var(--success);border:1px solid rgba(16,185,129,.3)';
            } else if(!pm2ok && !nodeok) {
                globalBadge.innerHTML = '<span class="pulse-dot pulse-red" style="background:var(--danger)"></span> الخدمات متوقفة';
                globalBadge.style.cssText = 'background:rgba(239,68,68,.15);color:var(--danger);border:1px solid rgba(239,68,68,.3)';
            } else {
                globalBadge.innerHTML = '<span class="pulse-dot pulse-yellow" style="background:var(--warning)"></span> جزئياً يعمل';
                globalBadge.style.cssText = 'background:rgba(245,158,11,.15);color:var(--warning);border:1px solid rgba(245,158,11,.3)';
            }
        }, 3000);
    }, 1600);
}

// Auto-check on load
window.addEventListener('load', () => setTimeout(checkAll, 600));
</script>

<?php require 'layout_end.php'; ?>
