import { Appointment, Invoice, Priority, WorkflowState } from '../types';

export type SortDirection = 'asc' | 'desc';

export interface SortField {
  column: string;
  direction: SortDirection;
}

export interface AdvancedFilterState {
  search: string;
  dateRangeMode: 'all' | 'today' | 'tomorrow' | 'yesterday' | 'next7days' | 'custom';
  startDate: string;
  endDate: string;
  modalities: string[]; // empty array means all
  priorities: Priority[]; // empty array means all
  statuses: WorkflowState[]; // empty array means all
  screeningStatus: 'all' | 'cleared' | 'pending';
  billingStatus: 'all' | 'paid' | 'unpaid';
}

export const defaultAdvancedFilters: AdvancedFilterState = {
  search: '',
  dateRangeMode: 'today',
  startDate: new Date().toISOString().split('T')[0],
  endDate: new Date().toISOString().split('T')[0],
  modalities: [],
  priorities: [],
  statuses: [],
  screeningStatus: 'all',
  billingStatus: 'all',
};

/**
 * Returns today's ISO date string (YYYY-MM-DD)
 */
export const getTodayDateString = (): string => {
  return new Date().toISOString().split('T')[0];
};

/**
 * Helper to compute date range from preset
 */
export const getDateRangeForPreset = (
  preset: AdvancedFilterState['dateRangeMode']
): { startDate: string; endDate: string } => {
  const today = new Date();
  const todayStr = today.toISOString().split('T')[0];

  switch (preset) {
    case 'today':
      return { startDate: todayStr, endDate: todayStr };
    case 'tomorrow': {
      const tomorrow = new Date(today);
      tomorrow.setDate(tomorrow.getDate() + 1);
      const tomStr = tomorrow.toISOString().split('T')[0];
      return { startDate: tomStr, endDate: tomStr };
    }
    case 'yesterday': {
      const yesterday = new Date(today);
      yesterday.setDate(yesterday.getDate() - 1);
      const yestStr = yesterday.toISOString().split('T')[0];
      return { startDate: yestStr, endDate: yestStr };
    }
    case 'next7days': {
      const nextWeek = new Date(today);
      nextWeek.setDate(nextWeek.getDate() + 7);
      return {
        startDate: todayStr,
        endDate: nextWeek.toISOString().split('T')[0],
      };
    }
    case 'all':
    case 'custom':
    default:
      return { startDate: '', endDate: '' };
  }
};

/**
 * Evaluates whether an appointment matches advanced filters
 */
export const matchesAdvancedFilters = (
  apt: Appointment,
  filters: AdvancedFilterState,
  getInvoice?: (apt: Appointment) => Invoice | undefined
): boolean => {
  // 1. Text search
  if (filters.search.trim()) {
    const q = filters.search.toLowerCase().trim();
    const matchesSearch =
      apt.patient.name.toLowerCase().includes(q) ||
      apt.patient.mrn.toLowerCase().includes(q) ||
      (apt.patient.phone && apt.patient.phone.includes(q)) ||
      apt.tokenNumber.toLowerCase().includes(q) ||
      apt.service.name.toLowerCase().includes(q) ||
      apt.modality.code.toLowerCase().includes(q) ||
      apt.modality.name.toLowerCase().includes(q) ||
      (apt.referrer && apt.referrer.name.toLowerCase().includes(q)) ||
      (apt.notes && apt.notes.toLowerCase().includes(q)) ||
      (apt.roomNumber && apt.roomNumber.toLowerCase().includes(q));

    if (!matchesSearch) return false;
  }

  // 2. Date filtering
  if (filters.dateRangeMode !== 'all') {
    if (filters.startDate && apt.date < filters.startDate) return false;
    if (filters.endDate && apt.date > filters.endDate) return false;
  }

  // 3. Modality
  if (filters.modalities.length > 0) {
    if (!filters.modalities.includes(apt.modality.code) && !filters.modalities.includes(String(apt.modalityId))) {
      return false;
    }
  }

  // 4. Priority
  if (filters.priorities.length > 0) {
    if (!filters.priorities.includes(apt.priority)) {
      return false;
    }
  }

  // 5. Workflow status
  if (filters.statuses.length > 0) {
    if (!filters.statuses.includes(apt.workflowState)) {
      return false;
    }
  }

  // 6. Screening clearance status
  if (filters.screeningStatus === 'cleared') {
    if (apt.screeningRequired && !apt.screeningCleared) return false;
  } else if (filters.screeningStatus === 'pending') {
    if (!apt.screeningRequired || apt.screeningCleared) return false;
  }

  // 7. Billing status
  if (filters.billingStatus !== 'all' && getInvoice) {
    const inv = getInvoice(apt);
    const isPaid = inv ? inv.balanceDue === 0 : false;
    if (filters.billingStatus === 'paid' && !isPaid) return false;
    if (filters.billingStatus === 'unpaid' && isPaid) return false;
  }

  return true;
};

/**
 * Multi-column comparator for appointments
 */
export const compareAppointmentsMultiSort = (
  a: Appointment,
  b: Appointment,
  sortFields: SortField[],
  getInvoice?: (apt: Appointment) => Invoice | undefined
): number => {
  for (const sort of sortFields) {
    const { column, direction } = sort;
    let cmp = 0;

    switch (column) {
      case 'token':
        cmp = a.tokenNumber.localeCompare(b.tokenNumber, undefined, { numeric: true });
        break;

      case 'priority': {
        const prioRank: Record<Priority, number> = { stat: 0, urgent: 1, routine: 2 };
        cmp = (prioRank[a.priority] ?? 2) - (prioRank[b.priority] ?? 2);
        break;
      }

      case 'patientName':
        cmp = a.patient.name.localeCompare(b.patient.name);
        break;

      case 'mrn':
        cmp = a.patient.mrn.localeCompare(b.patient.mrn, undefined, { numeric: true });
        break;

      case 'age':
        cmp = a.patient.age - b.patient.age;
        break;

      case 'modality':
        cmp = a.modality.code.localeCompare(b.modality.code);
        break;

      case 'service':
        cmp = a.service.name.localeCompare(b.service.name);
        break;

      case 'price':
        cmp = a.service.price - b.service.price;
        break;

      case 'date':
        cmp = a.date.localeCompare(b.date);
        break;

      case 'time':
        // Compare date first if different, then time
        cmp = (a.date + ' ' + a.time).localeCompare(b.date + ' ' + b.time);
        break;

      case 'room':
        cmp = a.roomNumber.localeCompare(b.roomNumber);
        break;

      case 'workflowState': {
        const stateRank: Record<WorkflowState, number> = {
          booked: 0,
          checked_in: 1,
          preparing: 2,
          in_progress: 3,
          acquired: 4,
          reading: 5,
          reported: 6,
          delivered: 7,
          no_show: 8,
          cancelled: 9,
        };
        cmp = (stateRank[a.workflowState] ?? 5) - (stateRank[b.workflowState] ?? 5);
        break;
      }

      case 'safety': {
        const safeA = a.screeningRequired ? (a.screeningCleared ? 2 : 0) : 1;
        const safeB = b.screeningRequired ? (b.screeningCleared ? 2 : 0) : 1;
        cmp = safeA - safeB;
        break;
      }

      case 'billing': {
        if (getInvoice) {
          const invA = getInvoice(a);
          const invB = getInvoice(b);
          const balA = invA ? invA.balanceDue : a.service.price;
          const balB = invB ? invB.balanceDue : b.service.price;
          cmp = balA - balB;
        }
        break;
      }

      case 'dose': {
        const doseA = a.doseLog?.doseValue || 0;
        const doseB = b.doseLog?.doseValue || 0;
        cmp = doseA - doseB;
        break;
      }

      default:
        cmp = 0;
        break;
    }

    if (cmp !== 0) {
      return direction === 'asc' ? cmp : -cmp;
    }
  }

  // Default stable fallback
  return a.tokenNumber.localeCompare(b.tokenNumber, undefined, { numeric: true });
};

/**
 * Multi-column sort toggle helper:
 * - If multiSort is false (regular click):
 *    - If column is primary and 'asc', becomes 'desc'.
 *    - If column is primary and 'desc', clears sort or resets.
 *    - If column is not primary, sets as single primary 'asc'.
 * - If multiSort is true (shift-click or multi-sort button):
 *    - Toggles column in the sort list or adds it.
 */
export const updateSortFields = (
  currentFields: SortField[],
  column: string,
  multiSort: boolean = false
): SortField[] => {
  const existingIdx = currentFields.findIndex((f) => f.column === column);

  if (!multiSort) {
    // Single column sort mode
    if (existingIdx === 0 && currentFields.length === 1) {
      if (currentFields[0].direction === 'asc') {
        return [{ column, direction: 'desc' }];
      } else {
        return []; // clear sorting to default
      }
    }
    return [{ column, direction: 'asc' }];
  } else {
    // Multi-column sort mode
    if (existingIdx >= 0) {
      const existing = currentFields[existingIdx];
      if (existing.direction === 'asc') {
        const next = [...currentFields];
        next[existingIdx] = { column, direction: 'desc' };
        return next;
      } else {
        // Remove from sort list
        return currentFields.filter((f) => f.column !== column);
      }
    } else {
      // Append secondary sort (up to 4 columns)
      if (currentFields.length >= 4) {
        return [...currentFields.slice(1), { column, direction: 'asc' }];
      }
      return [...currentFields, { column, direction: 'asc' }];
    }
  }
};
