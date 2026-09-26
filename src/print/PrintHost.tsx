import React, { useEffect, useLayoutEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { AlertTriangle } from 'lucide-react';
import { PrintDocumentView } from './PrintDocumentView';
import { PrintPreviewShell } from './PrintPreviewShell';
import { waitForPrintReady } from './readiness';
import { useDocumentStyles } from './useDocumentStyles';
import { printStore, useActivePrintDocument, usePrintError, PAPER_FALLBACK_MM } from './store';

/**
 * The document host — the only thing in the application that goes on paper.
 *
 * Mounted once at the root, it renders the active document into `<body>` (so
 * nothing in the app's layout can clip or reposition it), injects that document's
 * own stylesheet and `@page` rule into `<head>`, waits until the document can be
 * painted as designed and only then lets the print dialog open.
 *
 * `mode="print"` renders off-screen (still laid out and painted, which is what
 * makes the readiness gate meaningful). `mode="preview"` renders on screen with
 * the toolbar — and printing from the preview prints the very same element, so
 * the operator's preview and their paper cannot disagree.
 *
 * The old architecture had eight components calling `window.print()` from inside
 * the app shell, and a global stylesheet that hid everything except one
 * `.print-overlay` — so every print surface but that one produced a blank page.
 */
export const PrintHost: React.FC = () => {
  const active = useActivePrintDocument();
  const printError = usePrintError();
  const documentRef = useRef<HTMLDivElement | null>(null);
  const shellRef = useRef<HTMLDivElement | null>(null);
  const [scale, setScale] = useState(1);
  const [ready, setReady] = useState(false);

  const document = active?.document ?? null;
  const mode = active?.mode ?? 'print';
  const token = active?.token ?? 0;

  // A failure is shown BEFORE the `!document` bail: the common case is a fetch
  // that never produced a document at all (a 403 from the artifact registry, a
  // 404, a dropped connection), so the panel has to be reachable with nothing
  // active.
  const errorPanel = printError
    ? createPortal(
      <div className="pd-print-host pd-print-host--error" data-print-mode="error" role="alert">
        <div className="pd-shell-error">
          <AlertTriangle className="w-5 h-5" />
          <div>
            <div className="pd-shell-error-title">Could not prepare this document</div>
            <div className="pd-shell-error-body">{printError}</div>
          </div>
          <button type="button" className="pd-shell-btn" onClick={() => printStore.clear()}>
            Close
          </button>
        </div>
      </div>,
      window.document.body,
    )
    : null;

  // The document's stylesheet travels inside the payload (rendered from the same
  // blade partial the PDF engines use), so the preview, the browser print and the
  // PDF are styled by ONE file rather than three that must be kept in step.
  useDocumentStyles(document);

  // Presentation-only scaling: the document keeps its physical width, the preview
  // shrinks it to fit the screen. `transform` never appears inside the document.
  useLayoutEffect(() => {
    if (!document || mode !== 'preview') {
      setScale(1);

      return;
    }

    const measure = () => {
      const available = shellRef.current?.clientWidth ?? window.innerWidth;
      const paperPx = (document.render.widthMm * 96) / 25.4;
      const next = Math.min(1, (available - 48) / paperPx);
      setScale(Number.isFinite(next) && next > 0.2 ? next : 1);
    };

    measure();
    window.addEventListener('resize', measure);

    return () => window.removeEventListener('resize', measure);
  }, [document, mode]);

  useEffect(() => {
    if (!document) {
      return;
    }

    let cancelled = false;
    setReady(false);

    void (async () => {
      await waitForPrintReady(documentRef.current);
      if (cancelled) {
        return;
      }
      setReady(true);
      // `document` is the print document here, so the DOM node is reached
      // through `window` — a shadowing bug that used to throw at runtime.
      window.document.body.dataset.printReady = 'true';
      window.document.body.dataset.printArtifact = document.artifact;
      window.document.body.dataset.printPaper = document.paper;
      printStore.markReady();
    })();

    return () => {
      cancelled = true;
      delete window.document.body.dataset.printReady;
    };
    // Re-run for every activation (token), not merely for a new document object.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token]);

  if (printError) {
    return errorPanel;
  }

  if (!document) {
    return null;
  }

  const geometry = document.render ?? {
    widthMm: PAPER_FALLBACK_MM[document.paper].width,
    heightMm: PAPER_FALLBACK_MM[document.paper].height,
    safeWidthMm: PAPER_FALLBACK_MM[document.paper].width,
    contentWidthMm: PAPER_FALLBACK_MM[document.paper].width,
    feedMm: 0,
    fontScale: 1,
    margins: { top: 0, right: 0, bottom: 0, left: 0 },
    paper: document.paper,
    label: document.paper,
  };

  if (active?.error) {
    return errorPanel;
  }

  return createPortal(
    <div
      className={`pd-print-host pd-print-host--${mode}`}
      data-print-mode={mode}
      data-print-artifact={document.artifact}
      data-print-paper={document.paper}
      data-print-key={document.documentKey}
      data-print-ready={ready ? 'true' : 'false'}
    >
      <style
        // The @page rule must be parseable before printing; keeping it in the
        // host as well as in <head> also survives a fragment-only render in tests.
        dangerouslySetInnerHTML={{ __html: document.pageCss }}
      />
      {mode === 'preview' ? (
        <PrintPreviewShell document={document} shellRef={shellRef} scale={scale} />
      ) : null}

      <div className={`pd-print-surface${mode === 'print' ? ' pd-print-surface--offscreen' : ''}`}>
        <div className="pd-preview-scale" style={mode === 'preview' ? { transform: `scale(${scale})` } : undefined}>
          <div ref={documentRef} data-testid="print-document">
            <PrintDocumentView document={document} />
          </div>
        </div>
      </div>

      {mode === 'preview' ? (
        <div className="pd-shell-hint pd-no-print">
          Print at 100% scale with the browser's “Headers and footers” switched off for a clean sheet of{' '}
          {document.render.label} ({geometry.widthMm} mm wide).
        </div>
      ) : null}
    </div>,
    window.document.body,
  );
};
