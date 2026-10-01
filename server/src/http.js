'use strict';

class ApiError extends Error {
  constructor(status, message, details = undefined) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.details = details;
  }

  static badRequest(message, details) { return new ApiError(400, message, details); }
  static unauthorized(message = 'Sign in to continue.') { return new ApiError(401, message); }
  static forbidden(message = 'You do not have access to that.') { return new ApiError(403, message); }
  static notFound(message = 'Not found.') { return new ApiError(404, message); }
  static conflict(message, details) { return new ApiError(409, message, details); }
  static unprocessable(message, details) { return new ApiError(422, message, details); }
}

/** Wrap an async route so a rejected promise reaches the error middleware. */
const asyncHandler = (handler) => (req, res, next) => {
  Promise.resolve(handler(req, res, next)).catch(next);
};

function notFound(req, res, next) {
  next(new ApiError(404, `No API route matches ${req.method} ${req.originalUrl}`));
}

/**
 * Turn the database errors a client can actually act on into HTTP responses.
 * Anything else returns null and is reported as a 500.
 */
function fromMySQL(error) {
  const message = String(error.sqlMessage || error.message || '');

  // Raised by the appointments_no_overlap_* triggers.
  if (message.includes('SLOT_UNAVAILABLE')) {
    return ApiError.conflict('That slot has just been taken. Please choose another time.');
  }

  switch (error.code) {
    case 'ER_DUP_ENTRY':
      return ApiError.conflict('That record already exists.', {
        constraint: (message.match(/for key '([^']+)'/) || [])[1],
      });
    case 'ER_NO_REFERENCED_ROW_2':
      return ApiError.unprocessable('A record this one points at does not exist.');
    case 'ER_ROW_IS_REFERENCED_2':
      return ApiError.conflict('That record is still in use and cannot be removed.');
    case 'ER_CHECK_CONSTRAINT_VIOLATED':
    case 'ER_WARN_DATA_OUT_OF_RANGE':
      return ApiError.unprocessable('The database rejected those values.', {
        constraint: (message.match(/`([^`]+)`/) || [])[1],
      });
    case 'ER_SIGNAL_EXCEPTION':
      return ApiError.conflict(message || 'That change is not allowed.');
    default:
      return null;
  }
}

// Express only treats this as an error handler with all four arguments.
function errorHandler(error, req, res, next) { // eslint-disable-line no-unused-vars
  const mapped = error instanceof ApiError ? error : fromMySQL(error);

  if (mapped) {
    const body = { ok: false, error: mapped.message };
    if (mapped.details !== undefined) body.details = mapped.details;
    return res.status(mapped.status).json(body);
  }

  console.error(`[api] ${req.method} ${req.originalUrl} failed:`, error);
  return res.status(500).json({ ok: false, error: 'Something went wrong on our side.' });
}

module.exports = { ApiError, asyncHandler, notFound, errorHandler };