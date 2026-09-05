import React, { useState, useEffect } from 'react';
import {
  ActiveTab,
  Appointment,
  Patient,
  Modality,
  Service,
  Referrer,
  ScreeningForm,
  ReportTemplate,
  Invoice,
  InvoiceItem,
  RadiologyReport,
  StudyScreeningAnswer,
  DoseLog,
  InvoicePayment,
  StaffUser,
  ClinicProfileSettings,
  DicomNodeConfig,
  NotificationTemplate,
  AuditLogEntry,
  DoctorDispatchLog,
  AppNotification,
  InventoryItem,
  InventoryTransaction,
  AdverseReactionReport
} from './types';
import { StorageService } from './services/storageService';

import { Navbar } from './components/Navbar';
import { DashboardView } from './components/DashboardView';
import { CheckinBoardView } from './components/CheckinBoardView';
import { TechnologistView } from './components/TechnologistView';
import { ReportingView } from './components/ReportingView';
import { BillingView } from './components/BillingView';
import { QueueBoardView } from './components/QueueBoardView';
import { InventoryView } from './components/InventoryView';
import { MasterDataView } from './components/MasterDataView';
import { DoctorNetworkView } from './components/DoctorNetworkView';
import { SettingsView } from './components/SettingsView';

import { ScreeningModal } from './components/ScreeningModal';
import { DoseCaptureModal } from './components/DoseCaptureModal';
import { NewBookingModal } from './components/NewBookingModal';
import { NotificationCenter } from './components/NotificationCenter';
import { TerminalLockModal } from './components/TerminalLockModal';

export const App: React.FC = () => {
  const [activeTab, setActiveTab] = useState<ActiveTab>('dashboard');
  const [role, setRole] = useState<'admin' | 'receptionist' | 'technologist' | 'radiologist' | 'patient'>(() =>
    StorageService.loadRole('admin')
  );

  // Core domain state with local storage persistence
  const [appointments, setAppointments] = useState<Appointment[]>(() => StorageService.loadAppointments());
  const [patients, setPatients] = useState<Patient[]>(() => StorageService.loadPatients());
  const [modalities, setModalities] = useState<Modality[]>(() => StorageService.loadModalities());
  const [services, setServices] = useState<Service[]>(() => StorageService.loadServices());
  const [referrers, setReferrers] = useState<Referrer[]>(() => StorageService.loadReferrers());
  const [forms, setForms] = useState<ScreeningForm[]>(() => StorageService.loadForms());
  const [templates, setTemplates] = useState<ReportTemplate[]>(() => StorageService.loadTemplates());
  const [invoices, setInvoices] = useState<Invoice[]>(() => StorageService.loadInvoices());

  // Settings & Doctor Network state
  const [staffUsers, setStaffUsers] = useState<StaffUser[]>(() => StorageService.loadStaffUsers());
  const [clinicSettings, setClinicSettings] = useState<ClinicProfileSettings>(() => StorageService.loadClinicSettings());
  const [dicomNodes, setDicomNodes] = useState<DicomNodeConfig[]>(() => StorageService.loadDicomNodes());
  const [notificationTemplates, setNotificationTemplates] = useState<NotificationTemplate[]>(() => StorageService.loadNotificationTemplates());
  const [auditLogs, setAuditLogs] = useState<AuditLogEntry[]>(() => StorageService.loadAuditLogs());
  const [doctorDispatches, setDoctorDispatches] = useState<DoctorDispatchLog[]>(() => StorageService.loadDoctorDispatches());
  const [notifications, setNotifications] = useState<AppNotification[]>(() => StorageService.loadAppNotifications());

  // Tier 3: Contrast Media & Clinical Consumables Inventory state
  const [inventoryItems, setInventoryItems] = useState<InventoryItem[]>(() => StorageService.loadInventoryItems());
  const [inventoryTransactions, setInventoryTransactions] = useState<InventoryTransaction[]>(() => StorageService.loadInventoryTransactions());
  const [adverseReactions, setAdverseReactions] = useState<AdverseReactionReport[]>(() => StorageService.loadAdverseReactions());

  // UI Drawer / Security Modal states
  const [notificationCenterOpen, setNotificationCenterOpen] = useState(false);
  const [isTerminalLocked, setIsTerminalLocked] = useState(false);

  // Automatic state synchronization to persistent storage
  useEffect(() => {
    StorageService.saveAppointments(appointments);
  }, [appointments]);

  useEffect(() => {
    StorageService.savePatients(patients);
  }, [patients]);

  useEffect(() => {
    StorageService.saveModalities(modalities);
  }, [modalities]);

  useEffect(() => {
    StorageService.saveServices(services);
  }, [services]);

  useEffect(() => {
    StorageService.saveReferrers(referrers);
  }, [referrers]);

  useEffect(() => {
    StorageService.saveForms(forms);
  }, [forms]);

  useEffect(() => {
    StorageService.saveTemplates(templates);
  }, [templates]);

  useEffect(() => {
    StorageService.saveInvoices(invoices);
  }, [invoices]);

  useEffect(() => {
    StorageService.saveRole(role);
  }, [role]);

  useEffect(() => {
    StorageService.saveStaffUsers(staffUsers);
  }, [staffUsers]);

  useEffect(() => {
    StorageService.saveClinicSettings(clinicSettings);
  }, [clinicSettings]);

  useEffect(() => {
    StorageService.saveDicomNodes(dicomNodes);
  }, [dicomNodes]);

  useEffect(() => {
    StorageService.saveNotificationTemplates(notificationTemplates);
  }, [notificationTemplates]);

  useEffect(() => {
    StorageService.saveAuditLogs(auditLogs);
  }, [auditLogs]);

  useEffect(() => {
    StorageService.saveDoctorDispatches(doctorDispatches);
  }, [doctorDispatches]);

  useEffect(() => {
    StorageService.saveAppNotifications(notifications);
  }, [notifications]);

  useEffect(() => {
    StorageService.saveInventoryItems(inventoryItems);
  }, [inventoryItems]);

  useEffect(() => {
    StorageService.saveInventoryTransactions(inventoryTransactions);
  }, [inventoryTransactions]);

  useEffect(() => {
    StorageService.saveAdverseReactions(adverseReactions);
  }, [adverseReactions]);

  // Clinical Notification Handlers
  const handleAddNotification = (
    notif: Omit<AppNotification, 'id' | 'timestamp' | 'isRead'> & { timestamp?: string; isRead?: boolean }
  ) => {
    const timeNow = notif.timestamp || 'Just now';
    const newNotif: AppNotification = {
      id: `notif-${Date.now()}-${Math.floor(Math.random() * 1000)}`,
      timestamp: timeNow,
      isRead: notif.isRead ?? false,
      ...notif,
    };
    setNotifications(prev => [newNotif, ...prev]);
  };

  const handleMarkNotifAsRead = (id: string) => {
    setNotifications(prev => prev.map(n => (n.id === id ? { ...n, isRead: true } : n)));
  };

  const handleMarkAllNotifsAsRead = () => {
    setNotifications(prev => prev.map(n => ({ ...n, isRead: true })));
  };

  const handleClearAllNotifs = () => {
    setNotifications([]);
  };

  const handleDeleteNotif = (id: string) => {
    setNotifications(prev => prev.filter(n => n.id !== id));
  };

  const handleTriggerTestAlert = () => {
    handleAddNotification({
      title: 'EMERGENCY STAT: Cardiac CT Angiogram',
      message: 'Trauma protocol activated for Emergency Token CT-09. Immediate radiologist clearance needed.',
      category: 'stat',
      priority: 'critical',
      tokenNumber: 'CT-09',
      patientName: 'Malik Tariq (ICU Referral)',
      targetTab: 'technologist',
      actionLabel: 'View STAT Worklist',
    });
  };

  // Master Data Configuration Handlers
  const handleResetFactoryDefaults = () => {
    if (confirm('Are you sure you want to reset all records to the original factory configuration? All changes and new records will be refreshed.')) {
      StorageService.resetToFactoryDefaults();
      setAppointments(StorageService.loadAppointments());
      setPatients(StorageService.loadPatients());
      setModalities(StorageService.loadModalities());
      setServices(StorageService.loadServices());
      setReferrers(StorageService.loadReferrers());
      setForms(StorageService.loadForms());
      setTemplates(StorageService.loadTemplates());
      setInvoices(StorageService.loadInvoices());
      setStaffUsers(StorageService.loadStaffUsers());
      setClinicSettings(StorageService.loadClinicSettings());
      setDicomNodes(StorageService.loadDicomNodes());
      setNotificationTemplates(StorageService.loadNotificationTemplates());
      setAuditLogs(StorageService.loadAuditLogs());
      setDoctorDispatches(StorageService.loadDoctorDispatches());
      setNotifications(StorageService.loadAppNotifications());
      setInventoryItems(StorageService.loadInventoryItems());
      setInventoryTransactions(StorageService.loadInventoryTransactions());
      setAdverseReactions(StorageService.loadAdverseReactions());
      alert('System successfully restored to default factory dataset.');
    }
  };

  const handleExportBackup = () => {
    const backup = StorageService.exportDatabaseSnapshot();
    const dataStr = 'data:text/json;charset=utf-8,' + encodeURIComponent(JSON.stringify(backup, null, 2));
    const downloadAnchor = document.createElement('a');
    downloadAnchor.setAttribute('href', dataStr);
    downloadAnchor.setAttribute('download', `ADC_RIS_Database_Backup_${new Date().toISOString().split('T')[0]}.json`);
    document.body.appendChild(downloadAnchor);
    downloadAnchor.click();
    downloadAnchor.remove();
  };

  const handleImportBackup = (snapshot: any) => {
    const success = StorageService.importDatabaseSnapshot(snapshot);
    if (success) {
      setAppointments(StorageService.loadAppointments());
      setPatients(StorageService.loadPatients());
      setModalities(StorageService.loadModalities());
      setServices(StorageService.loadServices());
      setReferrers(StorageService.loadReferrers());
      setForms(StorageService.loadForms());
      setTemplates(StorageService.loadTemplates());
      setInvoices(StorageService.loadInvoices());
      setStaffUsers(StorageService.loadStaffUsers());
      setClinicSettings(StorageService.loadClinicSettings());
      setDicomNodes(StorageService.loadDicomNodes());
      setNotificationTemplates(StorageService.loadNotificationTemplates());
      setAuditLogs(StorageService.loadAuditLogs());
      setDoctorDispatches(StorageService.loadDoctorDispatches());
      setNotifications(StorageService.loadAppNotifications());
      setInventoryItems(StorageService.loadInventoryItems());
      setInventoryTransactions(StorageService.loadInventoryTransactions());
      setAdverseReactions(StorageService.loadAdverseReactions());
      alert('Database backup restored successfully!');
    } else {
      alert('Failed to import database snapshot. Please ensure the file format is valid.');
    }
  };

  // Master Data Configuration Handlers
  const handleAddService = (newSvc: Omit<Service, 'id'>) => {
    const nextId = Math.max(...services.map(s => s.id), 0) + 1;
    setServices(prev => [...prev, { ...newSvc, id: nextId }]);
  };

  const handleUpdateService = (updatedSvc: Service) => {
    setServices(prev => prev.map(s => (s.id === updatedSvc.id ? updatedSvc : s)));
  };

  const handleDeleteService = (serviceId: number) => {
    setServices(prev => prev.filter(s => s.id !== serviceId));
  };

  const handleAddModality = (newMod: Omit<Modality, 'id'>) => {
    const nextId = Math.max(...modalities.map(m => m.id), 0) + 1;
    setModalities(prev => [...prev, { ...newMod, id: nextId }]);
  };

  const handleUpdateModality = (updatedMod: Modality) => {
    setModalities(prev => prev.map(m => (m.id === updatedMod.id ? updatedMod : m)));
  };

  const handleAddReferrer = (newRef: Omit<Referrer, 'id'>) => {
    const nextId = Math.max(...referrers.map(r => r.id), 0) + 1;
    setReferrers(prev => [...prev, { ...newRef, id: nextId }]);
  };

  const handleUpdateReferrer = (updatedRef: Referrer) => {
    setReferrers(prev => prev.map(r => (r.id === updatedRef.id ? updatedRef : r)));
  };

  const handleDeleteReferrer = (refId: number) => {
    setReferrers(prev => prev.filter(r => r.id !== refId));
  };

  const handleAddForm = (newForm: ScreeningForm) => {
    setForms(prev => [...prev, newForm]);
  };

  const handleUpdateForm = (updatedForm: ScreeningForm) => {
    setForms(prev => prev.map(f => (f.id === updatedForm.id ? updatedForm : f)));
  };

  const handleAddTemplate = (newTpl: ReportTemplate) => {
    setTemplates(prev => [...prev, newTpl]);
  };

  const handleUpdateTemplate = (updatedTpl: ReportTemplate) => {
    setTemplates(prev => prev.map(t => (t.id === updatedTpl.id ? updatedTpl : t)));
  };

  const handleDeleteTemplate = (templateId: string) => {
    setTemplates(prev => prev.filter(t => t.id !== templateId));
  };

  // Staff Users Handlers
  const handleAddStaffUser = (newUser: Omit<StaffUser, 'id'>) => {
    const newId = `usr_${Date.now()}`;
    setStaffUsers(prev => [...prev, { ...newUser, id: newId }]);
    handleAddAuditLog({
      user: 'System Administrator',
      role: 'Administrator',
      action: 'Create Staff User Account',
      module: 'RBAC & Access Control',
      details: `Provisioned user account for ${newUser.name} (${newUser.role})`,
      status: 'success'
    });
  };

  const handleUpdateStaffUser = (updatedUser: StaffUser) => {
    setStaffUsers(prev => prev.map(u => (u.id === updatedUser.id ? updatedUser : u)));
    handleAddAuditLog({
      user: 'System Administrator',
      role: 'Administrator',
      action: 'Update Staff User Permissions',
      module: 'RBAC & Access Control',
      details: `Updated profile and access permissions for ${updatedUser.name}`,
      status: 'success'
    });
  };

  const handleDeleteStaffUser = (userId: string) => {
    const user = staffUsers.find(u => u.id === userId);
    setStaffUsers(prev => prev.filter(u => u.id !== userId));
    handleAddAuditLog({
      user: 'System Administrator',
      role: 'Administrator',
      action: 'Revoke Staff User Account',
      module: 'RBAC & Access Control',
      details: `Revoked access for user ${user?.name || userId}`,
      status: 'warning'
    });
  };

  // Clinic Settings Handler
  const handleUpdateClinicSettings = (newSettings: ClinicProfileSettings) => {
    setClinicSettings(newSettings);
    handleAddAuditLog({
      user: 'System Administrator',
      role: 'Administrator',
      action: 'Update Clinic Master Profile',
      module: 'System Settings',
      details: `Updated clinic registration and profile for ${newSettings.name}`,
      status: 'success'
    });
  };

  // DICOM Nodes Handlers
  const handleAddDicomNode = (newNode: Omit<DicomNodeConfig, 'id'>) => {
    const newId = `node_${Date.now()}`;
    setDicomNodes(prev => [...prev, { ...newNode, id: newId }]);
    handleAddAuditLog({
      user: 'PACS Administrator',
      role: 'Administrator',
      action: 'Register DICOM Modality Node',
      module: 'PACS / DICOM Networking',
      details: `Added DICOM AE Title ${newNode.aeTitle} (${newNode.ipAddress}:${newNode.port})`,
      status: 'success'
    });
  };

  const handleUpdateDicomNode = (updatedNode: DicomNodeConfig) => {
    setDicomNodes(prev => prev.map(n => (n.id === updatedNode.id ? updatedNode : n)));
    handleAddAuditLog({
      user: 'PACS Administrator',
      role: 'Administrator',
      action: 'Update DICOM Node Configuration',
      module: 'PACS / DICOM Networking',
      details: `Updated DICOM AE Title ${updatedNode.aeTitle} (${updatedNode.ipAddress}:${updatedNode.port})`,
      status: 'success'
    });
  };

  const handleDeleteDicomNode = (nodeId: string) => {
    const node = dicomNodes.find(n => n.id === nodeId);
    setDicomNodes(prev => prev.filter(n => n.id !== nodeId));
    handleAddAuditLog({
      user: 'PACS Administrator',
      role: 'Administrator',
      action: 'Delete DICOM Node Configuration',
      module: 'PACS / DICOM Networking',
      details: `Deleted DICOM node ${node?.aeTitle || nodeId}`,
      status: 'warning'
    });
  };

  // Notification Template Handler
  const handleUpdateNotificationTemplate = (updatedTemplate: NotificationTemplate) => {
    setNotificationTemplates(prev => prev.map(t => (t.id === updatedTemplate.id ? updatedTemplate : t)));
    handleAddAuditLog({
      user: 'System Administrator',
      role: 'Administrator',
      action: 'Update Notification Template',
      module: 'Communications Gateway',
      details: `Updated template ${updatedTemplate.name} for ${updatedTemplate.channel.toUpperCase()}`,
      status: 'success'
    });
  };

  // Audit Log Handler
  const handleAddAuditLog = (log: Omit<AuditLogEntry, 'id' | 'timestamp'>) => {
    const newEntry: AuditLogEntry = {
      ...log,
      id: `audit_${Date.now()}`,
      timestamp: new Date().toISOString().replace('T', ' ').substring(0, 19),
    };
    setAuditLogs(prev => [newEntry, ...prev]);
  };

  // Doctor Dispatch Handler
  const handleAddDoctorDispatch = (dispatch: Omit<DoctorDispatchLog, 'id' | 'sentAt'>) => {
    const newDispatch: DoctorDispatchLog = {
      ...dispatch,
      id: `disp_${Date.now()}`,
      sentAt: new Date().toISOString().replace('T', ' ').substring(0, 19),
    };
    setDoctorDispatches(prev => [newDispatch, ...prev]);

    // Also add to audit logs
    handleAddAuditLog({
      user: 'Doctor Network Dispatcher',
      role: 'Staff / Gateway',
      action: 'Report Dispatched to Doctor',
      module: 'Doctor Network',
      details: `Report for token ${dispatch.tokenNumber} sent to ${dispatch.referrerName} via ${dispatch.channel.toUpperCase()}.`,
      status: 'success',
    });

    handleAddNotification({
      title: `Doctor Dispatch: ${dispatch.channel.toUpperCase()}`,
      message: `Report sent to ${dispatch.referrerName} for patient ${dispatch.patientName} (${dispatch.studyName}).`,
      category: 'dispatch',
      priority: 'low',
      tokenNumber: dispatch.tokenNumber,
      patientName: dispatch.patientName,
      targetTab: 'doctors',
      actionLabel: 'View Dispatch Hub',
    });
  };

  // Active selections & modal triggers
  const [selectedAppointment, setSelectedAppointment] = useState<Appointment | null>(null);
  const [screeningModalApt, setScreeningModalApt] = useState<Appointment | null>(null);
  const [doseModalApt, setDoseModalApt] = useState<Appointment | null>(null);
  const [bookingModalOpen, setBookingModalOpen] = useState(false);

  // Sync selected appointment reference if appointments change
  const currentSelectedAppointment = appointments.find(a => a.id === selectedAppointment?.id) || selectedAppointment;

  // Workflow Handlers
  const handleCheckIn = (aptId: string) => {
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const apt = appointments.find(a => a.id === aptId);
    setAppointments(prev =>
      prev.map(a => (a.id === aptId ? { ...a, workflowState: 'checked_in', checkedInAt: timeNow } : a))
    );

    if (apt) {
      handleAddNotification({
        title: `Patient Checked In (#${apt.tokenNumber})`,
        message: `${apt.patient.name} has arrived at the reception desk. Token ${apt.tokenNumber} is now ready for preparation in ${apt.modality.name}.`,
        category: 'workflow',
        priority: apt.priority === 'stat' ? 'critical' : 'medium',
        appointmentId: apt.id,
        tokenNumber: apt.tokenNumber,
        patientName: apt.patient.name,
        targetTab: 'technologist',
        actionLabel: 'View Worklist',
      });
    }
  };

  const handleMarkNoShow = (aptId: string) => {
    setAppointments(prev =>
      prev.map(a => (a.id === aptId ? { ...a, workflowState: 'no_show', cancelReason: 'Patient failed to arrive' } : a))
    );
  };

  const handleStartPreparing = (aptId: string) => {
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setAppointments(prev =>
      prev.map(a => (a.id === aptId ? { ...a, workflowState: 'preparing', preparingAt: timeNow } : a))
    );
  };

  const handleStartAcquisition = (apt: Appointment) => {
    if (apt.screeningRequired && !apt.screeningCleared) {
      alert('Safety Notice: Please complete the safety questionnaire before starting image acquisition.');
      setScreeningModalApt(apt);
      return;
    }
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setAppointments(prev =>
      prev.map(a => (a.id === apt.id ? { ...a, workflowState: 'in_progress', inProgressAt: timeNow } : a))
    );
  };

  const handleSaveScreening = (aptId: string, answers: StudyScreeningAnswer[], isCleared: boolean) => {
    setAppointments(prev =>
      prev.map(a =>
        a.id === aptId
          ? {
              ...a,
              screeningAnswers: answers,
              screeningCleared: isCleared,
            }
          : a
      )
    );
  };

  const handleCompleteAcquisition = (aptId: string, doseLog: DoseLog) => {
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const apt = appointments.find(a => a.id === aptId);
    setAppointments(prev =>
      prev.map(a =>
        a.id === aptId
          ? {
              ...a,
              workflowState: 'acquired',
              acquiredAt: timeNow,
              doseLog,
            }
          : a
      )
    );

    // If contrast media was administered during acquisition, deduct 1 unit and log clinical inventory transaction
    if (doseLog.contrastAgent) {
      const agentName = doseLog.contrastAgent.toLowerCase();
      const matchedItem = inventoryItems.find(
        item =>
          (item.category === 'contrast_ct' || item.category === 'contrast_mri') &&
          (item.name.toLowerCase().includes(agentName) || agentName.includes(item.name.toLowerCase()) || agentName.includes(item.genericName.toLowerCase()))
      );

      if (matchedItem && matchedItem.currentStock > 0) {
        const consumedQty = 1;
        const newStock = Math.max(0, matchedItem.currentStock - consumedQty);
        setInventoryItems(prev =>
          prev.map(it => (it.id === matchedItem.id ? { ...it, currentStock: newStock } : it))
        );

        const newTx: InventoryTransaction = {
          id: `tx-${Date.now()}`,
          itemId: matchedItem.id,
          itemName: matchedItem.name,
          type: 'usage_study',
          quantity: consumedQty,
          batchNumber: matchedItem.batches[0]?.batchNumber || 'BATCH-AUTO',
          appointmentId: apt?.id,
          tokenNumber: apt?.tokenNumber,
          patientName: apt?.patient.name,
          performedBy: doseLog.recordedBy || 'Technologist',
          notes: `Auto-deducted during ${apt?.modality.code || 'CT'} acquisition (${doseLog.contrastVolumeMl || 0} mL administered).`,
          timestamp: new Date().toLocaleString(),
        };
        setInventoryTransactions(prev => [newTx, ...prev]);

        if (newStock <= matchedItem.minThreshold) {
          handleAddNotification({
            title: `Low Stock Warning: ${matchedItem.name}`,
            message: `${matchedItem.name} stock has reached ${newStock} ${matchedItem.unit} (threshold: ${matchedItem.minThreshold}). Reorder recommended.`,
            category: 'general',
            priority: newStock === 0 ? 'critical' : 'high',
            targetTab: 'inventory',
            actionLabel: 'Open Inventory & Restock',
          });
        }
      }
    }

    if (apt) {
      handleAddNotification({
        title: `Acquisition Complete (#${apt.tokenNumber})`,
        message: `${apt.modality.code} imaging completed for ${apt.patient.name}. DICOM series transferred to PACS and ready for reporting.`,
        category: 'workflow',
        priority: apt.priority === 'stat' ? 'high' : 'medium',
        appointmentId: apt.id,
        tokenNumber: apt.tokenNumber,
        patientName: apt.patient.name,
        targetTab: 'reporting',
        actionLabel: 'Open Diagnostic Report',
      });
    }
  };

  const handleCancelStudy = (aptId: string, reason: string) => {
    const apt = appointments.find(a => a.id === aptId);
    setAppointments(prev =>
      prev.map(a => (a.id === aptId ? { ...a, workflowState: 'cancelled', cancelReason: reason } : a))
    );
    handleAddAuditLog({
      user: 'Front Desk Admin',
      role: 'Receptionist',
      action: 'Cancel Examination Study',
      module: 'Scheduling & Front Desk',
      details: `Cancelled appointment #${apt?.tokenNumber} for ${apt?.patient?.name}. Reason: ${reason}`,
      status: 'warning',
    });
  };

  const handleUpdateAppointment = (aptId: string, updates: Partial<Appointment>) => {
    setAppointments(prev =>
      prev.map(a => (a.id === aptId ? { ...a, ...updates } : a))
    );
  };

  const handleRejectToTech = (aptId: string, reason: string) => {
    const apt = appointments.find(a => a.id === aptId);
    setAppointments(prev =>
      prev.map(a => (a.id === aptId ? { ...a, workflowState: 'preparing', rejectReason: reason } : a))
    );
    handleAddAuditLog({
      user: 'Dr. Shahzad Mir, FRCR',
      role: 'Radiologist',
      action: 'Quality Reject to Technologist',
      module: 'Radiology Reporting',
      details: `Rejected study #${apt?.tokenNumber} (${apt?.service?.name}) back to Technologist. Reason: ${reason}`,
      status: 'warning',
    });

    if (apt) {
      handleAddNotification({
        title: `Quality Rejection Alert (#${apt.tokenNumber})`,
        message: `Dr. Shahzad Mir rejected study #${apt.tokenNumber} back for repeat scan/technologist review: "${reason}"`,
        category: 'stat',
        priority: 'high',
        appointmentId: apt.id,
        tokenNumber: apt.tokenNumber,
        patientName: apt.patient.name,
        targetTab: 'technologist',
        actionLabel: 'View Study in Worklist',
      });
    }

    alert(`Study rejected back to Technologist. Reason: ${reason}`);
  };

  const handleSaveReport = (aptId: string, reportData: Partial<RadiologyReport>, isFinalize: boolean) => {
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const apt = appointments.find(a => a.id === aptId);

    setAppointments(prev =>
      prev.map(a => {
        if (a.id !== aptId) return a;

        const existingReport = a.report;
        const updatedReport: RadiologyReport = {
          id: existingReport?.id || `rep-${Date.now()}`,
          appointmentId: aptId,
          version: existingReport ? existingReport.version + (isFinalize ? 1 : 0) : 1,
          type: isFinalize ? 'final' : 'draft',
          clinicalHistory: reportData.clinicalHistory || '',
          technique: reportData.technique || '',
          comparison: reportData.comparison || '',
          findings: reportData.findings || '',
          impression: reportData.impression || '',
          recommendations: reportData.recommendations || '',
          criticalFlag: reportData.criticalFlag || false,
          authoredBy: 'Dr. Shahzad Mir, FRCR',
          signedBy: isFinalize ? 'Dr. Shahzad Mir, FRCR (Consultant Radiologist)' : undefined,
          signedAt: isFinalize ? timeNow : undefined,
          lockedAt: isFinalize ? timeNow : undefined,
          releases: existingReport?.releases || [],
        };

        return {
          ...a,
          workflowState: isFinalize ? 'reported' : 'reading',
          reportedAt: isFinalize ? timeNow : a.reportedAt,
          readingAt: a.readingAt || timeNow,
          report: updatedReport,
        };
      })
    );

    if (isFinalize) {
      handleAddAuditLog({
        user: 'Dr. Shahzad Mir, FRCR',
        role: 'Radiologist',
        action: 'Finalize & Electronically Sign Report',
        module: 'Radiology Reporting',
        details: `Electronically signed and verified report for patient ${apt?.patient?.name} (Token: ${apt?.tokenNumber})`,
        status: 'success',
      });

      if (apt) {
        handleAddNotification({
          title: `Diagnostic Report Finalized (#${apt.tokenNumber})`,
          message: `Dr. Shahzad Mir has signed the official diagnostic report for ${apt.patient.name} (${apt.service.name}). Ready for release/dispatch.`,
          category: 'workflow',
          priority: 'medium',
          appointmentId: apt.id,
          tokenNumber: apt.tokenNumber,
          patientName: apt.patient.name,
          targetTab: 'doctors',
          actionLabel: 'Dispatch to Doctor',
        });
      }
    }
  };

  const handleReleaseReport = (aptId: string, channel: 'hand' | 'email' | 'portal') => {
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const apt = appointments.find(a => a.id === aptId);

    setAppointments(prev =>
      prev.map(a => {
        if (a.id !== aptId || !a.report) return a;

        const newRelease = {
          id: `rel-${Date.now()}`,
          reportId: a.report.id,
          channel,
          releasedAt: timeNow,
          releasedBy: 'Reception / Portal Dispatch',
        };

        return {
          ...a,
          workflowState: channel === 'hand' ? 'delivered' : a.workflowState,
          deliveredAt: channel === 'hand' ? timeNow : a.deliveredAt,
          report: {
            ...a.report,
            releases: [...a.report.releases, newRelease],
          },
        };
      })
    );

    handleAddAuditLog({
      user: 'Reception / Dispatch Gateway',
      role: 'Staff',
      action: 'Release Diagnostic Report',
      module: 'Report Dispatch',
      details: `Released report for ${apt?.patient?.name} via ${channel.toUpperCase()}`,
      status: 'success',
    });

    if (apt) {
      handleAddNotification({
        title: `Report Dispatched (${channel.toUpperCase()})`,
        message: `Diagnostic report for ${apt.patient.name} (#${apt.tokenNumber}) successfully dispatched via ${channel.toUpperCase()}.`,
        category: 'dispatch',
        priority: 'low',
        appointmentId: apt.id,
        tokenNumber: apt.tokenNumber,
        patientName: apt.patient.name,
        targetTab: 'doctors',
        actionLabel: 'View Dispatch Log',
      });
    }

    alert(`Report successfully released via ${channel.toUpperCase()} dispatch.`);
  };

  const handleRecordPayment = (
    invoiceId: string,
    amount: number,
    method: 'cash' | 'card' | 'bank' | 'mobile' | 'insurance',
    reference: string
  ) => {
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    let matchedPatientName = '';
    let matchedToken = '';
    let matchedAptId = '';

    setInvoices(prev =>
      prev.map(inv => {
        if (inv.id !== invoiceId) return inv;

        matchedPatientName = inv.patient?.name || '';
        matchedToken = inv.appointmentToken || '';
        matchedAptId = inv.appointmentId || '';

        const newPayment: InvoicePayment = {
          id: `pay-${Date.now()}`,
          amount,
          method,
          reference,
          paidAt: timeNow,
          receivedBy: 'Billing Officer Amina',
        };

        const newPaidTotal = inv.paidTotal + amount;
        const newBalance = Math.max(0, inv.total - newPaidTotal);
        const newStatus = newBalance === 0 ? 'paid' : 'partial';

        return {
          ...inv,
          paidTotal: newPaidTotal,
          balanceDue: newBalance,
          status: newStatus,
          payments: [...inv.payments, newPayment],
        };
      })
    );

    handleAddAuditLog({
      user: 'Billing Officer Amina',
      role: 'Billing Officer',
      action: 'Record POS Payment',
      module: 'Billing & Invoicing',
      details: `Collected payment of Rs. ${amount.toLocaleString()} via ${method.toUpperCase()} for invoice #${invoiceId}`,
      status: 'success',
    });

    handleAddNotification({
      title: `Payment Received (Rs. ${amount.toLocaleString()})`,
      message: `POS settlement of Rs. ${amount.toLocaleString()} collected via ${method.toUpperCase()} for ${matchedPatientName || 'Invoice'}.`,
      category: 'billing',
      priority: 'low',
      appointmentId: matchedAptId,
      tokenNumber: matchedToken,
      patientName: matchedPatientName,
      targetTab: 'billing',
      actionLabel: 'View Invoices',
    });
  };

  const handleCreateInvoice = (
    appointmentId: string,
    discount: number,
    notes: string,
    extraItems?: InvoiceItem[],
    initialPayment?: { amount: number; method: 'cash' | 'card' | 'bank' | 'mobile' | 'insurance'; reference: string }
  ) => {
    const apt = appointments.find(a => a.id === appointmentId);
    if (!apt) return;

    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    
    const primaryItem: InvoiceItem = {
      id: `item-${Date.now()}-1`,
      serviceId: apt.service.id,
      description: `${apt.service.name} (${apt.service.code})`,
      quantity: 1,
      unitPrice: apt.service.price,
      discount: discount,
      lineTotal: Math.max(0, apt.service.price - discount),
    };

    const allItems = [primaryItem, ...(extraItems || [])];
    const subtotal = allItems.reduce((sum, it) => sum + (it.unitPrice * it.quantity), 0);
    const discountTotal = allItems.reduce((sum, it) => sum + (it.discount || 0), 0);
    const total = Math.max(0, subtotal - discountTotal);

    let payments: InvoicePayment[] = [];
    let paidTotal = 0;

    if (initialPayment && initialPayment.amount > 0) {
      payments = [
        {
          id: `pay-${Date.now()}`,
          amount: initialPayment.amount,
          method: initialPayment.method,
          reference: initialPayment.reference || 'INITIAL-RECEIPT',
          paidAt: timeNow,
          receivedBy: 'Billing Officer Amina',
        }
      ];
      paidTotal = initialPayment.amount;
    }

    const balanceDue = Math.max(0, total - paidTotal);
    const status = balanceDue === 0 ? 'paid' : (paidTotal > 0 ? 'partial' : 'issued');

    const newInvoice: Invoice = {
      id: `inv-${Date.now()}`,
      invoiceNumber: `INV-2026-${Math.floor(10000 + Math.random() * 90000)}`,
      patientId: apt.patientId,
      patient: apt.patient,
      appointmentId: apt.id,
      appointmentToken: apt.tokenNumber,
      subtotal,
      discountTotal,
      taxRate: 0,
      taxAmount: 0,
      total,
      paidTotal,
      balanceDue,
      status,
      notes,
      items: allItems,
      payments,
      createdAt: timeNow,
      issuedAt: timeNow,
    };

    setInvoices(prev => [newInvoice, ...prev]);
  };

  const handleAddInvoiceItem = (invoiceId: string, item: InvoiceItem) => {
    setInvoices(prev =>
      prev.map(inv => {
        if (inv.id !== invoiceId) return inv;
        const newItems = [...inv.items, item];
        const newSubtotal = newItems.reduce((sum, it) => sum + (it.unitPrice * it.quantity), 0);
        const newDiscountTotal = newItems.reduce((sum, it) => sum + (it.discount || 0), 0);
        const newTotal = Math.max(0, newSubtotal - newDiscountTotal);
        const newBalance = Math.max(0, newTotal - inv.paidTotal);
        const newStatus = newBalance === 0 ? 'paid' : (inv.paidTotal > 0 ? 'partial' : 'issued');
        return {
          ...inv,
          items: newItems,
          subtotal: newSubtotal,
          discountTotal: newDiscountTotal,
          total: newTotal,
          balanceDue: newBalance,
          status: newStatus,
        };
      })
    );
  };

  const handleVoidInvoice = (invoiceId: string, reason: string) => {
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    setInvoices(prev =>
      prev.map(inv => {
        if (inv.id !== invoiceId) return inv;
        return {
          ...inv,
          status: 'void',
          voidedAt: timeNow,
          notes: `${inv.notes ? inv.notes + ' | ' : ''}VOIDED: ${reason}`,
        };
      })
    );
  };

  const handleCreateBooking = (newAptData: Partial<Appointment>, newPatientData?: Partial<Patient>) => {
    let patientObj: Patient;

    if (newPatientData) {
      patientObj = newPatientData as Patient;
      setPatients(prev => [patientObj, ...prev]);
    } else {
      patientObj = newAptData.patient!;
    }

    const newAppointment: Appointment = {
      id: `apt-${Date.now()}`,
      tokenNumber: newAptData.tokenNumber || 'DX-99',
      patientId: patientObj.id,
      patient: patientObj,
      serviceId: newAptData.serviceId!,
      service: newAptData.service!,
      modalityId: newAptData.modalityId!,
      modality: newAptData.modality!,
      referrerId: newAptData.referrerId,
      referrer: newAptData.referrer,
      date: newAptData.date || new Date().toISOString().split('T')[0],
      time: newAptData.time || '11:30 AM',
      priority: newAptData.priority || 'routine',
      workflowState: 'booked',
      screeningRequired: newAptData.screeningRequired || false,
      screeningCleared: newAptData.screeningCleared || false,
      roomNumber: newAptData.roomNumber || 'Room 1',
      notes: newAptData.notes,
    };

    setAppointments(prev => [newAppointment, ...prev]);

    // Send Real-time Clinical Notification
    if (newAppointment.priority === 'stat') {
      handleAddNotification({
        title: `🚨 STAT Booking Created (#${newAppointment.tokenNumber})`,
        message: `Emergency priority study scheduled for ${patientObj.name} (${newAppointment.service.name}). Modality: ${newAppointment.modality.name}.`,
        category: 'stat',
        priority: 'critical',
        appointmentId: newAppointment.id,
        tokenNumber: newAppointment.tokenNumber,
        patientName: patientObj.name,
        targetTab: 'technologist',
        actionLabel: 'Open Tech Worklist',
      });
    } else {
      handleAddNotification({
        title: `New Appointment Booked (#${newAppointment.tokenNumber})`,
        message: `${patientObj.name} registered for ${newAppointment.service.name} at ${newAppointment.time}.`,
        category: 'workflow',
        priority: 'low',
        appointmentId: newAppointment.id,
        tokenNumber: newAppointment.tokenNumber,
        patientName: patientObj.name,
        targetTab: 'checkin',
        actionLabel: 'View Reception Desk',
      });
    }

    // Automatically generate invoice for the new booking
    const timeNow = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    const subtotal = newAppointment.service.price;
    const newInvoice: Invoice = {
      id: `inv-${Date.now()}`,
      invoiceNumber: `INV-2026-${Math.floor(10000 + Math.random() * 90000)}`,
      patientId: newAppointment.patientId,
      patient: newAppointment.patient,
      appointmentId: newAppointment.id,
      appointmentToken: newAppointment.tokenNumber,
      subtotal,
      discountTotal: 0,
      taxRate: 0,
      taxAmount: 0,
      total: subtotal,
      paidTotal: 0,
      balanceDue: subtotal,
      status: 'issued',
      notes: 'Initial booking study invoice',
      items: [
        {
          id: `item-${Date.now()}`,
          serviceId: newAppointment.service.id,
          description: `${newAppointment.service.name} (${newAppointment.service.code})`,
          quantity: 1,
          unitPrice: newAppointment.service.price,
          discount: 0,
          lineTotal: newAppointment.service.price,
        },
      ],
      payments: [],
      createdAt: timeNow,
      issuedAt: timeNow,
    };

    setInvoices(prev => [newInvoice, ...prev]);
  };

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-col antialiased selection:bg-cyan-500 selection:text-white">
      {/* Navigation Bar */}
      <Navbar
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        appointments={appointments}
        patients={patients}
        invoices={invoices}
        inventoryItems={inventoryItems}
        role={role}
        setRole={setRole}
        onSelectAppointment={(apt) => setSelectedAppointment(apt)}
        onOpenBookingModal={() => setBookingModalOpen(true)}
        notifications={notifications}
        onOpenNotifications={() => setNotificationCenterOpen(true)}
        staffUsers={staffUsers}
        onExportBackup={handleExportBackup}
        onLockTerminal={() => setIsTerminalLocked(true)}
      />

      {/* Main Content Area */}
      <main className="flex-1 w-full max-w-[1680px] mx-auto px-3 sm:px-4 lg:px-6 py-5">
        {activeTab === 'dashboard' && (
          <DashboardView
            appointments={appointments}
            modalities={modalities}
            invoices={invoices}
            setActiveTab={setActiveTab}
            onSelectAppointment={(apt) => setSelectedAppointment(apt)}
            onOpenBookingModal={() => setBookingModalOpen(true)}
          />
        )}

        {activeTab === 'checkin' && (
          <CheckinBoardView
            appointments={appointments}
            invoices={invoices}
            onCheckIn={handleCheckIn}
            onMarkNoShow={handleMarkNoShow}
            onCancelStudy={handleCancelStudy}
            onOpenBookingModal={() => setBookingModalOpen(true)}
            onOpenScreeningModal={(apt) => setScreeningModalApt(apt)}
            onRecordPayment={handleRecordPayment}
            onUpdateAppointment={handleUpdateAppointment}
            setActiveTab={setActiveTab}
          />
        )}

        {activeTab === 'technologist' && (
          <TechnologistView
            appointments={appointments}
            modalities={modalities}
            onStartPreparing={handleStartPreparing}
            onOpenScreeningModal={(apt) => setScreeningModalApt(apt)}
            onStartAcquisition={handleStartAcquisition}
            onOpenDoseModal={(apt) => setDoseModalApt(apt)}
            onCancelStudy={handleCancelStudy}
            onUpdateAppointment={handleUpdateAppointment}
          />
        )}

        {activeTab === 'reporting' && (
          <ReportingView
            appointments={appointments}
            templates={templates}
            selectedAppointment={currentSelectedAppointment}
            onSelectAppointment={(apt) => setSelectedAppointment(apt)}
            onSaveReport={handleSaveReport}
            onRejectToTech={handleRejectToTech}
            onReleaseReport={handleReleaseReport}
            onAddTemplate={handleAddTemplate}
          />
        )}

        {activeTab === 'billing' && (
          <BillingView
            invoices={invoices}
            appointments={appointments}
            patients={patients}
            onRecordPayment={handleRecordPayment}
            onCreateInvoice={handleCreateInvoice}
            onAddInvoiceItem={handleAddInvoiceItem}
            onVoidInvoice={handleVoidInvoice}
          />
        )}

        {activeTab === 'queue' && (
          <QueueBoardView appointments={appointments} />
        )}

        {activeTab === 'inventory' && (
          <InventoryView
            inventoryItems={inventoryItems}
            setInventoryItems={setInventoryItems}
            inventoryTransactions={inventoryTransactions}
            setInventoryTransactions={setInventoryTransactions}
            adverseReactions={adverseReactions}
            setAdverseReactions={setAdverseReactions}
            appointments={appointments}
            role={role}
            onNavigateToTab={(tab, appointmentId) => {
              setActiveTab(tab as ActiveTab);
              if (appointmentId) {
                const apt = appointments.find(a => a.id === appointmentId);
                if (apt) {
                  setSelectedAppointment(apt);
                }
              }
            }}
          />
        )}

        {activeTab === 'masters' && (
          <MasterDataView
            modalities={modalities}
            services={services}
            referrers={referrers}
            forms={forms}
            templates={templates}
            onAddService={handleAddService}
            onUpdateService={handleUpdateService}
            onDeleteService={handleDeleteService}
            onAddModality={handleAddModality}
            onUpdateModality={handleUpdateModality}
            onAddReferrer={handleAddReferrer}
            onUpdateReferrer={handleUpdateReferrer}
            onDeleteReferrer={handleDeleteReferrer}
            onAddForm={handleAddForm}
            onUpdateForm={handleUpdateForm}
            onAddTemplate={handleAddTemplate}
            onUpdateTemplate={handleUpdateTemplate}
            onDeleteTemplate={handleDeleteTemplate}
            onResetFactoryDefaults={handleResetFactoryDefaults}
            onExportBackup={handleExportBackup}
            onImportBackup={handleImportBackup}
          />
        )}

        {activeTab === 'doctors' && (
          <DoctorNetworkView
            referrers={referrers}
            appointments={appointments}
            patients={patients}
            modalities={modalities}
            doctorDispatches={doctorDispatches}
            onAddReferrer={handleAddReferrer}
            onUpdateReferrer={handleUpdateReferrer}
            onDeleteReferrer={handleDeleteReferrer}
            onAddDoctorDispatch={handleAddDoctorDispatch}
          />
        )}

        {activeTab === 'settings' && (
          <SettingsView
            staffUsers={staffUsers}
            onAddStaffUser={handleAddStaffUser}
            onUpdateStaffUser={handleUpdateStaffUser}
            onDeleteStaffUser={handleDeleteStaffUser}
            clinicSettings={clinicSettings}
            onUpdateClinicSettings={handleUpdateClinicSettings}
            dicomNodes={dicomNodes}
            onAddDicomNode={handleAddDicomNode}
            onUpdateDicomNode={handleUpdateDicomNode}
            onDeleteDicomNode={handleDeleteDicomNode}
            notificationTemplates={notificationTemplates}
            onUpdateNotificationTemplate={handleUpdateNotificationTemplate}
            auditLogs={auditLogs}
            onAddAuditLog={handleAddAuditLog}
            onResetFactoryDefaults={handleResetFactoryDefaults}
            onExportBackup={handleExportBackup}
            onImportBackup={handleImportBackup}
          />
        )}
      </main>

      {/* Modals & Overlays */}
      {screeningModalApt && (
        <ScreeningModal
          appointment={screeningModalApt}
          forms={forms}
          onSaveScreening={handleSaveScreening}
          onClose={() => setScreeningModalApt(null)}
        />
      )}

      {doseModalApt && (
        <DoseCaptureModal
          appointment={doseModalApt}
          onCompleteAcquisition={handleCompleteAcquisition}
          onClose={() => setDoseModalApt(null)}
        />
      )}

      {bookingModalOpen && (
        <NewBookingModal
          patients={patients}
          modalities={modalities}
          services={services}
          referrers={referrers}
          existingAppointments={appointments}
          onCreateBooking={handleCreateBooking}
          onClose={() => setBookingModalOpen(false)}
        />
      )}

      {/* Slide-out Clinical Notification Center */}
      <NotificationCenter
        isOpen={notificationCenterOpen}
        onClose={() => setNotificationCenterOpen(false)}
        notifications={notifications}
        onMarkAsRead={handleMarkNotifAsRead}
        onMarkAllAsRead={handleMarkAllNotifsAsRead}
        onClearAll={handleClearAllNotifs}
        onDeleteNotification={handleDeleteNotif}
        onNavigateToTab={(tab, appointmentId) => {
          setActiveTab(tab);
          if (appointmentId) {
            const apt = appointments.find(a => a.id === appointmentId);
            if (apt) {
              setSelectedAppointment(apt);
            }
          }
        }}
        onSelectAppointment={(apt) => {
          setSelectedAppointment(apt);
        }}
        appointments={appointments}
        onTriggerTestAlert={handleTriggerTestAlert}
      />

      {/* Security Terminal Lock Overlay */}
      <TerminalLockModal
        isOpen={isTerminalLocked}
        onUnlock={() => setIsTerminalLocked(false)}
        role={role}
        setRole={setRole}
        staffUsers={staffUsers}
      />

      {/* Footer */}
      <footer className="border-t border-slate-200 bg-white py-4 text-center text-xs text-slate-500">
        <div className="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
          <span>ADC Portal © 2026 Amad Diagnostic Centre — Radiology Information System</span>
          <span className="font-mono text-slate-600">Amad Diagnostic Centre • Islamabad</span>
        </div>
      </footer>
    </div>
  );
};
