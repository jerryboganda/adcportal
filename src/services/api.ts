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

http.interceptors.response.use(
  response => response,
  error => {
    const status = error?.response?.status;
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
    await axios.get('/sanctum/csrf-cookie', { withCredentials: true });
  } catch {
    // Same-origin deployments behind nginx serve this directly; a failure
    // only surfaces later as a 419 with a clear retry.
  }
}
