import React, { useEffect, useRef, useState } from 'react';
import { KeyRound, ShieldCheck, X } from 'lucide-react';

/**
 * Control-plane step-up re-authentication. Rendered by App for platform
 * identities; the api layer's 428 interceptor opens it (via the registered
 * handler) when a mutating platform action needs a fresh password
 * confirmation, then replays the original request after a successful confirm.
 *
 * onConfirm resolves `true` when the confirmation succeeded; returning
 * `false` (e.g. wrong password) keeps the modal open so the user can retry.
 * Cancel rejects the intercepted request - the action simply does not happen.
 */
export const StepUpModal: React.FC<{
  open: boolean;
  onConfirm: (password: string) => Promise<boolean>;
  onCancel: () => void;
}> = ({ open, onConfirm, onCancel }) => {
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    if (open) {
      setPassword('');
      setError(null);
      setBusy(false);
      window.setTimeout(() => inputRef.current?.focus(), 50);
    }
  }, [open]);

  if (! open) {
    return null;
  }

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (! password || busy) {
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const ok = await onConfirm(password);
      if (! ok) {
        setError('That password is not correct. Try again.');
      }
    } catch {
      setError('Confirmation failed. Try again.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[90] bg-slate-900/60 backdrop-blur-[2px] flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-label="Confirm your password">
      <form
        onSubmit={submit}
        className="w-full max-w-sm rounded-xl bg-white shadow-2xl border border-slate-200 p-5"
      >
        <div className="flex items-start gap-3">
          <div className="rounded-lg bg-cyan-50 border border-cyan-200 p-2 text-cyan-700">
            <ShieldCheck size={20} />
          </div>
          <div className="flex-1">
            <h2 className="text-sm font-bold text-slate-900">Confirm your password</h2>
            <p className="mt-1 text-xs text-slate-500 leading-relaxed">
              This platform action changes tenant state. Re-enter <strong>your own</strong> password to continue - the confirmation stays valid for 15 minutes.
            </p>
          </div>
          <button type="button" onClick={onCancel} className="text-slate-400 hover:text-slate-600" aria-label="Cancel">
            <X size={16} />
          </button>
        </div>

        <label className="block mt-4 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
          Password
        </label>
        <div className="mt-1 flex items-center gap-2 rounded-lg border border-slate-300 px-3 py-2 focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-100">
          <KeyRound size={14} className="text-slate-400" />
          <input
            ref={inputRef}
            type="password"
            value={password}
            onChange={e => setPassword(e.target.value)}
            className="w-full text-sm outline-none"
            placeholder="Your password"
            autoComplete="current-password"
            disabled={busy}
          />
        </div>
        {error && <p className="mt-2 text-xs font-medium text-rose-600">{error}</p>}

        <div className="mt-5 flex gap-2 justify-end">
          <button
            type="button"
            onClick={onCancel}
            className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50"
            disabled={busy}
          >
            Cancel
          </button>
          <button
            type="submit"
            className="rounded-lg bg-cyan-600 px-3.5 py-1.5 text-xs font-bold text-white hover:bg-cyan-700 disabled:opacity-50"
            disabled={busy || !password}
          >
            {busy ? 'Verifying…' : 'Confirm & continue'}
          </button>
        </div>
      </form>
    </div>
  );
};
