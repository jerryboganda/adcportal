import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  Activity,
  AlertTriangle,
  Clock,
  FileSearch,
  Layers,
  Plus,
  RefreshCw,
  Search,
  X,
} from 'lucide-react';
import {
  Appointment,
  ClinicProfileSettings,
  DictationCapability,
  Modality,
  Patient,
  RadiologistSummary,
  RadiologyReport,
  Referrer,
  ReportSearchRow,
  ReportingPreferences,
  ReportingSavedView,
  ReportingTab,
  ReportingViewFilters,
  Service,
  StaffUser,
  WorklistCounts,
  WorklistStudy,
} from '../types';

import * as api from '../services/apiService';
import { canAny } from '../services/permissions';
import {
  LEGACY_SAVED_VIEWS_KEY,
  ReportWorklist,
  SavedWorklistView,
  WorklistFilters,
  emptyWorklistFilters,
} from './reporting/ReportWorklist';
import { ReportWorkspace, WorkspaceController } from './reporting/ReportWorkspace';
import { CreateReportModal } from './reporting/CreateReportModal';
import { TemplateLibrary } from './reporting/TemplateLibrary';

/**
 * Radiology reporting workstation.
 *
 * Owns the reading queue (fetched server-side, filtered and paged in SQL),
 * the selected study's context, and the reporting workspace. Two entry paths
 * exist by design: work from the allocation queue, or create a report for an
 * external/offline study — both end in the same signed, versioned record.
 */

export interface ReportingViewProps {
  appointments: Appointment[];
  patients: Patient[];
  services: Service[];
  modalities: Modality[];
  referrers: Referrer[];
  permissions: string[];
  currentUser: StaffUser;
  clinicSettings: ClinicProfileSettings;
  /**
   * A study another board asked us to open (dashboard "Manage", global search,
   * a notification). `nonce` changes on every request so that asking twice for
   * the same study still opens it.
   */
  studyFocus?: { id: number; nonce: number } | null;
  /** Called once the hand-off above has been consumed, so it cannot fire again. */
  onStudyFocusHandled?: () => void;
  onRejectToTech: (appointmentId: string, reason: string) => Promise<void>;
  onReleaseReport: (appointmentId: string, channel: 'hand' | 'email' | 'portal') => void;
  flash: (kind: 'success' | 'error', message: string) => void;
}

const DEFAULT_PREFERENCES: ReportingPreferences = {
  dictationLanguage: 'en-US',
  dictationProvider: 'browser',
  templateAutoload: true,
  defaultTab: 'unreported',
};

const PREFERENCES_CACHE_KEY = 'polytronx_ris_reporting_preferences';
const VIEWS_CACHE_KEY = 'polytronx_ris_worklist_views_cache';

/**
 * The account is the source of truth for a radiologist's setup; localStorage is
 * only a CACHE, so the queue paints instantly on a workstation the user has
 * used before and still works if the preferences request fails. Anything read
 * from here is replaced by the server's answer on the next successful load.
 */
function readCache<T>(key: string, fallback: T): T {
  try {
    const raw = localStorage.getItem(key);
    return raw ? (JSON.parse(raw) as T) : fallback;
  } catch {
    return fallback;
  }
}

function writeCache(key: string, value: unknown): void {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch {
    /* storage unavailable (private mode) — the server copy is still correct */
  }
}

/**
 * Views saved by the OLD build, which kept them in this browser only. Read once
 * so the upgrade carries them onto the account instead of losing them.
 */
function readLegacyViews(): SavedWorklistView[] {
  const parsed = readCache<SavedWorklistView[]>(LEGACY_SAVED_VIEWS_KEY, []);
  return Array.isArray(parsed) ? parsed.filter(view => view?.name) : [];
}

function clearLegacyViews(): void {
  try {
    localStorage.removeItem(LEGACY_SAVED_VIEWS_KEY);
  } catch {
    /* nothing to clear */
  }
}

/** Every report status the queue can filter on. */
const REPORT_STATUS_FILTERS: readonly string[] = ['not_started', 'draft', 'preliminary', 'final', 'addendum'];

/**
 * A server row → the complete filter set the worklist component expects.
 *
 * Values are re-validated rather than trusted: a stored view was written by an
 * older build, or by hand. Anything the queue cannot express degrades to "any"
 * so a stale view can never silently hide studies.
 */
function toWorklistView(view: ReportingSavedView): SavedWorklistView {
  const filters = view.filters ?? {};

  return {
    id: view.id,
    name: view.name,
    tab: filters.tab ?? 'unreported',
    filters: {
      q: filters.q ?? '',
      priority:
        filters.priority === 'routine' || filters.priority === 'urgent' || filters.priority === 'stat'
          ? filters.priority
          : 'all',
      modalityId: typeof filters.modalityId === 'number' ? filters.modalityId : null,
      reportStatus: REPORT_STATUS_FILTERS.includes(filters.reportStatus ?? '')
        ? (filters.reportStatus as WorklistFilters['reportStatus'])
        : null,
      assignee: filters.assignee === 'me' || filters.assignee === 'unassigned' ? filters.assignee : 'all',
      sort: filters.sort === 'oldest' || filters.sort === 'newest' ? filters.sort : 'priority',
    },
  };
}

/** The worklist's complete filter set → the partial set the API stores. */
function toServerFilters(tab: ReportingTab, filters: WorklistFilters): ReportingViewFilters {
  return {
    tab,
    q: filters.q || undefined,
    priority: filters.priority,
    modalityId: filters.modalityId,
    reportStatus: filters.reportStatus,
    assignee: filters.assignee,
    sort: filters.sort,
  };
}

const EMPTY_COUNTS: WorklistCounts = {
  assigned: 0,
  unreported: 0,
  in_progress: 0,
  priority: 0,
  drafts: 0,
  preliminary: 0,
  finalized: 0,
  addenda: 0,
  recent: 0,
  all: 0,
  stat: 0,
};

export const ReportingView: React.FC<ReportingViewProps> = ({
  appointments,
  patients,
  services,
  modalities,
  referrers,
  permissions,
  currentUser,
  clinicSettings,
  studyFocus,
  onStudyFocusHandled,
  onRejectToTech,
  onReleaseReport,
  flash,
}) => {
  const canAuthor = canAny(permissions, ['report create', 'report edit']);
  const canReassign = permissions.includes('study assign');
  const canBrowseTemplates = canAny(permissions, ['report template manage', 'report create', 'report edit']);

  const [tab, setTab] = useState<ReportingTab>('unreported');

  /**
   * Has the radiologist chosen a tab themselves?
   *
   * The stored default tab arrives with the preferences request, which resolves
   * asynchronously — so without this flag a click made while that request is in
   * flight is silently undone a moment later ("the tab jumps back"). Applying a
   * stored default is a first-paint concern; it must never override a human.
   */
  const tabChosenByUser = useRef(false);
  const chooseTab = useCallback((next: ReportingTab) => {
    tabChosenByUser.current = true;
    setTab(next);
  }, []);
  const [filters, setFilters] = useState<WorklistFilters>(emptyWorklistFilters);
  const [page, setPage] = useState(1);
  const [studies, setStudies] = useState<WorklistStudy[]>([]);
  const [counts, setCounts] = useState<WorklistCounts>(EMPTY_COUNTS);
  const [total, setTotal] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [perPage, setPerPage] = useState(20);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [selectedStudy, setSelectedStudy] = useState<WorklistStudy | null>(null);
  const [radiologists, setRadiologists] = useState<RadiologistSummary[]>([]);
  const [createOpen, setCreateOpen] = useState(false);
  const [libraryOpen, setLibraryOpen] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [refreshToken, setRefreshToken] = useState(0);

  // The radiologist's own setup: server-held (per user, per clinic), cached
  // locally so the first paint is instant and an offline load still works.
  const [preferences, setPreferences] = useState<ReportingPreferences | null>(
    () => readCache<ReportingPreferences | null>(PREFERENCES_CACHE_KEY, null)
  );
  const [dictation, setDictation] = useState<DictationCapability | null>(null);
  const [savedViews, setSavedViews] = useState<SavedWorklistView[]>(() =>
    readCache<ReportingSavedView[]>(VIEWS_CACHE_KEY, []).map(toWorklistView)
  );

  const controllerRef = useRef<WorkspaceController | null>(null);
  /** Studies we created/saved here, so the workspace has its full context. */
  const studyCache = useRef<Record<string, Appointment>>({});

  // ---- reading queue (server-side) --------------------------------------
  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    const handle = window.setTimeout(() => {
      api
        .fetchReportingWorklist({
          tab,
          q: filters.q || undefined,
          priority: filters.priority,
          modalityId: filters.modalityId ?? undefined,
          reportStatus: filters.reportStatus ?? undefined,
          assignee: filters.assignee === 'all' ? undefined : filters.assignee,
          sort: filters.sort,
          page,
          perPage,
        })
        .then(result => {
          if (cancelled) return;
          setStudies(result.studies);
          setCounts(result.counts);
          setTotal(result.total);
          setHasMore(result.hasMore);
          setPerPage(result.perPage);
        })
        .catch((err: any) => {
          if (cancelled) return;
          setError(
            err?.status === 403
              ? 'Your role does not have access to the reading worklist.'
              : err?.message ?? 'Could not load the reading worklist.'
          );
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, filters.q ? 300 : 0);

    return () => {
      cancelled = true;
      window.clearTimeout(handle);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [tab, filters.q, filters.priority, filters.modalityId, filters.reportStatus, filters.assignee, filters.sort, page, perPage, refreshToken]);

  useEffect(() => {
    api
      .fetchRadiologistRoster()
      .then(setRadiologists)
      .catch(() => setRadiologists([]));
  }, []);

  // ---- the radiologist's own setup --------------------------------------
  // Loading it is not allowed to break the module: a failure leaves the cached
  // copy in place and reporting continues with sensible defaults.
  useEffect(() => {
    let cancelled = false;

    api
      .fetchReportingPreferences()
      .then(result => {
        if (cancelled) return;

        setPreferences(result.preferences);
        writeCache(PREFERENCES_CACHE_KEY, result.preferences);

        if (!tabChosenByUser.current) {
          setTab(result.preferences.defaultTab);
        }

        const views = result.views.map(toWorklistView);
        setSavedViews(views);
        writeCache(VIEWS_CACHE_KEY, result.views);

        return adoptLegacyViews(result.views);
      })
      .catch(() => {
        /* cached setup stays in use; the next load reconciles */
      });

    api
      .fetchDictationCapability()
      .then(capability => {
        if (!cancelled) setDictation(capability);
      })
      .catch(() => {
        if (!cancelled) setDictation(null);
      });

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  /**
   * One-time move of views the previous build kept in this browser onto the
   * radiologist's account. Only names the account does not already hold are
   * imported, and the local copy is kept until every write succeeds — an
   * upgrade must never be the reason someone's standing filter list disappears.
   */
  const adoptLegacyViews = useCallback(async (serverViews: ReportingSavedView[]) => {
    const legacy = readLegacyViews();
    if (legacy.length === 0) return;

    const known = new Set(serverViews.map(view => view.name.toLowerCase()));
    const pending = legacy.filter(view => !known.has(view.name.trim().toLowerCase())).slice(0, 20);

    if (pending.length === 0) {
      clearLegacyViews();
      return;
    }

    let views = serverViews;

    for (const view of pending) {
      try {
        views = await api.saveReportingView(view.name, toServerFilters(view.tab, view.filters));
      } catch {
        return; // kept locally: the next load tries again
      }
    }

    clearLegacyViews();
    setSavedViews(views.map(toWorklistView));
    writeCache(VIEWS_CACHE_KEY, views);
    flash('success', 'Your saved worklist views were moved to your account.');
  }, [flash]);

  const saveView = useCallback(async (name: string, viewTab: ReportingTab, viewFilters: WorklistFilters) => {
    try {
      const views = await api.saveReportingView(name, toServerFilters(viewTab, viewFilters));
      setSavedViews(views.map(toWorklistView));
      writeCache(VIEWS_CACHE_KEY, views);
      flash('success', `Saved view “${name}”.`);
    } catch (error: any) {
      flash('error', error?.message ?? 'That view could not be saved.');
    }
  }, [flash]);

  const deleteView = useCallback(async (view: SavedWorklistView) => {
    try {
      const views = await api.deleteReportingView(view.id);
      setSavedViews(views.map(toWorklistView));
      writeCache(VIEWS_CACHE_KEY, views);
    } catch (error: any) {
      flash('error', error?.message ?? 'That view could not be removed.');
    }
  }, [flash]);

  /**
   * Change one preference. Applied instantly (a radiologist toggling a setting
   * should not wait on a round trip) and rolled back if the server refuses it,
   * so the UI never claims a setting that was not stored.
   */
  const changePreference = useCallback(async (changes: Partial<ReportingPreferences>) => {
    const previous = preferences;
    const optimistic = { ...(preferences ?? DEFAULT_PREFERENCES), ...changes };

    setPreferences(optimistic);
    writeCache(PREFERENCES_CACHE_KEY, optimistic);

    try {
      const saved = await api.saveReportingPreferences(changes);
      setPreferences(saved);
      writeCache(PREFERENCES_CACHE_KEY, saved);
    } catch (error: any) {
      setPreferences(previous);
      if (previous) writeCache(PREFERENCES_CACHE_KEY, previous);
      flash('error', error?.message ?? 'That preference could not be saved.');
    }
  }, [preferences, flash]);

  const refresh = useCallback(() => setRefreshToken(token => token + 1), []);

  // ---- selection with wrong-patient safety ------------------------------
  const appointmentFor = useCallback(
    (study: WorklistStudy | null): Appointment | null => {
      if (!study) return null;
      // A study we just created or saved lives in the local cache (App's list
      // has not been refetched); otherwise resolve it from the loaded set.
      return (
        studyCache.current[study.id] ??
        appointments.find(appointment => appointment.id === study.id) ??
        null
      );
    },
    [appointments]
  );

  const selectedAppointment = useMemo(
    () => appointmentFor(selectedStudy),
    [appointmentFor, selectedStudy]
  );

  const openStudy = useCallback(
    async (study: WorklistStudy) => {
      if (study.id === selectedStudy?.id) return;

      // Never carry one patient's unsigned text into another patient's report.
      if (controllerRef.current?.isDirty()) {
        const proceed = window.confirm(
          'You have unsaved changes in the open report. Save them and switch to the selected study?'
        );
        if (!proceed) return;
        try {
          await controllerRef.current.flush();
        } catch {
          flash('error', 'Could not save the open draft — the study was not switched.');
          return;
        }
      }

      setSelectedStudy(study);
    },
    [flash, selectedStudy?.id]
  );

  // ---- hand-off from another board ---------------------------------------
  // The queue is paginated, so the study that was clicked elsewhere is not
  // reliably on the page we hold: resolve it from the server, which re-checks
  // the tenant and the study's state rather than trusting the id we were given.
  const consumedFocus = useRef<number | null>(null);
  useEffect(() => {
    if (!studyFocus || consumedFocus.current === studyFocus.nonce) return;
    consumedFocus.current = studyFocus.nonce;
    let cancelled = false;

    api
      .fetchReportingStudy(studyFocus.id)
      .then(study => {
        if (!cancelled) openStudy(study);
      })
      .catch((err: any) => {
        if (cancelled) return;
        flash(
          'error',
          err?.status === 404
            ? 'That study is not available to your clinic.'
            : err?.status === 409
              ? 'That study is no longer awaiting interpretation.'
              : 'Could not open the selected study.'
        );
      })
      .finally(() => {
        if (!cancelled) onStudyFocusHandled?.();
      });

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [studyFocus?.nonce]);

  const handleStudyUpdated = useCallback(
    (study: Appointment, report: RadiologyReport | null) => {
      studyCache.current[study.id] = study;

      setSelectedStudy(previous =>
        previous && previous.id === study.id
          ? {
              ...previous,
              reportId: report?.id ?? previous.reportId,
              reportStatus: report?.statusLabel ?? previous.reportStatus,
              reportType: report?.type ?? previous.reportType,
              reportSignedAt: report?.signedAt ?? previous.reportSignedAt,
              reportAuthor: report?.authoredBy ?? previous.reportAuthor,
              criticalFlag: Boolean(report?.criticalFlag),
              workflowState: study.workflowState,
              assignedRadiologistId: study.assignedRadiologistId,
              assignedRadiologistName: study.assignedRadiologistName,
            }
          : previous
      );

      // A signed report changes the queue's composition; keep it honest.
      if (report?.isSigned) refresh();
    },
    [refresh]
  );

  const assign = useCallback(
    async (study: WorklistStudy, radiologistId: string | null) => {
      try {
        await api.assignStudyToRadiologist(study.id, radiologistId);
        flash('success', radiologistId ? 'Study assigned.' : 'Study released.');
        refresh();
      } catch (err: any) {
        flash('error', err?.message ?? 'Could not update the assignment.');
      }
    },
    [flash, refresh]
  );

  const pendingInterpretation = counts.unreported + counts.preliminary + counts.drafts;
  const activeTabHint = useMemo(() => {
    switch (tab) {
      case 'assigned':
        return 'Studies allocated to you, plus unclaimed work you can pick up.';
      case 'unreported':
        return 'Acquired studies that still need an interpretation.';
      case 'priority':
        return 'STAT and urgent studies — report these first.';
      case 'in_progress':
        return 'Opened and being reported right now.';
      case 'drafts':
        return 'Your unsigned drafts. Autosave keeps them current.';
      case 'preliminary':
        return 'Signed as preliminary and awaiting a definitive report.';
      case 'finalized':
        return 'Signed final reports.';
      case 'addenda':
        return 'Reports amended after finalization — originals untouched.';
      default:
        return 'Every reportable study in the queue.';
    }
  }, [tab]);

  return (
    <div className="space-y-4">
      {/* workstation header */}
      <div className="bg-gradient-to-br from-white to-purple-50/40 rounded-3xl border border-purple-200 shadow-sm p-5">
        <div className="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
          <div>
            <div className="flex items-center gap-2 flex-wrap">
              <h1 className="text-xl font-black text-slate-900 tracking-tight">Radiologist Reading &amp; Reporting Suite</h1>
              <span className="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200">
                PACS Diagnostic Console
              </span>
            </div>
            <p className="text-[11px] text-slate-600 mt-1 max-w-3xl">
              Structured templates, browser dictation, autosaved drafts, electronic sign-off, immutable versioning and
              printed hospital-grade output — with prior studies and the critical-result log in the same workspace.
            </p>

            <div className="flex flex-wrap items-center gap-3 mt-2 text-[11px]">
              <span className="flex items-center gap-1.5 text-emerald-700 font-semibold">
                <span className="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse" /> Reading worklist is live from
                the server
              </span>
              <span className="text-slate-500">
                Reporting as <strong className="text-slate-800">{currentUser.name}</strong>
              </span>
              <span className="text-slate-500">
                {activeTabHint}
              </span>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <button
              type="button"
              onClick={() => setSearchOpen(true)}
              className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-700 text-xs font-bold border border-slate-300 hover:bg-slate-50 cursor-pointer"
            >
              <FileSearch className="w-3.5 h-3.5 text-purple-600" /> Find a report
            </button>

            {canBrowseTemplates && (
              <button
                type="button"
                onClick={() => setLibraryOpen(true)}
                className="flex items-center gap-1.5 px-3 py-2 rounded-xl bg-white text-slate-700 text-xs font-bold border border-slate-300 hover:bg-slate-50 cursor-pointer"
              >
                <Layers className="w-3.5 h-3.5 text-purple-600" /> Templates
              </button>
            )}

            {canAuthor && (
              <button
                type="button"
                data-testid="create-report-open"
                onClick={() => setCreateOpen(true)}
                className="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-md shadow-purple-600/30 cursor-pointer"
              >
                <Plus className="w-3.5 h-3.5" /> Create new report
              </button>
            )}

            <button
              type="button"
              onClick={refresh}
              aria-label="Refresh the reading worklist"
              className="p-2 rounded-xl bg-white text-slate-600 border border-slate-300 hover:bg-slate-50 cursor-pointer"
            >
              <RefreshCw className={`w-3.5 h-3.5 ${loading ? 'animate-spin' : ''}`} />
            </button>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-4 mt-4 pt-3 border-t border-purple-100 text-[11px]">
          <span className="flex items-center gap-1.5 text-slate-600">
            <Activity className="w-3.5 h-3.5 text-purple-600" /> Total in queue:{' '}
            <strong className="text-slate-900 font-mono">{counts.all}</strong>
          </span>
          <span className="flex items-center gap-1.5 text-amber-800 font-semibold">
            <Clock className="w-3.5 h-3.5" /> Pending interpretation:{' '}
            <strong className="font-mono">{pendingInterpretation}</strong>
          </span>
          {counts.stat > 0 && (
            <span className="flex items-center gap-1.5 text-rose-700 font-bold">
              <AlertTriangle className="w-3.5 h-3.5" /> STAT waiting: <strong className="font-mono">{counts.stat}</strong>
            </span>
          )}
          {counts.drafts > 0 && (
            <span className="text-slate-600">
              Your drafts: <strong className="font-mono text-slate-900">{counts.drafts}</strong>
            </span>
          )}
          {total > perPage && (
            <span className="text-slate-500">
              Showing {studies.length} of {total}
            </span>
          )}
        </div>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-7 gap-4 items-start">
        <div className="xl:col-span-3 2xl:col-span-2">
          <ReportWorklist
            studies={studies}
            counts={counts}
            tab={tab}
            filters={filters}
            loading={loading}
            error={error}
            page={page}
            perPage={perPage}
            total={total}
            hasMore={hasMore}
            selectedId={selectedStudy?.id ?? null}
            modalities={modalities}
            radiologists={radiologists}
            canAssignSelf={canAuthor}
            canReassign={canReassign}
            onTabChange={next => {
              chooseTab(next);
              setPage(1);
            }}
            onFiltersChange={next => {
              setFilters(next);
              setPage(1);
            }}
            onPageChange={setPage}
            onSelect={openStudy}
            onRefresh={refresh}
            onAssign={assign}
            savedViews={savedViews}
            onSaveView={saveView}
            onDeleteView={deleteView}
          />
        </div>

        <div className="xl:col-span-4 2xl:col-span-5">
          <ReportWorkspace
            study={selectedStudy}
            appointment={selectedAppointment}
            permissions={permissions}
            currentUser={{ id: currentUser.id, name: currentUser.name, role: currentUser.role }}
            clinicSettings={clinicSettings}
            radiologists={radiologists}
            controllerRef={controllerRef}
            onStudyUpdated={handleStudyUpdated}
            onToast={(message, tone) => flash(tone === 'error' ? 'error' : 'success', message)}
            preferences={preferences ?? DEFAULT_PREFERENCES}
            dictation={dictation}
            onPreferenceChange={changePreference}
            onRejectToTech={async (appointmentId, reason) => {
              await onRejectToTech(appointmentId, reason);
              refresh();
            }}
            onReleaseReport={onReleaseReport}
            onCreateReport={() => setCreateOpen(true)}
          />
        </div>
      </div>

      {createOpen && (
        <CreateReportModal
          patients={patients}
          services={services}
          modalities={modalities}
          referrers={referrers}
          onClose={() => setCreateOpen(false)}
          onError={message => flash('error', message)}
          onCreated={(study, report) => {
            studyCache.current[study.id] = study;
            setSelectedStudy({
              id: study.id,
              tokenNumber: study.tokenNumber,
              patientId: study.patientId,
              patientName: study.patient.name,
              mrn: study.patient.mrn,
              age: study.patient.age,
              gender: study.patient.gender,
              serviceId: study.serviceId,
              serviceName: study.service.name,
              modalityId: study.modalityId,
              modalityCode: study.modality?.code ?? '',
              modalityColor: study.modality?.color ?? '#7c3aed',
              bodyRegion: '',
              contrastType: '',
              date: study.date,
              time: study.time,
              priority: study.priority,
              workflowState: study.workflowState,
              assignedRadiologistId: study.assignedRadiologistId ?? null,
              assignedRadiologistName: study.assignedRadiologistName ?? currentUser.name,
              referrerName: study.referrer?.name ?? null,
              roomNumber: study.roomNumber ?? '',
              reportId: report?.id ?? null,
              reportStatus: report?.statusLabel ?? 'Not started',
              reportType: report?.type ?? 'draft',
              reportSignedAt: null,
              reportAuthor: report?.authoredBy ?? currentUser.name,
              criticalFlag: false,
            });
            flash('success', 'Manual report created — it is now in the patient’s record.');
            refresh();
          }}
        />
      )}

      {libraryOpen && (
        <TemplateLibrary
          modalities={modalities}
          services={services}
          permissions={permissions}
          onClose={() => setLibraryOpen(false)}
          onToast={(message, tone) => flash(tone === 'error' ? 'error' : 'success', message)}
        />
      )}

      {searchOpen && (
        <ReportSearchPanel
          modalities={modalities}
          onClose={() => setSearchOpen(false)}
          onOpen={row => {
            setSearchOpen(false);
            if (row.study) void openStudy(row.study);
          }}
        />
      )}
    </div>
  );
};

/**
 * Report search across the tenant's signed and draft reports (patient, MRN,
 * modality, radiologist, status, date range). Results are paged server-side.
 */
const ReportSearchPanel: React.FC<{
  modalities: Modality[];
  onClose: () => void;
  onOpen: (row: ReportSearchRow) => void;
}> = ({ modalities, onClose, onOpen }) => {
  const [query, setQuery] = useState('');
  const [modalityId, setModalityId] = useState<number | ''>('');
  const [status, setStatus] = useState<'' | 'draft' | 'preliminary' | 'final' | 'addendum'>('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [rows, setRows] = useState<ReportSearchRow[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    setLoading(true);
    setError(null);

    const handle = window.setTimeout(() => {
      api
        .searchReports({
          q: query || undefined,
          modalityId: modalityId === '' ? undefined : Number(modalityId),
          status: status || undefined,
          from: from || undefined,
          to: to || undefined,
          perPage: 30,
        })
        .then(payload => {
          if (!cancelled) setRows(payload.reports);
        })
        .catch((err: any) => {
          if (!cancelled) setError(err?.message ?? 'Report search failed.');
        })
        .finally(() => {
          if (!cancelled) setLoading(false);
        });
    }, query ? 300 : 0);

    return () => {
      cancelled = true;
      window.clearTimeout(handle);
    };
  }, [from, modalityId, query, status, to]);

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-start justify-center p-4 overflow-y-auto">
      <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-4xl shadow-2xl">
        <div className="flex items-start justify-between p-4 border-b border-slate-200">
          <div>
            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-1.5">
              <FileSearch className="w-4 h-4 text-purple-600" /> Report search
            </h3>
            <p className="text-[11px] text-slate-500 mt-0.5">
              Patient, MRN, token, accession or report text — across every version, including addenda.
            </p>
          </div>
          <button type="button" onClick={onClose} aria-label="Close search" className="p-1 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="p-4 space-y-3">
          <div className="grid grid-cols-2 md:grid-cols-5 gap-2 text-xs">
            <div className="col-span-2 relative">
              <Search className="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" />
              <input
                type="text"
                value={query}
                onChange={event => setQuery(event.target.value)}
                placeholder="Patient, MRN, token, accession…"
                className="w-full pl-8 p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>
            <select
              value={modalityId}
              onChange={event => setModalityId(event.target.value === '' ? '' : Number(event.target.value))}
              className="p-2 rounded-xl border border-slate-300 cursor-pointer"
            >
              <option value="">All modalities</option>
              {modalities.map(modality => (
                <option key={modality.id} value={modality.id}>
                  {modality.code}
                </option>
              ))}
            </select>
            <select
              value={status}
              onChange={event => setStatus(event.target.value as typeof status)}
              className="p-2 rounded-xl border border-slate-300 cursor-pointer"
            >
              <option value="">Any status</option>
              <option value="draft">Draft</option>
              <option value="preliminary">Preliminary</option>
              <option value="final">Final</option>
              <option value="addendum">Addendum</option>
            </select>
            <div className="flex items-center gap-1">
              <input
                type="date"
                value={from}
                onChange={event => setFrom(event.target.value)}
                className="w-full p-2 rounded-xl border border-slate-300"
              />
              <span className="text-slate-400">–</span>
              <input
                type="date"
                value={to}
                onChange={event => setTo(event.target.value)}
                className="w-full p-2 rounded-xl border border-slate-300"
              />
            </div>
          </div>

          {error && <div className="p-2 rounded-xl bg-rose-50 border border-rose-200 text-[11px] text-rose-800">{error}</div>}

          <div className="max-h-[55vh] overflow-y-auto border border-slate-200 rounded-2xl divide-y divide-slate-100">
            {loading && <p className="p-3 text-xs text-slate-500">Searching…</p>}
            {!loading && rows.length === 0 && (
              <p className="p-3 text-xs text-slate-500">No reports match these filters.</p>
            )}
            {rows.map(row => (
              <button
                key={row.report.id}
                type="button"
                onClick={() => onOpen(row)}
                className="w-full text-left p-3 hover:bg-slate-50 cursor-pointer"
              >
                <div className="flex items-center justify-between gap-2">
                  <span className="font-bold text-xs text-slate-900 truncate">
                    {row.study?.patientName ?? 'Patient'} · {row.study?.serviceName ?? 'Study'}
                  </span>
                  <span
                    className={`text-[10px] font-bold uppercase px-1.5 py-0.5 rounded border shrink-0 ${
                      row.report.isSigned
                        ? 'bg-emerald-50 text-emerald-800 border-emerald-200'
                        : 'bg-amber-50 text-amber-800 border-amber-200'
                    }`}
                  >
                    {row.report.statusLabel}
                  </span>
                </div>
                <div className="text-[10px] text-slate-500 mt-0.5">
                  {row.study ? `MRN ${row.study.mrn} · ${row.study.modalityCode} · ${row.study.date}` : 'Study not linked'}
                  {' · v'}
                  {row.report.version} · {row.report.authoredBy}
                  {row.report.signedAt ? ` · signed ${row.report.signedAt}` : ' · unsigned'}
                </div>
                {row.report.impression && (
                  <p className="text-[11px] text-slate-600 mt-1 line-clamp-2">{row.report.impression}</p>
                )}
              </button>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
};

export default ReportingView;
