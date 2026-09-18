import React, { useMemo, useState } from 'react';
import {
  Calendar,
  PlusCircle,
  X,
  Wallet,
  Loader2
} from 'lucide-react';
import { Patient, Service, Modality, Referrer, Priority, PaymentMethod, Room } from '../types';
import { BookingInput, PaymentStatus } from '../services/apiService';

interface NewBookingModalProps {
  patients: Patient[];
  /** Tenant-configured modalities (active ones are bookable). */
  modalities: Modality[];
  /** Tenant-configured imaging suites for the chosen modality. */
  rooms: Room[];
  /** Tenant-configured procedures/services (modality-scoped). */
  services: Service[];
  /** Tenant-configured payment methods — NEVER a hard-coded list. */
  paymentMethods: PaymentMethod[];
  referrers: Referrer[];
  /** Server-issued permissions: financial inputs render only when granted. */
  canRecordPayment: boolean;
  canApplyDiscount: boolean;
  currencySymbol: string;
  onCreateBooking: (input: BookingInput, newPatient?: Partial<Patient>) => Promise<unknown>;
  onClose: () => void;
}

export const NewBookingModal: React.FC<NewBookingModalProps> = ({
  patients,
  modalities,
  rooms,
  services,
  paymentMethods,
  referrers,
  canRecordPayment,
  canApplyDiscount,
  currencySymbol,
  onCreateBooking,
  onClose,
}) => {
  const [isNewPatient, setIsNewPatient] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [patientFilter, setPatientFilter] = useState('');

  // Existing patient selection
  const [selectedPatientId, setSelectedPatientId] = useState(patients[0]?.id || '');

  // New patient inputs
  const [newName, setNewName] = useState('');
  const [newPhone, setNewPhone] = useState('');
  const [newAge, setNewAge] = useState<number | ''>('');
  const [newGender, setNewGender] = useState<'male' | 'female' | 'other'>('male');
  const [newBloodGroup, setNewBloodGroup] = useState('');
  const [newAllergies, setNewAllergies] = useState('');

  // Study parameters. No magic fallback ids: if a tenant has not configured
  // modalities/services yet, booking must be blocked, not silently booked
  // against invented ids.
  const activeModalities = useMemo(() => modalities.filter(m => m.isActive), [modalities]);
  const [selectedModalityId, setSelectedModalityId] = useState<number | ''>(activeModalities[0]?.id ?? '');
  const modalityServices = useMemo(
    () => services.filter(s => s.modalityId === selectedModalityId),
    [services, selectedModalityId]
  );
  const modalityRooms = useMemo(
    () => rooms.filter(r => r.modalityId === selectedModalityId && r.isActive),
    [rooms, selectedModalityId]
  );
  const [selectedRoomId, setSelectedRoomId] = useState<number | ''>('');
  const [selectedServiceId, setSelectedServiceId] = useState<number | ''>(modalityServices[0]?.id ?? '');
  const [selectedReferrerId, setSelectedReferrerId] = useState<number | ''>('');
  const [priority, setPriority] = useState<Priority>('routine');
  const [scheduledDate, setScheduledDate] = useState(() => new Date().toISOString().split('T')[0]);
  const [scheduledTime, setScheduledTime] = useState('11:30');
  const [notes, setNotes] = useState('');

  // Booking-time financials. Totals/balance are display-only here — the
  // server recomputes everything from the tenant's configured service price.
  const [discountAmount, setDiscountAmount] = useState<number | ''>('');
  const [paymentStatus, setPaymentStatus] = useState<PaymentStatus>('unpaid');
  const [amountPaid, setAmountPaid] = useState<number | ''>('');
  const [paymentMethodId, setPaymentMethodId] = useState<number | ''>('');
  const [paymentReference, setPaymentReference] = useState('');

  const activePaymentMethods = useMemo(() => paymentMethods.filter(m => m.isActive), [paymentMethods]);

  const selectedService = services.find(s => s.id === selectedServiceId);
  const netPayable = useMemo(() => {
    const price = selectedService ? Number(selectedService.price) : 0;
    const discount = canApplyDiscount && discountAmount !== '' ? Math.max(0, Number(discountAmount)) : 0;
    return Math.max(0, price - discount);
  }, [selectedService, discountAmount, canApplyDiscount]);
  const balanceDue = paymentStatus === 'paid'
    ? 0
    : paymentStatus === 'partial'
      ? Math.max(0, netPayable - (amountPaid === '' ? 0 : Number(amountPaid)))
      : netPayable;

  const filteredPatients = useMemo(() => {
    const q = patientFilter.trim().toLowerCase();
    if (!q) return patients;
    return patients.filter(p =>
      p.name.toLowerCase().includes(q) ||
      p.mrn.toLowerCase().includes(q) ||
      p.phone.toLowerCase().includes(q)
    );
  }, [patients, patientFilter]);

  // Update service/suite selections when modality changes
  const handleModalityChange = (modId: number) => {
    setSelectedModalityId(modId);
    setSelectedRoomId('');
    const validServices = services.filter(s => s.modalityId === modId);
    setSelectedServiceId(validServices[0]?.id ?? '');
  };

  /** Client-side mirror of the server's payment validation — fails fast,
   *  but the server response remains authoritative. */
  const validateFinancials = (): string | null => {
    if (paymentStatus === 'unpaid') return null;
    if (amountPaid === '' || Number(amountPaid) <= 0) {
      return 'Enter the amount received for this payment status.';
    }
    if (paymentMethodId === '') {
      return 'Select the payment method used for this collection.';
    }
    const paid = Number(amountPaid);
    if (paymentStatus === 'partial' && paid >= netPayable) {
      return `A partial payment must be less than the payable amount (${currencySymbol} ${netPayable.toLocaleString()}).`;
    }
    if (paymentStatus === 'paid' && Math.abs(paid - netPayable) > 0.001) {
      return `The paid amount must match the payable amount (${currencySymbol} ${netPayable.toLocaleString()}).`;
    }
    return null;
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (submitting) return;
    if (!selectedServiceId || !selectedService) return;

    if (canRecordPayment) {
      const financialError = validateFinancials();
      if (financialError) {
        window.alert(financialError);
        return;
      }
    }

    // Token number, room assignment and the patient MRN are SERVER-AUTHORITATIVE:
    // they are minted by the API and returned on the created study. The client
    // never invents identifiers.

    const input: BookingInput = {
      serviceId: selectedService.id,
      roomId: selectedRoomId === '' ? undefined : Number(selectedRoomId),
      referrerId: referrers.find(r => r.id === selectedReferrerId)?.id,
      priority,
      date: scheduledDate,
      time: scheduledTime,
      notes,
      discountAmount:
        canApplyDiscount && discountAmount !== '' && Number(discountAmount) > 0
          ? Number(discountAmount)
          : undefined,
      payment: canRecordPayment
        ? {
            status: paymentStatus,
            amountPaid: paymentStatus === 'unpaid' ? undefined : Number(amountPaid),
            methodId: paymentStatus === 'unpaid' || paymentMethodId === '' ? undefined : Number(paymentMethodId),
            reference: paymentReference.trim() || undefined,
          }
        : { status: 'unpaid' },
    };

    setSubmitting(true);
    try {
      if (isNewPatient) {
        const newPatientData: Partial<Patient> = {
          name: newName,
          phone: newPhone,
          age: Number(newAge),
          gender: newGender,
          bloodGroup: newBloodGroup,
          allergies: newAllergies || 'NKDA',
        };
        await onCreateBooking(input, newPatientData);
      } else {
        const existingPatient = patients.find(p => p.id === selectedPatientId);
        if (!existingPatient) {
          setSubmitting(false);
          return;
        }
        await onCreateBooking({ ...input, patientId: existingPatient.id });
      }
      // Close only after the server has confirmed the booking.
      onClose();
    } catch {
      // The API layer surfaces the server's validation message as a flash;
      // keep the form open with the submitting lock released.
    } finally {
      setSubmitting(false);
    }
  };

  const modalityLabel = (m: Modality) => (m.name.includes(m.code) ? m.name : `${m.name} (${m.code})`);
  const today = new Date().toISOString().split('T')[0];
  const selectedRoom = modalityRooms.find(r => r.id === selectedRoomId);
  const summaryPatient = isNewPatient ? (newName.trim() || 'New walk-in') : (patients.find(p => p.id === selectedPatientId)?.name ?? '—');

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white border border-slate-200 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] overflow-y-auto">
        {/* Header */}
        <div className="flex items-center justify-between border-b border-slate-200 pb-3">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-700 border border-cyan-200 flex items-center justify-center shadow-xs">
              <Calendar className="w-5 h-5 text-cyan-600" />
            </div>
            <div>
              <h2 className="font-bold text-slate-900 text-base">Book Diagnostic Imaging Study</h2>
              <p className="text-xs text-slate-500">Radiology scheduler & walk-in registration</p>
            </div>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          {/* Patient Selection Toggle */}
          <div className="space-y-2">
            <div className="flex items-center justify-between">
              <label className="text-xs font-bold text-slate-800 uppercase tracking-wider">Patient Details</label>
              <div className="flex space-x-1 bg-slate-100 p-1 rounded-lg border border-slate-200 text-xs">
                <button
                  type="button"
                  onClick={() => setIsNewPatient(false)}
                  className={`px-3 py-1 rounded font-semibold transition-colors cursor-pointer ${
                    !isNewPatient ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  Existing Patient
                </button>
                <button
                  type="button"
                  onClick={() => setIsNewPatient(true)}
                  className={`px-3 py-1 rounded font-semibold transition-colors cursor-pointer ${
                    isNewPatient ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
                  }`}
                >
                  + New Walk-In
                </button>
              </div>
            </div>

            {!isNewPatient ? (
              <div className="space-y-1.5">
                <input
                  type="text"
                  value={patientFilter}
                  onChange={(e) => setPatientFilter(e.target.value)}
                  placeholder="Quick find by name, MRN or phone…"
                  className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500"
                />
                <select
                  value={selectedPatientId}
                  onChange={(e) => setSelectedPatientId(e.target.value)}
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
                >
                  {filteredPatients.length === 0 && (
                    <option value="">No patients match “{patientFilter}”</option>
                  )}
                  {filteredPatients.map(p => (
                    <option key={p.id} value={p.id}>
                      {p.name} ({p.mrn}) - {p.age}y {p.gender.toUpperCase()} - {p.phone}
                    </option>
                  ))}
                </select>
              </div>
            ) : (
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 bg-slate-50 p-3 rounded-xl border border-slate-200 text-xs">
                <div>
                  <span className="text-[11px] text-slate-600 font-medium block mb-1">Full Name *</span>
                  <input
                    type="text"
                    required
                    value={newName}
                    onChange={(e) => setNewName(e.target.value)}
                    placeholder="e.g. Tariq Mehmood"
                    className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500"
                  />
                </div>
                <div>
                  <span className="text-[11px] text-slate-600 font-medium block mb-1">Phone Number *</span>
                  <input
                    type="text"
                    required
                    aria-label="Phone Number"
                    value={newPhone}
                    onChange={(e) => setNewPhone(e.target.value)}
                    className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500 font-mono"
                  />
                </div>
                <div>
                  <span className="text-[11px] text-slate-600 font-medium block mb-1">Age & Gender</span>
                  <div className="flex space-x-2">
                    <input
                      type="number"
                      required
                      min="0"
                      aria-label="Patient age"
                      value={newAge}
                      onChange={(e) => setNewAge(e.target.value === '' ? '' : Number(e.target.value))}
                      className="w-20 bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs"
                    />
                    <select
                      value={newGender}
                      onChange={(e) => setNewGender(e.target.value as any)}
                      className="flex-1 bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs"
                    >
                      <option value="male">Male</option>
                      <option value="female">Female</option>
                      <option value="other">Other</option>
                    </select>
                  </div>
                </div>
                <div>
                  <span className="text-[11px] text-slate-600 font-medium block mb-1">Known Allergies</span>
                  <input
                    type="text"
                    value={newAllergies}
                    onChange={(e) => setNewAllergies(e.target.value)}
                    placeholder="e.g. Contrast, Penicillin, NKDA"
                    className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs"
                  />
                </div>
              </div>
            )}
          </div>

          {/* Modality, Suite & Procedure */}
          <div className="space-y-2">
            <label className="text-xs font-bold text-slate-800 uppercase tracking-wider">Imaging Modality & Exam</label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Modality</span>
                <select
                  aria-label="Modality"
                  value={selectedModalityId}
                  onChange={(e) => handleModalityChange(Number(e.target.value))}
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
                >
                  {activeModalities.length === 0 && <option value="">No modalities configured</option>}
                  {activeModalities.map(m => (
                    <option key={m.id} value={m.id}>
                      {modalityLabel(m)}
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Modality Suite</span>
                <select
                  aria-label="Modality Suite"
                  value={selectedRoomId}
                  onChange={(e) => setSelectedRoomId(e.target.value === '' ? '' : Number(e.target.value))}
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
                >
                  <option value="">Auto-assign suite</option>
                  {modalityRooms.map(r => (
                    <option key={r.id} value={r.id}>{r.name}</option>
                  ))}
                </select>
                {modalityRooms.length === 0 && (
                  <p className="text-[10px] text-amber-700 mt-1">
                    No imaging suites are configured for this modality — contact your administrator (Catalog &amp; Forms → Imaging Suites).
                  </p>
                )}
              </div>

              <div className="sm:col-span-2">
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Procedure Service</span>
                <select
                  aria-label="Procedure Service"
                  value={selectedServiceId}
                  onChange={(e) => setSelectedServiceId(e.target.value === '' ? '' : Number(e.target.value))}
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
                >
                  {modalityServices.length === 0 && (
                    <option value="">No procedures configured for this modality</option>
                  )}
                  {modalityServices.map(s => (
                    <option key={s.id} value={s.id}>
                      {s.name} - {currencySymbol} {Number(s.price).toLocaleString()}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Priority & Scheduling */}
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
            <div>
              <span className="text-[11px] text-slate-600 font-medium block mb-1 font-semibold">Priority</span>
              <select
                value={priority}
                onChange={(e) => setPriority(e.target.value as Priority)}
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-bold focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
              >
                <option value="routine">Routine</option>
                <option value="urgent">Urgent</option>
                <option value="stat">STAT (Immediate Emergency)</option>
              </select>
            </div>

            <div>
              <span className="text-[11px] text-slate-600 font-medium block mb-1 font-semibold">Scheduled Date</span>
              <input
                type="date"
                required
                min={today}
                value={scheduledDate}
                onChange={(e) => setScheduledDate(e.target.value)}
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-mono shadow-xs focus:outline-none focus:ring-1 focus:ring-cyan-500"
              />
            </div>

            <div>
              <span className="text-[11px] text-slate-600 font-medium block mb-1 font-semibold">Scheduled Time</span>
              <input
                type="time"
                required
                value={scheduledTime}
                onChange={(e) => setScheduledTime(e.target.value)}
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-mono shadow-xs focus:outline-none focus:ring-1 focus:ring-cyan-500"
              />
            </div>

            <div className="sm:col-span-3">
              <span className="text-[11px] text-slate-600 font-medium block mb-1 font-semibold">Referring Doctor</span>
              <select
                value={selectedReferrerId}
                onChange={(e) => setSelectedReferrerId(e.target.value === '' ? '' : Number(e.target.value))}
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs cursor-pointer shadow-xs"
              >
                <option value="">Self / Walk-in (no referrer)</option>
                {referrers.map(r => (
                  <option key={r.id} value={r.id}>
                    {r.name} ({r.specialty})
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Clinical Indication / Notes */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Clinical Indication / Notes
            </label>
            <input
              type="text"
              placeholder="e.g. Rule out fracture, persistent headache, pre-surgical assessment..."
              value={notes}
              onChange={(e) => setNotes(e.target.value)}
              className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500 shadow-xs"
            />
          </div>

          {/* Financial Information — tenant-configured, server-validated */}
          {(canRecordPayment || canApplyDiscount) && selectedService && (
            <div className="space-y-2 bg-slate-50 border border-slate-200 rounded-xl p-3">
              <div className="flex items-center justify-between">
                <label className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center">
                  <Wallet className="w-3.5 h-3.5 mr-1.5 text-cyan-600" /> Payment
                </label>
                <div className="text-xs font-bold text-slate-900 font-mono">
                  Payable: {currencySymbol} {netPayable.toLocaleString()}
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                {canApplyDiscount && (
                  <div>
                    <span className="text-[11px] text-slate-600 font-medium block mb-1">Discount ({currencySymbol})</span>
                    <input
                      type="number"
                      min="0"
                      value={discountAmount}
                      onChange={(e) => setDiscountAmount(e.target.value === '' ? '' : Number(e.target.value))}
                      placeholder="0"
                      className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs font-mono"
                    />
                  </div>
                )}
                <div>
                  <span className="text-[11px] text-slate-600 font-medium block mb-1">Payment Status</span>
                  <select
                    aria-label="Payment Status"
                    value={paymentStatus}
                    onChange={(e) => {
                      const next = e.target.value as PaymentStatus;
                      setPaymentStatus(next);
                      if (next === 'paid') setAmountPaid(netPayable);
                    }}
                    className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs cursor-pointer"
                  >
                    <option value="unpaid">Unpaid</option>
                    <option value="partial">Partially Paid</option>
                    <option value="paid" disabled={netPayable <= 0}>Paid</option>
                  </select>
                </div>
                {paymentStatus !== 'unpaid' && (
                  <div>
                    <span className="text-[11px] text-slate-600 font-medium block mb-1">Amount Paid ({currencySymbol})</span>
                    <input
                      type="number"
                      min="0"
                      aria-label="Amount Paid"
                      value={amountPaid}
                      onChange={(e) => setAmountPaid(e.target.value === '' ? '' : Number(e.target.value))}
                      placeholder="0"
                      className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs font-mono"
                    />
                  </div>
                )}
              </div>

              {paymentStatus !== 'unpaid' && (
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                  <div className="sm:col-span-2">
                    <span className="text-[11px] text-slate-600 font-medium block mb-1">Payment Method</span>
                    <select
                      aria-label="Payment Method"
                      value={paymentMethodId}
                      onChange={(e) => setPaymentMethodId(e.target.value === '' ? '' : Number(e.target.value))}
                      className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs cursor-pointer"
                    >
                      <option value="">Select method…</option>
                      {activePaymentMethods.length === 0 && (
                        <option value="" disabled>No payment methods configured — contact your administrator</option>
                      )}
                      {activePaymentMethods.map(m => (
                        <option key={m.id} value={m.id}>{m.name}</option>
                      ))}
                    </select>
                  </div>
                  <div>
                    <span className="text-[11px] text-slate-600 font-medium block mb-1">Reference / Txn No.</span>
                    <input
                      type="text"
                      value={paymentReference}
                      onChange={(e) => setPaymentReference(e.target.value)}
                      placeholder="Optional"
                      className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs font-mono"
                    />
                  </div>
                </div>
              )}

              <div className="flex items-center justify-between text-[11px] text-slate-600 pt-1">
                <span>Total: <strong className="text-slate-900 font-mono">{currencySymbol} {netPayable.toLocaleString()}</strong></span>
                <span>Paid: <strong className="text-slate-900 font-mono">{currencySymbol} {(paymentStatus === 'unpaid' ? 0 : Number(amountPaid) || 0).toLocaleString()}</strong></span>
                <span>Balance: <strong className={(balanceDue > 0 ? 'text-rose-600' : 'text-emerald-600') + ' font-mono'}>{currencySymbol} {balanceDue.toLocaleString()}</strong></span>
              </div>
            </div>
          )}

          {/* Pre-submission summary — what will be booked, at a glance */}
          <div className="bg-cyan-50 border border-cyan-100 rounded-xl p-2.5 text-[11px] text-slate-700 flex flex-wrap gap-x-3 gap-y-1">
            <span className="font-bold text-slate-900">{summaryPatient}</span>
            <span>·</span>
            <span>{selectedService?.name ?? 'No procedure selected'}</span>
            <span>·</span>
            <span>{activeModalities.find(m => m.id === selectedModalityId)?.name ?? '—'}{selectedRoom ? ` / ${selectedRoom.name}` : ''}</span>
            <span>·</span>
            <span>{scheduledDate} {scheduledTime}</span>
            {selectedService && (
              <>
                <span>·</span>
                <span className="font-mono font-bold">{currencySymbol} {netPayable.toLocaleString()}</span>
                <span>·</span>
                <span className={paymentStatus === 'unpaid' ? 'text-amber-700 font-semibold' : 'text-emerald-700 font-semibold'}>
                  {paymentStatus === 'unpaid' ? 'Unpaid' : paymentStatus === 'partial' ? 'Partially Paid' : 'Paid'}
                </span>
              </>
            )}
          </div>

          <div className="border-t border-slate-200 pt-3 flex space-x-2">
            <button
              type="button"
              onClick={onClose}
              disabled={submitting}
              className="flex-1 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer shadow-xs disabled:opacity-60 disabled:cursor-not-allowed"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={!selectedServiceId || submitting}
              className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-md shadow-cyan-600/30 cursor-pointer disabled:bg-slate-300 disabled:cursor-not-allowed disabled:shadow-none"
            >
              {submitting
                ? <Loader2 className="w-4 h-4 animate-spin" />
                : <PlusCircle className="w-4 h-4" />}
              <span>{submitting ? 'Booking…' : 'Confirm & Generate Token'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
