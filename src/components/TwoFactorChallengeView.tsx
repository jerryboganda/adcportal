import React, { useEffect, useRef, useState } from 'react';
import { KeyRound, Loader2, ShieldCheck } from 'lucide-react';
import { twoFactorCancelChallenge, twoFactorChallenge } from '../services/apiService';

interface TwoFactorChallengeViewProps {
  email: string;
  onVerified: () => void;
  onCancel: () => void;
}

/**
 * Second-factor step for enrolled platform identities. The server holds the
 * session in a "pending" state after the password step; a valid TOTP code
 * completes it and yields the real session.
 */
export const TwoFactorChallengeView: React.FC<TwoFactorChallengeViewProps> = ({ email, onVerified, onCancel }) => {
  const [code, setCode] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    inputRef.current?.focus();
  }, []);

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    // Digits only. The field accepts and the placeholder suggests "123 456", and
    // the server strips whitespace before comparing — so counting the space made
    // the format the UI advertises permanently unacceptable.
    if (code.replace(/\D/g, '').length !== 6) {
      setError('Enter the 6-digit code from your authenticator app.');
      return;
    }

    setBusy(true);
    try {
      await twoFactorChallenge(code.trim());
      onVerified();
    } catch (err: any) {
      setError(err?.message ?? 'Verification failed.');
      setCode('');
      inputRef.current?.focus();
    } finally {
      setBusy(false);
    }
  };

  const cancel = async () => {
    setBusy(true);
    try {
      await twoFactorCancelChallenge();
    } catch {
      // Clearing local state is enough even if the round-trip fails.
    }
    onCancel();
  };

  return (
    <div className="min-h-screen bg-slate-100 flex items-center justify-center p-6">
      <div className="w-full max-w-md bg-white rounded-2xl shadow-xl shadow-slate-200 border border-slate-200 p-8">
        <div className="flex items-center gap-3 mb-5">
          <div className="w-11 h-11 rounded-xl bg-cyan-500/10 border border-cyan-500/30 flex items-center justify-center">
            <ShieldCheck className="w-5.5 h-5.5 text-cyan-600" />
          </div>
          <div>
            <p className="font-bold text-slate-900 leading-tight">Two-factor verification</p>
            <p className="text-xs text-slate-500">{email}</p>
          </div>
        </div>

        <form onSubmit={submit} className="space-y-4" noValidate>
          <div className="flex items-center gap-2.5 rounded-lg border border-slate-300 focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-500/20 px-3.5 py-2.5 bg-slate-50">
            <KeyRound className="w-4 h-4 text-slate-400 shrink-0" />
            <input
              ref={inputRef}
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={7}
              value={code}
              onChange={e => setCode(e.target.value.replace(/[^\d\s]/g, ''))}
              placeholder="123 456"
              className="w-full bg-transparent outline-none text-lg tracking-[0.3em] font-mono placeholder:text-slate-300 placeholder:tracking-normal placeholder:text-sm placeholder:font-sans"
            />
          </div>

          {error && (
            <div role="alert" className="rounded-lg bg-rose-50 border border-rose-200 text-rose-700 text-xs px-3 py-2.5">
              {error}
            </div>
          )}

          <button
            type="submit"
            disabled={busy}
            className="w-full rounded-lg bg-cyan-600 hover:bg-cyan-700 disabled:opacity-60 text-white font-semibold text-sm py-2.5 flex items-center justify-center gap-2 transition"
          >
            {busy && <Loader2 className="w-4 h-4 animate-spin" />}
            Verify code
          </button>

          <button
            type="button"
            onClick={cancel}
            disabled={busy}
            className="w-full text-xs font-semibold text-slate-500 hover:text-slate-700 underline"
          >
            Back to sign-in
          </button>
        </form>

        <p className="mt-5 text-[11px] leading-relaxed text-slate-400 text-center">
          Enter the current code from your authenticator app. Codes refresh every 30 seconds.
        </p>
      </div>
    </div>
  );
};
