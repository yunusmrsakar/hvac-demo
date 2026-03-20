import { signToken } from '../../lib/auth.js';

const ADMIN_USER = process.env.ADMIN_USER || 'admin';
const ADMIN_PASS = process.env.ADMIN_PASS || 'admin123';

// Simple in-memory brute-force protection (per serverless instance)
const attempts = new Map();

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') return res.status(204).end();
  if (req.method !== 'POST') return res.status(405).json({ success: false, error: 'Method not allowed.' });

  const ip = req.headers['x-forwarded-for'] || 'unknown';
  const now = Date.now();
  const entry = attempts.get(ip) || { count: 0, lockedUntil: 0 };

  if (now < entry.lockedUntil) {
    const secs = Math.ceil((entry.lockedUntil - now) / 1000);
    return res.status(429).json({ success: false, error: `Too many attempts. Wait ${secs}s.` });
  }

  const { username, password } = req.body || {};
  if (username === ADMIN_USER && password === ADMIN_PASS) {
    attempts.delete(ip);
    const token = signToken({ admin: true, user: username });
    return res.status(200).json({ success: true, token });
  }

  entry.count = (entry.count || 0) + 1;
  if (entry.count >= 5) {
    entry.lockedUntil = now + 30_000;
    entry.count = 0;
    attempts.set(ip, entry);
    return res.status(429).json({ success: false, error: 'Too many failed attempts. Locked for 30 seconds.' });
  }
  attempts.set(ip, entry);
  const remaining = 5 - entry.count;
  return res.status(401).json({ success: false, error: `Invalid credentials. ${remaining} attempt(s) remaining.` });
}
