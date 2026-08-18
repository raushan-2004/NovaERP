import { Navigate, Outlet } from 'react-router-dom';
import { useAuthStore } from '../store/auth';
import AppShell from '../components/layout/AppShell';

/**
 * ProtectedRoute — wraps routes that require authentication.
 * Redirects unauthenticated users to /login.
 */
function ProtectedRoute() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return (
    <AppShell>
      <Outlet />
    </AppShell>
  );
}

export default ProtectedRoute;

