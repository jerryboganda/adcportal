import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
  AlertTriangle,
  CheckCircle2,
  ClipboardList,
  Download,
  Eye,
  FileText,
  Flame,
  History,
  Languages,
  Lock,
  MessageSquare,
  Mic,
  MicOff,
  Pause,
  Pencil,
  PhoneCall,
  Plus,
  Printer,
  Save,
  Send,
  Settings2,
  Sparkles,
  Trash2,
  UserPlus,
  X,
  Zap,
} from 'lucide-react';
import {
  Appointment,
  ClinicProfileSettings,
  CriticalFindingLog,
  DictationCapability,
  PriorExam,
  RadiologistSummary,
  RadiologyReport,
  ReportMacro,
  ReportTemplate,
  ReportingPreferences,
  StructuredField,
  StructuredValues,
  TemplateMatch,
  WorklistStudy,
} from '../../types';
import { canAny } from '../../services/permissions';
import * as api from '../../services/apiService';
import { reportPdfUrl } from '../../services/apiService';
import { useDictation } from './useDictation';
import { useReportAutosave } from './useReportAutosave';
import { ReportPrintSheet } from './ReportPrintSheet';

/**
 * The radiologist's reporting workspace.
 *
 * Loads the patient/study context, resolves the clinic's template for this
 * study, and owns the authoring buffer: structured values, dictation into the
 * focused field, debounced autosave against an optimistic-locked draft, and an
 * explicit sign-off that is the ONLY thing that finalizes clinical content.
 */

export interface WorkspaceController {
  isDirty: () => boolean;
  flush: () => Promise<void>;
}

interface ReportWorkspaceProps {
  study: WorklistStudy | null;
  /** Full study record for the selected worklist row (patient, dose, notes…). */
  appointment: Appointment | null;
  permissions: string[];
  currentUser: { id: string; name: string; role: string };
  clinicSettings?: ClinicProfileSettings;
  radiologists: RadiologistSummary[];
  controllerRef: React.MutableRefObject<WorkspaceController | null>;
  /** The radiologist's own setup, held on their account (per clinic). */
  preferences: ReportingPreferences;
  /** Whether this clinic can dictate through its own transcription service. */
  dictation: DictationCapability | null;
  onPreferenceChange: (changes: Partial<ReportingPreferences>) => void;
  onStudyUpdated: (study: Appointment, report: RadiologyReport | null) => void;
  onToast: (message: string, tone?: 'success' | 'error') => void;
  onRejectToTech: (appointmentId: string, reason: string) => Promise<void>;
  onReleaseReport: (appointmentId: string, channel: 'hand' | 'email' | 'portal') => void;
  onCreateReport: () => void;
}

const DICTATABLE_FIELDS = [
  { key: 'clinicalHistory', label: 'Clinical indication', rows: 2 },
  { key: 'technique', label: 'Technique', rows: 2 },
  { key: 'comparison', label: 'Comparison', rows: 2 },
  { key: 'findings', label: 'Findings', rows: 8 },
  { key: 'impression', label: 'Impression', rows: 3 },
  { key: 'recommendations', label: 'Recommendations', rows: 2 },
] as const;

type FieldKey = (typeof DICTATABLE_FIELDS)[number]['key'];

const REJECT_CATEGORIES = [
  'Patient motion artifact / image blurring',
  'Inadequate anatomical coverage / missing views',
  'Sub-optimal IV contrast timing',
  'Incorrect patient positioning',
  'Metal / foreign body artifact',
  'Sub-optimal exposure / penetration',
];

export const ReportWorkspace: React.FC<ReportWorkspaceProps> = ({
  study,
  appointment,
  permissions,
  currentUser,
  clinicSettings,
  radiologists,
  controllerRef,
  preferences,
  dictation: dictationCapability,
  onPreferenceChange,
  onStudyUpdated,
  onToast,
  onRejectToTech,
  onReleaseReport,
  onCreateReport,
}) => {
  const appointmentId = study?.id ?? null;
  const currentReport = appointment?.report ?? null;
  const isSigned = Boolean(currentReport?.isSigned || currentReport?.lockedAt);
  const isAddendumVersion = currentReport?.type === 'addendum';

  const canAuthor = canAny(permissions, ['report create', 'report edit']);
  // Mirrors the server exactly. `storeMacro`: a PERSONAL snippet needs
  // `report edit`, a TENANT one `report template edit`. `guardMacro`: editing a
  // personal snippet also accepts `report template edit`, a shared one does not.
  // Gating on `canAuthor` instead would offer a `report create`-only holder a
  // 403 on submit; gating the chips on `report edit` alone would hide controls
  // the server does allow.
  const canEditMacros = canAny(permissions, ['report edit']);
  const canPublishTenantMacros = canAny(permissions, ['report template edit']);
  const canManageMacro = (macro: ReportMacro) =>
    canAny(permissions, macro.scope === 'personal'
      ? ['report edit', 'report template edit']
      : ['report template edit']);
  const canSign = canAny(permissions, ['report sign']);
  const canRelease = canAny(permissions, ['report release']);
  const canDownload = canAny(permissions, ['report manage']);
  const canAssignOthers = canAny(permissions, ['study assign']);

  // ---- authoring buffer -------------------------------------------------
  const [values, setValues] = useState({
    clinicalHistory: '',
    technique: '',
    comparison: '',
    findings: '',
    impression: '',
    recommendations: '',
  });
  const [structuredValues, setStructuredValues] = useState<StructuredValues>({});
  const [criticalFlag, setCriticalFlag] = useState(false);
  const [template, setTemplate] = useState<ReportTemplate | null>(null);
  const [templateMatch, setTemplateMatch] = useState<TemplateMatch | null>(null);
  const [templateList, setTemplateList] = useState<ReportTemplate[]>([]);
  const [macros, setMacros] = useState<ReportMacro[]>([]);
  /** Macro being authored: null = closed, undefined-seeded = new snippet. */
  const [macroEditor, setMacroEditor] = useState<{ macro?: ReportMacro } | null>(null);
  const [priors, setPriors] = useState<PriorExam[]>([]);
  const [criticalLogs, setCriticalLogs] = useState<CriticalFindingLog[]>([]);
  const [activeField, setActiveField] = useState<FieldKey>('findings');
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const [preferencesOpen, setPreferencesOpen] = useState(false);
  // Kept in a ref as well: it is read inside the load effect, which must not
  // re-run every time the radiologist toggles a preference.
  const autoLoadRef = useRef(preferences.templateAutoload);
  autoLoadRef.current = preferences.templateAutoload;
  const autoLoadTemplate = preferences.templateAutoload;
  const [addendumOpen, setAddendumOpen] = useState(false);
  const [addendumText, setAddendumText] = useState('');
  const [criticalOpen, setCriticalOpen] = useState(false);
  const [rejectOpen, setRejectOpen] = useState(false);
  const [printOpen, setPrintOpen] = useState(false);
  const [historyOpen, setHistoryOpen] = useState(false);
  const [saveTemplateOpen, setSaveTemplateOpen] = useState(false);
  const [newTemplateName, setNewTemplateName] = useState('');
  const [savingTemplate, setSavingTemplate] = useState(false);

  const setField = useCallback((key: FieldKey, value: string) => {
    setValues(previous => ({ ...previous, [key]: value }));
  }, []);

  const fieldRefs = useRef<Partial<Record<FieldKey, HTMLTextAreaElement | null>>>({});
  const valuesRef = useRef(values);
  valuesRef.current = values;
  const structuredRef = useRef(structuredValues);
  structuredRef.current = structuredValues;
  const criticalRef = useRef(criticalFlag);
  criticalRef.current = criticalFlag;
  const templateRef = useRef(template);
  templateRef.current = template;

  /** Insert dictation at the caret of the field the radiologist is editing. */
  const insertAtCursor = useCallback(
    (key: FieldKey, text: string) => {
      const element = fieldRefs.current[key];
      const currentValue = valuesRef.current[key] ?? '';

      if (!element) {
        setField(key, currentValue ? `${currentValue} ${text}` : text);
        return;
      }

      const start = element.selectionStart ?? currentValue.length;
      const end = element.selectionEnd ?? start;
      const next = `${currentValue.slice(0, start)}${text}${currentValue.slice(end)}`;
      setField(key, next);

      requestAnimationFrame(() => {
        element.focus();
        const caret = start + text.length;
        try {
          element.setSelectionRange(caret, caret);
        } catch {
          /* some inputs disallow it; the text is already inserted */
        }
      });
    },
    [setField]
  );

  const dictation = useDictation({
    onInsert: text => insertAtCursor(activeField, text),
    targetLabel: DICTATABLE_FIELDS.find(field => field.key === activeField)?.label ?? null,
    // Preference from the radiologist's account; the provider decides whether
    // audio stays in the clinic (self-hosted engine) or may leave it (browser).
    language: preferences.dictationLanguage,
    onLanguageChange: language => onPreferenceChange({ dictationLanguage: language }),
    serverProvider: dictationCapability?.serverProvider ?? null,
    provider: preferences.dictationProvider,
    appointmentId,
  });

  // ---- load context -----------------------------------------------------
  useEffect(() => {
    if (!appointmentId) {
      setTemplate(null);
      setTemplateMatch(null);
      setPriors([]);
      setCriticalLogs([]);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setLoadError(null);

    const report = appointment?.report ?? null;

    setValues({
      clinicalHistory: report?.clinicalHistory ?? appointment?.notes ?? '',
      technique: report?.technique ?? '',
      comparison: report?.comparison ?? '',
      findings: report?.findings ?? '',
      impression: report?.impression ?? '',
      recommendations: report?.recommendations ?? '',
    });
    setStructuredValues((report?.structuredValues as StructuredValues) ?? {});
    setCriticalFlag(Boolean(report?.criticalFlag));

    const modalityId = study?.modalityId;

    Promise.all([
      api.resolveReportTemplate({ appointmentId }),
      api.fetchReportingTemplates(modalityId ? { modalityId } : {}),
      api.fetchReportPriors(appointmentId),
      api.fetchCriticalFindings(appointmentId),
      api.fetchReportMacros(modalityId ? { modalityId } : {}),
    ])
      .then(([match, templates, priorExams, logs, macroList]) => {
        if (cancelled) return;
        setTemplateMatch(match);
        setTemplateList(templates.templates);
        setPriors(priorExams);
        setCriticalLogs(logs);
        setMacros(macroList);

        // Only adopt the resolved baseline for an EMPTY, unsigned report: a
        // draft's clinical text is never overwritten by a template, and a
        // radiologist who turned pre-filling off keeps a truly blank editor.
        const hasContent = Boolean(report?.findings || report?.impression);
        if (match.matched && !isSigned && !hasContent && autoLoadRef.current) {
          setTemplate(match.matched);
          setValues(previous => ({
            ...previous,
            clinicalHistory: previous.clinicalHistory || match.matched!.clinicalHistory,
            technique: previous.technique || match.matched!.technique,
            findings: previous.findings || match.matched!.findings,
            impression: previous.impression || match.matched!.impression,
            recommendations: previous.recommendations || match.matched!.recommendations,
          }));
        } else if (report?.templateId) {
          setTemplate(templates.templates.find(t => t.id === report.templateId) ?? match.matched);
        } else {
          setTemplate(null);
        }
      })
      .catch((error: any) => {
        if (!cancelled) setLoadError(error?.message ?? 'Could not load the reporting context.');
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => {
      cancelled = true;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [appointmentId]);

  // ---- saving -----------------------------------------------------------
  const buildPayload = useCallback(() => {
    const current = valuesRef.current;
    return {
      clinicalHistory: current.clinicalHistory,
      technique: current.technique,
      comparison: current.comparison,
      findings: current.findings,
      impression: current.impression,
      recommendations: current.recommendations,
      criticalFlag: criticalRef.current,
      templateId: templateRef.current?.id,
      structuredValues: structuredRef.current,
    };
  }, []);

  const draftId = currentReport && !isSigned ? currentReport.id : null;
  const lockVersionRef = useRef<number>(currentReport?.lockVersion ?? 1);
  useEffect(() => {
    lockVersionRef.current = currentReport?.lockVersion ?? 1;
  }, [currentReport?.lockVersion]);

  /** Writes the buffer. Toasting is the caller's job (autosave stays quiet). */
  const persistDraft = useCallback(
    async () => {
      const payload = buildPayload();

      if (draftId) {
        const { study: updatedStudy, report } = await api.updateReport(draftId, {
          ...payload,
          lockVersion: lockVersionRef.current,
        });
        lockVersionRef.current = report?.lockVersion ?? lockVersionRef.current;
        onStudyUpdated(updatedStudy, report);
        return;
      }

      if (!appointmentId) return;

      const { study: updatedStudy, report } = await api.saveReport(appointmentId, payload);
      lockVersionRef.current = report?.lockVersion ?? 1;
      onStudyUpdated(updatedStudy, report);
    },
    [appointmentId, buildPayload, draftId, onStudyUpdated]
  );

  const autosave = useReportAutosave({
    // A signed report is history; nothing autosaves into it. New reports are
    // created explicitly with "Save draft" before autosave takes over.
    enabled: Boolean(draftId) && canAuthor,
    onSave: () => persistDraft(),
  });

  useEffect(() => {
    controllerRef.current = {
      isDirty: () => autosave.status === 'dirty' || autosave.status === 'saving',
      flush: autosave.saveNow,
    };
    return () => {
      controllerRef.current = null;
    };
  }, [autosave.saveNow, autosave.status, controllerRef]);

  const handleSaveDraft = async () => {
    if (!appointmentId) return;
    try {
      await persistDraft();
      onToast(currentReport ? 'Draft updated.' : 'Draft saved.');
    } catch (error: any) {
      if (error?.status === 409) {
        onToast('This report was changed by someone else — reload the draft before saving.', 'error');
        return;
      }
      onToast(error?.message ?? 'Could not save the draft.', 'error');
    }
  };

  const handleFinalize = async () => {
    if (!appointmentId) return;

    if (!values.impression.trim()) {
      onToast('An impression is required before a report can be finalized.', 'error');
      return;
    }

    const missing = (template?.structuredFields ?? []).filter(
      field => field.required && (structuredValues[field.key] === undefined || structuredValues[field.key] === '')
    );
    if (missing.length > 0) {
      onToast(`Complete the required structure: ${missing.map(field => field.label).join(', ')}.`, 'error');
      return;
    }

    try {
      const payload = { ...buildPayload(), signNow: true, signAs: 'final' as const };

      if (draftId) {
        const { study: updatedStudy, report } = await api.updateReport(draftId, {
          ...payload,
          lockVersion: lockVersionRef.current,
        });
        onStudyUpdated(updatedStudy, report);
      } else {
        const { study: updatedStudy, report } = await api.saveReport(appointmentId, payload);
        onStudyUpdated(updatedStudy, report);
      }

      autosave.markSaved();
      onToast('Report finalized and electronically signed.');
    } catch (error: any) {
      if (error?.status === 409) {
        onToast('Someone else saved this report first — reload before signing.', 'error');
        return;
      }
      onToast(error?.message ?? 'Could not finalize the report.', 'error');
    }
  };

  const handleSavePreliminary = async () => {
    if (!appointmentId || !draftId) return;
    try {
      const { study: updatedStudy, report } = await api.updateReport(draftId, {
        ...buildPayload(),
        lockVersion: lockVersionRef.current,
        signNow: true,
        signAs: 'preliminary',
      });
      onStudyUpdated(updatedStudy, report);
      onToast('Preliminary report signed. It stays on your worklist until finalized.');
    } catch (error: any) {
      onToast(error?.message ?? 'Could not sign the preliminary report.', 'error');
    }
  };

  const handleAddendum = async () => {
    if (!currentReport || !addendumText.trim()) return;
    try {
      const { study: updatedStudy, report } = await api.createReportAddendum(currentReport.id, {
        text: addendumText.trim(),
      });
      setAddendumOpen(false);
      setAddendumText('');
      onStudyUpdated(updatedStudy, report);
      onToast('Addendum signed as a new report version.');
    } catch (error: any) {
      onToast(error?.message ?? 'Could not append the addendum.', 'error');
    }
  };

  /** Applying a template replaces the skeleton — never silently over clinical text. */
  const applyTemplate = (next: ReportTemplate) => {
    const hasText = values.findings.trim() !== '' || values.impression.trim() !== '';
    if (hasText && !window.confirm(`Replace the current report text with the "${next.name}" baseline?`)) {
      return;
    }

    setTemplate(next);
    setStructuredValues({});
    setValues({
      clinicalHistory: next.clinicalHistory || values.clinicalHistory,
      technique: next.technique || values.technique,
      comparison: values.comparison,
      findings: next.findings,
      impression: next.impression,
      recommendations: next.recommendations,
    });
    autosave.touch();
    onToast(`Loaded “${next.name}” — baseline text only, edit before finalizing.`);
  };

  const applyMacro = (macro: ReportMacro) => {
    setValues(previous => ({
      ...previous,
      findings: previous.findings.trim() ? `${previous.findings}\n\n${macro.findings}` : macro.findings,
      impression: macro.impression
        ? previous.impression.trim()
          ? `${previous.impression}\n${macro.impression}`
          : macro.impression
        : previous.impression,
      recommendations: macro.recommendations
        ? previous.recommendations.trim()
          ? `${previous.recommendations}; ${macro.recommendations}`
          : macro.recommendations
        : previous.recommendations,
    }));
    autosave.touch();
    onToast(`Inserted “${macro.name}”.`);
    void api.useReportMacro(macro.id).catch(() => undefined);
  };

  /**
   * Retire a snippet. Archived, never deleted: reports already drafted from a
   * macro keep their provenance, and clinical text must stay auditable.
   */
  const archiveMacro = async (macro: ReportMacro) => {
    try {
      await api.archiveReportMacro(macro.id);
      setMacros(previous => previous.filter(m => m.id !== macro.id));
      onToast(`Archived “${macro.name}”.`, 'success');
    } catch (err: any) {
      onToast(err?.response?.data?.message ?? `Could not archive “${macro.name}”.`, 'error');
    }
  };

  /** Curated "Normal" phrasing for one structured field. */
  const markNormal = (field: StructuredField) => {
    const text = field.normalText ?? `${field.label}: normal.`;
    const option = field.options?.find(option => /^normal$/i.test(option));

    setStructuredValues(previous => ({
      ...previous,
      [field.key]:
        field.type === 'checkbox' ? true : option ?? previous[field.key] ?? '',
    }));

    if (!valuesRef.current.findings.includes(text)) {
      setValues(previous => ({
        ...previous,
        findings: previous.findings.trim() ? `${previous.findings}\n${text}` : text,
      }));
    }

    autosave.touch();
  };

  const handleSaveTemplateAsNew = async () => {
    if (!newTemplateName.trim()) return;
    setSavingTemplate(true);
    try {
      // Personal by default: a radiologist curating their own baseline does not
      // need clinic-wide publishing rights (shared templates are governed).
      const created = await api.createReportTemplate({
        name: newTemplateName.trim(),
        modalityId: study?.modalityId ?? 0,
        scope: 'personal',
        structuredFields: template?.structuredFields ?? [],
        clinicalHistory: values.clinicalHistory,
        technique: values.technique,
        findings: values.findings,
        impression: values.impression,
        recommendations: values.recommendations,
      } as any);

      setTemplateList(previous => [...previous, created]);
      setTemplate(created);
      setSaveTemplateOpen(false);
      setNewTemplateName('');
      onToast(`Saved “${created.name}” as your private template.`);
    } catch (error: any) {
      onToast(error?.message ?? 'Could not save the template.', 'error');
    } finally {
      setSavingTemplate(false);
    }
  };

  const insertContrastProtocol = () => {
    const log = appointment?.doseLog;
    if (!log) return;

    const text = `Administered ${log.contrastAgent ?? 'IV contrast'}${
      log.contrastVolumeMl ? ` (${log.contrastVolumeMl} mL)` : ''
    }${log.contrastFlowRate ? ` at ${log.contrastFlowRate} mL/s` : ''}${
      log.salineFlushMl ? ` with ${log.salineFlushMl} mL saline chase` : ''
    }. Dose: CTDIvol ${log.doseValue} ${log.doseUnit}${log.dlpValue ? `, DLP ${log.dlpValue} mGy·cm` : ''}.`;

    setField('technique', values.technique.trim() ? `${values.technique} ${text}` : text);
    autosave.touch();
  };

  const useMostRecentPrior = (prior: PriorExam) => {
    const line = `Compared with prior ${prior.study.serviceName} (${prior.study.date})${
      prior.study.modalityCode ? ` [${prior.study.modalityCode}]` : ''
    }.`;
    setField('comparison', line);
    autosave.touch();
    onToast('Comparison line linked to the prior study.');
  };

  const selectedFieldLabel = DICTATABLE_FIELDS.find(field => field.key === activeField)?.label ?? null;

  // ---- keyboard-first reporting -----------------------------------------
  // Kept in a ref bag so the window listener subscribes exactly once while
  // always invoking the CURRENT handlers. Ctrl/Cmd+S is intercepted because in
  // a browser it would otherwise try to save the page instead of the draft.
  const shortcutRef = useRef({
    // `locked` is derived below the empty-state return; the shortcut handler
    // only needs the signed state itself.
    locked: isSigned,
    canAuthor,
    canSign,
    saveDraft: handleSaveDraft,
    finalize: handleFinalize,
    toggleDictation: dictation.toggle,
    listening: dictation.listening,
    activeField,
    onToast,
  });
  shortcutRef.current = {
    locked: isSigned,
    canAuthor,
    canSign,
    saveDraft: handleSaveDraft,
    finalize: handleFinalize,
    toggleDictation: dictation.toggle,
    listening: dictation.listening,
    activeField,
    onToast,
  };

  useEffect(() => {
    const handleKeyDown = (event: KeyboardEvent) => {
      const current = shortcutRef.current;
      const accel = event.metaKey || event.ctrlKey;

      if (accel && event.key.toLowerCase() === 's') {
        event.preventDefault();

        if (current.locked || !current.canAuthor) return;

        if (event.shiftKey) {
          if (!current.canSign) return;
          // Signing by keyboard still requires a deliberate confirmation.
          if (window.confirm('Sign and finalize this report? A signed report is immutable.')) {
            void current.finalize();
          }
          return;
        }

        void current.saveDraft();
        return;
      }

      if (event.altKey && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
        event.preventDefault();
        const index = DICTATABLE_FIELDS.findIndex(field => field.key === current.activeField);
        const step = event.key === 'ArrowDown' ? 1 : -1;
        const next = DICTATABLE_FIELDS[(index + step + DICTATABLE_FIELDS.length) % DICTATABLE_FIELDS.length];
        setActiveField(next.key);
        fieldRefs.current[next.key]?.focus();
        return;
      }

      if (event.altKey && event.key.toLowerCase() === 'd') {
        event.preventDefault();
        current.toggleDictation();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, []);

  const autosaveLabel = useMemo(() => {
    switch (autosave.status) {
      case 'dirty':
        return 'Unsaved changes…';
      case 'saving':
        return 'Saving…';
      case 'saved':
        return autosave.lastSavedAt
          ? `Autosaved ${autosave.lastSavedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}`
          : 'Saved';
      case 'error':
        return autosave.error ?? 'Autosave failed';
      case 'conflict':
        return 'Edited elsewhere — reload';
      default:
        return isSigned ? 'Signed — read only' : 'Autosave on first change';
    }
  }, [autosave.error, autosave.lastSavedAt, autosave.status, isSigned]);

  // ---- empty state ------------------------------------------------------
  if (!study || !appointment) {
    return (
      <div className="bg-white p-12 rounded-3xl border border-slate-200 text-center text-slate-500 shadow-sm">
        <FileText className="w-12 h-12 mx-auto mb-3 opacity-50 text-purple-500" />
        <h3 className="text-base font-bold text-slate-900">No study selected</h3>
        <p className="text-xs mt-1 max-w-md mx-auto">
          Pick a study from the reading worklist to report it, or create a report for an external or offline
          examination that never went through scheduling.
        </p>
        {canAuthor && (
          <button
            type="button"
            data-testid="report-create-new"
            onClick={onCreateReport}
            className="mt-4 inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md shadow-purple-600/30 cursor-pointer"
          >
            <Plus className="w-4 h-4" /> Create new report
          </button>
        )}
      </div>
    );
  }

  const locked = isSigned;

  return (
    <div className="space-y-4" data-testid="report-workspace">
      {/* Patient / study header — always visible, prevents wrong-patient reads */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm">
        <div className="p-4 flex flex-col lg:flex-row lg:items-start justify-between gap-3">
          <div className="min-w-0">
            <div className="flex items-center gap-2 flex-wrap">
              <h2 className="text-lg font-black text-slate-900 truncate">{appointment.patient.name}</h2>
              <span
                className="text-xs font-bold text-white px-2 py-0.5 rounded font-mono"
                style={{ backgroundColor: study.modalityColor }}
              >
                {study.modalityCode}
              </span>
              <span className="font-mono text-[11px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                #{study.tokenNumber || '—'}
              </span>
              {study.priority !== 'routine' && (
                <span
                  className={`text-[10px] font-black uppercase px-2 py-0.5 rounded ${
                    study.priority === 'stat' ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white'
                  }`}
                >
                  {study.priority}
                </span>
              )}
              {criticalFlag && (
                <span className="bg-rose-600 text-white text-[10px] font-black uppercase px-2 py-0.5 rounded inline-flex items-center gap-1">
                  <Flame className="w-3 h-3" /> Critical finding
                </span>
              )}
            </div>
            <div className="text-[11px] text-slate-600 mt-1.5 grid grid-cols-2 sm:grid-cols-3 gap-x-4 gap-y-0.5">
              <span>
                <span className="text-slate-400">MRN:</span> <span className="font-mono">{study.mrn}</span>
              </span>
              <span>
                <span className="text-slate-400">Age / Sex:</span> {study.age}y / {study.gender}
              </span>
              <span>
                <span className="text-slate-400">DOB:</span> {appointment.patient.dob || '—'}
              </span>
              <span className="truncate">
                <span className="text-slate-400">Study:</span> {study.serviceName}
              </span>
              <span>
                <span className="text-slate-400">Study date:</span> {study.date} {study.time}
              </span>
              <span className="truncate">
                <span className="text-slate-400">Referrer:</span> {study.referrerName || 'Self / Walk-in'}
              </span>
              <span>
                <span className="text-slate-400">Room:</span> {study.roomNumber || '—'}
              </span>
              <span>
                <span className="text-slate-400">TAT:</span> {study.turnaroundHours != null ? `${study.turnaroundHours}h` : '—'}
              </span>
              <span className="truncate">
                <span className="text-slate-400">Reporting as:</span> {currentUser.name}
              </span>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2 shrink-0">
            <button
              type="button"
              data-testid="report-print"
              onClick={() => setPrintOpen(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold border border-slate-300 cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" /> Print preview
            </button>

            <button
              type="button"
              onClick={() => setHistoryOpen(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold border border-slate-300 cursor-pointer"
            >
              <History className="w-3.5 h-3.5" /> Version history
            </button>

            <button
              type="button"
              data-testid="report-preferences"
              onClick={() => setPreferencesOpen(true)}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-bold border border-slate-300 cursor-pointer"
            >
              <Settings2 className="w-3.5 h-3.5" /> Preferences
            </button>

            {currentReport && canDownload && (
              <a
                href={reportPdfUrl(currentReport.id)}
                className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold shadow-sm cursor-pointer"
              >
                <Download className="w-3.5 h-3.5" /> Server PDF
              </a>
            )}
          </div>
        </div>

        {/* Template resolution + autosave state */}
        <div className="px-4 py-2 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2 text-[11px]">
          <div className="flex items-center gap-2 text-slate-600 min-w-0">
            <Sparkles className="w-3.5 h-3.5 text-purple-600 shrink-0" />
            {loading ? (
              <span>Loading the reporting context…</span>
            ) : templateMatch ? (
              <span className="truncate" data-testid="report-template-resolution">
                {templateMatch.explanation}
                {templateMatch.ageGroupLabel ? ` · ${templateMatch.ageGroupLabel}` : ''}
              </span>
            ) : (
              <span data-testid="report-template-resolution">
                No template matches this study — the report opens blank rather than with an unrelated baseline.
              </span>
            )}
          </div>

          <div className="flex items-center gap-2">
            {!locked && (
              <select
                aria-label="Start from a template"
                value={template?.id ?? ''}
                onChange={event => {
                  const next = templateList.find(candidate => candidate.id === event.target.value);
                  if (next) applyTemplate(next);
                }}
                className="bg-white border border-slate-200 rounded-lg text-[11px] px-2 py-1 cursor-pointer max-w-[16rem]"
              >
                <option value="">Start from template…</option>
                {templateList.map(candidate => (
                  <option key={candidate.id} value={candidate.id}>
                    {candidate.name}
                    {candidate.scope === 'personal' ? ' (mine)' : ''}
                    {candidate.ageGroup ? ` · ${candidate.ageGroup}` : ''}
                  </option>
                ))}
              </select>
            )}

            {canAuthor && (
              <button
                type="button"
                onClick={() => {
                  setNewTemplateName(study.serviceName ? `${study.serviceName} standard` : '');
                  setSaveTemplateOpen(true);
                }}
                className="text-[11px] font-bold text-purple-700 hover:underline cursor-pointer"
              >
                Save as template
              </button>
            )}
          </div>
        </div>
      </div>

      {loadError && (
        <div className="p-3 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-800 flex items-start gap-2">
          <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
          <div>
            <strong>Some reporting context could not be loaded.</strong>
            <div>{loadError}</div>
          </div>
        </div>
      )}

      {autosave.conflict && (
        <div className="p-3 rounded-2xl bg-amber-50 border border-amber-300 text-xs text-amber-900 flex items-start justify-between gap-3">
          <div className="flex items-start gap-2">
            <AlertTriangle className="w-4 h-4 shrink-0 mt-0.5" />
            <div>
              <strong>This draft changed on the server.</strong>
              <div>{autosave.conflict.message} Nothing was overwritten — your text is still in the editor.</div>
            </div>
          </div>
          <button
            type="button"
            onClick={() => {
              autosave.resolveConflict();
              onToast('Autosave stopped. Reload the study to pick up the server copy.');
            }}
            className="px-2.5 py-1 rounded-lg bg-white border border-amber-300 font-bold cursor-pointer shrink-0"
          >
            Dismiss
          </button>
        </div>
      )}

      <div className="grid grid-cols-1 xl:grid-cols-4 gap-4">
        {/* ---- editor column ---- */}
        <div className="xl:col-span-3 space-y-4">
          {locked && (
            <div
              data-testid="report-immutable"
              className="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-900 flex items-center gap-2"
            >
              <Lock className="w-4 h-4 text-emerald-600" />
              <span>
                {currentReport?.statusLabel} report v{currentReport?.version}
                {isAddendumVersion ? ' (addendum)' : ''} signed by {currentReport?.signedBy || currentUser.name}
                {currentReport?.signedAt ? ` at ${currentReport.signedAt}` : ''}. Signed reports are immutable — use an
                addendum.
              </span>
            </div>
          )}

          {/* Dictation + macros */}
          {!locked && canAuthor && (
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 space-y-3">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                  <button
                    type="button"
                    onClick={dictation.toggle}
                    disabled={!dictation.supported}
                    data-testid="report-dictation"
                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold border cursor-pointer ${
                      dictation.listening
                        ? 'bg-rose-600 text-white border-rose-600 animate-pulse'
                        : 'bg-white text-slate-700 border-slate-300 hover:bg-slate-50'
                    } disabled:opacity-50 disabled:cursor-not-allowed`}
                  >
                    {dictation.listening ? <MicOff className="w-3.5 h-3.5" /> : <Mic className="w-3.5 h-3.5 text-purple-600" />}
                    {dictation.listening ? 'Stop dictation' : 'Start dictation'}
                  </button>

                  {dictation.listening && (
                    <span className="text-[11px] text-slate-500 flex items-center gap-1">
                      <Pause className="w-3 h-3" /> inserting into <strong>{selectedFieldLabel}</strong>
                    </span>
                  )}

                  {/*
                    Which engine is actually in use. A radiologist needs to know
                    whether their voice may leave the clinic, so this states it
                    rather than implying all dictation behaves the same.
                  */}
                  {dictation.supported && (
                    <span
                      data-testid="report-dictation-engine"
                      className="text-[10px] text-slate-500"
                      title={
                        dictation.provider === 'server'
                          ? 'Audio is posted to this clinic\u2019s own speech-to-text service.'
                          : 'Recognition is provided by the browser and may be processed by its vendor\u2019s speech service.'
                      }
                    >
                      {dictation.provider === 'server' ? 'clinic engine' : 'browser recognition'}
                    </span>
                  )}

                  {dictation.transcribing && (
                    <span data-testid="report-dictation-transcribing" className="text-[10px] text-slate-500 italic">
                      transcribing…
                    </span>
                  )}

                  <label className="flex items-center gap-1 text-[11px] text-slate-500">
                    <Languages className="w-3.5 h-3.5" />
                    <select
                      aria-label="Dictation language"
                      value={dictation.language}
                      onChange={event => dictation.setLanguage(event.target.value)}
                      className="bg-white border border-slate-200 rounded-md px-1.5 py-0.5 text-[11px] cursor-pointer"
                    >
                      {dictation.languages.map(option => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </label>
                </div>

                <span
                  data-testid="report-autosave"
                  className={`text-[11px] font-semibold ${autosave.status === 'error' || autosave.status === 'conflict' ? 'text-rose-700' : 'text-slate-500'}`}
                >
                  {autosave.status === 'saved' && <CheckCircle2 className="w-3.5 h-3.5 inline mr-1 text-emerald-600" />}
                  {autosaveLabel}
                </span>
              </div>

              {!dictation.supported && (
                <p
                  data-testid="report-dictation-unavailable"
                  className="text-[11px] text-slate-500 bg-slate-50 border border-slate-200 rounded-lg p-2"
                >
                  This browser has no speech-recognition support (Chrome and Edge do; Firefox generally does not) and
                  this clinic has no self-hosted speech-to-text service configured. Typing works exactly the same —
                  dictation is an optional input method, and recognised text is never final until you sign the report.
                </p>
              )}

              {dictation.error && (
                <p className="text-[11px] text-rose-700 bg-rose-50 border border-rose-200 rounded-lg p-2">{dictation.error}</p>
              )}

              {dictation.listening && dictation.interim && (
                <p className="text-[11px] text-slate-500 italic">
                  <span className="text-slate-400">listening… </span>
                  {dictation.interim}
                </p>
              )}

              {(macros.length > 0 || canEditMacros) && (
                <div className="space-y-1.5">
                  <div className="flex items-center justify-between gap-2">
                    <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wide flex items-center gap-1">
                      <Zap className="w-3 h-3 text-purple-600" /> {study.modalityCode} snippets &amp; macros
                    </div>
                    {canEditMacros && (
                      <button
                        type="button"
                        onClick={() => setMacroEditor({})}
                        title="Save this report's findings and impression as a reusable snippet"
                        className="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-purple-50 hover:bg-purple-100 text-purple-800 text-[10px] font-bold border border-purple-200 cursor-pointer"
                      >
                        <Plus className="w-3 h-3" /> Save as macro
                      </button>
                    )}
                  </div>
                  {macros.length > 0 && (
                    <div className="flex flex-wrap gap-1.5">
                      {macros.slice(0, 10).map(macro => (
                        <span key={macro.id} className="inline-flex items-stretch rounded-lg border border-slate-200 bg-white overflow-hidden">
                          <button
                            type="button"
                            title={macro.shortcut ? `${macro.shortcut} — ${macro.impression}` : macro.impression}
                            onClick={() => applyMacro(macro)}
                            className="px-2.5 py-1 text-slate-700 hover:text-purple-900 text-[11px] font-semibold cursor-pointer"
                          >
                            + {macro.name}
                            {macro.scope === 'personal' && <span className="text-purple-600"> ●</span>}
                          </button>
                          {canManageMacro(macro) && (
                            <>
                              <button
                                type="button"
                                aria-label={`Edit macro ${macro.name}`}
                                title="Edit this snippet"
                                onClick={() => setMacroEditor({ macro })}
                                className="px-1.5 text-slate-400 hover:text-purple-700 border-l border-slate-200 cursor-pointer"
                              >
                                <Pencil className="w-3 h-3" />
                              </button>
                              <button
                                type="button"
                                aria-label={`Archive macro ${macro.name}`}
                                title="Archive this snippet (reports already drafted from it keep their text)"
                                onClick={() => void archiveMacro(macro)}
                                className="px-1.5 text-slate-400 hover:text-red-600 border-l border-slate-200 cursor-pointer"
                              >
                                <Trash2 className="w-3 h-3" />
                              </button>
                            </>
                          )}
                        </span>
                      ))}
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* Structured fields */}
          {template && template.structuredFields.length > 0 && (
            <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-3">
              <div className="flex items-center justify-between">
                <h3 className="text-xs font-black text-slate-800 uppercase tracking-wide flex items-center gap-1.5">
                  <ClipboardList className="w-3.5 h-3.5 text-purple-600" /> Structured observations
                </h3>
                <span className="text-[10px] text-slate-400">Template “{template.name}” v{template.version}</span>
              </div>

              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {template.structuredFields.map(field => {
                  const value = structuredValues[field.key];

                  return (
                    <div key={field.key} className="space-y-1">
                      <div className="flex items-center justify-between gap-2">
                        <label className="text-[11px] font-bold text-slate-700">
                          {field.label}
                          {field.required && <span className="text-rose-600"> *</span>}
                        </label>
                        {!locked && field.normalText && (
                          <button
                            type="button"
                            onClick={() => markNormal(field)}
                            title={`Insert: ${field.normalText}`}
                            className="text-[10px] font-bold text-emerald-700 hover:underline cursor-pointer"
                          >
                            Normal
                          </button>
                        )}
                      </div>

                      {field.type === 'radio' || field.type === 'select' ? (
                        <select
                          aria-label={field.label}
                          disabled={locked}
                          value={typeof value === 'string' ? value : ''}
                          onChange={event => {
                            setStructuredValues(previous => ({ ...previous, [field.key]: event.target.value }));
                            autosave.touch();
                          }}
                          className="w-full bg-slate-50 border border-slate-300 rounded-lg px-2 py-1.5 text-xs cursor-pointer disabled:opacity-70"
                        >
                          <option value="">— select —</option>
                          {(field.options ?? []).map(option => (
                            <option key={option} value={option}>
                              {option}
                            </option>
                          ))}
                        </select>
                      ) : field.type === 'checkbox' ? (
                        <label className="flex items-center gap-2 text-xs text-slate-700">
                          <input
                            type="checkbox"
                            disabled={locked}
                            checked={value === true}
                            onChange={event => {
                              setStructuredValues(previous => ({ ...previous, [field.key]: event.target.checked }));
                              autosave.touch();
                            }}
                            className="accent-purple-600 w-4 h-4 cursor-pointer"
                          />
                          {value === true ? 'Yes' : 'No'}
                        </label>
                      ) : (
                        <div className="flex items-center gap-2">
                          <input
                            type={field.type === 'date' ? 'date' : field.type === 'number' || field.type === 'measurement' ? 'number' : 'text'}
                            step={field.type === 'measurement' ? 'any' : undefined}
                            aria-label={field.label}
                            disabled={locked}
                            value={typeof value === 'string' ? value : ''}
                            placeholder={field.placeholder}
                            onChange={event => {
                              setStructuredValues(previous => ({ ...previous, [field.key]: event.target.value }));
                              autosave.touch();
                            }}
                            className="flex-1 bg-white border border-slate-300 rounded-lg px-2 py-1.5 text-xs disabled:opacity-70"
                          />
                          {field.unit && <span className="text-[11px] text-slate-500">{field.unit}</span>}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
          )}

          {/* Report body */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-4 space-y-4">
            {DICTATABLE_FIELDS.map(field => (
              <div key={field.key}>
                <div className="flex items-center justify-between mb-1">
                  <label
                    htmlFor={`report-${field.key}`}
                    className={`text-xs font-bold uppercase tracking-wide ${
                      field.key === 'impression' ? 'text-purple-900' : 'text-slate-700'
                    }`}
                  >
                    {field.label}
                    {field.key === 'impression' && <span className="text-rose-600"> *</span>}
                  </label>
                  {field.key === 'technique' && appointment.doseLog?.contrastAgent && !locked && (
                    <button
                      type="button"
                      onClick={insertContrastProtocol}
                      className="text-[10px] font-bold text-cyan-700 hover:underline cursor-pointer"
                    >
                      Insert contrast &amp; dose data
                    </button>
                  )}
                  <span className="text-[10px] text-slate-400 font-mono">{values[field.key].length} chars</span>
                </div>

                <textarea
                  id={`report-${field.key}`}
                  data-testid={`report-${field.key}`}
                  ref={element => {
                    fieldRefs.current[field.key] = element;
                  }}
                  rows={field.rows}
                  disabled={locked || !canAuthor}
                  value={values[field.key]}
                  onFocus={() => setActiveField(field.key)}
                  onChange={event => {
                    setField(field.key, event.target.value);
                    autosave.touch();
                  }}
                  className={`w-full p-3 rounded-xl border text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:bg-slate-50 disabled:opacity-90 ${
                    field.key === 'impression'
                      ? 'bg-purple-50/40 border-2 border-purple-300 font-semibold'
                      : field.key === 'findings'
                        ? 'bg-white border-slate-300 font-mono leading-relaxed'
                        : 'bg-white border-slate-300'
                  } ${activeField === field.key && dictation.listening ? 'ring-2 ring-rose-400' : ''}`}
                />
              </div>
            ))}

            {/* Critical finding protocol — a separate record, not report prose */}
            <div className="flex flex-wrap items-center justify-between gap-3 p-3 rounded-2xl bg-slate-50 border border-slate-200">
              <div className="flex items-center gap-2">
                <AlertTriangle className={`w-4 h-4 ${criticalFlag ? 'text-rose-600' : 'text-slate-400'}`} />
                <div>
                  <div className="text-xs font-bold text-slate-900">Critical / urgent finding protocol</div>
                  <div className="text-[11px] text-slate-500">
                    Communication is recorded as its own auditable log — not inside the impression.
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-2">
                {criticalFlag && !locked && canSign && (
                  <button
                    type="button"
                    onClick={() => setCriticalOpen(true)}
                    className="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-[11px] flex items-center gap-1 cursor-pointer"
                  >
                    <PhoneCall className="w-3 h-3" /> Log communication
                  </button>
                )}

                <label className="relative inline-flex items-center cursor-pointer">
                  <input
                    type="checkbox"
                    checked={criticalFlag}
                    disabled={locked || !canAuthor}
                    onChange={event => {
                      setCriticalFlag(event.target.checked);
                      autosave.touch();
                      if (event.target.checked) setCriticalOpen(true);
                    }}
                    className="sr-only peer"
                  />
                  <div className="w-9 h-5 bg-slate-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-rose-600 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all" />
                </label>
              </div>
            </div>

            {criticalLogs.length > 0 && (
              <div className="space-y-1.5">
                <div className="text-[10px] font-bold text-slate-500 uppercase tracking-wide">
                  Critical-result communications ({criticalLogs.length})
                </div>
                {criticalLogs.map(log => (
                  <div key={log.id} className="text-[11px] text-slate-700 bg-rose-50/60 border border-rose-200 rounded-lg px-2.5 py-1.5">
                    <strong>{log.notifiedTo}</strong>
                    {log.notifiedRole ? ` (${log.notifiedRole})` : ''} via {log.method} at {log.communicatedAt}
                    {' — '}
                    read-back {log.readBackVerified ? 'verified' : 'not verified'}
                    <div className="text-slate-500">{log.summary}</div>
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Actions */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 flex flex-wrap items-center justify-between gap-3">
            {locked ? (
              <>
                <span className="text-xs text-emerald-800 font-semibold flex items-center gap-1.5">
                  <Lock className="w-4 h-4 text-emerald-600" /> Finalized and signed — the record is immutable.
                </span>
                <div className="flex flex-wrap items-center gap-2">
                  {canSign && (
                    <button
                      type="button"
                      onClick={() => setAddendumOpen(true)}
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-800 text-xs font-bold border border-purple-300 cursor-pointer"
                    >
                      <Plus className="w-3.5 h-3.5" /> Add signed addendum
                    </button>
                  )}
                  {canRelease && (
                    <>
                      <button
                        type="button"
                        onClick={() => onReleaseReport(study.id, 'portal')}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold border border-slate-300 cursor-pointer"
                      >
                        <Send className="w-3.5 h-3.5 text-cyan-600" /> Publish to portal
                      </button>
                      <button
                        type="button"
                        onClick={() => onReleaseReport(study.id, 'email')}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold border border-slate-300 cursor-pointer"
                      >
                        <Send className="w-3.5 h-3.5 text-purple-600" /> Email to doctor
                      </button>
                    </>
                  )}
                  {appointment.referrer?.phone && canRelease && (
                    <a
                      href={`https://wa.me/${appointment.referrer.phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(
                        `Dr. ${appointment.referrer.name}, the radiology report for ${appointment.patient.name} (${study.serviceName}, token ${study.tokenNumber}) is finalized.`
                      )}`}
                      target="_blank"
                      rel="noreferrer"
                      title="Opens WhatsApp with a prefilled message — WhatsApp delivery itself cannot be verified here."
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-300 cursor-pointer"
                    >
                      <MessageSquare className="w-3.5 h-3.5 text-emerald-600" /> WhatsApp
                    </a>
                  )}
                </div>
              </>
            ) : (
              <>
                <span className="text-[11px] text-slate-500">
                  {canAuthor
                    ? 'Drafts autosave once the report exists. Finalizing signs the report and updates the study.'
                    : 'Your role can view this report but not author or sign it.'}
                </span>
                <div className="flex flex-wrap items-center gap-2">
                  {!locked && canAuthor && (
                    <button
                      type="button"
                      onClick={() => {
                        setRejectOpen(true);
                      }}
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white text-slate-700 border border-slate-300 hover:bg-rose-50 hover:text-rose-700 text-xs font-bold cursor-pointer"
                    >
                      <X className="w-3.5 h-3.5" /> Reject to technologist
                    </button>
                  )}

                  {canAuthor && (
                    <button
                      type="button"
                      data-testid="report-save-draft"
                      onClick={handleSaveDraft}
                      className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold border border-slate-300 cursor-pointer"
                    >
                      <Save className="w-3.5 h-3.5" /> Save draft
                    </button>
                  )}

                  {canSign && draftId && (
                    <button
                      type="button"
                      onClick={handleSavePreliminary}
                      className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 text-indigo-800 text-xs font-bold border border-indigo-300 cursor-pointer"
                    >
                      <Eye className="w-3.5 h-3.5" /> Sign preliminary
                    </button>
                  )}

                  {canSign && (
                    <button
                      type="button"
                      data-testid="report-finalize"
                      onClick={handleFinalize}
                      className="flex items-center gap-2 px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md shadow-purple-600/30 cursor-pointer"
                    >
                      <CheckCircle2 className="w-4 h-4" /> Sign &amp; finalize
                    </button>
                  )}
                </div>
              </>
            )}
          </div>
        </div>

        {/* ---- context column ---- */}
        <div className="space-y-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 space-y-2">
            <div className="flex items-center justify-between">
              <span className="text-[11px] font-black text-slate-800 uppercase tracking-wide">Assignment</span>
              {canAssignOthers && radiologists.length > 0 && (
                <select
                  aria-label="Assign this study"
                  value={study.assignedRadiologistId ?? ''}
                  onChange={async event => {
                    try {
                      await api.assignStudyToRadiologist(study.id, event.target.value || null);
                      onToast('Assignment updated.');
                    } catch (error: any) {
                      onToast(error?.message ?? 'Could not update the assignment.', 'error');
                    }
                  }}
                  className="bg-white border border-slate-200 rounded-md text-[10px] px-1.5 py-0.5 cursor-pointer max-w-[9rem]"
                >
                  <option value="">Unassigned</option>
                  {radiologists.map(radiologist => (
                    <option key={radiologist.id} value={radiologist.id}>
                      {radiologist.name}
                    </option>
                  ))}
                </select>
              )}
            </div>
            <div className="text-[11px] text-slate-600">
              {study.assignedRadiologistName ? (
                <span className="font-semibold text-slate-800">{study.assignedRadiologistName}</span>
              ) : (
                <span className="italic text-slate-500">Not assigned</span>
              )}
            </div>
            {(() => {
              // `assignedRadiologistId` is a user ID and `currentUser.name` is a
              // display name; they were being compared to each other, so the test
              // was always true and the button always said "Claim / release"
              // while only ever releasing. The label now says what the click
              // will actually do.
              const mine =
                study.assignedRadiologistId != null &&
                String(study.assignedRadiologistId) === String(currentUser.id);

              if (!canAuthor || (!mine && study.assignedRadiologistId == null)) {
                return null;
              }

              return (
                <button
                  type="button"
                  onClick={async () => {
                    try {
                      if (mine) {
                        await api.assignStudyToRadiologist(study.id, null);
                        onToast('Study released from your list.');
                      } else {
                        await api.assignStudyToRadiologist(study.id, 'me');
                        onToast('Study assigned to you.');
                      }
                    } catch (error: any) {
                      onToast(
                        error?.message ?? (mine ? 'Could not release the study.' : 'Could not claim the study.'),
                        'error'
                      );
                    }
                  }}
                  className="flex items-center gap-1.5 text-[11px] font-bold text-purple-700 hover:underline cursor-pointer"
                >
                  <UserPlus className="w-3 h-3" /> {mine ? 'Release' : 'Claim'}
                </button>
              );
            })()}
          </div>

          {/* Priors */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 space-y-2">
            <div className="flex items-center gap-1.5 text-[11px] font-black text-slate-800 uppercase tracking-wide">
              <History className="w-3.5 h-3.5 text-slate-500" /> Prior imaging ({priors.length})
            </div>

            {priors.length === 0 ? (
              <p className="text-[11px] text-slate-400 italic">No previous studies for this patient.</p>
            ) : (
              <div className="space-y-1.5 max-h-72 overflow-y-auto">
                {priors.map(prior => (
                  <div key={prior.study.id} className="bg-slate-50 border border-slate-200 rounded-lg p-2 text-[11px]">
                    <div className="flex items-center justify-between gap-2">
                      <span className="font-bold text-slate-800 truncate">{prior.study.serviceName}</span>
                      <span className="font-mono text-slate-400 shrink-0">{prior.study.date}</span>
                    </div>
                    <div className="flex items-center justify-between gap-2 mt-0.5">
                      <span className="text-slate-500">
                        {prior.study.modalityCode} · {prior.report ? prior.report.statusLabel : 'No report'}
                        {prior.report?.signedBy ? ` · ${prior.report.signedBy}` : ''}
                      </span>
                      {!locked && canAuthor && (
                        <button
                          type="button"
                          onClick={() => useMostRecentPrior(prior)}
                          className="text-[10px] font-bold text-purple-700 hover:underline cursor-pointer shrink-0"
                        >
                          Set comparison
                        </button>
                      )}
                    </div>
                    {prior.report?.impression && (
                      <p className="text-slate-600 mt-1 line-clamp-2">{prior.report.impression}</p>
                    )}
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* Dose / acquisition */}
          <div className="bg-white rounded-2xl border border-slate-200 shadow-sm p-3 text-[11px] space-y-1">
            <div className="font-black text-slate-800 uppercase tracking-wide">Acquisition record</div>
            {appointment.doseLog ? (
              <>
                <div>
                  Radiation: <strong>{appointment.doseLog.doseValue} {appointment.doseLog.doseUnit}</strong>
                  {appointment.doseLog.dlpValue ? ` (DLP ${appointment.doseLog.dlpValue} mGy·cm)` : ''}
                </div>
                {appointment.doseLog.contrastAgent && (
                  <div>
                    Contrast: <strong>{appointment.doseLog.contrastAgent}</strong>
                    {appointment.doseLog.contrastVolumeMl ? ` (${appointment.doseLog.contrastVolumeMl} mL)` : ''}
                  </div>
                )}
                <div className="text-slate-500">
                  {appointment.doseLog.sliceCount ? `${appointment.doseLog.sliceCount} slices · ` : ''}
                  recorded by {appointment.doseLog.recordedBy ?? '—'} {appointment.doseLog.recordedAt ?? ''}
                </div>
              </>
            ) : (
              <p className="text-slate-400 italic">No dose record (non-ionising study, or not yet captured).</p>
            )}
            {appointment.notes && <p className="text-slate-600 border-t border-slate-100 pt-1">Note: {appointment.notes}</p>}
          </div>
        </div>
      </div>

      {/* ---------------- modals ---------------- */}

      {macroEditor && (
        <MacroEditorModal
          macro={macroEditor.macro}
          draft={values}
          canPublishTenant={canPublishTenantMacros}
          onClose={() => setMacroEditor(null)}
          onSaved={(saved, message) => {
            setMacros(previous => {
              const rest = previous.filter(m => m.id !== saved.id);
              return [saved, ...rest];
            });
            setMacroEditor(null);
            onToast(message, 'success');
          }}
          onError={message => onToast(message, 'error')}
        />
      )}

      {criticalOpen && (
        <CriticalFindingModal
          appointment={appointment}
          reportId={currentReport?.id}
          onClose={() => setCriticalOpen(false)}
          onRecorded={log => {
            setCriticalLogs(previous => [log, ...previous]);
            setCriticalOpen(false);
            onToast('Critical-result communication recorded.');
          }}
        />
      )}

      {rejectOpen && (
        <RejectModal
          study={study}
          onClose={() => setRejectOpen(false)}
          onConfirm={async (category, notes) => {
            const reason = notes.trim() ? `[${category}] - ${notes.trim()}` : category;
            await onRejectToTech(study.id, reason);
            setRejectOpen(false);
            onToast('Study rejected to the technologist worklist.');
          }}
        />
      )}

      {addendumOpen && (
        <Modal title="Add signed addendum" subtitle="A new signed version — the original stays untouched." onClose={() => setAddendumOpen(false)}>
          <textarea
            rows={5}
            value={addendumText}
            onChange={event => setAddendumText(event.target.value)}
            placeholder="e.g. Reviewed the outside MRI received today; the nodule is unchanged since 2024."
            className="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
          />
          <div className="flex justify-end gap-2 pt-3">
            <button type="button" onClick={() => setAddendumOpen(false)} className="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-300 text-xs font-semibold cursor-pointer">
              Cancel
            </button>
            <button
              type="button"
              onClick={handleAddendum}
              disabled={!addendumText.trim()}
              className="px-3.5 py-1.5 rounded-xl bg-purple-600 text-white text-xs font-bold disabled:opacity-50 cursor-pointer"
            >
              Sign &amp; append addendum
            </button>
          </div>
        </Modal>
      )}

      {saveTemplateOpen && (
        <Modal title="Save as template" subtitle="Saved to your private templates — share clinic-wide copies via the template library." onClose={() => setSaveTemplateOpen(false)}>
          <input
            type="text"
            value={newTemplateName}
            onChange={event => setNewTemplateName(event.target.value)}
            placeholder="e.g. CT Brain stroke protocol"
            className="w-full text-xs p-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
          />
          <div className="flex justify-end gap-2 pt-3">
            <button type="button" onClick={() => setSaveTemplateOpen(false)} className="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-300 text-xs font-semibold cursor-pointer">
              Cancel
            </button>
            <button
              type="button"
              onClick={handleSaveTemplateAsNew}
              disabled={!newTemplateName.trim() || savingTemplate}
              className="px-3.5 py-1.5 rounded-xl bg-purple-600 text-white text-xs font-bold disabled:opacity-50 cursor-pointer"
            >
              {savingTemplate ? 'Saving…' : 'Save template'}
            </button>
          </div>
        </Modal>
      )}

      {preferencesOpen && (
        <PreferencesModal
          languages={dictation.languages}
          language={preferences.dictationLanguage}
          onLanguageChange={dictation.setLanguage}
          autoLoadTemplate={autoLoadTemplate}
          onAutoLoadChange={next => onPreferenceChange({ templateAutoload: next })}
          provider={preferences.dictationProvider}
          onProviderChange={next => onPreferenceChange({ dictationProvider: next })}
          serverDictation={dictationCapability?.serverProvider ?? null}
          onClose={() => setPreferencesOpen(false)}
        />
      )}

      {historyOpen && (
        <VersionHistoryModal
          appointment={appointment}
          onClose={() => setHistoryOpen(false)}
          canDownload={canDownload}
        />
      )}

      {printOpen && (
        <ReportPrintSheet
          appointment={appointment}
          report={currentReport}
          draft={{
            ...values,
            criticalFlag,
            structuredValues,
            templateId: template?.id,
            structuredFields: template?.structuredFields ?? [],
          }}
          clinicSettings={clinicSettings}
          radiologistName={currentUser.name}
          onClose={() => setPrintOpen(false)}
        />
      )}
    </div>
  );
};

// ==================== small building blocks ====================

const Modal: React.FC<{
  title: string;
  subtitle?: string;
  onClose: () => void;
  children: React.ReactNode;
}> = ({ title, subtitle, onClose, children }) => (
  <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
    <div className="bg-white border border-slate-200 rounded-3xl max-w-lg w-full p-5 shadow-2xl space-y-3">
      <div className="flex items-start justify-between border-b border-slate-200 pb-3">
        <div>
          <h3 className="font-bold text-slate-900 text-sm">{title}</h3>
          {subtitle && <p className="text-[11px] text-slate-500 mt-0.5">{subtitle}</p>}
        </div>
        <button type="button" onClick={onClose} aria-label="Close" className="p-1 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
          <X className="w-4 h-4" />
        </button>
      </div>
      {children}
    </div>
  </div>
);

/**
 * Create or edit a reporting macro (the snippet library).
 *
 * A new snippet is seeded from the report currently in the editor, because that
 * is the text the radiologist just decided was worth keeping. Editing an
 * existing one prefills from the stored macro instead: overwriting a shared
 * snippet with whatever happens to be in the editor would be data loss.
 */
const MacroEditorModal: React.FC<{
  macro?: ReportMacro;
  draft: { findings: string; impression: string; recommendations: string };
  canPublishTenant: boolean;
  onClose: () => void;
  onSaved: (macro: ReportMacro, message: string) => void;
  onError: (message: string) => void;
}> = ({ macro, draft, canPublishTenant, onClose, onSaved, onError }) => {
  const [name, setName] = useState(macro?.name ?? '');
  const [shortcut, setShortcut] = useState(macro?.shortcut ?? '');
  const [findings, setFindings] = useState(macro?.findings ?? draft.findings);
  const [impression, setImpression] = useState(macro?.impression ?? draft.impression);
  const [recommendations, setRecommendations] = useState(macro?.recommendations ?? draft.recommendations);
  const [scope, setScope] = useState<'personal' | 'tenant'>(macro?.scope ?? 'personal');
  const [saving, setSaving] = useState(false);

  const submit = async () => {
    if (!name.trim() || !findings.trim() && !impression.trim()) {
      onError('A macro needs a name and at least findings or an impression.');

      return;
    }

    setSaving(true);

    try {
      if (macro) {
        const updated = await api.updateReportMacro({
          ...macro,
          name: name.trim(),
          shortcut: shortcut.trim(),
          findings,
          impression,
          recommendations,
          scope,
        });
        onSaved(updated, `Updated “${updated.name}”.`);
      } else {
        const created = await api.createReportMacro({
          name: name.trim(),
          shortcut: shortcut.trim() || undefined,
          findings,
          impression,
          recommendations,
          scope,
        });
        onSaved(created, `Saved “${created.name}”.`);
      }
    } catch (err: any) {
      onError(err?.response?.data?.message ?? 'Could not save the macro.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title={macro ? 'Edit snippet' : 'Save as snippet'}
      subtitle={macro ? macro.name : 'Reusable findings and impression for this modality'}
      onClose={onClose}
    >
      <div className="space-y-3">
        <div className="grid grid-cols-2 gap-2">
          <div>
            <label className="block text-[11px] font-bold text-slate-700 mb-1">Name *</label>
            <input value={name} onChange={e => setName(e.target.value)} maxLength={120}
              className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
          </div>
          <div>
            <label className="block text-[11px] font-bold text-slate-700 mb-1">Shortcut</label>
            <input value={shortcut} onChange={e => setShortcut(e.target.value)} maxLength={40} placeholder="e.g. CT-NORMAL"
              className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
          </div>
        </div>

        <div>
          <label className="block text-[11px] font-bold text-slate-700 mb-1">Findings *</label>
          <textarea value={findings} onChange={e => setFindings(e.target.value)} rows={4}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono" />
        </div>

        <div>
          <label className="block text-[11px] font-bold text-slate-700 mb-1">Impression *</label>
          <textarea value={impression} onChange={e => setImpression(e.target.value)} rows={2}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono" />
        </div>

        <div>
          <label className="block text-[11px] font-bold text-slate-700 mb-1">Recommendations</label>
          <textarea value={recommendations} onChange={e => setRecommendations(e.target.value)} rows={2}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm font-mono" />
        </div>

        <div>
          <label className="block text-[11px] font-bold text-slate-700 mb-1">Visible to</label>
          <select value={scope} onChange={e => setScope(e.target.value as 'personal' | 'tenant')}
            className="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm bg-white">
            <option value="personal">Only me</option>
            {canPublishTenant && <option value="tenant">Everyone in this clinic</option>}
          </select>
          {!canPublishTenant && (
            <p className="text-[10px] text-slate-500 mt-1">
              Publishing to the whole clinic needs the “report template edit” permission.
            </p>
          )}
        </div>

        <div className="flex justify-end gap-2 pt-1">
          <button type="button" onClick={onClose}
            className="px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 border border-slate-300 cursor-pointer">
            Cancel
          </button>
          <button type="button" onClick={() => void submit()} disabled={saving}
            className="px-3 py-1.5 rounded-xl text-xs font-bold bg-purple-700 hover:bg-purple-800 text-white disabled:opacity-50 cursor-pointer">
            {saving ? 'Saving…' : macro ? 'Save changes' : 'Save snippet'}
          </button>
        </div>
      </div>
    </Modal>
  );
};

const CriticalFindingModal: React.FC<{
  appointment: Appointment;
  reportId?: string;
  onClose: () => void;
  onRecorded: (log: CriticalFindingLog) => void;
}> = ({ appointment, reportId, onClose, onRecorded }) => {
  const [summary, setSummary] = useState('');
  const [notifiedTo, setNotifiedTo] = useState(appointment.referrer?.name ?? '');
  const [notifiedRole, setNotifiedRole] = useState('Referring clinician');
  const [contact, setContact] = useState(appointment.referrer?.phone ?? '');
  const [method, setMethod] = useState<CriticalFindingLog['method']>('phone');
  const [readBack, setReadBack] = useState(true);
  const [advice, setAdvice] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const submit = async () => {
    if (!summary.trim() || !notifiedTo.trim()) {
      setError('A finding summary and the name of the clinician notified are required.');
      return;
    }

    setSaving(true);
    setError(null);

    try {
      const log = await api.recordCriticalFinding(appointment.id, {
        summary: summary.trim(),
        notifiedTo: notifiedTo.trim(),
        notifiedRole: notifiedRole.trim() || undefined,
        contact: contact.trim() || undefined,
        method,
        readBackVerified: readBack,
        adviceGiven: advice.trim() || undefined,
        reportId,
      });
      onRecorded(log);
    } catch (err: any) {
      setError(err?.message ?? 'Could not record the communication.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <Modal
      title="Critical-result communication"
      subtitle="Medico-legal record of who was told, how, and whether it was read back."
      onClose={onClose}
    >
      <div className="space-y-3 text-xs">
        {error && <div className="p-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-800">{error}</div>}

        <div>
          <label htmlFor="critical-finding-summary" className="block font-semibold text-slate-700 mb-1">
            Finding communicated *
          </label>
          <textarea
            id="critical-finding-summary"
            rows={2}
            value={summary}
            onChange={event => setSummary(event.target.value)}
            placeholder="e.g. Acute subdural haematoma with 8 mm midline shift."
            className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
          />
        </div>

        <div className="grid grid-cols-2 gap-2">
          <div>
            <label htmlFor="critical-finding-notified" className="block font-semibold text-slate-700 mb-1">
              Clinician notified *
            </label>
            <input
              id="critical-finding-notified"
              type="text"
              value={notifiedTo}
              onChange={event => setNotifiedTo(event.target.value)}
              className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
            />
          </div>
          <div>
            <label htmlFor="critical-finding-role" className="block font-semibold text-slate-700 mb-1">
              Role
            </label>
            <input
              id="critical-finding-role"
              type="text"
              value={notifiedRole}
              onChange={event => setNotifiedRole(event.target.value)}
              className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
            />
          </div>
          <div>
            <label htmlFor="critical-finding-contact" className="block font-semibold text-slate-700 mb-1">
              Contact
            </label>
            <input
              id="critical-finding-contact"
              type="text"
              value={contact}
              onChange={event => setContact(event.target.value)}
              className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
            />
          </div>
          <div>
            <label htmlFor="critical-finding-method" className="block font-semibold text-slate-700 mb-1">
              Method
            </label>
            <select
              id="critical-finding-method"
              value={method}
              onChange={event => setMethod(event.target.value as CriticalFindingLog['method'])}
              className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
            >
              <option value="phone">Telephone</option>
              <option value="in_person">In person</option>
              <option value="sms">SMS</option>
              <option value="email">Email</option>
              <option value="portal">Portal</option>
            </select>
          </div>
        </div>

        <div>
          <label htmlFor="critical-finding-advice" className="block font-semibold text-slate-700 mb-1">
            Advice given
          </label>
          <textarea
            id="critical-finding-advice"
            rows={2}
            value={advice}
            onChange={event => setAdvice(event.target.value)}
            placeholder="e.g. Immediate neurosurgical referral advised."
            className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
          />
        </div>

        <label className="flex items-center gap-2 p-2 bg-slate-50 rounded-xl border border-slate-200 font-semibold text-slate-700">
          <input type="checkbox" checked={readBack} onChange={event => setReadBack(event.target.checked)} className="accent-rose-600 w-4 h-4 cursor-pointer" />
          Receiving clinician verbally read the finding back
        </label>
      </div>

      <div className="flex justify-end gap-2 pt-3 border-t border-slate-200">
        <button type="button" onClick={onClose} className="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-300 text-xs font-semibold cursor-pointer">
          Cancel
        </button>
        <button
          type="button"
          onClick={submit}
          disabled={saving}
          className="px-3.5 py-1.5 rounded-xl bg-rose-600 text-white text-xs font-bold disabled:opacity-50 cursor-pointer"
        >
          {saving ? 'Recording…' : 'Record communication'}
        </button>
      </div>
    </Modal>
  );
};

const PreferencesModal: React.FC<{
  languages: Array<{ value: string; label: string }>;
  language: string;
  onLanguageChange: (language: string) => void;
  autoLoadTemplate: boolean;
  onAutoLoadChange: (enabled: boolean) => void;
  provider: 'browser' | 'server';
  onProviderChange: (provider: 'browser' | 'server') => void;
  serverDictation: DictationCapability['serverProvider'] | null;
  onClose: () => void;
}> = ({
  languages,
  language,
  onLanguageChange,
  autoLoadTemplate,
  onAutoLoadChange,
  provider,
  onProviderChange,
  serverDictation,
  onClose,
}) => (
  <Modal
    title="Reporting preferences"
    subtitle="Personal working preferences, saved to your account for this clinic — they follow you to any workstation."
    onClose={onClose}
  >
    <div className="space-y-4 text-xs">
      <div
        data-testid="report-preferences-dictation"
        className="p-2.5 rounded-xl bg-slate-50 border border-slate-200 space-y-2"
      >
        <div className="font-semibold text-slate-800">Dictation</div>

        <label className="flex items-start gap-2">
          <input
            type="radio"
            name="dictation-provider"
            checked={provider === 'browser'}
            onChange={() => onProviderChange('browser')}
            className="accent-purple-600 mt-0.5 cursor-pointer"
          />
          <span>
            <span className="font-semibold text-slate-700">Browser speech recognition</span>
            <span className="block text-[11px] text-slate-500">
              Available in Chromium browsers. It is not guaranteed to run locally: the browser may send audio to its
              vendor&apos;s speech service. Never auto-finalised — review before signing.
            </span>
          </span>
        </label>

        <label className={`flex items-start gap-2 ${serverDictation?.available ? '' : 'opacity-60'}`}>
          <input
            type="radio"
            name="dictation-provider"
            checked={provider === 'server'}
            disabled={!serverDictation?.available}
            onChange={() => onProviderChange('server')}
            className="accent-purple-600 mt-0.5 cursor-pointer disabled:cursor-not-allowed"
          />
          <span>
            <span className="font-semibold text-slate-700">
              This clinic&apos;s own speech-to-text service
              {serverDictation?.label ? ` — ${serverDictation.label}` : ''}
            </span>
            <span className="block text-[11px] text-slate-500">
              {serverDictation?.available
                ? 'Audio is posted to the engine your clinic runs. It is not stored by this application.'
                : serverDictation?.reason ?? 'No self-hosted dictation service has been configured for this clinic.'}
            </span>
          </span>
        </label>

        <div>
          <label htmlFor="dictation-language" className="block font-semibold text-slate-700 mb-1">
            Dictation language
          </label>
          <select
            id="dictation-language"
            aria-label="Dictation language preference"
            value={language}
            onChange={event => onLanguageChange(event.target.value)}
            className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
          >
            {languages.map(option => (
              <option key={option.value} value={option.value}>
                {option.label}
              </option>
            ))}
          </select>
          <p className="text-[11px] text-slate-500 mt-1">
            Recognition accuracy and available languages are decided by the engine, not this application. Recognised
            text is inserted as a draft you must review before signing.
          </p>
        </div>
      </div>

      <label className="flex items-start gap-2 p-2.5 rounded-xl bg-slate-50 border border-slate-200">
        <input
          type="checkbox"
          checked={autoLoadTemplate}
          onChange={event => onAutoLoadChange(event.target.checked)}
          className="accent-purple-600 w-4 h-4 mt-0.5 cursor-pointer"
        />
        <span>
          <span className="font-semibold text-slate-800">Pre-fill empty reports from the resolved template</span>
          <span className="block text-[11px] text-slate-500">
            Applies only to a report with no text yet. A saved draft is never overwritten, and the baseline is always a
            drafting aid — never a confirmed normal study.
          </span>
        </span>
      </label>

      <div className="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
        <div className="font-semibold text-slate-800 mb-1">Keyboard shortcuts</div>
        <ul className="space-y-0.5 text-[11px] text-slate-600">
          <li>
            <kbd className="font-mono">Ctrl/⌘ + S</kbd> — save the draft
          </li>
          <li>
            <kbd className="font-mono">Ctrl/⌘ + Shift + S</kbd> — sign &amp; finalize (confirms first)
          </li>
          <li>
            <kbd className="font-mono">Alt + ↑ / ↓</kbd> — move between report sections
          </li>
          <li>
            <kbd className="font-mono">Alt + D</kbd> — start / stop dictation
          </li>
        </ul>
      </div>

      <p className="text-[11px] text-slate-400">
        Personal template baselines are saved from the workspace (“Save as template”) and appear only in your own
        template list.
      </p>
    </div>

    <div className="flex justify-end pt-3 border-t border-slate-200">
      <button
        type="button"
        onClick={onClose}
        className="px-3.5 py-1.5 rounded-xl bg-purple-600 text-white text-xs font-bold cursor-pointer"
      >
        Done
      </button>
    </div>
  </Modal>
);

const RejectModal: React.FC<{
  study: WorklistStudy;
  onClose: () => void;
  onConfirm: (category: string, notes: string) => Promise<void>;
}> = ({ study, onClose, onConfirm }) => {
  const [category, setCategory] = useState(REJECT_CATEGORIES[0]);
  const [notes, setNotes] = useState('');
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  return (
    <Modal title="Reject study to technologist" subtitle={`#${study.tokenNumber} · ${study.patientName} · ${study.serviceName}`} onClose={onClose}>
      <div className="space-y-3 text-xs">
        {error && <div className="p-2 rounded-lg bg-rose-50 border border-rose-200 text-rose-800">{error}</div>}

        <div>
          <label htmlFor="reject-category" className="block font-semibold text-slate-700 mb-1">
            QA deficiency
          </label>
          <select id="reject-category" value={category} onChange={event => setCategory(event.target.value)} className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer">
            {REJECT_CATEGORIES.map(option => (
              <option key={option} value={option}>
                {option}
              </option>
            ))}
          </select>
        </div>

        <div>
          <label htmlFor="reject-notes" className="block font-semibold text-slate-700 mb-1">
            Re-scan instructions
          </label>
          <textarea
            id="reject-notes"
            rows={3}
            value={notes}
            onChange={event => setNotes(event.target.value)}
            placeholder="e.g. Repeat the axial T2 sequence with immobilisation; cover down to L5-S1."
            className="w-full p-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
          />
        </div>
      </div>

      <div className="flex justify-end gap-2 pt-3 border-t border-slate-200">
        <button type="button" onClick={onClose} className="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-300 text-xs font-semibold cursor-pointer">
          Cancel
        </button>
        <button
          type="button"
          disabled={saving}
          onClick={async () => {
            setSaving(true);
            setError(null);
            try {
              await onConfirm(category, notes);
            } catch (err: any) {
              setError(err?.message ?? 'Could not reject the study.');
            } finally {
              setSaving(false);
            }
          }}
          className="px-3.5 py-1.5 rounded-xl bg-rose-600 text-white text-xs font-bold disabled:opacity-50 cursor-pointer"
        >
          {saving ? 'Sending…' : 'Send rejection'}
        </button>
      </div>
    </Modal>
  );
};

const VersionHistoryModal: React.FC<{
  appointment: Appointment;
  canDownload: boolean;
  onClose: () => void;
}> = ({ appointment, canDownload, onClose }) => {
  const versions = useMemo(() => {
    const list: RadiologyReport[] = [];
    if (appointment.report) list.push(appointment.report);
    return list;
  }, [appointment.report]);

  return (
    <Modal title="Report history" subtitle={`${appointment.patient.name} · ${appointment.service.name}`} onClose={onClose}>
      <div className="space-y-2 text-xs max-h-96 overflow-y-auto">
        <div className="p-2 rounded-xl bg-slate-50 border border-slate-200">
          <div className="font-bold text-slate-800">Current version</div>
          {versions.length === 0 ? (
            <p className="text-slate-500 mt-0.5">No report has been started for this study.</p>
          ) : (
            versions.map(version => (
              <div key={version.id} className="mt-1 space-y-0.5 text-slate-600">
                <div>
                  v{version.version} · <strong>{version.statusLabel}</strong>
                  {version.isSigned ? ' (signed)' : ' (editable)'}
                </div>
                <div>Authored by {version.authoredBy}</div>
                {version.signedBy && (
                  <div>
                    Signed by {version.signedBy}
                    {version.signedAt ? ` on ${version.signedAt}` : ''}
                  </div>
                )}
                {version.templateId && <div className="text-slate-400">Template rev {version.templateVersion ?? '—'}</div>}
                {version.summary && <p className="text-slate-500 italic mt-1">{version.summary}</p>}
                {canDownload && (
                  <a href={reportPdfUrl(version.id)} className="inline-flex items-center gap-1 text-cyan-700 font-bold hover:underline">
                    <Download className="w-3 h-3" /> Download this version
                  </a>
                )}
              </div>
            ))
          )}
        </div>

        <div className="p-2 rounded-xl bg-slate-50 border border-slate-200">
          <div className="font-bold text-slate-800">Full version chain</div>
          <p className="text-slate-500 mt-0.5">
            Use the search panel in the reporting header to browse every version ever signed for this patient, including
            addenda and corrected finals.
          </p>
        </div>
      </div>
    </Modal>
  );
};
