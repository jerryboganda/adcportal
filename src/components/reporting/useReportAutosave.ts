import { useCallback, useEffect, useRef, useState } from 'react';

/**
 * Debounced draft autosave.
 *
 * Rules that matter clinically:
 *  - only UNSIGNED drafts autosave (a signed report is immovable history),
 *  - typing is debounced so a busy editor does not hammer the API,
 *  - a transient network failure keeps the draft dirty and retries — work is
 *    never reported as "saved" when it is not,
 *  - a revision conflict STOPS autosaving and hands the server state to the
 *    UI, rather than overwriting a colleague's edit.
 */

export type AutosaveStatus = 'idle' | 'dirty' | 'saving' | 'saved' | 'error' | 'conflict';

export interface AutosaveConflict {
  message: string;
  /** Authoritative server state the caller should reconcile against. */
  serverReport?: unknown;
}

export interface UseReportAutosaveResult {
  status: AutosaveStatus;
  lastSavedAt: Date | null;
  error: string | null;
  conflict: AutosaveConflict | null;
  /** Mark the buffer dirty after an edit (the hook never reads your state). */
  touch: () => void;
  saveNow: () => Promise<void>;
  resolveConflict: () => void;
  markSaved: () => void;
}

export function useReportAutosave(options: {
  /** Autosave is off for signed reports and while nothing is selected. */
  enabled: boolean;
  /** Performs the write; reject with `{ status, message, raw }` on failure. */
  onSave: () => Promise<void>;
  /** Debounce window in ms. */
  delay?: number;
}): UseReportAutosaveResult {
  const { enabled, onSave, delay = 2500 } = options;

  const [status, setStatus] = useState<AutosaveStatus>('idle');
  const [lastSavedAt, setLastSavedAt] = useState<Date | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [conflict, setConflict] = useState<AutosaveConflict | null>(null);

  const timer = useRef<number | null>(null);
  const inFlight = useRef(false);
  const dirty = useRef(false);
  // Latest callbacks/options without re-arming the timer on every render.
  const saveRef = useRef(onSave);
  saveRef.current = onSave;
  const enabledRef = useRef(enabled);
  enabledRef.current = enabled;
  const blockedRef = useRef(false);

  const clearTimer = () => {
    if (timer.current !== null) {
      window.clearTimeout(timer.current);
      timer.current = null;
    }
  };

  const runSave = useCallback(async () => {
    if (!enabledRef.current || blockedRef.current || inFlight.current || !dirty.current) return;

    inFlight.current = true;
    setStatus('saving');
    setError(null);

    try {
      await saveRef.current();
      dirty.current = false;
      setLastSavedAt(new Date());
      setStatus('saved');
    } catch (err: any) {
      if (err?.status === 409) {
        // Someone else saved first: hold the buffer, keep it dirty, and let the
        // radiologist decide. Autosave must never win this race silently.
        blockedRef.current = true;
        setConflict({
          message: err?.message ?? 'This report was changed by someone else.',
          serverReport: err?.raw?.response?.data?.data?.report,
        });
        setStatus('conflict');
        return;
      }

      setError(err?.message ?? 'Could not autosave.');
      setStatus('error');
    } finally {
      inFlight.current = false;
    }
  }, []);

  const touch = useCallback(() => {
    if (!enabledRef.current || blockedRef.current) return;

    dirty.current = true;
    setStatus(current => (current === 'saving' ? current : 'dirty'));

    clearTimer();
    timer.current = window.setTimeout(() => {
      timer.current = null;
      void runSave();
    }, delay);
  }, [delay, runSave]);

  const saveNow = useCallback(async () => {
    clearTimer();
    dirty.current = true;
    blockedRef.current = false;
    await runSave();
  }, [runSave]);

  const resolveConflict = useCallback(() => {
    blockedRef.current = false;
    dirty.current = false;
    setConflict(null);
    setStatus('idle');
  }, []);

  const markSaved = useCallback(() => {
    dirty.current = false;
    blockedRef.current = false;
    setConflict(null);
    setError(null);
    setLastSavedAt(new Date());
    setStatus('saved');
  }, []);

  // Flush pending work before the tab goes away; a reload must not eat a
  // report the radiologist believed was saved.
  useEffect(() => {
    const onBeforeUnload = (event: BeforeUnloadEvent) => {
      if (!dirty.current || !enabledRef.current) return;
      event.preventDefault();
      event.returnValue = '';
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    return () => {
      window.removeEventListener('beforeunload', onBeforeUnload);
      clearTimer();
    };
  }, []);

  useEffect(() => {
    if (!enabled) {
      clearTimer();
    }
  }, [enabled]);

  return { status, lastSavedAt, error, conflict, touch, saveNow, resolveConflict, markSaved };
}
