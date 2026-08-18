import { create } from 'zustand';

/**
 * Auth store — Zustand client/global state.
 *
 * SCOPE: This store owns only client-side auth state:
 *   - The Sanctum token (in-memory only)
 *   - isAuthenticated flag
 *
 * It does NOT store the user object. The authenticated user
 * is server state owned by TanStack Query (see hooks/useAuth.ts).
 *
 * NOTE: Token is stored in-memory only. No localStorage persistence.
 * The production authentication persistence strategy (session cookie,
 * httpOnly cookie, etc.) will be evaluated during the Auth/RBAC stage.
 */
interface AuthState {
  token: string | null;
  isAuthenticated: boolean;
  setToken: (token: string) => void;
  clearAuth: () => void;
}

export const useAuthStore = create<AuthState>()((set) => ({
  token: null,
  isAuthenticated: false,

  setToken: (token: string) =>
    set({ token, isAuthenticated: true }),

  clearAuth: () =>
    set({ token: null, isAuthenticated: false }),
}));
