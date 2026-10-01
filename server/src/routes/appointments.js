'use strict';

const express = require('express');
const crypto = require('node:crypto');
const { ApiError, asyncHandler } = require('../http');
const { query, one, transaction } = require('../db');
const { requireAuth, requireManagement, MANAGEMENT_ROLES } = require('../auth');
const v = require('../validate');

const router = express.Router();

const STATUSES = ['pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'];
const OPEN_STATUSES = ['pending', 'confirmed'];

const SELECT = `SELECT a.*,
    b.name AS barber_name, b.slug AS barber_slug,
    s.name AS service_name, s.slug AS service_slug, s.duration_minutes,
    c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone,
    (a.starts_at <= UTC_TIMESTAMP()) AS has_started
  FROM appointments a
  JOIN barbers b ON b.id = a.barber_id
  JOIN services s ON s.id = a.service_id
  JOIN customers c ON c.id = a.customer_id`;

const isManagement = (req) => MANAGEMENT_ROLES.indexOf(req.user.role) !== -1;

async function loadAppointment(id) {
  const row = v.isUuid(id) ? await one(`${SELECT} WHERE a.id = ? LIMIT 1`, [id]) : null;
  if (!row) throw ApiError.notFound('That appointment does not exist.');
  return row;
}

/** A booking is visible to its customer, its barber, or management. */
function assertVisible(req, row) {
  if (isManagement(req)) return;
  if (req.user.kind === 'customer' && row.customer_id === req.user.sub) return;
  if (req.user.role === 'barber' && req.user.barber_id && row.barber_id === req.user.barber_id) return;
  throw ApiError.forbidden('That booking belongs to someone else.');
}

/** Mirrors BookingDAO::nextReference(): MB- plus five digits, retried on clash. */
async function nextReference(connection) {
  for (let attempt = 0; attempt < 12; attempt += 1) {
    const reference = `MB-${crypto.randomInt(10000, 100000)}`;
    const [rows] = await connection.query('SELECT id FROM appointments WHERE reference = ? LIMIT 1', [reference]);
    if (rows.length === 0) return reference;
  }
  throw new Error('Could not allocate a booking reference.');
}

async function findOrCreateCustomer(connection, input) {
  if (input.customer_id) {
    const [rows] = await connection.query('SELECT * FROM customers WHERE id = ? LIMIT 1', [input.customer_id]);
    if (rows.length === 0) throw ApiError.unprocessable('That customer does not exist.');
    return rows[0];
  }

  const email = v.requireEmail(input, 'customer_email');
  const [existing] = await connection.query('SELECT * FROM customers WHERE email = ? LIMIT 1', [email]);
  if (existing.length) return existing[0];

  const id = crypto.randomUUID();
  await connection.query(
    'INSERT INTO customers (id, name, email, phone) VALUES (?, ?, ?, ?)',
    [id, v.requireString(input, 'customer_name', { max: 150 }), email, v.requireString(input, 'customer_phone', { max: 40 })]
  );
  const [created] = await connection.query('SELECT * FROM customers WHERE id = ? LIMIT 1', [id]);
  return created[0];
}

router.get('/', requireAuth, asyncHandler(async (req, res) => {
  const { limit, offset } = v.pagination(req.query, { maxLimit: 200 });
  const where = [];
  const params = [];

  // Scope first: the caller's own rows, unless they run the salon.
  if (req.user.kind === 'customer') {
    where.push('a.customer_id = ?');
    params.push(req.user.sub);
  } else if (req.user.role === 'barber') {
    if (!req.user.barber_id) throw ApiError.forbidden('That login is not attached to a barber.');
    where.push('a.barber_id = ?');
    params.push(req.user.barber_id);
  } else if (!isManagement(req)) {
    throw ApiError.forbidden();
  } else if (req.query.barber_id) {
    where.push('a.barber_id = ?');
    params.push(v.requireUuid(req.query, 'barber_id'));
  }

  if (req.query.status) {
    where.push('a.status = ?');
    params.push(v.requireEnum(req.query, 'status', STATUSES));
  }
  if (req.query.service_id) {
    where.push('a.service_id = ?');
    params.push(v.requireUuid(req.query, 'service_id'));
  }
  if (req.query.from) {
    where.push('a.starts_at >= ?');
    params.push(v.toMysqlDateTime(v.requireDate(req.query, 'from')));
  }
  if (req.query.to) {
    where.push('a.starts_at <= ?');
    params.push(v.toMysqlDateTime(v.requireDate(req.query, 'to')));
  }
  if (v.boolValue(req.query.upcoming)) {
    where.push('a.starts_at >= UTC_TIMESTAMP()');
    where.push(`a.status IN ('pending', 'confirmed')`);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const rows = await query(
    `${SELECT} ${whereSql} ORDER BY a.starts_at DESC LIMIT ${limit} OFFSET ${offset}`,
    params
  );
  const total = await one(`SELECT COUNT(*) AS total FROM appointments a ${whereSql}`, params);

  res.json({ ok: true, data: rows, meta: { count: rows.length, total: total.total, limit, offset } });
}));

router.get('/:id', requireAuth, asyncHandler(async (req, res) => {
  const row = await loadAppointment(req.params.id);
  assertVisible(req, row);
  res.json({ ok: true, data: row });
}));

router.post('/', requireAuth, asyncHandler(async (req, res) => {
  const barberId = req.user.role === 'barber' && req.user.barber_id
    ? req.user.barber_id
    : v.requireUuid(req.body, 'barber_id');
  const serviceId = v.requireUuid(req.body, 'service_id');
  const startsAt = v.requireDate(req.body, 'starts_at');
  const notes = v.optionalString(req.body, 'notes', { max: 2000 });

  if (req.user.role === 'barber' && req.user.barber_id && req.body.barber_id
      && req.body.barber_id !== req.user.barber_id) {
    throw ApiError.forbidden('A barber can only take bookings for themselves.');
  }
  if (startsAt.getTime() <= Date.now()) {
    throw ApiError.unprocessable('A booking has to start in the future.');
  }

  const service = await one('SELECT * FROM services WHERE id = ? AND active = 1 LIMIT 1', [serviceId]);
  if (!service) throw ApiError.unprocessable('That treatment is not available.');
  const barber = await one('SELECT * FROM barbers WHERE id = ? AND active = 1 LIMIT 1', [barberId]);
  if (!barber) throw ApiError.unprocessable('That team member is not available.');

  const offers = await one('SELECT service_id FROM barber_services WHERE barber_id = ? AND service_id = ? LIMIT 1', [barberId, serviceId]);
  if (!offers) throw ApiError.unprocessable('That team member does not offer that treatment.');

  const startsSql = v.toMysqlDateTime(startsAt);
  const endsSql = v.toMysqlDateTime(new Date(startsAt.getTime() + service.duration_minutes * 60000));

  const created = await transaction(async (connection) => {
    // Re-check inside the transaction so two people cannot take the same slot.
    // The appointments_no_overlap_* triggers remain the final word.
    const [clash] = await connection.query(
      `SELECT id FROM appointments
       WHERE barber_id = ? AND status IN ('pending', 'confirmed', 'in_progress')
         AND starts_at < ? AND ends_at > ?
       LIMIT 1 FOR UPDATE`,
      [barberId, endsSql, startsSql]
    );
    if (clash.length) throw ApiError.conflict('Sorry, that slot was just taken. Please choose another time.');

    const customer = req.user.kind === 'customer'
      ? (await connection.query('SELECT * FROM customers WHERE id = ? LIMIT 1', [req.user.sub]))[0][0]
      : await findOrCreateCustomer(connection, req.body || {});

    if (!customer) throw ApiError.unprocessable('That customer does not exist.');

    const id = crypto.randomUUID();
    const reference = await nextReference(connection);
    const publicToken = crypto.randomUUID();

    await connection.query(
      `INSERT INTO appointments (id, reference, public_token, customer_id, barber_id, service_id,
         starts_at, ends_at, status, price_pence_snapshot, service_name_snapshot, notes)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)`,
      [id, reference, publicToken, customer.id, barberId, serviceId,
        startsSql, endsSql, service.price_pence, service.name, notes]
    );

    return id;
  });

  res.status(201).json({ ok: true, data: await loadAppointment(created) });
}));

router.patch('/:id', requireAuth, asyncHandler(async (req, res) => {
  const existing = await loadAppointment(req.params.id);
  assertVisible(req, existing);
  const body = req.body || {};
  const sets = [];
  const params = [];

  const ownsAsCustomer = req.user.kind === 'customer' && existing.customer_id === req.user.sub;
  const staff = isManagement(req) || (req.user.role === 'barber' && existing.barber_id === req.user.barber_id);

  if (body.status !== undefined) {
    if (!staff) throw ApiError.forbidden('Only the salon can move a booking through its statuses.');
    if (STATUSES.indexOf(body.status) === -1) throw ApiError.badRequest(`"status" must be one of: ${STATUSES.join(', ')}.`);
    sets.push('status = ?');
    params.push(body.status);
    sets.push('cancelled_at = ?');
    params.push(body.status === 'cancelled' ? v.toMysqlDateTime(new Date()) : null);
  }

  if (body.notes !== undefined) {
    if (!staff && !ownsAsCustomer) throw ApiError.forbidden();
    sets.push('notes = ?');
    params.push(v.optionalString(body, 'notes', { max: 2000 }));
  }

  if (body.starts_at !== undefined) {
    if (!staff) throw ApiError.forbidden('Only the salon can move a booking to another time.');
    const startsAt = v.requireDate(body, 'starts_at');
    if (startsAt.getTime() <= Date.now()) throw ApiError.unprocessable('A booking has to start in the future.');
    const service = await one('SELECT duration_minutes FROM services WHERE id = ? LIMIT 1', [existing.service_id]);
    const startsSql = v.toMysqlDateTime(startsAt);
    const endsSql = v.toMysqlDateTime(new Date(startsAt.getTime() + service.duration_minutes * 60000));

    const clash = await one(
      `SELECT id FROM appointments
       WHERE barber_id = ? AND id <> ? AND status IN ('pending', 'confirmed', 'in_progress')
         AND starts_at < ? AND ends_at > ? LIMIT 1`,
      [existing.barber_id, existing.id, endsSql, startsSql]
    );
    if (clash) throw ApiError.conflict('That slot is already taken. Please choose another time.');

    sets.push('starts_at = ?');
    params.push(startsSql);
    sets.push('ends_at = ?');
    params.push(endsSql);
  }

  if (sets.length === 0) throw ApiError.badRequest('Send at least one field to update.');

  params.push(existing.id);
  await query(`UPDATE appointments SET ${sets.join(', ')} WHERE id = ?`, params);

  res.json({ ok: true, data: await loadAppointment(existing.id) });
}));

// DELETE cancels, which is what "remove a booking" means here: only a booking
// that has not started can be cancelled, so history stays intact. A manager can
// pass ?purge=1 to erase the row outright.
router.delete('/:id', requireAuth, asyncHandler(async (req, res) => {
  const existing = await loadAppointment(req.params.id);
  assertVisible(req, existing);

  if (v.boolValue(req.query.purge)) {
    if (!isManagement(req)) throw ApiError.forbidden('Only a manager can erase a booking.');
    await query('DELETE FROM appointments WHERE id = ?', [existing.id]);
    return res.json({ ok: true, data: { id: existing.id, deleted: true } });
  }

  if (OPEN_STATUSES.indexOf(existing.status) === -1) {
    throw ApiError.conflict(`A booking that is ${existing.status.replace('_', ' ')} cannot be cancelled.`);
  }
  if (existing.has_started) {
    throw ApiError.conflict('That booking has already started.');
  }

  await query("UPDATE appointments SET status = 'cancelled', cancelled_at = UTC_TIMESTAMP() WHERE id = ?", [existing.id]);

  res.json({ ok: true, data: await loadAppointment(existing.id) });
}));

module.exports = router;