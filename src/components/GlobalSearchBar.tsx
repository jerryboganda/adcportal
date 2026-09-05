import React, { useState, useEffect, useRef, useMemo } from 'react';
import {
  Search,
  X,
  User,
  Activity,
  FileText,
  CreditCard,
  ArrowRight,
  Clock,
  AlertTriangle,
  CheckCircle2,
  Phone,
  Calendar,
  Layers,
  Sparkles,
  Eye,
  CornerDownLeft,
  Tv,
  BadgeAlert,
  Hash,
  ShieldCheck,
  Building2,
  FolderOpen
} from 'lucide-react';
import { Appointment, Patient, ActiveTab, Invoice, InventoryItem } from '../types';
import { Boxes, Droplet, ThermometerSnowflake } from 'lucide-react';

interface GlobalSearchBarProps {
  appointments: Appointment[];
  patients: Patient[];
  invoices?: Invoice[];
  inventoryItems?: InventoryItem[];
  setActiveTab: (tab: ActiveTab) => void;
  onSelectAppointment?: (apt: Appointment) => void;
  onOpenBookingModal?: () => void;
}

export const GlobalSearchBar: React.FC<GlobalSearchBarProps> = ({
  appointments,
  patients,
  invoices = [],
  inventoryItems = [],
  setActiveTab,
  onSelectAppointment,
  onOpenBookingModal,
}) => {
  const [query, setQuery] = useState('');
  const [isOpen, setIsOpen] = useState(false);
  const [activeCategory, setActiveCategory] = useState<'all' | 'appointments' | 'patients' | 'inventory' | 'stat'>('all');
  const [selectedQuickViewApt, setSelectedQuickViewApt] = useState<Appointment | null>(null);
  const [selectedQuickViewPatient, setSelectedQuickViewPatient] = useState<Patient | null>(null);

  const inputRef = useRef<HTMLInputElement>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // Global Keyboard Shortcut: Cmd+K, Ctrl+K or "/"
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // If user presses Cmd+K or Ctrl+K
      if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        inputRef.current?.focus();
        setIsOpen(true);
      } else if (e.key === '/' && document.activeElement?.tagName !== 'INPUT' && document.activeElement?.tagName !== 'TEXTAREA') {
        e.preventDefault();
        inputRef.current?.focus();
        setIsOpen(true);
      } else if (e.key === 'Escape') {
        setIsOpen(false);
        inputRef.current?.blur();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  // Click outside to close dropdown
  useEffect(() => {
    const handleClickOutside = (event: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(event.target as Node)) {
        setIsOpen(false);
      }
    };

    document.addEventListener('mousedown', handleClickOutside);
    return () => document.removeEventListener('mousedown', handleClickOutside);
  }, []);

  // Filter Appointments
  const filteredAppointments = useMemo(() => {
    const cleanQuery = query.trim().toLowerCase();
    if (!cleanQuery) return appointments.slice(0, 5); // Return recent 5 if no query

    return appointments.filter((apt) => {
      const matchToken = apt.tokenNumber.toLowerCase().includes(cleanQuery);
      const matchId = apt.id.toLowerCase().includes(cleanQuery);
      const matchPatientName = apt.patient.name.toLowerCase().includes(cleanQuery);
      const matchMRN = apt.patient.mrn.toLowerCase().includes(cleanQuery);
      const matchPhone = (apt.patient.phone || '').toLowerCase().includes(cleanQuery);
      const matchService = apt.service.name.toLowerCase().includes(cleanQuery) || apt.service.code.toLowerCase().includes(cleanQuery);
      const matchModality = apt.modality.name.toLowerCase().includes(cleanQuery) || apt.modality.code.toLowerCase().includes(cleanQuery);
      const matchState = apt.workflowState.toLowerCase().includes(cleanQuery);
      const matchPriority = apt.priority.toLowerCase().includes(cleanQuery);
      const matchDoctor = (apt.referrer?.name || '').toLowerCase().includes(cleanQuery);

      return (
        matchToken ||
        matchId ||
        matchPatientName ||
        matchMRN ||
        matchPhone ||
        matchService ||
        matchModality ||
        matchState ||
        matchPriority ||
        matchDoctor
      );
    });
  }, [appointments, query]);

  // Filter Patients
  const filteredPatients = useMemo(() => {
    const cleanQuery = query.trim().toLowerCase();
    if (!cleanQuery) return patients.slice(0, 4);

    return patients.filter((pat) => {
      const matchName = pat.name.toLowerCase().includes(cleanQuery);
      const matchMRN = pat.mrn.toLowerCase().includes(cleanQuery);
      const matchPhone = (pat.phone || '').toLowerCase().includes(cleanQuery);
      const matchEmail = (pat.email || '').toLowerCase().includes(cleanQuery);
      const matchId = pat.id.toLowerCase().includes(cleanQuery);
      const matchBlood = (pat.bloodGroup || '').toLowerCase().includes(cleanQuery);

      return matchName || matchMRN || matchPhone || matchEmail || matchId || matchBlood;
    });
  }, [patients, query]);

  // Filter Inventory SKUs & Contrast Media
  const filteredInventory = useMemo(() => {
    const cleanQuery = query.trim().toLowerCase();
    if (!cleanQuery) return [];

    return inventoryItems.filter((item) => {
      const matchCode = item.code.toLowerCase().includes(cleanQuery);
      const matchName = item.name.toLowerCase().includes(cleanQuery);
      const matchGeneric = (item.genericName || '').toLowerCase().includes(cleanQuery);
      const matchCategory = item.category.toLowerCase().includes(cleanQuery);
      const matchLocation = (item.storageLocation || '').toLowerCase().includes(cleanQuery);
      const matchSupplier = (item.supplier || '').toLowerCase().includes(cleanQuery);
      const matchBatch = item.batches.some(b => b.batchNumber.toLowerCase().includes(cleanQuery));

      return matchCode || matchName || matchGeneric || matchCategory || matchLocation || matchSupplier || matchBatch;
    });
  }, [inventoryItems, query]);

  // STAT / Critical triage items
  const statAppointments = useMemo(() => {
    return appointments.filter((a) => a.priority === 'stat' || a.priority === 'urgent');
  }, [appointments]);

  const hasSearchQuery = query.trim().length > 0;
  const totalResultsCount = filteredAppointments.length + filteredPatients.length + filteredInventory.length;

  // Navigation Handlers
  const handleOpenInModule = (apt: Appointment, targetTab: ActiveTab) => {
    if (onSelectAppointment) {
      onSelectAppointment(apt);
    }
    setActiveTab(targetTab);
    setIsOpen(false);
  };

  const handleSelectAppointmentDefault = (apt: Appointment) => {
    if (onSelectAppointment) {
      onSelectAppointment(apt);
    }
    // Route smartly based on study state
    if (['reading', 'reported', 'delivered'].includes(apt.workflowState)) {
      setActiveTab('reporting');
    } else if (['preparing', 'in_progress', 'acquired'].includes(apt.workflowState)) {
      setActiveTab('technologist');
    } else {
      setActiveTab('checkin');
    }
    setIsOpen(false);
  };

  const handleSelectPatientDefault = (patient: Patient) => {
    const patientApt = appointments.find((a) => a.patientId === patient.id);
    if (patientApt && onSelectAppointment) {
      onSelectAppointment(patientApt);
      if (['reading', 'reported', 'delivered'].includes(patientApt.workflowState)) {
        setActiveTab('reporting');
      } else {
        setActiveTab('checkin');
      }
    } else {
      setActiveTab('checkin');
    }
    setIsOpen(false);
  };

  const getStateBadge = (state: string) => {
    switch (state) {
      case 'booked':
        return <span className="bg-slate-100 text-slate-700 border border-slate-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">Booked</span>;
      case 'checked_in':
        return <span className="bg-blue-50 text-blue-700 border border-blue-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">Checked In</span>;
      case 'preparing':
        return <span className="bg-amber-50 text-amber-700 border border-amber-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">Preparing</span>;
      case 'in_progress':
        return <span className="bg-cyan-50 text-cyan-700 border border-cyan-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold animate-pulse">Acquiring</span>;
      case 'acquired':
        return <span className="bg-indigo-50 text-indigo-700 border border-indigo-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">Acquired</span>;
      case 'reading':
        return <span className="bg-purple-50 text-purple-700 border border-purple-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">In Reading</span>;
      case 'reported':
        return <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">Reported</span>;
      case 'delivered':
        return <span className="bg-teal-50 text-teal-700 border border-teal-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">Dispatched</span>;
      case 'cancelled':
        return <span className="bg-rose-50 text-rose-700 border border-rose-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">Cancelled</span>;
      case 'no_show':
        return <span className="bg-slate-100 text-slate-500 border border-slate-200 px-1.5 py-0.5 rounded-sm text-[10px] font-semibold">No-Show</span>;
      default:
        return <span className="bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-sm text-[10px]">{state}</span>;
    }
  };

  return (
    <div ref={containerRef} className="relative flex-1 max-w-xl mx-2 sm:mx-4">
      {/* Search Input Bar */}
      <div className="relative flex items-center">
        <div className="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400">
          <Search className="w-4 h-4 text-cyan-600" />
        </div>
        <input
          ref={inputRef}
          type="text"
          value={query}
          onChange={(e) => {
            setQuery(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          placeholder="Search by Patient Name, MRN, Token (DX-01), ID..."
          className="w-full pl-9 pr-20 py-1.5 bg-slate-100/90 hover:bg-slate-100 focus:bg-white text-slate-900 placeholder:text-slate-400 border border-slate-200 focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20 rounded-lg text-xs transition-all outline-hidden font-medium"
        />

        {/* Right side controls (Clear & Shortcut Badge) */}
        <div className="absolute inset-y-0 right-0 pr-2 flex items-center space-x-1">
          {query ? (
            <button
              onClick={() => {
                setQuery('');
                inputRef.current?.focus();
              }}
              className="p-1 text-slate-400 hover:text-slate-600 rounded-md hover:bg-slate-200 transition-colors"
              title="Clear search"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          ) : (
            <div className="hidden sm:flex items-center space-x-1 px-1.5 py-0.5 rounded border border-slate-200 bg-white text-[10px] font-mono text-slate-400 select-none shadow-2xs">
              <span className="text-[11px]">⌘</span>
              <span>K</span>
            </div>
          )}
        </div>
      </div>

      {/* Global Results Dropdown */}
      {isOpen && (
        <div className="absolute left-0 right-0 mt-1.5 bg-white rounded-xl shadow-2xl border border-slate-200 z-50 overflow-hidden flex flex-col max-h-[80vh] sm:max-h-[580px] animate-in fade-in slide-in-from-top-1 duration-150">
          {/* Filter Tabs Header */}
          <div className="flex items-center justify-between px-3 py-2 bg-slate-50/90 border-b border-slate-200 text-xs">
            <div className="flex items-center space-x-1 overflow-x-auto scrollbar-none">
              <button
                onClick={() => setActiveCategory('all')}
                className={`px-2.5 py-1 rounded-md font-medium text-xs transition-colors cursor-pointer ${
                  activeCategory === 'all'
                    ? 'bg-cyan-600 text-white shadow-xs'
                    : 'text-slate-600 hover:bg-slate-200/70'
                }`}
              >
                All Results ({totalResultsCount})
              </button>
              <button
                onClick={() => setActiveCategory('appointments')}
                className={`px-2.5 py-1 rounded-md font-medium text-xs transition-colors cursor-pointer flex items-center space-x-1 ${
                  activeCategory === 'appointments'
                    ? 'bg-cyan-600 text-white shadow-xs'
                    : 'text-slate-600 hover:bg-slate-200/70'
                }`}
              >
                <span>Studies</span>
                <span className="text-[10px] opacity-80">({filteredAppointments.length})</span>
              </button>
              <button
                onClick={() => setActiveCategory('patients')}
                className={`px-2.5 py-1 rounded-md font-medium text-xs transition-colors cursor-pointer flex items-center space-x-1 ${
                  activeCategory === 'patients'
                    ? 'bg-cyan-600 text-white shadow-xs'
                    : 'text-slate-600 hover:bg-slate-200/70'
                }`}
              >
                <span>Patients</span>
                <span className="text-[10px] opacity-80">({filteredPatients.length})</span>
              </button>
              {filteredInventory.length > 0 && (
                <button
                  onClick={() => setActiveCategory('inventory')}
                  className={`px-2.5 py-1 rounded-md font-medium text-xs transition-colors cursor-pointer flex items-center space-x-1 ${
                    activeCategory === 'inventory'
                      ? 'bg-amber-600 text-white shadow-xs'
                      : 'text-amber-700 hover:bg-amber-50'
                  }`}
                >
                  <Boxes className="w-3 h-3" />
                  <span>Consumables & Contrast</span>
                  <span className="text-[10px] opacity-80">({filteredInventory.length})</span>
                </button>
              )}
              {statAppointments.length > 0 && (
                <button
                  onClick={() => setActiveCategory('stat')}
                  className={`px-2 py-1 rounded-md font-bold text-xs transition-colors cursor-pointer flex items-center space-x-1 ${
                    activeCategory === 'stat'
                      ? 'bg-rose-600 text-white shadow-xs'
                      : 'text-rose-600 hover:bg-rose-50'
                  }`}
                >
                  <AlertTriangle className="w-3 h-3" />
                  <span>STAT / Urgent ({statAppointments.length})</span>
                </button>
              )}
            </div>

            <div className="hidden md:flex items-center text-[10px] text-slate-400 space-x-2">
              <span className="flex items-center gap-1">
                <CornerDownLeft className="w-3 h-3 text-slate-400" /> Click to jump
              </span>
            </div>
          </div>

          {/* Results Content Area */}
          <div className="overflow-y-auto flex-1 p-2 space-y-3 divide-y divide-slate-100">
            {/* No query recent prompt */}
            {!hasSearchQuery && activeCategory === 'all' && (
              <div className="p-2 space-y-2">
                <div className="flex items-center justify-between text-[11px] font-bold text-slate-400 uppercase tracking-wider px-1">
                  <span className="flex items-center gap-1.5">
                    <Sparkles className="w-3 h-3 text-cyan-600" /> Quick Worklist & Active Queue
                  </span>
                  <span>Press ESC to close</span>
                </div>
              </div>
            )}

            {/* Empty State */}
            {hasSearchQuery && filteredAppointments.length === 0 && filteredPatients.length === 0 && filteredInventory.length === 0 && (
              <div className="text-center py-10 px-4">
                <div className="w-12 h-12 rounded-xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
                  <Search className="w-6 h-6" />
                </div>
                <h4 className="text-sm font-bold text-slate-800">No matching records found</h4>
                <p className="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                  No patient, MRN, token, appointment, or consumable SKU matched <span className="font-semibold text-slate-700">"{query}"</span>.
                </p>
                {onOpenBookingModal && (
                  <button
                    onClick={() => {
                      setIsOpen(false);
                      onOpenBookingModal();
                    }}
                    className="mt-4 inline-flex items-center space-x-1.5 px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                  >
                    <span>+ Book New Patient & Study</span>
                  </button>
                )}
              </div>
            )}

            {/* APPOINTMENTS / STUDIES SECTION */}
            {(activeCategory === 'all' || activeCategory === 'appointments' || activeCategory === 'stat') && (
              (activeCategory === 'stat' ? statAppointments : filteredAppointments).length > 0 && (
                <div className="pt-2">
                  <div className="flex items-center justify-between text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 mb-1.5">
                    <span className="flex items-center gap-1.5">
                      <Activity className="w-3.5 h-3.5 text-cyan-600" />
                      {activeCategory === 'stat' ? 'High Priority Triage' : 'Diagnostic Studies & Appointments'}
                    </span>
                    <span className="text-[10px] text-slate-400 font-normal">
                      {(activeCategory === 'stat' ? statAppointments : filteredAppointments).length} found
                    </span>
                  </div>

                  <div className="space-y-1">
                    {(activeCategory === 'stat' ? statAppointments : filteredAppointments).map((apt) => {
                      const isStat = apt.priority === 'stat';
                      const isUrgent = apt.priority === 'urgent';

                      return (
                        <div
                          key={apt.id}
                          className="group relative p-2.5 rounded-lg hover:bg-slate-50 border border-transparent hover:border-slate-200 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-2 cursor-pointer"
                          onClick={() => handleSelectAppointmentDefault(apt)}
                        >
                          {/* Left: Token & Modality Swatch */}
                          <div className="flex items-start space-x-3">
                            <div
                              className="shrink-0 w-12 h-11 rounded-lg flex flex-col items-center justify-center font-bold shadow-2xs border text-white"
                              style={{ backgroundColor: apt.modality.color, borderColor: `${apt.modality.color}88` }}
                            >
                              <span className="text-[10px] uppercase tracking-tighter opacity-90">{apt.modality.code}</span>
                              <span className="text-xs font-mono font-black">{apt.tokenNumber}</span>
                            </div>

                            <div className="min-w-0">
                              <div className="flex items-center space-x-2 flex-wrap gap-y-0.5">
                                <span className="font-bold text-xs text-slate-900 group-hover:text-cyan-700 transition-colors">
                                  {apt.patient.name}
                                </span>
                                <span className="font-mono text-[11px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded-sm">
                                  {apt.patient.mrn}
                                </span>
                                {isStat && (
                                  <span className="bg-rose-100 text-rose-800 text-[10px] font-black px-1.5 py-0.2 rounded-sm border border-rose-200 animate-pulse">
                                    STAT ALERT
                                  </span>
                                )}
                                {isUrgent && (
                                  <span className="bg-amber-100 text-amber-800 text-[10px] font-bold px-1.5 py-0.2 rounded-sm border border-amber-200">
                                    URGENT
                                  </span>
                                )}
                              </div>

                              <div className="text-xs text-slate-600 font-medium truncate mt-0.5">
                                {apt.service.name}
                                <span className="text-[10px] text-slate-400 font-mono ml-1.5">({apt.service.code})</span>
                              </div>

                              <div className="flex items-center space-x-3 text-[11px] text-slate-400 mt-1">
                                <span className="flex items-center gap-1">
                                  <Clock className="w-3 h-3" /> {apt.time} ({apt.date})
                                </span>
                                <span>•</span>
                                <span>{apt.roomNumber}</span>
                                {apt.patient.phone && (
                                  <>
                                    <span>•</span>
                                    <span className="flex items-center gap-1 font-mono">
                                      <Phone className="w-2.5 h-2.5" /> {apt.patient.phone}
                                    </span>
                                  </>
                                )}
                              </div>
                            </div>
                          </div>

                          {/* Right: Status & Quick Action Buttons */}
                          <div className="flex items-center justify-between sm:justify-end space-x-2 shrink-0 pt-1 sm:pt-0 border-t sm:border-t-0 border-slate-100">
                            <div>{getStateBadge(apt.workflowState)}</div>

                            {/* Quick Action Navigation Buttons */}
                            <div className="flex items-center space-x-1 opacity-90 group-hover:opacity-100">
                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  setSelectedQuickViewApt(apt);
                                }}
                                className="p-1.5 text-slate-400 hover:text-slate-700 hover:bg-slate-200 rounded-md transition-colors"
                                title="Quick Preview Drawer"
                              >
                                <Eye className="w-3.5 h-3.5" />
                              </button>

                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleOpenInModule(apt, 'reporting');
                                }}
                                className="px-2 py-1 bg-purple-50 hover:bg-purple-100 text-purple-700 border border-purple-200 rounded-md text-[11px] font-semibold transition-colors flex items-center space-x-1"
                                title="Open in Radiology Reports"
                              >
                                <FileText className="w-3 h-3" />
                                <span className="hidden md:inline">Report</span>
                              </button>

                              <button
                                onClick={(e) => {
                                  e.stopPropagation();
                                  handleOpenInModule(apt, 'checkin');
                                }}
                                className="p-1.5 text-cyan-600 hover:bg-cyan-50 rounded-md transition-colors"
                                title="Jump to Reception Desk"
                              >
                                <ArrowRight className="w-3.5 h-3.5" />
                              </button>
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </div>
              )
            )}

            {/* PATIENTS DIRECTORY SECTION */}
            {(activeCategory === 'all' || activeCategory === 'patients') && filteredPatients.length > 0 && (
              <div className="pt-2">
                <div className="flex items-center justify-between text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 mb-1.5">
                  <span className="flex items-center gap-1.5">
                    <User className="w-3.5 h-3.5 text-indigo-600" /> Patients Directory
                  </span>
                  <span className="text-[10px] text-slate-400 font-normal">
                    {filteredPatients.length} found
                  </span>
                </div>

                <div className="space-y-1">
                  {filteredPatients.map((pat) => {
                    const patStudies = appointments.filter((a) => a.patientId === pat.id);

                    return (
                      <div
                        key={pat.id}
                        className="group p-2.5 rounded-lg hover:bg-indigo-50/40 border border-transparent hover:border-indigo-100 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-2 cursor-pointer"
                        onClick={() => handleSelectPatientDefault(pat)}
                      >
                        <div className="flex items-center space-x-3">
                          <div className="w-10 h-10 rounded-lg bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-xs uppercase shrink-0 border border-indigo-200">
                            {pat.name.split(' ').map((n) => n[0]).join('').slice(0, 2)}
                          </div>
                          <div>
                            <div className="flex items-center space-x-2">
                              <span className="font-bold text-xs text-slate-900 group-hover:text-indigo-700 transition-colors">
                                {pat.name}
                              </span>
                              <span className="font-mono text-[11px] font-semibold text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded-sm">
                                {pat.mrn}
                              </span>
                              <span className="text-[10px] text-slate-500 capitalize">
                                {pat.gender}, {pat.age}y
                              </span>
                            </div>
                            <div className="flex items-center space-x-3 text-[11px] text-slate-500 mt-0.5">
                              {pat.phone && (
                                <span className="flex items-center gap-1 font-mono">
                                  <Phone className="w-2.5 h-2.5 text-slate-400" /> {pat.phone}
                                </span>
                              )}
                              {pat.bloodGroup && (
                                <span className="bg-rose-50 text-rose-700 px-1 rounded-sm text-[10px] font-bold">
                                  {pat.bloodGroup}
                                </span>
                              )}
                              <span className="text-indigo-600 font-medium">
                                {patStudies.length} {patStudies.length === 1 ? 'study' : 'studies'} recorded
                              </span>
                            </div>
                          </div>
                        </div>

                        <div className="flex items-center space-x-1.5 self-end sm:self-center">
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              setSelectedQuickViewPatient(pat);
                            }}
                            className="p-1.5 text-slate-400 hover:text-indigo-700 hover:bg-indigo-100/60 rounded-md transition-colors"
                            title="View Patient Summary"
                          >
                            <Eye className="w-3.5 h-3.5" />
                          </button>
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              handleSelectPatientDefault(pat);
                            }}
                            className="px-2.5 py-1 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-md text-[11px] font-semibold transition-colors flex items-center space-x-1"
                          >
                            <span>Open in Portal</span>
                            <ArrowRight className="w-3 h-3" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}

            {/* INVENTORY & CONTRAST MEDIA SECTION */}
            {(activeCategory === 'all' || activeCategory === 'inventory') && filteredInventory.length > 0 && (
              <div className="pt-2">
                <div className="flex items-center justify-between text-[11px] font-bold text-slate-500 uppercase tracking-wider px-2 mb-1.5">
                  <span className="flex items-center gap-1.5">
                    <Boxes className="w-3.5 h-3.5 text-amber-600" />
                    Contrast Media & Consumables Stock
                  </span>
                  <span className="text-[10px] text-slate-400 font-normal">
                    {filteredInventory.length} found
                  </span>
                </div>

                <div className="space-y-1">
                  {filteredInventory.map((item) => {
                    const isLow = item.currentStock <= item.minThreshold;
                    const isContrast = item.category === 'contrast_ct' || item.category === 'contrast_mri';

                    return (
                      <div
                        key={item.id}
                        className="group p-2.5 rounded-lg hover:bg-amber-50/40 border border-transparent hover:border-amber-200 transition-all flex flex-col sm:flex-row sm:items-center justify-between gap-2 cursor-pointer"
                        onClick={() => {
                          setActiveTab('inventory');
                          setIsOpen(false);
                        }}
                      >
                        <div className="flex items-center space-x-3">
                          <div className={`w-10 h-10 rounded-lg flex items-center justify-center font-bold text-xs shrink-0 border ${
                            isContrast
                              ? 'bg-cyan-50 text-cyan-700 border-cyan-200'
                              : 'bg-amber-50 text-amber-700 border-amber-200'
                          }`}>
                            {isContrast ? <Droplet className="w-5 h-5" /> : <Boxes className="w-5 h-5" />}
                          </div>
                          <div>
                            <div className="flex items-center space-x-2">
                              <span className="font-bold text-xs text-slate-900 group-hover:text-amber-800 transition-colors">
                                {item.name}
                              </span>
                              <span className="font-mono text-[10px] font-bold text-slate-500 bg-slate-100 px-1.5 py-0.2 rounded-sm border border-slate-200">
                                {item.code}
                              </span>
                              {isLow && (
                                <span className="bg-amber-100 text-amber-800 border border-amber-200 px-1.5 py-0.2 rounded-sm text-[10px] font-bold animate-pulse">
                                  Low Stock
                                </span>
                              )}
                              {item.requiresColdChain && (
                                <span className="text-[10px] text-cyan-800 bg-cyan-50 border border-cyan-100 px-1.5 py-0.2 rounded-sm flex items-center gap-0.5">
                                  <ThermometerSnowflake className="w-2.5 h-2.5" />
                                  Cold Chain
                                </span>
                              )}
                            </div>
                            <div className="flex items-center space-x-3 text-[11px] text-slate-500 mt-0.5">
                              <span>Loc: <strong className="text-slate-700">{item.storageLocation}</strong></span>
                              <span>•</span>
                              <span>Lot: <strong className="font-mono text-slate-700">{item.batches[0]?.batchNumber || 'N/A'}</strong></span>
                              <span>•</span>
                              <span>Price: <strong className="text-slate-900">PKR {item.sellingPrice.toLocaleString()}</strong></span>
                            </div>
                          </div>
                        </div>

                        <div className="flex items-center space-x-2 self-end sm:self-center">
                          <div className="text-right">
                            <span className="font-mono font-bold text-sm text-slate-900 block leading-tight">
                              {item.currentStock} {item.unit}
                            </span>
                            <span className="text-[10px] text-slate-400">
                              Min: {item.minThreshold}
                            </span>
                          </div>
                          <button
                            onClick={(e) => {
                              e.stopPropagation();
                              setActiveTab('inventory');
                              setIsOpen(false);
                            }}
                            className="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 rounded-md text-[11px] font-semibold transition-colors flex items-center space-x-1"
                          >
                            <span>Manage Stock</span>
                            <ArrowRight className="w-3 h-3" />
                          </button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              </div>
            )}
          </div>

          {/* Quick Footer Action Bar */}
          <div className="p-2.5 bg-slate-50 border-t border-slate-200 flex items-center justify-between text-xs">
            <div className="flex items-center space-x-2 text-slate-500 text-[11px]">
              <span>Quick jump:</span>
              <button
                onClick={() => {
                  setActiveTab('checkin');
                  setIsOpen(false);
                }}
                className="hover:text-cyan-700 hover:underline cursor-pointer"
              >
                Reception Desk
              </button>
              <span>•</span>
              <button
                onClick={() => {
                  setActiveTab('reporting');
                  setIsOpen(false);
                }}
                className="hover:text-purple-700 hover:underline cursor-pointer"
              >
                Reporting Workstation
              </button>
              <span>•</span>
              <button
                onClick={() => {
                  setActiveTab('billing');
                  setIsOpen(false);
                }}
                className="hover:text-indigo-700 hover:underline cursor-pointer"
              >
                Invoices & POS
              </button>
            </div>

            {onOpenBookingModal && (
              <button
                onClick={() => {
                  setIsOpen(false);
                  onOpenBookingModal();
                }}
                className="text-cyan-700 hover:text-cyan-800 font-bold hover:underline cursor-pointer flex items-center gap-1"
              >
                <span>+ New Booking</span>
              </button>
            )}
          </div>
        </div>
      )}

      {/* QUICK VIEW / SNAPSHOT MODAL FOR APPOINTMENT */}
      {selectedQuickViewApt && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-2xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
            {/* Modal Header */}
            <div className="p-4 border-b border-slate-200 flex items-center justify-between bg-slate-50">
              <div className="flex items-center space-x-3">
                <div
                  className="w-10 h-10 rounded-xl flex items-center justify-center font-bold text-white shadow-sm"
                  style={{ backgroundColor: selectedQuickViewApt.modality.color }}
                >
                  <span className="font-mono text-sm">{selectedQuickViewApt.tokenNumber}</span>
                </div>
                <div>
                  <div className="flex items-center space-x-2">
                    <h3 className="text-sm font-bold text-slate-900">{selectedQuickViewApt.patient.name}</h3>
                    <span className="font-mono text-xs font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-md border border-slate-200">
                      {selectedQuickViewApt.patient.mrn}
                    </span>
                  </div>
                  <p className="text-xs text-slate-500">
                    {selectedQuickViewApt.service.name} • {selectedQuickViewApt.modality.name}
                  </p>
                </div>
              </div>
              <button
                onClick={() => setSelectedQuickViewApt(null)}
                className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            {/* Modal Body Details */}
            <div className="p-5 space-y-4 text-xs overflow-y-auto max-h-[70vh]">
              {/* Status & Priority Grid */}
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200">
                <div>
                  <span className="text-slate-400 text-[10px] block">Workflow Status</span>
                  <div className="mt-0.5">{getStateBadge(selectedQuickViewApt.workflowState)}</div>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block">Priority Tier</span>
                  <span className="font-bold text-slate-800 capitalize mt-0.5 block">
                    {selectedQuickViewApt.priority}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block">Scheduled Time</span>
                  <span className="font-bold text-slate-800 mt-0.5 block">
                    {selectedQuickViewApt.time}
                  </span>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block">Assigned Suite</span>
                  <span className="font-bold text-slate-800 mt-0.5 block">
                    {selectedQuickViewApt.roomNumber}
                  </span>
                </div>
              </div>

              {/* Patient Demographics */}
              <div className="border border-slate-200 rounded-xl p-3.5 space-y-2">
                <h4 className="font-bold text-slate-800 text-xs flex items-center gap-1.5">
                  <User className="w-3.5 h-3.5 text-cyan-600" /> Patient Demographics & Contact
                </h4>
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-2 text-slate-600">
                  <div>
                    <span className="text-slate-400 text-[10px] block">Age & Gender:</span>
                    <span>{selectedQuickViewApt.patient.age} years, {selectedQuickViewApt.patient.gender}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 text-[10px] block">Contact Phone:</span>
                    <span className="font-mono">{selectedQuickViewApt.patient.phone || 'N/A'}</span>
                  </div>
                  <div>
                    <span className="text-slate-400 text-[10px] block">Blood Group:</span>
                    <span className="font-semibold">{selectedQuickViewApt.patient.bloodGroup || 'Unknown'}</span>
                  </div>
                  {selectedQuickViewApt.patient.allergies && (
                    <div className="col-span-full bg-rose-50 border border-rose-200 p-2 rounded-md text-rose-800">
                      <span className="font-bold block text-[10px]">Allergies Recorded:</span>
                      {selectedQuickViewApt.patient.allergies}
                    </div>
                  )}
                </div>
              </div>

              {/* Radiation Dose Log if acquired */}
              {selectedQuickViewApt.doseLog && (
                <div className="border border-cyan-200 bg-cyan-50/50 rounded-xl p-3.5 space-y-1.5">
                  <h4 className="font-bold text-cyan-900 text-xs flex items-center gap-1.5">
                    <ShieldCheck className="w-3.5 h-3.5 text-cyan-600" /> Radiation Dose & Acquisition Record
                  </h4>
                  <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-cyan-900 font-mono text-[11px]">
                    <div>
                      <span className="text-cyan-700 text-[10px] block font-sans">Dose Value:</span>
                      {selectedQuickViewApt.doseLog.doseValue} {selectedQuickViewApt.doseLog.doseUnit}
                    </div>
                    {selectedQuickViewApt.doseLog.dlpValue && (
                      <div>
                        <span className="text-cyan-700 text-[10px] block font-sans">DLP Total:</span>
                        {selectedQuickViewApt.doseLog.dlpValue} mGy*cm
                      </div>
                    )}
                    <div>
                      <span className="text-cyan-700 text-[10px] block font-sans">Acquired By:</span>
                      <span className="font-sans">{selectedQuickViewApt.doseLog.recordedBy}</span>
                    </div>
                    <div>
                      <span className="text-cyan-700 text-[10px] block font-sans">QC Verification:</span>
                      <span className="text-emerald-700 font-bold font-sans">✓ Verified</span>
                    </div>
                  </div>
                </div>
              )}

              {/* Diagnostic Report Preview if available */}
              {selectedQuickViewApt.report && (
                <div className="border border-purple-200 bg-purple-50/40 rounded-xl p-3.5 space-y-2">
                  <div className="flex items-center justify-between">
                    <h4 className="font-bold text-purple-900 text-xs flex items-center gap-1.5">
                      <FileText className="w-3.5 h-3.5 text-purple-600" /> Radiology Diagnostic Report
                    </h4>
                    <span className="text-[10px] bg-purple-100 text-purple-800 font-bold px-2 py-0.5 rounded-sm">
                      {selectedQuickViewApt.report.type.toUpperCase()}
                    </span>
                  </div>
                  {selectedQuickViewApt.report.impression && (
                    <div className="bg-white p-2.5 rounded-lg border border-purple-100 text-slate-800">
                      <span className="font-bold text-[10px] text-purple-900 block uppercase">Impression / Conclusion:</span>
                      <p className="mt-0.5 leading-relaxed">{selectedQuickViewApt.report.impression}</p>
                    </div>
                  )}
                  <div className="text-[11px] text-slate-500 flex justify-between">
                    <span>Authored by: {selectedQuickViewApt.report.authoredBy}</span>
                    {selectedQuickViewApt.report.signedAt && (
                      <span>Signed: {selectedQuickViewApt.report.signedAt}</span>
                    )}
                  </div>
                </div>
              )}
            </div>

            {/* Modal Footer Routing Actions */}
            <div className="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
              <button
                onClick={() => setSelectedQuickViewApt(null)}
                className="px-3 py-1.5 text-slate-600 hover:text-slate-800 font-medium cursor-pointer"
              >
                Close Preview
              </button>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => {
                    const targetApt = selectedQuickViewApt;
                    setSelectedQuickViewApt(null);
                    handleOpenInModule(targetApt, 'checkin');
                  }}
                  className="px-3 py-1.5 bg-slate-200 hover:bg-slate-300 text-slate-800 rounded-lg font-semibold transition-colors cursor-pointer"
                >
                  Reception Desk
                </button>
                <button
                  onClick={() => {
                    const targetApt = selectedQuickViewApt;
                    setSelectedQuickViewApt(null);
                    handleOpenInModule(targetApt, 'reporting');
                  }}
                  className="px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg font-semibold shadow-sm transition-colors cursor-pointer flex items-center space-x-1.5"
                >
                  <span>Open Full Workstation</span>
                  <ArrowRight className="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* QUICK VIEW / SNAPSHOT MODAL FOR PATIENT */}
      {selectedQuickViewPatient && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-xl w-full shadow-2xl border border-slate-200 overflow-hidden flex flex-col animate-in fade-in zoom-in-95 duration-150">
            <div className="p-4 border-b border-slate-200 flex items-center justify-between bg-indigo-50/50">
              <div className="flex items-center space-x-3">
                <div className="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-sm">
                  {selectedQuickViewPatient.name.split(' ').map((n) => n[0]).join('').slice(0, 2)}
                </div>
                <div>
                  <h3 className="text-sm font-bold text-slate-900">{selectedQuickViewPatient.name}</h3>
                  <span className="font-mono text-xs font-semibold text-slate-500">
                    {selectedQuickViewPatient.mrn} • {selectedQuickViewPatient.gender}, {selectedQuickViewPatient.age}y
                  </span>
                </div>
              </div>
              <button
                onClick={() => setSelectedQuickViewPatient(null)}
                className="p-1.5 text-slate-400 hover:text-slate-600 rounded-lg hover:bg-slate-200"
              >
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="p-5 space-y-4 text-xs overflow-y-auto max-h-[70vh]">
              {/* Contact Info */}
              <div className="grid grid-cols-2 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-200 text-slate-700">
                <div>
                  <span className="text-slate-400 text-[10px] block">Phone:</span>
                  <span className="font-mono">{selectedQuickViewPatient.phone || 'N/A'}</span>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block">Email Address:</span>
                  <span>{selectedQuickViewPatient.email || 'N/A'}</span>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block">Date of Birth:</span>
                  <span>{selectedQuickViewPatient.dob || 'N/A'}</span>
                </div>
                <div>
                  <span className="text-slate-400 text-[10px] block">Blood Group:</span>
                  <span className="font-bold text-rose-700">{selectedQuickViewPatient.bloodGroup || 'N/A'}</span>
                </div>
              </div>

              {/* Patient Studies History */}
              <div className="space-y-2">
                <h4 className="font-bold text-slate-800 text-xs">Diagnostic History ({appointments.filter(a => a.patientId === selectedQuickViewPatient.id).length} studies)</h4>
                <div className="space-y-1.5 max-h-48 overflow-y-auto">
                  {appointments.filter(a => a.patientId === selectedQuickViewPatient.id).map(a => (
                    <div
                      key={a.id}
                      onClick={() => {
                        const apt = a;
                        setSelectedQuickViewPatient(null);
                        handleOpenInModule(apt, 'reporting');
                      }}
                      className="p-2 rounded-lg bg-slate-50 hover:bg-indigo-50 border border-slate-200 transition-colors flex items-center justify-between cursor-pointer"
                    >
                      <div>
                        <div className="font-bold text-slate-900">{a.service.name}</div>
                        <div className="text-[10px] text-slate-500 font-mono">{a.tokenNumber} • {a.date}</div>
                      </div>
                      <div>{getStateBadge(a.workflowState)}</div>
                    </div>
                  ))}
                </div>
              </div>
            </div>

            <div className="p-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
              <button
                onClick={() => setSelectedQuickViewPatient(null)}
                className="px-3 py-1.5 text-slate-600 hover:text-slate-800 font-medium cursor-pointer"
              >
                Close
              </button>
              <button
                onClick={() => {
                  const pat = selectedQuickViewPatient;
                  setSelectedQuickViewPatient(null);
                  handleSelectPatientDefault(pat);
                }}
                className="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold shadow-sm transition-colors cursor-pointer flex items-center space-x-1.5"
              >
                <span>Open in Patient Portal</span>
                <ArrowRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
