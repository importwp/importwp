/**
 * Client-side debug helpers gated by the plugin debug setting / IWP_DEBUG.
 */

export function isDebug() {
  if (typeof window === 'undefined' || !window.iwp) {
    return false;
  }

  return window.iwp.is_debug === true || window.iwp.is_debug === 'yes';
}

export function setDebug(enabled) {
  if (typeof window === 'undefined' || !window.iwp) {
    return;
  }

  window.iwp.is_debug = enabled ? 'yes' : 'no';
}

export function debugLog(...args) {
  if (!isDebug()) {
    return;
  }

  // eslint-disable-next-line no-console
  console.log('[ImportWP]', ...args);
}

export function debugError(...args) {
  if (!isDebug()) {
    return;
  }

  // eslint-disable-next-line no-console
  console.error('[ImportWP]', ...args);
}

export function debugWarn(...args) {
  if (!isDebug()) {
    return;
  }

  // eslint-disable-next-line no-console
  console.warn('[ImportWP]', ...args);
}
