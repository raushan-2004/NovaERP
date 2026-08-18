import { useState } from 'react';
import { NavLink } from 'react-router-dom';

interface NavItem {
  label: string;
  path: string;
  icon: string;
}

// Stage 0: only Dashboard. Future ERP modules will be added here in later stages.
const navItems: NavItem[] = [
  { label: 'Dashboard', path: '/dashboard', icon: '⊞' },
];

// Placeholder items showing future module structure (not yet implemented)
const futureModules: string[] = [
  'Organization',
  'CRM',
  'Sales',
  'Purchasing',
  'Inventory',
  'Manufacturing',
  'Accounting',
  'HR & Payroll',
];

function Sidebar() {
  const [collapsed, setCollapsed] = useState(false);

  return (
    <aside className={`nova-sidebar${collapsed ? ' nova-sidebar--collapsed' : ''}`}>
      {/* Brand */}
      <div className="nova-sidebar-brand">
        <div className="nova-sidebar-logo">N</div>
        {!collapsed && (
          <div className="nova-sidebar-brand-text">
            <span className="nova-sidebar-brand-name">NovaERP</span>
            <span className="nova-sidebar-brand-sub">NovaTech Industries</span>
          </div>
        )}
        <button
          id="sidebar-collapse-btn"
          className="nova-sidebar-collapse-btn"
          onClick={() => setCollapsed(!collapsed)}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {collapsed ? '›' : '‹'}
        </button>
      </div>

      {/* Navigation */}
      <nav className="nova-sidebar-nav" aria-label="Main navigation">
        {navItems.map((item) => (
          <NavLink
            key={item.path}
            to={item.path}
            id={`nav-${item.label.toLowerCase().replace(/\s+/g, '-')}`}
            className={({ isActive }) =>
              `nova-sidebar-link${isActive ? ' nova-sidebar-link--active' : ''}`
            }
          >
            <span className="nova-sidebar-link-icon">{item.icon}</span>
            {!collapsed && <span className="nova-sidebar-link-label">{item.label}</span>}
          </NavLink>
        ))}

        {/* Future modules — placeholder structure */}
        {!collapsed && (
          <div className="nova-sidebar-section">
            <span className="nova-sidebar-section-label">Coming in future stages</span>
            {futureModules.map((mod) => (
              <div key={mod} className="nova-sidebar-link nova-sidebar-link--disabled">
                <span className="nova-sidebar-link-icon">○</span>
                <span className="nova-sidebar-link-label">{mod}</span>
              </div>
            ))}
          </div>
        )}
      </nav>

      {/* Footer */}
      {!collapsed && (
        <div className="nova-sidebar-footer">
          <span>Stage 0 — Foundation</span>
        </div>
      )}
    </aside>
  );
}

export default Sidebar;
