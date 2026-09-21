import React, { useEffect, useRef, useState } from 'react';
import { AlertTriangle, Loader2, Printer, X } from 'lucide-react';
import { PrintDocumentView } from './PrintDocumentView';
import { fetchPrintDocument, printPdfUrl, recordPrintEvent } from './printService';
import { waitForPrintReady } from './readiness';
import { useDocumentStyles } from './useDocumentStyles';
import type { PrintArtifact, PrintDocumentModel, PrintPaper } from './types';

/**
 * `/print/{artifact}/{id}` — one document, no shell.
 *
 * A stable, authenticated URL that renders exactly the printable document: no
 * sidebar, no toolbar from the application, nothing that could appear on paper by
 * accident. It exists for three reasons:
 *
 *   1. a print view you can bookmark or hand to a colleague without giving them
 *      the whole application;
 *   2. an unambiguous target for print visual-regression tests (the screenshot
 *      IS the document);
 *   3. an escape hatch when a workstation's browser blocks printing from a modal.
 *
 * The renderer is the same `PrintDocumentView` the in-app preview uses, so this
 * route cannot become a second, differently-styled document.
 */
export const PrintDocumentRoute: React.FC = () => {
  const [document, setDocument] = useState<PrintDocumentModel | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [printing, setPrinting] = useState(false);
  const rootRef = useRef<HTMLDivElement | null>(null);

  const params = new URLSearchParams(window.location.search);
  const segments = window.location.pathname.split('/').filter(Boolean);
  const artifact = (segments[1] ?? '') as PrintArtifact;
  const documentId = segments[2] ?? '';
  const requestedPaper = (params.get('paper') as PrintPaper | null) ?? undefined;
  const autoPrint = params.get('print') === '1';

  useDocumentStyles(document);

  // Marks the shell as "this page IS a document", which is what the print
  // stylesheet keys on to put #root on paper instead of the host portal.
  useEffect(() => {
    window.document.body.classList.add('pd-route-active');

    return () => window.document.body.classList.remove('pd-route-active');
  }, []);

  useEffect(() => {
    let cancelled = false;

    void (async () => {
      try {
        const fetched = await fetchPrintDocument(artifact, documentId, { paper: requestedPaper });
        if (!cancelled) {
          setDocument(fetched);
        }
      } catch (cause) {
        if (!cancelled) {
          setError(
            (cause as { response?: { status?: number } })?.response?.status === 401
              ? 'Sign in to the application first, then open this print link again.'
              : 'This document could not be prepared.',
          );
        }
      }
    })();

    return () => {
      cancelled = true;
    };
  }, [artifact, documentId, requestedPaper]);

  // Readiness is published on the body so an automated test (and a support
  // engineer) can wait for the document rather than guess at a delay.
  useEffect(() => {
    if (!document) {
      return;
    }

    let cancelled = false;

    void (async () => {
      await waitForPrintReady(rootRef.current);
      if (cancelled) {
        return;
      }

      window.document.body.dataset.printReady = 'true';
      window.document.body.dataset.printArtifact = document.artifact;
      window.document.body.dataset.printPaper = document.paper;

      if (autoPrint) {
        setPrinting(true);
        window.print();
        await new Promise<void>((resolve) => window.setTimeout(resolve, 400));
        void recordPrintEvent(document.artifact, documentId, 'printed', document.paper);
        setPrinting(false);
      }
    })();

    return () => {
      cancelled = true;
      delete window.document.body.dataset.printReady;
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [document]);

  if (error) {
    return (
      <div className="pd-route-state" role="alert">
        <AlertTriangle className="w-5 h-5" />
        <div>
          <div className="pd-route-state-title">Document unavailable</div>
          <div className="pd-route-state-body">{error}</div>
        </div>
        <a className="pd-shell-btn" href="/">
          Back to the application
        </a>
      </div>
    );
  }

  if (!document) {
    return (
      <div className="pd-route-state">
        <Loader2 className="w-4 h-4 animate-spin" />
        <div className="pd-route-state-title">Preparing document…</div>
      </div>
    );
  }

  return (
    <div className="pd-route" ref={rootRef}>
      <div className="pd-shell-bar pd-no-print">
        <div className="pd-shell-title">
          <span className="pd-shell-badge">{document.artifactLabel}</span>
          <span className="pd-shell-key">{document.documentKey}</span>
          <span className="pd-shell-meta">{document.render.label}</span>
        </div>
        <div className="pd-shell-actions">
          <a className="pd-shell-btn" href={printPdfUrl(document.artifact, documentId, { paper: document.paper })}>
            Download PDF
          </a>
          <button
            type="button"
            className="pd-shell-btn pd-shell-btn--primary"
            disabled={printing}
            onClick={() => {
              window.print();
              void recordPrintEvent(document.artifact, documentId, 'printed', document.paper);
            }}
          >
            <Printer className="pd-shell-icon" /> Print
          </button>
          <a className="pd-shell-btn" href="/" aria-label="Close">
            <X className="pd-shell-icon" />
          </a>
        </div>
      </div>

      <div className="pd-route-paper">
        <PrintDocumentView document={document} />
      </div>
    </div>
  );
};
