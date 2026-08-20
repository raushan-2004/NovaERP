import { useQuery } from '@tanstack/react-query';
import { apiClient } from '../../api/client';
import { usePermission } from '../../hooks/usePermission';

// Helper inline SVG icons to prevent compilation issues
const TrendingUpIcon = ({ className }: { className?: string }) => (
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
    <path strokeLinecap="round" strokeLinejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
  </svg>
);

const CheckCircleIcon = ({ className }: { className?: string }) => (
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
    <path strokeLinecap="round" strokeLinejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
  </svg>
);

const AlertCircleIcon = ({ className }: { className?: string }) => (
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
    <path strokeLinecap="round" strokeLinejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
  </svg>
);

const ShoppingBagIcon = ({ className }: { className?: string }) => (
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
    <path strokeLinecap="round" strokeLinejoin="round" d="M15.75 10.5V6a3.75 3.75 0 10-7.5 0v4.5m11.356-1.993l1.263 12c.07.665-.45 1.243-1.119 1.243H4.25a1.125 1.125 0 01-1.12-1.243l1.264-12A1.125 1.125 0 015.513 7.5h12.974c.576 0 1.059.435 1.119 1.007zM8.625 10.5a.375.375 0 11-.75 0 .375.375 0 01.75 0zm7.5 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />
  </svg>
);

const ArrowRightIcon = ({ className }: { className?: string }) => (
  <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" strokeWidth="2" stroke="currentColor" className={className}>
    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3" />
  </svg>
);

export function SalesDashboardPage() {
  const { hasPermission } = usePermission();

  const { data, isLoading } = useQuery({
    queryKey: ['sales-dashboard'],
    queryFn: async () => {
      const res = await apiClient.get('/sales/dashboard');
      return res.data.data;
    },
    enabled: hasPermission('sales_orders.view'),
  });

  if (isLoading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-indigo-500"></div>
      </div>
    );
  }

  const stats = [
    {
      title: 'Total Invoiced Sales',
      value: `$${parseFloat(data?.total_invoiced || '0').toFixed(2)}`,
      description: 'Sum of all issued invoices',
      icon: TrendingUpIcon,
      color: 'from-blue-600/20 to-indigo-600/5 border-blue-500/20',
      textColor: 'text-blue-400',
    },
    {
      title: 'Payments Received',
      value: `$${parseFloat(data?.payments_received_total || '0').toFixed(2)}`,
      description: 'Total revenue collected',
      icon: CheckCircleIcon,
      color: 'from-emerald-600/20 to-teal-600/5 border-emerald-500/20',
      textColor: 'text-emerald-400',
    },
    {
      title: 'Outstanding Receivables',
      value: `$${parseFloat(data?.outstanding_invoices_total || '0').toFixed(2)}`,
      description: 'Awaiting customer payment',
      icon: AlertCircleIcon,
      color: 'from-amber-600/20 to-orange-600/5 border-amber-500/20',
      textColor: 'text-amber-400',
    },
    {
      title: 'Approved Sales Orders',
      value: `$${parseFloat(data?.approved_orders_total || '0').toFixed(2)}`,
      description: `${data?.orders_count || 0} total order contracts`,
      icon: ShoppingBagIcon,
      color: 'from-purple-600/20 to-indigo-600/5 border-purple-500/20',
      textColor: 'text-purple-400',
    },
  ];

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-bold tracking-tight text-white">Sales & Order-to-Cash Dashboard</h1>
        <p className="text-sm text-zinc-400">Real-time tracking of quotations, orders, deliveries, and payment settlements.</p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, idx) => {
          const Icon = stat.icon;
          return (
            <div
              key={idx}
              className={`p-6 rounded-xl border bg-gradient-to-br ${stat.color} shadow-lg backdrop-blur-sm space-y-4`}
            >
              <div className="flex justify-between items-start">
                <span className="text-sm font-medium text-zinc-400">{stat.title}</span>
                <span className={`p-2 rounded-lg bg-zinc-950/40 border border-zinc-800 ${stat.textColor}`}>
                  <Icon className="h-5 w-5" />
                </span>
              </div>
              <div>
                <h3 className="text-2xl font-bold text-white tracking-tight">{stat.value}</h3>
                <p className="text-xs text-zinc-500 mt-1">{stat.description}</p>
              </div>
            </div>
          );
        })}
      </div>

      {/* Visual walkthrough banner */}
      <div className="bg-gradient-to-r from-indigo-950/30 to-purple-950/20 border border-indigo-900/30 p-6 rounded-xl space-y-4">
        <h3 className="text-lg font-semibold text-indigo-300">Stage 3 OTC Cycle Reference</h3>
        <p className="text-sm text-zinc-400">
          The order-to-cash flow starts by sending a <strong>Quotation</strong> to the customer. 
          Once accepted, it transitions to a <strong>Sales Order</strong>. 
          Upon dispatch, you create a <strong>Delivery Note</strong> from the approved order which automatically issues inventory stock. 
          Then, you bill the customer by generating a <strong>Sales Invoice</strong>, and record payment transactions under <strong>Customer Payments</strong>.
        </p>
        <div className="flex flex-wrap gap-4 text-xs font-semibold">
          <span className="flex items-center gap-2 bg-indigo-950/80 border border-indigo-800 px-3 py-1 rounded text-indigo-400">
            Quotation <ArrowRightIcon className="h-3 w-3" />
          </span>
          <span className="flex items-center gap-2 bg-indigo-950/80 border border-indigo-800 px-3 py-1 rounded text-indigo-400">
            Sales Order <ArrowRightIcon className="h-3 w-3" />
          </span>
          <span className="flex items-center gap-2 bg-indigo-950/80 border border-indigo-800 px-3 py-1 rounded text-indigo-400">
            Delivery (Stock Issue) <ArrowRightIcon className="h-3 w-3" />
          </span>
          <span className="flex items-center gap-2 bg-indigo-950/80 border border-indigo-800 px-3 py-1 rounded text-indigo-400">
            Invoice <ArrowRightIcon className="h-3 w-3" />
          </span>
          <span className="flex items-center gap-2 bg-emerald-950/80 border border-emerald-800 px-3 py-1 rounded text-emerald-400">
            Payment (Settled)
          </span>
        </div>
      </div>
    </div>
  );
}

export default SalesDashboardPage;
