import { http } from './api';
import {
  AccessRoleRecord,
  ActiveTab,
  AdverseReactionReport,
  AppNotification,
  Appointment,
  AuditLogEntry,
  CriticalFindingLog,
  DictationCapability,
  PriorExam,
  Priority,
  RadiologistSummary,
  ReportMacro,
  ReportSearchRow,
  ReportStatusFilter,
  ReportingPreferences,
  ReportingSavedView,
  ReportingTab,
  ReportingViewFilters,
  ReportingWorklistResult,
  StructuredValues,
  StudyState,
  TemplateMatch,
  WorklistStudy,
  ClinicProfileSettings,
  DicomNodeConfig,
  EffectiveAccess,
  DoctorDispatchLog,
  DoseLog,
  Entitlements,
  EntitlementReconciliation,
  FailedJobRetryResult,
  FailedJobsPayload,
  InfrastructureSummary,
  InventoryItem,
  InventoryTransaction,
  Invoice,
  InvoiceItem,
  InvoicePayment,
  Modality,
  Patient,
  PaymentMethod,
  PermissionGroup,
  Plan,
  PlatformAuditEntry,
  PlatformOverviewStats,
  PlatformRole,
  PlatformSupportSessionRecord,
  PlatformUserRecord,
  RadiologyReport,
  Referrer,
  ReportTemplate,
  Room,
  ShiftReview,
  ShiftSummary,
  CatalogReview,
  ScreeningForm,
  ScreeningTriage,
  Service,
  StaffRole,
  StaffUser,
  StudyScreeningAnswer,
  SupportSessionInfo,
  Tenant360,
  TenantBranding,
  TenantBrandingView,
  TenantBrandingPayload,
  TenantDomainRecord,
  TenantIntegrationsPayload,
  TenantFacilityRecord,
  TenantMembership,
  TenantRecord,
  TenantUserRecord,
  PublicTenantContext,
  PlatformOperationsPayload,
  QueueDisplay,
  QueueDisplayEntry,
  QueueDisplaySettings,
  UsageSummary,
} from '../types';

/**
 * Typed client for the PolytronX - Enterprise PACS & RIS API. Every function returns the same
 * TypeScript models the UI already consumes (formerly hydrated from
 * localStorage); nulls from the server are normalized here so views keep
 * their null-safe rendering paths.
 */

type Nullable<T> = T | null;

export interface SessionUser extends StaffUser {
  businessId: number;
  businessName: string;
  /** White-label brand of the active tenant (null = account name is the brand). */
  businessBrandName?: string | null;
  subscriptionStatus: string;
  isPlatformAdmin: boolean;
  platformRole?: PlatformRole | null;
  memberships: TenantMembership[];
  supportSession?: SupportSessionInfo | null;
  /** Server-issued tenant permission names — UI gating only; the server
   *  remains the only enforcement point (mirrors TenantAuthorizer). */
  permissions: string[];
  /** Bumped server-side on every RBAC mutation; the SPA polls /me and
   *  re-hydrates when this counter moves (permission auto-refresh). */
  permissionsVersion: number;
}

export interface BootstrapPayload {
  user: SessionUser;
  platform?: boolean;
  entitlements?: Entitlements | null;
  branding?: TenantBranding | null;
  studies: Appointment[];
  invoices: Invoice[];
  patients: Patient[];
  modalities: Modality[];
  rooms: Room[];
  services: Service[];
  paymentMethods: PaymentMethod[];
  referrers: Referrer[];
  screeningForms: ScreeningForm[];
  templates: ReportTemplate[];
  staff: StaffUser[];
  /** Every role this clinic can assign, including tenant-created custom ones. */
  roles?: AccessRoleRecord[];
  clinicSettings: ClinicProfileSettings;
  dicomNodes: DicomNodeConfig[];
  notificationTemplates: NotificationTemplateT[];
  auditLogs: AuditLogEntry[];
  dispatches: DoctorDispatchLog[];
  notifications: AppNotification[];
  inventoryItems: InventoryItem[];
  inventoryTransactions: InventoryTransaction[];
  adverseReactions: AdverseReactionReport[];
}

export type NotificationTemplateT = {
  id: string;
  name: string;
  category: 'booking' | 'checkin' | 'ready' | 'critical' | 'doctor';
  channel: 'sms' | 'whatsapp' | 'email';
  subject?: string;
  templateBody: string;
  enabled: boolean;
};

const FALLBACK_PATIENT: Patient = {
  id: '0',
  mrn: '',
  name: 'Guest',
  email: '',
  phone: '',
  gender: 'other',
  dob: '',
  age: 0,
  bloodGroup: '',
};

function normalizeStudy(raw: any): Appointment {
  return {
    ...raw,
    patient: raw.patient ?? FALLBACK_PATIENT,
    service: raw.service ?? { id: 0, name: 'Unknown study', code: '', modalityId: 0, price: 0, durationMinutes: 0, preparationInstructions: '', requiresScreening: false, requiresContrast: false },
    modality: raw.modality ?? { id: 0, name: 'Unknown', code: 'DX', color: '#64748b', bufferMinutes: 0, isActive: true },
    screeningAnswers: raw.screeningAnswers ?? [],
  };
}

function normalizeInvoice(raw: any): Invoice {
  return {
    ...raw,
    patient: raw.patient ?? FALLBACK_PATIENT,
    items: raw.items ?? [],
    payments: raw.payments ?? [],
  };
}

/**
 * The API serializes master-data ids as strings while the SPA contract types
 * them as numbers. Coerce at the client boundary — otherwise `find(s => s.id
 * === selectedId)` silently misses (5 !== "5") and dependent UI (e.g. the
 * booking financial section) fails to render.
 */
const normalizeModality = (m: any): Modality => ({ ...m, id: Number(m.id) });
const normalizeRoom = (r: any): Room => ({
  ...r,
  id: Number(r.id),
  modalityId: Number(r.modalityId),
  locationId: r.locationId == null ? null : Number(r.locationId),
});
const normalizeService = (s: any): Service => ({ ...s, id: Number(s.id), modalityId: Number(s.modalityId), isBookableOnline: s.isBookableOnline ?? true });
const normalizePaymentMethod = (m: any): PaymentMethod => ({
  ...m,
  id: Number(m.id),
  kind: m.kind ?? 'other',
});
const normalizeReferrer = (r: any): Referrer => ({ ...r, id: Number(r.id), isActive: r.isActive ?? true });
const normalizeScreeningForm = (f: any): ScreeningForm => ({ ...f, isActive: f.isActive ?? true });

// ==================== auth ====================

export type LoginResult =
  | { kind: 'authenticated'; user: SessionUser }
  | { kind: 'two_factor_required'; email: string };

export async function login(email: string, password: string): Promise<LoginResult> {
  const { data } = await http.post('/login', { email, password });
  if (data?.data?.two_factor_required) {
    return { kind: 'two_factor_required', email: data.data.email ?? email };
  }
  return { kind: 'authenticated', user: data.data.user };
}

// ==================== two-factor (platform) ====================

export interface TwoFactorStatus {
  enabled: boolean;
  pending: boolean;
  email?: string;
}

export async function twoFactorStatus(): Promise<TwoFactorStatus> {
  const { data } = await http.get('/two-factor/status');
  return data.data;
}

export async function twoFactorSetup(): Promise<{ secret: string; otpauthUrl: string }> {
  const { data } = await http.post('/two-factor/setup');
  return data.data;
}

export async function twoFactorConfirm(code: string): Promise<void> {
  await http.post('/two-factor/confirm', { code });
}

export async function twoFactorChallenge(code: string): Promise<void> {
  await http.post('/two-factor/challenge', { code });
}

export async function twoFactorCancelChallenge(): Promise<void> {
  await http.post('/two-factor/challenge/cancel');
}

export async function twoFactorDisable(password: string, code: string): Promise<void> {
  await http.post('/two-factor/disable', { password, code });
}

export async function me(): Promise<SessionUser> {
  const { data } = await http.get('/me');
  return data.data.user;
}

export async function registerTenant(input: {
  orgType: 'clinic' | 'hospital';
  clinicName: string;
  name: string;
  email: string;
  phone?: string;
  password: string;
}): Promise<SessionUser> {
  const { data } = await http.post('/register', {
    org_type: input.orgType,
    clinic_name: input.clinicName,
    name: input.name,
    email: input.email,
    phone: input.phone,
    password: input.password,
  });
  return data.data.user;
}

export async function logout(): Promise<void> {
  await http.post('/logout');
}

export async function verifyPassword(password: string): Promise<boolean> {
  await http.post('/verify-password', { password });
  return true;
}

export async function bootstrap(): Promise<BootstrapPayload> {
  const { data } = await http.get('/bootstrap');
  return {
    ...data.data,
    studies: (data.data.studies ?? []).map(normalizeStudy),
    invoices: (data.data.invoices ?? []).map(normalizeInvoice),
    notifications: data.data.notifications ?? [],
    auditLogs: data.data.auditLogs ?? [],
    dispatches: data.data.dispatches ?? [],
    inventoryItems: data.data.inventoryItems ?? [],
    inventoryTransactions: data.data.inventoryTransactions ?? [],
    adverseReactions: data.data.adverseReactions ?? [],
    dicomNodes: data.data.dicomNodes ?? [],
    notificationTemplates: data.data.notificationTemplates ?? [],
    staff: data.data.staff ?? [],
    rooms: (data.data.rooms ?? []).map(normalizeRoom),
    paymentMethods: (data.data.paymentMethods ?? []).map(normalizePaymentMethod),
    modalities: (data.data.modalities ?? []).map(normalizeModality),
    services: (data.data.services ?? []).map(normalizeService),
    referrers: (data.data.referrers ?? []).map(normalizeReferrer),
  };
}

// ==================== studies ====================

export type PaymentStatus = 'unpaid' | 'partial' | 'paid';

export interface BookingInput {
  patientId?: string;
  newPatient?: Partial<Patient>;
  serviceId: number;
  roomId?: number;
  referrerId?: number;
  date: string;
  time: string;
  priority: 'routine' | 'urgent' | 'stat';
  notes?: string;
  /** Booking-time cash discount — requires `invoice edit` on the server. */
  discountAmount?: number;
  /** Booking-time settlement against the auto-issued invoice — requires
   *  `invoice payment` on the server. Totals are computed server-side. */
  payment?: {
    status: PaymentStatus;
    amountPaid?: number;
    /** Id of a tenant-configured, active PaymentMethod (server field name: `method`). */
    method?: number;
    reference?: string;
  };
}

export interface BookingResult {
  study: Appointment;
  invoice: Invoice;
  notifications: AppNotification[];
}

export async function createBooking(input: BookingInput): Promise<BookingResult> {
  const { data } = await http.post('/studies', {
    patientId: input.patientId ? Number(input.patientId) : undefined,
    newPatient: input.newPatient,
    serviceId: input.serviceId,
    roomId: input.roomId ?? undefined,
    referrerId: input.referrerId ?? undefined,
    date: input.date,
    time: input.time,
    priority: input.priority,
    notes: input.notes,
    discount: input.discountAmount !== undefined && input.discountAmount > 0
      ? { amount: input.discountAmount }
      : undefined,
    payment: input.payment,
  });
  return {
    study: normalizeStudy(data.data.study),
    invoice: normalizeInvoice(data.data.invoice),
    notifications: data.notifications ?? [],
  };
}

export type WorkflowAction =
  | 'checkin'
  | 'no_show'
  | 'call'
  | 'prepare'
  | 'start'
  | 'complete'
  | 'send_to_reading'
  | 'cancel'
  | 'reject';

export interface TransitionInput {
  appointmentId: string;
  action: WorkflowAction;
  reason?: string;
  dose?: DoseLog;
}

export async function transitionStudy(input: TransitionInput): Promise<{ study: Appointment; notifications: AppNotification[] }> {
  const payload: Record<string, unknown> = { action: input.action };
  if (input.reason !== undefined) payload.reason = input.reason;
  if (input.dose) {
    payload.dose = {
      doseValue: input.dose.doseValue,
      doseUnit: input.dose.doseUnit,
      dlpValue: input.dose.dlpValue ?? null,
      kvp: input.dose.kvp ?? null,
      mas: input.dose.mas ?? null,
      sliceCount: input.dose.sliceCount ?? null,
      seriesCount: input.dose.seriesCount ?? null,
      contrastAgent: input.dose.contrastAgent ?? null,
      contrastVolumeMl: input.dose.contrastVolumeMl ?? null,
      contrastFlowRate: input.dose.contrastFlowRate ?? null,
      cannulaSite: input.dose.cannulaSite ?? null,
      salineFlushMl: input.dose.salineFlushMl ?? null,
      techniqueNotes: input.dose.techniqueNotes ?? null,
      qcPassed: input.dose.qcPassed ?? true,
    };
  }
  const { data } = await http.post(`/studies/${input.appointmentId}/transition`, payload);
  return { study: normalizeStudy(data.data.study), notifications: data.notifications ?? [] };
}

export async function updateStudy(appointmentId: string, updates: Partial<Appointment>): Promise<Appointment> {
  const payload: Record<string, unknown> = {};
  if (updates.priority !== undefined) payload.priority = updates.priority;
  if (updates.roomNumber !== undefined) payload.roomNumber = updates.roomNumber;
  if (updates.time !== undefined) payload.time = updates.time;
  if (updates.notes !== undefined) payload.notes = updates.notes;
  if (updates.assignedRadiologistId !== undefined) payload.assignedRadiologistId = updates.assignedRadiologistId;

  const { data } = await http.put(`/studies/${appointmentId}`, payload);
  return normalizeStudy(data.data.study);
}

// ==================== live queue TV ====================

function normalizeQueueDisplay(raw: any): QueueDisplay {
  const entry = (e: any): QueueDisplayEntry => ({
    id: String(e?.id ?? ''),
    token: String(e?.token ?? ''),
    patientName: String(e?.patientName ?? 'Guest'),
    priority: e?.priority ?? 'routine',
    state: e?.state ?? 'booked',
    modality: e?.modality ?? null,
    roomName: String(e?.roomName ?? ''),
    checkedInAt: e?.checkedInAt ?? null,
    calledAt: e?.calledAt ?? null,
    calledAtLabel: e?.calledAtLabel ?? null,
    waitedMinutes: Number(e?.waitedMinutes ?? 0),
  });

  return {
    serverTime: String(raw?.serverTime ?? new Date().toISOString()),
    businessName: String(raw?.businessName ?? ''),
    announcement: String(raw?.announcement ?? ''),
    zones: (raw?.zones ?? []).map((z: any) => ({
      id: Number(z?.id ?? 0),
      code: String(z?.code ?? ''),
      name: String(z?.name ?? ''),
      color: String(z?.color ?? '#0080b6'),
      waiting: Number(z?.waiting ?? 0),
      serving: Number(z?.serving ?? 0),
    })),
    nowServing: (raw?.nowServing ?? []).map(entry),
    upNext: (raw?.upNext ?? []).map(entry),
    recentCalls: (raw?.recentCalls ?? []).map(entry),
    stats: {
      waiting: Number(raw?.stats?.waiting ?? 0),
      serving: Number(raw?.stats?.serving ?? 0),
      completed: Number(raw?.stats?.completed ?? 0),
      noShow: Number(raw?.stats?.noShow ?? 0),
    },
  };
}

/** Staff console feed — session-authed, `queue view` enforced server-side. */
export async function fetchQueueDisplay(): Promise<QueueDisplay> {
  const { data } = await http.get('/queue/display');
  return normalizeQueueDisplay(data.data);
}

/**
 * Waiting-room kiosk feed — the display key IS the credential. A 404 means
 * the link was regenerated/revoked; the TV view keeps polling either way.
 */
export async function fetchPublicQueueDisplay(key: string): Promise<QueueDisplay> {
  const { data } = await http.get('/public/queue-display', { params: { key } });
  return normalizeQueueDisplay(data.data);
}

export async function fetchQueueDisplaySettings(): Promise<QueueDisplaySettings> {
  const { data } = await http.get('/queue/display/settings');
  return {
    displayKey: String(data.data?.displayKey ?? ''),
    announcement: String(data.data?.announcement ?? ''),
  };
}

export async function saveQueueDisplaySettings(input: { announcement?: string; regenerateKey?: boolean }): Promise<QueueDisplaySettings> {
  const { data } = await http.put('/queue/display/settings', input);
  return {
    displayKey: String(data.data?.displayKey ?? ''),
    announcement: String(data.data?.announcement ?? ''),
  };
}

// ==================== screening ====================

export async function submitScreening(
  appointmentId: string,
  answers: Array<{ questionId: string; answerValue: string; overrideReason?: string }>
): Promise<Appointment> {
  const { data } = await http.post(`/studies/${appointmentId}/screening`, { answers });
  return normalizeStudy(data.data.study);
}

/**
 * Re-run the advisory AI screening triage (TypeSafe System One / Jev).
 * Returns { triage, study }; `triage` is null when the evaluation could not
 * run (gateway unreachable / disabled) — the study is always returned so
 * the caller can refresh its state either way.
 */
export async function rerunScreeningTriage(
  appointmentId: string
): Promise<{ triage: ScreeningTriage | null; study: Appointment }> {
  const { data } = await http.post(`/studies/${appointmentId}/screening/triage`);
  return {
    triage: (data.data?.triage ?? null) as ScreeningTriage | null,
    study: normalizeStudy(data.data.study),
  };
}

// ==================== reports ====================

export interface ReportInput {
  clinicalHistory: string;
  technique: string;
  comparison: string;
  findings: string;
  impression: string;
  recommendations: string;
  criticalFlag: boolean;
  templateId?: string;
  /** Values collected by the template's structured fields (validated server-side). */
  structuredValues?: StructuredValues;
  /**
   * Draft revision the editor loaded. The server refuses a save built from a
   * stale revision (409 + the current state) instead of silently overwriting
   * a colleague's work.
   */
  lockVersion?: number;
  signNow?: boolean;
  signAs?: 'final' | 'preliminary';
}

/** Create the first report for a study (an existing draft must be UPDATEd). */
export async function saveReport(
  appointmentId: string,
  input: ReportInput
): Promise<{ study: Appointment; report: RadiologyReport; notifications: AppNotification[] }> {
  const { data } = await http.post(`/studies/${appointmentId}/reports`, {
    ...input,
    templateId: input.templateId ? Number(input.templateId) : undefined,
  });
  return {
    study: normalizeStudy(data.data.study),
    report: data.data.report,
    notifications: data.notifications ?? [],
  };
}

/** Edit the current UNSIGNED draft (signed reports are immutable — addenda instead). */
export async function updateReport(
  reportId: string,
  input: ReportInput
): Promise<{ study: Appointment; report: RadiologyReport }> {
  const { data } = await http.put(`/reports/${reportId}`, {
    ...input,
    templateId: input.templateId ? Number(input.templateId) : undefined,
  });
  return { study: normalizeStudy(data.data.study), report: data.data.report };
}

/** One report version plus its study — used for draft recovery after an edit conflict. */
export async function fetchReport(reportId: string): Promise<{ study: Appointment; report: RadiologyReport }> {
  const { data } = await http.get(`/reports/${reportId}`);
  return { study: normalizeStudy(data.data.study), report: data.data.report };
}

/**
 * Append an addendum to a SIGNED report. The original signed version is never
 * rewritten; the addendum becomes its own signed version.
 */
export async function createReportAddendum(
  reportId: string,
  input: { text: string; recommendations?: string; criticalFlag?: boolean }
): Promise<{ study: Appointment; report: RadiologyReport }> {
  const { data } = await http.post(`/reports/${reportId}/addendum`, input);
  return { study: normalizeStudy(data.data.study), report: data.data.report };
}

// ==================== reporting module ====================

/** Drop empty filter values so they never reach the query string. */
function queryParams(params: Record<string, unknown>): Record<string, unknown> {
  return Object.fromEntries(
    Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== '')
  );
}

export interface WorklistQuery {
  tab?: ReportingTab;
  q?: string;
  priority?: Priority | 'all';
  modalityId?: number;
  status?: StudyState;
  reportStatus?: ReportStatusFilter;
  assignee?: string;
  from?: string;
  to?: string;
  sort?: 'priority' | 'oldest' | 'newest';
  page?: number;
  perPage?: number;
}

/** Server-side reading worklist: filtered, sorted and paginated in SQL. */
export async function fetchReportingWorklist(query: WorklistQuery = {}): Promise<ReportingWorklistResult> {
  const { data } = await http.get('/reporting/worklist', {
    params: queryParams(query as Record<string, unknown>),
  });
  return data.data;
}

/**
 * The signed-in radiologist's reporting setup for this clinic.
 *
 * Server-backed so it follows the person rather than one workstation; the
 * caller keeps a localStorage copy purely so the first paint is instant.
 */
export async function fetchReportingPreferences(): Promise<{
  preferences: ReportingPreferences;
  views: ReportingSavedView[];
  languages: string[];
  tabs: string[];
}> {
  const { data } = await http.get('/reporting/preferences');
  return data.data;
}

export async function saveReportingPreferences(
  changes: Partial<ReportingPreferences>
): Promise<ReportingPreferences> {
  const { data } = await http.put('/reporting/preferences', changes);
  return data.data.preferences;
}

export async function saveReportingView(
  name: string,
  filters: ReportingViewFilters
): Promise<ReportingSavedView[]> {
  const { data } = await http.post('/reporting/views', { name, filters });
  return data.data.views;
}

export async function renameReportingView(
  id: string,
  name: string,
  filters: ReportingViewFilters
): Promise<ReportingSavedView[]> {
  const { data } = await http.put(`/reporting/views/${id}`, { name, filters });
  return data.data.views;
}

export async function deleteReportingView(id: string): Promise<ReportingSavedView[]> {
  const { data } = await http.delete(`/reporting/views/${id}`);
  return data.data.views;
}

/** Does this clinic dictate through its own speech-to-text service? */
export async function fetchDictationCapability(): Promise<DictationCapability> {
  const { data } = await http.get('/reporting/dictation');
  return data.data;
}

/**
 * Transcribe one recorded chunk through the clinic's own engine.
 *
 * The audio is posted as a file and is never stored server-side; the returned
 * text is inserted into the field the radiologist is editing and still has to
 * be reviewed before the report is signed.
 */
/**
 * The file extension for a recording's container.
 *
 * Browsers do not agree on one: Chromium and Firefox record WebM/Opus, Safari
 * records MP4/AAC and cannot produce WebM at all. The extension is derived from
 * the blob's own type rather than assumed.
 */
function dictationFileExtension(mimeType: string): string {
  const type = (mimeType || '').toLowerCase();
  if (type.includes('mp4') || type.includes('aac')) return 'mp4';
  if (type.includes('ogg')) return 'ogg';
  if (type.includes('wav')) return 'wav';
  if (type.includes('mpeg')) return 'mp3';
  return 'webm';
}

export async function transcribeDictation(
  audio: Blob,
  language: string,
  appointmentId?: string | null
): Promise<{ text: string; provider: string; latencyMs: number }> {
  const body = new FormData();
  // The filename carries the container, and speech engines routinely demux by
  // it: a Safari recording uploaded as ".webm" while its bytes are MP4 is a
  // failure the clinic would read as "the engine rejected valid audio".
  body.append('audio', audio, `dictation.${dictationFileExtension(audio.type)}`);
  body.append('language', language);
  // Lets the audit trail record which study a dictation session belonged to.
  if (appointmentId) body.append('appointmentId', appointmentId);

  const { data } = await http.post('/reporting/dictation/transcribe', body);
  return data.data;
}

/**
 * One study in worklist shape. Used when another board (the dashboard's
 * "Manage" button, global search, a notification) hands a study to reporting:
 * the worklist is paginated and filtered, so the row that was clicked is not
 * reliably on the page the SPA already holds. The server re-checks the tenant
 * and the study's state, so an id from another clinic resolves to a 404.
 */
export async function fetchReportingStudy(id: number): Promise<WorklistStudy> {
  const { data } = await http.get(`/reporting/studies/${id}`);
  return data.data.study;
}

/**
 * Client mirror of `App\Support\AgeGroup` — used only to PREVIEW which template
 * a manual study will resolve to. The server recomputes the band from the
 * stored date of birth, so a wrong client value can never change the clinical
 * text that is actually applied.
 */
export function ageGroupFor(ageYears?: number | null): string | undefined {
  if (ageYears === undefined || ageYears === null || Number.isNaN(ageYears)) return undefined;
  if (ageYears <= 1) return 'infant';
  if (ageYears <= 12) return 'pediatric';
  if (ageYears <= 17) return 'adolescent';
  if (ageYears <= 64) return 'adult';
  return 'older_adult';
}

export async function fetchReportingTemplates(params: {
  q?: string;
  modalityId?: number;
  serviceId?: number;
  includeArchived?: boolean;
  mine?: boolean;
} = {}): Promise<{ templates: ReportTemplate[]; ageGroups: Record<string, string> }> {
  const { data } = await http.get('/reporting/templates', { params: queryParams(params) });
  return data.data;
}

/** Ask which template a study resolves to, and why (nothing is saved). */
export async function resolveReportTemplate(input: {
  appointmentId?: string | number;
  serviceId?: number;
  modalityId?: number;
  bodyRegion?: string;
  ageGroup?: string;
  sex?: 'male' | 'female';
  contrast?: 'with' | 'without' | 'both';
}): Promise<TemplateMatch> {
  const { data } = await http.post('/reporting/templates/resolve', {
    ...input,
    appointmentId: input.appointmentId ? Number(input.appointmentId) : undefined,
  });
  return data.data;
}

export async function duplicateReportTemplate(
  templateId: string,
  input: { name?: string; scope?: 'tenant' | 'personal' } = {}
): Promise<ReportTemplate> {
  const { data } = await http.post(`/report-templates/${templateId}/duplicate`, input);
  return data.data.template;
}

export async function archiveReportTemplate(templateId: string, archived = true): Promise<ReportTemplate> {
  const { data } = await http.post(`/report-templates/${templateId}/archive`, { archived });
  return data.data.template;
}

export async function fetchReportMacros(params: {
  modalityId?: number;
  serviceId?: number;
  q?: string;
} = {}): Promise<ReportMacro[]> {
  const { data } = await http.get('/reporting/macros', { params: queryParams(params) });
  return data.data.macros;
}

export async function createReportMacro(input: {
  name: string;
  shortcut?: string;
  modalityId?: number | null;
  serviceId?: number | null;
  findings: string;
  impression: string;
  recommendations?: string;
  scope: 'tenant' | 'personal';
}): Promise<ReportMacro> {
  const { data } = await http.post('/reporting/macros', input);
  return data.data.macro;
}

export async function updateReportMacro(macro: ReportMacro): Promise<ReportMacro> {
  const { data } = await http.put(`/reporting/macros/${macro.id}`, {
    name: macro.name,
    shortcut: macro.shortcut,
    modalityId: macro.modalityId ?? null,
    serviceId: macro.serviceId ?? null,
    findings: macro.findings,
    impression: macro.impression,
    recommendations: macro.recommendations,
    scope: macro.scope,
  });
  return data.data.macro;
}

export async function archiveReportMacro(macroId: string): Promise<void> {
  await http.delete(`/reporting/macros/${macroId}`);
}

export async function useReportMacro(macroId: string): Promise<number> {
  const { data } = await http.post(`/reporting/macros/${macroId}/use`);
  return data.data.usageCount;
}

export interface ReportSearchQuery {
  q?: string;
  modalityId?: number;
  radiologistId?: number;
  patientId?: number;
  status?: 'draft' | 'preliminary' | 'final' | 'addendum';
  from?: string;
  to?: string;
  page?: number;
  perPage?: number;
}

export async function searchReports(
  query: ReportSearchQuery = {}
): Promise<{ reports: ReportSearchRow[]; page: number; perPage: number; total: number; hasMore: boolean }> {
  const { data } = await http.get('/reporting/reports', {
    params: queryParams(query as Record<string, unknown>),
  });
  return data.data;
}

/** Prior examinations + their reports for the same patient (longitudinal record). */
export async function fetchReportPriors(appointmentId: string): Promise<PriorExam[]> {
  const { data } = await http.get(`/reporting/priors/${appointmentId}`);
  return data.data.priors;
}

export async function fetchCriticalFindings(appointmentId: string): Promise<CriticalFindingLog[]> {
  const { data } = await http.get(`/reporting/critical-findings/${appointmentId}`);
  return data.data.logs;
}

export async function recordCriticalFinding(
  appointmentId: string,
  input: {
    summary: string;
    notifiedTo: string;
    notifiedRole?: string;
    contact?: string;
    method: CriticalFindingLog['method'];
    readBackVerified?: boolean;
    adviceGiven?: string;
    reportId?: string;
  }
): Promise<CriticalFindingLog> {
  const { data } = await http.post(`/reporting/critical-findings/${appointmentId}`, {
    ...input,
    reportId: input.reportId ? Number(input.reportId) : undefined,
  });
  return data.data.log;
}

/** Radiologists who may receive a reading assignment (no `user manage` needed). */
export async function fetchRadiologistRoster(): Promise<RadiologistSummary[]> {
  const { data } = await http.get('/reporting/roster');
  return data.data.radiologists;
}

/**
 * Assign a study, or take it yourself.
 *
 * `radiologistId` is a real user id, the literal `'me'` (the server resolves that
 * to the caller), or `null` to unassign. It is sent as-is on purpose: this used
 * to be `Number(radiologistId)`, and `Number('me')` is `NaN`, which
 * `JSON.stringify` writes as `null` — so every "Claim" button in the product
 * quietly unassigned the study and then reported success.
 */
export async function assignStudyToRadiologist(
  appointmentId: string,
  radiologistId: string | null
): Promise<WorklistStudy> {
  const { data } = await http.post(`/reporting/studies/${appointmentId}/assign`, {
    radiologistId,
  });
  return data.data.study;
}

export interface ManualReportInput {
  patientId?: string;
  newPatient?: Partial<Patient>;
  serviceId: number;
  referrerId?: number;
  date: string;
  studyDate?: string;
  priority: Priority;
  indication?: string;
  templateId?: string;
  technique?: string;
  comparison?: string;
  findings?: string;
  impression?: string;
  recommendations?: string;
  criticalFlag?: boolean;
  structuredValues?: StructuredValues;
  signNow?: boolean;
}

/**
 * Create a report for an external / imported / offline study. A real study
 * record is created server-side (origin = manual) — reports are never left
 * detached from the patient's longitudinal record.
 */
export async function createManualReport(
  input: ManualReportInput
): Promise<{ study: Appointment; report: RadiologyReport }> {
  const { data } = await http.post('/reporting/reports/manual', {
    ...input,
    templateId: input.templateId ? Number(input.templateId) : undefined,
  });
  return { study: normalizeStudy(data.data.study), report: data.data.report };
}

export async function releaseReport(
  appointmentId: string,
  reportId: string,
  channel: 'hand' | 'email' | 'portal',
  recipientEmail?: string
): Promise<{ study: Appointment; notifications: AppNotification[] }> {
  const { data } = await http.post(`/reports/${reportId}/release`, { channel, recipientEmail });
  return { study: normalizeStudy(data.data.study), notifications: data.notifications ?? [] };
}

export function reportPdfUrl(reportId: string): string {
  return `/api/v1/reports/${reportId}/pdf`;
}

export function invoicePdfUrl(invoiceId: string): string {
  return `/api/v1/invoices/${invoiceId}/pdf`;
}

// ==================== billing ====================

export async function createInvoice(
  appointmentId: string,
  items: Array<{ serviceId?: number; description: string; quantity: number; unitPrice: number; discount?: number }>,
  options: { discountAmount?: number; discountNotes?: string; taxRate?: number; notes?: string; initialPayment?: { amount: number; method: InvoicePayment['method']; reference?: string } }
): Promise<Invoice> {
  const { data } = await http.post(`/studies/${appointmentId}/invoices`, {
    items: items.map(it => ({ ...it, serviceId: it.serviceId ? Number(it.serviceId) : undefined })),
    taxRate: options.taxRate ?? 0,
    discountAmount: options.discountAmount && options.discountAmount > 0 ? options.discountAmount : undefined,
    notes: options.notes,
    initialPayment: options.initialPayment,
    issueNow: true,
  });
  return normalizeInvoice(data.data.invoice);
}

export async function addInvoiceItem(invoiceId: string, item: { serviceId?: number; description: string; quantity: number; unitPrice: number; discount?: number }): Promise<Invoice> {
  const { data } = await http.post(`/invoices/${invoiceId}/items`, item);
  return normalizeInvoice(data.data.invoice);
}

export async function recordPayment(
  invoiceId: string,
  amount: number,
  method: InvoicePayment['method'],
  reference?: string
): Promise<Invoice> {
  const { data } = await http.post(`/invoices/${invoiceId}/payments`, { amount, method, reference });
  return normalizeInvoice(data.data.invoice);
}

export async function voidInvoice(invoiceId: string, reason: string): Promise<Invoice> {
  const { data } = await http.post(`/invoices/${invoiceId}/void`, { reason });
  return normalizeInvoice(data.data.invoice);
}

/** Refund part or all of one recorded collection (money OUT). */
export async function refundInvoicePayment(
  invoiceId: string,
  paymentId: string,
  amount: number,
  reason: string,
  reference?: string,
): Promise<Invoice> {
  const { data } = await http.post(`/invoices/${invoiceId}/refunds`, {
    paymentId: Number(paymentId),
    amount,
    reason,
    reference,
  });
  return normalizeInvoice(data.data.invoice);
}

/** Server-side ledger for one shift day (the reconciliation source of truth). */
export async function fetchShiftSummary(date?: string): Promise<ShiftSummary> {
  const { data } = await http.get('/billing/shift-summary', { params: date ? { date } : {} });
  return data.data.shift;
}

/** Advisory Jev judgment on a counted shift (fail-open; null when unavailable). */
export async function requestShiftReview(input: {
  cashExpected: number;
  cashCounted: number;
  totalCollected: number;
  refundedTotal: number;
  paymentCount: number;
}): Promise<ShiftReview | null> {
  try {
    const { data } = await http.post('/billing/shift-review', input);
    return data.data.review ?? null;
  } catch {
    return null; // advisory only — never blocks reconciliation
  }
}

// ==================== masters ====================

export async function createModality(input: Omit<Modality, 'id'>): Promise<Modality> {
  const { data } = await http.post('/modalities', input);
  return normalizeModality(data.data.modality);
}

export async function updateModality(modality: Modality): Promise<Modality> {
  const { data } = await http.put(`/modalities/${modality.id}`, modality);
  return normalizeModality(data.data.modality);
}

export async function deleteModality(id: string): Promise<void> {
  await http.delete(`/modalities/${id}`);
}

// ==================== rooms (imaging suites) ====================

export async function createRoom(input: Omit<Room, 'id'>): Promise<Room> {
  const { data } = await http.post('/rooms', input);
  return normalizeRoom(data.data.room);
}

export async function updateRoom(room: Room): Promise<Room> {
  const { data } = await http.put(`/rooms/${room.id}`, room);
  return normalizeRoom(data.data.room);
}

export async function deleteRoom(id: string): Promise<void> {
  await http.delete(`/rooms/${id}`);
}

// ==================== payment methods ====================

export async function createPaymentMethod(input: { code: string; name: string; kind?: PaymentMethod['kind']; isActive?: boolean; sortOrder?: number }): Promise<PaymentMethod> {
  const { data } = await http.post('/payment-methods', input);
  return normalizePaymentMethod(data.data.paymentMethod);
}

export async function updatePaymentMethod(method: Pick<PaymentMethod, 'id'> & { name: string; kind?: PaymentMethod['kind']; isActive?: boolean; sortOrder?: number }): Promise<PaymentMethod> {
  const { data } = await http.put(`/payment-methods/${method.id}`, {
    name: method.name,
    kind: method.kind,
    isActive: method.isActive,
    sortOrder: method.sortOrder,
  });
  return normalizePaymentMethod(data.data.paymentMethod);
}

export async function deletePaymentMethod(id: string): Promise<void> {
  await http.delete(`/payment-methods/${id}`);
}

export async function createService(input: Omit<Service, 'id'>): Promise<Service> {
  const { data } = await http.post('/services', input);
  return normalizeService(data.data.service);
}

export async function updateService(service: Service): Promise<Service> {
  const { data } = await http.put(`/services/${service.id}`, service);
  return normalizeService(data.data.service);
}

export async function deleteService(id: string): Promise<void> {
  await http.delete(`/services/${id}`);
}

export async function createReferrer(input: Omit<Referrer, 'id'>): Promise<Referrer> {
  const { data } = await http.post('/referrers', input);
  return normalizeReferrer(data.data.referrer);
}

export async function updateReferrer(referrer: Referrer): Promise<Referrer> {
  const { data } = await http.put(`/referrers/${referrer.id}`, referrer);
  return normalizeReferrer(data.data.referrer);
}

export async function deleteReferrer(id: string): Promise<void> {
  await http.delete(`/referrers/${id}`);
}

export interface NewScreeningForm {
  name: string;
  description?: string;
  modalityId?: number | null;
  questions: Array<{
    questionText: string;
    helpText?: string;
    answerType: 'boolean' | 'select' | 'text';
    riskValue?: string;
    isRiskBlocking: boolean;
    options?: string[];
  }>;
}

export async function createScreeningForm(input: NewScreeningForm): Promise<ScreeningForm> {
  const { data } = await http.post('/screening-forms', {
    name: input.name,
    description: input.description,
    modalityId: input.modalityId ?? undefined,
    questions: input.questions.map(q => ({
      questionText: q.questionText,
      helpText: q.helpText,
      answerType: q.answerType,
      options: q.options,
      riskValue: q.riskValue,
      isRiskBlocking: q.isRiskBlocking,
    })),
  });
  return data.data.form as ScreeningForm;
}

export async function updateScreeningFormQuestions(formId: string, questions: ScreeningForm['questions']): Promise<ScreeningForm> {
  const { data } = await http.put(`/screening-forms/${formId}`, {
    questions: questions.map(q => ({
      questionText: q.questionText,
      helpText: q.helpText,
      answerType: q.answerType,
      options: q.options,
      riskValue: q.riskValue,
      isRiskBlocking: q.isRiskBlocking,
    })),
  });
  return normalizeScreeningForm(data.data.form);
}

export async function toggleScreeningForm(formId: string): Promise<ScreeningForm> {
  const { data } = await http.post(`/screening-forms/${formId}/toggle`);
  return normalizeScreeningForm(data.data.form);
}

export async function deleteScreeningForm(formId: string): Promise<void> {
  await http.delete(`/screening-forms/${formId}`);
}

export async function updateScreeningFormMeta(
  formId: string,
  meta: { name?: string; description?: string; modalityId?: number | null; isActive?: boolean }
): Promise<ScreeningForm> {
  const { data } = await http.put(`/screening-forms/${formId}`, meta);
  return normalizeScreeningForm(data.data.form);
}

export async function reviewScreeningQuestion(
  questionText: string,
  siblingQuestions: string[] = []
): Promise<CatalogReview | null> {
  const { data } = await http.post('/catalog/review-screening-question', {
    questionText,
    siblingQuestions,
  });
  return (data.data.review ?? null) as CatalogReview | null;
}

export async function reviewPreparationInstructions(
  input: { name: string; requiresContrast?: boolean; requiresScreening?: boolean; instructions: string }
): Promise<CatalogReview | null> {
  const { data } = await http.post('/catalog/review-preparation', input);
  return (data.data.review ?? null) as CatalogReview | null;
}

export async function createReportTemplate(input: Omit<ReportTemplate, 'id'>): Promise<ReportTemplate> {
  const { data } = await http.post('/report-templates', input);
  return data.data.template;
}

export async function updateReportTemplate(template: ReportTemplate): Promise<ReportTemplate> {
  const { data } = await http.put(`/report-templates/${template.id}`, template);
  return data.data.template;
}

export async function deleteReportTemplate(id: string): Promise<void> {
  await http.delete(`/report-templates/${id}`);
}

// ==================== inventory ====================

export async function createInventoryItem(input: Omit<InventoryItem, 'id'>): Promise<{ item: InventoryItem; openingTransactions: InventoryTransaction[] }> {
  const { data } = await http.post('/inventory/items', {
    code: input.code,
    name: input.name,
    genericName: input.genericName,
    category: input.category,
    modality: input.modality,
    unit: input.unit,
    currentStock: input.batches.length > 0 ? 0 : input.currentStock,
    minThreshold: input.minThreshold,
    unitCost: input.unitCost,
    sellingPrice: input.sellingPrice,
    supplier: input.supplier,
    storageLocation: input.storageLocation,
    notes: input.notes,
    batches: input.batches.map(b => ({
      batchNumber: b.batchNumber,
      expiryDate: b.expiryDate,
      quantity: b.quantity,
      receivedDate: b.receivedDate,
    })),
  });
  return {
    item: data.data.item,
    openingTransactions: data.data.openingTransactions ?? [],
  };
}

export async function createInventoryTransaction(input: {
  itemId: string;
  type: 'usage_study' | 'stock_in' | 'adjustment' | 'wastage' | 'expired_discard';
  quantity: number;
  batchNumber?: string;
  direction?: 'in' | 'out';
  tokenNumber?: string;
  patientName?: string;
  notes?: string;
}): Promise<{ transaction: InventoryTransaction; item: InventoryItem }> {
  const { data } = await http.post('/inventory/transactions', input);
  return { transaction: data.data.transaction, item: data.data.item };
}

export async function createAdverseReaction(input: Omit<AdverseReactionReport, 'id' | 'reportedAt' | 'reportedBy'>): Promise<AdverseReactionReport> {
  const { data } = await http.post('/inventory/adverse-reactions', {
    appointmentId: input.appointmentId ? Number(input.appointmentId) : undefined,
    tokenNumber: input.tokenNumber,
    patientName: input.patientName,
    modality: input.modality,
    contrastAgent: input.contrastAgent,
    batchNumber: input.batchNumber,
    administeredVolume: input.administeredVolume,
    severity: input.severity,
    symptoms: input.symptoms,
    treatmentGiven: input.treatmentGiven,
    outcome: input.outcome,
    supervisingDoctor: input.supervisingDoctor,
    notes: input.notes,
  });
  return data.data.reaction;
}

// ==================== staff ====================

/**
 * Creating or updating a staff member. `roleId` assigns a tenant-created custom
 * role; omit it (or send null) to assign by the system `role` name instead.
 * The server reads roleId with `! empty()`, so null is not a request for a role.
 */
export type StaffInput = Omit<StaffUser, 'id'> & { password?: string };

export async function createStaff(input: StaffInput & { password: string }): Promise<StaffUser> {
  const { data } = await http.post('/staff', input);
  return data.data.staff;
}

export async function updateStaff(user: StaffInput & { id: string }): Promise<StaffUser> {
  const { data } = await http.put(`/staff/${user.id}`, user);
  return data.data.staff;
}

export async function deleteStaff(id: string): Promise<void> {
  await http.delete(`/staff/${id}`);
}

// ==================== settings ====================

export async function updateClinicSettings(settings: ClinicProfileSettings): Promise<ClinicProfileSettings> {
  const { data } = await http.put('/clinic', settings);
  return data.data.clinic;
}

export async function createDicomNode(input: Omit<DicomNodeConfig, 'id' | 'status' | 'lastPingTime' | 'lastPingLatencyMs'>): Promise<DicomNodeConfig> {
  const { data } = await http.post('/dicom-nodes', input);
  return data.data.node;
}

export async function updateDicomNode(node: DicomNodeConfig): Promise<DicomNodeConfig> {
  const { data } = await http.put(`/dicom-nodes/${node.id}`, {
    nodeName: node.nodeName,
    aeTitle: node.aeTitle,
    ipAddress: node.ipAddress,
    port: node.port,
    modalityCode: node.modalityCode ?? undefined,
    isWorklistSCP: node.isWorklistSCP,
    isStorageSCP: node.isStorageSCP,
  });
  return data.data.node;
}

export async function deleteDicomNode(id: string): Promise<void> {
  await http.delete(`/dicom-nodes/${id}`);
}

export async function pingDicomNode(id: string): Promise<{ node: DicomNodeConfig; probe: { status: string; latency: number | null } }> {
  const { data } = await http.post(`/dicom-nodes/${id}/ping`);
  return { node: data.data.node, probe: data.data.probe };
}

export async function updateNotificationTemplate(template: NotificationTemplateT): Promise<NotificationTemplateT> {
  const { data } = await http.put(`/notification-templates/${template.id}`, {
    templateBody: template.templateBody,
    enabled: template.enabled,
    subject: template.subject,
  });
  return data.data.template;
}

export async function createDispatch(input: {
  appointmentId: string;
  channel: 'whatsapp' | 'email' | 'sms' | 'portal';
  recipientContact: string;
}): Promise<{ dispatch: DoctorDispatchLog; notifications: AppNotification[] }> {
  const { data } = await http.post('/dispatches', {
    appointmentId: Number(input.appointmentId),
    channel: input.channel,
    recipientContact: input.recipientContact,
  });
  return { dispatch: data.data.dispatch, notifications: data.notifications ?? [] };
}

export async function fetchAuditLogs(): Promise<AuditLogEntry[]> {
  const { data } = await http.get('/audit-logs');
  return data.data.logs ?? [];
}

// ==================== notifications ====================
export async function markNotificationsRead(ids: string[]): Promise<void> {
  await http.post('/notifications/mark-read', { ids: ids.map(Number) });
}

export async function markAllNotificationsRead(): Promise<void> {
  await http.post('/notifications/mark-all-read');
}

export async function deleteNotification(id: string): Promise<void> {
  await http.delete(`/notifications/${id}`);
}

export async function clearNotifications(): Promise<void> {
  await http.delete('/notifications');
}

// ==================== backup ====================

export async function exportBackup(): Promise<Record<string, unknown>> {
  const { data } = await http.get('/backup');
  return data.data;
}

// ==================== tenant context ====================

export async function switchTenant(businessId: number): Promise<SessionUser> {
  const { data } = await http.post('/tenant/switch', { businessId });
  return data.data.user;
}

export async function enterSupportContext(businessId: number): Promise<SessionUser> {
  const { data } = await http.post('/tenant/enter', { businessId });
  return data.data.user;
}

export async function leaveSupportContext(): Promise<SessionUser> {
  const { data } = await http.post('/tenant/leave');
  return data.data.user;
}

// ==================== plans (public) ====================

export async function fetchPublicPlans(): Promise<Plan[]> {
  const { data } = await http.get('/plans');
  return data.data.plans ?? [];
}

// ==================== platform control plane ====================

export async function fetchPlatformOverview(): Promise<PlatformOverviewStats> {
  const { data } = await http.get('/platform/overview');
  return data.data.stats;
}

export async function fetchPlatformTenants(params?: { q?: string; status?: string; planId?: string }): Promise<TenantRecord[]> {
  const { data } = await http.get('/platform/tenants', { params });
  return data.data.tenants ?? [];
}

export async function provisionTenant(input: {
  name: string;
  adminName: string;
  adminEmail: string;
  adminPhone?: string;
  planId?: string | null;
  trialDays?: number | null;
}): Promise<{ tenant: TenantRecord; initialAdminPassword: string | null }> {
  const { data } = await http.post('/platform/tenants', {
    name: input.name,
    adminName: input.adminName,
    adminEmail: input.adminEmail,
    adminPhone: input.adminPhone ?? undefined,
    planId: input.planId ? Number(input.planId) : undefined,
    trialDays: input.trialDays ?? undefined,
  });
  return { tenant: data.data.tenant, initialAdminPassword: data.data.initialAdminPassword };
}

export async function fetchTenant360(id: string): Promise<Tenant360> {
  const { data } = await http.get(`/platform/tenants/${id}`);
  return data.data.tenant;
}

export async function updateTenantSubscription(id: string, updates: { name?: string; planId?: string | null; subscriptionEndsAt?: string | null; trialEndsAt?: string | null }): Promise<TenantRecord> {
  const { data } = await http.patch(`/platform/tenants/${id}`, updates);
  return data.data.tenant;
}

// ==================== platform: deployment topology ====================

export async function fetchInfrastructure(): Promise<InfrastructureSummary> {
  const { data } = await http.get('/platform/infrastructure');
  return data.data;
}

// ==================== platform: operations & observability (§80/§81) ====================

export async function fetchPlatformOperations(): Promise<PlatformOperationsPayload> {
  const { data } = await http.get('/platform/operations');
  return data.data;
}

export async function fetchFailedJobs(limit = 25): Promise<FailedJobsPayload> {
  const { data } = await http.get('/platform/operations/jobs', { params: { limit } });
  return data.data;
}

export async function retryFailedJob(uuid: string): Promise<FailedJobRetryResult> {
  const { data } = await http.post(`/platform/operations/jobs/${uuid}/retry`);
  return data.data;
}

export async function forgetFailedJob(uuid: string): Promise<{ uuid: string; removed: boolean }> {
  const { data } = await http.delete(`/platform/operations/jobs/${uuid}`);
  return data.data;
}

export async function reconcileTenantEntitlements(tenantId: string, apply = false): Promise<EntitlementReconciliation> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/entitlements/reconcile`, { apply });
  return data.data;
}

export async function updateTenantDeployment(id: string, input: {
  region: string;
  deploymentStamp: string;
  isolationProfile: string;
  databaseCluster?: string | null;
  storageRegion?: string | null;
  reason?: string;
}): Promise<TenantRecord> {
  const { data } = await http.patch(`/platform/tenants/${id}/deployment`, input);
  return data.data.tenant;
}

// ==================== platform: white-label branding + domains ====================

export async function fetchTenantBranding(id: string): Promise<TenantBrandingPayload> {
  const { data } = await http.get(`/platform/tenants/${id}/branding`);
  return data.data;
}

export async function updateTenantBranding(id: string, input: Partial<Omit<TenantBranding, 'emailFromName'>> & { emailFromName?: string }): Promise<TenantBrandingPayload> {
  const { data } = await http.put(`/platform/tenants/${id}/branding`, input);
  return data.data;
}

export async function addTenantDomain(id: string, host: string, isPrimary?: boolean): Promise<TenantBrandingPayload> {
  const { data } = await http.post(`/platform/tenants/${id}/domains`, { host, isPrimary });
  return data.data;
}

export async function verifyTenantDomain(id: string, domainId: string): Promise<TenantBrandingPayload> {
  const { data } = await http.post(`/platform/tenants/${id}/domains/${domainId}/verify`);
  return data.data;
}

export async function makeTenantDomainPrimary(id: string, domainId: string): Promise<TenantBrandingPayload> {
  const { data } = await http.post(`/platform/tenants/${id}/domains/${domainId}/primary`);
  return data.data;
}

export async function deleteTenantDomain(id: string, domainId: string): Promise<TenantBrandingPayload> {
  const { data } = await http.delete(`/platform/tenants/${id}/domains/${domainId}`);
  return data.data;
}

/**
 * Public login-screen branding for the host the browser is on. Cosmetic only:
 * the result never grants access to anything.
 */
export async function fetchPublicTenantContext(host?: string): Promise<PublicTenantContext> {
  const { data } = await http.get('/tenant-context', { params: host ? { host } : undefined });
  return data.data.branding;
}

// ==================== platform: tenant integration registry ====================

export async function fetchTenantIntegrations(tenantId: string): Promise<TenantIntegrationsPayload> {
  const { data } = await http.get(`/platform/tenants/${tenantId}/integrations`);
  return data.data;
}

export async function createTenantIntegration(tenantId: string, input: {
  type: string;
  name: string;
  facilityId?: string | null;
  config?: Record<string, string>;
  secrets?: Record<string, string>;
}): Promise<TenantIntegrationsPayload> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/integrations`, {
    ...input,
    facilityId: input.facilityId ? Number(input.facilityId) : undefined,
  });
  return data.data;
}

export async function updateTenantIntegration(tenantId: string, integrationId: string, input: {
  name?: string;
  facilityId?: string | null;
  config?: Record<string, string>;
  secrets?: Record<string, string>;
}): Promise<TenantIntegrationsPayload> {
  const { data } = await http.patch(`/platform/tenants/${tenantId}/integrations/${integrationId}`, {
    ...input,
    facilityId: input.facilityId === undefined ? undefined : (input.facilityId ? Number(input.facilityId) : null),
  });
  return data.data;
}

export async function rotateTenantIntegrationSecrets(tenantId: string, integrationId: string, secrets: Record<string, string>): Promise<TenantIntegrationsPayload> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/integrations/${integrationId}/secrets`, { secrets });
  return data.data;
}

export async function probeTenantIntegration(tenantId: string, integrationId: string): Promise<TenantIntegrationsPayload> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/integrations/${integrationId}/probe`);
  return data.data;
}

/** Real end-to-end test: sends a synthetic event through the channel. */
export async function testTenantIntegration(tenantId: string, integrationId: string): Promise<TenantIntegrationsPayload> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/integrations/${integrationId}/test`);
  return data.data;
}

export async function deleteTenantIntegration(tenantId: string, integrationId: string): Promise<TenantIntegrationsPayload> {
  const { data } = await http.delete(`/platform/tenants/${tenantId}/integrations/${integrationId}`);
  return data.data;
}

// ==================== platform: tenant user administration ====================

export async function createTenantUser(tenantId: string, input: {
  name: string;
  email: string;
  role: string;
  phone?: string;
  password?: string;
  isActive?: boolean;
}): Promise<{ user: TenantUserRecord; initialPassword: string | null }> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/users`, input);
  return { user: data.data.user, initialPassword: data.data.initialPassword ?? null };
}

export async function updateTenantUser(tenantId: string, userId: string, updates: {
  name?: string;
  email?: string;
  role?: string;
  phone?: string | null;
  isActive?: boolean;
  loginEnabled?: boolean;
}): Promise<TenantUserRecord> {
  const { data } = await http.patch(`/platform/tenants/${tenantId}/users/${userId}`, updates);
  return data.data.user;
}

export async function resetTenantUserPassword(tenantId: string, userId: string): Promise<{ user: TenantUserRecord; newPassword: string }> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/users/${userId}/reset-password`);
  return { user: data.data.user, newPassword: data.data.newPassword };
}

// ==================== platform: tenant facilities ====================

export async function createTenantFacility(tenantId: string, input: { name: string; address?: string; phone?: string; description?: string }): Promise<TenantFacilityRecord> {
  const { data } = await http.post(`/platform/tenants/${tenantId}/facilities`, input);
  return data.data.facility;
}

export async function updateTenantFacility(tenantId: string, facilityId: string, updates: { name?: string; address?: string | null; phone?: string | null; description?: string | null }): Promise<TenantFacilityRecord> {
  const { data } = await http.patch(`/platform/tenants/${tenantId}/facilities/${facilityId}`, updates);
  return data.data.facility;
}

export async function deleteTenantFacility(tenantId: string, facilityId: string): Promise<void> {
  await http.delete(`/platform/tenants/${tenantId}/facilities/${facilityId}`);
}

export type TenantLifecycleAction = 'activate' | 'suspend' | 'reactivate' | 'offboard' | 'terminate' | 'provision-retry';

export async function tenantLifecycleAction(id: string, action: TenantLifecycleAction, payload?: { reason?: string; confirmCode?: string }): Promise<TenantRecord> {
  const { data } = await http.post(`/platform/tenants/${id}/${action}`, payload ?? {});
  return data.data.tenant;
}

export async function updateTenantFeatures(id: string, overrides: Record<string, boolean>): Promise<{ features: Record<string, boolean>; overrides: { feature: string; enabled: boolean }[] }> {
  const { data } = await http.put(`/platform/tenants/${id}/features`, { overrides });
  return { features: data.data.features, overrides: data.data.overrides };
}

export async function fetchTenantUsage(id: string): Promise<UsageSummary> {
  const { data } = await http.get(`/platform/tenants/${id}/usage`);
  return data.data.usage;
}

export async function exportTenantData(id: string): Promise<Record<string, unknown>> {
  const { data } = await http.get(`/platform/tenants/${id}/export`);
  return data.data.export;
}

export async function fetchPlatformPlans(): Promise<Plan[]> {
  const { data } = await http.get('/platform/plans');
  return data.data.plans ?? [];
}

export async function createPlatformPlan(input: Partial<Plan> & { name: string; priceMonthly: number; currency: string; trialDays: number }): Promise<Plan> {
  const { data } = await http.post('/platform/plans', input);
  return data.data.plan;
}

export async function updatePlatformPlan(id: string, input: Partial<Plan>): Promise<Plan> {
  const { data } = await http.patch(`/platform/plans/${id}`, input);
  return data.data.plan;
}

export async function fetchPlatformUsers(): Promise<PlatformUserRecord[]> {
  const { data } = await http.get('/platform/users');
  return data.data.users ?? [];
}

export async function createPlatformUser(input: { name: string; email: string; password: string; role: PlatformRole }): Promise<PlatformUserRecord> {
  const { data } = await http.post('/platform/users', input);
  return data.data.user;
}

export async function updatePlatformUser(id: string, updates: { role?: PlatformRole; isActive?: boolean }): Promise<PlatformUserRecord> {
  const { data } = await http.patch(`/platform/users/${id}`, updates);
  return data.data.user;
}

export async function fetchSupportSessions(): Promise<PlatformSupportSessionRecord[]> {
  const { data } = await http.get('/platform/support-sessions');
  return data.data.sessions ?? [];
}

export async function startSupportSession(input: { businessId: string; reason: string; minutes?: number }): Promise<PlatformSupportSessionRecord> {
  const { data } = await http.post('/platform/support-sessions', {
    businessId: Number(input.businessId),
    reason: input.reason,
    minutes: input.minutes,
  });
  return data.data.session;
}

export async function endSupportSession(id: string, note?: string): Promise<PlatformSupportSessionRecord> {
  const { data } = await http.post(`/platform/support-sessions/${id}/end`, { note });
  return data.data.session;
}

export async function fetchPlatformAudit(params?: { action?: string; tenantId?: string }): Promise<PlatformAuditEntry[]> {
  const { data } = await http.get('/platform/audit', { params });
  return data.data.audit ?? [];
}

// ==================== control-plane step-up + tenant branding view ====================

/** Fresh password confirmation for mutating platform actions (428 gate). */
export async function confirmPlatformStepUp(password: string): Promise<void> {
  await http.post('/platform/step-up', { password });
}

export async function fetchPlatformStepUpStatus(): Promise<{ required: boolean; confirmedAt: string | null; windowMinutes: number }> {
  const { data } = await http.get('/platform/step-up');
  return data.data;
}

/** Tenant-side READ-ONLY white-label view (branding is platform-managed). */
export async function fetchTenantBrandingView(): Promise<TenantBrandingView> {
  const { data } = await http.get('/settings/branding');
  return data.data;
}

// ==================== tenant RBAC: roles & permissions admin ====================

export async function fetchAccessCatalog(): Promise<PermissionGroup[]> {
  const { data } = await http.get('/access/catalog');
  return data.data.catalog;
}

export async function fetchAccessRoles(): Promise<AccessRoleRecord[]> {
  const { data } = await http.get('/access/roles');
  return data.data.roles;
}

export async function createAccessRole(input: {
  name: string;
  displayName?: string;
  description?: string;
  permissions: string[];
}): Promise<AccessRoleRecord> {
  const { data } = await http.post('/access/roles', input);
  return data.data.role;
}

export async function updateAccessRole(
  id: string,
  input: { displayName?: string; description?: string }
): Promise<AccessRoleRecord> {
  const { data } = await http.patch(`/access/roles/${id}`, input);
  return data.data.role;
}

export async function syncAccessRolePermissions(id: string, permissions: string[]): Promise<AccessRoleRecord> {
  const { data } = await http.put(`/access/roles/${id}/permissions`, { permissions });
  return data.data.role;
}

export async function duplicateAccessRole(id: string, input?: { name?: string; displayName?: string }): Promise<AccessRoleRecord> {
  const { data } = await http.post(`/access/roles/${id}/duplicate`, input ?? {});
  return data.data.role;
}

export async function deleteAccessRole(id: string): Promise<void> {
  await http.delete(`/access/roles/${id}`);
}

export async function fetchEffectiveAccess(userId: string): Promise<EffectiveAccess> {
  const { data } = await http.get(`/access/users/${userId}/effective`);
  return data.data.access;
}

export async function syncUserOverrides(
  userId: string,
  input: { allow: string[]; deny: string[] }
): Promise<EffectiveAccess> {
  const { data } = await http.put(`/access/users/${userId}/overrides`, input);
  return data.data.access;
}
