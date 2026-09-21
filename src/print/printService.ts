import { http } from '../services/api';
import type {
  PrintArtifact,
  PrintCalibrationEntry,
  PrintDocumentModel,
  PrintPaper,
  PrintRegistryPayload,
  PrintSettingsModel,
} from './types';

/**
 * Print API client.
 *
 * One payload endpoint and one PDF endpoint for every printable document, so a
 * new printable screen never invents its own data path (and never re-fetches an
 * invoice from three endpoints to print it).
 */

const DEVICE_STORAGE_KEY = 'polytronx.print.device';

/**
 * This workstation's stable identity.
 *
 * The thermal safe width and feed belong to the PRINTER, not the user and not the
 * tenant: two counters on the same clinic can genuinely need 58 mm and 72 mm. A
 * generated id in localStorage keys the calibration on the server, so a reload or
 * another shift at the same desk keeps its dimensions.
 */
export function printDeviceId(): string {
  try {
    const existing = window.localStorage.getItem(DEVICE_STORAGE_KEY);
    if (existing && existing.trim() !== '') {
      return existing;
    }

    const generated = `ws-${Math.random().toString(36).slice(2, 10)}${Date.now().toString(36).slice(-4)}`;
    window.localStorage.setItem(DEVICE_STORAGE_KEY, generated);

    return generated;
  } catch {
    // Private mode / storage disabled: fall back to a volatile id rather than
    // failing a print.
    return 'ws-ephemeral';
  }
}

export function printDeviceLabel(deviceId: string): string {
  try {
    return window.localStorage.getItem(`${DEVICE_STORAGE_KEY}.label`) || deviceId;
  } catch {
    return deviceId;
  }
}

export interface PrintRequestOptions {
  paper?: PrintPaper;
  reprint?: boolean;
  /** Resolve the id as a STUDY and return a draft-report scaffold. */
  draft?: boolean;
  period?: string;
  countedCash?: number;
  supervisor?: string;
  cashier?: string;
  shiftName?: string;
  notes?: string;
}

function requestParams(options: PrintRequestOptions, includeDevice = true): Record<string, string> {
  const params: Record<string, string> = {};
  if (options.paper) params.paper = options.paper;
  if (options.reprint) params.reprint = '1';
  if (options.draft) params.draft = '1';
  if (options.period) params.period = options.period;
  if (options.countedCash !== undefined) params.countedCash = String(options.countedCash);
  if (options.supervisor) params.supervisor = options.supervisor;
  if (options.cashier) params.cashier = options.cashier;
  if (options.shiftName) params.shiftName = options.shiftName;
  if (options.notes) params.notes = options.notes;
  if (includeDevice) params.device = printDeviceId();

  return params;
}

/** The canonical document model for one artifact. */
export async function fetchPrintDocument(
  artifact: PrintArtifact,
  id: string | number,
  options: PrintRequestOptions = {},
): Promise<PrintDocumentModel> {
  const { data } = await http.get(`/print/${artifact}/${id}`, { params: requestParams(options) });

  return data.data.document as PrintDocumentModel;
}

/** URL of the rendered PDF of the same document. */
export function printPdfUrl(artifact: PrintArtifact, id: string | number, options: PrintRequestOptions = {}): string {
  const params = new URLSearchParams({ ...requestParams(options, true), download: '1' });

  return `/api/v1/print/${artifact}/${id}/pdf?${params.toString()}`;
}

export async function fetchPrintRegistry(): Promise<PrintRegistryPayload> {
  const { data } = await http.get('/print/registry');

  return data.data as PrintRegistryPayload;
}

/** Browser printing is invisible to the server: report it for the audit trail. */
export async function recordPrintEvent(
  artifact: PrintArtifact,
  id: string | number,
  event: 'printed' | 'reprinted' | 'pdf',
  paper?: PrintPaper,
): Promise<void> {
  try {
    await http.post(`/print/${artifact}/${id}/events`, {
      event,
      paper,
      device: printDeviceId(),
    });
  } catch {
    // Never surface an audit failure to the operator: the paper is already out.
  }
}

export async function fetchPrintSettings(): Promise<{
  settings: PrintSettingsModel;
  artifacts: PrintRegistryPayload['artifacts'];
  paperProfiles: PrintRegistryPayload['paperProfiles'];
  marginPresets: string[];
  calibration: PrintSettingsModel['calibration'][string] | null;
}> {
  const { data } = await http.get('/settings/printing', { params: { device: printDeviceId() } });

  return data.data;
}

export async function savePrintSettings(
  // `calibration` is replaced rather than narrowed: a workstation sends only the
  // printer it is standing at, and the server merges it into the tenant map.
  settings: Partial<Omit<PrintSettingsModel, 'calibration'>> & {
    calibration?: Record<string, Partial<PrintCalibrationEntry>>;
  },
): Promise<PrintSettingsModel> {
  const { data } = await http.put('/settings/printing', {
    ...settings,
    device: printDeviceId(),
  });

  return data.data.settings as PrintSettingsModel;
}
