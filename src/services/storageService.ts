import {
  Appointment,
  Patient,
  Modality,
  Service,
  Referrer,
  ScreeningForm,
  ReportTemplate,
  Invoice,
  StaffUser,
  ClinicProfileSettings,
  DicomNodeConfig,
  NotificationTemplate,
  AuditLogEntry,
  DoctorDispatchLog,
  AppNotification,
  InventoryItem,
  InventoryTransaction,
  AdverseReactionReport,
} from '../types';
import {
  initialAppointments,
  initialPatients,
  initialModalities,
  initialServices,
  initialReferrers,
  initialScreeningForms,
  initialReportTemplates,
  initialInvoices,
  initialStaffUsers,
  initialClinicSettings,
  initialDicomNodes,
  initialNotificationTemplates,
  initialAuditLogs,
  initialDoctorDispatchLogs,
  initialAppNotifications,
  initialInventoryItems,
  initialInventoryTransactions,
  initialAdverseReactions,
} from '../data/mockData';

const STORAGE_KEYS = {
  APPOINTMENTS: 'adc_ris_appointments_v2',
  PATIENTS: 'adc_ris_patients_v2',
  MODALITIES: 'adc_ris_modalities_v2',
  SERVICES: 'adc_ris_services_v2',
  REFERRERS: 'adc_ris_referrers_v2',
  FORMS: 'adc_ris_forms_v2',
  TEMPLATES: 'adc_ris_templates_v2',
  INVOICES: 'adc_ris_invoices_v2',
  ROLE: 'adc_ris_user_role_v2',
  ACTIVE_TAB: 'adc_ris_active_tab_v2',
  STAFF_USERS: 'adc_ris_staff_users_v2',
  CLINIC_SETTINGS: 'adc_ris_clinic_settings_v2',
  DICOM_NODES: 'adc_ris_dicom_nodes_v2',
  NOTIFICATIONS: 'adc_ris_notifications_v2',
  AUDIT_LOGS: 'adc_ris_audit_logs_v2',
  DOCTOR_DISPATCHES: 'adc_ris_doctor_dispatches_v2',
  APP_NOTIFICATIONS: 'adc_ris_live_notifications_v2',
  INVENTORY_ITEMS: 'adc_ris_inventory_items_v2',
  INVENTORY_TRANSACTIONS: 'adc_ris_inventory_transactions_v2',
  ADVERSE_REACTIONS: 'adc_ris_adverse_reactions_v2',
};

// Safe JSON parser helper
function loadFromStorage<T>(key: string, defaultValue: T): T {
  try {
    const item = localStorage.getItem(key);
    if (!item) return defaultValue;
    return JSON.parse(item) as T;
  } catch (error) {
    console.warn(`[StorageService] Failed to parse item for key "${key}", falling back to default:`, error);
    return defaultValue;
  }
}

// Safe JSON saver helper
function saveToStorage<T>(key: string, value: T): void {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch (error) {
    console.error(`[StorageService] Failed to persist key "${key}":`, error);
  }
}

export const StorageService = {
  // Load initial data with localStorage fallback
  loadAppointments: (): Appointment[] => loadFromStorage(STORAGE_KEYS.APPOINTMENTS, initialAppointments),
  saveAppointments: (data: Appointment[]) => saveToStorage(STORAGE_KEYS.APPOINTMENTS, data),

  loadPatients: (): Patient[] => loadFromStorage(STORAGE_KEYS.PATIENTS, initialPatients),
  savePatients: (data: Patient[]) => saveToStorage(STORAGE_KEYS.PATIENTS, data),

  loadModalities: (): Modality[] => loadFromStorage(STORAGE_KEYS.MODALITIES, initialModalities),
  saveModalities: (data: Modality[]) => saveToStorage(STORAGE_KEYS.MODALITIES, data),

  loadServices: (): Service[] => loadFromStorage(STORAGE_KEYS.SERVICES, initialServices),
  saveServices: (data: Service[]) => saveToStorage(STORAGE_KEYS.SERVICES, data),

  loadReferrers: (): Referrer[] => loadFromStorage(STORAGE_KEYS.REFERRERS, initialReferrers),
  saveReferrers: (data: Referrer[]) => saveToStorage(STORAGE_KEYS.REFERRERS, data),

  loadForms: (): ScreeningForm[] => loadFromStorage(STORAGE_KEYS.FORMS, initialScreeningForms),
  saveForms: (data: ScreeningForm[]) => saveToStorage(STORAGE_KEYS.FORMS, data),

  loadTemplates: (): ReportTemplate[] => loadFromStorage(STORAGE_KEYS.TEMPLATES, initialReportTemplates),
  saveTemplates: (data: ReportTemplate[]) => saveToStorage(STORAGE_KEYS.TEMPLATES, data),

  loadInvoices: (): Invoice[] => loadFromStorage(STORAGE_KEYS.INVOICES, initialInvoices),
  saveInvoices: (data: Invoice[]) => saveToStorage(STORAGE_KEYS.INVOICES, data),

  loadStaffUsers: (): StaffUser[] => loadFromStorage(STORAGE_KEYS.STAFF_USERS, initialStaffUsers),
  saveStaffUsers: (data: StaffUser[]) => saveToStorage(STORAGE_KEYS.STAFF_USERS, data),

  loadClinicSettings: (): ClinicProfileSettings => loadFromStorage(STORAGE_KEYS.CLINIC_SETTINGS, initialClinicSettings),
  saveClinicSettings: (data: ClinicProfileSettings) => saveToStorage(STORAGE_KEYS.CLINIC_SETTINGS, data),

  loadDicomNodes: (): DicomNodeConfig[] => loadFromStorage(STORAGE_KEYS.DICOM_NODES, initialDicomNodes),
  saveDicomNodes: (data: DicomNodeConfig[]) => saveToStorage(STORAGE_KEYS.DICOM_NODES, data),

  loadNotificationTemplates: (): NotificationTemplate[] => loadFromStorage(STORAGE_KEYS.NOTIFICATIONS, initialNotificationTemplates),
  saveNotificationTemplates: (data: NotificationTemplate[]) => saveToStorage(STORAGE_KEYS.NOTIFICATIONS, data),

  loadAuditLogs: (): AuditLogEntry[] => loadFromStorage(STORAGE_KEYS.AUDIT_LOGS, initialAuditLogs),
  saveAuditLogs: (data: AuditLogEntry[]) => saveToStorage(STORAGE_KEYS.AUDIT_LOGS, data),

  loadDoctorDispatches: (): DoctorDispatchLog[] => loadFromStorage(STORAGE_KEYS.DOCTOR_DISPATCHES, initialDoctorDispatchLogs),
  saveDoctorDispatches: (data: DoctorDispatchLog[]) => saveToStorage(STORAGE_KEYS.DOCTOR_DISPATCHES, data),

  loadAppNotifications: (): AppNotification[] => loadFromStorage(STORAGE_KEYS.APP_NOTIFICATIONS, initialAppNotifications),
  saveAppNotifications: (data: AppNotification[]) => saveToStorage(STORAGE_KEYS.APP_NOTIFICATIONS, data),

  loadInventoryItems: (): InventoryItem[] => loadFromStorage(STORAGE_KEYS.INVENTORY_ITEMS, initialInventoryItems),
  saveInventoryItems: (data: InventoryItem[]) => saveToStorage(STORAGE_KEYS.INVENTORY_ITEMS, data),

  loadInventoryTransactions: (): InventoryTransaction[] => loadFromStorage(STORAGE_KEYS.INVENTORY_TRANSACTIONS, initialInventoryTransactions),
  saveInventoryTransactions: (data: InventoryTransaction[]) => saveToStorage(STORAGE_KEYS.INVENTORY_TRANSACTIONS, data),

  loadAdverseReactions: (): AdverseReactionReport[] => loadFromStorage(STORAGE_KEYS.ADVERSE_REACTIONS, initialAdverseReactions),
  saveAdverseReactions: (data: AdverseReactionReport[]) => saveToStorage(STORAGE_KEYS.ADVERSE_REACTIONS, data),

  loadRole: (defaultRole: 'admin' | 'receptionist' | 'technologist' | 'radiologist' | 'patient' = 'admin') =>
    loadFromStorage(STORAGE_KEYS.ROLE, defaultRole),
  saveRole: (role: string) => saveToStorage(STORAGE_KEYS.ROLE, role),

  // Reset everything to pristine factory initial state
  resetToFactoryDefaults: () => {
    try {
      localStorage.removeItem(STORAGE_KEYS.APPOINTMENTS);
      localStorage.removeItem(STORAGE_KEYS.PATIENTS);
      localStorage.removeItem(STORAGE_KEYS.MODALITIES);
      localStorage.removeItem(STORAGE_KEYS.SERVICES);
      localStorage.removeItem(STORAGE_KEYS.REFERRERS);
      localStorage.removeItem(STORAGE_KEYS.FORMS);
      localStorage.removeItem(STORAGE_KEYS.TEMPLATES);
      localStorage.removeItem(STORAGE_KEYS.INVOICES);
      localStorage.removeItem(STORAGE_KEYS.STAFF_USERS);
      localStorage.removeItem(STORAGE_KEYS.CLINIC_SETTINGS);
      localStorage.removeItem(STORAGE_KEYS.DICOM_NODES);
      localStorage.removeItem(STORAGE_KEYS.NOTIFICATIONS);
      localStorage.removeItem(STORAGE_KEYS.AUDIT_LOGS);
      localStorage.removeItem(STORAGE_KEYS.DOCTOR_DISPATCHES);
      localStorage.removeItem(STORAGE_KEYS.APP_NOTIFICATIONS);
      localStorage.removeItem(STORAGE_KEYS.INVENTORY_ITEMS);
      localStorage.removeItem(STORAGE_KEYS.INVENTORY_TRANSACTIONS);
      localStorage.removeItem(STORAGE_KEYS.ADVERSE_REACTIONS);
      localStorage.removeItem(STORAGE_KEYS.ROLE);
      localStorage.removeItem(STORAGE_KEYS.ACTIVE_TAB);
    } catch (e) {
      console.error('[StorageService] Error resetting storage:', e);
    }
  },

  // Export full JSON database snapshot
  exportDatabaseSnapshot: () => {
    return {
      exportedAt: new Date().toISOString(),
      system: 'Amad Diagnostic Centre RIS Portal',
      version: '2.0.0',
      data: {
        appointments: StorageService.loadAppointments(),
        patients: StorageService.loadPatients(),
        modalities: StorageService.loadModalities(),
        services: StorageService.loadServices(),
        referrers: StorageService.loadReferrers(),
        forms: StorageService.loadForms(),
        templates: StorageService.loadTemplates(),
        invoices: StorageService.loadInvoices(),
        staffUsers: StorageService.loadStaffUsers(),
        clinicSettings: StorageService.loadClinicSettings(),
        dicomNodes: StorageService.loadDicomNodes(),
        notificationTemplates: StorageService.loadNotificationTemplates(),
        auditLogs: StorageService.loadAuditLogs(),
        doctorDispatches: StorageService.loadDoctorDispatches(),
        appNotifications: StorageService.loadAppNotifications(),
        inventoryItems: StorageService.loadInventoryItems(),
        inventoryTransactions: StorageService.loadInventoryTransactions(),
        adverseReactions: StorageService.loadAdverseReactions(),
      },
    };
  },

  // Import and restore full JSON database snapshot
  importDatabaseSnapshot: (snapshot: any): boolean => {
    try {
      if (!snapshot || !snapshot.data) return false;
      const {
        appointments,
        patients,
        modalities,
        services,
        referrers,
        forms,
        templates,
        invoices,
        staffUsers,
        clinicSettings,
        dicomNodes,
        notificationTemplates,
        auditLogs,
        doctorDispatches,
        appNotifications,
        inventoryItems,
        inventoryTransactions,
        adverseReactions,
      } = snapshot.data;
      if (appointments) StorageService.saveAppointments(appointments);
      if (patients) StorageService.savePatients(patients);
      if (modalities) StorageService.saveModalities(modalities);
      if (services) StorageService.saveServices(services);
      if (referrers) StorageService.saveReferrers(referrers);
      if (forms) StorageService.saveForms(forms);
      if (templates) StorageService.saveTemplates(templates);
      if (invoices) StorageService.saveInvoices(invoices);
      if (staffUsers) StorageService.saveStaffUsers(staffUsers);
      if (clinicSettings) StorageService.saveClinicSettings(clinicSettings);
      if (dicomNodes) StorageService.saveDicomNodes(dicomNodes);
      if (notificationTemplates) StorageService.saveNotificationTemplates(notificationTemplates);
      if (auditLogs) StorageService.saveAuditLogs(auditLogs);
      if (doctorDispatches) StorageService.saveDoctorDispatches(doctorDispatches);
      if (appNotifications) StorageService.saveAppNotifications(appNotifications);
      if (inventoryItems) StorageService.saveInventoryItems(inventoryItems);
      if (inventoryTransactions) StorageService.saveInventoryTransactions(inventoryTransactions);
      if (adverseReactions) StorageService.saveAdverseReactions(adverseReactions);
      return true;
    } catch (err) {
      console.error('[StorageService] Import failed:', err);
      return false;
    }
  },
};
