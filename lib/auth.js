import jwt from 'jsonwebtoken';

const JWT_SECRET = process.env.JWT_SECRET || 'coolbreeze-hvac-secret-change-in-production';
const JWT_EXPIRES = '8h';

export function signToken(payload) {
  return jwt.sign(payload, JWT_SECRET, { expiresIn: JWT_EXPIRES });
}

export function verifyToken(token) {
  try {
    return jwt.verify(token, JWT_SECRET);
  } catch {
    return null;
  }
}

export function requireAdmin(req, res) {
  const auth = req.headers.authorization || '';
  const token = auth.startsWith('Bearer ') ? auth.slice(7) : null;
  if (!token) {
    res.status(401).json({ success: false, error: 'Unauthorized' });
    return false;
  }
  const payload = verifyToken(token);
  if (!payload || !payload.admin) {
    res.status(401).json({ success: false, error: 'Invalid or expired token' });
    return false;
  }
  return true;
}
