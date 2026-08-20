import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { StockLedgerEntry } from '../../types/api';

const fields: CrudField[] = [
  {
    name: 'product_id',
    label: 'Product',
    type: 'select',
    optionsUrl: 'products',
  },
  {
    name: 'warehouse_id',
    label: 'Warehouse',
    type: 'select',
    optionsUrl: 'warehouses',
  },
  {
    name: 'movement_type',
    label: 'Movement Type',
    type: 'text',
  },
  {
    name: 'quantity',
    label: 'Quantity',
    type: 'text',
  },
  {
    name: 'balance_before',
    label: 'Bal (Before)',
    type: 'text',
  },
  {
    name: 'balance_after',
    label: 'Bal (After)',
    type: 'text',
  },
  {
    name: 'notes',
    label: 'Notes',
    type: 'text',
  },
  {
    name: 'occurred_at',
    label: 'Occurred At',
    type: 'text',
  },
];

const movementTypeStyles: Record<string, string> = {
  purchase_receipt: 'bg-emerald-500/10 text-emerald-400 border-emerald-500/20',
  purchase_return: 'bg-rose-500/10 text-rose-400 border-rose-500/20',
  transfer_in: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  transfer_out: 'bg-amber-500/10 text-amber-400 border-amber-500/20',
  adjustment_in: 'bg-teal-500/10 text-teal-400 border-teal-500/20',
  adjustment_out: 'bg-orange-500/10 text-orange-400 border-orange-500/20',
};

export function StockLedgerPage() {
  return (
    <CrudPage<StockLedgerEntry>
      title="Stock Ledger Audit Trail"
      endpoint="stock-ledger"
      fields={fields}
      viewPermission="inventory.view"
      createPermission="disabled"
      updatePermission="disabled"
      deletePermission="disabled"
      columnRenderers={{
        product_id: (row) => row.product ? `${row.product.name} (${row.product.sku})` : `Prod #${row.product_id}`,
        warehouse_id: (row) => row.warehouse?.name || `WH #${row.warehouse_id}`,
        movement_type: (row) => {
          const type = row.movement_type;
          const style = movementTypeStyles[type] || 'bg-slate-500/10 text-slate-400 border-slate-500/20';
          return (
            <span className={`px-2 py-0.5 rounded text-xs font-semibold uppercase border ${style}`}>
              {type.replace('_', ' ')}
            </span>
          );
        },
        quantity: (row) => (
          <span className="font-semibold text-text-primary">
            {parseFloat(row.quantity).toFixed(4)}
          </span>
        ),
        balance_before: (row) => parseFloat(row.balance_before).toFixed(4),
        balance_after: (row) => parseFloat(row.balance_after).toFixed(4),
        occurred_at: (row) => new Date(row.occurred_at).toLocaleString(),
      }}
    />
  );
}

export default StockLedgerPage;
