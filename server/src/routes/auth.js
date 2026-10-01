'use strict';

const express = require('express');
const crypto = require('node:crypto');
const { ApiError, asyncHandler } = require('../http');
const { query, one } = require('../db');
const {
  STAFF_ROLES,
  signToken,
  hashPassword,
  verifyPassword,
  requireAuth,
  requireCustomer,
  currentCustomer,
  currentProfile,
} = require('../auth');
const v = require('../validate');

const router = express.Router();

function publicCustomer(row) {
  const copy = Object.assign({}, row);
  delete copy.password_hash;
  copy.kind = 'customer';
  return copy;
}

function publicProfile(row) {
  const copy = Object.assign({}, row);
  delete copy.password_hash;
  copy.kind = 'staff';
  return copy;
}

router.post('/register', asyncHandler(async (req, res) => {
  const name = v.requireString(req.body, 'name', { max: 150 });
  const email = v.requireEmail(req.body);
  const phone = v.requireString(req.body, 'phone', { max: 40 });
  const password = v.requireString(req.body, 'password', { min: 8, max: 200 });
  const marketing = v.requireBool(req.body, 'marketing_opt_in');

  const id = crypto.randomUUID();
  await query(
    'INSERT INTO customers (id, name, email, phone, password_hash, marketing_opt_in) VALUES (?, ?, ?, ?, ?, ?)',
    [id, name, email, phone, hashPassword(password), marketing ? 1 : 0]
  );

  const customer = await one('SELECT * FROM customers WHERE id = ? LIMIT 1', [id]);
  const token = signToken({
    sub: id,
    kind: 'customer',
    role: 'customer',
    name: customer.name,
    email: customer.email,
  });

  res.status(201).json({ ok: true, data: { token, user: publicCustomer(customer) } });
}));

router.post('/login', asyncHandler(async (req, res) => {
  const email = v.requireEmail(req.body);
  const password = v.requireString(req.body, 'password', { max: 200 });

  const customer = await one('SELECT * FROM customers WHERE email = ? LIMIT 1', [email]);

  // Same message either way so the endpoint cannot be used to enumerate accounts.
  if (!customer || verifyPassword(password, customer.password_hash) === false) {
    throw ApiError.unauthorized('That email and password do not match.');
  }

  await query('UPDATE customers SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [customer.id]);

  const token = signToken({
    sub: customer.id,
    kind: 'customer',
    role: 'customer',
    name: customer.name,
    email: customer.email,
  });

  res.json({ ok: true, data: { token, user: publicCustomer(customer) } });
}));

router.post('/staff/login', asyncHandler(async (req, res) => {
  const email = v.requireEmail(req.body);
  const password = v.requireString(req.body, 'password', { max: 200 });

  const profile = await one('SELECT * FROM profiles WHERE email = ? AND is_active = 1 LIMIT 1', [email]);

  if (!profile || verifyPassword(password, profile.password_hash) === false) {
    throw ApiError.unauthorized('That email and password do not match.');
  }
  if (STAFF_ROLES.indexOf(profile.role) === -1) {
    throw ApiError.forbidden('That account has not been given a staff role yet.');
  }

  await query('UPDATE profiles SET last_login_at = UTC_TIMESTAMP() WHERE id = ?', [profile.id]);

  const token = signToken({
    sub: profile.id,
    kind: 'staff',
    role: profile.role,
    barber_id: profile.barber_id,
    name: profile.full_name,
    email: profile.email,
  });

  res.json({ ok: true, data: { token, user: publicProfile(profile) } });
}));

router.get('/me', requireAuth, asyncHandler(async (req, res) => {
  if (req.user.kind === 'customer') {
    return res.json({ ok: true, data: publicCustomer(await currentCustomer(req)) });
  }
  return res.json({ ok: true, data: publicProfile(await currentProfile(req)) });
}));

// A customer may edit their own details. Staff records are managed from
// admin/index.php and through the portal-logins pages there.
router.patch('/me', requireAuth, requireCustomer, asyncHandler(async (req, res) => {
  const customer = await currentCustomer(req);
  const sets = [];
  const params = [];

  if (req.body && req.body.name !== undefined) {
    sets.push('name = ?');
    params.push(v.requireString(req.body, 'name', { max: 150 }));
  }
  if (req.body && req.body.phone !== undefined) {
    sets.push('phone = ?');
    params.push(v.requireString(req.body, 'phone', { max: 40 }));
  }
  if (req.body && req.body.notes !== undefined) {
    sets.push('notes = ?');
    params.push(v.optionalString(req.body, 'notes', { max: 1000 }));
  }
  if (req.body && req.body.marketing_opt_in !== undefined) {
    sets.push('marketing_opt_in = ?');
    params.push(v.requireBool(req.body, 'marketing_opt_in') ? 1 : 0);
  }
  if (req.body && req.body.preferred_barber_id !== undefined) {
    const barberId = req.body.preferred_barber_id === null || req.body.preferred_barber_id === ''
      ? null
      : v.requireUuid(req.body, 'preferred_barber_id');
    if (barberId !== null) {
      const exists = await one('SELECT id FROM barbers WHERE id = ? LIMIT 1', [barberId]);
      if (!exists) throw ApiError.unprocessable('That barber does not exist.');
    }
    sets.push('preferred_barber_id = ?');
    params.push(barberId);
  }

  if (sets.length === 0) throw ApiError.badRequest('Send at least one field to update.');

  params.push(customer.id);
  await query(`UPDATE customers SET ${sets.join(', ')} WHERE id = ?`, params);

  res.json({ ok: true, data: publicCustomer(await currentCustomer(req)) });
}));

router.post('/password', requireAuth, asyncHandler(async (req, res) => {
  const current = v.requireString(req.body, 'current_password', { max: 200 });
  const next = v.requireString(req.body, 'new_password', { min: 8, max: 200 });

  const row = req.user.kind === 'customer' ? await currentCustomer(req) : await currentProfile(req);
  if (verifyPassword(current, row.password_hash) === false) {
    throw ApiError.badRequest('Your current password is not right.');
  }

  const table = req.user.kind === 'customer' ? 'customers' : 'profiles';
  await query(`UPDATE ${table} SET password_hash = ? WHERE id = ?`, [hashPassword(next), row.id]);

  res.json({ ok: true, data: { changed: true } });
}));

module.exports = router;