import { getDB, initSchema } from '../../lib/db.js';
import { requireAdmin } from '../../lib/auth.js';

export default async function handler(req, res) {
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization');

  if (req.method === 'OPTIONS') return res.status(204).end();
  if (!requireAdmin(req, res)) return;
  if (req.method !== 'GET') return res.status(405).json({ success: false, error: 'Method not allowed.' });

  const { status, from, to, q, page = '1' } = req.query;
  const perPage = 20;
  const offset = (Math.max(1, parseInt(page)) - 1) * perPage;

  try {
    await initSchema();
    const sql = getDB();

    // Build stats
    const statsRows = await sql`
      SELECT status, COUNT(*) AS cnt
      FROM bookings
      GROUP BY status
    `;
    const totalRow = await sql`SELECT COUNT(*) AS cnt FROM bookings`;
    const stats = { total: Number(totalRow[0].cnt), pending: 0, confirmed: 0, in_progress: 0, completed: 0, cancelled: 0 };
    for (const r of statsRows) {
      if (r.status in stats) stats[r.status] = Number(r.cnt);
    }

    // Build filtered list — use dynamic query with template literals carefully
    // We'll build conditions as an array and use sql.unsafe for dynamic WHERE
    const conditions = [];
    const values = [];

    if (status) { conditions.push(`status = $${values.push(status)}`); }
    if (from)   { conditions.push(`preferred_date >= $${values.push(from)}`); }
    if (to)     { conditions.push(`preferred_date <= $${values.push(to)}`); }
    if (q)      {
      const like = `%${q}%`;
      conditions.push(`(customer_name ILIKE $${values.push(like)} OR reference ILIKE $${values.push(like)} OR email ILIKE $${values.push(like)})`);
    }

    const where = conditions.length ? `WHERE ${conditions.join(' AND ')}` : '';
    const countQuery = `SELECT COUNT(*) AS cnt FROM bookings ${where}`;
    const listQuery  = `
      SELECT id, reference, customer_name, service_type, preferred_date, time_slot, status, created_at
      FROM bookings ${where}
      ORDER BY created_at DESC
      LIMIT $${values.push(perPage)} OFFSET $${values.push(offset)}
    `;

    // Use neon's unsafe query for dynamic SQL
    const { neon: neonRaw } = await import('@neondatabase/serverless');
    const rawSql = neonRaw(process.env.DATABASE_URL);

    const countResult = await rawSql(countQuery, values.slice(0, values.length - 2));
    const totalRows = Number(countResult[0].cnt);
    const bookings = await rawSql(listQuery, values);

    return res.status(200).json({
      success: true,
      stats,
      bookings,
      pagination: {
        total: totalRows,
        page: parseInt(page),
        perPage,
        totalPages: Math.max(1, Math.ceil(totalRows / perPage)),
      }
    });
  } catch (err) {
    console.error(err);
    return res.status(500).json({ success: false, error: 'Database error.' });
  }
}
