/* eslint-disable @typescript-eslint/no-explicit-any */
/* eslint-disable react-hooks/set-state-in-effect */
/* eslint-disable react-hooks/exhaustive-deps */
import { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';

interface ReturnLine {
  id: number;
  product_id: number;
  quantity_returned: string;
  notes: string | null;
  product?: { name: string; sku: string };
}

interface ReturnRow {
  id: number;
  return_number: string;
  goods_receipt_id: number;
  supplier_id: number;
  returned_by: number;
  return_date: string;
  reason: string;
  status: string;
  goods_receipt?: { grn_number: string };
  supplier?: { name: string };
  lines?: ReturnLine[];
}

export function PurchaseReturnsPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);

  const [formData, setFormData] = useState({
    goods_receipt_id: '',
    return_date: new Date().toISOString().substring(0, 10),
    reason: '',
  });

  const [grnLines, setGrnLines] = useState<Array<{ id: number; product: { name: string; sku: string }; quantity_received: string }>>([]);
  const [returnQuantities, setReturnQuantities] = useState<Record<number, string>>({}); // key = goods_receipt_line_id
  const [returnNotes, setReturnNotes] = useState<Record<number, string>>({}); // key = goods_receipt_line_id

  const [, setFormErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['purchase-returns', page, perPage, search],
    queryFn: async () => {
      const res = await apiClient.get('/purchase-returns', {
        params: { page, per_page: perPage, search: search || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('purchase_returns.view'),
  });

  const { data: grns } = useQuery({
    queryKey: ['options', 'goods-receipts'],
    queryFn: async () => {
      const res = await apiClient.get('/goods-receipts');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  // Fetch selected GRN lines when goods_receipt_id changes
  useEffect(() => {
    if (!formData.goods_receipt_id) {
      if (grnLines.length > 0) {
        setGrnLines([]);
      }
      return;
    }
    const fetchGrnDetails = async () => {
      try {
        const res = await apiClient.get(`/goods-receipts/${formData.goods_receipt_id}`);
        const lines = res.data.data.lines || [];
        setGrnLines(lines);


        const initialQtys: Record<number, string> = {};
        const initialNotes: Record<number, string> = {};
        lines.forEach((l: any) => {
          initialQtys[l.id] = '0';
          initialNotes[l.id] = '';
        });
        setReturnQuantities(initialQtys);
        setReturnNotes(initialNotes);
      } catch (err) {
        console.error('Failed to load GRN lines', err);
      }
    };
    fetchGrnDetails();
  }, [formData.goods_receipt_id]);

  // Mutations
  const createMutation = useMutation({
    mutationFn: async () => {
      const lines = Object.entries(returnQuantities)
        .filter(([_, qty]) => parseFloat(qty) > 0)
        .map(([grnLineId, qty]) => ({
          goods_receipt_line_id: parseInt(grnLineId),
          quantity_returned: parseFloat(qty),
          notes: returnNotes[parseInt(grnLineId)] || null,
        }));

      return apiClient.post('/purchase-returns', {
        ...formData,
        lines,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchase-returns'] });
      queryClient.invalidateQueries({ queryKey: ['stock-balances'] });
      queryClient.invalidateQueries({ queryKey: ['stock-ledger'] });
      setModalOpen(false);
      setFormData({ goods_receipt_id: '', return_date: new Date().toISOString().substring(0, 10), reason: '' });
      setGrnLines([]);
      setFormErrors({});
      setGeneralError(null);
    },
    onError: (err: any) => {
      if (err.errors) setFormErrors(err.errors);
      setGeneralError(err.message || 'Failed to process purchase return.');
    },
  });

  const columns = [
    { header: 'Return No.', accessor: (row: ReturnRow) => <span className="font-semibold text-text-primary">{row.return_number}</span> },
    { header: 'GRN Number', accessor: (row: ReturnRow) => row.goods_receipt?.grn_number || `GRN #${row.goods_receipt_id}` },
    { header: 'Supplier', accessor: (row: ReturnRow) => row.supplier?.name || `Supplier #${row.supplier_id}` },
    { header: 'Return Date', accessor: (row: ReturnRow) => row.return_date },
    { header: 'Reason', accessor: (row: ReturnRow) => <span className="text-text-secondary truncate max-w-[200px] inline-block">{row.reason}</span> },
    {
      header: 'Status',
      accessor: (row: ReturnRow) => (
        <span className="px-2 py-0.5 text-xs font-semibold rounded-full uppercase bg-emerald-950 text-emerald-400 border border-emerald-800">
          {row.status}
        </span>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h2 className="text-2xl font-bold text-text-primary">Purchase Returns</h2>
        <p className="text-sm text-text-secondary">Process supplier returns for damaged, defective, or excess materials.</p>
      </div>

      <DataTable<ReturnRow>
        columns={columns}
        data={data?.data || []}
        isLoading={isLoading}
        searchPlaceholder="Search returns..."
        searchValue={search}
        onSearchChange={(val) => { setSearch(val); setPage(1); }}
        onAddClick={() => setModalOpen(true)}
        addButtonLabel="New Return"
        addButtonPermission={hasPermission('purchase_returns.create')}
        currentPage={data?.meta?.current_page || 1}
        lastPage={data?.meta?.last_page || 1}
        totalItems={data?.meta?.total || 0}
        perPage={perPage}
        onPageChange={setPage}
        onPerPageChange={(num) => { setPerPage(num); setPage(1); }}
      />

      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="New Purchase Return">
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
            <div className="flex flex-col gap-1.5 sm:col-span-2">
              <label className="text-xs font-semibold text-text-secondary uppercase">Select Goods Receipt Note (GRN) *</label>
              <select
                value={formData.goods_receipt_id}
                onChange={(e) => setFormData({ ...formData, goods_receipt_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Completed GRN</option>
                {grns?.filter((g: any) => g.status === 'completed')?.map((g: any) => (
                  <option key={g.id} value={g.id}>{g.grn_number} (PO: {g.purchase_order?.po_number || g.purchase_order_id})</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Return Date *</label>
              <input
                type="date"
                value={formData.return_date}
                onChange={(e) => setFormData({ ...formData, return_date: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              />
            </div>

            <div className="flex flex-col gap-1.5 sm:col-span-2">
              <label className="text-xs font-semibold text-text-secondary uppercase">Reason for Return *</label>
              <textarea
                value={formData.reason}
                onChange={(e) => setFormData({ ...formData, reason: e.target.value })}
                placeholder="Explain why these items are being returned to the vendor..."
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm min-h-[60px]"
                required
              />
            </div>

            {/* GRN items selection */}
            {grnLines.length > 0 && (
              <div className="sm:col-span-2 border-t border-nova-700 pt-4 flex flex-col gap-3">
                <span className="text-sm font-bold text-text-primary">Items to Return</span>
                {grnLines.map((line) => (
                  <div key={line.id} className="flex flex-col sm:flex-row gap-3 items-end bg-nova-900/40 p-3 rounded-lg border border-nova-700/50">
                    <div className="flex-1 text-xs">
                      <span className="font-semibold block">{line.product?.name}</span>
                      <span className="text-text-secondary">Quantity Received: {parseFloat(line.quantity_received).toFixed(2)}</span>
                    </div>

                    <div className="w-full sm:max-w-[140px] flex flex-col gap-1.5">
                      <label className="text-[10px] font-bold text-text-muted uppercase">Qty to Return</label>
                      <input
                        type="number"
                        step="0.0001"
                        value={returnQuantities[line.id] || '0'}
                        onChange={(e) => setReturnQuantities({ ...returnQuantities, [line.id]: e.target.value })}
                        className="w-full bg-nova-900 border border-nova-700 rounded p-1 text-xs"
                        required
                      />
                    </div>

                    <div className="flex-1 flex flex-col gap-1.5 w-full">
                      <label className="text-[10px] font-bold text-text-muted uppercase">Notes</label>
                      <input
                        type="text"
                        value={returnNotes[line.id] || ''}
                        onChange={(e) => setReturnNotes({ ...returnNotes, [line.id]: e.target.value })}
                        placeholder="Condition, batch, etc."
                        className="w-full bg-nova-900 border border-nova-700 rounded p-1 text-xs"
                      />
                    </div>
                  </div>
                ))}
              </div>
            )}
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
              Process Return
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

export default PurchaseReturnsPage;
