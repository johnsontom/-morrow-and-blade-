'use strict';

const { ApiError } = require('./http');

const UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
const SLUG = /^[a-z0-9]+(?:-[a-z0-9]+)*$/;
const NAIVE_DATE = /^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2})?$/;

function read(body, field) {
  const value = body ? body[field] : undefined;
  return value === undefined || value === null ? '' : String(value).trim();
}

function requireString(body, field, options) {
  const opts = options || {};
  const min = opts.min === undefined ? 1 : opts.min;
  const max = opts.max === undefined ? 255 : opts.max;
  const value = read(body, field);
  if (value.length < min) throw ApiError.badRequest(`"${field}" is required.`);
  if (value.length > max) throw ApiError.badRequest(`"${field}" must be ${max} characters or fewer.`);
  return value;
}

function optionalString(body, field, options) {
  const opts = options || {};
  const max = opts.max === undefined ? 255 : opts.max;
  const fallback = opts.fallback === undefined ? '' : opts.fallback;
  const value = read(body, field);
  if (value.length > max) throw ApiError.badRequest(`"${field}" must be ${max} characters or fewer.`);
  return value === '' ? fallback : value;
}

function requireInt(body, field, options) {
  const opts = options || {};
  const raw = body ? body[field] : undefined;
  const value = Number(raw);
  if (raw === undefined || raw === null || raw === '' || Number.isInteger(value) === false) {
    throw ApiError.badRequest(`"${field}" must be a whole number.`);
  }
  if (opts.min !== undefined && value < opts.min) {
    throw ApiError.badRequest(`"${field}" must be at least ${opts.min}.`);
  }
  if (opts.max !== undefined && value > opts.max) {
    throw ApiError.badRequest(`"${field}" must be ${opts.max} or less.`);
  }
  return value;
}

function optionalInt(body, field, options) {
  const opts = options || {};
  const raw = body ? body[field] : undefined;
  if (raw === undefined || raw === null || raw === '') {
    return opts.fallback === undefined ? 0 : opts.fallback;
  }
  return requireInt(body, field, opts);
}

function boolValue(raw, fallback) {
  const fallbackValue = fallback === undefined ? false : fallback;
  if (raw === undefined || raw === null || raw === '') return fallbackValue;
  if (typeof raw === 'boolean') return raw;
  return ['1', 'true', 'yes', 'on'].includes(String(raw).toLowerCase());
}

function requireBool(body, field, options) {
  const opts = options || {};
  return boolValue(body ? body[field] : undefined, opts.fallback);
}

function requireEnum(body, field, allowed, options) {
  const opts = options || {};
  const value = read(body, field) || opts.fallback;
  if (allowed.indexOf(value) === -1) {
    throw ApiError.badRequest(`"${field}" must be one of: ${allowed.join(', ')}.`);
  }
  return value;
}

function slugify(value) {
  return String(value)
    .toLowerCase()
    .normalize('NFKD')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '')
    .slice(0, 100);
}

/** A slug supplied by the client, or one derived from the given default. */
function requireSlug(body, field, options) {
  const opts = options || {};
  const value = read(body, field) || opts.fallback;
  if (SLUG.test(value) === false) {
    throw ApiError.badRequest(`"${field}" must be lower-case words separated by hyphens.`);
  }
  return value;
}

function requireEmail(body, field) {
  const name = field === undefined ? 'email' : field;
  const value = requireString(body, name, { max: 254 }).toLowerCase();
  if (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value) === false) {
    throw ApiError.badRequest(`"${name}" must be a valid email address.`);
  }
  return value;
}

function requireUuid(body, field) {
  const value = requireString(body, field, { max: 36 });
  if (UUID.test(value) === false) throw ApiError.badRequest(`"${field}" must be a UUID.`);
  return value;
}

function isUuid(value) {
  return UUID.test(String(value || ''));
}

/**
 * Parse a date and time. A naive "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DDTHH:MM:SS"
 * is read as UTC, which is how the PHP side stores every timestamp.
 */
function requireDate(body, field) {
  const value = requireString(body, field, { max: 40 });
  const iso = NAIVE_DATE.test(value) ? `${value.replace(' ', 'T')}Z` : value;
  const parsed = new Date(iso);
  if (Number.isNaN(parsed.getTime())) {
    throw ApiError.badRequest(`"${field}" must be a date and time.`);
  }
  return parsed;
}

/** Format a Date as the naive UTC 'YYYY-MM-DD HH:MM:SS' the schema stores. */
function toMysqlDateTime(date) {
  return date.toISOString().slice(0, 19).replace('T', ' ');
}

function parseJsonArray(value, fallback) {
  const fallbackValue = fallback === undefined ? [] : fallback;
  if (Array.isArray(value)) return value.map((item) => String(item));
  if (typeof value !== 'string' || value.trim() === '') return fallbackValue;
  try {
    const parsed = JSON.parse(value);
    return Array.isArray(parsed) ? parsed.map((item) => String(item)) : fallbackValue;
  } catch {
    throw ApiError.badRequest('That field must be a JSON array of strings.');
  }
}

/** Clamp a page size and offset so a client cannot ask for the whole table. */
function pagination(query, options) {
  const opts = options || {};
  const maxLimit = opts.maxLimit === undefined ? 100 : opts.maxLimit;
  const defaultLimit = opts.defaultLimit === undefined ? 50 : opts.defaultLimit;
  const source = query || {};
  const requestedLimit = Number(source.limit) || defaultLimit;
  const limit = Math.trunc(Math.min(Math.max(requestedLimit, 1), maxLimit));
  const offset = Math.trunc(Math.max(Number(source.offset) || 0, 0));
  return { limit, offset };
}

module.exports = {
  UUID,
  SLUG,
  read,
  requireString,
  optionalString,
  requireInt,
  optionalInt,
  requireBool,
  boolValue,
  requireEnum,
  slugify,
  requireSlug,
  requireEmail,
  requireUuid,
  isUuid,
  requireDate,
  toMysqlDateTime,
  parseJsonArray,
  pagination,
};