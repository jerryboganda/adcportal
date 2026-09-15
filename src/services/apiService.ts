import { http } from './api';
import {
  ActiveTab,
  AdverseReactionReport,
  AppNotification,
  Appointment,
  AuditLogEntry,
  ClinicProfileSettings,
  DicomNodeConfig,
  DoctorDispatchLog,
  DoseLog,
  Entitlements,
  InfrastructureSummary,
  InventoryItem,
  InventoryTransaction,
  Invoice,
  InvoiceItem,
  InvoicePayment,
  Modality,
  Patient,
  Plan,
  PlatformAuditEntry,
  PlatformOverviewStats,
  PlatformRole,
  PlatformSupportSessionRecord,
  PlatformUserRecord,
  RadiologyReport,
  Referrer,
  ReportTemplate,
  ScreeningForm,
  Service,
  StaffRole,
  StaffUser,
  StudyScreeningAnswer,
  SupportSessionInfo,
  Tenant360,
  TenantBranding,
  TenantBrandingPayload,
  TenantDomainRecord,
  TenantFacilityRecord,
  TenantMembership,
  TenantRecord,
  TenantUserRecord,
  PublicTenantContext,
  UsageSummary,
} from '../types';

/**
 * Typed client for the PolytronX - RIS API. Every function returns the same
 * TypeScript models the UI already consumes (formerly hydrated from
 * localStorage); nulls from the server are normalized here so views keep
 * their null-safe rendering paths.
 */

type Nullable<T> = T | null;

export interface SessionUser extends StaffUser {
  businessId: number;
  businessName: string;
  subscriptionStatus: string;
  isPlatformAdmin: boolean;
  platformRole?: PlatformRole | null;
  memberships: TenantMembership[];
  supportSession?: SupportSessionInfo | null;
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
  services: Service[];
  referrers: Referrer[];
  screeningForms: ScreeningForm[];
  templates: ReportTemplate[];
  staff: StaffUser[];
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

// ==================== auth ====================

export async function login(email: string, password: string): Promise<SessionUser> {
  const { data } = await http.post('/login', { email, password });
  return data.data.user;
}

export async function me(): Promise<SessionUser> {
  const { data } = await http.get('/me');
  return data.data.user;
}

export async function registerTenant(input: {
  clinicName: string;
  name: string;
  email: string;
  phone?: string;
  password: string;
}): Promise<SessionUser> {
  const { data } = await http.post('/register', {
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
  };
}

// ==================== studies ====================

export interface BookingInput {
  patientId?: string;
  newPatient?: Partial<Patient>;
  serviceId: number;
  referrerId?: number;
  date: string;
  time: string;
  priority: 'routine' | 'urgent' | 'stat';
  notes?: string;
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
    referrerId: input.referrerId ?? undefined,
    date: input.date,
    time: input.time,
    priority: input.priority,
    notes: input.notes,
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
  | 'prepare'
  | 'start'
  | 'complete'
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

// ==================== screening ====================

export async function getScreening(appointmentId: string): Promise<{ form: ScreeningForm | null; answers: StudyScreeningAnswer[] }> {
  const { data } = await http.get(`/studies/${appointmentId}/screening`);
  return { form: data.data.form, answers: data.data.answers ?? [] };
}

export async function submitScreening(
  appointmentId: string,
  answers: Array<{ questionId: string; answerValue: string; overrideReason?: string }>
): Promise<Appointment> {
  const { data } = await http.post(`/studies/${appointmentId}/screening`, { answers });
  return normalizeStudy(data.data.study);
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
  signNow?: boolean;
  signAs?: 'final' | 'preliminary';
}

export async function saveReport(appointmentId: string, input: ReportInput): Promise<{ study: Appointment; notifications: AppNotification[] }> {
  const { data } = await http.post(`/studies/${appointmentId}/reports`, {
    ...input,
    templateId: input.templateId ? Number(input.templateId) : undefined,
  });
  return { study: normalizeStudy(data.data.study), notifications: data.notifications ?? [] };
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
  options: { discountNotes?: string; taxRate?: number; notes?: string; initialPayment?: { amount: number; method: InvoicePayment['method']; reference?: string } }
): Promise<Invoice> {
  const { data } = await http.post(`/studies/${appointmentId}/invoices`, {
    items: items.map(it => ({ ...it, serviceId: it.serviceId ? Number(it.serviceId) : undefined })),
    taxRate: options.taxRate ?? 0,
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

// ==================== masters ====================

export async function createModality(input: Omit<Modality, 'id'>): Promise<Modality> {
  const { data } = await http.post('/modalities', input);
  return data.data.modality;
}

export async function updateModality(modality: Modality): Promise<Modality> {
  const { data } = await http.put(`/modalities/${modality.id}`, modality);
  return data.data.modality;
}

export async function deleteModality(id: string): Promise<void> {
  await http.delete(`/modalities/${id}`);
}

export async function createService(input: Omit<Service, 'id'>): Promise<Service> {
  const { data } = await http.post('/services', input);
  return data.data.service;
}

export async function updateService(service: Service): Promise<Service> {
  const { data } = await http.put(`/services/${service.id}`, service);
  return data.data.service;
}

export async function deleteService(id: string): Promise<void> {
  await http.delete(`/services/${id}`);
}

export async function createReferrer(input: Omit<Referrer, 'id'>): Promise<Referrer> {
  const { data } = await http.post('/referrers', input);
  return data.data.referrer;
}

export async function updateReferrer(referrer: Referrer): Promise<Referrer> {
  const { data } = await http.put(`/referrers/${referrer.id}`, referrer);
  return data.data.referrer;
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
  return data.data.form;
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
  return data.data.form;
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

export async function createInventoryItem(input: Omit<InventoryItem, 'id'>): Promise<InventoryItem> {
  const { data } = await http.post('/inventory/items', {
    code: input.code,
    name: input.name,
    genericName: input.genericName,
    category: input.category,
    modality: input.modality,
    unit: input.unit,
    currentStock: input.currentStock,
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
  return data.data.item;
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

export async function createStaff(input: Omit<StaffUser, 'id'> & { password: string }): Promise<StaffUser> {
  const { data } = await http.post('/staff', input);
  return data.data.staff;
}

export async function updateStaff(user: StaffUser & { password?: string }): Promise<StaffUser> {
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

export async function fetchMemberships(): Promise<TenantMembership[]> {
  const { data } = await http.get('/memberships');
  return data.data.memberships ?? [];
}

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

export async function fetchTenantAudit(id: string): Promise<PlatformAuditEntry[]> {
  const { data } = await http.get(`/platform/tenants/${id}/audit`);
  return data.data.audit ?? [];
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
