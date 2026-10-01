'use strict';

const express = require('express');
const crypto = require('node:crypto');
const { ApiError, asyncHandler } = require('../http');
const { query, one } = require('../db');
const { requireManagement } = require('../auth');
const v = require('../validate');

const router = express.Router();

const STAFF_KINDS = ['barber', 'beautician', 'nail_technician', 'therapist'];

const SELECT = `SELECT b.*, s.name AS salon_name, s.slug AS salon_slug
  FROM barbers b
  LEFT JOIN salons s ON s.id = b.salon_id`;

/** rating is DECIMAL(2,1), so it needs its own reader rather than requireInt. */
function requireRating(body, field, fallback) {
  const raw = body ? body[field] : undefined;
  if (raw === undefined || raw === null || raw === '') return fallback;
  const value = Number(raw);
  if (Number.isFinite(value) === false) throw ApiError.badRequest(`"${field}" must be a number.`);
  if (value < 0 || value > 5) throw ApiError.badRequest(`"${field}" must be between 0 and 5.`);
  return Math.round(value * 10) / 10;
}


async function loadBarber(idOrSlug) {
  const row = v.isUuid(idOrSlug)
    ? await one(`${SELECT} WHERE b.id = ? LIMIT 1`, [idOrSlug])
    : await one(`${SELECT} WHERE b.slug = ? LIMIT 1`, [idOrSlug]);
  if (!row) throw ApiError.notFound('That team member does not exist.');
  return row;
}

async function assertSalon(salonId) {
  const row = await one('SELECT id FROM salons WHERE id = ? LIMIT 1', [salonId]);
  if (!row) throw ApiError.unprocessable('That branch does not exist.');
}

router.get('/', asyncHandler(async (req, res) => {
  const { limit, offset } = v.pagination(req.query, { maxLimit: 200 });
  const where = [];
  const params = [];

  if (req.query.active !== 'all') {
    where.push('b.active = ?');
    params.push(v.boolValue(req.query.active, true) ? 1 : 0);
  }
  if (req.query.kind) {
    where.push('b.staff_kind = ?');
    params.push(v.requireEnum(req.query, 'kind', STAFF_KINDS));
  }
  if (req.query.salon) {
    where.push('s.slug = ?');
    params.push(String(req.query.salon));
  }
  if (req.query.q) {
    const like = `%${String(req.query.q)}%`;
    where.push('(b.name LIKE ? OR b.role LIKE ? OR b.specialties LIKE ?)');
    params.push(like, like, like);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const rows = await query(
    `${SELECT} ${whereSql} ORDER BY b.display_order, b.name LIMIT ${limit} OFFSET ${offset}`,
    params
  );
  const total = await one(
    `SELECT COUNT(*) AS total FROM barbers b LEFT JOIN salons s ON s.id = b.salon_id ${whereSql}`,
    params
  );

  res.json({ ok: true, data: rows, meta: { count: rows.length, total: total.total, limit, offset } });
}));

// Detail view adds what the profile page needs: the treatments they offer and
// their weekly rota.
router.get('/:idOrSlug', asyncHandler(async (req, res) => {
  const barber = await loadBarber(req.params.idOrSlug);
  const [services, hours] = await Promise.all([
    query(
      `SELECT s.id, s.slug, s.name, s.duration_minutes, s.price_pence
       FROM services s
       JOIN barber_services bs ON bs.service_id = s.id
       WHERE bs.barber_id = ? AND s.active = 1
       ORDER BY s.display_order, s.name`,
      [barber.id]
    ),
    query(
      'SELECT weekday, start_time, end_time, is_working FROM working_hours WHERE barber_id = ? ORDER BY weekday',
      [barber.id]
    ),
  ]);

  res.json({ ok: true, data: Object.assign({}, barber, { services, working_hours: hours }) });
}));

router.post('/', requireManagement, asyncHandler(async (req, res) => {
  const name = v.requireString(req.body, 'name', { max: 150 });
  const slug = v.requireSlug(req.body, 'slug', { fallback: v.slugify(name) });
  const salonId = req.body && req.body.salon_id ? v.requireUuid(req.body, 'salon_id') : null;
  if (salonId) await assertSalon(salonId);

  const id = crypto.randomUUID();
  await query(
    `INSERT INTO barbers (id, slug, name, role, staff_kind, salon_id, bio, photo_url, specialties,
       years_experience, rating, review_count, active, display_order)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      id,
      slug,
      name,
      v.optionalString(req.body, 'role', { max: 120, fallback: 'Barber' }),
      v.requireEnum(req.body, 'staff_kind', STAFF_KINDS, { fallback: 'barber' }),
      salonId,
      v.optionalString(req.body, 'bio', { max: 2000 }),
      v.optionalString(req.body, 'photo_url', { max: 500 }),
      JSON.stringify(v.parseJsonArray(req.body && req.body.specialties, [])),
      v.optionalInt(req.body, 'years_experience', { min: 0, fallback: 0 }),
      requireRating(req.body, 'rating', 5.0),
      v.optionalInt(req.body, 'review_count', { min: 0, fallback: 0 }),
      v.requireBool(req.body, 'active', { fallback: true }) ? 1 : 0,
      v.optionalInt(req.body, 'display_order', { min: 0, fallback: 0 }),
    ]
  );

  res.status(201).json({ ok: true, data: await loadBarber(id) });
}));

router.patch('/:id', requireManagement, asyncHandler(async (req, res) => {
  const existing = await loadBarber(req.params.id);
  const body = req.body || {};
  const sets = [];
  const params = [];

  if (body.name !== undefined) { sets.push('name = ?'); params.push(v.requireString(body, 'name', { max: 150 })); }
  if (body.slug !== undefined) { sets.push('slug = ?'); params.push(v.requireSlug(body, 'slug')); }
  if (body.role !== undefined) { sets.push('role = ?'); params.push(v.optionalString(body, 'role', { max: 120, fallback: 'Barber' })); }
  if (body.staff_kind !== undefined) { sets.push('staff_kind = ?'); params.push(v.requireEnum(body, 'staff_kind', STAFF_KINDS)); }
  if (body.bio !== undefined) { sets.push('bio = ?'); params.push(v.optionalString(body, 'bio', { max: 2000 })); }
  if (body.photo_url !== undefined) { sets.push('photo_url = ?'); params.push(v.optionalString(body, 'photo_url', { max: 500 })); }
  if (body.specialties !== undefined) { sets.push('specialties = ?'); params.push(JSON.stringify(v.parseJsonArray(body.specialties, []))); }
  if (body.years_experience !== undefined) { sets.push('years_experience = ?'); params.push(v.requireInt(body, 'years_experience', { min: 0 })); }
  if (body.rating !== undefined) { sets.push('rating = ?'); params.push(requireRating(body, 'rating', existing.rating)); }
  if (body.review_count !== undefined) { sets.push('review_count = ?'); params.push(v.requireInt(body, 'review_count', { min: 0 })); }
  if (body.active !== undefined) { sets.push('active = ?'); params.push(v.requireBool(body, 'active') ? 1 : 0); }
  if (body.display_order !== undefined) { sets.push('display_order = ?'); params.push(v.requireInt(body, 'display_order', { min: 0 })); }
  if (body.salon_id !== undefined) {
    const salonId = body.salon_id === null || body.salon_id === '' ? null : v.requireUuid(body, 'salon_id');
    if (salonId) await assertSalon(salonId);
    sets.push('salon_id = ?');
    params.push(salonId);
  }

  if (sets.length === 0) throw ApiError.badRequest('Send at least one field to update.');

  params.push(existing.id);
  await query(`UPDATE barbers SET ${sets.join(', ')} WHERE id = ?`, params);

  res.json({ ok: true, data: await loadBarber(existing.id) });
}));

// Same soft/hard split as services: leave the row so historic appointments and
// messages survive, unless ?purge=1 is passed.
router.delete('/:id', requireManagement, asyncHandler(async (req, res) => {
  const existing = await loadBarber(req.params.id);

  if (v.boolValue(req.query.purge)) {
    await query('DELETE FROM barbers WHERE id = ?', [existing.id]);
    return res.json({ ok: true, data: { id: existing.id, deleted: true } });
  }

  await query('UPDATE barbers SET active = 0 WHERE id = ?', [existing.id]);
  return res.json({ ok: true, data: { id: existing.id, active: false, softDeleted: true } });
}));

module.exports = router;