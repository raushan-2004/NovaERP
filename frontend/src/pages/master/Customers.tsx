import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Customer } from '../../types/api';

const fields: CrudField[] = [
  { name: 'company_id', label: 'Belongs to Company', type: 'select', required: true, optionsUrl: 'companies' },
  { name: 'name', label: 'Customer Name', type: 'text', required: true, placeholder: 'e.g. Acme Electronics Corp' },
  { name: 'customer_code', label: 'Customer Code', type: 'text', required: true, placeholder: 'e.g. CUST-ACME' },
  { name: 'email', label: 'Email Address', type: 'email', placeholder: 'e.g. billing@acme.com' },
  { name: 'phone', label: 'Phone Number', type: 'text', placeholder: 'e.g. +1 555-0199' },
  { name: 'tax_number', label: 'Tax Identification No', type: 'text', placeholder: 'e.g. GSTIN-987654321' },
  { name: 'address', label: 'Billing Address', type: 'textarea', placeholder: 'Enter complete customer billing address...', hideInTable: true },
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

export function CustomersPage() {
  return (
    <CrudPage<Customer>
      title="Customers"
      endpoint="customers"
      fields={fields}
      viewPermission="customers.view"
      createPermission="customers.create"
      updatePermission="customers.update"
      deletePermission="customers.delete"
    />
  );
}

export default CustomersPage;
