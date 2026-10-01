'use strict';

const express = require('express');
const { ApiError, asyncHandler } = require('../http');
const { query, one } = require('../db');
const v = require('../validate');

const router = express.Router();

router.get('/', asyncHandler(async (req, res) => {
  const { limit, offset } = v.pagination(req.query, { maxLimit: 100 });
  const rows = await query(
    `SELECT c.*, (SELECT COUNT(*) FROM services s WHERE s.category_id = c.id AND s.active = 1) AS service_count
     FROM service_categories c
     ORDER BY c.display_order, c.name
     LIMIT ${limit} OFFSET ${offset}`
  );
  res.json({ ok: true, data: rows, meta: { count: rows.length, limit, offset } });
}));

router.get('/:idOrSlug', asyncHandler(async (req, res) => {
  const key = req.params.idOrSlug;
  const row = v.isUuid(key)
    ? await one('SELECT * FROM service_categories WHERE id = ? LIMIT 1', [key])
    : await one('SELECT * FROM service_categories WHERE slug = ? LIMIT 1', [key]);
  if (!row) throw ApiError.notFound('That category does not exist.');
  res.json({ ok: true, data: row });
}));

module.exports = router;