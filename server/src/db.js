'use strict';

const mysql = require('mysql2/promise');
const config = require('./config');

// One pool for the whole process. dateStrings keeps DATETIME columns as the
// naive UTC strings the PHP side writes, rather than letting the driver shift
// them into the server's local timezone. decimalNumbers turns DECIMAL into a
// number so a rating arrives as 4.8 rather than "4.8".
const pool = mysql.createPool({
  host: config.db.host,
  port: config.db.port,
  user: config.db.user,
  password: config.db.password,
  database: config.db.database,
  waitForConnections: true,
  connectionLimit: config.db.poolSize,
  queueLimit: 0,
  dateStrings: true,
  decimalNumbers: true,
  charset: 'utf8mb4_unicode_ci',
});

async function query(sql, params = []) {
  const [rows] = await pool.query(sql, params);
  return rows;
}

async function one(sql, params = []) {
  const rows = await query(sql, params);
  return rows.length ? rows[0] : null;
}

/** Run fn inside a transaction, rolling back if it throws. */
async function transaction(fn) {
  const connection = await pool.getConnection();
  try {
    await connection.beginTransaction();
    const result = await fn(connection);
    await connection.commit();
    return result;
  } catch (error) {
    try {
      await connection.rollback();
    } catch {
      // The connection is already unusable; nothing left to undo.
    }
    throw error;
  } finally {
    connection.release();
  }
}

async function ping() {
  await pool.query('SELECT 1');
}

async function close() {
  await pool.end();
}

module.exports = { pool, query, one, transaction, ping, close };