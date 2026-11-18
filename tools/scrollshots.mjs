// Scatta screenshot a più punti di scorrimento (per controllare animazioni legate allo scroll).
// uso: node tools/scrollshots.mjs <url> <outdir> <width> <height> <y1,y2,...>
import { spawn } from 'node:child_process';
import fs from 'node:fs';
const [url, out, W = '1440', H = '900', ys = '0'] = process.argv.slice(2);
fs.mkdirSync(out, { recursive: true });
const chrome = spawn('google-chrome', ['--headless=new', '--no-sandbox', '--remote-debugging-port=9333', '--user-data-dir=/tmp/ss-prof', `--window-size=${W},${H}`, 'about:blank'], { stdio: 'ignore' });
const sleep = ms => new Promise(r => setTimeout(r, ms));
let ver;
for (let i = 0; i < 50; i++) { try { ver = await (await fetch('http://127.0.0.1:9333/json/list')).json(); if (ver.length) break; } catch {} await sleep(200); }
const page = ver.find(t => t.type === 'page');
const ws = new WebSocket(page.webSocketDebuggerUrl);
await new Promise(r => ws.addEventListener('open', r));
let id = 0; const pend = new Map();
ws.addEventListener('message', e => { const m = JSON.parse(e.data); if (m.id && pend.has(m.id)) { pend.get(m.id)(m); pend.delete(m.id); } });
const send = (method, params = {}) => new Promise(r => { const i = ++id; pend.set(i, r); ws.send(JSON.stringify({ id: i, method, params })); });
await send('Emulation.setDeviceMetricsOverride', { width: +W, height: +H, deviceScaleFactor: 1, mobile: +W < 640 });
await send('Page.enable'); await send('Runtime.enable');
await send('Page.navigate', { url });
await sleep(4000);
const ev = async expr => (await send('Runtime.evaluate', { expression: expr, awaitPromise: true, returnByValue: true })).result?.result?.value;
console.log('height', await ev('document.documentElement.scrollHeight'), 'fx', await ev('document.documentElement.className'));
for (const y of ys.split(',')) {
  // scorre a piccoli passi, come una rotella, così ScrollTrigger aggiorna tutto
  await ev(`(async()=>{const t=${y};let c=scrollY;while(Math.abs(c-t)>1){c+=Math.sign(t-c)*Math.min(120,Math.abs(t-c));scrollTo(0,c);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}return 1})()`);
  await sleep(1300);
  const shot = await send('Page.captureScreenshot', { format: 'jpeg', quality: 70 });
  fs.writeFileSync(`${out}/y${String(y).padStart(5, '0')}.jpg`, Buffer.from(shot.result.data, 'base64'));
}
ws.close(); chrome.kill();
