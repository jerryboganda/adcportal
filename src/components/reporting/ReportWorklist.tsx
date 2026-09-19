import React, { useEffect, useRef, useState } from 'react';
import {
  AlertTriangle,
  ChevronLeft,
  ChevronRight,
  Flame,
  History,
  Layers,
  RefreshCw,
  Search,
  UserCheck,
  UserPlus,
  X,
} from 'lucide-react';
import { Modality, RadiologistSummary, ReportStatusFilter, ReportingTab, WorklistCounts, WorklistStudy } from '../../types';

/**
 * The radiologist's reading worklist.
 *
 * Every count, filter and page is computed on the SERVER (see
 * ReportingController::worklist) — a hospital's study table is not a browser
 * collection. This component owns only the presentation and the debounced
 * search input.
 */

export interface WorklistFilters {
  q: string;
  priority: 'all' | 'routine' | 'urgent' | 'stat';
  modalityId: number | null;
  reportStatus: ReportStatusFilter | null;
  assignee: 'all' | 'me' | 'unassigned';
  sort: 'priority' | 'oldest' | 'newest';
}

export const emptyWorklistFilters: WorklistFilters = {
  q: '',
  priority: 'all',
  modalityId: null,
  reportStatus: null,
  assignee: 'all',
  sort: 'priority',
};

/**
 * A radiologist's saved view: one click back to "my STAT CTs from today".
 *
 * Personal workstation state — the filter is a UI preference, so it is stored
 * locally per browser rather than pushed into the tenant's data model. Only
 * filter values are kept (never patient data).
 */
export interface SavedWorklistView {
  name: string;
  tab: ReportingTab;
  filters: WorklistFilters;
}

const SAVED_VIEWS_KEY = 'polytronx_ris_worklist_views';

function readSavedViews(): SavedWorklistView[] {
  try {
    const raw = localStorage.getItem(SAVED_VIEWS_KEY);
    const parsed = raw ? JSON.parse(raw) : [];
    return Array.isArray(parsed) ? parsed.filter((view: SavedWorklistView) => view?.name) : [];
  } catch {
    return [];
  }
}

function writeSavedViews(views: SavedWorklistView[]): void {
  try {
    localStorage.setItem(SAVED_VIEWS_KEY, JSON.stringify(views));
  } catch {
    /* storage unavailable (private mode) — views simply do not persist */
  }
}

interface TabDefinition {
  key: ReportingTab;
  label: string;
  countKey: keyof WorklistCounts;
  hint: string;
}

export const WORKLIST_TABS: TabDefinition[] = [
  { key: 'unreported', label: 'Unreported', countKey: 'unreported', hint: 'Acquired or being read' },
  { key: 'assigned', label: 'Assigned to me', countKey: 'assigned', hint: 'Studies on your list' },
  { key: 'priority', label: 'Priority', countKey: 'priority', hint: 'STAT and urgent' },
  { key: 'in_progress', label: 'In progress', countKey: 'in_progress', hint: 'Opened for reporting' },
  { key: 'drafts', label: 'My drafts', countKey: 'drafts', hint: 'Unsigned work of yours' },
  { key: 'preliminary', label: 'Preliminary', countKey: 'preliminary', hint: 'Signed as preliminary, awaiting final' },
  { key: 'finalized', label: 'Finalized', countKey: 'finalized', hint: 'Signed and released' },
  { key: 'addenda', label: 'Addenda', countKey: 'addenda', hint: 'Amended reports' },
  { key: 'recent', label: 'Recent', countKey: 'recent', hint: 'Anything with a report version' },
  { key: 'all', label: 'All reports', countKey: 'all', hint: 'Every reportable study' },
];

interface ReportWorklistProps {
  studies: WorklistStudy[];
  counts: WorklistCounts;
  tab: ReportingTab;
  filters: WorklistFilters;
  loading: boolean;
  error: string | null;
  page: number;
  perPage: number;
  total: number;
  hasMore: boolean;
  selectedId: string | null;
  modalities: Modality[];
  radiologists: RadiologistSummary[];
  canAssignSelf: boolean;
  canReassign: boolean;
  onTabChange: (tab: ReportingTab) => void;
  onFiltersChange: (filters: WorklistFilters) => void;
  onPageChange: (page: number) => void;
  onSelect: (study: WorklistStudy) => void;
  onRefresh: () => void;
  onAssign: (study: WorklistStudy, radiologistId: string | null) => void;
}

/** Deep-equality check for a saved filter set (field order is not significant). */
function sameFilters(a: WorklistFilters, b: WorklistFilters): boolean {
  return (
    a.q === b.q &&
    a.priority === b.priority &&
    a.modalityId === b.modalityId &&
    a.reportStatus === b.reportStatus &&
    a.assignee === b.assignee &&
    a.sort === b.sort
  );
}

const priorityStyles: Record<string, string> = {
  stat: 'bg-rose-600 text-white',
  urgent: 'bg-amber-500 text-white',
  routine: 'bg-slate-200 text-slate-700',
};

const statusStyles: Record<string, string> = {
  'Not started': 'bg-slate-100 text-slate-600 border-slate-200',
  Draft: 'bg-amber-50 text-amber-800 border-amber-200',
  Preliminary: 'bg-indigo-50 text-indigo-800 border-indigo-200',
  Final: 'bg-emerald-50 text-emerald-800 border-emerald-200',
  Addendum: 'bg-purple-50 text-purple-800 border-purple-200',
};

export const ReportWorklist: React.FC<ReportWorklistProps> = ({
  studies,
  counts,
  tab,
  filters,
  loading,
  error,
  page,
  perPage,
  total,
  hasMore,
  selectedId,
  modalities,
  radiologists,
  canAssignSelf,
  canReassign,
  onTabChange,
  onFiltersChange,
  onPageChange,
  onSelect,
  onRefresh,
  onAssign,
}) => {
  // The input is local so typing stays instant; the request is debounced.
  const [searchDraft, setSearchDraft] = useState(filters.q);
  const [savedViews, setSavedViews] = useState<SavedWorklistView[]>(readSavedViews);
  const debounce = useRef<number | null>(null);

  const activeView = savedViews.find(view => view.tab === tab && sameFilters(view.filters, filters)) ?? null;

  const saveCurrentView = () => {
    const name = window.prompt('Name this view (e.g. "My STAT CTs")')
      ?? '';
    const trimmed = name.trim();
    if (trimmed === '') return;

    const next = [
      ...savedViews.filter(view => view.name !== trimmed),
      { name: trimmed, tab, filters: { ...filters } },
    ].slice(0, 8);

    setSavedViews(next);
    writeSavedViews(next);
  };

  const removeView = (name: string) => {
    const next = savedViews.filter(view => view.name !== name);
    setSavedViews(next);
    writeSavedViews(next);
  };

  useEffect(() => {
    setSearchDraft(filters.q);
  }, [filters.q]);

  useEffect(() => () => {
    if (debounce.current !== null) window.clearTimeout(debounce.current);
  }, []);

  const handleSearch = (value: string) => {
    setSearchDraft(value);
    if (debounce.current !== null) window.clearTimeout(debounce.current);
    debounce.current = window.setTimeout(() => {
      onFiltersChange({ ...filters, q: value.trim() });
    }, 320);
  };

  const activeFilters =
    filters.priority !== 'all' ||
    filters.modalityId !== null ||
    filters.reportStatus !== null ||
    filters.assignee !== 'all' ||
    filters.q !== '';

  const from = total === 0 ? 0 : (page - 1) * perPage + 1;
  const to = Math.min(page * perPage, total);

  return (
    <div
      data-testid="reporting-worklist"
      className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden flex flex-col"
    >
      {/* Tabs: counts come from the server, so they are global truth. */}
      <div className="px-3 pt-3">
        <div className="flex flex-wrap gap-1.5">
          {WORKLIST_TABS.map(definition => {
            const count = counts?.[definition.countKey] ?? 0;
            const isActive = tab === definition.key;

            return (
              <button
                key={definition.key}
                type="button"
                title={definition.hint}
                onClick={() => onTabChange(definition.key)}
                className={`px-2.5 py-1.5 rounded-lg text-[11px] font-bold border transition-colors cursor-pointer flex items-center gap-1.5 ${
                  isActive
                    ? 'bg-purple-600 text-white border-purple-600 shadow-sm'
                    : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
                }`}
              >
                <span>{definition.label}</span>
                <span
                  className={`px-1.5 rounded-md text-[10px] font-mono ${
                    isActive ? 'bg-purple-500/60 text-white' : 'bg-slate-100 text-slate-600'
                  }`}
                >
                  {count}
                </span>
              </button>
            );
          })}
        </div>
      </div>

      {/* Saved personal views — one click back to a standing filter set. */}
      {(savedViews.length > 0 || activeFilters) && (
        <div className="px-3 flex flex-wrap items-center gap-1.5">
          {savedViews.map(view => (
            <span
              key={view.name}
              className={`inline-flex items-center gap-1 pl-2 pr-1 py-0.5 rounded-lg border text-[10px] font-bold ${
                activeView?.name === view.name
                  ? 'bg-purple-600 text-white border-purple-600'
                  : 'bg-white text-slate-600 border-slate-200'
              }`}
            >
              <button
                type="button"
                title={`${view.name} — ${view.tab}`}
                onClick={() => {
                  onTabChange(view.tab);
                  onFiltersChange({ ...view.filters });
                  onPageChange(1);
                }}
                className="cursor-pointer"
              >
                {view.name}
              </button>
              <button
                type="button"
                aria-label={`Delete saved view ${view.name}`}
                onClick={() => removeView(view.name)}
                className="opacity-60 hover:opacity-100 cursor-pointer"
              >
                <X className="w-2.5 h-2.5" />
              </button>
            </span>
          ))}

          {activeFilters && !activeView && (
            <button
              type="button"
              data-testid="worklist-save-view"
              onClick={saveCurrentView}
              className="px-2 py-0.5 rounded-lg border border-dashed border-purple-300 text-[10px] font-bold text-purple-700 hover:bg-purple-50 cursor-pointer"
            >
              + Save current view
            </button>
          )}
        </div>
      )}

      {/* Filters */}
      <div className="p-3 space-y-2 border-b border-slate-100">
        <div className="flex items-center gap-2">
          <div className="relative flex-1">
            <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
            <input
              type="text"
              value={searchDraft}
              onChange={event => handleSearch(event.target.value)}
              placeholder="Search patient, MRN, token, procedure…"
              aria-label="Search reading worklist"
              className="w-full bg-slate-50 text-slate-800 pl-8 pr-8 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 focus:bg-white"
            />
            {searchDraft !== '' && (
              <button
                type="button"
                aria-label="Clear search"
                onClick={() => {
                  setSearchDraft('');
                  onFiltersChange({ ...filters, q: '' });
                }}
                className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-700 cursor-pointer"
              >
                <X className="w-3.5 h-3.5" />
              </button>
            )}
          </div>

          <button
            type="button"
            onClick={onRefresh}
            title="Reload the worklist"
            className="p-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 cursor-pointer"
          >
            <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
          </button>
        </div>

        <div className="grid grid-cols-2 gap-2">
          <select
            aria-label="Filter by priority"
            value={filters.priority}
            onChange={event => onFiltersChange({ ...filters, priority: event.target.value as WorklistFilters['priority'] })}
            className="bg-slate-50 text-slate-800 px-2 py-1.5 rounded-lg border border-slate-300 text-xs cursor-pointer"
          >
            <option value="all">All priorities</option>
            <option value="stat">STAT</option>
            <option value="urgent">Urgent</option>
            <option value="routine">Routine</option>
          </select>

          <select
            aria-label="Filter by modality"
            value={filters.modalityId ?? ''}
            onChange={event =>
              onFiltersChange({ ...filters, modalityId: event.target.value === '' ? null : Number(event.target.value) })
            }
            className="bg-slate-50 text-slate-800 px-2 py-1.5 rounded-lg border border-slate-300 text-xs cursor-pointer"
          >
            <option value="">All modalities</option>
            {modalities.map(modality => (
              <option key={modality.id} value={modality.id}>
                {modality.name} ({modality.code})
              </option>
            ))}
          </select>

          <select
            aria-label="Filter by report status"
            value={filters.reportStatus ?? ''}
            onChange={event =>
              onFiltersChange({
                ...filters,
                reportStatus: (event.target.value || null) as ReportStatusFilter | null,
              })
            }
            className="bg-slate-50 text-slate-800 px-2 py-1.5 rounded-lg border border-slate-300 text-xs cursor-pointer"
          >
            <option value="">Any report status</option>
            <option value="not_started">Not started</option>
            <option value="draft">Draft</option>
            <option value="preliminary">Preliminary</option>
            <option value="final">Final</option>
            <option value="addendum">Addendum</option>
          </select>

          <select
            aria-label="Filter by assignment"
            value={filters.assignee}
            onChange={event => onFiltersChange({ ...filters, assignee: event.target.value as WorklistFilters['assignee'] })}
            className="bg-slate-50 text-slate-800 px-2 py-1.5 rounded-lg border border-slate-300 text-xs cursor-pointer"
          >
            <option value="all">Anyone&apos;s queue</option>
            <option value="me">Assigned to me</option>
            <option value="unassigned">Unassigned</option>
          </select>
        </div>

        <div className="flex items-center justify-between">
          <select
            aria-label="Sort worklist"
            value={filters.sort}
            onChange={event => onFiltersChange({ ...filters, sort: event.target.value as WorklistFilters['sort'] })}
            className="bg-white text-slate-600 px-2 py-1 rounded-lg border border-slate-200 text-[11px] cursor-pointer"
          >
            <option value="priority">Priority, then oldest acquired</option>
            <option value="oldest">Oldest acquired first</option>
            <option value="newest">Newest acquired first</option>
          </select>

          {activeFilters && (
            <button
              type="button"
              onClick={() => onFiltersChange(emptyWorklistFilters)}
              className="text-[11px] font-bold text-purple-700 hover:underline cursor-pointer"
            >
              Reset filters
            </button>
          )}
        </div>
      </div>

      {/* Rows */}
      <div className="flex-1 min-h-[16rem] max-h-[34rem] overflow-y-auto divide-y divide-slate-100">
        {error ? (
          <div className="p-4 text-xs text-rose-700 bg-rose-50 border-b border-rose-100 flex items-start gap-2">
            <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
            <div>
              <div className="font-bold">The reading worklist could not be loaded.</div>
              <div className="text-rose-600">{error}</div>
            </div>
          </div>
        ) : loading && studies.length === 0 ? (
          <div className="p-4 space-y-2" aria-busy="true">
            {[0, 1, 2, 3].map(index => (
              <div key={index} className="h-12 rounded-xl bg-slate-100 animate-pulse" />
            ))}
          </div>
        ) : studies.length === 0 ? (
          <div className="p-8 text-center text-slate-400 text-xs">
            <Layers className="w-8 h-8 mx-auto mb-2 opacity-50" />
            No studies match this view.
            <div className="mt-1 text-[11px] text-slate-400">
              {tab === 'assigned'
                ? 'Claim work from the Unreported tab to build your list.'
                : 'Adjust the filters, or book a study to see it here once acquired.'}
            </div>
          </div>
        ) : (
          studies.map(study => {
            const isSelected = selectedId === study.id;
            const isStat = study.priority === 'stat';
            const statusClass = statusStyles[study.reportStatus] ?? statusStyles['Not started'];

            return (
              <div
                key={study.id}
                data-testid="worklist-row"
                onClick={() => onSelect(study)}
                className={`p-3 cursor-pointer transition-colors ${
                  isSelected ? 'bg-purple-50/70' : isStat ? 'bg-rose-50/20 hover:bg-rose-50/40' : 'hover:bg-slate-50'
                }`}
              >
                <div className="flex items-center justify-between gap-2">
                  <div className="flex items-center gap-1.5 flex-wrap">
                    <span
                      className={`text-[10px] font-black uppercase tracking-wide px-1.5 py-0.5 rounded ${
                        priorityStyles[study.priority] ?? priorityStyles.routine
                      }`}
                    >
                      {isStat && <Flame className="w-2.5 h-2.5 inline mr-0.5 -mt-0.5" />}
                      {study.priority}
                    </span>
                    <span className="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                      #{study.tokenNumber || '—'}
                    </span>
                    <span
                      className="text-[10px] font-bold text-white px-1.5 py-0.5 rounded font-mono"
                      style={{ backgroundColor: study.modalityColor }}
                    >
                      {study.modalityCode}
                    </span>
                    {study.rejectReason && (
                      <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-100 text-rose-800 border border-rose-200">
                        Re-scan requested
                      </span>
                    )}
                  </div>

                  <span className={`text-[10px] font-bold px-2 py-0.5 rounded-md border whitespace-nowrap ${statusClass}`}>
                    {study.reportStatus}
                  </span>
                </div>

                <div className="mt-1.5 flex items-baseline justify-between gap-2">
                  <div className="min-w-0">
                    <div className="font-bold text-xs text-slate-900 truncate">{study.patientName}</div>
                    <div className="text-[11px] text-slate-500 truncate">
                      {study.serviceName}
                      {study.bodyRegion ? ` • ${study.bodyRegion}` : ''}
                    </div>
                  </div>
                  <div className="text-right shrink-0">
                    <div className="text-[10px] text-slate-400 font-mono">
                      {study.age}y {study.gender[0]?.toUpperCase() ?? ''}
                    </div>
                    <div className="text-[10px] text-slate-400 font-mono">{study.mrn}</div>
                  </div>
                </div>

                <div className="mt-1.5 flex items-center justify-between gap-2 text-[10px] text-slate-500">
                  <span className="flex items-center gap-1.5 truncate">
                    <History className="w-3 h-3" />
                    {study.date}
                    {study.time ? ` ${study.time}` : ''}
                    {study.turnaroundHours != null ? ` • TAT ${study.turnaroundHours}h` : ''}
                  </span>

                  <span className="flex items-center gap-1.5 shrink-0">
                    {study.assignedRadiologistName ? (
                      <span className="flex items-center gap-1 text-slate-600 font-semibold truncate max-w-[9rem]">
                        <UserCheck className="w-3 h-3 text-emerald-600" />
                        {study.assignedRadiologistName}
                      </span>
                    ) : canAssignSelf ? (
                      <button
                        type="button"
                        onClick={event => {
                          event.stopPropagation();
                          onAssign(study, 'me');
                        }}
                        className="flex items-center gap-1 font-bold text-purple-700 hover:underline cursor-pointer"
                      >
                        <UserPlus className="w-3 h-3" /> Claim
                      </button>
                    ) : (
                      <span className="italic">Unassigned</span>
                    )}

                    {canReassign && (
                      <select
                        aria-label={`Assign ${study.patientName}`}
                        value={study.assignedRadiologistId ?? ''}
                        onClick={event => event.stopPropagation()}
                        onChange={event => {
                          event.stopPropagation();
                          onAssign(study, event.target.value === '' ? null : event.target.value);
                        }}
                        className="bg-white border border-slate-200 rounded-md text-[10px] px-1 py-0.5 cursor-pointer max-w-[8rem]"
                      >
                        <option value="">Unassign</option>
                        {radiologists.map(radiologist => (
                          <option key={radiologist.id} value={radiologist.id}>
                            {radiologist.name}
                          </option>
                        ))}
                      </select>
                    )}
                  </span>
                </div>
              </div>
            );
          })
        )}
      </div>

      {/* Pagination */}
      <div className="px-3 py-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
        <span>
          {from}–{to} of {total}
        </span>
        <div className="flex items-center gap-1">
          <button
            type="button"
            disabled={page <= 1}
            onClick={() => onPageChange(page - 1)}
            className="p-1 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50 cursor-pointer disabled:cursor-default"
            aria-label="Previous page"
          >
            <ChevronLeft className="w-3.5 h-3.5" />
          </button>
          <span className="font-mono px-1">Page {page}</span>
          <button
            type="button"
            disabled={!hasMore}
            onClick={() => onPageChange(page + 1)}
            className="p-1 rounded-lg border border-slate-200 disabled:opacity-40 hover:bg-slate-50 cursor-pointer disabled:cursor-default"
            aria-label="Next page"
          >
            <ChevronRight className="w-3.5 h-3.5" />
          </button>
        </div>
      </div>
    </div>
  );
};
