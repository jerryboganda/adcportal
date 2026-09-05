import React, { useState, useEffect, useRef, useMemo } from 'react';
import {
  Radiation,
  ShieldCheck,
  ShieldAlert,
  Play,
  CheckCircle2,
  AlertTriangle,
  Clock,
  FileCheck,
  Flame,
  Search,
  RotateCcw,
  Eye,
  FileText,
  Printer,
  Ban,
  Layers,
  Sparkles,
  Sliders,
  Maximize2,
  ZoomIn,
  ZoomOut,
  RefreshCw,
  X,
  Droplet,
  Info,
  ChevronRight,
  Activity,
  Check,
  AlertCircle,
  LayoutGrid,
  List,
  User
} from 'lucide-react';
import { Appointment, Modality, DoseLog, WorkflowState } from '../types';
import { RadiologicalVisualizer } from './RadiologicalVisualizer';
import { WorklistFilterToolbar } from './WorklistFilterToolbar';
import { SortableColumnHeader } from './SortableColumnHeader';
import {
  AdvancedFilterState,
  defaultAdvancedFilters,
  SortField,
  matchesAdvancedFilters,
  compareAppointmentsMultiSort,
} from '../utils/tableUtils';

interface TechnologistViewProps {
  appointments: Appointment[];
  modalities: Modality[];
  onStartPreparing: (aptId: string) => void;
  onOpenScreeningModal: (apt: Appointment) => void;
  onStartAcquisition: (apt: Appointment) => void;
  onOpenDoseModal: (apt: Appointment) => void;
  onCancelStudy: (aptId: string, reason: string) => void;
  onUpdateAppointment?: (aptId: string, updates: Partial<Appointment>) => void;
}

export const TechnologistView: React.FC<TechnologistViewProps> = ({
  appointments,
  modalities,
  onStartPreparing,
  onOpenScreeningModal,
  onStartAcquisition,
  onOpenDoseModal,
  onCancelStudy,
  onUpdateAppointment,
}) => {
  // Advanced Filters & Multi-Column Sorting state
  const [filters, setFilters] = useState<AdvancedFilterState>({
    ...defaultAdvancedFilters,
    dateRangeMode: 'all',
  });
  const [sortFields, setSortFields] = useState<SortField[]>([
    { column: 'priority', direction: 'asc' },
    { column: 'time', direction: 'asc' },
  ]);
  const [viewMode, setViewMode] = useState<'cards' | 'table'>('cards');

  // Modals state
  const [pacsModalApt, setPacsModalApt] = useState<Appointment | null>(null);
  const [noteModalApt, setNoteModalApt] = useState<Appointment | null>(null);
  const [noteText, setNoteText] = useState('');
  const [cancelModalApt, setCancelModalApt] = useState<Appointment | null>(null);
  const [cancelReason, setCancelReason] = useState('Patient Refusal');
  const [cancelNotes, setCancelNotes] = useState('');
  const [wristbandApt, setWristbandApt] = useState<Appointment | null>(null);

  // Active tech pipeline states
  const techStates = ['checked_in', 'preparing', 'in_progress', 'acquired'];

  const allTechAppointments = appointments.filter((a) => techStates.includes(a.workflowState));

  // Count summaries
  const statCount = allTechAppointments.filter(a => a.priority === 'stat').length;
  const inProgressCount = allTechAppointments.filter(a => a.workflowState === 'in_progress').length;
  const needsScreeningCount = allTechAppointments.filter(a => a.screeningRequired && !a.screeningCleared).length;
  const acquiredCount = allTechAppointments.filter(a => a.workflowState === 'acquired').length;

  // Available status items for technologist queue
  const availableStatuses = useMemo(() => [
    { value: 'checked_in' as WorkflowState, label: 'Checked In / Lounge', count: allTechAppointments.filter(a => a.workflowState === 'checked_in').length },
    { value: 'preparing' as WorkflowState, label: 'Preparing', count: allTechAppointments.filter(a => a.workflowState === 'preparing').length },
    { value: 'in_progress' as WorkflowState, label: 'Scanning Active', count: inProgressCount },
    { value: 'acquired' as WorkflowState, label: 'Acquired / PACS QC', count: acquiredCount },
  ], [allTechAppointments, inProgressCount, acquiredCount]);

  // Filtered and multi-sorted appointments
  const filteredAppointments = useMemo(() => {
    const list = allTechAppointments.filter((apt) => matchesAdvancedFilters(apt, filters));
    return list.sort((a, b) => compareAppointmentsMultiSort(a, b, sortFields));
  }, [allTechAppointments, filters, sortFields]);

  // Handle Note Save
  const handleSaveNote = () => {
    if (!noteModalApt || !onUpdateAppointment) return;
    onUpdateAppointment(noteModalApt.id, { notes: noteText.trim() });
    setNoteModalApt(null);
  };

  // Handle Repeat / Restart Scan
  const handleRepeatScan = (apt: Appointment) => {
    if (!onUpdateAppointment) return;
    onUpdateAppointment(apt.id, {
      workflowState: 'in_progress',
      rejectReason: undefined,
      inProgressAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
    });
  };

  // Handle Cancel Submission
  const handleConfirmCancel = () => {
    if (!cancelModalApt) return;
    const fullReason = cancelNotes.trim() ? `${cancelReason} - ${cancelNotes.trim()}` : cancelReason;
    onCancelStudy(cancelModalApt.id, fullReason);
    setCancelModalApt(null);
    setCancelNotes('');
  };

  return (
    <div className="space-y-5">
      {/* Header & Quick Action Bar */}
      <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div className="flex items-center space-x-2.5">
              <h1 className="text-xl font-black text-slate-900 tracking-tight">Technologist Worklist & PACS Suite</h1>
              <span className="bg-amber-50 text-amber-800 text-xs px-2.5 py-1 rounded-md border border-amber-200 font-bold whitespace-nowrap inline-flex items-center gap-1">
                <Radiation className="w-3.5 h-3.5 text-amber-600" /> ALARA Protocol Active
              </span>
            </div>
            <p className="text-slate-500 text-xs mt-1">
              Patient safety verification, protocol selection, scan acquisition, radiation dose tracking, and PACS DICOM QA.
            </p>
          </div>

          {/* Quick Metrics & View Toggle */}
          <div className="flex flex-wrap items-center gap-2">
            <div 
              onClick={() => setFilters(prev => ({ ...prev, statuses: [], priorities: [] }))}
              className="flex items-center space-x-2 bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-xl text-xs cursor-pointer hover:bg-slate-100 transition-colors"
            >
              <Activity className="w-4 h-4 text-cyan-600" />
              <span className="text-slate-600">Active Queue:</span>
              <span className="font-mono font-bold text-slate-900">{allTechAppointments.length}</span>
            </div>
            <div 
              onClick={() => setFilters(prev => ({ ...prev, statuses: ['in_progress'], priorities: [] }))}
              className="flex items-center space-x-2 bg-amber-50 border border-amber-200 px-3 py-1.5 rounded-xl text-xs cursor-pointer hover:bg-amber-100 transition-colors"
            >
              <Play className="w-3.5 h-3.5 text-amber-600 fill-current" />
              <span className="text-amber-800">Scanning:</span>
              <span className="font-mono font-bold text-amber-900">{inProgressCount}</span>
            </div>
            {statCount > 0 && (
              <div 
                onClick={() => setFilters(prev => ({ ...prev, priorities: ['stat'] }))}
                className="flex items-center space-x-2 bg-rose-50 border border-rose-300 px-3 py-1.5 rounded-xl text-xs animate-pulse cursor-pointer hover:bg-rose-100 transition-colors"
              >
                <Flame className="w-3.5 h-3.5 text-rose-600" />
                <span className="text-rose-800 font-bold">STAT:</span>
                <span className="font-mono font-black text-rose-900">{statCount}</span>
              </div>
            )}
            <div 
              onClick={() => setFilters(prev => ({ ...prev, statuses: ['acquired'], priorities: [] }))}
              className="flex items-center space-x-2 bg-purple-50 border border-purple-200 px-3 py-1.5 rounded-xl text-xs cursor-pointer hover:bg-purple-100 transition-colors"
            >
              <FileCheck className="w-4 h-4 text-purple-600" />
              <span className="text-purple-800">Acquired (QA):</span>
              <span className="font-mono font-bold text-purple-900">{acquiredCount}</span>
            </div>

            {/* View Mode Toggle */}
            <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 ml-1">
              <button
                onClick={() => setViewMode('cards')}
                className={`p-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer ${
                  viewMode === 'cards' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-800'
                }`}
                title="Suite Examination Cards"
              >
                <LayoutGrid className="w-3.5 h-3.5" />
                <span className="hidden sm:inline text-[11px]">Cards</span>
              </button>
              <button
                onClick={() => setViewMode('table')}
                className={`p-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-all cursor-pointer ${
                  viewMode === 'table' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-500 hover:text-slate-800'
                }`}
                title="High-Density Detailed Table"
              >
                <List className="w-3.5 h-3.5" />
                <span className="hidden sm:inline text-[11px]">Table</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      {/* Advanced Filter, Date & Search Toolbar with Multi-Sort Badges */}
      <WorklistFilterToolbar
        filters={filters}
        onFilterChange={setFilters}
        sortFields={sortFields}
        onSortChange={setSortFields}
        modalities={modalities}
        availableStatuses={availableStatuses}
        totalCount={allTechAppointments.length}
        filteredCount={filteredAppointments.length}
        showBillingFilter={false}
        showScreeningFilter={true}
        variantTitle="Technologist Worklist"
      />

      {/* Worklist Display (Table or Cards) */}
      {filteredAppointments.length === 0 ? (
        <div className="bg-white p-12 text-center rounded-2xl border border-slate-200 shadow-sm">
          <CheckCircle2 className="w-12 h-12 text-emerald-500 mx-auto mb-3 opacity-70" />
          <h3 className="text-base font-bold text-slate-900">Technologist Queue Clear</h3>
          <p className="text-xs text-slate-500 mt-1">No pending examinations match the selected filter criteria.</p>
          <button
            onClick={() => {
              setFilters({ ...defaultAdvancedFilters, dateRangeMode: 'all' });
              setSortFields([]);
            }}
            className="mt-3 text-xs text-amber-700 hover:underline font-semibold cursor-pointer"
          >
            Clear Filters
          </button>
        </div>
      ) : viewMode === 'table' ? (
        /* Detailed High-Density Table View */
        <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="w-full overflow-x-hidden">
            <table className="w-full text-left text-xs table-auto">
              <thead className="bg-slate-100/90 text-slate-600 uppercase font-bold text-[11px] tracking-wider border-b border-slate-200">
                <tr>
                  <SortableColumnHeader
                    column="token"
                    label="Token & Modality"
                    sortFields={sortFields}
                    onSortChange={setSortFields}
                    className="whitespace-nowrap"
                  />
                  <SortableColumnHeader
                    column="priority"
                    label="Priority"
                    sortFields={sortFields}
                    onSortChange={setSortFields}
                    className="whitespace-nowrap"
                  />
                  <SortableColumnHeader
                    column="patientName"
                    label="Patient & MRN"
                    sortFields={sortFields}
                    onSortChange={setSortFields}
                  />
                  <SortableColumnHeader
                    column="service"
                    label="Examination & Room"
                    sortFields={sortFields}
                    onSortChange={setSortFields}
                  />
                  <SortableColumnHeader
                    column="safety"
                    label="Safety Questionnaire"
                    sortFields={sortFields}
                    onSortChange={setSortFields}
                    className="whitespace-nowrap"
                  />
                  <SortableColumnHeader
                    column="workflowState"
                    label="Status"
                    sortFields={sortFields}
                    onSortChange={setSortFields}
                    className="whitespace-nowrap"
                  />
                  <th className="py-2.5 px-3 text-right whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200 text-slate-700">
                {filteredAppointments.map((apt) => {
                  const isStat = apt.priority === 'stat';
                  const isUrgent = apt.priority === 'urgent';
                  const isCheckedIn = apt.workflowState === 'checked_in';
                  const isPreparing = apt.workflowState === 'preparing';
                  const isInProgress = apt.workflowState === 'in_progress';
                  const isAcquired = apt.workflowState === 'acquired';
                  const hasReject = Boolean(apt.rejectReason);

                  return (
                    <tr 
                      key={apt.id}
                      className={`hover:bg-slate-50/80 transition-colors ${
                        isStat ? 'bg-rose-50/20' : hasReject ? 'bg-rose-50/30' : isInProgress ? 'bg-amber-50/20' : ''
                      }`}
                    >
                      <td className="py-3 px-3">
                        <div className="flex items-center space-x-2">
                          <span className="font-mono font-bold text-slate-900 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                            {apt.tokenNumber}
                          </span>
                          <span
                            className="text-[10px] font-bold text-white px-1.5 py-0.5 rounded font-mono"
                            style={{ backgroundColor: apt.modality.color }}
                          >
                            {apt.modality.code}
                          </span>
                        </div>
                      </td>
                      <td className="py-3 px-3">
                        {isStat ? (
                          <span className="px-2 py-0.5 rounded bg-rose-600 text-white font-black text-[10px] uppercase inline-flex items-center gap-1 shadow-2xs animate-pulse">
                            <Flame className="w-3 h-3" /> STAT
                          </span>
                        ) : isUrgent ? (
                          <span className="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold text-[10px] uppercase border border-amber-300">
                            Urgent
                          </span>
                        ) : (
                          <span className="text-slate-500 font-medium text-[11px]">Routine</span>
                        )}
                      </td>
                      <td className="py-3 px-3">
                        <div className="font-bold text-slate-900">{apt.patient.name}</div>
                        <div className="text-[11px] text-slate-500 font-mono">
                          MRN: {apt.patient.mrn} • {apt.patient.age}y/{apt.patient.gender[0].toUpperCase()}
                        </div>
                      </td>
                      <td className="py-3 px-3">
                        <div className="font-medium text-slate-800">{apt.service.name}</div>
                        <div className="text-[11px] text-slate-500 font-mono">
                          Room: <strong className="text-slate-700">{apt.roomNumber}</strong> • {apt.time}
                        </div>
                      </td>
                      <td className="py-3 px-3">
                        <div className="flex items-center space-x-1.5">
                          {apt.screeningCleared ? (
                            <span className="inline-flex items-center space-x-1 text-emerald-700 font-semibold bg-emerald-50 px-2 py-0.5 rounded-md border border-emerald-200">
                              <ShieldCheck className="w-3.5 h-3.5 text-emerald-600" />
                              <span>Cleared</span>
                            </span>
                          ) : (
                            <button
                              onClick={() => onOpenScreeningModal(apt)}
                              className="inline-flex items-center space-x-1 text-amber-800 font-semibold bg-amber-50 hover:bg-amber-100 px-2 py-0.5 rounded-md border border-amber-200 cursor-pointer"
                            >
                              <ShieldAlert className="w-3.5 h-3.5 text-amber-600" />
                              <span>Screening Req</span>
                            </button>
                          )}
                        </div>
                      </td>
                      <td className="py-3 px-3">
                        <span className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider whitespace-nowrap ${
                          isInProgress ? 'bg-amber-100 text-amber-800 border border-amber-300 animate-pulse' :
                          isPreparing ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' :
                          isAcquired ? 'bg-purple-100 text-purple-800 border border-purple-200' :
                          'bg-sky-100 text-sky-800 border border-sky-200'
                        }`}>
                          {apt.workflowState.replace('_', ' ')}
                        </span>
                      </td>
                      <td className="py-3 px-3 text-right">
                        <div className="flex items-center justify-end space-x-1.5">
                          <button
                            onClick={() => setPacsModalApt(apt)}
                            className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-cyan-700 border border-slate-300 transition-colors cursor-pointer"
                            title="PACS DICOM Viewer"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </button>
                          {isCheckedIn && (
                            <button
                              onClick={() => onStartPreparing(apt.id)}
                              className="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-[11px] shadow-xs cursor-pointer"
                            >
                              Prep
                            </button>
                          )}
                          {isPreparing && (
                            <button
                              onClick={() => onStartAcquisition(apt)}
                              className="px-2.5 py-1 rounded-lg bg-amber-600 hover:bg-amber-500 text-white font-bold text-[11px] shadow-xs cursor-pointer"
                            >
                              Scan
                            </button>
                          )}
                          {isInProgress && (
                            <button
                              onClick={() => onOpenDoseModal(apt)}
                              className="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-[11px] shadow-xs cursor-pointer"
                            >
                              Complete
                            </button>
                          )}
                        </div>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
          {filteredAppointments.map((apt) => {
            const isStat = apt.priority === 'stat';
            const isUrgent = apt.priority === 'urgent';
            const isCheckedIn = apt.workflowState === 'checked_in';
            const isPreparing = apt.workflowState === 'preparing';
            const isInProgress = apt.workflowState === 'in_progress';
            const isAcquired = apt.workflowState === 'acquired';
            const hasReject = Boolean(apt.rejectReason);

            return (
              <div
                key={apt.id}
                className={`bg-white rounded-2xl border p-4 shadow-sm flex flex-col justify-between transition-all relative ${
                  isStat
                    ? 'border-rose-300 ring-1 ring-rose-400 bg-rose-50/15'
                    : hasReject
                    ? 'border-rose-400 ring-1 ring-rose-400 bg-rose-50/25'
                    : isInProgress
                    ? 'border-amber-300 ring-1 ring-amber-400 bg-amber-50/15'
                    : isAcquired
                    ? 'border-purple-200 bg-purple-50/10'
                    : 'border-slate-200'
                }`}
              >
                {/* Rejection by Radiologist Warning Banner */}
                {hasReject && (
                  <div className="mb-3 p-2.5 rounded-xl bg-rose-100/90 border border-rose-300 text-rose-900 text-xs flex items-start justify-between gap-2">
                    <div className="flex items-start space-x-2">
                      <AlertCircle className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                      <div>
                        <span className="font-black uppercase tracking-wide text-[10px] bg-rose-600 text-white px-1.5 py-0.2 rounded mr-1">
                          REJECTED BY RADIOLOGIST
                        </span>
                        <div className="mt-1 font-medium text-[11px] text-rose-800">
                          <strong>Reason:</strong> {apt.rejectReason}
                        </div>
                      </div>
                    </div>
                    <button
                      onClick={() => handleRepeatScan(apt)}
                      className="px-2 py-1 rounded bg-rose-600 hover:bg-rose-500 text-white font-bold text-[11px] transition-colors whitespace-nowrap shadow-xs cursor-pointer flex items-center gap-1"
                    >
                      <RotateCcw className="w-3 h-3" /> Re-Acquire
                    </button>
                  </div>
                )}

                {/* Card Header */}
                <div>
                  <div className="flex items-start justify-between gap-2">
                    <div className="flex items-center space-x-2 flex-wrap gap-y-1">
                      <span className="font-mono font-black text-sm px-2.5 py-0.5 rounded-lg bg-slate-100 text-cyan-800 border border-slate-300">
                        {apt.tokenNumber}
                      </span>
                      <span
                        className="text-[11px] font-bold text-white px-2 py-0.5 rounded font-mono shadow-2xs"
                        style={{ backgroundColor: apt.modality.color }}
                      >
                        {apt.modality.code}
                      </span>
                      {isStat && (
                        <span className="px-2 py-0.5 rounded bg-rose-600 text-white font-black text-[10px] uppercase flex items-center gap-1 shadow-2xs animate-pulse">
                          <Flame className="w-3 h-3" /> STAT Traumatology
                        </span>
                      )}
                      {isUrgent && (
                        <span className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 font-bold text-[10px] uppercase border border-amber-300">
                          Urgent
                        </span>
                      )}
                    </div>

                    {/* Status Badge */}
                    <span className={`text-[10px] font-bold px-2 py-0.5 rounded uppercase tracking-wider whitespace-nowrap ${
                      isInProgress ? 'bg-amber-100 text-amber-800 border border-amber-300 animate-pulse' :
                      isPreparing ? 'bg-indigo-100 text-indigo-800 border border-indigo-200' :
                      isAcquired ? 'bg-purple-100 text-purple-800 border border-purple-200' :
                      'bg-sky-100 text-sky-800 border border-sky-200'
                    }`}>
                      {apt.workflowState.replace('_', ' ')}
                    </span>
                  </div>

                  {/* Patient Demographics */}
                  <div className="mt-2.5 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                    <div className="flex justify-between items-start">
                      <div>
                        <div className="font-bold text-xs text-slate-900">{apt.patient.name}</div>
                        <div className="text-[11px] text-slate-500 font-mono mt-0.5">
                          MRN: {apt.patient.mrn} • {apt.patient.age}y • {apt.patient.gender.toUpperCase()}
                        </div>
                      </div>
                      <span className="text-[11px] text-slate-600 font-mono font-semibold bg-white px-2 py-0.5 rounded border border-slate-200">
                        {apt.roomNumber}
                      </span>
                    </div>

                    {apt.patient.allergies && (
                      <div className="mt-1.5 text-[10px] flex items-center text-amber-900 bg-amber-50 px-2 py-0.5 rounded border border-amber-200 font-medium">
                        <AlertTriangle className="w-3 h-3 mr-1 shrink-0 text-amber-600" />
                        <span>Allergies: <strong>{apt.patient.allergies}</strong></span>
                      </div>
                    )}
                  </div>

                  {/* Examination Details */}
                  <div className="mt-2.5 space-y-1">
                    <div className="flex items-center justify-between">
                      <div className="text-xs font-bold text-slate-800">{apt.service.name}</div>
                      <span className="text-[10px] text-slate-400 font-mono">{apt.service.durationMinutes} mins</span>
                    </div>
                    <div className="text-[11px] text-slate-500 leading-snug">{apt.service.preparationInstructions}</div>
                    
                    {/* Clinical / Tech Notes */}
                    {apt.notes && (
                      <div className="text-[11px] text-cyan-900 bg-cyan-50/60 p-1.5 rounded-md border border-cyan-200 italic">
                        <strong>Tech Note:</strong> {apt.notes}
                      </div>
                    )}
                  </div>

                  {/* Safety Screening Status */}
                  <div className="mt-2.5 flex items-center justify-between p-2 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                    <div className="flex items-center space-x-2">
                      {apt.screeningCleared ? (
                        <ShieldCheck className="w-4 h-4 text-emerald-600 shrink-0" />
                      ) : (
                        <ShieldAlert className="w-4 h-4 text-amber-600 shrink-0" />
                      )}
                      <span className="text-slate-700 text-[11px]">
                        Safety Clearance:{' '}
                        {apt.screeningCleared ? (
                          <strong className="text-emerald-700 font-bold">CLEARED</strong>
                        ) : (
                          <strong className="text-amber-700 font-bold">PENDING QUESTIONNAIRE</strong>
                        )}
                      </span>
                    </div>

                    <button
                      onClick={() => onOpenScreeningModal(apt)}
                      className="px-2 py-0.5 rounded bg-white hover:bg-slate-100 text-cyan-700 font-bold text-[10px] border border-slate-300 transition-colors cursor-pointer shadow-2xs"
                    >
                      {apt.screeningAnswers && apt.screeningAnswers.length > 0 ? 'Review Form' : 'Fill Form'}
                    </button>
                  </div>

                  {/* Dose & Acquisition Log Summary if completed */}
                  {apt.doseLog && (
                    <div className="mt-2 p-2 rounded-lg bg-purple-50/90 border border-purple-200 text-[11px] text-purple-900 space-y-1">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-1.5">
                          <Radiation className="w-3.5 h-3.5 text-purple-600" />
                          <span>Dose: <strong>{apt.doseLog.doseValue} {apt.doseLog.doseUnit}</strong></span>
                        </div>
                        {apt.doseLog.sliceCount && (
                          <span className="text-[10px] font-mono font-bold bg-purple-100 px-1.5 py-0.2 rounded">
                            {apt.doseLog.sliceCount} Slices ({apt.doseLog.seriesCount || 1} Series)
                          </span>
                        )}
                      </div>
                      {apt.doseLog.contrastAgent && (
                        <div className="flex items-center space-x-1 text-[10px] text-purple-800">
                          <Droplet className="w-3 h-3 text-cyan-600" />
                          <span>Contrast: {apt.doseLog.contrastAgent} ({apt.doseLog.contrastVolumeMl}ml • {apt.doseLog.contrastFlowRate || 'IV'})</span>
                        </div>
                      )}
                    </div>
                  )}
                </div>

                {/* Workflow Action Buttons Toolbar */}
                <div className="mt-3.5 pt-2.5 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
                  <div className="flex items-center space-x-1.5">
                    {/* Secondary Actions Menu */}
                    <button
                      onClick={() => {
                        setNoteModalApt(apt);
                        setNoteText(apt.notes || '');
                      }}
                      title="Add / Edit Technologist Clinical Notes"
                      className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border border-slate-200 transition-colors cursor-pointer"
                    >
                      <FileText className="w-3.5 h-3.5" />
                    </button>

                    <button
                      onClick={() => setWristbandApt(apt)}
                      title="Print Patient Accession Barcode & Wristband"
                      className="p-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border border-slate-200 transition-colors cursor-pointer"
                    >
                      <Printer className="w-3.5 h-3.5" />
                    </button>

                    {/* View PACS QC Viewer */}
                    <button
                      onClick={() => setPacsModalApt(apt)}
                      title="Launch PACS DICOM Quality Assurance Viewer"
                      className="flex items-center space-x-1 px-2 py-1 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 text-[11px] font-bold border border-cyan-200 transition-colors cursor-pointer"
                    >
                      <Eye className="w-3.5 h-3.5 text-cyan-600" />
                      <span>PACS QC</span>
                    </button>

                    <button
                      onClick={() => {
                        setCancelModalApt(apt);
                        setCancelReason('Patient Refusal');
                        setCancelNotes('');
                      }}
                      className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors cursor-pointer"
                      title="Cancel / Abort Examination"
                    >
                      <Ban className="w-3.5 h-3.5" />
                    </button>
                  </div>

                  {/* Primary Stage Transition Buttons */}
                  <div className="flex items-center space-x-2">
                    {isCheckedIn && (
                      <button
                        onClick={() => onStartPreparing(apt.id)}
                        className="flex items-center space-x-1 px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs transition-all shadow-xs cursor-pointer"
                      >
                        <Clock className="w-3.5 h-3.5" />
                        <span>Start Prep</span>
                      </button>
                    )}

                    {(isCheckedIn || isPreparing) && (
                      <button
                        onClick={() => onStartAcquisition(apt)}
                        className="flex items-center space-x-1 px-3 py-1.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-xs transition-all shadow-xs cursor-pointer"
                      >
                        <Play className="w-3.5 h-3.5 fill-current" />
                        <span>Start Scanning</span>
                      </button>
                    )}

                    {isInProgress && (
                      <button
                        onClick={() => onOpenDoseModal(apt)}
                        className="flex items-center space-x-1 px-3.5 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-black text-xs transition-all shadow-md shadow-emerald-600/20 cursor-pointer animate-bounce-subtle"
                      >
                        <CheckCircle2 className="w-3.5 h-3.5" />
                        <span>Complete & Log Dose</span>
                      </button>
                    )}

                    {isAcquired && (
                      <div className="flex items-center space-x-1.5">
                        <button
                          onClick={() => onOpenDoseModal(apt)}
                          className="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-purple-800 text-[11px] font-bold border border-purple-200 transition-colors cursor-pointer"
                        >
                          Edit Dose
                        </button>
                        <span className="text-[11px] text-purple-700 font-bold flex items-center gap-1 bg-purple-50 px-2 py-1 rounded-md border border-purple-200">
                          <FileCheck className="w-3.5 h-3.5 text-purple-600" /> In Reading Worklist
                        </span>
                      </div>
                    )}
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      )}

      {/* ========================================================================= */}
      {/* PACS DICOM QUALITY ASSURANCE VIEWER MODAL */}
      {/* ========================================================================= */}
      {pacsModalApt && (
        <PacsInspectorModal
          appointment={pacsModalApt}
          onClose={() => setPacsModalApt(null)}
          onSignOff={() => {
            if (onUpdateAppointment) {
              onUpdateAppointment(pacsModalApt.id, {
                notes: pacsModalApt.notes ? `${pacsModalApt.notes} [PACS QC Verified]` : '[PACS QC Verified by Tech]',
              });
            }
            setPacsModalApt(null);
          }}
        />
      )}

      {/* ========================================================================= */}
      {/* TECHNOLOGIST CLINICAL NOTE MODAL */}
      {/* ========================================================================= */}
      {noteModalApt && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-xl bg-cyan-50 text-cyan-700 border border-cyan-200 flex items-center justify-center">
                  <FileText className="w-4 h-4 text-cyan-600" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Technologist Clinical Remarks</h3>
                  <p className="text-[11px] text-slate-500">
                    #{noteModalApt.tokenNumber} • {noteModalApt.patient.name}
                  </p>
                </div>
              </div>
              <button onClick={() => setNoteModalApt(null)} className="p-1 rounded-lg text-slate-400 hover:text-slate-700">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-2">
              <label className="block text-xs font-semibold text-slate-700">
                Observation, Patient Condition, & Procedure Notes:
              </label>
              <textarea
                rows={4}
                value={noteText}
                onChange={(e) => setNoteText(e.target.value)}
                placeholder="e.g., 20G IV cannula placed right ACF. Patient had minor motion during initial sequence; repeated with immobilization. Contrast injected without extravasation."
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500"
              />
            </div>

            <div className="flex space-x-2 pt-2 border-t border-slate-200">
              <button
                onClick={() => setNoteModalApt(null)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveNote}
                className="flex-1 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-md shadow-cyan-600/30"
              >
                Save Notes
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* CLINICAL CANCEL / ABORT STUDY MODAL */}
      {/* ========================================================================= */}
      {cancelModalApt && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center">
                  <Ban className="w-4 h-4 text-rose-600" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Cancel / Abort Examination</h3>
                  <p className="text-[11px] text-slate-500">
                    #{cancelModalApt.tokenNumber} • {cancelModalApt.patient.name} ({cancelModalApt.patient.mrn})
                  </p>
                </div>
              </div>
              <button onClick={() => setCancelModalApt(null)} className="p-1 rounded-lg text-slate-400 hover:text-slate-700">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Standard Cancellation Reason</label>
                <select
                  value={cancelReason}
                  onChange={(e) => setCancelReason(e.target.value)}
                  className="w-full bg-white text-slate-800 p-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-rose-500 cursor-pointer"
                >
                  <option value="Patient Refusal">Patient Refusal / Non-Compliance</option>
                  <option value="Severe Contrast Reaction">Severe Contrast Allergy / Anaphylactoid</option>
                  <option value="Claustrophobia / Panic">Severe Claustrophobia / Panic Attack</option>
                  <option value="Hemodynamic Instability">Hemodynamic Instability / Medical Emergency</option>
                  <option value="Renal Impairment">Impaired Renal Function (eGFR &lt; 30)</option>
                  <option value="Equipment Technical Fault">Equipment Technical Fault / Scanner Maintenance</option>
                  <option value="Duplicate Order">Duplicate / Inappropriate Order</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Detailed Clinical Reason / Incident Log</label>
                <textarea
                  rows={3}
                  value={cancelNotes}
                  onChange={(e) => setCancelNotes(e.target.value)}
                  placeholder="Provide clinical context for audit log..."
                  className="w-full bg-white text-slate-900 p-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-rose-500"
                />
              </div>
            </div>

            <div className="flex space-x-2 pt-2 border-t border-slate-200">
              <button
                onClick={() => setCancelModalApt(null)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300"
              >
                Back
              </button>
              <button
                onClick={handleConfirmCancel}
                className="flex-1 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md shadow-rose-600/30"
              >
                Confirm Cancellation
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* ACCESSION BARCODE & PATIENT WRISTBAND MODAL */}
      {/* ========================================================================= */}
      {wristbandApt && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-xl bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center justify-center">
                  <Printer className="w-4 h-4 text-indigo-600" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Patient Wristband & Barcode Label</h3>
                  <p className="text-[11px] text-slate-500">Accession Identifier for PACS & Laboratory</p>
                </div>
              </div>
              <button onClick={() => setWristbandApt(null)} className="p-1 rounded-lg text-slate-400 hover:text-slate-700">
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Printable Label Card */}
            <div className="bg-slate-50 p-4 rounded-2xl border-2 border-dashed border-slate-300 text-slate-900 space-y-2">
              <div className="flex justify-between items-start border-b border-slate-200 pb-2">
                <div>
                  <div className="font-black text-sm text-cyan-800">AMAD DIAGNOSTIC CENTRE</div>
                  <div className="text-[10px] text-slate-500">RIS Accession: ACC-{wristbandApt.id.slice(-6).toUpperCase()}</div>
                </div>
                <div className="text-right">
                  <div className="font-mono font-black text-sm bg-slate-200 px-2 py-0.5 rounded">
                    #{wristbandApt.tokenNumber}
                  </div>
                  <div className="text-[10px] font-bold text-slate-600">{wristbandApt.modality.code}</div>
                </div>
              </div>

              <div className="space-y-0.5">
                <div className="font-bold text-xs text-slate-900">{wristbandApt.patient.name}</div>
                <div className="font-mono text-[11px] text-slate-600">
                  MRN: {wristbandApt.patient.mrn} • Age: {wristbandApt.patient.age}y • {wristbandApt.patient.gender.toUpperCase()}
                </div>
                <div className="text-[11px] font-semibold text-slate-800">{wristbandApt.service.name}</div>
              </div>

              {/* Simulated 1D Barcode */}
              <div className="pt-2 flex flex-col items-center">
                <div className="h-10 w-full bg-slate-900 rounded flex items-center justify-center p-1">
                  <div className="w-full h-full flex justify-between items-stretch bg-white p-0.5 gap-[2px]">
                    {Array.from({ length: 42 }).map((_, i) => (
                      <div
                        key={i}
                        className={`h-full ${i % 3 === 0 ? 'w-1 bg-slate-900' : i % 2 === 0 ? 'w-0.5 bg-slate-900' : 'w-0.5 bg-transparent'}`}
                      />
                    ))}
                  </div>
                </div>
                <div className="font-mono text-[10px] tracking-widest text-slate-500 mt-1">
                  *{wristbandApt.patient.mrn}*
                </div>
              </div>
            </div>

            <div className="flex space-x-2 pt-2 border-t border-slate-200">
              <button
                onClick={() => setWristbandApt(null)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300"
              >
                Close
              </button>
              <button
                onClick={() => {
                  window.print();
                }}
                className="flex-1 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-xs shadow-md shadow-indigo-600/30 flex items-center justify-center gap-1.5"
              >
                <Printer className="w-3.5 h-3.5" /> Print Label
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// =============================================================================
// SUB-COMPONENT: PACS QC & DICOM INSPECTOR MODAL
// =============================================================================
interface PacsInspectorModalProps {
  appointment: Appointment;
  onClose: () => void;
  onSignOff: () => void;
}

const PacsInspectorModal: React.FC<PacsInspectorModalProps> = ({
  appointment,
  onClose,
  onSignOff,
}) => {
  const [sliceIndex, setSliceIndex] = useState(1);
  const [windowPreset, setWindowPreset] = useState<'soft_tissue' | 'bone' | 'lung' | 'brain' | 'invert'>('soft_tissue');
  const [zoomLevel, setZoomLevel] = useState(100);
  const [isPlayingCine, setIsPlayingCine] = useState(false);
  const maxSlices = appointment.doseLog?.sliceCount || (appointment.modality.code === 'CT' ? 32 : appointment.modality.code === 'MR' ? 24 : 4);

  // Cine loop animation
  useEffect(() => {
    let interval: any = null;
    if (isPlayingCine && maxSlices > 1) {
      interval = setInterval(() => {
        setSliceIndex((prev) => (prev >= maxSlices ? 1 : prev + 1));
      }, 150);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [isPlayingCine, maxSlices]);

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6">
      <div className="bg-slate-900 border border-slate-800 rounded-3xl max-w-5xl w-full h-[92vh] shadow-2xl flex flex-col overflow-hidden text-slate-100">
        {/* Top PACS Header Bar */}
        <div className="bg-slate-950 px-5 py-3 border-b border-slate-800 flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="w-8 h-8 rounded-lg bg-cyan-950 text-cyan-400 border border-cyan-800 flex items-center justify-center">
              <Eye className="w-4 h-4" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <span className="font-mono font-bold text-cyan-400 text-xs">AMAD PACS-QA v4.2</span>
                <span className="text-[10px] bg-slate-800 text-slate-300 px-1.5 py-0.2 rounded font-mono">
                  {appointment.modality.code} • #{appointment.tokenNumber}
                </span>
              </div>
              <h2 className="text-sm font-bold text-slate-100">
                {appointment.patient.name} <span className="text-slate-400 font-normal">({appointment.patient.mrn})</span>
              </h2>
            </div>
          </div>

          <div className="flex items-center space-x-2">
            <span className="text-[11px] font-mono text-emerald-400 bg-emerald-950/60 border border-emerald-800 px-2 py-0.5 rounded flex items-center gap-1">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping" />
              DICOM STORE SCP: OK
            </span>
            <button
              onClick={onClose}
              className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 cursor-pointer"
            >
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Main Work Area */}
        <div className="flex-1 flex flex-col md:flex-row overflow-hidden">
          {/* Central DICOM Canvas Viewer */}
          <div className="flex-1 bg-black flex flex-col items-center justify-center relative p-4 select-none overflow-hidden">
            {/* DICOM Overlay Top Left */}
            <div className="absolute top-4 left-4 text-[11px] font-mono text-cyan-400/90 leading-tight pointer-events-none space-y-0.5">
              <div>{appointment.patient.name.toUpperCase()}</div>
              <div>MRN: {appointment.patient.mrn}</div>
              <div>DOB: {appointment.patient.dob} ({appointment.patient.gender[0].toUpperCase()})</div>
              <div>STUDY: {appointment.service.name}</div>
              <div>ACC: ACC-{appointment.id.slice(-6).toUpperCase()}</div>
            </div>

            {/* DICOM Overlay Top Right */}
            <div className="absolute top-4 right-4 text-[11px] font-mono text-cyan-400/90 leading-tight text-right pointer-events-none space-y-0.5">
              <div>AMAD DIAGNOSTIC CENTRE</div>
              <div>MODALITY: {appointment.modality.code}</div>
              <div>ROOM: {appointment.roomNumber}</div>
              <div>MATRIX: 512 x 512</div>
              <div>KVp: {appointment.doseLog?.kvp || 120} | mA: {appointment.doseLog?.mas || 200}</div>
            </div>

            {/* Central Simulated Radiological Image */}
            <div
              className="transition-transform duration-100 flex items-center justify-center"
              style={{ transform: `scale(${zoomLevel / 100})` }}
            >
              <RadiologicalVisualizer
                modality={appointment.modality.code}
                sliceIndex={sliceIndex}
                maxSlices={maxSlices}
                windowPreset={windowPreset}
              />
            </div>

            {/* DICOM Overlay Bottom Left */}
            <div className="absolute bottom-4 left-4 text-[11px] font-mono text-cyan-400/90 leading-tight pointer-events-none space-y-0.5">
              <div>SLICE: {sliceIndex} / {maxSlices}</div>
              <div>THICKNESS: 1.0 mm</div>
              <div>WINDOW: {windowPreset.toUpperCase()}</div>
              <div>ZOOM: {zoomLevel}%</div>
            </div>

            {/* DICOM Overlay Bottom Right */}
            <div className="absolute bottom-4 right-4 text-[11px] font-mono text-cyan-400/90 leading-tight text-right pointer-events-none space-y-0.5">
              <div>DOSE: {appointment.doseLog?.doseValue || 'N/A'} {appointment.doseLog?.doseUnit || ''}</div>
              <div>CONTRAST: {appointment.doseLog?.contrastAgent || 'NONE'}</div>
              <div>STATUS: QC VERIFICATION</div>
            </div>
          </div>

          {/* Right Toolbar & Metadata Inspection Panel */}
          <div className="w-full md:w-80 bg-slate-900 border-t md:border-t-0 md:border-l border-slate-800 p-4 flex flex-col justify-between overflow-y-auto space-y-4 text-xs">
            <div className="space-y-4">
              {/* Window Presets */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                  <Sliders className="w-3.5 h-3.5 text-cyan-400" /> Window / Level Presets
                </label>
                <div className="grid grid-cols-2 gap-1.5">
                  {[
                    { id: 'soft_tissue', label: 'Soft Tissue' },
                    { id: 'bone', label: 'Bone Window' },
                    { id: 'lung', label: 'Lung Window' },
                    { id: 'brain', label: 'Brain / Neuro' },
                    { id: 'invert', label: 'Invert Grayscale' },
                  ].map((preset) => (
                    <button
                      key={preset.id}
                      onClick={() => setWindowPreset(preset.id as any)}
                      className={`px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer text-left ${
                        windowPreset === preset.id
                          ? 'bg-cyan-500 text-slate-950 font-bold'
                          : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                      }`}
                    >
                      {preset.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Slice Navigation & Cine Loop */}
              <div className="space-y-2 bg-slate-950 p-3 rounded-xl border border-slate-800">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold text-slate-300">Slice Scrubbing</span>
                  <span className="font-mono text-cyan-400 font-bold">{sliceIndex} / {maxSlices}</span>
                </div>
                <input
                  type="range"
                  min="1"
                  max={maxSlices}
                  value={sliceIndex}
                  onChange={(e) => setSliceIndex(Number(e.target.value))}
                  className="w-full accent-cyan-400 cursor-pointer"
                />
                <div className="flex items-center justify-between pt-1">
                  <button
                    onClick={() => setIsPlayingCine(!isPlayingCine)}
                    className={`px-3 py-1 rounded-lg text-xs font-bold transition-colors cursor-pointer flex items-center gap-1.5 ${
                      isPlayingCine ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'
                    }`}
                  >
                    <Play className="w-3 h-3 fill-current" />
                    <span>{isPlayingCine ? 'Pause Cine' : 'Play Cine Loop'}</span>
                  </button>

                  <button
                    onClick={() => {
                      setSliceIndex(1);
                      setZoomLevel(100);
                      setWindowPreset('soft_tissue');
                    }}
                    className="p-1 text-slate-400 hover:text-white"
                    title="Reset View"
                  >
                    <RefreshCw className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              {/* Zoom Controls */}
              <div className="flex items-center justify-between bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                <span className="text-[11px] font-bold text-slate-300">Zoom: {zoomLevel}%</span>
                <div className="flex items-center space-x-1.5">
                  <button
                    onClick={() => setZoomLevel(Math.max(50, zoomLevel - 20))}
                    className="p-1 bg-slate-800 hover:bg-slate-700 rounded text-slate-200"
                  >
                    <ZoomOut className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => setZoomLevel(Math.min(250, zoomLevel + 20))}
                    className="p-1 bg-slate-800 hover:bg-slate-700 rounded text-slate-200"
                  >
                    <ZoomIn className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => setZoomLevel(100)}
                    className="px-1.5 py-0.5 bg-slate-800 hover:bg-slate-700 rounded text-[10px] text-slate-300 font-mono"
                  >
                    1:1
                  </button>
                </div>
              </div>

              {/* DICOM Tag Information */}
              <div className="space-y-1.5 bg-slate-950/60 p-3 rounded-xl border border-slate-800 font-mono text-[10px] text-slate-400">
                <div className="text-slate-300 font-sans font-bold text-[11px] mb-1">DICOM Header Tags</div>
                <div>(0008,0060) Modality: {appointment.modality.code}</div>
                <div>(0018,0050) Slice Thickness: 1.0 mm</div>
                <div>(0018,0060) kVp: {appointment.doseLog?.kvp || 120}</div>
                <div>(0018,1150) Exposure Time: 450 ms</div>
                <div>(0028,0010) Rows / Columns: 512 x 512</div>
                <div>(0028,0100) Bits Allocated: 16</div>
              </div>
            </div>

            {/* QA Approval Action */}
            <div className="pt-3 border-t border-slate-800 space-y-2">
              <button
                onClick={onSignOff}
                className="w-full flex items-center justify-center space-x-2 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/30 transition-all cursor-pointer"
              >
                <Check className="w-4 h-4" />
                <span>Verify QC & Push to Radiologist</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
