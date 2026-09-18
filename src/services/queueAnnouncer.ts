/**
 * Live Queue TV audio: tri-tone WebAudio chime + Web Speech announcement
 * (English / romanized Urdu / bilingual). Shared by the waiting-room kiosk
 * and the staff console so both surfaces announce identically.
 *
 * Browsers block audio until a user gesture has occurred on the page — the
 * kiosk unlocks on its one-tap "Enable sound" button before arming.
 */

export type AnnouncementLanguage = 'bilingual' | 'english' | 'urdu';

/** Monotonic sequence: a newer call invalidates pending utterances of the previous one. */
let announceSeq = 0;

/** Tri-tone harmonic chime (F5 → A5 → C6) via WebAudio. */
export function playChime(): void {
  try {
    const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
    if (!AudioCtx) return;
    const ctx = new AudioCtx();
    const t0 = ctx.currentTime;
    const notes: Array<{ freq: number; start: number; decay: number; gain: number }> = [
      { freq: 698.46, start: 0, decay: 0.45, gain: 0.25 },   // F5
      { freq: 880.0, start: 0.15, decay: 0.65, gain: 0.3 },  // A5
      { freq: 1046.5, start: 0.35, decay: 1.2, gain: 0.35 }, // C6
    ];
    for (const { freq, start, decay, gain } of notes) {
      const osc = ctx.createOscillator();
      const g = ctx.createGain();
      osc.type = 'sine';
      osc.frequency.setValueAtTime(freq, t0 + start);
      g.gain.setValueAtTime(gain, t0 + start);
      g.gain.exponentialRampToValueAtTime(0.001, t0 + decay);
      osc.connect(g);
      g.connect(ctx.destination);
      osc.start(t0 + start);
      osc.stop(t0 + decay);
    }
    // Release the AudioContext once the chime has fully decayed.
    window.setTimeout(() => void ctx.close().catch(() => undefined), 1600);
  } catch {
    // Audio unavailable — the visual banner still announces the call.
  }
}

function speak(text: string, lang: string, rate: number, pitch: number): void {
  if (!('speechSynthesis' in window)) return;
  const utterance = new SpeechSynthesisUtterance(text);
  utterance.lang = lang;
  utterance.rate = rate;
  utterance.pitch = pitch;
  window.speechSynthesis.speak(utterance);
}

/** Announce one called token. Room text is stripped of parentheticals for speech. */
export function announceCall(opts: {
  token: string;
  roomName: string;
  language: AnnouncementLanguage;
}): void {
  if (!('speechSynthesis' in window)) return;

  const seq = ++announceSeq;
  const room = (opts.roomName || 'the examination area').replace(/\([^)]*\)/g, '').trim();

  window.speechSynthesis.cancel();

  if (opts.language !== 'urdu') {
    window.setTimeout(() => {
      if (seq !== announceSeq) return;
      speak(`Token number ${opts.token}. Please proceed to ${room}.`, 'en-US', 0.92, 1.05);
    }, 500);
  }

  if (opts.language !== 'english') {
    // Romanized Urdu: renders on every OS even without an Urdu voice pack.
    const urdu = `Tawajjah farmayiye. Token number ${opts.token}. Baraye meherbani ${room} mein tashreef layiye.`;
    window.setTimeout(() => {
      if (seq !== announceSeq) return;
      speak(urdu, 'ur-PK', 0.88, 1.0);
    }, opts.language === 'bilingual' ? 3400 : 500);
  }
}

/** One-time user-gesture unlock so later programmatic audio is allowed. */
export function unlockAudio(): void {
  try {
    const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
    if (AudioCtx) {
      const ctx = new AudioCtx();
      void ctx.resume().catch(() => undefined);
      window.setTimeout(() => void ctx.close().catch(() => undefined), 200);
    }
    if ('speechSynthesis' in window) {
      const warmup = new SpeechSynthesisUtterance(' ');
      warmup.volume = 0;
      window.speechSynthesis.speak(warmup);
    }
  } catch {
    // Unlock is best-effort; individual announcers fail soft.
  }
}
