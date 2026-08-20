import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { StockAdjustment } from '../../types/api';

const fields: CrudField[] = [
  {
    name: 'product_id',
    label: 'Product',
    type: 'select',
    required: true,
    optionsUrl: 'products',
  },
  {
    name: 'warehouse_id',
    label: 'Warehouse',
    type: 'select',
    required: true,
    optionsUrl: 'warehouses',
  },
  {
    name: 'adjusted_quantity',
    label: 'Adjustment Quantity (+/-)',
    type: 'number',
    required: true,
    placeholder: 'e.g. 50 or -25',
  },
  {
    name: 'reason',
    label: 'Reason for Adjustment',
    type: 'textarea',
    required: true,
    placeholder: 'Explain why the stock is being adjusted...',
  },
];

export function StockAdjustmentsPage() {
  return (
    <CrudPage<StockAdjustment>
      title="Stock Adjustments"
      endpoint="stock-adjustments"
      fields={fields}
      viewPermission="inventory.view"
      createPermission="inventory.adjust"
      updatePermission="disabled"
      deletePermission="disabled"
      columnRenderers={{
        product_id: (row) => row.product ? `${row.product.name} (${row.product.sku})` : `Prod #${row.product_id}`,
        warehouse_id: (row) => row.warehouse?.name || `WH #${row.warehouse_id}`,
        adjusted_quantity: (row) => {
          const val = parseFloat(row.adjusted_quantity);
          const isPos = val >= 0;
          return (
            <span className={`font-semibold ${isPos ? 'text-green-400' : 'text-red-400'}`}>
              {isPos ? '+' : ''}{val.toFixed(4)}
            </span>
          );
        },
        reason: (row) => <span className="text-text-secondary truncate max-w-[200px] inline-block">{row.reason}</span>,
      }}
    />
  );
}

export default StockAdjustmentsPage;
