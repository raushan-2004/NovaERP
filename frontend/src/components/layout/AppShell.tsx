import type { ReactNode } from 'react';
import Sidebar from './Sidebar';
import Header from './Header';

interface AppShellProps {
  children: ReactNode;
}

function AppShell({ children }: AppShellProps) {
  return (
    <div className="nova-app-shell">
      <Sidebar />
      <div className="nova-main-wrapper">
        <Header />
        <main className="nova-main-content">
          {children}
        </main>
      </div>
    </div>
  );
}

export default AppShell;
