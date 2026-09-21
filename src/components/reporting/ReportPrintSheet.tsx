import React, { useEffect, useMemo, useRef, useState } from 'react';
import { AlertTriangle, Loader2 } from 'lucide-react';
import { buildDraftReportDocument, type DraftReportInput } from '../../print/draftReport';
import { openPrintPreview } from '../../print/printDocument';
import { fetchPrintDocument } from '../../print/printService';
import type { PrintObservation } from '../../print/types';
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
 * Proof-print the working copy.
 *
 * The document is rendered from the buffer the editor holds, so a radiologist can
 * check a report before filing it — but it is rendered by the SAME print engine
 * as everything else, from a server scaffold: the letterhead, patient identity,
 * study context, paper geometry and stylesheet all come from
 * `/print/report/{study}?draft=1`, and only the narrative comes from the buffer.
 *
 * The previous implementation owned a private copy of the report layout, which is
 * precisely how a proof print could look like a different document than the
 * filed one. Printing here still creates nothing: it is a rendering of a buffer.
 */
export const ReportPrintSheet: React.FC<{
  appointment: Appointment;
  report: RadiologyReport | null;
  draft: PrintDraft;
  clinicSettings?: ClinicProfileSettings;
  radiologistName: string;
  onClose: () => void;
}> = ({ appointment, report, draft, radiologistName, onClose }) => {
  const [error, setError] = useState<string | null>(null);
  const requested = useRef(false);

  const structuredRows = useMemo<PrintObservation[]>(
    () =>
      draft.structuredFields
        .map((field) => ({ field, value: draft.structuredValues[field.key] }))
        .filter((row) => row.value !== undefined && row.value !== '' && row.value !== false)
        .map((row) => ({
          label: row.field.label,
          display: row.value === true ? 'Yes' : `${row.value}${row.field.unit ? ` ${row.field.unit}` : ''}`,
        }))
        .map((row) => ({ label: row.label, value: row.display })),
    [draft.structuredFields, draft.structuredValues],
  );

  useEffect(() => {
    if (requested.current) {
      return;
    }
    requested.current = true;

    void (async () => {
      try {
        // The scaffold is the server's own A4 report document for this study:
        // branding, patient, study metadata, geometry and stylesheet.
        const scaffold = await fetchPrintDocument('report', appointment.id, { draft: true, paper: 'a4' });

        const input: DraftReportInput = {
          reportId: report?.id ?? null,
          version: report?.version,
          clinicalHistory: draft.clinicalHistory,
          technique: draft.technique,
          comparison: draft.comparison,
          findings: draft.findings,
          impression: draft.impression,
          recommendations: draft.recommendations,
          criticalFlag: draft.criticalFlag,
          structuredRows,
          radiologistName,
          signedBy: report?.signedBy ?? null,
          signedAt: report?.signedAt ?? null,
          statusLabel: report?.statusLabel ?? null,
        };

        await openPrintPreview({
          artifact: 'report',
          id: appointment.id,
          document: buildDraftReportDocument(scaffold, input),
        });

        // The engine owns the preview surface; the workspace only has to release
        // its own state so the same preview cannot be opened twice.
        onClose();
      } catch {
        setError('The draft could not be prepared for printing.');
        onClose();
      }
    })();
  }, [appointment.id, draft, onClose, radiologistName, report, structuredRows]);

  if (error) {
    return (
      <div className="fixed bottom-4 left-1/2 z-[70] -translate-x-1/2 rounded-xl border border-rose-300 bg-rose-50 px-4 py-2.5 text-xs font-semibold text-rose-800 shadow-lg">
        <span className="inline-flex items-center gap-2">
          <AlertTriangle className="w-3.5 h-3.5" /> {error}
        </span>
      </div>
    );
  }

  return (
    <div className="fixed bottom-4 left-1/2 z-[70] -translate-x-1/2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-xs font-semibold text-slate-700 shadow-lg">
      <span className="inline-flex items-center gap-2">
        <Loader2 className="w-3.5 h-3.5 animate-spin" /> Preparing the draft document…
      </span>
    </div>
  );
};
