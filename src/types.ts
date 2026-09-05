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
  code: 'DX' | 'US' | 'CT' | 'MR' | 'MG';
  color: string;
  bufferMinutes: number;
  isActive: boolean;
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
  method: 'cash' | 'card' | 'bank' | 'mobile' | 'insurance';
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

export type StaffRole = 'admin' | 'radiologist' | 'technologist' | 'receptionist' | 'billing' | 'nurse';

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

export type UserPresenceStatus = 'available' | 'in_procedure' | 'reporting' | 'away' | 'busy';

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

