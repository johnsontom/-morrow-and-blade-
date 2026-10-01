'use strict';

const path = require('node:path');
const express = require('express');
const cors = require('cors');
const config = require('./config');
const { notFound, errorHandler } = require('./http');
const { optionalAuth } = require('./auth');
const { close } = require('./db');

const app = express();

app.disable('x-powered-by');
app.use(express.json({ limit: '1mb' }));

// The API carries a bearer token rather than a cookie, so there is nothing to
// protect with credentials: true. An empty allow list means "any origin",
// which suits a marker running the PHP site on 8085 and the API on 8087.
app.use(cors(config.cors.origins.length ? { origin: config.cors.origins } : { origin: true }));

// Decode a bearer token when one is present, so the role guards on the
// catalogue routes have a req.user to look at. Routes that must have a token
// still add requireAuth of their own.
app.use(optionalAuth);

// The vanilla-JS console in public/ is served from here.
app.use(express.static(path.join(__dirname, '..', 'public')));

// A small index so the root of the service is self-describing.
app.get('/api', (req, res) => {
  res.json({
    ok: true,
    service: 'morrow-and-blade-api',
    version: '1.0.0',
    docs: 'https://github.com/ - see server/README.md',
    endpoints: {
      health: '/api/health',
      auth: ['POST /api/auth/register', 'POST /api/auth/login', 'POST /api/auth/staff/login', 'GET /api/auth/me'],
      services: ['GET /api/services', 'POST /api/services', 'PATCH /api/services/:id', 'DELETE /api/services/:id'],
      categories: ['GET /api/categories', 'GET /api/categories/:idOrSlug'],
      barbers: ['GET /api/barbers', 'GET /api/barbers/:idOrSlug', 'POST /api/barbers', 'PATCH /api/barbers/:id', 'DELETE /api/barbers/:id'],
      branches: ['GET /api/branches', 'GET /api/branches/nearest?lat&lng', 'POST /api/branches', 'PATCH /api/branches/:id', 'DELETE /api/branches/:id'],
      appointments: ['GET /api/appointments', 'POST /api/appointments', 'GET /api/appointments/:id', 'PATCH /api/appointments/:id', 'DELETE /api/appointments/:id'],
      conversations: ['GET /api/conversations', 'POST /api/conversations', 'GET /api/conversations/:id/messages', 'POST /api/conversations/:id/messages'],
      reports: ['GET /api/reports/summary'],
    },
  });
});

app.use('/api/health', require('./routes/health'));
app.use('/api/auth', require('./routes/auth'));
app.use('/api/services', require('./routes/services'));
app.use('/api/categories', require('./routes/categories'));
app.use('/api/barbers', require('./routes/barbers'));
app.use('/api/branches', require('./routes/branches'));
app.use('/api/appointments', require('./routes/appointments'));
app.use('/api/conversations', require('./routes/conversations'));
app.use('/api/reports', require('./routes/reports'));

app.use(notFound);
app.use(errorHandler);

// Only listen when started directly, so tests can import the app freely.
if (require.main === module) {
  const server = app.listen(config.port, () => {
    console.log(`[api] Morrow & Blade API listening on http://localhost:${config.port}`);
    console.log(`[api] Database ${config.db.user}@${config.db.host}:${config.db.port}/${config.db.database}`);
  });

  let shuttingDown = false;
  const shutdown = (signal) => {
    if (shuttingDown) return;
    shuttingDown = true;
    console.log(`[api] ${signal} received, shutting down.`);
    server.close(async () => {
      await close();
      process.exit(0);
    });
  };

  ['SIGTERM', 'SIGINT'].forEach((signal) => process.on(signal, () => shutdown(signal)));
}

module.exports = app;