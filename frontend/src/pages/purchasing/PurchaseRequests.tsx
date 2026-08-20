/* eslint-disable @typescript-eslint/no-explicit-any */
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';

interface PRLine {
  id: number;
  product_id: number;
  unit_id: number;
  quantity: string;
  notes: string | null;
  product?: { name: string; sku: string };
  unit?: { abbreviation: string };
}

interface PRRow {
  id: number;
  request_number: string;
  company_id: number;
  branch_id: number;
  requested_by: number;
  required_date: string | null;
  status: string;
  notes: string | null;
  company?: { name: string };
  branch?: { name: string };
  lines?: PRLine[];
}

export function PurchaseRequestsPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [search, setSearch] = useState('');
  
  const [modalOpen, setModalOpen] = useState(false);
  const [convertModalOpen, setConvertModalOpen] = useState(false);
  const [selectedPr, setSelectedPr] = useState<PRRow | null>(null);

  // Creation State
  const [formData, setFormData] = useState({
    company_id: '',
    branch_id: '',
    required_date: '',
    notes: '',
  });
  const [formLines, setFormLines] = useState<Array<{ product_id: string; unit_id: string; quantity: string; notes: string }>>([
    { product_id: '', unit_id: '', quantity: '1', notes: '' },
  ]);

  // Conversion State
  const [convertData, setConvertData] = useState({
    supplier_id: '',
    order_date: new Date().toISOString().substring(0, 10),
    expected_delivery_date: '',
    notes: '',
    unit_price_map: {} as Record<number, string>,
    tax_rate_map: {} as Record<number, string>,
  });

  const [, setFormErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['purchase-requests', page, perPage, search],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/purchase-requests', {
        params: { page, per_page: perPage, search: search || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('purchase_requests.view'),
  });

  const { data: companies } = useQuery({
    queryKey: ['options', 'companies'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/companies');
      return res.data.data;
    },
    enabled: modalOpen || convertModalOpen,
  });

  const { data: branches } = useQuery({
    queryKey: ['options', 'branches'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/branches');
      return res.data.data;
    },
    enabled: modalOpen || convertModalOpen,
  });

  const { data: suppliers } = useQuery({
    queryKey: ['options', 'suppliers'],
    queryFn: async () => {
      const res = await apiClient.get('/api/v1/suppliers');
      return res.data.data;
    },
    enabled: convertModalOpen,
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
      return apiClient.post('/api/v1/purchase-requests', {
        ...formData,
        lines: formLines,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchase-requests'] });
      setModalOpen(false);
      setFormData({ company_id: '', branch_id: '', required_date: '', notes: '' });
      setFormLines([{ product_id: '', unit_id: '', quantity: '1', notes: '' }]);
      setFormErrors({});
      setGeneralError(null);
    },
    onError: (err: any) => {
      if (err.errors) setFormErrors(err.errors);
      setGeneralError(err.message || 'Failed to create request.');
    },
  });

  const transitionMutation = useMutation({
    mutationFn: async ({ id, action }: { id: number; action: string }) => {
      return apiClient.post(`/api/v1/purchase-requests/${id}/${action}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchase-requests'] });
    },
    onError: (err: any) => {
      alert(err.message || 'Operation failed.');
    },
  });

  const convertMutation = useMutation({
    mutationFn: async () => {
      if (!selectedPr) return;
      return apiClient.post(`/api/v1/purchase-requests/${selectedPr.id}/convert-to-po`, {
        company_id: selectedPr.company_id,
        branch_id: selectedPr.branch_id,
        ...convertData,
      });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['purchase-requests'] });
      queryClient.invalidateQueries({ queryKey: ['purchase-orders'] });
      setConvertModalOpen(false);
      setSelectedPr(null);
      setConvertData({
        supplier_id: '',
        order_date: new Date().toISOString().substring(0, 10),
        expected_delivery_date: '',
        notes: '',
        unit_price_map: {},
        tax_rate_map: {},
      });
    },
    onError: (err: any) => {
      alert(err.message || 'Failed to convert to Purchase Order.');
    },
  });

  const addLine = () => {
    setFormLines([...formLines, { product_id: '', unit_id: '', quantity: '1', notes: '' }]);
  };

  const removeLine = (idx: number) => {
    setFormLines(formLines.filter((_, i) => i !== idx));
  };

  const updateLine = (idx: number, key: string, value: string) => {
    const next = [...formLines];
    next[idx] = { ...next[idx], [key]: value };
    setFormLines(next);
  };

  // Table columns
  const columns = [
    { header: 'PR Number', accessor: (row: PRRow) => <span className="font-semibold text-text-primary">{row.request_number}</span> },
    { header: 'Company', accessor: (row: PRRow) => row.company?.name || `Company #${row.company_id}` },
    { header: 'Branch', accessor: (row: PRRow) => row.branch?.name || `Branch #${row.branch_id}` },
    { header: 'Required Date', accessor: (row: PRRow) => row.required_date || 'N/A' },
    {
      header: 'Status',
      accessor: (row: PRRow) => {
        let style = 'bg-slate-950 text-slate-400 border-slate-800';
        if (row.status === 'submitted') style = 'bg-blue-950 text-blue-400 border border-blue-800';
        if (row.status === 'approved') style = 'bg-green-950 text-green-400 border border-green-800';
        if (row.status === 'converted') style = 'bg-emerald-950 text-emerald-400 border border-emerald-800';
        if (row.status === 'rejected') style = 'bg-red-950 text-red-400 border border-red-800';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded-full uppercase border ${style}`}>
            {row.status}
          </span>
        );
      },
    },
    {
      header: 'Actions',
      accessor: (row: PRRow) => (
        <div className="flex gap-2">
          {row.status === 'draft' && hasPermission('purchase_requests.update') && (
            <button
              onClick={() => transitionMutation.mutate({ id: row.id, action: 'submit' })}
              disabled={transitionMutation.isPending}
              className="px-2 py-1 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded transition"
            >
              Submit
            </button>
          )}

          {row.status === 'submitted' && hasPermission('purchase_requests.approve') && (
            <>
              <button
                onClick={() => transitionMutation.mutate({ id: row.id, action: 'approve' })}
                disabled={transitionMutation.isPending}
                className="px-2 py-1 text-xs font-semibold text-white bg-green-600 hover:bg-green-500 rounded transition"
              >
                Approve
              </button>
              <button
                onClick={() => transitionMutation.mutate({ id: row.id, action: 'reject' })}
                disabled={transitionMutation.isPending}
                className="px-2 py-1 text-xs font-semibold text-white bg-red-600 hover:bg-red-500 rounded transition"
              >
                Reject
              </button>
            </>
          )}

          {row.status === 'approved' && hasPermission('purchase_requests.approve') && (
            <button
              onClick={() => {
                setSelectedPr(row);
                // Initialize mapping with default 0s
                const prices: Record<number, string> = {};
                const taxes: Record<number, string> = {};
                row.lines?.forEach((l) => {
                  prices[l.product_id] = '0.00';
                  taxes[l.product_id] = '0.18';
                });
                setConvertData((prev) => ({ ...prev, unit_price_map: prices, tax_rate_map: taxes }));
                setConvertModalOpen(true);
              }}
              className="px-2 py-1 text-xs font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded transition"
            >
              Convert to PO
            </button>
          )}
        </div>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h2 className="text-2xl font-bold text-text-primary">Purchase Requests</h2>
        <p className="text-sm text-text-secondary">Request components or raw materials from organizational branches.</p>
      </div>

      <DataTable<PRRow>
        columns={columns}
        data={data?.data || []}
        isLoading={isLoading}
        searchPlaceholder="Search requests..."
        searchValue={search}
        onSearchChange={(val) => { setSearch(val); setPage(1); }}
        onAddClick={() => setModalOpen(true)}
        addButtonLabel="New PR"
        addButtonPermission={hasPermission('purchase_requests.create')}
        currentPage={data?.meta?.current_page || 1}
        lastPage={data?.meta?.last_page || 1}
        totalItems={data?.meta?.total || 0}
        perPage={perPage}
        onPageChange={setPage}
        onPerPageChange={(num) => { setPerPage(num); setPage(1); }}
      />

      {/* Creation Modal */}
      <Modal isOpen={modalOpen} onClose={() => setModalOpen(false)} title="New Purchase Request">
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
              <label className="text-xs font-semibold text-text-secondary uppercase">Required Date</label>
              <input
                type="date"
                value={formData.required_date}
                onChange={(e) => setFormData({ ...formData, required_date: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
              />
            </div>

            <div className="flex flex-col gap-1.5 sm:col-span-2">
              <label className="text-xs font-semibold text-text-secondary uppercase">Notes</label>
              <textarea
                value={formData.notes}
                onChange={(e) => setFormData({ ...formData, notes: e.target.value })}
                placeholder="Enter justification or details..."
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm min-h-[60px]"
              />
            </div>

            {/* Lines Section */}
            <div className="sm:col-span-2 border-t border-nova-700 pt-4 flex flex-col gap-3">
              <div className="flex justify-between items-center">
                <span className="text-sm font-bold text-text-primary">Requested Items</span>
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

                  <div className="flex-1 flex flex-col gap-1.5 w-full sm:max-w-[120px]">
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

                  <div className="flex-1 flex flex-col gap-1.5 w-full sm:max-w-[100px]">
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
              Create Request
            </button>
          </div>
        </form>
      </Modal>

      {/* Convert to PO Modal */}
      <Modal isOpen={convertModalOpen} onClose={() => setConvertModalOpen(false)} title="Convert Purchase Request to PO">
        <form
          onSubmit={(e) => {
            e.preventDefault();
            convertMutation.mutate();
          }}
          className="flex flex-col gap-4 text-text-primary"
        >
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div className="flex flex-col gap-1.5 sm:col-span-2">
              <label className="text-xs font-semibold text-text-secondary uppercase">Supplier *</label>
              <select
                value={convertData.supplier_id}
                onChange={(e) => setConvertData({ ...convertData, supplier_id: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              >
                <option value="">Select Supplier</option>
                {suppliers?.map((s: any) => (
                  <option key={s.id} value={s.id}>{s.name}</option>
                ))}
              </select>
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Order Date *</label>
              <input
                type="date"
                value={convertData.order_date}
                onChange={(e) => setConvertData({ ...convertData, order_date: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
                required
              />
            </div>

            <div className="flex flex-col gap-1.5">
              <label className="text-xs font-semibold text-text-secondary uppercase">Expected Delivery Date</label>
              <input
                type="date"
                value={convertData.expected_delivery_date}
                onChange={(e) => setConvertData({ ...convertData, expected_delivery_date: e.target.value })}
                className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm"
              />
            </div>

            {/* Financial mapping inputs for PR items */}
            <div className="sm:col-span-2 border-t border-nova-700 pt-4 flex flex-col gap-3">
              <span className="text-sm font-bold">Set Prices and Taxes</span>
              {selectedPr?.lines?.map((line) => (
                <div key={line.id} className="flex flex-col sm:flex-row gap-3 items-end bg-nova-900/40 p-3 rounded-lg border border-nova-700/50">
                  <div className="flex-1 text-xs">
                    <span className="font-semibold block">{line.product?.name}</span>
                    <span className="text-text-secondary">Quantity: {parseFloat(line.quantity).toFixed(2)}</span>
                  </div>

                  <div className="w-full sm:max-w-[140px] flex flex-col gap-1.5">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Unit Price *</label>
                    <input
                      type="number"
                      step="0.0001"
                      value={convertData.unit_price_map[line.product_id] || ''}
                      onChange={(e) => {
                        const prices = { ...convertData.unit_price_map, [line.product_id]: e.target.value };
                        setConvertData({ ...convertData, unit_price_map: prices });
                      }}
                      className="w-full bg-nova-900 border border-nova-700 rounded p-1 text-xs"
                      required
                    />
                  </div>

                  <div className="w-full sm:max-w-[120px] flex flex-col gap-1.5">
                    <label className="text-[10px] font-bold text-text-muted uppercase">Tax Rate *</label>
                    <select
                      value={convertData.tax_rate_map[line.product_id] || '0.18'}
                      onChange={(e) => {
                        const taxes = { ...convertData.tax_rate_map, [line.product_id]: e.target.value };
                        setConvertData({ ...convertData, tax_rate_map: taxes });
                      }}
                      className="w-full bg-nova-900 border border-nova-700 rounded p-1 text-xs"
                      required
                    >
                      <option value="0">0%</option>
                      <option value="0.05">5%</option>
                      <option value="0.12">12%</option>
                      <option value="0.18">18%</option>
                      <option value="0.28">28%</option>
                    </select>
                  </div>
                </div>
              ))}
            </div>
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-nova-700">
            <button
              type="button"
              onClick={() => setConvertModalOpen(false)}
              className="px-4 py-2 text-sm font-medium text-text-secondary bg-nova-700 rounded-lg"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={convertMutation.isPending}
              className="px-4 py-2 text-sm font-semibold text-white bg-emerald-600 hover:bg-emerald-500 rounded-lg flex items-center gap-1.5"
            >
              {convertMutation.isPending && <Icons.Loader size={14} />}
              Generate PO
            </button>
          </div>
        </form>
      </Modal>
    </div>
  );
}

export default PurchaseRequestsPage;
