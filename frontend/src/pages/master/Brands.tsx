import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Brand } from '../../types/api';

const fields: CrudField[] = [
  { name: 'name', label: 'Brand Name', type: 'text', required: true, placeholder: 'e.g. Texas Instruments' },
  { name: 'code', label: 'Brand Code', type: 'text', required: true, placeholder: 'e.g. TI-BRAND' },
  { name: 'description', label: 'Description', type: 'textarea', placeholder: 'Enter brand details...' },
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

export function BrandsPage() {
  return (
    <CrudPage<Brand>
      title="Brands"
      endpoint="brands"
      fields={fields}
      viewPermission="products.view"
      createPermission="products.create"
      updatePermission="products.update"
      deletePermission="products.delete"
    />
  );
}

export default BrandsPage;
