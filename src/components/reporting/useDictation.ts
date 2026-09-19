import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

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
  /** A speech-recognition implementation exists in this browser. */
  supported: boolean;
  listening: boolean;
  /** Interim (not yet final) hypothesis, shown but never stored verbatim. */
  interim: string;
  error: string | null;
  language: string;
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

const LANGUAGE_KEY = 'polytronx_ris_dictation_language';

function speechRecognitionCtor(): any | null {
  if (typeof window === 'undefined') return null;
  const w = window as any;
  return w.SpeechRecognition ?? w.webkitSpeechRecognition ?? null;
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
}): UseDictationResult {
  const supported = useMemo(() => speechRecognitionCtor() !== null, []);
  const [listening, setListening] = useState(false);
  const [interim, setInterim] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [language, setLanguageState] = useState<string>(
    () => localStorage.getItem(LANGUAGE_KEY) ?? 'en-US'
  );

  const recognitionRef = useRef<any>(null);
  // Kept in a ref so the long-lived recognition handlers always call the
  // CURRENT insert callback (the target field can change between phrases).
  const insertRef = useRef(options.onInsert);
  insertRef.current = options.onInsert;

  const setLanguage = useCallback((next: string) => {
    setLanguageState(next);
    localStorage.setItem(LANGUAGE_KEY, next);

    if (recognitionRef.current) {
      try {
        recognitionRef.current.lang = next;
      } catch {
        /* the next session picks the language up */
      }
    }
  }, []);

  const stop = useCallback(() => {
    try {
      recognitionRef.current?.stop();
    } catch {
      /* already stopped */
    }
    recognitionRef.current = null;
    setListening(false);
    setInterim('');
  }, []);

  const start = useCallback(() => {
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
  }, [language, options.targetLabel]);

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
  }, []);

  return {
    supported,
    listening,
    interim,
    error,
    language,
    targetLabel: options.targetLabel,
    languages: LANGUAGES,
    toggle,
    start,
    stop,
    setLanguage,
  };
}
