import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';

interface StockTransferRow {
  id: number;
  transfer_number: string;
  from_warehouse_id: number;
  to_warehouse_id: number;
  product_id: number;
  quantity: string;
  status: string;
  transferred_by: number;
  transferred_at: string | null;
  notes: string | null;
  from_warehouse?: { name: string };
  to_warehouse?: { name: string };
  product?: { name: string; sku: string };
}

export function StockTransfersPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);

  const [formData, setFormData] = useState({
    from_warehouse_id: '',
    to_warehouse_id: '',
    product_id: '',
    quantity: '',
    notes: '',
  });
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['stock-transfers', page, perPage, search],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/stock-transfers', {
        params: { page, per_page: perPage, search: search || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('inventory.view'),
  });

  const { data: warehouses } = useQuery({
    queryKey: ['options', 'warehouses'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/warehouses');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  const { data: products } = useQuery({
    queryKey: ['options', 'products'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/products');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  // Mutations
  const createMutation = useMutation({
    mutationFn: async () => {
      return apiClient.post('/api/v1/stock-transfers', formData);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stock-transfers'] });
      setModalOpen(false);
      setFormData({ from_warehouse_id: '', to_warehouse_id: '', product_id: '', quantity: '', notes: '' });
      setFormErrors({});
      setGeneralError(null);
    },
    onError: (err: any) => {
      if (err.errors) setFormErrors(err.errors);
      setGeneralError(err.message || 'Failed to create transfer.');
    },
  });

  const completeMutation = useMutation({
    mutationFn: async (id: number) => {
      return apiClient.post(`/api/v1/stock-transfers/${id}/complete`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stock-transfers'] });
      queryClient.invalidateQueries({ queryKey: ['stock-balances'] });
      queryClient.invalidateQueries({ queryKey: ['stock-ledger'] });
    },
    onError: (err: any) => {
      alert(err.message || 'Failed to complete transfer.');
    },
  });

  const cancelMutation = useMutation({
    mutationFn: async (id: number) => {
      return apiClient.post(`/api/v1/stock-transfers/${id}/cancel`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['stock-transfers'] });
    },
    onError: (err: any) => {
      alert(err.message || 'Failed to cancel transfer.');
    },
  });

  // Table structure
  const columns = [
    { header: 'Transfer No.', accessor: (row: StockTransferRow) => <span className="font-semibold text-text-primary">{row.transfer_number}</span> },
    { header: 'From Warehouse', accessor: (row: StockTransferRow) => row.from_warehouse?.name || `WH #${row.from_warehouse_id}` },
    { header: 'To Warehouse', accessor: (row: StockTransferRow) => row.to_warehouse?.name || `WH #${row.to_warehouse_id}` },
    { header: 'Product', accessor: (row: StockTransferRow) => row.product ? `${row.product.name} (${row.product.sku})` : `Prod #${row.product_id}` },
    { header: 'Quantity', accessor: (row: StockTransferRow) => parseFloat(row.quantity).toFixed(4) },
    {
      header: 'Status',
      accessor: (row: StockTransferRow) => {
        let style = 'bg-slate-950 text-slate-400 border-slate-800';
        if (row.status === 'completed') style = 'bg-green-950 text-green-400 border border-green-800';
        if (row.status === 'cancelled') style = 'bg-red-950 text-red-400 border border-red-800';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded-full uppercase border ${style}`}>
            {row.status}
          </span>
        );
      },
    },
    {
      header: 'Actions',
      accessor: (row: StockTransferRow) => {
        if (row.status !== 'draft' || !hasPermission('inventory.adjust')) return null;
        return (
          <div className="flex gap-2">
            <button
              onClick={() => {
                if (confirm('Complete this stock transfer? This will deduct from source and add to destination.')) {
                  completeMutation.mutate(row.id);
                }
              }}
              disabled={completeMutation.isPending}
              className="px-2 py-1 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded transition"
            >
              Complete
            </button>
            <button
              onClick={() => {
                if (confirm('Cancel this stock transfer?')) {
                  cancelMutation.mutate(row.id);
                }
              }}
              disabled={cancelMutation.isPending}
              className="px-2 py-1 text-xs font-semibold text-white bg-red-600 hover:bg-red-500 rounded transition"
            >
              Cancel
            </button>
          </div>
        );
      },
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h2 className="text-2xl font-bold text-text-primary">Stock Transfers</h2>
        <p className="text-sm text-text-secondary">Move stock from one warehouse location to another atomically.</p>
      </div>

      <DataTable<StockTransferRow>
        columns={columns}
        data={data?.data || []}
        isLoading={isLoading}
        searchPlaceholder="Search transfers..."
        searchValue={search}
        onSearchChange={(val) => { setSearch(val); setPage(1); }}
        onAddClick={() => setModalOpen(true)}
        addButtonLabel="New Transfer"
        addButtonPermission={hasPermission('inventory.adjust')}
        currentPage={data?.meta?.current_page || 1}
        lastPage={data?.meta?.last_page || 1}
        totalItems={data?.meta?.total || 0}
        perPage={perPage}
        onPageChange={setPage}
        onPerPageChange={(num) => { setPerPage(num); setPage(1); }}
      />

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="New Stock Transfer">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            createMutation.mutate();
          }}
          className="flex flex-col gap-4 text-text-primary"
        >
          {generalError && (
            <div className="p-3 bg-red-950/50 border border-red-800 rounded-lg text-sm text-red-400">
              {generalError}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">From Warehouse *</label>
              <select
                value={formData.from_warehouse_id}
                onChange={(e) => setFormData({ ...formData, from_warehouse_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Source Warehouse</option>
                {warehouses?.map((wh: any) => (
                  <option key={wh.id} value={wh.id}>{wh.name}</option>
                ))}
              </select>
              {formErrors.from_warehouse_id && <span className="text-xs text-red-400">{formErrors.from_warehouse_id[0]}</span>}
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">To Warehouse *</label>
              <select
                value={formData.to_warehouse_id}
                onChange={(e) => setFormData({ ...formData, to_warehouse_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Destination Warehouse</option>
                {warehouses?.map((wh: any) => (
                  <option key={wh.id} value={wh.id}>{wh.name}</option>
                ))}
              </select>
              {formErrors.to_warehouse_id && <span className="text-xs text-red-400">{formErrors.to_warehouse_id[0]}</span>}
            </div>

            <div className="flex flex-col gap-1.5 sm:col-span-2">
              <label className="text-xs font-semibold text-text-secondary uppercase">Product *</label>
              <select
                value={formData.product_id}
                onChange={(e) => setFormData({ ...formData, product_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Product to Transfer</option>
                {products?.filter((p: any) => p.track_inventory)?.map((p: any) => (
                  <option key={p.id} value={p.id}>{p.name} ({p.sku})</option>
                ))}
              </select>
              {formErrors.product_id && <span className="text-xs text-red-400">{formErrors.product_id[0]}</span>}
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Quantity *</label>
              <input
                type="number"
                step="0.0001"
                value={formData.quantity}
                onChange={(e) => setFormData({ ...formData, quantity: e.target.value })}
                placeholder="0.0000"
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              />
              {formErrors.quantity && <span className="text-xs text-red-400">{formErrors.quantity[0]}</span>}
            </div>

            <div className="flex flex-col gap-1.5 sm:col-span-2">
              <label className="text-xs font-semibold text-text-secondary uppercase">Notes</label>
              <textarea
                value={formData.notes}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                placeholder="Enter movement details..."
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm min-h-[80px]"
              />
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
              Initiate Transfer
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

export default StockTransfersPage;
