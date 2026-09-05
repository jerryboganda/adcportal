import React, { useState, useRef, useEffect } from 'react';
import {
  User,
  ChevronDown,
  Shield,
  Stethoscope,
  Activity,
  UserCheck,
  CreditCard,
  Settings,
  LogOut,
  Lock,
  Download,
  FileText,
  Clock,
  Sparkles,
  CheckCircle2,
  Tv,
  Check,
  Building,
  KeyRound,
  ShieldAlert,
  UserPlus
} from 'lucide-react';
import { StaffUser, ActiveTab, UserPresenceStatus } from '../types';

interface UserProfileMenuProps {
  role: 'admin' | 'receptionist' | 'technologist' | 'radiologist' | 'patient';
  setRole: (role: 'admin' | 'receptionist' | 'technologist' | 'radiologist' | 'patient') => void;
  staffUsers: StaffUser[];
  activeTab: ActiveTab;
  setActiveTab: (tab: ActiveTab) => void;
  onExportBackup?: () => void;
  onLockTerminal?: () => void;
}

export const UserProfileMenu: React.FC<UserProfileMenuProps> = ({
  role,
  setRole,
  staffUsers,
  activeTab,
  setActiveTab,
  onExportBackup,
  onLockTerminal,
}) => {
  const [isOpen, setIsOpen] = useState(false);
  const [presenceStatus, setPresenceStatus] = useState<UserPresenceStatus>('available');
  const menuRef = useRef<HTMLDivElement>(null);

  // Match the active user details based on selected role
  const getCurrentUser = () => {
    switch (role) {
      case 'radiologist':
        return (
          staffUsers.find(u => u.role === 'radiologist') || {
            id: 'usr-1',
            name: 'Dr. Shahzad Khan, MBBS, FCPS',
            email: 'dr.shahzad@amaddiagnosticcentre.com.pk',
            role: 'radiologist' as const,
            department: 'Radiology & Imaging',
            phone: '+92 300 8501234',
            initials: 'SK',
            isActive: true,
            canSignReports: true,
            canVoidInvoices: false,
            canOverrideScreening: true,
            canEditMasters: false,
            canAccessPacs: true,
            lastLogin: 'Today, 09:15 AM',
          }
        );
      case 'technologist':
        return (
          staffUsers.find(u => u.role === 'technologist') || {
            id: 'usr-2',
            name: 'Kamran Ali (Lead RT)',
            email: 'kamran.tech@amaddiagnosticcentre.com.pk',
            role: 'technologist' as const,
            department: 'MRI & CT Suites',
            phone: '+92 333 5554321',
            initials: 'KA',
            isActive: true,
            canSignReports: false,
            canVoidInvoices: false,
            canOverrideScreening: false,
            canEditMasters: false,
            canAccessPacs: true,
            lastLogin: 'Today, 08:30 AM',
          }
        );
      case 'receptionist':
        return (
          staffUsers.find(u => u.role === 'receptionist') || {
            id: 'usr-3',
            name: 'Amina Bilal',
            email: 'amina.reception@amaddiagnosticcentre.com.pk',
            role: 'receptionist' as const,
            department: 'Front Desk & Registration',
            phone: '+92 312 4447890',
            initials: 'AB',
            isActive: true,
            canSignReports: false,
            canVoidInvoices: true,
            canOverrideScreening: false,
            canEditMasters: false,
            canAccessPacs: false,
            lastLogin: 'Today, 08:00 AM',
          }
        );
      case 'patient':
        return {
          id: 'pat-guest',
          name: 'Patient Portal Guest',
          email: 'patient.portal@amadclinic.pk',
          role: 'patient' as any,
          department: 'Public Patient Self-Service',
          phone: '+92 300 1234567',
          initials: 'PT',
          isActive: true,
          canSignReports: false,
          canVoidInvoices: false,
          canOverrideScreening: false,
          canEditMasters: false,
          canAccessPacs: false,
          lastLogin: 'Online Now',
        };
      case 'admin':
      default:
        return (
          staffUsers.find(u => u.role === 'admin') || {
            id: 'usr-4',
            name: 'Muhammad Farhan (CFO / Admin)',
            email: 'admin@amaddiagnosticcentre.com.pk',
            role: 'admin' as const,
            department: 'Executive Administration',
            phone: '+92 321 9991122',
            initials: 'MF',
            isActive: true,
            canSignReports: true,
            canVoidInvoices: true,
            canOverrideScreening: true,
            canEditMasters: true,
            canAccessPacs: true,
            lastLogin: 'Today, 07:45 AM',
          }
        );
    }
  };

  const currentUser = getCurrentUser();

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

  const getPresenceColor = (status: UserPresenceStatus) => {
    switch (status) {
      case 'available':
        return 'bg-emerald-500 ring-emerald-300';
      case 'reporting':
        return 'bg-purple-500 ring-purple-300';
      case 'in_procedure':
        return 'bg-amber-500 ring-amber-300';
      case 'away':
        return 'bg-slate-400 ring-slate-200';
      case 'busy':
        return 'bg-rose-500 ring-rose-300';
    }
  };

  const getPresenceLabel = (status: UserPresenceStatus) => {
    switch (status) {
      case 'available':
        return 'Available / On Duty';
      case 'reporting':
        return 'In Reporting Session';
      case 'in_procedure':
        return 'In Procedure Suite';
      case 'away':
        return 'Away / On Break';
      case 'busy':
        return 'Busy / Do Not Disturb';
    }
  };

  const getRoleGradient = (currentRole: string) => {
    switch (currentRole) {
      case 'radiologist':
        return 'from-purple-600 via-indigo-600 to-sky-600';
      case 'technologist':
        return 'from-cyan-600 via-teal-600 to-emerald-600';
      case 'receptionist':
        return 'from-sky-600 via-blue-600 to-indigo-600';
      case 'patient':
        return 'from-emerald-600 to-teal-700';
      case 'admin':
      default:
        return 'from-slate-800 via-slate-700 to-cyan-800';
    }
  };

  const getRoleBadgeStyle = (currentRole: string) => {
    switch (currentRole) {
      case 'radiologist':
        return 'bg-purple-100 text-purple-800 border-purple-200';
      case 'technologist':
        return 'bg-cyan-100 text-cyan-800 border-cyan-200';
      case 'receptionist':
        return 'bg-sky-100 text-sky-800 border-sky-200';
      case 'patient':
        return 'bg-emerald-100 text-emerald-800 border-emerald-200';
      case 'admin':
      default:
        return 'bg-slate-800 text-white border-slate-700';
    }
  };

  const getRoleDisplayName = (r: string) => {
    switch (r) {
      case 'radiologist':
        return 'Consultant Radiologist';
      case 'technologist':
        return 'Lead Technologist';
      case 'receptionist':
        return 'Front Desk Officer';
      case 'patient':
        return 'Patient Self-Service';
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
        className={`flex items-center space-x-2 p-1.5 sm:px-3 sm:py-1.5 rounded-xl border transition-all duration-150 cursor-pointer shadow-xs ${
          isOpen
            ? 'bg-slate-100 border-cyan-400 ring-2 ring-cyan-500/20'
            : 'bg-white hover:bg-slate-50 border-slate-200'
        }`}
        aria-expanded={isOpen}
        aria-haspopup="true"
      >
        {/* Avatar Circle with Presence Beacon */}
        <div className="relative shrink-0">
          <div className={`w-8 h-8 rounded-full bg-gradient-to-tr ${getRoleGradient(role)} flex items-center justify-center text-white font-black text-xs shadow-xs tracking-wider border-2 border-white`}>
            {currentUser.initials}
          </div>
          <span
            className={`absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full ring-2 ring-white ${getPresenceColor(
              presenceStatus
            )}`}
            title={getPresenceLabel(presenceStatus)}
          />
        </div>

        {/* User Name & ID Info (Desktop) */}
        <div className="text-left hidden lg:block leading-tight pr-0.5">
          <div className="flex items-center space-x-1.5">
            <span className="text-xs font-bold text-slate-800 tracking-tight block max-w-[140px] truncate">
              {currentUser.name}
            </span>
          </div>
          <div className="flex items-center space-x-1.5 text-[10px] text-slate-500 font-medium">
            <span className="font-mono text-slate-600 bg-slate-100 px-1 py-0.2 rounded font-semibold">
              {currentUser.id.toUpperCase()}
            </span>
            <span>•</span>
            <span className="capitalize text-slate-600">{role}</span>
          </div>
        </div>

        {/* Dropdown Chevron */}
        <ChevronDown
          className={`w-4 h-4 text-slate-400 transition-transform duration-200 ${
            isOpen ? 'rotate-180 text-cyan-600' : ''
          }`}
        />
      </button>

      {/* Luxury Dropdown Menu Card */}
      {isOpen && (
        <div className="absolute right-0 mt-2 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-slate-200/80 z-50 overflow-hidden animate-in fade-in zoom-in-95 duration-150 divide-y divide-slate-100">
          {/* Identity Header */}
          <div className="p-4 bg-gradient-to-b from-slate-900 via-slate-800 to-slate-900 text-white">
            <div className="flex items-start space-x-3.5">
              {/* Large Avatar */}
              <div className="relative shrink-0">
                <div className={`w-13 h-13 rounded-2xl bg-gradient-to-tr ${getRoleGradient(role)} flex items-center justify-center text-white font-black text-lg shadow-lg border-2 border-white/20`}>
                  {currentUser.initials}
                </div>
                <span
                  className={`absolute -bottom-1 -right-1 w-4 h-4 rounded-full ring-2 ring-slate-900 ${getPresenceColor(
                    presenceStatus
                  )}`}
                />
              </div>

              {/* User Bio */}
              <div className="flex-1 min-w-0">
                <div className="flex items-center space-x-1.5 flex-wrap">
                  <h3 className="text-sm font-bold text-white tracking-tight truncate">
                    {currentUser.name}
                  </h3>
                </div>
                <p className="text-xs text-cyan-300 font-semibold mt-0.5">
                  {getRoleDisplayName(role)}
                </p>
                <div className="flex items-center space-x-2 mt-1.5 text-[11px] text-slate-300">
                  <span className="font-mono bg-white/10 px-1.5 py-0.5 rounded text-white/90">
                    ID: {currentUser.id.toUpperCase()}
                  </span>
                  <span className="text-slate-400">•</span>
                  <span className="truncate">{currentUser.department}</span>
                </div>
                <p className="text-[11px] text-slate-400 truncate mt-1">
                  {currentUser.email}
                </p>
              </div>
            </div>

            {/* Live Presence Status Selector */}
            <div className="mt-3.5 pt-3 border-t border-slate-700/60">
              <div className="flex items-center justify-between text-[11px] text-slate-400 mb-1.5">
                <span className="font-semibold uppercase tracking-wider text-[10px] text-slate-400">Clinical Presence</span>
                <span className="text-cyan-300 font-medium">{getPresenceLabel(presenceStatus)}</span>
              </div>
              <div className="grid grid-cols-4 gap-1">
                {(['available', 'reporting', 'in_procedure', 'away'] as UserPresenceStatus[]).map((status) => (
                  <button
                    key={status}
                    onClick={() => setPresenceStatus(status)}
                    className={`py-1 px-1.5 rounded-lg text-[10px] font-bold capitalize transition-colors flex items-center justify-center space-x-1 cursor-pointer ${
                      presenceStatus === status
                        ? 'bg-white/20 text-white shadow-xs border border-white/30'
                        : 'bg-white/5 text-slate-400 hover:bg-white/10 hover:text-slate-200'
                    }`}
                  >
                    <span className={`w-1.5 h-1.5 rounded-full ${getPresenceColor(status).split(' ')[0]}`} />
                    <span className="truncate">
                      {status === 'available' ? 'Online' : status === 'in_procedure' ? 'Scan' : status === 'reporting' ? 'Report' : 'Away'}
                    </span>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Persona / Fast Role Switcher */}
          <div className="p-3 bg-slate-50/70">
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block px-1 mb-2">
              Switch Active RIS Role
            </span>
            <div className="grid grid-cols-1 gap-1">
              {[
                { id: 'admin', label: 'System Administrator', icon: Shield, desc: 'Full governance, RBAC, backups & master catalogs' },
                { id: 'radiologist', label: 'Dr. Shahzad Mir, FRCR (Radiologist)', icon: Stethoscope, desc: 'Reporting workbench, verification & e-signing' },
                { id: 'technologist', label: 'Kamran Ali (Lead Technologist)', icon: Activity, desc: 'Modality worklists, patient prep & dose logging' },
                { id: 'receptionist', label: 'Amina Bilal (Front Desk)', icon: UserCheck, desc: 'Registration, token queue, check-in & POS cashier' },
                { id: 'patient', label: 'Patient Self-Service Portal', icon: User, desc: 'Self booking, appointment view & instant report portal' },
              ].map((item) => {
                const Icon = item.icon;
                const isCurrent = role === item.id;
                return (
                  <button
                    key={item.id}
                    onClick={() => {
                      setRole(item.id as any);
                      setIsOpen(false);
                    }}
                    className={`flex items-center justify-between p-2 rounded-xl text-left transition-all cursor-pointer ${
                      isCurrent
                        ? 'bg-white shadow-xs border border-cyan-300 ring-1 ring-cyan-200 text-cyan-950 font-bold'
                        : 'hover:bg-white text-slate-700 hover:text-slate-900 border border-transparent'
                    }`}
                  >
                    <div className="flex items-center space-x-2.5 min-w-0">
                      <div className={`p-1.5 rounded-lg shrink-0 ${isCurrent ? 'bg-cyan-100 text-cyan-700' : 'bg-slate-200/60 text-slate-600'}`}>
                        <Icon className="w-4 h-4" />
                      </div>
                      <div className="min-w-0">
                        <div className="text-xs font-bold truncate flex items-center space-x-1.5">
                          <span>{item.label}</span>
                        </div>
                        <p className="text-[10px] text-slate-500 font-normal truncate">
                          {item.desc}
                        </p>
                      </div>
                    </div>
                    {isCurrent && (
                      <Check className="w-4 h-4 text-cyan-600 shrink-0 ml-2" />
                    )}
                  </button>
                );
              })}
            </div>
          </div>

          {/* Quick RIS Actions & Settings */}
          <div className="p-2 space-y-0.5">
            <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block px-2 py-1">
              Management & Tools
            </span>

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

            {onExportBackup && (
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
                } else {
                  alert(`Terminal locked for ${currentUser.name}. Click switch role to resume.`);
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
                  setRole('receptionist');
                  setActiveTab('checkin');
                  setIsOpen(false);
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
