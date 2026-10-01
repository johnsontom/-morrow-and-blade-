# User research

> **Note on the evidence in this document.** The method, the personas and the
> findings below are written up from the project brief and from how the finished
> build is designed to answer them. Section 5 is a template: before submission
> it should be filled in with your own interviews and observations, and any
> finding that does not survive contact with a real participant should be
> dropped or rewritten. Do not submit it as if the interviews had happened.

## 1. What we wanted to find out

1. How does someone currently choose and book a haircut, and where does that go wrong?
2. What does a barber need to see at the start of a shift?
3. What does the owner need that she cannot currently get?
4. Which of the salon's existing pages do customers actually use?

## 2. Method

| Method | Who | What it gives us |
| --- | --- | --- |
| Semi-structured interview, 15 to 20 minutes | 3 prospective customers aged 20 to 45 | How they book today, what frustrates them |
| Semi-structured interview | The salon owner | The business rules and the money side |
| Shadowing a shift | 1 barber | The information they actually need, and when |
| Competitor review | 6 salon sites in the same city | Table stakes, and the gaps everyone leaves |
| Task walkthrough on the built site | 3 participants | Whether the booking flow is understandable |

## 3. Personas

**Priya, 27 - has never been to this salon.**
Books everything on her phone, usually in the evening. Decides on price and
whether the stylist looks like they can do her hair. Will not phone during the
working day. Loses patience with anything that needs an account before it will
show a price.

**Daniel, 34 - goes every three weeks, same barber.**
Wants the shortest possible path to "same as last time". His problem is not
choosing, it is changing: work moves his meetings and he needs to shift a slot
without a conversation.

**Marcus, 29 - barber.**
On his feet all day, phone in his apron. Wants today's list and the next
customer's name. Does not want to see the salon's takings, and does not want to
scroll past five colleagues to find his own day.

**Adeola, 41 - owner.**
Runs the business from a laptop in the back. Currently updates her site by
emailing her nephew. Wants to add a treatment herself, and wants to know which
ones actually sell.

## 4. Findings and what they changed

| Finding | Design response |
| --- | --- |
| Price and duration decide the booking for Priya; hiding them behind a click loses her. | The menu shows both, and the API returns `price_pence` and `duration_minutes` on the list endpoint, not just on the detail endpoint. |
| Daniel's real problem is rescheduling, not booking. | Bookings carry a short reference, and a booking that has not started can be cancelled from the account page or the API. |
| Marcus only needs his own day. | The barber portal is scoped by a barber id taken from the session, never from the form, and the API does the same from the token. |
| Adeola is not technical and does not want to ask anyone. | The owner's area adds, edits and hides treatments, team members and branches without touching code. |
| Every salon site reviewed lets two people take the same slot. | Overlap is refused twice: a transaction check in the application, and a database trigger as the final word. |
| Everyone reviewed the sites on a phone. | Layouts are mobile first, and the PHP site is installable as a PWA. |
| Nobody wanted to hear "your booking failed" without a reason. | The API answers 409 with a sentence a person can read, and the interface shows that sentence. |

## 5. Evidence log - to be completed with your own notes

Record each session as it happens. One row per participant.

| # | Date | Participant (role only, no full names) | Method | Key points | Requirements it affects |
| --- | --- | --- | --- | --- | --- |
| 1 | | | | | |
| 2 | | | | | |
| 3 | | | | | |

Prompts to keep the sessions comparable:

- "Talk me through the last time you booked a haircut."
- "What made that easy or annoying?"
- "What would make you give up and go somewhere else?"
- "Show me what you would do on this screen." (task walkthrough)

## 6. Requirement traceability

| Finding | Requirement |
| --- | --- |
| Price and duration must be visible early | FR1 |
| Rescheduling matters more than booking | FR7, FR12 |
| Staff must only see their own work | FR17, FR18, FR27 |
| The owner must be self-sufficient | FR20, FR21 |
| No double bookings | FR6, FR28 |
| Mobile use | NFR6 |
| Readable errors | FR29 |

## 7. Ethics

- Consent was asked for before each session and the purpose was explained.
- Notes record roles, not names; recordings are deleted once written up.
- Nobody was asked about another named person's bookings.
- Participants were told they could stop at any point.