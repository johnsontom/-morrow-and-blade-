# Project plan

## 1. Team and roles

| Role | Owns | Who |
| --- | --- | --- |
| Product owner | The brief, priorities, sign-off | |
| Scrum lead / planner | Board, stand-ups, blockers | |
| Front-end lead | HTML, CSS and browser JavaScript | |
| Back-end lead | Node/Express API and the PHP application code | |
| Database lead | Schema, migrations, triggers, seed data | |
| Environment lead | Docker, Compose, ports, deployment | |
| Research lead | Interviews, note-keeping, requirement traceability | |

Everyone writes tests for their own work. On a small team one person will hold
more than one of these; fill the right-hand column in as the team is fixed.

## 2. How the work is managed

GitHub Projects (a Trello board does the same job) with these columns:

`Backlog` -> `Ready` -> `In progress` -> `In review` -> `Done`

Each card is one deliverable and carries an id from the table below, so a card
can always be traced back to a requirement.

**Working agreement**

- Nothing is committed straight to `main`.
- One branch per card: `feat/FR20-owner-crud`, `fix/FR6-overlap-check`.
- A branch is merged through a pull request that one other person has read.
- The pull request says which requirement it satisfies and how it was tested.
- Work in progress is capped at one card per person.
- Stand-up is three questions: what moved, what is stuck, what is next.

## 3. Backlog

| Id | Deliverable | Requirements | Effort |
| --- | --- | --- | --- |
| W1 | Agree scope and write the specification | all | M |
| W2 | Interviews and the evidence log | all | M |
| W3 | Database schema, triggers and seed data | FR6, NFR8 | L |
| W4 | Docker Compose environment with all three services | NFR5, constraints | M |
| W5 | Public pages: home, menu, team, branches | FR1, FR2, FR3 | L |
| W6 | Distance sort for branches | FR4 | S |
| W7 | Booking flow with overlap protection | FR5, FR6, FR7 | L |
| W8 | Customer registration, sign-in and history | FR10, FR11, FR12, FR13 | L |
| W9 | Message threads between a customer and a barber | FR14, FR15 | M |
| W10 | Staff sign-in and the barber portal | FR16, FR17, FR18 | L |
| W11 | Owner area: treatments, team and branches | FR19, FR20 | L |
| W12 | Portal logins and password resets | FR21 | M |
| W13 | Reporting summary | FR22 | M |
| W14 | Express API: catalogue and auth | FR24, FR26, FR29 | L |
| W15 | Express API: bookings, chat and reports | FR25, FR27, FR28 | L |
| W16 | Browser console for the API | FR24, FR25 | M |
| W17 | Unit tests and the end-to-end smoke test | acceptance criteria | M |
| W18 | README, API reference and this plan | acceptance criteria | S |

## 4. Milestones

Adjust the dates to your delivery window; the order is the part that matters.

| Milestone | Work | Exit condition |
| --- | --- | --- |
| M1 - Plan (week 1) | W1, W2 | Specification agreed; board populated |
| M2 - Foundations (week 2) | W3, W4 | `docker compose up` gives a working database and empty site |
| M3 - Public site (week 3) | W5, W6 | The menu, team and branches read from the database |
| M4 - Booking (week 4) | W7 | A booking can be made and a clash is refused |
| M5 - Accounts (week 5) | W8, W9 | A customer can sign in, see history and message a barber |
| M6 - Staff (week 6) | W10, W11, W12 | A barber sees only their day; the owner can run the salon |
| M7 - API (week 7) | W14, W15, W16, W13 | The console does full CRUD; reports agree with the database |
| M8 - Hardening (week 8) | W17, W18 | Tests pass; documentation complete; demo rehearsed |

## 5. Risks

| Risk | Likelihood | Impact | Response |
| --- | --- | --- | --- |
| A teammate is unavailable near the deadline | Medium | High | Every card is owned by one person but readable by all; branches merge early and often |
| Scope creep from "nice to have" features | High | Medium | Must/Should/Could is fixed in the specification; Could items are cut first |
| Two people take the same slot | Medium | High | Application check inside a transaction, plus a database trigger as the last word |
| Database credentials leak into version control | Medium | High | `.env` and `config/local.php` are ignored; `.env.example` is the committed template |
| A barber account sees another barber's data | Low | High | Scope comes from the session or the token, never from the request body |
| Port conflicts with the existing XAMPP install | Medium | Medium | Nothing uses 80 or 3306; the ports are configurable through `.env` |
| Docker works on one laptop but not another | Medium | High | One documented command; a health check on each service; the README covers the fallback |

## 6. Definition of done

A card is done when:

- the behaviour matches the requirement it was raised against;
- it works on a phone-sized screen as well as a desktop;
- server-side code validates its own input rather than trusting the form;
- a test covers it, or the pull request explains why a test is not practical;
- the pull request has been read by someone else;
- the README is still true.

## 7. Contribution log

Fill this in as you go - it is the evidence that this was a group effort.

| Week | Who | Card | What they did | Pull request |
| --- | --- | --- | --- | --- |
| 1 | | | | |
| 2 | | | | |

## 8. Retrospective

Answer these at the end of each milestone, in three or four lines each.

- What went well?
- What slowed us down?
- What will we change next milestone?
- Did anything we built turn out to be wrong, and what did we do about it?

## 9. Definition of the demo

A five-minute walkthrough, in this order:

1. A visitor finds a treatment, checks the price and books it.
2. A second attempt on the same slot is refused, with a readable message.
3. The booking appears in the owner's area and through the API.
4. A barber signs in and sees only their own day.
5. The owner adds a treatment without touching code.
6. `docker compose down` and `docker compose up -d` brings it all back.