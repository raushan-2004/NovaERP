import { useCurrentUser, useLogout } from '../../hooks/useAuth';

function Header() {
  const { data: user } = useCurrentUser();
  const logout = useLogout();

  return (
    <header className="nova-header" role="banner">
      <div className="nova-header-left">
        <h1 className="nova-header-title">NovaERP</h1>
        <span className="nova-header-env-badge">Development</span>
      </div>

      <div className="nova-header-right">
        {user && (
          <div className="nova-header-user" id="header-user-info">
            <div className="nova-header-user-avatar" aria-hidden="true">
              {user.name.charAt(0).toUpperCase()}
            </div>
            <div className="nova-header-user-details">
              <span className="nova-header-user-name">{user.name}</span>
              <span className="nova-header-user-email">{user.email}</span>
            </div>
          </div>
        )}

        <button
          id="header-logout-btn"
          className="nova-btn nova-btn--ghost"
          onClick={() => logout.mutate()}
          disabled={logout.isPending}
          aria-label="Sign out"
        >
          {logout.isPending ? 'Signing out…' : 'Sign out'}
        </button>
      </div>
    </header>
  );
}

export default Header;
