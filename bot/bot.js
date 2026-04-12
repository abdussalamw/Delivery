/**
 * ====================================================
 * Delivery Bot — Baileys + Express
 * نظام استقبال طلبات التوصيل عبر واتساب
 * ====================================================
 */

const { default: makeWASocket, useMultiFileAuthState, DisconnectReason, makeInMemoryStore } = require('@whiskeysockets/baileys');
const pino = require('pino');
const express = require('express');
const qrcode = require('qrcode');
const http = require('http');

const app = express();
app.use(express.json());

const PORT = 3000;
const AUTH_DIR = './auth_info';

// ====== متغيرات الحالة ======
let sock = null;
let currentQR = null;
let isConnected = false;
let connectedPhone = null;
let pairingCodePending = null;
let messageBuffer = {}; // تجميع رسائل العميل لفترة 45 ثانية

// ====== الاتصال بقاعدة البيانات PHP (عبر HTTP داخلي) ======
const DB_WEBHOOK = 'http://localhost/Delivery/webhook_handler.php';

// ====== تهيئة البوت ======
async function startBot(usePairing = false, pairingPhone = null) {
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);

    sock = makeWASocket({
        logger: pino({ level: 'silent' }),
        auth: state,
        printQRInTerminal: !usePairing,
        browser: ['Delivery Bot', 'Chrome', '1.0.0'],
    });

    if (usePairing && pairingPhone && !sock.authState.creds.registered) {
        setTimeout(async () => {
            try {
                const code = await sock.requestPairingCode(pairingPhone);
                pairingCodePending = code;
                console.log('✅ Pairing Code:', code);
            } catch (e) {
                console.error('❌ خطأ في طلب الكود:', e.message);
            }
        }, 3000);
    }

    // ====== حدث QR ======
    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            currentQR = await qrcode.toDataURL(qr);
            isConnected = false;
            console.log('📷 QR Code جاهز — افتح http://localhost:3000/status');
        }

        if (connection === 'close') {
            isConnected = false;
            currentQR = null;
            connectedPhone = null;
            const code = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = code !== DisconnectReason.loggedOut;
            console.log('🔌 انقطع الاتصال — الكود:', code, '— إعادة المحاولة:', shouldReconnect);
            if (shouldReconnect) {
                setTimeout(() => startBot(), 5000);
            }
        }

        if (connection === 'open') {
            isConnected = true;
            currentQR = null;
            connectedPhone = sock.user?.id?.split(':')[0] || null;
            console.log('✅ واتساب متصل! الرقم:', connectedPhone);
        }
    });

    sock.ev.on('creds.update', saveCreds);

    // ====== استقبال الرسائل ======
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            if (msg.key.fromMe) continue;
            if (!msg.message) continue;

            const from = msg.key.remoteJid;
            if (from.endsWith('@g.us')) continue; // تجاهل المجموعات

            const phone = from.replace('@s.whatsapp.net', '');
            const text = msg.message?.conversation
                || msg.message?.extendedTextMessage?.text
                || '';

            const location = msg.message?.locationMessage
                ? `https://maps.google.com/?q=${msg.message.locationMessage.degreesLatitude},${msg.message.locationMessage.degreesLongitude}`
                : null;

            console.log(`📨 رسالة من ${phone}: ${text.substring(0, 60)}`);

            // تجميع الرسائل لمدة 45 ثانية قبل المعالجة
            if (!messageBuffer[phone]) {
                messageBuffer[phone] = { messages: [], location: null, timer: null };
            }
            if (text) messageBuffer[phone].messages.push(text);
            if (location) messageBuffer[phone].location = location;

            clearTimeout(messageBuffer[phone].timer);
            messageBuffer[phone].timer = setTimeout(async () => {
                await processOrder(phone, messageBuffer[phone]);
                delete messageBuffer[phone];
            }, 45000); // 45 ثانية
        }
    });
}

// ====== معالجة الطلب ======
async function processOrder(phone, buffer) {
    const combined = buffer.messages.join('\n');
    console.log(`⚙️ معالجة طلب من ${phone}: ${combined.substring(0, 80)}...`);

    try {
        const res = await fetch(DB_WEBHOOK, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                from: phone,
                message: combined,
                location_url: buffer.location,
                timestamp: Math.floor(Date.now() / 1000),
            })
        });
        const data = await res.json();

        if (data.success && sock && isConnected) {
            const jid = phone + '@s.whatsapp.net';
            // إرسال رسالة تأكيد للعميل
            await sock.sendMessage(jid, {
                text: `✅ *تم استلام طلبك بنجاح!*\n\n📦 رقم الطلب: *${data.order_number}*\n💰 رسوم التوصيل: *${data.delivery_fee} ر.س*\n🛵 سيتم إرسال مندوب قريباً\n\n_نظام التوصيل الذكي_`
            });
        }
    } catch (e) {
        console.error('❌ خطأ في معالجة الطلب:', e.message);
    }
}

// ====================================================
// ====== Express API Routes ======
// ====================================================

// الحالة العامة
app.get('/status', (req, res) => {
    res.json({
        connected: isConnected,
        phone: connectedPhone,
        hasQR: !!currentQR,
        uptime: process.uptime(),
        version: '1.0.0'
    });
});

// Ping
app.get('/ping', (req, res) => {
    res.json({ ok: true, ts: Date.now() });
});

// QR Code
app.get('/wa/qr', (req, res) => {
    if (isConnected) {
        return res.json({ connected: true, phone: connectedPhone });
    }
    if (currentQR) {
        return res.json({ qr: currentQR });
    }
    res.json({ error: 'QR لم يتم توليده بعد — انتظر 10 ثوانٍ وأعد المحاولة' });
});

// حالة واتساب
app.get('/wa/status', (req, res) => {
    res.json({ connected: isConnected, phone: connectedPhone });
});

// طلب Pairing Code
app.post('/wa/pair', async (req, res) => {
    const { phone } = req.body;
    if (!phone) return res.status(400).json({ error: 'أدخل رقم الهاتف' });

    if (isConnected) {
        return res.json({ error: 'البوت متصل بالفعل — افصله أولاً لإعادة الربط' });
    }

    // إعادة تشغيل البوت مع وضع الـ Pairing
    await startBot(true, phone.replace(/[^0-9]/g, ''));

    // انتظار الكود
    let waited = 0;
    const check = () => {
        if (pairingCodePending) {
            const code = pairingCodePending;
            pairingCodePending = null;
            return res.json({ code });
        }
        waited += 500;
        if (waited > 15000) return res.json({ error: 'انتهت مهلة الانتظار — أعد المحاولة' });
        setTimeout(check, 500);
    };
    setTimeout(check, 3500);
});

// قطع الاتصال
app.post('/wa/disconnect', async (req, res) => {
    if (sock) {
        await sock.logout();
        isConnected = false;
        currentQR = null;
        connectedPhone = null;
    }
    res.json({ ok: true });
});

// إرسال رسالة يدوية (للاختبار والإشعارات)
app.post('/send', async (req, res) => {
    const { phone, message } = req.body;
    if (!sock || !isConnected) {
        return res.status(503).json({ error: 'البوت غير متصل' });
    }
    try {
        const jid = phone.replace(/[^0-9]/g, '') + '@s.whatsapp.net';
        await sock.sendMessage(jid, { text: message });
        res.json({ ok: true });
    } catch (e) {
        res.status(500).json({ error: e.message });
    }
});

// ====================================================
// ====== تشغيل الخادم ======
// ====================================================
app.listen(PORT, () => {
    console.log(`🚀 Delivery Bot API يعمل على http://localhost:${PORT}`);
    console.log('⚙️  جاري الاتصال بالواتساب...');
    startBot();
});

process.on('uncaughtException', (err) => {
    console.error('❌ خطأ غير متوقع:', err.message);
});
