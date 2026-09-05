import React, { useState } from 'react';
import {
  Activity,
  UserCheck,
  FileText,
  Clock,
  AlertOctagon,
  CheckCircle2,
  DollarSign,
  ArrowRight,
  TrendingUp,
  PlusCircle,
  ShieldAlert,
  Flame,
  Radio,
  Tv,
  Stethoscope,
  Receipt,
  Sliders,
  Sparkles,
  ChevronRight,
  Zap,
  Terminal,
  Layers,
  HeartPulse
} from 'lucide-react';
import { Appointment, ActiveTab, Modality, Invoice } from '../types';

interface DashboardViewProps {
  appointments: Appointment[];
  modalities: Modality[];
  invoices: Invoice[];
  setActiveTab: (tab: ActiveTab) => void;
  onSelectAppointment: (apt: Appointment) => void;
  onOpenBookingModal: () => void;
}

export const DashboardView: React.FC<DashboardViewProps> = ({
  appointments,
  modalities,
  invoices,
  setActiveTab,
  onSelectAppointment,
  onOpenBookingModal,
}) => {
  const [cockpitFilter, setCockpitFilter] = useState<'all' | 'clinical' | 'admin'>('all');

  const totalStudies = appointments.length;
  const inProgressStudies = appointments.filter(a => a.workflowState === 'in_progress').length;
  const waitingStudies = appointments.filter(a => ['booked', 'checked_in', 'preparing'].includes(a.workflowState)).length;
  const awaitingReading = appointments.filter(a => ['acquired', 'reading'].includes(a.workflowState)).length;
  const reportedStudies = appointments.filter(a => ['reported', 'delivered'].includes(a.workflowState)).length;
  const statCount = appointments.filter(a => a.priority === 'stat').length;
  const safetyPendingCount = appointments.filter(a => a.screeningRequired && !a.screeningCleared).length;
  const bookedPendingArrival = appointments.filter(a => a.workflowState === 'booked').length;
  const readyToScanCount = appointments.filter(a => a.workflowState === 'checked_in' || a.workflowState === 'preparing').length;

  const totalBilled = invoices.reduce((acc, curr) => acc + curr.total, 0);
  const totalPaid = invoices.reduce((acc, curr) => acc + curr.paidTotal, 0);
  const totalDue = invoices.reduce((acc, curr) => acc + curr.balanceDue, 0);

  const pipelineStages = [
    { label: 'Booked', count: appointments.filter(a => a.workflowState === 'booked').length, color: 'bg-slate-100 text-slate-700 border border-slate-200' },
    { label: 'Checked In', count: appointments.filter(a => a.workflowState === 'checked_in').length, color: 'bg-sky-50 text-sky-700 border border-sky-200' },
    { label: 'In Progress', count: inProgressStudies, color: 'bg-amber-50 text-amber-700 border border-amber-200' },
    { label: 'Awaiting Read', count: appointments.filter(a => a.workflowState === 'acquired').length, color: 'bg-purple-50 text-purple-700 border border-purple-200' },
    { label: 'Reading', count: appointments.filter(a => a.workflowState === 'reading').length, color: 'bg-indigo-50 text-indigo-700 border border-indigo-200' },
    { label: 'Reported', count: reportedStudies, color: 'bg-emerald-50 text-emerald-700 border border-emerald-200' },
  ];

  // Operations Console Modules
  const cockpitModules = [
    {
      id: 'intake',
      title: 'Book Diagnostic Study',
      category: 'clinical',
      description: 'Patient registration, procedure selection, priority assignment, and immediate appointment scheduling.',
      badgeText: 'New Patient',
      badgeColor: 'bg-cyan-50 text-cyan-700 border-cyan-200',
      icon: PlusCircle,
      iconBg: 'bg-cyan-50 text-cyan-600 border-cyan-200',
      actionLabel: 'Book Study',
      actionType: 'modal',
      action: onOpenBookingModal,
      metricValue: `${totalStudies} Scheduled`,
      metricSub: 'Active slots today',
      borderHover: 'hover:border-cyan-400 hover:shadow-cyan-500/10'
    },
    {
      id: 'reception',
      title: 'Patient Check-In & Tokens',
      category: 'admin',
      description: 'Confirm patient arrival, issue waiting tokens, verify study details, and initiate prep queue.',
      badgeText: bookedPendingArrival > 0 ? `${bookedPendingArrival} Arriving` : 'All Checked In',
      badgeColor: bookedPendingArrival > 0 ? 'bg-sky-50 text-sky-700 border-sky-200' : 'bg-slate-50 text-slate-600 border-slate-200',
      icon: UserCheck,
      iconBg: 'bg-sky-50 text-sky-600 border-sky-200',
      actionLabel: 'Open Check-In',
      actionType: 'tab',
      action: () => setActiveTab('checkin'),
      metricValue: `${appointments.filter(a => a.workflowState === 'checked_in').length} Waiting`,
      metricSub: 'Ready for scanning',
      borderHover: 'hover:border-sky-400 hover:shadow-sky-500/10'
    },
    {
      id: 'safety',
      title: 'Safety Screening Protocols',
      category: 'clinical',
      description: 'MRI implant checklists, renal function (eGFR) contrast review, and pregnancy verification.',
      badgeText: safetyPendingCount > 0 ? `${safetyPendingCount} Pending Review` : 'All Cleared',
      badgeColor: safetyPendingCount > 0 ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200',
      icon: ShieldAlert,
      iconBg: 'bg-amber-50 text-amber-600 border-amber-200',
      actionLabel: 'Screening Forms',
      actionType: 'tab',
      action: () => setActiveTab('checkin'),
      metricValue: `${appointments.filter(a => a.screeningCleared).length} Cleared`,
      metricSub: 'Pre-scan validated',
      borderHover: 'hover:border-amber-400 hover:shadow-amber-500/10'
    },
    {
      id: 'technologist',
      title: 'Technologist Worklist',
      category: 'clinical',
      description: 'Scan acquisition control, DICOM procedure execution, radiation dose logging, and contrast records.',
      badgeText: inProgressStudies > 0 ? `${inProgressStudies} In Progress` : 'Suites Ready',
      badgeColor: inProgressStudies > 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200',
      icon: Radio,
      iconBg: 'bg-emerald-50 text-emerald-600 border-emerald-200',
      actionLabel: 'Open Worklist',
      actionType: 'tab',
      action: () => setActiveTab('technologist'),
      metricValue: `${readyToScanCount} In Queue`,
      metricSub: `${modalities.length} active modalities`,
      borderHover: 'hover:border-emerald-400 hover:shadow-emerald-500/10'
    },
    {
      id: 'reporting',
      title: 'Radiologist Reporting',
      category: 'clinical',
      description: 'Structured diagnostic reading, report templates, critical findings dispatch, and verified PDF sign-off.',
      badgeText: awaitingReading > 0 ? `${awaitingReading} To Read` : 'Worklist Clear',
      badgeColor: awaitingReading > 0 ? 'bg-purple-50 text-purple-700 border-purple-200' : 'bg-slate-50 text-slate-600 border-slate-200',
      icon: Stethoscope,
      iconBg: 'bg-purple-50 text-purple-600 border-purple-200',
      actionLabel: 'Reading Desk',
      actionType: 'tab',
      action: () => setActiveTab('reporting'),
      metricValue: `${reportedStudies} Signed`,
      metricSub: 'Completed reports',
      borderHover: 'hover:border-purple-400 hover:shadow-purple-500/10'
    },
    {
      id: 'stat',
      title: 'STAT Emergency Worklist',
      category: 'clinical',
      description: 'Immediate trauma triaging, priority reading queues, and direct critical notifications to physicians.',
      badgeText: statCount > 0 ? `${statCount} STAT Active` : 'No STAT Pending',
      badgeColor: statCount > 0 ? 'bg-rose-50 text-rose-700 border-rose-300 font-bold' : 'bg-slate-50 text-slate-600 border-slate-200',
      icon: Flame,
      iconBg: 'bg-rose-50 text-rose-600 border-rose-200',
      actionLabel: 'STAT Queue',
      actionType: 'tab',
      action: () => setActiveTab('reporting'),
      metricValue: `${statCount} Urgent`,
      metricSub: 'Priority attention',
      borderHover: 'hover:border-rose-400 hover:shadow-rose-500/10'
    },
    {
      id: 'billing',
      title: 'Billing & Cash Counter',
      category: 'admin',
      description: 'Patient invoices, multi-tender payments (Cash/POS), discount approvals, and printed receipts.',
      badgeText: totalDue > 0 ? `Rs. ${totalDue.toLocaleString()} Due` : 'Settled',
      badgeColor: totalDue > 0 ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-50 text-slate-600 border-slate-200',
      icon: Receipt,
      iconBg: 'bg-teal-50 text-teal-600 border-teal-200',
      actionLabel: 'Open Billing',
      actionType: 'tab',
      action: () => setActiveTab('billing'),
      metricValue: `Rs. ${totalPaid.toLocaleString()}`,
      metricSub: `Collected of Rs. ${totalBilled.toLocaleString()}`,
      borderHover: 'hover:border-teal-400 hover:shadow-teal-500/10'
    },
    {
      id: 'queue',
      title: 'Waiting Area TV Display',
      category: 'admin',
      description: 'Overhead queue board for waiting lounge with real-time token tracking and audio chime alerts.',
      badgeText: `${waitingStudies} In Waiting`,
      badgeColor: 'bg-indigo-50 text-indigo-700 border-indigo-200',
      icon: Tv,
      iconBg: 'bg-indigo-50 text-indigo-600 border-indigo-200',
      actionLabel: 'Open Display',
      actionType: 'tab',
      action: () => setActiveTab('queue'),
      metricValue: `${appointments.filter(a => a.workflowState === 'in_progress').length} In Exam Rooms`,
      metricSub: 'Current active scans',
      borderHover: 'hover:border-indigo-400 hover:shadow-indigo-500/10'
    },
    {
      id: 'masters',
      title: 'Procedure & Tariff Master',
      category: 'admin',
      description: 'Manage clinical procedures, fee schedules, modality suites, and report templates.',
      badgeText: `${modalities.length} Modalities`,
      badgeColor: 'bg-slate-100 text-slate-700 border-slate-200',
      icon: Sliders,
      iconBg: 'bg-slate-100 text-slate-700 border-slate-200',
      actionLabel: 'Manage Catalog',
      actionType: 'tab',
      action: () => setActiveTab('masters'),
      metricValue: '5 Suites Configured',
      metricSub: 'Templates & questionnaires',
      borderHover: 'hover:border-slate-400 hover:shadow-slate-500/10'
    },
  ];

  const filteredModules = cockpitModules.filter(m => {
    if (cockpitFilter === 'all') return true;
    return m.category === cockpitFilter;
  });

  return (
    <div className="space-y-6">
      {/* OPERATIONS CONSOLE */}
      <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-sm space-y-5">
        {/* Console Header */}
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-4 border-b border-slate-200">
          <div>
            <div className="flex items-center space-x-2.5">
              <div className="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-700 border border-cyan-200 flex items-center justify-center shadow-xs">
                <Terminal className="w-5 h-5" />
              </div>
              <div>
                <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                  Operations Console
                </h1>
                <p className="text-xs text-slate-500 mt-0.5">
                  Direct access to radiology workflow desks, patient intake, diagnostic reporting, and clinic services.
                </p>
              </div>
            </div>
          </div>

          {/* Quick Filters */}
          <div className="flex flex-wrap items-center gap-2">
            <div className="flex bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-semibold">
              <button
                onClick={() => setCockpitFilter('all')}
                className={`px-3 py-1 rounded-lg transition-colors cursor-pointer ${
                  cockpitFilter === 'all' ? 'bg-white text-slate-900 shadow-xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                All Desks
              </button>
              <button
                onClick={() => setCockpitFilter('clinical')}
                className={`px-3 py-1 rounded-lg transition-colors cursor-pointer ${
                  cockpitFilter === 'clinical' ? 'bg-white text-cyan-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Clinical
              </button>
              <button
                onClick={() => setCockpitFilter('admin')}
                className={`px-3 py-1 rounded-lg transition-colors cursor-pointer ${
                  cockpitFilter === 'admin' ? 'bg-white text-indigo-700 shadow-xs' : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                Operations
              </button>
            </div>
          </div>
        </div>

        {/* Console Grid */}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          {filteredModules.map((module) => {
            const Icon = module.icon;
            return (
              <div
                key={module.id}
                onClick={module.action}
                className={`group bg-white p-4.5 rounded-2xl border border-slate-200 shadow-xs hover:shadow-md transition-all duration-150 cursor-pointer flex flex-col justify-between space-y-3 ${module.borderHover}`}
              >
                {/* Tile Header */}
                <div className="flex items-start justify-between">
                  <div className="flex items-center space-x-2.5">
                    <div className={`w-10 h-10 rounded-xl flex items-center justify-center border shadow-xs ${module.iconBg}`}>
                      <Icon className="w-5 h-5" />
                    </div>
                    <div>
                      <h3 className="font-bold text-slate-900 text-sm tracking-tight group-hover:text-cyan-700 transition-colors">
                        {module.title}
                      </h3>
                      <span className="text-[11px] text-slate-500 font-medium">{module.metricSub}</span>
                    </div>
                  </div>

                  <span className={`text-[11px] font-bold px-2 py-1 rounded-md border shrink-0 whitespace-nowrap ${module.badgeColor}`}>
                    {module.badgeText}
                  </span>
                </div>

                {/* Tile Body: Description */}
                <p className="text-xs text-slate-500 line-clamp-2 leading-relaxed">
                  {module.description}
                </p>

                {/* Tile Footer */}
                <div className="pt-2.5 border-t border-slate-100 flex items-center justify-between">
                  <div>
                    <div className="font-mono text-xs font-bold text-slate-900">{module.metricValue}</div>
                  </div>

                  <button
                    type="button"
                    className="flex items-center space-x-1 px-3 py-1.5 rounded-lg bg-slate-50 group-hover:bg-cyan-600 text-slate-700 group-hover:text-white font-semibold text-xs border border-slate-200 group-hover:border-cyan-600 transition-all shadow-2xs"
                  >
                    <span>{module.actionLabel}</span>
                    <ChevronRight className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>
            );
          })}
        </div>
      </div>

      {/* KPI Stats Overview Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        {/* Total Today */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Studies Today</span>
            <div className="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-600 border border-cyan-200 flex items-center justify-center">
              <Activity className="w-4.5 h-4.5" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-3xl font-black text-slate-900">{totalStudies}</span>
            <span className="text-xs text-cyan-700 font-bold">{waitingStudies} in queue</span>
          </div>
          <div className="mt-2 flex items-center text-[11px] text-slate-500">
            <span className="text-emerald-600 font-bold flex items-center mr-1">
              <TrendingUp className="w-3 h-3 mr-0.5" /> 100%
            </span>
            clinic uptime on 5 modalities
          </div>
        </div>

        {/* Radiation & Tech Status */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Active Scanning</span>
            <div className="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center">
              <Clock className="w-4.5 h-4.5" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-3xl font-black text-amber-600">{inProgressStudies}</span>
            <span className="text-xs text-slate-500">{appointments.filter(a => a.workflowState === 'preparing').length} in prep</span>
          </div>
          <div className="mt-2 flex items-center text-[11px] text-slate-500">
            <span>Safety screening validated before scanning</span>
          </div>
        </div>

        {/* Reports Pending Read */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Awaiting Read</span>
            <div className="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 border border-purple-200 flex items-center justify-center">
              <FileText className="w-4.5 h-4.5" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-3xl font-black text-purple-700">{awaitingReading}</span>
            <span className="text-xs text-emerald-700 font-bold">{reportedStudies} finalized</span>
          </div>
          <div className="mt-2 flex items-center text-[11px] text-slate-500">
            {statCount > 0 ? (
              <span className="text-rose-600 font-bold flex items-center">
                <Flame className="w-3 h-3 mr-0.5" /> {statCount} STAT urgent exams
              </span>
            ) : (
              <span>Turnaround time under 45 mins</span>
            )}
          </div>
        </div>

        {/* Daily Revenue */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs font-semibold uppercase tracking-wider text-slate-500">Billing Collected</span>
            <div className="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 border border-emerald-200 flex items-center justify-center">
              <DollarSign className="w-4.5 h-4.5" />
            </div>
          </div>
          <div className="mt-3 flex items-baseline justify-between">
            <span className="text-2xl font-black text-emerald-600">Rs. {totalPaid.toLocaleString()}</span>
            <span className="text-xs text-slate-500 font-mono">/ {totalBilled.toLocaleString()}</span>
          </div>
          <div className="mt-2 flex items-center text-[11px] text-slate-500">
            <span>Outstanding balance: <strong className="text-slate-800">Rs. {totalDue.toLocaleString()}</strong></span>
          </div>
        </div>
      </div>

      {/* Pipeline Visual Funnel */}
      <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div className="flex items-center justify-between mb-3">
          <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Clinical Study Lifecycle Workflow</h2>
          <span className="text-xs text-slate-500">Automatic transition guards & audit logs</span>
        </div>
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2">
          {pipelineStages.map((stage, idx) => (
            <div key={idx} className={`p-3 rounded-xl flex flex-col items-center justify-center text-center ${stage.color}`}>
              <span className="text-xl font-extrabold">{stage.count}</span>
              <span className="text-[11px] font-semibold tracking-wide uppercase mt-1">{stage.label}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Modality Status & Attention Queue */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Modalities Distribution */}
        <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
          <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider mb-4">Modalities & Room Status</h2>
          <div className="space-y-3">
            {modalities.map((m) => {
              const count = appointments.filter(a => a.modalityId === m.id).length;
              return (
                <div key={m.id} className="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200">
                  <div className="flex items-center space-x-3">
                    <span
                      className="w-8 h-8 rounded-lg flex items-center justify-center font-mono font-bold text-xs text-white shadow-xs"
                      style={{ backgroundColor: m.color }}
                    >
                      {m.code}
                    </span>
                    <div>
                      <div className="text-xs font-semibold text-slate-800">{m.name}</div>
                      <div className="text-[11px] text-slate-500">Slot buffer: {m.bufferMinutes} mins</div>
                    </div>
                  </div>
                  <div className="text-right">
                    <span className="text-sm font-bold text-slate-900">{count}</span>
                    <span className="text-[11px] text-slate-500 block">studies</span>
                  </div>
                </div>
              );
            })}
          </div>
        </div>

        {/* Live Studies Queue (Fast Action) */}
        <div className="lg:col-span-2 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm flex flex-col">
          <div className="flex items-center justify-between mb-4">
            <div className="flex items-center space-x-2">
              <h2 className="text-sm font-bold text-slate-900 uppercase tracking-wider">Active Clinical Queue</h2>
              <span className="text-xs px-2.5 py-1 rounded-md bg-cyan-50 text-cyan-700 font-mono border border-cyan-200 font-semibold whitespace-nowrap">Today</span>
            </div>
            <button
              onClick={() => setActiveTab('technologist')}
              className="text-xs text-cyan-700 hover:text-cyan-800 font-semibold flex items-center gap-1 cursor-pointer"
            >
              View Full Worklist <ArrowRight className="w-3.5 h-3.5" />
            </button>
          </div>

          <div className="w-full overflow-x-hidden flex-1">
            <table className="w-full text-left text-xs table-auto">
              <thead className="bg-slate-100/90 text-slate-600 uppercase font-bold text-[11px] tracking-wider border-b border-slate-200">
                <tr>
                  <th className="py-2.5 px-3 whitespace-nowrap">Token / Priority</th>
                  <th className="py-2.5 px-3">Patient & MRN</th>
                  <th className="py-2.5 px-3">Study / Modality</th>
                  <th className="py-2.5 px-3 whitespace-nowrap">Status</th>
                  <th className="py-2.5 px-3 text-right whitespace-nowrap">Action</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {appointments.slice(0, 5).map((apt) => (
                  <tr key={apt.id} className="hover:bg-slate-50 transition-colors">
                    <td className="py-2.5 px-3 whitespace-nowrap">
                      <div className="flex items-center space-x-1.5">
                        <span className="font-mono font-bold text-slate-800 px-2 py-0.5 rounded bg-slate-100 border border-slate-300 text-xs">
                          {apt.tokenNumber}
                        </span>
                        {apt.priority === 'stat' && (
                          <span className="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 font-extrabold text-[10px] border border-rose-300 animate-pulse whitespace-nowrap">
                            STAT
                          </span>
                        )}
                        {apt.priority === 'urgent' && (
                          <span className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-bold text-[10px] border border-amber-200 whitespace-nowrap">
                            URGENT
                          </span>
                        )}
                      </div>
                    </td>
                    <td className="py-2.5 px-3">
                      <div className="font-semibold text-slate-900 leading-snug truncate max-w-[170px]">{apt.patient.name}</div>
                      <div className="text-[10px] text-slate-500 font-mono whitespace-nowrap">{apt.patient.mrn} • {apt.patient.age}y {apt.patient.gender[0].toUpperCase()}</div>
                    </td>
                    <td className="py-2.5 px-3">
                      <div className="font-medium text-slate-800 leading-snug truncate max-w-[180px]">{apt.service.name}</div>
                      <div className="text-[10px] text-slate-500">{apt.roomNumber}</div>
                    </td>
                    <td className="py-2.5 px-3 whitespace-nowrap">
                      <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold whitespace-nowrap ${
                        apt.workflowState === 'in_progress' ? 'bg-amber-50 text-amber-800 border border-amber-300' :
                        apt.workflowState === 'acquired' ? 'bg-purple-50 text-purple-700 border border-purple-200' :
                        apt.workflowState === 'reading' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' :
                        apt.workflowState === 'reported' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                        apt.workflowState === 'checked_in' ? 'bg-sky-50 text-sky-700 border border-sky-200' :
                        'bg-slate-100 text-slate-600 border border-slate-200'
                      }`}>
                        {apt.workflowState.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="py-2.5 px-3 text-right whitespace-nowrap">
                      <button
                        onClick={() => {
                          if (['acquired', 'reading', 'reported'].includes(apt.workflowState)) {
                            setActiveTab('reporting');
                          } else {
                            setActiveTab('technologist');
                          }
                          onSelectAppointment(apt);
                        }}
                        className="px-2 py-1 rounded bg-slate-100 hover:bg-cyan-600 hover:text-white text-slate-700 font-semibold text-[11px] border border-slate-300 transition-all cursor-pointer shadow-2xs whitespace-nowrap"
                      >
                        Manage
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

