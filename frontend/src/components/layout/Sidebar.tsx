import React, { useState } from 'react';
import { NavLink } from 'react-router-dom';
import { usePermission } from '../../hooks/usePermission';
import { Icons } from '../common/Icons';

interface NavItem {
  label: string;
  path: string;
  icon: React.ComponentType<{ size?: number }>;
  permission: string;
}

interface NavSection {
  title: string;
  permissionCheck: (hasPermission: (p: string) => boolean) => boolean;
  items: NavItem[];
}

function Sidebar() {
  const [collapsed, setCollapsed] = useState(false);
  const { hasPermission } = usePermission();

  const sections: NavSection[] = [
    {
      title: 'Administration',
      permissionCheck: (has) => has('roles.view') || has('users.view'),
      items: [
        { label: 'Roles', path: '/rbac/roles', icon: Icons.Shield, permission: 'roles.view' },
        { label: 'Users', path: '/rbac/users', icon: Icons.Users, permission: 'users.view' },
      ],
    },
    {
      title: 'Organization',
      permissionCheck: (has) => has('organization.view') || has('employees.view'),
      items: [
        { label: 'Companies', path: '/org/companies', icon: Icons.Org, permission: 'organization.view' },
        { label: 'Branches', path: '/org/branches', icon: Icons.Org, permission: 'organization.view' },
        { label: 'Departments', path: '/org/departments', icon: Icons.Org, permission: 'organization.view' },
        { label: 'Employees', path: '/org/employees', icon: Icons.Users, permission: 'employees.view' },
      ],
    },
    {
      title: 'Master Data',
      permissionCheck: (has) =>
        has('products.view') ||
        has('customers.view') ||
        has('suppliers.view') ||
        has('warehouses.view'),
      items: [
        { label: 'Categories', path: '/master/categories', icon: Icons.Folder, permission: 'products.view' },
        { label: 'Brands', path: '/master/brands', icon: Icons.Folder, permission: 'products.view' },
        { label: 'Units (UOM)', path: '/master/units', icon: Icons.Folder, permission: 'products.view' },
        { label: 'Products', path: '/master/products', icon: Icons.Settings, permission: 'products.view' },
        { label: 'Customers', path: '/master/customers', icon: Icons.Users, permission: 'customers.view' },
        { label: 'Suppliers', path: '/master/suppliers', icon: Icons.Users, permission: 'suppliers.view' },
        { label: 'Warehouses', path: '/master/warehouses', icon: Icons.Settings, permission: 'warehouses.view' },
      ],
    },
    {
      title: 'Inventory',
      permissionCheck: (has) => has('inventory.view'),
      items: [
        { label: 'Warehouse Locations', path: '/inventory/warehouse-locations', icon: Icons.Folder, permission: 'inventory.view' },
        { label: 'Stock Balances', path: '/inventory/stock-balances', icon: Icons.Folder, permission: 'inventory.view' },
        { label: 'Stock Ledger', path: '/inventory/stock-ledger', icon: Icons.Folder, permission: 'inventory.view' },
        { label: 'Stock Transfers', path: '/inventory/stock-transfers', icon: Icons.Folder, permission: 'inventory.view' },
        { label: 'Stock Adjustments', path: '/inventory/stock-adjustments', icon: Icons.Folder, permission: 'inventory.view' },
      ],
    },
    {
      title: 'Purchasing',
      permissionCheck: (has) => has('purchase_orders.view') || has('purchase_requests.view'),
      items: [
        { label: 'Purchase Requests', path: '/purchasing/purchase-requests', icon: Icons.Folder, permission: 'purchase_requests.view' },
        { label: 'Purchase Orders', path: '/purchasing/purchase-orders', icon: Icons.Folder, permission: 'purchase_orders.view' },
        { label: 'Goods Receipts', path: '/purchasing/goods-receipts', icon: Icons.Folder, permission: 'goods_receipts.view' },
        { label: 'Purchase Returns', path: '/purchasing/purchase-returns', icon: Icons.Folder, permission: 'purchase_returns.view' },
      ],
    },
  ];

  return (
    <aside className={`nova-sidebar${collapsed ? ' nova-sidebar--collapsed' : ''} border-r border-nova-700/60 bg-nova-800`}>
      {/* Brand */}
      <div className="nova-sidebar-brand border-b border-nova-700/60 p-4 min-h-[64px] flex items-center justify-between gap-3">
        <div className="flex items-center gap-3">
          <div className="nova-sidebar-logo w-9 h-9 bg-accent-500 text-white font-bold rounded-lg flex items-center justify-center">N</div>
          {!collapsed && (
            <div className="nova-sidebar-brand-text">
              <span className="nova-sidebar-brand-name font-bold text-sm text-text-primary">NovaERP</span>
              <span className="nova-sidebar-brand-sub text-[10px] text-text-muted">NovaTech Industries</span>
            </div>
          )}
        </div>
        <button
          id="sidebar-collapse-btn"
          className="nova-sidebar-collapse-btn p-1.5 hover:bg-nova-700 rounded-lg text-text-muted hover:text-text-primary transition"
          onClick={() => setCollapsed(!collapsed)}
          aria-label={collapsed ? 'Expand sidebar' : 'Collapse sidebar'}
        >
          {collapsed ? '›' : '‹'}
        </button>
      </div>

      {/* Navigation */}
      <nav className="nova-sidebar-nav flex-1 py-4 px-2 overflow-y-auto flex flex-col gap-1" aria-label="Main navigation">
        <NavLink
          to="/dashboard"
          id="nav-dashboard"
          className={({ isActive }) =>
            `nova-sidebar-link flex items-center gap-3 p-2.5 rounded-lg text-sm transition ${
              isActive ? 'bg-nova-600 text-text-primary' : 'text-text-secondary hover:bg-nova-700 hover:text-text-primary'
            }`
          }
        >
          <span className="nova-sidebar-link-icon"><Icons.Dashboard size={18} /></span>
          {!collapsed && <span className="nova-sidebar-link-label font-medium">Dashboard</span>}
        </NavLink>

        {sections.map((section) => {
          if (!section.permissionCheck(hasPermission)) return null;

          return (
            <div key={section.title} className="flex flex-col gap-1 mt-4">
              {!collapsed && (
                <span className="nova-sidebar-section-label px-3 text-[10px] font-bold text-text-muted uppercase tracking-wider">
                  {section.title}
                </span>
              )}
              {section.items.map((item) => {
                if (!hasPermission(item.permission)) return null;
                const Icon = item.icon;

                return (
                  <NavLink
                    key={item.path}
                    to={item.path}
                    id={`nav-${item.label.toLowerCase().replace(/\s+/g, '-')}`}
                    className={({ isActive }) =>
                      `nova-sidebar-link flex items-center gap-3 p-2.5 rounded-lg text-sm transition ${
                        isActive ? 'bg-nova-600 text-text-primary' : 'text-text-secondary hover:bg-nova-700 hover:text-text-primary'
                      }`
                    }
                  >
                    <span className="nova-sidebar-link-icon"><Icon size={18} /></span>
                    {!collapsed && <span className="nova-sidebar-link-label font-medium">{item.label}</span>}
                  </NavLink>
                );
              })}
            </div>
          );
        })}
      </nav>

      {/* Footer */}
      {!collapsed && (
        <div className="nova-sidebar-footer p-4 border-t border-nova-700/60 text-center text-[10px] text-text-muted">
          <span>NovaTech v1.0 • Stage 2</span>
        </div>
      )}
    </aside>
  );
}

export default Sidebar;
