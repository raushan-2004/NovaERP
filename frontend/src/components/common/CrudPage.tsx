import { useState, type ReactNode } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';
import DataTable, { type Column } from './DataTable';
import Modal from './Modal';
import ConfirmDialog from './ConfirmDialog';
import { Icons } from './Icons';

export interface CrudField {
  name: string;
  label: string;
  type: 'text' | 'email' | 'password' | 'select' | 'textarea' | 'number';
  required?: boolean;
  options?: { value: string | number; label: string }[];
  optionsUrl?: string; // API URL to load select options
  placeholder?: string;
  hideInTable?: boolean;
  hideInForm?: boolean;
  disabledOnEdit?: boolean;
}

interface CrudPageProps<T> {
  title: string;
  endpoint: string;
  fields: CrudField[];
  viewPermission: string;
  createPermission: string;
  updatePermission: string;
  deletePermission: string;
  
  // Custom column rendering overrides
  columnRenderers?: Record<string, (row: T) => ReactNode>;
  
  // Optional pre-submit transform
  transformData?: (data: Record<string, any>) => Record<string, any>;
  
  // Additional filters (e.g. company scoped)
  additionalFilters?: Record<string, any>;
}

export function CrudPage<T extends { id: number | string; [key: string]: any }>({
  title,
  endpoint,
  fields,
  viewPermission,
  createPermission,
  updatePermission,
  deletePermission,
  columnRenderers = {},
  transformData,
  additionalFilters = {},
}: CrudPageProps<T>) {
  const queryClient = useQueryClient();
  const { hasPermission } = usePermission();

  // State
  const [search, setSearch] = useState('');
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [sortColumn, setSortColumn] = useState<string>('id');
  const [sortDirection, setSortDirection] = useState<'asc' | 'desc'>('desc');

  const [modalOpen, setModalOpen] = useState(false);
  const [editingRow, setEditingRow] = useState<T | null>(null);
  const [formData, setFormData] = useState<Record<string, any>>({});
  const [formErrors, setFormErrors] = useState<Record<string, string[]>>({});
  const [generalError, setGeneralError] = useState<string | null>(null);

  const [confirmDeleteOpen, setConfirmDeleteOpen] = useState(false);
  const [deletingRow, setDeletingRow] = useState<T | null>(null);

  // Load select options for fields dynamically
  const dynamicOptionsQueries = fields
    .filter((f) => f.type === 'select' && f.optionsUrl)
    .reduce<Record<string, any>>((acc, field) => {
      acc[field.name] = useQuery({
        queryKey: ['options', field.name, field.optionsUrl],
        queryFn: async () => {
          const res = await apiClient.get(field.optionsUrl!);
          // The API returns data inside `data` wrapper
          const dataList = res.data.data;
          return dataList.map((item: any) => ({
            value: item.id,
            label: item.name || item.employee_code || item.branch_code || item.warehouse_code || item.email || item.id,
          }));
        },
        enabled: modalOpen, // Only fetch when form is open
        staleTime: 60000,
      });
      return acc;
    }, {});

  // Fetch Main Table Data
  const { data, isLoading } = useQuery({
    queryKey: [endpoint, page, perPage, sortColumn, sortDirection, search, additionalFilters],
    queryFn: async () => {
      const params = {
        page,
        per_page: perPage,
        sort_by: sortColumn,
        sort_desc: sortDirection === 'desc' ? '1' : '0',
        search: search || undefined,
        ...additionalFilters,
      };
      const res = await apiClient.get(`/api/v1/${endpoint}`, { params });
      return res.data;
    },
    enabled: hasPermission(viewPermission),
  });

  // Mutations
  const saveMutation = useMutation({
    mutationFn: async () => {
      let payload = { ...formData };
      if (transformData) {
        payload = transformData(payload);
      }

      if (editingRow) {
        return apiClient.put(`/api/v1/${endpoint}/${editingRow.id}`, payload);
      } else {
        return apiClient.post(`/api/v1/${endpoint}`, payload);
      }
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [endpoint] });
      setModalOpen(false);
      setEditingRow(null);
      setFormData({});
      setFormErrors({});
      setGeneralError(null);
    },
    onError: (err: any) => {
      if (err.errors) {
        setFormErrors(err.errors);
      }
      setGeneralError(err.message || 'An error occurred while saving.');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: async (id: string | number) => {
      return apiClient.delete(`/api/v1/${endpoint}/${id}`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: [endpoint] });
      setConfirmDeleteOpen(false);
      setDeletingRow(null);
    },
  });

  // Action Handlers
  const handleAdd = () => {
    setEditingRow(null);
    const defaultData: Record<string, any> = {};
    fields.forEach((f) => {
      if (f.type === 'select') {
        defaultData[f.name] = f.options?.[0]?.value || '';
      } else {
        defaultData[f.name] = '';
      }
    });
    setFormData(defaultData);
    setFormErrors({});
    setGeneralError(null);
    setModalOpen(true);
  };

  const handleEdit = (row: T) => {
    setEditingRow(row);
    const editData: Record<string, any> = {};
    fields.forEach((f) => {
      editData[f.name] = row[f.name] ?? '';
    });
    setFormData(editData);
    setFormErrors({});
    setGeneralError(null);
    setModalOpen(true);
  };

  const handleDeletePrompt = (row: T) => {
    setDeletingRow(row);
    setConfirmDeleteOpen(true);
  };

  const handleDeleteConfirm = () => {
    if (deletingRow) {
      deleteMutation.mutate(deletingRow.id);
    }
  };

  // Build Table Columns
  const tableColumns: Column<T>[] = fields
    .filter((f) => !f.hideInTable)
    .map((field) => ({
      header: field.label,
      accessor: (row: T) => {
        if (columnRenderers[field.name]) {
          return columnRenderers[field.name](row);
        }
        
        // Handle nested relationship displays (e.g. company.name, branch.name)
        if (field.name.endsWith('_id')) {
          const relName = field.name.substring(0, field.name.length - 3);
          if (row[relName] && typeof row[relName] === 'object') {
            return row[relName].name || row[relName].employee_code || row[relName].branch_code || row[relName].id;
          }
        }

        const val = row[field.name];
        if (field.name === 'status') {
          const isAct = val === 'active';
          return (
            <span className={`px-2 py-0.5 text-xs font-semibold rounded-full ${
              isAct ? 'bg-green-950 text-green-400 border border-green-800' : 'bg-red-950 text-red-400 border border-red-800'
            }`}>
              {val}
            </span>
          );
        }
        return val !== null && val !== undefined ? String(val) : '';
      },
      sortable: true,
      sortKey: field.name,
    }));

  if (!hasPermission(viewPermission)) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[50vh] text-center">
        <h2 className="text-2xl font-bold text-red-400 mb-2">Access Denied</h2>
        <p className="text-text-secondary">You do not have permission to view {title}.</p>
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-6">
      {/* Title */}
      <div className="flex flex-col gap-1">
        <h2 className="text-2xl font-bold text-text-primary">{title}</h2>
        <p className="text-sm text-text-secondary">Manage {title.toLowerCase()} records, credentials, and settings.</p>
      </div>

      {/* Main Data Table */}
      <DataTable<T>
        columns={tableColumns}
        data={data?.data || []}
        isLoading={isLoading}
        searchPlaceholder={`Search ${title.toLowerCase()}...`}
        searchValue={search}
        onSearchChange={(val) => { setSearch(val); setPage(1); }}
        onAddClick={handleAdd}
        addButtonLabel={`Add ${title}`}
        addButtonPermission={hasPermission(createPermission)}
        sortColumn={sortColumn}
        sortDirection={sortDirection}
        onSortChange={(col, dir) => { setSortColumn(col); setSortDirection(dir); }}
        currentPage={data?.meta?.current_page || 1}
        lastPage={data?.meta?.last_page || 1}
        totalItems={data?.meta?.total || 0}
        perPage={perPage}
        onPageChange={setPage}
        onPerPageChange={(num) => { setPerPage(num); setPage(1); }}
        onEditClick={handleEdit}
        onDeleteClick={handleDeletePrompt}
        editPermission={hasPermission(updatePermission)}
        deletePermission={hasPermission(deletePermission)}
      />

      {/* Create/Edit Modal */}
      <Modal
        isOpen={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editingRow ? `Edit ${title}` : `Create New ${title}`}
      >
        <form
          onSubmit={(e) => {
            e.preventDefault();
            saveMutation.mutate();
          }}
          className="flex flex-col gap-4 text-text-primary"
        >
          {generalError && (
            <div className="p-3 bg-red-950/50 border border-red-800 rounded-lg text-sm text-red-400">
              {generalError}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {fields
              .filter((f) => !f.hideInForm)
              .map((field) => {
                const errorMsgs = formErrors[field.name];
                const isDisabled = !!(editingRow && field.disabledOnEdit);

                return (
                  <div 
                    key={field.name} 
                    className={`flex flex-col gap-1.5 ${field.type === 'textarea' ? 'sm:col-span-2' : ''}`}
                  >
                    <label className="text-xs font-semibold text-text-secondary uppercase tracking-wider">
                      {field.label} {field.required && <span className="text-red-400">*</span>}
                    </label>

                    {field.type === 'textarea' ? (
                      <textarea
                        value={formData[field.name] || ''}
                        onChange={(e) => setFormData({ ...formData, [field.name]: e.target.value })}
                        placeholder={field.placeholder || `Enter ${field.label.toLowerCase()}`}
                        disabled={isDisabled}
                        className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm text-text-primary focus:outline-hidden focus:border-accent-500 transition min-h-[100px]"
                      />
                    ) : field.type === 'select' ? (
                      <select
                        value={formData[field.name] || ''}
                        onChange={(e) => setFormData({ ...formData, [field.name]: e.target.value })}
                        disabled={isDisabled}
                        className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm text-text-primary focus:outline-hidden focus:border-accent-500 transition"
                      >
                        {field.optionsUrl ? (
                          // Dynamic options loaded from endpoint
                          dynamicOptionsQueries[field.name]?.isLoading ? (
                            <option>Loading options...</option>
                          ) : (
                            <>
                              {!field.required && <option value="">None</option>}
                              {dynamicOptionsQueries[field.name]?.data?.map((opt: any) => (
                                <option key={opt.value} value={opt.value}>
                                  {opt.label}
                                </option>
                              ))}
                            </>
                          )
                        ) : (
                          // Static options
                          field.options?.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                              {opt.label}
                            </option>
                          ))
                        )}
                      </select>
                    ) : (
                      <input
                        type={field.type}
                        value={formData[field.name] || ''}
                        onChange={(e) => setFormData({ ...formData, [field.name]: e.target.value })}
                        placeholder={field.placeholder || `Enter ${field.label.toLowerCase()}`}
                        disabled={isDisabled}
                        className="w-full bg-nova-900 border border-nova-700 rounded-lg p-2.5 text-sm text-text-primary focus:outline-hidden focus:border-accent-500 transition"
                      />
                    )}

                    {errorMsgs && (
                      <span className="text-xs text-red-400">{errorMsgs[0]}</span>
                    )}
                  </div>
                );
              })}
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-nova-700 mt-4">
            <button
              type="button"
              onClick={() => setModalOpen(false)}
              className="px-4 py-2 text-sm font-medium text-text-secondary bg-nova-700 hover:bg-nova-600 rounded-lg transition"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={saveMutation.isPending}
              className="flex items-center justify-center gap-1.5 px-4 py-2 text-sm font-semibold text-white bg-accent-500 hover:bg-accent-400 active:bg-accent-600 rounded-lg transition"
            >
              {saveMutation.isPending && <Icons.Loader size={14} />}
              {editingRow ? 'Update' : 'Create'}
            </button>
          </div>
        </form>
      </Modal>

      {/* Delete Confirmation Dialog */}
      <ConfirmDialog
        isOpen={confirmDeleteOpen}
        onClose={() => setConfirmDeleteOpen(false)}
        onConfirm={handleDeleteConfirm}
        title={`Delete ${title}`}
        message={`Are you sure you want to delete this ${title.toLowerCase()} record? This action cannot be undone.`}
      />
    </div>
  );
}
export default CrudPage;
