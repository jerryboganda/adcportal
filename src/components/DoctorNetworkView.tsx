import React, { useState } from 'react';
import {
  Stethoscope,
  Users,
  Building2,
  Phone,
  Mail,
  FileText,
  Send,
  Plus,
  Edit2,
  Trash2,
  CheckCircle2,
  Clock,
  Search,
  DollarSign,
  Printer,
  ChevronRight,
  Sparkles,
  Share2,
  Check,
  X,
  Activity,
  AlertTriangle,
  ArrowUpRight,
  TrendingUp,
  MessageSquare,
  Award
} from 'lucide-react';
import {
  Referrer,
  Appointment,
  Patient,
  DoctorDispatchLog,
  Modality
} from '../types';

interface DoctorNetworkViewProps {
  referrers: Referrer[];
  appointments: Appointment[];
  patients: Patient[];
  modalities: Modality[];
  doctorDispatches: DoctorDispatchLog[];
  onAddReferrer: (ref: Omit<Referrer, 'id'>) => void;
  onUpdateReferrer: (ref: Referrer) => void;
  onDeleteReferrer: (refId: number) => void;
  onAddDoctorDispatch: (dispatch: Omit<DoctorDispatchLog, 'id' | 'sentAt'>) => void;
}

type DoctorSubTab = 'directory' | 'manifest' | 'dispatches' | 'commission' | 'analytics';

export const DoctorNetworkView: React.FC<DoctorNetworkViewProps> = ({
  referrers,
  appointments,
  patients,
  modalities,
  doctorDispatches,
  onAddReferrer,
  onUpdateReferrer,
  onDeleteReferrer,
  onAddDoctorDispatch,
}) => {
  const [activeSubTab, setActiveSubTab] = useState<DoctorSubTab>('directory');
  const [selectedDoctorId, setSelectedDoctorId] = useState<number | 'all'>('all');
  const [searchQuery, setSearchQuery] = useState('');
  const [specialtyFilter, setSpecialtyFilter] = useState('all');

  // Doctor Form Modal
  const [doctorModalOpen, setDoctorModalOpen] = useState(false);
  const [editingDoctor, setEditingDoctor] = useState<Referrer | null>(null);
  const [docName, setDocName] = useState('');
  const [docClinic, setDocClinic] = useState('');
  const [docSpecialty, setDocSpecialty] = useState('');
  const [docPhone, setDocPhone] = useState('');
  const [docEmail, setDocEmail] = useState('');

  // Dispatch Report Modal
  const [dispatchModalOpen, setDispatchModalOpen] = useState(false);
  const [dispatchApt, setDispatchApt] = useState<Appointment | null>(null);
  const [dispatchChannel, setDispatchChannel] = useState<'whatsapp' | 'email' | 'sms'>('whatsapp');
  const [dispatchRecipient, setDispatchRecipient] = useState('');
  const [dispatchSuccessToast, setDispatchSuccessToast] = useState(false);

  // Settlement Print Modal
  const [settlementDoctor, setSettlementDoctor] = useState<Referrer | null>(null);

  // Specialties list
  const specialties = Array.from(new Set(referrers.map(r => r.specialty))).filter(Boolean);

  // Appointments with referrers
  const referredAppointments = appointments.filter(a => a.referrerId !== undefined && a.referrerId !== null);

  // Calculate metrics
  const totalReferredCount = referredAppointments.length;
  const reportedReferredCount = referredAppointments.filter(a => ['reported', 'delivered'].includes(a.workflowState)).length;

  // Filtered doctors
  const filteredDoctors = referrers.filter(doc => {
    const matchesSearch =
      doc.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      doc.clinicName.toLowerCase().includes(searchQuery.toLowerCase()) ||
      doc.specialty.toLowerCase().includes(searchQuery.toLowerCase());
    const matchesSpecialty = specialtyFilter === 'all' || doc.specialty === specialtyFilter;
    return matchesSearch && matchesSpecialty;
  });

  // Filtered manifest
  const filteredManifest = referredAppointments.filter(apt => {
    const matchesDoctor = selectedDoctorId === 'all' || apt.referrerId === selectedDoctorId;
    const matchesSearch =
      apt.patient.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      apt.tokenNumber.toLowerCase().includes(searchQuery.toLowerCase()) ||
      (apt.service?.name || '').toLowerCase().includes(searchQuery.toLowerCase()) ||
      (apt.referrer?.name || '').toLowerCase().includes(searchQuery.toLowerCase());
    return matchesDoctor && matchesSearch;
  });

  const handleOpenDoctorModal = (doc?: Referrer) => {
    if (doc) {
      setEditingDoctor(doc);
      setDocName(doc.name);
      setDocClinic(doc.clinicName);
      setDocSpecialty(doc.specialty);
      setDocPhone(doc.phone);
      setDocEmail(doc.email);
    } else {
      setEditingDoctor(null);
      setDocName('');
      setDocClinic('Islamabad Medical Center');
      setDocSpecialty('General Medicine');
      setDocPhone('+92 300 ');
      setDocEmail('');
    }
    setDoctorModalOpen(true);
  };

  const handleSaveDoctor = (e: React.FormEvent) => {
    e.preventDefault();
    if (!docName.trim() || !docPhone.trim()) {
      alert('Please fill doctor name and contact number.');
      return;
    }

    if (editingDoctor) {
      onUpdateReferrer({
        ...editingDoctor,
        name: docName,
        clinicName: docClinic,
        specialty: docSpecialty,
        phone: docPhone,
        email: docEmail,
      });
    } else {
      onAddReferrer({
        name: docName,
        clinicName: docClinic,
        specialty: docSpecialty,
        phone: docPhone,
        email: docEmail,
      });
    }
    setDoctorModalOpen(false);
  };

  const handleOpenDispatchModal = (apt: Appointment) => {
    setDispatchApt(apt);
    const doctor = referrers.find(r => r.id === apt.referrerId);
    setDispatchRecipient(doctor?.phone || doctor?.email || '+92 300 5551234');
    setDispatchChannel('whatsapp');
    setDispatchModalOpen(true);
  };

  const handleSendDispatch = (e: React.FormEvent) => {
    e.preventDefault();
    if (!dispatchApt) return;

    const doctor = referrers.find(r => r.id === dispatchApt.referrerId);
    onAddDoctorDispatch({
      appointmentId: dispatchApt.id,
      tokenNumber: dispatchApt.tokenNumber,
      patientName: dispatchApt.patient.name,
      referrerId: dispatchApt.referrerId || 1,
      referrerName: doctor?.name || 'Referring Physician',
      studyName: dispatchApt.service?.name || 'Radiology Examination',
      channel: dispatchChannel,
      recipientContact: dispatchRecipient,
      status: 'delivered',
      sentBy: 'Reception / Portal Auto-Gateway',
    });

    setDispatchModalOpen(false);
    setDispatchSuccessToast(true);
    setTimeout(() => setDispatchSuccessToast(false), 3500);
  };

  return (
    <div className="space-y-6">
      {/* Top Header Banner */}
      <div className="bg-gradient-to-r from-slate-900 via-sky-950 to-indigo-950 rounded-2xl p-6 text-white shadow-lg border border-slate-700/50">
        <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div className="space-y-1">
            <div className="inline-flex items-center space-x-2 px-2.5 py-0.5 rounded-full bg-sky-500/20 text-sky-300 text-xs font-semibold border border-sky-500/30">
              <Stethoscope className="w-3.5 h-3.5" />
              <span>Referring Clinicians & Consultant Portal Hub</span>
            </div>
            <h1 className="text-2xl font-black tracking-tight text-white">Doctor Network & Referral Management</h1>
            <p className="text-sm text-slate-300">
              Manage referring physician directory, track live patient study manifests, dispatch verified reports via WhatsApp, and audit clinical commissions.
            </p>
          </div>
          <div className="flex items-center space-x-3">
            <button
              onClick={() => handleOpenDoctorModal()}
              className="flex items-center space-x-2 px-4 py-2 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 text-xs font-bold transition-all shadow-md shadow-sky-500/20 cursor-pointer"
            >
              <Plus className="w-4 h-4" />
              <span>Register Referring Doctor</span>
            </button>
          </div>
        </div>

        {/* Quick Highlights Strip */}
        <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-6 pt-5 border-t border-slate-700/60">
          <div className="bg-slate-800/60 p-3 rounded-xl border border-slate-700/50">
            <span className="text-[11px] text-slate-400 font-medium block">Active Referring Doctors</span>
            <span className="text-xl font-black text-sky-400">{referrers.length} Consultants</span>
          </div>
          <div className="bg-slate-800/60 p-3 rounded-xl border border-slate-700/50">
            <span className="text-[11px] text-slate-400 font-medium block">Total Referred Studies</span>
            <span className="text-xl font-black text-cyan-400">{totalReferredCount} Studies</span>
          </div>
          <div className="bg-slate-800/60 p-3 rounded-xl border border-slate-700/50">
            <span className="text-[11px] text-slate-400 font-medium block">Verified & Reported</span>
            <span className="text-xl font-black text-emerald-400">{reportedReferredCount} Reports</span>
          </div>
          <div className="bg-slate-800/60 p-3 rounded-xl border border-slate-700/50">
            <span className="text-[11px] text-slate-400 font-medium block">Dispatches Logged</span>
            <span className="text-xl font-black text-indigo-400">{doctorDispatches.length} WhatsApp/Emails</span>
          </div>
        </div>

        {/* Sub-Navigation Tabs */}
        <div className="flex space-x-1.5 mt-5 border-t border-slate-700/60 pt-4 overflow-x-auto scrollbar-none">
          {[
            { id: 'directory', label: 'Doctor Directory', icon: Users, count: referrers.length },
            { id: 'manifest', label: 'Referral Study Manifest', icon: Activity, count: totalReferredCount },
            { id: 'dispatches', label: 'Report Dispatches & Logs', icon: Send, count: doctorDispatches.length },
            { id: 'commission', label: 'Consultant Settlements', icon: DollarSign },
            { id: 'analytics', label: 'Referral Analytics', icon: TrendingUp },
          ].map(tab => {
            const Icon = tab.icon;
            const isActive = activeSubTab === tab.id;
            return (
              <button
                key={tab.id}
                onClick={() => setActiveSubTab(tab.id as DoctorSubTab)}
                className={`flex items-center space-x-2 px-4 py-2 rounded-xl text-xs font-bold transition-all whitespace-nowrap cursor-pointer ${
                  isActive
                    ? 'bg-sky-500 text-slate-950 shadow-md shadow-sky-500/25'
                    : 'text-slate-300 hover:text-white hover:bg-slate-800/70'
                }`}
              >
                <Icon className="w-4 h-4" />
                <span>{tab.label}</span>
                {tab.count !== undefined && (
                  <span className={`text-[10px] px-1.5 py-0.2 rounded-md font-extrabold ${isActive ? 'bg-slate-900 text-sky-400' : 'bg-slate-700 text-slate-300'}`}>
                    {tab.count}
                  </span>
                )}
              </button>
            );
          })}
        </div>
      </div>

      {/* Success Notification */}
      {dispatchSuccessToast && (
        <div className="p-3.5 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded-xl text-xs font-semibold flex items-center space-x-2 shadow-xs">
          <CheckCircle2 className="w-4 h-4 text-emerald-600 shrink-0" />
          <span>Radiology report and secure portal link successfully dispatched to the referring physician!</span>
        </div>
      )}

      {/* SUBTAB 1: DOCTOR DIRECTORY */}
      {activeSubTab === 'directory' && (
        <div className="space-y-6">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div className="flex flex-wrap items-center gap-3 flex-1">
              <div className="relative flex-1 min-w-[240px]">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Search by doctor name, specialty, hospital..."
                  value={searchQuery}
                  onChange={e => setSearchQuery(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-sky-500 text-slate-800"
                />
              </div>
              <div className="flex items-center space-x-1.5">
                <span className="text-xs text-slate-500 font-medium">Specialty:</span>
                <select
                  value={specialtyFilter}
                  onChange={e => setSpecialtyFilter(e.target.value)}
                  className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 font-medium text-slate-700 focus:outline-none focus:ring-1 focus:ring-sky-500"
                >
                  <option value="all">All Specialties</option>
                  {specialties.map(spec => (
                    <option key={spec} value={spec}>{spec}</option>
                  ))}
                </select>
              </div>
            </div>
            <button
              onClick={() => handleOpenDoctorModal()}
              className="flex items-center space-x-1.5 px-4 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold transition-all shadow-xs cursor-pointer shrink-0"
            >
              <Plus className="w-4 h-4" />
              <span>Add Doctor</span>
            </button>
          </div>

          {/* Doctor Cards */}
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredDoctors.map(doctor => {
              const docAppointments = appointments.filter(a => a.referrerId === doctor.id);
              const activeCount = docAppointments.filter(a => !['cancelled', 'no_show', 'delivered'].includes(a.workflowState)).length;

              return (
                <div key={doctor.id} className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs hover:border-sky-300 transition-all flex flex-col justify-between">
                  <div>
                    <div className="flex items-start justify-between">
                      <div className="flex items-center space-x-3">
                        <div className="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 font-bold flex items-center justify-center text-sm shadow-xs">
                          <Stethoscope className="w-5 h-5" />
                        </div>
                        <div>
                          <h3 className="font-bold text-slate-900 text-sm">{doctor.name}</h3>
                          <p className="text-xs text-sky-700 font-semibold">{doctor.specialty}</p>
                        </div>
                      </div>
                    </div>

                    <div className="mt-4 space-y-2 text-xs text-slate-600 bg-slate-50/70 p-3 rounded-lg border border-slate-100">
                      <div className="flex items-center justify-between">
                        <span className="text-slate-500">Affiliated Clinic:</span>
                        <span className="font-semibold text-slate-800 text-right">{doctor.clinicName}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-slate-500">Phone / WhatsApp:</span>
                        <span className="font-mono text-slate-800 font-semibold">{doctor.phone}</span>
                      </div>
                      <div className="flex items-center justify-between">
                        <span className="text-slate-500">Email:</span>
                        <span className="text-slate-700 truncate max-w-[180px]">{doctor.email || 'N/A'}</span>
                      </div>
                    </div>

                    {/* Quick Direct Communication Strip */}
                    <div className="mt-2.5 flex items-center gap-1.5 pt-2 border-t border-slate-100">
                      <a
                        href={`https://wa.me/${doctor.phone.replace(/[^0-9]/g, '')}`}
                        target="_blank"
                        rel="noreferrer"
                        className="flex-1 py-1.5 px-2 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 rounded-lg text-[11px] font-bold border border-emerald-200 flex items-center justify-center space-x-1 transition-colors"
                        title="Chat on WhatsApp"
                      >
                        <MessageSquare className="w-3 h-3 text-emerald-600" />
                        <span>WhatsApp</span>
                      </a>
                      <a
                        href={`tel:${doctor.phone}`}
                        className="py-1.5 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold border border-slate-200 flex items-center justify-center transition-colors"
                        title="Call Doctor"
                      >
                        <Phone className="w-3 h-3 text-slate-600" />
                      </a>
                      {doctor.email && (
                        <a
                          href={`mailto:${doctor.email}?subject=Patient%20Referral%20Updates%20-%20Amad%20Diagnostic%20Centre`}
                          className="py-1.5 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-[11px] font-semibold border border-slate-200 flex items-center justify-center transition-colors"
                          title="Send Email"
                        >
                          <Mail className="w-3 h-3 text-slate-600" />
                        </a>
                      )}
                    </div>

                    {/* Stats Strip */}
                    <div className="mt-3 grid grid-cols-2 gap-2 text-center text-xs">
                      <div className="p-2 rounded-lg bg-slate-100/70 border border-slate-200">
                        <span className="text-[10px] text-slate-500 block">Total Referrals</span>
                        <span className="font-bold text-slate-900 text-sm">{docAppointments.length}</span>
                      </div>
                      <div className="p-2 rounded-lg bg-sky-50 border border-sky-100">
                        <span className="text-[10px] text-sky-700 block">In-Progress Studies</span>
                        <span className="font-bold text-sky-900 text-sm">{activeCount}</span>
                      </div>
                    </div>
                  </div>

                  <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between">
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => {
                          setSelectedDoctorId(doctor.id);
                          setActiveSubTab('manifest');
                        }}
                        className="text-xs font-semibold text-sky-600 hover:text-sky-800 flex items-center space-x-1 cursor-pointer"
                      >
                        <span>Studies ({docAppointments.length})</span>
                        <ChevronRight className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => setSettlementDoctor(doctor)}
                        className="text-xs font-semibold text-emerald-700 hover:text-emerald-900 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200 cursor-pointer"
                      >
                        Statement
                      </button>
                    </div>
                    <div className="flex items-center space-x-1">
                      <button
                        onClick={() => handleOpenDoctorModal(doctor)}
                        className="p-1.5 rounded-lg text-slate-500 hover:text-sky-600 hover:bg-slate-100 cursor-pointer"
                        title="Edit Doctor"
                      >
                        <Edit2 className="w-3.5 h-3.5" />
                      </button>
                      <button
                        onClick={() => {
                          if (confirm(`Remove referring physician record for ${doctor.name}?`)) {
                            onDeleteReferrer(doctor.id);
                          }
                        }}
                        className="p-1.5 rounded-lg text-slate-400 hover:text-rose-600 hover:bg-rose-50 cursor-pointer"
                        title="Delete Doctor"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* SUBTAB 2: REFERRAL STUDY MANIFEST */}
      {activeSubTab === 'manifest' && (
        <div className="space-y-4">
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div className="flex flex-wrap items-center gap-3 flex-1">
              <div className="relative flex-1 min-w-[240px]">
                <Search className="w-4 h-4 absolute left-3 top-2.5 text-slate-400" />
                <input
                  type="text"
                  placeholder="Filter studies by patient, token, test name..."
                  value={searchQuery}
                  onChange={e => setSearchQuery(e.target.value)}
                  className="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-1 focus:ring-sky-500 text-slate-800"
                />
              </div>
              <div className="flex items-center space-x-1.5">
                <span className="text-xs text-slate-500 font-medium">Referring Doctor:</span>
                <select
                  value={selectedDoctorId}
                  onChange={e => setSelectedDoctorId(e.target.value === 'all' ? 'all' : parseInt(e.target.value))}
                  className="text-xs bg-slate-50 border border-slate-200 rounded-lg px-2.5 py-1.5 font-semibold text-slate-800"
                >
                  <option value="all">All Referring Doctors ({referredAppointments.length} Studies)</option>
                  {referrers.map(doc => (
                    <option key={doc.id} value={doc.id}>{doc.name} ({doc.specialty})</option>
                  ))}
                </select>
              </div>
            </div>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                  <th className="p-3">Token & Date</th>
                  <th className="p-3">Patient Details</th>
                  <th className="p-3">Requested Examination</th>
                  <th className="p-3">Referring Doctor</th>
                  <th className="p-3">Workflow State</th>
                  <th className="p-3">Report Status</th>
                  <th className="p-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {filteredManifest.map(apt => {
                  const isReported = ['reported', 'delivered'].includes(apt.workflowState);

                  return (
                    <tr key={apt.id} className="hover:bg-slate-50/80 transition-colors">
                      <td className="p-3 whitespace-nowrap">
                        <span className="font-mono font-bold text-sky-700 bg-sky-50 px-2 py-0.5 rounded border border-sky-200">
                          {apt.tokenNumber}
                        </span>
                        <span className="block text-[11px] text-slate-500 mt-1">{apt.date} • {apt.time}</span>
                      </td>

                      <td className="p-3">
                        <span className="font-bold text-slate-900 block">{apt.patient.name}</span>
                        <span className="text-[11px] text-slate-500 font-mono">MRN: {apt.patient.mrn} ({apt.patient.gender[0].toUpperCase()}, {apt.patient.age}y)</span>
                      </td>

                      <td className="p-3">
                        <div className="flex items-center space-x-1.5">
                          <span
                            className="w-2 h-2 rounded-full shrink-0"
                            style={{ backgroundColor: apt.modality?.color || '#0284c7' }}
                          />
                          <span className="font-semibold text-slate-800">{apt.service?.name || 'Radiology Study'}</span>
                        </div>
                        <span className="text-[11px] text-slate-500 block mt-0.5">{apt.modality?.name}</span>
                      </td>

                      <td className="p-3">
                        <span className="font-bold text-slate-900 block">{apt.referrer?.name || 'N/A'}</span>
                        <span className="text-[11px] text-slate-500">{apt.referrer?.clinicName}</span>
                      </td>

                      <td className="p-3">
                        <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-800 uppercase">
                          {apt.workflowState.replace('_', ' ')}
                        </span>
                      </td>

                      <td className="p-3">
                        {isReported ? (
                          <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <CheckCircle2 className="w-3 h-3" />
                            <span>Signed & Released</span>
                          </span>
                        ) : (
                          <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-700 border border-amber-200">
                            <Clock className="w-3 h-3" />
                            <span>Pending Sign-off</span>
                          </span>
                        )}
                      </td>

                      <td className="p-3 text-right">
                        <button
                          onClick={() => handleOpenDispatchModal(apt)}
                          className="px-3 py-1.5 rounded-lg bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-200 font-bold text-xs inline-flex items-center space-x-1.5 transition-colors cursor-pointer"
                        >
                          <Send className="w-3 h-3" />
                          <span>Dispatch to Doctor</span>
                        </button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* SUBTAB 3: REPORT DISPATCHES & LOGS */}
      {activeSubTab === 'dispatches' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between bg-white p-4 rounded-xl border border-slate-200 shadow-xs">
            <div>
              <h3 className="font-bold text-slate-900 text-sm">Doctor Report Dispatches & Delivery Status</h3>
              <p className="text-xs text-slate-500">Live communication audit log of digital reports and portal links sent to referring physicians.</p>
            </div>
            <span className="text-xs font-semibold px-3 py-1 bg-emerald-50 text-emerald-700 border border-emerald-200 rounded-full">
              WhatsApp & Email Gateway Active
            </span>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 overflow-hidden shadow-xs">
            <table className="w-full text-left text-xs border-collapse">
              <thead>
                <tr className="bg-slate-50 border-b border-slate-200 text-slate-600 font-semibold">
                  <th className="p-3">Dispatched At</th>
                  <th className="p-3">Referring Doctor</th>
                  <th className="p-3">Patient & Token</th>
                  <th className="p-3">Radiology Examination</th>
                  <th className="p-3">Channel & Recipient</th>
                  <th className="p-3">Delivery Status</th>
                  <th className="p-3">Sent By</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {doctorDispatches.map(disp => (
                  <tr key={disp.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="p-3 font-mono text-slate-500 whitespace-nowrap">{disp.sentAt}</td>
                    <td className="p-3 font-bold text-slate-900">{disp.referrerName}</td>
                    <td className="p-3">
                      <span className="font-semibold text-slate-900 block">{disp.patientName}</span>
                      <span className="font-mono text-sky-700 text-[11px] font-bold">{disp.tokenNumber}</span>
                    </td>
                    <td className="p-3 font-medium text-slate-800">{disp.studyName}</td>
                    <td className="p-3">
                      <div className="flex items-center space-x-1.5">
                        <span className={`text-[10px] uppercase font-bold px-1.5 py-0.5 rounded ${disp.channel === 'whatsapp' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'}`}>
                          {disp.channel}
                        </span>
                        <span className="font-mono text-slate-600">{disp.recipientContact}</span>
                      </div>
                    </td>
                    <td className="p-3">
                      <span className="inline-flex items-center space-x-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <CheckCircle2 className="w-3 h-3" />
                        <span>{disp.status.toUpperCase()}</span>
                      </span>
                    </td>
                    <td className="p-3 text-slate-500 text-[11px]">{disp.sentBy}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* SUBTAB 4: CONSULTANT SETTLEMENTS */}
      {activeSubTab === 'commission' && (
        <div className="space-y-6">
          <div className="bg-white rounded-xl border border-slate-200 p-5 shadow-xs">
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-slate-100">
              <div>
                <h3 className="font-bold text-slate-900 text-sm">Consultant Referral Accounts & Statements</h3>
                <p className="text-xs text-slate-500">Itemized audit of referred investigation revenue and clinical collaboration incentives.</p>
              </div>
              <div className="flex items-center space-x-2">
                <span className="text-xs text-slate-500 font-medium">Default Share Rate:</span>
                <span className="px-2.5 py-1 bg-emerald-50 text-emerald-700 font-bold text-xs rounded-lg border border-emerald-200">
                  12% Institutional Incentive
                </span>
              </div>
            </div>

            <div className="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
              {referrers.map(doctor => {
                const docAppointments = appointments.filter(a => a.referrerId === doctor.id);
                const totalReferredBill = docAppointments.reduce((sum, a) => sum + (a.service?.price || 5500), 0);
                const estimatedShare = Math.round(totalReferredBill * 0.12);

                return (
                  <div key={doctor.id} className="p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white transition-all flex flex-col justify-between space-y-4 shadow-xs hover:border-emerald-300">
                    <div>
                      <div className="flex items-center justify-between">
                        <span className="text-[11px] font-bold text-sky-700 bg-sky-100 px-2 py-0.5 rounded">
                          {doctor.specialty}
                        </span>
                        <span className="text-xs font-bold text-slate-500">{docAppointments.length} Studies</span>
                      </div>
                      <h4 className="font-bold text-slate-900 text-sm mt-2">{doctor.name}</h4>
                      <p className="text-xs text-slate-500">{doctor.clinicName}</p>

                      <div className="mt-3 pt-3 border-t border-slate-200 space-y-1.5 text-xs">
                        <div className="flex justify-between">
                          <span className="text-slate-500">Gross Study Value:</span>
                          <span className="font-bold text-slate-900 font-mono">Rs. {totalReferredBill.toLocaleString()}</span>
                        </div>
                        <div className="flex justify-between">
                          <span className="text-slate-500">Accrued Share (12%):</span>
                          <span className="font-bold text-emerald-700 font-mono">Rs. {estimatedShare.toLocaleString()}</span>
                        </div>
                      </div>
                    </div>

                    <div className="space-y-2">
                      <button
                        onClick={() => setSettlementDoctor(doctor)}
                        className="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-all shadow-xs cursor-pointer flex items-center justify-center space-x-1.5"
                      >
                        <Printer className="w-3.5 h-3.5 text-sky-400" />
                        <span>View Statement & Payout</span>
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </div>
      )}

      {/* SUBTAB 5: REFERRAL ANALYTICS */}
      {activeSubTab === 'analytics' && (
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
            <h3 className="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <TrendingUp className="w-4 h-4 text-sky-600" />
              <span>Modality Utilization by Referring Clinicians</span>
            </h3>
            <p className="text-xs text-slate-500">Distribution of referred patients across MRI, CT, Ultrasound, Digital X-Ray, and Mammography.</p>

            <div className="space-y-3 pt-2">
              {modalities.map(mod => {
                const count = referredAppointments.filter(a => a.modalityId === mod.id).length;
                const percentage = totalReferredCount > 0 ? Math.round((count / totalReferredCount) * 100) : 0;

                return (
                  <div key={mod.id} className="space-y-1 text-xs">
                    <div className="flex justify-between font-semibold text-slate-800">
                      <span>{mod.name}</span>
                      <span>{count} Studies ({percentage}%)</span>
                    </div>
                    <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                      <div
                        className="h-full rounded-full transition-all duration-500"
                        style={{ width: `${percentage}%`, backgroundColor: mod.color }}
                      />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          <div className="bg-white rounded-xl border border-slate-200 p-6 shadow-xs space-y-4">
            <h3 className="font-bold text-slate-900 text-sm flex items-center space-x-2">
              <Award className="w-4 h-4 text-amber-600" />
              <span>Top Referring Clinical Institutions</span>
            </h3>
            <p className="text-xs text-slate-500">Hospital and private clinic ranking based on examination referrals in Islamabad / Rawalpindi.</p>

            <div className="space-y-3 pt-2">
              {referrers.map((doc, idx) => (
                <div key={doc.id} className="flex items-center justify-between p-3 rounded-lg bg-slate-50 border border-slate-100 text-xs">
                  <div className="flex items-center space-x-3">
                    <span className="w-6 h-6 rounded-full bg-slate-200 text-slate-700 font-bold flex items-center justify-center text-xs">
                      #{idx + 1}
                    </span>
                    <div>
                      <h4 className="font-bold text-slate-900">{doc.clinicName}</h4>
                      <p className="text-slate-500">{doc.name} ({doc.specialty})</p>
                    </div>
                  </div>
                  <span className="font-bold text-sky-700 bg-sky-100 px-2 py-1 rounded text-xs">
                    {appointments.filter(a => a.referrerId === doc.id).length} Referrals
                  </span>
                </div>
              ))}
            </div>
          </div>
        </div>
      )}

      {/* Add / Edit Doctor Modal */}
      {doctorModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <h3 className="font-bold text-slate-900 text-base">
                {editingDoctor ? 'Edit Referring Physician' : 'Register New Referring Doctor'}
              </h3>
              <button onClick={() => setDoctorModalOpen(false)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <form onSubmit={handleSaveDoctor} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Doctor Name & Title *</label>
                <input
                  type="text"
                  required
                  value={docName}
                  onChange={e => setDocName(e.target.value)}
                  placeholder="e.g. Dr. Tariq Mahmood, FRCP"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-semibold focus:ring-1 focus:ring-sky-500"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Clinical Specialty</label>
                <input
                  type="text"
                  value={docSpecialty}
                  onChange={e => setDocSpecialty(e.target.value)}
                  placeholder="e.g. Pulmonology / Neurology / Orthopedics"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-sky-500"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Affiliated Clinic / Hospital</label>
                <input
                  type="text"
                  value={docClinic}
                  onChange={e => setDocClinic(e.target.value)}
                  placeholder="e.g. Islamabad Medical Center"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 focus:ring-1 focus:ring-sky-500"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Phone / WhatsApp *</label>
                  <input
                    type="text"
                    required
                    value={docPhone}
                    onChange={e => setDocPhone(e.target.value)}
                    placeholder="+92 300 1234567"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono"
                  />
                </div>
                <div>
                  <label className="block font-semibold text-slate-700 mb-1">Email Address</label>
                  <input
                    type="email"
                    value={docEmail}
                    onChange={e => setDocEmail(e.target.value)}
                    placeholder="doctor@clinic.pk"
                    className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800"
                  />
                </div>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button
                  type="button"
                  onClick={() => setDoctorModalOpen(false)}
                  className="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg font-semibold hover:bg-slate-200 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-sky-600 text-white rounded-lg font-bold hover:bg-sky-700 shadow-xs cursor-pointer"
                >
                  {editingDoctor ? 'Save Changes' : 'Register Doctor'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Dispatch Report Modal */}
      {dispatchModalOpen && dispatchApt && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-slate-100">
              <div className="flex items-center space-x-2">
                <Send className="w-5 h-5 text-sky-600" />
                <h3 className="font-bold text-slate-900 text-base">Dispatch Verified Report to Doctor</h3>
              </div>
              <button onClick={() => setDispatchModalOpen(false)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 text-xs space-y-1.5">
              <div className="flex justify-between">
                <span className="text-slate-500">Patient:</span>
                <span className="font-bold text-slate-900">{dispatchApt.patient.name} (Token: {dispatchApt.tokenNumber})</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Examination:</span>
                <span className="font-semibold text-slate-800">{dispatchApt.service?.name || 'Radiology Examination'}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Referring Doctor:</span>
                <span className="font-bold text-sky-700">{dispatchApt.referrer?.name || 'Assigned Consultant'}</span>
              </div>
            </div>

            <form onSubmit={handleSendDispatch} className="space-y-4 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Dispatch Channel</label>
                <div className="grid grid-cols-3 gap-2">
                  {(['whatsapp', 'email', 'sms'] as const).map(ch => (
                    <button
                      key={ch}
                      type="button"
                      onClick={() => setDispatchChannel(ch)}
                      className={`py-2 px-3 rounded-lg border text-xs font-bold uppercase transition-all cursor-pointer ${
                        dispatchChannel === ch
                          ? 'bg-sky-50 border-sky-500 text-sky-700 shadow-xs'
                          : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                      }`}
                    >
                      {ch}
                    </button>
                  ))}
                </div>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Recipient Destination ({dispatchChannel.toUpperCase()})</label>
                <input
                  type="text"
                  required
                  value={dispatchRecipient}
                  onChange={e => setDispatchRecipient(e.target.value)}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-slate-800 font-mono"
                />
              </div>

              <div className="p-3 bg-sky-50/60 rounded-xl border border-sky-100 text-slate-700 space-y-1 font-mono text-[11px]">
                <span className="text-slate-400 block font-sans font-semibold">Message Preview:</span>
                <p>
                  "Dr. {dispatchApt.referrer?.name || 'Doctor'}, official Radiology Report for your patient {dispatchApt.patient.name} ({dispatchApt.service?.name || 'Radiology Study'}) has been finalized and verified by Amad Diagnostic Centre. Review online: https://portal.amaddiagnosticcentre.com.pk/report/{dispatchApt.patient.mrn}"
                </p>
              </div>

              <div className="pt-3 border-t border-slate-100 flex items-center justify-end space-x-2">
                <button
                  type="button"
                  onClick={() => setDispatchModalOpen(false)}
                  className="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg font-semibold hover:bg-slate-200 cursor-pointer"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  className="px-5 py-2 bg-sky-600 text-white rounded-lg font-bold hover:bg-sky-700 shadow-xs cursor-pointer flex items-center space-x-1.5"
                >
                  <Send className="w-3.5 h-3.5" />
                  <span>Send Report Dispatch</span>
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Printable Settlement Sheet Modal */}
      {settlementDoctor && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-2xl w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200">
              <div>
                <h3 className="font-bold text-slate-900 text-base">Doctor Referral Settlement Sheet</h3>
                <p className="text-xs text-slate-500">Amad Diagnostic Centre • Referral Accounting Department</p>
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => window.print()}
                  className="px-3 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-bold flex items-center space-x-1 cursor-pointer"
                >
                  <Printer className="w-3.5 h-3.5" />
                  <span>Print</span>
                </button>
                <button onClick={() => setSettlementDoctor(null)} className="text-slate-400 hover:text-slate-600 cursor-pointer">
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            <div className="p-4 bg-slate-50 rounded-xl border border-slate-200 space-y-3 text-xs">
              <div className="flex justify-between">
                <div>
                  <span className="text-slate-500 block">Consultant Name:</span>
                  <span className="font-bold text-slate-900 text-sm">{settlementDoctor.name}</span>
                </div>
                <div className="text-right">
                  <span className="text-slate-500 block">Clinic / Hospital:</span>
                  <span className="font-bold text-slate-900">{settlementDoctor.clinicName}</span>
                </div>
              </div>
              <div className="flex justify-between pt-2 border-t border-slate-200">
                <span>Period: Current Active Cycle (August 2026)</span>
                <span className="font-semibold text-sky-700">Specialty: {settlementDoctor.specialty}</span>
              </div>
            </div>

            <div className="max-h-60 overflow-y-auto border border-slate-200 rounded-xl">
              <table className="w-full text-left text-xs">
                <thead className="bg-slate-100 border-b border-slate-200 text-slate-600 font-semibold sticky top-0">
                  <tr>
                    <th className="p-2.5">Token</th>
                    <th className="p-2.5">Patient</th>
                    <th className="p-2.5">Investigation</th>
                    <th className="p-2.5 text-right">Fee (PKR)</th>
                    <th className="p-2.5 text-right">Share (12%)</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 text-slate-700">
                  {appointments.filter(a => a.referrerId === settlementDoctor.id).map(apt => {
                    const fee = 6500;
                    const share = Math.round(fee * 0.12);
                    return (
                      <tr key={apt.id}>
                        <td className="p-2.5 font-mono font-bold text-sky-700">{apt.tokenNumber}</td>
                        <td className="p-2.5 font-medium">{apt.patient.name}</td>
                        <td className="p-2.5">{apt.service?.name || 'Radiology Study'}</td>
                        <td className="p-2.5 text-right font-mono">Rs. {fee.toLocaleString()}</td>
                        <td className="p-2.5 text-right font-mono font-bold text-emerald-700">Rs. {share.toLocaleString()}</td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>

            <div className="pt-3 border-t border-slate-200 flex justify-between items-center text-xs">
              <span className="text-slate-500">Authorized by ADC Accounts Division</span>
              <button
                onClick={() => setSettlementDoctor(null)}
                className="px-4 py-2 bg-slate-200 text-slate-800 rounded-lg font-bold hover:bg-slate-300 cursor-pointer"
              >
                Close Statement
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
