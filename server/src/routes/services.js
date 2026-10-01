'use strict';

const express = require('express');
const crypto = require('node:crypto');
const { ApiError, asyncHandler } = require('../http');
const { query, one } = require('../db');
const { requireManagement } = require('../auth');
const v = require('../validate');

const router = express.Router();

const SELECT = `SELECT s.*, c.slug AS category_slug, c.name AS category_name
  FROM services s
  LEFT JOIN service_categories c ON c.id = s.category_id`;

async function loadService(idOrSlug) {
  const row = v.isUuid(idOrSlug)
    ? await one(`${SELECT} WHERE s.id = ? LIMIT 1`, [idOrSlug])
    : await one(`${SELECT} WHERE s.slug = ? LIMIT 1`, [idOrSlug]);
  if (!row) throw ApiError.notFound('That service does not exist.');
  return row;
}

async function assertCategory(categoryId) {
  const row = await one('SELECT id FROM service_categories WHERE id = ? LIMIT 1', [categoryId]);
  if (!row) throw ApiError.unprocessable('That service category does not exist.');
}

router.get('/', asyncHandler(async (req, res) => {
  const { limit, offset } = v.pagination(req.query, { maxLimit: 200 });
  const where = [];
  const params = [];

  if (req.query.active !== 'all') {
    where.push('s.active = ?');
    params.push(v.boolValue(req.query.active, true) ? 1 : 0);
  }
  if (req.query.featured !== undefined) {
    where.push('s.featured = ?');
    params.push(v.boolValue(req.query.featured) ? 1 : 0);
  }
  if (req.query.category) {
    where.push('c.slug = ?');
    params.push(String(req.query.category));
  }
  if (req.query.q) {
    const like = `%${String(req.query.q)}%`;
    where.push('(s.name LIKE ? OR s.description LIKE ?)');
    params.push(like, like);
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const rows = await query(
    `${SELECT} ${whereSql} ORDER BY s.display_order, s.name LIMIT ${limit} OFFSET ${offset}`,
    params
  );
  const total = await one(
    `SELECT COUNT(*) AS total FROM services s LEFT JOIN service_categories c ON c.id = s.category_id ${whereSql}`,
    params
  );

  res.json({ ok: true, data: rows, meta: { count: rows.length, total: total.total, limit, offset } });
}));

router.get('/:idOrSlug', asyncHandler(async (req, res) => {
  res.json({ ok: true, data: await loadService(req.params.idOrSlug) });
}));

router.post('/', requireManagement, asyncHandler(async (req, res) => {
  const name = v.requireString(req.body, 'name', { max: 150 });
  const slug = v.requireSlug(req.body, 'slug', { fallback: v.slugify(name) });
  const categoryId = req.body && req.body.category_id ? v.requireUuid(req.body, 'category_id') : null;
  if (categoryId) await assertCategory(categoryId);

  const id = crypto.randomUUID();
  await query(
    `INSERT INTO services (id, slug, category_id, name, description, duration_minutes, price_pence, featured, active, display_order)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)`,
    [
      id,
      slug,
      categoryId,
      name,
      v.optionalString(req.body, 'description', { max: 1000 }),
      v.requireInt(req.body, 'duration_minutes', { min: 10, max: 240 }),
      v.requireInt(req.body, 'price_pence', { min: 1 }),
      v.requireBool(req.body, 'featured') ? 1 : 0,
      v.requireBool(req.body, 'active', { fallback: true }) ? 1 : 0,
      v.optionalInt(req.body, 'display_order', { min: 0, fallback: 0 }),
    ]
  );

  res.status(201).json({ ok: true, data: await loadService(id) });
}));

router.patch('/:id', requireManagement, asyncHandler(async (req, res) => {
  const existing = await loadService(req.params.id);
  const sets = [];
  const params = [];
  const body = req.body || {};

  if (body.name !== undefined) { sets.push('name = ?'); params.push(v.requireString(body, 'name', { max: 150 })); }
  if (body.slug !== undefined) { sets.push('slug = ?'); params.push(v.requireSlug(body, 'slug')); }
  if (body.description !== undefined) { sets.push('description = ?'); params.push(v.optionalString(body, 'description', { max: 1000 })); }
  if (body.duration_minutes !== undefined) { sets.push('duration_minutes = ?'); params.push(v.requireInt(body, 'duration_minutes', { min: 10, max: 240 })); }
  if (body.price_pence !== undefined) { sets.push('price_pence = ?'); params.push(v.requireInt(body, 'price_pence', { min: 1 })); }
  if (body.featured !== undefined) { sets.push('featured = ?'); params.push(v.requireBool(body, 'featured') ? 1 : 0); }
  if (body.active !== undefined) { sets.push('active = ?'); params.push(v.requireBool(body, 'active') ? 1 : 0); }
  if (body.display_order !== undefined) { sets.push('display_order = ?'); params.push(v.requireInt(body, 'display_order', { min: 0 })); }
  if (body.category_id !== undefined) {
    const categoryId = body.category_id === null || body.category_id === '' ? null : v.requireUuid(body, 'category_id');
    if (categoryId) await assertCategory(categoryId);
    sets.push('category_id = ?');
    params.push(categoryId);
  }

  if (sets.length === 0) throw ApiError.badRequest('Send at least one field to update.');

  params.push(existing.id);
  await query(`UPDATE services SET ${sets.join(', ')} WHERE id = ?`, params);

  res.json({ ok: true, data: await loadService(existing.id) });
}));

// Off by default rather than deleted, so historic bookings keep their link to
// the treatment. ?purge=1 erases the row for a genuine hard delete.
router.delete('/:id', requireManagement, asyncHandler(async (req, res) => {
  const existing = await loadService(req.params.id);

  if (v.boolValue(req.query.purge)) {
    await query('DELETE FROM services WHERE id = ?', [existing.id]);
    return res.json({ ok: true, data: { id: existing.id, deleted: true } });
  }

  await query('UPDATE services SET active = 0 WHERE id = ?', [existing.id]);
  return res.json({ ok: true, data: { id: existing.id, active: false, softDeleted: true } });
}));

module.exports = router;