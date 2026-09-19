import React, { useMemo } from 'react';
import { AlertTriangle, Printer, X } from 'lucide-react';
import {
  Appointment,
  ClinicProfileSettings,
  RadiologyReport,
  StructuredField,
  StructuredValues,
} from '../../types';

/** Editor buffer handed to the print renderer so unsaved text can be proofed. */
export interface PrintDraft {
  clinicalHistory: string;
  technique: string;
  comparison: string;
  findings: string;
  impression: string;
  recommendations: string;
  criticalFlag: boolean;
  templateId?: string;
  structuredFields: StructuredField[];
  structuredValues: StructuredValues;
}

/**
 * Print preview / final document.
 *
 * The document is rendered from the SAME data the editor holds, so the
 * radiologist proof-reads exactly what will be filed. Printing here does not
 * create or duplicate any record — it is a rendering of an existing report (or
 * of the unsaved buffer, clearly marked as a draft).
 */
export const ReportPrintSheet: React.FC<{
  appointment: Appointment;
  report: RadiologyReport | null;
  draft: PrintDraft;
  clinicSettings?: ClinicProfileSettings;
  radiologistName: string;
  onClose: () => void;
}> = ({ appointment, report, draft, clinicSettings, radiologistName, onClose }) => {
  const signed = Boolean(report?.isSigned);

  const structuredRows = useMemo(
    () =>
      draft.structuredFields
        .map(field => ({ field, value: draft.structuredValues[field.key] }))
        .filter(row => row.value !== undefined && row.value !== '' && row.value !== false)
        .map(row => ({
          label: row.field.label,
          display: row.value === true ? 'Yes' : `${row.value}${row.field.unit ? ` ${row.field.unit}` : ''}`,
        })),
    [draft.structuredFields, draft.structuredValues]
  );

  const sections: Array<{ label: string; body: string }> = [
    { label: 'Clinical indication', body: draft.clinicalHistory },
    { label: 'Technique', body: draft.technique },
    { label: 'Comparison', body: draft.comparison },
    { label: 'Findings', body: draft.findings },
    { label: 'Impression', body: draft.impression },
    { label: 'Recommendations', body: draft.recommendations },
  ].filter(section => section.body && section.body.trim() !== '');

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-start justify-center p-4 overflow-y-auto">
      <div className="bg-white border border-slate-200 rounded-3xl max-w-3xl w-full shadow-2xl">
        <div className="no-print flex items-center justify-between p-4 border-b border-slate-200">
          <div>
            <h3 className="font-bold text-slate-900 text-sm">Print preview</h3>
            <p className="text-[11px] text-slate-500">
              {signed
                ? `Signed ${report?.statusLabel} v${report?.version} — this is the record that will be filed.`
                : 'Unsaved working copy — anything printed now is marked DRAFT and is not a signed report.'}
            </p>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => window.print()}
              className="flex items-center gap-1.5 px-3.5 py-1.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold shadow-md shadow-purple-600/30 cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" /> Print
            </button>
            <button
              type="button"
              onClick={onClose}
              aria-label="Close print preview"
              className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 cursor-pointer"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* The printed document */}
        <div className="print-report-page p-4" data-testid="report-print-sheet">
          <div className="p-8 text-slate-900 text-[12px] leading-relaxed bg-white">
            {/* Letterhead */}
            <div className="flex items-start justify-between border-b-2 border-slate-800 pb-3">
              <div>
                <div className="text-xl font-black tracking-tight">{clinicSettings?.name ?? 'Diagnostic Centre'}</div>
                {clinicSettings?.headerTagline && (
                  <div className="text-[10px] uppercase tracking-widest text-slate-600">{clinicSettings.headerTagline}</div>
                )}
                <div className="text-[10px] text-slate-600 mt-1">
                  {[clinicSettings?.branch, clinicSettings?.address, clinicSettings?.city].filter(Boolean).join(', ')}
                </div>
                <div className="text-[10px] text-slate-600">
                  {[clinicSettings?.phone && `Tel: ${clinicSettings.phone}`, clinicSettings?.email].filter(Boolean).join(' · ')}
                </div>
              </div>
              <div className="text-right text-[10px] text-slate-600">
                {clinicSettings?.pnraLicenseNo && <div>PNRA: {clinicSettings.pnraLicenseNo}</div>}
                {clinicSettings?.pmcRegistrationNo && <div>PMC: {clinicSettings.pmcRegistrationNo}</div>}
                {clinicSettings?.website && <div>{clinicSettings.website}</div>}
                <div className="font-black text-slate-900 text-sm mt-1 uppercase tracking-wide">
                  Radiology Report
                </div>
                {!signed && (
                  <div className="mt-1 inline-flex items-center gap-1 font-black text-rose-700 border border-rose-600 px-1.5 py-0.5">
                    <AlertTriangle className="w-3 h-3" /> DRAFT — NOT SIGNED
                  </div>
                )}
              </div>
            </div>

            {/* Patient + study identity */}
            <table className="w-full mt-3 text-[11px] border-collapse">
              <tbody>
                <tr>
                  <td className="py-0.5 pr-2 text-slate-500 w-24">Patient</td>
                  <td className="py-0.5 pr-4 font-bold">{appointment.patient.name}</td>
                  <td className="py-0.5 pr-2 text-slate-500 w-24">MRN</td>
                  <td className="py-0.5 font-mono">{appointment.patient.mrn}</td>
                </tr>
                <tr>
                  <td className="py-0.5 pr-2 text-slate-500">Age / Sex</td>
                  <td className="py-0.5 pr-4">
                    {appointment.patient.age != null ? `${appointment.patient.age} y` : '—'} / {appointment.patient.gender}
                  </td>
                  <td className="py-0.5 pr-2 text-slate-500">DOB</td>
                  <td className="py-0.5">{appointment.patient.dob || '—'}</td>
                </tr>
                <tr>
                  <td className="py-0.5 pr-2 text-slate-500">Examination</td>
                  <td className="py-0.5 pr-4 font-semibold">{appointment.service.name}</td>
                  <td className="py-0.5 pr-2 text-slate-500">Modality</td>
                  <td className="py-0.5">{appointment.modality?.code ?? '—'}</td>
                </tr>
                <tr>
                  <td className="py-0.5 pr-2 text-slate-500">Study date</td>
                  <td className="py-0.5 pr-4">
                    {appointment.date} {appointment.time}
                  </td>
                  <td className="py-0.5 pr-2 text-slate-500">Token</td>
                  <td className="py-0.5 font-mono">#{appointment.tokenNumber}</td>
                </tr>
                <tr>
                  <td className="py-0.5 pr-2 text-slate-500">Referred by</td>
                  <td className="py-0.5 pr-4">{appointment.referrer ? `Dr. ${appointment.referrer.name}` : 'Self / Walk-in'}</td>
                  <td className="py-0.5 pr-2 text-slate-500">Priority</td>
                  <td className="py-0.5 uppercase">{appointment.priority}</td>
                </tr>
              </tbody>
            </table>

            {/* Structured observations */}
            {structuredRows.length > 0 && (
              <div className="mt-4">
                <div className="text-[10px] font-black uppercase tracking-widest text-slate-700 border-b border-slate-300 pb-0.5">
                  Observations
                </div>
                <table className="w-full text-[11px] border-collapse mt-1">
                  <tbody>
                    {structuredRows.map(row => (
                      <tr key={row.label} className="align-top">
                        <td className="py-0.5 pr-3 text-slate-600 w-56">{row.label}</td>
                        <td className="py-0.5 font-semibold">{row.display}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {/* Narrative sections */}
            {sections.map(section => (
              <div key={section.label} className="mt-4">
                <div
                  className={`text-[10px] font-black uppercase tracking-widest border-b pb-0.5 ${
                    section.label === 'Impression' ? 'text-purple-900 border-purple-300' : 'text-slate-700 border-slate-300'
                  }`}
                >
                  {section.label}
                </div>
                <p className={`mt-1 whitespace-pre-wrap ${section.label === 'Impression' ? 'font-semibold' : ''}`}>
                  {section.body}
                </p>
              </div>
            ))}

            {draft.criticalFlag && (
              <div className="mt-4 border border-rose-600 text-rose-800 p-2 text-[10px] font-bold">
                CRITICAL FINDING — documented communication protocol applies to this study.
              </div>
            )}

            {clinicSettings?.reportLegalDisclaimer && (
              <p className="mt-5 text-[9px] text-slate-500 border-t border-slate-200 pt-2">
                {clinicSettings.reportLegalDisclaimer}
              </p>
            )}

            {/* Signature block */}
            <div className="mt-8 flex justify-between items-end text-[11px]">
              <div>
                {report?.signedBy && (
                  <>
                    <div className="font-bold text-slate-900">{report.signedBy}</div>
                    <div className="text-slate-600">Consultant Radiologist</div>
                    {clinicSettings?.pmcRegistrationNo && (
                      <div className="text-slate-500 text-[10px]">PMC Reg: {clinicSettings.pmcRegistrationNo}</div>
                    )}
                    <div className="text-slate-500 text-[10px]">{clinicSettings?.name}</div>
                  </>
                )}
              </div>
              <div className="text-right">
                <div className="border-t border-slate-800 pt-0.5 w-56">Electronic signature</div>
                <div className="text-slate-500 text-[10px]">
                  {report?.signedAt
                    ? `${report.signedAt}${signed ? ` · ${report.statusLabel} v${report.version}` : ''}`
                    : `${radiologistName} · not signed`}
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};
