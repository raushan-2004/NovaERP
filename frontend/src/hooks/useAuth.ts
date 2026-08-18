import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { fetchMe, login as apiLogin, logout as apiLogout } from '../api/auth';
import { useAuthStore } from '../store/auth';
import type { LoginCredentials } from '../types/api';

export const AUTH_ME_QUERY_KEY = ['auth', 'me'] as const;

/**
 * useCurrentUser — TanStack Query hook for the authenticated user.
 *
 * Server state: the user object lives here, not in Zustand.
 * Enabled only when a token exists in the Zustand auth store.
 */
export function useCurrentUser() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  return useQuery({
    queryKey: AUTH_ME_QUERY_KEY,
    queryFn: fetchMe,
    enabled: isAuthenticated,
    staleTime: 5 * 60 * 1000, // 5 minutes
    retry: false,
  });
}

/**
 * useLogin — mutation hook for the login flow.
 * On success: stores token in Zustand, invalidates any stale user query.
 */
export function useLogin() {
  const { setToken } = useAuthStore();
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  return useMutation({
    mutationFn: (credentials: LoginCredentials) => apiLogin(credentials),
    onSuccess: (data) => {
      setToken(data.token);
      // Seed user data from login response so /me is immediately available
      queryClient.setQueryData(AUTH_ME_QUERY_KEY, {
        id: data.user.id,
        name: data.user.name,
        email: data.user.email,
        created_at: '',
      });
      navigate('/dashboard');
    },
  });
}

/**
 * useLogout — mutation hook for the logout flow.
 * On success: clears Zustand token, clears TanStack Query cache, redirects to login.
 */
export function useLogout() {
  const { clearAuth } = useAuthStore();
  const queryClient = useQueryClient();
  const navigate = useNavigate();

  return useMutation({
    mutationFn: apiLogout,
    onSuccess: () => {
      clearAuth();
      queryClient.clear();
      navigate('/login');
    },
    onError: () => {
      // Even if the server call fails, clear local state
      clearAuth();
      queryClient.clear();
      navigate('/login');
    },
  });
}
