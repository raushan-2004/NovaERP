import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Supplier } from '../../types/api';

const fields: CrudField[] = [
  { name: 'company_id', label: 'Belongs to Company', type: 'select', required: true, optionsUrl: 'companies' },
  { name: 'name', label: 'Supplier Name', type: 'text', required: true, placeholder: 'e.g. Shenzhen Semis Corp' },
  { name: 'supplier_code', label: 'Supplier Code', type: 'text', required: true, placeholder: 'e.g. SUP-SHEN' },
  { name: 'email', label: 'Email Address', type: 'email', placeholder: 'e.g. sales@shenzhensemis.com' },
  { name: 'phone', label: 'Phone Number', type: 'text', placeholder: 'e.g. +86 755 1234 5678' },
  { name: 'tax_number', label: 'Tax Identification No', type: 'text', placeholder: 'e.g. VAT-888999' },
  { name: 'address', label: 'Supplier Address', type: 'textarea', placeholder: 'Enter complete supplier shipping/mailing address...', hideInTable: true },
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

export function SuppliersPage() {
  return (
    <CrudPage<Supplier>
      title="Suppliers"
      endpoint="suppliers"
      fields={fields}
      viewPermission="suppliers.view"
      createPermission="suppliers.create"
      updatePermission="suppliers.update"
      deletePermission="suppliers.delete"
    />
  );
}

export default SuppliersPage;
