// Richworks Talent Tracker: tiny dependency-free server. Serves the page and a JSON REST API.
// Data is stored in data/db.json. Run: node server.js  (PORT env var optional)
const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 3000;
const DATA_DIR = path.join(__dirname, 'data');
const DB_FILE = path.join(DATA_DIR, 'db.json');
const PAGE = path.join(__dirname, 'richworks-tracker.html');
const COLLECTIONS = ['people', 'records'];

fs.mkdirSync(DATA_DIR, { recursive: true });
let db = { people: {}, records: {} };
try { db = { ...db, ...JSON.parse(fs.readFileSync(DB_FILE, 'utf8')) }; } catch {}

function save() {
  const tmp = DB_FILE + '.tmp';
  fs.writeFileSync(tmp, JSON.stringify(db, null, 2));
  fs.renameSync(tmp, DB_FILE);
}

function send(res, status, body, type = 'application/json; charset=utf-8') {
  res.writeHead(status, { 'Content-Type': type, 'Cache-Control': 'no-store' });
  res.end(typeof body === 'string' ? body : JSON.stringify(body));
}

function readBody(req) {
  return new Promise((resolve, reject) => {
    let raw = '';
    req.on('data', c => { raw += c; if (raw.length > 1e6) { reject(new Error('too large')); req.destroy(); } });
    req.on('end', () => { try { resolve(raw ? JSON.parse(raw) : {}); } catch (e) { reject(e); } });
  });
}

http.createServer(async (req, res) => {
  const url = new URL(req.url, 'http://localhost');
  const parts = url.pathname.split('/').filter(Boolean);

  if (req.method === 'GET' && (parts.length === 0 || url.pathname === '/index.html')) {
    return send(res, 200, fs.readFileSync(PAGE, 'utf8'), 'text/html; charset=utf-8');
  }
  if (req.method === 'GET' && url.pathname === '/logo.png') {
    res.writeHead(200, { 'Content-Type': 'image/png', 'Cache-Control': 'max-age=86400' });
    return res.end(fs.readFileSync(path.join(__dirname, 'logo.png')));
  }
  if (parts[0] !== 'api' || !COLLECTIONS.includes(parts[1])) return send(res, 404, { error: 'not found' });

  const col = db[parts[1]];
  const id = parts[2];
  try {
    if (req.method === 'GET' && !id) return send(res, 200, Object.entries(col).map(([i, d]) => ({ id: i, ...d })));
    if (req.method === 'POST' && !id) {
      const newId = crypto.randomUUID();
      col[newId] = await readBody(req);
      save();
      return send(res, 201, { id: newId, ...col[newId] });
    }
    if (id && !(id in col)) return send(res, 404, { error: 'not found' });
    if (req.method === 'PATCH' && id) {
      col[id] = { ...col[id], ...(await readBody(req)) };
      save();
      return send(res, 200, { id, ...col[id] });
    }
    if (req.method === 'DELETE' && id) {
      delete col[id];
      if (parts[1] === 'people') for (const [rid, r] of Object.entries(db.records)) if (r.personId === id) delete db.records[rid];
      save();
      return send(res, 200, { ok: true });
    }
    send(res, 405, { error: 'method not allowed' });
  } catch (e) {
    send(res, 400, { error: String(e.message || e) });
  }
}).listen(PORT, () => console.log(`Richworks Talent Tracker running at http://localhost:${PORT}`));
