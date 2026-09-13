import React, { useState } from 'react';
import { Activity, Building2, Loader2, Lock, Mail, Phone, ShieldCheck, User as UserIcon } from 'lucide-react';
import { login, registerTenant, SessionUser } from '../services/apiService';

interface LoginViewProps {
  onAuthenticated: (user: SessionUser) => void;
}

/**
 * Real authentication gate. Session is a Laravel Sanctum cookie; roles and
 * permissions come from the server, never from the client.
 */
export const LoginView: React.FC<LoginViewProps> = ({ onAuthenticated }) => {
  const [mode, setMode] = useState<'login' | 'register'>('login');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [clinicName, setClinicName] = useState('');
  const [fullName, setFullName] = useState('');
  const [phone, setPhone] = useState('');
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState('');

  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    setError('');

    if (mode === 'login' && (!email.trim() || !password)) {
      setError('Enter your email and password.');
      return;
    }
    if (mode === 'register' && (!clinicName.trim() || !fullName.trim() || !email.trim() || password.length < 8)) {
      setError('Clinic name, your name, email and a password of at least 8 characters are required.');
      return;
    }

    setBusy(true);
    try {
      let user: SessionUser;
      if (mode === 'login') {
        user = await login(email.trim(), password);
      } else {
        user = await registerTenant({
          clinicName: clinicName.trim(),
          name: fullName.trim(),
          email: email.trim(),
          phone: phone.trim() || undefined,
          password,
        });
      }
      onAuthenticated(user);
    } catch (err: any) {
      setError(err?.message ?? 'Authentication failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-100 flex">
      {/* Brand panel */}
      <div className="hidden lg:flex lg:w-[46%] bg-gradient-to-br from-slate-900 via-cyan-950 to-slate-900 text-white flex-col justify-between p-12">
        <div className="flex items-center gap-3">
          <div className="w-11 h-11 rounded-xl bg-cyan-500/20 border border-cyan-400/40 flex items-center justify-center">
            <Activity className="w-6 h-6 text-cyan-400" />
          </div>
          <div>
            <p className="font-bold text-lg leading-tight">ADC Portal</p>
            <p className="text-xs text-cyan-300/80">Radiology Information System</p>
          </div>
        </div>

        <div className="space-y-6 max-w-md">
          <h1 className="text-3xl font-bold leading-snug">
            Study workflow, reporting and billing — <span className="text-cyan-400">backed by a real clinical record</span>.
          </h1>
          <ul className="space-y-3 text-sm text-slate-300">
            {[
              'Every booking, report and payment is persisted and audited.',
              'Role-based access enforced server-side per clinic.',
              'Safety screening gates acquisition before needle-time.',
            ].map(item => (
              <li key={item} className="flex items-start gap-2.5">
                <ShieldCheck className="w-4.5 h-4.5 mt-0.5 text-cyan-400 shrink-0" />
                <span>{item}</span>
              </li>
            ))}
          </ul>
        </div>

        <p className="text-xs text-slate-500">Amad Diagnostic Centre • Islamabad</p>
      </div>

      {/* Form panel */}
      <div className="flex-1 flex items-center justify-center p-6">
        <div className="w-full max-w-md">
          <div className="bg-white rounded-2xl shadow-xl shadow-slate-200 border border-slate-200 p-8">
            <div className="flex mb-6 rounded-lg bg-slate-100 p-1 text-sm font-semibold">
              <button
                type="button"
                onClick={() => { setMode('login'); setError(''); }}
                className={`flex-1 rounded-md py-2 transition ${mode === 'login' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'}`}
              >
                Staff Sign In
              </button>
              <button
                type="button"
                onClick={() => { setMode('register'); setError(''); }}
                className={`flex-1 rounded-md py-2 transition ${mode === 'register' ? 'bg-white shadow text-slate-900' : 'text-slate-500 hover:text-slate-700'}`}
              >
                Register Clinic
              </button>
            </div>

            <form onSubmit={submit} className="space-y-4" noValidate>
              {mode === 'register' && (
                <>
                  <Field icon={<Building2 className="w-4 h-4 text-slate-400" />}>
                    <input
                      type="text"
                      value={clinicName}
                      onChange={e => setClinicName(e.target.value)}
                      placeholder="Diagnostic centre name"
                      autoComplete="organization"
                      className="w-full bg-transparent outline-none text-sm placeholder:text-slate-400"
                    />
                  </Field>
                  <Field icon={<UserIcon className="w-4 h-4 text-slate-400" />}>
                    <input
                      type="text"
                      value={fullName}
                      onChange={e => setFullName(e.target.value)}
                      placeholder="Administrator full name"
                      autoComplete="name"
                      className="w-full bg-transparent outline-none text-sm placeholder:text-slate-400"
                    />
                  </Field>
                  <Field icon={<Phone className="w-4 h-4 text-slate-400" />}>
                    <input
                      type="tel"
                      value={phone}
                      onChange={e => setPhone(e.target.value)}
                      placeholder="Phone (optional)"
                      autoComplete="tel"
                      className="w-full bg-transparent outline-none text-sm placeholder:text-slate-400"
                    />
                  </Field>
                </>
              )}

              <Field icon={<Mail className="w-4 h-4 text-slate-400" />}>
                <input
                  type="email"
                  value={email}
                  onChange={e => setEmail(e.target.value)}
                  placeholder="Work email"
                  autoComplete="email"
                  className="w-full bg-transparent outline-none text-sm placeholder:text-slate-400"
                />
              </Field>

              <Field icon={<Lock className="w-4 h-4 text-slate-400" />}>
                <input
                  type="password"
                  value={password}
                  onChange={e => setPassword(e.target.value)}
                  placeholder={mode === 'register' ? 'Password (min 8 characters)' : 'Password'}
                  autoComplete={mode === 'register' ? 'new-password' : 'current-password'}
                  className="w-full bg-transparent outline-none text-sm placeholder:text-slate-400"
                />
              </Field>

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
                {mode === 'login' ? 'Sign In' : 'Create Clinic Account (14-day trial)'}
              </button>
            </form>

            <p className="mt-5 text-[11px] leading-relaxed text-slate-400 text-center">
              Access is audited. Roles and permissions are enforced on the server —
              your session grants only what your clinic administrator assigned.
            </p>
          </div>
        </div>
      </div>
    </div>
  );
};

const Field: React.FC<{ icon: React.ReactNode; children: React.ReactNode }> = ({ icon, children }) => (
  <div className="flex items-center gap-2.5 rounded-lg border border-slate-300 focus-within:border-cyan-500 focus-within:ring-2 focus-within:ring-cyan-500/20 px-3.5 py-2.5 bg-slate-50">
    {icon}
    {children}
  </div>
);
