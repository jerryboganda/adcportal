import React, { useEffect, useState } from 'react';
import { Building2, LogOut, MessageCircle, ShieldAlert, SwitchCamera } from 'lucide-react';
import * as api from '../services/apiService';
import { SessionUser } from '../services/apiService';
import { Plan } from '../types';

/**
 * Shown when the tenant's subscription does not permit product use
 * (suspended / expired / trial ended / provisioning / offboarding / terminated).
 * The server refuses every tenant API call with 402 — this screen mirrors the
 * state and the (manual, offline) path to resolution. Activation is manual by
 * the platform administrator; nothing here pretends to take payment.
 */
export const SubscriptionGateView: React.FC<{
  user: SessionUser;
  status: string;
  message: string;
  onSwitchTenant: (businessId: number) => void;
  onSignOut: () => void;
}> = ({ user, status, message, onSwitchTenant, onSignOut }) => {
  const [plans, setPlans] = useState<Plan[]>([]);
  const [plansError, setPlansError] = useState(false);

  useEffect(() => {
    api.fetchPublicPlans().then(setPlans).catch(() => setPlansError(true));
  }, []);

  const otherTenants = user.memberships.filter(m => m.businessId !== user.businessId);
  const headline: Record<string, string> = {
    suspended: 'Clinic account suspended',
    expired: 'Subscription expired',
    trialing: 'Trial ended',
    provisioning: 'Clinic is being provisioned',
    offboarding: 'Clinic is being offboarded',
    terminated: 'Clinic account terminated',
  };

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center px-4 py-10">
      <div className="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center text-[13px] font-black text-white">PX</div>
          <div>
            <p className="text-sm font-bold text-slate-900">PolytronX — RIS</p>
            <p className="text-xs text-slate-500">{user.businessName}</p>
          </div>
          <span className="ml-auto inline-flex items-center gap-1.5 rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold capitalize text-amber-800">
            <ShieldAlert size={13} /> {status}
          </span>
        </div>

        <h1 className="mt-5 text-lg font-bold text-slate-900">{headline[status] ?? 'Subscription required'}</h1>
        <p className="mt-1 text-sm text-slate-600">{message}</p>
        <p className="mt-1 text-xs text-slate-500">
          Subscription activation is performed by the PolytronX platform administrator after plan confirmation —
          no payment is taken inside the product.
        </p>

        {otherTenants.length > 0 && (
          <div className="mt-4 rounded-xl border border-cyan-200 bg-cyan-50/60 p-3">
            <p className="text-xs font-semibold text-cyan-900">Your other clinics</p>
            <div className="mt-2 space-y-1.5">
              {otherTenants.map(m => (
                <button
                  key={m.businessId}
                  onClick={() => onSwitchTenant(m.businessId)}
                  className="inline-flex items-center gap-2 rounded-lg border border-cyan-300 bg-white px-3 py-1.5 text-xs font-semibold text-cyan-800 hover:bg-cyan-50"
                >
                  <SwitchCamera size={13} /> Switch to {m.businessName}
                </button>
              ))}
            </div>
          </div>
        )}

        <div className="mt-5">
          <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Available plans</p>
          {plansError ? (
            <p className="mt-2 text-xs text-slate-500">Plan catalog unavailable right now.</p>
          ) : (
            <div className="mt-2 grid gap-2 sm:grid-cols-3">
              {plans.map(p => (
                <div key={p.id} className="rounded-xl border border-slate-200 p-3">
                  <p className="text-sm font-bold text-slate-900">{p.name}</p>
                  <p className="text-sm font-semibold text-cyan-700">{p.currency} {p.priceMonthly.toLocaleString()}<span className="text-[11px] font-medium text-slate-400">/mo</span></p>
                  <p className="mt-1 text-[11px] text-slate-500">{p.maxUsers ? `${p.maxUsers} seats` : 'Unlimited seats'} · {p.maxStudiesPerMonth ? `${p.maxStudiesPerMonth.toLocaleString()} studies/mo` : 'Unlimited studies'}</p>
                  <p className="text-[11px] text-slate-400">{p.trialDays}-day trial</p>
                </div>
              ))}
              {plans.length === 0 && <p className="text-xs text-slate-500">No published plans.</p>}
            </div>
          )}
        </div>

        <div className="mt-5 flex flex-wrap items-center gap-2">
          <a
            href="https://wa.me/447843985126"
            target="_blank"
            rel="noreferrer"
            className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700"
          >
            <MessageCircle size={14} /> Contact platform support (WhatsApp)
          </a>
          <button
            onClick={onSignOut}
            className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
          >
            <LogOut size={14} /> Sign out
          </button>
          {user.isPlatformAdmin && (
            <span className="inline-flex items-center gap-1.5 text-xs text-slate-500"><Building2 size={13} /> Platform staff: reopen the console after signing in.</span>
          )}
        </div>
      </div>
    </div>
  );
};
