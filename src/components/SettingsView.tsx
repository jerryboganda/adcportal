import React, { useState } from 'react';
import {
  Shield,
  UserCheck,
  Building2,
  Server,
  Bell,
  History,
  Database,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  AlertTriangle,
  RefreshCw,
  Download,
  Upload,
  Search,
  Lock,
  Smartphone,
  Send,
  Sliders,
  Check,
  X,
  FileCheck,
  Radio,
  Clock,
  Sparkles,
  Info
} from 'lucide-react';
import {
  StaffUser,
  StaffRole,
  ClinicProfileSettings,
  DicomNodeConfig,
  NotificationTemplate,
  AuditLogEntry
} from '../types';

interface SettingsViewProps {
  staffUsers: StaffUser[];
  onAddStaffUser: (user: Omit<StaffUser, 'id'>) => void;
  onUpdateStaffUser: (user: StaffUser) => void;
  onDeleteStaffUser: (userId: string) => void;

  clinicSettings: ClinicProfileSettings;
  onUpdateClinicSettings: (settings: ClinicProfileSettings) => void;

  dicomNodes: DicomNodeConfig[];
  onAddDicomNode: (node: Omit<DicomNodeConfig, 'id'>) => void;
  onUpdateDicomNode: (node: DicomNodeConfig) => void;
  onDeleteDicomNode: (nodeId: string) => void;

  notificationTemplates: NotificationTemplate[];
  onUpdateNotificationTemplate: (template: NotificationTemplate) => void;

  auditLogs: AuditLogEntry[];
  onAddAuditLog: (log: Omit<AuditLogEntry, 'id' | 'timestamp'>) => void;

  onResetFactoryDefaults: () => void;
  onExportBackup: () => void;
  onImportBackup: (snapshot: any) => void;
}

type SettingsSection = 'users' | 'clinic' | 'dicom' | 'notifications' | 'audit' | 'database';

export const SettingsView: React.FC<SettingsViewProps> = ({
  staffUsers,
  onAddStaffUser,
  onUpdateStaffUser,
  onDeleteStaffUser,
  clinicSettings,
  onUpdateClinicSettings,
  dicomNodes,
  onAddDicomNode,
  onUpdateDicomNode,
  onDeleteDicomNode,
  notificationTemplates,
  onUpdateNotificationTemplate,
  auditLogs,
  onAddAuditLog,
  onResetFactoryDefaults,
  onExportBackup,
  onImportBackup,
}) => {
  const [activeSection, setActiveSection] = useState<SettingsSection>('users');

  // User form modal state
  const [userModalOpen, setUserModalOpen] = useState(false);
  const [editingUser, setEditingUser] = useState<StaffUser | null>(null);
  const [userSearch, setUserSearch] = useState('');
  const [roleFilter, setRoleFilter] = useState<string>('all');

  // Clinic profile form state
  const [profileForm, setProfileForm] = useState<ClinicProfileSettings>({ ...clinicSettings });
  const [profileSavedToast, setProfileSavedToast] = useState(false);

  // DICOM node modal & testing state
  const [nodeModalOpen, setNodeModalOpen] = useState(false);
  const [editingNode, setEditingNode] = useState<DicomNodeConfig | null>(null);
  const [pingingNodeId, setPingingNodeId] = useState<string | null>(null);
  const [pingResult, setPingResult] = useState<{ nodeId: string; success: boolean; latency: number; message: string } | null>(null);

  // Notification tester state
  const [testTemplate, setTestTemplate] = useState<NotificationTemplate | null>(null);
  const [testPhoneNumber, setTestPhoneNumber] = useState('+92 300 5557812');
  const [testDispatchSuccess, setTestDispatchSuccess] = useState(false);

  // Audit search state
  const [auditSearch, setAuditSearch] = useState('');
  const [auditModuleFilter, setAuditModuleFilter] = useState('all');

  // User form state
  const [userName, setUserName] = useState('');
  const [userEmail, setUserEmail] = useState('');
  const [userRole, setUserRole] = useState<StaffRole>('receptionist');
  const [userDepartment, setUserDepartment] = useState('');
  const [userPhone, setUserPhone] = useState('');
  const [userInitials, setUserInitials] = useState('');
  const [canSignReports, setCanSignReports] = useState(false);
  const [canVoidInvoices, setCanVoidInvoices] = useState(false);
  const [canOverrideScreening, setCanOverrideScreening] = useState(false);
  const [canEditMasters, setCanEditMasters] = useState(false);
  const [canAccessPacs, setCanAccessPacs] = useState(false);

  // Node form state
  const [nodeName, setNodeName] = useState('');
  const [nodeAeTitle, setNodeAeTitle] = useState('');
  const [nodeIp, setNodeIp] = useState('');
  const [nodePort, setNodePort] = useState(104);
  const [nodeModality, setNodeModality] = useState('');
  const [nodeIsWorklist, setNodeIsWorklist] = useState(true);
  const [nodeIsStorage, setNodeIsStorage] = useState(false);

  const handleOpenUserModal = (user?: StaffUser) => {
    if (user) {
      setEditingUser(user);
      setUserName(user.name);
      setUserEmail(user.email);
      setUserRole(user.role);
      setUserDepartment(user.department);
      setUserPhone(user.phone);
      setUserInitials(user.initials);
      setCanSignReports(user.canSignReports);
      setCanVoidInvoices(user.canVoidInvoices);
      setCanOverrideScreening(user.canOverrideScreening);
      setCanEditMasters(user.canEditMasters);
      setCanAccessPacs(user.canAccessPacs);
    } else {
      setEditingUser(null);
      setUserName('');
      setUserEmail('');
      setUserRole('receptionist');
      setUserDepartment('Front Desk');
      setUserPhone('');
      setUserInitials('');
      setCanSignReports(false);
      setCanVoidInvoices(false);
      setCanOverrideScreening(false);
      setCanEditMasters(false);
      setCanAccessPacs(false);
    }
    setUserModalOpen(true);
  };

  const handleSaveUser = (e: React.FormEvent) => {
    e.preventDefault();
    if (!userName.trim() || !userEmail.trim()) {
      alert('Please provide full name and email.');
      return;
    }

    const initials = userInitials.trim() || userName.split(' ').map(n => n[0]).join('').substring(0, 3).toUpperCase();

    if (editingUser) {
      onUpdateStaffUser({
        ...editingUser,
        name: userName,
        email: userEmail,
        role: userRole,
        department: userDepartment,
        phone: userPhone,
        initials,
        canSignReports,
        canVoidInvoices,
        canOverrideScreening,
        canEditMasters,
        canAccessPacs,
      });
      onAddAuditLog({
        user: 'Admin Muhammad Farhan',
        role: 'Administrator',
        action: 'User Account Updated',
        module: 'Settings & Security',
        details: `Updated permissions and profile for ${userName} (${userRole}).`,
        status: 'success',
      });
    } else {
      onAddStaffUser({
        name: userName,
        email: userEmail,
        role: userRole,
        department: userDepartment,
        phone: userPhone,
        initials,
        isActive: true,
        canSignReports,
        canVoidInvoices,
        canOverrideScreening,
        canEditMasters,
        canAccessPacs,
        lastLogin: 'Never',
      });
      onAddAuditLog({
        user: 'Admin Muhammad Farhan',
        role: 'Administrator',
        action: 'New Staff User Created',
        module: 'Settings & Security',
        details: `Created new staff account for ${userName} (${userRole}).`,
        status: 'success',
      });
    }
    setUserModalOpen(false);
  };

  const handleSaveClinicProfile = (e: React.FormEvent) => {
    e.preventDefault();
    onUpdateClinicSettings(profileForm);
    onAddAuditLog({
      user: 'Admin Muhammad Farhan',
      role: 'Administrator',
      action: 'Clinic Profile Updated',
      module: 'Settings & Branding',
      details: `Updated regulatory registrations, branch contacts, and disclaimers for ${profileForm.name}.`,
      status: 'success',
    });
    setProfileSavedToast(true);
    setTimeout(() => setProfileSavedToast(false), 3500);
  };

  const handleOpenNodeModal = (node?: DicomNodeConfig) => {
    if (node) {
      setEditingNode(node);
      setNodeName(node.nodeName);
      setNodeAeTitle(node.aeTitle);
      setNodeIp(node.ipAddress);
      setNodePort(node.port);
      setNodeModality(node.modalityCode || '');
      setNodeIsWorklist(node.isWorklistSCP);
      setNodeIsStorage(node.isStorageSCP);
    } else {
      setEditingNode(null);
      setNodeName('');
      setNodeAeTitle('NEW_AE_TITLE');
      setNodeIp('192.168.10.');
      setNodePort(104);
      setNodeModality('');
      setNodeIsWorklist(true);
      setNodeIsStorage(false);
    }
    setNodeModalOpen(true);
  };

  const handleSaveNode = (e: React.FormEvent) => {
    e.preventDefault();
    if (!nodeName.trim() || !nodeAeTitle.trim() || !nodeIp.trim()) {
      alert('Please fill all required DICOM network parameters.');
      return;
    }

    if (editingNode) {
      onUpdateDicomNode({
        ...editingNode,
        nodeName,
        aeTitle: nodeAeTitle.toUpperCase(),
        ipAddress: nodeIp,
        port: nodePort,
        modalityCode: nodeModality || undefined,
        isWorklistSCP: nodeIsWorklist,
        isStorageSCP: nodeIsStorage,
      });
    } else {
      onAddDicomNode({
        nodeName,
        aeTitle: nodeAeTitle.toUpperCase(),
        ipAddress: nodeIp,
        port: nodePort,
        modalityCode: nodeModality || undefined,
        isWorklistSCP: nodeIsWorklist,
        isStorageSCP: nodeIsStorage,
        status: 'online',
        lastPingTime: 'Just now',
        lastPingLatencyMs: 12,
      });
    }
    setNodeModalOpen(false);
  };

  const handleTestPingNode = (node: DicomNodeConfig) => {
    setPingingNodeId(node.id);
    setPingResult(null);

    setTimeout(() => {
      const simulatedLatency = Math.floor(6 + Math.random() * 18);
      setPingingNodeId(null);
      setPingResult({
        nodeId: node.id,
        success: true,
        latency: simulatedLatency,
        message: `DICOM C-ECHO Response received from ${node.aeTitle}@${node.ipAddress}:${node.port} in ${simulatedLatency}ms. Node status is ONLINE and compliant with DICOM 3.0 standard.`,
      });

      onUpdateDicomNode({
        ...node,
        status: 'online',
        lastPingTime: 'Just now',
        lastPingLatencyMs: simulatedLatency,
      });
    }, 900);
  };

  const handleSendTestMessage = () => {
    if (!testTemplate) return;
    setTestDispatchSuccess(true);
    onAddAuditLog({
      user: 'Staff Tester',
      role: 'Administrator',
      action: 'Test Notification Dispatched',
      module: 'SMS/WhatsApp Gateway',
      details: `Dispatched test message (${testTemplate.name}) to ${testPhoneNumber} via ${testTemplate.channel.toUpperCase()}.`,
      status: 'success',
    });
    setTimeout(() => setTestDispatchSuccess(false), 4000);
  };

  // Filtered users
  const filteredUsers = staffUsers.filter(u => {
    const matchesSearch =
      u.name.toLowerCase().includes(userSearch.toLowerCase()) ||
      u.email.toLowerCase().includes(userSearch.toLowerCase()) ||
      u.department.toLowerCase().includes(userSearch.toLowerCase());
    const matchesRole = roleFilter === 'all' || u.role === roleFilter;
    return matchesSearch && matchesRole;
  });

  // Filtered audit logs
  const filteredAuditLogs = auditLogs.filter(log => {
    const matchesSearch =
      log.user.toLowerCase().includes(auditSearch.toLowerCase()) ||
      log.details.toLowerCase().includes(auditSearch.toLowerCase()) ||
      log.action.toLowerCase().includes(auditSearch.toLowerCase());
    const matchesModule = auditModuleFilter === 'all' || log.module.toLowerCase().includes(auditModuleFilter.toLowerCase());
    return matchesSearch && matchesModule;
  });

  return (
    <div className="space-y-6">
      {/* Top Header Banner */}
      <div className="bg-gradient-to-r from-slate-900 via-slate-800 to-cyan-950 rounded-2xl p-6 text-white shadow-lg border border-slate-700/50">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="inline-flex items-center space-x-2 px-2.5 py-0.5 rounded-full bg-cyan-500/20 text-cyan-300 text-xs font-semibold border border-cyan-500/30">
              <Sliders className="w-3.5 h-3.5" />
              <span>System Administration & Access Management</span>
            </div>
            <h1 className="text-2xl font-black tracking-tight text-white">System Settings & Governance</h1>
            <p className="text-sm text-slate-300">
              Manage user roles, RBAC access permissions, PACS DICOM server nodes, WhatsApp/SMS gateways, and system audit logs.
            </p>
          </div>
          <div className="flex items-center space-x-3">
            <button
              onClick={onExportBackup}
              className="flex items-center space-x-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-600 text-xs font-semibold transition-all shadow-xs cursor-pointer"
            >
              <Download className="w-4 h-4 text-cyan-400" />
              <span>Export Backup</span>
            </button>
            <button
              onClick={onResetFactoryDefaults}
              className="flex items-center space-x-2 px-3.5 py-2 rounded-xl bg-rose-950/60 hover:bg-rose-900/80 text-rose-200 border border-rose-800/80 text-xs font-semibold transition-all shadow-xs cursor-pointer"
            >
              <RefreshCw className="w-4 h-4 text-rose-400" />
              <span>Factory Reset</span>
            </button>
          </div>
        </div>

        {/* Navigation Tabs */}
        <div className="flex space-x-1.5 mt-6 border-t border-slate-700/60 pt-4 overflow-x-auto scrollbar-none">
          {[
            { id: 'users', label: 'Users & RBAC Access', icon: Shield, count: staffUsers.length },
            { id: 'clinic', label: 'Clinic & Branch Setup', icon: Building2 },
            { id: 'dicom', label: 'PACS & DICOM Nodes', icon: Server, count: dicomNodes.length },
            { id: 'notifications', label: 'SMS & WhatsApp Gateway', icon: Bell, count: notificationTemplates.length },
            { id: 'audit', label: 'System Audit Logs', icon: History, count: auditLogs.length },
            { id: 'database', label: 'Data & Maintenance', icon: Database },
          ].map(tab => {
            const Icon = tab.icon;
            const isActive = activeSection === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveSection(tab.id as SettingsSection)}
                className={`flex items-center space-x-2 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
                  isActive
                    ? 'bg-cyan-500 text-white shadow-md shadow-cyan-500/25'
                    : 'text-slate-300 hover:text-white hover:bg-slate-800/70'
                }`}
              >
                <Icon className="w-4 h-4" />
                <span>{tab.label}</span>
                {tab.count !== undefined && (
                  <span className={`text-[10px] px-1.5 py-0.2 rounded-md font-extrabold ${isActive ? 'bg-cyan-700 text-white' : 'bg-slate-700 text-slate-300'}`}>
                    {tab.count}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* SECTION 1: USERS & RBAC ACCESS */}
      {activeSection === 'users' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div className="flex flex-wrap items-center gap-3 flex-1">
              <div className="relative flex-1 min-w-[240px]">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Search staff by name, email, department..."
                  value={userSearch}
                  onChange={e => setUserSearch(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-cyan-500 text-slate-800"
                />
              </div>
              <div className="flex items-center space-x-1.5">
                <span className="text-xs text-slate-500 font-medium">Role:</span>
                <select
                  value={roleFilter}
                  onChange={e => setRoleFilter(e.target.value)}
                  className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                >
                  <option value="all">All Roles ({staffUsers.length})</option>
                  <option value="admin">Administrators</option>
                  <option value="radiologist">Radiologists</option>
                  <option value="technologist">Technologists</option>
                  <option value="receptionist">Receptionists</option>
                  <option value="billing">Billing Officers</option>
                </select>
              </div>
            </div>
            <button
              onClick={() => handleOpenUserModal()}
              className="flex items-center space-x-1.5 px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer shrink-0"
            >
              <Plus className="w-4 h-4" />
              <span>Add Staff User</span>
            </button>
          </div>

          {/* User Cards Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredUsers.map(user => {
              const roleBadgeColor =
                user.role === 'admin'
                  ? 'bg-rose-50 text-rose-700 border-rose-200'
                  : user.role === 'radiologist'
                  ? 'bg-purple-50 text-purple-700 border-purple-200'
                  : user.role === 'technologist'
                  ? 'bg-blue-50 text-blue-700 border-blue-200'
                  : user.role === 'receptionist'
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-200'
                  : 'bg-amber-50 text-amber-700 border-amber-200';

              return (
                <div key={user.id} className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs hover:border-cyan-300 transition-all flex flex-col justify-between">
                  <div>
                    <div className="flex items-start justify-between">
                      <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 rounded-full bg-slate-900 text-white font-bold flex items-center justify-center text-sm shadow-xs">
                          {user.initials}
                        </div>
                        <div>
                          <h3 className="font-bold text-slate-900 text-sm">{user.name}</h3>
                          <p className="text-xs text-slate-500">{user.email}</p>
                        </div>
                      </div>
                      <span className={`text-[10px] uppercase tracking-wider font-extrabold px-2 py-0.5 rounded-md border ${roleBadgeColor}`}>
                        {user.role}
                      </span>
                    </div>

                    <div className="mt-4 space-y-2 text-xs text-slate-600 bg-slate-50/70 p-3 rounded-lg border border-slate-100">
                      <div className="flex items-center justify-between">
                        <span className="text-slate-500">Department:</span>
                        <span className="font-semibold text-slate-800">{user.department}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-slate-500">Phone:</span>
                        <span className="font-mono text-slate-800">{user.phone || 'N/A'}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-slate-500">Last Login:</span>
                        <span className="text-slate-600">{user.lastLogin || 'Recent'}</span>
                      </div>
                    </div>

                    {/* Permissions Badges */}
                    <div className="mt-3">
                      <span className="text-[11px] font-semibold text-slate-500 block mb-1.5">RBAC Permissions:</span>
                      <div className="flex flex-wrap gap-1.5">
                        {user.canSignReports && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-semibold">Sign Reports</span>
                        )}
                        {user.canVoidInvoices && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-amber-100 text-amber-800 font-semibold">Void Invoices</span>
                        )}
                        {user.canOverrideScreening && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-rose-100 text-rose-800 font-semibold">Safety Override</span>
                        )}
                        {user.canAccessPacs && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-cyan-100 text-cyan-800 font-semibold">PACS Access</span>
                        )}
                        {user.canEditMasters && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-semibold">Edit Masters</span>
                        )}
                        {!user.canSignReports && !user.canVoidInvoices && !user.canOverrideScreening && !user.canAccessPacs && !user.canEditMasters && (
                          <span className="text-[10px] px-2 py-0.5 rounded bg-slate-100 text-slate-600 font-medium">Standard Operator</span>
                        )}
                      </div>
                    </div>
                  </div>

                  <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                    <span className="inline-flex items-center space-x-1 text-xs text-emerald-600 font-medium">
                      <CheckCircle2 className="w-3.5 h-3.5" />
                      <span>Active Staff</span>
                    </span>
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => handleOpenUserModal(user)}
                        className="p-1.5 rounded-lg text-slate-600 hover:text-cyan-600 hover:bg-slate-100 text-xs font-medium flex items-center space-x-1 cursor-pointer transition-colors"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                        <span>Edit</span>
                      </button>
                      <button
                        onClick={() => {
                          if (confirm(`Are you sure you want to remove user account for ${user.name}?`)) {
                            onDeleteStaffUser(user.id);
                          }
                        }}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 text-xs font-medium cursor-pointer transition-colors"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          {/* RBAC Permission Matrix Reference */}
          <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs">
            <h3 className="font-bold text-slate-900 text-sm mb-2 flex items-center space-x-2">
              <Shield className="w-4 h-4 text-cyan-600" />
              <span>Role-Based Access Control (RBAC) Permission Matrix</span>
            </h3>
            <p className="text-xs text-slate-500 mb-4">
              Overview of default capability authorizations enforced across Amad Diagnostic Centre RIS Portal.
            </p>
            <div className="overflow-x-auto">
              <table className="w-full text-left text-xs border-collapse">
                <thead>
                  <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                    <th className="p-2.5">System Capability</th>
                    <th className="p-2.5 text-center">Admin</th>
                    <th className="p-2.5 text-center">Radiologist</th>
                    <th className="p-2.5 text-center">Technologist</th>
                    <th className="p-2.5 text-center">Receptionist</th>
                    <th className="p-2.5 text-center">Billing Officer</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  <tr>
                    <td className="p-2.5 font-medium">Patient Check-in & Intake</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                  </tr>
                  <tr>
                    <td className="p-2.5 font-medium">Image Acquisition & Dose Logging</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                  </tr>
                  <tr>
                    <td className="p-2.5 font-medium">Radiological Findings & Sign-Off</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                  </tr>
                  <tr>
                    <td className="p-2.5 font-medium">Cash POS & Invoice Issuance</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                  </tr>
                  <tr>
                    <td className="p-2.5 font-medium">Override Screening Risk Flags</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                  </tr>
                  <tr>
                    <td className="p-2.5 font-medium">DICOM Node & Master Catalog Edit</td>
                    <td className="p-2.5 text-center text-emerald-600 font-bold">✓</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                    <td className="p-2.5 text-center text-slate-300">—</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* SECTION 2: CLINIC PROFILE & BRANCH SETUP */}
      {activeSection === 'clinic' && (
        <form onSubmit={handleSaveClinicProfile} className="space-y-6">
          {profileSavedToast && (
            <div className="p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center space-x-2">
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
              <span>Clinic settings and legal disclaimers successfully saved to storage!</span>
            </div>
          )}

          <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-6">
            <div>
              <h3 className="font-bold text-slate-900 text-sm mb-1">Diagnostic Centre Identification & Contacts</h3>
              <p className="text-xs text-slate-500">Official information printed on diagnostic reports, invoice receipts, and portal headers.</p>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
              <div>
                <label className="block text-slate-700 font-semibold mb-1">Institution Legal Name</label>
                <input
                  type="text"
                  value={profileForm.name}
                  onChange={e => setProfileForm({ ...profileForm, name: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-semibold focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Branch / Campus</label>
                <input
                  type="text"
                  value={profileForm.branch}
                  onChange={e => setProfileForm({ ...profileForm, branch: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Physical Address</label>
                <input
                  type="text"
                  value={profileForm.address}
                  onChange={e => setProfileForm({ ...profileForm, address: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">City & Postal Code</label>
                <input
                  type="text"
                  value={profileForm.city}
                  onChange={e => setProfileForm({ ...profileForm, city: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Reception Phone / Landline</label>
                <input
                  type="text"
                  value={profileForm.phone}
                  onChange={e => setProfileForm({ ...profileForm, phone: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500 font-mono"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Emergency / 24/7 Helpline</label>
                <input
                  type="text"
                  value={profileForm.emergencyPhone}
                  onChange={e => setProfileForm({ ...profileForm, emergencyPhone: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500 font-mono"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Official Email</label>
                <input
                  type="email"
                  value={profileForm.email}
                  onChange={e => setProfileForm({ ...profileForm, email: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Portal URL / Domain</label>
                <input
                  type="text"
                  value={profileForm.website}
                  onChange={e => setProfileForm({ ...profileForm, website: e.target.value })}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500 font-mono"
                />
              </div>
            </div>

            <div className="pt-4 border-t border-slate-200">
              <h3 className="font-bold text-slate-900 text-sm mb-1">Statutory Licensures & Tax Registrations</h3>
              <p className="text-xs text-slate-500 mb-4">Regulatory credentials mandatory under PNRA & Healthcare Commission statutes.</p>
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
                <div>
                  <label className="block text-slate-700 font-semibold mb-1">PNRA Radiation License #</label>
                  <input
                    type="text"
                    value={profileForm.pnraLicenseNo}
                    onChange={e => setProfileForm({ ...profileForm, pnraLicenseNo: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
                <div>
                  <label className="block text-slate-700 font-semibold mb-1">Healthcare Commission / PMDC Reg #</label>
                  <input
                    type="text"
                    value={profileForm.pmcRegistrationNo}
                    onChange={e => setProfileForm({ ...profileForm, pmcRegistrationNo: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
                <div>
                  <label className="block text-slate-700 font-semibold mb-1">Tax NTN / Sales Tax STRN</label>
                  <input
                    type="text"
                    value={profileForm.taxId}
                    onChange={e => setProfileForm({ ...profileForm, taxId: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
              </div>
            </div>

            <div className="pt-4 border-t border-slate-200">
              <h3 className="font-bold text-slate-900 text-sm mb-1">Disclaimers & Clinical Governance Policies</h3>
              <div className="grid grid-cols-1 gap-4 text-xs mt-3">
                <div>
                  <label className="block text-slate-700 font-semibold mb-1">Official Invoice Receipt Disclaimer</label>
                  <textarea
                    rows={2}
                    value={profileForm.invoiceFooterDisclaimer}
                    onChange={e => setProfileForm({ ...profileForm, invoiceFooterDisclaimer: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
                <div>
                  <label className="block text-slate-700 font-semibold mb-1">Radiology Diagnostic Legal Disclaimer</label>
                  <textarea
                    rows={2}
                    value={profileForm.reportLegalDisclaimer}
                    onChange={e => setProfileForm({ ...profileForm, reportLegalDisclaimer: e.target.value })}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
              </div>

              {/* Policy Toggles */}
              <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 pt-3 border-t border-slate-100">
                <label className="flex items-center space-x-2 text-xs text-slate-700 cursor-pointer font-medium">
                  <input
                    type="checkbox"
                    checked={profileForm.requireScreeningSignOff}
                    onChange={e => setProfileForm({ ...profileForm, requireScreeningSignOff: e.target.checked })}
                    className="rounded text-cyan-600 focus:ring-cyan-500"
                  />
                  <span>Enforce Safety Screening Clearance</span>
                </label>

                <label className="flex items-center space-x-2 text-xs text-slate-700 cursor-pointer font-medium">
                  <input
                    type="checkbox"
                    checked={profileForm.enableCriticalFindingsAlerts}
                    onChange={e => setProfileForm({ ...profileForm, enableCriticalFindingsAlerts: e.target.checked })}
                    className="rounded text-cyan-600 focus:ring-cyan-500"
                  />
                  <span>Enable STAT Critical Alert Pushes</span>
                </label>

                <label className="flex items-center space-x-2 text-xs text-slate-700 cursor-pointer font-medium">
                  <input
                    type="checkbox"
                    checked={profileForm.autoSendWhatsappReport}
                    onChange={e => setProfileForm({ ...profileForm, autoSendWhatsappReport: e.target.checked })}
                    className="rounded text-cyan-600 focus:ring-cyan-500"
                  />
                  <span>Auto-Send WhatsApp on Report Sign-off</span>
                </label>
              </div>
            </div>

            <div className="pt-4 border-t border-slate-200 flex justify-end">
              <button
                type="submit"
                className="px-6 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold transition-all shadow-md shadow-cyan-600/20 cursor-pointer flex items-center space-x-2"
              >
                <Check className="w-4 h-4" />
                <span>Save Profile Changes</span>
              </button>
            </div>
          </div>
        </form>
      )}

      {/* SECTION 3: PACS & DICOM NODES */}
      {activeSection === 'dicom' && (
        <div className="space-y-6">
          <div className="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div>
              <h3 className="font-bold text-slate-900 text-sm">DICOM Application Entities & PACS Topology</h3>
              <p className="text-xs text-slate-500">Configured Modality Worklist (MWL) and Storage SCP nodes across ADC radiology network.</p>
            </div>
            <button
              onClick={() => handleOpenNodeModal()}
              className="flex items-center space-x-1.5 px-4 py-2 rounded-lg bg-cyan-600 hover:bg-cyan-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>Add DICOM Node</span>
            </button>
          </div>

          {/* Ping Result Notification */}
          {pingResult && (
            <div className={`p-4 rounded-xl text-xs font-medium border flex items-start space-x-3 ${pingResult.success ? 'bg-emerald-50 text-emerald-900 border-emerald-200' : 'bg-rose-50 text-rose-900 border-rose-200'}`}>
              <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0 mt-0.5" />
              <div className="flex-1">
                <p className="font-bold">{pingResult.message}</p>
                <p className="text-[11px] text-emerald-700 mt-1">DICOM SOP Class: Verification SOP Class (1.2.840.10008.1.1) • Ping Latency: {pingResult.latency}ms</p>
              </div>
              <button onClick={() => setPingResult(null)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                <X className="w-4 h-4" />
              </button>
            </div>
          )}

          {/* DICOM Nodes Grid */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {dicomNodes.map(node => (
              <div key={node.id} className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs hover:border-cyan-300 transition-all flex flex-col justify-between">
                <div>
                  <div className="flex items-start justify-between">
                    <div>
                      <h4 className="font-bold text-slate-900 text-sm">{node.nodeName}</h4>
                      <p className="text-xs font-mono text-cyan-700 font-bold mt-0.5">AE: {node.aeTitle}</p>
                    </div>
                    <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                      <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-ping" />
                      <span>{node.status.toUpperCase()}</span>
                    </span>
                  </div>

                  <div className="mt-4 space-y-1.5 text-xs text-slate-600 bg-slate-50 p-3 rounded-lg border border-slate-100 font-mono">
                    <div className="flex justify-between">
                      <span className="text-slate-500">Host IP:</span>
                      <span className="font-bold text-slate-800">{node.ipAddress}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Port:</span>
                      <span className="font-bold text-slate-800">{node.port}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Modality:</span>
                      <span className="font-bold text-slate-800">{node.modalityCode || 'All Modalities'}</span>
                    </div>
                    <div className="flex justify-between">
                      <span className="text-slate-500">Last Verified:</span>
                      <span className="text-slate-700">{node.lastPingTime || 'Recently'} ({node.lastPingLatencyMs || 10}ms)</span>
                    </div>
                  </div>

                  <div className="mt-3 flex items-center space-x-2">
                    {node.isWorklistSCP && (
                      <span className="text-[10px] px-2 py-0.5 rounded bg-blue-100 text-blue-800 font-semibold">Modality Worklist (MWL)</span>
                    )}
                    {node.isStorageSCP && (
                      <span className="text-[10px] px-2 py-0.5 rounded bg-purple-100 text-purple-800 font-semibold">Storage SCP</span>
                    )}
                  </div>
                </div>

                <div className="mt-5 pt-3 border-t border-slate-100 flex items-center justify-between">
                  <button
                    onClick={() => handleTestPingNode(node)}
                    disabled={pingingNodeId === node.id}
                    className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-cyan-50 text-slate-700 hover:text-cyan-700 text-xs font-semibold transition-all cursor-pointer"
                  >
                    <Radio className={`w-3.5 h-3.5 ${pingingNodeId === node.id ? 'animate-spin text-cyan-600' : 'text-slate-500'}`} />
                    <span>{pingingNodeId === node.id ? 'Echoing...' : 'Ping C-ECHO'}</span>
                  </button>

                  <div className="flex items-center space-x-2">
                    <button
                      onClick={() => handleOpenNodeModal(node)}
                      className="p-1.5 text-slate-500 hover:text-cyan-600 hover:bg-slate-100 rounded-lg cursor-pointer"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    <button
                      onClick={() => {
                        if (confirm(`Remove DICOM node ${node.nodeName}?`)) {
                          onDeleteDicomNode(node.id);
                        }
                      }}
                      className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg cursor-pointer"
                    >
                      <Trash2 className="w-3.5 h-3.5" />
                    </button>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* SECTION 4: NOTIFICATIONS & SMS / WHATSAPP GATEWAY */}
      {activeSection === 'notifications' && (
        <div className="space-y-6">
          <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
              <div>
                <h3 className="font-bold text-slate-900 text-sm">Automated Messaging & SMS / WhatsApp Gateway</h3>
                <p className="text-xs text-slate-500">Configure appointment alerts, ready report portal links, and emergency doctor notifications.</p>
              </div>
              <div className="flex items-center space-x-2">
                <span className="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                  <span className="w-2 h-2 rounded-full bg-emerald-500" />
                  <span>Gateway Status: Live & Connected</span>
                </span>
              </div>
            </div>

            {/* Template List */}
            <div className="mt-5 space-y-4">
              {notificationTemplates.map(tpl => (
                <div key={tpl.id} className="p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white transition-all space-y-3">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                      <span className={`text-[10px] uppercase font-bold px-2 py-0.5 rounded ${tpl.channel === 'whatsapp' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}`}>
                        {tpl.channel}
                      </span>
                      <h4 className="font-bold text-slate-900 text-xs">{tpl.name}</h4>
                    </div>
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => {
                          setTestTemplate(tpl);
                          setTestDispatchSuccess(false);
                        }}
                        className="px-2.5 py-1 rounded-md bg-white border border-slate-200 text-slate-700 hover:text-cyan-700 hover:border-cyan-300 text-xs font-semibold shadow-xs cursor-pointer flex items-center space-x-1"
                      >
                        <Send className="w-3 h-3 text-cyan-600" />
                        <span>Test Send</span>
                      </button>
                      <label className="relative inline-flex items-center cursor-pointer">
                        <input
                          type="checkbox"
                          checked={tpl.enabled}
                          onChange={e => onUpdateNotificationTemplate({ ...tpl, enabled: e.target.checked })}
                          className="sr-only peer"
                        />
                        <div className="w-8 h-4 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-cyan-600"></div>
                      </label>
                    </div>
                  </div>

                  <textarea
                    rows={2}
                    value={tpl.templateBody}
                    onChange={e => onUpdateNotificationTemplate({ ...tpl, templateBody: e.target.value })}
                    className="w-full text-xs p-2.5 bg-white border border-slate-200 rounded-lg text-slate-800 font-mono focus:ring-1 focus:ring-cyan-500"
                  />
                  <p className="text-[11px] text-slate-400">Available variables: <code className="text-slate-600 font-semibold">{'{patient_name}'}, {'{mrn}'}, {'{token}'}, {'{study_name}'}, {'{time}'}, {'{report_link}'}, {'{clinic_phone}'}</code></p>
                </div>
              ))}
            </div>
          </div>

          {/* Test Dispatch Modal / Drawer */}
          {testTemplate && (
            <div className="bg-white rounded-xl border border-cyan-200 p-5 shadow-md space-y-4">
              <div className="flex items-center justify-between pb-2 border-b border-slate-100">
                <div className="flex items-center space-x-2">
                  <Smartphone className="w-4 h-4 text-cyan-600" />
                  <h4 className="font-bold text-slate-900 text-sm">Live Dispatch Test: {testTemplate.name}</h4>
                </div>
                <button onClick={() => setTestTemplate(null)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                  <X className="w-4 h-4" />
                </button>
              </div>

              {testDispatchSuccess ? (
                <div className="p-3 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-lg text-xs font-semibold flex items-center space-x-2">
                  <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
                  <span>Dispatched successfully to {testPhoneNumber}! Message ID: MSG-2026-{Math.floor(100000 + Math.random() * 900000)}</span>
                </div>
              ) : (
                <div className="space-y-3 text-xs">
                  <div>
                    <label className="block text-slate-700 font-semibold mb-1">Destination Phone Number</label>
                    <input
                      type="text"
                      value={testPhoneNumber}
                      onChange={e => setTestPhoneNumber(e.target.value)}
                      className="w-full max-w-sm px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono"
                    />
                  </div>
                  <div className="p-3 bg-slate-50 rounded-lg border border-slate-200 font-mono text-[11px] text-slate-700">
                    <span className="text-slate-400 block mb-1">Preview with sample values:</span>
                    {testTemplate.templateBody
                      .replace('{patient_name}', 'Muhammad Haroon')
                      .replace('{token}', 'MR-01')
                      .replace('{mrn}', 'ADC-2026-08142')
                      .replace('{study_name}', 'MRI Lumbar Spine')
                      .replace('{time}', '09:30 AM')
                      .replace('{date}', 'Today')
                      .replace('{report_link}', 'https://portal.amaddiagnosticcentre.com.pk/report/ADC-2026-08142')
                      .replace('{clinic_phone}', '+92 51 2801122')
                      .replace('{doctor_name}', 'Dr. Shahzad Khan')
                      .replace('{prep_notes}', 'Remove metal items')}
                  </div>
                  <button
                    onClick={handleSendTestMessage}
                    className="px-4 py-2 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-bold text-xs shadow-xs cursor-pointer flex items-center space-x-1.5"
                  >
                    <Send className="w-3.5 h-3.5" />
                    <span>Trigger Live Dispatch</span>
                  </button>
                </div>
              )}
            </div>
          )}
        </div>
      )}

      {/* SECTION 5: SYSTEM AUDIT LOGS */}
      {activeSection === 'audit' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div className="flex flex-wrap items-center gap-3 flex-1">
              <div className="relative flex-1 min-w-[240px]">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Filter audit logs by action, user, details..."
                  value={auditSearch}
                  onChange={e => setAuditSearch(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-cyan-500 text-slate-800"
                />
              </div>
              <select
                value={auditModuleFilter}
                onChange={e => setAuditModuleFilter(e.target.value)}
                className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 font-medium text-slate-700"
              >
                <option value="all">All Modules</option>
                <option value="Radiology">Radiology Reports</option>
                <option value="Tech">Tech Worklist</option>
                <option value="Billing">Billing & POS</option>
                <option value="Reception">Reception Desk</option>
                <option value="Settings">Settings & Security</option>
              </select>
            </div>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                  <th className="p-3">Timestamp</th>
                  <th className="p-3">User & Role</th>
                  <th className="p-3">Action</th>
                  <th className="p-3">Module</th>
                  <th className="p-3">Details</th>
                  <th className="p-3">IP Address</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {filteredAuditLogs.map(log => (
                  <tr key={log.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="p-3 font-mono text-slate-500 whitespace-nowrap">{log.timestamp}</td>
                    <td className="p-3 font-semibold text-slate-900 whitespace-nowrap">
                      {log.user}
                      <span className="block text-[11px] font-normal text-slate-500">{log.role}</span>
                    </td>
                    <td className="p-3 font-bold text-slate-800 whitespace-nowrap">
                      <span className={`inline-block px-2 py-0.5 rounded text-[10px] ${log.status === 'danger' ? 'bg-rose-100 text-rose-800' : log.status === 'warning' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`}>
                        {log.action}
                      </span>
                    </td>
                    <td className="p-3 text-slate-600 whitespace-nowrap">{log.module}</td>
                    <td className="p-3 text-slate-700">{log.details}</td>
                    <td className="p-3 font-mono text-slate-400 text-[11px]">{log.ipAddress || '192.168.10.x'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* SECTION 6: DATA & DATABASE MAINTENANCE */}
      {activeSection === 'database' && (
        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          {/* Backup Card */}
          <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
            <div className="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-600 flex items-center justify-center font-bold">
              <Download className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-bold text-slate-900 text-sm">Export Database Snapshot</h3>
              <p className="text-xs text-slate-500 mt-1">
                Creates an immutable full JSON backup of all appointments, patients, radiology reports, invoices, DICOM nodes, and audit logs.
              </p>
            </div>
            <button
              onClick={onExportBackup}
              className="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer flex items-center justify-center space-x-2"
            >
              <Download className="w-4 h-4 text-cyan-400" />
              <span>Download JSON Backup Snapshot</span>
            </button>
          </div>

          {/* Import Card */}
          <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
            <div className="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold">
              <Upload className="w-5 h-5" />
            </div>
            <div>
              <h3 className="font-bold text-slate-900 text-sm">Restore from Backup Snapshot</h3>
              <p className="text-xs text-slate-500 mt-1">
                Upload a verified JSON snapshot file to restore records across all clinical departments.
              </p>
            </div>
            <label className="w-full py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl text-xs font-bold transition-all shadow-xs cursor-pointer flex items-center justify-center space-x-2">
              <Upload className="w-4 h-4" />
              <span>Select Backup File to Restore</span>
              <input
                type="file"
                accept=".json"
                className="hidden"
                onChange={e => {
                  const file = e.target.files?.[0];
                  if (file) {
                    const reader = new FileReader();
                    reader.onload = ev => {
                      try {
                        const parsed = JSON.parse(ev.target?.result as string);
                        onImportBackup(parsed);
                      } catch (err) {
                        alert('Invalid JSON file format.');
                      }
                    };
                    reader.readAsText(file);
                  }
                }}
              />
            </label>
          </div>
        </div>
      )}

      {/* User Add / Edit Modal */}
      {userModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="font-bold text-slate-900 text-base">
                {editingUser ? 'Edit Staff Account' : 'Create New Staff User'}
              </h3>
              <button onClick={() => setUserModalOpen(false)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveUser} className="space-y-4 text-xs">
              <div className="grid grid-cols-2 gap-3">
                <div className="col-span-2 sm:col-span-1">
                  <label className="block font-semibold text-slate-700 mb-1">Full Name *</label>
                  <input
                    type="text"
                    required
                    value={userName}
                    onChange={e => setUserName(e.target.value)}
                    placeholder="e.g. Dr. Ayesha Siddiqui"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
                <div className="col-span-2 sm:col-span-1">
                  <label className="block font-semibold text-slate-700 mb-1">Email Address *</label>
                  <input
                    type="email"
                    required
                    value={userEmail}
                    onChange={e => setUserEmail(e.target.value)}
                    placeholder="email@amaddiagnosticcentre.com.pk"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Staff Role</label>
                  <select
                    value={userRole}
                    onChange={e => setUserRole(e.target.value as StaffRole)}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-medium"
                  >
                    <option value="admin">Administrator</option>
                    <option value="radiologist">Radiologist</option>
                    <option value="technologist">Technologist</option>
                    <option value="receptionist">Receptionist</option>
                    <option value="billing">Billing Officer</option>
                  </select>
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Department</label>
                  <input
                    type="text"
                    value={userDepartment}
                    onChange={e => setUserDepartment(e.target.value)}
                    placeholder="e.g. MRI & CT Suites"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Phone Number</label>
                  <input
                    type="text"
                    value={userPhone}
                    onChange={e => setUserPhone(e.target.value)}
                    placeholder="+92 300 1234567"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Initials / Badge Code</label>
                  <input
                    type="text"
                    value={userInitials}
                    onChange={e => setUserInitials(e.target.value)}
                    placeholder="e.g. AS"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-bold uppercase"
                  />
                </div>
              </div>

              {/* Granular Permission Checklist */}
              <div className="pt-2 border-t border-slate-100">
                <span className="font-semibold text-slate-700 block mb-2">Granular Role Permissions</span>
                <div className="space-y-2 bg-slate-50 p-3 rounded-xl border border-slate-200">
                  <label className="flex items-center space-x-2 text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={canSignReports}
                      onChange={e => setCanSignReports(e.target.checked)}
                      className="rounded text-cyan-600 focus:ring-cyan-500"
                    />
                    <span>Can Authorize & Cryptographically Sign Radiology Reports</span>
                  </label>

                  <label className="flex items-center space-x-2 text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={canVoidInvoices}
                      onChange={e => setCanVoidInvoices(e.target.checked)}
                      className="rounded text-cyan-600 focus:ring-cyan-500"
                    />
                    <span>Can Void Official Invoices & Issue Cash Refunds</span>
                  </label>

                  <label className="flex items-center space-x-2 text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={canOverrideScreening}
                      onChange={e => setCanOverrideScreening(e.target.checked)}
                      className="rounded text-cyan-600 focus:ring-cyan-500"
                    />
                    <span>Can Override MRI / Contrast Patient Safety Risk Blocks</span>
                  </label>

                  <label className="flex items-center space-x-2 text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={canAccessPacs}
                      onChange={e => setCanAccessPacs(e.target.checked)}
                      className="rounded text-cyan-600 focus:ring-cyan-500"
                    />
                    <span>Can Access PACS DICOM Worklist / Nodes</span>
                  </label>

                  <label className="flex items-center space-x-2 text-slate-700 cursor-pointer">
                    <input
                      type="checkbox"
                      checked={canEditMasters}
                      onChange={e => setCanEditMasters(e.target.checked)}
                      className="rounded text-cyan-600 focus:ring-cyan-500"
                    />
                    <span>Can Edit Master Service Catalog & Price Schedules</span>
                  </label>
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button
                  type="button"
                  onClick={() => setUserModalOpen(false)}
                  className="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg font-semibold hover:bg-slate-200 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-cyan-600 text-white rounded-lg font-bold hover:bg-cyan-700 shadow-xs cursor-pointer"
                >
                  {editingUser ? 'Save User' : 'Create User'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* DICOM Node Modal */}
      {nodeModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="font-bold text-slate-900 text-base">
                {editingNode ? 'Edit DICOM Node' : 'Configure New DICOM Node'}
              </h3>
              <button onClick={() => setNodeModalOpen(false)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveNode} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Friendly Node Name *</label>
                <input
                  type="text"
                  required
                  value={nodeName}
                  onChange={e => setNodeName(e.target.value)}
                  placeholder="e.g. Somatom 128-Slice CT"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-semibold"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">AE Title *</label>
                  <input
                    type="text"
                    required
                    value={nodeAeTitle}
                    onChange={e => setNodeAeTitle(e.target.value.toUpperCase())}
                    placeholder="ADC_CT_01"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono uppercase"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Port *</label>
                  <input
                    type="number"
                    required
                    value={nodePort}
                    onChange={e => setNodePort(parseInt(e.target.value) || 104)}
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Host IP Address *</label>
                  <input
                    type="text"
                    required
                    value={nodeIp}
                    onChange={e => setNodeIp(e.target.value)}
                    placeholder="192.168.10.103"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Modality Code</label>
                  <input
                    type="text"
                    value={nodeModality}
                    onChange={e => setNodeModality(e.target.value.toUpperCase())}
                    placeholder="CT / MR / DX"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-bold uppercase"
                  />
                </div>
              </div>

              <div className="space-y-2 pt-2 border-t border-slate-100">
                <label className="flex items-center space-x-2 text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={nodeIsWorklist}
                    onChange={e => setNodeIsWorklist(e.target.checked)}
                    className="rounded text-cyan-600 focus:ring-cyan-500"
                  />
                  <span>Enable Modality Worklist (MWL SCP)</span>
                </label>
                <label className="flex items-center space-x-2 text-slate-700 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={nodeIsStorage}
                    onChange={e => setNodeIsStorage(e.target.checked)}
                    className="rounded text-cyan-600 focus:ring-cyan-500"
                  />
                  <span>Enable Image Storage & Query/Retrieve (C-STORE SCP)</span>
                </label>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button
                  type="button"
                  onClick={() => setNodeModalOpen(false)}
                  className="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg font-semibold hover:bg-slate-200 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-cyan-600 text-white rounded-lg font-bold hover:bg-cyan-700 shadow-xs cursor-pointer"
                >
                  Save DICOM Node
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
};
