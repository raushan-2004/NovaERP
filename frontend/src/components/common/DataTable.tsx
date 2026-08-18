import React, { useState } from 'react';
import { Icons } from './Icons';

export interface Column<T> {
  header: string;
  accessor: keyof T | ((row: T) => React.ReactNode);
  sortable?: boolean;
  sortKey?: string;
  className?: string;
}

interface DataTableProps<T> {
  columns: Column<T>[];
  data: T[];
  isLoading?: boolean;
  searchPlaceholder?: string;
  searchValue?: string;
  onSearchChange?: (val: string) => void;
  onAddClick?: () => void;
  addButtonLabel?: string;
  addButtonPermission?: boolean;
  
  // Sorting
  sortColumn?: string;
  sortDirection?: 'asc' | 'desc';
  onSortChange?: (column: string, direction: 'asc' | 'desc') => void;

  // Pagination
  currentPage?: number;
  lastPage?: number;
  totalItems?: number;
  perPage?: number;
  onPageChange?: (page: number) => void;
  onPerPageChange?: (perPage: number) => void;

  // Row Action buttons
  onEditClick?: (row: T) => void;
  onDeleteClick?: (row: T) => void;
  editPermission?: boolean;
  deletePermission?: boolean;
}

export function DataTable<T extends { id: number | string }>({
  columns,
  data = [],
  isLoading = false,
  searchPlaceholder = 'Search...',
  searchValue = '',
  onSearchChange,
  onAddClick,
  addButtonLabel = 'Add New',
  addButtonPermission = true,
  
  sortColumn,
  sortDirection,
  onSortChange,

  currentPage = 1,
  lastPage = 1,
  totalItems = 0,
  perPage = 15,
  onPageChange,
  onPerPageChange,

  onEditClick,
  onDeleteClick,
  editPermission = true,
  deletePermission = true,
}: DataTableProps<T>) {
  const [localSearch, setLocalSearch] = useState(searchValue);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (onSearchChange) {
      onSearchChange(localSearch);
    }
  };

  const handleSort = (col: Column<T>) => {
    if (!col.sortable || !onSortChange) return;
    const key = col.sortKey || String(col.accessor);
    let direction: 'asc' | 'desc' = 'asc';
    if (sortColumn === key && sortDirection === 'asc') {
      direction = 'desc';
    }
    onSortChange(key, direction);
  };

  return (
    <div className="flex flex-col w-full gap-4 bg-nova-800/40 border border-nova-700/80 rounded-xl p-5 backdrop-blur-xs">
      
      {/* Top Header Row (Search & Action Buttons) */}
      <div className="flex flex-col sm:flex-row gap-4 items-center justify-between">
        {onSearchChange ? (
          <form onSubmit={handleSearchSubmit} className="relative w-full sm:max-w-md flex gap-2">
            <div className="relative flex-1">
              <span className="absolute inset-y-0 left-0 pl-3 flex items-center text-text-muted">
                <Icons.Search size={16} />
              </span>
              <input
                type="text"
                value={localSearch}
                onChange={(e) => setLocalSearch(e.target.value)}
                placeholder={searchPlaceholder}
                className="w-full pl-9 pr-4 py-2 text-sm bg-nova-900 border border-nova-700 rounded-lg text-text-primary placeholder:text-text-muted focus:outline-hidden focus:border-accent-500 transition"
              />
            </div>
            <button
              type="submit"
              className="px-4 py-2 text-sm font-semibold bg-nova-700 hover:bg-nova-600 active:bg-nova-800 rounded-lg transition"
            >
              Search
            </button>
          </form>
        ) : (
          <div />
        )}

        {onAddClick && addButtonPermission && (
          <button
            onClick={onAddClick}
            className="flex items-center gap-1 px-4 py-2 text-sm font-semibold text-white bg-accent-500 hover:bg-accent-400 active:bg-accent-600 rounded-lg shadow-md transition-all duration-150"
          >
            <Icons.Plus size={16} />
            {addButtonLabel}
          </button>
        )}
      </div>

      {/* Table Container */}
      <div className="w-full overflow-x-auto rounded-lg border border-nova-700/50 bg-nova-900/10">
        <table className="w-full text-left border-collapse">
          <thead>
            <tr className="bg-nova-900/60 text-text-secondary text-xs uppercase tracking-wider font-semibold border-b border-nova-700">
              {columns.map((col, idx) => (
                <th
                  key={idx}
                  onClick={() => handleSort(col)}
                  className={`p-4 ${col.sortable ? 'cursor-pointer select-none hover:text-text-primary' : ''} ${col.className || ''}`}
                >
                  <div className="flex items-center gap-1">
                    {col.header}
                    {col.sortable && sortColumn === (col.sortKey || String(col.accessor)) && (
                      sortDirection === 'asc' ? <Icons.ArrowUp size={12} /> : <Icons.ArrowDown size={12} />
                    )}
                  </div>
                </th>
              ))}
              {(onEditClick || onDeleteClick) && (
                <th className="p-4 text-right w-28">Actions</th>
              )}
            </tr>
          </thead>
          <tbody className="divide-y divide-nova-700/40 text-text-secondary text-sm">
            {isLoading ? (
              <tr>
                <td colSpan={columns.length + 1} className="p-8 text-center">
                  <div className="flex items-center justify-center gap-2 text-text-muted">
                    <Icons.Loader size={20} />
                    Loading data...
                  </div>
                </td>
              </tr>
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length + 1} className="p-8 text-center text-text-muted">
                  No records found.
                </td>
              </tr>
            ) : (
              data.map((row) => (
                <tr key={row.id} className="hover:bg-nova-700/20 hover:text-text-primary transition-colors">
                  {columns.map((col, colIdx) => (
                    <td key={colIdx} className={`p-4 align-middle ${col.className || ''}`}>
                      {typeof col.accessor === 'function'
                        ? col.accessor(row)
                        : (row[col.accessor] as React.ReactNode)}
                    </td>
                  ))}
                  {(onEditClick || onDeleteClick) && (
                    <td className="p-4 align-middle text-right">
                      <div className="flex justify-end gap-2">
                        {onEditClick && editPermission && (
                          <button
                            onClick={() => onEditClick(row)}
                            className="p-1.5 text-text-muted hover:text-accent-400 hover:bg-nova-700 rounded-md transition"
                            title="Edit"
                          >
                            <Icons.Edit size={15} />
                          </button>
                        )}
                        {onDeleteClick && deletePermission && (
                          <button
                            onClick={() => onDeleteClick(row)}
                            className="p-1.5 text-text-muted hover:text-red-400 hover:bg-nova-700 rounded-md transition"
                            title="Delete"
                          >
                            <Icons.Trash size={15} />
                          </button>
                        )}
                      </div>
                    </td>
                  )}
                </tr>
              ))
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination Controls */}
      {onPageChange && totalItems > 0 && (
        <div className="flex flex-col sm:flex-row gap-4 items-center justify-between text-xs text-text-muted pt-2">
          <div className="flex items-center gap-2">
            <span>Show</span>
            <select
              value={perPage}
              onChange={(e) => onPerPageChange?.(Number(e.target.value))}
              className="bg-nova-900 border border-nova-700 rounded-md p-1 focus:outline-hidden text-text-secondary"
            >
              {[15, 30, 50, 100].map((v) => (
                <option key={v} value={v}>{v}</option>
              ))}
            </select>
            <span>entries (Showing {(currentPage - 1) * perPage + 1} to {Math.min(currentPage * perPage, totalItems)} of {totalItems})</span>
          </div>

          <div className="flex items-center gap-1">
            <button
              onClick={() => onPageChange(currentPage - 1)}
              disabled={currentPage <= 1}
              className="px-2.5 py-1.5 bg-nova-700 hover:bg-nova-600 disabled:opacity-40 disabled:hover:bg-nova-700 rounded-md transition"
            >
              Previous
            </button>
            
            {Array.from({ length: lastPage }, (_, i) => i + 1)
              .filter((p) => Math.abs(p - currentPage) < 3 || p === 1 || p === lastPage)
              .map((p, idx, arr) => {
                const prev = arr[idx - 1];
                const showEllipsis = prev && p - prev > 1;
                return (
                  <React.Fragment key={p}>
                    {showEllipsis && <span className="px-1">...</span>}
                    <button
                      onClick={() => onPageChange(p)}
                      className={`px-3 py-1.5 rounded-md font-semibold transition ${
                        p === currentPage
                          ? 'bg-accent-500 text-white'
                          : 'bg-nova-700 hover:bg-nova-600'
                      }`}
                    >
                      {p}
                    </button>
                  </React.Fragment>
                );
              })}

            <button
              onClick={() => onPageChange(currentPage + 1)}
              disabled={currentPage >= lastPage}
              className="px-2.5 py-1.5 bg-nova-700 hover:bg-nova-600 disabled:opacity-40 disabled:hover:bg-nova-700 rounded-md transition"
            >
              Next
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
export default DataTable;
