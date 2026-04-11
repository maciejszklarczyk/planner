const API_URL = process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000';

export class ApiError extends Error {
    constructor(
        public status: number,
        public statusText: string,
        public body: unknown,
    ) {
        super(`API Error ${status}: ${statusText}`);
    }
}

export const api = {
    async get<T>(endpoint: string): Promise<T> {
        const res = await fetch(`${API_URL}${endpoint}`, {
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            cache: 'no-store',
        });
        if (!res.ok) {
            throw new ApiError(res.status, res.statusText, await res.json());
        }
        return res.json();
    },

    async post<T>(endpoint: string, data?: unknown): Promise<T> {
        const res = await fetch(`${API_URL}${endpoint}`, {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: data ? JSON.stringify(data) : undefined,
        });
        if (!res.ok) {
            throw new ApiError(res.status, res.statusText, await res.json());
        }
        return res.json();
    },

    async put<T>(endpoint: string, data?: unknown): Promise<T> {
        const res = await fetch(`${API_URL}${endpoint}`, {
            method: 'PUT',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: data ? JSON.stringify(data) : undefined,
        });
        if (!res.ok) {
            throw new ApiError(res.status, res.statusText, await res.json());
        }
        return res.json();
    },

    async postFormData<T>(endpoint: string, formData: FormData): Promise<T> {
        const res = await fetch(`${API_URL}${endpoint}`, {
            method: 'POST',
            credentials: 'include',
            body: formData,
        });
        if (!res.ok) {
            throw new ApiError(res.status, res.statusText, await res.json());
        }
        return res.json();
    },

    async delete<T>(endpoint: string): Promise<T> {
        const res = await fetch(`${API_URL}${endpoint}`, {
            method: 'DELETE',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
        });
        if (!res.ok) {
            throw new ApiError(res.status, res.statusText, await res.json());
        }
        if (res.status === 204 || res.headers.get('content-length') === '0') {
            return undefined as T;
        }
        return res.json();
    },
};