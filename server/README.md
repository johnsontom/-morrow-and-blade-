# Morrow & Blade API (Node.js + Express)

The back-end half of the project, written in JavaScript on Node and Express. It
is a REST API over the **same MariaDB database** the PHP site uses, so both
front ends read and write one set of tables with no duplication.

## Running it

Inside the Compose stack it starts automatically as the `api` service:

```
docker compose up -d --build
```

| What | Where |
| --- | --- |
| API root (route index) | <http://localhost:8087/api> |
| Health check | <http://localhost:8087/api/health> |
| Browser console for the API | <http://localhost:8087/> |

Standalone, pointed at any MariaDB that holds the schema:

```
cd server
npm install
DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=salon DB_PASS=salon npm start
```

## Environment

| Variable | Default | Notes |
| --- | --- | --- |
| `API_PORT` | `8087` | Port the API listens on |
| `DB_HOST` / `DB_PORT` | `127.0.0.1` / `3306` | `db` / `3306` inside Compose |
| `DB_NAME` / `DB_USER` / `DB_PASS` | `morrow_and_blade_php` / `salon` / `salon` | Shared with the PHP app |
| `JWT_SECRET` | development default | Set this in production |
| `JWT_TTL` | `2h` | Token lifetime |
| `CORS_ORIGINS` | unset, meaning any origin | Comma-separated allow list |

The service reads the project's `.env` if there is one, so anything set for
Compose is picked up without being configured twice.

## How it is laid out

```
src/index.js       app wiring, static hosting, error handling
src/config.js      environment, loaded from the same .env Compose reads
src/db.js          one mysql2 pool, plus query/one/transaction helpers
src/http.js        ApiError, the async wrapper, MySQL error translation
src/validate.js    request validation and pagination helpers
src/auth.js        JWT issuing, bcrypt checks against PHP password_hash()
src/routes/        one router per resource
public/            vanilla-JS console that consumes the API
scripts/smoke.mjs  end-to-end check that needs a live stack
test/              node:test unit and routing tests
```

## Endpoints

Everything is JSON. Send `Authorization: Bearer <token>` for the routes that
need a sign-in.

### Auth

| Method | Path | Who |
| --- | --- | --- |
| `POST` | `/api/auth/register` | anyone, creates a customer account |
| `POST` | `/api/auth/login` | anyone, customer sign-in |
| `POST` | `/api/auth/staff/login` | anyone, barber/manager/owner sign-in |
| `GET` | `/api/auth/me` | signed in |
| `PATCH` | `/api/auth/me` | signed-in customer, update own details |
| `POST` | `/api/auth/password` | signed in, change own password |

### Catalogue

| Method | Path | Who |
| --- | --- | --- |
| `GET` | `/api/services`, `/api/services/:idOrSlug` | public |
| `POST` `PATCH` `DELETE` | `/api/services[/:id]` | manager or owner |
| `GET` | `/api/categories`, `/api/categories/:idOrSlug` | public |
| `GET` | `/api/barbers`, `/api/barbers/:idOrSlug` | public |
| `POST` `PATCH` `DELETE` | `/api/barbers[/:id]` | manager or owner |
| `GET` | `/api/branches`, `/api/branches/nearest?lat&lng` | public |
| `POST` `PATCH` `DELETE` | `/api/branches[/:id]` | manager or owner |

### Bookings, chat and reports

| Method | Path | Who |
| --- | --- | --- |
| `GET` `POST` | `/api/appointments` | signed in |
| `GET` `PATCH` `DELETE` | `/api/appointments/:id` | the booking's owner, or staff |
| `GET` `POST` | `/api/conversations` | signed in |
| `GET` `PATCH` `DELETE` | `/api/conversations/:id` | a member of the thread, or a manager |
| `GET` `POST` | `/api/conversations/:id/messages` | a member of the thread |
| `GET` | `/api/reports/summary` | manager or owner |

Deleting is domain-correct rather than literal: `DELETE /api/appointments/:id`
cancels a booking, `DELETE /api/conversations/:id` closes a thread, and
`DELETE /api/services/:id` hides a treatment. Each accepts `?purge=1` from a
manager to erase the row outright.

## Notes

- Passwords are checked with bcrypt against the `password_hash` values PHP
  wrote, so one account works on both front ends.
- Booking writes go through the same overlap rules as the PHP side: a
  transaction re-checks the slot, and the `appointments_no_overlap_*` triggers
  remain the final say. A clash comes back as `409` with a message worth
  showing to a person.
- Timestamps are stored naive-UTC, matching the `UTC_TIMESTAMP()` usage in PHP.
- `GET /api/branches/nearest` uses a haversine expression so the database does
  the distance work.
- Errors are translated once, in `src/http.js`: a duplicate key becomes `409`,
  a missing referenced row becomes `422`, and `SLOT_UNAVAILABLE` becomes a
  readable `409` rather than a stack trace.

## Tests

```
npm test                     # unit and routing tests, no database needed
node scripts/smoke.mjs       # end-to-end CRUD, needs the stack running
```