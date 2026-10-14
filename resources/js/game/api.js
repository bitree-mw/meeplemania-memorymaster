/**
 * Minimal JSON client for the gameplay API. Uses the session cookie and
 * Laravel's XSRF-TOKEN cookie for CSRF protection.
 */
export class ApiError extends Error {
    constructor(message, { status = 0, code = null, network = false } = {}) {
        super(message);
        this.status = status;
        this.code = code;
        this.network = network;
    }

    /** Network drops and server errors can be retried; rule violations cannot. */
    get retryable() {
        return this.network || this.status >= 500 || this.status === 429;
    }
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : null;
}

export async function request(method, url, body, { timeoutMs = 12000 } = {}) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    const token = xsrfToken();

    if (token) headers['X-XSRF-TOKEN'] = token;
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            body: body === undefined ? undefined : JSON.stringify(body),
            credentials: 'same-origin',
            signal: controller.signal,
        });
    } catch {
        throw new ApiError('Connection lost. Check your network and try again.', { network: true });
    } finally {
        clearTimeout(timer);
    }

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const messages = {
            419: 'Your session expired. Refresh the page to keep playing.',
            429: 'Slow down a little — too many requests. Try again in a moment.',
        };
        throw new ApiError(data.message || messages[response.status] || 'Something went wrong.', {
            status: response.status,
            code: data.code ?? null,
        });
    }

    return data;
}

const base = '/api/play';

export const playApi = {
    start: ({ game, mode, duration }) => request('POST', `${base}/sessions`, { game, mode, duration }),
    show: (uuid) => request('GET', `${base}/sessions/${uuid}`),
    sync: (uuid, results) => request('POST', `${base}/sessions/${uuid}/sync`, { results }),
    finish: (uuid, results, reason) => request('POST', `${base}/sessions/${uuid}/finish`, { results, reason }),
    pause: (uuid) => request('POST', `${base}/sessions/${uuid}/pause`),
    resume: (uuid) => request('POST', `${base}/sessions/${uuid}/resume`),
};
