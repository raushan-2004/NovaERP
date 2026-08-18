import { useCurrentUser } from './useAuth';

/**
 * usePermission — React hook for frontend RBAC checks.
 *
 * Provides functions to check user permissions and roles:
 *   - hasPermission(perm): Checks if user has a permission or is Super Admin
 *   - hasAnyPermission([perms]): Checks if user has at least one of the permissions
 *   - hasAllPermissions([perms]): Checks if user has all of the permissions
 *   - hasRole(role): Checks if user has a specific role
 */
export function usePermission() {
  const { data: user } = useCurrentUser();

  const isSuperAdmin = user?.roles.some((r) => r.name === 'Super Admin') ?? false;

  const hasPermission = (permission: string): boolean => {
    if (isSuperAdmin) return true;
    return user?.permissions.includes(permission) ?? false;
  };

  const hasAnyPermission = (permissions: string[]): boolean => {
    if (isSuperAdmin) return true;
    if (!user) return false;
    return permissions.some((perm) => user.permissions.includes(perm));
  };

  const hasAllPermissions = (permissions: string[]): boolean => {
    if (isSuperAdmin) return true;
    if (!user) return false;
    return permissions.every((perm) => user.permissions.includes(perm));
  };

  const hasRole = (role: string): boolean => {
    return user?.roles.some((r) => r.name === role) ?? false;
  };

  return {
    user,
    isSuperAdmin,
    hasPermission,
    hasAnyPermission,
    hasAllPermissions,
    hasRole,
    isLoading: !user,
  };
}
