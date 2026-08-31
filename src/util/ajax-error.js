import { debugError } from './debug';

/**
 * Normalize jQuery XHR / Error / string failures into a user-facing message.
 *
 * @param {any} error
 * @returns {string}
 */
export function formatAjaxError(error) {
  if (error == null) {
    return 'An unknown error occurred.';
  }

  if (typeof error === 'string') {
    return error;
  }

  if (error instanceof Error && error.message) {
    // Prefer REST body when present on wrapped errors
    if (error.responseJSON) {
      const fromJson = messageFromResponseJson(error.responseJSON);
      if (fromJson) {
        return fromJson;
      }
    }
    return error.message;
  }

  const parts = [];

  if (error.responseJSON) {
    const fromJson = messageFromResponseJson(error.responseJSON);
    if (fromJson) {
      parts.push(fromJson);
    }
  }

  if (error.responseText && typeof error.responseText === 'string') {
    const text = error.responseText.trim();
    if (text && !parts.length) {
      // Avoid dumping huge HTML error pages into notices
      parts.push(
        text.length > 300 ? `${text.substring(0, 297)}…` : text
      );
    }
  }

  if (error.data && typeof error.data === 'string') {
    parts.push(error.data);
  } else if (error.data && error.data.message) {
    parts.push(error.data.message);
  }

  if (error.message && typeof error.message === 'string') {
    parts.push(error.message);
  }

  if (error.statusText) {
    parts.push(
      error.status
        ? `${error.statusText} (HTTP ${error.status})`
        : error.statusText
    );
  } else if (error.status) {
    parts.push(`HTTP ${error.status}`);
  }

  if (parts.length === 0) {
    try {
      return JSON.stringify(error);
    } catch (e) {
      return 'An unknown error occurred.';
    }
  }

  // De-dupe while preserving order
  return [...new Set(parts)].join(' — ');
}

function messageFromResponseJson(json) {
  if (!json || typeof json !== 'object') {
    return '';
  }

  // ImportWP REST envelope: { status: 'E', data: '...' }
  if (json.status === 'E' && json.data) {
    if (typeof json.data === 'string') {
      return json.data;
    }
    if (json.data.message) {
      return json.data.message;
    }
  }

  // WP_Error style
  if (json.message) {
    return json.message;
  }

  if (json.code && json.data && json.data.message) {
    return `${json.code}: ${json.data.message}`;
  }

  return '';
}

/**
 * Log a failure for debugging and return a notice-ready message.
 *
 * @param {any} error
 * @param {string} [context]
 * @returns {string}
 */
export function logAjaxError(error, context = '') {
  const message = formatAjaxError(error);
  debugError(context || 'AJAX error', message, error);
  return message;
}
