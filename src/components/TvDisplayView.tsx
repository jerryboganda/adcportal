import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Maximize2, Minimize2, Radio, Volume2, VolumeX, WifiOff } from 'lucide-react';
import { QueueDisplay, QueueZone } from '../types';
import { fetchPublicQueueDisplay } from '../services/apiService';
import { AnnouncementLanguage, announceCall, playChime, unlockAudio } from '../services/queueAnnouncer';

const POLL_INTERVAL_MS = 5000;
const LIVE_WINDOW_MS = 15000;
const BANNER_MS = 8000;

const SOUND_KEY = 'polytronx_tv_sound';
const LANG_KEY = 'polytronx_tv_lang';

const GUIDANCE = [
  '🔔 Please keep your token slip ready before entering the examination suite.',
  '🧪 IV-contrast CT/MRI patients: submit your serum creatinine / eGFR report at the nurse station.',
  '💧 Abdomen & pelvis ultrasound patients: maintain a full urinary bladder as instructed at reception.',
  '⚡ Emergency (STAT) and intensive-care cases receive immediate priority.',
  '🙏 Please remain in the waiting area — the screen and voice call each token in turn.',
];

/**
 * Waiting-room TV kiosk (`/tv?key=…`): a public, always-dark display surface
 * rendered OUTSIDE the authenticated app shell. It never shows a login
 * screen — on network failure it keeps polling behind a "reconnecting"
 * overlay, and an invalid/regenerated key degrades to a setup hint.
 */
export const TvDisplayView: React.FC = () => {
  const displayKey = useMemo(() => new URLSearchParams(window.location.search).get('key') ?? '', []);
  const zoneFilter = useMemo(() => {
    const raw = new URLSearchParams(window.location.search).get('zones');
    return raw ? raw.split(',').map(s => s.trim().toUpperCase()).filter(Boolean) : null;
  }, []);

  const [display, setDisplay] = useState<QueueDisplay | null>(null);
  const [lastSyncAt, setLastSyncAt] = useState<number>(0);
  const [failureStreak, setFailureStreak] = useState(0);
  const [invalidKey, setInvalidKey] = useState(false);
  const [now, setNow] = useState(new Date());
  const [soundOn, setSoundOn] = useState(() => localStorage.getItem(SOUND_KEY) === '1');
  const [language, setLanguage] = useState<AnnouncementLanguage>(
    () => (localStorage.getItem(LANG_KEY) as AnnouncementLanguage) || 'bilingual'
  );
  const [banner, setBanner] = useState<{ token: string; room: string } | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(() => !!document.fullscreenElement);

  useEffect(() => {
    const onFsChange = () => setIsFullscreen(!!document.fullscreenElement);
    document.addEventListener('fullscreenchange', onFsChange);
    return () => document.removeEventListener('fullscreenchange', onFsChange);
  }, []);

  const seenCalls = useRef<Set<string>>(new Set());
  const firstLoad = useRef(true);
  const soundRef = useRef(soundOn);
  const langRef = useRef(language);
  soundRef.current = soundOn;
  langRef.current = language;

  // 1s tick drives the clock + served-patient elapsed timers.
  useEffect(() => {
    const timer = window.setInterval(() => setNow(new Date()), 1000);
    return () => window.clearInterval(timer);
  }, []);

  useEffect(() => {
    document.title = display?.businessName ? `${display.businessName} — Patient Queue` : 'Patient Queue';
  }, [display?.businessName]);

  const applyPoll = useCallback((payload: QueueDisplay) => {
    setDisplay(payload);
    setLastSyncAt(Date.now());
    setFailureStreak(0);
    setInvalidKey(false);

    // Announce calls that are new since the previous poll. The very first
    // load only seeds the seen-set so a freshly opened TV never replays a
    // call that was already announced minutes ago.
    for (const entry of payload.nowServing) {
      if (!entry.calledAt) continue;
      const fingerprint = `${entry.id}:${entry.calledAt}`;
      if (firstLoad.current || seenCalls.current.has(fingerprint)) {
        seenCalls.current.add(fingerprint);
        continue;
      }
      seenCalls.current.add(fingerprint);
      if (soundRef.current) {
        playChime();
        announceCall({ token: entry.token, roomName: entry.roomName, language: langRef.current });
      }
      setBanner({ token: entry.token, room: entry.roomName || 'the examination area' });
      window.setTimeout(() => setBanner(null), BANNER_MS);
    }
    firstLoad.current = false;
  }, []);

  useEffect(() => {
    if (!displayKey) return;
    let cancelled = false;

    const poll = async () => {
      try {
        const payload = await fetchPublicQueueDisplay(displayKey);
        if (!cancelled) applyPoll(payload);
      } catch (err: any) {
        if (cancelled) return;
        if (err?.status === 404) {
          setInvalidKey(true);
        } else {
          setFailureStreak(s => s + 1);
        }
      }
    };

    void poll();
    const interval = window.setInterval(poll, POLL_INTERVAL_MS);
    return () => {
      cancelled = true;
      window.clearInterval(interval);
    };
  }, [displayKey, applyPoll]);

  const enableSound = () => {
    unlockAudio();
    localStorage.setItem(SOUND_KEY, '1');
    setSoundOn(true);
  };

  const toggleSound = () => {
    if (soundOn) {
      localStorage.setItem(SOUND_KEY, '0');
      setSoundOn(false);
    } else {
      enableSound();
    }
  };

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      void document.documentElement.requestFullscreen().catch(() => undefined);
    } else {
      void document.exitFullscreen().catch(() => undefined);
    }
  };

  const isLive = Date.now() - lastSyncAt < LIVE_WINDOW_MS;
  const reconnecting = failureStreak >= 2 || (lastSyncAt > 0 && Date.now() - lastSyncAt > LIVE_WINDOW_MS);

  // Elapsed helpers (client clock vs absolute ISO instants — skew is immaterial for display).
  const elapsed = (iso: string | null) => {
    if (!iso) return null;
    const minutes = Math.max(0, Math.floor((now.getTime() - new Date(iso).getTime()) / 60000));
    if (minutes < 60) return `${minutes} min`;
    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
  };

  const zones: QueueZone[] = useMemo(() => {
    if (!display) return [];
    const active = display.zones.filter(z => z.serving > 0 || z.waiting > 0);
    const filtered = zoneFilter ? active.filter(z => zoneFilter.includes(z.code.toUpperCase())) : active;
    return filtered;
  }, [display, zoneFilter]);

  if (!displayKey) {
    return (
      <TvShell>
        <div className="text-center max-w-xl px-6">
          <MonitorIcon />
          <h1 className="text-2xl font-black text-white mt-6">Waiting-Area Display</h1>
          <p className="text-slate-400 mt-3 text-sm leading-relaxed">
            This screen must be opened with the clinic&apos;s private display link
            (<code className="text-cyan-300">/tv?key=…</code>). Ask the front desk to copy it from
            Live Queue TV → Display Setup.
          </p>
        </div>
      </TvShell>
    );
  }

  if (invalidKey) {
    return (
      <TvShell>
        <div className="text-center max-w-xl px-6">
          <WifiOff className="w-12 h-12 text-rose-400 mx-auto" />
          <h1 className="text-2xl font-black text-white mt-6">Display link is no longer valid</h1>
          <p className="text-slate-400 mt-3 text-sm leading-relaxed">
            The queue display link was regenerated or revoked. The front desk can copy the current
            link from Live Queue TV → Display Setup.
          </p>
        </div>
      </TvShell>
    );
  }

  if (!display) {
    return (
      <TvShell>
        <div className="text-center">
          <div className="w-12 h-12 border-4 border-cyan-500/30 border-t-cyan-400 rounded-full animate-spin mx-auto" />
          <p className="text-slate-400 mt-6 text-sm font-semibold">Connecting to the live queue…</p>
        </div>
      </TvShell>
    );
  }

  const tickerMessages = display.announcement
    ? [`📢 ${display.announcement}`, ...GUIDANCE]
    : GUIDANCE;

  return (
    <div className="fixed inset-0 bg-slate-950 text-white overflow-hidden flex flex-col select-none">
      {/* ================= header ================= */}
      <header className="shrink-0 px-6 lg:px-10 pt-5 pb-4 border-b border-slate-800/80 bg-slate-900/40">
        <div className="flex items-center justify-between gap-4">
          <div className="flex items-center gap-4 min-w-0">
            <div className="w-12 h-12 lg:w-14 lg:h-14 rounded-2xl bg-cyan-600 shadow-lg shadow-cyan-600/30 flex items-center justify-center font-black text-xl lg:text-2xl shrink-0">
              PX
            </div>
            <div className="min-w-0">
              <h1 className="text-xl lg:text-3xl font-black tracking-tight truncate">{display.businessName || 'Radiology Department'}</h1>
              <p className="text-[11px] lg:text-sm text-cyan-400 font-bold tracking-wider uppercase">
                Radiology &amp; Imaging Department • Patient Calling System
              </p>
            </div>
          </div>

          <div className="flex items-center gap-5">
            <div className="text-right">
              <div className="text-2xl lg:text-4xl font-black font-mono tracking-widest text-cyan-300">
                {now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
              </div>
              <div className="text-[11px] lg:text-sm font-semibold text-slate-400">
                {now.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' })}
              </div>
            </div>

            {/* LIVE badge — derived from actual poll freshness, not decorative. */}
            <span
              className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] lg:text-xs font-black border ${
                isLive
                  ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40'
                  : 'bg-amber-500/15 text-amber-300 border-amber-500/40'
              }`}
              title={isLive ? 'Live feed connected' : 'Reconnecting to the live feed'}
            >
              <Radio className={`w-3 h-3 ${isLive ? 'animate-pulse' : ''}`} />
              {isLive ? 'LIVE' : 'SYNC…'}
            </span>

            <div className="flex items-center gap-1.5">
              <button
                onClick={toggleSound}
                className={`p-2 lg:p-2.5 rounded-xl border text-xs font-bold cursor-pointer transition-colors ${
                  soundOn
                    ? 'bg-emerald-500/15 text-emerald-300 border-emerald-500/40 hover:bg-emerald-500/25'
                    : 'bg-slate-800 text-slate-400 border-slate-700 hover:bg-slate-700'
                }`}
                title={soundOn ? 'Mute voice announcements' : 'Enable voice announcements'}
              >
                {soundOn ? <Volume2 className="w-4 h-4 lg:w-5 lg:h-5" /> : <VolumeX className="w-4 h-4 lg:w-5 lg:h-5" />}
              </button>
              {soundOn && (
                <div className="flex rounded-xl border border-slate-700 bg-slate-900 p-0.5 text-[10px] lg:text-xs font-bold">
                  {(['bilingual', 'english', 'urdu'] as AnnouncementLanguage[]).map(lang => (
                    <button
                      key={lang}
                      onClick={() => {
                        localStorage.setItem(LANG_KEY, lang);
                        setLanguage(lang);
                      }}
                      className={`px-2 py-1.5 rounded-lg cursor-pointer transition-colors ${
                        language === lang ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:text-slate-200'
                      }`}
                    >
                      {lang === 'bilingual' ? 'EN+اردو' : lang === 'english' ? 'EN' : 'اردو'}
                    </button>
                  ))}
                </div>
              )}
              <button
                onClick={toggleFullscreen}
                className="p-2 lg:p-2.5 rounded-xl bg-slate-800 border border-slate-700 text-slate-300 hover:bg-slate-700 cursor-pointer"
                title="Toggle fullscreen"
              >
                {isFullscreen ? <Minimize2 className="w-4 h-4 lg:w-5 lg:h-5" /> : <Maximize2 className="w-4 h-4 lg:w-5 lg:h-5" />}
              </button>
            </div>
          </div>
        </div>

        {!soundOn && (
          <button
            onClick={enableSound}
            className="mt-3 w-full flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl bg-cyan-600/90 hover:bg-cyan-500 text-white text-sm font-bold cursor-pointer shadow-lg shadow-cyan-600/20"
          >
            <Volume2 className="w-4 h-4" />
            Tap once to enable voice announcements
          </button>
        )}
      </header>

      {/* ================= attention banner ================= */}
      <div className="shrink-0 px-6 lg:px-10 pt-4">
        {banner ? (
          <div
            key={`${banner.token}:${banner.room}`}
            className="animate-in fade-in slide-in-from-top-2 duration-300 rounded-2xl bg-gradient-to-r from-cyan-600 via-sky-600 to-indigo-700 border border-white/20 px-6 py-3.5 text-center shadow-2xl"
          >
            <p className="text-[10px] lg:text-xs font-black tracking-[0.25em] text-cyan-100 uppercase">
              Patient Attention Please • توجہ فرمائیں
            </p>
            <p className="text-2xl lg:text-4xl font-black font-mono tracking-wider mt-0.5">
              TOKEN {banner.token}
            </p>
            <p className="text-sm lg:text-lg font-bold text-cyan-100">
              Please proceed to {banner.room}
            </p>
          </div>
        ) : (
          <div className="rounded-2xl bg-slate-900/40 border border-slate-800/60 px-6 py-2 text-center text-[11px] lg:text-xs font-semibold text-slate-500 tracking-wider uppercase">
            {display.stats.waiting > 0
              ? `${display.stats.waiting} patient${display.stats.waiting === 1 ? '' : 's'} waiting • please watch the screen for your token`
              : 'Welcome — please watch the screen for your token'}
          </div>
        )}
      </div>

      {/* ================= zone grid ================= */}
      <main className="flex-1 min-h-0 overflow-hidden px-6 lg:px-10 py-4">
        {zones.length === 0 ? (
          <div className="h-full flex flex-col items-center justify-center text-center">
            <div className="w-16 h-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center">
              <span className="text-3xl">✓</span>
            </div>
            <p className="text-xl lg:text-2xl font-black text-emerald-300 mt-5">Queue is clear</p>
            <p className="text-slate-500 text-sm mt-1">No patients currently waiting in any zone.</p>
          </div>
        ) : (
          <div className="h-full grid grid-cols-1 md:grid-cols-2 2xl:grid-cols-3 gap-4 lg:gap-5 content-start overflow-y-auto">
            {zones.map(zone => {
              const serving = display.nowServing.filter(e => e.modality?.id === zone.id);
              const next = display.upNext.filter(e => e.modality?.id === zone.id).slice(0, 4);
              return (
                <section
                  key={zone.id}
                  className="rounded-2xl border border-slate-800 bg-slate-900/50 overflow-hidden flex flex-col"
                >
                  <div
                    className="px-4 py-2.5 flex items-center justify-between border-b border-slate-800"
                    style={{ backgroundColor: `${zone.color}22` }}
                  >
                    <div className="flex items-center gap-2 min-w-0">
                      <span className="w-2.5 h-2.5 rounded-full shrink-0" style={{ backgroundColor: zone.color }} />
                      <h2 className="text-sm lg:text-base font-black uppercase tracking-wider truncate">{zone.name}</h2>
                    </div>
                    <span className="text-[10px] lg:text-xs font-bold text-slate-400 shrink-0">
                      {zone.serving > 0 ? `${zone.serving} in suite` : `${zone.waiting} waiting`}
                    </span>
                  </div>

                  <div className="p-3 space-y-2.5 flex-1">
                    {serving.length === 0 ? (
                      <p className="text-xs text-slate-600 font-semibold py-3 text-center uppercase tracking-widest">
                        Suite available
                      </p>
                    ) : (
                      serving.slice(0, 3).map(entry => (
                        <div
                          key={entry.id}
                          className="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3.5 py-2.5"
                        >
                          <div className="flex items-center justify-between gap-2">
                            <span className="text-2xl lg:text-4xl font-black font-mono tracking-wider text-emerald-300">
                              {entry.token}
                            </span>
                            <div className="text-right min-w-0">
                              <p className="text-xs lg:text-sm font-bold truncate">{entry.patientName}</p>
                              <p className="text-[10px] lg:text-xs text-slate-400 truncate">
                                {entry.roomName || 'Examination suite'}
                              </p>
                            </div>
                          </div>
                          <div className="flex items-center justify-between mt-1.5">
                            <span className="text-[9px] lg:text-[10px] font-black uppercase tracking-widest text-emerald-400 flex items-center gap-1">
                              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" />
                              {entry.state === 'in_progress' ? 'In scanner' : entry.calledAt ? 'Called' : 'In suite'}
                            </span>
                            <span className="text-[10px] text-slate-500 font-mono">{elapsed(entry.calledAt ?? entry.checkedInAt)}</span>
                          </div>
                        </div>
                      ))
                    )}

                    {next.length > 0 && (
                      <div className="pt-1">
                        <p className="text-[9px] lg:text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Next</p>
                        <div className="flex flex-wrap gap-1.5">
                          {next.map(entry => (
                            <span
                              key={entry.id}
                              className="px-2.5 py-1 rounded-lg bg-slate-800/80 border border-slate-700 text-xs lg:text-sm font-mono font-bold text-slate-300"
                            >
                              {entry.token}
                              {entry.priority === 'stat' && <span className="text-rose-400 font-black ml-1">STAT</span>}
                            </span>
                          ))}
                        </div>
                      </div>
                    )}
                  </div>
                </section>
              );
            })}
          </div>
        )}
      </main>

      {/* ================= recent calls ================= */}
      {display.recentCalls.length > 0 && (
        <div className="shrink-0 px-6 lg:px-10 pb-2">
          <div className="rounded-xl bg-slate-900/40 border border-slate-800/60 px-4 py-2 flex items-center gap-3 overflow-hidden">
            <span className="text-[10px] font-black uppercase tracking-widest text-slate-500 shrink-0">Recently called</span>
            <div className="flex gap-2 overflow-hidden">
              {display.recentCalls.slice(0, 6).map(entry => (
                <span
                  key={entry.id}
                  className="px-2 py-0.5 rounded bg-slate-800/70 border border-slate-700/70 text-[10px] lg:text-xs font-mono font-bold text-slate-400 whitespace-nowrap"
                >
                  #{entry.token} • {entry.calledAtLabel}
                </span>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* ================= ticker ================= */}
      <Ticker messages={tickerMessages} />

      {/* ================= reconnect overlay ================= */}
      {reconnecting && (
        <div className="absolute inset-0 bg-slate-950/85 backdrop-blur-sm flex flex-col items-center justify-center z-40">
          <div className="w-12 h-12 border-4 border-cyan-500/30 border-t-cyan-400 rounded-full animate-spin" />
          <p className="text-white font-bold mt-5">Reconnecting to the live queue…</p>
          <p className="text-slate-500 text-xs mt-1">The display resumes automatically.</p>
        </div>
      )}
    </div>
  );
};

/** Rotating bottom ticker — the remount key makes each message fade in fresh. */
const Ticker: React.FC<{ messages: string[] }> = ({ messages }) => {
  const [index, setIndex] = useState(0);

  useEffect(() => {
    const timer = window.setInterval(() => {
      setIndex(prev => (prev + 1) % messages.length);
    }, 8000);
    return () => window.clearInterval(timer);
  }, [messages.length]);

  return (
    <footer className="shrink-0 bg-cyan-950/60 border-t border-cyan-500/20 px-6 lg:px-10 py-2.5 flex items-center gap-3 overflow-hidden">
      <span className="bg-cyan-600 text-white text-[10px] font-black px-2 py-0.5 rounded uppercase tracking-widest shrink-0">
        Notice
      </span>
      <p key={index} className="animate-in fade-in duration-500 text-xs lg:text-sm font-semibold text-cyan-100 truncate">
        {messages[index]}
      </p>
    </footer>
  );
};

const TvShell: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div className="fixed inset-0 bg-slate-950 text-white flex items-center justify-center">{children}</div>
);

const MonitorIcon: React.FC = () => (
  <div className="w-16 h-16 rounded-2xl bg-cyan-600/20 border border-cyan-500/40 mx-auto flex items-center justify-center">
    <Radio className="w-8 h-8 text-cyan-400" />
  </div>
);
