import axios from 'axios';

/**
 * Same-origin API client for the Laravel backend. Session cookie auth is
 * handled by Sanctum (XSRF-TOKEN cookie → X-XSRF-TOKEN header, automatic
 * in axios). A 401 anywhere means "log in again".
 */
export const http = axios.create({
  baseURL: '/api/v1',
  withCredentials: true,
  headers: {
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

let unauthorizedHandler: (() => void) | null = null;

export function onUnauthorized(handler: () => void): void {
  unauthorizedHandler = handler;
}

let csrfRefresh: Promise<void> | null = null;

/** Fetch a fresh CSRF token cookie; concurrent callers share one request. */
function refreshCsrf(): Promise<void> {
  csrfRefresh = csrfRefresh ?? axios.get('/sanctum/csrf-cookie', { withCredentials: true })
    .then(() => undefined)
    .finally(() => {
      csrfRefresh = null;
    });

  return csrfRefresh;
}

http.interceptors.response.use(
  response => response,
  async error => {
    const status = error?.response?.status;

    // Session/CSRF token went stale server-side (deploy, DB rebuild, idle
    // expiry). Refresh the token cookie and replay the original request once.
    if (status === 419 && error?.config && !(error.config as any).__csrfRetried) {
      (error.config as any).__csrfRetried = true;
      try {
        await refreshCsrf();
      } catch {
        // fall through — the replay will fail with a clear error
      }
      return http.request(error.config);
    }

    if (status === 401 && unauthorizedHandler) {
      unauthorizedHandler();
    }

    const data = error?.response?.data;
    const serverMessage: string | undefined =
      data?.message ??
      (typeof data?.error === 'string' ? data.error : undefined) ??
      (Array.isArray(data?.errors) ? data.errors[0] : undefined);
    const fieldErrors = data?.errors && typeof data.errors === 'object' && !Array.isArray(data.errors)
      ? Object.values(data.errors).flat().join(' ')
      : undefined;

    const message =
      serverMessage ??
      fieldErrors ??
      (error?.code === 'ERR_NETWORK'
        ? 'Cannot reach the server. Check your connection.'
        : status
          ? `Request failed (${status}).`
          : 'Unexpected error.');

    return Promise.reject({ status, message, raw: error });
  }
);

/** Laravel CSRF cookie (required before the first login POST). */
export async function initCsrf(): Promise<void> {
  try {
    await refreshCsrf();
  } catch {
    // Same-origin deployments behind nginx serve this directly; a failure
    // only surfaces later as a 419, which the interceptor auto-heals.
  }
}
