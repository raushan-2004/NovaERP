import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Department } from '../../types/api';

const fields: CrudField[] = [
  { name: 'company_id', label: 'Company', type: 'select', required: true, optionsUrl: 'companies' },
  { name: 'branch_id', label: 'Branch', type: 'select', required: true, optionsUrl: 'branches' },
  { name: 'name', label: 'Department Name', type: 'text', required: true, placeholder: 'e.g. Quality Assurance' },
  { name: 'department_code', label: 'Department Code', type: 'text', required: true, placeholder: 'e.g. QA-DEPT' },
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

export function DepartmentsPage() {
  return (
    <CrudPage<Department>
      title="Departments"
      endpoint="departments"
      fields={fields}
      viewPermission="organization.view"
      createPermission="organization.create"
      updatePermission="organization.update"
      deletePermission="organization.delete"
    />
  );
}

export default DepartmentsPage;
