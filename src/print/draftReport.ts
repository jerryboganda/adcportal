import type { PrintDocumentModel, PrintObservation, PrintSection, PrintSignature } from './types';

/**
 * Build a report document from the UNSAVED editor buffer.
 *
 * This is the one place a print document originates in the browser, and it exists
 * for one reason: a radiologist must be able to proof a working copy before it is
 * persisted. Everything visual — the letterhead, the patient block, the study
 * context, the paper geometry and the ENTIRE stylesheet — comes from a server
 * scaffold (`/print/report/{studyId}?draft=1`), so the draft is the real A4
 * document with a DRAFT mark, not a second layout maintained by hand.
 *
 * The buffer only supplies what the server cannot know: the narrative text, the
 * structured values, the critical flag and who is currently reading.
 */

export interface DraftReportInput {
  reportId: string | number | null;
  version?: number;
  clinicalHistory: string;
  technique: string;
  comparison: string;
  findings: string;
  impression: string;
  recommendations: string;
  criticalFlag: boolean;
  structuredRows: PrintObservation[];
  radiologistName: string;
  signedBy: string | null;
  signedAt?: string | null;
  statusLabel?: string | null;
}

function sectionsFromBuffer(input: DraftReportInput): PrintSection[] {
  const pairs: Array<[string, string, boolean]> = [
    ['Clinical indication', input.clinicalHistory, false],
    ['Technique', input.technique, false],
    ['Comparison', input.comparison, false],
    ['Findings', input.findings, false],
    ['Impression', input.impression, true],
    ['Recommendations', input.recommendations, false],
  ];

  return pairs
    .map(([label, body, emphasis]) => ({ label, body: (body ?? '').trim(), emphasis }))
    .filter((section) => section.body !== '')
    .map((section) => ({
      label: section.label,
      // The buffer's line breaks are clinical content: they are preserved, never
      // re-wrapped, exactly as the server does for a persisted report.
      body: section.body.replace(/\r\n/g, '\n'),
      emphasis: section.emphasis,
      tone: section.emphasis ? 'accent' : 'default',
    }));
}

export function buildDraftReportDocument(
  scaffold: PrintDocumentModel,
  input: DraftReportInput,
): PrintDocumentModel {
  const marks = scaffold.marks.filter((mark) => mark.code === 'DRAFT' || mark.code === 'REPRINT');
  if (input.criticalFlag && !marks.some((mark) => mark.code === 'CRITICAL')) {
    marks.push({ code: 'CRITICAL', label: 'CRITICAL FINDING', tone: 'critical' });
  }

  const signature: PrintSignature = {
    ...(scaffold.signature ?? {
      caption: 'Electronic signature',
      name: '',
      role: '',
      lines: [],
      statement: '',
      reference: '',
    }),
    // A working copy is never presented as signed, whatever the persisted row says.
    name: input.signedBy || input.radiologistName,
    statement: input.signedBy
      ? `Draft after the signed version by ${input.signedBy}${input.signedAt ? ` on ${input.signedAt}` : ''} — this working copy is not signed.`
      : 'Not signed — draft working copy.',
    reference: input.reportId ? `Draft of report R-${input.reportId}` : 'Unsaved working copy',
  };

  return {
    ...scaffold,
    artifact: 'report',
    artifactLabel: 'Radiology report',
    kindLabel: 'DRAFT REPORT',
    documentKey: input.reportId ? `R-${input.reportId}-draft` : scaffold.documentKey,
    title: 'Radiology Report — draft',
    status: {
      code: 'draft',
      label: input.reportId && input.version ? `Unsigned draft v${input.version}` : 'Unsaved draft',
      tone: 'warn',
    },
    marks,
    observations: input.structuredRows,
    sections: sectionsFromBuffer(input),
    criticalLogs: scaffold.criticalLogs,
    signature,
    notices: [
      ...scaffold.notices.filter((notice) => notice.tone !== 'critical'),
      {
        tone: 'critical',
        text: 'This is an UNSAVED working copy. It is not a signed report and must not be filed, dispatched or acted on as one.',
      },
    ],
  };
}
