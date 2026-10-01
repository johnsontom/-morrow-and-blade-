'use strict';

const express = require('express');
const { asyncHandler } = require('../http');
const { query, one } = require('../db');
const { requireAuth, requireManagement } = require('../auth');
const v = require('../validate');

const router = express.Router();

function defaultRange(req) {
  const to = req.query.to ? v.requireDate(req.query, 'to') : new Date();
  const from = req.query.from
    ? v.requireDate(req.query, 'from')
    : new Date(to.getTime() - 30 * 24 * 60 * 60 * 1000);
  return { from: v.toMysqlDateTime(from), to: v.toMysqlDateTime(to) };
}

router.get('/summary', requireAuth, requireManagement, asyncHandler(async (req, res) => {
  const range = defaultRange(req);

  const [byStatus, money, upcoming, topServices, byBarber, daily] = await Promise.all([
    query(
      `SELECT status, COUNT(*) AS bookings, COALESCE(SUM(duration_minutes), 0) AS booked_minutes
       FROM appointments a
       JOIN services s ON s.id = a.service_id
       WHERE a.starts_at BETWEEN ? AND ?
       GROUP BY status
       ORDER BY bookings DESC`,
      [range.from, range.to]
    ),
    one(
      `SELECT COALESCE(SUM(price_pence_snapshot), 0) AS revenue_pence
       FROM appointments
       WHERE status = 'completed' AND starts_at BETWEEN ? AND ?`,
      [range.from, range.to]
    ),
    one(
      `SELECT COUNT(*) AS upcoming
       FROM appointments
       WHERE status IN ('pending', 'confirmed') AND starts_at >= UTC_TIMESTAMP()`
    ),
    query(
      `SELECT s.id, s.name, COUNT(*) AS bookings, COALESCE(SUM(a.price_pence_snapshot), 0) AS revenue_pence
       FROM appointments a
       JOIN services s ON s.id = a.service_id
       WHERE a.starts_at BETWEEN ? AND ? AND a.status <> 'cancelled'
       GROUP BY s.id, s.name
       ORDER BY bookings DESC
       LIMIT 10`,
      [range.from, range.to]
    ),
    query(
      `SELECT b.id, b.name, b.slug, COUNT(*) AS bookings,
              COALESCE(SUM(CASE WHEN a.status = 'completed' THEN a.price_pence_snapshot ELSE 0 END), 0) AS revenue_pence
       FROM appointments a
       JOIN barbers b ON b.id = a.barber_id
       WHERE a.starts_at BETWEEN ? AND ?
       GROUP BY b.id, b.name, b.slug
       ORDER BY bookings DESC`,
      [range.from, range.to]
    ),
    query(
      `SELECT DATE(starts_at) AS day,
              COUNT(*) AS bookings,
              COALESCE(SUM(CASE WHEN status = 'completed' THEN price_pence_snapshot ELSE 0 END), 0) AS revenue_pence
       FROM appointments
       WHERE starts_at BETWEEN ? AND ?
       GROUP BY DATE(starts_at)
       ORDER BY day ASC`,
      [range.from, range.to]
    ),
  ]);

  const totals = byStatus.reduce((sum, row) => sum + Number(row.bookings), 0);

  res.json({
    ok: true,
    data: {
      range,
      totals: { bookings: totals, by_status: byStatus },
      revenue_pence: money.revenue_pence,
      upcoming,
      top_services: topServices,
      by_barber: byBarber,
      daily,
    },
  });
}));

module.exports = router;