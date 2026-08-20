/* eslint-disable @typescript-eslint/no-explicit-any */
 
/* eslint-disable react-hooks/set-state-in-effect */
import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';
import type { SalesReturn, Delivery, Warehouse } from '../../types/api';

export function SalesReturnsPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [statusFilter, setStatusFilter] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedReturn, setSelectedReturn] = useState<SalesReturn | null>(null);

  // Form states
  const [deliveryId, setDeliveryId] = useState('');
  const [warehouseId, setWarehouseId] = useState('');
  const [returnedDate, setReturnedDate] = useState(new Date().toISOString().substring(0, 10));
  const [reason, setReason] = useState('');
  
  const [deliveryLines, setDeliveryLines] = useState<any[]>([]);
  const [returnQuantities, setReturnQuantities] = useState<Record<number, string>>({}); // key = delivery_line_id
  const [returnNotes, setReturnNotes] = useState<Record<number, string>>({}); // key = delivery_line_id

  const [formError, setFormError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['sales-returns', page, perPage, statusFilter],
    queryFn: async () => {
      const res = await apiClient.get('/sales-returns', {
        params: { page, per_page: perPage, status: statusFilter || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('sales_returns.view'),
  });

  const { data: completedDeliveries } = useQuery({
    queryKey: ['options', 'completed-deliveries'],
    queryFn: async () => {
      const res = await apiClient.get('/deliveries');
      return (res.data?.data || []).filter((del: Delivery) => del.status === 'completed');
    },
    enabled: modalOpen,
  });

  const { data: warehouses } = useQuery({
    queryKey: ['options', 'warehouses'],
    queryFn: async () => {
      const res = await apiClient.get('/warehouses');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  // Fetch delivery lines when deliveryId changes
  useEffect(() => {
    if (!deliveryId) {
      setDeliveryLines([]);
      setReturnQuantities({});
      setReturnNotes({});
      return;
    }

    const fetchDeliveryDetails = async () => {
      try {
        const res = await apiClient.get(`/deliveries/${deliveryId}`);
        const lines = res.data.data.lines || [];
        setDeliveryLines(lines);

        const initialQtys: Record<number, string> = {};
        const initialNotes: Record<number, string> = {};
        lines.forEach((l: any) => {
          initialQtys[l.id] = '0';
          initialNotes[l.id] = '';
        });
        setReturnQuantities(initialQtys);
        setReturnNotes(initialNotes);
      } catch (err: any) {
        console.error('Failed to fetch delivery details', err);
      }
    };

    fetchDeliveryDetails();
  }, [deliveryId]);

  // Mutations
  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/sales-returns', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-returns'] });
      setModalOpen(false);
      resetForm();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Failed to create sales return');
    }
  });

  const approveMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/sales-returns/${id}/approve`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-returns'] });
      if (selectedReturn) setSelectedReturn(null);
    }
  });

  const completeMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/sales-returns/${id}/complete`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-returns'] });
      if (selectedReturn) setSelectedReturn(null);
    },
    onError: (err: any) => {
      alert(err.response?.data?.message || 'Completion failed');
    }
  });

  const resetForm = () => {
    setDeliveryId('');
    setWarehouseId('');
    setReturnedDate(new Date().toISOString().substring(0, 10));
    setReason('');
    setDeliveryLines([]);
    setReturnQuantities({});
    setReturnNotes({});
    setFormError(null);
  };

  const handleQtyChange = (lineId: number, val: string) => {
    setReturnQuantities({ ...returnQuantities, [lineId]: val });
  };

  const handleNoteChange = (lineId: number, val: string) => {
    setReturnNotes({ ...returnNotes, [lineId]: val });
  };

  const handleSubmitReturn = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!deliveryId || !warehouseId || !reason) {
      setFormError('Please select a Delivery Note, Warehouse, and provide a Reason.');
      return;
    }

    const linesPayload = Object.entries(returnQuantities)
      .map(([lineIdStr, qtyStr]) => ({
        delivery_line_id: parseInt(lineIdStr),
        quantity: parseFloat(qtyStr),
        notes: returnNotes[parseInt(lineIdStr)] || null,
      }))
      .filter(l => l.quantity > 0);

    if (linesPayload.length === 0) {
      setFormError('Must return at least one item with quantity greater than zero.');
      return;
    }

    createMutation.mutate({
      delivery_id: parseInt(deliveryId),
      warehouse_id: parseInt(warehouseId),
      returned_date: returnedDate,
      reason: reason,
      lines: linesPayload,
    });
  };

  const columns = [
    { header: 'Return Number', accessor: (row: SalesReturn) => <span className="font-semibold text-text-primary">{row.return_number}</span> },
    { header: 'Customer', accessor: (row: SalesReturn) => row.customer?.name || `ID #${row.customer_id}` },
    { header: 'Sales Order', accessor: (row: SalesReturn) => row.sales_order?.order_number || `SO #${row.sales_order_id}` },
    { header: 'Warehouse', accessor: (row: SalesReturn) => row.warehouse?.name || `WH #${row.warehouse_id}` },
    { header: 'Returned Date', accessor: (row: SalesReturn) => row.returned_date },
    {
      header: 'Status',
      accessor: (row: SalesReturn) => {
        let badgeColor = 'bg-zinc-800 text-zinc-300 border-zinc-700';
        if (row.status === 'approved') badgeColor = 'bg-blue-950 text-blue-400 border border-blue-900';
        if (row.status === 'completed') badgeColor = 'bg-emerald-950 text-emerald-400 border border-emerald-900';
        if (row.status === 'cancelled') badgeColor = 'bg-rose-950 text-rose-400 border border-rose-900';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded uppercase border ${badgeColor}`}>
            {row.status}
          </span>
        );
      }
    },
    {
      header: 'Actions',
      accessor: (row: SalesReturn) => (
        <div className="flex gap-2">
          <button
            onClick={() => setSelectedReturn(row)}
            className="p-1 text-zinc-400 hover:text-white transition-colors"
            title="View Details"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
          {row.status === 'draft' && hasPermission('sales_returns.approve') && (
            <button
              onClick={() => approveMutation.mutate(row.id)}
              className="px-2 py-0.5 text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded font-semibold transition-colors"
            >
              Approve
            </button>
          )}
          {row.status === 'approved' && hasPermission('sales_returns.approve') && (
            <button
              onClick={() => completeMutation.mutate(row.id)}
              className="px-2 py-0.5 text-xs bg-emerald-600 hover:bg-emerald-500 text-white rounded font-semibold transition-colors"
            >
              Complete
            </button>
          )}
        </div>
      )
    }
  ];

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white">Sales Returns</h1>
          <p className="text-sm text-zinc-400">Record customer product returns, inspect returned items, and restore inventory counts.</p>
        </div>
        {hasPermission('sales_returns.create') && (
          <button
            onClick={() => { resetForm(); setModalOpen(true); }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition-all shadow-lg hover:shadow-indigo-500/20"
          >
            <Icons.Plus className="h-4 w-4" /> Record Sales Return
          </button>
        )}
      </div>

      <div className="flex justify-between items-center bg-zinc-900/50 p-4 rounded-lg border border-zinc-800">
        <select
          value={statusFilter}
          onChange={(e) => { setStatusFilter(e.target.value); setPage(1); }}
          className="bg-zinc-950 border border-zinc-800 rounded px-3 py-1.5 text-sm text-zinc-300 focus:outline-none focus:border-indigo-500"
        >
          <option value="">All Statuses</option>
          <option value="draft">Draft</option>
          <option value="approved">Approved</option>
          <option value="completed">Completed</option>
          <option value="cancelled">Cancelled</option>
        </select>
      </div>

      <DataTable
        columns={columns}
        data={data?.data || []}
        isLoading={isLoading}
        currentPage={page}
        lastPage={data?.meta?.last_page || 1}
        onPageChange={setPage}
      />

      {/* Details Modal */}
      {selectedReturn && (
        <Modal
          isOpen={!!selectedReturn}
          onClose={() => setSelectedReturn(null)}
          title={`Sales Return Details: ${selectedReturn.return_number}`}
        >
          <div className="space-y-6 text-sm text-zinc-300">
            <div className="grid grid-cols-2 gap-4 bg-zinc-950 p-4 rounded-lg border border-zinc-850">
              <div>
                <p className="text-xs text-zinc-500">Customer</p>
                <p className="font-semibold text-white">{selectedReturn.customer?.name}</p>
                <p className="text-xs text-zinc-500 mt-2">Warehouse</p>
                <p className="font-semibold text-white">{selectedReturn.warehouse?.name}</p>
              </div>
              <div>
                <p className="text-xs text-zinc-500">Status</p>
                <p className="capitalize font-semibold text-white">{selectedReturn.status}</p>
                <p className="text-xs text-zinc-500 mt-2">Returned Date</p>
                <p>{selectedReturn.returned_date}</p>
                <p className="text-xs text-zinc-500 mt-2">Reason</p>
                <p className="text-white italic">"{selectedReturn.reason}"</p>
              </div>
            </div>

            <div>
              <h4 className="font-semibold text-white mb-2">Returned Items</h4>
              <table className="w-full text-left border-collapse bg-zinc-950/40 rounded border border-zinc-850">
                <thead>
                  <tr className="border-b border-zinc-850 text-zinc-400">
                    <th className="p-2">Product Name</th>
                    <th className="p-2 text-right">Returned Qty</th>
                    <th className="p-2">Notes</th>
                  </tr>
                </thead>
                <tbody>
                  {selectedReturn.lines?.map((line) => (
                    <tr key={line.id} className="border-b border-zinc-850/50">
                      <td className="p-2">
                        <div className="font-medium text-white">{line.product?.name}</div>
                        <div className="text-xs text-zinc-500">{line.product?.sku}</div>
                      </td>
                      <td className="p-2 text-right text-indigo-400 font-semibold">{parseFloat(line.quantity).toFixed(0)}</td>
                      <td className="p-2 text-zinc-400 text-xs">{line.notes || '—'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex justify-end gap-3 mt-6">
              {selectedReturn.status === 'draft' && hasPermission('sales_returns.approve') && (
                <button
                  onClick={() => approveMutation.mutate(selectedReturn.id)}
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded font-semibold transition-colors"
                >
                  Approve Return
                </button>
              )}
              {selectedReturn.status === 'approved' && hasPermission('sales_returns.approve') && (
                <button
                  onClick={() => completeMutation.mutate(selectedReturn.id)}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded font-semibold transition-colors"
                >
                  Complete Return & Restock
                </button>
              )}
              <button
                onClick={() => setSelectedReturn(null)}
                className="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-white rounded font-semibold transition-colors"
              >
                Close
              </button>
            </div>
          </div>
        </Modal>
      )}

      {/* Creation Modal */}
      {modalOpen && (
        <Modal
          isOpen={modalOpen}
          onClose={() => setModalOpen(false)}
          title="Record Customer Sales Return"
        >
          <form onSubmit={handleSubmitReturn} className="space-y-6 text-sm text-zinc-300">
            {formError && (
              <div className="p-3 bg-rose-950/50 border border-rose-800 text-rose-400 rounded">
                {formError}
              </div>
            )}

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Completed Delivery Note</label>
                <select
                  value={deliveryId}
                  onChange={(e) => setDeliveryId(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                >
                  <option value="">Select Delivery</option>
                  {completedDeliveries?.map((del: Delivery) => (
                    <option key={del.id} value={del.id}>{del.delivery_number} ({del.sales_order?.order_number})</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Restocking Warehouse</label>
                <select
                  value={warehouseId}
                  onChange={(e) => setWarehouseId(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                >
                  <option value="">Select Warehouse</option>
                  {warehouses?.map((wh: Warehouse) => (
                    <option key={wh.id} value={wh.id}>{wh.name} ({wh.warehouse_code})</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Returned Date</label>
                <input
                  type="date"
                  value={returnedDate}
                  onChange={(e) => setReturnedDate(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Return Reason</label>
                <input
                  type="text"
                  value={reason}
                  onChange={(e) => setReason(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                  placeholder="Defective goods, customer cancellation..."
                />
              </div>
            </div>

            {deliveryLines.length > 0 && (
              <div>
                <h4 className="font-semibold text-white mb-2">Delivery Line Items</h4>
                <table className="w-full text-left border-collapse bg-zinc-950/40 rounded border border-zinc-850">
                  <thead>
                    <tr className="border-b border-zinc-850 text-zinc-400">
                      <th className="p-2">Product</th>
                      <th className="p-2 text-right">Delivered Qty</th>
                      <th className="p-2 text-right w-32">Qty to Return</th>
                      <th className="p-2">Line Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    {deliveryLines.map((line) => {
                      const maxReturn = parseFloat(line.delivered_quantity);
                      return (
                        <tr key={line.id} className="border-b border-zinc-850/50">
                          <td className="p-2">
                            <div className="font-medium text-white">{line.product?.name}</div>
                            <div className="text-xs text-zinc-500">{line.product?.sku}</div>
                          </td>
                          <td className="p-2 text-right text-indigo-400 font-semibold">{maxReturn.toFixed(0)}</td>
                          <td className="p-2 text-right">
                            <input
                              type="number"
                              value={returnQuantities[line.id] || '0'}
                              onChange={(e) => handleQtyChange(line.id, e.target.value)}
                              className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-center text-white"
                              max={maxReturn}
                              min="0"
                            />
                          </td>
                          <td className="p-2">
                            <input
                              type="text"
                              value={returnNotes[line.id] || ''}
                              onChange={(e) => handleNoteChange(line.id, e.target.value)}
                              className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-white"
                              placeholder="Defect details..."
                            />
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}

            <div className="flex justify-end gap-3 pt-4 border-t border-zinc-800">
              <button
                type="button"
                onClick={() => setModalOpen(false)}
                className="px-4 py-2 bg-zinc-800 hover:bg-zinc-700 text-white rounded font-semibold transition-colors"
              >
                Cancel
              </button>
              <button
                type="submit"
                disabled={createMutation.isPending}
                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-semibold transition-colors disabled:opacity-50"
              >
                {createMutation.isPending ? 'Saving...' : 'Record Sales Return'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default SalesReturnsPage;
