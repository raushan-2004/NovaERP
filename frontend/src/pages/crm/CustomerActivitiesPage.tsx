/* eslint-disable @typescript-eslint/no-explicit-any */
import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable from '../../components/common/DataTable';
import Modal from '../../components/common/Modal';
import { Icons } from '../../components/common/Icons';
import type { CustomerActivity, Customer } from '../../types/api';

export function CustomerActivitiesPage() {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  const [page, setPage] = useState(1);
  const [perPage] = useState(15);
  const [customerFilter, setCustomerFilter] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editingActivity, setEditingActivity] = useState<CustomerActivity | null>(null);

  // Form states
  const [customerId, setCustomerId] = useState('');
  const [activityType, setActivityType] = useState('call');
  const [activityDate, setActivityDate] = useState(new Date().toISOString().substring(0, 10));
  const [description, setDescription] = useState('');
  const [notes, setNotes] = useState('');

  const [formError, setFormError] = useState<string | null>(null);

  // Queries
  const { data, isLoading } = useQuery({
    queryKey: ['customer-activities', page, perPage, customerFilter],
    queryFn: async () => {
      const res = await apiClient.get('/customer-activities', {
        params: { page, per_page: perPage, customer_id: customerFilter || undefined },
      });
      return res.data;
    },
    enabled: hasPermission('crm.view'),
  });

  const { data: customers } = useQuery({
    queryKey: ['options', 'customers'],
    queryFn: async () => {
      const res = await apiClient.get('/customers');
      return res.data.data;
    },
    enabled: true,
  });

  // Mutations
  const createMutation = useMutation({
    mutationFn: async (payload: any) => {
      const res = await apiClient.post('/customer-activities', payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-activities'] });
      setModalOpen(false);
      resetForm();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Failed to create activity log');
    }
  });

  const updateMutation = useMutation({
    mutationFn: async ({ id, payload }: { id: number; payload: any }) => {
      const res = await apiClient.put(`/customer-activities/${id}`, payload);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-activities'] });
      setModalOpen(false);
      resetForm();
    },
    onError: (err: any) => {
      setFormError(err.response?.data?.message || 'Failed to update activity log');
    }
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: number) => {
      const res = await apiClient.delete(`/customer-activities/${id}`);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['customer-activities'] });
    }
  });

  const resetForm = () => {
    setCustomerId('');
    setActivityType('call');
    setActivityDate(new Date().toISOString().substring(0, 10));
    setDescription('');
    setNotes('');
    setEditingActivity(null);
    setFormError(null);
  };

  const handleEditClick = (activity: CustomerActivity) => {
    setEditingActivity(activity);
    setCustomerId(activity.customer_id.toString());
    setActivityType(activity.activity_type);
    setActivityDate(activity.activity_date);
    setDescription(activity.description);
    setNotes(activity.notes || '');
    setModalOpen(true);
  };

  const handleSubmitForm = (e: React.FormEvent) => {
    e.preventDefault();
    setFormError(null);

    if (!customerId || !description || !activityDate) {
      setFormError('Please select a Customer, Activity Date, and provide a Description.');
      return;
    }

    const payload = {
      customer_id: parseInt(customerId),
      activity_type: activityType,
      activity_date: activityDate,
      description: description,
      notes: notes || null,
    };

    if (editingActivity) {
      updateMutation.mutate({ id: editingActivity.id, payload });
    } else {
      createMutation.mutate(payload);
    }
  };

  const columns = [
    { header: 'Date', accessor: (row: CustomerActivity) => row.activity_date },
    { header: 'Customer', accessor: (row: CustomerActivity) => row.customer?.name || `ID #${row.customer_id}` },
    {
      header: 'Type',
      accessor: (row: CustomerActivity) => {
        let colors = 'bg-zinc-800 text-zinc-300';
        if (row.activity_type === 'call') colors = 'bg-blue-950 text-blue-400 border border-blue-900';
        if (row.activity_type === 'email') colors = 'bg-teal-950 text-teal-400 border border-teal-900';
        if (row.activity_type === 'meeting') colors = 'bg-purple-950 text-purple-400 border border-purple-900';
        if (row.activity_type === 'follow_up') colors = 'bg-amber-950 text-amber-400 border border-amber-900';
        return (
          <span className={`px-2 py-0.5 text-xs font-semibold rounded uppercase ${colors}`}>
            {row.activity_type.replace('_', ' ')}
          </span>
        );
      }
    },
    { header: 'Description', accessor: (row: CustomerActivity) => row.description },
    {
      header: 'Actions',
      accessor: (row: CustomerActivity) => (
        <div className="flex gap-2">
          {hasPermission('crm.update') && (
            <button
              onClick={() => handleEditClick(row)}
              className="p-1 text-zinc-400 hover:text-white transition-colors"
              title="Edit"
            >
              <Icons.Edit className="h-4 w-4" />
            </button>
          )}
          {hasPermission('crm.update') && (
            <button
              onClick={() => { if (confirm('Are you sure you want to delete this activity note?')) deleteMutation.mutate(row.id); }}
              className="p-1 text-zinc-400 hover:text-rose-400 transition-colors"
              title="Delete"
            >
              <Icons.Trash className="h-4 w-4" />
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
          <h1 className="text-2xl font-bold tracking-tight text-white">Customer Activities (CRM)</h1>
          <p className="text-sm text-zinc-400">Log customer communications, schedule meetings, register follow-up tasks, and inspect interaction histories.</p>
        </div>
        {hasPermission('crm.create') && (
          <button
            onClick={() => { resetForm(); setModalOpen(true); }}
            className="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-sm font-semibold transition-all shadow-lg hover:shadow-indigo-500/20"
          >
            <Icons.Plus className="h-4 w-4" /> Log CRM Activity
          </button>
        )}
      </div>

      <div className="flex justify-between items-center bg-zinc-900/50 p-4 rounded-lg border border-zinc-800">
        <select
          value={customerFilter}
          onChange={(e) => { setCustomerFilter(e.target.value); setPage(1); }}
          className="bg-zinc-950 border border-zinc-800 rounded px-3 py-1.5 text-sm text-zinc-300 focus:outline-none focus:border-indigo-500"
        >
          <option value="">All Customers</option>
          {customers?.map((c: any) => (
            <option key={c.id} value={c.id}>{c.name}</option>
          ))}
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

      {/* Creation/Edit Modal */}
      {modalOpen && (
        <Modal
          isOpen={modalOpen}
          onClose={() => setModalOpen(false)}
          title={editingActivity ? 'Edit CRM Activity' : 'Log CRM Activity'}
        >
          <form onSubmit={handleSubmitForm} className="space-y-6 text-sm text-zinc-300">
            {formError && (
              <div className="p-3 bg-rose-950/50 border border-rose-800 text-rose-400 rounded">
                {formError}
              </div>
            )}

            <div>
              <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Customer</label>
              <select
                value={customerId}
                onChange={(e) => setCustomerId(e.target.value)}
                className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                disabled={!!editingActivity}
              >
                <option value="">Select Customer</option>
                {customers?.map((cust: Customer) => (
                  <option key={cust.id} value={cust.id}>{cust.name} ({cust.customer_code})</option>
                ))}
              </select>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Activity Type</label>
                <select
                  value={activityType}
                  onChange={(e) => setActivityType(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                >
                  <option value="call">Phone Call</option>
                  <option value="email">Email</option>
                  <option value="meeting">In-Person Meeting</option>
                  <option value="note">General Note</option>
                  <option value="follow_up">Follow Up Action</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Activity Date</label>
                <input
                  type="date"
                  value={activityDate}
                  onChange={(e) => setActivityDate(e.target.value)}
                  className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Summary Description</label>
              <input
                type="text"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
                className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white focus:outline-none focus:border-indigo-500"
                placeholder="Brief summary of discussion, decision, or result..."
              />
            </div>

            <div>
              <label className="block text-xs font-semibold uppercase text-zinc-400 mb-1">Detailed Discussion Notes</label>
              <textarea
                value={notes}
                onChange={(e) => setNotes(e.target.value)}
                className="w-full bg-zinc-950 border border-zinc-800 rounded px-3 py-2 text-white h-24 focus:outline-none focus:border-indigo-500"
                placeholder="Detailed meeting minutes or email transcripts..."
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
                disabled={createMutation.isPending || updateMutation.isPending}
                className="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-semibold transition-colors disabled:opacity-50"
              >
                {createMutation.isPending || updateMutation.isPending ? 'Saving...' : editingActivity ? 'Update Activity' : 'Log Activity'}
              </button>
            </div>
          </form>
        </Modal>
      )}
    </div>
  );
}

export default CustomerActivitiesPage;
