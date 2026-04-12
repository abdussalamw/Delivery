/**
 * ====================================================
 * Delivery Bot — Baileys + Express
 * ====================================================
 */

const { 
    default: makeWASocket, 
    useMultiFileAuthState, 
    DisconnectReason,
    fetchLatestBaileysVersion,
    makeCacheableSignalKeyStore
} = require('@whiskeysockets/baileys');
const pino = require('pino');
const express = require('express');
const qrcode = require('qrcode');

const app = express();
app.use(express.json());

const PORT = 3000;
const AUTH_DIR = './auth_info';

let sock = null;
let currentQR = null;
let isConnected = false;
let connectedPhone = null;
let pairingCodePending = null;
let messageBuffer = {};

const DB_WEBHOOK = process.env.WEBHOOK_URL || 'http://localhost/Delivery/webhook_handler.php';

async function startBot(usePairing = false, pairingPhone = null) {
    console.log(`[BOT] 🚀 Starting... Pairing: ${usePairing}`);
    
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    const { version, isLatest } = await fetchLatestBaileysVersion();
    console.log(`[BOT] WA Version: ${version.join('.')}, latest: ${isLatest}`);

    sock = makeWASocket({
        version,
        logger: pino({ level: 'info' }),
        auth: {
            creds: state.creds,
            keys: makeCacheableSignalKeyStore(state.keys, pino({ level: 'info' })),
        },
        printQRInTerminal: false,
        browser: ["Ubuntu", "Chrome", "20.0.04"],
        connectTimeoutMs: 60000,
        keepAliveIntervalMs: 20000,
    });

    if (usePairing && pairingPhone && !sock.authState.creds.registered) {
        setTimeout(async () => {
            try {
                const code = await sock.requestPairingCode(pairingPhone);
                pairingCodePending = code;
                console.log('✅ Pairing Code:', code);
            } catch (e) {
                console.error('❌ Pairing Error:', e.message);
            }
        }, 5000);
    }

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            currentQR = await qrcode.toDataURL(qr);
            isConnected = false;
            console.log('📷 [BOT] QR Code generated');
        }

        if (connection === 'close') {
            isConnected = false;
            currentQR = null;
            connectedPhone = null;
            const code = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = code !== DisconnectReason.loggedOut;
            console.log('🔌 [BOT] Connection Closed. Code:', code, 'Reconnect:', shouldReconnect);
            if (shouldReconnect) {
                setTimeout(() => startBot(), 10000);
            }
        }

        if (connection === 'open') {
            isConnected = true;
            currentQR = null;
            connectedPhone = sock.user?.id?.split(':')[0] || null;
            console.log('✅ [BOT] Connected! Phone:', connectedPhone);
        }
    });

    // ... (rest of messages listener and processOrder remain same)
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;
        for (const msg of messages) {
            if (msg.key.fromMe || !msg.message) continue;
            const from = msg.key.remoteJid;
            if (from.endsWith('@g.us')) continue;
            const phone = from.replace('@s.whatsapp.net', '');
            const text = msg.message?.conversation || msg.message?.extendedTextMessage?.text || '';
            const location = msg.message?.locationMessage 
                ? `https://maps.google.com/?q=${msg.message.locationMessage.degreesLatitude},${msg.message.locationMessage.degreesLongitude}` : null;
            console.log(`📨 [BOT] Message from ${phone}`);
            if (!messageBuffer[phone]) { messageBuffer[phone] = { messages: [], location: null, timer: null }; }
            if (text) messageBuffer[phone].messages.push(text);
            if (location) messageBuffer[phone].location = location;
            clearTimeout(messageBuffer[phone].timer);
            messageBuffer[phone].timer = setTimeout(async () => {
                await processOrder(phone, messageBuffer[phone]);
                delete messageBuffer[phone];
            }, 45000);
        }
    });
}

async function processOrder(phone, buffer) {
    const combined = buffer.messages.join('\n');
    try {
        const res = await fetch(DB_WEBHOOK, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ from: phone, message: combined, location_url: buffer.location, timestamp: Math.floor(Date.now() / 1000) })
        });
        const data = await res.json();
        if (data.success && isConnected) {
            await sock.sendMessage(phone + '@s.whatsapp.net', { text: `✅ *تم استلام طلبك!*\n\n📦 رقم الطلب: *${data.order_number}*\n💰 رسوم التوصيل: *${data.delivery_fee} ر.س*` });
        }
    } catch (e) {
        console.error('❌ [BOT] Webhook Error:', e.message);
    }
}

app.get('/status', (req, res) => { res.json({ connected: isConnected, phone: connectedPhone, hasQR: !!currentQR, uptime: process.uptime() }); });
app.get('/wa/qr', (req, res) => {
    if (isConnected) return res.json({ connected: true, phone: connectedPhone });
    if (currentQR) return res.json({ qr: currentQR });
    res.json({ error: 'QR not ready' });
});
app.post('/wa/pair', async (req, res) => {
    const { phone } = req.body;
    if (!phone) return res.status(400).json({ error: 'Phone missing' });
    await startBot(true, phone.replace(/[^0-9]/g, ''));
    let waited = 0;
    const check = () => {
        if (pairingCodePending) { const code = pairingCodePending; pairingCodePending = null; return res.json({ code }); }
        if (waited > 20000) return res.json({ error: 'Timeout' });
        waited += 500; setTimeout(check, 500);
    };
    setTimeout(check, 5000);
});
app.post('/wa/disconnect', async (req, res) => { if (sock) { await sock.logout(); isConnected = false; currentQR = null; connectedPhone = null; } res.json({ ok: true }); });
app.post('/send', async (req, res) => {
    const { phone, message } = req.body;
    if (!isConnected) return res.status(503).json({ error: 'Disconnected' });
    try { await sock.sendMessage(phone.replace(/[^0-9]/g, '') + '@s.whatsapp.net', { text: message }); res.json({ ok: true }); }
    catch (e) { res.status(500).json({ error: e.message }); }
});

app.listen(PORT, () => {
    console.log(`🚀 API: http://localhost:${PORT}`);
    startBot().catch(err => console.error('FAILED TO START BOT:', err));
});

process.on('uncaughtException', (err) => { console.error('❌ Fatal:', err); });
process.on('unhandledRejection', (err) => { console.error('❌ Rejection:', err); });
