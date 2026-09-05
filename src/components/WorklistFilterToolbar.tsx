import React, { useState } from 'react';
import {
  Search,
  Calendar,
  Filter,
  ArrowUpDown,
  ArrowUp,
  ArrowDown,
  X,
  RotateCcw,
  SlidersHorizontal,
  Layers,
  Flame,
  ShieldCheck,
  CreditCard,
  ChevronDown,
  ChevronUp,
  Clock,
  Sparkles,
} from 'lucide-react';
import { AdvancedFilterState, SortField, getDateRangeForPreset } from '../utils/tableUtils';
import { Modality, Priority, WorkflowState } from '../types';

interface WorklistFilterToolbarProps {
  filters: AdvancedFilterState;
  onFilterChange: (newFilters: AdvancedFilterState) => void;
  sortFields: SortField[];
  onSortChange: (newSort: SortField[]) => void;
  modalities?: Modality[];
  availableStatuses?: { value: WorkflowState; label: string; count?: number }[];
  totalCount: number;
  filteredCount: number;
  showBillingFilter?: boolean;
  showScreeningFilter?: boolean;
  variantTitle?: string;
}

export const WorklistFilterToolbar: React.FC<WorklistFilterToolbarProps> = ({
  filters,
  onFilterChange,
  sortFields,
  onSortChange,
  modalities = [
    { id: '1', code: 'DX', name: 'Digital X-Ray', color: '#0284c7' },
    { id: '2', code: 'US', name: 'Ultrasound', color: '#059669' },
    { id: '3', code: 'CT', name: 'CT Scan', color: '#d97706' },
    { id: '4', code: 'MR', name: 'MRI 1.5T', color: '#7c3aed' },
    { id: '5', code: 'MG', name: 'Mammography', color: '#db2777' },
  ],
  availableStatuses,
  totalCount,
  filteredCount,
  showBillingFilter = false,
  showScreeningFilter = true,
  variantTitle = 'Worklist',
}) => {
  const [isAdvancedExpanded, setIsAdvancedExpanded] = useState(false);

  // Helper to toggle a modality in filter
  const toggleModality = (code: string) => {
    const next = filters.modalities.includes(code)
      ? filters.modalities.filter((m) => m !== code)
      : [...filters.modalities, code];
    onFilterChange({ ...filters, modalities: next });
  };

  // Helper to toggle a priority
  const togglePriority = (prio: Priority) => {
    const next = filters.priorities.includes(prio)
      ? filters.priorities.filter((p) => p !== prio)
      : [...filters.priorities, prio];
    onFilterChange({ ...filters, priorities: next });
  };

  // Helper to toggle a status
  const toggleStatus = (status: WorkflowState) => {
    const next = filters.statuses.includes(status)
      ? filters.statuses.filter((s) => s !== status)
      : [...filters.statuses, status];
    onFilterChange({ ...filters, statuses: next });
  };

  // Date range preset selection
  const handleDatePreset = (preset: AdvancedFilterState['dateRangeMode']) => {
    const { startDate, endDate } = getDateRangeForPreset(preset);
    onFilterChange({
      ...filters,
      dateRangeMode: preset,
      startDate: preset === 'all' ? '' : startDate,
      endDate: preset === 'all' ? '' : endDate,
    });
  };

  // Reset all filters to default
  const handleResetFilters = () => {
    const today = new Date().toISOString().split('T')[0];
    onFilterChange({
      search: '',
      dateRangeMode: 'today',
      startDate: today,
      endDate: today,
      modalities: [],
      priorities: [],
      statuses: [],
      screeningStatus: 'all',
      billingStatus: 'all',
    });
    onSortChange([]);
  };

  // Count active non-default filters
  const activeFiltersCount =
    (filters.search ? 1 : 0) +
    (filters.dateRangeMode !== 'today' ? 1 : 0) +
    filters.modalities.length +
    filters.priorities.length +
    filters.statuses.length +
    (filters.screeningStatus !== 'all' ? 1 : 0) +
    (filters.billingStatus !== 'all' ? 1 : 0);

  const removeSortField = (column: string) => {
    onSortChange(sortFields.filter((s) => s.column !== column));
  };

  const getColumnDisplayName = (col: string): string => {
    const names: Record<string, string> = {
      token: 'Token #',
      priority: 'Priority',
      patientName: 'Patient Name',
      mrn: 'MRN',
      age: 'Age',
      modality: 'Modality',
      service: 'Exam / Procedure',
      price: 'Price',
      date: 'Date',
      time: 'Scheduled Time',
      room: 'Room',
      workflowState: 'Workflow Status',
      safety: 'Safety Clearance',
      billing: 'Billing Balance',
      dose: 'Radiation Dose',
    };
    return names[col] || col;
  };

  return (
    <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm space-y-3.5">
      {/* Primary Toolbar: Search + Quick Date Presets + Modalities + Advanced Toggle */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        {/* Search Bar */}
        <div className="relative flex-1 max-w-md">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={filters.search}
            onChange={(e) => onFilterChange({ ...filters, search: e.target.value })}
            placeholder="Search patient name, MRN, token, doctor, procedure..."
            className="w-full bg-slate-50 text-slate-800 pl-9 pr-8 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:bg-white transition-all placeholder:text-slate-400"
          />
          {filters.search && (
            <button
              onClick={() => onFilterChange({ ...filters, search: '' })}
              className="absolute right-2.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 text-xs font-bold p-1 cursor-pointer"
              title="Clear search"
            >
              <X className="w-3.5 h-3.5" />
            </button>
          )}
        </div>

        {/* Date Range Presets */}
        <div className="flex flex-wrap items-center gap-1.5">
          <div className="flex items-center space-x-1 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-semibold">
            <button
              onClick={() => handleDatePreset('today')}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer ${
                filters.dateRangeMode === 'today'
                  ? 'bg-white text-slate-900 shadow-2xs font-bold'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Today
            </button>
            <button
              onClick={() => handleDatePreset('tomorrow')}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer ${
                filters.dateRangeMode === 'tomorrow'
                  ? 'bg-white text-slate-900 shadow-2xs font-bold'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Tomorrow
            </button>
            <button
              onClick={() => handleDatePreset('next7days')}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer ${
                filters.dateRangeMode === 'next7days'
                  ? 'bg-white text-slate-900 shadow-2xs font-bold'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              7 Days
            </button>
            <button
              onClick={() => handleDatePreset('all')}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer ${
                filters.dateRangeMode === 'all'
                  ? 'bg-white text-slate-900 shadow-2xs font-bold'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              All Dates
            </button>
            <button
              onClick={() => onFilterChange({ ...filters, dateRangeMode: 'custom' })}
              className={`px-2.5 py-1 rounded-lg transition-all cursor-pointer flex items-center space-x-1 ${
                filters.dateRangeMode === 'custom'
                  ? 'bg-white text-cyan-800 shadow-2xs font-bold'
                  : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              <Calendar className="w-3 h-3 text-cyan-600" />
              <span>Range</span>
            </button>
          </div>

          {/* Custom Date Range Inputs when in 'custom' or active date mode */}
          {filters.dateRangeMode === 'custom' && (
            <div className="flex items-center space-x-1.5 bg-slate-50 px-2.5 py-1 rounded-xl border border-cyan-300 text-xs shadow-2xs">
              <input
                type="date"
                value={filters.startDate}
                onChange={(e) => onFilterChange({ ...filters, startDate: e.target.value })}
                className="bg-transparent text-slate-800 focus:outline-none cursor-pointer text-xs font-semibold"
              />
              <span className="text-slate-400 font-bold">to</span>
              <input
                type="date"
                value={filters.endDate}
                onChange={(e) => onFilterChange({ ...filters, endDate: e.target.value })}
                className="bg-transparent text-slate-800 focus:outline-none cursor-pointer text-xs font-semibold"
              />
            </div>
          )}

          {/* Toggle Advanced Filters Button */}
          <button
            onClick={() => setIsAdvancedExpanded(!isAdvancedExpanded)}
            className={`flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border transition-all cursor-pointer ${
              isAdvancedExpanded || activeFiltersCount > 0
                ? 'bg-cyan-50 text-cyan-800 border-cyan-300 shadow-2xs'
                : 'bg-slate-100 text-slate-700 hover:bg-slate-200 border-slate-200'
            }`}
          >
            <SlidersHorizontal className="w-3.5 h-3.5 text-cyan-600" />
            <span>Filters</span>
            {activeFiltersCount > 0 && (
              <span className="w-4 h-4 rounded-full bg-cyan-600 text-white text-[10px] flex items-center justify-center font-bold">
                {activeFiltersCount}
              </span>
            )}
            {isAdvancedExpanded ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
          </button>
        </div>
      </div>

      {/* Modality Chips Quick Row */}
      <div className="flex flex-wrap items-center justify-between gap-2 pt-1 border-t border-slate-100">
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Modality:</span>
          <button
            onClick={() => onFilterChange({ ...filters, modalities: [] })}
            className={`px-2.5 py-1 rounded-lg text-xs font-semibold transition-all cursor-pointer ${
              filters.modalities.length === 0
                ? 'bg-slate-900 text-white shadow-xs'
                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
            }`}
          >
            All Modalities
          </button>
          {modalities.map((mod) => {
            const isSelected = filters.modalities.includes(mod.code);
            return (
              <button
                key={mod.id || mod.code}
                onClick={() => toggleModality(mod.code)}
                className={`flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-xs font-bold transition-all cursor-pointer border ${
                  isSelected
                    ? 'bg-slate-900 text-white border-slate-900 shadow-xs'
                    : 'bg-slate-50 text-slate-700 hover:bg-slate-100 border-slate-200'
                }`}
              >
                <span className="w-2 h-2 rounded-full" style={{ backgroundColor: mod.color }} />
                <span>{mod.code}</span>
              </button>
            );
          })}
        </div>

        {/* Priority Quick Badges */}
        <div className="flex items-center gap-1.5">
          <span className="text-[11px] font-bold text-slate-400 uppercase tracking-wider mr-1">Priority:</span>
          {(['routine', 'urgent', 'stat'] as Priority[]).map((prio) => {
            const isSelected = filters.priorities.includes(prio);
            return (
              <button
                key={prio}
                onClick={() => togglePriority(prio)}
                className={`px-2 py-0.5 rounded-lg text-xs font-bold uppercase transition-all cursor-pointer border ${
                  isSelected
                    ? prio === 'stat'
                      ? 'bg-rose-600 text-white border-rose-600 shadow-xs'
                      : prio === 'urgent'
                      ? 'bg-amber-500 text-white border-amber-500 shadow-xs'
                      : 'bg-slate-800 text-white border-slate-800 shadow-xs'
                    : prio === 'stat'
                    ? 'bg-rose-50 text-rose-700 border-rose-200 hover:bg-rose-100'
                    : prio === 'urgent'
                    ? 'bg-amber-50 text-amber-800 border-amber-200 hover:bg-amber-100'
                    : 'bg-slate-50 text-slate-600 border-slate-200 hover:bg-slate-100'
                }`}
              >
                {prio === 'stat' ? '⚡ STAT' : prio}
              </button>
            );
          })}
        </div>
      </div>

      {/* Expandable Advanced Filtering Drawer */}
      {isAdvancedExpanded && (
        <div className="pt-3 border-t border-slate-200 bg-slate-50/80 -mx-4 -mb-4 p-4 rounded-b-2xl space-y-3">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
            {/* Statuses Filter */}
            {availableStatuses && availableStatuses.length > 0 && (
              <div className="space-y-1.5">
                <label className="font-bold text-slate-700 block uppercase tracking-wider text-[10px]">
                  Workflow State
                </label>
                <div className="flex flex-wrap gap-1 max-h-24 overflow-y-auto p-1 bg-white rounded-lg border border-slate-200">
                  {availableStatuses.map((st) => {
                    const isSel = filters.statuses.includes(st.value);
                    return (
                      <button
                        key={st.value}
                        onClick={() => toggleStatus(st.value)}
                        className={`px-2 py-0.5 rounded text-[11px] font-semibold transition-all cursor-pointer ${
                          isSel
                            ? 'bg-cyan-700 text-white font-bold'
                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200'
                        }`}
                      >
                        {st.label} {st.count !== undefined && `(${st.count})`}
                      </button>
                    );
                  })}
                </div>
              </div>
            )}

            {/* Safety Clearance Filter */}
            {showScreeningFilter && (
              <div className="space-y-1.5">
                <label className="font-bold text-slate-700 block uppercase tracking-wider text-[10px]">
                  Safety Screening Clearance
                </label>
                <select
                  value={filters.screeningStatus}
                  onChange={(e) =>
                    onFilterChange({ ...filters, screeningStatus: e.target.value as any })
                  }
                  className="w-full bg-white text-slate-800 px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-medium cursor-pointer"
                >
                  <option value="all">All (Cleared & Pending)</option>
                  <option value="cleared">Safety Cleared Only</option>
                  <option value="pending">Pending Questionnaire / Action</option>
                </select>
              </div>
            )}

            {/* Billing Settlement Filter */}
            {showBillingFilter && (
              <div className="space-y-1.5">
                <label className="font-bold text-slate-700 block uppercase tracking-wider text-[10px]">
                  Billing & Settlement
                </label>
                <select
                  value={filters.billingStatus}
                  onChange={(e) =>
                    onFilterChange({ ...filters, billingStatus: e.target.value as any })
                  }
                  className="w-full bg-white text-slate-800 px-2.5 py-1.5 rounded-lg border border-slate-300 text-xs font-medium cursor-pointer"
                >
                  <option value="all">All Invoices</option>
                  <option value="paid">Fully Settled / Paid Only</option>
                  <option value="unpaid">Outstanding Balance / Unpaid</option>
                </select>
              </div>
            )}

            {/* Date Range Direct Custom Input */}
            <div className="space-y-1.5">
              <label className="font-bold text-slate-700 block uppercase tracking-wider text-[10px]">
                Explicit Date Bounds
              </label>
              <div className="grid grid-cols-2 gap-1.5">
                <input
                  type="date"
                  value={filters.startDate}
                  onChange={(e) =>
                    onFilterChange({ ...filters, dateRangeMode: 'custom', startDate: e.target.value })
                  }
                  className="bg-white text-slate-800 px-2 py-1 rounded-lg border border-slate-300 text-[11px] font-medium"
                />
                <input
                  type="date"
                  value={filters.endDate}
                  onChange={(e) =>
                    onFilterChange({ ...filters, dateRangeMode: 'custom', endDate: e.target.value })
                  }
                  className="bg-white text-slate-800 px-2 py-1 rounded-lg border border-slate-300 text-[11px] font-medium"
                />
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Multi-Column Sorting Active Bar + Active Filter Summary */}
      <div className="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 text-xs">
        {/* Left: Active Sorting Hierarchy Badges */}
        <div className="flex flex-wrap items-center gap-1.5">
          <span className="text-slate-400 font-bold text-[11px] uppercase tracking-wider flex items-center gap-1">
            <ArrowUpDown className="w-3 h-3 text-cyan-600" />
            Sort:
          </span>

          {sortFields.length === 0 ? (
            <span className="text-slate-400 italic text-[11px]">
              Default (Priority / Time). <span className="hidden sm:inline text-slate-400">Click column headers to sort; Shift+Click for multi-column sort.</span>
            </span>
          ) : (
            <div className="flex flex-wrap items-center gap-1">
              {sortFields.map((sf, idx) => (
                <span
                  key={sf.column}
                  className="inline-flex items-center space-x-1 px-2 py-0.5 rounded-md bg-cyan-50 border border-cyan-200 text-cyan-900 font-semibold text-[11px] shadow-2xs"
                >
                  <span className="w-3.5 h-3.5 rounded-full bg-cyan-600 text-white text-[9px] flex items-center justify-center font-bold">
                    {idx + 1}
                  </span>
                  <span>{getColumnDisplayName(sf.column)}</span>
                  {sf.direction === 'asc' ? (
                    <ArrowUp className="w-3 h-3 text-cyan-700" />
                  ) : (
                    <ArrowDown className="w-3 h-3 text-cyan-700" />
                  )}
                  <button
                    onClick={() => removeSortField(sf.column)}
                    className="hover:text-rose-600 cursor-pointer ml-0.5 font-bold"
                    title={`Remove sort by ${getColumnDisplayName(sf.column)}`}
                  >
                    ×
                  </button>
                </span>
              ))}

              <button
                onClick={() => onSortChange([])}
                className="text-[10px] text-slate-500 hover:text-slate-800 underline font-medium cursor-pointer ml-1"
              >
                Clear Sort
              </button>
            </div>
          )}
        </div>

        {/* Right: Results Count + Reset All */}
        <div className="flex items-center space-x-2.5">
          <span className="text-slate-600 font-medium text-[11px]">
            Showing <strong className="text-slate-900 font-bold">{filteredCount}</strong> of {totalCount} studies
          </span>

          {(activeFiltersCount > 0 || sortFields.length > 0) && (
            <button
              onClick={handleResetFilters}
              className="flex items-center space-x-1 px-2.5 py-1 rounded-lg bg-rose-50 hover:bg-rose-100 text-rose-700 font-bold text-[11px] border border-rose-200 transition-colors cursor-pointer"
            >
              <RotateCcw className="w-3 h-3" />
              <span>Reset All</span>
            </button>
          )}
        </div>
      </div>
    </div>
  );
};
