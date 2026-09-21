import { ActiveTab, Entitlements } from '../types';

/**
 * Server-issued permission gating for the SPA.
 *
 * The permission STRINGS mirror `app/Support/PermissionCatalog.php` — the
 * server remains the only enforcement point; these helpers only decide what
 * the UI renders. Every gate consumes the effective permission set issued in
 * `/me` + `/bootstrap` (never the role label, never client-side role maps).
 */

export const can = (permissions: string[], permission: string): boolean =>
  permissions.includes(permission);

export const canAny = (permissions: string[], list: string[]): boolean =>
  list.some(p => permissions.includes(p));

export const canAll = (permissions: string[], list: string[]): boolean =>
  list.every(p => permissions.includes(p));

// ==================== navigation modules ====================

export interface NavGate {
  id: ActiveTab;
  label: string;
  /** ANY-of permission keys that reveal this module. */
  module: string[];
  /** Server-enforced subscription feature that must also be enabled. */
  feature?: 'inventory' | 'dispatch';
}

/**
 * Module-level gates. The module keys (`*_view`) are catalog permissions the
 * tenant admin can toggle per role in Settings → Roles & Permissions.
 */
export const NAV_ITEMS: NavGate[] = [
  { id: 'checkin', label: 'Reception Desk', module: ['reception view'] },
  { id: 'technologist', label: 'Tech Worklist', module: ['technologist view'] },
  { id: 'reporting', label: 'Radiology Reports', module: ['reports view'] },
  { id: 'billing', label: 'Billing & POS', module: ['billing view'] },
  { id: 'queue', label: 'Live Queue TV', module: ['queue view'] },
  { id: 'inventory', label: 'Consumables & Contrast', module: ['inventory view'], feature: 'inventory' },
  { id: 'masters', label: 'Catalog & Forms', module: ['catalog view'] },
  { id: 'doctors', label: 'Doctor Network', module: ['doctors view'], feature: 'dispatch' },
  { id: 'settings', label: 'Settings', module: ['setting manage', 'user manage', 'user logs history', 'role view'] },
];

/** Tabs the session may open; dashboard is always available. */
export function allowedTabs(permissions: string[], entitlements?: Entitlements | null): ActiveTab[] {
  const tabs: ActiveTab[] = ['dashboard'];
  for (const item of NAV_ITEMS) {
    if (item.feature && entitlements?.features && entitlements.features[item.feature] === false) {
      continue;
    }
    if (canAny(permissions, item.module)) {
      tabs.push(item.id);
    }
  }
  return tabs;
}

// ==================== settings sections ====================

export type SettingsSectionId =
  | 'users'
  | 'clinic'
  | 'branding'
  | 'dicom'
  | 'notifications'
  | 'printing'
  | 'audit'
  | 'database';

export const SETTINGS_SECTIONS: { id: SettingsSectionId; module: string[] }[] = [
  { id: 'users', module: ['user manage', 'role view'] },
  { id: 'clinic', module: ['setting manage'] },
  { id: 'branding', module: ['setting manage'] },
  { id: 'dicom', module: ['setting manage'] },
  { id: 'notifications', module: ['setting manage'] },
  // Printing & documents is its own grant so a clinic can delegate paper to the
  // front desk without handing over users, DICOM nodes or the audit trail.
  { id: 'printing', module: ['print settings manage', 'setting manage'] },
  { id: 'audit', module: ['user logs history'] },
  { id: 'database', module: ['setting manage'] },
];

export function allowedSettingsSections(permissions: string[]): SettingsSectionId[] {
  return SETTINGS_SECTIONS.filter(s => canAny(permissions, s.module)).map(s => s.id);
}

// ==================== dashboard widgets / actions ====================

/** Fine-grained gates for dashboard surfaces (module tiles, KPI cards). */
export const DASHBOARD_GATES = {
  /** Booking tile + New Study actions. */
  book: ['appointment create'],
  /** Reception / check-in tiles + queue-call actions. */
  reception: ['reception view', 'study checkin'],
  safetyScreening: ['study screen'],
  techWorklist: ['technologist view', 'study acquire'],
  reporting: ['reports view', 'report create'],
  statWorklist: ['reports view', 'report manage'],
  billing: ['billing view', 'invoice manage'],
  queueBoard: ['queue view'],
  masters: ['catalog view'],
  doctors: ['doctors view'],
  /** Revenue KPI card — money data never reaches roles without billing perms. */
  revenue: ['billing view', 'invoice manage'],
} as const;

/** In-page action gates (buttons inside allowed views). */
export const ACTION_GATES = {
  studyAssign: ['study assign'],
  checkin: ['study checkin'],
  screen: ['study screen'],
  acquire: ['study acquire'],
  studyCancel: ['study cancel'],
  reportCreate: ['report create'],
  reportEdit: ['report edit'],
  reportSign: ['report sign'],
  reportRelease: ['report release'],
  reportPdf: ['report manage'],
  invoiceCreate: ['invoice create'],
  invoiceEdit: ['invoice edit'],
  paymentCollect: ['invoice payment'],
  invoiceVoid: ['invoice delete'],
  staffCreate: ['user create'],
  staffEdit: ['user edit'],
  staffDelete: ['user delete'],
  manageSettings: ['setting manage'],
  auditHistory: ['user logs history'],
  backupExport: ['setting manage'],
  accessManage: ['role manage'],
  accessView: ['role view'],
} as const;

export type ActionGate = keyof typeof ACTION_GATES;

export const canAction = (permissions: string[], gate: ActionGate): boolean =>
  canAny(permissions, [...ACTION_GATES[gate]]);
