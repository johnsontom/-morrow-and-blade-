'use strict';

const express = require('express');
const { asyncHandler } = require('../http');
const { ping } = require('../db');

const router = express.Router();

// Compose uses this for the container health check, so it touches the database
// rather than just proving the process is listening.
router.get('/', asyncHandler(async (req, res) => {
  await ping();
  res.json({
    ok: true,
    service: 'morrow-and-blade-api',
    database: 'up',
    time: new Date().toISOString(),
  });
}));

module.exports = router;