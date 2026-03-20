import { neon } from '@neondatabase/serverless';

let _sql = null;

export function getDB() {
  if (!_sql) {
    if (!process.env.DATABASE_URL) {
      throw new Error('DATABASE_URL environment variable is not set.');
    }
    _sql = neon(process.env.DATABASE_URL);
  }
  return _sql;
}

export async function initSchema() {
  const sql = getDB();
  await sql`
    CREATE TABLE IF NOT EXISTS bookings (
      id         SERIAL PRIMARY KEY,
      reference  TEXT UNIQUE NOT NULL,
      service_type   TEXT NOT NULL,
      customer_name  TEXT NOT NULL,
      phone          TEXT NOT NULL,
      email          TEXT NOT NULL,
      preferred_date TEXT NOT NULL,
      time_slot      TEXT NOT NULL,
      address        TEXT NOT NULL,
      notes          TEXT DEFAULT '',
      status         TEXT DEFAULT 'pending',
      admin_notes    TEXT DEFAULT '',
      created_at     TIMESTAMPTZ DEFAULT NOW(),
      updated_at     TIMESTAMPTZ DEFAULT NOW()
    )
  `;
}
