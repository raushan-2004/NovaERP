import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Unit } from '../../types/api';

const fields: CrudField[] = [
  { name: 'name', label: 'Unit Name', type: 'text', required: true, placeholder: 'e.g. Kilogram' },
  { name: 'abbreviation', label: 'Abbreviation', type: 'text', required: true, placeholder: 'e.g. kg' },
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

export function UnitsPage() {
  return (
    <CrudPage<Unit>
      title="Units of Measure"
      endpoint="units"
      fields={fields}
      viewPermission="products.view"
      createPermission="products.create"
      updatePermission="products.update"
      deletePermission="products.delete"
    />
  );
}

export default UnitsPage;
