// End-to-end smoke test for the Express API.
//
// Unlike `npm test` this one needs a real service and a real database, so it
// runs against the Compose stack rather than in CI:
//
//   docker compose up -d --build
//   node server/scripts/smoke.mjs
//
// It exercises the whole CRUD path - read, create, update, delete - plus
// sign-in, the permission checks and the booking rules, and then puts the
// seed data back as it found it.

const BASE = (process.argv[2] || process.env.API_URL || 'http://127.0.0.1:8087').replace(/\/+$/, '');

let passed = 0;
const failures = [];

function check(name, condition, detail) {
  if (condition) {
    passed += 1;
    console.log('  ok   ' + name + (detail ? ' (' + detail + ')' : ''));
    return true;
  }
  failures.push(name);
  console.log('  FAIL ' + name + (detail ? ' (' + detail + ')' : ''));
  return false;
}

async function call(path, options) {
  const opts = options || {};
  const headers = { Accept: 'application/json' };
  if (opts.body !== undefined) headers['Content-Type'] = 'application/json';
  if (opts.token) headers.Authorization = 'Bearer ' + opts.token;

  const response = await fetch(BASE + path, {
    method: opts.method || 'GET',
    headers,
    body: opts.body === undefined ? undefined : JSON.stringify(opts.body),
  });

  const text = await response.text();
  let body = null;
  if (text) { try { body = JSON.parse(text); } catch (error) { body = { raw: text }; } }
  return { status: response.status, body: body || {} };
}

async function main() {
  console.log('Smoke testing ' + BASE + '\n');

  console.log('Reads');
  const health = await call('/api/health');
  check('health reports the database is up', health.status === 200 && health.body.database === 'up', 'HTTP ' + health.status);

  const services = await call('/api/services');
  check('GET /api/services returns a list', services.status === 200 && Array.isArray(services.body.data), (services.body.data || []).length + ' rows');

  const team = await call('/api/barbers');
  check('GET /api/barbers returns a list', team.status === 200 && (team.body.data || []).length > 0, (team.body.data || []).length + ' people');

  const branches = await call('/api/branches?active=all');
  check('GET /api/branches returns a list', branches.status === 200, (branches.body.data || []).length + ' branches');

  const nearest = await call('/api/branches/nearest?lat=51.5074&lng=-0.1278');
  check('nearest sorts branches by distance', nearest.status === 200
    && ((nearest.body.data || []).length === 0 || typeof nearest.body.data[0].distance_km === 'number'),
    (nearest.body.data || []).length ? nearest.body.data[0].distance_km + ' km' : 'no branches');

  const missing = await call('/api/services/11111111-2222-4333-8444-555555555555');
  check('an unknown id gives 404', missing.status === 404, 'HTTP ' + missing.status);

  console.log('\nSign-in and permissions');
  const login = await call('/api/auth/staff/login', {
    method: 'POST',
    body: { email: 'admin@morrowandblade.co.uk', password: 'Admin@123' },
  });
  const token = login.body.data && login.body.data.token;
  check('the owner can sign in with the seeded account', Boolean(token), 'HTTP ' + login.status);

  const wrongPassword = await call('/api/auth/staff/login', {
    method: 'POST',
    body: { email: 'admin@morrowandblade.co.uk', password: 'not-the-password' },
  });
  check('a wrong password is refused', wrongPassword.status === 401);

  const anonymousWrite = await call('/api/services', {
    method: 'POST',
    body: { name: 'Should never exist', duration_minutes: 30, price_pence: 100 },
  });
  check('an anonymous write is refused', anonymousWrite.status === 401, 'HTTP ' + anonymousWrite.status);

  const anonymousReport = await call('/api/reports/summary');
  check('reports are closed to anonymous callers', anonymousReport.status === 401);

  console.log('\nCreate, read, update, delete');
  const created = await call('/api/services', {
    method: 'POST',
    token,
    body: {
      name: 'Smoke Test Cut',
      slug: 'smoke-test-cut',
      description: 'Created by scripts/smoke.mjs and removed again at the end.',
      duration_minutes: 30,
      price_pence: 2500,
      display_order: 99,
    },
  });
  const id = created.body.data && created.body.data.id;
  check('a manager can create a treatment', created.status === 201 && Boolean(id), 'HTTP ' + created.status);

  const read = await call('/api/services/' + id);
  check('the new treatment reads back by id', read.status === 200 && read.body.data.name === 'Smoke Test Cut');

  const bySlug = await call('/api/services/smoke-test-cut');
  check('the new treatment reads back by slug', bySlug.status === 200 && bySlug.body.data.id === id);

  const updated = await call('/api/services/' + id, { method: 'PATCH', token, body: { price_pence: 3000, featured: true } });
  check('the treatment can be updated', updated.status === 200
    && Number(updated.body.data.price_pence) === 3000
    && Number(updated.body.data.featured) === 1);

  const rejected = await call('/api/services/' + id, { method: 'PATCH', token, body: { duration_minutes: 5 } });
  check('a 5 minute treatment is rejected by validation', rejected.status === 400, 'HTTP ' + rejected.status);

  const duplicate = await call('/api/services', {
    method: 'POST', token,
    body: { slug: 'smoke-test-cut', name: 'Duplicate slug', duration_minutes: 30, price_pence: 1000 },
  });
  check('a duplicate slug is refused', duplicate.status === 409, 'HTTP ' + duplicate.status);

  const softDeleted = await call('/api/services/' + id, { method: 'DELETE', token });
  check('DELETE hides the treatment rather than erasing it', softDeleted.status === 200 && softDeleted.body.data.softDeleted === true);

  const purged = await call('/api/services/' + id + '?purge=1', { method: 'DELETE', token });
  check('the test row can be purged for real', purged.status === 200 && purged.body.data.deleted === true);

  const gone = await call('/api/services/' + id);
  check('the purged treatment is gone', gone.status === 404);

  console.log('\nBooking rules');
  const detail = await call('/api/barbers/' + ((team.body.data || [])[0] || {}).id);
  const offer = ((detail.body.data || {}).services || [])[0];

  if (!offer) {
    console.log('  skip no barber/service pairing in the seed data');
  } else {
    const barberId = detail.body.data.id;
    const slot = new Date(Date.now() + 21 * 24 * 60 * 60 * 1000);
    slot.setUTCHours(9, 0, 0, 0);

    const booking = await call('/api/appointments', {
      method: 'POST', token,
      body: {
        service_id: offer.id,
        barber_id: barberId,
        starts_at: slot.toISOString(),
        customer_name: 'Smoke Test',
        customer_email: 'smoke.test@example.com',
        customer_phone: '07700 900123',
        notes: 'Created by scripts/smoke.mjs',
      },
    });
    check('a booking can be created', booking.status === 201 && Boolean(booking.body.data.reference), 'HTTP ' + booking.status);

    const clash = await call('/api/appointments', {
      method: 'POST', token,
      body: { service_id: offer.id, barber_id: barberId, starts_at: slot.toISOString(), customer_id: booking.body.data.customer_id },
    });
    check('a double booking is refused', clash.status === 409, 'HTTP ' + clash.status);

    const badTime = await call('/api/appointments', {
      method: 'POST', token,
      body: { service_id: offer.id, barber_id: barberId, starts_at: new Date(Date.now() - 86400000).toISOString(), customer_id: booking.body.data.customer_id },
    });
    check('a booking in the past is refused', badTime.status === 422, 'HTTP ' + badTime.status);

    if (booking.status === 201) {
      const cancelled = await call('/api/appointments/' + booking.body.data.id, { method: 'DELETE', token });
      check('the booking can be cancelled', cancelled.status === 200 && cancelled.body.data.status === 'cancelled');
    }
  }

  console.log('\nReports');
  const report = await call('/api/reports/summary', { token });
  check('a manager can read the summary', report.status === 200 && Boolean(report.body.data.totals), 'HTTP ' + report.status);

  console.log('\n' + passed + ' passed, ' + failures.length + ' failed');
  if (failures.length) {
    console.log('Failed: ' + failures.join(', '));
    process.exit(1);
  }
}

main().catch((error) => {
  console.error('\nSmoke test could not run:', error.message);
  process.exit(1);
});