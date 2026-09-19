import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Archive,
  ArchiveRestore,
  Copy,
  FileText,
  Layers,
  Plus,
  Save,
  Search,
  Sparkles,
  Star,
  X,
} from 'lucide-react';
import { Modality, ReportTemplate, Service, StructuredField, StructuredFieldType } from '../../types';
import * as api from '../../services/apiService';

/**
 * Reporting template library.
 *
 * Clinical template text is governed content: it lives here (and in the
 * database), never inside a React component. Radiologists may keep private
 * baselines; publishing a tenant-wide template requires the template
 * permission, and every save becomes a new revision that authored reports stay
 * linked to.
 */

const AGE_GROUP_OPTIONS = [
  { value: '', label: 'Any age' },
  { value: 'neonatal', label: 'Neonatal (0–28 days)' },
  { value: 'infant', label: 'Infant (1–23 months)' },
  { value: 'pediatric', label: 'Pediatric (2–12 years)' },
  { value: 'adolescent', label: 'Adolescent (13–17 years)' },
  { value: 'adult', label: 'Adult (18–64 years)' },
  { value: 'older_adult', label: 'Older adult (65+)' },
];

const FIELD_TYPES: StructuredFieldType[] = ['text', 'number', 'measurement', 'select', 'radio', 'checkbox', 'date'];

interface TemplateDraft {
  id?: string;
  name: string;
  modalityId: number;
  serviceId: number | '';
  bodyRegion: string;
  ageGroup: string;
  sex: '' | 'male' | 'female';
  contrast: '' | 'with' | 'without' | 'both';
  scope: 'tenant' | 'personal';
  isDefault: boolean;
  clinicalHistory: string;
  technique: string;
  findings: string;
  impression: string;
  recommendations: string;
  structuredFields: StructuredField[];
}

const blankDraft = (modalityId: number): TemplateDraft => ({
  name: '',
  modalityId,
  serviceId: '',
  bodyRegion: '',
  ageGroup: '',
  sex: '',
  contrast: '',
  scope: 'personal',
  isDefault: false,
  clinicalHistory: '',
  technique: '',
  findings: '',
  impression: '',
  recommendations: '',
  structuredFields: [],
});

const toDraft = (template: ReportTemplate): TemplateDraft => ({
  id: template.id,
  name: template.name,
  modalityId: template.modalityId,
  serviceId: template.serviceId ?? '',
  bodyRegion: template.bodyRegion ?? '',
  ageGroup: template.ageGroup ?? '',
  sex: template.sex ?? '',
  contrast: template.contrast ?? '',
  scope: template.scope,
  isDefault: template.isDefault,
  clinicalHistory: template.clinicalHistory,
  technique: template.technique,
  findings: template.findings,
  impression: template.impression,
  recommendations: template.recommendations,
  structuredFields: template.structuredFields ?? [],
});

export const TemplateLibrary: React.FC<{
  modalities: Modality[];
  services: Service[];
  permissions: string[];
  onClose: () => void;
  onToast: (message: string, tone?: 'success' | 'error') => void;
}> = ({ modalities, services, permissions, onClose, onToast }) => {
  const canPublish = permissions.includes('report template create');
  const canEdit = permissions.includes('report template edit') || permissions.includes('report create');
  const canDelete = permissions.includes('report template delete');

  const [templates, setTemplates] = useState<ReportTemplate[]>([]);
  const [query, setQuery] = useState('');
  const [modalityFilter, setModalityFilter] = useState<number | ''>('');
  const [includeArchived, setIncludeArchived] = useState(false);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [draft, setDraft] = useState<TemplateDraft | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const payload = await api.fetchReportingTemplates({
        q: query || undefined,
        modalityId: modalityFilter === '' ? undefined : Number(modalityFilter),
        includeArchived,
      });
      setTemplates(payload.templates);
    } catch (err: any) {
      setError(err?.message ?? 'Could not load the template library.');
    } finally {
      setLoading(false);
    }
  }, [includeArchived, modalityFilter, query]);

  useEffect(() => {
    const handle = window.setTimeout(() => void load(), 250);
    return () => window.clearTimeout(handle);
  }, [load]);

  const selected = useMemo(
    () => templates.find(template => template.id === selectedId) ?? null,
    [selectedId, templates]
  );

  useEffect(() => {
    if (selected) setDraft(toDraft(selected));
  }, [selected]);

  const save = async () => {
    if (!draft) return;
    if (!draft.name.trim()) {
      setError('A template name is required.');
      return;
    }
    if (!draft.modalityId) {
      setError('Choose the modality this template belongs to.');
      return;
    }
    if (draft.scope === 'tenant' && !canPublish) {
      setError('Publishing a clinic-wide template needs the template permission. Save it privately instead.');
      return;
    }

    setSaving(true);
    setError(null);

    const payload = {
      name: draft.name.trim(),
      modalityId: draft.modalityId,
      serviceId: draft.serviceId === '' ? null : Number(draft.serviceId),
      bodyRegion: draft.bodyRegion.trim() || null,
      ageGroup: draft.ageGroup || null,
      sex: draft.sex || null,
      contrast: draft.contrast || null,
      scope: draft.scope,
      isDefault: draft.isDefault,
      clinicalHistory: draft.clinicalHistory,
      technique: draft.technique,
      findings: draft.findings,
      impression: draft.impression,
      recommendations: draft.recommendations,
      structuredFields: draft.structuredFields,
    };

    try {
      if (draft.id) {
        const existing = templates.find(template => template.id === draft.id);
        const saved = await api.updateReportTemplate({
          ...(existing as ReportTemplate),
          ...(payload as any),
        });
        setTemplates(previous => previous.map(template => (template.id === saved.id ? saved : template)));
        onToast(`Saved “${saved.name}” as v${saved.version}.`);
      } else {
        const created = await api.createReportTemplate(payload as any);
        setTemplates(previous => [created, ...previous]);
        setSelectedId(created.id);
        onToast(`Created “${created.name}”.`);
      }
    } catch (err: any) {
      setError(err?.message ?? 'Could not save the template.');
    } finally {
      setSaving(false);
    }
  };

  const duplicate = async (template: ReportTemplate) => {
    try {
      const copy = await api.duplicateReportTemplate(template.id, {
        name: `${template.name} (copy)`,
        scope: template.scope === 'tenant' && !canPublish ? 'personal' : template.scope,
      });
      setTemplates(previous => [copy, ...previous]);
      setSelectedId(copy.id);
      onToast(`Duplicated as “${copy.name}”.`);
    } catch (err: any) {
      onToast(err?.message ?? 'Could not duplicate the template.', 'error');
    }
  };

  const toggleArchive = async (template: ReportTemplate) => {
    try {
      const updated = await api.archiveReportTemplate(template.id, !template.isArchived);
      if (updated.isArchived && !includeArchived) {
        setTemplates(previous => previous.filter(candidate => candidate.id !== updated.id));
        setSelectedId(null);
      } else {
        setTemplates(previous => previous.map(candidate => (candidate.id === updated.id ? updated : candidate)));
      }
      onToast(updated.isArchived ? 'Template archived.' : 'Template restored.');
    } catch (err: any) {
      onToast(err?.message ?? 'Could not change the template state.', 'error');
    }
  };

  const remove = async (template: ReportTemplate) => {
    if (!window.confirm(`Delete “${template.name}”? Existing reports keep the text they were authored with.`)) return;
    try {
      await api.deleteReportTemplate(template.id);
      setTemplates(previous => previous.filter(candidate => candidate.id !== template.id));
      setSelectedId(null);
      onToast('Template deleted.');
    } catch (err: any) {
      onToast(err?.message ?? 'Could not delete the template.', 'error');
    }
  };

  const updateStructuredField = (index: number, patch: Partial<StructuredField>) => {
    setDraft(current =>
      current
        ? {
            ...current,
            structuredFields: current.structuredFields.map((field, position) =>
              position === index ? { ...field, ...patch } : field
            ),
          }
        : current
    );
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white border border-slate-200 rounded-3xl w-full max-w-6xl h-[88vh] shadow-2xl flex flex-col">
        <div className="flex items-center justify-between p-4 border-b border-slate-200">
          <div>
            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-1.5">
              <Layers className="w-4 h-4 text-purple-600" /> Reporting template library
            </h3>
            <p className="text-[11px] text-slate-500 mt-0.5">
              Versioned clinical content. The resolver picks the most specific match: procedure → procedure + age band →
              body region → modality → blank.
            </p>
          </div>
          <div className="flex items-center gap-2">
            {canEdit && (
              <button
                type="button"
                onClick={() => {
                  setSelectedId(null);
                  setDraft(blankDraft(modalities[0]?.id ?? 0));
                }}
                className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-md shadow-purple-600/30 cursor-pointer"
              >
                <Plus className="w-3.5 h-3.5" /> New template
              </button>
            )}
            <button type="button" onClick={onClose} aria-label="Close library" className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        <div className="flex-1 grid grid-cols-1 lg:grid-cols-5 min-h-0">
          {/* list */}
          <div className="lg:col-span-2 border-r border-slate-200 flex flex-col min-h-0">
            <div className="p-3 space-y-2 border-b border-slate-100">
              <div className="relative">
                <Search className="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" />
                <input
                  type="text"
                  value={query}
                  onChange={event => setQuery(event.target.value)}
                  placeholder="Search templates…"
                  className="w-full pl-8 p-2 text-xs rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
              </div>
              <div className="flex items-center gap-2 text-[11px]">
                <select
                  value={modalityFilter}
                  onChange={event => setModalityFilter(event.target.value === '' ? '' : Number(event.target.value))}
                  className="flex-1 p-1.5 rounded-lg border border-slate-300 cursor-pointer"
                >
                  <option value="">All modalities</option>
                  {modalities.map(modality => (
                    <option key={modality.id} value={modality.id}>
                      {modality.code} — {modality.name}
                    </option>
                  ))}
                </select>
                <label className="flex items-center gap-1 whitespace-nowrap text-slate-600">
                  <input
                    type="checkbox"
                    checked={includeArchived}
                    onChange={event => setIncludeArchived(event.target.checked)}
                    className="accent-purple-600 cursor-pointer"
                  />
                  Archived
                </label>
              </div>
            </div>

            <div className="flex-1 overflow-y-auto divide-y divide-slate-100">
              {loading && <p className="p-4 text-xs text-slate-500">Loading templates…</p>}
              {!loading && templates.length === 0 && (
                <p className="p-4 text-xs text-slate-500">
                  No templates yet. Create one here, or save a report you have written from the workspace.
                </p>
              )}
              {templates.map(template => (
                <button
                  key={template.id}
                  type="button"
                  onClick={() => setSelectedId(template.id)}
                  className={`w-full text-left p-3 hover:bg-slate-50 cursor-pointer ${
                    selectedId === template.id ? 'bg-purple-50/70' : ''
                  }`}
                >
                  <div className="flex items-center gap-1.5">
                    <span className="font-bold text-xs text-slate-900 truncate">{template.name}</span>
                    {template.isDefault && <Star className="w-3 h-3 text-amber-500 shrink-0" fill="currentColor" />}
                    {template.isArchived && (
                      <span className="text-[10px] font-bold uppercase px-1.5 py-0.5 rounded bg-slate-200 text-slate-600">
                        archived
                      </span>
                    )}
                  </div>
                  <div className="text-[10px] text-slate-500 mt-0.5">
                    {template.modalityCode ?? '—'} · v{template.version} · {template.scope}
                    {template.ageGroup ? ` · ${template.ageGroup}` : ''}
                    {template.bodyRegion ? ` · ${template.bodyRegion}` : ''}
                    {template.sex ? ` · ${template.sex}` : ''}
                    {template.contrast ? ` · ${template.contrast} contrast` : ''}
                  </div>
                  {template.serviceName && (
                    <div className="text-[10px] text-purple-700 mt-0.5">Procedure: {template.serviceName}</div>
                  )}
                </button>
              ))}
            </div>
          </div>

          {/* detail / editor */}
          <div className="lg:col-span-3 overflow-y-auto p-4 min-h-0">
            {error && <div className="mb-3 p-2 rounded-xl bg-rose-50 border border-rose-200 text-[11px] text-rose-800">{error}</div>}

            {!draft && (
              <div className="h-full flex flex-col items-center justify-center text-center text-slate-400">
                <FileText className="w-10 h-10 mb-2 text-purple-300" />
                <p className="text-xs max-w-sm">
                  Select a template to inspect its clinical text and structured fields, or create a new one.
                </p>
              </div>
            )}

            {draft && (
              <div className="space-y-4 text-xs">
                <div className="grid grid-cols-2 gap-3">
                  <div className="col-span-2">
                    <label htmlFor="template-name" className="block font-semibold text-slate-700 mb-1">
                      Name *
                    </label>
                    <input
                      id="template-name"
                      type="text"
                      value={draft.name}
                      onChange={event => setDraft({ ...draft, name: event.target.value })}
                      className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
                    />
                  </div>

                  <div>
                    <label htmlFor="template-modality" className="block font-semibold text-slate-700 mb-1">
                      Modality *
                    </label>
                    <select
                      id="template-modality"
                      value={draft.modalityId}
                      onChange={event => setDraft({ ...draft, modalityId: Number(event.target.value) })}
                      className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
                    >
                      {modalities.map(modality => (
                        <option key={modality.id} value={modality.id}>
                          {modality.code} — {modality.name}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label htmlFor="template-procedure" className="block font-semibold text-slate-700 mb-1">
                      Procedure (most specific)
                    </label>
                    <select
                      id="template-procedure"
                      value={draft.serviceId}
                      onChange={event =>
                        setDraft({ ...draft, serviceId: event.target.value === '' ? '' : Number(event.target.value) })
                      }
                      className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
                    >
                      <option value="">Any procedure for this modality</option>
                      {services
                        .filter(service => service.modalityId === draft.modalityId)
                        .map(service => (
                          <option key={service.id} value={service.id}>
                            {service.name}
                          </option>
                        ))}
                    </select>
                  </div>

                  <div>
                    <label htmlFor="template-body-region" className="block font-semibold text-slate-700 mb-1">
                      Body region
                    </label>
                    <input
                      id="template-body-region"
                      type="text"
                      value={draft.bodyRegion}
                      onChange={event => setDraft({ ...draft, bodyRegion: event.target.value })}
                      placeholder="e.g. brain, chest, abdomen"
                      className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
                    />
                  </div>

                  <div>
                    <label htmlFor="template-age-group" className="block font-semibold text-slate-700 mb-1">
                      Age band
                    </label>
                    <select
                      id="template-age-group"
                      value={draft.ageGroup}
                      onChange={event => setDraft({ ...draft, ageGroup: event.target.value })}
                      className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
                    >
                      {AGE_GROUP_OPTIONS.map(option => (
                        <option key={option.value} value={option.value}>
                          {option.label}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div>
                    <label htmlFor="template-sex" className="block font-semibold text-slate-700 mb-1">
                      Sex (only if clinically relevant)
                    </label>
                    <select
                      id="template-sex"
                      value={draft.sex}
                      onChange={event => setDraft({ ...draft, sex: event.target.value as TemplateDraft['sex'] })}
                      className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
                    >
                      <option value="">Any</option>
                      <option value="female">Female</option>
                      <option value="male">Male</option>
                    </select>
                  </div>

                  <div>
                    <label htmlFor="template-contrast" className="block font-semibold text-slate-700 mb-1">
                      Contrast / protocol
                    </label>
                    <select
                      id="template-contrast"
                      value={draft.contrast}
                      onChange={event => setDraft({ ...draft, contrast: event.target.value as TemplateDraft['contrast'] })}
                      className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
                    >
                      <option value="">Any</option>
                      <option value="without">Non-contrast</option>
                      <option value="with">With contrast</option>
                      <option value="both">Both</option>
                    </select>
                  </div>

                  <div>
                    <label htmlFor="template-scope" className="block font-semibold text-slate-700 mb-1">
                      Scope
                    </label>
                    <select
                      id="template-scope"
                      value={draft.scope}
                      onChange={event => setDraft({ ...draft, scope: event.target.value as TemplateDraft['scope'] })}
                      className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
                    >
                      <option value="personal">My private template</option>
                      <option value="tenant" disabled={!canPublish}>
                        Clinic-wide {canPublish ? '' : '(needs template permission)'}
                      </option>
                    </select>
                  </div>

                  <label className="flex items-center gap-2 mt-5 text-slate-700 font-semibold">
                    <input
                      type="checkbox"
                      checked={draft.isDefault}
                      onChange={event => setDraft({ ...draft, isDefault: event.target.checked })}
                      className="accent-purple-600 w-4 h-4 cursor-pointer"
                    />
                    Default for this modality/age combination
                  </label>
                </div>

                {/* clinical sections */}
                {(
                  [
                    ['clinicalHistory', 'Clinical indication'],
                    ['technique', 'Technique'],
                    ['findings', 'Findings baseline'],
                    ['impression', 'Impression baseline'],
                    ['recommendations', 'Recommendations'],
                  ] as Array<[keyof TemplateDraft, string]>
                ).map(([key, label]) => (
                  <div key={String(key)}>
                    <label htmlFor={`template-section-${String(key)}`} className="block font-semibold text-slate-700 mb-1">
                      {label}
                    </label>
                    <textarea
                      id={`template-section-${String(key)}`}
                      rows={key === 'findings' ? 5 : 2}
                      value={(draft[key] as string) ?? ''}
                      onChange={event => setDraft({ ...draft, [key]: event.target.value })}
                      className="w-full p-2 rounded-xl border border-slate-300 font-mono focus:outline-none focus:ring-1 focus:ring-purple-500"
                    />
                  </div>
                ))}

                {/* structured fields */}
                <div className="p-3 rounded-2xl bg-slate-50 border border-slate-200 space-y-2">
                  <div className="flex items-center justify-between">
                    <span className="font-black uppercase tracking-wide text-slate-700 text-[11px] flex items-center gap-1.5">
                      <Sparkles className="w-3.5 h-3.5 text-purple-600" /> Structured fields
                    </span>
                    <button
                      type="button"
                      onClick={() =>
                        setDraft({
                          ...draft,
                          structuredFields: [
                            ...draft.structuredFields,
                            { key: `field_${draft.structuredFields.length + 1}`, label: '', type: 'text' },
                          ],
                        })
                      }
                      className="text-[11px] font-bold text-purple-700 hover:underline cursor-pointer"
                    >
                      + Add field
                    </button>
                  </div>

                  {draft.structuredFields.length === 0 && (
                    <p className="text-[11px] text-slate-500">
                      Optional. Add measurement / laterality / severity controls where the study warrants them.
                    </p>
                  )}

                  {draft.structuredFields.map((field, index) => (
                    <div key={index} className="bg-white border border-slate-200 rounded-xl p-2 space-y-2">
                      <div className="grid grid-cols-2 gap-2">
                        <input
                          type="text"
                          value={field.label}
                          placeholder="Label, e.g. Appendix diameter"
                          onChange={event => updateStructuredField(index, { label: event.target.value })}
                          className="p-1.5 rounded-lg border border-slate-300"
                        />
                        <input
                          type="text"
                          value={field.key}
                          placeholder="key"
                          onChange={event => updateStructuredField(index, { key: event.target.value })}
                          className="p-1.5 rounded-lg border border-slate-300 font-mono"
                        />
                        <select
                          value={field.type}
                          onChange={event =>
                            updateStructuredField(index, { type: event.target.value as StructuredFieldType })
                          }
                          className="p-1.5 rounded-lg border border-slate-300 cursor-pointer"
                        >
                          {FIELD_TYPES.map(type => (
                            <option key={type} value={type}>
                              {type}
                            </option>
                          ))}
                        </select>
                        <input
                          type="text"
                          value={field.unit ?? ''}
                          placeholder="unit (mm, cm…)"
                          onChange={event => updateStructuredField(index, { unit: event.target.value })}
                          className="p-1.5 rounded-lg border border-slate-300"
                        />
                        {(field.type === 'select' || field.type === 'radio') && (
                          <input
                            type="text"
                            value={(field.options ?? []).join(', ')}
                            placeholder="Options, comma separated"
                            onChange={event =>
                              updateStructuredField(index, {
                                options: event.target.value
                                  .split(',')
                                  .map(option => option.trim())
                                  .filter(Boolean),
                              })
                            }
                            className="col-span-2 p-1.5 rounded-lg border border-slate-300"
                          />
                        )}
                        <input
                          type="text"
                          value={field.normalText ?? ''}
                          placeholder="Curated normal phrasing inserted by the Normal control"
                          onChange={event => updateStructuredField(index, { normalText: event.target.value })}
                          className="col-span-2 p-1.5 rounded-lg border border-slate-300"
                        />
                      </div>
                      <div className="flex items-center justify-between">
                        <label className="flex items-center gap-1.5 text-slate-600 font-semibold">
                          <input
                            type="checkbox"
                            checked={Boolean(field.required)}
                            onChange={event => updateStructuredField(index, { required: event.target.checked })}
                            className="accent-purple-600 cursor-pointer"
                          />
                          Required before signing
                        </label>
                        <button
                          type="button"
                          onClick={() =>
                            setDraft({
                              ...draft,
                              structuredFields: draft.structuredFields.filter((_, position) => position !== index),
                            })
                          }
                          className="text-[11px] font-bold text-rose-600 hover:underline cursor-pointer"
                        >
                          Remove
                        </button>
                      </div>
                    </div>
                  ))}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2 pt-1">
                  <div className="flex items-center gap-3">
                    {draft.id && (
                      <>
                        <button
                          type="button"
                          onClick={() => void duplicate(selected as ReportTemplate)}
                          className="flex items-center gap-1 font-bold text-slate-600 hover:underline cursor-pointer"
                        >
                          <Copy className="w-3.5 h-3.5" /> Duplicate
                        </button>
                        <button
                          type="button"
                          onClick={() => void toggleArchive(selected as ReportTemplate)}
                          className="flex items-center gap-1 font-bold text-slate-600 hover:underline cursor-pointer"
                        >
                          {selected?.isArchived ? (
                            <>
                              <ArchiveRestore className="w-3.5 h-3.5" /> Restore
                            </>
                          ) : (
                            <>
                              <Archive className="w-3.5 h-3.5" /> Archive
                            </>
                          )}
                        </button>
                        {canDelete && (
                          <button
                            type="button"
                            onClick={() => void remove(selected as ReportTemplate)}
                            className="font-bold text-rose-600 hover:underline cursor-pointer"
                          >
                            Delete
                          </button>
                        )}
                      </>
                    )}
                  </div>

                  <div className="flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => setDraft(null)}
                      className="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-300 font-semibold cursor-pointer"
                    >
                      Close preview
                    </button>
                    {canEdit && (
                      <button
                        type="button"
                        onClick={save}
                        disabled={saving}
                        className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-purple-600 text-white font-bold disabled:opacity-50 cursor-pointer"
                      >
                        <Save className="w-3.5 h-3.5" /> {saving ? 'Saving…' : draft.id ? 'Save new revision' : 'Create template'}
                      </button>
                    )}
                  </div>
                </div>

                {draft.id && (
                  <p className="text-[10px] text-slate-400">
                    Saving creates a new revision. Reports already authored against v{selected?.version ?? '—'} keep
                    their original text and record the revision they came from.
                  </p>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};
