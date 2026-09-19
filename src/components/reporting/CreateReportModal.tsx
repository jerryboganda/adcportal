import React, { useEffect, useMemo, useState } from 'react';
import { AlertTriangle, FilePlus2, Search, Sparkles, UserPlus, X } from 'lucide-react';
import {
  Appointment,
  Modality,
  Patient,
  Priority,
  RadiologyReport,
  Referrer,
  ReportTemplate,
  Service,
  TemplateMatch,
} from '../../types';
import * as api from '../../services/apiService';

/**
 * Create a report for a study that never came through scheduling: an external
 * CD, imported imaging, a paper request, or a retrospective read.
 *
 * Two rules drive the design:
 *  - the patient is picked from the MASTER patient registry (the same records
 *    reception uses), never a private reporting-side list, and
 *  - a real study row is created server-side, so the report lands in the
 *    patient's longitudinal record instead of floating free.
 */
export const CreateReportModal: React.FC<{
  patients: Patient[];
  services: Service[];
  modalities: Modality[];
  referrers: Referrer[];
  onClose: () => void;
  onCreated: (study: Appointment, report: RadiologyReport) => void;
  onError: (message: string) => void;
}> = ({ patients, services, modalities, referrers, onClose, onCreated, onError }) => {
  const activeModalities = useMemo(() => modalities.filter(modality => modality.isActive), [modalities]);
  const [modalityId, setModalityId] = useState<number>(activeModalities[0]?.id ?? 0);
  const [serviceId, setServiceId] = useState<number>(0);
  const [patientQuery, setPatientQuery] = useState('');
  const [patientId, setPatientId] = useState<string>('');
  const [newPatient, setNewPatient] = useState({ name: '', phone: '', gender: 'male' as Patient['gender'], age: '' });
  const [isNewPatient, setIsNewPatient] = useState(false);
  const [studyDate, setStudyDate] = useState(() => new Date().toISOString().slice(0, 10));
  const [priority, setPriority] = useState<Priority>('routine');
  const [indication, setIndication] = useState('');
  const [referrerId, setReferrerId] = useState<number | ''>('');
  const [templateMatch, setTemplateMatch] = useState<TemplateMatch | null>(null);
  const [templateOverride, setTemplateOverride] = useState<string>('');
  const [templates, setTemplates] = useState<ReportTemplate[]>([]);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const modalityServices = useMemo(
    () => services.filter(service => service.modalityId === modalityId),
    [modalityId, services]
  );

  useEffect(() => {
    if (modalityServices.length > 0 && !modalityServices.some(service => service.id === serviceId)) {
      setServiceId(modalityServices[0].id);
    }
  }, [modalityServices, serviceId]);

  const matches = useMemo(() => {
    const query = patientQuery.trim().toLowerCase();
    if (query === '') return patients.slice(0, 6);
    return patients
      .filter(
        patient =>
          patient.name.toLowerCase().includes(query) ||
          patient.mrn.toLowerCase().includes(query) ||
          (patient.phone ?? '').includes(query)
      )
      .slice(0, 8);
  }, [patientQuery, patients]);

  const selectedPatient = patients.find(patient => patient.id === patientId) ?? null;

  // Resolve the clinic's baseline for the chosen examination — and explain it,
  // so a radiologist never wonders where the text came from.
  useEffect(() => {
    if (!serviceId || !modalityId) {
      setTemplateMatch(null);
      return;
    }

    const age = isNewPatient ? Number(newPatient.age) || undefined : selectedPatient?.age;
    const gender = isNewPatient ? newPatient.gender : selectedPatient?.gender;

    let cancelled = false;
    api
      .resolveReportTemplate({
        serviceId,
        modalityId,
        ageGroup: api.ageGroupFor(age),
        sex: gender === 'male' || gender === 'female' ? gender : undefined,
      })
      .then(match => {
        if (!cancelled) setTemplateMatch(match);
      })
      .catch(() => {
        if (!cancelled) setTemplateMatch(null);
      });

    return () => {
      cancelled = true;
    };
  }, [isNewPatient, modalityId, newPatient.age, newPatient.gender, selectedPatient?.age, selectedPatient?.gender, serviceId]);

  useEffect(() => {
    api
      .fetchReportingTemplates({ modalityId: modalityId || undefined })
      .then(payload => setTemplates(payload.templates))
      .catch(() => setTemplates([]));
  }, [modalityId]);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setError(null);

    if (!serviceId) {
      setError('Choose the examination that was performed.');
      return;
    }
    if (!isNewPatient && !patientId) {
      setError('Select the patient from the registry, or switch to “New patient”.');
      return;
    }
    if (isNewPatient && !newPatient.name.trim()) {
      setError('A patient name is required.');
      return;
    }

    setSubmitting(true);
    try {
      const { study, report } = await api.createManualReport({
        patientId: isNewPatient ? undefined : patientId,
        newPatient: isNewPatient
          ? {
              name: newPatient.name.trim(),
              phone: newPatient.phone.trim() || undefined,
              gender: newPatient.gender,
              age: newPatient.age ? Number(newPatient.age) : undefined,
              dob: studyDate,
            }
          : undefined,
        serviceId,
        referrerId: referrerId === '' ? undefined : Number(referrerId),
        date: studyDate,
        studyDate,
        priority,
        indication: indication.trim() || undefined,
        templateId: templateOverride || undefined,
      });

      onCreated(study, report);
      onClose();
    } catch (err: any) {
      if (err?.status === 422) {
        const first = err?.raw?.response?.data?.errors
          ? Object.values(err.raw.response.data.errors)[0]
          : null;
        setError((first as string) ?? 'The server rejected these details.');
      } else {
        onError(err?.message ?? 'Could not create the report.');
      }
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-start justify-center p-4 overflow-y-auto">
      <form
        onSubmit={submit}
        data-testid="create-report-modal"
        className="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full shadow-2xl"
      >
        <div className="flex items-start justify-between p-4 border-b border-slate-200">
          <div>
            <h3 className="font-bold text-slate-900 text-sm flex items-center gap-1.5">
              <FilePlus2 className="w-4 h-4 text-purple-600" /> Create new report
            </h3>
            <p className="text-[11px] text-slate-500 mt-0.5">
              For external, imported or offline examinations. A study record is created so the report joins the
              patient&apos;s history.
            </p>
          </div>
          <button type="button" onClick={onClose} aria-label="Close" className="p-1 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer">
            <X className="w-4 h-4" />
          </button>
        </div>

        <div className="p-4 space-y-4 text-xs max-h-[70vh] overflow-y-auto">
          {error && (
            <div className="p-2 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 flex items-start gap-2">
              <AlertTriangle className="w-3.5 h-3.5 mt-0.5 shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {/* Patient */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <span className="font-black uppercase tracking-wide text-slate-700 text-[11px]">Patient</span>
              <button
                type="button"
                onClick={() => setIsNewPatient(current => !current)}
                className="text-[11px] font-bold text-purple-700 hover:underline flex items-center gap-1 cursor-pointer"
              >
                {isNewPatient ? (
                  <>
                    <Search className="w-3 h-3" /> Search the registry instead
                  </>
                ) : (
                  <>
                    <UserPlus className="w-3 h-3" /> Register a new patient
                  </>
                )}
              </button>
            </div>

            {isNewPatient ? (
              <div className="grid grid-cols-2 gap-2">
                <input
                  type="text"
                  placeholder="Full name *"
                  value={newPatient.name}
                  onChange={event => setNewPatient(previous => ({ ...previous, name: event.target.value }))}
                  className="col-span-2 p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
                <input
                  type="text"
                  placeholder="Phone"
                  value={newPatient.phone}
                  onChange={event => setNewPatient(previous => ({ ...previous, phone: event.target.value }))}
                  className="p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
                <input
                  type="number"
                  min={0}
                  max={130}
                  placeholder="Age (years)"
                  value={newPatient.age}
                  onChange={event => setNewPatient(previous => ({ ...previous, age: event.target.value }))}
                  className="p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
                <select
                  value={newPatient.gender}
                  onChange={event =>
                    setNewPatient(previous => ({ ...previous, gender: event.target.value as Patient['gender'] }))
                  }
                  className="p-2 rounded-xl border border-slate-300 cursor-pointer"
                >
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
            ) : (
              <div className="space-y-2">
                <div className="relative">
                  <Search className="w-3.5 h-3.5 absolute left-2.5 top-2.5 text-slate-400" />
                  <input
                    type="text"
                    placeholder="Search by name, MRN or phone…"
                    value={patientQuery}
                    onChange={event => setPatientQuery(event.target.value)}
                    className="w-full pl-8 p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
                  />
                </div>

                {selectedPatient ? (
                  <div className="flex items-center justify-between p-2 rounded-xl bg-purple-50 border border-purple-200">
                    <div>
                      <div className="font-bold text-slate-900">{selectedPatient.name}</div>
                      <div className="text-[11px] text-slate-600">
                        MRN <span className="font-mono">{selectedPatient.mrn}</span> · {selectedPatient.age} y ·{' '}
                        {selectedPatient.gender}
                      </div>
                    </div>
                    <button type="button" onClick={() => setPatientId('')} className="text-[11px] font-bold text-purple-700 hover:underline cursor-pointer">
                      Change
                    </button>
                  </div>
                ) : (
                  <div className="max-h-40 overflow-y-auto border border-slate-200 rounded-xl divide-y divide-slate-100">
                    {matches.length === 0 && (
                      <p className="p-2 text-slate-500">
                        No registry match. Use “Register a new patient” — duplicates are checked server-side.
                      </p>
                    )}
                    {matches.map(patient => (
                      <button
                        key={patient.id}
                        type="button"
                        onClick={() => setPatientId(patient.id)}
                        className="w-full text-left p-2 hover:bg-slate-50 cursor-pointer"
                      >
                        <div className="font-semibold text-slate-800">{patient.name}</div>
                        <div className="text-[11px] text-slate-500">
                          MRN <span className="font-mono">{patient.mrn}</span> · {patient.age} y · {patient.gender}
                          {patient.phone ? ` · ${patient.phone}` : ''}
                        </div>
                      </button>
                    ))}
                  </div>
                )}
              </div>
            )}
          </div>

          {/* Examination */}
          <div className="grid grid-cols-2 gap-2">
            <div>
              <label htmlFor="create-report-modality" className="block font-semibold text-slate-700 mb-1">
                Modality *
              </label>
              <select
                id="create-report-modality"
                value={modalityId}
                onChange={event => setModalityId(Number(event.target.value))}
                className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
              >
                {activeModalities.map(modality => (
                  <option key={modality.id} value={modality.id}>
                    {modality.code} — {modality.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="create-report-procedure" className="block font-semibold text-slate-700 mb-1">
                Examination / procedure *
              </label>
              <select
                id="create-report-procedure"
                value={serviceId}
                onChange={event => setServiceId(Number(event.target.value))}
                className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
              >
                {modalityServices.length === 0 && <option value={0}>No catalog service for this modality</option>}
                {modalityServices.map(service => (
                  <option key={service.id} value={service.id}>
                    {service.name}
                  </option>
                ))}
              </select>
            </div>

            <div>
              <label htmlFor="create-report-study-date" className="block font-semibold text-slate-700 mb-1">
                Study date *
              </label>
              <input
                id="create-report-study-date"
                type="date"
                value={studyDate}
                onChange={event => setStudyDate(event.target.value)}
                className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>

            <div>
              <label htmlFor="create-report-priority" className="block font-semibold text-slate-700 mb-1">
                Priority
              </label>
              <select
                id="create-report-priority"
                value={priority}
                onChange={event => setPriority(event.target.value as Priority)}
                className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
              >
                <option value="routine">Routine</option>
                <option value="urgent">Urgent</option>
                <option value="stat">STAT</option>
              </select>
            </div>

            <div className="col-span-2">
              <label htmlFor="create-report-referrer" className="block font-semibold text-slate-700 mb-1">
                Referring doctor
              </label>
              <select
                id="create-report-referrer"
                value={referrerId}
                onChange={event => setReferrerId(event.target.value === '' ? '' : Number(event.target.value))}
                className="w-full p-2 rounded-xl border border-slate-300 cursor-pointer"
              >
                <option value="">Self / Walk-in / External</option>
                {referrers.map(referrer => (
                  <option key={referrer.id} value={referrer.id}>
                    Dr. {referrer.name}
                    {referrer.specialty ? ` — ${referrer.specialty}` : ''}
                  </option>
                ))}
              </select>
            </div>

            <div className="col-span-2">
              <label
                htmlFor="create-report-indication"
                className="block font-semibold text-slate-700 mb-1"
              >
                Clinical indication
              </label>
              <textarea
                id="create-report-indication"
                data-testid="create-report-indication"
                rows={2}
                value={indication}
                onChange={event => setIndication(event.target.value)}
                placeholder="e.g. Chronic headache, rule out space-occupying lesion."
                className="w-full p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>
          </div>

          {/* Template */}
          <div
            data-testid="create-report-template"
            className="p-3 rounded-2xl bg-slate-50 border border-slate-200 space-y-2"
          >
            <div className="flex items-center gap-1.5 font-black uppercase tracking-wide text-slate-700 text-[11px]">
              <Sparkles className="w-3.5 h-3.5 text-purple-600" /> Baseline template
            </div>
            {templateMatch?.matched ? (
              <p className="text-slate-600">
                <strong className="text-slate-900">{templateMatch.matched.name}</strong> — {templateMatch.explanation}
                {templateMatch.ageGroupLabel ? ` · ${templateMatch.ageGroupLabel}` : ''}
              </p>
            ) : (
              <p className="text-slate-500">
                No template matches this examination and age group — the report will open blank rather than with an
                unrelated baseline.
              </p>
            )}

            <select
              value={templateOverride}
              onChange={event => setTemplateOverride(event.target.value)}
              className="w-full p-2 rounded-xl border border-slate-300 bg-white cursor-pointer"
            >
              <option value="">
                {templateMatch?.matched ? `Use resolved: ${templateMatch.matched.name}` : 'Start blank (no template)'}
              </option>
              {templates.map(template => (
                <option key={template.id} value={template.id}>
                  {template.name}
                  {template.scope === 'personal' ? ' (mine)' : ''}
                  {template.ageGroup ? ` · ${template.ageGroup}` : ''}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="flex items-center justify-between gap-2 p-4 border-t border-slate-200">
          <span className="text-[11px] text-slate-500">
            Signed reports and templates are governed by the same RBAC as scheduled studies.
          </span>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={onClose}
              className="px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-300 text-xs font-semibold cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              data-testid="create-report-submit"
              disabled={submitting}
              className="px-4 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-md shadow-purple-600/30 disabled:opacity-50 cursor-pointer"
            >
              {submitting ? 'Creating…' : 'Create & open report'}
            </button>
          </div>
        </div>
      </form>
    </div>
  );
};
