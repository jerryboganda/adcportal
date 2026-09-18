import React, { useCallback, useEffect, useRef, useState } from 'react';
import {
  Activity,
  BellRing,
  CheckCircle2,
  ChevronDown,
  ChevronUp,
  Copy,
  ExternalLink,
  Flame,
  KeyRound,
  Layers,
  Link2,
  Maximize2,
  Minimize2,
  Radio,
  RefreshCw,
  Save,
  Tv,
  UserX,
  Volume2,
  VolumeX,
} from 'lucide-react';
import { QueueDisplay, QueueDisplayEntry, QueueDisplaySettings, QueueZone } from '../types';
import * as api from '../services/apiService';
import { canAny } from '../services/permissions';
import { AnnouncementLanguage, announceCall, playChime } from '../services/queueAnnouncer';

const POLL_INTERVAL_MS = 5000;
const LIVE_WINDOW_MS = 15000;

const VOICE_KEY = 'polytronx_console_voice';
const LANG_KEY = 'polytronx_console_lang';

interface QueueBoardViewProps {
  /** Server-issued effective permission set — gates call/no-show/setup actions. */
  permissions: string[];
  /** White-label brand (same source as the topbar) — headline identity. */
  brandName?: string | null;
  clinicName?: string | null;
  /** Server-persisted call (POST /studies/{id}/transition {action:'call'}). */
  onCallPatient: (aptId: string) => void | Promise<void>;
  /** Server-persisted no-show — clears a called patient who never appeared. */
  onMarkNoShow: (aptId: string) => void | Promise<void>;
}

/**
 * Staff Queue Console: the operational counterpart of the waiting-room TV
 * (/tv). Self-fed from GET /queue/display (polled), so it reflects calls,
 * check-ins and acquisitions from EVERY terminal within one poll interval.
 * `queue view` is enforced server-side; actions additionally require
 * `study checkin` (call/no-show) or `setting manage` (display setup).
 */
export const QueueBoardView: React.FC<QueueBoardViewProps> = ({
  permissions,
  brandName,
  clinicName,
  onCallPatient,
  onMarkNoShow,
}) => {
  const canCall = canAny(permissions, ['study checkin']);
  const canManage = canAny(permissions, ['setting manage']);

  const [display, setDisplay] = useState<QueueDisplay | null>(null);
  const [lastSyncAt, setLastSyncAt] = useState(0);
  const [zoneFilter, setZoneFilter] = useState<string>('all');
  const [isFullscreen, setIsFullscreen] = useState(() => !!document.fullscreenElement);
  const [busyIds, setBusyIds] = useState<Set<string>>(new Set());

  const [voiceOn, setVoiceOn] = useState(() => localStorage.getItem(VOICE_KEY) !== '0');
  const [language, setLanguage] = useState<AnnouncementLanguage>(
    () => (localStorage.getItem(LANG_KEY) as AnnouncementLanguage) || 'bilingual'
  );

  const [setupOpen, setSetupOpen] = useState(false);
  const [settings, setSettings] = useState<QueueDisplaySettings | null>(null);
  const [settingsBusy, setSettingsBusy] = useState(false);
  const [announcementDraft, setAnnouncementDraft] = useState('');
  const [copied, setCopied] = useState(false);

  const voiceRef = useRef(voiceOn);
  const langRef = useRef(language);
  voiceRef.current = voiceOn;
  langRef.current = language;

  // ==================== live polling ====================

  const refresh = useCallback(async () => {
    try {
      setDisplay(await api.fetchQueueDisplay());
      setLastSyncAt(Date.now());
    } catch {
      // Transient — the next tick retries; the badge shows staleness.
    }
  }, []);

  useEffect(() => {
    void refresh();
    const interval = window.setInterval(() => {
      if (document.visibilityState === 'visible') void refresh();
    }, POLL_INTERVAL_MS);
    const onFocus = () => void refresh();
    window.addEventListener('focus', onFocus);
    return () => {
      window.clearInterval(interval);
      window.removeEventListener('focus', onFocus);
    };
  }, [refresh]);

  useEffect(() => {
    const onFsChange = () => setIsFullscreen(!!document.fullscreenElement);
    document.addEventListener('fullscreenchange', onFsChange);
    return () => document.removeEventListener('fullscreenchange', onFsChange);
  }, []);

  // Admins: load the display link once so "Open TV" works immediately —
  // opening Display Setup reuses this instead of refetching.
  useEffect(() => {
    if (!canManage) return;
    let cancelled = false;
    api.fetchQueueDisplaySettings()
      .then(s => {
        if (cancelled) return;
        setSettings(prev => prev ?? s);
        setAnnouncementDraft(prev => prev || s.announcement);
      })
      .catch(() => undefined); // permission raced/revoked — Setup shows it
    return () => { cancelled = true; };
  }, [canManage]);

  // ==================== actions ====================

  const withBusy = async (id: string, fn: () => Promise<void>) => {
    setBusyIds(prev => new Set(prev).add(id));
    try {
      await fn();
    } finally {
      setBusyIds(prev => {
        const next = new Set(prev);
        next.delete(id);
        return next;
      });
    }
  };

  const callEntry = (entry: QueueDisplayEntry) => withBusy(entry.id, async () => {
    // Local audio feedback; every TV announces from the polled server stamp.
    if (voiceRef.current) {
      playChime();
      announceCall({ token: entry.token, roomName: entry.roomName, language: langRef.current });
    }
    await onCallPatient(entry.id);
    await refresh();
  });

  const noShowEntry = (entry: QueueDisplayEntry) => withBusy(entry.id, async () => {
    await onMarkNoShow(entry.id);
    await refresh();
  });

  const recallEntry = (entry: QueueDisplayEntry) => withBusy(entry.id, async () => {
    // Re-calling re-stamps called_at server-side → every TV re-announces.
    if (voiceRef.current) {
      playChime();
      announceCall({ token: entry.token, roomName: entry.roomName, language: langRef.current });
    }
    await onCallPatient(entry.id);
    await refresh();
  });

  // ==================== display setup (admin) ====================

  const openSetup = async () => {
    const next = !setupOpen;
    setSetupOpen(next);
    if (next && !settings) {
      setSettingsBusy(true);
      try {
        const loaded = await api.fetchQueueDisplaySettings();
        setSettings(loaded);
        setAnnouncementDraft(loaded.announcement);
      } finally {
        setSettingsBusy(false);
      }
    }
  };

  const tvUrl = settings?.displayKey
    ? `${window.location.origin}/tv?key=${encodeURIComponent(settings.displayKey)}`
    : '';

  const copyLink = async () => {
    if (!tvUrl) return;
    try {
      await navigator.clipboard.writeText(tvUrl);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      window.prompt('Copy the TV display link:', tvUrl);
    }
  };

  const regenerateKey = async () => {
    if (!window.confirm('Regenerate the display link? Every TV currently using the old link will stop updating until it is re-opened with the new link.')) {
      return;
    }
    setSettingsBusy(true);
    try {
      const saved = await api.saveQueueDisplaySettings({ regenerateKey: true });
      setSettings(saved);
      setAnnouncementDraft(saved.announcement);
    } finally {
      setSettingsBusy(false);
    }
  };

  const saveAnnouncement = async () => {
    setSettingsBusy(true);
    try {
      const saved = await api.saveQueueDisplaySettings({ announcement: announcementDraft });
      setSettings(prev => prev ? { ...prev, announcement: saved.announcement } : saved);
      setAnnouncementDraft(saved.announcement);
      void refresh();
    } finally {
      setSettingsBusy(false);
    }
  };

  // ==================== derived ====================

  const isLive = Date.now() - lastSyncAt < LIVE_WINDOW_MS;
  const headline = (brandName?.trim() || clinicName?.trim() || 'Radiology Queue');

  const zones: (QueueZone | { id: number; code: 'all'; name: string; color: string; waiting: number; serving: number })[] = [
    { id: -1, code: 'all', name: 'All Zones', color: '#0891b2', waiting: display?.stats.waiting ?? 0, serving: display?.stats.serving ?? 0 },
    ...(display?.zones ?? []),
  ];

  const matchesZone = (entry: QueueDisplayEntry) =>
    zoneFilter === 'all' || entry.modality?.id === Number(zoneFilter);

  const nowServing = (display?.nowServing ?? []).filter(matchesZone);
  const upNext = (display?.upNext ?? []).filter(matchesZone);
  const recentCalls = display?.recentCalls ?? [];

  const waited = (entry: QueueDisplayEntry) => {
    if (entry.calledAt) return undefined;
    return entry.waitedMinutes > 0 ? `waiting ${entry.waitedMinutes} min` : undefined;
  };

  return (
    <div className="space-y-4">
      {/* ==================== console header ==================== */}
      <div className="p-4 rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-cyan-600 text-white flex items-center justify-center shadow-md shadow-cyan-600/20 shrink-0">
              <Tv className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <h2 className="text-base font-bold tracking-tight">Live Queue Console</h2>
                <span
                  className={`text-[10px] font-bold px-2 py-0.5 rounded-full border flex items-center gap-1 ${
                    isLive
                      ? 'bg-emerald-50 text-emerald-600 border-emerald-200'
                      : 'bg-amber-50 text-amber-600 border-amber-200'
                  }`}
                  title={isLive ? 'Live — auto-refreshes every 5 seconds' : 'Reconnecting…'}
                >
                  <Radio className={`w-3 h-3 ${isLive ? 'animate-pulse' : ''}`} />
                  {isLive ? 'LIVE' : 'SYNC…'}
                </span>
              </div>
              <p className="text-xs text-slate-500">
                Calls, check-ins and acquisitions from every terminal appear here within seconds and on the waiting-room TV.
              </p>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {/* Stats */}
            {display && (
              <div className="flex items-center rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold overflow-hidden">
                <span className="px-2.5 py-1.5 text-cyan-700 border-r border-slate-200">Waiting {display.stats.waiting}</span>
                <span className="px-2.5 py-1.5 text-emerald-700 border-r border-slate-200">Serving {display.stats.serving}</span>
                <span className="px-2.5 py-1.5 text-slate-600 border-r border-slate-200">Done {display.stats.completed}</span>
                <span className="px-2.5 py-1.5 text-rose-600">No-show {display.stats.noShow}</span>
              </div>
            )}

            {/* Voice */}
            <button
              onClick={() => {
                localStorage.setItem(VOICE_KEY, voiceOn ? '0' : '1');
                setVoiceOn(!voiceOn);
              }}
              className={`flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all cursor-pointer ${
                voiceOn
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-300'
                  : 'bg-slate-100 text-slate-500 border-slate-300'
              }`}
              title="Chime + voice when you call a patient from this console"
            >
              {voiceOn ? <Volume2 className="w-3.5 h-3.5" /> : <VolumeX className="w-3.5 h-3.5" />}
              <span>{voiceOn ? 'Voice ON' : 'Muted'}</span>
            </button>

            {voiceOn && (
              <select
                value={language}
                onChange={e => {
                  localStorage.setItem(LANG_KEY, e.target.value);
                  setLanguage(e.target.value as AnnouncementLanguage);
                }}
                className="px-2 py-1.5 rounded-xl border border-slate-300 bg-white text-xs font-semibold text-slate-700 cursor-pointer"
                title="Announcement language"
              >
                <option value="bilingual">Bilingual (EN+UR)</option>
                <option value="english">EN only</option>
                <option value="urdu">اردو only</option>
              </select>
            )}

            {canManage && (
              <>
                <button
                  onClick={openSetup}
                  className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-bold border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 cursor-pointer"
                >
                  <Link2 className="w-3.5 h-3.5" />
                  Display Setup
                  {setupOpen ? <ChevronUp className="w-3 h-3" /> : <ChevronDown className="w-3 h-3" />}
                </button>
                {tvUrl && (
                  <button
                    onClick={() => window.open(tvUrl, '_blank')}
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold shadow-md shadow-cyan-600/20 cursor-pointer"
                    title="Open the waiting-room TV display in a new tab"
                  >
                    <ExternalLink className="w-3.5 h-3.5" />
                    Open TV
                  </button>
                )}
              </>
            )}

            <button
              onClick={() => {
                if (!document.fullscreenElement) {
                  void document.documentElement.requestFullscreen().catch(() => undefined);
                } else {
                  void document.exitFullscreen().catch(() => undefined);
                }
              }}
              className="p-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-600 cursor-pointer"
              title="Fullscreen console"
            >
              {isFullscreen ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
            </button>
          </div>
        </div>

        {/* ==================== display setup (admin only) ==================== */}
        {canManage && setupOpen && (
          <div className="mt-3 pt-3 border-t border-slate-200 space-y-3">
            <p className="text-xs font-bold text-slate-600 uppercase tracking-wider flex items-center gap-1.5">
              <KeyRound className="w-3.5 h-3.5" /> Waiting-room TV display link
            </p>
            {settingsBusy && !settings ? (
              <p className="text-xs text-slate-500 flex items-center gap-2"><RefreshCw className="w-3.5 h-3.5 animate-spin" /> Loading…</p>
            ) : (
              <>
                <div className="flex flex-col sm:flex-row gap-2">
                  <input
                    readOnly
                    value={tvUrl || 'Save once to generate the private display link'}
                    className="flex-1 px-3 py-2 rounded-xl border border-slate-300 bg-slate-50 text-xs font-mono text-slate-700"
                    onFocus={e => e.target.select()}
                  />
                  {tvUrl && (
                    <>
                      <button onClick={copyLink} className="px-3 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 cursor-pointer flex items-center gap-1.5">
                        <Copy className="w-3.5 h-3.5" /> {copied ? 'Copied!' : 'Copy link'}
                      </button>
                      <button onClick={regenerateKey} className="px-3 py-2 rounded-xl border border-rose-200 bg-rose-50 hover:bg-rose-100 text-xs font-bold text-rose-700 cursor-pointer flex items-center gap-1.5">
                        <RefreshCw className="w-3.5 h-3.5" /> Regenerate
                      </button>
                    </>
                  )}
                </div>
                <p className="text-[11px] text-slate-500">
                  Open this link in the waiting-area TV browser — it never asks for a login and shows the live queue automatically.
                  Regenerate if the link ever leaves your control; the old screen stops updating immediately.
                </p>

                <div className="flex flex-col sm:flex-row gap-2 sm:items-center">
                  <input
                    value={announcementDraft}
                    onChange={e => setAnnouncementDraft(e.target.value)}
                    maxLength={500}
                    placeholder="Optional announcement for the TV ticker (e.g. OPD closed today — radiology operates 9 AM to 9 PM)"
                    className="flex-1 px-3 py-2 rounded-xl border border-slate-300 text-xs"
                  />
                  <button
                    onClick={saveAnnouncement}
                    disabled={settingsBusy}
                    className="px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 disabled:opacity-50 text-white text-xs font-bold cursor-pointer flex items-center gap-1.5"
                  >
                    <Save className="w-3.5 h-3.5" /> Save announcement
                  </button>
                </div>
              </>
            )}
          </div>
        )}

        {/* ==================== zone filter (server-derived) ==================== */}
        <div className="flex items-center space-x-2 mt-3 pt-3 border-t border-slate-200 overflow-x-auto">
          <span className="text-xs font-semibold text-slate-500 whitespace-nowrap flex items-center gap-1">
            <Layers className="w-3.5 h-3.5" /> Zones:
          </span>
          {zones.map(zone => {
            const active = zoneFilter === String(zone.id);
            const count = zone.serving + zone.waiting;
            return (
              <button
                key={zone.id}
                onClick={() => setZoneFilter(String(zone.id))}
                className={`px-3 py-1 rounded-lg text-xs font-bold whitespace-nowrap transition-all cursor-pointer flex items-center space-x-1.5 ${
                  active ? 'bg-cyan-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-700'
                }`}
              >
                <span>{zone.name}</span>
                <span className={`text-[10px] px-1.5 rounded-full font-mono font-black ${active ? 'bg-white/20' : 'bg-slate-200 text-slate-700'}`}>
                  {count}
                </span>
              </button>
            );
          })}
        </div>
      </div>

      {/* ==================== board ==================== */}
      {!display ? (
        <div className="p-16 rounded-2xl border border-slate-200 bg-white text-center">
          <RefreshCw className="w-8 h-8 animate-spin mx-auto text-cyan-600" />
          <p className="text-sm text-slate-500 mt-3 font-semibold">Connecting to the live queue…</p>
        </div>
      ) : (
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-4">
          {/* -------- NOW SERVING -------- */}
          <div className="lg:col-span-7 space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-black text-emerald-600 uppercase tracking-wider flex items-center gap-2">
                <span className="relative flex h-2.5 w-2.5">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
                  <span className="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500" />
                </span>
                Now Serving
              </h3>
              <span className="text-xs font-mono font-bold text-slate-400">{nowServing.length} in suites</span>
            </div>

            {nowServing.length === 0 ? (
              <div className="p-10 rounded-2xl border border-slate-200 bg-white text-center">
                <Activity className="w-8 h-8 mx-auto mb-2 text-cyan-300" />
                <p className="text-sm font-bold text-slate-600">All examination suites available</p>
                <p className="text-xs text-slate-400 mt-1">Call a waiting patient to bring them into a suite.</p>
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                {nowServing.map(entry => {
                  const inScanner = entry.state === 'in_progress';
                  const busy = busyIds.has(entry.id);
                  return (
                    <div
                      key={entry.id}
                      className={`p-4 rounded-2xl border-2 bg-white relative overflow-hidden ${
                        inScanner ? 'border-emerald-400 shadow-md shadow-emerald-100' : 'border-sky-300 shadow-sm'
                      }`}
                    >
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-black uppercase tracking-wider text-slate-700 truncate">
                          {entry.roomName || 'Examination suite'}
                        </span>
                        {entry.modality && (
                          <span
                            className="text-[10px] font-black px-2 py-0.5 rounded text-white font-mono shrink-0"
                            style={{ backgroundColor: entry.modality.color }}
                          >
                            {entry.modality.code}
                          </span>
                        )}
                      </div>

                      <div className="my-2.5 flex items-baseline justify-between gap-2">
                        <span className="text-3xl md:text-4xl font-black font-mono tracking-wider">{entry.token}</span>
                        {entry.priority === 'stat' && (
                          <span className="text-xs bg-rose-600 text-white font-black px-2 py-0.5 rounded-md animate-pulse flex items-center gap-1">
                            <Flame className="w-3 h-3" /> STAT
                          </span>
                        )}
                      </div>

                      <p className="text-xs font-bold text-slate-700 truncate">{entry.patientName}</p>
                      <p className="text-[11px] text-slate-400 font-semibold mt-0.5">
                        {inScanner ? 'Inside scanner' : entry.state === 'preparing' ? 'Being prepared' : 'Called — awaiting staff'}
                      </p>

                      {canCall && (
                        <div className="mt-3 pt-2.5 border-t border-slate-100 flex items-center gap-2">
                          <button
                            onClick={() => recallEntry(entry)}
                            disabled={busy}
                            className="px-2.5 py-1 rounded-lg bg-white hover:bg-slate-50 text-cyan-700 border border-slate-300 text-xs font-bold cursor-pointer disabled:opacity-50 flex items-center gap-1"
                            title="Re-announce on every TV"
                          >
                            <BellRing className="w-3.5 h-3.5" /> Call again
                          </button>
                          {entry.state === 'checked_in' && (
                            <button
                              onClick={() => noShowEntry(entry)}
                              disabled={busy}
                              className="px-2.5 py-1 rounded-lg bg-white hover:bg-rose-50 text-rose-600 border border-rose-200 text-xs font-bold cursor-pointer disabled:opacity-50 flex items-center gap-1"
                              title="Patient never appeared — clear from the queue"
                            >
                              <UserX className="w-3.5 h-3.5" /> No-show
                            </button>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>

          {/* -------- NEXT IN LINE -------- */}
          <div className="lg:col-span-5 space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-black text-cyan-700 uppercase tracking-wider">Next in Line</h3>
              <span className="text-xs font-mono font-bold text-slate-400">{display.stats.waiting} waiting</span>
            </div>

            <div className="rounded-2xl border border-slate-200 bg-slate-50 p-3 space-y-2">
              {upNext.length === 0 ? (
                <div className="py-10 text-center">
                  <CheckCircle2 className="w-8 h-8 mx-auto text-emerald-400 opacity-60" />
                  <p className="text-sm font-bold text-slate-600 mt-2">Queue is clear</p>
                  <p className="text-xs text-slate-400">No patients waiting in this zone.</p>
                </div>
              ) : (
                upNext.map((entry, idx) => {
                  const busy = busyIds.has(entry.id);
                  return (
                    <div
                      key={entry.id}
                      className="p-2.5 rounded-xl border border-slate-200 bg-white flex items-center justify-between gap-2"
                    >
                      <div className="flex items-center gap-2.5 min-w-0">
                        <span className="w-6 h-6 rounded-lg bg-slate-100 border border-slate-200 text-slate-500 font-mono font-bold text-[10px] flex items-center justify-center shrink-0">
                          {idx + 1}
                        </span>
                        <div className="min-w-0">
                          <div className="flex items-center gap-1.5">
                            <span className="font-mono font-black text-base text-cyan-700">{entry.token}</span>
                            {entry.modality && (
                              <span
                                className="text-[9px] font-bold px-1.5 py-0.5 rounded text-white font-mono"
                                style={{ backgroundColor: entry.modality.color }}
                              >
                                {entry.modality.code}
                              </span>
                            )}
                            {entry.priority === 'stat' && (
                              <span className="text-[9px] bg-rose-600 text-white font-black px-1.5 rounded animate-pulse">STAT</span>
                            )}
                            {waited(entry) && (
                              <span className="text-[9px] font-bold text-amber-600">{waited(entry)}</span>
                            )}
                          </div>
                          <p className="text-xs font-semibold text-slate-500 truncate">{entry.patientName}</p>
                        </div>
                      </div>

                      {canCall ? (
                        <button
                          onClick={() => callEntry(entry)}
                          disabled={busy}
                          className="px-2.5 py-1 rounded-lg bg-cyan-600 hover:bg-cyan-500 disabled:opacity-50 text-white text-xs font-black cursor-pointer flex items-center gap-1 shrink-0"
                          title="Call to the suite — announces on every TV"
                        >
                          <Volume2 className="w-3 h-3" /> Call
                        </button>
                      ) : (
                        <span className="text-[10px] text-slate-400 font-bold shrink-0 truncate max-w-[90px]">{entry.roomName}</span>
                      )}
                    </div>
                  );
                })
              )}
            </div>

            {/* -------- recent calls -------- */}
            <div className="p-3 rounded-xl border border-slate-200 bg-slate-100/60">
              <p className="text-[11px] font-bold text-slate-500 flex items-center gap-1">
                <BellRing className="w-3 h-3" /> Recent calls
              </p>
              <div className="flex flex-wrap items-center gap-2 pt-1.5">
                {recentCalls.length === 0 ? (
                  <span className="text-[10px] italic text-slate-400">No calls recorded yet today.</span>
                ) : (
                  recentCalls.map(entry => (
                    <span
                      key={entry.id}
                      className="px-2 py-0.5 rounded bg-white border border-slate-200 font-mono text-[10px] font-bold text-slate-600"
                    >
                      #{entry.token} • {entry.calledAtLabel}
                    </span>
                  ))
                )}
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ==================== headline identity strip ==================== */}
      <p className="text-center text-[11px] text-slate-400 font-semibold">
        {headline} • the waiting-room TV shows this same queue at <span className="font-mono">/tv</span>
      </p>
    </div>
  );
};
