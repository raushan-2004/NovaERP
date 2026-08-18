import { apiClient } from './client';
import type { ApiSuccessResponse, AuthUser, LoginCredentials, LoginResponse } from '../types/api';

/**
 * POST /api/v1/auth/login
 * Returns token + user summary. Token is stored by the caller in Zustand.
 */
export async function login(credentials: LoginCredentials): Promise<LoginResponse> {
  const { data } = await apiClient.post<ApiSuccessResponse<LoginResponse>>('/auth/login', credentials);
  return data.data;
}

/**
 * GET /api/v1/auth/me
 * Returns the full authenticated user. Owned by TanStack Query — not stored in Zustand.
 */
export async function fetchMe(): Promise<AuthUser> {
  const { data } = await apiClient.get<ApiSuccessResponse<AuthUser>>('/auth/me');
  return data.data;
}

/**
 * POST /api/v1/auth/logout
 * Revokes the current Sanctum token on the server.
 */
export async function logout(): Promise<void> {
  await apiClient.post('/auth/logout');
}
