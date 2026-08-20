import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';

interface POLine {
  id: number;
  product_id: number;
  unit_id: number;
  quantity: string;
  unit_price: string;
  tax_rate: string;
  tax_amount: string;
  line_total: string;
  received_quantity: string;
  product?: { name: string; sku: string };
  unit?: { abbreviation: string };
}

interface PORow {
  id: number;
  po_number: string;
  company_id: number;
  branch_id: number;
  supplier_id: number;
  purchase_request_id: number | null;
  created_by: number;
  order_date: string;
  expected_delivery_date: string | null;
  status: string;
  notes: string | null;
  subtotal: string;
  tax_amount: string;
  total_amount: string;
  company?: { name: string };
  branch?: { name: string };
  supplier?: { name: string };
  lines?: POLine[];
}

export function PurchaseOrdersPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [search, setSearch] = useState('');
  
  const [modalOpen, setModalOpen] = useState(false);
  const [receiveModalOpen, setReceiveModalOpen] = useState(false);
  const [selectedPo, setSelectedPo] = useState<PORow | null>(null);

  // Creation State
  const [formData, setFormData] = useState({
    company_id: '',
    branch_id: '',
    supplier_id: '',
    order_date: new Date().toISOString().substring(0, 10),
    expected_delivery_date: '',
    notes: '',
  });
  const [formLines, setFormLines] = useState<Array<{ product_id: string; unit_id: string; quantity: string; unit_price: string; tax_rate: string }>>([
    { product_id: '', unit_id: '', quantity: '1', unit_price: '0.00', tax_rate: '0.18' },
  ]);

  // Receiving State
  const [receiveData, setReceiveData] = useState({
    warehouse_id: '',
    received_date: new Date().toISOString().substring(0, 10),
    notes: '',
    quantities: {} as Record<number, string>, // key = purchase_order_line_id
  });

  const [, setFormErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['purchase-orders', page, perPage, search],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/purchase-orders', {
        params: { page, per_page: perPage, search: search || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('purchase_orders.view'),
  });

  const { data: companies } = useQuery({
    queryKey: ['options', 'companies'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/companies');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  const { data: branches } = useQuery({
    queryKey: ['options', 'branches'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/branches');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  const { data: suppliers } = useQuery({
    queryKey: ['options', 'suppliers'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/suppliers');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  const { data: warehouses } = useQuery({
    queryKey: ['options', 'warehouses'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/warehouses');
      return res.data.data;
    },
    enabled: receiveModalOpen,
  });

  const { data: products } = useQuery({
    queryKey: ['options', 'products'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/products');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  const { data: units } = useQuery({
    queryKey: ['options', 'units'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/units');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  // Mutations
  const createMutation = useMutation({
    mutationFn: async () => {
      return apiClient.post('/api/v1/purchase-orders', {
        ...formData,
        lines: formLines,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] });
      setModalOpen(false);
      setFormData({ company_id: '', branch_id: '', supplier_id: '', order_date: new Date().toISOString().substring(0, 10), expected_delivery_date: '', notes: '' });
      setFormLines([{ product_id: '', unit_id: '', quantity: '1', unit_price: '0.00', tax_rate: '0.18' }]);
      setFormErrors({});
      setGeneralError(null);
    },
    onError: (err: any) => {
      if (err.errors) setFormErrors(err.errors);
      setGeneralError(err.message || 'Failed to create PO.');
    },
  });

  const transitionMutation = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: string }) => {
      return apiClient.post(`/api/v1/purchase-orders/${id}/${action}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] });
    },
    onError: (err: any) => {
      alert(err.message || 'Operation failed.');
    },
  });

  const receiveMutation = useMutation({
    mutationFn: async () => {
      if (!selectedPo) return;
      const lines = Object.entries(receiveData.quantities).map(([poLineId, qty]) => ({
        purchase_order_line_id: parseInt(poLineId),
        quantity_received: parseFloat(qty),
      }));
      return apiClient.post('/api/v1/goods-receipts', {
        purchase_order_id: selectedPo.id,
        warehouse_id: parseInt(receiveData.warehouse_id),
        received_date: receiveData.received_date,
        notes: receiveData.notes,
        lines,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] });
      queryClient.invalidateQueries({ queryKey: ['goods-receipts'] });
      queryClient.invalidateQueries({ queryKey: ['stock-balances'] });
      queryClient.invalidateQueries({ queryKey: ['stock-ledger'] });
      setReceiveModalOpen(false);
      setSelectedPo(null);
      setReceiveData({ warehouse_id: '', received_date: new Date().toISOString().substring(0, 10), notes: '', quantities: {} });
    },
    onError: (err: any) => {
      alert(err.message || 'Failed to process Goods Receipt.');
    },
  });

  const addLine = () => {
    setFormLines([...formLines, { product_id: '', unit_id: '', quantity: '1', unit_price: '0.00', tax_rate: '0.18' }]);
  };

  const removeLine = (idx: number) => {
    setFormLines(formLines.filter((_, i) => i !== idx));
  };

  const updateLine = (idx: number, key: string, value: string) => {
    const next = [...formLines];
    next[idx] = { ...next[idx], [key]: value };
    setFormLines(next);
  };

  // Computations for PO pricing preview
  const subtotal = formLines.reduce((sum, line) => {
    const qty = parseFloat(line.quantity) || 0;
    const price = parseFloat(line.unit_price) || 0;
    return sum + qty * price;
  }, 0);

  const tax = formLines.reduce((sum, line) => {
    const qty = parseFloat(line.quantity) || 0;
    const price = parseFloat(line.unit_price) || 0;
    const rate = parseFloat(line.tax_rate) || 0;
    return sum + qty * price * rate;
  }, 0);

  const total = subtotal + tax;

  // Table columns
  const columns = [
    { header: 'PO Number', accessor: (row: PORow) => <span className="font-semibold text-text-primary">{row.po_number}</span> },
    { header: 'Supplier', accessor: (row: PORow) => row.supplier?.name || `Supplier #${row.supplier_id}` },
    { header: 'Total Value', accessor: (row: PORow) => <span className="font-bold text-accent-400">${parseFloat(row.total_amount).toFixed(2)}</span> },
    { header: 'Order Date', accessor: (row: PORow) => row.order_date },
    {
      header: 'Status',
      accessor: (row: PORow) => {
        let style = 'bg-slate-950 text-slate-400 border-slate-800';
        if (row.status === 'submitted') style = 'bg-blue-950 text-blue-400 border border-blue-800';
        if (row.status === 'approved') style = 'bg-green-950 text-green-400 border border-green-800';
        if (row.status === 'sent') style = 'bg-teal-950 text-teal-400 border border-teal-800';
        if (row.status === 'partially_received') style = 'bg-amber-950 text-amber-400 border border-amber-800';
        if (row.status === 'fully_received') style = 'bg-emerald-950 text-emerald-400 border border-emerald-800';
        if (row.status === 'closed') style = 'bg-indigo-950 text-indigo-400 border border-indigo-800';
        if (row.status === 'cancelled') style = 'bg-red-950 text-red-400 border border-red-800';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded-full uppercase border ${style}`}>
            {row.status.replace('_', ' ')}
          </span>
        );
      },
    },
    {
      header: 'Actions',
      accessor: (row: PORow) => (
        <div className="flex gap-2">
          {row.status === 'draft' && hasPermission('purchase_orders.update') && (
            <button
              onClick={() => transitionMutation.mutate({ id: row.id, action: 'submit' })}
              disabled={transitionMutation.isPending}
              className="px-2 py-1 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded transition"
            >
              Submit
            </button>
          )}

          {row.status === 'submitted' && hasPermission('purchase_orders.approve') && (
            <>
              <button
                onClick={() => transitionMutation.mutate({ id: row.id, action: 'approve' })}
                disabled={transitionMutation.isPending}
                className="px-2 py-1 text-xs font-semibold text-white bg-green-600 hover:bg-green-500 rounded transition"
              >
                Approve
              </button>
              <button
                onClick={() => transitionMutation.mutate({ id: row.id, action: 'cancel' })}
                disabled={transitionMutation.isPending}
                className="px-2 py-1 text-xs font-semibold text-white bg-red-600 hover:bg-red-500 rounded transition"
              >
                Cancel
              </button>
            </>
          )}

          {row.status === 'approved' && hasPermission('purchase_orders.update') && (
            <button
              onClick={() => transitionMutation.mutate({ id: row.id, action: 'send' })}
              disabled={transitionMutation.isPending}
              className="px-2 py-1 text-xs font-semibold text-white bg-teal-600 hover:bg-teal-500 rounded transition"
            >
              Send to Supplier
            </button>
          )}

          {(row.status === 'sent' || row.status === 'partially_received') && hasPermission('goods_receipts.create') && (
            <button
              onClick={() => {
                setSelectedPo(row);
                // Load line variables with remaining to be received qty
                const qtyMap: Record<number, string> = {};
                row.lines?.forEach((l) => {
                  const rem = parseFloat(l.quantity) - parseFloat(l.received_quantity);
                  qtyMap[l.id] = String(rem > 0 ? rem : 0);
                });
                setReceiveData((prev) => ({ ...prev, quantities: qtyMap }));
                setReceiveModalOpen(true);
              }}
              className="px-2 py-1 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded transition"
            >
              Receive Goods
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h2 className="text-2xl font-bold text-text-primary">Purchase Orders</h2>
        <p className="text-sm text-text-secondary">Initiate supply agreements and verify incoming vendor goods.</p>
      </div>

      <DataTable<PORow>
        columns={columns}
        data={data?.data || []}
        isLoading={isLoading}
        searchPlaceholder="Search orders..."
        searchValue={search}
        onSearchChange={(val) => { setSearch(val); setPage(1); }}
        onAddClick={() => setModalOpen(true)}
        addButtonLabel="New PO"
        addButtonPermission={hasPermission('purchase_orders.create')}
        currentPage={data?.meta?.current_page || 1}
        lastPage={data?.meta?.last_page || 1}
        totalItems={data?.meta?.total || 0}
        perPage={perPage}
        onPageChange={setPage}
        onPerPageChange={(num) => { setPerPage(num); setPage(1); }}
      />

      {/* Creation Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="New Purchase Order">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            createMutation.mutate();
          }}
          className="flex flex-col gap-4 text-text-primary animate-in fade-in"
        >
          {generalError && (
            <div className="p-3 bg-red-950/50 border border-red-800 rounded-lg text-sm text-red-400">
              {generalError}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Company *</label>
              <select
                value={formData.company_id}
                onChange={(e) => setFormData({ ...formData, company_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Company</option>
                {companies?.map((c: any) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Branch *</label>
              <select
                value={formData.branch_id}
                onChange={(e) => setFormData({ ...formData, branch_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Branch</option>
                {branches?.map((b: any) => (
                  <option key={b.id} value={b.id}>{b.name}</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Supplier *</label>
              <select
                value={formData.supplier_id}
                onChange={(e) => setFormData({ ...formData, supplier_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Vendor</option>
                {suppliers?.map((s: any) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Order Date *</label>
              <input
                type="date"
                value={formData.order_date}
                onChange={(e) => setFormData({ ...formData, order_date: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Delivery Date</label>
              <input
                type="date"
                value={formData.expected_delivery_date}
                onChange={(e) => setFormData({ ...formData, expected_delivery_date: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
              />
            </div>

            <div className="flex flex-col gap-1.5 sm:col-span-3">
              <label className="text-xs font-semibold text-text-secondary uppercase">Notes</label>
              <textarea
                value={formData.notes}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                placeholder="Enter order terms or details..."
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm min-h-[50px]"
              />
            </div>

            {/* Line items Dynamic Grid */}
            <div className="sm:col-span-3 border-t border-nova-700 pt-4 flex flex-col gap-3">
              <div className="flex justify-between items-center">
                <span className="text-sm font-bold text-text-primary">Order Items</span>
                <button
                  type="button"
                  onClick={addLine}
                  className="px-2.5 py-1 text-xs font-semibold text-white bg-accent-500 hover:bg-accent-400 rounded transition flex items-center gap-1"
                >
                  <Icons.Plus size={12} /> Add Item
                </button>
              </div>

              {formLines.map((line, idx) => (
                <div key={idx} className="flex flex-col sm:flex-row gap-3 items-end bg-nova-900/40 p-3 rounded-lg border border-nova-700/50">
                  <div className="flex-1 flex flex-col gap-1.5 w-full">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Product *</label>
                    <select
                      value={line.product_id}
                      onChange={(e) => updateLine(idx, 'product_id', e.target.value)}
                      className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2 text-xs"
                      required
                    >
                      <option value="">Select Item</option>
                      {products?.map((p: any) => (
                        <option key={p.id} value={p.id}>{p.name} ({p.sku})</option>
                      ))}
                    </select>
                  </div>

                  <div className="flex-1 flex flex-col gap-1.5 w-full sm:max-w-[90px]">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Unit *</label>
                    <select
                      value={line.unit_id}
                      onChange={(e) => updateLine(idx, 'unit_id', e.target.value)}
                      className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2 text-xs"
                      required
                    >
                      <option value="">UOM</option>
                      {units?.map((u: any) => (
                        <option key={u.id} value={u.id}>{u.abbreviation}</option>
                      ))}
                    </select>
                  </div>

                  <div className="flex-1 flex flex-col gap-1.5 w-full sm:max-w-[90px]">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Quantity *</label>
                    <input
                      type="number"
                      step="0.0001"
                      value={line.quantity}
                      onChange={(e) => updateLine(idx, 'quantity', e.target.value)}
                      className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2 text-xs"
                      required
                    />
                  </div>

                  <div className="flex-1 flex flex-col gap-1.5 w-full sm:max-w-[100px]">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Price *</label>
                    <input
                      type="number"
                      step="0.01"
                      value={line.unit_price}
                      onChange={(e) => updateLine(idx, 'unit_price', e.target.value)}
                      className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2 text-xs"
                      required
                    />
                  </div>

                  <div className="flex-1 flex flex-col gap-1.5 w-full sm:max-w-[90px]">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Tax *</label>
                    <select
                      value={line.tax_rate}
                      onChange={(e) => updateLine(idx, 'tax_rate', e.target.value)}
                      className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2 text-xs"
                      required
                    >
                      <option value="0">0%</option>
                      <option value="0.05">5%</option>
                      <option value="0.12">12%</option>
                      <option value="0.18">18%</option>
                      <option value="0.28">28%</option>
                    </select>
                  </div>

                  {formLines.length > 1 && (
                    <button
                      type="button"
                      onClick={() => removeLine(idx)}
                      className="p-2 text-red-400 hover:bg-nova-700 rounded-lg"
                    >
                      <Icons.Trash size={16} />
                    </button>
                  )}
                </div>
              ))}
            </div>

            {/* Financial Summary */}
            <div className="sm:col-span-3 bg-nova-900 p-4 rounded-xl flex flex-col gap-2 border border-nova-700 text-right text-sm">
              <div>Subtotal Net: <span className="font-semibold">${subtotal.toFixed(2)}</span></div>
              <div>Sales Tax: <span className="font-semibold">${tax.toFixed(2)}</span></div>
              <div className="text-base font-bold text-accent-400 border-t border-nova-700 pt-2">
                Total Amount: ${total.toFixed(2)}
              </div>
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-nova-700">
            <button
              type="button"
              onClick={() => setModalOpen(false)}
              className="px-4 py-2 text-sm font-medium text-text-secondary bg-nova-700 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={createMutation.isPending}
              className="px-4 py-2 text-sm font-semibold text-white bg-accent-500 hover:bg-accent-400 rounded-lg flex items-center gap-1.5"
            >
              {createMutation.isPending && <Icons.Loader size={14} />}
              Generate Order
            </button>
          </div>
        </form>
      </Modal>

      {/* Receive Goods Modal */}
      <Modal isOpen={receiveModalOpen} onClose={() => setReceiveModalOpen(false)} title="Create Goods Receipt Note (GRN)">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            receiveMutation.mutate();
          }}
          className="flex flex-col gap-4 text-text-primary"
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Receiving Warehouse *</label>
              <select
                value={receiveData.warehouse_id}
                onChange={(e) => setReceiveData({ ...receiveData, warehouse_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Warehouse Location</option>
                {warehouses?.map((w: any) => (
                  <option key={w.id} value={w.id}>{w.name}</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Received Date *</label>
              <input
                type="date"
                value={receiveData.received_date}
                onChange={(e) => setReceiveData({ ...receiveData, received_date: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              />
            </div>

            <div className="flex flex-col gap-1.5 sm:col-span-2">
              <label className="text-xs font-semibold text-text-secondary uppercase">Receipt Notes</label>
              <textarea
                value={receiveData.notes}
                onChange={(e) => setReceiveData({ ...receiveData, notes: e.target.value })}
                placeholder="Enter inspection or shipping details..."
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm min-h-[60px]"
              />
            </div>

            {/* Receipt line items input */}
            <div className="sm:col-span-2 border-t border-nova-700 pt-4 flex flex-col gap-3">
              <span className="text-sm font-bold text-text-primary">Quantities to Receive</span>
              {selectedPo?.lines?.map((line) => (
                <div key={line.id} className="flex flex-col sm:flex-row gap-3 items-end bg-nova-900/40 p-3 rounded-lg border border-nova-700/50">
                  <div className="flex-1 text-xs">
                    <span className="font-semibold block">{line.product?.name}</span>
                    <span className="text-text-muted">Total Ordered: {parseFloat(line.quantity).toFixed(2)}</span>
                    <span className="text-text-secondary block">Already Received: {parseFloat(line.received_quantity).toFixed(2)}</span>
                  </div>

                  <div className="w-full sm:max-w-[140px] flex flex-col gap-1.5">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Qty Received *</label>
                    <input
                      type="number"
                      step="0.0001"
                      value={receiveData.quantities[line.id] || ''}
                      onChange={(e) => {
                        const qtys = { ...receiveData.quantities, [line.id]: e.target.value };
                        setReceiveData({ ...receiveData, quantities: qtys });
                      }}
                      className="w-full bg-nova-900 border border-nova-700 rounded p-1 text-xs"
                      required
                    />
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-nova-700">
            <button
              type="button"
              onClick={() => setReceiveModalOpen(false)}
              className="px-4 py-2 text-sm font-medium text-text-secondary bg-nova-700 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={receiveMutation.isPending}
              className="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-lg flex items-center gap-1.5"
            >
              {receiveMutation.isPending && <Icons.Loader size={14} />}
              Complete Receipt
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

export default PurchaseOrdersPage;
