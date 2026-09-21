import React, { useEffect, useState } from 'react';
import { Download, Loader2, Printer, Ruler, Save, X } from 'lucide-react';
import { fetchPrintRegistry, printPdfUrl, printDeviceId, savePrintSettings } from './printService';
import { closePrintDocument, printActiveDocument, repreviewWithPaper } from './printDocument';
import { printStore, useActivePrintDocument } from './store';
import type { PrintArtifactMeta, PrintDocumentModel, PrintPaper } from './types';

/**
 * The preview toolbar.
 *
 * Every printable document opens in this ONE shell: Preview → Print → Download
 * PDF, plus the paper selector when the artifact genuinely supports more than one
 * format (`Invoice: A4 / 80 mm`) — and never a choice that would put a radiology
 * report on a receipt roll.
 *
 * The calibration panel is here because the thermal safe width is a property of
 * the printer in front of the operator, not a platform constant: a counter whose
 * roll only feeds 58 mm can fix its own receipts without a support ticket, and
 * the value is stored against this workstation so it survives a reload and a
 * shift change.
 */

// One registry fetch per session: the artifact → paper mapping is static.
let registryCache: PrintArtifactMeta[] | null = null;
let registryPromise: Promise<PrintArtifactMeta[]> | null = null;

function useArtifactMeta(artifact: PrintDocumentModel['artifact']): PrintArtifactMeta | null {
  const [meta, setMeta] = useState<PrintArtifactMeta | null>(
    () => registryCache?.find((entry) => entry.artifact === artifact) ?? null,
  );

  useEffect(() => {
    if (registryCache) {
      setMeta(registryCache.find((entry) => entry.artifact === artifact) ?? null);

      return;
    }

    let cancelled = false;
    registryPromise ??= fetchPrintRegistry()
      .then((payload) => {
        registryCache = payload.artifacts;

        return payload.artifacts;
      })
      .catch(() => {
        registryPromise = null;

        return [];
      });

    void registryPromise.then((artifacts) => {
      if (!cancelled) {
        setMeta(artifacts.find((entry) => entry.artifact === artifact) ?? null);
      }
    });

    return () => {
      cancelled = true;
    };
  }, [artifact]);

  return meta;
}

const PAPER_LABELS: Record<PrintPaper, string> = {
  a4: 'A4',
  thermal80: '80 mm receipt',
  label: 'Label tag',
};

export const PrintPreviewShell: React.FC<{
  document: PrintDocumentModel;
  shellRef: React.RefObject<HTMLDivElement | null>;
  scale: number;
}> = ({ document, shellRef, scale }) => {
  const active = useActivePrintDocument();
  const meta = useArtifactMeta(document.artifact);
  const [calibrationOpen, setCalibrationOpen] = useState(false);
  const [saving, setSaving] = useState(false);
  const [savedNote, setSavedNote] = useState('');
  const [draft, setDraft] = useState({
    label: '',
    safeWidthMm: document.render.safeWidthMm,
    feedMm: document.render.feedMm,
    fontScale: document.render.fontScale,
  });

  // A calibration saved at another desk must not be overwritten by a stale form
  // value when this operator prints a different document.
  useEffect(() => {
    setDraft((current) => ({
      ...current,
      safeWidthMm: document.render.safeWidthMm,
      feedMm: document.render.feedMm,
      fontScale: document.render.fontScale,
    }));
  }, [document.render.safeWidthMm, document.render.feedMm, document.render.fontScale]);

  const allowedPapers = meta?.allowedPapers ?? [document.paper];
  const isDraft = document.marks.some((mark) => mark.code === 'DRAFT');

  const saveCalibration = async () => {
    setSaving(true);
    setSavedNote('');

    try {
      await savePrintSettings({
        calibration: {
          [printDeviceId()]: {
            label: draft.label || 'This workstation',
            safeWidthMm: draft.safeWidthMm,
            feedMm: draft.feedMm,
            fontScale: draft.fontScale,
          },
        },
      });

      setSavedNote('Saved for this workstation.');

      if (active) {
        await repreviewWithPaper({ artifact: active.artifact, id: active.documentId }, document.paper);
      }
    } catch {
      setSavedNote('Could not save the calibration.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="pd-shell pd-no-print">
      <div className="pd-shell-bar">
        <div className="pd-shell-title">
          <span className="pd-shell-badge">{document.artifactLabel}</span>
          <span className="pd-shell-key">{document.documentKey}</span>
          {document.status ? <span className="pd-shell-status">{document.status.label}</span> : null}
          <span className="pd-shell-meta">
            {document.render.label}
            {document.paper === 'thermal80' ? ` · ${document.render.safeWidthMm} mm printable` : ''}
            {isDraft ? ' · draft' : ''}
          </span>
        </div>

        <div className="pd-shell-actions">
          {allowedPapers.length > 1 && active ? (
            <label className="pd-shell-select">
              <span>Format</span>
              <select
                value={document.paper}
                onChange={(event) => {
                  void repreviewWithPaper(
                    { artifact: active.artifact, id: active.documentId },
                    event.target.value as PrintPaper,
                  );
                }}
              >
                {allowedPapers.map((paper) => (
                  <option key={paper} value={paper}>
                    {PAPER_LABELS[paper]}
                  </option>
                ))}
              </select>
            </label>
          ) : null}

          {document.artifact === 'receipt' || document.artifact === 'label' || document.artifact === 'token' ? (
            <button type="button" className="pd-shell-btn" onClick={() => setCalibrationOpen((open) => !open)}>
              <Ruler className="pd-shell-icon" /> Calibrate
            </button>
          ) : null}

          {active?.serverBacked ? (
            <a className="pd-shell-btn" href={printPdfUrl(active.artifact, active.documentId, { paper: document.paper })}>
              <Download className="pd-shell-icon" /> Download PDF
            </a>
          ) : null}

          <button type="button" className="pd-shell-btn pd-shell-btn--primary" onClick={() => void printActiveDocument()}>
            <Printer className="pd-shell-icon" /> Print
          </button>

          <button type="button" className="pd-shell-btn" onClick={() => closePrintDocument()} aria-label="Close print preview">
            <X className="pd-shell-icon" />
          </button>
        </div>
      </div>

      {calibrationOpen ? (
        <div className="pd-shell-calibration">
          <div className="pd-shell-field">
            <label htmlFor="pd-cal-label">Printer / desk</label>
            <input
              id="pd-cal-label"
              value={draft.label}
              placeholder="Front desk"
              onChange={(event) => setDraft({ ...draft, label: event.target.value })}
            />
          </div>
          <div className="pd-shell-field">
            <label htmlFor="pd-cal-width">Printable width (mm)</label>
            <input
              id="pd-cal-width"
              type="number"
              min={48}
              max={80}
              step={0.5}
              value={draft.safeWidthMm}
              onChange={(event) => setDraft({ ...draft, safeWidthMm: Number(event.target.value) })}
            />
          </div>
          <div className="pd-shell-field">
            <label htmlFor="pd-cal-feed">Bottom feed (mm)</label>
            <input
              id="pd-cal-feed"
              type="number"
              min={0}
              max={30}
              step={0.5}
              value={draft.feedMm}
              onChange={(event) => setDraft({ ...draft, feedMm: Number(event.target.value) })}
            />
          </div>
          <div className="pd-shell-field">
            <label htmlFor="pd-cal-scale">Text size</label>
            <input
              id="pd-cal-scale"
              type="number"
              min={0.85}
              max={1.25}
              step={0.05}
              value={draft.fontScale}
              onChange={(event) => setDraft({ ...draft, fontScale: Number(event.target.value) })}
            />
          </div>
          <button type="button" className="pd-shell-btn pd-shell-btn--primary" onClick={() => void saveCalibration()} disabled={saving}>
            {saving ? <Loader2 className="pd-shell-icon animate-spin" /> : <Save className="pd-shell-icon" />} Save for this device
          </button>
          {savedNote ? <span className="pd-shell-note">{savedNote}</span> : null}
        </div>
      ) : null}

      <div className="pd-shell-preview" ref={shellRef}>
        <div className="pd-shell-scale-note">
          Preview at {(scale * 100).toFixed(0)}% — the document itself is {document.render.widthMm} mm wide.
        </div>
      </div>
    </div>
  );
};

/** Guard used by call sites that only want a preview when one can be shown. */
export function previewIsOpen(): boolean {
  return printStore.get() !== null;
}
