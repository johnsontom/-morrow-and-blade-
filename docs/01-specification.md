# Specification - Morrow & Blade

## 1. Purpose

A booking system for a small barbering business. It has to do three jobs:

1. let a member of the public see what the salon offers and book a slot;
2. let a customer manage their own bookings and speak to their barber;
3. let the salon run the book - staff records, treatments, branches, rota and reporting.

The work satisfies a course brief that asks for a CRUD web application built as a
group project, covering HTML, front-end JavaScript, a JavaScript back end on
Node.js/Express, a relational database, Git, Docker, and a specification backed
by user research.

## 2. Scope

**In scope**

- A public marketing and booking site.
- Customer accounts with their own appointment history and message threads.
- A staff portal where a barber sees only their own work.
- An owner's area for the whole salon.
- A REST API over the same data, used by a browser console and available to any
  future client.
- A Docker environment that brings the whole stack up with one command.

**Out of scope**

- Taking payments. Prices are shown and recorded on the booking, but no card
  processing is attempted.
- Email and SMS delivery. The chat and the confirmation reference stand in for
  notifications.
- Multi-tenant support. One salon business, several branches.
- Native mobile apps. The PHP site is installable as a PWA instead.

## 3. Users

| User | What they need |
| --- | --- |
| **Priya, prospective customer** | To find out whether the salon does the treatment she wants, at a price and time she can afford, without phoning. |
| **Daniel, regular customer** | To rebook quickly with the same barber and to move an appointment when work gets in the way. |
| **Marcus, barber** | To see today's list on his phone and know who is next, without seeing the rest of the salon's money. |
| **Adeola, salon owner** | To add a new treatment or team member herself, and to see how the week actually went. |

## 4. Functional requirements

### 4.1 Public site

| ID | Requirement | Priority |
| --- | --- | --- |
| FR1 | Show the treatment menu with duration and price. | Must |
| FR2 | Show the team, with each person's specialities and the treatments they offer. | Must |
| FR3 | Show each branch with address, contact details and a map link. | Must |
| FR4 | Sort branches by distance from the visitor's location. | Should |
| FR5 | Let a visitor book a treatment, with a person, on a date and time. | Must |
| FR6 | Refuse a slot that would overlap an existing booking for that person. | Must |
| FR7 | Give the customer a short reference for the booking. | Must |
| FR8 | Answer common questions in plain language. | Should |
| FR9 | Stay readable offline once visited. | Could |

### 4.2 Accounts and chat

| ID | Requirement | Priority |
| --- | --- | --- |
| FR10 | Register with name, email, phone and password. | Must |
| FR11 | Sign in and out; passwords are never stored in plain text. | Must |
| FR12 | See your own appointment history and cancel a booking that has not started. | Must |
| FR13 | Edit your own contact details. | Should |
| FR14 | Hold a private message thread with one barber per thread. | Should |
| FR15 | See an unread indicator per thread. | Could |

### 4.3 Staff and owner area

| ID | Requirement | Priority |
| --- | --- | --- |
| FR16 | One sign-in page for staff; the role decides what is visible. | Must |
| FR17 | A barber sees and edits only their own record, rota and status. | Must |
| FR18 | A barber sees only their own appointments and messages. | Must |
| FR19 | An owner or manager sees the whole salon. | Must |
| FR20 | The owner can add and edit treatments, team members and branches. | Must |
| FR21 | The owner can create a staff login and choose its role. | Should |
| FR22 | The owner can see bookings, revenue and top treatments for a date range. | Should |
| FR23 | A booking moves through pending, confirmed, in progress, completed, cancelled and no-show. | Should |

### 4.4 REST API

| ID | Requirement | Priority |
| --- | --- | --- |
| FR24 | Serve treatments, team, branches, bookings and conversations as JSON. | Must |
| FR25 | Support create, read, update and delete on each of those resources. | Must |
| FR26 | Authenticate with a signed bearer token; refuse writes from anonymous callers. | Must |
| FR27 | Scope a caller's results to what they are allowed to see. | Must |
| FR28 | Return meaningful status codes, including 409 when a slot is taken. | Must |
| FR29 | Validate input and answer with a readable message when it is wrong. | Must |

## 5. Non-functional requirements

| ID | Requirement |
| --- | --- |
| NFR1 | Passwords stored with `password_hash()` and checked with bcrypt. |
| NFR2 | Every state-changing form carries a CSRF token. |
| NFR3 | `config/`, `app/`, `database/`, `storage/` and `server/` are denied to the web server. |
| NFR4 | A barber can never read or write another barber's data, enforced on the server, not in the form. |
| NFR5 | One command brings the whole stack up: `docker compose up -d --build`. |
| NFR6 | The site is usable on a phone. |
| NFR7 | Pages work with a keyboard alone and have sensible landmarks. |
| NFR8 | The figures on a report page agree with the rows the database holds. |

## 6. Data model

Seventeen tables. The ones that carry the most weight:

| Table | Holds |
| --- | --- |
| `services` / `service_categories` | The treatment menu, priced in pence. |
| `barbers` | Team members, linked to a branch, with a JSON array of specialities. |
| `barber_services` | Which treatments each person offers. |
| `salons` | Branches, with coordinates for the distance sort. |
| `working_hours` / `schedule_exceptions` | The recurring rota and the exceptions to it. |
| `customers` | Booking customers. An account is optional. |
| `profiles` | Staff logins, with a role and an optional link to a barber. |
| `appointments` | The bookings, with a price and name snapshot taken at booking time. |
| `conversations` / `messages` | One thread per customer and barber pair. |
| `availability_events` | Written by a trigger whenever a booking or override changes. |

Two triggers, `appointments_no_overlap_insert` and `appointments_no_overlap_update`,
raise `SLOT_UNAVAILABLE` if a booking would overlap an existing one for the same
person. That is the last line of defence: the application checks the slot as
well, but the database is what makes a double booking impossible.

## 7. Acceptance criteria

The work is done when:

- all Must requirements above are met and demonstrable;
- a booking can be made from the public site, seen in the owner's area, and read
  back through the API;
- two overlapping bookings for one barber are refused;
- a barber login cannot reach another barber's appointments, messages or record;
- `docker compose up -d --build` on a clean machine gives a working site, API and
  database;
- `npm test` and `node server/scripts/smoke.mjs` both pass;
- the README explains how to run it.

## 8. Constraints

- Group project with a fixed deadline and a small team.
- No budget for paid services, so anything used must be free or already licensed.
- The salon already hosts other sites under XAMPP on ports 80 and 3306, so the
  stack must not take those ports.