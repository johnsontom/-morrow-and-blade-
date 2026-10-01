# Morrow & Blade

A salon booking site with two back ends over one database:

- a **PHP 8 + Apache** site that renders the customer-facing pages, the barber
  portal and the owner's area;
- a **Node.js + Express REST API** that serves the same data as JSON, with
  JWT sign-in and its own browser console.

Both read and write the same MariaDB schema - the same tables, the same
triggers, the same seed data - so a booking made through the PHP site shows up
in the API and the other way round. The whole stack, database included, runs in
Docker, and nothing here touches XAMPP's ports: XAMPP's Apache keeps 80 and its
MariaDB keeps 3306, and the copy in `C:\xampp\htdocs` is never modified.

## What is here

**Public site (PHP)**
- `index.php` home, `services.php` treatment menu, `barbers.php` team, `barber.php` single profile
- `branches.php` - every branch on a map, with a "use my location" button that sorts them by distance
- `booking.php` -> `confirmation.php` - pick a treatment, a person and a slot; double bookings are refused
- `ai.php` - the assistant, answering questions about prices, availability, styles, hours and directions
- `contact.php` - the salon's own details

**Customer accounts**
- `register.php`, `login.php`, `account/logout.php`
- `account/` - overview, appointment history, details, inbox
- `account/chat.php` - a private thread with one barber

**Barber portal** (separate from the owner's area)
- `barber/login.php` - one door for staff; the role decides where you land
- `barber/` - today's list, appointments, messages, own rota, own live status, own public profile
- A barber can only ever read and write their own record: the id comes from the session, never the form

**Owner area**
- `admin/index.php` - the whole salon: today's book, adding a team member (with an
  optional portal login and a role of barber, manager or owner), branches, portal
  logins, password resets

**REST API (Node + Express)** - see `server/README.md` for the full reference
- `server/src/routes/` - full CRUD over treatments, team, branches, bookings and
  conversations, plus a management report summary
- `server/public/` - a vanilla-JavaScript console that drives the API in the browser
- `server/scripts/smoke.mjs` - an end-to-end check that runs against the live stack

## Installing it

From this folder:

```bash
docker compose up -d --build
```

| What | Where |
| --- | --- |
| The PHP site | <http://localhost:8085> |
| The API console | <http://localhost:8087> |
| The API itself | <http://localhost:8087/api> |
| phpMyAdmin | <http://localhost:8086> |
| The database itself | `localhost:3307` |

Every port sits clear of XAMPP. To move any of them, copy `.env.example` to
`.env` and edit it there.

The first start builds the PHP 8.2 + Apache image and the Node 22 image, creates
the `morrow_and_blade_php` database and imports `database/morrow_and_blade.sql` -
all 17 tables, the 8 triggers and the seed data. Later starts reuse the volume
and skip the import, so anything added through either front end survives a
restart.

```bash
docker compose down         # stop, keep the database
docker compose down -v      # stop and wipe the database
docker compose logs -f app  # follow the PHP web server
docker compose logs -f api  # follow the Node API
```

Inside the compose network the database is simply `db` on port 3306; both the
PHP config and the API's environment variables point at it.

### Without Docker

The PHP site still runs from `htdocs` under XAMPP: copy this folder to
`C:\xampp\htdocs\barber-salon`, import the dump through phpMyAdmin and open
<http://localhost/barber-salon/>. Point it wherever you like with `DB_HOST`,
`DB_PORT`, `DB_NAME`, `DB_USER` and `DB_PASS`; the defaults in
`config/config.php` are the Docker ones above.

The API runs the same way, reading the same variables:

```bash
cd server
npm install
DB_HOST=127.0.0.1 DB_PORT=3307 DB_USER=salon DB_PASS=salon npm start
```

## Signing in

| Who | Email | Password |
| --- | --- | --- |
| Owner | `admin@morrowandblade.co.uk` | `Admin@123` |
| Any barber | `<slug>@morrowandblade.co.uk`, e.g. `jay-morrow@morrowandblade.co.uk` | `Barber@123` |

The same accounts work on both front ends: the API checks passwords with bcrypt
against the `password_hash` values PHP's `password_hash()` wrote, so there is
one set of credentials rather than two.

Change these from **Owner -> Portal logins** before the site goes live. That page
can create a new login and pick its role - owner, manager or barber - reset any
password and switch a login off without deleting the record. An owner or manager
login sees the whole salon; a barber login is attached to one team member and
only ever sees that person's own page.

## Tests

```bash
cd server
npm test                      # unit and routing tests; no database needed
node scripts/smoke.mjs        # end-to-end CRUD against the running stack
```

`npm test` covers the validation helpers, the PHP-compatible password check,
token signing and the routing and error handling. `scripts/smoke.mjs` needs the
Compose stack up: it signs in, creates, reads, updates and deletes a treatment,
checks that a double booking is refused, and tidies up after itself.

## How the pieces fit

```
index.php ...      the public PHP pages
account/ barber/   the two signed-in portals
admin/             the owner's area
config/            constants, database credentials, class loading
app/               DB (PDO singleton), Auth (sessions + CSRF), helpers
app/dao/           one class per table group - all the PHP SQL lives here
app/Ai/            Grounding (database -> brief) and Assistant (model + rules)
includes/          header, footer, portal chrome, chat thread, assistant widget
api/               PHP JSON endpoints: chat polling, nearest salon, assistant
database/          the importable dump and the migrations used to build it
storage/           session files (denied over the web)

server/            the Node + Express REST API
  src/routes/      one router per resource, all the API SQL
  public/          the vanilla-JS console
  scripts/         the end-to-end smoke test
docker/php/        the Apache image for the PHP side
docker-compose.yml runs all four containers
docs/              specification, user research and the project plan
```

`docker-compose.yml` sits beside them and runs the whole stack, with the web
image built from `docker/php/` and the API image built from `server/`.

## Notes worth knowing

- The database runs in a MariaDB container published on port 3307; XAMPP's own
  MariaDB still holds 3306 and is left untouched.
- Passwords are stored with `password_hash()`. Nothing is kept in plain text.
- Every state-changing PHP form carries a CSRF token and is checked on submit;
  the API is stateless and uses a signed bearer token instead.
- Sessions are written inside `storage/sessions`, so the app works even where
  the system temp directory is not writable.
- `config/`, `app/`, `includes/`, `database/`, `storage/` and `server/` all carry
  an `.htaccess` that denies direct web access.
- Overlap protection exists twice on both front ends: the booking page checks
  the slot first, and the `appointments_no_overlap_*` triggers refuse a clash at
  the database level.
- The service worker (`sw.js`) makes the site installable and keeps visited
  pages readable offline. Booking and chat always need a connection.