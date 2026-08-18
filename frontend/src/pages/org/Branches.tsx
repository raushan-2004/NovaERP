import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Branch } from '../../types/api';

const fields: CrudField[] = [
  { name: 'company_id', label: 'Parent Company', type: 'select', required: true, optionsUrl: 'companies' },
  { name: 'name', label: 'Branch Name', type: 'text', required: true, placeholder: 'e.g. New Delhi Warehouse & Office' },
  { name: 'branch_code', label: 'Branch Code', type: 'text', required: true, placeholder: 'e.g. DEL-01' },
  { name: 'phone', label: 'Phone', type: 'text', placeholder: 'e.g. +91 11 9876 5432' },
  { name: 'email', label: 'Email', type: 'email', placeholder: 'e.g. delhi@novatech.com' },
  { name: 'address', label: 'Address', type: 'textarea', placeholder: 'Enter physical location address...', hideInTable: true },
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

export function BranchesPage() {
  return (
    <CrudPage<Branch>
      title="Branches"
      endpoint="branches"
      fields={fields}
      viewPermission="organization.view"
      createPermission="organization.create"
      updatePermission="organization.update"
      deletePermission="organization.delete"
    />
  );
}

export default BranchesPage;
