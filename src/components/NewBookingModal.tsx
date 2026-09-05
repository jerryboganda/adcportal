import React, { useState } from 'react';
import {
  Calendar,
  User,
  PlusCircle,
  X,
  Flame,
  CheckCircle2,
  Clock
} from 'lucide-react';
import { Patient, Service, Modality, Referrer, Priority, Appointment } from '../types';

interface NewBookingModalProps {
  patients: Patient[];
  modalities: Modality[];
  services: Service[];
  referrers: Referrer[];
  existingAppointments: Appointment[];
  onCreateBooking: (newApt: Partial<Appointment>, newPatient?: Partial<Patient>) => void;
  onClose: () => void;
}

export const NewBookingModal: React.FC<NewBookingModalProps> = ({
  patients,
  modalities,
  services,
  referrers,
  existingAppointments,
  onCreateBooking,
  onClose,
}) => {
  const [isNewPatient, setIsNewPatient] = useState(false);

  // Existing patient selection
  const [selectedPatientId, setSelectedPatientId] = useState(patients[0]?.id || '');

  // New patient inputs
  const [newName, setNewName] = useState('');
  const [newPhone, setNewPhone] = useState('+92 300 ');
  const [newAge, setNewAge] = useState<number>(35);
  const [newGender, setNewGender] = useState<'male' | 'female' | 'other'>('male');
  const [newBloodGroup, setNewBloodGroup] = useState('B+');
  const [newAllergies, setNewAllergies] = useState('');

  // Study parameters
  const [selectedModalityId, setSelectedModalityId] = useState<number>(modalities[0]?.id || 1);
  const filteredServices = services.filter(s => s.modalityId === selectedModalityId);
  const [selectedServiceId, setSelectedServiceId] = useState<number>(filteredServices[0]?.id || services[0]?.id || 101);
  const [selectedReferrerId, setSelectedReferrerId] = useState<number>(referrers[0]?.id || 1);
  const [priority, setPriority] = useState<Priority>('routine');
  const [scheduledTime, setScheduledTime] = useState('11:30 AM');
  const [notes, setNotes] = useState('');

  // Update selected service when modality changes
  const handleModalityChange = (modId: number) => {
    setSelectedModalityId(modId);
    const validServices = services.filter(s => s.modalityId === modId);
    if (validServices.length > 0) {
      setSelectedServiceId(validServices[0].id);
    }
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const modality = modalities.find(m => m.id === selectedModalityId) || modalities[0];
    const service = services.find(s => s.id === selectedServiceId) || services[0];
    const referrer = referrers.find(r => r.id === selectedReferrerId);

    // Auto-generate token number e.g. "DX-03", "MR-03"
    const modCount = existingAppointments.filter(a => a.modalityId === modality.id).length + 1;
    const tokenNumber = `${modality.code}-${String(modCount).padStart(2, '0')}`;

    let roomNumber = `Room ${modality.id} (${modality.name.split(' ')[0]} Suite)`;
    if (modality.code === 'DX') roomNumber = 'Room 1 (X-Ray Suite A)';
    if (modality.code === 'US') roomNumber = 'Room 2 (Ultrasound Suite)';
    if (modality.code === 'CT') roomNumber = 'Room 3 (CT 128-Slice)';
    if (modality.code === 'MR') roomNumber = 'Room 4 (MRI 1.5T Suite)';
    if (modality.code === 'MG') roomNumber = 'Room 5 (Mammography Suite)';

    if (isNewPatient) {
      const generatedMrn = `ADC-2026-${Math.floor(10000 + Math.random() * 90000)}`;
      const newPatientData: Partial<Patient> = {
        id: `pat-${Date.now()}`,
        mrn: generatedMrn,
        name: newName,
        phone: newPhone,
        age: newAge,
        gender: newGender,
        bloodGroup: newBloodGroup,
        allergies: newAllergies || 'NKDA',
      };

      onCreateBooking(
        {
          tokenNumber,
          serviceId: service.id,
          service,
          modalityId: modality.id,
          modality,
          referrerId: referrer?.id,
          referrer,
          priority,
          time: scheduledTime,
          date: new Date().toISOString().split('T')[0],
          workflowState: 'booked',
          screeningRequired: service.requiresScreening || service.requiresContrast,
          screeningCleared: !(service.requiresScreening || service.requiresContrast),
          roomNumber,
          notes,
        },
        newPatientData
      );
    } else {
      const existingPatient = patients.find(p => p.id === selectedPatientId) || patients[0];
      onCreateBooking({
        patientId: existingPatient.id,
        patient: existingPatient,
        tokenNumber,
        serviceId: service.id,
        service,
        modalityId: modality.id,
        modality,
        referrerId: referrer?.id,
        referrer,
        priority,
        time: scheduledTime,
        date: new Date().toISOString().split('T')[0],
        workflowState: 'booked',
        screeningRequired: service.requiresScreening || service.requiresContrast,
        screeningCleared: !(service.requiresScreening || service.requiresContrast),
        roomNumber,
        notes,
      });
    }

    onClose();
  };

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
              <select
                value={selectedPatientId}
                onChange={(e) => setSelectedPatientId(e.target.value)}
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
              >
                {patients.map(p => (
                  <option key={p.id} value={p.id}>
                    {p.name} ({p.mrn}) - {p.age}y {p.gender.toUpperCase()} - {p.phone}
                  </option>
                ))}
              </select>
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
                      value={newAge}
                      onChange={(e) => setNewAge(Number(e.target.value))}
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

          {/* Modality & Procedure */}
          <div className="space-y-2">
            <label className="text-xs font-bold text-slate-800 uppercase tracking-wider">Imaging Modality & Exam</label>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Modality Suite</span>
                <select
                  value={selectedModalityId}
                  onChange={(e) => handleModalityChange(Number(e.target.value))}
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
                >
                  {modalities.map(m => (
                    <option key={m.id} value={m.id}>
                      {m.name} ({m.code})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Procedure Service</span>
                <select
                  value={selectedServiceId}
                  onChange={(e) => setSelectedServiceId(Number(e.target.value))}
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
                >
                  {filteredServices.map(s => (
                    <option key={s.id} value={s.id}>
                      {s.name} - Rs. {s.price.toLocaleString()}
                    </option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          {/* Priority & Time */}
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
              <span className="text-[11px] text-slate-600 font-medium block mb-1 font-semibold">Scheduled Time</span>
              <input
                type="text"
                value={scheduledTime}
                onChange={(e) => setScheduledTime(e.target.value)}
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-mono shadow-xs"
              />
            </div>

            <div>
              <span className="text-[11px] text-slate-600 font-medium block mb-1 font-semibold">Referring Doctor</span>
              <select
                value={selectedReferrerId}
                onChange={(e) => setSelectedReferrerId(Number(e.target.value))}
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs cursor-pointer shadow-xs"
              >
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

          <div className="border-t border-slate-200 pt-3 flex space-x-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer shadow-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs shadow-md shadow-cyan-600/30 cursor-pointer"
            >
              <PlusCircle className="w-4 h-4" />
              <span>Confirm & Generate Token</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
