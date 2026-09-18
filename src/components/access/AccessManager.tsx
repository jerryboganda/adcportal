import React, { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Shield,
  Plus,
  Copy,
  Trash2,
  Search,
  Save,
  AlertTriangle,
  Check,
  X,
  Eye,
  ChevronDown,
  ChevronRight,
  Users,
  Lock,
  RefreshCw,
} from 'lucide-react';
import { AccessRoleRecord, EffectiveAccess, PermissionDef, PermissionGroup, StaffUser } from '../../types';
import * as api from '../../services/apiService';
import {
  ACTION_GATES,
  ActionGate,
  allowedSettingsSections,
  allowedTabs,
  canAction,
  canAny,
} from '../../services/permissions';

/**
 * Tenant RBAC control center: role management, the grouped permission editor,
 * a cross-role matrix, per-user allow/deny overrides and a safe "preview as
 * role" simulation. Everything here is server-backed (/api/v1/access/*) — the
 * server enforces the same `role view` / `role manage` gates and remains the
 * only authorization authority.
 */

type WorkspaceTab = 'roles' | 'matrix' | 'users';
type OverrideState = 'inherit' | 'allow' | 'deny';

interface AccessManagerProps {
  /** Acting admin's effective permissions — gates this UI itself. */
  permissions: string[];
  staffUsers: StaffUser[];
}

const BUTTON_BASE =
  'inline-flex items-center gap-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer';

const AccessManager: React.FC<AccessManagerProps> = ({ permissions, staffUsers }) => {
  const canManage = canAny(permissions, ['role manage']);

  const [tab, setTab] = useState<WorkspaceTab>('roles');
  const [catalog, setCatalog] = useState<PermissionGroup[]>([]);
  const [roles, setRoles] = useState<AccessRoleRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const [selectedRoleId, setSelectedRoleId] = useState<string | null>(null);
  const [selected, setSelected] = useState<Set<string>>(new Set());
  const [dirty, setDirty] = useState(false);
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState('');
  const [expandedGroups, setExpandedGroups] = useState<Set<string>>(new Set());
  const [statusMessage, setStatusMessage] = useState<{ kind: 'ok' | 'err'; text: string } | null>(null);

  const [createOpen, setCreateOpen] = useState(false);
  const [newName, setNewName] = useState('');
  const [newDisplayName, setNewDisplayName] = useState('');

  const [previewFor, setPreviewFor] = useState<{ role: AccessRoleRecord; simulated: string[] } | null>(null);

  const [overrideUser, setOverrideUser] = useState<StaffUser | null>(null);
  const [overrideAccess, setOverrideAccess] = useState<EffectiveAccess | null>(null);
  const [overrideDraft, setOverrideDraft] = useState<Record<string, OverrideState>>({});
  const [overrideSearch, setOverrideSearch] = useState('');
  const [overrideSaving, setOverrideSaving] = useState(false);

  const reloadRoles = useCallback(async (preferId?: string) => {
    const list = await api.fetchAccessRoles();
    setRoles(list);
    const target = (preferId && list.find(r => r.id === preferId)) || list.find(r => r.id === selectedRoleId) || list[0] || null;
    setSelectedRoleId(target ? target.id : null);
    setSelected(new Set(target ? target.permissions : []));
    setDirty(false);
  }, [selectedRoleId]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const [cat, list] = await Promise.all([api.fetchAccessCatalog(), api.fetchAccessRoles()]);
        if (cancelled) return;
        setCatalog(cat);
        setRoles(list);
        setSelectedRoleId(list[0]?.id ?? null);
        setSelected(new Set(list[0]?.permissions ?? []));
      } catch (err: any) {
        if (!cancelled) setError(err?.message ?? 'Failed to load access control data.');
      } finally {
        if (!cancelled) setLoading(false);
      }
    })();
    return () => { cancelled = true; };
  }, []);

  const allPermissions = useMemo(
    () => catalog.flatMap(g => g.permissions),
    [catalog]
  );

  /** parent permission → permissions that require it */
  const dependents = useMemo(() => {
    const map = new Map<string, string[]>();
    for (const def of allPermissions) {
      for (const parent of def.implies) {
        map.set(parent, [...(map.get(parent) ?? []), def.name]);
      }
    }
    return map;
  }, [allPermissions]);

  const selectedRole = roles.find(r => r.id === selectedRoleId) ?? null;

  const selectRole = (role: AccessRoleRecord) => {
    if (dirty && !window.confirm('Discard unsaved permission changes for this role?')) {
      return;
    }
    setSelectedRoleId(role.id);
    setSelected(new Set(role.permissions));
    setDirty(false);
    setStatusMessage(null);
  };

  /** Granting a permission pulls in its dependencies (report sign ⇒ report edit). */
  const checkWithDependencies = (set: Set<string>, name: string): Set<string> => {
    const def = allPermissions.find(d => d.name === name);
    const next = new Set(set);
    next.add(name);
    for (const parent of def?.implies ?? []) {
      next.add(parent);
    }
    return next;
  };

  /** Revoking a dependency revokes everything that requires it (with notice). */
  const uncheckWithDependents = (set: Set<string>, name: string): Set<string> => {
    const next = new Set(set);
    next.delete(name);
    for (const child of dependents.get(name) ?? []) {
      if (next.has(child)) {
        next.delete(child);
      }
    }
    return next;
  };

  const togglePermission = (name: string) => {
    if (!canManage || !selectedRole) return;
    if (selectedRole.undeletable && ['role view', 'role manage', 'user manage', 'setting manage', 'user logs history'].includes(name)) {
      setStatusMessage({ kind: 'err', text: 'Protected admin permissions cannot be removed (lockout guard).' });
      return;
    }
    setSelected(prev => {
      if (prev.has(name)) {
        const removed = dependents.get(name)?.filter(c => prev.has(c)) ?? [];
        const next = uncheckWithDependents(prev, name);
        if (removed.length > 0) {
          setStatusMessage({ kind: 'ok', text: `Also revoked: ${removed.join(', ')} (they require ${name}).` });
        }
        return next;
      }
      return checkWithDependencies(prev, name);
    });
    setDirty(true);
  };

  const setGroup = (group: PermissionGroup, value: boolean) => {
    if (!canManage) return;
    setSelected(prev => {
      let next = new Set(prev);
      for (const def of group.permissions) {
        if (value) {
          next = checkWithDependencies(next, def.name);
        } else {
          next.delete(def.name);
        }
      }
      return next;
    });
    setDirty(true);
  };

  const savePermissions = async () => {
    if (!selectedRole || !dirty) return;
    setSaving(true);
    setStatusMessage(null);
    try {
      const updated = await api.syncAccessRolePermissions(selectedRole.id, Array.from(selected).sort());
      setRoles(prev => prev.map(r => (r.id === updated.id ? updated : r)));
      setSelected(new Set(updated.permissions));
      setDirty(false);
      setStatusMessage({ kind: 'ok', text: `Saved ${updated.displayName}. Open sessions refresh automatically within a minute.` });
    } catch (err: any) {
      setStatusMessage({ kind: 'err', text: err?.message ?? 'Could not save permissions.' });
    } finally {
      setSaving(false);
    }
  };

  const createRole = async () => {
    if (!newName.trim()) return;
    try {
      const created = await api.createAccessRole({
        name: newName.trim(),
        displayName: newDisplayName.trim() || newName.trim(),
        permissions: [],
      });
      setCreateOpen(false);
      setNewName('');
      setNewDisplayName('');
      await reloadRoles(created.id);
      setStatusMessage({ kind: 'ok', text: `Role ${created.displayName} created — now grant its permissions.` });
    } catch (err: any) {
      setStatusMessage({ kind: 'err', text: err?.message ?? 'Could not create the role.' });
    }
  };

  const duplicateRole = async (role: AccessRoleRecord) => {
    try {
      const copy = await api.duplicateAccessRole(role.id);
      await reloadRoles(copy.id);
      setStatusMessage({ kind: 'ok', text: `Duplicated as ${copy.displayName}.` });
    } catch (err: any) {
      setStatusMessage({ kind: 'err', text: err?.message ?? 'Could not duplicate the role.' });
    }
  };

  const deleteRole = async (role: AccessRoleRecord) => {
    if (!window.confirm(`Delete the role ${role.displayName}? This cannot be undone.`)) return;
    try {
      await api.deleteAccessRole(role.id);
      setSelectedRoleId(null);
      await reloadRoles();
      setStatusMessage({ kind: 'ok', text: `Role ${role.displayName} deleted.` });
    } catch (err: any) {
      setStatusMessage({ kind: 'err', text: err?.message ?? 'Could not delete the role.' });
    }
  };

  const openOverrides = async (user: StaffUser) => {
    setOverrideUser(user);
    setOverrideAccess(null);
    setOverrideDraft({});
    setOverrideSearch('');
    try {
      const access = await api.fetchEffectiveAccess(user.id);
      const draft: Record<string, OverrideState> = {};
      for (const name of access.allowedOverrides) draft[name] = 'allow';
      for (const name of access.deniedOverrides) draft[name] = 'deny';
      setOverrideAccess(access);
      setOverrideDraft(draft);
    } catch (err: any) {
      setStatusMessage({ kind: 'err', text: err?.message ?? 'Could not load effective access.' });
      setOverrideUser(null);
    }
  };

  const saveOverrides = async () => {
    if (!overrideUser) return;
    setOverrideSaving(true);
    try {
      const allow = Object.entries(overrideDraft).filter(([, m]) => m === 'allow').map(([n]) => n);
      const deny = Object.entries(overrideDraft).filter(([, m]) => m === 'deny').map(([n]) => n);
      const access = await api.syncUserOverrides(overrideUser.id, { allow, deny });
      setOverrideAccess(access);
      setStatusMessage({ kind: 'ok', text: `Overrides saved for ${overrideUser.name}.` });
      setOverrideUser(null);
    } catch (err: any) {
      setStatusMessage({ kind: 'err', text: err?.message ?? 'Could not save overrides.' });
    } finally {
      setOverrideSaving(false);
    }
  };

  const groupState = (group: PermissionGroup): 'all' | 'some' | 'none' => {
    const names = group.permissions.map(p => p.name);
    const count = names.filter(n => selected.has(n)).length;
    if (count === 0) return 'none';
    return count === names.length ? 'all' : 'some';
  };

  const matchesSearch = (def: PermissionDef): boolean => {
    if (!search.trim()) return true;
    const q = search.trim().toLowerCase();
    return def.name.toLowerCase().includes(q) || def.label.toLowerCase().includes(q);
  };

  const effectiveForPreview = (role: AccessRoleRecord): string[] =>
    role.id === selectedRoleId && dirty ? Array.from(selected).sort() : role.permissions;

  if (loading) {
    return (
      <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs text-xs text-slate-500 flex items-center gap-2">
        <RefreshCw className="w-4 h-4 animate-spin" /> Loading roles & permissions…
      </div>
    );
  }

  if (error) {
    return (
      <div className="bg-rose-50 border border-rose-200 text-rose-800 rounded-xl p-4 text-xs font-semibold">
        {error}
      </div>
    );
  }

  return (
    <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs space-y-4">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div className="flex items-center space-x-2">
          <Shield className="w-4 h-4 text-cyan-600" />
          <div>
            <h3 className="font-bold text-slate-900 text-sm">Roles & Permissions</h3>
            <p className="text-xs text-slate-500">
              Fine-grained, tenant-scoped access control. Changes persist server-side and reach open sessions automatically.
            </p>
          </div>
        </div>
        <div className="flex items-center gap-2">
          {(['roles', 'matrix', 'users'] as WorkspaceTab[]).map(t => (
            <button
              key={t}
              onClick={() => setTab(t)}
              className={`${BUTTON_BASE} px-3 py-1.5 border ${
                tab === t
                  ? 'bg-cyan-50 text-cyan-700 border-cyan-200'
                  : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'
              }`}
            >
              {t === 'roles' ? 'Roles' : t === 'matrix' ? 'Permission Matrix' : 'User Overrides'}
            </button>
          ))}
        </div>
      </div>

      {statusMessage && (
        <div className={`rounded-lg p-2.5 text-xs font-semibold flex items-center gap-2 ${
          statusMessage.kind === 'ok'
            ? 'bg-emerald-50 border border-emerald-200 text-emerald-800'
            : 'bg-rose-50 border border-rose-200 text-rose-800'
        }`}>
          {statusMessage.kind === 'ok' ? <Check className="w-3.5 h-3.5" /> : <AlertTriangle className="w-3.5 h-3.5" />}
          {statusMessage.text}
        </div>
      )}

      {/* ==================== ROLES ==================== */}
      {tab === 'roles' && (
        <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4">
          {/* Role list */}
          <div className="border border-slate-200 rounded-xl overflow-hidden">
            <div className="bg-slate-50 border-b border-slate-200 px-3 py-2 flex items-center justify-between">
              <span className="text-xs font-bold text-slate-700 uppercase tracking-wide">Roles ({roles.length})</span>
              {canManage && (
                <button onClick={() => setCreateOpen(true)} className={`${BUTTON_BASE} text-cyan-700 hover:text-cyan-900`}>
                  <Plus className="w-3.5 h-3.5" /> New
                </button>
              )}
            </div>
            <div className="max-h-[520px] overflow-y-auto divide-y divide-slate-100">
              {roles.map(role => (
                <button
                  key={role.id}
                  onClick={() => selectRole(role)}
                  className={`w-full text-left px-3 py-2.5 transition-colors cursor-pointer ${
                    role.id === selectedRoleId ? 'bg-cyan-50/70' : 'hover:bg-slate-50'
                  }`}
                >
                  <div className="flex items-center justify-between gap-2">
                    <span className="text-xs font-bold text-slate-800">{role.displayName}</span>
                    {role.system ? (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-slate-100 text-slate-600 font-bold">SYSTEM</span>
                    ) : (
                      <span className="text-[10px] px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 font-bold">CUSTOM</span>
                    )}
                  </div>
                  <div className="text-[11px] text-slate-500 mt-0.5 flex items-center gap-1">
                    <Users className="w-3 h-3" /> {role.users} user{role.users === 1 ? '' : 's'} · {role.permissions.length} permissions
                  </div>
                </button>
              ))}
            </div>
          </div>

          {/* Permission editor */}
          <div className="border border-slate-200 rounded-xl p-4 space-y-3">
            {!selectedRole ? (
              <p className="text-xs text-slate-500">Select a role to configure its permissions.</p>
            ) : (
              <>
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-2">
                  <div>
                    <div className="flex items-center gap-2">
                      <h4 className="text-sm font-bold text-slate-900">{selectedRole.displayName}</h4>
                      {selectedRole.undeletable && (
                        <span className="inline-flex items-center gap-1 text-[10px] font-bold text-amber-700 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">
                          <Lock className="w-3 h-3" /> PROTECTED
                        </span>
                      )}
                    </div>
                    <p className="text-[11px] text-slate-500 font-mono">{selectedRole.name}</p>
                  </div>
                  <div className="flex items-center gap-2">
                    <button
                      onClick={() => setPreviewFor({ role: selectedRole, simulated: effectiveForPreview(selectedRole) })}
                      className={`${BUTTON_BASE} px-3 py-1.5 border border-slate-200 text-slate-600 hover:bg-slate-50`}
                    >
                      <Eye className="w-3.5 h-3.5" /> Preview as role
                    </button>
                    {canManage && !selectedRole.undeletable && (
                      <>
                        <button onClick={() => duplicateRole(selectedRole)} className={`${BUTTON_BASE} px-3 py-1.5 border border-slate-200 text-slate-600 hover:bg-slate-50`}>
                          <Copy className="w-3.5 h-3.5" /> Duplicate
                        </button>
                        <button onClick={() => deleteRole(selectedRole)} className={`${BUTTON_BASE} px-3 py-1.5 border border-rose-200 text-rose-600 hover:bg-rose-50`}>
                          <Trash2 className="w-3.5 h-3.5" /> Delete
                        </button>
                      </>
                    )}
                  </div>
                </div>

                <div className="flex items-center gap-2">
                  <div className="relative flex-1">
                    <Search className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
                    <input
                      value={search}
                      onChange={e => setSearch(e.target.value)}
                      placeholder="Search permissions…"
                      className="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-200"
                    />
                  </div>
                  {canManage && (
                    <button
                      onClick={savePermissions}
                      disabled={!dirty || saving}
                      className={`${BUTTON_BASE} px-4 py-2 ${
                        dirty && !saving
                          ? 'bg-cyan-600 text-white hover:bg-cyan-700 shadow-sm'
                          : 'bg-slate-100 text-slate-400 cursor-not-allowed'
                      }`}
                    >
                      <Save className="w-3.5 h-3.5" /> {saving ? 'Saving…' : dirty ? 'Save Changes' : 'Saved'}
                    </button>
                  )}
                </div>

                <div className="space-y-2 max-h-[430px] overflow-y-auto pr-1">
                  {catalog.map(group => {
                    const visible = group.permissions.filter(matchesSearch);
                    if (visible.length === 0) return null;
                    const state = groupState(group);
                    const expanded = expandedGroups.has(group.group) || !!search.trim();
                    return (
                      <div key={group.group} className="border border-slate-200 rounded-lg overflow-hidden">
                        <div className="bg-slate-50 px-3 py-2 flex items-center justify-between">
                          <button
                            onClick={() => {
                              const next = new Set(expandedGroups);
                              if (next.has(group.group)) next.delete(group.group); else next.add(group.group);
                              setExpandedGroups(next);
                            }}
                            className="flex items-center gap-1.5 text-xs font-bold text-slate-700 cursor-pointer"
                          >
                            {expanded ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
                            {group.group}
                            <span className="text-[10px] font-semibold text-slate-400">
                              {group.permissions.filter(p => selected.has(p.name)).length}/{group.permissions.length}
                            </span>
                          </button>
                          {canManage && (
                            <div className="flex items-center gap-2">
                              <button onClick={() => setGroup(group, true)} className="text-[10px] font-bold text-cyan-700 hover:underline cursor-pointer">all</button>
                              <button onClick={() => setGroup(group, false)} className="text-[10px] font-bold text-slate-400 hover:text-rose-600 cursor-pointer">clear</button>
                              <input
                                type="checkbox"
                                checked={state === 'all'}
                                ref={el => { if (el) el.indeterminate = state === 'some'; }}
                                onChange={e => setGroup(group, e.target.checked)}
                                className="accent-cyan-600 cursor-pointer"
                                title="Toggle entire group"
                              />
                            </div>
                          )}
                        </div>
                        {expanded && (
                          <div className="divide-y divide-slate-50">
                            {visible.map(def => {
                              const locked = selectedRole.undeletable && ['role view', 'role manage', 'user manage', 'setting manage', 'user logs history'].includes(def.name);
                              const checked = selected.has(def.name);
                              return (
                                <label
                                  key={def.name}
                                  className={`flex items-center justify-between gap-3 px-3 py-1.5 ${locked ? 'bg-amber-50/40' : 'hover:bg-slate-50/60'} ${canManage && !locked ? 'cursor-pointer' : 'cursor-default'}`}
                                >
                                  <div className="flex items-center gap-2 min-w-0">
                                    <input
                                      type="checkbox"
                                      checked={checked}
                                      disabled={!canManage || locked}
                                      onChange={() => togglePermission(def.name)}
                                      className="accent-cyan-600 shrink-0"
                                    />
                                    <span className="text-xs text-slate-700 truncate">{def.label}</span>
                                    {def.dangerous && (
                                      <span className="inline-flex items-center gap-0.5 text-[10px] font-bold text-rose-600 bg-rose-50 border border-rose-200 rounded px-1 py-0 shrink-0">
                                        <AlertTriangle className="w-2.5 h-2.5" /> sensitive
                                      </span>
                                    )}
                                    {locked && <Lock className="w-3 h-3 text-amber-600 shrink-0" />}
                                  </div>
                                  <span className="text-[10px] text-slate-400 font-mono shrink-0 hidden sm:block">{def.name}</span>
                                </label>
                              );
                            })}
                          </div>
                        )}
                      </div>
                    );
                  })}
                </div>
              </>
            )}
          </div>
        </div>
      )}

      {/* ==================== MATRIX ==================== */}
      {tab === 'matrix' && (
        <div className="overflow-x-auto border border-slate-200 rounded-xl">
          <table className="w-full text-left text-xs border-collapse">
            <thead>
              <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                <th className="p-2.5 sticky left-0 bg-slate-50">Permission</th>
                {roles.map(role => (
                  <th key={role.id} className="p-2.5 text-center whitespace-nowrap" title={role.displayName}>
                    {role.displayName}
                  </th>
                ))}
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-slate-700">
              {catalog.map(group => (
                <React.Fragment key={group.group}>
                  <tr className="bg-slate-50/60">
                    <td colSpan={roles.length + 1} className="p-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-500">
                      {group.group}
                    </td>
                  </tr>
                  {group.permissions.map(def => (
                    <tr key={def.name} className="hover:bg-slate-50/50">
                      <td className="p-2 sticky left-0 bg-white">
                        <span className="font-medium">{def.label}</span>
                        {def.dangerous && <AlertTriangle className="w-3 h-3 inline ml-1 text-rose-500" />}
                        <span className="text-[10px] text-slate-400 font-mono ml-2">{def.name}</span>
                      </td>
                      {roles.map(role => (
                        <td key={role.id} className="p-2 text-center">
                          {role.permissions.includes(def.name)
                            ? <Check className="w-3.5 h-3.5 inline text-emerald-600" />
                            : <X className="w-3.5 h-3.5 inline text-slate-300" />}
                        </td>
                      ))}
                    </tr>
                  ))}
                </React.Fragment>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* ==================== USER OVERRIDES ==================== */}
      {tab === 'users' && (
        <div className="border border-slate-200 rounded-xl overflow-hidden">
          <div className="bg-slate-50 border-b border-slate-200 px-3 py-2 text-xs font-bold text-slate-700 uppercase tracking-wide">
            Per-user permission overrides (allow / deny on top of the role)
          </div>
          <div className="divide-y divide-slate-100 max-h-[480px] overflow-y-auto">
            {staffUsers.map(user => (
              <div key={user.id} className="px-3 py-2.5 flex items-center justify-between gap-3">
                <div>
                  <div className="text-xs font-bold text-slate-800">{user.name}</div>
                  <div className="text-[11px] text-slate-500">{user.email} · {user.role}</div>
                </div>
                <button
                  onClick={() => openOverrides(user)}
                  className={`${BUTTON_BASE} px-3 py-1.5 border border-slate-200 text-slate-600 hover:bg-slate-50`}
                >
                  <Eye className="w-3.5 h-3.5" /> Effective access
                </button>
              </div>
            ))}
            {staffUsers.length === 0 && (
              <div className="px-3 py-4 text-xs text-slate-500">No staff accounts visible (requires `user manage`).</div>
            )}
          </div>
        </div>
      )}

      {/* ==================== CREATE ROLE ==================== */}
      {createOpen && (
        <div className="fixed inset-0 z-[70] bg-slate-900/40 flex items-center justify-center p-4" onClick={() => setCreateOpen(false)}>
          <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-sm p-5 space-y-3" onClick={e => e.stopPropagation()}>
            <h4 className="text-sm font-bold text-slate-900">Create custom role</h4>
            <input
              autoFocus
              value={newName}
              onChange={e => setNewName(e.target.value)}
              placeholder="Role name (e.g. Senior Radiologist)"
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-200"
            />
            <input
              value={newDisplayName}
              onChange={e => setNewDisplayName(e.target.value)}
              placeholder="Display label (optional)"
              className="w-full px-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-200"
            />
            <div className="flex justify-end gap-2 pt-1">
              <button onClick={() => setCreateOpen(false)} className={`${BUTTON_BASE} px-3 py-2 border border-slate-200 text-slate-600`}>Cancel</button>
              <button onClick={createRole} disabled={!newName.trim()} className={`${BUTTON_BASE} px-4 py-2 bg-cyan-600 text-white hover:bg-cyan-700 disabled:bg-slate-100 disabled:text-slate-400`}>
                Create Role
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ==================== PREVIEW AS ROLE ==================== */}
      {previewFor && (
        <PreviewModal
          roleName={previewFor.role.displayName}
          simulated={previewFor.simulated}
          onClose={() => setPreviewFor(null)}
        />
      )}

      {/* ==================== EFFECTIVE ACCESS / OVERRIDES ==================== */}
      {overrideUser && (
        <OverrideModal
          user={overrideUser}
          access={overrideAccess}
          draft={overrideDraft}
          search={overrideSearch}
          catalog={allPermissions}
          canManage={canManage}
          saving={overrideSaving}
          onSearch={setOverrideSearch}
          onSet={(name, mode) => {
            setOverrideDraft(prev => {
              const next = { ...prev };
              if (mode === 'inherit') delete next[name]; else next[name] = mode;
              return next;
            });
          }}
          onClose={() => setOverrideUser(null)}
          onSave={saveOverrides}
        />
      )}
    </div>
  );
};

// ==================== preview modal ====================

const PreviewModal: React.FC<{ roleName: string; simulated: string[]; onClose: () => void }> = ({ roleName, simulated, onClose }) => {
  const tabs = allowedTabs(simulated, null);
  const sections = allowedSettingsSections(simulated);
  const gates = Object.keys(ACTION_GATES) as ActionGate[];
  const grantedGates = gates.filter(g => canAction(simulated, g));

  return (
    <div className="fixed inset-0 z-[70] bg-slate-900/40 flex items-center justify-center p-4" onClick={onClose}>
      <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-lg max-h-[80vh] overflow-y-auto p-5 space-y-4" onClick={e => e.stopPropagation()}>
        <div>
          <h4 className="text-sm font-bold text-slate-900">What “{roleName}” sees</h4>
          <p className="text-xs text-slate-500">Safe simulation using the same visibility rules as the live app — no impersonation.</p>
        </div>
        <div className="space-y-3 text-xs">
          <div>
            <div className="font-bold text-slate-700 mb-1">Navigation modules</div>
            <div className="flex flex-wrap gap-1.5">
              {tabs.map(t => <span key={t} className="px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 font-semibold">{t}</span>)}
              {tabs.length === 0 && <span className="text-slate-400">none</span>}
            </div>
          </div>
          <div>
            <div className="font-bold text-slate-700 mb-1">Settings sections</div>
            <div className="flex flex-wrap gap-1.5">
              {sections.map(s => <span key={s} className="px-2 py-0.5 rounded bg-emerald-50 border border-emerald-200 text-emerald-700 font-semibold">{s}</span>)}
              {sections.length === 0 && <span className="text-slate-400">none — Settings tab hidden</span>}
            </div>
          </div>
          <div>
            <div className="font-bold text-slate-700 mb-1">Enabled actions</div>
            <div className="flex flex-wrap gap-1.5">
              {grantedGates.map(g => <span key={g} className="px-2 py-0.5 rounded bg-cyan-50 border border-cyan-200 text-cyan-700 font-semibold">{g}</span>)}
            </div>
          </div>
          <div>
            <div className="font-bold text-slate-700 mb-1">Effective permissions ({simulated.length})</div>
            <p className="text-slate-500 font-mono text-[10px] leading-relaxed">{simulated.join(' · ')}</p>
          </div>
        </div>
        <div className="flex justify-end">
          <button onClick={onClose} className={`${BUTTON_BASE} px-4 py-2 bg-slate-800 text-white hover:bg-slate-900`}>Close</button>
        </div>
      </div>
    </div>
  );
};

// ==================== override modal ====================

const OverrideModal: React.FC<{
  user: StaffUser;
  access: EffectiveAccess | null;
  draft: Record<string, OverrideState>;
  search: string;
  catalog: PermissionDef[];
  canManage: boolean;
  saving: boolean;
  onSearch: (q: string) => void;
  onSet: (name: string, mode: OverrideState) => void;
  onClose: () => void;
  onSave: () => void;
}> = ({ user, access, draft, search, catalog, canManage, saving, onSearch, onSet, onClose, onSave }) => (
  <div className="fixed inset-0 z-[70] bg-slate-900/40 flex items-center justify-center p-4" onClick={onClose}>
    <div className="bg-white rounded-2xl border border-slate-200 shadow-xl w-full max-w-2xl max-h-[85vh] overflow-y-auto p-5 space-y-4" onClick={e => e.stopPropagation()}>
      <div>
        <h4 className="text-sm font-bold text-slate-900">Effective access — {user.name}</h4>
        <p className="text-xs text-slate-500">
          Role: {access?.role ?? user.role} · {access?.permissions.length ?? 0} effective permissions. Allow grants beyond the role; deny subtracts from it.
        </p>
      </div>

      {access && (
        <div className="grid grid-cols-1 md:grid-cols-3 gap-2 text-[11px]">
          <div className="rounded-lg border border-slate-200 p-2.5">
            <div className="font-bold text-slate-700 mb-1">From roles ({access.rolePermissions.length})</div>
            <p className="text-slate-500 font-mono break-all leading-relaxed">{access.rolePermissions.join(' · ') || '—'}</p>
          </div>
          <div className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-2.5">
            <div className="font-bold text-emerald-700 mb-1">Allowed overrides ({access.allowedOverrides.length})</div>
            <p className="text-emerald-800/80 font-mono break-all leading-relaxed">{access.allowedOverrides.join(' · ') || '—'}</p>
          </div>
          <div className="rounded-lg border border-rose-200 bg-rose-50/50 p-2.5">
            <div className="font-bold text-rose-700 mb-1">Denied overrides ({access.deniedOverrides.length})</div>
            <p className="text-rose-800/80 font-mono break-all leading-relaxed">{access.deniedOverrides.join(' · ') || '—'}</p>
          </div>
        </div>
      )}

      {canManage && (
        <>
          <div className="relative">
            <Search className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <input
              value={search}
              onChange={e => onSearch(e.target.value)}
              placeholder="Search a permission to override…"
              className="w-full pl-8 pr-3 py-2 text-xs border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-cyan-200"
            />
          </div>
          <div className="border border-slate-200 rounded-lg divide-y divide-slate-50 max-h-56 overflow-y-auto">
            {catalog
              .filter(def => !search.trim() || def.name.includes(search.toLowerCase()) || def.label.toLowerCase().includes(search.toLowerCase()))
              .map(def => {
                const mode: OverrideState = draft[def.name] ?? 'inherit';
                return (
                  <div key={def.name} className="px-3 py-1.5 flex items-center justify-between gap-2">
                    <span className="text-xs text-slate-700 truncate">{def.label} <span className="text-[10px] text-slate-400 font-mono">{def.name}</span></span>
                    <div className="flex items-center gap-1 shrink-0">
                      {(['inherit', 'allow', 'deny'] as OverrideState[]).map(m => (
                        <button
                          key={m}
                          onClick={() => onSet(def.name, m)}
                          className={`${BUTTON_BASE} px-2 py-0.5 border text-[10px] ${
                            mode === m
                              ? m === 'allow'
                                ? 'bg-emerald-600 text-white border-emerald-600'
                                : m === 'deny'
                                  ? 'bg-rose-600 text-white border-rose-600'
                                  : 'bg-slate-600 text-white border-slate-600'
                              : 'bg-white text-slate-500 border-slate-200 hover:bg-slate-50'
                          }`}
                        >
                          {m}
                        </button>
                      ))}
                    </div>
                  </div>
                );
              })}
          </div>
          <div className="flex justify-end gap-2">
            <button onClick={onClose} className={`${BUTTON_BASE} px-3 py-2 border border-slate-200 text-slate-600`}>Cancel</button>
            <button onClick={onSave} disabled={saving} className={`${BUTTON_BASE} px-4 py-2 bg-cyan-600 text-white hover:bg-cyan-700 disabled:bg-slate-100 disabled:text-slate-400`}>
              <Save className="w-3.5 h-3.5" /> {saving ? 'Saving…' : 'Save Overrides'}
            </button>
          </div>
        </>
      )}
    </div>
  </div>
);

export { AccessManager };
