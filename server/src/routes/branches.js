'use strict';

const express = require('express');
const crypto = require('node:crypto');
const { ApiError, asyncHandler } = require('../http');
const { query, one } = require('../db');
const { requireManagement } = require('../auth');
const v = require('../validate');

const router = express.Router();

function requireCoordinate(body, field, min, max) {
  const raw = body ? body[field] : undefined;
  const value = Number(raw);
  if (raw === undefined || raw === null || raw === '' || Number.isFinite(value) === false) {
    throw ApiError.badRequest(`"${field}" must be a number.`);
  }
  if (value < min || value > max) throw ApiError.badRequest(`"${field}" must be between ${min} and ${max}.`);
  return value;
}

async function loadBranch(idOrSlug) {
  const row = v.isUuid(idOrSlug)
    ? await one('SELECT * FROM salons WHERE id = ? LIMIT 1', [idOrSlug])
    : await one('SELECT * FROM salons WHERE slug = ? LIMIT 1', [idOrSlug]);
  if (!row) throw ApiError.notFound('That branch does not exist.');
  return row;
}

/** Let the database do the distance maths; haversine, in kilometres. */
const DISTANCE = `ROUND(6371 * ACOS(LEAST(1, GREATEST(-1,
    COS(RADIANS(?)) * COS(RADIANS(s.latitude)) * COS(RADIANS(s.longitude) - RADIANS(?))
    + SIN(RADIANS(?)) * SIN(RADIANS(s.latitude))
  ))), 2) AS distance_km`;

// Declared before /:idOrSlug so the literal path is not read as a slug.
router.get('/nearest', asyncHandler(async (req, res) => {
  const lat = requireCoordinate(req.query, 'lat', -90, 90);
  const lng = requireCoordinate(req.query, 'lng', -180, 180);
  const { limit } = v.pagination(req.query, { maxLimit: 50, defaultLimit: 5 });

  const rows = await query(
    `SELECT s.*, ${DISTANCE}
     FROM salons s
     WHERE s.active = 1
     ORDER BY distance_km ASC
     LIMIT ${limit}`,
    [lat, lng, lat]
  );

  res.json({ ok: true, data: rows, meta: { count: rows.length, origin: { lat, lng } } });
}));

router.get('/', asyncHandler(async (req, res) => {
  const { limit, offset } = v.pagination(req.query, { maxLimit: 200 });
  const where = [];
  const params = [];

  if (req.query.active !== 'all') {
    where.push('s.active = ?');
    params.push(v.boolValue(req.query.active, true) ? 1 : 0);
  }
  if (req.query.city) {
    where.push('s.city = ?');
    params.push(String(req.query.city));
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const rows = await query(
    `SELECT s.* FROM salons s ${whereSql} ORDER BY s.display_order, s.name LIMIT ${limit} OFFSET ${offset}`,
    params
  );
  const total = await one(`SELECT COUNT(*) AS total FROM salons s ${whereSql}`, params);

  res.json({ ok: true, data: rows, meta: { count: rows.length, total: total.total, limit, offset } });
}));

router.get('/:idOrSlug', asyncHandler(async (req, res) => {
  const branch = await loadBranch(req.params.idOrSlug);
  const staff = await query(
    'SELECT id, slug, name, role, staff_kind, photo_url, rating FROM barbers WHERE salon_id = ? AND active = 1 ORDER BY display_order, name',
    [branch.id]
  );
  res.json({ ok: true, data: Object.assign({}, branch, { team: staff }) });
}));

router.post('/', requireManagement, asyncHandler(async (req, res) => {
  const name = v.requireString(req.body, 'name', { max: 150 });
  const slug = v.requireSlug(req.body, 'slug', { fallback: v.slugify(name) });

  const id = crypto.randomUUID();
  await query(
    `INSERT INTO salons (id, slug, name, address_line_1, address_line_2, city, postcode, latitude, longitude,
       phone, email, opening_hours, is_primary, active, display_order)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      id,
      slug,
      name,
      v.requireString(req.body, 'address_line_1', { max: 255 }),
      v.optionalString(req.body, 'address_line_2', { max: 255 }),
      v.requireString(req.body, 'city', { max: 120 }),
      v.requireString(req.body, 'postcode', { max: 20 }),
      requireCoordinate(req.body, 'latitude', -90, 90),
      requireCoordinate(req.body, 'longitude', -180, 180),
      v.optionalString(req.body, 'phone', { max: 40 }),
      v.optionalString(req.body, 'email', { max: 254 }),
      JSON.stringify(req.body && req.body.opening_hours && typeof req.body.opening_hours === 'object'
        ? req.body.opening_hours
        : v.parseJsonArray(req.body && req.body.opening_hours, [])),
      v.requireBool(req.body, 'is_primary') ? 1 : 0,
      v.requireBool(req.body, 'active', { fallback: true }) ? 1 : 0,
      v.optionalInt(req.body, 'display_order', { min: 0, fallback: 0 }),
    ]
  );

  res.status(201).json({ ok: true, data: await loadBranch(id) });
}));

router.patch('/:id', requireManagement, asyncHandler(async (req, res) => {
  const existing = await loadBranch(req.params.id);
  const body = req.body || {};
  const sets = [];
  const params = [];

  const text = {
    name: 150,
    address_line_1: 255,
    address_line_2: 255,
    city: 120,
    postcode: 20,
    phone: 40,
    email: 254,
  };
  for (const field of Object.keys(text)) {
    if (body[field] === undefined) continue;
    const required = ['name', 'address_line_1', 'city', 'postcode'].indexOf(field) !== -1;
    sets.push(`${field} = ?`);
    params.push(required
      ? v.requireString(body, field, { max: text[field] })
      : v.optionalString(body, field, { max: text[field] }));
  }

  if (body.slug !== undefined) { sets.push('slug = ?'); params.push(v.requireSlug(body, 'slug')); }
  if (body.latitude !== undefined) { sets.push('latitude = ?'); params.push(requireCoordinate(body, 'latitude', -90, 90)); }
  if (body.longitude !== undefined) { sets.push('longitude = ?'); params.push(requireCoordinate(body, 'longitude', -180, 180)); }
  if (body.is_primary !== undefined) { sets.push('is_primary = ?'); params.push(v.requireBool(body, 'is_primary') ? 1 : 0); }
  if (body.active !== undefined) { sets.push('active = ?'); params.push(v.requireBool(body, 'active') ? 1 : 0); }
  if (body.display_order !== undefined) { sets.push('display_order = ?'); params.push(v.requireInt(body, 'display_order', { min: 0 })); }
  if (body.opening_hours !== undefined) {
    sets.push('opening_hours = ?');
    params.push(JSON.stringify(body.opening_hours && typeof body.opening_hours === 'object'
      ? body.opening_hours
      : v.parseJsonArray(body.opening_hours, [])));
  }

  if (sets.length === 0) throw ApiError.badRequest('Send at least one field to update.');

  params.push(existing.id);
  await query(`UPDATE salons SET ${sets.join(', ')} WHERE id = ?`, params);

  res.json({ ok: true, data: await loadBranch(existing.id) });
}));

router.delete('/:id', requireManagement, asyncHandler(async (req, res) => {
  const existing = await loadBranch(req.params.id);

  if (v.boolValue(req.query.purge)) {
    await query('DELETE FROM salons WHERE id = ?', [existing.id]);
    return res.json({ ok: true, data: { id: existing.id, deleted: true } });
  }

  await query('UPDATE salons SET active = 0 WHERE id = ?', [existing.id]);
  return res.json({ ok: true, data: { id: existing.id, active: false, softDeleted: true } });
}));

module.exports = router;