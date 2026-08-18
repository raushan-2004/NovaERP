import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import ProtectedRoute from './ProtectedRoute';
import LoginPage from '../pages/LoginPage';
import DashboardPage from '../pages/DashboardPage';
import NotFoundPage from '../pages/NotFoundPage';

// RBAC Pages
import RolesPage from '../pages/rbac/Roles';
import UsersPage from '../pages/rbac/Users';

// Org Pages
import CompaniesPage from '../pages/org/Companies';
import BranchesPage from '../pages/org/Branches';
import DepartmentsPage from '../pages/org/Departments';
import EmployeesPage from '../pages/org/Employees';

// Master Data Pages
import CategoriesPage from '../pages/master/Categories';
import BrandsPage from '../pages/master/Brands';
import UnitsPage from '../pages/master/Units';
import ProductsPage from '../pages/master/Products';
import CustomersPage from '../pages/master/Customers';
import SuppliersPage from '../pages/master/Suppliers';
import WarehousesPage from '../pages/master/Warehouses';

function AppRouter() {
  return (
    <BrowserRouter>
      <Routes>
        {/* Public routes */}
        <Route path="/login" element={<LoginPage />} />

        {/* Protected routes — wrapped by AppShell */}
        <Route element={<ProtectedRoute />}>
          <Route path="/dashboard" element={<DashboardPage />} />
          
          {/* RBAC */}
          <Route path="/rbac/roles" element={<RolesPage />} />
          <Route path="/rbac/users" element={<UsersPage />} />

          {/* Organization */}
          <Route path="/org/companies" element={<CompaniesPage />} />
          <Route path="/org/branches" element={<BranchesPage />} />
          <Route path="/org/departments" element={<DepartmentsPage />} />
          <Route path="/org/employees" element={<EmployeesPage />} />

          {/* Master Data */}
          <Route path="/master/categories" element={<CategoriesPage />} />
          <Route path="/master/brands" element={<BrandsPage />} />
          <Route path="/master/units" element={<UnitsPage />} />
          <Route path="/master/products" element={<ProductsPage />} />
          <Route path="/master/customers" element={<CustomersPage />} />
          <Route path="/master/suppliers" element={<SuppliersPage />} />
          <Route path="/master/warehouses" element={<WarehousesPage />} />
        </Route>

        {/* Redirects */}
        <Route path="/" element={<Navigate to="/dashboard" replace />} />
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  );
}

export default AppRouter;
