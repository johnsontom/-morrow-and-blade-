'use strict';

const express = require('express');
const crypto = require('node:crypto');
const { ApiError, asyncHandler } = require('../http');
const { query, one } = require('../db');
const { requireAuth, requireCustomer, MANAGEMENT_ROLES } = require('../auth');
const v = require('../validate');

const router = express.Router();

const SELECT = `SELECT cv.*,
    c.name AS customer_name, c.email AS customer_email,
    b.name AS barber_name, b.slug AS barber_slug,
    (SELECT m.body FROM messages m WHERE m.conversation_id = cv.id ORDER BY m.id DESC LIMIT 1) AS last_message,
    (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = cv.id) AS message_count
  FROM conversations cv
  JOIN customers c ON c.id = cv.customer_id
  JOIN barbers b ON b.id = cv.barber_id`;

const isManagement = (req) => MANAGEMENT_ROLES.indexOf(req.user.role) !== -1;

async function loadConversation(id) {
  const row = v.isUuid(id) ? await one(`${SELECT} WHERE cv.id = ? LIMIT 1`, [id]) : null;
  if (!row) throw ApiError.notFound('That conversation does not exist.');
  return row;
}

/** Which side of the thread the caller is on: customer, barber, or null. */
function sideOf(req, row) {
  if (req.user.kind === 'customer' && row.customer_id === req.user.sub) return 'customer';
  if (req.user.barber_id && row.barber_id === req.user.barber_id) return 'barber';
  return null;
}

function assertMember(req, row) {
  if (isManagement(req)) return;
  if (sideOf(req, row) === null) throw ApiError.forbidden('That conversation belongs to someone else.');
}

router.get('/', requireAuth, asyncHandler(async (req, res) => {
  const { limit, offset } = v.pagination(req.query, { maxLimit: 200 });
  const where = [];
  const params = [];

  if (req.user.kind === 'customer') {
    where.push('cv.customer_id = ?');
    params.push(req.user.sub);
  } else if (isManagement(req)) {
    if (req.query.barber_id) {
      where.push('cv.barber_id = ?');
      params.push(v.requireUuid(req.query, 'barber_id'));
    }
  } else if (req.user.barber_id) {
    where.push('cv.barber_id = ?');
    params.push(req.user.barber_id);
  } else {
    throw ApiError.forbidden('That login is not attached to a barber.');
  }

  if (req.query.status) {
    where.push('cv.status = ?');
    params.push(v.requireEnum(req.query, 'status', ['open', 'closed']));
  }

  const whereSql = where.length ? `WHERE ${where.join(' AND ')}` : '';
  const rows = await query(
    `${SELECT} ${whereSql} ORDER BY cv.last_message_at DESC, cv.created_at DESC LIMIT ${limit} OFFSET ${offset}`,
    params
  );

  res.json({ ok: true, data: rows, meta: { count: rows.length, limit, offset } });
}));

// One thread per customer/barber pair, so asking twice returns the same thread
// instead of failing on the unique key.
router.post('/', requireAuth, requireCustomer, asyncHandler(async (req, res) => {
  const barberId = v.requireUuid(req.body, 'barber_id');
  const subject = v.optionalString(req.body, 'subject', { max: 200 });

  const barber = await one('SELECT id FROM barbers WHERE id = ? AND active = 1 LIMIT 1', [barberId]);
  if (!barber) throw ApiError.unprocessable('That team member does not exist.');

  const existing = await one(
    'SELECT id FROM conversations WHERE customer_id = ? AND barber_id = ? LIMIT 1',
    [req.user.sub, barberId]
  );
  if (existing) {
    return res.json({ ok: true, data: await loadConversation(existing.id), meta: { created: false } });
  }

  const id = crypto.randomUUID();
  await query(
    'INSERT INTO conversations (id, customer_id, barber_id, subject) VALUES (?, ?, ?, ?)',
    [id, req.user.sub, barberId, subject]
  );

  return res.status(201).json({ ok: true, data: await loadConversation(id), meta: { created: true } });
}));

router.get('/:id', requireAuth, asyncHandler(async (req, res) => {
  const row = await loadConversation(req.params.id);
  assertMember(req, row);
  res.json({ ok: true, data: row, meta: { side: sideOf(req, row) } });
}));

router.get('/:id/messages', requireAuth, asyncHandler(async (req, res) => {
  const conversation = await loadConversation(req.params.id);
  assertMember(req, conversation);

  const side = sideOf(req, conversation);
  const { limit } = v.pagination(req.query, { maxLimit: 200, defaultLimit: 100 });

  // Clearing the unread badge is opt-in, so a plain GET stays side-effect free.
  if (v.boolValue(req.query.mark_read) && side !== null) {
    await query(
      'UPDATE messages SET read_at = UTC_TIMESTAMP() WHERE conversation_id = ? AND read_at IS NULL AND sender_type <> ?',
      [conversation.id, side]
    );
    const column = side === 'customer' ? 'customer_unread' : 'barber_unread';
    await query(`UPDATE conversations SET ${column} = 0 WHERE id = ?`, [conversation.id]);
  }

  const rows = await query(
    `SELECT m.*, p.full_name AS sender_profile_name
     FROM messages m
     LEFT JOIN profiles p ON p.id = m.sender_profile_id
     WHERE m.conversation_id = ?
     ORDER BY m.id ASC
     LIMIT ${limit}`,
    [conversation.id]
  );

  res.json({ ok: true, data: rows, meta: { count: rows.length, side } });
}));

router.post('/:id/messages', requireAuth, asyncHandler(async (req, res) => {
  const conversation = await loadConversation(req.params.id);
  assertMember(req, conversation);

  if (conversation.status === 'closed') throw ApiError.conflict('That conversation is closed.');

  const side = sideOf(req, conversation);
  const staffSide = side === 'barber' || isManagement(req);
  if (side === null && !isManagement(req)) throw ApiError.forbidden();

  const body = v.requireString(req.body, 'body', { max: 4000 });
  const senderType = staffSide ? 'barber' : 'customer';

  const inserted = await query(
    'INSERT INTO messages (conversation_id, sender_type, sender_customer_id, sender_profile_id, body) VALUES (?, ?, ?, ?, ?)',
    [
      conversation.id,
      senderType,
      senderType === 'customer' ? conversation.customer_id : null,
      senderType === 'barber' ? req.user.sub : null,
      body,
    ]
  );

  const counter = senderType === 'customer' ? 'barber_unread' : 'customer_unread';
  await query(
    `UPDATE conversations SET last_message_at = UTC_TIMESTAMP(), ${counter} = ${counter} + 1, status = 'open' WHERE id = ?`,
    [conversation.id]
  );

  const message = await one('SELECT * FROM messages WHERE id = ? LIMIT 1', [inserted.insertId]);

  res.status(201).json({ ok: true, data: message, meta: { side: senderType } });
}));

router.patch('/:id', requireAuth, asyncHandler(async (req, res) => {
  const conversation = await loadConversation(req.params.id);
  assertMember(req, conversation);
  const body = req.body || {};
  const sets = [];
  const params = [];

  if (body.subject !== undefined) {
    if (sideOf(req, conversation) !== 'customer' && !isManagement(req)) {
      throw ApiError.forbidden('Only the customer can rename the thread.');
    }
    sets.push('subject = ?');
    params.push(v.optionalString(body, 'subject', { max: 200 }));
  }
  if (body.status !== undefined) {
    sets.push('status = ?');
    params.push(v.requireEnum(body, 'status', ['open', 'closed']));
  }

  if (sets.length === 0) throw ApiError.badRequest('Send at least one field to update.');

  params.push(conversation.id);
  await query(`UPDATE conversations SET ${sets.join(', ')} WHERE id = ?`, params);

  res.json({ ok: true, data: await loadConversation(conversation.id) });
}));

// DELETE closes the thread, which is the safe default: the messages stay for
// both sides. A manager can pass ?purge=1 to remove it and its messages.
router.delete('/:id', requireAuth, asyncHandler(async (req, res) => {
  const conversation = await loadConversation(req.params.id);
  assertMember(req, conversation);

  if (v.boolValue(req.query.purge)) {
    if (!isManagement(req)) throw ApiError.forbidden('Only a manager can erase a conversation.');
    await query('DELETE FROM conversations WHERE id = ?', [conversation.id]);
    return res.json({ ok: true, data: { id: conversation.id, deleted: true } });
  }

  await query("UPDATE conversations SET status = 'closed' WHERE id = ?", [conversation.id]);
  res.json({ ok: true, data: await loadConversation(conversation.id) });
}));

module.exports = router;