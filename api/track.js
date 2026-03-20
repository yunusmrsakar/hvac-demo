import { getDB, initSchema } from '../lib/db.js';

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');

  if (req.method === 'OPTIONS') return res.status(204).end();
  if (req.method !== 'GET') return res.status(405).json({ success: false, error: 'Method not allowed.' });

  const ref = String(req.query.ref || '').trim();
  if (!ref) {
    return res.status(400).json({ success: false, error: 'No reference number provided.' });
  }

  try {
    await initSchema();
    const sql = getDB();
    const rows = await sql`
      SELECT id, reference, service_type, customer_name, phone, email,
             preferred_date, time_slot, address, notes, status, admin_notes,
             created_at, updated_at
      FROM bookings
      WHERE reference = ${ref}
    `;

    if (rows.length === 0) {
      return res.status(404).json({ success: false, error: 'No booking found with that reference number.' });
    }

    return res.status(200).json({ success: true, booking: rows[0] });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ success: false, error: 'Database error. Please try again.' });
  }
}
