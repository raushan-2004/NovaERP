/* eslint-disable @typescript-eslint/no-explicit-any */
 
/* eslint-disable react-hooks/set-state-in-effect */
import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';
import type { Delivery, SalesOrder, Warehouse } from '../../types/api';

export function DeliveriesPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [statusFilter, setStatusFilter] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedDelivery, setSelectedDelivery] = useState<Delivery | null>(null);

  // Form states
  const [salesOrderId, setSalesOrderId] = useState('');
  const [warehouseId, setWarehouseId] = useState('');
  const [deliveryDate, setDeliveryDate] = useState(new Date().toISOString().substring(0, 10));
  const [notes, setNotes] = useState('');
  
  const [soLines, setSoLines] = useState<any[]>([]);
  const [deliveryQuantities, setDeliveryQuantities] = useState<Record<number, string>>({}); // key = sales_order_line_id

  const [formError, setFormError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['deliveries', page, perPage, statusFilter],
    queryFn: async () => {
      const res = await apiClient.get('/deliveries', {
        params: { page, per_page: perPage, status: statusFilter || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('deliveries.view'),
  });

  const { data: salesOrders } = useQuery({
    queryKey: ['options', 'approved-sales-orders'],
    queryFn: async () => {
      const res = await apiClient.get('/sales-orders');
      // Filter only approved/partially_delivered
      return (res.data?.data || []).filter((so: SalesOrder) => ['approved', 'partially_delivered'].includes(so.status));
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

  // Fetch selected SO lines when salesOrderId changes
  useEffect(() => {
    if (!salesOrderId) {
      setSoLines([]);
      setDeliveryQuantities({});
      return;
    }

    const fetchSoDetails = async () => {
      try {
        const res = await apiClient.get(`/sales-orders/${salesOrderId}`);
        const lines = res.data.data.lines || [];
        setSoLines(lines);

        const initialQtys: Record<number, string> = {};
        lines.forEach((l: any) => {
          const remaining = parseFloat(l.quantity) - parseFloat(l.delivered_quantity);
          initialQtys[l.id] = remaining > 0 ? remaining.toString() : '0';
        });
        setDeliveryQuantities(initialQtys);
      } catch (err: any) {
        console.error('Failed to fetch sales order details', err);
      }
    };

    fetchSoDetails();
  }, [salesOrderId]);

  // Mutations
  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/deliveries', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['deliveries'] });
      setModalOpen(false);
      resetForm();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Failed to create delivery note');
    }
  });

  const completeMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/deliveries/${id}/complete`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['deliveries'] });
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] });
      if (selectedDelivery) setSelectedDelivery(null);
    },
    onError: (err: any) => {
      alert(err.response?.data?.message || 'Fulfillment/Complete failed');
    }
  });

  const resetForm = () => {
    setSalesOrderId('');
    setWarehouseId('');
    setDeliveryDate(new Date().toISOString().substring(0, 10));
    setNotes('');
    setSoLines([]);
    setDeliveryQuantities({});
    setFormError(null);
  };

  const handleQtyChange = (lineId: number, val: string) => {
    setDeliveryQuantities({ ...deliveryQuantities, [lineId]: val });
  };

  const handleSubmitDelivery = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!salesOrderId || !warehouseId) {
      setFormError('Please select a Sales Order and Warehouse.');
      return;
    }

    const linesPayload = Object.entries(deliveryQuantities)
      .map(([lineIdStr, qtyStr]) => ({
        sales_order_line_id: parseInt(lineIdStr),
        quantity: parseFloat(qtyStr),
      }))
      .filter(l => l.quantity > 0);

    if (linesPayload.length === 0) {
      setFormError('Must deliver at least one item with quantity greater than zero.');
      return;
    }

    createMutation.mutate({
      sales_order_id: parseInt(salesOrderId),
      warehouse_id: parseInt(warehouseId),
      delivery_date: deliveryDate,
      notes: notes || null,
      lines: linesPayload,
    });
  };

  const columns = [
    { header: 'Delivery Number', accessor: (row: Delivery) => <span className="font-semibold text-text-primary">{row.delivery_number}</span> },
    { header: 'Sales Order', accessor: (row: Delivery) => row.sales_order?.order_number || `SO #${row.sales_order_id}` },
    { header: 'Customer', accessor: (row: Delivery) => row.customer?.name || `ID #${row.customer_id}` },
    { header: 'Warehouse', accessor: (row: Delivery) => row.warehouse?.name || `WH #${row.warehouse_id}` },
    { header: 'Delivery Date', accessor: (row: Delivery) => row.delivery_date },
    {
      header: 'Status',
      accessor: (row: Delivery) => {
        let badgeColor = 'bg-zinc-800 text-zinc-300 border-zinc-705';
        if (row.status === 'completed') badgeColor = 'bg-emerald-950 text-emerald-400 border border-emerald-900';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded uppercase border ${badgeColor}`}>
            {row.status}
          </span>
        );
      }
    },
    {
      header: 'Actions',
      accessor: (row: Delivery) => (
        <div className="flex gap-2">
          <button
            onClick={() => setSelectedDelivery(row)}
            className="p-1 text-zinc-400 hover:text-white transition-colors"
            title="View Details"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
          {row.status === 'draft' && hasPermission('deliveries.complete') && (
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
          <h1 className="text-2xl font-bold tracking-tight text-white">Goods Deliveries</h1>
          <p className="text-sm text-zinc-400">Create delivery notes, dispatch goods from warehouses, and update stock ledgers.</p>
        </div>
        {hasPermission('deliveries.create') && (
          <button
            onClick={() => { resetForm(); setModalOpen(true); }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition-all shadow-lg hover:shadow-indigo-500/20"
          >
            <Icons.Plus className="h-4 w-4" /> New Delivery Note
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
          <option value="completed">Completed</option>
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
      {selectedDelivery && (
        <Modal
          isOpen={!!selectedDelivery}
          onClose={() => setSelectedDelivery(null)}
          title={`Delivery details: ${selectedDelivery.delivery_number}`}
        >
          <div className="space-y-6 text-sm text-zinc-300">
            <div className="grid grid-cols-2 gap-4 bg-zinc-950 p-4 rounded-lg border border-zinc-850">
              <div>
                <p className="text-xs text-zinc-500">Sales Order</p>
                <p className="font-semibold text-white">{selectedDelivery.sales_order?.order_number}</p>
                <p className="text-xs text-zinc-500 mt-2">Warehouse</p>
                <p className="font-semibold text-white">{selectedDelivery.warehouse?.name}</p>
              </div>
              <div>
                <p className="text-xs text-zinc-500">Status</p>
                <p className="capitalize font-semibold text-white">{selectedDelivery.status}</p>
                <p className="text-xs text-zinc-500 mt-2">Delivery Date</p>
                <p>{selectedDelivery.delivery_date}</p>
              </div>
            </div>

            <div>
              <h4 className="font-semibold text-white mb-2">Dispatched Items</h4>
              <table className="w-full text-left border-collapse bg-zinc-950/40 rounded border border-zinc-850">
                <thead>
                  <tr className="border-b border-zinc-850 text-zinc-400">
                    <th className="p-2">Product Name</th>
                    <th className="p-2 text-right">Ordered Qty</th>
                    <th className="p-2 text-right">Delivered Qty</th>
                  </tr>
                </thead>
                <tbody>
                  {selectedDelivery.lines?.map((line) => (
                    <tr key={line.id} className="border-b border-zinc-850/50">
                      <td className="p-2">
                        <div className="font-medium text-white">{line.product?.name}</div>
                        <div className="text-xs text-zinc-500">{line.product?.sku}</div>
                      </td>
                      <td className="p-2 text-right">{parseFloat(line.ordered_quantity).toFixed(0)}</td>
                      <td className="p-2 text-right text-emerald-400 font-semibold">{parseFloat(line.delivered_quantity).toFixed(0)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex justify-end gap-3 mt-6">
              {selectedDelivery.status === 'draft' && hasPermission('deliveries.complete') && (
                <button
                  onClick={() => completeMutation.mutate(selectedDelivery.id)}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded font-semibold transition-colors"
                >
                  Complete & Issue Stock
                </button>
              )}
              <button
                onClick={() => setSelectedDelivery(null)}
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
          title="Create Delivery Note"
        >
          <form onSubmit={handleSubmitDelivery} className="space-y-6 text-sm text-zinc-300">
            {formError && (
              <div className="p-3 bg-rose-950/50 border border-rose-800 text-rose-400 rounded">
                {formError}
              </div>
            )}

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Approved Sales Order</label>
                <select
                  value={salesOrderId}
                  onChange={(e) => setSalesOrderId(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                >
                  <option value="">Select Sales Order</option>
                  {salesOrders?.map((so: SalesOrder) => (
                    <option key={so.id} value={so.id}>{so.order_number} ({so.customer?.name})</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Source Warehouse</label>
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

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Delivery Date</label>
                <input
                  type="date"
                  value={deliveryDate}
                  onChange={(e) => setDeliveryDate(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                />
              </div>
            </div>

            {soLines.length > 0 && (
              <div>
                <h4 className="font-semibold text-white mb-2">Order Line Items</h4>
                <table className="w-full text-left border-collapse bg-zinc-950/40 rounded border border-zinc-850">
                  <thead>
                    <tr className="border-b border-zinc-850 text-zinc-400">
                      <th className="p-2">Product</th>
                      <th className="p-2 text-right">Ordered Qty</th>
                      <th className="p-2 text-right">Delivered Qty</th>
                      <th className="p-2 text-right">Remaining Qty</th>
                      <th className="p-2 text-right w-32">Qty to Deliver</th>
                    </tr>
                  </thead>
                  <tbody>
                    {soLines.map((line) => {
                      const remaining = parseFloat(line.quantity) - parseFloat(line.delivered_quantity);
                      return (
                        <tr key={line.id} className="border-b border-zinc-850/50">
                          <td className="p-2">
                            <div className="font-medium text-white">{line.product?.name}</div>
                            <div className="text-xs text-zinc-500">{line.product?.sku}</div>
                          </td>
                          <td className="p-2 text-right">{parseFloat(line.quantity).toFixed(0)}</td>
                          <td className="p-2 text-right text-zinc-500">{parseFloat(line.delivered_quantity).toFixed(0)}</td>
                          <td className="p-2 text-right text-indigo-400 font-semibold">{remaining.toFixed(0)}</td>
                          <td className="p-2 text-right">
                            <input
                              type="number"
                              value={deliveryQuantities[line.id] || '0'}
                              onChange={(e) => handleQtyChange(line.id, e.target.value)}
                              className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-center text-white"
                              max={remaining}
                              min="0"
                            />
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}

            <div>
              <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Notes</label>
              <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white h-20 focus:outline-none focus:border-indigo-500"
                placeholder="Fulfillment status notes..."
              />
            </div>

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
                {createMutation.isPending ? 'Saving...' : 'Create Delivery Note'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default DeliveriesPage;
