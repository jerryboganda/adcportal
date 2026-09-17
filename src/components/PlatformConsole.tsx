import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Activity, Archive, ArrowLeft, BarChart3, Building2, CheckCircle2, ChevronRight, Copy, CreditCard,
  Download, Edit2, HeartPulse, Hospital, KeyRound, LifeBuoy, LogOut, Pause, Pencil, Play, Plus, RefreshCw, RotateCcw, ScrollText,
  Search, Server, ShieldCheck, Trash2, Users as UsersIcon, XCircle, Paintbrush,
} from 'lucide-react';
import * as api from '../services/apiService';
import { SessionUser } from '../services/apiService';
import { TwoFactorSettingsCard } from './TwoFactorSettingsCard';
import { StepUpModal } from './StepUpModal';
import {
  DeploymentCatalog,
  Entitlements,
  EntitlementReconciliation,
  FailedJobsPayload,
  InfrastructureSummary,
  Plan,
  PlatformAuditEntry,
  PlatformOperationsPayload,
  PlatformOverviewStats,
  PlatformRole,
  PlatformSupportSessionRecord,
  PlatformUserRecord,
  Tenant360,
  TenantBranding,
  TenantBrandingPayload,
  TenantFacilityRecord,
  TenantHealth,
  TenantIntegrationRecord,
  TenantIntegrationsPayload,
  TenantLifecycleEntry,
  TenantRecord,
  TenantUserRecord,
  UsageSummary,
} from '../types';

/**
 * SaaS control plane for platform staff (PolytronX vendor). Visually distinct
 * from the tenant RIS shell on purpose: platform operators work on tenants,
 * plans, subscriptions and audit — never on a single clinic's worklist.
 * Every action shown is backed by a server endpoint that enforces platform
 * capabilities; this UI only mirrors what the role already holds.
 */

type Section = 'overview' | 'tenants' | 'plans' | 'usage' | 'infrastructure' | 'operations' | 'support' | 'users' | 'audit';

const ROLE_CAPABILITIES: Record<string, string[]> = {
  super_admin: ['*'],
  ops: ['tenants.view', 'tenants.manage', 'tenants.lifecycle', 'provisioning.manage', 'usage.view', 'health.view', 'audit.view', 'platform.users.view', 'infrastructure.manage', 'integrations.manage', 'operations.manage'],
  billing: ['tenants.view', 'plans.manage', 'subscriptions.manage', 'usage.view', 'audit.view'],
  support: ['tenants.view', 'support.manage', 'health.view', 'audit.view', 'operations.manage'],
  auditor: ['tenants.view', 'usage.view', 'health.view', 'audit.view'],
};

function can(user: SessionUser, capability: string): boolean {
  const caps = ROLE_CAPABILITIES[user.platformRole ?? 'ops'] ?? [];
  return caps.includes('*') || caps.includes(capability);
}

const STATUS_STYLES: Record<string, string> = {
  active: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  trialing: 'bg-cyan-50 text-cyan-700 border-cyan-200',
  suspended: 'bg-amber-50 text-amber-700 border-amber-200',
  expired: 'bg-orange-50 text-orange-700 border-orange-200',
  provisioning: 'bg-indigo-50 text-indigo-700 border-indigo-200',
  offboarding: 'bg-rose-50 text-rose-700 border-rose-200',
  terminated: 'bg-slate-100 text-slate-600 border-slate-300',
};

function StatusBadge({ status }: { status: string }) {
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize ${STATUS_STYLES[status] ?? 'bg-slate-50 text-slate-600 border-slate-200'}`}>
      {status}
    </span>
  );
}

/** Clinic vs hospital tenant flavor (both share the portal today). */
function OrgTypeBadge({ orgType }: { orgType?: string | null }) {
  const isHospital = orgType === 'hospital';
  return (
    <span
      title={isHospital ? 'Hospital tenant' : 'Clinic tenant'}
      className={`inline-flex items-center gap-0.5 rounded-full border px-1.5 py-0.5 align-middle text-[10px] font-semibold ${isHospital ? 'border-violet-200 bg-violet-50 text-violet-700' : 'border-slate-200 bg-slate-50 text-slate-600'}`}
    >
      {isHospital ? <Hospital size={9} /> : <Building2 size={9} />}
      {isHospital ? 'Hospital' : 'Clinic'}
    </span>
  );
}

function Card({ label, value, sub, tone = 'slate' }: { label: string; value: React.ReactNode; sub?: string; tone?: 'slate' | 'cyan' | 'emerald' | 'amber' | 'rose' }) {
  const tones: Record<string, string> = {
    slate: 'border-slate-200',
    cyan: 'border-cyan-200 bg-cyan-50/40',
    emerald: 'border-emerald-200 bg-emerald-50/40',
    amber: 'border-amber-200 bg-amber-50/40',
    rose: 'border-rose-200 bg-rose-50/40',
  };
  return (
    <div className={`rounded-xl border bg-white p-4 ${tones[tone]}`}>
      <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</p>
      <p className="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{value}</p>
      {sub && <p className="mt-0.5 text-xs text-slate-500">{sub}</p>}
    </div>
  );
}

function fmtBytes(bytes: number): string {
  if (!bytes) return '0 B';
  const units = ['B', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
  return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
}

function fmtMoney(v: number, currency: string): string {
  return `${currency} ${v.toLocaleString(undefined, { maximumFractionDigits: 0 })}`;
}

function fmtWhen(iso?: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' });
}

export const PlatformConsole: React.FC<{
  user: SessionUser;
  onSignOut: () => void;
  onEnterTenant: (businessId: number) => Promise<void>;
  notify: (kind: 'error' | 'success', message: string) => void;
}> = ({ user, onSignOut, onEnterTenant, notify }) => {
  const [section, setSection] = useState<Section>('overview');
  const [selectedTenantId, setSelectedTenantId] = useState<string | null>(null);

  const fail = useCallback((err: any, fallback: string) => {
    notify('error', err?.message ?? fallback);
  }, [notify]);

  const sections: { key: Section; label: string; icon: React.ReactNode }[] = useMemo(() => [
    { key: 'overview', label: 'Overview', icon: <Activity size={15} /> },
    { key: 'tenants', label: 'Tenants', icon: <Building2 size={15} /> },
    { key: 'plans', label: 'Plans', icon: <CreditCard size={15} /> },
    { key: 'usage', label: 'Usage', icon: <BarChart3 size={15} /> },
    { key: 'infrastructure', label: 'Infrastructure', icon: <Server size={15} /> },
    { key: 'operations', label: 'Operations', icon: <HeartPulse size={15} /> },
    { key: 'support', label: 'Support Sessions', icon: <LifeBuoy size={15} /> },
    { key: 'users', label: 'Platform Users', icon: <ShieldCheck size={15} /> },
    { key: 'audit', label: 'Audit', icon: <ScrollText size={15} /> },
  ], []);

  const [securityOpen, setSecurityOpen] = useState(false);
  const [reconfirmOpen, setReconfirmOpen] = useState(false);

  return (
    <div className="min-h-screen bg-slate-100 text-slate-900 flex flex-col antialiased">
      {/* Console header — dark, deliberately distinct from the clinic shell */}
      <header className="bg-slate-900 text-white">
        <div className="mx-auto max-w-[1680px] px-4 lg:px-6 py-3 flex items-center gap-4">
          <div className="flex items-center gap-2.5">
            <div className="w-9 h-9 rounded-lg bg-gradient-to-br from-cyan-400 to-cyan-600 flex items-center justify-center text-[13px] font-black tracking-tight">PX</div>
            <div>
              <p className="text-sm font-bold leading-tight">PolytronX — Platform Console</p>
              <p className="text-[11px] text-slate-400 leading-tight">SaaS control plane</p>
            </div>
          </div>
          <div className="ml-auto flex items-center gap-3">
            <div className="hidden sm:block text-right">
              <p className="text-xs font-semibold">{user.name}</p>
              <p className="text-[11px] text-cyan-300 capitalize">{user.platformRole ?? 'ops'}</p>
            </div>
            <button
              onClick={() => setSecurityOpen(o => !o)}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-800"
            >
              <KeyRound size={13} /> Security
            </button>
            <button
              onClick={onSignOut}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-800"
            >
              <LogOut size={13} /> Sign out
            </button>
          </div>
        </div>
        <nav className="mx-auto max-w-[1680px] px-4 lg:px-6 flex gap-1 overflow-x-auto">
          {sections.map(s => (
            <button
              key={s.key}
              onClick={() => { setSection(s.key); setSelectedTenantId(null); }}
              className={`inline-flex items-center gap-1.5 whitespace-nowrap px-3 py-2 text-xs font-semibold border-b-2 transition-colors ${
                section === s.key && !selectedTenantId
                  ? 'border-cyan-400 text-cyan-300'
                  : section === s.key
                    ? 'border-cyan-400 text-cyan-300'
                    : 'border-transparent text-slate-400 hover:text-slate-200'
              }`}
            >
              {s.icon} {s.label}
            </button>
          ))}
        </nav>
      </header>

      {securityOpen && (
        <div className="fixed inset-0 z-[70] bg-slate-900/50 flex items-start justify-center p-4 sm:p-8" onClick={() => setSecurityOpen(false)}>
          <div className="w-full max-w-lg" onClick={e => e.stopPropagation()}>
            <TwoFactorSettingsCard notify={notify} />
            <button
              onClick={() => { setSecurityOpen(false); setReconfirmOpen(true); }}
              className="mt-3 w-full rounded-lg border border-cyan-300 bg-cyan-50 px-3 py-2 text-xs font-semibold text-cyan-800 hover:bg-cyan-100"
            >
              <span className="inline-flex items-center gap-1.5">
                <Paintbrush size={13} /> Re-confirm password for platform actions
              </span>
            </button>
            <button
              onClick={() => setSecurityOpen(false)}
              className="mt-3 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50"
            >
              Close
            </button>
          </div>
        </div>
      )}

      <main className="flex-1 w-full max-w-[1680px] mx-auto px-4 lg:px-6 py-5">
        {selectedTenantId ? (
          <TenantDetail
            tenantId={selectedTenantId}
            user={user}
            onBack={() => setSelectedTenantId(null)}
            notify={notify}
            fail={fail}
          />
        ) : (
          <>
            {section === 'overview' && <OverviewSection notify={notify} fail={fail} />}
            {section === 'tenants' && <TenantsSection user={user} onOpenTenant={setSelectedTenantId} notify={notify} fail={fail} />}
            {section === 'plans' && <PlansSection notify={notify} fail={fail} />}
            {section === 'usage' && <UsageSection notify={notify} fail={fail} />}
            {section === 'infrastructure' && <InfrastructureSection user={user} notify={notify} fail={fail} />}
            {section === 'operations' && <OperationsSection user={user} notify={notify} fail={fail} />}
            {section === 'support' && <SupportSection user={user} onEnterTenant={onEnterTenant} notify={notify} fail={fail} />}
            {section === 'users' && <PlatformUsersSection user={user} notify={notify} fail={fail} />}
            {section === 'audit' && <AuditSection notify={notify} fail={fail} />}
          </>
        )}
      </main>

      <StepUpModal
        open={reconfirmOpen}
        onConfirm={async (password) => {
          try {
            await api.confirmPlatformStepUp(password);
            setReconfirmOpen(false);
            notify('success', 'Platform actions re-confirmed for 15 minutes.');
            return true;
          } catch {
            return false;
          }
        }}
        onCancel={() => setReconfirmOpen(false)}
      />
    </div>
  );
};

// ==================== Overview ====================

const OverviewSection: React.FC<{ notify: (k: 'error' | 'success', m: string) => void; fail: (e: any, f: string) => void }> = ({ notify, fail }) => {
  const [stats, setStats] = useState<PlatformOverviewStats | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setStats(await api.fetchPlatformOverview());
    } catch (err) {
      fail(err, 'Failed to load platform overview.');
    } finally {
      setLoading(false);
    }
  }, [fail]);

  useEffect(() => { load(); }, [load]);

  if (loading && !stats) return <SectionSpinner label="Loading platform telemetry…" />;
  if (!stats) return <EmptyState label="Overview unavailable." onRetry={load} />;

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold">Platform overview</h2>
        <button onClick={load} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
          <RefreshCw size={13} className={loading ? 'animate-spin' : ''} /> Refresh
        </button>
      </div>

      <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
        <Card label="Tenants" value={stats.tenants.total} sub={`${stats.commercial.newTenantsLast30Days} new in 30 days`} />
        <Card label="Active" value={stats.tenants.active} tone="emerald" sub={`${stats.tenants.trialing} trialing`} />
        <Card label="Needs attention" value={stats.tenants.needsAttention} tone="amber" sub={`${stats.tenants.suspended} suspended · ${stats.tenants.provisioning} provisioning · ${stats.tenants.expired} expired`} />
        <Card label="Contracted / month" value={fmtMoney(stats.commercial.contractedMonthlyValue, stats.commercial.currency)} tone="cyan" sub="Active + trialing plans (manual activation)" />
        <Card label="Studies this month" value={stats.usage.studiesThisMonth} />
        <Card label="Reports this month" value={stats.usage.reportsThisMonth} />
        <Card label="Platform users" value={stats.usage.users} />
        <Card label="Storage consumed" value={fmtBytes(stats.usage.storageBytes)} />
      </div>

      <div className="grid md:grid-cols-3 gap-3">
        <Card label="Trials expiring ≤ 7 days" value={stats.commercial.trialsExpiringIn7Days} tone={stats.commercial.trialsExpiringIn7Days > 0 ? 'amber' : 'slate'} />
        <Card label="Active break-glass sessions" value={stats.operations.activeSupportSessions} tone={stats.operations.activeSupportSessions > 0 ? 'rose' : 'slate'} />
        <Card label="Failed jobs" value={stats.operations.failedJobs} tone={stats.operations.failedJobs > 0 ? 'amber' : 'slate'} />
      </div>

      <div className="rounded-xl border border-slate-200 bg-white">
        <p className="border-b border-slate-100 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Recent tenant lifecycle events</p>
        {stats.operations.recentLifecycleEvents.length === 0 ? (
          <p className="px-4 py-4 text-sm text-slate-500">No lifecycle events recorded yet.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {stats.operations.recentLifecycleEvents.map(e => (
              <li key={e.id} className="flex items-center gap-3 px-4 py-2.5 text-sm">
                <span className="font-medium text-slate-700">{e.event.replace(/_/g, ' ')}</span>
                <span className="text-slate-400">tenant #{e.tenantId}</span>
                {e.toStatus && <StatusBadge status={e.toStatus} />}
                <span className="ml-auto text-xs text-slate-400">{fmtWhen(e.at)}</span>
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

// ==================== Tenants ====================

const TenantsSection: React.FC<{
  user: SessionUser;
  onOpenTenant: (id: string) => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ user, onOpenTenant, notify, fail }) => {
  const [tenants, setTenants] = useState<TenantRecord[]>([]);
  const [plans, setPlans] = useState<Plan[]>([]);
  const [q, setQ] = useState('');
  const [status, setStatus] = useState('');
  const [orgFilter, setOrgFilter] = useState<'all' | 'clinic' | 'hospital'>('all');
  const [loading, setLoading] = useState(true);
  const [provisionOpen, setProvisionOpen] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [t, p] = await Promise.all([
        api.fetchPlatformTenants({ q: q || undefined, status: status || undefined }),
        can(user, 'tenants.manage') ? api.fetchPlatformPlans().catch(() => []) : Promise.resolve([]),
      ]);
      setTenants(t);
      setPlans(p);
    } catch (err) {
      fail(err, 'Failed to load tenants.');
    } finally {
      setLoading(false);
    }
  }, [q, status, user, fail]);

  useEffect(() => { load(); }, [load]);

  const visibleTenants = useMemo(
    () => (orgFilter === 'all' ? tenants : tenants.filter(t => (t.orgType ?? 'clinic') === orgFilter)),
    [tenants, orgFilter],
  );

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <h2 className="text-lg font-bold mr-auto">Tenants</h2>
        <div className="flex rounded-lg border border-slate-300 bg-white p-0.5 text-xs font-semibold">
          {(['all', 'clinic', 'hospital'] as const).map(f => (
            <button
              key={f}
              type="button"
              onClick={() => setOrgFilter(f)}
              className={`rounded-md px-2.5 py-1 transition ${orgFilter === f ? 'bg-slate-900 text-white' : 'text-slate-500 hover:text-slate-700'}`}
            >
              {f === 'all' ? 'All types' : `${f}s`}
            </button>
          ))}
        </div>
        <div className="relative">
          <Search size={14} className="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            value={q}
            onChange={e => setQ(e.target.value)}
            placeholder="Search name or tenant code…"
            className="w-64 rounded-lg border border-slate-300 bg-white py-1.5 pl-8 pr-3 text-sm focus:border-cyan-500 focus:outline-none"
          />
        </div>
        <select
          value={status}
          onChange={e => setStatus(e.target.value)}
          className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm capitalize focus:border-cyan-500 focus:outline-none"
        >
          <option value="">All statuses</option>
          {['provisioning', 'trialing', 'active', 'suspended', 'expired', 'offboarding', 'terminated'].map(s => (
            <option key={s} value={s}>{s}</option>
          ))}
        </select>
        {can(user, 'tenants.manage') && (
          <button
            onClick={() => setProvisionOpen(true)}
            className="inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-700"
          >
            <Plus size={14} /> Provision tenant
          </button>
        )}
      </div>

      <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
        {loading && tenants.length === 0 ? (
          <SectionSpinner label="Loading tenants…" />
        ) : visibleTenants.length === 0 ? (
          <p className="px-4 py-8 text-center text-sm text-slate-500">No tenants match.</p>
        ) : (
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                <th className="px-4 py-2.5 font-semibold">Tenant</th>
                <th className="px-4 py-2.5 font-semibold">Status</th>
                <th className="px-4 py-2.5 font-semibold">Plan</th>
                <th className="px-4 py-2.5 font-semibold text-right">Users</th>
                <th className="px-4 py-2.5 font-semibold text-right">Studies</th>
                <th className="px-4 py-2.5 font-semibold">Term ends</th>
                <th className="px-4 py-2.5" />
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100">
              {visibleTenants.map(t => (
                <tr key={t.id} className="hover:bg-slate-50/70">
                  <td className="px-4 py-2.5">
                    <p className="font-semibold text-slate-800">
                      {t.name} <OrgTypeBadge orgType={t.orgType} />
                    </p>
                    <p className="text-[11px] text-slate-400">{t.tenantCode}</p>
                  </td>
                  <td className="px-4 py-2.5"><StatusBadge status={t.subscriptionStatus} /></td>
                  <td className="px-4 py-2.5 text-slate-600">{t.plan ? `${t.plan.name} · ${fmtMoney(t.plan.priceMonthly, t.plan.currency)}` : '—'}</td>
                  <td className="px-4 py-2.5 text-right tabular-nums">{t.counts.users}</td>
                  <td className="px-4 py-2.5 text-right tabular-nums">{t.counts.studies}</td>
                  <td className="px-4 py-2.5 text-xs text-slate-500">
                    {t.subscriptionStatus === 'trialing' ? fmtWhen(t.trialEndsAt) : fmtWhen(t.subscriptionEndsAt)}
                  </td>
                  <td className="px-4 py-2.5 text-right">
                    <button onClick={() => onOpenTenant(t.id)} className="inline-flex items-center gap-0.5 text-xs font-semibold text-cyan-700 hover:text-cyan-900">
                      Open <ChevronRight size={13} />
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {provisionOpen && (
        <ProvisionModal
          plans={plans}
          onClose={() => setProvisionOpen(false)}
          onProvisioned={(tenant, password) => {
            setProvisionOpen(false);
            load();
            notify('success', password
              ? `Tenant "${tenant.name}" provisioned. Initial admin password (shown once): ${password}`
              : `Tenant "${tenant.name}" provisioned.`);
          }}
          fail={fail}
        />
      )}
    </div>
  );
};

const ProvisionModal: React.FC<{
  plans: Plan[];
  onClose: () => void;
  onProvisioned: (tenant: TenantRecord, initialPassword: string | null) => void;
  fail: (e: any, f: string) => void;
}> = ({ plans, onClose, onProvisioned, fail }) => {
  const [name, setName] = useState('');
  const [adminName, setAdminName] = useState('');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminPhone, setAdminPhone] = useState('');
  const [planId, setPlanId] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    if (!name.trim() || !adminName.trim() || !adminEmail.trim()) return;
    setBusy(true);
    try {
      const result = await api.provisionTenant({
        name: name.trim(),
        adminName: adminName.trim(),
        adminEmail: adminEmail.trim(),
        adminPhone: adminPhone.trim() || undefined,
        planId: planId || null,
      });
      onProvisioned(result.tenant, result.initialAdminPassword);
    } catch (err) {
      fail(err, 'Provisioning failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <ModalShell title="Provision new tenant" onClose={onClose}>
      <div className="space-y-3">
        <Field label="Clinic / organization name">
          <input value={name} onChange={e => setName(e.target.value)} className={inputCls} placeholder="Alpha Hospital Network" />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Owner name">
            <input value={adminName} onChange={e => setAdminName(e.target.value)} className={inputCls} placeholder="Dr. Owner" />
          </Field>
          <Field label="Owner phone (optional)">
            <input value={adminPhone} onChange={e => setAdminPhone(e.target.value)} className={inputCls} placeholder="+61…" />
          </Field>
        </div>
        <Field label="Owner email">
          <input value={adminEmail} onChange={e => setAdminEmail(e.target.value)} type="email" className={inputCls} placeholder="owner@clinic.example" />
        </Field>
        <Field label="Plan">
          <select value={planId} onChange={e => setPlanId(e.target.value)} className={inputCls}>
            <option value="">Default (first active plan)</option>
            {plans.map(p => <option key={p.id} value={p.id}>{p.name} — {fmtMoney(p.priceMonthly, p.currency)} / {p.trialDays}d trial</option>)}
          </select>
        </Field>
        <p className="text-xs text-slate-500">The tenant starts with the plan's trial unless the operator activates it afterwards. The generated admin password is shown once after provisioning.</p>
        <div className="flex justify-end gap-2 pt-1">
          <button onClick={onClose} className={btnGhost}>Cancel</button>
          <button onClick={submit} disabled={busy || !name.trim() || !adminName.trim() || !adminEmail.trim()} className={btnPrimary}>
            {busy ? 'Provisioning…' : 'Provision tenant'}
          </button>
        </div>
      </div>
    </ModalShell>
  );
};

// ==================== Tenant 360 ====================

/**
 * Tab labels for Tenant 360. Generic words like "Branding" read like
 * platform self-service from inside a tenant's record — every label names
 * WHOSE setting this is and, where it matters, WHO may change it.
 */
const TENANT_TAB_LABELS: Record<(typeof TAB_KEYS)[number], string> = {
  overview: 'Overview',
  users: 'Tenant users',
  facilities: 'Tenant facilities',
  deployment: 'Deployment (platform)',
  branding: 'Tenant branding',
  integrations: 'Tenant integrations',
  lifecycle: 'Lifecycle (platform)',
  audit: 'Tenant audit',
  features: 'Feature entitlements',
};

const TAB_KEYS = ['overview', 'users', 'facilities', 'deployment', 'branding', 'integrations', 'lifecycle', 'audit', 'features'] as const;

const TenantDetail: React.FC<{
  tenantId: string;
  user: SessionUser;
  onBack: () => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenantId, user, onBack, notify, fail }) => {
  const [tenant, setTenant] = useState<Tenant360 | null>(null);
  const [tab, setTab] = useState<'overview' | 'users' | 'facilities' | 'deployment' | 'branding' | 'integrations' | 'lifecycle' | 'audit' | 'features'>('overview');
  const [confirmTerminate, setConfirmTerminate] = useState('');
  const [suspendReason, setSuspendReason] = useState('');
  const [busy, setBusy] = useState(false);
  const [editOpen, setEditOpen] = useState(false);
  const [userModal, setUserModal] = useState<{ mode: 'create' } | { mode: 'edit'; user: TenantUserRecord } | null>(null);
  const [facilityModal, setFacilityModal] = useState<{ facility: TenantFacilityRecord | null } | null>(null);
  const [oneTimeSecret, setOneTimeSecret] = useState<{ title: string; secret: string } | null>(null);

  const load = useCallback(async () => {
    try {
      setTenant(await api.fetchTenant360(tenantId));
    } catch (err) {
      fail(err, 'Failed to load tenant.');
    }
  }, [tenantId, fail]);

  useEffect(() => { load(); }, [load]);

  const act = async (action: api.TenantLifecycleAction, payload?: { reason?: string; confirmCode?: string }) => {
    setBusy(true);
    try {
      await api.tenantLifecycleAction(tenantId, action, payload);
      notify('success', `Action "${action.replace(/-/g, ' ')}" completed.`);
      await load();
    } catch (err) {
      fail(err, 'Lifecycle action failed.');
    } finally {
      setBusy(false);
    }
  };

  if (!tenant) return <SectionSpinner label="Loading tenant 360°…" />;

  const ent = tenant.entitlements;

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-3">
        <button onClick={onBack} className="inline-flex items-center gap-1 text-xs font-semibold text-slate-600 hover:text-slate-900">
          <ArrowLeft size={14} /> All tenants
        </button>
        <h2 className="text-lg font-bold">{tenant.name}</h2>
        <StatusBadge status={tenant.subscriptionStatus} />
        <OrgTypeBadge orgType={tenant.orgType} />
        <span className="text-xs text-slate-400">{tenant.tenantCode}</span>
        <div className="ml-auto flex flex-wrap items-center gap-2">
          {can(user, 'tenants.lifecycle') && tenant.subscriptionStatus === 'provisioning' && can(user, 'provisioning.manage') && (
            <button onClick={() => act('provision-retry')} disabled={busy} className={btnGhost}><RotateCcw size={13} /> Retry provisioning</button>
          )}
          {can(user, 'tenants.lifecycle') && ['trialing', 'expired', 'provisioning'].includes(tenant.subscriptionStatus) && (
            <button onClick={() => act('activate')} disabled={busy} className={btnPrimarySm}><Play size={13} /> Activate</button>
          )}
          {can(user, 'tenants.lifecycle') && tenant.subscriptionStatus === 'suspended' && (
            <button onClick={() => act('reactivate')} disabled={busy} className={btnPrimarySm}><RotateCcw size={13} /> Reactivate</button>
          )}
          {can(user, 'tenants.lifecycle') && ['trialing', 'active'].includes(tenant.subscriptionStatus) && (
            <button
              onClick={() => act('suspend', { reason: suspendReason || 'Suspended from platform console.' })}
              disabled={busy}
              className="inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100"
            >
              <Pause size={13} /> Suspend
            </button>
          )}
          {can(user, 'tenants.lifecycle') && ['trialing', 'active', 'suspended', 'expired'].includes(tenant.subscriptionStatus) && (
            <button onClick={() => act('offboard', { reason: 'Offboarding requested from platform console.' })} disabled={busy} className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100">
              <Archive size={13} /> Start offboarding
            </button>
          )}
          {can(user, 'tenants.lifecycle') && tenant.subscriptionStatus === 'offboarding' && (
            <div className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-white px-2 py-1">
              <input
                value={confirmTerminate}
                onChange={e => setConfirmTerminate(e.target.value)}
                placeholder={`type ${tenant.tenantCode}`}
                className="w-36 rounded border border-slate-300 px-2 py-1 text-xs focus:outline-none focus:border-rose-400"
              />
              <button
                onClick={() => act('terminate', { confirmCode: confirmTerminate, reason: 'Terminated after offboarding.' })}
                disabled={busy || confirmTerminate !== tenant.tenantCode}
                className="inline-flex items-center gap-1 rounded bg-rose-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-rose-700 disabled:opacity-40"
              >
                <XCircle size={13} /> Terminate
              </button>
            </div>
          )}
        </div>
      </div>

      {['suspended', 'active', 'trialing'].includes(tenant.subscriptionStatus) && can(user, 'tenants.lifecycle') && (
        <input
          value={suspendReason}
          onChange={e => setSuspendReason(e.target.value)}
          placeholder="Audit note for the next lifecycle action (optional)…"
          className="w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs focus:border-cyan-500 focus:outline-none"
        />
      )}

      {/*
        Acting-context banner. This screen is the PLATFORM operating ON a
        tenant (Tenant 360), not the tenant's own portal — make that
        unmissable so platform actions can never be mistaken for tenant-side
        self-service (and vice versa).
      */}
      <div className="rounded-lg border border-cyan-200 bg-cyan-50/60 px-3 py-2 text-xs leading-relaxed text-cyan-900">
        <strong>Platform control-plane view.</strong> You are operating on{' '}
        <strong>{tenant.name}</strong> ({tenant.orgType}) as platform staff. Every change below
        — branding, features, integrations, users, lifecycle — applies to <strong>this tenant
        only</strong>, is audited, and is invisible to the tenant's own admins until it takes
        effect in their portal.
      </div>

      <div className="flex gap-1 overflow-x-auto border-b border-slate-200">
        {(TAB_KEYS).map(t => (
          <button
            key={t}
            onClick={() => setTab(t)}
            className={`whitespace-nowrap px-3 py-2 text-xs font-semibold capitalize border-b-2 -mb-px ${tab === t ? 'border-cyan-500 text-cyan-700' : 'border-transparent text-slate-500 hover:text-slate-800'}`}
          >
            {TENANT_TAB_LABELS[t]}
          </button>
        ))}
      </div>

      {tab === 'overview' && (
        <div className="space-y-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <Card label="Plan" value={tenant.plan?.name ?? '—'} sub={tenant.plan ? `${fmtMoney(tenant.plan.priceMonthly, tenant.plan.currency)} / month` : 'No plan assigned'} />
            <Card label="Users" value={`${ent.usage.users}${ent.limits.maxUsers ? ` / ${ent.limits.maxUsers}` : ''}`} sub="seats" />
            <Card label="Studies (month)" value={`${ent.usage.studiesThisMonth}${ent.limits.maxStudiesPerMonth ? ` / ${ent.limits.maxStudiesPerMonth}` : ''}`} sub="metered" />
            <Card label="Storage" value={fmtBytes(ent.usage.storageBytes)} sub={ent.limits.maxStorageMb ? `of ${ent.limits.maxStorageMb} MB` : 'unmetered'} />
          </div>
          <div className="grid md:grid-cols-2 gap-3">
            <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-2 text-sm">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Subscription</p>
              <Row k="Status" v={<StatusBadge status={ent.subscriptionStatus} />} />
              <Row k="Trial ends" v={fmtWhen(ent.trialEndsAt)} />
              <Row k="Term ends" v={fmtWhen(ent.subscriptionEndsAt)} />
              <Row k="Patients" v={String(tenant.patientCount)} />
              <Row k="DICOM nodes" v={String(tenant.dicomNodeCount)} />
              <Row k="Created" v={tenant.createdAt ?? '—'} />
              <div className="flex flex-wrap gap-2 pt-1">
                {can(user, 'subscriptions.manage') && (
                  <button onClick={() => setEditOpen(true)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <Pencil size={13} /> Edit profile & subscription
                  </button>
                )}
                {can(user, 'tenants.manage') && (
                  <button
                    onClick={async () => {
                      try {
                        const data = await api.exportTenantData(tenant.id);
                        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = `tenant_${tenant.id}_export.json`;
                        a.click();
                        URL.revokeObjectURL(url);
                        notify('success', 'Tenant export downloaded.');
                      } catch (err) { fail(err, 'Export failed.'); }
                    }}
                    className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                  >
                    <Download size={13} /> Export tenant data (JSON)
                  </button>
                )}
              </div>
            </div>
            <div className="rounded-xl border border-slate-200 bg-white p-4">
              <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">Module entitlements (server-enforced)</p>
              <div className="mt-2 flex flex-wrap gap-2">
                {Object.entries(ent.features).map(([f, on]) => (
                  <span key={f} className={`inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-semibold ${on ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-500'}`}>
                    {on ? <CheckCircle2 size={12} /> : <XCircle size={12} />} {f}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </div>
      )}

      {tab === 'users' && (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <p className="text-xs text-slate-500">{tenant.users.length} account(s) — access changes revoke the user's live sessions and are audited.</p>
            {can(user, 'tenants.manage') && (
              <button onClick={() => setUserModal({ mode: 'create' })} className={btnPrimary}>
                <Plus size={13} /> Add user
              </button>
            )}
          </div>
          <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                  <th className="px-4 py-2.5 font-semibold">Name</th>
                  <th className="px-4 py-2.5 font-semibold">Email</th>
                  <th className="px-4 py-2.5 font-semibold">Role</th>
                  <th className="px-4 py-2.5 font-semibold">Login</th>
                  <th className="px-4 py-2.5 font-semibold">Last login</th>
                  {can(user, 'tenants.manage') && <th className="px-4 py-2.5 font-semibold text-right">Actions</th>}
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {tenant.users.map(u => {
                  const isActiveAdmin = u.isAdmin && u.role === 'admin';
                  const canManageRow = can(user, 'tenants.manage');
                  return (
                    <tr key={u.id}>
                      <td className="px-4 py-2.5 font-medium text-slate-800">
                        {u.name}
                        {u.isAdmin && <span className="ml-1.5 rounded bg-cyan-50 px-1.5 py-0.5 text-[10px] font-bold text-cyan-700">OWNER</span>}
                        {!u.active && <span className="ml-1.5 rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold text-slate-500">INACTIVE</span>}
                      </td>
                      <td className="px-4 py-2.5 text-slate-600">{u.email}</td>
                      <td className="px-4 py-2.5 capitalize text-slate-600">{u.role}</td>
                      <td className="px-4 py-2.5">{u.loginEnabled ? <span className="text-emerald-600 text-xs font-semibold">enabled</span> : <span className="text-rose-600 text-xs font-semibold">revoked</span>}</td>
                      <td className="px-4 py-2.5 text-xs text-slate-500">{u.lastLogin ? fmtWhen(u.lastLogin) : 'Never'}</td>
                      {canManageRow && (
                        <td className="px-4 py-2.5">
                          <div className="flex flex-wrap items-center justify-end gap-1.5">
                            <button onClick={() => setUserModal({ mode: 'edit', user: u })} disabled={busy} className={btnGhost} title="Edit identity and role">
                              <Pencil size={12} /> Edit
                            </button>
                            <button
                              onClick={async () => {
                                if (!window.confirm(`Rotate the password for ${u.email}? Live sessions are revoked and the new password is shown once.`)) return;
                                setBusy(true);
                                try {
                                  const { newPassword } = await api.resetTenantUserPassword(tenant.id, u.id);
                                  setOneTimeSecret({ title: `New password for ${u.email}`, secret: newPassword });
                                  await load();
                                } catch (err) { fail(err, 'Password reset failed.'); } finally { setBusy(false); }
                              }}
                              disabled={busy}
                              className={btnGhost}
                              title="Generate a new password (shown once)"
                            >
                              <KeyRound size={12} /> Reset password
                            </button>
                            <button
                              onClick={async () => {
                                setBusy(true);
                                try {
                                  await api.updateTenantUser(tenant.id, u.id, { loginEnabled: !u.loginEnabled });
                                  notify('success', `Login ${u.loginEnabled ? 'revoked' : 'restored'} for ${u.email}.`);
                                  await load();
                                } catch (err) { fail(err, 'Access change failed.'); } finally { setBusy(false); }
                              }}
                              disabled={busy}
                              className={u.loginEnabled
                                ? 'inline-flex items-center gap-1.5 rounded-lg border border-amber-300 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800 hover:bg-amber-100 disabled:opacity-40'
                                : 'inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 disabled:opacity-40'}
                              title={u.loginEnabled ? 'Revoke interactive login' : 'Restore interactive login'}
                            >
                              {u.loginEnabled ? <XCircle size={12} /> : <CheckCircle2 size={12} />} {u.loginEnabled ? 'Revoke login' : 'Restore login'}
                            </button>
                            <button
                              onClick={async () => {
                                setBusy(true);
                                try {
                                  await api.updateTenantUser(tenant.id, u.id, { isActive: !u.active });
                                  notify('success', `${u.name} is now ${u.active ? 'inactive' : 'active'}.`);
                                  await load();
                                } catch (err) { fail(err, 'Access change failed.'); } finally { setBusy(false); }
                              }}
                              disabled={busy}
                              className={btnGhost}
                              title={u.active ? 'Deactivate this account' : 'Reactivate this account'}
                            >
                              {u.active ? <Pause size={12} /> : <Play size={12} />} {u.active ? 'Deactivate' : 'Activate'}
                            </button>
                            {isActiveAdmin && (
                              <span className="text-[10px] font-semibold uppercase tracking-wide text-slate-400" title="The platform refuses to revoke the last active administrator of a tenant.">admin</span>
                            )}
                          </div>
                        </td>
                      )}
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {tab === 'facilities' && (
        <div className="space-y-2">
          <div className="flex items-center justify-between">
            <p className="text-xs text-slate-500">Facilities (locations) of this tenant. Deletion is refused while studies still reference a facility.</p>
            {can(user, 'tenants.manage') && (
              <button onClick={() => setFacilityModal({ facility: null })} className={btnPrimary}>
                <Plus size={13} /> Add facility
              </button>
            )}
          </div>
          <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
            {tenant.facilities.length === 0 ? (
              <p className="px-4 py-6 text-sm text-slate-500">No facility (location) records.</p>
            ) : (
              <ul className="divide-y divide-slate-100">
                {tenant.facilities.map(f => (
                  <li key={f.id} className="flex items-center gap-3 px-4 py-3">
                    <div className="min-w-0">
                      <p className="text-sm font-semibold text-slate-800">{f.name}</p>
                      <p className="text-xs text-slate-500">{f.address || '—'} {f.phone && `· ${f.phone}`}</p>
                    </div>
                    {can(user, 'tenants.manage') && (
                      <div className="ml-auto flex shrink-0 items-center gap-1.5">
                        <button onClick={() => setFacilityModal({ facility: f })} disabled={busy} className={btnGhost}>
                          <Pencil size={12} /> Edit
                        </button>
                        <button
                          onClick={async () => {
                            if (!window.confirm(`Delete facility "${f.name}"? This is refused while studies still reference it.`)) return;
                            setBusy(true);
                            try {
                              await api.deleteTenantFacility(tenant.id, f.id);
                              notify('success', `Facility "${f.name}" deleted.`);
                              await load();
                            } catch (err) { fail(err, 'Delete failed — the facility may still be referenced by studies.'); } finally { setBusy(false); }
                          }}
                          disabled={busy}
                          className="inline-flex items-center gap-1.5 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-800 hover:bg-rose-100 disabled:opacity-40"
                        >
                          <Trash2 size={12} /> Delete
                        </button>
                      </div>
                    )}
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>
      )}

      {tab === 'lifecycle' && (
        <ol className="relative border-l-2 border-slate-200 ml-3 space-y-4">
          {tenant.lifecycle.map(e => (
            <li key={e.id} className="ml-4">
              <span className="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full border-2 border-white bg-cyan-500" />
              <p className="text-sm font-semibold text-slate-800">{e.event.replace(/_/g, ' ')}</p>
              <p className="text-xs text-slate-500">{e.fromStatus ?? '—'} → {e.toStatus ?? '—'} · {fmtWhen(e.at)}</p>
              {e.details?.summary && <p className="mt-0.5 text-xs text-slate-600">{e.details.summary}</p>}
            </li>
          ))}
        </ol>
      )}

      {tab === 'audit' && (
        <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
          {tenant.audit.length === 0 ? (
            <p className="px-4 py-6 text-sm text-slate-500">No audit entries attributed to this tenant.</p>
          ) : (
            <ul className="divide-y divide-slate-100">
              {tenant.audit.map(l => (
                <li key={l.id} className="px-4 py-2.5 text-sm">
                  <div className="flex items-center gap-2">
                    <span className="font-semibold text-slate-800">{l.actionLabel}</span>
                    <span className="text-xs text-slate-400">{l.actorName}</span>
                    <span className="ml-auto text-xs text-slate-400">{fmtWhen(l.at)}</span>
                  </div>
                  {l.details && <p className="text-xs text-slate-600">{l.details}</p>}
                </li>
              ))}
            </ul>
          )}
        </div>
      )}

      {tab === 'deployment' && <DeploymentTab tenant={tenant} user={user} onChanged={load} notify={notify} fail={fail} />}

      {tab === 'branding' && <BrandingTab tenant={tenant} user={user} notify={notify} fail={fail} />}

      {tab === 'integrations' && <IntegrationsTab tenant={tenant} user={user} notify={notify} fail={fail} />}

      {tab === 'features' && <FeatureOverridesTab tenant={tenant} onChanged={load} notify={notify} fail={fail} />}

      {editOpen && (
        <EditTenantModal
          tenant={tenant}
          onClose={() => setEditOpen(false)}
          onSaved={() => { setEditOpen(false); load(); }}
          notify={notify}
          fail={fail}
        />
      )}
      {userModal?.mode === 'create' && (
        <TenantUserModal
          tenantId={tenant.id}
          onClose={() => setUserModal(null)}
          onSaved={secret => { setUserModal(null); if (secret) setOneTimeSecret(secret); load(); }}
          notify={notify}
          fail={fail}
        />
      )}
      {userModal?.mode === 'edit' && (
        <TenantUserModal
          tenantId={tenant.id}
          user={userModal.user}
          onClose={() => setUserModal(null)}
          onSaved={() => { setUserModal(null); load(); }}
          notify={notify}
          fail={fail}
        />
      )}
      {facilityModal && (
        <FacilityModal
          tenantId={tenant.id}
          facility={facilityModal.facility}
          onClose={() => setFacilityModal(null)}
          onSaved={() => { setFacilityModal(null); load(); }}
          notify={notify}
          fail={fail}
        />
      )}
      {oneTimeSecret && (
        <OneTimeSecretModal
          title={oneTimeSecret.title}
          secret={oneTimeSecret.secret}
          onClose={() => setOneTimeSecret(null)}
        />
      )}
    </div>
  );
};

// ==================== white-label branding + custom domains ====================

/**
 * White-labeling for one tenant. Branding is presentation only; a custom host
 * must pass DNS verification before it serves that brand, and a host never
 * grants access to tenant data (the tenant is always resolved from the session).
 */
// ==================== platform operations (master-prompt §80/§81) ====================

/** Health verdict badge for the fleet rollup. */
const HEALTH_STYLES: Record<TenantHealth, string> = {
  ok: 'bg-emerald-50 text-emerald-700 border-emerald-200',
  degraded: 'bg-amber-50 text-amber-700 border-amber-200',
  critical: 'bg-rose-50 text-rose-700 border-rose-200',
};

function HealthBadge({ health }: { health: TenantHealth }) {
  return (
    <span className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[11px] font-semibold capitalize ${HEALTH_STYLES[health] ?? HEALTH_STYLES.ok}`}>
      {health}
    </span>
  );
}

/** Quota read-out that distinguishes "unlimited" from "not measured here". */
function fmtQuota(q: { used: number | null; limit: number | null; percent: number | null }): string {
  if (q.limit === null) return 'unlimited';
  if (q.used === null) return 'not measured';
  return `${q.used} / ${q.limit}${q.percent !== null ? ` · ${q.percent}%` : ''}`;
}

/**
 * Operations: what is actually happening across the fleet (§80) and the tools
 * to act on it (§81). Every number comes from the server's own measurement —
 * nothing here is inferred client-side.
 */
const OperationsSection: React.FC<{
  user: SessionUser;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ user, notify, fail }) => {
  const [data, setData] = useState<PlatformOperationsPayload | null>(null);
  const [jobs, setJobs] = useState<FailedJobsPayload | null>(null);
  const [loading, setLoading] = useState(true);
  const [busyUuid, setBusyUuid] = useState<string | null>(null);
  const [recon, setRecon] = useState<{ tenantId: number; report: EntitlementReconciliation } | null>(null);
  const [reconBusy, setReconBusy] = useState(false);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      const [operations, failed] = await Promise.all([api.fetchPlatformOperations(), api.fetchFailedJobs()]);
      setData(operations);
      setJobs(failed);
    } catch (err) {
      fail(err, 'Failed to load platform operations.');
    } finally {
      setLoading(false);
    }
  }, [fail]);

  useEffect(() => { load(); }, [load]);

  const canManage = can(user, 'operations.manage');

  const retryJob = async (uuid: string) => {
    setBusyUuid(uuid);
    try {
      const result = await api.retryFailedJob(uuid);
      notify(
        result.requeued ? 'success' : 'error',
        result.requeued ? 'Job pushed back onto the queue.' : `Retry did not complete: ${result.output || 'no output'}`,
      );
      await load();
    } catch (err) {
      fail(err, 'Failed to retry the job.');
    } finally {
      setBusyUuid(null);
    }
  };

  const forgetJob = async (uuid: string) => {
    setBusyUuid(uuid);
    try {
      await api.forgetFailedJob(uuid);
      notify('success', 'Failed job discarded.');
      await load();
    } catch (err) {
      fail(err, 'Failed to discard the job.');
    } finally {
      setBusyUuid(null);
    }
  };

  const reconcile = async (tenantId: number, apply: boolean) => {
    setReconBusy(true);
    try {
      const report = await api.reconcileTenantEntitlements(String(tenantId), apply);
      setRecon({ tenantId, report });
      if (apply) notify('success', `Reconciled — ${report.pruned.length} stale override(s) pruned.`);
    } catch (err) {
      fail(err, 'Failed to reconcile entitlements.');
    } finally {
      setReconBusy(false);
    }
  };

  if (loading && !data) return <SectionSpinner label="Loading platform operations…" />;
  if (!data) return <EmptyState label="Operations unavailable." onRetry={load} />;

  const system = data.system;

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center gap-3">
        <h2 className="text-lg font-bold">Operations</h2>
        <button onClick={load} className={btnGhost}><RefreshCw size={13} className={loading ? 'animate-spin' : ''} /> Refresh</button>
        <span className="ml-auto text-xs text-slate-500">
          Measured {fmtWhen(system.checkedAt)} · {system.appEnv} · {system.appVersion}
        </span>
      </div>

      {data.provisioningStuck.length > 0 && (
        <div className="rounded-lg border border-indigo-300 bg-indigo-50 px-3 py-2 text-xs text-indigo-900">
          {data.provisioningStuck.length} tenant(s) are still provisioning — retry the provisioning step from their tenant record.
        </div>
      )}

      {data.summary.critical > 0 && (
        <div className="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-xs text-rose-900">
          {data.summary.critical} tenant(s) need attention now: {data.summary.failingIntegrations} failing integration(s),{' '}
          {data.summary.quotaBreaches} quota breach(es).
        </div>
      )}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Card label="Database" value={system.database} sub={`cache: ${system.cacheStore}`} tone={system.database === 'ok' ? 'emerald' : 'rose'} />
        <Card label="Queue" value={system.pendingJobs} sub={`${system.queueConnection} · oldest ${fmtWhen(system.oldestPendingJobAt)}`} tone={system.pendingJobs > 0 ? 'amber' : 'slate'} />
        <Card label="Failed jobs" value={system.failedJobs} sub={`store: ${system.failedJobDriver}`} tone={system.failedJobs > 0 ? 'rose' : 'emerald'} />
        <Card label="Storage" value={system.storageWritable ? 'writable' : 'read-only'} sub={system.lastLifecycleEvent ? `last lifecycle: ${system.lastLifecycleEvent}` : 'no lifecycle events'} tone={system.storageWritable ? 'emerald' : 'rose'} />
        <Card label="Tenants healthy" value={`${data.summary.ok} / ${data.summary.total}`} tone="emerald" />
        <Card label="Degraded" value={data.summary.degraded} tone={data.summary.degraded > 0 ? 'amber' : 'slate'} />
        <Card label="Critical" value={data.summary.critical} tone={data.summary.critical > 0 ? 'rose' : 'slate'} />
        <Card label="Failing integrations" value={data.summary.failingIntegrations} tone={data.summary.failingIntegrations > 0 ? 'rose' : 'slate'} />
      </div>

      <div className="rounded-xl border border-slate-200 bg-white">
        <div className="flex items-center gap-3 border-b border-slate-200 px-4 py-3">
          <h3 className="text-sm font-bold text-slate-800">Tenant health</h3>
          <span className="text-xs text-slate-500">
            Worst first · {data.returned} of {data.totalTenants} shown · storage {data.storageBasis}
          </span>
        </div>
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                <th className="px-4 py-2.5 font-semibold">Tenant</th>
                <th className="px-4 py-2.5 font-semibold">Health</th>
                <th className="px-4 py-2.5 font-semibold">Placement</th>
                <th className="px-4 py-2.5 font-semibold">Integrations</th>
                <th className="px-4 py-2.5 font-semibold">Quotas</th>
                <th className="px-4 py-2.5 font-semibold">Why</th>
                {canManage && <th className="px-4 py-2.5 font-semibold">Entitlements</th>}
              </tr>
            </thead>
            <tbody>
              {data.tenants.map(row => (
                <tr key={row.tenantId} className="border-b border-slate-100 last:border-0 align-top">
                  <td className="px-4 py-3">
                    <p className="font-semibold text-slate-800">{row.name}</p>
                    <p className="text-[11px] text-slate-500">{row.tenantCode ?? '—'} · <StatusBadge status={row.subscriptionStatus} /></p>
                  </td>
                  <td className="px-4 py-3"><HealthBadge health={row.health} /></td>
                  <td className="px-4 py-3 text-xs text-slate-600">
                    {row.region ?? '—'} / {row.deploymentStamp ?? '—'}
                    <p className="text-[11px] text-slate-400">{row.isolationProfile ?? 'unplaced'}</p>
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-600">
                    {row.integrations === 0 ? '—' : (
                      <>
                        <span className="tabular-nums">{row.integrations}</span> total
                        {row.failingIntegrations > 0 && <p className="text-rose-600">{row.failingIntegrations} failing</p>}
                        {row.unconfiguredIntegrations > 0 && <p className="text-amber-600">{row.unconfiguredIntegrations} unconfigured</p>}
                      </>
                    )}
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-600">
                    <p>users {fmtQuota(row.quotas.users)}</p>
                    <p>studies {fmtQuota(row.quotas.studies)}</p>
                    <p>
                      storage {row.storageBytes === null ? 'not measured' : fmtBytes(row.storageBytes)}
                      {row.quotas.storage.limit !== null && ` of ${fmtBytes(row.quotas.storage.limit)}`}
                    </p>
                  </td>
                  <td className="px-4 py-3 text-xs text-slate-600">
                    {row.reasons.length === 0 ? <span className="text-slate-400">healthy</span> : (
                      <ul className="list-disc space-y-0.5 pl-4">
                        {row.reasons.map((reason, index) => <li key={index}>{reason}</li>)}
                      </ul>
                    )}
                  </td>
                  {canManage && (
                    <td className="px-4 py-3">
                      <button disabled={reconBusy} onClick={() => reconcile(row.tenantId, false)} className={btnGhost}>
                        Reconcile
                      </button>
                    </td>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>

      {recon && (
        <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
          <div className="flex flex-wrap items-center gap-3">
            <h3 className="text-sm font-bold text-slate-800">
              Entitlement reconciliation — {data.tenants.find(t => t.tenantId === recon.tenantId)?.name ?? `tenant ${recon.tenantId}`}
            </h3>
            <button onClick={() => setRecon(null)} className={btnGhost}>Close</button>
            {recon.report.stale.length > 0 && (
              <button disabled={reconBusy} onClick={() => reconcile(recon.tenantId, true)} className={btnPrimary}>
                Prune {recon.report.stale.length} stale override(s)
              </button>
            )}
            {recon.report.applied && (
              <span className="text-xs text-emerald-700">Applied — {recon.report.pruned.length} pruned.</span>
            )}
          </div>
          <p className="text-xs text-slate-500">
            Overrides that contradict the plan are reported, never removed: they are a commercial decision.
          </p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                  <th className="px-3 py-2 font-semibold">Feature</th>
                  <th className="px-3 py-2 font-semibold">Override</th>
                  <th className="px-3 py-2 font-semibold">Plan</th>
                  <th className="px-3 py-2 font-semibold">Platform default</th>
                  <th className="px-3 py-2 font-semibold">Effective</th>
                  <th className="px-3 py-2 font-semibold">State</th>
                </tr>
              </thead>
              <tbody>
                {recon.report.overrides.map(row => (
                  <tr key={row.feature} className="border-b border-slate-100 last:border-0">
                    <td className="px-3 py-2 font-medium text-slate-700">{row.feature}</td>
                    <td className="px-3 py-2">{row.override ? 'on' : 'off'}</td>
                    <td className="px-3 py-2">{row.plan === null ? '—' : row.plan ? 'on' : 'off'}</td>
                    <td className="px-3 py-2">{row.platformDefault === null ? '—' : row.platformDefault ? 'on' : 'off'}</td>
                    <td className="px-3 py-2">{row.effective ? 'on' : 'off'}</td>
                    <td className={`px-3 py-2 text-xs ${row.state === 'stale' ? 'text-rose-600' : row.state === 'redundant' ? 'text-amber-600' : 'text-slate-600'}`}>
                      {row.state}
                    </td>
                  </tr>
                ))}
                {recon.report.overrides.length === 0 && (
                  <tr><td colSpan={6} className="px-3 py-4 text-center text-xs text-slate-500">No overrides stored for this tenant.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      <div className="grid gap-3 lg:grid-cols-2">
        <div className="rounded-xl border border-slate-200 bg-white">
          <div className="border-b border-slate-200 px-4 py-3">
            <h3 className="text-sm font-bold text-slate-800">Deployment health</h3>
            <p className="text-xs text-slate-500">Which region or stamp is affected.</p>
          </div>
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                <th className="px-4 py-2 font-semibold">Region / stamp</th>
                <th className="px-4 py-2 font-semibold">Tenants</th>
                <th className="px-4 py-2 font-semibold">Degraded</th>
                <th className="px-4 py-2 font-semibold">Critical</th>
              </tr>
            </thead>
            <tbody>
              {data.deployments.map(d => (
                <tr key={`${d.region}/${d.deploymentStamp}`} className="border-b border-slate-100 last:border-0">
                  <td className="px-4 py-2 text-slate-700">
                    {d.region ?? '—'} / {d.deploymentStamp ?? '—'}
                    <p className="text-[11px] text-slate-400">{d.isolationProfile ?? 'unplaced'}</p>
                  </td>
                  <td className="px-4 py-2 tabular-nums">{d.tenants}</td>
                  <td className={`px-4 py-2 tabular-nums ${d.degraded ? 'text-amber-600' : 'text-slate-400'}`}>{d.degraded}</td>
                  <td className={`px-4 py-2 tabular-nums ${d.critical ? 'text-rose-600' : 'text-slate-400'}`}>{d.critical}</td>
                </tr>
              ))}
              {data.deployments.length === 0 && (
                <tr><td colSpan={4} className="px-4 py-6 text-center text-xs text-slate-500">No tenants placed yet.</td></tr>
              )}
            </tbody>
          </table>
        </div>

        <div className="rounded-xl border border-slate-200 bg-white">
          <div className="border-b border-slate-200 px-4 py-3">
            <h3 className="text-sm font-bold text-slate-800">Failed background jobs</h3>
            <p className="text-xs text-slate-500">
              Payloads are never shown — only the job class, the exception and the property names it carries.
            </p>
          </div>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                  <th className="px-4 py-2 font-semibold">Job</th>
                  <th className="px-4 py-2 font-semibold">Failure</th>
                  {canManage && <th className="px-4 py-2 font-semibold">Actions</th>}
                </tr>
              </thead>
              <tbody>
                {(jobs?.jobs ?? []).map(job => (
                  <tr key={job.uuid} className="border-b border-slate-100 last:border-0 align-top">
                    <td className="px-4 py-3">
                      <p className="font-medium text-slate-800 break-all">{job.jobClass ?? 'unknown job'}</p>
                      <p className="text-[11px] text-slate-500">
                        queue {job.queue ?? '—'} · {job.attempts} attempt(s) · {fmtWhen(job.failedAt)}
                      </p>
                      {job.payloadPropertyNames.length > 0 && (
                        <p className="mt-1 text-[11px] text-slate-400 break-all">
                          payload properties: {job.payloadPropertyNames.join(', ')}
                        </p>
                      )}
                    </td>
                    <td className="px-4 py-3">
                      <p className="text-xs font-semibold text-rose-600">{job.exceptionType ?? 'Exception'}</p>
                      <p className="text-[11px] text-slate-600 break-all">{job.exceptionSummary ?? '—'}</p>
                    </td>
                    {canManage && (
                      <td className="px-4 py-3">
                        <div className="flex flex-col gap-1.5">
                          <button disabled={busyUuid === job.uuid} onClick={() => retryJob(job.uuid)} className={btnPrimary}>
                            <RotateCcw size={12} /> Retry
                          </button>
                          <button disabled={busyUuid === job.uuid} onClick={() => forgetJob(job.uuid)} className={btnGhost}>
                            <Trash2 size={12} /> Discard
                          </button>
                        </div>
                      </td>
                    )}
                  </tr>
                ))}
                {(jobs?.jobs ?? []).length === 0 && (
                  <tr>
                    <td colSpan={canManage ? 3 : 2} className="px-4 py-6 text-center text-xs text-slate-500">
                      No failed jobs. Nothing is stuck.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  );
};

// ==================== deployment topology ====================

/**
 * Infrastructure placement of one tenant. The catalog comes from the server
 * (config-owned), so the console can only offer placements the operator has
 * actually declared; the API validates the same list again server-side.
 */
const DeploymentTab: React.FC<{
  tenant: Tenant360;
  user: SessionUser;
  onChanged: () => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenant, user, onChanged, notify, fail }) => {
  const [catalog, setCatalog] = useState<DeploymentCatalog | null>(null);
  const [form, setForm] = useState({
    region: tenant.deployment.region ?? '',
    deploymentStamp: tenant.deployment.deploymentStamp ?? '',
    isolationProfile: tenant.deployment.isolationProfile ?? 'pooled',
    databaseCluster: tenant.deployment.databaseCluster ?? '',
    storageRegion: tenant.deployment.storageRegion ?? '',
    reason: '',
  });
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.fetchInfrastructure()
      .then(summary => setCatalog(summary.catalog))
      .catch(err => fail(err, 'Failed to load the deployment catalog.'));
  }, [fail]);

  const save = async () => {
    setBusy(true);
    try {
      await api.updateTenantDeployment(tenant.id, {
        region: form.region,
        deploymentStamp: form.deploymentStamp,
        isolationProfile: form.isolationProfile,
        databaseCluster: form.databaseCluster || null,
        storageRegion: form.storageRegion || null,
        reason: form.reason || undefined,
      });
      notify('success', `Deployment placement updated for ${tenant.name}.`);
      onChanged();
    } catch (err) {
      fail(err, 'Failed to update the deployment placement.');
    } finally {
      setBusy(false);
    }
  };

  if (!catalog) return <SectionSpinner label="Loading deployment catalog…" />;

  const field = 'w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-cyan-500 focus:outline-none';
  const label = 'text-[11px] font-semibold uppercase tracking-wide text-slate-500';
  const editable = can(user, 'infrastructure.manage');

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
      <p className="text-xs text-slate-500">
        Where this tenant's data physically lives. Region, stamp and isolation profile come from the
        operator's declared catalog; a change is audited and recorded in the tenant's lifecycle history.
      </p>
      <div className="grid md:grid-cols-2 gap-3">
        <label className="space-y-1"><span className={label}>Region</span>
          <select disabled={!editable} className={field} value={form.region} onChange={e => setForm({ ...form, region: e.target.value })}>
            <option value="">— select —</option>
            {catalog.regions.map(r => <option key={r.key} value={r.key}>{r.label}</option>)}
          </select>
        </label>
        <label className="space-y-1"><span className={label}>Deployment stamp</span>
          <select disabled={!editable} className={field} value={form.deploymentStamp} onChange={e => setForm({ ...form, deploymentStamp: e.target.value })}>
            <option value="">— select —</option>
            {catalog.deploymentStamps.map(s => <option key={s.key} value={s.key}>{s.label}</option>)}
          </select>
        </label>
        <label className="space-y-1"><span className={label}>Isolation profile</span>
          <select disabled={!editable} className={field} value={form.isolationProfile} onChange={e => setForm({ ...form, isolationProfile: e.target.value })}>
            {catalog.isolationProfiles.map(p => <option key={p.key} value={p.key}>{p.label}</option>)}
          </select>
        </label>
        <label className="space-y-1"><span className={label}>Database cluster</span>
          <input disabled={!editable} className={field} value={form.databaseCluster} onChange={e => setForm({ ...form, databaseCluster: e.target.value })} placeholder="primary" />
        </label>
        <label className="space-y-1"><span className={label}>Storage region</span>
          <input disabled={!editable} className={field} value={form.storageRegion} onChange={e => setForm({ ...form, storageRegion: e.target.value })} placeholder="defaults to the region" />
        </label>
        <label className="space-y-1"><span className={label}>Change reason (audited)</span>
          <input disabled={!editable} className={field} value={form.reason} onChange={e => setForm({ ...form, reason: e.target.value })} placeholder="e.g. enterprise contract requires dedicated isolation" />
        </label>
      </div>
      {editable ? (
        <button onClick={save} disabled={busy || !form.region || !form.deploymentStamp} className={btnPrimarySm}>
          <Server size={13} /> Save placement
        </button>
      ) : (
        <p className="text-[11px] text-slate-500">Read-only: your platform role does not hold <code>infrastructure.manage</code>.</p>
      )}
    </div>
  );
};

const InfrastructureSection: React.FC<{
  user: SessionUser;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ user, notify, fail }) => {
  const [summary, setSummary] = useState<InfrastructureSummary | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setSummary(await api.fetchInfrastructure());
    } catch (err) {
      fail(err, 'Failed to load infrastructure placement.');
    } finally {
      setLoading(false);
    }
  }, [fail]);

  useEffect(() => { load(); }, [load]);

  if (loading && !summary) return <SectionSpinner label="Loading infrastructure placement…" />;
  if (!summary) return null;

  const buckets: { title: string; rows: { key: string; tenants: number }[] }[] = [
    { title: 'Tenants per region', rows: summary.placement.regions },
    { title: 'Tenants per deployment stamp', rows: summary.placement.deploymentStamps },
    { title: 'Tenants per isolation profile', rows: summary.placement.isolationProfiles },
  ];

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <h2 className="text-sm font-bold text-slate-800">Deployment topology — platform-managed placement</h2>
        <button onClick={load} className={btnGhost}><RefreshCw size={13} /> Refresh</button>
        <span className="ml-auto text-xs text-slate-500">
          {can(user, 'infrastructure.manage') ? 'Re-place a tenant from its Deployment tab.' : 'Read-only for your platform role.'}
        </span>
      </div>

      {summary.unplacedTenants > 0 && (
        <div className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
          {summary.unplacedTenants} tenant(s) have no isolation profile recorded — re-place them from their tenant record.
        </div>
      )}

      <div className="grid md:grid-cols-3 gap-3">
        {buckets.map(b => (
          <div key={b.title} className="rounded-xl border border-slate-200 bg-white p-4">
            <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{b.title}</p>
            <ul className="mt-2 space-y-1">
              {b.rows.length === 0 && <li className="text-xs text-slate-400">No tenants.</li>}
              {b.rows.map(r => (
                <li key={r.key} className="flex items-center justify-between text-sm">
                  <span className="font-medium text-slate-700">{r.key}</span>
                  <span className="tabular-nums text-slate-500">{r.tenants}</span>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-4">
        <p className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Declared catalog</p>
        <div className="mt-2 grid md:grid-cols-3 gap-3 text-sm">
          <div>
            <p className="text-xs font-semibold text-slate-600">Regions</p>
            <ul className="mt-1 space-y-0.5">
              {summary.catalog.regions.map(r => <li key={r.key} className="text-xs text-slate-600">{r.label} <span className="text-slate-400">({r.key} · storage {r.storage})</span></li>)}
            </ul>
          </div>
          <div>
            <p className="text-xs font-semibold text-slate-600">Stamps</p>
            <ul className="mt-1 space-y-0.5">
              {summary.catalog.deploymentStamps.map(s => <li key={s.key} className="text-xs text-slate-600">{s.label} <span className="text-slate-400">({s.key})</span></li>)}
            </ul>
          </div>
          <div>
            <p className="text-xs font-semibold text-slate-600">Isolation profiles</p>
            <ul className="mt-1 space-y-0.5">
              {summary.catalog.isolationProfiles.map(p => <li key={p.key} className="text-xs text-slate-600">{p.label} <span className="text-slate-400">({p.key})</span></li>)}
            </ul>
          </div>
        </div>
      </div>
    </div>
  );
};

const BrandingTab: React.FC<{
  tenant: Tenant360;
  user: SessionUser;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenant, user, notify, fail }) => {
  const [payload, setPayload] = useState<TenantBrandingPayload | null>(null);
  const [form, setForm] = useState<TenantBranding | null>(null);
  const [newHost, setNewHost] = useState('');
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const data = await api.fetchTenantBranding(tenant.id);
      setPayload(data);
      setForm(data.branding);
    } catch (err) {
      fail(err, 'Failed to load branding.');
    }
  }, [tenant.id, fail]);

  useEffect(() => { load(); }, [load]);

  if (!payload || !form) return <SectionSpinner label="Loading branding…" />;

  const canManage = can(user, 'tenants.manage');
  const canBrand = canManage && payload.entitlements.branding;
  const canDomains = canManage && payload.entitlements.customDomains;

  const saveBranding = async () => {
    setBusy(true);
    try {
      const updated = await api.updateTenantBranding(tenant.id, {
        appName: form.appName,
        primaryColor: form.primaryColor,
        accentColor: form.accentColor,
        logoUrl: form.logoUrl || null,
        faviconUrl: form.faviconUrl || null,
        loginMessage: form.loginMessage || null,
        reportHeader: form.reportHeader || null,
        reportFooter: form.reportFooter || null,
        emailFromName: form.emailFromName,
        emailFromAddress: form.emailFromAddress || null,
        supportEmail: form.supportEmail || null,
        supportPhone: form.supportPhone || null,
      });
      setPayload(updated);
      setForm(updated.branding);
      notify('success', `Branding saved for ${tenant.name}.`);
    } catch (err) {
      fail(err, 'Failed to save branding.');
    } finally {
      setBusy(false);
    }
  };

  const run = async (fn: () => Promise<TenantBrandingPayload>, ok: string) => {
    setBusy(true);
    try {
      const updated = await fn();
      setPayload(updated);
      setForm(updated.branding);
      notify(updated.brandingOverridden ? 'success' : 'error', updated.brandingOverridden ? ok : 'No changes were saved — the form was identical to the current values.');
    } catch (err) {
      fail(err, 'Domain action failed.');
    } finally {
      setBusy(false);
    }
  };

  const field = 'w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-cyan-500 focus:outline-none';
  const label = 'text-[11px] font-semibold uppercase tracking-wide text-slate-500';

  return (
    <div className="space-y-4">
      <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
        <div className="flex items-center gap-2">
          <p className="text-sm font-bold text-slate-800">White-label presentation — {tenant.name}'s portal, login screen and emails</p>
          {!payload.brandingOverridden && <span className="rounded-full border border-slate-200 bg-slate-50 px-2 py-0.5 text-[11px] text-slate-600">using platform defaults</span>}
        </div>
        <p className="text-xs text-slate-500">
          Platform-managed presentation for this tenant only. These values change what
          <strong> {tenant.name}'s users</strong> see — they never alter the PolytronX platform brand,
          and the tenant's own admins cannot edit them here (this console is platform staff only).
        </p>
        {!payload.entitlements.branding && (
          <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            The <code>branding</code> entitlement is disabled for this tenant — enable it in the Features tab first.
          </p>
        )}
        <div className="grid md:grid-cols-2 gap-3">
          <label className="space-y-1"><span className={label}>Application name</span>
            <input disabled={!canBrand} className={field} value={form.appName} onChange={e => setForm({ ...form, appName: e.target.value })} />
          </label>
          <label className="space-y-1"><span className={label}>Primary colour</span>
            <input disabled={!canBrand} className={field} value={form.primaryColor} onChange={e => setForm({ ...form, primaryColor: e.target.value })} placeholder="#0e7490" />
          </label>
          <label className="space-y-1"><span className={label}>Accent colour</span>
            <input disabled={!canBrand} className={field} value={form.accentColor} onChange={e => setForm({ ...form, accentColor: e.target.value })} placeholder="#06b6d4" />
          </label>
          <label className="space-y-1"><span className={label}>Logo URL</span>
            <input disabled={!canBrand} className={field} value={form.logoUrl ?? ''} onChange={e => setForm({ ...form, logoUrl: e.target.value })} placeholder="https://…" />
          </label>
          <label className="space-y-1"><span className={label}>Login message</span>
            <input disabled={!canBrand} className={field} value={form.loginMessage ?? ''} onChange={e => setForm({ ...form, loginMessage: e.target.value })} />
          </label>
          <label className="space-y-1"><span className={label}>Email from name</span>
            <input disabled={!canBrand} className={field} value={form.emailFromName} onChange={e => setForm({ ...form, emailFromName: e.target.value })} />
          </label>
          <label className="space-y-1"><span className={label}>Email from address</span>
            <input disabled={!canBrand} className={field} value={form.emailFromAddress ?? ''} onChange={e => setForm({ ...form, emailFromAddress: e.target.value })} />
          </label>
          <label className="space-y-1"><span className={label}>Support email</span>
            <input disabled={!canBrand} className={field} value={form.supportEmail ?? ''} onChange={e => setForm({ ...form, supportEmail: e.target.value })} />
          </label>
          <label className="space-y-1"><span className={label}>Support phone</span>
            <input disabled={!canBrand} className={field} value={form.supportPhone ?? ''} onChange={e => setForm({ ...form, supportPhone: e.target.value })} />
          </label>
          <label className="space-y-1 md:col-span-2"><span className={label}>Report header</span>
            <textarea disabled={!canBrand} rows={2} className={field} value={form.reportHeader ?? ''} onChange={e => setForm({ ...form, reportHeader: e.target.value })} />
          </label>
          <label className="space-y-1 md:col-span-2"><span className={label}>Report footer</span>
            <textarea disabled={!canBrand} rows={2} className={field} value={form.reportFooter ?? ''} onChange={e => setForm({ ...form, reportFooter: e.target.value })} />
          </label>
        </div>
        {canBrand ? (
          <button onClick={saveBranding} disabled={busy} className={btnPrimarySm}><Pencil size={13} /> Save {tenant.name} branding</button>
        ) : (
          <p className="text-[11px] text-slate-500">Read-only: you need <code>tenants.manage</code> and the tenant's <code>branding</code> entitlement.</p>
        )}
      </div>

      <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
        <p className="text-sm font-bold text-slate-800">Custom domains — DNS-verified hosts that may serve {tenant.name} branding</p>
        <p className="text-xs text-slate-500">
          Publish a TXT record <code>{payload.verificationRecord.replace('<host>', 'your.host')}</code> containing{' '}
          <code>polytronx-ris-verify=&lt;tenant code&gt;</code>, then verify. Only verified hosts serve this tenant's brand,
          and a host is never an authorization input.
        </p>
        {!payload.entitlements.customDomains && (
          <p className="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            The <code>custom_domains</code> entitlement is disabled for this tenant.
          </p>
        )}
        {payload.domains.length === 0 ? (
          <p className="text-xs text-slate-500">No custom domain registered.</p>
        ) : (
          <ul className="divide-y divide-slate-100 rounded-lg border border-slate-200">
            {payload.domains.map(d => (
              <li key={d.id} className="flex flex-wrap items-center gap-2 px-3 py-2 text-sm">
                <span className="font-medium text-slate-800">{d.host}</span>
                {d.isPrimary && <span className="rounded-full border border-cyan-200 bg-cyan-50 px-2 py-0.5 text-[11px] text-cyan-700">primary</span>}
                <span className={`rounded-full border px-2 py-0.5 text-[11px] ${d.verifiedAt ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-amber-200 bg-amber-50 text-amber-700'}`}>
                  {d.verifiedAt ? `verified ${fmtWhen(d.verifiedAt)}` : 'unverified'}
                </span>
                {canDomains && (
                  <span className="ml-auto flex items-center gap-2">
                    <button disabled={busy} onClick={() => run(() => api.verifyTenantDomain(tenant.id, d.id), `DNS verification ran for ${d.host}.`)} className={btnGhost}>Verify</button>
                    {!d.isPrimary && <button disabled={busy} onClick={() => run(() => api.makeTenantDomainPrimary(tenant.id, d.id), `${d.host} is now primary.`)} className={btnGhost}>Make primary</button>}
                    <button disabled={busy} onClick={() => run(() => api.deleteTenantDomain(tenant.id, d.id), `${d.host} removed.`)} className="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100"><Trash2 size={13} /> Remove</button>
                  </span>
                )}
              </li>
            ))}
          </ul>
        )}
        {canDomains && (
          <div className="flex flex-wrap items-center gap-2">
            <input className={`${field} max-w-xs`} value={newHost} onChange={e => setNewHost(e.target.value)} placeholder="ris.hospital.com" />
            <button
              disabled={busy || newHost.trim() === ''}
              onClick={() => run(() => api.addTenantDomain(tenant.id, newHost.trim()), `${newHost.trim()} registered (unverified).`).then(() => setNewHost(''))}
              className={btnPrimarySm}
            >
              <Plus size={13} /> Add domain
            </button>
          </div>
        )}
      </div>
    </div>
  );
};

// ==================== tenant integrations ====================

const INTEGRATION_STATUS_STYLES: Record<string, string> = {
  active: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  error: 'border-rose-200 bg-rose-50 text-rose-700',
  unconfigured: 'border-amber-200 bg-amber-50 text-amber-700',
  disabled: 'border-slate-200 bg-slate-50 text-slate-600',
};

/**
 * Tenant integration registry. Credentials are write-only: the API returns a
 * mask, never a value, so rotating a secret means typing a new one.
 * Health checks are real — the button reports exactly what the probe did.
 */
const IntegrationsTab: React.FC<{
  tenant: Tenant360;
  user: SessionUser;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenant, user, notify, fail }) => {
  const [payload, setPayload] = useState<TenantIntegrationsPayload | null>(null);
  const [busy, setBusy] = useState(false);
  const [creating, setCreating] = useState(false);
  const [type, setType] = useState('');
  const [name, setName] = useState('');
  const [facilityId, setFacilityId] = useState('');
  const [config, setConfig] = useState<Record<string, string>>({});
  const [secrets, setSecrets] = useState<Record<string, string>>({});
  const [rotateFor, setRotateFor] = useState<TenantIntegrationRecord | null>(null);
  const [rotateValues, setRotateValues] = useState<Record<string, string>>({});
  const [editFor, setEditFor] = useState<TenantIntegrationRecord | null>(null);
  const [editName, setEditName] = useState('');
  const [editFacilityId, setEditFacilityId] = useState('');
  const [editConfig, setEditConfig] = useState<Record<string, string>>({});

  const load = useCallback(async () => {
    try {
      setPayload(await api.fetchTenantIntegrations(tenant.id));
    } catch (err) {
      fail(err, 'Failed to load integrations.');
    }
  }, [tenant.id, fail]);

  useEffect(() => { load(); }, [load]);

  if (!payload) return <SectionSpinner label="Loading integrations…" />;

  const canManage = can(user, 'tenants.manage');
  const def = payload.catalog.find(c => c.type === type) ?? null;
  const field = 'w-full rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs focus:border-cyan-500 focus:outline-none';
  const label = 'text-[11px] font-semibold uppercase tracking-wide text-slate-500';

  const run = async (fn: () => Promise<TenantIntegrationsPayload>, ok: string) => {
    setBusy(true);
    try {
      const updated = await fn();
      setPayload(updated);
      notify('success', ok);
    } catch (err) {
      fail(err, 'Integration action failed.');
    } finally {
      setBusy(false);
    }
  };

  const create = async () => {
    setBusy(true);
    try {
      const updated = await api.createTenantIntegration(tenant.id, {
        type,
        name: name.trim(),
        facilityId: facilityId || null,
        config,
        secrets,
      });
      setPayload(updated);
      notify('success', `${name.trim()} registered.`);
      setCreating(false);
      setName('');
      setType('');
      setFacilityId('');
      setConfig({});
      setSecrets({});
    } catch (err) {
      fail(err, 'Failed to create the integration.');
    } finally {
      setBusy(false);
    }
  };

  const saveEdit = async () => {
    if (!editFor) return;
    setBusy(true);
    try {
      const updated = await api.updateTenantIntegration(tenant.id, editFor.id, {
        name: editName.trim(),
        facilityId: editFacilityId || null,
        config: editConfig,
      });
      setPayload(updated);
      notify('success', `${editName.trim()} updated.`);
      setEditFor(null);
    } catch (err) {
      fail(err, 'Failed to update the integration.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-xs text-slate-500">
          Each integration belongs to this tenant only; credentials are encrypted at rest and never returned by the API.
        </p>
        {canManage && (
          <button onClick={() => setCreating(v => !v)} className={`${btnPrimarySm} ml-auto`}>
            <Plus size={13} /> {creating ? 'Cancel' : 'Register integration'}
          </button>
        )}
      </div>

      {creating && (
        <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
          <div className="grid md:grid-cols-3 gap-3">
            <label className="space-y-1"><span className={label}>Type</span>
              <select className={field} value={type} onChange={e => { setType(e.target.value); setConfig({}); setSecrets({}); }}>
                <option value="">— select —</option>
                {payload.catalog.map(c => (
                  <option key={c.type} value={c.type} disabled={c.feature ? payload.entitlements[c.feature] === false : false}>
                    {c.label}{c.feature && payload.entitlements[c.feature] === false ? ' (entitlement off)' : ''}
                  </option>
                ))}
              </select>
            </label>
            <label className="space-y-1"><span className={label}>Name</span>
              <input className={field} value={name} onChange={e => setName(e.target.value)} placeholder="e.g. Main PACS" />
            </label>
            <label className="space-y-1"><span className={label}>Facility (optional)</span>
              <select className={field} value={facilityId} onChange={e => setFacilityId(e.target.value)}>
                <option value="">All facilities</option>
                {tenant.facilities.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
              </select>
            </label>
          </div>

          {def && (
            <>
              <div className="grid md:grid-cols-3 gap-3">
                {def.requiredKeys.map(key => (
                  <label key={key} className="space-y-1"><span className={label}>{key} <span className="text-rose-500">*</span></span>
                    <input className={field} value={config[key] ?? ''} onChange={e => setConfig({ ...config, [key]: e.target.value })} />
                  </label>
                ))}
                {def.secretKeys.map(key => (
                  <label key={key} className="space-y-1"><span className={label}>{key} (credential)</span>
                    <input type="password" className={field} value={secrets[key] ?? ''} onChange={e => setSecrets({ ...secrets, [key]: e.target.value })} placeholder="stored encrypted" />
                  </label>
                ))}
              </div>
              <p className="text-[11px] text-slate-500">
                Health check: <span className="font-semibold">{def.probe === 'tcp' ? 'TCP socket reachability' : def.probe === 'http' ? 'live HTTP request' : 'configuration completeness only (no delivery is asserted)'}</span>
                {' '}· entitlement <code>{def.feature}</code>
              </p>
              <button onClick={create} disabled={busy || !name.trim() || !type} className={btnPrimarySm}>Register integration</button>
            </>
          )}
        </div>
      )}

      {payload.integrations.length === 0 ? (
        <p className="rounded-xl border border-slate-200 bg-white px-4 py-6 text-sm text-slate-500">No integrations registered for this tenant.</p>
      ) : (
        <ul className="space-y-2">
          {payload.integrations.map(i => (
            <li key={i.id} className="rounded-xl border border-slate-200 bg-white p-4">
              <div className="flex flex-wrap items-center gap-2">
                <span className="font-semibold text-sm text-slate-800">{i.name}</span>
                <span className="text-xs text-slate-500">{i.typeLabel}</span>
                <span className={`rounded-full border px-2 py-0.5 text-[11px] font-semibold ${INTEGRATION_STATUS_STYLES[i.status] ?? ''}`}>{i.status}</span>
                {i.facilityId && <span className="text-[11px] text-slate-400">facility {tenant.facilities.find(f => f.id === i.facilityId)?.name ?? i.facilityId}</span>}
                <span className="ml-auto text-[11px] text-slate-400">last check {fmtWhen(i.lastCheckedAt)}</span>
              </div>

              <div className="mt-2 grid md:grid-cols-2 gap-2 text-xs">
                <div className="space-y-0.5">
                  {Object.entries(i.config).length === 0
                    ? <p className="text-slate-400">No configuration stored.</p>
                    : Object.entries(i.config).map(([k, v]) => <p key={k} className="text-slate-600"><span className="font-medium">{k}</span>: {v}</p>)}
                </div>
                <div className="space-y-0.5">
                  {Object.keys(i.secrets).length === 0
                    ? <p className="text-slate-400">No credentials required for this type.</p>
                    : Object.entries(i.secrets).map(([k, s]) => (
                      <p key={k} className="text-slate-600"><span className="font-medium">{k}</span>: {s.present ? s.mask : <span className="text-amber-600">not set</span>}</p>
                    ))}
                </div>
              </div>

              {i.lastError && <p className="mt-2 rounded-lg border border-rose-200 bg-rose-50 px-2.5 py-1.5 text-[11px] text-rose-700">{i.lastError}</p>}

              {i.deliveryLog.length > 0 && (
                <div className="mt-2 rounded-lg border border-slate-200 bg-slate-50/60 px-2.5 py-2">
                  <p className="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Recent deliveries</p>
                  <ul className="mt-1 space-y-0.5">
                    {i.deliveryLog.map(d => (
                      <li key={d.id} className="flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span className={`rounded px-1 py-px font-semibold ${d.status === 'sent' ? 'bg-emerald-100 text-emerald-700' : d.status === 'failed' ? 'bg-rose-100 text-rose-700' : 'bg-slate-200 text-slate-600'}`}>{d.status}</span>
                        <span className="text-slate-600">{d.event}</span>
                        {d.target && <span className="text-slate-400">→ {d.target}</span>}
                        {d.latencyMs != null && <span className="text-slate-400">{d.latencyMs} ms</span>}
                        <span className="ml-auto text-slate-400">{d.at ? new Date(d.at).toLocaleString() : ''}</span>
                      </li>
                    ))}
                  </ul>
                </div>
              )}

              {canManage && (
                <div className="mt-3 flex flex-wrap items-center gap-2">
                  <button disabled={busy} onClick={() => { setEditFor(i); setEditName(i.name); setEditFacilityId(i.facilityId ?? ''); setEditConfig({ ...i.config }); }} className={btnGhost}>
                    <Edit2 size={13} /> Edit
                  </button>
                  <button disabled={busy} onClick={() => run(() => api.probeTenantIntegration(tenant.id, i.id), `Health check ran for ${i.name}.`)} className={btnGhost}>
                    <Activity size={13} /> Run health check
                  </button>
                  {i.type !== 'dicom' && (
                    <button
                      disabled={busy}
                      onClick={() => run(() => api.testTenantIntegration(tenant.id, i.id), `Test delivery attempted for ${i.name} — result below.`)}
                      className={btnGhost}
                    >
                      <CheckCircle2 size={13} /> Send test
                    </button>
                  )}
                  {Object.keys(i.secrets).length > 0 && (
                    <button disabled={busy} onClick={() => { setRotateFor(i); setRotateValues({}); }} className={btnGhost}>
                      <KeyRound size={13} /> Rotate credentials
                    </button>
                  )}
                  <button disabled={busy} onClick={() => run(() => api.deleteTenantIntegration(tenant.id, i.id), `${i.name} deleted.`)} className="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-100">
                    <Trash2 size={13} /> Delete
                  </button>
                </div>
              )}

              {editFor?.id === i.id && (
                <div className="mt-3 space-y-2 rounded-lg border border-cyan-200 bg-cyan-50/40 p-3">
                  <div className="grid md:grid-cols-2 gap-2">
                    <label className="space-y-1"><span className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Name</span>
                      <input className={field} value={editName} onChange={e => setEditName(e.target.value)} />
                    </label>
                    <label className="space-y-1"><span className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">Facility (optional)</span>
                      <select className={field} value={editFacilityId} onChange={e => setEditFacilityId(e.target.value)}>
                        <option value="">All facilities</option>
                        {tenant.facilities.map(f => <option key={f.id} value={f.id}>{f.name}</option>)}
                      </select>
                    </label>
                  </div>
                  <div className="grid md:grid-cols-3 gap-2">
                    {(payload.catalog.find(c => c.type === i.type)?.requiredKeys ?? Object.keys(i.config)).map(key => (
                      <label key={key} className="space-y-1"><span className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{key}</span>
                        <input className={field} value={editConfig[key] ?? ''} onChange={e => setEditConfig({ ...editConfig, [key]: e.target.value })} />
                      </label>
                    ))}
                  </div>
                  <p className="text-[11px] text-slate-500">Configuration is replaced wholesale on save; stored credentials are untouched — use Rotate credentials to change those.</p>
                  <div className="flex gap-2">
                    <button disabled={busy || !editName.trim()} onClick={saveEdit} className={btnPrimarySm}>Save changes</button>
                    <button disabled={busy} onClick={() => setEditFor(null)} className={btnGhost}>Cancel</button>
                  </div>
                </div>
              )}

              {rotateFor?.id === i.id && (
                <div className="mt-3 space-y-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
                  <p className="text-[11px] text-slate-500">New values replace the stored credentials. They are never displayed again.</p>
                  <div className="grid md:grid-cols-2 gap-2">
                    {Object.keys(i.secrets).map(k => (
                      <input key={k} type="password" className={field} placeholder={k} value={rotateValues[k] ?? ''} onChange={e => setRotateValues({ ...rotateValues, [k]: e.target.value })} />
                    ))}
                  </div>
                  <div className="flex gap-2">
                    <button
                      disabled={busy || Object.values(rotateValues).every(v => !v)}
                      onClick={() => run(() => api.rotateTenantIntegrationSecrets(tenant.id, i.id, rotateValues), `Credentials rotated for ${i.name}.`).then(() => setRotateFor(null))}
                      className={btnPrimarySm}
                    >
                      Save new credentials
                    </button>
                    <button onClick={() => setRotateFor(null)} className={btnGhost}>Cancel</button>
                  </div>
                </div>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
};

const FeatureOverridesTab: React.FC<{
  tenant: Tenant360;
  onChanged: () => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenant, onChanged, notify, fail }) => {
  const [busy, setBusy] = useState(false);
  const effective = tenant.entitlements.features;
  const overrides = Object.fromEntries(tenant.featureOverrides.map(o => [o.feature, o.enabled]));

  const setFeature = async (feature: string, enabled: boolean) => {
    setBusy(true);
    try {
      await api.updateTenantFeatures(tenant.id, { ...overrides, [feature]: enabled });
      notify('success', `Feature "${feature}" ${enabled ? 'enabled' : 'disabled'} for ${tenant.name}.`);
      onChanged();
    } catch (err) {
      fail(err, 'Failed to update feature override.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
      <p className="text-xs text-slate-500">Platform overrides sit on top of the plan's feature set. The server enforces these on every write endpoint.</p>
      {Object.entries(effective).map(([feature, on]) => (
        <div key={feature} className="flex items-center justify-between rounded-lg border border-slate-200 px-3 py-2">
          <div>
            <p className="text-sm font-semibold text-slate-800 capitalize">{feature}</p>
            <p className="text-[11px] text-slate-500">currently {on ? 'enabled' : 'disabled'} {feature in overrides ? '(platform override)' : '(plan/default)'}</p>
          </div>
          <button
            disabled={busy}
            onClick={() => setFeature(feature, !on)}
            className={`rounded-lg px-3 py-1.5 text-xs font-semibold ${on ? 'border border-rose-300 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border border-emerald-300 bg-emerald-50 text-emerald-700 hover:bg-emerald-100'}`}
          >
            {on ? 'Disable' : 'Enable'}
          </button>
        </div>
      ))}
    </div>
  );
};

// ==================== tenant manageability modals ====================

const EditTenantModal: React.FC<{
  tenant: Tenant360;
  onClose: () => void;
  onSaved: () => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenant, onClose, onSaved, notify, fail }) => {
  const [plans, setPlans] = useState<Plan[]>([]);
  const [name, setName] = useState(tenant.name);
  const [planId, setPlanId] = useState<string>(tenant.plan?.id ?? '');
  const [trialEndsAt, setTrialEndsAt] = useState(tenant.trialEndsAt ? tenant.trialEndsAt.slice(0, 10) : '');
  const [termEndsAt, setTermEndsAt] = useState(tenant.subscriptionEndsAt ? tenant.subscriptionEndsAt.slice(0, 10) : '');
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    api.fetchPlatformPlans().then(setPlans).catch(() => setPlans([]));
  }, []);

  const submit = async () => {
    setBusy(true);
    try {
      await api.updateTenantSubscription(tenant.id, {
        name: name.trim() !== tenant.name ? name.trim() : undefined,
        planId: planId || null,
        trialEndsAt: trialEndsAt || null,
        subscriptionEndsAt: termEndsAt || null,
      });
      notify('success', 'Tenant profile and subscription updated.');
      onSaved();
    } catch (err) {
      fail(err, 'Update failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <ModalShell title={`Edit tenant: ${tenant.tenantCode}`} onClose={onClose}>
      <div className="space-y-3">
        <Field label="Tenant name">
          <input value={name} onChange={e => setName(e.target.value)} className={inputCls} />
        </Field>
        <Field label="Plan">
          <select value={planId} onChange={e => setPlanId(e.target.value)} className={inputCls}>
            <option value="">— no plan —</option>
            {plans.map(p => (
              <option key={p.id} value={p.id}>{p.name} · {fmtMoney(p.priceMonthly, p.currency)}/mo</option>
            ))}
          </select>
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Trial ends">
            <input type="date" value={trialEndsAt} onChange={e => setTrialEndsAt(e.target.value)} className={inputCls} />
          </Field>
          <Field label="Term ends">
            <input type="date" value={termEndsAt} onChange={e => setTermEndsAt(e.target.value)} className={inputCls} />
          </Field>
        </div>
        <p className="text-xs text-slate-500">The tenant code <span className="font-semibold">{tenant.tenantCode}</span> is the permanent identity anchor and never changes. Status changes go through the lifecycle actions.</p>
        <div className="flex justify-end gap-2 pt-1">
          <button onClick={onClose} className={btnGhost}>Cancel</button>
          <button onClick={submit} disabled={busy || !name.trim()} className={btnPrimary}>
            {busy ? 'Saving…' : 'Save changes'}
          </button>
        </div>
      </div>
    </ModalShell>
  );
};

const TENANT_ROLES = ['admin', 'radiologist', 'technologist', 'receptionist', 'billing'] as const;

const TenantUserModal: React.FC<{
  tenantId: string;
  user?: TenantUserRecord;
  onClose: () => void;
  onSaved: (secret?: { title: string; secret: string }) => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenantId, user, onClose, onSaved, notify, fail }) => {
  const editing = Boolean(user);
  const [name, setName] = useState(user?.name ?? '');
  const [email, setEmail] = useState(user?.email ?? '');
  const [role, setRole] = useState<string>(user?.role ?? 'receptionist');
  const [phone, setPhone] = useState('');
  const [password, setPassword] = useState('');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    try {
      if (editing && user) {
        await api.updateTenantUser(tenantId, user.id, {
          name: name.trim() !== user.name ? name.trim() : undefined,
          email: email.trim() !== user.email ? email.trim() : undefined,
          role: role !== user.role ? role : undefined,
          phone: phone.trim() !== '' ? phone.trim() : undefined,
        });
        notify('success', `User ${email.trim()} updated.`);
        onSaved();
      } else {
        const { initialPassword } = await api.createTenantUser(tenantId, {
          name: name.trim(),
          email: email.trim(),
          role,
          phone: phone.trim() || undefined,
          password: password || undefined,
        });
        notify('success', `User ${email.trim()} created.`);
        onSaved(initialPassword ? { title: `Initial password for ${email.trim()}`, secret: initialPassword } : undefined);
      }
    } catch (err) {
      fail(err, editing ? 'User update failed.' : 'User creation failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <ModalShell title={editing ? `Edit user: ${user?.email}` : 'Add user to this tenant'} onClose={onClose}>
      <div className="space-y-3">
        <Field label="Full name">
          <input value={name} onChange={e => setName(e.target.value)} className={inputCls} />
        </Field>
        <Field label="Email">
          <input type="email" value={email} onChange={e => setEmail(e.target.value)} className={inputCls} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Tenant role">
            <select value={role} onChange={e => setRole(e.target.value)} className={inputCls}>
              {TENANT_ROLES.map(r => <option key={r} value={r}>{r}</option>)}
            </select>
          </Field>
          <Field label="Phone (optional)">
            <input value={phone} onChange={e => setPhone(e.target.value)} className={inputCls} />
          </Field>
        </div>
        {!editing && (
          <Field label="Password — leave blank to auto-generate">
            <input
              type="text"
              value={password}
              onChange={e => setPassword(e.target.value)}
              placeholder="Minimum 8 characters; blank = generated & shown once"
              className={inputCls}
              autoComplete="new-password"
            />
          </Field>
        )}
        <p className="text-xs text-slate-500">
          {editing
            ? 'Role changes re-attach the tenant role and update the membership; the last active administrator of this tenant can never be demoted or locked out.'
            : 'The role is the tenant\u2019s own role definition — platform staff never grant privileges across tenants.'}
        </p>
        <div className="flex justify-end gap-2 pt-1">
          <button onClick={onClose} className={btnGhost}>Cancel</button>
          <button onClick={submit} disabled={busy || !name.trim() || !email.trim()} className={btnPrimary}>
            {busy ? 'Saving…' : editing ? 'Save changes' : 'Create user'}
          </button>
        </div>
      </div>
    </ModalShell>
  );
};

const FacilityModal: React.FC<{
  tenantId: string;
  facility: TenantFacilityRecord | null;
  onClose: () => void;
  onSaved: () => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ tenantId, facility, onClose, onSaved, notify, fail }) => {
  const editing = Boolean(facility);
  const [name, setName] = useState(facility?.name ?? '');
  const [address, setAddress] = useState(facility?.address ?? '');
  const [phone, setPhone] = useState(facility?.phone ?? '');
  const [description, setDescription] = useState(facility?.description ?? '');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    try {
      if (editing && facility) {
        await api.updateTenantFacility(tenantId, facility.id, {
          name: name.trim() !== facility.name ? name.trim() : undefined,
          address: address !== facility.address ? address : undefined,
          phone: phone !== (facility.phone ?? '') ? phone : undefined,
          description: description !== (facility.description ?? '') ? description : undefined,
        });
        notify('success', 'Facility updated.');
      } else {
        await api.createTenantFacility(tenantId, { name: name.trim(), address, phone, description });
        notify('success', `Facility "${name.trim()}" created.`);
      }
      onSaved();
    } catch (err) {
      fail(err, editing ? 'Facility update failed.' : 'Facility creation failed.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <ModalShell title={editing ? `Edit facility: ${facility?.name}` : 'Add facility'} onClose={onClose}>
      <div className="space-y-3">
        <Field label="Facility name">
          <input value={name} onChange={e => setName(e.target.value)} className={inputCls} />
        </Field>
        <Field label="Address">
          <input value={address} onChange={e => setAddress(e.target.value)} className={inputCls} />
        </Field>
        <div className="grid grid-cols-2 gap-3">
          <Field label="Phone">
            <input value={phone} onChange={e => setPhone(e.target.value)} className={inputCls} />
          </Field>
          <Field label="Description (optional)">
            <input value={description} onChange={e => setDescription(e.target.value)} className={inputCls} />
          </Field>
        </div>
        <div className="flex justify-end gap-2 pt-1">
          <button onClick={onClose} className={btnGhost}>Cancel</button>
          <button onClick={submit} disabled={busy || !name.trim()} className={btnPrimary}>
            {busy ? 'Saving…' : editing ? 'Save changes' : 'Add facility'}
          </button>
        </div>
      </div>
    </ModalShell>
  );
};

const OneTimeSecretModal: React.FC<{ title: string; secret: string; onClose: () => void }> = ({ title, secret, onClose }) => {
  const [copied, setCopied] = useState(false);
  return (
    <ModalShell title={title} onClose={onClose}>
      <div className="space-y-3">
        <p className="text-xs text-slate-500">Hand this value to the user over a secure channel. It is shown <span className="font-semibold">only once</span> and cannot be retrieved again.</p>
        <div className="flex items-center gap-2 rounded-lg border border-cyan-200 bg-cyan-50 px-3 py-2.5">
          <code className="flex-1 select-all break-all font-mono text-sm font-bold text-cyan-900">{secret}</code>
          <button
            onClick={async () => {
              try {
                await navigator.clipboard.writeText(secret);
                setCopied(true);
                setTimeout(() => setCopied(false), 1500);
              } catch { /* clipboard unavailable — the value stays selectable */ }
            }}
            className={btnGhost}
          >
            <Copy size={12} /> {copied ? 'Copied' : 'Copy'}
          </button>
        </div>
        <div className="flex justify-end">
          <button onClick={onClose} className={btnPrimary}>Done</button>
        </div>
      </div>
    </ModalShell>
  );
};

// ==================== Plans ====================

const PlansSection: React.FC<{ notify: (k: 'error' | 'success', m: string) => void; fail: (e: any, f: string) => void }> = ({ notify, fail }) => {
  const [plans, setPlans] = useState<Plan[]>([]);
  const [editing, setEditing] = useState<Partial<Plan> | null>(null);
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      setPlans(await api.fetchPlatformPlans());
    } catch (err) {
      fail(err, 'Failed to load plans.');
    } finally {
      setLoading(false);
    }
  }, [fail]);

  useEffect(() => { load(); }, [load]);

  const save = async (plan: Partial<Plan>) => {
    try {
      if (plan.id) {
        await api.updatePlatformPlan(plan.id, plan);
      } else {
        await api.createPlatformPlan(plan as any);
      }
      notify('success', `Plan "${plan.name}" saved.`);
      setEditing(null);
      load();
    } catch (err) {
      fail(err, 'Failed to save plan.');
    }
  };

  if (loading && plans.length === 0) return <SectionSpinner label="Loading plans…" />;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold">Plans</h2>
        <button onClick={() => setEditing({ currency: 'PKR', trialDays: 14, isActive: true, features: {} })} className={btnPrimarySm}><Plus size={13} /> New plan</button>
      </div>
      <div className="grid md:grid-cols-2 xl:grid-cols-3 gap-3">
        {plans.map(p => (
          <div key={p.id} className="rounded-xl border border-slate-200 bg-white p-4">
            <div className="flex items-start justify-between">
              <div>
                <p className="text-sm font-bold text-slate-900">{p.name}</p>
                <p className="text-[11px] text-slate-400">{p.slug}{p.isActive ? '' : ' · inactive'}</p>
              </div>
              <span className="text-sm font-bold text-cyan-700">{fmtMoney(p.priceMonthly, p.currency)}<span className="text-[11px] font-medium text-slate-400">/mo</span></span>
            </div>
            <ul className="mt-3 space-y-1 text-xs text-slate-600">
              <li>Trial: {p.trialDays} days · {p.subscribers ?? 0} subscribers</li>
              <li>Users: {p.maxUsers ?? '∞'} · Studies/mo: {p.maxStudiesPerMonth ?? '∞'}</li>
              <li>Storage: {p.maxStorageMb ? `${p.maxStorageMb} MB` : '∞'} · Locations: {p.maxLocations ?? '∞'}</li>
              {p.features && Object.entries(p.features).filter(([, v]) => !v).map(([f]) => (
                <li key={f} className="text-rose-600">module disabled: {f}</li>
              ))}
            </ul>
            <button onClick={() => setEditing(p)} className="mt-3 text-xs font-semibold text-cyan-700 hover:text-cyan-900">Edit plan</button>
          </div>
        ))}
      </div>
      {editing && (
        <PlanEditor
          plan={editing}
          onClose={() => setEditing(null)}
          onSave={save}
        />
      )}
    </div>
  );
};

const PlanEditor: React.FC<{ plan: Partial<Plan>; onClose: () => void; onSave: (p: Partial<Plan>) => void }> = ({ plan, onClose, onSave }) => {
  const [form, setForm] = useState<Partial<Plan>>({ ...plan });
  const set = (k: keyof Plan, v: any) => setForm(prev => ({ ...prev, [k]: v }));

  return (
    <ModalShell title={plan.id ? `Edit plan: ${plan.name}` : 'Create plan'} onClose={onClose}>
      <div className="space-y-3">
        <div className="grid grid-cols-2 gap-3">
          <Field label="Name"><input value={form.name ?? ''} onChange={e => set('name', e.target.value)} className={inputCls} /></Field>
          <Field label="Currency"><input value={form.currency ?? 'PKR'} onChange={e => set('currency', e.target.value)} className={inputCls} /></Field>
          <Field label="Price / month"><input type="number" min="0" value={form.priceMonthly ?? 0} onChange={e => set('priceMonthly', Number(e.target.value))} className={inputCls} /></Field>
          <Field label="Trial days"><input type="number" min="0" value={form.trialDays ?? 0} onChange={e => set('trialDays', Number(e.target.value))} className={inputCls} /></Field>
          <Field label="Max users (blank = ∞)"><input type="number" min="1" value={form.maxUsers ?? ''} onChange={e => set('maxUsers', e.target.value === '' ? null : Number(e.target.value))} className={inputCls} /></Field>
          <Field label="Max studies / month"><input type="number" min="1" value={form.maxStudiesPerMonth ?? ''} onChange={e => set('maxStudiesPerMonth', e.target.value === '' ? null : Number(e.target.value))} className={inputCls} /></Field>
          <Field label="Max storage (MB)"><input type="number" min="1" value={form.maxStorageMb ?? ''} onChange={e => set('maxStorageMb', e.target.value === '' ? null : Number(e.target.value))} className={inputCls} /></Field>
          <Field label="Max locations"><input type="number" min="1" value={form.maxLocations ?? ''} onChange={e => set('maxLocations', e.target.value === '' ? null : Number(e.target.value))} className={inputCls} /></Field>
        </div>
        <Field label="Description"><input value={form.description ?? ''} onChange={e => set('description', e.target.value)} className={inputCls} /></Field>
        <label className="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" checked={form.isActive ?? true} onChange={e => set('isActive', e.target.checked)} /> Active (signups + assignments)
        </label>
        <div className="flex justify-end gap-2 pt-1">
          <button onClick={onClose} className={btnGhost}>Cancel</button>
          <button onClick={() => onSave(form)} disabled={!form.name} className={btnPrimary}>Save plan</button>
        </div>
      </div>
    </ModalShell>
  );
};

// ==================== Usage ====================

const UsageSection: React.FC<{ notify: (k: 'error' | 'success', m: string) => void; fail: (e: any, f: string) => void }> = ({ notify, fail }) => {
  const [tenants, setTenants] = useState<TenantRecord[]>([]);
  const [tenantId, setTenantId] = useState('');
  const [usage, setUsage] = useState<UsageSummary | null>(null);

  useEffect(() => {
    api.fetchPlatformTenants().then(setTenants).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!tenantId) { setUsage(null); return; }
    api.fetchTenantUsage(tenantId).then(setUsage).catch(err => fail(err, 'Failed to load usage.'));
  }, [tenantId, fail]);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <h2 className="text-lg font-bold mr-auto">Usage</h2>
        <select value={tenantId} onChange={e => setTenantId(e.target.value)} className="rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-sm focus:border-cyan-500 focus:outline-none">
          <option value="">Select tenant…</option>
          {tenants.map(t => <option key={t.id} value={t.id}>{t.name}</option>)}
        </select>
      </div>
      {!usage ? (
        <p className="text-sm text-slate-500">Choose a tenant to inspect metered usage.</p>
      ) : (
        <div className="space-y-4">
          <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
            <Card label="Users" value={`${usage.current.users}${usage.limits.maxUsers ? ` / ${usage.limits.maxUsers}` : ''}`} />
            <Card label="Studies (month)" value={`${usage.current.studiesThisMonth}${usage.limits.maxStudiesPerMonth ? ` / ${usage.limits.maxStudiesPerMonth}` : ''}`} tone="cyan" />
            <Card label="Storage" value={fmtBytes(usage.current.storageBytes)} sub={usage.limits.maxStorageMb ? `of ${usage.limits.maxStorageMb} MB` : 'unmetered'} />
            <Card label="Locations" value={`${usage.current.locations}${usage.limits.maxLocations ? ` / ${usage.limits.maxLocations}` : ''}`} />
          </div>
          <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
            <p className="border-b border-slate-100 px-4 py-2.5 text-xs font-semibold uppercase tracking-wide text-slate-500">Monthly meters (last 6 months)</p>
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
                  <th className="px-4 py-2 font-semibold">Period</th>
                  <th className="px-4 py-2 font-semibold text-right">Studies</th>
                  <th className="px-4 py-2 font-semibold text-right">Reports</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100">
                {Object.entries(usage.monthly).map(([period, metrics]) => (
                  <tr key={period}>
                    <td className="px-4 py-2 font-medium text-slate-700">{period}</td>
                    <td className="px-4 py-2 text-right tabular-nums">{metrics.studies ?? 0}</td>
                    <td className="px-4 py-2 text-right tabular-nums">{metrics.reports ?? 0}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}
    </div>
  );
};

// ==================== Support sessions ====================

const SupportSection: React.FC<{
  user: SessionUser;
  onEnterTenant: (businessId: number) => Promise<void>;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ user, onEnterTenant, notify, fail }) => {
  const [sessions, setSessions] = useState<PlatformSupportSessionRecord[]>([]);
  const [tenants, setTenants] = useState<TenantRecord[]>([]);
  const [businessId, setBusinessId] = useState('');
  const [reason, setReason] = useState('');
  const [minutes, setMinutes] = useState(60);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    try {
      const [s, t] = await Promise.all([api.fetchSupportSessions(), api.fetchPlatformTenants()]);
      setSessions(s);
      setTenants(t);
    } catch (err) {
      fail(err, 'Failed to load support sessions.');
    }
  }, [fail]);

  useEffect(() => { load(); }, [load]);

  const start = async () => {
    setBusy(true);
    try {
      await api.startSupportSession({ businessId, reason, minutes });
      notify('success', 'Support session opened. Use "Enter clinic" to operate inside the tenant.');
      setReason('');
      load();
    } catch (err) {
      fail(err, 'Failed to open support session.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-4">
      <h2 className="text-lg font-bold">Break-glass support sessions</h2>
      <div className="rounded-xl border border-slate-200 bg-white p-4 space-y-3">
        <p className="text-xs text-slate-500">Sessions are time-boxed, reason-mandated, fully audited, and limited to one active session per platform user. Entering a clinic shows a permanent banner inside the tenant app.</p>
        <div className="grid md:grid-cols-4 gap-3">
          <Field label="Tenant">
            <select value={businessId} onChange={e => setBusinessId(e.target.value)} className={inputCls}>
              <option value="">Select…</option>
              {tenants.map(t => <option key={t.id} value={t.id}>{t.name} ({t.tenantCode})</option>)}
            </select>
          </Field>
          <Field label="Reason (audited, min 10 chars)">
            <input value={reason} onChange={e => setReason(e.target.value)} className={inputCls} placeholder="Ticket #123 — investigating worklist reports" />
          </Field>
          <Field label="Duration (minutes)">
            <input type="number" min={5} max={240} value={minutes} onChange={e => setMinutes(Number(e.target.value))} className={inputCls} />
          </Field>
          <div className="flex items-end">
            <button onClick={start} disabled={busy || !businessId || reason.trim().length < 10} className={btnPrimary}>Open session</button>
          </div>
        </div>
      </div>

      <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
        {sessions.length === 0 ? (
          <p className="px-4 py-6 text-sm text-slate-500">No support sessions recorded.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {sessions.map(s => (
              <li key={s.id} className="flex flex-wrap items-center gap-3 px-4 py-3 text-sm">
                <div className="min-w-0">
                  <p className="font-semibold text-slate-800">{s.tenantName ?? `Tenant #${s.businessId}`}</p>
                  <p className="text-xs text-slate-500 truncate max-w-md">{s.reason}</p>
                </div>
                <span className="text-xs text-slate-400">{s.platformUserName}</span>
                <span className={`text-xs font-semibold ${s.isActive ? 'text-rose-600' : 'text-slate-400'}`}>{s.isActive ? 'ACTIVE' : 'closed'}</span>
                <span className="text-xs text-slate-400 ml-auto">expires {fmtWhen(s.expiresAt)}</span>
                {s.isActive && user.supportSession?.id === s.id && (
                  <button onClick={() => onEnterTenant(s.businessId)} className={btnPrimarySm}><KeyRound size={12} /> Enter clinic</button>
                )}
                {s.isActive && user.supportSession?.id !== s.id && (
                  <button
                    onClick={async () => { try { await api.endSupportSession(s.id); load(); } catch (err) { fail(err, 'Failed to end session.'); } }}
                    className={btnGhost}
                  >
                    End
                  </button>
                )}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

// ==================== Platform users ====================

const PlatformUsersSection: React.FC<{
  user: SessionUser;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ user, notify, fail }) => {
  const [users, setUsers] = useState<PlatformUserRecord[]>([]);
  const [creating, setCreating] = useState(false);
  const isSuper = user.platformRole === 'super_admin';

  const load = useCallback(async () => {
    try {
      setUsers(await api.fetchPlatformUsers());
    } catch (err) {
      fail(err, 'Failed to load platform users.');
    }
  }, [fail]);

  useEffect(() => { load(); }, [load]);

  const setRole = async (u: PlatformUserRecord, role: PlatformRole) => {
    try {
      await api.updatePlatformUser(u.id, { role });
      notify('success', `${u.email} is now ${role}.`);
      load();
    } catch (err) {
      fail(err, 'Role change failed.');
    }
  };

  const toggleActive = async (u: PlatformUserRecord) => {
    try {
      await api.updatePlatformUser(u.id, { isActive: !u.isActive });
      load();
    } catch (err) {
      fail(err, 'Update failed.');
    }
  };

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h2 className="text-lg font-bold">Platform users</h2>
        {isSuper && <button onClick={() => setCreating(true)} className={btnPrimarySm}><Plus size={13} /> New platform user</button>}
      </div>
      <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-slate-200 bg-slate-50 text-left text-[11px] uppercase tracking-wide text-slate-500">
              <th className="px-4 py-2.5 font-semibold">Name</th>
              <th className="px-4 py-2.5 font-semibold">Email</th>
              <th className="px-4 py-2.5 font-semibold">Role</th>
              <th className="px-4 py-2.5 font-semibold">State</th>
              <th className="px-4 py-2.5 font-semibold">Last login</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-slate-100">
            {users.map(u => (
              <tr key={u.id}>
                <td className="px-4 py-2.5 font-medium text-slate-800">{u.name}</td>
                <td className="px-4 py-2.5 text-slate-600">{u.email}</td>
                <td className="px-4 py-2.5">
                  {isSuper && u.id !== user.id ? (
                    <select
                      value={u.role}
                      onChange={e => setRole(u, e.target.value as PlatformRole)}
                      className="rounded border border-slate-300 px-2 py-1 text-xs capitalize focus:outline-none focus:border-cyan-500"
                    >
                      {['super_admin', 'ops', 'billing', 'support', 'auditor'].map(r => <option key={r} value={r}>{r}</option>)}
                    </select>
                  ) : (
                    <span className="capitalize">{u.role}</span>
                  )}
                </td>
                <td className="px-4 py-2.5">
                  {u.id !== user.id && isSuper ? (
                    <button onClick={() => toggleActive(u)} className={`text-xs font-semibold ${u.isActive ? 'text-emerald-600 hover:text-emerald-800' : 'text-slate-400 hover:text-slate-700'}`}>
                      {u.isActive ? 'active' : 'disabled'}
                    </button>
                  ) : (
                    <span className={`text-xs font-semibold ${u.isActive ? 'text-emerald-600' : 'text-slate-400'}`}>{u.isActive ? 'active' : 'disabled'}</span>
                  )}
                </td>
                <td className="px-4 py-2.5 text-xs text-slate-500">{u.lastLogin ? fmtWhen(u.lastLogin) : 'Never'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
      {creating && (
        <NewPlatformUserModal
          onClose={() => setCreating(false)}
          onCreated={() => { setCreating(false); load(); }}
          notify={notify}
          fail={fail}
        />
      )}
    </div>
  );
};

const NewPlatformUserModal: React.FC<{
  onClose: () => void;
  onCreated: () => void;
  notify: (k: 'error' | 'success', m: string) => void;
  fail: (e: any, f: string) => void;
}> = ({ onClose, onCreated, notify, fail }) => {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [role, setRole] = useState<PlatformRole>('ops');
  const [busy, setBusy] = useState(false);

  const submit = async () => {
    setBusy(true);
    try {
      await api.createPlatformUser({ name, email, password, role });
      notify('success', `Platform user ${email} created.`);
      onCreated();
    } catch (err) {
      fail(err, 'Failed to create platform user.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <ModalShell title="New platform user" onClose={onClose}>
      <div className="space-y-3">
        <Field label="Name"><input value={name} onChange={e => setName(e.target.value)} className={inputCls} /></Field>
        <Field label="Email"><input type="email" value={email} onChange={e => setEmail(e.target.value)} className={inputCls} /></Field>
        <Field label="Password"><input type="password" value={password} onChange={e => setPassword(e.target.value)} className={inputCls} /></Field>
        <Field label="Role">
          <select value={role} onChange={e => setRole(e.target.value as PlatformRole)} className={inputCls}>
            <option value="ops">ops — tenant lifecycle & provisioning</option>
            <option value="billing">billing — plans & subscriptions</option>
            <option value="support">support — break-glass sessions</option>
            <option value="auditor">auditor — read-only audit & stats</option>
          </select>
        </Field>
        <div className="flex justify-end gap-2 pt-1">
          <button onClick={onClose} className={btnGhost}>Cancel</button>
          <button onClick={submit} disabled={busy || !name || !email || password.length < 8} className={btnPrimary}>Create user</button>
        </div>
      </div>
    </ModalShell>
  );
};

// ==================== Audit ====================

const AuditSection: React.FC<{ notify: (k: 'error' | 'success', m: string) => void; fail: (e: any, f: string) => void }> = ({ notify, fail }) => {
  const [entries, setEntries] = useState<PlatformAuditEntry[]>([]);
  const [filter, setFilter] = useState('');
  const [tenantFilter, setTenantFilter] = useState('');
  const [loading, setLoading] = useState(true);

  const load = useCallback(async () => {
    setLoading(true);
    try {
      // Server-side tenant scoping; the free-text box narrows the fetched window client-side.
      setEntries(await api.fetchPlatformAudit({ tenantId: tenantFilter.trim() || undefined }));
    } catch (err) {
      fail(err, 'Failed to load platform audit.');
    } finally {
      setLoading(false);
    }
  }, [fail, tenantFilter]);

  useEffect(() => { load(); }, [load]);

  const visible = entries.filter(e => !filter || e.action.includes(filter) || e.module.toLowerCase().includes(filter.toLowerCase()));

  if (loading && entries.length === 0) return <SectionSpinner label="Loading audit stream…" />;

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <h2 className="text-lg font-bold mr-auto">Platform audit</h2>
        <input
          value={tenantFilter}
          onChange={e => setTenantFilter(e.target.value)}
          placeholder="Tenant id… (server filter)"
          className="w-40 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-cyan-500 focus:outline-none"
        />
        <input value={filter} onChange={e => setFilter(e.target.value)} placeholder="Filter action / module…" className="w-56 rounded-lg border border-slate-300 px-3 py-1.5 text-sm focus:border-cyan-500 focus:outline-none" />
      </div>
      <div className="rounded-xl border border-slate-200 bg-white overflow-hidden">
        {visible.length === 0 ? (
          <p className="px-4 py-6 text-sm text-slate-500">No audit entries.</p>
        ) : (
          <ul className="divide-y divide-slate-100">
            {visible.map(e => (
              <li key={e.id} className="px-4 py-2.5 text-sm">
                <div className="flex flex-wrap items-center gap-2">
                  <span className={`font-semibold ${e.status === 'warning' ? 'text-amber-700' : 'text-slate-800'}`}>{e.actionLabel}</span>
                  {e.tenantId && <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">tenant #{e.tenantId}</span>}
                  <span className="text-xs text-slate-500">{e.actorName}</span>
                  <span className="ml-auto text-xs text-slate-400">{fmtWhen(e.at)}</span>
                </div>
                {e.details && <p className="text-xs text-slate-600">{e.details}</p>}
              </li>
            ))}
          </ul>
        )}
      </div>
    </div>
  );
};

// ==================== shared bits ====================

const inputCls = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-cyan-500 focus:outline-none';
const btnPrimary = 'inline-flex items-center gap-1.5 rounded-lg bg-cyan-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-cyan-700 disabled:opacity-40';
const btnPrimarySm = btnPrimary;
const btnGhost = 'inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40';

function ModalShell({ title, onClose, children }: { title: string; onClose: () => void; children: React.ReactNode }) {
  return (
    <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/40 p-4" role="dialog" aria-modal="true">
      <div className="w-full max-w-lg rounded-2xl bg-white p-5 shadow-2xl">
        <div className="mb-3 flex items-center justify-between">
          <h3 className="text-sm font-bold text-slate-900">{title}</h3>
          <button onClick={onClose} className="text-slate-400 hover:text-slate-700" aria-label="Close">✕</button>
        </div>
        {children}
      </div>
    </div>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <label className="block">
      <span className="mb-1 block text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</span>
      {children}
    </label>
  );
}

function Row({ k, v }: { k: string; v: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-3">
      <span className="text-xs text-slate-500">{k}</span>
      <span className="text-sm font-medium text-slate-800 text-right">{v}</span>
    </div>
  );
}

function SectionSpinner({ label }: { label: string }) {
  return (
    <div className="flex items-center justify-center gap-3 py-16 text-sm text-slate-500">
      <div className="h-5 w-5 animate-spin rounded-full border-[3px] border-cyan-500/30 border-t-cyan-600" />
      {label}
    </div>
  );
}

function EmptyState({ label, onRetry }: { label: string; onRetry: () => void }) {
  return (
    <div className="py-16 text-center">
      <p className="text-sm text-slate-500">{label}</p>
      <button onClick={onRetry} className="mt-2 text-xs font-semibold text-cyan-700 underline">Retry</button>
    </div>
  );
}
