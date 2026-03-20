import { getDB, initSchema } from '../../lib/db.js';
import { requireAdmin } from '../../lib/auth.js';

const VALID_STATUSES = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled'];

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, PUT, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(204).end();
  if (!requireAdmin(req, res)) return;

  const id = parseInt(req.query.id || '0');
  if (!id) return res.status(400).json({ success: false, error: 'Invalid booking ID.' });

  try {
    await initSchema();
    const sql = getDB();

    if (req.method === 'GET') {
      const rows = await sql`SELECT * FROM bookings WHERE id = ${id}`;
      if (rows.length === 0) return res.status(404).json({ success: false, error: 'Booking not found.' });
      return res.status(200).json({ success: true, booking: rows[0] });
    }

    if (req.method === 'PUT') {
      const { status, admin_notes } = req.body || {};
      if (!VALID_STATUSES.includes(status)) {
        return res.status(422).json({ success: false, error: 'Invalid status.' });
      }
      await sql`
        UPDATE bookings
        SET status = ${status}, admin_notes = ${admin_notes || ''}, updated_at = NOW()
        WHERE id = ${id}
      `;
      const rows = await sql`SELECT * FROM bookings WHERE id = ${id}`;
      return res.status(200).json({ success: true, booking: rows[0] });
    }

    return res.status(405).json({ success: false, error: 'Method not allowed.' });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ success: false, error: 'Database error.' });
  }
}
