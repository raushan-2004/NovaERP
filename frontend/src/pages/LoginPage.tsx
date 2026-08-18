import { useState, type FormEvent } from 'react';
import { Navigate } from 'react-router-dom';
import { useAuthStore } from '../store/auth';
import { useLogin } from '../hooks/useAuth';
import type { ApiError } from '../types/api';

function LoginPage() {
  const isAuthenticated = useAuthStore((s) => s.isAuthenticated);
  const login = useLogin();

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');

  // Already authenticated — redirect to dashboard
  if (isAuthenticated) {
    return <Navigate to="/dashboard" replace />;
  }

  const handleSubmit = (e: FormEvent) => {
    e.preventDefault();
    login.mutate({ email, password });
  };

  const apiError = login.error as ApiError | null;
  const fieldErrors = apiError?.errors;

  return (
    <div className="nova-login-page" role="main">
      <div className="nova-login-card">
        {/* Brand */}
        <div className="nova-login-brand">
          <div className="nova-login-logo" aria-hidden="true">N</div>
          <h1 className="nova-login-title">NovaERP</h1>
          <p className="nova-login-subtitle">NovaTech Industries</p>
        </div>

        <h2 className="nova-login-heading">Sign in to your account</h2>

        {/* General error */}
        {apiError && !fieldErrors && (
          <div className="nova-alert nova-alert--error" role="alert" id="login-error-message">
            {apiError.message}
          </div>
        )}

        <form id="login-form" className="nova-login-form" onSubmit={handleSubmit} noValidate>
          <div className="nova-form-group">
            <label htmlFor="login-email" className="nova-label">
              Email address
            </label>
            <input
              id="login-email"
              type="email"
              autoComplete="email"
              required
              className={`nova-input${fieldErrors?.email ? ' nova-input--error' : ''}`}
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="admin@novatech.com"
            />
            {fieldErrors?.email && (
              <span className="nova-field-error" role="alert">{fieldErrors.email[0]}</span>
            )}
          </div>

          <div className="nova-form-group">
            <label htmlFor="login-password" className="nova-label">
              Password
            </label>
            <input
              id="login-password"
              type="password"
              autoComplete="current-password"
              required
              className={`nova-input${fieldErrors?.password ? ' nova-input--error' : ''}`}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              placeholder="••••••••"
            />
            {fieldErrors?.password && (
              <span className="nova-field-error" role="alert">{fieldErrors.password[0]}</span>
            )}
          </div>

          <button
            id="login-submit-btn"
            type="submit"
            className="nova-btn nova-btn--primary nova-btn--full"
            disabled={login.isPending}
          >
            {login.isPending ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <p className="nova-login-footer">
          Enterprise Resource Planning Platform
        </p>
      </div>
    </div>
  );
}

export default LoginPage;
