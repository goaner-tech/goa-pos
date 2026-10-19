import { reactive } from 'vue';

const TOKEN_KEY = 'pos.token';

export const session = reactive({
    employee: null,
    token: localStorage.getItem(TOKEN_KEY),
});

export class ApiError extends Error {
    constructor(status, body) {
        const firstError = body?.errors ? Object.values(body.errors)[0]?.[0] : null;
        super(firstError || body?.message || `Request failed (${status})`);
        this.status = status;
        this.body = body;
    }
}

export async function api(method, path, data) {
    const response = await fetch(`/api${path}`, {
        method,
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(session.token ? { Authorization: `Bearer ${session.token}` } : {}),
        },
        body: data === undefined ? undefined : JSON.stringify(data),
    });

    const body = response.status === 204 ? null : await response.json().catch(() => null);

    if (response.status === 401) {
        clearSession();
    }
    if (!response.ok) {
        throw new ApiError(response.status, body);
    }

    return body;
}

export async function login(pin) {
    const { token, employee } = await api('POST', '/login', { pin, device: navigator.userAgent.slice(0, 100) });
    session.token = token;
    session.employee = employee;
    localStorage.setItem(TOKEN_KEY, token);

    return employee;
}

export async function restoreSession() {
    if (!session.token) return null;
    try {
        session.employee = (await api('GET', '/me')).employee;
    } catch {
        clearSession();
    }

    return session.employee;
}

export async function logout() {
    try {
        await api('POST', '/logout');
    } finally {
        clearSession();
    }
}

function clearSession() {
    session.token = null;
    session.employee = null;
    localStorage.removeItem(TOKEN_KEY);
}
