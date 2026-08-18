import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { Warehouse } from '../../types/api';

const fields: CrudField[] = [
  { name: 'branch_id', label: 'Belongs to Branch', type: 'select', required: true, optionsUrl: 'branches' },
  { name: 'name', label: 'Warehouse Name', type: 'text', required: true, placeholder: 'e.g. Main Finished Goods Depot' },
  { name: 'warehouse_code', label: 'Warehouse Code', type: 'text', required: true, placeholder: 'e.g. WH-MAIN' },
  { name: 'address', label: 'Physical Address', type: 'textarea', placeholder: 'Enter physical warehouse address...', hideInTable: true },
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

export function WarehousesPage() {
  return (
    <CrudPage<Warehouse>
      title="Warehouses"
      endpoint="warehouses"
      fields={fields}
      viewPermission="warehouses.view"
      createPermission="warehouses.create"
      updatePermission="warehouses.update"
      deletePermission="warehouses.delete"
    />
  );
}

export default WarehousesPage;
