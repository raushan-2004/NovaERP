import CrudPage, { type CrudField } from '../../components/common/CrudPage';
import type { GoodsReceipt } from '../../types/api';

const fields: CrudField[] = [
  {
    name: 'grn_number',
    label: 'GRN Number',
    type: 'text',
  },
  {
    name: 'purchase_order_id',
    label: 'Purchase Order',
    type: 'select',
    optionsUrl: 'purchase-orders',
  },
  {
    name: 'warehouse_id',
    label: 'Warehouse',
    type: 'select',
    optionsUrl: 'warehouses',
  },
  {
    name: 'received_date',
    label: 'Received Date',
    type: 'text',
  },
  {
    name: 'status',
    label: 'Status',
    type: 'text',
  },
  {
    name: 'notes',
    label: 'Notes',
    type: 'text',
  },
];

export function GoodsReceiptsPage() {
  return (
    <CrudPage<GoodsReceipt>
      title="Goods Receipt Notes (GRN)"
      endpoint="goods-receipts"
      fields={fields}
      viewPermission="goods_receipts.view"
      createPermission="disabled"
      updatePermission="disabled"
      deletePermission="disabled"
      columnRenderers={{
        grn_number: (row) => <span className="font-semibold text-text-primary">{row.grn_number}</span>,
        purchase_order_id: (row) => row.purchase_order?.po_number || `PO #${row.purchase_order_id}`,
        warehouse_id: (row) => row.warehouse?.name || `WH #${row.warehouse_id}`,
        status: (row) => (
          <span className="px-2 py-0.5 text-xs font-semibold rounded-full uppercase bg-emerald-950 text-emerald-400 border border-emerald-800">
            {row.status}
          </span>
        ),
      }}
    />
  );
}

export default GoodsReceiptsPage;
