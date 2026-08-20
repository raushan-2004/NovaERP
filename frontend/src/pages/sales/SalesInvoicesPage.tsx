/* eslint-disable @typescript-eslint/no-explicit-any */
 
/* eslint-disable react-hooks/set-state-in-effect */
import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';
import type { SalesInvoice, SalesOrder } from '../../types/api';

export function SalesInvoicesPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [statusFilter, setStatusFilter] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedInvoice, setSelectedInvoice] = useState<SalesInvoice | null>(null);

  // Form states
  const [salesOrderId, setSalesOrderId] = useState('');
  const [invoiceDate, setInvoiceDate] = useState(new Date().toISOString().substring(0, 10));
  const [dueDate, setDueDate] = useState(() => new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10)); // 30 days due
  
  const [soLines, setSoLines] = useState<any[]>([]);
  const [billQuantities, setBillQuantities] = useState<Record<number, string>>({}); // key = sales_order_line_id

  const [formError, setFormError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['sales-invoices', page, perPage, statusFilter],
    queryFn: async () => {
      const res = await apiClient.get('/sales-invoices', {
        params: { page, per_page: perPage, status: statusFilter || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('sales_invoices.view'),
  });

  const { data: salesOrders } = useQuery({
    queryKey: ['options', 'invoiceable-sales-orders'],
    queryFn: async () => {
      const res = await apiClient.get('/sales-orders');
      // Filter status suitable for invoicing: approved, partially_delivered, fully_delivered
      return (res.data?.data || []).filter((so: SalesOrder) => ['approved', 'partially_delivered', 'fully_delivered'].includes(so.status));
    },
    enabled: modalOpen,
  });

  // Fetch order details when salesOrderId changes
  useEffect(() => {
    if (!salesOrderId) {
      setSoLines([]);
      setBillQuantities({});
      return;
    }

    const fetchSoDetails = async () => {
      try {
        const res = await apiClient.get(`/sales-orders/${salesOrderId}`);
        const lines = res.data.data.lines || [];
        setSoLines(lines);

        const initialQtys: Record<number, string> = {};
        lines.forEach((l: any) => {
          // Billable qty is up to delivered quantity minus already invoiced
          const remaining = parseFloat(l.delivered_quantity) - parseFloat(l.invoiced_quantity);
          initialQtys[l.id] = remaining > 0 ? remaining.toString() : '0';
        });
        setBillQuantities(initialQtys);
      } catch (err: any) {
        console.error('Failed to fetch sales order details', err);
      }
    };

    fetchSoDetails();
  }, [salesOrderId]);

  // Mutations
  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/sales-invoices', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-invoices'] });
      setModalOpen(false);
      resetForm();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Failed to create sales invoice');
    }
  });

  const issueMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.post(`/sales-invoices/${id}/issue`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['sales-invoices'] });
      queryClient.invalidateQueries({ queryKey: ['sales-orders'] });
      if (selectedInvoice) setSelectedInvoice(null);
    },
    onError: (err: any) => {
      alert(err.response?.data?.message || 'Invoice issuance failed');
    }
  });

  const resetForm = () => {
    setSalesOrderId('');
    setInvoiceDate(new Date().toISOString().substring(0, 10));
    setDueDate(new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString().substring(0, 10));
    setSoLines([]);
    setBillQuantities({});
    setFormError(null);
  };

  const handleQtyChange = (lineId: number, val: string) => {
    setBillQuantities({ ...billQuantities, [lineId]: val });
  };

  const handleSubmitInvoice = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!salesOrderId) {
      setFormError('Please select a Sales Order.');
      return;
    }

    const linesPayload = Object.entries(billQuantities)
      .map(([lineIdStr, qtyStr]) => ({
        sales_order_line_id: parseInt(lineIdStr),
        quantity: parseFloat(qtyStr),
      }))
      .filter(l => l.quantity > 0);

    if (linesPayload.length === 0) {
      setFormError('Must bill at least one delivered item with quantity greater than zero.');
      return;
    }

    createMutation.mutate({
      sales_order_id: parseInt(salesOrderId),
      invoice_date: invoiceDate,
      due_date: dueDate,
      lines: linesPayload,
    });
  };

  const columns = [
    { header: 'Invoice Number', accessor: (row: SalesInvoice) => <span className="font-semibold text-text-primary">{row.invoice_number}</span> },
    { header: 'Sales Order', accessor: (row: SalesInvoice) => row.sales_order?.order_number || `SO #${row.sales_order_id}` },
    { header: 'Customer', accessor: (row: SalesInvoice) => row.customer?.name || `ID #${row.customer_id}` },
    { header: 'Invoice Date', accessor: (row: SalesInvoice) => row.invoice_date },
    { header: 'Due Date', accessor: (row: SalesInvoice) => row.due_date },
    { header: 'Total Amount', accessor: (row: SalesInvoice) => <span className="font-semibold">${parseFloat(row.total).toFixed(2)}</span> },
    { header: 'Balance Due', accessor: (row: SalesInvoice) => <span className="font-semibold text-rose-400">${parseFloat(row.amount_due).toFixed(2)}</span> },
    {
      header: 'Status',
      accessor: (row: SalesInvoice) => {
        let badgeColor = 'bg-zinc-800 text-zinc-300 border-zinc-705';
        if (row.status === 'issued') badgeColor = 'bg-blue-950 text-blue-400 border border-blue-900';
        if (row.status === 'partially_paid') badgeColor = 'bg-orange-950 text-orange-400 border border-orange-900';
        if (row.status === 'paid') badgeColor = 'bg-emerald-950 text-emerald-400 border border-emerald-900';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded uppercase ${badgeColor}`}>
            {row.status.replace('_', ' ')}
          </span>
        );
      }
    },
    {
      header: 'Actions',
      accessor: (row: SalesInvoice) => (
        <div className="flex gap-2">
          <button
            onClick={() => setSelectedInvoice(row)}
            className="p-1 text-zinc-400 hover:text-white transition-colors"
            title="View Details"
          >
            <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
              <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
              <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
          </button>
          {row.status === 'draft' && hasPermission('sales_invoices.issue') && (
            <button
              onClick={() => issueMutation.mutate(row.id)}
              className="px-2 py-0.5 text-xs bg-indigo-600 hover:bg-indigo-500 text-white rounded font-semibold transition-colors"
            >
              Issue
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
          <h1 className="text-2xl font-bold tracking-tight text-white">Sales Invoices</h1>
          <p className="text-sm text-zinc-400">Bill customers for delivered goods, set credit limits and due dates, and monitor receivables.</p>
        </div>
        {hasPermission('sales_invoices.create') && (
          <button
            onClick={() => { resetForm(); setModalOpen(true); }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition-all shadow-lg hover:shadow-indigo-500/20"
          >
            <Icons.Plus className="h-4 w-4" /> Create Sales Invoice
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
          <option value="issued">Issued</option>
          <option value="partially_paid">Partially Paid</option>
          <option value="paid">Paid</option>
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
      {selectedInvoice && (
        <Modal
          isOpen={!!selectedInvoice}
          onClose={() => setSelectedInvoice(null)}
          title={`Sales Invoice details: ${selectedInvoice.invoice_number}`}
        >
          <div className="space-y-6 text-sm text-zinc-300">
            <div className="grid grid-cols-2 gap-4 bg-zinc-950 p-4 rounded-lg border border-zinc-850">
              <div>
                <p className="text-xs text-zinc-500">Customer</p>
                <p className="font-semibold text-white">{selectedInvoice.customer?.name}</p>
                <p className="text-xs text-zinc-500 mt-2">Sales Order Reference</p>
                <p className="font-semibold text-white">{selectedInvoice.sales_order?.order_number}</p>
              </div>
              <div>
                <p className="text-xs text-zinc-500">Status</p>
                <p className="capitalize font-semibold text-white">{selectedInvoice.status.replace('_', ' ')}</p>
                <p className="text-xs text-zinc-500 mt-2">Invoice Date</p>
                <p>{selectedInvoice.invoice_date}</p>
                <p className="text-xs text-zinc-500 mt-2">Due Date</p>
                <p className="text-rose-400 font-semibold">{selectedInvoice.due_date}</p>
              </div>
            </div>

            <div>
              <h4 className="font-semibold text-white mb-2">Billed Items</h4>
              <table className="w-full text-left border-collapse bg-zinc-950/40 rounded border border-zinc-850">
                <thead>
                  <tr className="border-b border-zinc-850 text-zinc-400">
                    <th className="p-2">Product Name</th>
                    <th className="p-2 text-right">Billed Qty</th>
                    <th className="p-2 text-right">Unit Price</th>
                    <th className="p-2 text-right">Discount</th>
                    <th className="p-2 text-right">Tax Rate</th>
                    <th className="p-2 text-right">Total</th>
                  </tr>
                </thead>
                <tbody>
                  {selectedInvoice.lines?.map((line) => (
                    <tr key={line.id} className="border-b border-zinc-850/50">
                      <td className="p-2">
                        <div className="font-medium text-white">{line.product?.name}</div>
                        <div className="text-xs text-zinc-500">{line.product?.sku}</div>
                      </td>
                      <td className="p-2 text-right">{parseFloat(line.quantity).toFixed(0)}</td>
                      <td className="p-2 text-right">${parseFloat(line.unit_price).toFixed(2)}</td>
                      <td className="p-2 text-right text-rose-400">-${parseFloat(line.discount).toFixed(2)}</td>
                      <td className="p-2 text-right">{(parseFloat(line.tax_rate) * 100).toFixed(0)}%</td>
                      <td className="p-2 text-right text-white font-medium">${parseFloat(line.line_total).toFixed(2)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>

            <div className="flex justify-between items-center border-t border-zinc-800 pt-4">
              <div className="text-xs text-zinc-500">
                Created on {new Date(selectedInvoice.created_at).toLocaleString()}
              </div>
              <div className="space-y-1 text-right">
                <div className="flex justify-between w-64 text-zinc-400">
                  <span>Subtotal:</span>
                  <span>${parseFloat(selectedInvoice.subtotal).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-zinc-400">
                  <span>Discount:</span>
                  <span className="text-rose-400">-${parseFloat(selectedInvoice.discount).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-zinc-400">
                  <span>Tax:</span>
                  <span>${parseFloat(selectedInvoice.tax).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-white font-bold border-t border-zinc-850 pt-1">
                  <span>Total Amount:</span>
                  <span>${parseFloat(selectedInvoice.total).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-emerald-400 font-semibold">
                  <span>Amount Paid:</span>
                  <span>${parseFloat(selectedInvoice.amount_paid).toFixed(2)}</span>
                </div>
                <div className="flex justify-between w-64 text-white font-bold text-lg border-t border-zinc-800 pt-1">
                  <span>Amount Due:</span>
                  <span>${parseFloat(selectedInvoice.amount_due).toFixed(2)}</span>
                </div>
              </div>
            </div>

            <div className="flex justify-end gap-3 mt-6">
              {selectedInvoice.status === 'draft' && hasPermission('sales_invoices.issue') && (
                <button
                  onClick={() => issueMutation.mutate(selectedInvoice.id)}
                  className="px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded font-semibold transition-colors"
                >
                  Issue Invoice
                </button>
              )}
              <button
                onClick={() => setSelectedInvoice(null)}
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
          title="Create Customer Invoice"
        >
          <form onSubmit={handleSubmitInvoice} className="space-y-6 text-sm text-zinc-300">
            {formError && (
              <div className="p-3 bg-rose-950/50 border border-rose-800 text-rose-400 rounded">
                {formError}
              </div>
            )}

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Delivered Sales Order</label>
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
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Invoice Date</label>
                <input
                  type="date"
                  value={invoiceDate}
                  onChange={(e) => setInvoiceDate(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Due Date</label>
                <input
                  type="date"
                  value={dueDate}
                  onChange={(e) => setDueDate(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                />
              </div>
            </div>

            {soLines.length > 0 && (
              <div>
                <h4 className="font-semibold text-white mb-2">Order line items (Delivered vs Invoiced)</h4>
                <table className="w-full text-left border-collapse bg-zinc-950/40 rounded border border-zinc-850">
                  <thead>
                    <tr className="border-b border-zinc-850 text-zinc-400">
                      <th className="p-2">Product</th>
                      <th className="p-2 text-right">Delivered Qty</th>
                      <th className="p-2 text-right">Invoiced Qty</th>
                      <th className="p-2 text-right">Invoiceable Qty</th>
                      <th className="p-2 text-right w-32">Qty to Bill</th>
                    </tr>
                  </thead>
                  <tbody>
                    {soLines.map((line) => {
                      const remaining = parseFloat(line.delivered_quantity) - parseFloat(line.invoiced_quantity);
                      return (
                        <tr key={line.id} className="border-b border-zinc-850/50">
                          <td className="p-2">
                            <div className="font-medium text-white">{line.product?.name}</div>
                            <div className="text-xs text-zinc-500">{line.product?.sku}</div>
                          </td>
                          <td className="p-2 text-right text-indigo-400 font-semibold">{parseFloat(line.delivered_quantity).toFixed(0)}</td>
                          <td className="p-2 text-right text-zinc-500">{parseFloat(line.invoiced_quantity).toFixed(0)}</td>
                          <td className="p-2 text-right text-emerald-400 font-bold">{remaining > 0 ? remaining.toFixed(0) : '0'}</td>
                          <td className="p-2 text-right">
                            <input
                              type="number"
                              value={billQuantities[line.id] || '0'}
                              onChange={(e) => handleQtyChange(line.id, e.target.value)}
                              className="w-full bg-zinc-900 border border-zinc-800 rounded px-2 py-1 text-xs text-center text-white"
                              max={remaining > 0 ? remaining : 0}
                              min="0"
                              disabled={remaining <= 0}
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
                {createMutation.isPending ? 'Saving...' : 'Create Invoice'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default SalesInvoicesPage;
