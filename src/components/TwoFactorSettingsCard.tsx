import React, { useEffect, useState } from 'react';
import { KeyRound, Loader2, ShieldAlert, ShieldCheck } from 'lucide-react';
import { twoFactorConfirm, twoFactorDisable, twoFactorSetup, twoFactorStatus, TwoFactorStatus } from '../services/apiService';

interface TwoFactorSettingsCardProps {
  notify: (kind: 'error' | 'success', message: string) => void;
}

type Phase = 'idle' | 'enrolling';

/**
 * Platform-console security card: enroll an authenticator app (TOTP) or
 * disable an existing second factor (password + current code required).
 */
export const TwoFactorSettingsCard: React.FC<TwoFactorSettingsCardProps> = ({ notify }) => {
  const [status, setStatus] = useState<TwoFactorStatus | null>(null);
  const [phase, setPhase] = useState<Phase>('idle');
  const [secret, setSecret] = useState('');
  const [otpauthUrl, setOtpauthUrl] = useState('');
  const [code, setCode] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  const refresh = async () => {
    try {
      setStatus(await twoFactorStatus());
    } catch {
      // Status is cosmetic on this card; actions surface their own errors.
    }
  };

  useEffect(() => { refresh(); }, []);

  const startEnrollment = async () => {
    setBusy(true);
    try {
      const { secret: s, otpauthUrl: u } = await twoFactorSetup();
      setSecret(s);
      setOtpauthUrl(u);
      setCode('');
      setPhase('enrolling');
    } catch (err: any) {
      notify('error', err?.message ?? 'Could not start enrollment.');
    } finally {
      setBusy(false);
    }
  };

  const confirmEnrollment = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    try {
      await twoFactorConfirm(code.trim());
      setPhase('idle');
      setSecret('');
      setCode('');
      notify('success', 'Two-factor authentication enabled.');
      refresh();
    } catch (err: any) {
      notify('error', err?.message ?? 'Verification failed.');
    } finally {
      setBusy(false);
    }
  };

  const disable = async (e: React.FormEvent) => {
    e.preventDefault();
    setBusy(true);
    try {
      await twoFactorDisable(password, code.trim());
      setPassword('');
      setCode('');
      notify('success', 'Two-factor authentication disabled.');
      refresh();
    } catch (err: any) {
      notify('error', err?.message ?? 'Could not disable two-factor.');
    } finally {
      setBusy(false);
    }
  };

  const enabled = status?.enabled ?? false;

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-5">
      <div className="flex items-start gap-3">
        <div className={`w-10 h-10 rounded-lg flex items-center justify-center shrink-0 ${enabled ? 'bg-emerald-50 border border-emerald-200' : 'bg-amber-50 border border-amber-200'}`}>
          {enabled ? <ShieldCheck className="w-5 h-5 text-emerald-600" /> : <ShieldAlert className="w-5 h-5 text-amber-600" />}
        </div>
        <div className="flex-1 min-w-0">
          <p className="text-sm font-bold text-slate-900">Two-factor authentication</p>
          <p className="text-xs text-slate-500 mt-0.5">
            {enabled
              ? 'Active. A code from your authenticator app is required each sign-in.'
              : 'Recommended: require a time-based code (authenticator app) at every sign-in.'}
          </p>

          {!enabled && phase !== 'enrolling' && (
            <button
              onClick={startEnrollment}
              disabled={busy}
              className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-700 disabled:opacity-60 px-3 py-1.5 text-xs font-semibold text-white transition"
            >
              {busy ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <KeyRound size={13} />}
              Set up authenticator
            </button>
          )}

          {phase === 'enrolling' && (
            <div className="mt-3 space-y-3">
              <div className="rounded-lg bg-slate-50 border border-slate-200 p-3">
                <p className="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">1. Add the secret to your app</p>
                <p className="text-[11px] text-slate-500 mt-1">
                  In Google Authenticator / 1Password / Aegis choose “Add account → Enter setup key” and paste:
                </p>
                <p className="mt-2 rounded bg-white border border-slate-200 px-2 py-1.5 font-mono text-xs tracking-widest break-all select-all">{secret}</p>
                {otpauthUrl && (
                  <a href={otpauthUrl} className="mt-2 inline-block text-[11px] font-semibold text-cyan-700 hover:text-cyan-900 underline">
                    Open in authenticator app
                  </a>
                )}
              </div>
              <form onSubmit={confirmEnrollment} className="space-y-2">
                <p className="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">2. Confirm the first code</p>
                <input
                  type="text"
                  inputMode="numeric"
                  maxLength={7}
                  value={code}
                  onChange={e => setCode(e.target.value.replace(/[^\d\s]/g, ''))}
                  placeholder="123 456"
                  className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm tracking-[0.3em] outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20"
                />
                <div className="flex gap-2">
                  <button type="submit" disabled={busy} className="rounded-lg bg-cyan-600 hover:bg-cyan-700 disabled:opacity-60 px-3 py-1.5 text-xs font-semibold text-white inline-flex items-center gap-1.5">
                    {busy && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                    Activate
                  </button>
                  <button type="button" onClick={() => { setPhase('idle'); setSecret(''); setCode(''); }} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                    Cancel
                  </button>
                </div>
              </form>
            </div>
          )}

          {enabled && (
            <form onSubmit={disable} className="mt-3 space-y-2">
              <input
                type="password"
                value={password}
                onChange={e => setPassword(e.target.value)}
                placeholder="Current password"
                autoComplete="current-password"
                className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 text-sm outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20"
              />
              <input
                type="text"
                inputMode="numeric"
                maxLength={7}
                value={code}
                onChange={e => setCode(e.target.value.replace(/[^\d\s]/g, ''))}
                placeholder="Current authenticator code"
                className="w-full rounded-lg border border-slate-300 bg-slate-50 px-3 py-2 font-mono text-sm tracking-[0.3em] outline-none focus:border-cyan-500 focus:ring-2 focus:ring-cyan-500/20"
              />
              <button type="submit" disabled={busy || !password || code.trim().length !== 6} className="rounded-lg border border-rose-300 text-rose-700 hover:bg-rose-50 disabled:opacity-50 px-3 py-1.5 text-xs font-semibold inline-flex items-center gap-1.5">
                {busy && <Loader2 className="w-3.5 h-3.5 animate-spin" />}
                Disable two-factor
              </button>
            </form>
          )}
        </div>
      </div>
    </div>
  );
};
