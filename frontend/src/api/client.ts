import axios, { AxiosError } from 'axios';
import { ApiError } from '../types/api';
import { useAuthStore } from '../store/auth';

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL as string;

if (!API_BASE_URL) {
  throw new Error('VITE_API_BASE_URL environment variable is not set.');
}

export const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  },
  timeout: 15000,
});

// ─── Request Interceptor ──────────────────────────────────────────────────────
// Attach the Sanctum token from Zustand store on every request.
apiClient.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token;
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// ─── Response Interceptor ─────────────────────────────────────────────────────
// Normalize API errors into ApiError instances for consistent handling.
apiClient.interceptors.response.use(
  (response) => response,
  (error: AxiosError<{ success: false; message: string; errors: Record<string, string[]> | null }>) => {
    const status = error.response?.status ?? 0;
    const data = error.response?.data;
    const message = data?.message ?? error.message ?? 'An unexpected error occurred.';
    const errors = data?.errors ?? null;

    // On 401 — clear local auth state (token is no longer valid)
    if (status === 401) {
      useAuthStore.getState().clearAuth();
    }

    return Promise.reject(new ApiError(status, message, errors));
  },
);
