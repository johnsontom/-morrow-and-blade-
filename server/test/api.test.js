'use strict';

// Smoke tests. Everything here runs without a database, so `npm test` is safe
// to run anywhere; the routes that need MariaDB are exercised through the
// Compose stack instead.

const test = require('node:test');
const assert = require('node:assert/strict');
const bcrypt = require('bcryptjs');

const v = require('../src/validate');
const { ApiError } = require('../src/http');
const { verifyPassword, hashPassword, signToken, verifyToken } = require('../src/auth');
const { close } = require('../src/db');
const app = require('../src/index');

test.after(async () => {
  await close();
});

test('slugify turns a title into a slug', () => {
  assert.equal(v.slugify("Miller's Beard Trim"), 'miller-s-beard-trim');
  assert.equal(v.slugify('  Hot Towel Shave  '), 'hot-towel-shave');
  assert.equal(v.slugify('Skin Fade!!!'), 'skin-fade');
});

test('requireUuid rejects anything that is not a UUID', () => {
  assert.equal(v.requireUuid({ id: 'a3f1c2d4-1b2c-4d5e-8f90-123456789abc' }, 'id').length, 36);
  assert.throws(() => v.requireUuid({ id: 'not-a-uuid' }, 'id'), /must be a UUID/);
});

test('requireDate reads a naive timestamp as UTC, matching PHP', () => {
  const parsed = v.requireDate({ at: '2026-10-05 14:30:00' }, 'at');
  assert.equal(parsed.toISOString(), '2026-10-05T14:30:00.000Z');
  assert.equal(v.toMysqlDateTime(parsed), '2026-10-05 14:30:00');
  assert.throws(() => v.requireDate({ at: 'yesterday' }, 'at'), /must be a date and time/);
});

test('pagination clamps the page size', () => {
  assert.deepEqual(v.pagination({ limit: '10', offset: '20' }), { limit: 10, offset: 20 });
  assert.equal(v.pagination({ limit: '5000' }, { maxLimit: 200 }).limit, 200);
  assert.equal(v.pagination({ limit: '-3' }).limit, 1);
  assert.equal(v.pagination({}).offset, 0);
});

test('a PHP-style $2y bcrypt hash verifies', () => {
  // bcryptjs emits $2b; PHP writes $2y. Same algorithm, different label, so
  // swapping the prefix gives a genuine PHP-shaped digest to test against.
  const digest = hashPassword('Admin@123');
  const phpStyle = `$2y$${digest.slice(4)}`;

  assert.equal(verifyPassword('Admin@123', phpStyle), true);
  assert.equal(verifyPassword('wrong-password', phpStyle), false);
  assert.equal(verifyPassword('Admin@123', ''), false);
  assert.equal(verifyPassword('Admin@123', 'not-a-hash'), false);
  assert.equal(bcrypt.compareSync('Admin@123', phpStyle), true, 'bcryptjs itself accepts $2y');
});

test('tokens round-trip and carry the role', () => {
  const token = signToken({ sub: 'abc', kind: 'staff', role: 'manager' });
  const claims = verifyToken(token);
  assert.equal(claims.sub, 'abc');
  assert.equal(claims.role, 'manager');
  assert.equal(claims.iss, 'morrow-and-blade-api');
});

test('ApiError carries its status', () => {
  assert.equal(ApiError.notFound().status, 404);
  assert.equal(ApiError.conflict('taken').status, 409);
});

test('the service describes its own routes', async () => {
  await withServer(async (base) => {
    const response = await fetch(`${base}/api`);
    assert.equal(response.status, 200);
    const body = await response.json();
    assert.equal(body.ok, true);
    assert.ok(body.endpoints.appointments.length > 0);
  });
});

test('an unknown route answers with JSON, not a stack trace', async () => {
  await withServer(async (base) => {
    const response = await fetch(`${base}/api/does-not-exist`);
    assert.equal(response.status, 404);
    const body = await response.json();
    assert.equal(body.ok, false);
    assert.match(body.error, /No API route matches/);
  });
});

test('a protected route refuses an anonymous caller', async () => {
  await withServer(async (base) => {
    const response = await fetch(`${base}/api/appointments`);
    assert.equal(response.status, 401);
  });
});

async function withServer(run) {
  const server = app.listen(0);
  await new Promise((resolve) => server.once('listening', resolve));
  const { port } = server.address();
  try {
    await run(`http://127.0.0.1:${port}`);
  } finally {
    await new Promise((resolve) => server.close(resolve));
  }
}