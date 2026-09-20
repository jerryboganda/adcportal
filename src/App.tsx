import React, { useCallback, useEffect, useRef, useState } from 'react';
import { ShieldAlert } from 'lucide-react';
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
  InventoryItem,
  InventoryTransaction,
  Invoice,
  InvoiceItem,
  InvoicePayment,
  Modality,
  Patient,
  PaymentMethod,
  Referrer,
  ReportTemplate,
  Room,
  ScreeningForm,
  Service,
  StaffUser,
  StudyScreeningAnswer,
  Entitlements,
  TenantBranding,
  TenantBrandingView,
} from './types';
import {
  AppRole,
} from './types';

import { Navbar } from './components/Navbar';
import { DashboardView } from './components/DashboardView';
import { CheckinBoardView } from './components/CheckinBoardView';
import { DoctorNetworkView } from './components/DoctorNetworkView';
import { QueueBoardView } from './components/QueueBoardView';
import { LoginView } from './components/LoginView';
import { TwoFactorChallengeView } from './components/TwoFactorChallengeView';

import { ScreeningModal } from './components/ScreeningModal';
import { DoseCaptureModal } from './components/DoseCaptureModal';
import { NewBookingModal } from './components/NewBookingModal';
import { NotificationCenter } from './components/NotificationCenter';
import { TerminalLockModal } from './components/TerminalLockModal';
import { StepUpModal } from './components/StepUpModal';
import { SubscriptionGateView } from './components/SubscriptionGateView';

import * as api from './services/apiService';
import { SessionUser } from './services/apiService';
import { onUnauthorized, onPermissionDenied, onStepUpRequired, initCsrf } from './services/api';
import { canAny } from './services/permissions';

/**
 * Module tabs are loaded on demand.
 *
 * They are the largest part of the application (the platform console alone is
 * some 2,900 lines) and a signed-in clinician sees exactly one of them at a
 * time — most often the dashboard. Shipping all of them in the entry chunk made
 * every login, on every workstation, pay for the ones it would never open.
 *
 * Each import is mapped to its named export because these are named-export
 * components, not default ones.
 */
const ReportingView = React.lazy(() =>
  import('./components/ReportingView').then(module => ({ default: module.ReportingView }))
);
const BillingView = React.lazy(() =>
  import('./components/BillingView').then(module => ({ default: module.BillingView }))
);
const InventoryView = React.lazy(() =>
  import('./components/InventoryView').then(module => ({ default: module.InventoryView }))
);
const MasterDataView = React.lazy(() =>
  import('./components/MasterDataView').then(module => ({ default: module.MasterDataView }))
);
const SettingsView = React.lazy(() =>
  import('./components/SettingsView').then(module => ({ default: module.SettingsView }))
);
const PlatformConsole = React.lazy(() =>
  import('./components/PlatformConsole').then(module => ({ default: module.PlatformConsole }))
);
const TechnologistView = React.lazy(() =>
  import('./components/TechnologistView').then(module => ({ default: module.TechnologistView }))
);

type BootStatus = 'loading' | 'unauthenticated' | 'ready' | 'platform' | 'gated';

const ACTIVE_TAB_KEY = 'polytronx_ris_active_tab_v2';
const TERMINAL_LOCK_KEY = 'polytronx_ris_terminal_locked';

/**
 * Shown while a module chunk loads. It is a status region rather than a
 * bare spinner so a screen reader announces the wait.
 */
const ModuleLoading: React.FC<{ label?: string }> = ({ label }) => (
  <div
    role="status"
    aria-live="polite"
    className="flex items-center justify-center gap-2 py-16 text-xs text-slate-500"
  >
    <span className="w-3.5 h-3.5 rounded-full border-2 border-slate-300 border-t-cyan-600 animate-spin" />
    Loading {label ?? 'module'}…
  </div>
);

export const App: React.FC = () => {
  const [bootStatus, setBootStatus] = useState<BootStatus>('loading');
  const [bootError, setBootError] = useState<string | null>(null);
  const [user, setUser] = useState<SessionUser | null>(null);
  const [gateInfo, setGateInfo] = useState<{ status: string; message: string } | null>(null);
  const [flash, setFlash] = useState<{ kind: 'error' | 'success'; message: string } | null>(null);
  // Control-plane step-up re-auth: armed for platform identities, driven by
  // the api-layer 428 interceptor; the modal collects the fresh password.
  const [stepUpOpen, setStepUpOpen] = useState(false);
  const [stepUpResolve, setStepUpResolve] = useState<(() => void) | null>(null);
  const [stepUpReject, setStepUpReject] = useState<(() => void) | null>(null);
  const [brandingView, setBrandingView] = useState<TenantBrandingView | null>(null);
  const [challengeEmail, setChallengeEmail] = useState<string | null>(null);

  // ==================== domain state (hydrated from the API) ====================
  const [appointments, setAppointments] = useState<Appointment[]>([]);
  const [patients, setPatients] = useState<Patient[]>([]);
  const [modalities, setModalities] = useState<Modality[]>([]);
  // Imaging suites (rooms) + tenant payment methods — tenant configuration,
  // hydrated from the bootstrap payload, mirrored for UI gating only.
  const [rooms, setRooms] = useState<Room[]>([]);
  const [services, setServices] = useState<Service[]>([]);
  const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>([]);
  const [referrers, setReferrers] = useState<Referrer[]>([]);
  const [forms, setForms] = useState<ScreeningForm[]>([]);
  const [templates, setTemplates] = useState<ReportTemplate[]>([]);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [staffUsers, setStaffUsers] = useState<StaffUser[]>([]);
  const [clinicSettings, setClinicSettings] = useState<ClinicProfileSettings | null>(null);
  const [entitlements, setEntitlements] = useState<Entitlements | null>(null);
  // White-label presentation for the active tenant (server-resolved from the
  // session, never from a client-supplied host).
  const [branding, setBranding] = useState<TenantBranding | null>(null);

  // Browser-tab title follows the tenant's white-label brand. Priority:
  // white-label appName → tenant account name → platform default.
  useEffect(() => {
    const brand = branding?.appName?.trim() || user?.businessBrandName?.trim() || user?.businessName?.trim() || '';
    document.title = brand ? `${brand} — PolytronX Enterprise PACS & RIS` : 'PolytronX - Enterprise PACS & RIS';
  }, [branding?.appName, user?.businessBrandName, user?.businessName]);
  const [dicomNodes, setDicomNodes] = useState<DicomNodeConfig[]>([]);
  const [notificationTemplates, setNotificationTemplates] = useState<api.NotificationTemplateT[]>([]);
  const [auditLogs, setAuditLogs] = useState<AuditLogEntry[]>([]);
  const [doctorDispatches, setDoctorDispatches] = useState<DoctorDispatchLog[]>([]);
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [inventoryItems, setInventoryItems] = useState<InventoryItem[]>([]);
  const [inventoryTransactions, setInventoryTransactions] = useState<InventoryTransaction[]>([]);
  const [adverseReactions, setAdverseReactions] = useState<AdverseReactionReport[]>([]);

  const [activeTab, setActiveTab] = useState<ActiveTab>(
    () => (localStorage.getItem(ACTIVE_TAB_KEY) as ActiveTab) || 'dashboard'
  );
  const [notificationCenterOpen, setNotificationCenterOpen] = useState(false);
  // Terminal lock survives a page refresh (sessionStorage): a lock that
  // disappears on reload would be theatre, not security.
  const [isTerminalLocked, setIsTerminalLocked] = useState<boolean>(
    () => sessionStorage.getItem(TERMINAL_LOCK_KEY) === '1'
  );

  useEffect(() => {
    localStorage.setItem(ACTIVE_TAB_KEY, activeTab);
  }, [activeTab]);

  useEffect(() => {
    if (isTerminalLocked) {
      sessionStorage.setItem(TERMINAL_LOCK_KEY, '1');
    } else {
      sessionStorage.removeItem(TERMINAL_LOCK_KEY);
    }
  }, [isTerminalLocked]);

  // ==================== bootstrap ====================

  const runBootstrap = useCallback(async () => {
    setBootStatus('loading');
    setBootError(null);
    setGateInfo(null);
    try {
      await initCsrf();
      const payload = await api.bootstrap();

      // Control plane: platform staff without an active support session get
      // the SaaS console, never a clinic dashboard.
      if (payload.user.isPlatformAdmin && !payload.user.supportSession) {
        setUser(payload.user);
        setBootStatus('platform');
        return;
      }

      setUser(payload.user);
      setAppointments(payload.studies);
      setInvoices(payload.invoices);
      setPatients(payload.patients);
      setModalities(payload.modalities);
      setRooms(payload.rooms);
      setServices(payload.services);
      setPaymentMethods(payload.paymentMethods);
      setReferrers(payload.referrers);
      setForms(payload.screeningForms);
      setTemplates(payload.templates);
      setStaffUsers(payload.staff);
      setClinicSettings(payload.clinicSettings);
      setEntitlements(payload.entitlements ?? null);
      setBranding(payload.branding ?? null);
      setDicomNodes(payload.dicomNodes);
      setNotificationTemplates(payload.notificationTemplates);
      setAuditLogs(payload.auditLogs);
      setDoctorDispatches(payload.dispatches);
      setNotifications(payload.notifications);
      setInventoryItems(payload.inventoryItems);
      setInventoryTransactions(payload.inventoryTransactions);
      setAdverseReactions(payload.adverseReactions);
      setBootStatus('ready');
    } catch (err: any) {
      if (err?.status === 401) {
        setBootStatus('unauthenticated');
      } else if (err?.status === 402) {
        // Tenant not subscribable — resolve identity via /me (never gated)
        // and show the subscription gate with the server's message.
        try {
          const fresh = await api.me();
          setUser(fresh);
        } catch {
          // identity unresolvable — fall back to login
          setBootStatus('unauthenticated');
          return;
        }
        setGateInfo({ status: err?.raw?.subscriptionStatus ?? 'expired', message: err?.message ?? 'This clinic subscription is not active.' });
        setBootStatus('gated');
      } else {
        setBootError(err?.message ?? 'Failed to reach the server.');
        setBootStatus('unauthenticated');
      }
    }
  }, []);

  // Flash + error surfacing — declared early: the bootstrap effects below
  // reference them (permission-denied re-hydration flashes to the user).
  const showFlash = useCallback((kind: 'error' | 'success', message: string) => {
    setFlash({ kind, message });
    window.setTimeout(() => setFlash(null), 5000);
  }, []);

  const fail = useCallback((err: any, fallback: string) => {
    showFlash('error', err?.message ?? fallback);
  }, [showFlash]);

  useEffect(() => {
    onUnauthorized(() => {
      setUser(null);
      setBootStatus('unauthenticated');
    });

    // Structured 403 (`permission.denied`): the server-side permission set
    // moved under an open session (tenant admin edited a role). Re-hydrate
    // so navigation/actions adapt automatically — never retry the request.
    onPermissionDenied(() => {
      showFlash('error', 'Your access was updated by an administrator — refreshing your workspace.');
      runBootstrap();
    });

    // Control-plane step-up: when a mutating platform action comes back 428,
    // open the password modal; the interceptor awaits it, then replays.
    onStepUpRequired(() => new Promise<void>((resolve, reject) => {
      setStepUpResolve(() => resolve);
      setStepUpReject(() => reject);
      setStepUpOpen(true);
    }));

    runBootstrap();
  }, [runBootstrap, showFlash]);

  // ==================== permission auto-refresh ====================
  // Polls the cheap /me endpoint (60s while visible + on window focus) and
  // re-runs the full bootstrap only when the server-issued permission set
  // moved (permissionsVersion counter). RBAC edits therefore reach open
  // sessions with no reload and no developer intervention.
  useEffect(() => {
    if (bootStatus !== 'ready' || !user || user.isPlatformAdmin) {
      return;
    }
    let cancelled = false;
    const check = async () => {
      if (document.visibilityState !== 'visible') {
        return;
      }
      try {
        const fresh = await api.me();
        if (cancelled) {
          return;
        }
        if (
          fresh.permissionsVersion !== user.permissionsVersion ||
          fresh.permissions.join('|') !== user.permissions.join('|')
        ) {
          await runBootstrap();
        }
      } catch {
        // Transient failure — the next tick retries; never log the user out.
      }
    };
    const interval = window.setInterval(check, 60_000);
    const onFocus = () => { void check(); };
    window.addEventListener('focus', onFocus);
    return () => {
      cancelled = true;
      window.clearInterval(interval);
      window.removeEventListener('focus', onFocus);
    };
  }, [bootStatus, user, runBootstrap]);

  const settleStepUp = useCallback((ok: boolean) => {
    setStepUpOpen(false);
    if (ok) {
      stepUpResolve?.();
    } else {
      stepUpReject?.();
    }
    setStepUpResolve(null);
    setStepUpReject(null);
  }, [stepUpResolve, stepUpReject]);

  const handleAuthenticated = useCallback((_user: SessionUser) => {
    setChallengeEmail(null);
    runBootstrap();
  }, [runBootstrap]);

  const handleTwoFactorVerified = useCallback(() => {
    setChallengeEmail(null);
    runBootstrap();
  }, [runBootstrap]);

  const handleTwoFactorCancel = useCallback(() => {
    setChallengeEmail(null);
    setBootStatus('unauthenticated');
  }, []);

  const handleLogout = useCallback(async () => {
    try {
      await api.logout();
    } catch {
      // Session may already be gone — clearing local state is enough.
    }
    setUser(null);
    setGateInfo(null);
    setBootStatus('unauthenticated');
    setActiveTab('dashboard');
  }, []);

  const handleSwitchTenant = useCallback(async (businessId: number) => {
    try {
      const fresh = await api.switchTenant(businessId);
      setUser(fresh);
      await runBootstrap();
    } catch (err: any) {
      showFlash('error', err?.message ?? 'Tenant switch failed.');
    }
  }, [runBootstrap, showFlash]);

  const handleEnterSupportTenant = useCallback(async (businessId: number) => {
    try {
      const fresh = await api.enterSupportContext(businessId);
      setUser(fresh);
      await runBootstrap();
    } catch (err: any) {
      showFlash('error', err?.message ?? 'Failed to enter the clinic context.');
    }
  }, [runBootstrap, showFlash]);

  const handleLeaveSupportTenant = useCallback(async () => {
    try {
      const fresh = await api.leaveSupportContext();
      setUser(fresh);
      await runBootstrap();
    } catch (err: any) {
      showFlash('error', err?.message ?? 'Failed to leave the clinic context.');
    }
  }, [runBootstrap, showFlash]);

  // ==================== shared helpers ====================

  const adoptNotifications = useCallback((incoming: AppNotification[] | undefined) => {
    if (incoming && incoming.length) {
      setNotifications(prev => [...incoming, ...prev]);
    }
  }, []);

  const replaceStudy = useCallback((study: Appointment) => {
    const normalized = { ...study, patient: study.patient ?? appointments.find(a => a.id === study.id)?.patient ?? study.patient };
    setAppointments(prev => prev.map(a => (a.id === normalized.id ? normalized : a)));
  }, [appointments]);

  const replaceInvoice = useCallback((invoice: Invoice) => {
    setInvoices(prev => prev.map(inv => (inv.id === invoice.id ? invoice : inv)));
  }, [invoices]);

  // ==================== workflow handlers ====================

  const runTransition = useCallback(async (
    aptId: string,
    action: api.WorkflowAction,
    extra?: { reason?: string; dose?: DoseLog }
  ) => {
    try {
      const { study, notifications: incoming } = await api.transitionStudy({ appointmentId: aptId, action, ...extra });
      replaceStudy(study);
      adoptNotifications(incoming);
      return study;
    } catch (err: any) {
      fail(err, 'Workflow action failed.');
      throw err;
    }
  }, [adoptNotifications, fail, replaceStudy]);

  const handleCheckIn = useCallback((aptId: string) => {
    runTransition(aptId, 'checkin').catch(() => undefined);
  }, [runTransition]);

  const handleMarkNoShow = useCallback((aptId: string) => {
    return runTransition(aptId, 'no_show').catch(() => undefined);
  }, [runTransition]);

  const handleStartPreparing = useCallback((aptId: string) => {
    runTransition(aptId, 'prepare').catch(() => undefined);
  }, [runTransition]);

  const handleStartAcquisition = useCallback((apt: Appointment) => {
    if (apt.screeningRequired && !apt.screeningCleared) {
      showFlash('error', 'Safety Notice: complete the safety questionnaire before starting image acquisition.');
      setScreeningModalApt(apt);
      return;
    }
    runTransition(apt.id, 'start').catch(() => undefined);
  }, [runTransition, showFlash]);

  const handleCompleteAcquisition = useCallback((aptId: string, doseLog: DoseLog) => {
    runTransition(aptId, 'complete', { dose: doseLog }).catch(() => undefined);
  }, [runTransition]);

  const handleCancelStudy = useCallback((aptId: string, reason: string) => {
    runTransition(aptId, 'cancel', { reason }).catch(() => undefined);
  }, [runTransition]);

  /** PACS QC verified by the technologist: hand the study to the reading radiologist. */
  const handleSendToReading = useCallback((aptId: string) => {
    runTransition(aptId, 'send_to_reading').catch(() => undefined);
  }, [runTransition]);

  /** Queue-board call: persisted server-side so every terminal sees the same "now serving". */
  const handleCallPatient = useCallback((aptId: string) => {
    return runTransition(aptId, 'call').catch(() => undefined);
  }, [runTransition]);

  const handleRejectToTech = useCallback(async (aptId: string, reason: string) => {
    try {
      await runTransition(aptId, 'reject', { reason });
      showFlash('success', 'Study rejected back to Technologist.');
    } catch (err: any) {
      // Flash already shown by runTransition; rethrow so the caller does not
      // toast success over a failure.
      throw err;
    }
  }, [runTransition, showFlash]);

  const handleUpdateAppointment = useCallback(async (aptId: string, updates: Partial<Appointment>) => {
    // Direct workflowState writes are not permitted — transitions only.
    const { workflowState, ...editable } = updates;
    void workflowState;
    try {
      const study = await api.updateStudy(aptId, editable);
      replaceStudy(study);
    } catch (err: any) {
      fail(err, 'Could not update the study.');
    }
  }, [fail, replaceStudy]);

  // ==================== screening ====================

  const handleSaveScreening = useCallback(async (aptId: string, answers: StudyScreeningAnswer[], _isCleared: boolean) => {
    void _isCleared; // server re-derives clearance from the recorded answers
    try {
      const study = await api.submitScreening(
        aptId,
        answers.map(a => ({
          questionId: a.questionId,
          answerValue: a.answerValue,
          overrideReason: a.overrideReason || undefined,
        }))
      );
      replaceStudy(study);
    } catch (err: any) {
      fail(err, 'Could not record the screening answers.');
    }
  }, [fail, replaceStudy]);

  // ==================== reporting ====================

  // Report persistence lives in the reporting workspace itself
  // (components/reporting/ReportWorkspace.tsx), which owns the draft buffer,
  // optimistic-lock version and autosave. App only mirrors the returned study.

  const handleReleaseReport = useCallback(async (aptId: string, channel: 'hand' | 'email' | 'portal') => {
    const apt = appointments.find(a => a.id === aptId);
    const reportId = apt?.report?.id;
    if (!reportId) {
      showFlash('error', 'No signed report found for this study.');
      return;
    }
    try {
      const { study, notifications: incoming } = await api.releaseReport(aptId, reportId, channel);
      replaceStudy(study);
      adoptNotifications(incoming);
      showFlash('success', `Report released via ${channel.toUpperCase()} dispatch.`);
    } catch (err: any) {
      fail(err, 'Report release failed.');
    }
  }, [appointments, adoptNotifications, fail, replaceStudy, showFlash]);

  // ==================== billing ====================

  const handleRecordPayment = useCallback(async (
    invoiceId: string,
    amount: number,
    method: InvoicePayment['method'],
    reference: string
  ) => {
    try {
      const invoice = await api.recordPayment(invoiceId, amount, method, reference);
      replaceInvoice(invoice);
      showFlash('success', `Payment of Rs. ${amount.toLocaleString()} recorded.`);
    } catch (err: any) {
      fail(err, 'Could not record the payment.');
      throw err;
    }
  }, [fail, replaceInvoice, showFlash]);

  const handleCreateInvoice = useCallback(async (
    appointmentId: string,
    discountAmount: number,
    notes: string,
    extraItems?: InvoiceItem[],
    initialPayment?: { amount: number; method: InvoicePayment['method']; reference: string },
    taxRate?: number
  ) => {
    const apt = appointments.find(a => a.id === appointmentId);
    if (!apt) return;

    const items = [
      { serviceId: apt.service.id, description: `${apt.service.name} (${apt.service.code})`, quantity: 1, unitPrice: apt.service.price, discount: 0 },
      ...(extraItems ?? []).map(it => ({ description: it.description, quantity: it.quantity, unitPrice: it.unitPrice, discount: it.discount })),
    ];

    try {
      const invoice = await api.createInvoice(appointmentId, items, {
        // Cash discount is server-authoritative: it travels in the contract
        // and folds into the persisted totals (never recomputed client-side).
        discountAmount: discountAmount > 0 ? discountAmount : undefined,
        taxRate: taxRate ?? 0,
        notes,
        initialPayment: initialPayment && initialPayment.amount > 0 ? initialPayment : undefined,
      });
      setInvoices(prev => [invoice, ...prev]);
      showFlash('success', `Invoice ${invoice.invoiceNumber} created.`);
    } catch (err: any) {
      fail(err, 'Could not create the invoice.');
      throw err; // keeps the cashier's modal open — the latch resets via finally
    }
  }, [appointments, fail, showFlash]);

  const handleAddInvoiceItem = useCallback(async (invoiceId: string, item: Omit<InvoiceItem, 'id'> & { id?: string; lineTotal?: number }) => {
    try {
      const invoice = await api.addInvoiceItem(invoiceId, {
        description: item.description,
        quantity: item.quantity,
        unitPrice: item.unitPrice,
        discount: item.discount ?? 0,
      });
      replaceInvoice(invoice);
    } catch (err: any) {
      fail(err, 'Could not add the invoice line.');
      throw err;
    }
  }, [fail, replaceInvoice]);

  const handleVoidInvoice = useCallback(async (invoiceId: string, reason: string) => {
    try {
      const invoice = await api.voidInvoice(invoiceId, reason);
      replaceInvoice(invoice);
      showFlash('success', 'Invoice voided.');
    } catch (err: any) {
      fail(err, 'Could not void the invoice.');
      throw err;
    }
  }, [fail, replaceInvoice, showFlash]);

  const handleRefundPayment = useCallback(async (
    invoiceId: string,
    paymentId: string,
    amount: number,
    reason: string,
    reference?: string
  ) => {
    try {
      const invoice = await api.refundInvoicePayment(invoiceId, paymentId, amount, reason, reference);
      replaceInvoice(invoice);
      showFlash('success', `Refund of Rs. ${amount.toLocaleString()} issued.`);
    } catch (err: any) {
      fail(err, 'Could not issue the refund.');
      throw err;
    }
  }, [fail, replaceInvoice, showFlash]);

  // ==================== booking ====================

  // Server-issued permission set (mirrored for UI only — the API enforces
  // the same checks and answers 403 regardless of what the SPA renders).
  const permissions = user?.permissions ?? [];
  const canBook = canAny(permissions, ['appointment create']);

  // The single booking-modal opener: permission-checked so no entry point
  // (search bar, dashboard module, reception walk-in) can bypass it.
  const openBookingModal = useCallback(() => {
    if (!canAny(user?.permissions ?? [], ['appointment create'])) {
      showFlash('error', 'You do not have permission to create patient bookings.');
      return;
    }
    setBookingModalOpen(true);
  }, [user, showFlash]);

  const handleCreateBooking = useCallback(async (input: api.BookingInput, newPatientData?: Partial<Patient>) => {
    try {
      const { study, invoice, notifications: incoming } = await api.createBooking({
        ...input,
        newPatient: newPatientData,
      });

      setAppointments(prev => [study, ...prev]);
      setInvoices(prev => [invoice, ...prev]);
      adoptNotifications(incoming);

      if (newPatientData) {
        setPatients(prev => [study.patient, ...prev]);
      }

      // The confirmation reception actually needs: the token minted
      // server-side, next to the SERVER'S money (payable/balance recomputed
      // from the tenant's configured price — never the form's numbers).
      const symbol = clinicSettings?.currencySymbol ?? 'Rs.';
      const token = study.tokenNumber ? `Token #${study.tokenNumber}` : 'Token pending';
      const balance = invoice.balanceDue > 0
        ? ` · balance ${symbol} ${invoice.balanceDue.toLocaleString()}`
        : ' · settled';

      showFlash('success', `Booked — ${token} · ${study.service.name} · payable ${symbol} ${invoice.total.toLocaleString()}${balance}.`);

      return study;
    } catch (err: any) {
      // A 5xx is not a validation problem: say so honestly, and make clear
      // that the failed attempt left nothing behind, instead of echoing a
      // bare "Server Error" with no next step.
      if (typeof err?.status === 'number' && err.status >= 500) {
        showFlash(
          'error',
          'The booking could not be completed on the server and nothing was saved. Please try again — if it keeps failing, note the time and contact support.',
        );
      } else {
        fail(err, 'Booking failed.');
      }
      throw err;
    }
  }, [adoptNotifications, clinicSettings, fail, showFlash]);

  // ==================== master data ====================

  const handleAddService = useCallback(async (newSvc: Omit<Service, 'id'>) => {
    try {
      const service = await api.createService(newSvc);
      setServices(prev => [...prev, service]);
      showFlash('success', `Procedure "${service.name}" added to the catalog.`);
    } catch (err: any) { fail(err, 'Could not create the procedure.'); }
  }, [fail, showFlash]);

  const handleUpdateService = useCallback(async (updatedSvc: Service) => {
    try {
      const service = await api.updateService(updatedSvc);
      setServices(prev => prev.map(s => (s.id === service.id ? service : s)));
      showFlash('success', `Procedure "${service.name}" updated.`);
    } catch (err: any) { fail(err, 'Could not update the procedure.'); }
  }, [fail, showFlash]);

  const handleDeleteService = useCallback(async (serviceId: number) => {
    try {
      await api.deleteService(String(serviceId));
      setServices(prev => prev.filter(s => s.id !== serviceId));
      showFlash('success', 'Procedure removed from the catalog.');
    } catch (err: any) { fail(err, 'Could not delete the procedure.'); }
  }, [fail, showFlash]);

  const handleAddModality = useCallback(async (newMod: Omit<Modality, 'id'>) => {
    try {
      const modality = await api.createModality(newMod);
      setModalities(prev => [...prev, modality]);
      showFlash('success', `Modality suite "${modality.name}" created.`);
    } catch (err: any) { fail(err, 'Could not create the modality.'); }
  }, [fail, showFlash]);

  const handleUpdateModality = useCallback(async (updatedMod: Modality) => {
    try {
      const modality = await api.updateModality(updatedMod);
      setModalities(prev => prev.map(m => (m.id === modality.id ? modality : m)));
      showFlash('success', `Modality suite "${modality.name}" updated.`);
    } catch (err: any) { fail(err, 'Could not update the modality.'); }
  }, [fail, showFlash]);

  const handleDeleteModality = useCallback(async (modalityId: number) => {
    try {
      await api.deleteModality(String(modalityId));
      setModalities(prev => prev.filter(m => m.id !== modalityId));
      showFlash('success', 'Modality suite deleted.');
    } catch (err: any) { fail(err, 'Could not delete the modality.'); }
  }, [fail, showFlash]);

  const handleAddRoom = useCallback(async (newRoom: Omit<Room, 'id'>) => {
    try {
      const room = await api.createRoom(newRoom);
      setRooms(prev => [...prev, room]);
      showFlash('success', `Imaging suite "${room.name}" created.`);
    } catch (err: any) { fail(err, 'Could not create the imaging suite.'); }
  }, [fail, showFlash]);

  const handleUpdateRoom = useCallback(async (updatedRoom: Room) => {
    try {
      const room = await api.updateRoom(updatedRoom);
      setRooms(prev => prev.map(r => (r.id === room.id ? room : r)));
      showFlash('success', `Imaging suite "${room.name}" updated.`);
    } catch (err: any) { fail(err, 'Could not update the imaging suite.'); }
  }, [fail, showFlash]);

  const handleDeleteRoom = useCallback(async (roomId: number) => {
    try {
      await api.deleteRoom(String(roomId));
      setRooms(prev => prev.filter(r => r.id !== roomId));
      showFlash('success', 'Imaging suite deleted.');
    } catch (err: any) { fail(err, 'Could not delete the imaging suite.'); }
  }, [fail, showFlash]);

  const handleAddPaymentMethod = useCallback(async (input: { code: string; name: string; isActive?: boolean; sortOrder?: number }) => {
    try {
      const method = await api.createPaymentMethod(input);
      setPaymentMethods(prev => [...prev, method]);
      showFlash('success', `Payment method "${method.name}" added.`);
    } catch (err: any) { fail(err, 'Could not create the payment method.'); }
  }, [fail, showFlash]);

  const handleUpdatePaymentMethod = useCallback(async (method: { id: number; name: string; isActive?: boolean; sortOrder?: number }) => {
    try {
      const updated = await api.updatePaymentMethod(method);
      setPaymentMethods(prev => prev.map(m => (m.id === updated.id ? updated : m)));
      showFlash('success', `Payment method "${updated.name}" updated.`);
    } catch (err: any) { fail(err, 'Could not update the payment method.'); }
  }, [fail, showFlash]);

  const handleDeletePaymentMethod = useCallback(async (methodId: number) => {
    try {
      await api.deletePaymentMethod(String(methodId));
      setPaymentMethods(prev => prev.filter(m => m.id !== methodId));
      showFlash('success', 'Payment method deleted.');
    } catch (err: any) { fail(err, 'Could not delete the payment method.'); }
  }, [fail, showFlash]);

  const handleAddReferrer = useCallback(async (newRef: Omit<Referrer, 'id'>) => {
    try {
      const referrer = await api.createReferrer(newRef);
      setReferrers(prev => [...prev, referrer]);
      showFlash('success', `Referring doctor "${referrer.name}" added.`);
    } catch (err: any) { fail(err, 'Could not create the referrer.'); }
  }, [fail, showFlash]);

  const handleUpdateReferrer = useCallback(async (updatedRef: Referrer) => {
    try {
      const referrer = await api.updateReferrer(updatedRef);
      setReferrers(prev => prev.map(r => (r.id === referrer.id ? referrer : r)));
      showFlash('success', `Referring doctor "${referrer.name}" updated.`);
    } catch (err: any) { fail(err, 'Could not update the referrer.'); }
  }, [fail, showFlash]);

  const handleDeleteReferrer = useCallback(async (refId: number) => {
    try {
      await api.deleteReferrer(String(refId));
      setReferrers(prev => prev.filter(r => r.id !== refId));
      showFlash('success', 'Referring doctor removed.');
    } catch (err: any) { fail(err, 'Could not delete the referrer.'); }
  }, [fail, showFlash]);

  const handleAddForm = useCallback(async (newForm: {
    name: string;
    description?: string;
    modalityId?: number | null;
    questions: Array<{
      questionText: string;
      helpText?: string;
      answerType: 'boolean' | 'select' | 'text';
      riskValue?: string;
      isRiskBlocking: boolean;
    }>;
  }) => {
    try {
      const form = await api.createScreeningForm(newForm);
      setForms(prev => [...prev, form]);
      showFlash('success', `Screening form "${form.name}" created.`);
    } catch (err: any) { fail(err, 'Could not create the screening form.'); }
  }, [fail, showFlash]);

  const handleUpdateForm = useCallback(async (updatedForm: ScreeningForm) => {
    try {
      const form = await api.updateScreeningFormQuestions(updatedForm.id, updatedForm.questions);
      setForms(prev => prev.map(f => (f.id === form.id ? { ...f, questions: form.questions } : f)));
      showFlash('success', 'Screening form questions updated.');
    } catch (err: any) { fail(err, 'Could not update the screening form.'); }
  }, [fail, showFlash]);

  const handleToggleScreeningForm = useCallback(async (formId: string) => {
    try {
      const form = await api.toggleScreeningForm(formId);
      setForms(prev => prev.map(f => (f.id === form.id ? form : f)));
      showFlash('success', form.isActive
        ? `Screening form "${form.name}" activated — technologists will now receive it at check-in.`
        : `Screening form "${form.name}" deactivated — it is no longer served at check-in.`);
    } catch (err: any) { fail(err, 'Could not update the screening form.'); }
  }, [fail, showFlash]);

  const handleDeleteScreeningForm = useCallback(async (formId: string) => {
    try {
      await api.deleteScreeningForm(formId);
      setForms(prev => prev.filter(f => f.id !== formId));
      showFlash('success', 'Screening form deleted.');
    } catch (err: any) { fail(err, 'Could not delete the screening form.'); }
  }, [fail, showFlash]);

  const handleUpdateScreeningFormMeta = useCallback(async (
    formId: string,
    meta: { name?: string; description?: string; modalityId?: number | null; isActive?: boolean }
  ) => {
    try {
      const form = await api.updateScreeningFormMeta(formId, meta);
      setForms(prev => prev.map(f => (f.id === form.id ? form : f)));
      showFlash('success', 'Screening form updated.');
    } catch (err: any) { fail(err, 'Could not update the screening form.'); }
  }, [fail, showFlash]);

  const handleAddTemplate = useCallback(async (newTpl: Omit<ReportTemplate, 'id'>) => {
    try {
      const template = await api.createReportTemplate(newTpl);
      setTemplates(prev => [...prev, template]);
      showFlash('success', `Report template "${template.name}" created.`);
    } catch (err: any) {
      fail(err, 'Could not create the template.');
      throw err; // callers decide whether a success toast is honest
    }
  }, [fail]);

  const handleUpdateTemplate = useCallback(async (updatedTpl: ReportTemplate) => {
    try {
      const template = await api.updateReportTemplate(updatedTpl);
      setTemplates(prev => prev.map(t => (t.id === template.id ? template : t)));
      showFlash('success', `Report template "${template.name}" saved as v${template.version}.`);
    } catch (err: any) { fail(err, 'Could not update the template.'); }
  }, [fail, showFlash]);

  const handleDeleteTemplate = useCallback(async (templateId: string) => {
    try {
      await api.deleteReportTemplate(templateId);
      setTemplates(prev => prev.filter(t => t.id !== templateId));
      showFlash('success', 'Report template deleted.');
    } catch (err: any) { fail(err, 'Could not delete the template.'); }
  }, [fail, showFlash]);

  // ==================== staff ====================

  const handleAddStaffUser = useCallback(async (newUser: Omit<StaffUser, 'id'> & { password: string }) => {
    try {
      const staff = await api.createStaff(newUser);
      setStaffUsers(prev => [...prev, staff]);
      showFlash('success', `Account created for ${staff.name}.`);
    } catch (err: any) { fail(err, 'Could not create the staff account.'); }
  }, [fail, showFlash]);

  const handleUpdateStaffUser = useCallback(async (updatedUser: StaffUser & { password?: string }) => {
    try {
      const staff = await api.updateStaff(updatedUser);
      setStaffUsers(prev => prev.map(u => (u.id === staff.id ? staff : u)));
    } catch (err: any) { fail(err, 'Could not update the staff account.'); }
  }, [fail]);

  const handleDeleteStaffUser = useCallback(async (userId: string) => {
    try {
      await api.deleteStaff(userId);
      setStaffUsers(prev => prev.filter(u => u.id !== userId));
      showFlash('success', 'Staff account revoked.');
    } catch (err: any) { fail(err, 'Could not revoke the staff account.'); }
  }, [fail, showFlash]);

  // ==================== clinic / platform settings ====================

  const handleOpenBrandingSettings = useCallback(async () => {
    try {
      setBrandingView(await api.fetchTenantBrandingView());
    } catch {
      setBrandingView(null);
    }
  }, []);

  const handleUpdateClinicSettings = useCallback(async (newSettings: ClinicProfileSettings) => {
    try {
      const clinic = await api.updateClinicSettings(newSettings);
      setClinicSettings(clinic);
      showFlash('success', 'Clinic profile saved.');
    } catch (err: any) {
      fail(err, 'Could not save the clinic profile.');
      throw err; // caller decides whether its own success state is honest
    }
  }, [fail, showFlash]);

  const handleAddDicomNode = useCallback(async (newNode: Omit<DicomNodeConfig, 'id'>) => {
    try {
      const node = await api.createDicomNode(newNode);
      setDicomNodes(prev => [...prev, node]);
    } catch (err: any) { fail(err, 'Could not register the DICOM node.'); }
  }, [fail]);

  const handleUpdateDicomNode = useCallback(async (updatedNode: DicomNodeConfig) => {
    try {
      const node = await api.updateDicomNode(updatedNode);
      setDicomNodes(prev => prev.map(n => (n.id === node.id ? node : n)));
    } catch (err: any) { fail(err, 'Could not update the DICOM node.'); }
  }, [fail]);

  const handleDeleteDicomNode = useCallback(async (nodeId: string) => {
    try {
      await api.deleteDicomNode(nodeId);
      setDicomNodes(prev => prev.filter(n => n.id !== nodeId));
    } catch (err: any) { fail(err, 'Could not delete the DICOM node.'); }
  }, [fail]);

  const handlePingDicomNode = useCallback(async (nodeId: string) => {
    try {
      const { node, probe } = await api.pingDicomNode(nodeId);
      setDicomNodes(prev => prev.map(n => (n.id === node.id ? node : n)));
      showFlash(
        probe.status === 'online' ? 'success' : 'error',
        probe.status === 'online'
          ? `TCP probe OK — ${node.ipAddress}:${node.port} reachable (${probe.latency}ms).`
          : `Probe failed — ${node.ipAddress}:${node.port} unreachable.`
      );
    } catch (err: any) { fail(err, 'Probe request failed.'); }
  }, [fail, showFlash]);

  // Debounced template persistence (the settings editor persists per keystroke).
  const templateTimers = useRef<Record<string, number>>({});
  const handleUpdateNotificationTemplate = useCallback((updatedTemplate: api.NotificationTemplateT) => {
    setNotificationTemplates(prev => prev.map(t => (t.id === updatedTemplate.id ? updatedTemplate : t)));
    window.clearTimeout(templateTimers.current[updatedTemplate.id]);
    templateTimers.current[updatedTemplate.id] = window.setTimeout(() => {
      api.updateNotificationTemplate(updatedTemplate).catch((err: any) => fail(err, 'Could not save the template.'));
    }, 700);
  }, [fail]);

  // ==================== inventory ====================

  const handleCreateInventoryItem = useCallback(async (item: Omit<InventoryItem, 'id'>) => {
    try {
      const { item: created, openingTransactions } = await api.createInventoryItem(item);
      setInventoryItems(prev => [created, ...prev]);
      // The server records the opening-stock movements itself; the client
      // renders exactly what the ledger returned — never synthesized rows.
      if (openingTransactions.length > 0) {
        setInventoryTransactions(prev => [...openingTransactions, ...prev]);
      }
    } catch (err: any) { fail(err, 'Could not create the SKU.'); }
  }, [fail]);

  const handleStockMovement = useCallback(async (input: {
    itemId: string;
    type: InventoryTransaction['type'];
    quantity: number;
    batchNumber?: string;
    direction?: 'in' | 'out';
    tokenNumber?: string;
    patientName?: string;
    notes?: string;
  }) => {
    try {
      const { transaction, item } = await api.createInventoryTransaction(input);
      setInventoryItems(prev => prev.map(it => (it.id === item.id ? item : it)));
      setInventoryTransactions(prev => [transaction, ...prev]);
    } catch (err: any) { fail(err, 'Stock movement failed.'); }
  }, [fail]);

  const handleCreateAdverseReaction = useCallback(async (reaction: Omit<AdverseReactionReport, 'id' | 'reportedAt' | 'reportedBy'>) => {
    try {
      const created = await api.createAdverseReaction(reaction);
      setAdverseReactions(prev => [created, ...prev]);
      showFlash('success', 'Adverse reaction report recorded.');
    } catch (err: any) { fail(err, 'Could not record the adverse reaction.'); }
  }, [fail, showFlash]);

  // ==================== doctor dispatch ====================

  const handleAddDoctorDispatch = useCallback(async (input: { appointmentId: string; channel: DoctorDispatchLog['channel']; recipientContact: string }) => {
    try {
      const { dispatch, notifications: incoming } = await api.createDispatch(input);
      setDoctorDispatches(prev => [dispatch, ...prev]);
      adoptNotifications(incoming);
      showFlash(
        'success',
        dispatch.status === 'delivered'
          ? `Dispatch sent via ${dispatch.channel.toUpperCase()}.`
          : `Dispatch logged as pending — ${dispatch.channel.toUpperCase()} gateway is not configured, deliver manually.`
      );
    } catch (err: any) { fail(err, 'Dispatch failed.'); }
  }, [adoptNotifications, fail, showFlash]);

  // ==================== notifications ====================
  // Optimistic updates with ROLLBACK: a swallowed failure used to silently
  // resurrect on the next bootstrap (server never saw the change).

  const handleMarkNotifAsRead = useCallback((id: string) => {
    setNotifications(prev => prev.map(n => (n.id === id ? { ...n, isRead: true } : n)));
    api.markNotificationsRead([id]).catch(() => {
      setNotifications(prev => prev.map(n => (n.id === id ? { ...n, isRead: false } : n)));
      showFlash('error', 'Could not mark the notification as read.');
    });
  }, [showFlash]);

  const handleMarkAllNotifsAsRead = useCallback(() => {
    setNotifications(prev => prev.map(n => ({ ...n, isRead: true })));
    api.markAllNotificationsRead().catch(() => {
      setNotifications(prev => prev.map(n => ({ ...n, isRead: false })));
      showFlash('error', 'Could not mark all notifications as read.');
    });
  }, [showFlash]);

  const handleClearAllNotifs = useCallback(() => {
    const previous = notifications;
    setNotifications([]);
    api.clearNotifications().catch(() => {
      setNotifications(previous);
      showFlash('error', 'Could not clear the notifications.');
    });
  }, [notifications, showFlash]);

  const handleDeleteNotif = useCallback((id: string) => {
    const previous = notifications;
    setNotifications(prev => prev.filter(n => n.id !== id));
    api.deleteNotification(id).catch(() => {
      setNotifications(previous);
      showFlash('error', 'Could not delete the notification.');
    });
  }, [notifications, showFlash]);

  // ==================== backup ====================

  // The settings audit tab re-reads the server trail on demand instead of
  // only ever showing the login-time bootstrap snapshot.
  const handleRefreshAuditLogs = useCallback(async () => {
    try {
      setAuditLogs(await api.fetchAuditLogs());
    } catch (err: any) { fail(err, 'Could not refresh the audit log.'); }
  }, [fail]);

  const handleExportBackup = useCallback(async () => {
    try {
      const snapshot = await api.exportBackup();
      const blob = new Blob([JSON.stringify(snapshot, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const anchor = document.createElement('a');
      anchor.href = url;
      anchor.download = `PolytronX_Enterprise_PACS_RIS_Database_Backup_${new Date().toISOString().split('T')[0]}.json`;
      document.body.appendChild(anchor);
      anchor.click();
      anchor.remove();
      URL.revokeObjectURL(url);
    } catch (err: any) { fail(err, 'Backup export failed.'); }
  }, [fail]);

  // ==================== selections & modals ====================

  /**
   * A study the user asked to open from OUTSIDE the reporting tab: the
   * dashboard's "Manage" button, global search, or a notification. Reporting
   * owns its own selection (a server-driven, paginated worklist), so this is a
   * one-way hand-off — it is never mirrored back. The nonce lets the same study
   * be requested twice and still register.
   */
  const [studyFocus, setStudyFocus] = useState<{ id: number; nonce: number } | null>(null);
  const focusStudy = useCallback((apt: Appointment | null) => {
    if (!apt) return;
    setStudyFocus(previous => ({ id: Number(apt.id), nonce: (previous?.nonce ?? 0) + 1 }));
  }, []);
  const [screeningModalApt, setScreeningModalApt] = useState<Appointment | null>(null);
  const [doseModalApt, setDoseModalApt] = useState<Appointment | null>(null);
  const [bookingModalOpen, setBookingModalOpen] = useState(false);

  // ==================== render gates ====================

  if (user && bootStatus === 'platform') {
    return (
      <>
        {flash && (
          <div role="status" className={`fixed top-3 left-1/2 -translate-x-1/2 z-[80] rounded-lg px-4 py-2.5 text-sm font-medium shadow-lg border ${flash.kind === 'error' ? 'bg-rose-50 border-rose-300 text-rose-800' : 'bg-emerald-50 border-emerald-300 text-emerald-800'}`}>
            {flash.message}
          </div>
        )}
        <PlatformConsole
          user={user}
          onSignOut={handleLogout}
          onEnterTenant={handleEnterSupportTenant}
          notify={showFlash}
        />
        <StepUpModal
          open={stepUpOpen}
          onConfirm={async (password) => {
            try {
              await api.confirmPlatformStepUp(password);
              settleStepUp(true);
            } catch {
              return false; // wrong password — modal stays open
            }
            return true;
          }}
          onCancel={() => settleStepUp(false)}
        />
      </>
    );
  }

  if (bootStatus === 'gated' && user && gateInfo) {
    return (
      <SubscriptionGateView
        user={user}
        status={gateInfo.status}
        message={gateInfo.message}
        onSwitchTenant={handleSwitchTenant}
        onSignOut={handleLogout}
      />
    );
  }

  if (bootStatus !== 'ready' || !user || !clinicSettings) {
    if (bootStatus === 'unauthenticated') {
      if (challengeEmail) {
        return (
          <TwoFactorChallengeView
            email={challengeEmail}
            onVerified={handleTwoFactorVerified}
            onCancel={handleTwoFactorCancel}
          />
        );
      }

      return (
        <LoginView
          onAuthenticated={handleAuthenticated}
          onTwoFactorRequired={(email) => { setChallengeEmail(email); }}
        />
      );
    }

    return (
      <div className="min-h-screen bg-slate-50 flex flex-col items-center justify-center gap-4 text-slate-600">
        <div className="w-10 h-10 border-4 border-cyan-500/30 border-t-cyan-600 rounded-full animate-spin" />
        <p className="text-sm">{bootError ? 'Connection problem' : 'Connecting to PolytronX - Enterprise PACS & RIS…'}</p>
        {bootError && (
          <>
            <p className="text-xs text-rose-600 max-w-sm text-center">{bootError}</p>
            <button
              onClick={() => runBootstrap()}
              className="text-xs font-semibold text-cyan-700 hover:text-cyan-900 underline"
            >
              Retry
            </button>
          </>
        )}
      </div>
    );
  }

  const role: AppRole = user.role as AppRole;

  return (
    <div className="min-h-screen bg-slate-50 text-slate-900 flex flex-col antialiased selection:bg-cyan-500 selection:text-white">
      {flash && (
        <div
          role="status"
          className={`fixed top-3 left-1/2 -translate-x-1/2 z-[80] rounded-lg px-4 py-2.5 text-sm font-medium shadow-lg border ${
            flash.kind === 'error'
              ? 'bg-rose-50 border-rose-300 text-rose-800'
              : 'bg-emerald-50 border-emerald-300 text-emerald-800'
          }`}
        >
          {flash.message}
        </div>
      )}

      {user.supportSession && (
        <div className="bg-rose-600 text-white">
          <div className="mx-auto flex max-w-[1680px] flex-wrap items-center gap-2 px-3 py-1.5 text-xs font-semibold">
            <ShieldAlert size={13} />
            <span>
              BREAK-GLASS SUPPORT SESSION — operating inside <strong>{user.businessName}</strong> · reason: {user.supportSession.reason} ·
              expires {user.supportSession.expiresAt ? new Date(user.supportSession.expiresAt).toLocaleTimeString() : 'soon'}. All actions are audited.
            </span>
            <button
              onClick={handleLeaveSupportTenant}
              className="ml-auto rounded border border-white/40 px-2 py-0.5 text-[11px] font-bold hover:bg-white/10"
            >
              Exit to platform console
            </button>
          </div>
        </div>
      )}

      <Navbar
        activeTab={activeTab}
        setActiveTab={setActiveTab}
        appointments={appointments}
        patients={patients}
        invoices={invoices}
        inventoryItems={inventoryItems}
        role={role}
        entitlements={entitlements}
        brandName={branding?.appName ?? null}
        onSelectAppointment={focusStudy}
        onOpenBookingModal={openBookingModal}
        canOpenBooking={canBook}
        notifications={notifications}
        onOpenNotifications={() => setNotificationCenterOpen(true)}
        staffUsers={staffUsers}
        currentUser={user}
        onSignOut={handleLogout}
        onSwitchTenant={handleSwitchTenant}
        onExportBackup={handleExportBackup}
        onLockTerminal={() => setIsTerminalLocked(true)}
      />

      <main className="flex-1 w-full max-w-[1680px] mx-auto px-3 sm:px-4 lg:px-6 py-5">
        {/* One boundary for every module tab: a chunk that fails to load shows
            the error through the app's existing error surface, not a blank page. */}
        <React.Suspense fallback={<ModuleLoading label={activeTab} />}>
        {activeTab === 'dashboard' && (
          <DashboardView
            appointments={appointments}
            modalities={modalities}
            invoices={invoices}
            permissions={permissions}
            setActiveTab={setActiveTab}
            onSelectAppointment={focusStudy}
            onOpenBookingModal={openBookingModal}
            canOpenBooking={canBook}
          />
        )}

        {activeTab === 'checkin' && (
          <CheckinBoardView
            appointments={appointments}
            invoices={invoices}
            permissions={permissions}
            onCheckIn={handleCheckIn}
            onMarkNoShow={handleMarkNoShow}
            onCancelStudy={handleCancelStudy}
            onOpenBookingModal={openBookingModal}
            canOpenBooking={canBook}
            paymentMethods={paymentMethods}
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
            permissions={permissions}
            onStartPreparing={handleStartPreparing}
            onOpenScreeningModal={(apt) => setScreeningModalApt(apt)}
            onStartAcquisition={handleStartAcquisition}
            onOpenDoseModal={(apt) => setDoseModalApt(apt)}
            onSendToReading={handleSendToReading}
            onCancelStudy={handleCancelStudy}
            onUpdateAppointment={handleUpdateAppointment}
            onTriageUpdated={replaceStudy}
          />
        )}

        {activeTab === 'reporting' && (
          <ReportingView
            appointments={appointments}
            patients={patients}
            services={services}
            modalities={modalities}
            referrers={referrers}
            permissions={permissions}
            currentUser={user}
            clinicSettings={clinicSettings}
            studyFocus={studyFocus}
            onStudyFocusHandled={() => setStudyFocus(null)}
            onRejectToTech={handleRejectToTech}
            onReleaseReport={handleReleaseReport}
            flash={showFlash}
          />
        )}

        {activeTab === 'billing' && (
          <BillingView
            invoices={invoices}
            appointments={appointments}
            permissions={permissions}
            patients={patients}
            clinicSettings={clinicSettings}
            paymentMethods={paymentMethods}
            onRecordPayment={handleRecordPayment}
            onCreateInvoice={handleCreateInvoice}
            onAddInvoiceItem={handleAddInvoiceItem}
            onVoidInvoice={handleVoidInvoice}
            onRefundPayment={handleRefundPayment}
          />
        )}

        {activeTab === 'queue' && (
          <QueueBoardView
            permissions={permissions}
            brandName={branding?.appName ?? null}
            clinicName={clinicSettings?.name ?? null}
            onCallPatient={handleCallPatient}
            onMarkNoShow={handleMarkNoShow}
          />
        )}

        {activeTab === 'inventory' && (
          <InventoryView
            inventoryItems={inventoryItems}
            inventoryTransactions={inventoryTransactions}
            permissions={permissions}
            adverseReactions={adverseReactions}
            onCreateItem={handleCreateInventoryItem}
            onStockMovement={handleStockMovement}
            onCreateAdverseReaction={handleCreateAdverseReaction}
            appointments={appointments}
            role={role}
          />
        )}

        {activeTab === 'masters' && (
          <MasterDataView
            modalities={modalities}
            rooms={rooms}
            permissions={permissions}
            services={services}
            paymentMethods={paymentMethods}
            referrers={referrers}
            forms={forms}
            templates={templates}
            onAddService={handleAddService}
            onUpdateService={handleUpdateService}
            onDeleteService={handleDeleteService}
            onAddModality={handleAddModality}
            onUpdateModality={handleUpdateModality}
            onDeleteModality={handleDeleteModality}
            onAddRoom={handleAddRoom}
            onUpdateRoom={handleUpdateRoom}
            onDeleteRoom={handleDeleteRoom}
            onAddPaymentMethod={handleAddPaymentMethod}
            onUpdatePaymentMethod={handleUpdatePaymentMethod}
            onDeletePaymentMethod={handleDeletePaymentMethod}
            onAddReferrer={handleAddReferrer}
            onUpdateReferrer={handleUpdateReferrer}
            onDeleteReferrer={handleDeleteReferrer}
            onAddForm={handleAddForm}
            onUpdateForm={handleUpdateForm}
            onToggleForm={handleToggleScreeningForm}
            onDeleteForm={handleDeleteScreeningForm}
            onUpdateFormMeta={handleUpdateScreeningFormMeta}
            onAddTemplate={handleAddTemplate}
            clinicName={clinicSettings?.name ?? null}
            onUpdateTemplate={handleUpdateTemplate}
            onDeleteTemplate={handleDeleteTemplate}
            onExportBackup={handleExportBackup}
          />
        )}

        {activeTab === 'doctors' && (
          <DoctorNetworkView
            referrers={referrers}
            appointments={appointments}
            permissions={permissions}
            patients={patients}
            modalities={modalities}
            doctorDispatches={doctorDispatches}
            clinicSettings={clinicSettings}
            onAddReferrer={handleAddReferrer}
            onUpdateReferrer={handleUpdateReferrer}
            onDeleteReferrer={handleDeleteReferrer}
            onAddDoctorDispatch={handleAddDoctorDispatch}
          />
        )}

        {activeTab === 'settings' && (
          <SettingsView
            currentUser={user}
            permissions={permissions}
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
            onPingDicomNode={handlePingDicomNode}
            notificationTemplates={notificationTemplates}
            onUpdateNotificationTemplate={handleUpdateNotificationTemplate}
            auditLogs={auditLogs}
            onRefreshAuditLogs={handleRefreshAuditLogs}
            onExportBackup={handleExportBackup}
            brandingView={brandingView}
            onLoadBrandingView={handleOpenBrandingSettings}
          />
        )}
        </React.Suspense>
      </main>

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
          inventoryItems={inventoryItems}
          onCompleteAcquisition={handleCompleteAcquisition}
          onClose={() => setDoseModalApt(null)}
        />
      )}

      {bookingModalOpen && (
        <NewBookingModal
          patients={patients}
          modalities={modalities}
          rooms={rooms}
          services={services}
          paymentMethods={paymentMethods}
          referrers={referrers}
          canRecordPayment={canAny(permissions, ['invoice payment'])}
          canApplyDiscount={canAny(permissions, ['invoice edit'])}
          currencySymbol={clinicSettings?.currencySymbol ?? 'Rs.'}
          onCreateBooking={handleCreateBooking}
          onClose={() => setBookingModalOpen(false)}
        />
      )}

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
            focusStudy(appointments.find(a => a.id === appointmentId) ?? null);
          }
        }}
        onSelectAppointment={focusStudy}
        appointments={appointments}
      />

      <TerminalLockModal
        isOpen={isTerminalLocked}
        onUnlock={() => setIsTerminalLocked(false)}
        userName={user.name}
        role={role}
        staffUsers={staffUsers}
      />

      <footer className="border-t border-slate-200 bg-white py-4 text-center text-xs text-slate-500">
        <div className="max-w-[1680px] mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
          <span>{clinicSettings.name} © {new Date().getFullYear()} — Radiology Information System{clinicSettings.city ? ` • ${clinicSettings.city}` : ''}</span>
          <span className="font-mono text-slate-600">{clinicSettings.name} • {clinicSettings.city}</span>
        </div>
      </footer>
    </div>
  );
};
