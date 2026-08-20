/* eslint-disable @typescript-eslint/no-explicit-any */
 
/* eslint-disable react-hooks/set-state-in-effect */
import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';
import type { CustomerPayment, SalesInvoice } from '../../types/api';

export function CustomerPaymentsPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [modalOpen, setModalOpen] = useState(false);
  const [selectedPayment, setSelectedPayment] = useState<CustomerPayment | null>(null);

  // Form states
  const [salesInvoiceId, setSalesInvoiceId] = useState('');
  const [amount, setAmount] = useState('');
  const [paymentMethod, setPaymentMethod] = useState('bank_transfer');
  const [reference, setReference] = useState('');
  const [notes, setNotes] = useState('');

  const [selectedInvoice, setSelectedInvoice] = useState<any | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['customer-payments', page, perPage],
    queryFn: async () => {
      const res = await apiClient.get('/customer-payments', {
        params: { page, per_page: perPage },
      });
      return res.data;
    },
    enabled: hasPermission('customer_payments.view'),
  });

  const { data: invoices } = useQuery({
    queryKey: ['options', 'outstanding-invoices'],
    queryFn: async () => {
      const res = await apiClient.get('/sales-invoices');
      // Filter status suitable for payment: issued, partially_paid
      return (res.data?.data || []).filter((inv: SalesInvoice) => ['issued', 'partially_paid'].includes(inv.status));
    },
    enabled: modalOpen,
  });

  // Fetch invoice details when invoice changes to display outstanding balance
  useEffect(() => {
    if (!salesInvoiceId) {
      setSelectedInvoice(null);
      setAmount('');
      return;
    }
    const fetchInv = async () => {
      try {
        const res = await apiClient.get(`/sales-invoices/${salesInvoiceId}`);
        const inv = res.data.data;
        setSelectedInvoice(inv);
        setAmount(parseFloat(inv.amount_due).toFixed(2));
      } catch (err: any) {
        console.error(err);
      }
    };
    fetchInv();
  }, [salesInvoiceId]);

  // Mutations
  const recordMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/customer-payments', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-payments'] });
      queryClient.invalidateQueries({ queryKey: ['sales-invoices'] });
      setModalOpen(false);
      resetForm();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Failed to record payment');
    }
  });

  const resetForm = () => {
    setSalesInvoiceId('');
    setAmount('');
    setPaymentMethod('bank_transfer');
    setReference('');
    setNotes('');
    setSelectedInvoice(null);
    setFormError(null);
  };

  const handleSubmitPayment = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!salesInvoiceId || !amount || parseFloat(amount) <= 0) {
      setFormError('Please select an Invoice and provide a valid Payment Amount.');
      return;
    }

    if (selectedInvoice && parseFloat(amount) > parseFloat(selectedInvoice.amount_due)) {
      setFormError(`Payment amount cannot exceed Outstanding Balance of $${parseFloat(selectedInvoice.amount_due).toFixed(2)}.`);
      return;
    }

    recordMutation.mutate({
      sales_invoice_id: parseInt(salesInvoiceId),
      amount: parseFloat(amount),
      payment_method: paymentMethod,
      reference: reference || null,
      notes: notes || null,
    });
  };

  const columns = [
    { header: 'Payment Number', accessor: (row: CustomerPayment) => <span className="font-semibold text-text-primary">{row.payment_number}</span> },
    { header: 'Customer', accessor: (row: CustomerPayment) => row.customer?.name || `ID #${row.customer_id}` },
    { header: 'Invoice Reference', accessor: (row: CustomerPayment) => row.sales_invoice?.invoice_number || `INV #${row.sales_invoice_id}` },
    { header: 'Payment Date', accessor: (row: CustomerPayment) => row.payment_date },
    { header: 'Method', accessor: (row: CustomerPayment) => <span className="uppercase text-xs">{row.payment_method.replace('_', ' ')}</span> },
    { header: 'Amount Paid', accessor: (row: CustomerPayment) => <span className="font-semibold text-emerald-400">${parseFloat(row.amount).toFixed(2)}</span> },
    {
      header: 'Actions',
      accessor: (row: CustomerPayment) => (
        <button
          onClick={() => setSelectedPayment(row)}
          className="p-1 text-zinc-400 hover:text-white transition-colors"
          title="View Details"
        >
          <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" strokeWidth="2">
            <path strokeLinecap="round" strokeLinejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0z" />
            <path strokeLinecap="round" strokeLinejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
          </svg>
        </button>
      )
    }
  ];

  return (
    <div className="space-y-6">
      <div className="flex justify-between items-center">
        <div>
          <h1 className="text-2xl font-bold tracking-tight text-white">Customer Payments</h1>
          <p className="text-sm text-zinc-400">Record customer deposits, process incoming cash/bank transactions, and update invoice balance ledger.</p>
        </div>
        {hasPermission('customer_payments.create') && (
          <button
            onClick={() => { resetForm(); setModalOpen(true); }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition-all shadow-lg hover:shadow-indigo-500/20"
          >
            <Icons.Plus className="h-4 w-4" /> Record Customer Payment
          </button>
        )}
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
      {selectedPayment && (
        <Modal
          isOpen={!!selectedPayment}
          onClose={() => setSelectedPayment(null)}
          title={`Payment Note: ${selectedPayment.payment_number}`}
        >
          <div className="space-y-4 text-sm text-zinc-300">
            <div className="bg-zinc-950 p-4 rounded border border-zinc-850 space-y-2">
              <div className="flex justify-between">
                <span className="text-zinc-500">Customer:</span>
                <span className="text-white font-medium">{selectedPayment.customer?.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-zinc-500">Invoice:</span>
                <span className="text-white font-medium">{selectedPayment.sales_invoice?.invoice_number}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-zinc-500">Payment Date:</span>
                <span>{selectedPayment.payment_date}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-zinc-500">Payment Method:</span>
                <span className="uppercase">{selectedPayment.payment_method.replace('_', ' ')}</span>
              </div>
              {selectedPayment.reference && (
                <div className="flex justify-between">
                  <span className="text-zinc-500">Reference/TxID:</span>
                  <span className="font-mono text-xs">{selectedPayment.reference}</span>
                </div>
              )}
              <div className="flex justify-between border-t border-zinc-850 pt-2 text-lg">
                <span className="text-white font-bold">Amount Paid:</span>
                <span className="text-emerald-400 font-bold">${parseFloat(selectedPayment.amount).toFixed(2)}</span>
              </div>
            </div>

            {selectedPayment.notes && (
              <div>
                <p className="text-xs text-zinc-500 mb-1">Notes</p>
                <p className="text-zinc-400 italic">"{selectedPayment.notes}"</p>
              </div>
            )}

            <div className="flex justify-end gap-3 mt-6">
              <button
                onClick={() => setSelectedPayment(null)}
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
          title="Record Customer Payment"
        >
          <form onSubmit={handleSubmitPayment} className="space-y-6 text-sm text-zinc-300">
            {formError && (
              <div className="p-3 bg-rose-950/50 border border-rose-800 text-rose-400 rounded">
                {formError}
              </div>
            )}

            <div>
              <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Outstanding Invoice</label>
              <select
                value={salesInvoiceId}
                onChange={(e) => setSalesInvoiceId(e.target.value)}
                className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
              >
                <option value="">Select Invoice</option>
                {invoices?.map((inv: SalesInvoice) => (
                  <option key={inv.id} value={inv.id}>{inv.invoice_number} (Outstanding: ${parseFloat(inv.amount_due).toFixed(2)})</option>
                ))}
              </select>
            </div>

            {selectedInvoice && (
              <div className="grid grid-cols-2 gap-4 bg-zinc-950 p-3 rounded border border-zinc-850">
                <div>
                  <p className="text-xs text-zinc-500">Customer</p>
                  <p className="font-semibold text-white">{selectedInvoice.customer?.name}</p>
                </div>
                <div>
                  <p className="text-xs text-zinc-500">Outstanding Balance</p>
                  <p className="font-bold text-indigo-400">${parseFloat(selectedInvoice.amount_due).toFixed(2)}</p>
                </div>
              </div>
            )}

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Amount to Pay ($)</label>
                <input
                  type="number"
                  step="0.01"
                  value={amount}
                  onChange={(e) => setAmount(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white font-semibold text-emerald-400 focus:outline-none focus:border-indigo-500"
                  min="0.01"
                  placeholder="0.00"
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Payment Method</label>
                <select
                  value={paymentMethod}
                  onChange={(e) => setPaymentMethod(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                >
                  <option value="cash">Cash</option>
                  <option value="bank_transfer">Bank Transfer</option>
                  <option value="card">Credit/Debit Card</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Reference / Transaction Number (Optional)</label>
                <input
                  type="text"
                  value={reference}
                  onChange={(e) => setReference(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                  placeholder="TxID, Check number, wiring receipt..."
                />
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Notes</label>
                <textarea
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white h-20 focus:outline-none focus:border-indigo-500"
                  placeholder="Additional payment details..."
                />
              </div>
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
                disabled={recordMutation.isPending}
                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-semibold transition-colors disabled:opacity-50"
              >
                {recordMutation.isPending ? 'Saving...' : 'Record Payment'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default CustomerPaymentsPage;
