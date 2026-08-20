/* eslint-disable @typescript-eslint/no-explicit-any */
import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';
import type { SalesOrder, Customer, Product } from '../../types/api';

export function SalesOrdersPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [statusFilter, setStatusFilter] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedOrder, setSelectedOrder] = useState<SalesOrder | null>(null);

  // Form states (for direct SO creation)
  const [customerId, setCustomerId] = useState('');
  const [orderDate, setOrderDate] = useState(new Date().toISOString().substring(0, 10));
  const [expectedDeliveryDate, setExpectedDeliveryDate] = useState('');
  const [notes, setNotes] = useState('');
  const [lines, setLines] = useState<Array<{ product_id: string; quantity: string; unit_price: string; discount: string; tax_rate: string }>>([
    { product_id: '', quantity: '1', unit_price: '0.00', discount: '0.00', tax_rate: '0.18' }
  ]);

  const [formError, setFormError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['sales-orders', page, perPage, statusFilter],
    queryFn: async () => {
      const res = await apiClient.get('/sales-orders', {
        params: { page, per_page: perPage, status: statusFilter || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('sales_orders.view'),
  });

  const { data: customers } = useQuery({
    queryKey: ['options', 'customers'],
    queryFn: async () => {
      const res = await apiClient.get('/customers');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  const { data: products } = useQuery({
    queryKey: ['options', 'products'],
    queryFn: async () => {
      const res = await apiClient.get('/products');
      return res.data.data;
    },
    enabled: modalOpen,
  });

  // Mutations
  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/sales-orders', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] });
      setModalOpen(false);
      resetForm();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Failed to create sales order');
    }
  });

  const submitMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/sales-orders/${id}/submit`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] });
      if (selectedOrder) setSelectedOrder(null);
    }
  });

  const approveMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/sales-orders/${id}/approve`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] });
      if (selectedOrder) setSelectedOrder(null);
    }
  });

  const cancelMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/sales-orders/${id}/cancel`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] });
      if (selectedOrder) setSelectedOrder(null);
    }
  });

  const resetForm = () => {
    setCustomerId('');
    setOrderDate(new Date().toISOString().substring(0, 10));
    setExpectedDeliveryDate('');
    setNotes('');
    setLines([{ product_id: '', quantity: '1', unit_price: '0.00', discount: '0.00', tax_rate: '0.18' }]);
    setFormError(null);
  };

  const handleAddLine = () => {
    setLines([...lines, { product_id: '', quantity: '1', unit_price: '0.00', discount: '0.00', tax_rate: '0.18' }]);
  };

  const handleRemoveLine = (idx: number) => {
    if (lines.length > 1) {
      setLines(lines.filter((_, i) => i !== idx));
    }
  };

  const handleLineChange = (idx: number, field: string, value: string) => {
    const updated = [...lines];
    updated[idx] = { ...updated[idx], [field]: value };
    setLines(updated);
  };

  const handleSubmitOrder = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!customerId) {
      setFormError('Please select a customer.');
      return;
    }

    if (lines.some(l => !l.product_id || parseFloat(l.quantity) <= 0)) {
      setFormError('Please select products and ensure quantities are greater than zero.');
      return;
    }

    createMutation.mutate({
      customer_id: parseInt(customerId),
      order_date: orderDate,
      expected_delivery_date: expectedDeliveryDate || null,
      notes: notes || null,
      lines: lines.map(l => ({
        product_id: parseInt(l.product_id),
        quantity: parseFloat(l.quantity),
        unit_price: parseFloat(l.unit_price),
        discount: parseFloat(l.discount),
        tax_rate: parseFloat(l.tax_rate),
      }))
    });
  };

  const columns = [
    { header: 'Order Number', accessor: (row: SalesOrder) => <span className="font-semibold text-text-primary">{row.order_number}</span> },
    { header: 'Customer', accessor: (row: SalesOrder) => row.customer?.name || `ID #${row.customer_id}` },
    { header: 'Order Date', accessor: (row: SalesOrder) => row.order_date },
    { header: 'Total', accessor: (row: SalesOrder) => <span className="font-semibold">${parseFloat(row.total).toFixed(2)}</span> },
    {
      header: 'Status',
      accessor: (row: SalesOrder) => {
        let badgeColor = 'bg-zinc-800 text-zinc-300 border-zinc-700';
        if (row.status === 'submitted') badgeColor = 'bg-blue-950 text-blue-400 border border-blue-900';
        if (row.status === 'approved') badgeColor = 'bg-indigo-950 text-indigo-400 border border-indigo-900';
        if (row.status === 'partially_delivered') badgeColor = 'bg-orange-950 text-orange-400 border border-orange-900';
        if (row.status === 'fully_delivered') badgeColor = 'bg-emerald-950 text-emerald-400 border border-emerald-900';
        if (row.status === 'cancelled') badgeColor = 'bg-rose-950 text-rose-400 border border-rose-900';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded uppercase border ${badgeColor}`}>
            {row.status.replace('_', ' ')}
          </span>
        );
      }
    },
    {
      header: 'Actions',
      accessor: (row: SalesOrder) => (
        <div className="flex gap-2">
          <button
            onClick={() => setSelectedOrder(row)}
            className="p-1 text-zinc-400 hover:text-white transition-colors"
            title="View Details"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
          {row.status === 'draft' && hasPermission('sales_orders.update') && (
            <button
              onClick={() => submitMutation.mutate(row.id)}
              className="px-2 py-0.5 text-xs bg-blue-600 hover:bg-blue-500 text-white rounded transition-colors"
            >
              Submit
            </button>
          )}
          {row.status === 'submitted' && hasPermission('sales_orders.approve') && (
            <button
              onClick={() => approveMutation.mutate(row.id)}
              className="px-2 py-0.5 text-xs bg-emerald-600 hover:bg-emerald-500 text-white rounded transition-colors font-medium"
            >
              Approve
            </button>
          )}
          {['draft', 'submitted', 'approved'].includes(row.status) && hasPermission('sales_orders.update') && (
            <button
              onClick={() => cancelMutation.mutate(row.id)}
              className="px-2 py-0.5 text-xs bg-zinc-800 hover:bg-zinc-700 text-rose-400 rounded transition-colors"
            >
              Cancel
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
          <h1 className="text-2xl font-bold tracking-tight text-white">Sales Orders</h1>
          <p className="text-sm text-zinc-400">Manage client purchase orders, track fulfillment states, and initiate deliveries.</p>
        </div>
        {hasPermission('sales_orders.create') && (
          <button
            onClick={() => { resetForm(); setModalOpen(true); }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition-all shadow-lg hover:shadow-indigo-500/20"
          >
            <Icons.Plus className="h-4 w-4" /> Direct Sales Order
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
          <option value="submitted">Submitted</option>
          <option value="approved">Approved</option>
          <option value="partially_delivered">Partially Delivered</option>
          <option value="fully_delivered">Fully Delivered</option>
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
      {selectedOrder && (
        <Modal
          isOpen={!!selectedOrder}
          onClose={() => setSelectedOrder(null)}
          title={`Sales Order Details: ${selectedOrder.order_number}`}
        >
          <div className="space-y-6 text-sm text-zinc-300">
            <div className="grid grid-cols-2 gap-4 bg-zinc-950 p-4 rounded-lg border border-zinc-850">
              <div>
                <p className="text-xs text-zinc-500">Customer</p>
                <p className="font-semibold text-white">{selectedOrder.customer?.name}</p>
                <p className="text-zinc-400">{selectedOrder.customer?.email || 'No email'}</p>
              </div>
              <div>
                <p className="text-xs text-zinc-500">Status</p>
                <p className="capitalize font-semibold text-white">{selectedOrder.status.replace('_', ' ')}</p>
                <p className="text-xs text-zinc-500 mt-2">Order Date</p>
                <p>{selectedOrder.order_date}</p>
                {selectedOrder.expected_delivery_date && (
                  <>
                    <p className="text-xs text-zinc-500 mt-2">Expected Delivery Date</p>
                    <p>{selectedOrder.expected_delivery_date}</p>
                  </>
                )}
              </div>
            </div>

            <div>
              <h4 className="font-semibold text-white mb-2">Line Items</h4>
              <table className="w-full text-left border-collapse bg-zinc-950/40 rounded border border-zinc-850">
                <thead>
                  <tr className="border-b border-zinc-850 text-zinc-400">
                    <th className="p-2">Product Name</th>
                    <th className="p-2 text-right">Ordered</th>
                    <th className="p-2 text-right">Delivered</th>
                    <th className="p-2 text-right">Invoiced</th>
                    <th className="p-2 text-right">Price</th>
                    <th className="p-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {selectedOrder.lines?.map((line) => (
                    <tr key={line.id} className="border-b border-zinc-850/50">
                      <td className="p-2">
                        <div className="font-medium text-white">{line.product?.name}</div>
                        <div className="text-xs text-zinc-500">{line.product?.sku}</div>
                      </td>
                      <td className="p-2 text-right">{parseFloat(line.quantity).toFixed(0)}</td>
                      <td className="p-2 text-right text-indigo-400">{parseFloat(line.delivered_quantity).toFixed(0)}</td>
                      <td className="p-2 text-right text-emerald-400">{parseFloat(line.invoiced_quantity).toFixed(0)}</td>
                      <td className="p-2 text-right">${parseFloat(line.unit_price).toFixed(2)}</td>
                      <td className="p-2 text-right text-white font-medium">${parseFloat(line.line_total).toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex justify-between items-center border-t border-zinc-800 pt-4">
              <div className="text-xs text-zinc-500">
                Created on {new Date(selectedOrder.created_at).toLocaleString()}
              </div>
              <div className="space-y-1 text-right">
                <div className="flex justify-between w-64 text-zinc-400">
                  <span>Subtotal:</span>
                  <span>${parseFloat(selectedOrder.subtotal).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-zinc-400">
                  <span>Discount:</span>
                  <span className="text-rose-400">-${parseFloat(selectedOrder.discount).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-zinc-400">
                  <span>Tax:</span>
                  <span>${parseFloat(selectedOrder.tax).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-white font-bold text-lg border-t border-zinc-850 pt-1">
                  <span>Total:</span>
                  <span>${parseFloat(selectedOrder.total).toFixed(2)}</span>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 mt-6">
              {selectedOrder.status === 'draft' && hasPermission('sales_orders.update') && (
                <button
                  onClick={() => submitMutation.mutate(selectedOrder.id)}
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded font-semibold transition-colors"
                >
                  Submit Order
                </button>
              )}
              {selectedOrder.status === 'submitted' && hasPermission('sales_orders.approve') && (
                <button
                  onClick={() => approveMutation.mutate(selectedOrder.id)}
                  className="px-4 py-2 bg-emerald-600 hover:bg-emerald-500 text-white rounded font-semibold transition-colors"
                >
                  Approve Order
                </button>
              )}
              {['draft', 'submitted', 'approved'].includes(selectedOrder.status) && hasPermission('sales_orders.update') && (
                <button
                  onClick={() => cancelMutation.mutate(selectedOrder.id)}
                  className="px-4 py-2 bg-zinc-900 hover:bg-zinc-800 text-rose-400 border border-zinc-800 rounded font-semibold transition-colors"
                >
                  Cancel Order
                </button>
              )}
              <button
                onClick={() => setSelectedOrder(null)}
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
          title="Create Direct Sales Order"
        >
          <form onSubmit={handleSubmitOrder} className="space-y-6 text-sm text-zinc-300">
            {formError && (
              <div className="p-3 bg-rose-950/50 border border-rose-800 text-rose-400 rounded">
                {formError}
              </div>
            )}

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Customer</label>
                <select
                  value={customerId}
                  onChange={(e) => setCustomerId(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                >
                  <option value="">Select Customer</option>
                  {customers?.map((cust: Customer) => (
                    <option key={cust.id} value={cust.id}>{cust.name} ({cust.customer_code})</option>
                  ))}
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Order Date</label>
                <input
                  type="date"
                  value={orderDate}
                  onChange={(e) => setOrderDate(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Expected Delivery Date (Optional)</label>
                <input
                  type="date"
                  value={expectedDeliveryDate}
                  onChange={(e) => setExpectedDeliveryDate(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                />
              </div>
            </div>

            <div>
              <div className="flex justify-between items-center mb-3">
                <h4 className="font-semibold text-white">Line Items</h4>
                <button
                  type="button"
                  onClick={handleAddLine}
                  className="flex items-center gap-1 text-xs text-indigo-400 hover:text-indigo-300 font-semibold"
                >
                  <Icons.Plus className="h-3 w-3" /> Add Product
                </button>
              </div>

              <div className="space-y-2 max-h-60 overflow-y-auto pr-1">
                {lines.map((line, idx) => (
                  <div key={idx} className="flex gap-2 items-center bg-zinc-950 p-2 rounded border border-zinc-850">
                    <div className="flex-1">
                      <select
                        value={line.product_id}
                        onChange={(e) => handleLineChange(idx, 'product_id', e.target.value)}
                        className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-white"
                      >
                        <option value="">Select Product</option>
                        {products?.map((prod: Product) => (
                          <option key={prod.id} value={prod.id}>{prod.name} (SKU: {prod.sku})</option>
                        ))}
                      </select>
                    </div>

                    <div className="w-20">
                      <input
                        type="number"
                        placeholder="Qty"
                        value={line.quantity}
                        onChange={(e) => handleLineChange(idx, 'quantity', e.target.value)}
                        className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-center text-white"
                        min="1"
                      />
                    </div>

                    <div className="w-24">
                      <input
                        type="number"
                        step="0.01"
                        placeholder="Price"
                        value={line.unit_price}
                        onChange={(e) => handleLineChange(idx, 'unit_price', e.target.value)}
                        className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-right text-white"
                        min="0"
                      />
                    </div>

                    <div className="w-24">
                      <input
                        type="number"
                        step="0.01"
                        placeholder="Discount"
                        value={line.discount}
                        onChange={(e) => handleLineChange(idx, 'discount', e.target.value)}
                        className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-right text-white"
                        min="0"
                      />
                    </div>

                    <div className="w-24">
                      <select
                        value={line.tax_rate}
                        onChange={(e) => handleLineChange(idx, 'tax_rate', e.target.value)}
                        className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-white"
                      >
                        <option value="0.00">0% Tax</option>
                        <option value="0.05">5% Tax</option>
                        <option value="0.12">12% Tax</option>
                        <option value="0.18">18% Tax</option>
                        <option value="0.28">28% Tax</option>
                      </select>
                    </div>

                    {lines.length > 1 && (
                      <button
                        type="button"
                        onClick={() => handleRemoveLine(idx)}
                        className="p-1 text-rose-400 hover:bg-rose-950 rounded transition-colors"
                      >
                        <Icons.Trash className="h-4 w-4" />
                      </button>
                    )}
                  </div>
                ))}
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Notes</label>
              <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white h-20 focus:outline-none focus:border-indigo-500"
                placeholder="Client specific details..."
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
                {createMutation.isPending ? 'Saving...' : 'Create Sales Order'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default SalesOrdersPage;
