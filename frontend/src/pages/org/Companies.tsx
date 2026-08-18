import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Company } from '../../types/api';

const fields: CrudField[] = [
  { name: 'name', label: 'Company Name', type: 'text', required: true, placeholder: 'e.g. NovaTech Industries Ltd' },
  { name: 'registration_number', label: 'Registration No', type: 'text', placeholder: 'e.g. CIN-123456789' },
  { name: 'phone', label: 'Phone Number', type: 'text', placeholder: 'e.g. +91 11 2345 6789' },
  { name: 'email', label: 'Email Address', type: 'email', placeholder: 'e.g. contact@novatech.com' },
  { name: 'website', label: 'Website', type: 'text', placeholder: 'e.g. https://www.novatech.com' },
  { name: 'address', label: 'Address', type: 'textarea', placeholder: 'Enter complete registered address...', hideInTable: true },
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

export function CompaniesPage() {
  return (
    <CrudPage<Company>
      title="Companies"
      endpoint="companies"
      fields={fields}
      viewPermission="organization.view"
      createPermission="organization.create"
      updatePermission="organization.update"
      deletePermission="organization.delete"
    />
  );
}

export default CompaniesPage;
