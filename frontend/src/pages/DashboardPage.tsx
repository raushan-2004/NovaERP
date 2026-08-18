import { useCurrentUser } from '../hooks/useAuth';

function DashboardPage() {
  const { data: user, isLoading } = useCurrentUser();

  return (
    <div className="nova-page" id="dashboard-page">
      <div className="nova-page-header">
        <h2 className="nova-page-title">Dashboard</h2>
        <p className="nova-page-subtitle">
          Welcome to NovaERP — Stage 0 Foundation
        </p>
      </div>

      {/* User Info Card */}
      <div className="nova-card" id="dashboard-user-card">
        <div className="nova-card-header">
          <h3 className="nova-card-title">Authenticated Session</h3>
          <span className="nova-badge nova-badge--success">Active</span>
        </div>
        {isLoading ? (
          <div className="nova-skeleton-block" aria-busy="true" aria-label="Loading user info" />
        ) : user ? (
          <dl className="nova-definition-list">
            <div className="nova-dl-row">
              <dt>Name</dt>
              <dd id="dashboard-user-name">{user.name}</dd>
            </div>
            <div className="nova-dl-row">
              <dt>Email</dt>
              <dd id="dashboard-user-email">{user.email}</dd>
            </div>
            <div className="nova-dl-row">
              <dt>Account Created</dt>
              <dd>{new Date(user.created_at).toLocaleDateString()}</dd>
            </div>
          </dl>
        ) : null}
      </div>

      {/* Stage Status */}
      <div className="nova-card nova-card--muted" id="dashboard-stage-card">
        <div className="nova-card-header">
          <h3 className="nova-card-title">Build Stage</h3>
          <span className="nova-badge nova-badge--info">Stage 0</span>
        </div>
        <p className="nova-card-body-text">
          Foundation is complete. Authentication, API infrastructure, and application shell are operational.
          ERP modules will be introduced in subsequent stages.
        </p>
        <div className="nova-module-grid" aria-label="Planned modules">
          {[
            'Organization', 'CRM', 'Sales', 'Purchasing',
            'Inventory', 'Manufacturing', 'Accounting', 'HR & Payroll',
          ].map((mod) => (
            <div key={mod} className="nova-module-chip nova-module-chip--pending">
              {mod}
            </div>
          ))}
        </div>
      </div>
    </div>
  );
}

export default DashboardPage;
