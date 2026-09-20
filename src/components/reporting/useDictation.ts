import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import * as api from '../../services/apiService';
import { DictationCapability } from '../../types';

/**
 * Browser speech recognition for radiology dictation.
 *
 * Standards-based feature detection (`SpeechRecognition` / the Chromium
 * prefixed `webkitSpeechRecognition`) — there is no "Chrome API" or "Edge
 * API"; both browsers implement the same Web Speech interface, and Firefox
 * (commonly) does not. When it is unavailable the reporting editor keeps
 * working and merely explains why the microphone is off.
 *
 * Privacy: browser speech recognition is NOT guaranteed to run locally. In
 * Chromium implementations the audio may be sent to the vendor's speech
 * service, so dictation is presented as an optional input method — a manual
 * typing fallback always remains, and nothing here is ever auto-finalized.
 */

export interface DictationState {
  /**
   * Dictation can be used right now: either this browser has a speech
   * implementation, or the clinic runs its own engine (which needs no browser
   * speech support at all, only a microphone and MediaRecorder).
   */
  supported: boolean;
  listening: boolean;
  /** Interim (not yet final) hypothesis, shown but never stored verbatim. */
  interim: string;
  error: string | null;
  language: string;
  /** Which engine is actually in use for this session. */
  provider: 'browser' | 'server' | 'none';
  /** True while a recorded chunk is being transcribed by the clinic's engine. */
  transcribing: boolean;
}

export interface UseDictationResult extends DictationState {
  toggle: () => void;
  start: () => void;
  stop: () => void;
  setLanguage: (language: string) => void;
  /** Where the next transcript will land, for the UI to display honestly. */
  targetLabel: string | null;
  /** BCP-47 tags offered for dictation. */
  languages: Array<{ value: string; label: string }>;
}

const LANGUAGES = [
  { value: 'en-US', label: 'English (US)' },
  { value: 'en-GB', label: 'English (UK)' },
  { value: 'en-IN', label: 'English (India)' },
  { value: 'ur-PK', label: 'Urdu (Pakistan)' },
  { value: 'ar-SA', label: 'Arabic' },
];

function speechRecognitionCtor(): any | null {
  if (typeof window === 'undefined') return null;
  const w = window as any;
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
}

/**
 * Recording a chunk for the clinic's own engine.
 *
 * Chunks are cut into short segments and sent as they close, so a long "start
 * dictation … stop dictation" session keeps producing text instead of waiting
 * for one huge upload at the end — and so a failed segment costs a phrase, not
 * the whole dictation.
 */
const SERVER_CHUNK_MS = 12000;

function mediaRecorderSupported(): boolean {
  if (typeof window === 'undefined') return false;

  return typeof (window as any).MediaRecorder === 'function'
    && Boolean(navigator.mediaDevices?.getUserMedia);
}

/**
 * The audio container to record in, in order of preference.
 *
 * Not every browser records the same format: Chromium and Firefox produce WebM
 * (Opus), Safari produces MP4/AAC and cannot produce WebM at all. Naming the
 * container is what lets a self-hosted engine demux the recording, so the
 * browser is asked for the first format it actually supports rather than being
 * left to pick one silently.
 */
const RECORDING_FORMATS = [
  'audio/webm;codecs=opus',
  'audio/webm',
  'audio/ogg;codecs=opus',
  'audio/mp4',
] as const;

function preferredRecordingFormat(): string | null {
  if (typeof window === 'undefined') return null;

  const recorder = (window as any).MediaRecorder;
  if (typeof recorder?.isTypeSupported !== 'function') return null;

  return RECORDING_FORMATS.find(format => {
    try {
      return recorder.isTypeSupported(format);
    } catch {
      return false;
    }
  }) ?? null;
}

/**
 * Spoken punctuation / structure commands.
 *
 * Deliberately conservative: only a small closed set of unambiguous phrases,
 * matched on whole words. Medical vocabulary is never interpreted as a
 * command, because a misheard drug or anatomy term turned into a control
 * instruction would corrupt the clinical text.
 */
const COMMANDS: Array<[RegExp, string]> = [
  [/\bnew paragraph\b/gi, '\n\n'],
  [/\bnew line\b/gi, '\n'],
  [/\bfull stop\b/gi, '.'],
  [/\bperiod\b/gi, '.'],
  [/\bcomma\b/gi, ','],
  [/\bsemicolon\b/gi, ';'],
  [/\bcolon\b/gi, ':'],
  [/\bquestion mark\b/gi, '?'],
  [/\bopen bracket\b/gi, '('],
  [/\bclose bracket\b/gi, ')'],
];

export function applyDictationCommands(text: string): string {
  let output = text;
  for (const [pattern, replacement] of COMMANDS) {
    output = output.replace(pattern, replacement);
  }
  return output;
}

export function useDictation(options: {
  /** Receives FINAL transcript chunks; the caller inserts them at the cursor. */
  onInsert: (text: string) => void;
  /** Human label of the field that will receive dictation. */
  targetLabel: string | null;
  /** The radiologist's saved language (the account is the source of truth). */
  language: string;
  onLanguageChange: (language: string) => void;
  /** The clinic's own engine, when one is configured. */
  serverProvider?: DictationCapability['serverProvider'] | null;
  /** The provider the radiologist chose, when that choice is available. */
  provider?: 'browser' | 'server';
  /** The study being dictated into, so the audit trail can name it. */
  appointmentId?: string | null;
}): UseDictationResult {
  const serverAvailable = Boolean(options.serverProvider?.available) && mediaRecorderSupported();
  const browserAvailable = useMemo(() => speechRecognitionCtor() !== null, []);

  // Honour the saved choice, but never claim to use an engine that is not
  // there: a clinic preference for server dictation on a machine with no
  // self-hosted engine configured falls back to the browser rather than
  // failing at the microphone.
  const provider: DictationState['provider'] =
    options.provider === 'server' && serverAvailable
      ? 'server'
      : browserAvailable
        ? 'browser'
        : serverAvailable
          ? 'server'
          : 'none';

  const supported = provider !== 'none';
  const [listening, setListening] = useState(false);
  const [interim, setInterim] = useState('');
  const [transcribing, setTranscribing] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const language = options.language;
  const { onLanguageChange } = options;

  const recognitionRef = useRef<any>(null);
  const recorderRef = useRef<MediaRecorder | null>(null);
  const streamRef = useRef<MediaStream | null>(null);
  const chunkTimerRef = useRef<number | null>(null);
  const languageRef = useRef(language);
  languageRef.current = language;
  const appointmentRef = useRef(options.appointmentId ?? null);
  appointmentRef.current = options.appointmentId ?? null;
  const providerRef = useRef(provider);
  providerRef.current = provider;

  /** Hold back 'no speech' noise: an empty chunk is normal, not an error. */
  const sendChunk = useCallback(async (audio: Blob) => {
    if (audio.size === 0) return;

    setTranscribing(true);
    try {
      const result = await api.transcribeDictation(audio, languageRef.current, appointmentRef.current);
      const cleaned = applyDictationCommands(result.text.trim());
      if (cleaned !== '') {
        insertRef.current(cleaned);
        setError(null);
      }
    } catch (err: any) {
      setError(
        err?.status === 409
          ? 'This clinic has no self-hosted dictation service. Switch to browser dictation in preferences.'
          : err?.message ?? 'The dictation service could not transcribe that phrase. Type it instead.'
      );
    } finally {
      setTranscribing(false);
    }
  }, []);
  // Kept in a ref so the long-lived recognition handlers always call the
  // CURRENT insert callback (the target field can change between phrases).
  const insertRef = useRef(options.onInsert);
  insertRef.current = options.onInsert;

  // The chosen language is stored on the radiologist's account, so it follows
  // them between workstations; the recognition session is told immediately.
  const setLanguage = useCallback((next: string) => {
    onLanguageChange(next);

    if (recognitionRef.current) {
      try {
        recognitionRef.current.lang = next;
      } catch {
        /* the next session picks the language up */
      }
    }
  }, [onLanguageChange]);

  const stopRecorder = useCallback(() => {
    if (chunkTimerRef.current !== null) {
      window.clearInterval(chunkTimerRef.current);
      chunkTimerRef.current = null;
    }

    try {
      recorderRef.current?.state !== 'inactive' && recorderRef.current?.stop();
    } catch {
      /* already stopped */
    }
    recorderRef.current = null;

    // Release the microphone: leaving a track live keeps the browser's
    // recording indicator on, which looks like the app is still listening.
    streamRef.current?.getTracks().forEach(track => track.stop());
    streamRef.current = null;
  }, []);

  const stop = useCallback(() => {
    try {
      recognitionRef.current?.stop();
    } catch {
      /* already stopped */
    }
    recognitionRef.current = null;
    stopRecorder();
    setListening(false);
    setInterim('');
  }, [stopRecorder]);

  /** Server dictation: record locally, transcribe in the clinic's network. */
  const startServer = useCallback(async () => {
    if (!options.targetLabel) {
      setError('Click into a report field first — dictation is inserted into the field you are editing.');
      return;
    }

    setError(null);

    let stream: MediaStream;
    try {
      stream = await navigator.mediaDevices.getUserMedia({ audio: true });
    } catch {
      setError('Microphone permission was denied. Allow it in the browser, or type instead.');
      return;
    }

    streamRef.current = stream;

    // Ask for a named container where the browser allows it, so the engine on
    // the other end is told what it is actually being handed.
    const format = preferredRecordingFormat();
    const recorder = format
      ? new (window as any).MediaRecorder(stream, { mimeType: format })
      : new (window as any).MediaRecorder(stream);
    recorderRef.current = recorder;

    recorder.ondataavailable = (event: any) => {
      if (event.data && event.data.size > 0) void sendChunk(event.data);
    };

    recorder.onerror = () => {
      setError('Recording failed. Your text is unchanged — try again or type.');
      stop();
    };

    try {
      recorder.start();
    } catch {
      setError('Could not start recording. Try again, or type the report.');
      stopRecorder();
      return;
    }

    setListening(true);

    // A long session is transcribed in short phrases rather than one upload.
    chunkTimerRef.current = window.setInterval(() => {
      try {
        if (recorder.state === 'recording') {
          recorder.requestData();
        }
      } catch {
        /* the next tick will try again */
      }
    }, SERVER_CHUNK_MS);
  }, [options.targetLabel, sendChunk, stop, stopRecorder]);

  const start = useCallback(() => {
    if (providerRef.current === 'server') {
      void startServer();
      return;
    }

    const Recognition = speechRecognitionCtor();

    if (!Recognition) {
      setError('This browser does not support speech recognition. Type the report instead.');
      return;
    }

    if (!options.targetLabel) {
      setError('Click into a report field first — dictation is inserted into the field you are editing.');
      return;
    }

    setError(null);

    const recognition = new Recognition();
    recognition.lang = language;
    recognition.continuous = true;
    recognition.interimResults = true;

    recognition.onresult = (event: any) => {
      let pending = '';

      for (let i = event.resultIndex; i < event.results.length; i += 1) {
        const result = event.results[i];
        const transcript = result[0]?.transcript ?? '';

        if (result.isFinal) {
          const cleaned = applyDictationCommands(transcript.trim());
          if (cleaned !== '') insertRef.current(cleaned);
        } else {
          pending += transcript;
        }
      }

      setInterim(pending);
    };

    recognition.onerror = (event: any) => {
      const code = event?.error;

      setError(
        code === 'not-allowed' || code === 'service-not-allowed'
          ? 'Microphone permission was denied. Allow it in the browser, or type instead.'
          : code === 'no-speech'
            ? 'No speech detected — check the microphone and try again.'
            : code === 'network'
              ? 'The speech service is unreachable. Dictation may depend on a vendor service; type instead.'
              : 'Dictation stopped unexpectedly. Your text is unchanged.'
      );

      setListening(false);
      setInterim('');
    };

    recognition.onend = () => {
      setListening(false);
      setInterim('');
    };

    recognitionRef.current = recognition;

    try {
      recognition.start();
      setListening(true);
    } catch {
      setError('Could not start dictation. Try again, or type the report.');
      setListening(false);
    }
  }, [language, options.targetLabel, startServer]);

  const toggle = useCallback(() => {
    if (listening) {
      stop();
    } else {
      start();
    }
  }, [listening, start, stop]);

  // Never leave the microphone capturing after the workspace unmounts.
  useEffect(() => () => {
    try {
      recognitionRef.current?.abort?.();
    } catch {
      /* nothing to abort */
    }

    if (chunkTimerRef.current !== null) window.clearInterval(chunkTimerRef.current);
    try {
      recorderRef.current?.state !== 'inactive' && recorderRef.current?.stop();
    } catch {
      /* nothing to stop */
    }
    streamRef.current?.getTracks().forEach(track => track.stop());
  }, []);

  return {
    supported,
    listening,
    interim,
    error,
    language,
    provider,
    transcribing,
    targetLabel: options.targetLabel,
    languages: LANGUAGES,
    toggle,
    start,
    stop,
    setLanguage,
  };
}
