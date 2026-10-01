'use strict';

const fs = require('node:fs');
const path = require('node:path');

// Reuse the same .env the Compose stack reads. Node's own loader keeps this
// dependency-free; a missing or broken file just falls back to the defaults.
for (const candidate of [
  path.resolve(__dirname, '..', '..', '.env'),
  path.resolve(__dirname, '..', '.env'),
]) {
  try {
    if (fs.existsSync(candidate)) {
      process.loadEnvFile(candidate);
      break;
    }
  } catch {
    // Ignore it: the API still boots on the defaults below.
  }
}

const config = {
  port: Number(process.env.API_PORT || 8087),
  env: process.env.NODE_ENV || 'development',
  db: {
    // Compose sets DB_HOST=db; running on the host it is 127.0.0.1.
    host: process.env.DB_HOST || '127.0.0.1',
    port: Number(process.env.DB_PORT || 3306),
    user: process.env.DB_USER || 'salon',
    password: process.env.DB_PASS || 'salon',
    database: process.env.DB_NAME || 'morrow_and_blade_php',
    poolSize: Number(process.env.DB_POOL_SIZE || 10),
  },
  jwt: {
    secret: process.env.JWT_SECRET || 'dev-only-change-me',
    ttl: process.env.JWT_TTL || '2h',
  },
  cors: {
    origins: (process.env.CORS_ORIGINS || '')
      .split(',')
      .map((value) => value.trim())
      .filter(Boolean),
  },
};

if (config.env === 'production' && config.jwt.secret === 'dev-only-change-me') {
  console.warn('[config] JWT_SECRET is not set; falling back to the insecure development default.');
}

module.exports = config;