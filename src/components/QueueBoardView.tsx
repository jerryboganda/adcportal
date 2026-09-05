import React, { useState, useEffect, useRef } from 'react';
import {
  Tv,
  Volume2,
  VolumeX,
  Maximize2,
  Minimize2,
  Clock,
  Activity,
  Sparkles,
  CheckCircle2,
  Flame,
  Radio,
  Sliders,
  Languages,
  Moon,
  Sun,
  Bell,
  Play,
  RotateCcw,
  Layers,
  ArrowRight,
  AlertTriangle,
  Info,
  ShieldCheck,
  UserCheck
} from 'lucide-react';
import { Appointment } from '../types';

interface QueueBoardViewProps {
  appointments: Appointment[];
}

export const QueueBoardView: React.FC<QueueBoardViewProps> = ({ appointments }) => {
  const [currentTime, setCurrentTime] = useState(new Date());
  const [calledToken, setCalledToken] = useState<{ token: string; room: string; service: string; modality: string } | null>(null);
  const [isFullscreen, setIsFullscreen] = useState(false);
  const [isDarkMode, setIsDarkMode] = useState(false);
  const [voiceEnabled, setVoiceEnabled] = useState(true);
  const [voiceLanguage, setVoiceLanguage] = useState<'bilingual' | 'english' | 'urdu'>('bilingual');
  const [selectedModalityFilter, setSelectedModalityFilter] = useState<string>('all');
  const [callHistory, setCallHistory] = useState<{ token: string; room: string; time: string; modality: string }[]>([
    { token: 'DX-01', room: 'Room 1 (X-Ray Suite A)', time: '12:20 PM', modality: 'DX' },
    { token: 'CT-01', room: 'Room 2 (128-Slice CT)', time: '12:12 PM', modality: 'CT' },
    { token: 'US-01', room: 'Room 3 (Ultrasound Suite)', time: '12:05 PM', modality: 'US' },
  ]);

  const [tickerIndex, setTickerIndex] = useState(0);
  const tickerMessages = [
    '🔔 Amad Diagnostic Centre: Please keep your computerized token slip ready before entering the examination suite.',
    '🧪 Patients undergoing IV Contrast CT or MRI scans must submit their serum creatinine / eGFR report to the nurse station.',
    '💧 Abdomen & Pelvis Ultrasound patients: Please maintain a full urinary bladder as instructed by the reception.',
    '⚡ STAT Trauma & Intensive Care cases receive immediate emergency clinical priority.',
    '📄 Verified final diagnostic reports with key DICOM images can be downloaded from the ADC Online Patient Portal.',
  ];

  const boardContainerRef = useRef<HTMLDivElement>(null);

  // Clock interval
  useEffect(() => {
    const timer = setInterval(() => setCurrentTime(new Date()), 1000);
    return () => clearInterval(timer);
  }, []);

  // Ticker rotation interval
  useEffect(() => {
    const tickerTimer = setInterval(() => {
      setTickerIndex((prev) => (prev + 1) % tickerMessages.length);
    }, 8000);
    return () => clearInterval(tickerTimer);
  }, [tickerMessages.length]);

  // Fullscreen change listener
  useEffect(() => {
    const handleFsChange = () => {
      setIsFullscreen(!!document.fullscreenElement);
    };
    document.addEventListener('fullscreenchange', handleFsChange);
    return () => document.removeEventListener('fullscreenchange', handleFsChange);
  }, []);

  // Filtered Appointments
  const activeModalityFilter = (apt: Appointment) => {
    if (selectedModalityFilter === 'all') return true;
    return apt.modality.code === selectedModalityFilter;
  };

  const nowServing = appointments.filter(a => a.workflowState === 'in_progress' && activeModalityFilter(a));
  const preparing = appointments.filter(a => a.workflowState === 'preparing' && activeModalityFilter(a));
  const upNext = appointments.filter(a => a.workflowState === 'checked_in' && activeModalityFilter(a));

  // High-fidelity Hospital Chime & Web Speech API Synthesis
  const triggerChime = (token: string, room: string, serviceName: string, modalityCode: string) => {
    setCalledToken({ token, room, service: serviceName, modality: modalityCode });
    
    // Add to call history
    const callTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setCallHistory(prev => [{ token, room, time: callTime, modality: modalityCode }, ...prev.slice(0, 4)]);

    // 1. Dual-tone Harmonic Medical Chime (Web Audio API)
    try {
      const audioCtx = new (window.AudioContext || (window as any).webkitAudioContext)();
      const now = audioCtx.currentTime;

      // Note 1: F5 (698.46 Hz)
      const osc1 = audioCtx.createOscillator();
      const gain1 = audioCtx.createGain();
      osc1.type = 'sine';
      osc1.frequency.setValueAtTime(698.46, now);
      gain1.gain.setValueAtTime(0.25, now);
      gain1.gain.exponentialRampToValueAtTime(0.001, now + 0.45);
      osc1.connect(gain1);
      gain1.connect(audioCtx.destination);
      osc1.start(now);
      osc1.stop(now + 0.45);

      // Note 2: A5 (880.00 Hz)
      const osc2 = audioCtx.createOscillator();
      const gain2 = audioCtx.createGain();
      osc2.type = 'sine';
      osc2.frequency.setValueAtTime(880.00, now + 0.15);
      gain2.gain.setValueAtTime(0.3, now + 0.15);
      gain2.gain.exponentialRampToValueAtTime(0.001, now + 0.65);
      osc2.connect(gain2);
      gain2.connect(audioCtx.destination);
      osc2.start(now + 0.15);
      osc2.stop(now + 0.65);

      // Note 3: C6 (1046.50 Hz) - Tri-tone Resolution
      const osc3 = audioCtx.createOscillator();
      const gain3 = audioCtx.createGain();
      osc3.type = 'sine';
      osc3.frequency.setValueAtTime(1046.50, now + 0.35);
      gain3.gain.setValueAtTime(0.35, now + 0.35);
      gain3.gain.exponentialRampToValueAtTime(0.001, now + 1.2);
      osc3.connect(gain3);
      gain3.connect(audioCtx.destination);
      osc3.start(now + 0.35);
      osc3.stop(now + 1.2);
    } catch (e) {
      console.log('Audio Context chime unavailable or blocked.');
    }

    // 2. Synthesized Speech Announcement
    if (voiceEnabled && 'speechSynthesis' in window) {
      window.speechSynthesis.cancel(); // cancel pending

      const tokenSpoken = token.split('').join(' ');
      const cleanRoom = room.replace(/\([^)]*\)/g, '').trim();

      setTimeout(() => {
        if (voiceLanguage === 'english' || voiceLanguage === 'bilingual') {
          const textEnglish = `Token number ${tokenSpoken}. Please proceed to ${cleanRoom}.`;
          const utteranceEn = new SpeechSynthesisUtterance(textEnglish);
          utteranceEn.rate = 0.92;
          utteranceEn.pitch = 1.05;
          utteranceEn.lang = 'en-US';
          window.speechSynthesis.speak(utteranceEn);
        }

        if (voiceLanguage === 'urdu' || voiceLanguage === 'bilingual') {
          // Romanized Urdu announcement for waiting room
          const textUrdu = `Tawajjah farmayiye. Token number ${tokenSpoken}. Baraye meherbani ${cleanRoom} mein tashreef layiye.`;
          const utteranceUrdu = new SpeechSynthesisUtterance(textUrdu);
          utteranceUrdu.rate = 0.88;
          utteranceUrdu.pitch = 1.0;
          utteranceUrdu.lang = 'ur-PK';
          // Fallback to default voice if ur-PK is not installed
          setTimeout(() => {
            window.speechSynthesis.speak(utteranceUrdu);
          }, 1800);
        }
      }, 500);
    }

    setTimeout(() => {
      setCalledToken(null);
    }, 6500);
  };

  const toggleFullscreen = () => {
    if (!document.fullscreenElement) {
      if (boardContainerRef.current) {
        boardContainerRef.current.requestFullscreen().catch(() => {});
      } else {
        document.documentElement.requestFullscreen().catch(() => {});
      }
    } else {
      document.exitFullscreen().catch(() => {});
    }
  };

  // Modality statistics for status strip
  const modalitiesList = [
    { code: 'all', name: 'All Suites', count: appointments.filter(a => ['in_progress', 'preparing', 'checked_in'].includes(a.workflowState)).length },
    { code: 'MR', name: 'MRI Suite', count: appointments.filter(a => a.modality.code === 'MR' && ['in_progress', 'preparing', 'checked_in'].includes(a.workflowState)).length },
    { code: 'CT', name: 'CT Scan', count: appointments.filter(a => a.modality.code === 'CT' && ['in_progress', 'preparing', 'checked_in'].includes(a.workflowState)).length },
    { code: 'US', name: 'Ultrasound', count: appointments.filter(a => a.modality.code === 'US' && ['in_progress', 'preparing', 'checked_in'].includes(a.workflowState)).length },
    { code: 'DX', name: 'X-Ray / CR', count: appointments.filter(a => a.modality.code === 'DX' && ['in_progress', 'preparing', 'checked_in'].includes(a.workflowState)).length },
    { code: 'MG', name: 'Mammography', count: appointments.filter(a => a.modality.code === 'MG' && ['in_progress', 'preparing', 'checked_in'].includes(a.workflowState)).length },
  ];

  return (
    <div className="space-y-4" ref={boardContainerRef}>
      {/* TV Display Control Bar (Management Console) */}
      <div className={`p-4 rounded-2xl border transition-all ${
        isDarkMode ? 'bg-slate-900 border-slate-800 text-white' : 'bg-white border-slate-200 shadow-sm text-slate-900'
      }`}>
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-cyan-600 text-white flex items-center justify-center shadow-md shadow-cyan-600/20">
              <Tv className="w-5 h-5" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <h2 className="text-base font-bold tracking-tight">Radiology Waiting Area TV Display System</h2>
                <span className="bg-emerald-500/20 text-emerald-500 text-[10px] font-bold px-2 py-0.5 rounded-full border border-emerald-500/30 flex items-center gap-1">
                  <Radio className="w-3 h-3 animate-pulse" /> LIVE STREAMING
                </span>
              </div>
              <p className="text-xs opacity-75">
                Multi-zone high-visibility patient calling console, automated voice synthesis (English/Urdu), and tri-tone audio chimes.
              </p>
            </div>
          </div>

          {/* Quick Settings & Controls */}
          <div className="flex flex-wrap items-center gap-2">
            {/* Audio Voice Synthesizer Toggle */}
            <button
              onClick={() => setVoiceEnabled(!voiceEnabled)}
              className={`flex items-center space-x-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold border transition-all cursor-pointer ${
                voiceEnabled
                  ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-700'
                  : 'bg-slate-100 text-slate-500 border-slate-300 dark:bg-slate-800 dark:text-slate-400'
              }`}
              title="Toggle Voice Synthesizer Announcements"
            >
              {voiceEnabled ? <Volume2 className="w-3.5 h-3.5" /> : <VolumeX className="w-3.5 h-3.5" />}
              <span>{voiceEnabled ? 'Voice: ON' : 'Voice: MUTED'}</span>
            </button>

            {/* Language Switch */}
            {voiceEnabled && (
              <div className="flex items-center bg-slate-100 dark:bg-slate-800 rounded-xl p-0.5 border border-slate-300 dark:border-slate-700 text-xs font-medium">
                <button
                  onClick={() => setVoiceLanguage('bilingual')}
                  className={`px-2 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition-all ${
                    voiceLanguage === 'bilingual' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400'
                  }`}
                >
                  Bilingual (EN+UR)
                </button>
                <button
                  onClick={() => setVoiceLanguage('english')}
                  className={`px-2 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition-all ${
                    voiceLanguage === 'english' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400'
                  }`}
                >
                  EN Only
                </button>
                <button
                  onClick={() => setVoiceLanguage('urdu')}
                  className={`px-2 py-1 rounded-lg text-[11px] font-bold cursor-pointer transition-all ${
                    voiceLanguage === 'urdu' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400'
                  }`}
                >
                  اردو Only
                </button>
              </div>
            )}

            {/* Dark Mode Theme Toggle */}
            <button
              onClick={() => setIsDarkMode(!isDarkMode)}
              className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-semibold border border-slate-300 dark:border-slate-700 transition-colors cursor-pointer"
              title="Toggle High-Contrast Display Mode"
            >
              {isDarkMode ? <Sun className="w-3.5 h-3.5 text-amber-400" /> : <Moon className="w-3.5 h-3.5 text-slate-600" />}
              <span>{isDarkMode ? 'Light Mode' : 'Dark Mode'}</span>
            </button>

            {/* Fullscreen Button */}
            <button
              onClick={toggleFullscreen}
              className="flex items-center space-x-1.5 px-3.5 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold shadow-md shadow-cyan-600/20 transition-colors cursor-pointer"
              title="Switch to 1080p/4K Waiting Hall TV Fullscreen Mode"
            >
              {isFullscreen ? <Minimize2 className="w-3.5 h-3.5" /> : <Maximize2 className="w-3.5 h-3.5" />}
              <span>{isFullscreen ? 'Exit Fullscreen' : 'TV Fullscreen'}</span>
            </button>
          </div>
        </div>

        {/* Modality Suite Tabs */}
        <div className="flex items-center space-x-2 mt-3 pt-3 border-t border-slate-200 dark:border-slate-800 overflow-x-auto">
          <span className="text-xs font-semibold opacity-60 whitespace-nowrap flex items-center gap-1">
            <Layers className="w-3.5 h-3.5" /> Suite Filter:
          </span>
          {modalitiesList.map((m) => (
            <button
              key={m.code}
              onClick={() => setSelectedModalityFilter(m.code)}
              className={`px-3 py-1 rounded-lg text-xs font-bold whitespace-nowrap transition-all cursor-pointer flex items-center space-x-1.5 ${
                selectedModalityFilter === m.code
                  ? 'bg-cyan-600 text-white shadow-xs'
                  : 'bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300'
              }`}
            >
              <span>{m.name}</span>
              <span className={`text-[10px] px-1.5 py-0.2 rounded-full font-mono font-black ${
                selectedModalityFilter === m.code ? 'bg-white/20 text-white' : 'bg-slate-200 dark:bg-slate-700 text-slate-800 dark:text-slate-200'
              }`}>
                {m.count}
              </span>
            </button>
          ))}
        </div>
      </div>

      {/* ========================================================================= */}
      {/* PRIMARY TV DISPLAY CANVAS                                                */}
      {/* ========================================================================= */}
      <div
        className={`rounded-3xl border-2 transition-all duration-300 p-6 md:p-8 space-y-6 ${
          isDarkMode
            ? 'bg-slate-950 border-slate-800 text-white shadow-2xl'
            : 'bg-white border-cyan-300 text-slate-900 shadow-xl'
        }`}
      >
        {/* Top TV Header Bar */}
        <div className="flex flex-col md:flex-row md:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5 gap-4">
          <div className="flex items-center space-x-4">
            <div className="w-14 h-14 rounded-2xl bg-cyan-600 text-white font-black text-2xl flex items-center justify-center shadow-lg shadow-cyan-600/30">
              ADC
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <h1 className="text-2xl md:text-3xl font-black tracking-tight">AMAD DIAGNOSTIC CENTRE</h1>
                <span className="bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 text-xs font-black px-2 py-0.5 rounded uppercase font-mono">
                  RIS/PACS LIVE
                </span>
              </div>
              <p className="text-xs md:text-sm text-cyan-700 dark:text-cyan-400 font-bold tracking-wider uppercase mt-0.5 flex items-center gap-2">
                <span>Radiology & Advanced Imaging Department</span>
                <span>•</span>
                <span>Patient Calling System</span>
              </p>
            </div>
          </div>

          <div className="text-right flex items-center md:items-end justify-between md:justify-center flex-row md:flex-col">
            <div className="text-3xl md:text-4xl font-black font-mono tracking-widest text-cyan-600 dark:text-cyan-300">
              {currentTime.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
            </div>
            <div className="text-xs md:text-sm font-semibold opacity-75">
              {currentTime.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' })}
            </div>
          </div>
        </div>

        {/* Dynamic Patient Calling Attention Flash Banner */}
        {calledToken && (
          <div className="bg-gradient-to-r from-cyan-600 via-sky-600 to-indigo-700 p-5 rounded-2xl text-center shadow-2xl animate-bounce border-2 border-white/40">
            <div className="flex items-center justify-center space-x-2 text-white/90 font-bold text-xs md:text-sm tracking-widest uppercase mb-1">
              <Bell className="w-5 h-5 animate-spin" />
              <span>PATIENT ATTENTION PLEASE • توجہ فرمائیں</span>
            </div>
            <div className="text-3xl md:text-5xl font-black text-white font-mono tracking-wider">
              TOKEN #{calledToken.token}
            </div>
            <div className="text-base md:text-xl font-bold text-cyan-100 mt-1">
              PLEASE PROCEED TO {calledToken.room.toUpperCase()}
            </div>
            <div className="text-xs text-white/80 mt-0.5">
              {calledToken.service}
            </div>
          </div>
        )}

        {/* 2-Column Split: NOW SERVING vs NEXT IN LINE */}
        <div className="grid grid-cols-1 lg:grid-cols-12 gap-6">
          {/* ------------------------------------------------------------- */}
          {/* COLUMN 1: NOW EXAMINING / SERVING (7 Columns)                 */}
          {/* ------------------------------------------------------------- */}
          <div className="lg:col-span-7 space-y-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center space-x-2.5">
                <span className="relative flex h-3.5 w-3.5">
                  <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                  <span className="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                </span>
                <h2 className="text-lg md:text-xl font-black text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">
                  NOW EXAMINING / SERVING
                </h2>
              </div>
              <span className="text-xs font-mono font-bold opacity-60">
                {nowServing.length + preparing.length} Active Suites
              </span>
            </div>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {[...nowServing, ...preparing].map((apt) => {
                const isScanning = apt.workflowState === 'in_progress';
                return (
                  <div
                    key={apt.id}
                    className={`p-5 rounded-2xl border-2 transition-all flex flex-col justify-between relative overflow-hidden ${
                      isScanning
                        ? isDarkMode
                          ? 'bg-emerald-950/40 border-emerald-500/80 shadow-lg shadow-emerald-950/50'
                          : 'bg-emerald-50/70 border-emerald-400 shadow-md'
                        : isDarkMode
                        ? 'bg-sky-950/40 border-sky-500/80 shadow-lg shadow-sky-950/50'
                        : 'bg-sky-50/70 border-sky-300 shadow-md'
                    }`}
                  >
                    {/* Header: Room Name & Modality Badge */}
                    <div>
                      <div className="flex items-center justify-between">
                        <span className="text-xs font-black uppercase tracking-wider text-slate-800 dark:text-slate-200">
                          {apt.roomNumber}
                        </span>
                        <span
                          className="text-[10px] font-black px-2.5 py-0.5 rounded text-white font-mono shadow-xs"
                          style={{ backgroundColor: apt.modality.color }}
                        >
                          {apt.modality.code}
                        </span>
                      </div>

                      {/* Token Display (Large Bold) */}
                      <div className="my-3 flex items-baseline justify-between">
                        <div className="text-4xl md:text-5xl font-black font-mono tracking-wider text-slate-900 dark:text-white">
                          {apt.tokenNumber}
                        </div>
                        {apt.priority === 'stat' && (
                          <span className="text-xs bg-rose-600 text-white font-black px-2 py-0.5 rounded-md animate-pulse flex items-center gap-1 shadow-xs">
                            <Flame className="w-3 h-3" /> STAT
                          </span>
                        )}
                      </div>

                      <div className="text-xs font-bold truncate opacity-90">{apt.service.name}</div>
                      <div className="text-[11px] opacity-60 truncate mt-0.5">Patient: {apt.patient.name}</div>
                    </div>

                    {/* Footer: State & Recall Button */}
                    <div className="mt-4 pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between">
                      <span
                        className={`text-xs font-black uppercase flex items-center gap-1.5 ${
                          isScanning ? 'text-emerald-600 dark:text-emerald-400' : 'text-sky-600 dark:text-sky-400'
                        }`}
                      >
                        <span className="w-2 h-2 rounded-full bg-current animate-pulse" />
                        {isScanning ? 'Inside Scanner' : 'Patient Prep'}
                      </span>

                      <button
                        onClick={() => triggerChime(apt.tokenNumber, apt.roomNumber, apt.service.name, apt.modality.code)}
                        title="Re-Chime on TV with Voice Announcement"
                        className="px-2.5 py-1 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-100 dark:hover:bg-slate-700 text-cyan-700 dark:text-cyan-300 border border-slate-300 dark:border-slate-700 text-xs font-bold cursor-pointer shadow-xs flex items-center gap-1"
                      >
                        <Volume2 className="w-3.5 h-3.5" />
                        <span>Call</span>
                      </button>
                    </div>
                  </div>
                );
              })}

              {nowServing.length === 0 && preparing.length === 0 && (
                <div className="col-span-2 p-10 rounded-2xl bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 text-center text-slate-500 dark:text-slate-400">
                  <Activity className="w-10 h-10 mx-auto mb-2 opacity-40 text-cyan-500" />
                  <span className="text-sm font-bold block">All Examination Suites Available</span>
                  <span className="text-xs opacity-75 mt-0.5 block">Next waiting patients are being summoned to the preparation suites.</span>
                </div>
              )}
            </div>
          </div>

          {/* ------------------------------------------------------------- */}
          {/* COLUMN 2: NEXT IN LINE / SUB-WAITING QUEUE (5 Columns)        */}
          {/* ------------------------------------------------------------- */}
          <div className="lg:col-span-5 space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-lg md:text-xl font-black text-cyan-700 dark:text-cyan-400 uppercase tracking-wider">
                NEXT IN LINE (QUEUE)
              </h2>
              <span className="text-xs font-mono font-bold opacity-60">
                {upNext.length} Patients Waiting
              </span>
            </div>

            <div className={`rounded-2xl border p-3.5 space-y-2.5 ${
              isDarkMode ? 'bg-slate-900/60 border-slate-800' : 'bg-slate-50 border-slate-200'
            }`}>
              {upNext.length === 0 ? (
                <div className="py-12 text-center text-slate-400 dark:text-slate-500 text-xs font-medium space-y-1">
                  <CheckCircle2 className="w-8 h-8 mx-auto opacity-40 text-emerald-500" />
                  <p className="font-bold text-slate-600 dark:text-slate-400">Queue is clear</p>
                  <p>No patients currently waiting in sub-queue for selected modalities.</p>
                </div>
              ) : (
                upNext.map((apt, idx) => (
                  <div
                    key={apt.id}
                    className={`p-3 rounded-xl border flex items-center justify-between transition-all ${
                      isDarkMode
                        ? 'bg-slate-900 border-slate-800 hover:border-slate-700'
                        : 'bg-white border-slate-200 hover:border-cyan-300 shadow-xs'
                    }`}
                  >
                    <div className="flex items-center space-x-3">
                      <span className="w-7 h-7 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-mono font-bold text-xs flex items-center justify-center border border-slate-200 dark:border-slate-700">
                        {idx + 1}
                      </span>
                      <div>
                        <div className="flex items-center space-x-2">
                          <span className="font-mono font-black text-base md:text-lg text-cyan-700 dark:text-cyan-300">
                            {apt.tokenNumber}
                          </span>
                          <span
                            className="text-[9px] font-bold px-1.5 py-0.2 rounded text-white font-mono shadow-xs"
                            style={{ backgroundColor: apt.modality.color }}
                          >
                            {apt.modality.code}
                          </span>
                          {apt.priority === 'stat' && (
                            <span className="text-[9px] bg-rose-600 text-white font-black px-1.5 rounded animate-pulse">
                              STAT
                            </span>
                          )}
                        </div>
                        <div className="text-xs font-semibold opacity-90 truncate max-w-[180px]">
                          {apt.service.name}
                        </div>
                      </div>
                    </div>

                    <div className="text-right">
                      <span className="text-xs font-bold opacity-80 block truncate max-w-[130px]">
                        {apt.roomNumber}
                      </span>
                      <button
                        onClick={() => triggerChime(apt.tokenNumber, apt.roomNumber, apt.service.name, apt.modality.code)}
                        className="text-xs text-cyan-600 hover:text-cyan-500 dark:text-cyan-400 font-black uppercase flex items-center gap-1 mt-1 cursor-pointer ml-auto bg-cyan-50 dark:bg-cyan-950/60 px-2 py-0.5 rounded border border-cyan-200 dark:border-cyan-800"
                      >
                        <Volume2 className="w-3 h-3" /> Call
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>

            {/* Recent Call Log Strip */}
            <div className={`p-3 rounded-xl border text-xs space-y-1.5 ${
              isDarkMode ? 'bg-slate-900/30 border-slate-800' : 'bg-slate-100/60 border-slate-200'
            }`}>
              <div className="flex items-center justify-between text-[11px] font-bold opacity-75">
                <span className="flex items-center gap-1">
                  <RotateCcw className="w-3 h-3" /> Recent Calls Handover:
                </span>
                <span className="font-mono text-[10px]">Auto-Synced</span>
              </div>
              <div className="flex flex-wrap items-center gap-2 pt-1">
                {callHistory.map((item, i) => (
                  <span
                    key={i}
                    className="px-2 py-0.5 rounded bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 font-mono text-[10px] font-bold text-slate-700 dark:text-slate-300"
                  >
                    #{item.token} • {item.time}
                  </span>
                ))}
              </div>
            </div>
          </div>
        </div>

        {/* Animated Scrolling Bottom News Ticker */}
        <div className={`p-3.5 rounded-2xl border flex items-center justify-between text-xs transition-all ${
          isDarkMode ? 'bg-slate-900 border-slate-800 text-slate-300' : 'bg-slate-50 border-slate-200 text-slate-700'
        }`}>
          <div className="flex items-center space-x-3 overflow-hidden flex-1">
            <span className="bg-cyan-600 text-white font-black text-[10px] px-2 py-0.5 rounded uppercase tracking-wider whitespace-nowrap flex items-center gap-1 shadow-xs">
              <Sparkles className="w-3 h-3" /> ADC BROADCAST
            </span>
            <div className="truncate font-medium animate-in fade-in duration-300 key={tickerIndex}">
              {tickerMessages[tickerIndex]}
            </div>
          </div>

          <div className="hidden sm:flex items-center space-x-2 text-cyan-600 dark:text-cyan-400 font-bold uppercase text-[11px] pl-4 border-l border-slate-300 dark:border-slate-700 whitespace-nowrap">
            <ShieldCheck className="w-3.5 h-3.5" />
            <span>Islamabad Radiology Hub</span>
          </div>
        </div>
      </div>
    </div>
  );
};
