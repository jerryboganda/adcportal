import React, { useState } from 'react';
import {
  User,
  Calendar,
  FileText,
  Download,
  Clock,
  ShieldCheck,
  CreditCard,
  Phone,
  MapPin,
  Sparkles,
  CheckCircle2,
  AlertCircle,
  Eye,
  PlusCircle,
  Share2,
  QrCode,
  Lock,
  ChevronRight,
  AlertTriangle,
  RotateCcw,
  ZoomIn,
  ZoomOut,
  Maximize2,
  Minimize2,
  Sliders,
  Printer,
  Copy,
  Check,
  Send,
  MessageSquare,
  DollarSign,
  Activity,
  Heart,
  Droplets,
  Flame,
  CheckSquare,
  Square
} from 'lucide-react';
import { Patient, Appointment, Invoice, Service, Modality, Referrer, RadiologyReport } from '../types';
import { generateRadiologyReportPdf, generateInvoicePdf } from '../utils/pdfGenerator';

interface PatientPortalViewProps {
  patients: Patient[];
  appointments: Appointment[];
  invoices: Invoice[];
  services?: Service[];
  modalities?: Modality[];
  referrers?: Referrer[];
  onRecordPayment?: (invoiceId: string, amount: number, method: 'cash' | 'card' | 'bank' | 'mobile' | 'insurance', reference: string) => void;
  onBookAppointment?: (newAptData: Partial<Appointment>, newPatientData?: Partial<Patient>) => void;
}

export const PatientPortalView: React.FC<PatientPortalViewProps> = ({
  patients,
  appointments,
  invoices,
  services = [],
  modalities = [],
  referrers = [],
  onRecordPayment,
  onBookAppointment,
}) => {
  const [selectedPatientId, setSelectedPatientId] = useState<string>(patients[1]?.id || patients[0]?.id);
  const [activeSubTab, setActiveSubTab] = useState<'overview' | 'reports' | 'booking' | 'prep' | 'billing'>('overview');

  // PACS DICOM Viewer State
  const [pacsModalApt, setPacsModalApt] = useState<Appointment | null>(null);
  const [activeSliceIndex, setActiveSliceIndex] = useState(0);
  const [windowPreset, setWindowPreset] = useState<'soft' | 'bone' | 'lung' | 'brain' | 'invert'>('soft');
  const [zoomLevel, setZoomLevel] = useState(1);
  const [showDicomMetadata, setShowDicomMetadata] = useState(true);

  // Online Payment Modal State
  const [payingInvoice, setPayingInvoice] = useState<Invoice | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<'raast' | 'card' | 'mobile'>('raast');
  const [paymentRef, setPaymentRef] = useState('');
  const [isProcessingPayment, setIsProcessingPayment] = useState(false);
  const [paymentSuccessMessage, setPaymentSuccessMessage] = useState('');

  // Booking Modal / Form State
  const [isBookingOpen, setIsBookingOpen] = useState(false);
  const [bookModalityId, setBookModalityId] = useState<number>(modalities[0]?.id || 1);
  const [bookServiceId, setBookServiceId] = useState<number>(services[0]?.id || 101);
  const [bookReferrerId, setBookReferrerId] = useState<number | undefined>(undefined);
  const [bookDate, setBookDate] = useState<string>(new Date().toISOString().split('T')[0]);
  const [bookTime, setBookTime] = useState<string>('02:30 PM');
  const [bookNotes, setBookNotes] = useState<string>('');
  const [bookingSuccessToken, setBookingSuccessToken] = useState<string | null>(null);

  // Preparation Checklist Tracking (stored locally per appointment)
  const [checkedPrepItems, setCheckedPrepItems] = useState<Record<string, boolean>>({
    fasting: true,
    hydration: false,
    metals: true,
    reports: true,
  });

  // Share Modal State
  const [shareReportApt, setShareReportApt] = useState<Appointment | null>(null);
  const [copiedLink, setCopiedLink] = useState(false);

  const patient = patients.find(p => p.id === selectedPatientId) || patients[0];
  const patientAppointments = appointments.filter(a => a.patientId === patient?.id);
  const patientInvoices = invoices.filter(i => i.patientId === patient?.id);

  // Filtered services for booking dropdown
  const availableServices = services.filter(s => s.modalityId === bookModalityId);

  // Mock DICOM slices for key image viewer
  const getDicomSlices = (modalityCode: string) => {
    switch (modalityCode) {
      case 'CT':
        return [
          { title: 'Axial High-Resolution Chest View (Sub-pleural Bases)', kvp: '120 kVp', mas: '180 mAs', thickness: '1.25 mm', matrix: '512x512', src: 'https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=800&q=80' },
          { title: 'Coronal MPR Lung Window (Bronchovascular Tree)', kvp: '120 kVp', mas: '200 mAs', thickness: '1.25 mm', matrix: '512x512', src: 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80' },
          { title: 'Mediastinal Soft Tissue Window (Aortic Arch)', kvp: '120 kVp', mas: '220 mAs', thickness: '2.5 mm', matrix: '512x512', src: 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?auto=format&fit=crop&w=800&q=80' },
        ];
      case 'MR':
        return [
          { title: 'T2-Weighted Axial Brain (Ventricular System)', kvp: '1.5 Tesla', mas: 'TR: 4000ms / TE: 90ms', thickness: '3.0 mm', matrix: '320x320', src: 'https://images.unsplash.com/photo-1559757175-5700dde675bc?auto=format&fit=crop&w=800&q=80' },
          { title: 'FLAIR Sagittal (Corpus Callosum & Periventricular)', kvp: '1.5 Tesla', mas: 'TR: 9000ms / TE: 120ms', thickness: '3.0 mm', matrix: '320x320', src: 'https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=800&q=80' },
          { title: 'DWI / ADC Map (Diffusion Restriction Assessment)', kvp: '1.5 Tesla', mas: 'b-value: 1000 s/mm2', thickness: '4.0 mm', matrix: '256x256', src: 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=800&q=80' },
        ];
      case 'US':
        return [
          { title: 'Longitudinal View Gallbladder (Lumen & Acoustic Shadowing)', kvp: 'Curvilinear 3.5 MHz', mas: 'Gain: 68 dB', thickness: 'Depth: 14 cm', matrix: 'B-Mode Cine', src: 'https://images.unsplash.com/photo-1579684385127-1ef15d508118?auto=format&fit=crop&w=800&q=80' },
          { title: 'Transverse View Right Kidney (Corticomedullary Ratio)', kvp: 'Curvilinear 3.5 MHz', mas: 'Gain: 64 dB', thickness: 'Depth: 12 cm', matrix: 'Color Doppler', src: 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80' },
        ];
      case 'MG':
        return [
          { title: 'Right Craniocaudal (RCC) Full-Field Digital Projection', kvp: '28 kVp', mas: '85 mAs', thickness: 'Compression: 11 daN', matrix: '3328x4096', src: 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?auto=format&fit=crop&w=800&q=80' },
          { title: 'Left Mediolateral Oblique (LMLO) Pectoralis View', kvp: '29 kVp', mas: '92 mAs', thickness: 'Compression: 12 daN', matrix: '3328x4096', src: 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?auto=format&fit=crop&w=800&q=80' },
        ];
      default:
        return [
          { title: 'PA Chest Digital Radiography (Inspiratory Effort)', kvp: '110 kVp', mas: '4.0 mAs', thickness: 'SID: 180 cm', matrix: '2048x2048', src: 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?auto=format&fit=crop&w=800&q=80' },
          { title: 'Lateral Chest View (Retrocardiac & Retrosternal Clear Spaces)', kvp: '120 kVp', mas: '8.0 mAs', thickness: 'SID: 180 cm', matrix: '2048x2048', src: 'https://images.unsplash.com/photo-1516549655169-df83a0774514?auto=format&fit=crop&w=800&q=80' },
        ];
    }
  };

  const handleExecutePayment = () => {
    if (!payingInvoice || !onRecordPayment) return;
    setIsProcessingPayment(true);

    setTimeout(() => {
      const ref = paymentRef || `PORTAL-RAAST-${Math.floor(100000 + Math.random() * 900000)}`;
      onRecordPayment(payingInvoice.id, payingInvoice.balanceDue, paymentMethod === 'raast' ? 'bank' : paymentMethod === 'mobile' ? 'mobile' : 'card', ref);
      setIsProcessingPayment(false);
      setPaymentSuccessMessage(`Payment of Rs. ${payingInvoice.balanceDue.toLocaleString()} verified successfully via ${paymentMethod.toUpperCase()} (Ref: ${ref})!`);
      setTimeout(() => {
        setPayingInvoice(null);
        setPaymentSuccessMessage('');
        setPaymentRef('');
      }, 2000);
    }, 1200);
  };

  const handleBookingSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!onBookAppointment || !patient) return;

    const selModality = modalities.find(m => m.id === bookModalityId) || modalities[0];
    const selService = services.find(s => s.id === bookServiceId) || services[0];
    const selReferrer = referrers.find(r => r.id === bookReferrerId);

    const tokenPrefix = selModality.code;
    const randomTokenNum = Math.floor(10 + Math.random() * 90);
    const tokenNumber = `${tokenPrefix}-${randomTokenNum}`;

    onBookAppointment({
      tokenNumber,
      patientId: patient.id,
      patient,
      serviceId: selService.id,
      service: selService,
      modalityId: selModality.id,
      modality: selModality,
      referrerId: selReferrer?.id,
      referrer: selReferrer,
      date: bookDate,
      time: bookTime,
      priority: 'routine',
      screeningRequired: selService.requiresScreening,
      screeningCleared: !selService.requiresScreening,
      roomNumber: `Room ${selModality.id} (${selModality.name})`,
      notes: bookNotes ? `[Portal Booking]: ${bookNotes}` : '[Self-Service Online Booking]',
    });

    setBookingSuccessToken(tokenNumber);
    setTimeout(() => {
      setIsBookingOpen(false);
      setBookingSuccessToken(null);
      setBookNotes('');
      setActiveSubTab('overview');
    }, 2200);
  };

  const handleShareReport = (apt: Appointment) => {
    setShareReportApt(apt);
    setCopiedLink(false);
  };

  const copyShareableLink = () => {
    if (!shareReportApt) return;
    const url = `https://portal.amaddiagnosticcentre.com.pk/report/verify?token=${shareReportApt.tokenNumber}&mrn=${patient.mrn}`;
    navigator.clipboard.writeText(url);
    setCopiedLink(true);
    setTimeout(() => setCopiedLink(false), 2500);
  };

  const workflowSteps = [
    { key: 'booked', label: '1. Booked & Scheduled' },
    { key: 'checked_in', label: '2. Checked In' },
    { key: 'preparing', label: '3. Prep & Contrast' },
    { key: 'in_progress', label: '4. Inside Scanner' },
    { key: 'acquired', label: '5. Scanned & PACS' },
    { key: 'reading', label: '6. Radiologist Reading' },
    { key: 'reported', label: '7. Verified & Signed' },
  ];

  const getWorkflowStepIndex = (state: string) => {
    switch (state) {
      case 'booked': return 0;
      case 'checked_in': return 1;
      case 'preparing': return 2;
      case 'in_progress': return 3;
      case 'acquired': return 4;
      case 'reading': return 5;
      case 'reported':
      case 'delivered': return 6;
      default: return 0;
    }
  };

  return (
    <div className="space-y-6">
      {/* Top Header & Simulation Patient Selector */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div className="flex items-center space-x-3.5">
          <div className="w-12 h-12 rounded-2xl bg-cyan-600 text-white flex items-center justify-center shadow-md shadow-cyan-600/20">
            <User className="w-6 h-6" />
          </div>
          <div>
            <div className="flex items-center space-x-2">
              <h1 className="text-xl font-bold text-slate-900 tracking-tight">Patient Online Health Portal</h1>
              <span className="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-0.5 rounded-full border border-emerald-200 font-bold flex items-center gap-1">
                <ShieldCheck className="w-3.5 h-3.5" /> Verified Secure Session
              </span>
            </div>
            <p className="text-slate-500 text-xs mt-0.5">
              Access your real-time imaging study progress, view verified DICOM scans, download diagnostic PDF reports, and settle billing.
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {/* Quick Sub-navigation Tabs */}
          <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs font-semibold">
            <button
              onClick={() => setActiveSubTab('overview')}
              className={`px-3 py-1.5 rounded-lg cursor-pointer transition-all ${
                activeSubTab === 'overview' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Overview
            </button>
            <button
              onClick={() => setActiveSubTab('reports')}
              className={`px-3 py-1.5 rounded-lg cursor-pointer transition-all ${
                activeSubTab === 'reports' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Reports & PACS ({patientAppointments.filter(a => a.workflowState === 'reported' || a.workflowState === 'delivered').length})
            </button>
            <button
              onClick={() => setActiveSubTab('prep')}
              className={`px-3 py-1.5 rounded-lg cursor-pointer transition-all ${
                activeSubTab === 'prep' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Pre-Scan Protocols
            </button>
            <button
              onClick={() => setActiveSubTab('billing')}
              className={`px-3 py-1.5 rounded-lg cursor-pointer transition-all ${
                activeSubTab === 'billing' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
              }`}
            >
              Invoices & Receipts ({patientInvoices.length})
            </button>
          </div>

          {/* New Appointment Booking Trigger */}
          <button
            onClick={() => setIsBookingOpen(true)}
            className="flex items-center space-x-1.5 px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-cyan-600 to-sky-600 hover:from-cyan-500 hover:to-sky-500 text-white text-xs font-bold shadow-md shadow-cyan-600/20 transition-all cursor-pointer"
          >
            <PlusCircle className="w-3.5 h-3.5" />
            <span>Book New Study</span>
          </button>

          {/* Switch Active Patient (Simulation Switcher) */}
          <div className="flex items-center space-x-1.5 bg-slate-50 px-2 py-1 rounded-xl border border-slate-200 text-xs">
            <span className="text-[11px] text-slate-500 font-medium">Switch Patient:</span>
            <select
              value={selectedPatientId}
              onChange={(e) => setSelectedPatientId(e.target.value)}
              className="bg-white text-cyan-800 font-bold px-2 py-1 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
            >
              {patients.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.mrn})
                </option>
              ))}
            </select>
          </div>
        </div>
      </div>

      {/* Patient Profile & Clinical Safety Header */}
      {patient && (
        <div className="bg-gradient-to-r from-sky-50/70 via-white to-slate-50 p-6 rounded-2xl border border-slate-200 shadow-sm flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
          <div className="flex items-center space-x-4">
            <div className="w-16 h-16 rounded-2xl bg-gradient-to-br from-cyan-600 to-sky-700 text-white font-black text-2xl flex items-center justify-center shadow-md shadow-cyan-600/20">
              {patient.name.split(' ').map(n => n[0]).join('').slice(0, 2)}
            </div>
            <div>
              <div className="flex items-center space-x-2.5">
                <h2 className="text-xl font-bold text-slate-900">{patient.name}</h2>
                <span className="text-xs font-mono px-2 py-0.5 rounded bg-white text-cyan-800 font-bold border border-slate-300 shadow-xs">
                  MRN: {patient.mrn}
                </span>
                <span className="bg-emerald-100 text-emerald-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase">
                  Active Patient
                </span>
              </div>

              <div className="text-xs text-slate-600 mt-1.5 flex flex-wrap items-center gap-3">
                <span>Age: <strong className="text-slate-900">{patient.age} Yrs</strong></span>
                <span>•</span>
                <span>Gender: <strong className="text-slate-900">{patient.gender.toUpperCase()}</strong></span>
                <span>•</span>
                <span>Blood Group: <strong className="text-slate-900">{patient.bloodGroup}</strong></span>
                <span>•</span>
                <span>Phone: <strong className="text-slate-900">{patient.phone}</strong></span>
                <span>•</span>
                <span>Email: <span className="text-slate-800">{patient.email}</span></span>
              </div>

              {/* Medical Alerts & Allergies Badge Strip */}
              <div className="mt-2.5 flex flex-wrap items-center gap-2">
                <span className="text-[11px] font-bold text-slate-500 flex items-center gap-1">
                  <Activity className="w-3.5 h-3.5 text-cyan-600" /> Clinical Flags:
                </span>
                {patient.allergies ? (
                  <span className="bg-rose-50 text-rose-700 border border-rose-200 text-[11px] font-semibold px-2 py-0.5 rounded-md flex items-center gap-1">
                    <AlertTriangle className="w-3 h-3 text-rose-600" /> Allergy: {patient.allergies}
                  </span>
                ) : (
                  <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[11px] font-semibold px-2 py-0.5 rounded-md">
                    No Known Drug / Contrast Allergies (NKDA)
                  </span>
                )}
                {patient.medicalHistory && (
                  <span className="bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-medium px-2 py-0.5 rounded-md">
                    History: {patient.medicalHistory}
                  </span>
                )}
              </div>
            </div>
          </div>

          <div className="text-left lg:text-right text-xs text-slate-600 space-y-1 bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
            <div className="flex items-center lg:justify-end space-x-1.5 text-slate-800 font-semibold">
              <MapPin className="w-3.5 h-3.5 text-cyan-600" />
              <span>Amad Diagnostic Centre, Main Blue Area, Islamabad</span>
            </div>
            <div className="flex items-center lg:justify-end space-x-1.5 text-slate-500">
              <Phone className="w-3.5 h-3.5 text-slate-400" />
              <span>24/7 Helpline: +92 51 2223344 / +92 300 1234567</span>
            </div>
            <div className="text-[11px] text-cyan-700 font-medium">
              Digital PACS & RIS Cloud Hub • Online Portal v4.2
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 1: OVERVIEW & ACTIVE STUDY PROGRESSION                                */}
      {/* ========================================================================= */}
      {activeSubTab === 'overview' && (
        <div className="space-y-6">
          {/* Quick Metrics Bar */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
              <div>
                <span className="text-xs text-slate-500 font-semibold block">Total Studies</span>
                <span className="text-2xl font-black text-slate-900">{patientAppointments.length}</span>
              </div>
              <div className="w-10 h-10 rounded-xl bg-cyan-50 text-cyan-700 flex items-center justify-center">
                <Calendar className="w-5 h-5" />
              </div>
            </div>

            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
              <div>
                <span className="text-xs text-slate-500 font-semibold block">Verified Reports</span>
                <span className="text-2xl font-black text-emerald-600">
                  {patientAppointments.filter(a => a.workflowState === 'reported' || a.workflowState === 'delivered').length}
                </span>
              </div>
              <div className="w-10 h-10 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center">
                <CheckCircle2 className="w-5 h-5" />
              </div>
            </div>

            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
              <div>
                <span className="text-xs text-slate-500 font-semibold block">Active / In Queue</span>
                <span className="text-2xl font-black text-sky-600">
                  {patientAppointments.filter(a => ['booked', 'checked_in', 'preparing', 'in_progress', 'acquired', 'reading'].includes(a.workflowState)).length}
                </span>
              </div>
              <div className="w-10 h-10 rounded-xl bg-sky-50 text-sky-700 flex items-center justify-center">
                <Clock className="w-5 h-5" />
              </div>
            </div>

            <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
              <div>
                <span className="text-xs text-slate-500 font-semibold block">Unsettled Balance</span>
                <span className="text-2xl font-black text-rose-600 font-mono">
                  Rs. {patientInvoices.reduce((sum, inv) => sum + inv.balanceDue, 0).toLocaleString()}
                </span>
              </div>
              <div className="w-10 h-10 rounded-xl bg-rose-50 text-rose-700 flex items-center justify-center">
                <DollarSign className="w-5 h-5" />
              </div>
            </div>
          </div>

          {/* Active Appointments with Step-by-Step Progress Bar */}
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-base font-bold text-slate-900 flex items-center gap-2">
                <Activity className="w-4 h-4 text-cyan-600" />
                <span>Your Imaging Studies & Real-Time Status</span>
              </h2>
              <span className="text-xs text-slate-500">Live PACS / RIS synchronized</span>
            </div>

            {patientAppointments.length === 0 ? (
              <div className="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 text-xs shadow-sm space-y-3">
                <Calendar className="w-10 h-10 mx-auto text-slate-300" />
                <p className="font-semibold text-slate-600">No appointments on record for this patient.</p>
                <button
                  onClick={() => setIsBookingOpen(true)}
                  className="px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs cursor-pointer shadow-sm inline-flex items-center gap-1.5"
                >
                  <PlusCircle className="w-4 h-4" /> Book First Diagnostic Study
                </button>
              </div>
            ) : (
              <div className="space-y-4">
                {patientAppointments.map((apt) => {
                  const stepIdx = getWorkflowStepIndex(apt.workflowState);
                  const isReported = apt.workflowState === 'reported' || apt.workflowState === 'delivered';
                  const isLiveToday = apt.workflowState === 'checked_in' || apt.workflowState === 'preparing' || apt.workflowState === 'in_progress';

                  return (
                    <div key={apt.id} className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
                      {/* Top Card Bar */}
                      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 pb-4">
                        <div className="flex items-center space-x-3">
                          <span className="font-mono font-black text-sm px-3 py-1 rounded-xl bg-slate-100 text-cyan-900 border border-slate-300">
                            Token #{apt.tokenNumber}
                          </span>
                          <span
                            className="text-xs font-black text-white px-2.5 py-0.5 rounded-lg font-mono shadow-xs"
                            style={{ backgroundColor: apt.modality.color }}
                          >
                            {apt.modality.code}
                          </span>
                          <div>
                            <h3 className="font-bold text-slate-900 text-base">{apt.service.name}</h3>
                            <span className="text-xs text-slate-500">{apt.roomNumber} • Ref: {apt.referrer?.name || 'Walk-in / Self'}</span>
                          </div>
                        </div>

                        <div className="flex items-center space-x-2">
                          <span className={`text-xs font-bold px-3 py-1 rounded-full uppercase flex items-center gap-1.5 ${
                            isReported ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
                            isLiveToday ? 'bg-amber-50 text-amber-800 border border-amber-300 animate-pulse' :
                            'bg-sky-50 text-sky-700 border border-sky-200'
                          }`}>
                            <span className="w-2 h-2 rounded-full bg-current" />
                            {isReported ? 'Report Ready' : apt.workflowState.replace('_', ' ')}
                          </span>

                          {/* Quick Action Buttons */}
                          {isReported && apt.report && (
                            <button
                              onClick={() => generateRadiologyReportPdf(apt, apt.report!)}
                              className="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-1 cursor-pointer shadow-xs"
                              title="Download PDF"
                            >
                              <Download className="w-3.5 h-3.5" /> PDF
                            </button>
                          )}

                          <button
                            onClick={() => setPacsModalApt(apt)}
                            className="px-3 py-1 rounded-lg bg-cyan-50 hover:bg-cyan-100 text-cyan-800 border border-cyan-200 text-xs font-bold flex items-center gap-1 cursor-pointer shadow-xs"
                            title="Open PACS DICOM Viewer"
                          >
                            <Eye className="w-3.5 h-3.5 text-cyan-600" /> PACS Images
                          </button>
                        </div>
                      </div>

                      {/* Step Progress Tracker */}
                      <div className="space-y-2 pt-1">
                        <div className="flex items-center justify-between text-xs font-bold text-slate-600">
                          <span>Workflow Progression:</span>
                          <span className="text-cyan-700 font-mono">Stage {stepIdx + 1} of 7</span>
                        </div>

                        <div className="grid grid-cols-7 gap-1.5">
                          {workflowSteps.map((step, idx) => {
                            const isCompleted = idx <= stepIdx;
                            const isCurrent = idx === stepIdx;

                            return (
                              <div key={step.key} className="space-y-1">
                                <div
                                  className={`h-2 rounded-full transition-all ${
                                    isCurrent
                                      ? 'bg-cyan-600 ring-2 ring-cyan-200'
                                      : isCompleted
                                      ? 'bg-emerald-500'
                                      : 'bg-slate-200'
                                  }`}
                                />
                                <span className={`text-[10px] block leading-tight font-medium ${
                                  isCurrent ? 'text-cyan-800 font-bold' : isCompleted ? 'text-slate-700' : 'text-slate-400'
                                }`}>
                                  {step.label}
                                </span>
                              </div>
                            );
                          })}
                        </div>
                      </div>

                      {/* Study Details & Preparation Note */}
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-3 text-xs bg-slate-50 p-3.5 rounded-xl border border-slate-200">
                        <div className="space-y-1">
                          <div className="flex items-center space-x-1.5">
                            <Clock className="w-3.5 h-3.5 text-slate-400" />
                            <span>Schedule: <strong>{apt.date} at {apt.time}</strong></span>
                          </div>
                          <div>Duration: <strong>~{apt.service.durationMinutes} mins</strong></div>
                          <div>Screening Safety: {apt.screeningCleared ? (
                            <span className="text-emerald-700 font-bold">Cleared</span>
                          ) : (
                            <span className="text-amber-700 font-bold">Pending Questionnaire</span>
                          )}</div>
                        </div>

                        <div>
                          <span className="font-semibold text-slate-800 block">Pre-Scan Preparation Guide:</span>
                          <p className="text-slate-600 mt-0.5 text-[11px] leading-relaxed">
                            {apt.service.preparationInstructions}
                          </p>
                        </div>
                      </div>

                      {/* Verified Impression Box if Reported */}
                      {isReported && apt.report && (
                        <div className="p-4 rounded-xl bg-gradient-to-r from-emerald-50 to-teal-50 border border-emerald-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                          <div className="space-y-1 flex-1">
                            <div className="flex items-center space-x-2">
                              <CheckCircle2 className="w-4 h-4 text-emerald-600" />
                              <span className="text-xs font-bold text-emerald-900">
                                Official Impression (Signed by {apt.report.signedBy})
                              </span>
                            </div>
                            <p className="text-xs text-slate-800 italic font-serif">
                              "{apt.report.impression}"
                            </p>
                          </div>

                          <div className="flex items-center space-x-2">
                            <button
                              onClick={() => handleShareReport(apt)}
                              className="px-3 py-1.5 rounded-xl bg-white hover:bg-slate-50 text-slate-700 border border-slate-300 text-xs font-bold flex items-center gap-1.5 shadow-xs cursor-pointer"
                            >
                              <Share2 className="w-3.5 h-3.5 text-cyan-600" /> Share Link
                            </button>
                            <button
                              onClick={() => generateRadiologyReportPdf(apt, apt.report!)}
                              className="px-4 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-1.5 shadow-md shadow-emerald-600/20 cursor-pointer"
                            >
                              <Download className="w-3.5 h-3.5" /> Download Report
                            </button>
                          </div>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            )}
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 2: REPORTS & DICOM PACS GALLERY                                      */}
      {/* ========================================================================= */}
      {activeSubTab === 'reports' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-900">Verified Diagnostic Reports & PACS Key Images</h2>
            <span className="text-xs text-slate-500">Official digitally signed radiology findings</span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {patientAppointments.map((apt) => {
              const hasReport = !!apt.report;
              const slices = getDicomSlices(apt.modality.code);

              return (
                <div key={apt.id} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4 flex flex-col justify-between">
                  <div>
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                      <div className="flex items-center space-x-2">
                        <span
                          className="text-xs font-black text-white px-2.5 py-0.5 rounded-lg font-mono shadow-xs"
                          style={{ backgroundColor: apt.modality.color }}
                        >
                          {apt.modality.code}
                        </span>
                        <h3 className="font-bold text-slate-900 text-sm">{apt.service.name}</h3>
                      </div>
                      <span className="text-xs font-mono font-bold text-slate-500">#{apt.tokenNumber}</span>
                    </div>

                    {/* Key Slice Thumbnail Preview */}
                    <div className="my-3 relative rounded-xl overflow-hidden bg-slate-900 border border-slate-300 aspect-video group">
                      <img
                        src={slices[0].src}
                        alt="PACS Slice"
                        className="w-full h-full object-cover opacity-85 group-hover:scale-105 transition-transform duration-300"
                      />
                      <div className="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent flex flex-col justify-between p-3 text-white">
                        <div className="flex items-center justify-between text-[10px] font-mono opacity-90">
                          <span className="bg-black/60 px-1.5 py-0.5 rounded">{apt.modality.code} DICOM STACK</span>
                          <span className="bg-black/60 px-1.5 py-0.5 rounded">{slices.length} Key Frames</span>
                        </div>
                        <div>
                          <div className="text-xs font-bold line-clamp-1">{slices[0].title}</div>
                          <div className="text-[10px] opacity-75 font-mono">{slices[0].matrix} • {slices[0].thickness}</div>
                        </div>
                      </div>

                      <button
                        onClick={() => setPacsModalApt(apt)}
                        className="absolute inset-0 m-auto w-12 h-12 rounded-full bg-cyan-600/90 text-white flex items-center justify-center shadow-lg hover:bg-cyan-500 transition-colors opacity-90 hover:opacity-100 cursor-pointer"
                        title="Open Interactive PACS Viewer"
                      >
                        <Eye className="w-6 h-6" />
                      </button>
                    </div>

                    {/* Findings / Impression */}
                    {hasReport ? (
                      <div className="space-y-2 text-xs">
                        <div className="p-3 rounded-xl bg-slate-50 border border-slate-200">
                          <span className="font-semibold text-slate-700 block mb-1">Impression:</span>
                          <p className="text-slate-800 italic line-clamp-2">"{apt.report?.impression}"</p>
                        </div>
                        <div className="text-[11px] text-slate-500 flex items-center justify-between">
                          <span>Consultant: <strong>{apt.report?.signedBy}</strong></span>
                          <span>Signed: {apt.report?.signedAt || apt.date}</span>
                        </div>
                      </div>
                    ) : (
                      <div className="p-4 rounded-xl bg-amber-50/60 border border-amber-200 text-xs text-amber-800">
                        Scan acquisition in progress. Diagnostic report will be released following radiologist interpretation.
                      </div>
                    )}
                  </div>

                  {/* Actions */}
                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button
                      onClick={() => setPacsModalApt(apt)}
                      className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-cyan-900 text-xs font-bold flex items-center justify-center gap-1.5 cursor-pointer shadow-xs"
                    >
                      <Eye className="w-3.5 h-3.5 text-cyan-700" /> View PACS Slices
                    </button>

                    {hasReport && apt.report && (
                      <button
                        onClick={() => generateRadiologyReportPdf(apt, apt.report!)}
                        className="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center justify-center gap-1.5 cursor-pointer shadow-md shadow-emerald-600/20"
                      >
                        <Download className="w-3.5 h-3.5" /> PDF Report
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 3: PRE-SCAN PROTOCOLS & SAFETY CHECKLIST                             */}
      {/* ========================================================================= */}
      {activeSubTab === 'prep' && (
        <div className="space-y-6">
          <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-sm space-y-4">
            <div className="flex items-center space-x-2">
              <ShieldCheck className="w-5 h-5 text-cyan-600" />
              <h2 className="text-base font-bold text-slate-900">Interactive Pre-Examination Preparation Protocol</h2>
            </div>
            <p className="text-xs text-slate-600 leading-relaxed">
              Following pre-imaging instructions ensures optimal diagnostic image resolution, safety during IV contrast administration, and prevents scan rescheduling.
            </p>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4 pt-2">
              {/* Checklist 1: Fasting */}
              <div className="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                    <Flame className="w-4 h-4 text-amber-500" /> 1. Fasting (NPO) Requirement
                  </span>
                  <button
                    onClick={() => setCheckedPrepItems(prev => ({ ...prev, fasting: !prev.fasting }))}
                    className="text-xs font-bold text-cyan-700 flex items-center gap-1 cursor-pointer"
                  >
                    {checkedPrepItems.fasting ? (
                      <span className="flex items-center text-emerald-700"><CheckSquare className="w-4 h-4 mr-1" /> Confirmed</span>
                    ) : (
                      <span className="flex items-center text-slate-500"><Square className="w-4 h-4 mr-1" /> Mark Done</span>
                    )}
                  </button>
                </div>
                <p className="text-xs text-slate-600">
                  Maintain 4-6 hours fasting for Abdominal Ultrasound and IV Contrast CT/MRI scans to prevent aspiration and biliary collapse.
                </p>
              </div>

              {/* Checklist 2: Hydration & Bladder */}
              <div className="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                    <Droplets className="w-4 h-4 text-sky-500" /> 2. Urinary Bladder Filling
                  </span>
                  <button
                    onClick={() => setCheckedPrepItems(prev => ({ ...prev, hydration: !prev.hydration }))}
                    className="text-xs font-bold text-cyan-700 flex items-center gap-1 cursor-pointer"
                  >
                    {checkedPrepItems.hydration ? (
                      <span className="flex items-center text-emerald-700"><CheckSquare className="w-4 h-4 mr-1" /> Confirmed</span>
                    ) : (
                      <span className="flex items-center text-slate-500"><Square className="w-4 h-4 mr-1" /> Mark Done</span>
                    )}
                  </button>
                </div>
                <p className="text-xs text-slate-600">
                  For Pelvis & Obstetrics scans, drink 1 litre of water 1 hour prior to scan. Do not void before entering the exam room.
                </p>
              </div>

              {/* Checklist 3: Metal & Implant Safety */}
              <div className="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                    <Lock className="w-4 h-4 text-rose-500" /> 3. Ferromagnetic Metal Clearance
                  </span>
                  <button
                    onClick={() => setCheckedPrepItems(prev => ({ ...prev, metals: !prev.metals }))}
                    className="text-xs font-bold text-cyan-700 flex items-center gap-1 cursor-pointer"
                  >
                    {checkedPrepItems.metals ? (
                      <span className="flex items-center text-emerald-700"><CheckSquare className="w-4 h-4 mr-1" /> Confirmed</span>
                    ) : (
                      <span className="flex items-center text-slate-500"><Square className="w-4 h-4 mr-1" /> Mark Done</span>
                    )}
                  </button>
                </div>
                <p className="text-xs text-slate-600">
                  Remove all piercings, necklaces, belt buckles, dentures, coins, and hairpins prior to entering the MRI or X-Ray suite.
                </p>
              </div>

              {/* Checklist 4: Renal Function */}
              <div className="p-4 rounded-xl border border-slate-200 bg-slate-50 space-y-2">
                <div className="flex items-center justify-between">
                  <span className="font-bold text-xs text-slate-900 flex items-center gap-1.5">
                    <FileText className="w-4 h-4 text-purple-500" /> 4. Creatinine & eGFR Lab Report
                  </span>
                  <button
                    onClick={() => setCheckedPrepItems(prev => ({ ...prev, reports: !prev.reports }))}
                    className="text-xs font-bold text-cyan-700 flex items-center gap-1 cursor-pointer"
                  >
                    {checkedPrepItems.reports ? (
                      <span className="flex items-center text-emerald-700"><CheckSquare className="w-4 h-4 mr-1" /> Confirmed</span>
                    ) : (
                      <span className="flex items-center text-slate-500"><Square className="w-4 h-4 mr-1" /> Mark Done</span>
                    )}
                  </button>
                </div>
                <p className="text-xs text-slate-600">
                  Contrast studies require a valid Serum Creatinine test (less than 30 days old) to confirm normal renal clearance.
                </p>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* TAB 4: BILLING, INVOICES & ONLINE SETTLEMENT                             */}
      {/* ========================================================================= */}
      {activeSubTab === 'billing' && (
        <div className="space-y-4">
          <div className="flex items-center justify-between">
            <h2 className="text-base font-bold text-slate-900">Invoices, POS Receipts & Online Payment</h2>
            <span className="text-xs text-slate-500">Official tax invoices & instant payment settlement</span>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {patientInvoices.map((inv) => {
              const isPaid = inv.status === 'paid' || inv.balanceDue === 0;

              return (
                <div key={inv.id} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3 flex flex-col justify-between">
                  <div className="space-y-3">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                      <div>
                        <span className="font-mono font-bold text-slate-900 text-sm">{inv.invoiceNumber}</span>
                        <div className="text-[11px] text-slate-500">Issued: {inv.createdAt}</div>
                      </div>
                      <span className={`text-xs font-bold px-2.5 py-0.5 rounded-full uppercase ${
                        isPaid ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'
                      }`}>
                        {isPaid ? 'Paid in Full' : `Due: Rs. ${inv.balanceDue.toLocaleString()}`}
                      </span>
                    </div>

                    {/* Item Breakdown */}
                    <div className="space-y-1 text-xs">
                      {inv.items.map((item) => (
                        <div key={item.id} className="flex items-center justify-between text-slate-700">
                          <span className="truncate max-w-[200px]">{item.description} (x{item.quantity})</span>
                          <span className="font-mono font-semibold">Rs. {item.lineTotal.toLocaleString()}</span>
                        </div>
                      ))}
                    </div>

                    <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 text-xs font-mono space-y-1">
                      <div className="flex items-center justify-between text-slate-600">
                        <span>Subtotal:</span>
                        <span>Rs. {inv.subtotal.toLocaleString()}</span>
                      </div>
                      {inv.discountTotal > 0 && (
                        <div className="flex items-center justify-between text-emerald-700">
                          <span>Discount / Concession:</span>
                          <span>- Rs. {inv.discountTotal.toLocaleString()}</span>
                        </div>
                      )}
                      <div className="flex items-center justify-between font-bold text-slate-900 border-t border-slate-200 pt-1">
                        <span>Total Paid:</span>
                        <span className="text-emerald-700">Rs. {inv.paidTotal.toLocaleString()}</span>
                      </div>
                    </div>
                  </div>

                  {/* Payment / Receipt Actions */}
                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between gap-2">
                    <button
                      onClick={() => generateInvoicePdf(inv)}
                      className="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-cyan-900 border border-slate-300 text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-xs flex-1 justify-center"
                    >
                      <Download className="w-3.5 h-3.5 text-cyan-700" />
                      <span>Download Receipt</span>
                    </button>

                    {!isPaid && (
                      <button
                        onClick={() => setPayingInvoice(inv)}
                        className="px-4 py-2 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white text-xs font-bold flex items-center gap-1.5 cursor-pointer shadow-md shadow-emerald-600/20 flex-1 justify-center"
                      >
                        <CreditCard className="w-3.5 h-3.5" />
                        <span>Pay Online Now</span>
                      </button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 1: INTERACTIVE DICOM PACS KEY-IMAGE VIEWER                          */}
      {/* ========================================================================= */}
      {pacsModalApt && (
        <div className="fixed inset-0 z-50 bg-black/80 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-slate-950 text-white rounded-3xl border border-slate-800 shadow-2xl max-w-4xl w-full max-h-[92vh] flex flex-col overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            {/* PACS Header Bar */}
            <div className="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-900/60">
              <div className="flex items-center space-x-3">
                <div className="w-8 h-8 rounded-lg bg-cyan-600 flex items-center justify-center text-white font-bold text-xs">
                  {pacsModalApt.modality.code}
                </div>
                <div>
                  <h3 className="text-sm font-bold tracking-tight text-white">{pacsModalApt.service.name}</h3>
                  <div className="text-[11px] font-mono text-slate-400">
                    Patient: {patient.name} ({patient.mrn}) • Accession: #{pacsModalApt.tokenNumber}
                  </div>
                </div>
              </div>

              <div className="flex items-center space-x-2">
                <button
                  onClick={() => setPacsModalApt(null)}
                  className="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold cursor-pointer"
                >
                  Close Viewer
                </button>
              </div>
            </div>

            {/* PACS Viewport Body */}
            <div className="flex-1 p-4 grid grid-cols-1 md:grid-cols-12 gap-4 overflow-hidden">
              {/* Main Viewport (9 Columns) */}
              <div className="md:col-span-9 flex flex-col items-center justify-center bg-black rounded-2xl border border-slate-800 relative overflow-hidden group min-h-[380px]">
                {/* Active Image with Filters */}
                {(() => {
                  const slices = getDicomSlices(pacsModalApt.modality.code);
                  const activeSlice = slices[activeSliceIndex] || slices[0];

                  let filterStyle = '';
                  if (windowPreset === 'bone') filterStyle = 'contrast(160%) brightness(120%)';
                  else if (windowPreset === 'lung') filterStyle = 'contrast(190%) brightness(90%)';
                  else if (windowPreset === 'brain') filterStyle = 'contrast(130%) brightness(105%)';
                  else if (windowPreset === 'invert') filterStyle = 'invert(100%) contrast(120%)';
                  else filterStyle = 'contrast(115%) brightness(100%)';

                  return (
                    <>
                      <img
                        src={activeSlice.src}
                        alt="DICOM Viewport"
                        style={{
                          transform: `scale(${zoomLevel})`,
                          filter: filterStyle,
                          transition: 'transform 0.2s ease, filter 0.2s ease',
                        }}
                        className="max-h-[360px] object-contain select-none"
                      />

                      {/* DICOM Overlay HUD */}
                      {showDicomMetadata && (
                        <>
                          <div className="absolute top-3 left-3 text-[10px] font-mono text-cyan-400 bg-black/60 p-2 rounded border border-slate-800 space-y-0.5 select-none">
                            <div>ADC PACS v4.2</div>
                            <div>MOD: {pacsModalApt.modality.code}</div>
                            <div>{activeSlice.kvp}</div>
                            <div>{activeSlice.mas}</div>
                          </div>

                          <div className="absolute top-3 right-3 text-[10px] font-mono text-cyan-400 bg-black/60 p-2 rounded border border-slate-800 text-right space-y-0.5 select-none">
                            <div>SL: {activeSliceIndex + 1}/{slices.length}</div>
                            <div>THK: {activeSlice.thickness}</div>
                            <div>MTX: {activeSlice.matrix}</div>
                            <div>ZOOM: {(zoomLevel * 100).toFixed(0)}%</div>
                          </div>

                          <div className="absolute bottom-3 left-3 right-3 text-center text-xs font-mono text-white/90 bg-black/70 py-1.5 px-3 rounded-lg border border-slate-800 truncate select-none">
                            {activeSlice.title}
                          </div>
                        </>
                      )}
                    </>
                  );
                })()}

                {/* Floating Viewport Controls */}
                <div className="absolute bottom-12 flex items-center space-x-1.5 bg-slate-900/90 p-1.5 rounded-xl border border-slate-700 shadow-xl opacity-90 group-hover:opacity-100 transition-opacity">
                  <button
                    onClick={() => setZoomLevel(prev => Math.min(prev + 0.25, 2.5))}
                    className="p-1.5 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white cursor-pointer"
                    title="Zoom In"
                  >
                    <ZoomIn className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => setZoomLevel(prev => Math.max(prev - 0.25, 0.75))}
                    className="p-1.5 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white cursor-pointer"
                    title="Zoom Out"
                  >
                    <ZoomOut className="w-4 h-4" />
                  </button>
                  <button
                    onClick={() => setZoomLevel(1)}
                    className="p-1.5 rounded-lg hover:bg-slate-800 text-slate-300 hover:text-white cursor-pointer text-[10px] font-bold"
                    title="Reset Zoom"
                  >
                    100%
                  </button>
                  <div className="h-4 w-px bg-slate-700 mx-1" />
                  <button
                    onClick={() => setShowDicomMetadata(!showDicomMetadata)}
                    className={`p-1.5 rounded-lg text-xs font-bold cursor-pointer ${
                      showDicomMetadata ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:bg-slate-800'
                    }`}
                    title="Toggle DICOM HUD"
                  >
                    HUD
                  </button>
                </div>
              </div>

              {/* PACS Sidebar: Window/Level Presets & Slices Carousel (3 Columns) */}
              <div className="md:col-span-3 space-y-3 flex flex-col justify-between">
                <div className="space-y-3">
                  <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block">
                    Window / Level Presets
                  </span>
                  <div className="grid grid-cols-2 gap-1.5 text-xs">
                    {[
                      { key: 'soft', label: 'Soft Tissue' },
                      { key: 'bone', label: 'Bone Contrast' },
                      { key: 'lung', label: 'Lung Window' },
                      { key: 'brain', label: 'Brain Density' },
                      { key: 'invert', label: 'Invert LUT' },
                    ].map(w => (
                      <button
                        key={w.key}
                        onClick={() => setWindowPreset(w.key as any)}
                        className={`p-2 rounded-xl text-left font-bold transition-all cursor-pointer ${
                          windowPreset === w.key
                            ? 'bg-cyan-600 text-white shadow-sm'
                            : 'bg-slate-900 hover:bg-slate-800 text-slate-300 border border-slate-800'
                        }`}
                      >
                        {w.label}
                      </button>
                    ))}
                  </div>

                  <span className="text-xs font-bold text-slate-400 uppercase tracking-wider block pt-2">
                    Key Slices ({getDicomSlices(pacsModalApt.modality.code).length})
                  </span>
                  <div className="space-y-2 max-h-[160px] overflow-y-auto pr-1">
                    {getDicomSlices(pacsModalApt.modality.code).map((sl, idx) => (
                      <button
                        key={idx}
                        onClick={() => setActiveSliceIndex(idx)}
                        className={`w-full p-2 rounded-xl text-left text-xs transition-all cursor-pointer flex items-center space-x-2 ${
                          activeSliceIndex === idx
                            ? 'bg-slate-800 border-2 border-cyan-500 text-white'
                            : 'bg-slate-900/60 border border-slate-800 text-slate-400 hover:text-slate-200'
                        }`}
                      >
                        <span className="w-5 h-5 rounded bg-slate-800 text-cyan-400 font-mono font-bold flex items-center justify-center text-[10px]">
                          {idx + 1}
                        </span>
                        <span className="truncate text-[11px] font-medium">{sl.title}</span>
                      </button>
                    ))}
                  </div>
                </div>

                <div className="pt-3 border-t border-slate-800">
                  <button
                    onClick={() => {
                      if (pacsModalApt.report) {
                        generateRadiologyReportPdf(pacsModalApt, pacsModalApt.report);
                      }
                    }}
                    className="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center justify-center gap-1.5 cursor-pointer shadow-md shadow-emerald-600/20"
                  >
                    <Download className="w-3.5 h-3.5" /> Download Full Diagnostic PDF
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 2: ONLINE BILLING PAYMENT (RAAST / CARD / MOBILE)                   */}
      {/* ========================================================================= */}
      {payingInvoice && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="text-base font-bold text-slate-900">Settle Invoice Online</h3>
                <span className="text-xs font-mono text-cyan-800 font-bold">{payingInvoice.invoiceNumber}</span>
              </div>
              <button
                onClick={() => setPayingInvoice(null)}
                className="text-slate-400 hover:text-slate-600 font-bold text-sm cursor-pointer"
              >
                ✕
              </button>
            </div>

            {paymentSuccessMessage ? (
              <div className="p-5 rounded-2xl bg-emerald-50 border border-emerald-200 text-center space-y-2">
                <CheckCircle2 className="w-10 h-10 text-emerald-600 mx-auto" />
                <h4 className="text-sm font-bold text-emerald-900">Payment Successfully Completed</h4>
                <p className="text-xs text-emerald-700">{paymentSuccessMessage}</p>
              </div>
            ) : (
              <div className="space-y-4">
                {/* Total Balance Due Box */}
                <div className="p-4 rounded-2xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                  <span className="text-xs font-semibold text-slate-600">Total Payable Balance:</span>
                  <span className="text-2xl font-black font-mono text-cyan-800">
                    Rs. {payingInvoice.balanceDue.toLocaleString()}
                  </span>
                </div>

                {/* Tender Channel Selector */}
                <div className="grid grid-cols-3 gap-2">
                  {[
                    { id: 'raast', label: 'Raast QR (Instant)' },
                    { id: 'card', label: 'Debit / Credit Card' },
                    { id: 'mobile', label: 'Easypaisa / JazzCash' },
                  ].map(m => (
                    <button
                      key={m.id}
                      onClick={() => setPaymentMethod(m.id as any)}
                      className={`p-2.5 rounded-xl text-center text-xs font-bold transition-all cursor-pointer border ${
                        paymentMethod === m.id
                          ? 'bg-cyan-600 text-white border-cyan-600 shadow-xs'
                          : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                      }`}
                    >
                      {m.label}
                    </button>
                  ))}
                </div>

                {/* Method Specific Display */}
                {paymentMethod === 'raast' && (
                  <div className="p-4 rounded-xl bg-cyan-50 border border-cyan-200 text-center space-y-2">
                    <QrCode className="w-16 h-16 mx-auto text-cyan-800" />
                    <div className="text-xs font-bold text-cyan-900">Scan Raast QR via any Banking App</div>
                    <div className="text-[11px] font-mono text-slate-600">IBAN: PK72RAAS00018273645501</div>
                  </div>
                )}

                {paymentMethod === 'card' && (
                  <div className="space-y-2 text-xs">
                    <input
                      type="text"
                      placeholder="Card Number (4000 1234 5678 9010)"
                      className="w-full p-2.5 rounded-xl border border-slate-300 font-mono"
                    />
                    <div className="grid grid-cols-2 gap-2">
                      <input type="text" placeholder="MM/YY" className="p-2.5 rounded-xl border border-slate-300 font-mono" />
                      <input type="password" placeholder="CVV" className="p-2.5 rounded-xl border border-slate-300 font-mono" />
                    </div>
                  </div>
                )}

                {paymentMethod === 'mobile' && (
                  <div className="space-y-2 text-xs">
                    <label className="text-[11px] font-semibold text-slate-600">Mobile Wallet Account Number (03XX-XXXXXXX):</label>
                    <input
                      type="text"
                      placeholder="0300 1234567"
                      defaultValue={patient.phone}
                      className="w-full p-2.5 rounded-xl border border-slate-300 font-mono"
                    />
                  </div>
                )}

                <div className="space-y-1">
                  <label className="text-[11px] font-semibold text-slate-600">Payment Reference / Transaction ID (Optional):</label>
                  <input
                    type="text"
                    value={paymentRef}
                    onChange={(e) => setPaymentRef(e.target.value)}
                    placeholder="e.g. TXN-984729"
                    className="w-full p-2.5 rounded-xl border border-slate-300 text-xs font-mono"
                  />
                </div>

                <div className="pt-2 flex items-center justify-end space-x-2">
                  <button
                    onClick={() => setPayingInvoice(null)}
                    className="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleExecutePayment}
                    disabled={isProcessingPayment}
                    className="px-6 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold flex items-center gap-1.5 shadow-md shadow-emerald-600/20 cursor-pointer disabled:opacity-50"
                  >
                    {isProcessingPayment ? 'Authorizing...' : `Confirm Payment of Rs. ${payingInvoice.balanceDue.toLocaleString()}`}
                  </button>
                </div>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 3: SELF-SERVICE ONLINE BOOKING REQUEST                              */}
      {/* ========================================================================= */}
      {isBookingOpen && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-lg w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center space-x-2">
                <Calendar className="w-5 h-5 text-cyan-600" />
                <h3 className="text-base font-bold text-slate-900">Book Diagnostic Radiology Study</h3>
              </div>
              <button
                onClick={() => setIsBookingOpen(false)}
                className="text-slate-400 hover:text-slate-600 font-bold text-sm cursor-pointer"
              >
                ✕
              </button>
            </div>

            {bookingSuccessToken ? (
              <div className="p-6 rounded-2xl bg-emerald-50 border border-emerald-200 text-center space-y-3">
                <CheckCircle2 className="w-12 h-12 text-emerald-600 mx-auto animate-bounce" />
                <h4 className="text-base font-bold text-emerald-900">Appointment Confirmed!</h4>
                <div className="text-2xl font-black font-mono text-cyan-900 bg-white p-3 rounded-xl border border-emerald-200 inline-block">
                  Token: #{bookingSuccessToken}
                </div>
                <p className="text-xs text-slate-600">
                  Your appointment has been registered with the clinic. Your token slip is ready.
                </p>
              </div>
            ) : (
              <form onSubmit={handleBookingSubmit} className="space-y-3.5 text-xs">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">1. Select Modality Suite:</label>
                  <div className="grid grid-cols-3 gap-1.5">
                    {modalities.map((m) => (
                      <button
                        type="button"
                        key={m.id}
                        onClick={() => {
                          setBookModalityId(m.id);
                          const firstServ = services.find(s => s.modalityId === m.id);
                          if (firstServ) setBookServiceId(firstServ.id);
                        }}
                        className={`p-2 rounded-xl text-center font-bold border transition-all cursor-pointer ${
                          bookModalityId === m.id
                            ? 'bg-cyan-600 text-white border-cyan-600 shadow-xs'
                            : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-slate-100'
                        }`}
                      >
                        {m.name}
                      </button>
                    ))}
                  </div>
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1">2. Select Procedure / Scan:</label>
                  <select
                    value={bookServiceId}
                    onChange={(e) => setBookServiceId(Number(e.target.value))}
                    className="w-full p-2.5 rounded-xl border border-slate-300 font-semibold focus:outline-none focus:ring-1 focus:ring-cyan-500"
                  >
                    {availableServices.map((s) => (
                      <option key={s.id} value={s.id}>
                        {s.name} — Rs. {s.price.toLocaleString()} ({s.durationMinutes} mins)
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="font-bold text-slate-700 block mb-1">Preferred Date:</label>
                    <input
                      type="date"
                      value={bookDate}
                      onChange={(e) => setBookDate(e.target.value)}
                      className="w-full p-2.5 rounded-xl border border-slate-300 font-medium"
                    />
                  </div>
                  <div>
                    <label className="font-bold text-slate-700 block mb-1">Preferred Time Slot:</label>
                    <select
                      value={bookTime}
                      onChange={(e) => setBookTime(e.target.value)}
                      className="w-full p-2.5 rounded-xl border border-slate-300 font-medium"
                    >
                      <option value="09:00 AM">09:00 AM (Morning)</option>
                      <option value="10:30 AM">10:30 AM (Morning)</option>
                      <option value="12:00 PM">12:00 PM (Noon)</option>
                      <option value="02:30 PM">02:30 PM (Afternoon)</option>
                      <option value="04:30 PM">04:30 PM (Evening)</option>
                      <option value="06:00 PM">06:00 PM (Evening)</option>
                    </select>
                  </div>
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1">Referring Physician (Optional):</label>
                  <select
                    value={bookReferrerId || ''}
                    onChange={(e) => setBookReferrerId(e.target.value ? Number(e.target.value) : undefined)}
                    className="w-full p-2.5 rounded-xl border border-slate-300"
                  >
                    <option value="">Walk-in / Self-Referred</option>
                    {referrers.map((r) => (
                      <option key={r.id} value={r.id}>
                        {r.name} ({r.specialty} - {r.clinicName})
                      </option>
                    ))}
                  </select>
                </div>

                <div>
                  <label className="font-bold text-slate-700 block mb-1">Clinical Indication / Symptoms / Notes:</label>
                  <textarea
                    rows={2}
                    value={bookNotes}
                    onChange={(e) => setBookNotes(e.target.value)}
                    placeholder="e.g. Pain in right lower quadrant, advised ultrasound by Dr. Rabia."
                    className="w-full p-2.5 rounded-xl border border-slate-300 text-xs"
                  />
                </div>

                <div className="pt-2 flex items-center justify-end space-x-2">
                  <button
                    type="button"
                    onClick={() => setIsBookingOpen(false)}
                    className="px-4 py-2 rounded-xl font-bold text-slate-600 hover:bg-slate-100 cursor-pointer"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    className="px-6 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold shadow-md shadow-cyan-600/20 cursor-pointer"
                  >
                    Confirm Diagnostic Booking
                  </button>
                </div>
              </form>
            )}
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* MODAL 4: SHARE VERIFIED REPORT (WHATSAPP / LINK / EMAIL)                  */}
      {/* ========================================================================= */}
      {shareReportApt && (
        <div className="fixed inset-0 z-50 bg-black/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-3xl border border-slate-200 shadow-2xl max-w-md w-full p-6 space-y-4 animate-in fade-in zoom-in-95 duration-200">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center space-x-2">
                <Share2 className="w-5 h-5 text-cyan-600" />
                <h3 className="text-base font-bold text-slate-900">Share Diagnostic Report</h3>
              </div>
              <button
                onClick={() => setShareReportApt(null)}
                className="text-slate-400 hover:text-slate-600 font-bold text-sm cursor-pointer"
              >
                ✕
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <p className="text-slate-600">
                Share this verified diagnostic report and key DICOM images securely with your consulting physician.
              </p>

              <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1">
                <div className="font-bold text-slate-900">{shareReportApt.service.name}</div>
                <div className="text-[11px] text-slate-500">Token #{shareReportApt.tokenNumber} • Signed by {shareReportApt.report?.signedBy}</div>
              </div>

              {/* Copy URL */}
              <div className="space-y-1">
                <span className="font-semibold text-slate-700">Secure Direct Access URL:</span>
                <div className="flex items-center space-x-1.5">
                  <input
                    type="text"
                    readOnly
                    value={`https://portal.amaddiagnosticcentre.com.pk/report/verify?token=${shareReportApt.tokenNumber}&mrn=${patient.mrn}`}
                    className="w-full p-2 rounded-xl bg-slate-100 border border-slate-300 text-[11px] font-mono text-slate-700"
                  />
                  <button
                    onClick={copyShareableLink}
                    className="p-2 rounded-xl bg-cyan-600 text-white font-bold text-xs flex items-center gap-1 cursor-pointer whitespace-nowrap"
                  >
                    {copiedLink ? <Check className="w-4 h-4 text-white" /> : <Copy className="w-4 h-4" />}
                    <span>{copiedLink ? 'Copied' : 'Copy'}</span>
                  </button>
                </div>
              </div>

              {/* Share Channels */}
              <div className="pt-2 grid grid-cols-2 gap-2">
                <a
                  href={`https://api.whatsapp.com/send?text=${encodeURIComponent(`Amad Diagnostic Centre Verified Report for ${patient.name} (${shareReportApt.service.name}): https://portal.amaddiagnosticcentre.com.pk/report/verify?token=${shareReportApt.tokenNumber}&mrn=${patient.mrn}`)}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="p-2.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 font-bold border border-emerald-300 text-center flex items-center justify-center gap-1.5 cursor-pointer"
                >
                  <MessageSquare className="w-4 h-4 text-emerald-600" />
                  <span>Send via WhatsApp</span>
                </a>

                <button
                  onClick={() => {
                    if (shareReportApt.report) {
                      generateRadiologyReportPdf(shareReportApt, shareReportApt.report);
                    }
                  }}
                  className="p-2.5 rounded-xl bg-cyan-50 hover:bg-cyan-100 text-cyan-800 font-bold border border-cyan-300 text-center flex items-center justify-center gap-1.5 cursor-pointer"
                >
                  <Download className="w-4 h-4 text-cyan-600" />
                  <span>Download PDF</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
