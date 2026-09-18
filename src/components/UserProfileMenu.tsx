import React, { useState, useRef, useEffect } from 'react';
import {
  ChevronDown,
  Stethoscope,
  Settings,
  LogOut,
  Lock,
  Download,
  Building,
  SwitchCamera,
} from 'lucide-react';
import { ActiveTab, AppRole, StaffUser } from '../types';
import { canAny } from '../services/permissions';
import { SessionUser } from '../services/apiService';

interface UserProfileMenuProps {
  role: AppRole;
  currentUser: SessionUser;
  staffUsers: StaffUser[];
  setActiveTab: (tab: ActiveTab) => void;
  onSignOut: () => void;
  onExportBackup?: () => void;
  onLockTerminal?: () => void;
  onSwitchTenant?: (businessId: number) => void;
}

/**
 * Session identity comes from the authenticated API user. There is no client
 * role switching — permissions are issued by the server for this session only.
 */
export const UserProfileMenu: React.FC<UserProfileMenuProps> = ({
  role,
  currentUser,
  staffUsers,
  setActiveTab,
  onSignOut,
  onExportBackup,
  onLockTerminal,
  onSwitchTenant,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const menuRef = useRef<HTMLDivElement>(null);

  // Close menu when clicking outside
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (menuRef.current && !menuRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };
    if (isOpen) {
      document.addEventListener('mousedown', handleClickOutside);
    }
    return () => {
      document.removeEventListener('mousedown', handleClickOutside);
    };
  }, [isOpen]);

  const getRoleGradient = (currentRole: string) => {
    switch (currentRole) {
      case 'radiologist':
        return 'from-purple-600 via-indigo-600 to-sky-600';
      case 'technologist':
        return 'from-cyan-600 via-teal-600 to-emerald-600';
      case 'receptionist':
      case 'billing':
        return 'from-sky-600 via-blue-600 to-indigo-600';
      case 'admin':
      default:
        return 'from-slate-800 via-slate-700 to-cyan-800';
    }
  };

  const getRoleDisplayName = (r: string) => {
    switch (r) {
      case 'radiologist':
        return 'Consultant Radiologist';
      case 'technologist':
        return 'Radiographer / Technologist';
      case 'receptionist':
        return 'Front Desk Officer';
      case 'billing':
        return 'Billing Officer';
      case 'admin':
      default:
        return 'System Administrator';
    }
  };

  return (
    <div className="relative" ref={menuRef}>
      {/* Profile Trigger Button */}
      <button
        type="button"
        onClick={() => setIsOpen(!isOpen)}
        aria-label="Open profile menu"
        className={`flex items-center space-x-2 p-1.5 sm:px-3 sm:py-1.5 rounded-xl border transition-all duration-150 cursor-pointer shadow-xs ${
          isOpen
            ? 'bg-slate-100 border-cyan-400 ring-2 ring-cyan-500/20'
            : 'bg-white hover:bg-slate-50 border-slate-200'
        }`}
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        {/* Avatar Circle */}
        <div className="relative shrink-0">
          <div className={`w-8 h-8 rounded-full bg-gradient-to-tr ${getRoleGradient(role)} flex items-center justify-center text-white font-black text-xs shadow-xs tracking-wider border-2 border-white`}>
            {currentUser.initials || currentUser.name.slice(0, 2).toUpperCase()}
          </div>
        </div>

        {/* User Name & ID Info (Desktop) */}
        <div className="text-left hidden lg:block leading-tight pr-0.5">
          <div className="flex items-center space-x-1.5">
            <span className="text-xs font-bold text-slate-800 tracking-tight block max-w-[140px] truncate">
              {currentUser.name}
            </span>
          </div>
          <div className="flex items-center space-x-1.5 text-[10px] text-slate-500 font-medium">
            <span className="capitalize text-slate-600">{getRoleDisplayName(role)}</span>
          </div>
        </div>

        {/* Dropdown Chevron */}
        <ChevronDown
          className={`w-4 h-4 text-slate-400 transition-transform duration-200 ${
            isOpen ? 'rotate-180 text-cyan-600' : ''
          }`}
        />
      </button>

      {/* Dropdown Menu Card */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200/80 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-150 divide-y divide-slate-100">
          {/* Identity Header */}
          <div className="p-4 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white">
            <div className="flex items-start space-x-3.5">
              <div className="relative shrink-0">
                <div className={`w-13 h-13 rounded-2xl bg-gradient-to-tr ${getRoleGradient(role)} flex items-center justify-center text-white font-black text-lg shadow-lg border-2 border-white/20`}>
                  {currentUser.initials || currentUser.name.slice(0, 2).toUpperCase()}
                </div>
              </div>

              <div className="flex-1 min-w-0">
                <h3 className="text-sm font-bold text-white tracking-tight truncate">
                  {currentUser.name}
                </h3>
                <p className="text-xs text-cyan-300 font-semibold mt-0.5">
                  {getRoleDisplayName(role)}
                </p>
                <div className="flex items-center space-x-2 mt-1.5 text-[11px] text-slate-300">
                  <span className="font-mono bg-white/10 px-1.5 py-0.5 rounded text-white/90 truncate max-w-[160px]">
                    {currentUser.businessName}
                  </span>
                  {currentUser.businessBrandName && currentUser.businessBrandName !== currentUser.businessName && (
                    <span className="text-[11px] text-cyan-300 truncate max-w-[160px]">{currentUser.businessBrandName}</span>
                  )}
                </div>
                <p className="text-[11px] text-slate-400 truncate mt-1">
                  {currentUser.email}
                </p>
              </div>
            </div>

          </div>

          {/* Tenant switcher — only when this identity legitimately belongs to
              more than one clinic; the server re-verifies every switch. */}
          {onSwitchTenant && currentUser.memberships.length > 1 && (
            <div className="p-2 space-y-0.5">
              <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block px-2 py-1">
                Your clinics
              </span>
              {currentUser.memberships.map(m => {
                const isCurrent = m.businessId === currentUser.businessId;
                return (
                  <button
                    key={m.businessId}
                    onClick={() => {
                      if (isCurrent) return;
                      setIsOpen(false);
                      onSwitchTenant(m.businessId);
                    }}
                    className={`w-full flex items-center justify-between px-3 py-2 text-xs font-semibold rounded-lg transition-colors cursor-pointer ${
                      isCurrent
                        ? 'bg-cyan-50 text-cyan-800'
                        : 'text-slate-700 hover:text-cyan-800 hover:bg-slate-50'
                    }`}
                  >
                    <span className="flex items-center space-x-2 truncate">
                      <SwitchCamera className="w-4 h-4 text-slate-500 shrink-0" />
                      <span className="truncate">{m.businessName}{m.businessBrandName && m.businessBrandName !== m.businessName ? ` — ${m.businessBrandName}` : ''}</span>
                    </span>
                    <span className="text-[10px] uppercase text-slate-400">{isCurrent ? 'current' : m.role}</span>
                  </button>
                );
              })}
            </div>
          )}

          {/* Quick RIS Actions & Settings — every entry is permission-gated
              so the menu mirrors the server-issued effective access. */}
          <div className="p-2 space-y-0.5">
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block px-2 py-1">
              Management & Tools
            </span>

            {canAny(currentUser.permissions, ['setting manage', 'user manage', 'user logs history', 'role view']) && (
            <button
              onClick={() => {
                setActiveTab('settings');
                setIsOpen(false);
              }}
              className="w-full flex items-center justify-between px-3 py-2 text-xs font-semibold text-slate-700 hover:text-cyan-800 hover:bg-slate-50 rounded-lg transition-colors cursor-pointer"
            >
              <div className="flex items-center space-x-2">
                <Settings className="w-4 h-4 text-slate-500" />
                <span>Clinic Settings & Staff RBAC</span>
              </div>
              <span className="text-[10px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded font-mono">
                {staffUsers.length} Staff
              </span>
            </button>
            )}

            {canAny(currentUser.permissions, ['doctors view']) && (
            <button
              onClick={() => {
                setActiveTab('doctors');
                setIsOpen(false);
              }}
              className="w-full flex items-center space-x-2 px-3 py-2 text-xs font-semibold text-slate-700 hover:text-cyan-800 hover:bg-slate-50 rounded-lg transition-colors cursor-pointer"
            >
              <Stethoscope className="w-4 h-4 text-slate-500" />
              <span>Doctor Referral Network & Dispatch</span>
            </button>
            )}

            {canAny(currentUser.permissions, ['catalog view']) && (
            <button
              onClick={() => {
                setActiveTab('masters');
                setIsOpen(false);
              }}
              className="w-full flex items-center space-x-2 px-3 py-2 text-xs font-semibold text-slate-700 hover:text-cyan-800 hover:bg-slate-50 rounded-lg transition-colors cursor-pointer"
            >
              <Building className="w-4 h-4 text-slate-500" />
              <span>Service Catalog & Screening Forms</span>
            </button>
            )}

            {onExportBackup && canAny(currentUser.permissions, ['setting manage']) && (
              <button
                onClick={() => {
                  onExportBackup();
                  setIsOpen(false);
                }}
                className="w-full flex items-center space-x-2 px-3 py-2 text-xs font-semibold text-slate-700 hover:text-cyan-800 hover:bg-slate-50 rounded-lg transition-colors cursor-pointer"
              >
                <Download className="w-4 h-4 text-slate-500" />
                <span>Export RIS Database Backup</span>
              </button>
            )}
          </div>

          {/* Footer / Lock & Sign Out */}
          <div className="p-2 bg-slate-50 flex items-center justify-between gap-2">
            <button
              onClick={() => {
                if (onLockTerminal) {
                  onLockTerminal();
                }
                setIsOpen(false);
              }}
              className="flex-1 flex items-center justify-center space-x-1.5 py-1.5 px-3 bg-white hover:bg-slate-100 border border-slate-200 rounded-lg text-xs font-bold text-slate-700 transition-colors cursor-pointer shadow-xs"
            >
              <Lock className="w-3.5 h-3.5 text-slate-500" />
              <span>Lock Terminal</span>
            </button>

            <button
              onClick={() => {
                if (confirm(`Sign out from current session (${currentUser.name})?`)) {
                  setIsOpen(false);
                  onSignOut();
                }
              }}
              className="flex-1 flex items-center justify-center space-x-1.5 py-1.5 px-3 bg-rose-50 hover:bg-rose-100 border border-rose-200 rounded-lg text-xs font-bold text-rose-700 transition-colors cursor-pointer shadow-xs"
            >
              <LogOut className="w-3.5 h-3.5 text-rose-600" />
              <span>Sign Out</span>
            </button>
          </div>
        </div>
      )}
    </div>
  );
};
