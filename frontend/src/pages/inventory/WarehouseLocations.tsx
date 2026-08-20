import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { WarehouseLocation } from '../../types/api';

const fields: CrudField[] = [
  {
    name: 'warehouse_id',
    label: 'Warehouse',
    type: 'select',
    required: true,
    optionsUrl: 'warehouses',
  },
  {
    name: 'code',
    label: 'Location Code',
    type: 'text',
    required: true,
    placeholder: 'e.g. SEC-A-ROW1',
  },
  {
    name: 'name',
    label: 'Location Name',
    type: 'text',
    required: true,
    placeholder: 'e.g. Section A, Row 1',
  },
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

export function WarehouseLocationsPage() {
  return (
    <CrudPage<WarehouseLocation>
      title="Warehouse Locations"
      endpoint="warehouse-locations"
      fields={fields}
      viewPermission="inventory.view"
      createPermission="inventory.adjust"
      updatePermission="inventory.adjust"
      deletePermission="inventory.adjust"
      columnRenderers={{
        warehouse_id: (row) => row.warehouse?.name || `WH #${row.warehouse_id}`,
      }}
    />
  );
}

export default WarehouseLocationsPage;
