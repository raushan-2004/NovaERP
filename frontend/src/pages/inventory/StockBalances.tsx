import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { StockBalance } from '../../types/api';

const fields: CrudField[] = [
  {
    name: 'product_id',
    label: 'Product',
    type: 'select',
    required: true,
    optionsUrl: 'products',
    disabledOnEdit: true,
  },
  {
    name: 'warehouse_id',
    label: 'Warehouse',
    type: 'select',
    required: true,
    optionsUrl: 'warehouses',
    disabledOnEdit: true,
  },
  {
    name: 'quantity',
    label: 'In-Stock Quantity',
    type: 'text',
    required: true,
    placeholder: '0.0000',
  },
];

export function StockBalancesPage() {
  return (
    <CrudPage<StockBalance>
      title="Stock Balances"
      endpoint="stock-balances"
      fields={fields}
      viewPermission="inventory.view"
      createPermission="disabled" // Disable additions
      updatePermission="disabled" // Disable edits
      deletePermission="disabled" // Disable deletions
      columnRenderers={{
        product_id: (row) => row.product ? `${row.product.name} (${row.product.sku})` : `Prod #${row.product_id}`,
        warehouse_id: (row) => row.warehouse?.name || `WH #${row.warehouse_id}`,
        quantity: (row) => (
          <span className="font-semibold text-text-primary">
            {parseFloat(row.quantity).toFixed(4)}
          </span>
        ),
      }}
    />
  );
}

export default StockBalancesPage;
