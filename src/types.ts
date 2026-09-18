export type StudyState =
  | 'booked'
  | 'checked_in'
  | 'preparing'
  | 'in_progress'
  | 'acquired'
  | 'reading'
  | 'reported'
  | 'delivered'
  | 'cancelled'
  | 'no_show';

export type WorkflowState = StudyState;

export type Priority = 'routine' | 'urgent' | 'stat';

export interface Modality {
  id: number;
  name: string;
  /** DICOM-style code (DX/US/CT/MR/MG...) — server-issued, not a closed set. */
  code: string;
  color: string;
  bufferMinutes: number;
  isActive: boolean;
}

/** Imaging suite (room) — tenant-configured, scoped to one modality. */
export interface Room {
  id: number;
  name: string;
  modalityId: number;
  locationId?: number | null;
  capacityPerSlot: number;
  description: string;
  isActive: boolean;
}

/** Tenant-configured payment method (code = wire format on payments). */
export interface PaymentMethod {
  id: number;
  code: string;
  name: string;
  isActive: boolean;
  sortOrder: number;
}

export interface Service {
  id: number;
  name: string;
  code: string;
  modalityId: number;
  price: number;
  durationMinutes: number;
  preparationInstructions: string;
  requiresScreening: boolean;
  requiresContrast: boolean;
}

export interface Patient {
  id: string;
  mrn: string;
  name: string;
  email: string;
  phone: string;
  gender: 'male' | 'female' | 'other';
  dob: string;
  age: number;
  bloodGroup: string;
  medicalHistory?: string;
  allergies?: string;
}

export interface Referrer {
  id: number;
  name: string;
  clinicName: string;
  email: string;
  phone: string;
  specialty: string;
}

export interface DoseLog {
  appointmentId: string;
  doseValue: number; // e.g. CTDIvol in mGy, DAP in Gy.cm2, or SAR in W/kg
  doseUnit: string;
  dlpValue?: number; // Dose Length Product (mGy*cm) for CT
  kvp?: number; // Peak kilovoltage
  mas?: number; // Milliampere-seconds
  sliceCount?: number; // Number of acquired slices/images
  seriesCount?: number; // Number of series
  contrastAgent?: string;
  contrastVolumeMl?: number;
  contrastFlowRate?: string;
  cannulaSite?: string;
  salineFlushMl?: number;
  techniqueNotes?: string;
  qcPassed?: boolean;
  recordedAt: string;
  recordedBy: string;
}

export interface ScreeningQuestion {
  id: string;
  formId: string;
  questionText: string;
  helpText?: string;
  answerType: 'boolean' | 'select' | 'text';
  riskValue?: string; // 'yes' or specific option
  isRiskBlocking: boolean;
  options?: string[];
  sortOrder: number;
}

export interface ScreeningForm {
  id: string;
  name: string;
  slug: string;
  description: string;
  modalityId?: number | null;
  questions: ScreeningQuestion[];
}

export interface StudyScreeningAnswer {
  appointmentId: string;
  questionId: string;
  questionText: string;
  answerValue: string;
  isRisk: boolean;
  overrideReason?: string;
  answeredBy: string;
  answeredAt: string;
}

export interface RadiologyReport {
  id: string;
  appointmentId: string;
  version: number;
  type: 'draft' | 'preliminary' | 'final' | 'addendum';
  parentReportId?: string;
  clinicalHistory: string;
  technique: string;
  comparison: string;
  findings: string;
  impression: string;
  recommendations?: string;
  criticalFlag: boolean;
  authoredBy: string;
  signedBy?: string;
  signedAt?: string;
  lockedAt?: string;
  pdfPath?: string;
  releases: ReportRelease[];
}

export interface ReportRelease {
  id: string;
  reportId: string;
  channel: 'hand' | 'email' | 'portal';
  recipientEmail?: string;
  releasedAt: string;
  releasedBy: string;
}

export interface Appointment {
  id: string;
  tokenNumber: string;
  patientId: string;
  patient: Patient;
  serviceId: number;
  service: Service;
  modalityId: number;
  modality: Modality;
  /** Imaging suite the study is booked against (server-validated). */
  roomId?: number | null;
  referrerId?: number;
  referrer?: Referrer;
  date: string;
  time: string;
  priority: Priority;
  workflowState: StudyState;
  cancelReason?: string;
  rejectReason?: string;
  screeningRequired: boolean;
  screeningCleared: boolean;
  screeningAnswers?: StudyScreeningAnswer[];
  performedByStaff?: string;
  assignedRadiologistId?: string;
  assignedRadiologistName?: string;
  checkedInAt?: string;
  preparingAt?: string;
  inProgressAt?: string;
  acquiredAt?: string;
  readingAt?: string;
  reportedAt?: string;
  deliveredAt?: string;
  /** Server-stamped when the queue board calls this token (shared across terminals). */
  calledAt?: string;
  doseLog?: DoseLog;
  report?: RadiologyReport;
  roomNumber: string;
  notes?: string;
}

export interface InvoiceItem {
  id: string;
  serviceId?: number;
  description: string;
  quantity: number;
  unitPrice: number;
  discount: number;
  lineTotal: number;
}

export interface InvoicePayment {
  id: string;
  amount: number;
  /** Code of a TENANT-CONFIGURED payment method (see PaymentMethod). */
  method: string;
  reference?: string;
  paidAt: string;
  receivedBy: string;
}

export interface Invoice {
  id: string;
  invoiceNumber: string;
  patientId: string;
  patient: Patient;
  appointmentId: string;
  appointmentToken: string;
  subtotal: number;
  discountTotal: number;
  /** Cash discount applied at invoice level (server-authoritative). */
  manualDiscount: number;
  taxRate: number;
  taxAmount: number;
  total: number;
  paidTotal: number;
  balanceDue: number;
  status: 'draft' | 'issued' | 'partial' | 'paid' | 'void';
  notes?: string;
  items: InvoiceItem[];
  payments: InvoicePayment[];
  createdAt: string;
  issuedAt?: string;
  voidedAt?: string;
}

export interface ReportTemplate {
  id: string;
  name: string;
  code?: string;
  modalityId: number;
  clinicalHistory: string;
  technique: string;
  findings: string;
  impression: string;
  recommendations: string;
}

// Roles the backend can issue (see StaffUserController validation + seed).
// There is deliberately no 'nurse': the server never assigns one, and a role
// the API cannot issue must not exist in the client vocabulary.
export type StaffRole = 'admin' | 'radiologist' | 'technologist' | 'receptionist' | 'billing';

/** Role vocabulary used by the SPA shell (server-issued, never client-picked).
 *  The five canonical names are issued by system roles; tenant CUSTOM roles
 *  surface their own name — the role string is cosmetic (avatar/labels) while
 *  all real gating is permission-driven. */
export type AppRole = 'admin' | 'radiologist' | 'technologist' | 'receptionist' | 'billing' | (string & {});

export interface StaffUser {
  id: string;
  name: string;
  email: string;
  role: StaffRole;
  department: string;
  phone: string;
  initials: string;
  isActive: boolean;
  canSignReports: boolean;
  canVoidInvoices: boolean;
  canOverrideScreening: boolean;
  canEditMasters: boolean;
  canAccessPacs: boolean;
  lastLogin?: string;
}

export interface ClinicProfileSettings {
  name: string;
  branch: string;
  address: string;
  city: string;
  phone: string;
  emergencyPhone: string;
  email: string;
  website: string;
  pnraLicenseNo: string; // Pakistan Nuclear Regulatory Authority
  pmcRegistrationNo: string; // Pakistan Medical Commission
  taxId: string; // NTN / STRN
  currencySymbol: string;
  headerTagline: string;
  invoiceFooterDisclaimer: string;
  reportLegalDisclaimer: string;
  requireScreeningSignOff: boolean;
  enableCriticalFindingsAlerts: boolean;
  autoSendWhatsappReport: boolean;
  /** Opt-in patient email reminders (idempotent, window-based). */
  sendAppointmentReminders: boolean;
  reminderHours: number;
  /** Referrer commission share (%) used by the settlement sheet. */
  referralCommissionPercent: number;
}

export interface DicomNodeConfig {
  id: string;
  nodeName: string;
  aeTitle: string;
  ipAddress: string;
  port: number;
  modalityCode?: string;
  isWorklistSCP: boolean;
  isStorageSCP: boolean;
  status: 'online' | 'unreachable' | 'testing';
  lastPingTime?: string;
  lastPingLatencyMs?: number;
}

export interface NotificationTemplate {
  id: string;
  name: string;
  category: 'booking' | 'checkin' | 'ready' | 'critical' | 'doctor';
  channel: 'sms' | 'whatsapp' | 'email';
  subject?: string;
  templateBody: string;
  enabled: boolean;
}

export interface AuditLogEntry {
  id: string;
  timestamp: string;
  user: string;
  role: string;
  action: string;
  module: string;
  details: string;
  ipAddress?: string;
  status: 'success' | 'warning' | 'danger';
}

export interface DoctorDispatchLog {
  id: string;
  appointmentId: string;
  tokenNumber: string;
  patientName: string;
  referrerId: number;
  referrerName: string;
  studyName: string;
  channel: 'whatsapp' | 'email' | 'sms' | 'portal';
  recipientContact: string;
  sentAt: string;
  status: 'delivered' | 'read' | 'pending';
  sentBy: string;
}

export type NotificationCategory = 'stat' | 'workflow' | 'billing' | 'dispatch' | 'pacs' | 'security' | 'general';
export type NotificationPriority = 'low' | 'medium' | 'high' | 'critical';

export interface AppNotification {
  id: string;
  title: string;
  message: string;
  category: NotificationCategory;
  priority: NotificationPriority;
  timestamp: string;
  isRead: boolean;
  appointmentId?: string;
  tokenNumber?: string;
  patientName?: string;
  targetTab?: ActiveTab;
  actionLabel?: string;
}

export interface InventoryBatch {
  batchNumber: string;
  expiryDate: string; // YYYY-MM-DD
  quantity: number;
  receivedDate: string;
}

export type InventoryCategory =
  | 'contrast_ct'
  | 'contrast_mri'
  | 'cannula_syringes'
  | 'ppe_safety'
  | 'pharmacy_emergency'
  | 'general_consumable';

export interface InventoryItem {
  id: string;
  code: string;
  name: string;
  genericName: string;
  category: InventoryCategory;
  modality: 'CT' | 'MRI' | 'XRAY' | 'US' | 'ALL';
  unit: string; // "Vial 100mL", "Vial 20mL", "Piece", "Box (50)", "Pack"
  currentStock: number;
  minThreshold: number; // Reorder alert level
  unitCost: number; // in PKR Rs.
  sellingPrice: number; // standard billable price in PKR Rs.
  batches: InventoryBatch[];
  supplier: string;
  storageLocation: string; // "CT Console Bay Room 1", "MRI Prep Cold Cabinet"
  requiresColdChain?: boolean;
  isBillable: boolean;
  notes?: string;
}

export type TransactionType = 'usage_study' | 'stock_in' | 'adjustment' | 'wastage' | 'expired_discard';

export interface InventoryTransaction {
  id: string;
  itemId: string;
  itemName: string;
  type: TransactionType;
  quantity: number;
  batchNumber: string;
  timestamp: string;
  performedBy: string;
  appointmentId?: string;
  tokenNumber?: string;
  patientName?: string;
  notes?: string;
}

export type AdverseSeverity = 'mild' | 'moderate' | 'severe_anaphylaxis' | 'extravasation';
export type AdverseOutcome = 'resolved_on_site' | 'referred_to_er' | 'under_observation';

export interface AdverseReactionReport {
  id: string;
  appointmentId?: string;
  tokenNumber: string;
  patientName: string;
  modality: 'CT' | 'MRI';
  contrastAgent: string;
  batchNumber: string;
  administeredVolume: string; // e.g. "80 mL"
  severity: AdverseSeverity;
  symptoms: string[];
  treatmentGiven: string;
  outcome: AdverseOutcome;
  reportedBy: string;
  reportedAt: string;
  supervisingDoctor: string;
  notes?: string;
}

export type ActiveTab =
  | 'dashboard'
  | 'checkin'
  | 'technologist'
  | 'reporting'
  | 'billing'
  | 'queue'
  | 'inventory'
  | 'masters'
  | 'doctors'
  | 'settings';

// ==================== SaaS platform (control plane) ====================

export type SubscriptionStatus =
  | 'provisioning'
  | 'trialing'
  | 'active'
  | 'suspended'
  | 'expired'
  | 'offboarding'
  | 'terminated';

export type PlatformRole = 'super_admin' | 'ops' | 'billing' | 'support' | 'auditor';

export interface Plan {
  id: string;
  name: string;
  slug: string;
  description: string;
  priceMonthly: number;
  currency: string;
  trialDays: number;
  maxUsers?: number | null;
  maxStudiesPerMonth?: number | null;
  maxStorageMb?: number | null;
  maxLocations?: number | null;
  features?: Record<string, boolean>;
  isActive: boolean;
  subscribers?: number;
}

export interface TenantMembership {
  businessId: number;
  businessName: string;
  /** White-label brand of that tenant (null = account name is the brand). */
  businessBrandName?: string | null;
  role: string;
  isDefault: boolean;
  subscriptionStatus: SubscriptionStatus;
}

export interface SupportSessionInfo {
  id: string;
  businessId: number;
  reason: string;
  startedAt?: string;
  expiresAt?: string;
  endedAt?: string;
  isActive: boolean;
}

export interface Entitlements {
  plan: Plan | null;
  subscriptionStatus: SubscriptionStatus;
  trialEndsAt?: string | null;
  subscriptionEndsAt?: string | null;
  limits: {
    maxUsers?: number | null;
    maxStudiesPerMonth?: number | null;
    maxStorageMb?: number | null;
    maxLocations?: number | null;
  };
  usage: {
    users: number;
    studiesThisMonth: number;
    storageBytes: number;
    locations: number;
  };
  features: Record<string, boolean>;
}

export interface TenantCounts {
  users: number;
  studies: number;
}

/** Control-plane infrastructure placement of one tenant (master-prompt §59/§60). */
export interface TenantDeployment {
  region: string | null;
  deploymentStamp: string | null;
  isolationProfile: string | null;
  databaseCluster: string | null;
  storageRegion: string | null;
}

export interface DeploymentOption {
  key: string;
  label: string;
}

export interface DeploymentCatalog {
  regions: { key: string; label: string; storage: string }[];
  deploymentStamps: DeploymentOption[];
  isolationProfiles: DeploymentOption[];
}

export interface InfrastructureSummary {
  catalog: DeploymentCatalog;
  placement: {
    regions: { key: string; tenants: number }[];
    deploymentStamps: { key: string; tenants: number }[];
    isolationProfiles: { key: string; tenants: number }[];
  };
  unplacedTenants: number;
}

/** Per-tenant health verdict from the observability dashboard (§80). */
export type TenantHealth = 'ok' | 'degraded' | 'critical';

export interface QuotaPressure {
  used: number | null;
  limit: number | null;
  percent: number | null;
  nearLimit: boolean;
  exceeded: boolean;
}

export interface TenantHealthRow {
  tenantId: number;
  name: string;
  tenantCode: string | null;
  subscriptionStatus: string;
  trialEndsAt: string | null;
  subscriptionEndsAt: string | null;
  region: string | null;
  deploymentStamp: string | null;
  isolationProfile: string | null;
  health: TenantHealth;
  reasons: string[];
  integrations: number;
  failingIntegrations: number;
  unconfiguredIntegrations: number;
  usage: { users: number; facilities: number; studiesThisMonth: number };
  limits: {
    maxUsers: number | null;
    maxStudiesPerMonth: number | null;
    maxStorageMb: number | null;
    maxLocations: number | null;
  };
  quotas: Record<'users' | 'studies' | 'locations' | 'storage', QuotaPressure>;
  quotasBreached: string[];
  quotasApproaching: string[];
  storageBytes: number | null;
}

export interface DeploymentHealth {
  region: string | null;
  deploymentStamp: string | null;
  isolationProfile: string | null;
  tenants: number;
  degraded: number;
  critical: number;
}

export interface PlatformSystemHealth {
  database: string;
  cacheStore: string;
  queueConnection: string;
  failedJobDriver: string;
  pendingJobs: number;
  oldestPendingJobAt: string | null;
  failedJobs: number;
  storageWritable: boolean;
  appEnv: string;
  appVersion: string;
  lastLifecycleEventAt: string | null;
  lastLifecycleEvent: string | null;
  checkedAt: string;
}

export interface PlatformOperationsPayload {
  system: PlatformSystemHealth;
  totalTenants: number;
  returned: number;
  storageBasis: string;
  summary: {
    total: number;
    ok: number;
    degraded: number;
    critical: number;
    failingIntegrations: number;
    quotaBreaches: number;
  };
  tenants: TenantHealthRow[];
  deployments: DeploymentHealth[];
  provisioningStuck: TenantHealthRow[];
}

/** A failed background job. The payload itself is never sent (§81). */
export interface FailedJobRecord {
  id: string;
  uuid: string;
  queue: string | null;
  connection: string | null;
  jobClass: string | null;
  attempts: number;
  failedAt: string | null;
  exceptionType: string | null;
  exceptionSummary: string | null;
  payloadBytes: number;
  payloadPropertyNames: string[];
}

export interface FailedJobsPayload {
  jobs: FailedJobRecord[];
  limit: number;
  system: PlatformSystemHealth;
}

export interface FailedJobRetryResult {
  uuid: string;
  requeued: boolean;
  exitCode: number;
  output: string;
  job: FailedJobRecord | null;
}

export interface EntitlementReconciliationRow {
  feature: string;
  override: boolean;
  plan: boolean | null;
  platformDefault: boolean | null;
  effective: boolean;
  state: 'stale' | 'redundant' | 'overrides-plan' | 'override-without-plan';
}

export interface EntitlementReconciliation {
  catalog: string[];
  planFeatures: Record<string, boolean>;
  overrides: EntitlementReconciliationRow[];
  stale: string[];
  redundant: string[];
  contradicting: string[];
  effective: Record<string, boolean>;
  pruned: string[];
  driftDetected: boolean;
  applied: boolean;
}

/** Presentation-only white-label overrides (master-prompt §37). */
export interface TenantBranding {
  appName: string;
  primaryColor: string;
  accentColor: string;
  logoUrl: string | null;
  faviconUrl: string | null;
  loginMessage: string | null;
  reportHeader: string | null;
  reportFooter: string | null;
  emailFromName: string;
  emailFromAddress: string | null;
  supportEmail: string | null;
  supportPhone: string | null;
}

export interface TenantDomainRecord {
  id: string;
  host: string;
  isPrimary: boolean;
  verifiedAt: string | null;
  createdAt?: string | null;
}

export interface TenantBrandingPayload {
  branding: TenantBranding;
  brandingOverridden: boolean;
  domains: TenantDomainRecord[];
  entitlements: { branding: boolean; customDomains: boolean };
  verificationRecord: string;
  /** Present on register/verify responses only. */
  verification?: { record: string; type: string; value: string; found?: string[]; verified?: boolean };
}

/** Public login-screen lookup: cosmetic only, never an identity claim. */
export interface PublicTenantContext extends TenantBranding {
  tenantId: string | null;
  tenantCode: string | null;
  matched: boolean;
  verified: boolean;
}

/** Tenant-owned integration registry entry (master-prompt §33/§44). */
export interface IntegrationSecretState {
  present: boolean;
  mask: string;
}

export type IntegrationProbe = 'tcp' | 'http' | 'config';
export type IntegrationStatus = 'active' | 'error' | 'unconfigured' | 'disabled';

export interface IntegrationCatalogEntry {
  type: string;
  label: string;
  feature: string | null;
  probe: IntegrationProbe;
  requiredKeys: string[];
  secretKeys: string[];
}

/** One recorded delivery attempt through an integration (real send). */
export interface IntegrationDeliveryRecord {
  id: string;
  event: string;
  status: 'sent' | 'failed' | 'skipped';
  target: string | null;
  detail: string | null;
  latencyMs: number | null;
  at: string | null;
}

export interface TenantIntegrationRecord {
  id: string;
  type: string;
  typeLabel: string;
  name: string;
  facilityId: string | null;
  config: Record<string, string>;
  /** Presence + mask only — secret values never leave the server. */
  secrets: Record<string, IntegrationSecretState>;
  status: IntegrationStatus;
  lastCheckedAt: string | null;
  lastError: string | null;
  /** Last real delivery attempts through this integration (newest first). */
  deliveryLog: IntegrationDeliveryRecord[];
  createdAt?: string | null;
}

export interface TenantIntegrationsPayload {
  catalog: IntegrationCatalogEntry[];
  entitlements: Record<string, boolean>;
  integrations: TenantIntegrationRecord[];
  integration?: TenantIntegrationRecord;
  probe?: { status: IntegrationStatus; detail: string; check: string };
  test?: { status: string; detail: string; latencyMs?: number | null };
}

export interface TenantRecord {
  id: string;
  name: string;
  /** White-label presentation name (tenant_brandings.app_name); null = account name is the brand. */
  brandName?: string | null;
  slug: string;
  /** Organization flavor: clinic and hospital share the portal today. */
  orgType: 'clinic' | 'hospital';
  tenantCode: string;
  subscriptionStatus: SubscriptionStatus;
  plan: Plan | null;
  trialEndsAt?: string | null;
  subscriptionEndsAt?: string | null;
  isActive: boolean;
  createdAt?: string;
  counts: TenantCounts;
  deployment: TenantDeployment;
}

export interface TenantLifecycleEntry {
  id: string;
  event: string;
  fromStatus?: string | null;
  toStatus?: string | null;
  actorId?: string | null;
  details?: { summary?: string; exportPath?: string } | null;
  at?: string;
}

export interface TenantUserRecord {
  id: string;
  name: string;
  email: string;
  role: string;
  isAdmin: boolean;
  active: boolean;
  loginEnabled: boolean;
  lastLogin?: string | null;
}

export interface TenantMembershipRecord {
  id: string;
  userId: string;
  userName?: string;
  role: string;
  isDefault: boolean;
}

export interface TenantFacilityRecord {
  id: string;
  name: string;
  address: string;
  phone: string;
  description?: string;
}

export interface PlatformAuditEntry {
  id: string;
  at?: string;
  actorName: string;
  action: string;
  actionLabel: string;
  module: string;
  subjectType: string;
  subjectId: string;
  tenantId?: string | null;
  details: string;
  ipAddress: string;
  status: 'success' | 'warning' | 'danger';
}

export interface Tenant360 extends TenantRecord {
  patientCount: number;
  dicomNodeCount: number;
  lifecycle: TenantLifecycleEntry[];
  users: TenantUserRecord[];
  memberships: TenantMembershipRecord[];
  facilities: TenantFacilityRecord[];
  entitlements: Entitlements;
  featureOverrides: { feature: string; enabled: boolean }[];
  audit: PlatformAuditEntry[];
  supportSessions: (SupportSessionInfo & { platformUserName?: string })[];
}

export interface PlatformOverviewStats {
  tenants: {
    total: number;
    active: number;
    trialing: number;
    suspended: number;
    expired: number;
    provisioning: number;
    offboarding: number;
    terminated: number;
    needsAttention: number;
  };
  commercial: {
    contractedMonthlyValue: number;
    currency: string;
    trialsExpiringIn7Days: number;
    newTenantsLast30Days: number;
  };
  usage: {
    studiesThisMonth: number;
    reportsThisMonth: number;
    users: number;
    storageBytes: number;
  };
  operations: {
    activeSupportSessions: number;
    failedJobs: number;
    recentLifecycleEvents: { id: string; tenantId: string; event: string; toStatus?: string | null; at?: string }[];
  };
}

export interface PlatformUserRecord {
  id: string;
  name: string;
  email: string;
  type: 'super_admin' | 'platform_admin';
  role: PlatformRole;
  capabilities: string[];
  isActive: boolean;
  lastLogin?: string | null;
}

export interface PlatformSupportSessionRecord extends SupportSessionInfo {
  platformUserName?: string;
  tenantName?: string;
  tenantCode?: string;
}

export interface UsageSummary {
  limits: Entitlements['limits'];
  current: Entitlements['usage'];
  monthly: Record<string, Record<string, number>>;
}

/** Tenant-side custom-domain entry (GET /settings/branding). */
export interface TenantBrandingDomainView {
  host: string;
  isPrimary: boolean;
  verified: boolean;
}

/**
 * Tenant-side white-label view — READ-ONLY. Branding is platform-managed;
 * clinic/hospital admins can see the presentation settings that govern their
 * portal and receive a change-request hint instead of an edit form.
 */
export interface TenantBrandingView {
  branding: TenantBranding;
  overridden: boolean;
  domains: TenantBrandingDomainView[];
  managedBy: 'platform';
  changeHint: string;
}

// ==================== Tenant RBAC (Roles & Permissions admin) ====================

/** One permission in the server catalog (app/Support/PermissionCatalog). */
export interface PermissionDef {
  /** Stable key — the security contract; never renamed. */
  name: string;
  label: string;
  /** Visual danger marker (delete/void/manage-access class actions). */
  dangerous: boolean;
  /** Permissions auto-required when this one is granted. */
  implies: string[];
}

/** Permissions grouped by catalog category, in display order. */
export interface PermissionGroup {
  group: string;
  permissions: PermissionDef[];
}

export interface AccessRoleRecord {
  id: string;
  /** Stable machine name. */
  name: string;
  displayName: string;
  description: string;
  /** One of the five platform-provisioned system roles. */
  system: boolean;
  /** Cannot be deleted (admin) — protected against tenant lockout. */
  undeletable: boolean;
  /** Active staff users holding this role. */
  users: number;
  permissions: string[];
}

/** Effective permission set + provenance for one staff member. */
export interface EffectiveAccess {
  userId: string;
  role: AppRole;
  permissions: string[];
  rolePermissions: string[];
  allowedOverrides: string[];
  deniedOverrides: string[];
}
