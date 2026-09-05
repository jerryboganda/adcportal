import React, { useState, useMemo } from 'react';
import {
  Search,
  UserCheck,
  UserX,
  PlusCircle,
  Printer,
  FileText,
  Clock,
  CheckCircle2,
  Calendar,
  AlertCircle,
  ShieldAlert,
  CreditCard,
  Receipt,
  Eye,
  Edit3,
  X,
  Flame,
  AlertTriangle,
  QrCode,
  Tag,
  Share2,
  SlidersHorizontal,
  ChevronRight,
  Phone,
  User,
  HeartPulse,
  Radio,
  FileCheck,
  Check,
  DollarSign
} from 'lucide-react';
import { Appointment, Invoice, ActiveTab, Priority, Patient, WorkflowState } from '../types';
import { WorklistFilterToolbar } from './WorklistFilterToolbar';
import { SortableColumnHeader } from './SortableColumnHeader';
import {
  AdvancedFilterState,
  defaultAdvancedFilters,
  SortField,
  matchesAdvancedFilters,
  compareAppointmentsMultiSort,
} from '../utils/tableUtils';

interface CheckinBoardViewProps {
  appointments: Appointment[];
  invoices: Invoice[];
  onCheckIn: (aptId: string) => void;
  onMarkNoShow: (aptId: string) => void;
  onCancelStudy: (aptId: string, reason: string) => void;
  onOpenBookingModal: () => void;
  onOpenScreeningModal: (apt: Appointment) => void;
  onRecordPayment: (
    invoiceId: string,
    amount: number,
    method: 'cash' | 'card' | 'bank' | 'mobile' | 'insurance',
    reference: string
  ) => void;
  onUpdateAppointment: (aptId: string, updates: Partial<Appointment>) => void;
  setActiveTab: (tab: ActiveTab) => void;
}

export const CheckinBoardView: React.FC<CheckinBoardViewProps> = ({
  appointments,
  invoices,
  onCheckIn,
  onMarkNoShow,
  onCancelStudy,
  onOpenBookingModal,
  onOpenScreeningModal,
  onRecordPayment,
  onUpdateAppointment,
  setActiveTab,
}) => {
  // Filters & Sorting State
  const [filters, setFilters] = useState<AdvancedFilterState>({
    ...defaultAdvancedFilters,
    dateRangeMode: 'today',
  });
  const [sortFields, setSortFields] = useState<SortField[]>([
    { column: 'priority', direction: 'asc' },
    { column: 'time', direction: 'asc' },
  ]);

  // Modals & Drawers state
  const [tokenSlipApt, setTokenSlipApt] = useState<Appointment | null>(null);
  const [wristbandApt, setWristbandApt] = useState<Appointment | null>(null);
  const [patientDetailApt, setPatientDetailApt] = useState<Appointment | null>(null);
  const [paymentModalApt, setPaymentModalApt] = useState<Appointment | null>(null);
  const [editModalApt, setEditModalApt] = useState<Appointment | null>(null);
  const [cancelModalApt, setCancelModalApt] = useState<Appointment | null>(null);
  const [cancelReason, setCancelReason] = useState('');
  const [showManifestModal, setShowManifestModal] = useState(false);

  // Quick Payment form state
  const [payAmount, setPayAmount] = useState<number>(0);
  const [payMethod, setPayMethod] = useState<'cash' | 'card' | 'bank' | 'mobile' | 'insurance'>('cash');
  const [payRef, setPayRef] = useState('');

  // Edit / Reassignment form state
  const [editPriority, setEditPriority] = useState<Priority>('routine');
  const [editRoom, setEditRoom] = useState('');
  const [editTime, setEditTime] = useState('');
  const [editNotes, setEditNotes] = useState('');

  // Helper to find invoice for appointment
  const getInvoiceForApt = (apt: Appointment): Invoice | undefined => {
    return invoices.find(inv => inv.appointmentId === apt.id || inv.appointmentToken === apt.tokenNumber);
  };

  // Filtered and Multi-Sorted appointments list
  const filteredAppointments = useMemo(() => {
    const list = appointments.filter((apt) => matchesAdvancedFilters(apt, filters, getInvoiceForApt));
    return list.sort((a, b) => compareAppointmentsMultiSort(a, b, sortFields, getInvoiceForApt));
  }, [appointments, filters, sortFields, invoices]);

  // Modality list for filter toolbar
  const uniqueModalities = useMemo(() => {
    const map = new Map<string, { id: number; code: string; name: string; color: string }>();
    appointments.forEach((a) => {
      if (a.modality && !map.has(a.modality.code)) {
        map.set(a.modality.code, {
          id: a.modality.id,
          code: a.modality.code,
          name: a.modality.name,
          color: a.modality.color,
        });
      }
    });
    return Array.from(map.values());
  }, [appointments]);

  // Available status counts for toolbar
  const availableStatuses = useMemo(() => {
    const statusMap: Record<WorkflowState, { label: string; count: number }> = {
      booked: { label: 'Booked', count: 0 },
      checked_in: { label: 'Checked In', count: 0 },
      preparing: { label: 'Preparing', count: 0 },
      in_progress: { label: 'Scanning', count: 0 },
      acquired: { label: 'Acquired', count: 0 },
      reading: { label: 'Reading', count: 0 },
      reported: { label: 'Reported', count: 0 },
      delivered: { label: 'Delivered', count: 0 },
      no_show: { label: 'No Show', count: 0 },
      cancelled: { label: 'Cancelled', count: 0 },
    };

    appointments.forEach((a) => {
      if (statusMap[a.workflowState]) {
        statusMap[a.workflowState].count += 1;
      }
    });

    return (Object.keys(statusMap) as WorkflowState[]).map((st) => ({
      value: st,
      label: statusMap[st].label,
      count: statusMap[st].count,
    }));
  }, [appointments]);

  // KPI counters
  const totalScheduleCount = appointments.length;
  const bookedCount = appointments.filter(a => a.workflowState === 'booked').length;
  const checkedInCount = appointments.filter(a => a.workflowState === 'checked_in').length;
  const inProgressCount = appointments.filter(a => ['preparing', 'in_progress', 'acquired', 'reading'].includes(a.workflowState)).length;
  const statUrgentCount = appointments.filter(a => a.priority === 'stat' || a.priority === 'urgent').length;
  
  // Outstanding Due sum for filtered appointments
  const totalDueForFiltered = filteredAppointments.reduce((sum, apt) => {
    const inv = getInvoiceForApt(apt);
    return sum + (inv ? inv.balanceDue : apt.service.price);
  }, 0);

  // Handle opening quick payment
  const openPaymentCollector = (apt: Appointment) => {
    const inv = getInvoiceForApt(apt);
    const balance = inv ? inv.balanceDue : apt.service.price;
    setPayAmount(balance);
    setPayMethod('cash');
    setPayRef(`RCP-${Date.now().toString().slice(-4)}`);
    setPaymentModalApt(apt);
  };

  // Submit payment
  const handleSavePayment = (e: React.FormEvent) => {
    e.preventDefault();
    if (!paymentModalApt) return;
    const inv = getInvoiceForApt(paymentModalApt);
    if (inv) {
      onRecordPayment(inv.id, payAmount, payMethod, payRef || `REC-${Date.now().toString().slice(-4)}`);
    }
    setPaymentModalApt(null);
  };

  // Open Edit / Reassign modal
  const openEditModal = (apt: Appointment) => {
    setEditPriority(apt.priority);
    setEditRoom(apt.roomNumber);
    setEditTime(apt.time);
    setEditNotes(apt.notes || '');
    setEditModalApt(apt);
  };

  // Save Edit / Reassignment
  const handleSaveEdit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!editModalApt) return;
    onUpdateAppointment(editModalApt.id, {
      priority: editPriority,
      roomNumber: editRoom,
      time: editTime,
      notes: editNotes,
    });
    setEditModalApt(null);
  };

  // Confirm cancel
  const handleConfirmCancel = () => {
    if (!cancelModalApt) return;
    onCancelStudy(cancelModalApt.id, cancelReason || 'Cancelled at patient/attendant request');
    setCancelModalApt(null);
    setCancelReason('');
  };

  return (
    <div className="space-y-6">
      {/* Reception Desk Header & Action Bar */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
          <div className="flex items-center space-x-2.5">
            <div className="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-700 border border-cyan-200 flex items-center justify-center font-bold">
              <UserCheck className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <h1 className="text-xl font-bold text-slate-900 tracking-tight">
                  Reception & Patient Check-In Desk
                </h1>
                <span className="text-xs px-2.5 py-1 rounded-md bg-emerald-50 text-emerald-700 border border-emerald-200 font-semibold whitespace-nowrap inline-flex items-center gap-1.5">
                  <span className="w-1.5 h-1.5 rounded-sm bg-emerald-500 animate-pulse"></span> Front Desk Active
                </span>
              </div>
              <p className="text-slate-500 text-xs mt-0.5">
                Arrival verification, queue token issuance, safety contraindication clearance, billing settlement, and study management.
              </p>
            </div>
          </div>
        </div>

        {/* Action Controls */}
        <div className="flex flex-wrap items-center gap-2">
          <button
            onClick={() => setShowManifestModal(true)}
            className="flex items-center space-x-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 transition-all cursor-pointer shadow-2xs"
            title="View & Print Daily Front Desk Registration Manifest"
          >
            <Printer className="w-3.5 h-3.5 text-slate-600" />
            <span>Daily Run-Sheet</span>
          </button>

          <button
            onClick={onOpenBookingModal}
            className="flex items-center justify-center space-x-2 px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold text-xs shadow-md shadow-cyan-600/20 transition-all cursor-pointer"
          >
            <PlusCircle className="w-4 h-4" />
            <span>Walk-In / New Booking</span>
          </button>
        </div>
      </div>

      {/* KPI Counters Bar */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div 
          onClick={() => setFilters(prev => ({ ...prev, statuses: [], priorities: [], billingStatus: 'all' }))}
          className={`bg-white p-3.5 rounded-xl border transition-all cursor-pointer shadow-2xs ${
            filters.statuses.length === 0 && filters.priorities.length === 0 && filters.billingStatus === 'all'
              ? 'border-cyan-500 ring-2 ring-cyan-500/20 bg-cyan-50/20'
              : 'border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className="text-slate-500 text-[11px] font-medium block">Total Scheduled</span>
          <div className="flex items-baseline justify-between mt-1">
            <span className="text-xl font-bold text-slate-900">{totalScheduleCount}</span>
            <span className="text-[10px] text-slate-400 font-medium">Slots</span>
          </div>
        </div>

        <div 
          onClick={() => setFilters(prev => ({ ...prev, statuses: ['booked'], priorities: [], billingStatus: 'all' }))}
          className={`bg-white p-3.5 rounded-xl border transition-all cursor-pointer shadow-2xs ${
            filters.statuses.length === 1 && filters.statuses.includes('booked')
              ? 'border-amber-500 ring-2 ring-amber-500/20 bg-amber-50/20'
              : 'border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className="text-slate-500 text-[11px] font-medium block">Awaiting Check-In</span>
          <div className="flex items-baseline justify-between mt-1">
            <span className="text-xl font-bold text-amber-600">{bookedCount}</span>
            <span className="text-[10px] text-amber-700/80 font-medium">Pending</span>
          </div>
        </div>

        <div 
          onClick={() => setFilters(prev => ({ ...prev, statuses: ['checked_in'], priorities: [], billingStatus: 'all' }))}
          className={`bg-white p-3.5 rounded-xl border transition-all cursor-pointer shadow-2xs ${
            filters.statuses.length === 1 && filters.statuses.includes('checked_in')
              ? 'border-sky-500 ring-2 ring-sky-500/20 bg-sky-50/20'
              : 'border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className="text-slate-500 text-[11px] font-medium block">Waiting Lounge</span>
          <div className="flex items-baseline justify-between mt-1">
            <span className="text-xl font-bold text-sky-600">{checkedInCount}</span>
            <span className="text-[10px] text-sky-700/80 font-medium">In Queue</span>
          </div>
        </div>

        <div 
          onClick={() => setFilters(prev => ({ ...prev, statuses: ['preparing', 'in_progress', 'acquired', 'reading'], priorities: [], billingStatus: 'all' }))}
          className={`bg-white p-3.5 rounded-xl border transition-all cursor-pointer shadow-2xs ${
            filters.statuses.includes('in_progress') || filters.statuses.includes('preparing')
              ? 'border-indigo-500 ring-2 ring-indigo-500/20 bg-indigo-50/20'
              : 'border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className="text-slate-500 text-[11px] font-medium block">Inside Scan Suite</span>
          <div className="flex items-baseline justify-between mt-1">
            <span className="text-xl font-bold text-indigo-600">{inProgressCount}</span>
            <span className="text-[10px] text-indigo-700/80 font-medium">Examining</span>
          </div>
        </div>

        <div 
          onClick={() => setFilters(prev => ({ ...prev, billingStatus: prev.billingStatus === 'unpaid' ? 'all' : 'unpaid' }))}
          className={`bg-white p-3.5 rounded-xl border transition-all cursor-pointer shadow-2xs ${
            filters.billingStatus === 'unpaid'
              ? 'border-teal-500 ring-2 ring-teal-500/20 bg-teal-50/20'
              : 'border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className="text-slate-500 text-[11px] font-medium block">Outstanding Balance</span>
          <div className="flex items-baseline justify-between mt-1">
            <span className="text-base font-bold text-teal-700">Rs. {totalDueForFiltered.toLocaleString()}</span>
            <span className="text-[10px] text-teal-600 font-medium">Due</span>
          </div>
        </div>

        <div 
          onClick={() => setFilters(prev => ({ ...prev, priorities: prev.priorities.includes('stat') ? [] : ['stat', 'urgent'] }))}
          className={`bg-white p-3.5 rounded-xl border transition-all cursor-pointer shadow-2xs ${
            filters.priorities.includes('stat')
              ? 'border-rose-500 ring-2 ring-rose-500/20 bg-rose-50/20'
              : 'border-slate-200 hover:border-slate-300'
          }`}
        >
          <span className="text-slate-500 text-[11px] font-medium block">STAT & Emergency</span>
          <div className="flex items-baseline justify-between mt-1">
            <span className={`text-xl font-bold ${statUrgentCount > 0 ? 'text-rose-600' : 'text-slate-700'}`}>
              {statUrgentCount}
            </span>
            {statUrgentCount > 0 ? (
              <span className="text-[10px] text-rose-600 font-bold px-1.5 py-0.2 rounded bg-rose-50 border border-rose-200">
                ACTIVE
              </span>
            ) : (
              <span className="text-[10px] text-slate-400">Routine</span>
            )}
          </div>
        </div>
      </div>

      {/* Advanced Filter, Date & Search Toolbar with Multi-Sort Badges */}
      <WorklistFilterToolbar
        filters={filters}
        onFilterChange={setFilters}
        sortFields={sortFields}
        onSortChange={setSortFields}
        modalities={uniqueModalities as any}
        availableStatuses={availableStatuses}
        totalCount={appointments.length}
        filteredCount={filteredAppointments.length}
        showBillingFilter={true}
        showScreeningFilter={true}
        variantTitle="Reception Check-In"
      />

      {/* Main Reception Appointments Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="w-full overflow-x-hidden">
          <table className="w-full text-left text-xs table-auto">
            <thead className="bg-slate-100/90 text-slate-600 uppercase font-bold text-[11px] tracking-wider border-b border-slate-200">
              <tr>
                <SortableColumnHeader
                  column="token"
                  label="Token & Priority"
                  sortFields={sortFields}
                  onSortChange={setSortFields}
                  className="whitespace-nowrap"
                  tooltip="Sort by Token # or hold Shift to multi-sort"
                />
                <SortableColumnHeader
                  column="patientName"
                  label="Patient & MRN"
                  sortFields={sortFields}
                  onSortChange={setSortFields}
                  tooltip="Sort by Patient Name / MRN"
                />
                <SortableColumnHeader
                  column="service"
                  label="Diagnostic Procedure"
                  sortFields={sortFields}
                  onSortChange={setSortFields}
                  tooltip="Sort by Diagnostic Examination"
                />
                <SortableColumnHeader
                  column="time"
                  label="Slot / Arrival"
                  sortFields={sortFields}
                  onSortChange={setSortFields}
                  className="whitespace-nowrap"
                  tooltip="Sort by Date & Scheduled Time"
                />
                <SortableColumnHeader
                  column="safety"
                  label="Safety"
                  sortFields={sortFields}
                  onSortChange={setSortFields}
                  className="whitespace-nowrap"
                  tooltip="Sort by Safety Clearance"
                />
                <SortableColumnHeader
                  column="billing"
                  label="Billing"
                  sortFields={sortFields}
                  onSortChange={setSortFields}
                  className="whitespace-nowrap"
                  tooltip="Sort by Balance Due"
                />
                <SortableColumnHeader
                  column="workflowState"
                  label="Workflow"
                  sortFields={sortFields}
                  onSortChange={setSortFields}
                  className="whitespace-nowrap"
                  tooltip="Sort by Workflow Status"
                />
                <th className="py-2.5 px-3 text-right whitespace-nowrap">Desk Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 text-slate-700">
              {filteredAppointments.length === 0 ? (
                <tr>
                  <td colSpan={8} className="py-10 text-center text-slate-400 space-y-2">
                    <AlertCircle className="w-7 h-7 mx-auto text-slate-300" />
                    <p className="text-xs font-medium text-slate-600">No patient appointments match the selected filters.</p>
                    <button
                      onClick={() => {
                        setFilters({
                          ...defaultAdvancedFilters,
                          dateRangeMode: 'all',
                        });
                        setSortFields([]);
                      }}
                      className="text-xs text-cyan-600 hover:underline font-semibold cursor-pointer"
                    >
                      Clear All Filters
                    </button>
                  </td>
                </tr>
              ) : (
                filteredAppointments.map((apt) => {
                  const isBooked = apt.workflowState === 'booked';
                  const isCheckedIn = apt.workflowState === 'checked_in';
                  const inv = getInvoiceForApt(apt);
                  const isPaid = inv ? inv.balanceDue === 0 : false;
                  const balanceDue = inv ? inv.balanceDue : apt.service.price;

                  return (
                    <tr key={apt.id} className="hover:bg-slate-50/80 transition-colors">
                      {/* Token & Priority */}
                      <td className="py-2.5 px-3 whitespace-nowrap">
                        <div className="flex items-center space-x-1.5">
                          <span className="font-mono font-bold text-xs px-2 py-0.5 rounded bg-slate-100 text-cyan-900 border border-slate-300 shadow-2xs">
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

                      {/* Patient Info & MRN */}
                      <td className="py-2.5 px-3">
                        <div className="flex items-center space-x-1.5">
                          <button
                            onClick={() => setPatientDetailApt(apt)}
                            className="font-bold text-slate-900 hover:text-cyan-700 text-left transition-colors cursor-pointer truncate max-w-[170px]"
                            title={apt.patient.name}
                          >
                            {apt.patient.name}
                          </button>
                        </div>
                        <div className="text-[10px] text-slate-500 font-mono mt-0.5 whitespace-nowrap">
                          <span className="font-semibold text-slate-700">{apt.patient.mrn}</span> • {apt.patient.age}y {apt.patient.gender[0].toUpperCase()}
                        </div>
                        {apt.referrer ? (
                          <div className="text-[10px] text-slate-400 mt-0.5 truncate max-w-[170px]" title={`${apt.referrer.name} (${apt.referrer.specialty})`}>
                            Ref: {apt.referrer.name}
                          </div>
                        ) : (
                          <div className="text-[10px] text-slate-400 mt-0.5">Self Walk-In</div>
                        )}
                      </td>

                      {/* Procedure & Room */}
                      <td className="py-2.5 px-3">
                        <div className="font-semibold text-slate-900 leading-snug truncate max-w-[180px]" title={apt.service.name}>
                          {apt.service.name}
                        </div>
                        <div className="flex items-center space-x-1.5 mt-0.5 text-[10px]">
                          <span
                            className="px-1 py-0.2 rounded font-bold text-white font-mono text-[9px] shadow-2xs"
                            style={{ backgroundColor: apt.modality.color }}
                          >
                            {apt.modality.code}
                          </span>
                          <span className="text-slate-500 font-medium truncate max-w-[100px]">{apt.roomNumber}</span>
                          <span className="text-slate-400 font-mono whitespace-nowrap">Rs. {apt.service.price.toLocaleString()}</span>
                        </div>
                      </td>

                      {/* Scheduled Time & Arrival Telemetry */}
                      <td className="py-2.5 px-3 whitespace-nowrap">
                        <div className="flex items-center space-x-1 font-mono text-slate-900 font-semibold text-xs">
                          <Clock className="w-3 h-3 text-slate-400 shrink-0" />
                          <span>{apt.time}</span>
                        </div>
                        {apt.checkedInAt ? (
                          <div className="text-[10px] text-sky-700 font-semibold mt-0.5 flex items-center gap-1">
                            <UserCheck className="w-3 h-3 text-sky-600 shrink-0" />
                            <span>Arr: {apt.checkedInAt}</span>
                          </div>
                        ) : (
                          <div className="text-[10px] text-amber-700 font-medium mt-0.5">Awaiting</div>
                        )}
                      </td>

                      {/* Safety Clearance Status */}
                      <td className="py-2.5 px-3 whitespace-nowrap">
                        {apt.screeningRequired ? (
                          apt.screeningCleared ? (
                            <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                              <CheckCircle2 className="w-3 h-3 text-emerald-600 shrink-0" /> Cleared
                            </span>
                          ) : (
                            <button
                              onClick={() => onOpenScreeningModal(apt)}
                              className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-300 transition-colors cursor-pointer shadow-2xs"
                              title="Click to perform patient screening"
                            >
                              <ShieldAlert className="w-3 h-3 text-amber-600 shrink-0" /> Pre-Check
                            </button>
                          )
                        ) : (
                          <span className="text-[10px] text-slate-400 font-medium">Standard</span>
                        )}
                      </td>

                      {/* Billing & Settlement */}
                      <td className="py-2.5 px-3 whitespace-nowrap">
                        {isPaid ? (
                          <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <Check className="w-3 h-3 text-emerald-600 shrink-0" /> Paid
                          </span>
                        ) : (
                          <div className="flex items-center space-x-1">
                            <span className="px-1.5 py-0.5 rounded text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                              Rs. {balanceDue.toLocaleString()}
                            </span>
                            <button
                              onClick={() => openPaymentCollector(apt)}
                              className="px-1.5 py-0.5 rounded bg-teal-600 hover:bg-teal-500 text-white font-bold text-[10px] shadow-2xs transition-colors cursor-pointer"
                              title="Collect Cash/Card at Reception"
                            >
                              Collect
                            </button>
                          </div>
                        )}
                      </td>

                      {/* Workflow State Badge */}
                      <td className="py-2.5 px-3 whitespace-nowrap">
                        <span className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold ${
                          apt.workflowState === 'booked' ? 'bg-slate-100 text-slate-700 border border-slate-200' :
                          apt.workflowState === 'checked_in' ? 'bg-sky-50 text-sky-700 border border-sky-200' :
                          apt.workflowState === 'preparing' ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' :
                          apt.workflowState === 'in_progress' ? 'bg-amber-50 text-amber-800 border border-amber-300' :
                          apt.workflowState === 'acquired' ? 'bg-purple-50 text-purple-700 border border-purple-200' :
                          apt.workflowState === 'reported' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                          apt.workflowState === 'delivered' ? 'bg-teal-50 text-teal-700 border border-teal-200' :
                          'bg-rose-50 text-rose-700 border border-rose-200'
                        }`}>
                          {apt.workflowState.replace('_', ' ').toUpperCase()}
                        </span>
                      </td>

                      {/* Desk Actions Toolbar */}
                      <td className="py-2.5 px-3 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end space-x-1">
                          {/* 1-Click Check-In */}
                          {isBooked && (
                            <button
                              onClick={() => onCheckIn(apt.id)}
                              className="flex items-center space-x-1 px-2 py-1 rounded bg-sky-600 hover:bg-sky-500 text-white font-bold text-[11px] transition-all shadow-2xs cursor-pointer"
                              title="Confirm patient arrival and allocate to queue"
                            >
                              <UserCheck className="w-3 h-3" />
                              <span>Check In</span>
                            </button>
                          )}

                          {/* Print Token Slip */}
                          <button
                            onClick={() => setTokenSlipApt(apt)}
                            title="Print Queue Token Slip"
                            className="p-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors cursor-pointer"
                          >
                            <Printer className="w-3.5 h-3.5" />
                          </button>

                          {/* Print Wristband Label */}
                          <button
                            onClick={() => setWristbandApt(apt)}
                            title="Print Patient Wristband / Specimen Barcode Label"
                            className="p-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 transition-colors cursor-pointer"
                          >
                            <Tag className="w-3.5 h-3.5" />
                          </button>

                          {/* Inspect Patient Card Drawer */}
                          <button
                            onClick={() => setPatientDetailApt(apt)}
                            title="View Patient Record & History"
                            className="p-1 rounded bg-slate-100 hover:bg-cyan-50 text-slate-600 hover:text-cyan-700 border border-slate-300 transition-colors cursor-pointer"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </button>

                          {/* Edit / Reassign */}
                          <button
                            onClick={() => openEditModal(apt)}
                            title="Reassign Room, Change Time or Priority"
                            className="p-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-300 transition-colors cursor-pointer"
                          >
                            <Edit3 className="w-3.5 h-3.5" />
                          </button>

                          {/* Cancel / No-Show */}
                          {isBooked && (
                            <button
                              onClick={() => {
                                setCancelModalApt(apt);
                                setCancelReason('Patient did not arrive at appointment slot');
                              }}
                              title="Mark No-Show or Cancel"
                              className="p-1 rounded bg-slate-100 hover:bg-rose-50 text-slate-400 hover:text-rose-600 border border-slate-300 transition-colors cursor-pointer"
                            >
                              <UserX className="w-3.5 h-3.5" />
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })
              )}
            </tbody>
          </table>
        </div>
      </div>

      {/* QUICK PAYMENT COLLECTION MODAL */}
      {paymentModalApt && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <div className="flex items-center space-x-2">
                <div className="w-8 h-8 rounded-lg bg-teal-50 text-teal-700 flex items-center justify-center font-bold">
                  <CreditCard className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Front Desk POS Payment Collection</h3>
                  <p className="text-[11px] text-slate-500">Amad Diagnostic Centre Cashier Counter</p>
                </div>
              </div>
              <button
                onClick={() => setPaymentModalApt(null)}
                className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Patient & Study Summary */}
            <div className="bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs space-y-1.5">
              <div className="flex justify-between">
                <span className="text-slate-500">Patient:</span>
                <span className="font-bold text-slate-900">{paymentModalApt.patient.name} ({paymentModalApt.patient.mrn})</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Procedure:</span>
                <span className="font-semibold text-slate-800">{paymentModalApt.service.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Token Number:</span>
                <span className="font-mono font-bold text-cyan-700">{paymentModalApt.tokenNumber}</span>
              </div>
            </div>

            {/* Payment Input Form */}
            <form onSubmit={handleSavePayment} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-slate-700 font-semibold mb-1">Payment Amount (PKR) *</label>
                <input
                  type="number"
                  value={payAmount}
                  onChange={(e) => setPayAmount(Number(e.target.value))}
                  min={1}
                  required
                  className="w-full bg-white text-slate-900 px-3 py-2 rounded-lg border border-slate-300 text-sm font-bold focus:outline-none focus:ring-2 focus:ring-teal-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Tender / Payment Method</label>
                <div className="grid grid-cols-3 gap-2">
                  {(['cash', 'card', 'mobile', 'bank', 'insurance'] as const).map((m) => (
                    <button
                      key={m}
                      type="button"
                      onClick={() => setPayMethod(m)}
                      className={`py-1.5 px-2 rounded-lg border text-center font-semibold capitalize cursor-pointer transition-all ${
                        payMethod === m
                          ? 'bg-teal-600 text-white border-teal-600 shadow-2xs'
                          : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                      }`}
                    >
                      {m === 'mobile' ? 'EasyPaisa/Jazz' : m}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Receipt / Transaction Reference</label>
                <input
                  type="text"
                  value={payRef}
                  onChange={(e) => setPayRef(e.target.value)}
                  placeholder="e.g. POS-Slip #4819 or Cash Register Ref"
                  className="w-full bg-white text-slate-800 px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-teal-500"
                />
              </div>

              <div className="pt-2 flex space-x-2">
                <button
                  type="button"
                  onClick={() => setPaymentModalApt(null)}
                  className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 flex items-center justify-center space-x-1 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold cursor-pointer shadow-md shadow-teal-600/20"
                >
                  <DollarSign className="w-4 h-4" />
                  <span>Confirm Payment</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* REASSIGN / EDIT MODAL */}
      {editModalApt && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <div>
                <h3 className="font-bold text-slate-900 text-sm">Modify Appointment Details</h3>
                <p className="text-[11px] text-slate-500">Token #{editModalApt.tokenNumber} • {editModalApt.patient.name}</p>
              </div>
              <button
                onClick={() => setEditModalApt(null)}
                className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <form onSubmit={handleSaveEdit} className="space-y-3.5 text-xs">
              <div>
                <label className="block text-slate-700 font-semibold mb-1">Priority Level</label>
                <select
                  value={editPriority}
                  onChange={(e) => setEditPriority(e.target.value as Priority)}
                  className="w-full bg-white text-slate-800 px-3 py-2 rounded-lg border border-slate-300 font-medium focus:outline-none focus:ring-1 focus:ring-cyan-500"
                >
                  <option value="routine">Routine (Standard Queue)</option>
                  <option value="urgent">Urgent (Priority Bump)</option>
                  <option value="stat">STAT Emergency (Trauma Immediate)</option>
                </select>
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Assigned Suite / Room</label>
                <input
                  type="text"
                  value={editRoom}
                  onChange={(e) => setEditRoom(e.target.value)}
                  className="w-full bg-white text-slate-800 px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Scheduled Time Slot</label>
                <input
                  type="text"
                  value={editTime}
                  onChange={(e) => setEditTime(e.target.value)}
                  className="w-full bg-white text-slate-800 px-3 py-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div>
                <label className="block text-slate-700 font-semibold mb-1">Reception Notes / Indication</label>
                <textarea
                  value={editNotes}
                  onChange={(e) => setEditNotes(e.target.value)}
                  rows={2}
                  placeholder="Special instructions or clinical condition..."
                  className="w-full bg-white text-slate-800 p-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-cyan-500"
                />
              </div>

              <div className="pt-2 flex space-x-2">
                <button
                  type="button"
                  onClick={() => setEditModalApt(null)}
                  className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="flex-1 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold cursor-pointer shadow-md shadow-cyan-600/20"
                >
                  Save Changes
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* CANCEL / NO-SHOW CONFIRMATION DIALOG */}
      {cancelModalApt && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <div className="flex items-center space-x-3 text-rose-600">
              <AlertTriangle className="w-6 h-6 shrink-0" />
              <div>
                <h3 className="font-bold text-slate-900 text-sm">Cancel / Mark No-Show</h3>
                <p className="text-[11px] text-slate-500">{cancelModalApt.patient.name} ({cancelModalApt.tokenNumber})</p>
              </div>
            </div>

            <div className="text-xs text-slate-600 space-y-2">
              <p>Please provide a cancellation or no-show reason for audit logging:</p>
              <textarea
                value={cancelReason}
                onChange={(e) => setCancelReason(e.target.value)}
                rows={2}
                placeholder="Reason for cancellation or failure to arrive..."
                className="w-full bg-white text-slate-800 p-2 rounded-lg border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500 text-xs"
              />
            </div>

            <div className="flex space-x-2 pt-2">
              <button
                onClick={() => setCancelModalApt(null)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs cursor-pointer"
              >
                Go Back
              </button>
              <button
                onClick={handleConfirmCancel}
                className="flex-1 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs cursor-pointer shadow-md shadow-rose-600/20"
              >
                Confirm Cancel
              </button>
            </div>
          </div>
        </div>
      )}

      {/* PATIENT DETAIL / PROFILE DRAWER */}
      {patientDetailApt && (
        <div className="fixed inset-0 z-50 bg-black/50 backdrop-blur-xs flex justify-end">
          <div className="bg-white w-full max-w-md h-full overflow-y-auto shadow-2xl p-6 flex flex-col justify-between space-y-6 animate-in slide-in-from-right duration-200">
            <div className="space-y-6">
              {/* Drawer Header */}
              <div className="flex items-center justify-between pb-4 border-b border-slate-200">
                <div className="flex items-center space-x-2.5">
                  <div className="w-10 h-10 rounded-xl bg-cyan-100 text-cyan-800 flex items-center justify-center font-bold text-sm">
                    {patientDetailApt.patient.name.charAt(0)}
                  </div>
                  <div>
                    <h3 className="font-bold text-slate-900 text-base">{patientDetailApt.patient.name}</h3>
                    <p className="text-xs text-slate-500 font-mono">MRN: {patientDetailApt.patient.mrn}</p>
                  </div>
                </div>
                <button
                  onClick={() => setPatientDetailApt(null)}
                  className="p-1 rounded-lg hover:bg-slate-100 text-slate-400 hover:text-slate-600 cursor-pointer"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>

              {/* Patient Core Demographics */}
              <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs">
                <div className="font-bold text-slate-800 uppercase tracking-wider text-[10px]">Patient Demographics</div>
                <div className="grid grid-cols-2 gap-2 text-slate-700 pt-1">
                  <div>
                    <span className="text-slate-400 block text-[10px]">Age / Gender</span>
                    <span className="font-semibold text-slate-900">{patientDetailApt.patient.age} yrs • {patientDetailApt.patient.gender.toUpperCase()}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block text-[10px]">Blood Group</span>
                    <span className="font-semibold text-slate-900">{patientDetailApt.patient.bloodGroup}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block text-[10px]">Phone Number</span>
                    <span className="font-mono text-slate-900">{patientDetailApt.patient.phone}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 block text-[10px]">Email</span>
                    <span className="text-slate-900 truncate block">{patientDetailApt.patient.email || 'None on record'}</span>
                  </div>
                </div>
              </div>

              {/* Current Diagnostic Study Order */}
              <div className="border border-slate-200 rounded-xl p-4 space-y-3 text-xs">
                <div className="flex items-center justify-between">
                  <div className="font-bold text-slate-800 uppercase tracking-wider text-[10px]">Study Order</div>
                  <span className="font-mono font-bold px-2 py-0.5 rounded bg-cyan-50 text-cyan-800 border border-cyan-200">
                    Token #{patientDetailApt.tokenNumber}
                  </span>
                </div>

                <div className="space-y-1.5">
                  <div className="text-sm font-bold text-slate-900">{patientDetailApt.service.name}</div>
                  <div className="flex items-center space-x-2">
                    <span
                      className="px-2 py-0.5 rounded font-bold text-white text-[10px]"
                      style={{ backgroundColor: patientDetailApt.modality.color }}
                    >
                      {patientDetailApt.modality.name}
                    </span>
                    <span className="text-slate-600 font-medium">{patientDetailApt.roomNumber}</span>
                  </div>
                </div>

                {patientDetailApt.referrer && (
                  <div className="pt-2 border-t border-slate-100">
                    <span className="text-slate-400 block text-[10px]">Referring Physician</span>
                    <span className="font-semibold text-slate-800">{patientDetailApt.referrer.name}</span>
                    <span className="text-slate-500 block text-[11px]">{patientDetailApt.referrer.clinicName} • {patientDetailApt.referrer.specialty}</span>
                  </div>
                )}

                {patientDetailApt.notes && (
                  <div className="pt-2 border-t border-slate-100">
                    <span className="text-slate-400 block text-[10px]">Clinical Indication / History</span>
                    <p className="text-slate-700 italic mt-0.5">{patientDetailApt.notes}</p>
                  </div>
                )}
              </div>

              {/* Preparation Guidance Checklist */}
              <div className="bg-amber-50/50 p-4 rounded-xl border border-amber-200 text-xs space-y-2">
                <div className="font-bold text-amber-900 uppercase tracking-wider text-[10px] flex items-center gap-1.5">
                  <ShieldAlert className="w-3.5 h-3.5 text-amber-600" />
                  <span>Modality Preparation Checklist</span>
                </div>
                <p className="text-slate-700 leading-relaxed">
                  {patientDetailApt.service.preparationInstructions || 'Standard procedure protocol. Direct patient to examination room upon technologist call.'}
                </p>
              </div>
            </div>

            {/* Drawer Footer Actions */}
            <div className="pt-4 border-t border-slate-200 space-y-2">
              <div className="flex space-x-2">
                <button
                  onClick={() => {
                    setTokenSlipApt(patientDetailApt);
                    setPatientDetailApt(null);
                  }}
                  className="flex-1 flex items-center justify-center space-x-1 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer"
                >
                  <Printer className="w-3.5 h-3.5" />
                  <span>Print Token</span>
                </button>
                <button
                  onClick={() => {
                    openPaymentCollector(patientDetailApt);
                    setPatientDetailApt(null);
                  }}
                  className="flex-1 flex items-center justify-center space-x-1 px-3 py-2 rounded-xl bg-teal-600 hover:bg-teal-500 text-white font-bold text-xs cursor-pointer shadow-md shadow-teal-600/20"
                >
                  <Receipt className="w-3.5 h-3.5" />
                  <span>Collect Payment</span>
                </button>
              </div>

              <button
                onClick={() => setPatientDetailApt(null)}
                className="w-full py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-600 font-semibold text-xs cursor-pointer"
              >
                Close Drawer
              </button>
            </div>
          </div>
        </div>
      )}

      {/* TOKEN SLIP THERMAL PRINT PREVIEW MODAL */}
      {tokenSlipApt && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <div className="text-center border-b border-slate-200 pb-3">
              <h3 className="font-black text-slate-900 text-base tracking-tight">Amad Diagnostic Centre</h3>
              <p className="text-[11px] text-slate-500">Radiology Reception Queue Slip</p>
            </div>

            <div className="bg-slate-50 p-4 rounded-xl border border-slate-200 text-center space-y-1">
              <span className="text-[11px] text-slate-500 uppercase font-semibold">Queue Token Number</span>
              <div className="text-4xl font-black text-cyan-800 font-mono tracking-wider">
                {tokenSlipApt.tokenNumber}
              </div>
              <div className="text-xs font-bold text-slate-700 mt-1">{tokenSlipApt.roomNumber}</div>
            </div>

            <div className="space-y-1.5 text-xs text-slate-700">
              <div className="flex justify-between">
                <span className="text-slate-500">Patient:</span>
                <span className="font-bold text-slate-900">{tokenSlipApt.patient.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">MRN:</span>
                <span className="font-mono text-slate-800">{tokenSlipApt.patient.mrn}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Exam:</span>
                <span className="font-semibold text-slate-800">{tokenSlipApt.service.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Scheduled Time:</span>
                <span className="font-mono text-slate-800">{tokenSlipApt.time}</span>
              </div>
            </div>

            {/* Modality Instructions */}
            <div className="bg-amber-50 p-2.5 rounded-lg border border-amber-200 text-[10px] text-amber-900">
              <strong className="block">Patient Preparation Notice:</strong>
              {tokenSlipApt.service.preparationInstructions || 'Please proceed to waiting lounge. Technologist will call your token number.'}
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setTokenSlipApt(null)}
                className="flex-1 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs cursor-pointer"
              >
                Close
              </button>
              <button
                onClick={() => {
                  window.print();
                  setTokenSlipApt(null);
                }}
                className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold text-xs cursor-pointer shadow-md shadow-cyan-600/20"
              >
                <Printer className="w-3.5 h-3.5" />
                <span>Print Slip</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* PATIENT WRISTBAND / SPECIMEN LABEL PRINT MODAL */}
      {wristbandApt && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-2xl max-w-sm w-full p-6 shadow-2xl space-y-4">
            <div className="text-center border-b border-slate-200 pb-3">
              <h3 className="font-bold text-slate-900 text-sm">Patient Wristband & Film Label</h3>
              <p className="text-[11px] text-slate-500">Thermal Barcode Tag (2.5" × 1.0")</p>
            </div>

            {/* Wristband Preview Box */}
            <div className="border-2 border-dashed border-slate-300 p-4 rounded-xl bg-slate-50 space-y-2">
              <div className="flex justify-between items-start">
                <div>
                  <div className="font-black text-slate-900 text-sm">{wristbandApt.patient.name}</div>
                  <div className="text-[10px] text-slate-600 font-mono">
                    MRN: {wristbandApt.patient.mrn} • {wristbandApt.patient.age}y {wristbandApt.patient.gender[0].toUpperCase()}
                  </div>
                </div>
                <span className="font-mono font-bold text-xs bg-slate-200 px-1.5 py-0.5 rounded">
                  {wristbandApt.tokenNumber}
                </span>
              </div>

              {/* Barcode Simulation */}
              <div className="bg-white p-2 rounded border border-slate-200 flex flex-col items-center justify-center space-y-1">
                <div className="flex items-center space-x-1 h-8">
                  {[4,2,5,1,3,2,6,1,4,2,3,5,2,4,1,6,2,3,4,1,5,2].map((h, i) => (
                    <span
                      key={i}
                      className="bg-slate-900 inline-block"
                      style={{ width: `${(i % 3) + 1}px`, height: `${h * 4 + 10}px` }}
                    ></span>
                  ))}
                </div>
                <span className="font-mono text-[9px] text-slate-500 tracking-widest">{wristbandApt.patient.mrn}</span>
              </div>

              <div className="text-[10px] text-slate-500 flex justify-between">
                <span>{wristbandApt.service.name}</span>
                <span>{wristbandApt.roomNumber.split(' ')[0]}</span>
              </div>
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setWristbandApt(null)}
                className="flex-1 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs cursor-pointer"
              >
                Close
              </button>
              <button
                onClick={() => {
                  window.print();
                  setWristbandApt(null);
                }}
                className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-semibold text-xs cursor-pointer shadow-md shadow-cyan-600/20"
              >
                <Tag className="w-3.5 h-3.5" />
                <span>Print Label</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* DAILY RECEPTION RUN-SHEET MANIFEST MODAL */}
      {showManifestModal && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-2xl max-w-4xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <div>
                <h3 className="font-bold text-slate-900 text-base">Amad Diagnostic Centre — Daily Front Desk Manifest</h3>
                <p className="text-xs text-slate-500">Date: {filters.dateRangeMode !== 'all' && filters.startDate ? `${filters.startDate} to ${filters.endDate || filters.startDate}` : 'All Dates'} • Total Registered: {filteredAppointments.length}</p>
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => window.print()}
                  className="flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white font-semibold text-xs cursor-pointer shadow-2xs"
                >
                  <Printer className="w-3.5 h-3.5" />
                  <span>Print Manifest</span>
                </button>
                <button
                  onClick={() => setShowManifestModal(false)}
                  className="text-slate-400 hover:text-slate-600 p-1 cursor-pointer"
                >
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Manifest Table */}
            <div className="w-full overflow-x-hidden flex-1 overflow-y-auto border border-slate-200 rounded-xl">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-100 text-slate-700 font-semibold sticky top-0">
                  <tr>
                    <th className="py-2.5 px-3">Token #</th>
                    <th className="py-2.5 px-3">MRN</th>
                    <th className="py-2.5 px-3">Patient Name</th>
                    <th className="py-2.5 px-3">Procedure</th>
                    <th className="py-2.5 px-3">Slot</th>
                    <th className="py-2.5 px-3">Arrival</th>
                    <th className="py-2.5 px-3">Payment</th>
                    <th className="py-2.5 px-3">Signature</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-200 text-slate-800">
                  {filteredAppointments.map((apt) => {
                    const inv = getInvoiceForApt(apt);
                    const isPaid = inv ? inv.balanceDue === 0 : false;
                    return (
                      <tr key={apt.id}>
                        <td className="py-2.5 px-3 font-mono font-bold">{apt.tokenNumber}</td>
                        <td className="py-2.5 px-3 font-mono">{apt.patient.mrn}</td>
                        <td className="py-2.5 px-3 font-semibold">{apt.patient.name}</td>
                        <td className="py-2.5 px-3">{apt.service.name}</td>
                        <td className="py-2.5 px-3 font-mono">{apt.time}</td>
                        <td className="py-2.5 px-3 font-mono">{apt.checkedInAt || '—'}</td>
                        <td className="py-2.5 px-3 font-semibold">
                          {isPaid ? <span className="text-emerald-700">PAID</span> : <span className="text-rose-700">DUE</span>}
                        </td>
                        <td className="py-2.5 px-3 border-b border-dashed border-slate-300 w-24"></td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div className="flex justify-end pt-2">
              <button
                onClick={() => setShowManifestModal(false)}
                className="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs cursor-pointer"
              >
                Close
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
