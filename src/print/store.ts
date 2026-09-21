import { useSyncExternalStore } from 'react';
import type { PrintArtifact, PrintDocumentModel, PrintPaper } from './types';

/**
 * The print host store.
 *
 * Printing is imperative (`printDocument(...)` from a click handler) while the
 * document is React-rendered, so a tiny external store bridges the two: the call
 * sets the active document, the host renders it, and the call awaits the host's
 * readiness signal before opening the print dialog.
 *
 * It is deliberately module-level rather than context state: one document is
 * being printed at a time on a workstation, and a re-render of any screen must
 * not be able to lose the document that is already on its way to paper.
 */

export type PrintHostMode = 'print' | 'preview';

export interface ActivePrintDocument {
  /** Increments per activation so the host re-runs its pipeline for every print. */
  token: number;
  mode: PrintHostMode;
  document: PrintDocumentModel;
  artifact: PrintArtifact;
  documentId: string;
  /**
   * True when the document came from the print API — i.e. it also has a
   * server-rendered PDF to download. A draft built in the browser does not.
   */
  serverBacked: boolean;
  /** Set when the document could not be prepared; the host renders this state. */
  error?: string;
}

let active: ActivePrintDocument | null = null;
let token = 0;
let readyResolver: (() => void) | null = null;
let readyPromise: Promise<void> | null = null;
const listeners = new Set<() => void>();

function emit(): void {
  listeners.forEach((listener) => listener());
}

export const printStore = {
  subscribe(listener: () => void): () => void {
    listeners.add(listener);

    return () => listeners.delete(listener);
  },

  get(): ActivePrintDocument | null {
    return active;
  },

  activate(input: {
    mode: PrintHostMode;
    document: PrintDocumentModel;
    artifact: PrintArtifact;
    documentId: string;
    serverBacked: boolean;
  }): Promise<void> {
    token += 1;
    readyPromise = new Promise<void>((resolve) => {
      readyResolver = resolve;
    });
    active = { token, ...input };
    emit();

    return readyPromise;
  },

  fail(message: string): void {
    if (!active) {
      return;
    }

    active = { ...active, error: message };
    emit();
    readyResolver?.();
    readyResolver = null;
  },

  /** Called by the host once fonts, images and layout have settled. */
  markReady(): void {
    readyResolver?.();
    readyResolver = null;
  },

  clear(): void {
    active = null;
    readyResolver = null;
    readyPromise = null;
    emit();
  },

  /** True while a document is active — used to lock the UI during printing. */
  isActive(): boolean {
    return active !== null;
  },
};

export function useActivePrintDocument(): ActivePrintDocument | null {
  return useSyncExternalStore(printStore.subscribe, printStore.get, printStore.get);
}

/** Default geometry for the short window before a payload arrives. */
export const PAPER_FALLBACK_MM: Record<PrintPaper, { width: number; height: number | null }> = {
  a4: { width: 210, height: 297 },
  thermal80: { width: 80, height: null },
  label: { width: 63.5, height: 25.4 },
};
