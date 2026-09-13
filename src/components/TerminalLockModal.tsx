import React, { useState } from 'react';
import { Lock, Unlock, KeyRound, ShieldAlert, Loader2 } from 'lucide-react';
import { AppRole, StaffUser } from '../types';
import { verifyPassword } from '../services/apiService';

interface TerminalLockModalProps {
  isOpen: boolean;
  onUnlock: () => void;
  userName: string;
  role: AppRole;
  staffUsers: StaffUser[];
}

/**
 * Terminal lock. The only way back in is re-verifying the signed-in user's
 * password against the server — no PIN backdoors, no persona switching.
 */
export const TerminalLockModal: React.FC<TerminalLockModalProps> = ({
  isOpen,
  onUnlock,
  userName,
  role,
}) => {
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [busy, setBusy] = useState(false);

  if (!isOpen) return null;

  const handleUnlock = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!password) {
      setError('Enter your account password to unlock this terminal.');
      return;
    }

    setBusy(true);
    setError('');
    try {
      await verifyPassword(password);
      setPassword('');
      onUnlock();
    } catch (err: any) {
      setError(err?.message ?? 'Verification failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in duration-200">
      <div className="bg-slate-900 border border-slate-700/80 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl text-center text-white">
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-tr from-cyan-600 to-indigo-600 flex items-center justify-center mx-auto shadow-lg shadow-cyan-500/20 mb-4 border border-white/20">
          <Lock className="w-8 h-8 text-white" />
        </div>

        <h2 className="text-xl font-black tracking-tight text-white mb-1">
          Terminal Locked
        </h2>
        <p className="text-xs text-slate-400 mb-6">
          Re-enter your account password to resume this clinical session.
        </p>

        {/* Current Active User Profile Banner */}
        <div className="bg-slate-800/80 border border-slate-700 rounded-2xl p-3 mb-6 flex items-center space-x-3 text-left">
          <div className="w-10 h-10 rounded-xl bg-cyan-600 flex items-center justify-center text-white font-bold text-sm">
            {userName.slice(0, 2).toUpperCase()}
          </div>
          <div className="flex-1 min-w-0">
            <h4 className="text-xs font-bold text-white truncate">{userName}</h4>
            <p className="text-[10px] text-cyan-400 capitalize font-medium">{role}</p>
          </div>
          <span className="text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2 py-0.5 rounded-full font-bold">
            Session Active
          </span>
        </div>

        <form onSubmit={handleUnlock} className="space-y-4">
          <div className="relative">
            <KeyRound className="w-4 h-4 absolute left-3.5 top-3 text-slate-400" />
            <input
              type="password"
              placeholder="Account password"
              value={password}
              onChange={(e) => {
                setPassword(e.target.value);
                setError('');
              }}
              autoFocus
              autoComplete="current-password"
              className="w-full pl-10 pr-4 py-2.5 bg-slate-800/90 border border-slate-700 rounded-xl text-sm font-mono text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
            />
          </div>

          {error && (
            <p className="text-xs text-rose-400 font-semibold flex items-center justify-center space-x-1">
              <ShieldAlert className="w-3.5 h-3.5" />
              <span>{error}</span>
            </p>
          )}

          <button
            type="submit"
            disabled={busy}
            className="w-full py-2.5 px-4 bg-gradient-to-r from-cyan-600 to-sky-600 hover:from-cyan-500 hover:to-sky-500 disabled:opacity-60 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-cyan-600/30 flex items-center justify-center space-x-2 cursor-pointer"
          >
            {busy ? <Loader2 className="w-4 h-4 animate-spin" /> : <Unlock className="w-4 h-4" />}
            <span>Unlock Clinical Terminal</span>
          </button>
        </form>
      </div>
    </div>
  );
};
