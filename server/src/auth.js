'use strict';

const jwt = require('jsonwebtoken');
const bcrypt = require('bcryptjs');
const config = require('./config');
const { ApiError } = require('./http');
const { one } = require('./db');

// The roles in profiles that may see the whole salon.
const MANAGEMENT_ROLES = ['admin', 'manager'];
const STAFF_ROLES = ['admin', 'manager', 'barber', 'staff'];

function signToken(claims) {
  return jwt.sign(claims, config.jwt.secret, {
    expiresIn: config.jwt.ttl,
    issuer: 'morrow-and-blade-api',
  });
}

function verifyToken(token) {
  return jwt.verify(token, config.jwt.secret, { issuer: 'morrow-and-blade-api' });
}

/**
 * PHP writes password_hash() digests, which carry a $2y$ prefix. bcryptjs
 * speaks $2a$/$2b$, so the prefix is normalised before comparing; the
 * underlying algorithm is identical. A malformed hash returns false rather
 * than throwing, so one bad row cannot take the sign-in route down.
 */
function verifyPassword(plain, hash) {
  if (typeof plain !== 'string' || typeof hash !== 'string' || hash === '') return false;
  const normalised = hash.indexOf('$2y$') === 0 ? `$2b$${hash.slice(4)}` : hash;
  try {
    return bcrypt.compareSync(plain, normalised);
  } catch {
    return false;
  }
}

function hashPassword(plain) {
  return bcrypt.hashSync(plain, 10);
}

function bearer(req) {
  const header = req.get('authorization') || '';
  const parts = header.split(' ');
  const scheme = parts[0];
  const token = parts[1];
  return scheme && scheme.toLowerCase() === 'bearer' && token ? token : null;
}

/** Attach req.user when a valid token is present. Never rejects. */
function optionalAuth(req, res, next) {
  const token = bearer(req);
  if (!token) return next();
  try {
    req.user = verifyToken(token);
  } catch {
    req.user = undefined;
  }
  return next();
}

function requireAuth(req, res, next) {
  const token = bearer(req);
  if (!token) return next(ApiError.unauthorized());
  try {
    req.user = verifyToken(token);
    return next();
  } catch (error) {
    const message = error.name === 'TokenExpiredError'
      ? 'Your session has expired. Please sign in again.'
      : 'That sign-in is not valid.';
    return next(ApiError.unauthorized(message));
  }
}

function requireRole() {
  const roles = Array.prototype.slice.call(arguments);
  return (req, res, next) => {
    if (!req.user) return next(ApiError.unauthorized());
    if (roles.indexOf(req.user.role) === -1) return next(ApiError.forbidden());
    return next();
  };
}

const requireCustomer = requireRole('customer');
const requireManagement = requireRole.apply(null, MANAGEMENT_ROLES);
const requireStaff = requireRole.apply(null, STAFF_ROLES);

/** Load the signed-in customer, or 401 if the account has since gone. */
async function currentCustomer(req) {
  const row = await one('SELECT * FROM customers WHERE id = ? LIMIT 1', [req.user.sub]);
  if (!row) throw ApiError.unauthorized('That account no longer exists.');
  return row;
}

/** Load the signed-in staff profile, or 401 if it has been deactivated. */
async function currentProfile(req) {
  const row = await one('SELECT * FROM profiles WHERE id = ? AND is_active = 1 LIMIT 1', [req.user.sub]);
  if (!row) throw ApiError.unauthorized('That staff account no longer exists.');
  return row;
}

module.exports = {
  MANAGEMENT_ROLES,
  STAFF_ROLES,
  signToken,
  verifyToken,
  hashPassword,
  verifyPassword,
  optionalAuth,
  requireAuth,
  requireRole,
  requireCustomer,
  requireManagement,
  requireStaff,
  currentCustomer,
  currentProfile,
};