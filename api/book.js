import { getDB, initSchema } from '../lib/db.js';

function generateReference() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let alpha = '';
  for (let i = 0; i < 4; i++) {
    alpha += chars[Math.floor(Math.random() * chars.length)];
  }
  const digits = String(Math.floor(Math.random() * 10000)).padStart(4, '0');
  return `CB-${alpha}-${digits}`;
}

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') return res.status(204).end();
  if (req.method !== 'POST') return res.status(405).json({ success: false, error: 'Method not allowed.' });

  const data = req.body;
  if (!data || typeof data !== 'object') {
    return res.status(400).json({ success: false, error: 'Invalid JSON body.' });
  }

  const required = ['service_type', 'customer_name', 'phone', 'email', 'preferred_date', 'time_slot', 'address'];
  const missing = required.filter(f => !String(data[f] || '').trim());
  if (missing.length) {
    return res.status(422).json({ success: false, error: `Missing required fields: ${missing.join(', ')}` });
  }

  const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRe.test(data.email.trim())) {
    return res.status(422).json({ success: false, error: 'Invalid email address.' });
  }

  const serviceType   = data.service_type.trim();
  const customerName  = data.customer_name.trim();
  const phone         = data.phone.trim();
  const email         = data.email.trim();
  const preferredDate = data.preferred_date.trim();
  const timeSlot      = data.time_slot.trim();
  const address       = data.address.trim();
  const notes         = (data.notes || '').trim();

  try {
    await initSchema();
    const sql = getDB();

    // Generate unique reference
    let reference = '';
    for (let attempt = 0; attempt < 10; attempt++) {
      const candidate = generateReference();
      const rows = await sql`SELECT id FROM bookings WHERE reference = ${candidate}`;
      if (rows.length === 0) { reference = candidate; break; }
    }
    if (!reference) {
      return res.status(500).json({ success: false, error: 'Could not generate a unique reference. Please try again.' });
    }

    await sql`
      INSERT INTO bookings
        (reference, service_type, customer_name, phone, email, preferred_date, time_slot, address, notes, status)
      VALUES
        (${reference}, ${serviceType}, ${customerName}, ${phone}, ${email}, ${preferredDate}, ${timeSlot}, ${address}, ${notes}, 'pending')
    `;

    return res.status(200).json({ success: true, reference, name: customerName });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ success: false, error: 'Database error. Please try again.' });
  }
}
