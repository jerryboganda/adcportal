import React, { useEffect, useMemo } from 'react';
import {
  LayoutDashboard,
  UserCheck,
  Activity,
  FileText,
  CreditCard,
  Tv,
  Boxes,
  Database,
  AlertTriangle,
  Clock,
  CheckCircle2,
  Bell,
  Stethoscope,
  Settings,
  Flame
} from 'lucide-react';
import { ActiveTab, AppRole, Appointment, Patient, Invoice, StaffUser, AppNotification, InventoryItem, Entitlements } from '../types';
import { GlobalSearchBar } from './GlobalSearchBar';
import { UserProfileMenu } from './UserProfileMenu';
import { SessionUser } from '../services/apiService';
import { allowedTabs } from '../services/permissions';

/**
 * Tab visibility is PERMISSION-DRIVEN from the server-issued effective
 * permission set (currentUser.permissions — never picked client-side from the
 * role label) plus the tenant's server-enforced module entitlements.
 */

interface NavbarProps {
  activeTab: ActiveTab;
  setActiveTab: (tab: ActiveTab) => void;
  appointments: Appointment[];
  patients: Patient[];
  invoices?: Invoice[];
  inventoryItems?: InventoryItem[];
  role: AppRole;
  entitlements?: Entitlements | null;
  onSelectAppointment?: (apt: Appointment) => void;
  onOpenBookingModal?: () => void;
  /** Server-issued `appointment create` — hides booking affordances otherwise. */
  canOpenBooking?: boolean;
  staffUsers?: StaffUser[];
  currentUser: SessionUser;
  onSignOut: () => void;
  onSwitchTenant?: (businessId: number) => void;
  notifications?: AppNotification[];
  onOpenNotifications?: () => void;
  onExportBackup?: () => void;
  onLockTerminal?: () => void;
  /** Tenant white-label application name (server-resolved; cosmetic only). */
  brandName?: string | null;
  /** Tenant currency symbol; inventory prices in search results are labelled with it. */
  currency: string;
}

export const Navbar: React.FC<NavbarProps> = ({
  activeTab,
  setActiveTab,
  currency,
  appointments,
  patients,
  invoices = [],
  inventoryItems = [],
  role,
  entitlements,
  onSelectAppointment,
  onOpenBookingModal,
  canOpenBooking,
  staffUsers = [],
  currentUser,
  onSignOut,
  onSwitchTenant,
  notifications = [],
  onOpenNotifications,
  onExportBackup,
  onLockTerminal,
  brandName,
}) => {
  // Compute badge counts
  const statCount = appointments.filter(a => a.priority === 'stat' && !['reported', 'delivered', 'cancelled', 'no_show'].includes(a.workflowState)).length;
  const waitingCount = appointments.filter(a => ['booked', 'checked_in', 'preparing'].includes(a.workflowState)).length;
  const readingCount = appointments.filter(a => ['acquired', 'reading'].includes(a.workflowState)).length;
  const lowStockCount = inventoryItems.filter(i => i.currentStock <= i.minThreshold).length;

  const unreadNotifications = notifications.filter(n => !n.isRead);
  const unreadCount = unreadNotifications.length;
  const hasCriticalNotif = unreadNotifications.some(n => n.priority === 'critical' || n.category === 'stat');

  const navItems = useMemo(() => {
    const allowed = new Set(allowedTabs(currentUser.permissions, entitlements));
    const all = [
      { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard },
      { id: 'checkin', label: 'Reception Desk', icon: UserCheck, badge: waitingCount, badgeColor: 'bg-cyan-500' },
      { id: 'technologist', label: 'Tech Worklist', icon: Activity, badge: statCount > 0 ? `${statCount} STAT` : undefined, badgeColor: 'bg-rose-500 animate-pulse' },
      { id: 'reporting', label: 'Radiology Reports', icon: FileText, badge: readingCount, badgeColor: 'bg-purple-500' },
      { id: 'billing', label: 'Billing & POS', icon: CreditCard },
      { id: 'queue', label: 'Live Queue TV', icon: Tv },
      { id: 'inventory', label: 'Consumables & Contrast', icon: Boxes, badge: lowStockCount > 0 ? `${lowStockCount} Low` : undefined, badgeColor: 'bg-amber-500' },
      { id: 'masters', label: 'Catalog & Forms', icon: Database },
      { id: 'doctors', label: 'Doctor Network', icon: Stethoscope },
      { id: 'settings', label: 'Settings', icon: Settings },
    ];
    return all.filter(item => allowed.has(item.id as ActiveTab));
  }, [currentUser.permissions, entitlements, waitingCount, statCount, readingCount, lowStockCount]);

  // If the persisted/active tab is not permitted in this role+tenant context,
  // snap back to the first allowed tab (never render an ungated surface).
  useEffect(() => {
    if (navItems.length > 0 && !navItems.some(item => item.id === activeTab)) {
      setActiveTab(navItems[0].id as ActiveTab);
    }
  }, [activeTab, navItems, setActiveTab]);

  return (
    <header className="bg-white border-b border-slate-200 sticky top-0 z-40 shadow-xs">
      <div className="max-w-[1680px] mx-auto px-3 sm:px-6 lg:px-8">
        <div className="flex items-center justify-between h-16 gap-2 sm:gap-4">
          {/* Logo & Brand */}
          <div className="flex items-center space-x-2 sm:space-x-3 cursor-pointer shrink-0" onClick={() => setActiveTab('dashboard')}>
            <div className="w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-gradient-to-tr from-cyan-600 via-sky-500 to-indigo-600 flex items-center justify-center shadow-md shadow-cyan-600/20 text-white font-black text-lg sm:text-xl tracking-wider shrink-0">
              PX
            </div>
            <div className="hidden sm:block">
              <span className="font-bold text-base sm:text-lg text-slate-900 tracking-tight block leading-tight">{brandName?.trim() || 'PolytronX - Enterprise PACS & RIS'}</span>
              <p className="text-[11px] text-slate-500 font-medium">Radiology Information System</p>
            </div>
          </div>

          {/* Global Search Bar (Center / Prominent) */}
          <GlobalSearchBar
            appointments={appointments}
            patients={patients}
            permissions={currentUser.permissions}
            invoices={invoices}
            inventoryItems={inventoryItems}
            setActiveTab={setActiveTab}
            currency={currency}
            onSelectAppointment={onSelectAppointment}
            onOpenBookingModal={onOpenBookingModal}
            canOpenBooking={canOpenBooking}
          />

          {/* Quick Metrics Bar (Desktop) */}
          <div className="hidden xl:flex items-center space-x-3 bg-slate-50 py-1.5 px-3 rounded-xl border border-slate-200 text-xs shrink-0">
            <div className="flex items-center space-x-1.5 text-slate-600">
              <Clock className="w-3.5 h-3.5 text-cyan-600" />
              <span>Queue:</span>
              <span className="font-bold text-cyan-700">{waitingCount}</span>
            </div>
            <div className="w-px h-3.5 bg-slate-200" />
            <div className="flex items-center space-x-1.5 text-slate-600">
              <FileText className="w-3.5 h-3.5 text-purple-600" />
              <span>To Read:</span>
              <span className="font-bold text-purple-700">{readingCount}</span>
            </div>
            {statCount > 0 && (
              <>
                <div className="w-px h-3.5 bg-slate-200" />
                <div className="flex items-center space-x-1.5 text-rose-600 font-bold animate-pulse">
                  <AlertTriangle className="w-3.5 h-3.5" />
                  <span>{statCount} STAT Alert</span>
                </div>
              </>
            )}
          </div>

          {/* Right Header Actions: Notification Bell + User Profile Dropdown */}
          <div className="flex items-center space-x-2 shrink-0">
            {/* Notification Bell Button */}
            {onOpenNotifications && (
              <button
                type="button"
                onClick={onOpenNotifications}
                title={unreadCount > 0 ? `${unreadCount} unread clinical notifications` : 'Clinical Notification Center'}
                className={`relative p-2 rounded-xl border transition-all duration-150 cursor-pointer ${
                  unreadCount > 0
                    ? hasCriticalNotif
                      ? 'bg-rose-50 border-rose-300 text-rose-700 hover:bg-rose-100 shadow-xs'
                      : 'bg-cyan-50 border-cyan-300 text-cyan-700 hover:bg-cyan-100 shadow-xs'
                    : 'bg-white hover:bg-slate-50 text-slate-600 border-slate-200'
                }`}
                aria-label="Open notifications"
              >
                <Bell className={`w-5 h-5 ${hasCriticalNotif ? 'animate-swing text-rose-600' : ''}`} />
                {unreadCount > 0 && (
                  <span
                    className={`absolute -top-1 -right-1 px-1.5 min-w-[18px] h-[18px] text-[10px] font-black rounded-full flex items-center justify-center text-white border-2 border-white shadow-xs ${
                      hasCriticalNotif ? 'bg-rose-600 animate-pulse' : 'bg-cyan-600'
                    }`}
                  >
                    {unreadCount > 9 ? '9+' : unreadCount}
                  </span>
                )}
              </button>
            )}

            {/* Comprehensive User Profile Dropdown */}
            <UserProfileMenu
              role={role}
              currentUser={currentUser}
              staffUsers={staffUsers}
              setActiveTab={setActiveTab}
              onSignOut={onSignOut}
              onExportBackup={onExportBackup}
              onLockTerminal={onLockTerminal}
              onSwitchTenant={onSwitchTenant}
            />
          </div>
        </div>

        {/* Navigation Tabs */}
        <nav className="flex space-x-1 overflow-x-auto py-2 scrollbar-none border-t border-slate-100">
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive = activeTab === item.id;
            return (
              <button
                key={item.id}
                onClick={() => setActiveTab(item.id as ActiveTab)}
                className={`flex items-center space-x-2 px-3.5 py-2 rounded-lg text-xs font-semibold whitespace-nowrap transition-all duration-150 cursor-pointer ${
                  isActive
                    ? 'bg-cyan-50 text-cyan-700 border border-cyan-200 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 border border-transparent'
                }`}
              >
                <Icon className={`w-4 h-4 ${isActive ? 'text-cyan-600' : 'text-slate-500'}`} />
                <span>{item.label}</span>
                {item.badge !== undefined && item.badge !== 0 && (
                  <span className={`text-[10px] px-1.5 py-0.5 rounded-md text-white font-bold whitespace-nowrap ${item.badgeColor || 'bg-slate-600'}`}>
                    {item.badge}
                  </span>
                )}
              </button>
            );
          })}
        </nav>
      </div>
    </header>
  );
};
