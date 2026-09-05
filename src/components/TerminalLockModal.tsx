import React, { useState } from 'react';
import { Lock, Unlock, KeyRound, ShieldAlert, User, Shield, Stethoscope, Activity, UserCheck } from 'lucide-react';
import { StaffUser } from '../types';

interface TerminalLockModalProps {
  isOpen: boolean;
  onUnlock: () => void;
  role: 'admin' | 'receptionist' | 'technologist' | 'radiologist' | 'patient';
  setRole: (role: 'admin' | 'receptionist' | 'technologist' | 'radiologist' | 'patient') => void;
  staffUsers: StaffUser[];
}

export const TerminalLockModal: React.FC<TerminalLockModalProps> = ({
  isOpen,
  onUnlock,
  role,
  setRole,
  staffUsers,
}) => {
  const [pin, setPin] = useState('');
  const [error, setError] = useState('');

  if (!isOpen) return null;

  const currentStaff = staffUsers.find(u => u.role === role) || {
    name: role === 'radiologist' ? 'Dr. Shahzad Mir' : role === 'technologist' ? 'Kamran Ali' : 'Amina Bilal',
    department: 'Radiology Division',
  };

  const handleUnlock = (e: React.FormEvent) => {
    e.preventDefault();
    // Allow PIN 1234 or any non-empty PIN for swift access in clinical workflow
    if (pin === '1234' || pin.length >= 4) {
      setError('');
      setPin('');
      onUnlock();
    } else {
      setError('Please enter a valid 4-digit PIN (Default: 1234)');
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in duration-200">
      <div className="bg-slate-900 border border-slate-700/80 rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl text-center text-white">
        {/* Lock Icon */}
        <div className="w-16 h-16 rounded-2xl bg-gradient-to-tr from-cyan-600 to-indigo-600 flex items-center justify-center mx-auto shadow-lg shadow-cyan-500/20 mb-4 border border-white/20">
          <Lock className="w-8 h-8 text-white animate-bounce" />
        </div>

        <h2 className="text-xl font-black tracking-tight text-white mb-1">
          Terminal Locked
        </h2>
        <p className="text-xs text-slate-400 mb-6">
          ADC Radiology Information System is secured. Enter your PIN or quick-switch staff profile to resume work.
        </p>

        {/* Current Active User Profile Banner */}
        <div className="bg-slate-800/80 border border-slate-700 rounded-2xl p-3 mb-6 flex items-center space-x-3 text-left">
          <div className="w-10 h-10 rounded-xl bg-cyan-600 flex items-center justify-center text-white font-bold text-sm">
            {currentStaff.name.slice(0, 2).toUpperCase()}
          </div>
          <div className="flex-1 min-w-0">
            <h4 className="text-xs font-bold text-white truncate">{currentStaff.name}</h4>
            <p className="text-[10px] text-cyan-400 capitalize font-medium">{role} • {currentStaff.department}</p>
          </div>
          <span className="text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-2 py-0.5 rounded-full font-bold">
            Session Active
          </span>
        </div>

        {/* Unlock Form */}
        <form onSubmit={handleUnlock} className="space-y-4 mb-6">
          <div className="relative">
            <KeyRound className="w-4 h-4 absolute left-3.5 top-3 text-slate-400" />
            <input
              type="password"
              placeholder="Enter Staff Security PIN (e.g. 1234)"
              value={pin}
              onChange={(e) => {
                setPin(e.target.value);
                setError('');
              }}
              autoFocus
              maxLength={8}
              className="w-full pl-10 pr-4 py-2.5 bg-slate-800/90 border border-slate-700 rounded-xl text-center tracking-widest text-sm font-mono text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-cyan-500 focus:border-transparent"
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
            className="w-full py-2.5 px-4 bg-gradient-to-r from-cyan-600 to-sky-600 hover:from-cyan-500 hover:to-sky-500 text-white font-bold rounded-xl text-xs transition-all shadow-lg shadow-cyan-600/30 flex items-center justify-center space-x-2 cursor-pointer"
          >
            <Unlock className="w-4 h-4" />
            <span>Unlock Clinical Terminal</span>
          </button>
        </form>

        {/* Quick Switch Staff Bar */}
        <div className="pt-4 border-t border-slate-800">
          <span className="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-2">
            Or Switch Active Staff Persona
          </span>
          <div className="grid grid-cols-2 gap-2 text-xs">
            <button
              type="button"
              onClick={() => {
                setRole('admin');
                onUnlock();
              }}
              className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 hover:text-white transition-colors flex items-center space-x-1.5 justify-center cursor-pointer border border-slate-700"
            >
              <Shield className="w-3.5 h-3.5 text-cyan-400" />
              <span>Admin</span>
            </button>
            <button
              type="button"
              onClick={() => {
                setRole('radiologist');
                onUnlock();
              }}
              className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 hover:text-white transition-colors flex items-center space-x-1.5 justify-center cursor-pointer border border-slate-700"
            >
              <Stethoscope className="w-3.5 h-3.5 text-purple-400" />
              <span>Radiologist</span>
            </button>
            <button
              type="button"
              onClick={() => {
                setRole('technologist');
                onUnlock();
              }}
              className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 hover:text-white transition-colors flex items-center space-x-1.5 justify-center cursor-pointer border border-slate-700"
            >
              <Activity className="w-3.5 h-3.5 text-emerald-400" />
              <span>Technologist</span>
            </button>
            <button
              type="button"
              onClick={() => {
                setRole('receptionist');
                onUnlock();
              }}
              className="py-1.5 px-2 bg-slate-800 hover:bg-slate-700 rounded-lg text-slate-300 hover:text-white transition-colors flex items-center space-x-1.5 justify-center cursor-pointer border border-slate-700"
            >
              <UserCheck className="w-3.5 h-3.5 text-sky-400" />
              <span>Receptionist</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
