import { fetchPrintDocument, recordPrintEvent, type PrintRequestOptions } from './printService';
import { printStore } from './store';
import type { PrintArtifact, PrintDocumentModel, PrintPaper } from './types';

/**
 * The one way anything in the application prints.
 *
 * ```
 * printDocument({ artifact: 'receipt', id: invoice.id });
 * openPrintPreview({ artifact: 'invoice', id: invoice.id });
 * ```
 *
 * Callers do not fetch a template, do not build markup and do not own a paper
 * size: the artifact registry decides the paper, the server builds the document
 * and the host renders it. That is what removed eight independent print
 * implementations — and with them the reason the preview and the page could
 * disagree.
 *
 * A document built in the browser (an unsaved report draft) can be handed in
 * directly; it goes through the SAME renderer, which is why a draft print is
 * visually the final document marked DRAFT rather than a different design.
 */

export interface PrintRequest {
  artifact: PrintArtifact;
  id: string | number;
  paper?: PrintPaper;
  options?: PrintRequestOptions;
  document?: PrintDocumentModel;
}

const AFTER_PRINT_TIMEOUT_MS = 30_000;

function waitForAfterPrint(): Promise<void> {
  return new Promise<void>((resolve) => {
    let settled = false;

    const finish = () => {
      if (settled) {
        return;
      }
      settled = true;
      window.removeEventListener('afterprint', finish);

      // Some engines fire `afterprint` before the dialog closes; a short delay
      // keeps the document mounted while the print job is still being spooled.
      window.setTimeout(() => resolve(), 250);
    };

    window.addEventListener('afterprint', finish, { once: true });
    window.setTimeout(finish, AFTER_PRINT_TIMEOUT_MS);
  });
}

/**
 * The message an operator gets when a document will not load.
 *
 * Almost every failure here is a 403 — the artifact registry gates printing as
 * its own grant, so a role without `label print` or `receipt print` is refused —
 * and a bare "failed" teaches the operator nothing about what to do next.
 */
function describePrintFailure(error: unknown): string {
    const status = (error as { status?: number; response?: { status?: number } })?.status
        ?? (error as { response?: { status?: number } })?.response?.status;

    if (status === 403) {
        return 'You do not have permission to print this document. An administrator can grant it under Settings → Users & RBAC.';
    }
    if (status === 404) {
        return 'This document no longer exists.';
    }

    const message = (error as { message?: string })?.message;

    return message && message.trim() !== '' ? message : 'The document could not be prepared for printing.';
}

async function resolveDocument(request: PrintRequest): Promise<PrintDocumentModel> {
    if (request.document) {
        return request.document;
    }

    return fetchPrintDocument(request.artifact, request.id, { paper: request.paper, ...request.options });
}

/**
 * Render the document off-screen, wait until it can be painted as designed, then
 * open the print dialog. Resolves once the dialog has closed (or the print was
 * aborted), so a caller can reprint or chain a follow-up.
 */
export async function printDocument(request: PrintRequest): Promise<void> {
    let document: PrintDocumentModel;

    try {
        document = await resolveDocument(request);
    } catch (error) {
        // Never let this escape: every call site is `void openPrintPreview(...)`,
        // so an unhandled rejection here is an operator who clicked Print and
        // saw nothing happen, with the real reason only in the console.
        printStore.fail(describePrintFailure(error));

        return;
    }

    const ready = printStore.activate({
        mode: 'print',
        document,
        artifact: request.artifact,
        documentId: String(request.id),
        serverBacked: !request.document,
    });

    await ready;

    if (printStore.getError()) {
        return;
    }

  try {
    window.print();
    await waitForAfterPrint();
  } finally {
    printStore.clear();
  }

  if (!request.document) {
    void recordPrintEvent(
      request.artifact,
      request.id,
      request.options?.reprint ? 'reprinted' : 'printed',
      document.paper,
    );
  }
}

/** Open the in-app preview; the operator prints from there. */
export async function openPrintPreview(request: PrintRequest): Promise<void> {
    let document: PrintDocumentModel;

    try {
        document = await resolveDocument(request);
    } catch (error) {
        printStore.fail(describePrintFailure(error));

        return;
    }

    await printStore.activate({
        mode: 'preview',
        document,
        artifact: request.artifact,
        documentId: String(request.id),
        serverBacked: !request.document,
    });
}

/** Re-open the preview for an already fetched document with different paper. */
export async function repreviewWithPaper(
    request: PrintRequest,
    paper: PrintPaper,
): Promise<void> {
    let document: PrintDocumentModel;

    try {
        document = await resolveDocument({ ...request, paper });
    } catch (error) {
        printStore.fail(describePrintFailure(error));

        return;
    }

    await printStore.activate({
        mode: 'preview',
        document,
        artifact: request.artifact,
        documentId: String(request.id),
        serverBacked: !request.document,
    });
}

/** Print whatever the preview is currently showing — the same pixels. */
export async function printActiveDocument(): Promise<void> {
  const active = printStore.get();
  if (!active) {
    return;
  }

  window.print();
  await waitForAfterPrint();

  void recordPrintEvent(active.artifact, active.documentId, 'printed', active.document.paper);
}

export function closePrintDocument(): void {
  printStore.clear();
}
