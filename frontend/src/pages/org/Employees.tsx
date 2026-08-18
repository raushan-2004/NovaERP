import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Employee } from '../../types/api';

const fields: CrudField[] = [
  { name: 'user_id', label: 'Linked User Account', type: 'select', optionsUrl: 'users', required: false },
  { name: 'company_id', label: 'Company', type: 'select', required: true, optionsUrl: 'companies' },
  { name: 'branch_id', label: 'Branch', type: 'select', required: true, optionsUrl: 'branches' },
  { name: 'department_id', label: 'Department', type: 'select', optionsUrl: 'departments', required: false },
  { name: 'first_name', label: 'First Name', type: 'text', required: true, placeholder: 'e.g. Rahul' },
  { name: 'last_name', label: 'Last Name', type: 'text', required: true, placeholder: 'e.g. Sharma' },
  { name: 'employee_code', label: 'Employee Code', type: 'text', required: true, placeholder: 'e.g. NTECH-088' },
  { name: 'email', label: 'Work Email', type: 'email', required: true, placeholder: 'e.g. rahul@novatech.com' },
  { name: 'phone', label: 'Phone Number', type: 'text', placeholder: 'e.g. +91 99999 88888' },
  {
    name: 'status',
    label: 'Status',
    type: 'select',
    required: true,
    options: [
      { value: 'active', label: 'Active' },
      { value: 'inactive', label: 'Inactive' },
    ],
  },
];

const transformData = (data: Record<string, any>) => {
  const payload = { ...data };
  if (payload.user_id === '') {
    payload.user_id = null;
  }
  if (payload.department_id === '') {
    payload.department_id = null;
  }
  return payload;
};

export function EmployeesPage() {
  return (
    <CrudPage<Employee>
      title="Employees"
      endpoint="employees"
      fields={fields}
      viewPermission="employees.view"
      createPermission="employees.create"
      updatePermission="employees.update"
      deletePermission="employees.delete"
      transformData={transformData}
    />
  );
}

export default EmployeesPage;
