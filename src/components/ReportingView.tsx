import React, { useState, useEffect } from 'react';
import {
  FileText,
  CheckCircle2,
  AlertTriangle,
  Download,
  Send,
  RotateCcw,
  Radiation,
  Sparkles,
  Flame,
  Search,
  Lock,
  Plus,
  Eye,
  Sliders,
  Play,
  RefreshCw,
  ZoomIn,
  ZoomOut,
  X,
  PhoneCall,
  History,
  BookOpen,
  Mic,
  MicOff,
  MessageSquare,
  Printer,
  Droplet
} from 'lucide-react';
import { Appointment, RadiologyReport, ReportTemplate } from '../types';
import { generateRadiologyReportPdf } from '../utils/pdfGenerator';
import { RadiologicalVisualizer } from './RadiologicalVisualizer';

interface ReportingViewProps {
  appointments: Appointment[];
  templates: ReportTemplate[];
  selectedAppointment: Appointment | null;
  onSelectAppointment: (apt: Appointment) => void;
  onSaveReport: (aptId: string, reportData: Partial<RadiologyReport>, isFinalize: boolean) => void;
  onRejectToTech: (aptId: string, reason: string) => void;
  onReleaseReport: (aptId: string, channel: 'hand' | 'email' | 'portal') => void;
  onAddTemplate?: (template: ReportTemplate) => void;
}

export const ReportingView: React.FC<ReportingViewProps> = ({
  appointments,
  templates,
  selectedAppointment,
  onSelectAppointment,
  onSaveReport,
  onRejectToTech,
  onReleaseReport,
  onAddTemplate,
}) => {
  const [filterPriority, setFilterPriority] = useState<string>('all');
  const [filterModality, setFilterModality] = useState<string>('all');
  const [searchTerm, setSearchTerm] = useState('');

  // Active study for reporting form
  const apt = selectedAppointment || appointments.find(a => ['acquired', 'reading', 'reported', 'delivered'].includes(a.workflowState)) || null;

  // Local state for report editing fields
  const [clinicalHistory, setClinicalHistory] = useState(apt?.report?.clinicalHistory || apt?.notes || '');
  const [technique, setTechnique] = useState(apt?.report?.technique || '');
  const [comparison, setComparison] = useState(apt?.report?.comparison || 'No previous studies available in PACS archive.');
  const [findings, setFindings] = useState(apt?.report?.findings || '');
  const [impression, setImpression] = useState(apt?.report?.impression || '');
  const [recommendations, setRecommendations] = useState(apt?.report?.recommendations || '');
  const [criticalFlag, setCriticalFlag] = useState(apt?.report?.criticalFlag || false);

  // Modals state
  const [pacsModalOpen, setPacsModalOpen] = useState(false);
  const [rejectModalOpen, setRejectModalOpen] = useState(false);
  const [rejectCategory, setRejectCategory] = useState('Patient Motion Artifact');
  const [rejectNotes, setRejectNotes] = useState('');
  const [criticalModalOpen, setCriticalModalOpen] = useState(false);
  const [criticalDoctorName, setCriticalDoctorName] = useState(apt?.referrer?.name || '');
  const [criticalPhone, setCriticalPhone] = useState(apt?.referrer?.phone || '+92 300 5551234');
  const [criticalReadback, setCriticalReadback] = useState(true);
  const [criticalAdvice, setCriticalAdvice] = useState('Immediate clinical evaluation recommended.');
  const [criticalEscalatedLog, setCriticalEscalatedLog] = useState<string | null>(null);

  const [addendumModalOpen, setAddendumModalOpen] = useState(false);
  const [addendumText, setAddendumText] = useState('');

  const [pdfPreviewModalOpen, setPdfPreviewModalOpen] = useState(false);
  const [saveTemplateModalOpen, setSaveTemplateModalOpen] = useState(false);
  const [newTemplateName, setNewTemplateName] = useState('');

  const [isDictating, setIsDictating] = useState(false);
  const [toastMessage, setToastMessage] = useState<string | null>(null);

  const showToast = (msg: string) => {
    setToastMessage(msg);
    setTimeout(() => setToastMessage(null), 3500);
  };

  // Sync state when selected appointment changes
  useEffect(() => {
    if (apt) {
      setClinicalHistory(apt.report?.clinicalHistory || apt.notes || 'Routine clinical investigation requested.');
      setTechnique(
        apt.report?.technique ||
          (apt.doseLog
            ? `Standard ${apt.modality.name} protocol. Radiation dose: ${apt.doseLog.doseValue} ${apt.doseLog.doseUnit} (DLP: ${apt.doseLog.dlpValue || 'N/A'}). Contrast: ${apt.doseLog.contrastAgent || 'None'}.`
            : `Standard ${apt.modality.name} protocol acquired in multiple projections without complications.`)
      );
      setComparison(apt.report?.comparison || 'No previous studies available in PACS archive.');
      setFindings(apt.report?.findings || '');
      setImpression(apt.report?.impression || '');
      setRecommendations(apt.report?.recommendations || '');
      setCriticalFlag(apt.report?.criticalFlag || false);
      setCriticalDoctorName(apt.referrer?.name || 'Treating Consultant');
      setCriticalPhone(apt.referrer?.phone || '+92 300 5551234');
      setCriticalEscalatedLog(null);
    }
  }, [apt?.id]);

  // Find prior appointments for this patient
  const priorAppointments = appointments.filter(
    (a) => a.patient.id === apt?.patient.id && a.id !== apt?.id
  );

  const reportingWorklist = appointments
    .filter((a) => {
      const matchState = ['acquired', 'reading', 'reported', 'delivered'].includes(a.workflowState);
      const matchPrio = filterPriority === 'all' || a.priority === filterPriority;
      const matchMod = filterModality === 'all' || a.modality.code === filterModality;
      const matchSearch =
        a.patient.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        a.patient.mrn.toLowerCase().includes(searchTerm.toLowerCase()) ||
        a.tokenNumber.toLowerCase().includes(searchTerm.toLowerCase()) ||
        a.service.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
        (a.referrer?.name || '').toLowerCase().includes(searchTerm.toLowerCase());

      return matchState && matchPrio && matchMod && matchSearch;
    })
    .sort((a, b) => {
      const prioOrder = { stat: 0, urgent: 1, routine: 2 };
      const prioDiff = prioOrder[a.priority] - prioOrder[b.priority];
      if (prioDiff !== 0) return prioDiff;

      const stateOrder: Record<string, number> = { acquired: 0, reading: 1, reported: 2, delivered: 3 };
      return (stateOrder[a.workflowState] ?? 9) - (stateOrder[b.workflowState] ?? 9);
    });

  const isFinalized = apt?.workflowState === 'reported' || apt?.workflowState === 'delivered';

  // Apply template
  const handleApplyTemplate = (tpl: ReportTemplate) => {
    setClinicalHistory(tpl.clinicalHistory);
    setTechnique(tpl.technique);
    setFindings(tpl.findings);
    setImpression(tpl.impression);
    setRecommendations(tpl.recommendations);
    showToast(`Loaded Template: "${tpl.name}"`);
  };

  // Quick Macro Inserter
  const handleInsertMacro = (title: string, macroFindings: string, macroImpression: string, macroRec?: string) => {
    setFindings((prev) => (prev.trim() ? `${prev}\n\n${macroFindings}` : macroFindings));
    setImpression((prev) => (prev.trim() ? `${prev}\n${macroImpression}` : macroImpression));
    if (macroRec) {
      setRecommendations((prev) => (prev.trim() ? `${prev}; ${macroRec}` : macroRec));
    }
    showToast(`Inserted Macro: ${title}`);
  };

  // Handle Save / Finalize
  const handleSave = (isFinal: boolean) => {
    if (!apt) return;
    if (isFinal && !impression.trim()) {
      alert('Please provide an Impression / Conclusion before finalizing the report.');
      return;
    }

    const reportPayload: Partial<RadiologyReport> = {
      clinicalHistory,
      technique,
      comparison,
      findings: criticalEscalatedLog ? `${findings}\n\n[CRITICAL FINDING ESCALATION LOG]\n${criticalEscalatedLog}` : findings,
      impression,
      recommendations,
      criticalFlag,
      type: isFinal ? 'final' : 'draft',
      version: apt.report?.version ? (isFinal ? apt.report.version : apt.report.version) : 1,
    };

    onSaveReport(apt.id, reportPayload, isFinal);
    showToast(isFinal ? 'Report Finalized & Electronically Signed' : 'Draft Saved Successfully');
  };

  // Handle Addendum submission
  const handleSaveAddendum = () => {
    if (!apt || !addendumText.trim()) return;
    const existingFindings = apt.report?.findings || findings;
    const addendumHeader = `\n\n--- ADDENDUM / AMENDMENT (${new Date().toLocaleDateString()} ${new Date().toLocaleTimeString()}) by Consultant Radiologist ---\n${addendumText.trim()}`;

    onSaveReport(
      apt.id,
      {
        findings: `${existingFindings}${addendumHeader}`,
        type: 'addendum',
        version: (apt.report?.version || 1) + 1,
      },
      true
    );
    setAddendumModalOpen(false);
    setAddendumText('');
    showToast('Addendum successfully signed and appended to official report.');
  };

  // Handle Structured Rejection
  const handleConfirmReject = () => {
    if (!apt) return;
    const fullReason = rejectNotes.trim() ? `[${rejectCategory}] - ${rejectNotes.trim()}` : rejectCategory;
    onRejectToTech(apt.id, fullReason);
    setRejectModalOpen(false);
    setRejectNotes('');
    showToast(`Study rejected to technologist worklist: "${rejectCategory}"`);
  };

  // Save new custom template
  const handleSaveCustomTemplate = () => {
    if (!newTemplateName.trim() || !apt) return;
    const newTpl: ReportTemplate = {
      id: `tpl-custom-${Date.now()}`,
      modalityId: apt.modalityId,
      name: newTemplateName.trim(),
      code: `TPL-${newTemplateName.toUpperCase().replace(/\s+/g, '-')}`,
      clinicalHistory,
      technique,
      findings,
      impression,
      recommendations,
    };

    if (onAddTemplate) {
      onAddTemplate(newTpl);
    }
    setSaveTemplateModalOpen(false);
    setNewTemplateName('');
    showToast(`Custom Template "${newTpl.name}" saved!`);
  };

  // Simulated Voice Dictation
  const toggleDictation = () => {
    if (!isDictating) {
      setIsDictating(true);
      showToast('Voice dictation active. Speak clinical findings...');
      setTimeout(() => {
        setFindings((prev) =>
          prev
            ? `${prev}\nNo acute intracranial hemorrhage, territorial infarction, or mass effect. Ventricles and sulci are normal for age.`
            : 'No acute intracranial hemorrhage, territorial infarction, or mass effect. Ventricles and sulci are normal for age.'
        );
        setIsDictating(false);
        showToast('Voice transcription transcribed to Findings.');
      }, 2200);
    } else {
      setIsDictating(false);
    }
  };

  // AI Structured Cleanup
  const handleAiCleanup = () => {
    if (!findings.trim()) {
      showToast('Please type some rough findings before running AI structuring.');
      return;
    }
    showToast('AI RadLex formatting in progress...');
    setTimeout(() => {
      setFindings((prev) => {
        return `ANATOMICAL FINDINGS:\n• Parenchyma: Homogeneous signal intensity without focal lesion.\n• Vascular: Major vessels demonstrate expected flow voids.\n• Osseous Structures: Intact alignment without aggressive lytic or blastic changes.\n• Soft Tissues: Unremarkable symmetrical presentation.`;
      });
      if (!impression.trim()) {
        setImpression('Normal diagnostic study. No acute radiological abnormality detected.');
      }
      showToast('AI Formatted report generated to RadLex standard.');
    }, 700);
  };

  // Common quick macros by modality
  const getMacrosForModality = (modCode: string) => {
    switch (modCode) {
      case 'MR':
        return [
          {
            title: 'Normal Brain MRI',
            findings: 'Brain parenchyma demonstrates normal signal characteristics on all pulse sequences. No acute infarction, hemorrhage, or space-occupying lesion. Ventricles, cisterns, and sulci are within normal limits for age. Midline structures are central. Major intracranial flow voids are preserved.',
            impression: 'Unremarkable MRI Brain examination. No acute intracranial pathology.',
            rec: 'Clinical follow-up as indicated.',
          },
          {
            title: 'L4-L5 Disc Herniation',
            findings: 'Physiological lumbar lordosis is maintained. L4-L5 intervertebral disc demonstrates diffuse bulge with a focal posterior central/paracentral protrusion indenting the thecal sac and causing mild bilateral neuroforaminal narrowing. The conus medullaris terminates normally at L1 level.',
            impression: 'L4-L5 posterior disc protrusion with mild thecal sac impingement and bilateral neural exit foraminal narrowing.',
            rec: 'Physiotherapy and neuro-surgical/orthopedic clinical correlation advised.',
          },
        ];
      case 'CT':
        return [
          {
            title: 'Normal Chest CT',
            findings: 'Both lungs are well-aerated with normal bronchovascular markings. No pulmonary consolidation, nodule, mass, or ground-glass opacities. Trachea and central bronchi are patent. Mediastinum and hila show no lymphadenopathy. Heart size is normal. No pleural effusion or pneumothorax.',
            impression: 'Normal High-Resolution Computed Tomography of Chest.',
            rec: 'Routine follow-up.',
          },
          {
            title: 'Acute Appendicitis',
            findings: 'The appendix is significantly distended measuring 11 mm in outer diameter with circumferential mural thickening and avid mucosal hyperenhancement. Extensive periappendiceal fat stranding and localized fluid accumulation in the right iliac fossa. An appendicolith is noted at the base.',
            impression: 'Findings highly suspicious of Acute Suppurative Appendicitis with localized peritonitis.',
            rec: 'Immediate surgical consultation / emergency intervention recommended.',
          },
        ];
      case 'DX':
        return [
          {
            title: 'Normal Chest X-Ray',
            findings: 'The lung fields are clear bilaterally with no active parenchymal infiltrate, consolidation, or mass. The cardiothoracic ratio is within normal limits. Both costophrenic and cardiophrenic angles are sharp. Visualized bony thorax and soft tissues are unremarkable.',
            impression: 'Clear chest radiograph. No acute cardiopulmonary abnormality.',
            rec: 'No immediate imaging follow-up needed.',
          },
          {
            title: 'No Acute Fracture',
            findings: 'Cortical margins are intact without evidence of acute fracture, dislocation, or bone destruction. Joint spaces are preserved. Surrounding soft tissues demonstrate no abnormal swelling or radiopaque foreign body.',
            impression: 'No radiographically detectable acute fracture or dislocation.',
            rec: 'Clinical correlation; repeat views in 7-10 days if symptoms persist.',
          },
        ];
      case 'US':
        return [
          {
            title: 'Normal Abdomen Ultrasound',
            findings: 'Liver is normal in size, contour, and echotexture with no focal lesion. Gallbladder is well-distended, thin-walled, with no calculus or sludge. Common bile duct is normal caliber. Pancreas and spleen appear unremarkable. Both kidneys demonstrate normal size, corticomedullary differentiation, and no hydronephrosis or calculus. Urinary bladder is clear.',
            impression: 'Unremarkable whole abdomen ultrasound examination.',
            rec: 'Routine clinical management.',
          },
          {
            title: 'Cholelithiasis (Gallstones)',
            findings: 'The gallbladder is well-distended with acoustic shadowing produced by multiple mobile echogenic calculi in the gallbladder lumen, largest measuring 14 mm. Gallbladder wall thickness is normal (2.2 mm). No pericholecystic fluid. CBD is normal in caliber.',
            impression: 'Cholelithiasis without sonographic evidence of acute cholecystitis.',
            rec: 'Gastroenterology / general surgery consult advised.',
          },
        ];
      case 'MG':
        return [
          {
            title: 'BI-RADS 1 (Negative)',
            findings: 'Bilateral mammograms show predominantly fibroglandular breast density (ACR Density B). No dominant mass, architectural distortion, or suspicious clustered microcalcifications. Skin and nipple-areolar complexes are normal bilaterally. Visualized axillary lymph nodes are benign in appearance.',
            impression: 'BI-RADS CATEGORY 1: Negative mammogram.',
            rec: 'Annual screening mammography recommended.',
          },
          {
            title: 'BI-RADS 2 (Benign)',
            findings: 'Bilateral symmetric breast parenchyma. Well-circumscribed, oval radiolucent fat-containing lesion with thin capsule in right upper outer quadrant, consistent with benign oil cyst / lipoma. No suspicious microcalcifications or architectural distortion.',
            impression: 'BI-RADS CATEGORY 2: Benign findings.',
            rec: 'Routine annual screening mammography.',
          },
        ];
      default:
        return [
          {
            title: 'Normal Study',
            findings: 'Systematic anatomical review reveals normal tissue characteristics, contours, and alignment. No focal lesion, abnormal fluid collection, or inflammatory changes observed.',
            impression: 'Unremarkable radiological examination.',
            rec: 'Clinical correlation advised.',
          },
        ];
    }
  };

  return (
    <div className="space-y-5">
      {/* Toast Notification */}
      {toastMessage && (
        <div className="fixed top-5 right-5 z-50 bg-slate-900 text-white px-4 py-2.5 rounded-2xl shadow-xl border border-slate-700 text-xs font-semibold flex items-center space-x-2 animate-slide-in">
          <Sparkles className="w-4 h-4 text-cyan-400" />
          <span>{toastMessage}</span>
        </div>
      )}

      {/* Header Banner */}
      <div className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
        <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div>
            <div className="flex items-center space-x-2.5">
              <h1 className="text-xl font-black text-slate-900 tracking-tight">Radiologist Reading & Reporting Suite</h1>
              <span className="bg-purple-50 text-purple-700 text-xs px-2.5 py-1 rounded-md border border-purple-200 font-bold whitespace-nowrap inline-flex items-center gap-1">
                <FileText className="w-3.5 h-3.5 text-purple-600" /> PACS Diagnostic Console v4.2
              </span>
            </div>
            <p className="text-slate-500 text-xs mt-1">
              Diagnostic image interpretation, structured templates, critical finding escalation, electronic sign-off, and multi-channel PDF release.
            </p>
          </div>

          {/* Quick Header Actions */}
          <div className="flex flex-wrap items-center gap-2">
            {apt && (
              <button
                onClick={() => setPacsModalOpen(true)}
                className="flex items-center space-x-1.5 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs transition-all shadow-md shadow-slate-900/20 cursor-pointer"
              >
                <Eye className="w-4 h-4 text-cyan-400" />
                <span>Launch PACS Viewer</span>
              </button>
            )}

            {apt && (
              <button
                onClick={() => setPdfPreviewModalOpen(true)}
                className="flex items-center space-x-1.5 px-3.5 py-2 rounded-xl bg-cyan-50 hover:bg-cyan-100 text-cyan-800 font-bold text-xs border border-cyan-300 transition-all cursor-pointer shadow-xs"
              >
                <FileText className="w-4 h-4 text-cyan-600" />
                <span>Live PDF Preview</span>
              </button>
            )}

            {apt && apt.report && isFinalized && (
              <button
                onClick={() => generateRadiologyReportPdf(apt, apt.report!)}
                className="flex items-center space-x-1.5 px-3.5 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs transition-all shadow-md shadow-cyan-600/20 cursor-pointer"
              >
                <Download className="w-4 h-4" />
                <span>Download Signed PDF</span>
              </button>
            )}
          </div>
        </div>

        {/* Global Reading Queue Status Bar */}
        <div className="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-100 text-xs">
          <div className="flex items-center space-x-3 text-slate-600">
            <span className="flex items-center gap-1">
              <span className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
              PACS Storage Server: <strong className="text-slate-900">Connected (DICOM SCP)</strong>
            </span>
            <span>•</span>
            <span>Signed Radiologist: <strong className="text-purple-900">Dr. M. Raza, MBBS, FCPS (Radiology)</strong></span>
          </div>

          <div className="flex items-center space-x-2">
            <span className="text-[11px] text-slate-400">Total in Queue: {reportingWorklist.length}</span>
            <span className="bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5 rounded">
              {reportingWorklist.filter(a => ['acquired', 'reading'].includes(a.workflowState)).length} Pending Interpretation
            </span>
          </div>
        </div>
      </div>

      {/* Main 12-Column Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-5">
        {/* ========================================================================= */}
        {/* LEFT COLUMN: READING WORKLIST & PATIENT DOSSIER (4 COLS) */}
        {/* ========================================================================= */}
        <div className="lg:col-span-4 space-y-4">
          {/* Worklist Search & Filters */}
          <div className="bg-white p-4 rounded-2xl border border-slate-200 space-y-3 shadow-sm">
            <div className="flex items-center justify-between">
              <h2 className="text-xs font-black text-slate-800 uppercase tracking-wider">
                Reading Queue ({reportingWorklist.length})
              </h2>
              <span className="text-[11px] text-purple-700 font-bold font-mono">
                {reportingWorklist.filter(a => ['acquired', 'reading'].includes(a.workflowState)).length} Unreported
              </span>
            </div>

            {/* Search Input */}
            <div className="relative">
              <Search className="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Search patient, MRN, token..."
                className="w-full bg-slate-50 text-slate-800 pl-8 pr-3 py-1.5 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 focus:bg-white"
              />
            </div>

            {/* Filter Dropdowns */}
            <div className="flex gap-2">
              <select
                value={filterPriority}
                onChange={(e) => setFilterPriority(e.target.value)}
                className="flex-1 bg-slate-50 text-slate-800 px-2 py-1.5 rounded-lg border border-slate-300 text-xs cursor-pointer"
              >
                <option value="all">All Priorities</option>
                <option value="stat">STAT Traumatology</option>
                <option value="urgent">Urgent</option>
                <option value="routine">Routine</option>
              </select>

              <select
                value={filterModality}
                onChange={(e) => setFilterModality(e.target.value)}
                className="flex-1 bg-slate-50 text-slate-800 px-2 py-1.5 rounded-lg border border-slate-300 text-xs cursor-pointer"
              >
                <option value="all">All Modalities</option>
                <option value="DX">X-Ray (DX)</option>
                <option value="US">Ultrasound (US)</option>
                <option value="CT">CT Scan (CT)</option>
                <option value="MR">MRI (MR)</option>
                <option value="MG">Mammography (MG)</option>
              </select>
            </div>
          </div>

          {/* Worklist Studies Scroll List */}
          <div className="space-y-2 max-h-[380px] overflow-y-auto pr-1">
            {reportingWorklist.length === 0 ? (
              <div className="bg-white p-8 rounded-2xl border border-slate-200 text-center text-slate-400 text-xs shadow-sm">
                No studies match the selected filters.
              </div>
            ) : (
              reportingWorklist.map((item) => {
                const isSelected = apt?.id === item.id;
                const isItemFinal = ['reported', 'delivered'].includes(item.workflowState);
                const isStat = item.priority === 'stat';
                const hasReject = Boolean(item.rejectReason);

                return (
                  <div
                    key={item.id}
                    onClick={() => onSelectAppointment(item)}
                    className={`p-3 rounded-xl border transition-all cursor-pointer relative ${
                      isSelected
                        ? 'bg-purple-50/80 border-purple-500 shadow-sm ring-2 ring-purple-400'
                        : isStat
                        ? 'bg-rose-50/20 border-rose-300 hover:bg-rose-50/40'
                        : 'bg-white hover:bg-slate-50 border-slate-200'
                    }`}
                  >
                    <div className="flex items-center justify-between">
                      <div className="flex items-center space-x-1.5 flex-wrap gap-y-1">
                        <span className="font-mono font-black text-xs px-2 py-0.5 rounded bg-slate-100 text-cyan-800 border border-slate-300">
                          {item.tokenNumber}
                        </span>
                        <span
                          className="text-[10px] font-bold text-white px-1.5 py-0.2 rounded font-mono shadow-xs"
                          style={{ backgroundColor: item.modality.color }}
                        >
                          {item.modality.code}
                        </span>
                        {isStat && (
                          <span className="px-1.5 py-0.2 rounded bg-rose-600 text-white font-black text-[9px] uppercase tracking-wide flex items-center gap-0.5 animate-pulse">
                            <Flame className="w-2.5 h-2.5" /> STAT
                          </span>
                        )}
                        {hasReject && (
                          <span className="px-1.5 py-0.2 rounded bg-rose-100 text-rose-800 font-bold text-[9px] border border-rose-300">
                            Re-Scan Requested
                          </span>
                        )}
                      </div>

                      <span
                        className={`text-[10px] font-bold px-2 py-0.5 rounded-md whitespace-nowrap ${
                          isItemFinal
                            ? 'bg-emerald-100 text-emerald-800 border border-emerald-300'
                            : item.workflowState === 'reading'
                            ? 'bg-indigo-100 text-indigo-800 border border-indigo-300'
                            : 'bg-purple-100 text-purple-800 border border-purple-300'
                        }`}
                      >
                        {item.workflowState.replace('_', ' ').toUpperCase()}
                      </span>
                    </div>

                    <div className="mt-2 flex justify-between items-start">
                      <div>
                        <div className="font-bold text-xs text-slate-900">{item.patient.name}</div>
                        <div className="text-[11px] text-slate-500 line-clamp-1">{item.service.name}</div>
                      </div>
                      <span className="text-[10px] text-slate-400 font-mono mt-0.5">
                        {item.patient.age}y • {item.patient.gender[0].toUpperCase()}
                      </span>
                    </div>
                  </div>
                );
              })
            )}
          </div>

          {/* Selected Patient Demographics & Prior Scans Dossier */}
          {apt && (
            <div className="bg-white p-4 rounded-2xl border border-slate-200 space-y-3.5 text-xs shadow-sm">
              <div className="flex items-center justify-between border-b border-slate-200 pb-2.5">
                <span className="font-black text-slate-900 uppercase tracking-wide text-[11px]">
                  Patient & Exam Dossier
                </span>
                <span className="text-[11px] font-mono font-black text-cyan-800 bg-cyan-50 px-2 py-0.5 rounded border border-cyan-200">
                  {apt.patient.mrn}
                </span>
              </div>

              {/* Patient Demographics */}
              <div className="space-y-1.5 text-slate-700">
                <div className="flex justify-between">
                  <span className="text-slate-500">Patient Name:</span>
                  <span className="font-bold text-slate-900">{apt.patient.name}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Demographics:</span>
                  <span>{apt.patient.age}y / {apt.patient.gender.toUpperCase()} / {apt.patient.bloodGroup}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Referring Physician:</span>
                  <span className="font-semibold text-slate-800">{apt.referrer?.name || 'Walk-In / Self'}</span>
                </div>
                <div className="flex justify-between">
                  <span className="text-slate-500">Exam Room:</span>
                  <span className="font-mono text-slate-800">{apt.roomNumber}</span>
                </div>
              </div>

              {/* Radiation Dose & Acquisition Log */}
              {apt.doseLog ? (
                <div className="bg-purple-50/70 p-3 rounded-xl border border-purple-200 space-y-1 text-[11px]">
                  <div className="flex items-center justify-between text-purple-900 font-bold">
                    <div className="flex items-center space-x-1">
                      <Radiation className="w-3.5 h-3.5 text-purple-600" />
                      <span>Dose & Acquisition Parameters</span>
                    </div>
                    <span className="font-mono text-[10px] bg-purple-200 text-purple-900 px-1.5 py-0.2 rounded">
                      {apt.doseLog.sliceCount || 1} Slices
                    </span>
                  </div>
                  <div className="text-slate-800">
                    Radiation: <strong>{apt.doseLog.doseValue} {apt.doseLog.doseUnit}</strong>
                    {apt.doseLog.dlpValue && ` (DLP: ${apt.doseLog.dlpValue} mGy*cm)`}
                  </div>
                  {apt.doseLog.contrastAgent && (
                    <div className="text-slate-800">
                      IV Contrast: <strong>{apt.doseLog.contrastAgent}</strong> ({apt.doseLog.contrastVolumeMl} mL)
                    </div>
                  )}
                  <div className="text-[10px] text-slate-500">
                    Acquired by: {apt.doseLog.recordedBy} at {apt.doseLog.recordedAt}
                  </div>
                </div>
              ) : (
                <div className="text-[11px] text-slate-400 italic bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                  Non-ionizing scan (Ultrasound/MRI) - No radiation exposure recorded.
                </div>
              )}

              {/* Prior Examinations List */}
              <div className="space-y-1.5 pt-1 border-t border-slate-100">
                <div className="flex items-center justify-between text-[11px] font-bold text-slate-700">
                  <span className="flex items-center gap-1">
                    <History className="w-3.5 h-3.5 text-slate-500" /> Prior Imaging ({priorAppointments.length})
                  </span>
                  {priorAppointments.length > 0 && (
                    <button
                      onClick={() => {
                        const prior = priorAppointments[0];
                        const compStr = `Compared with previous ${prior.service.name} (${prior.date}) in PACS archive.`;
                        setComparison(compStr);
                        showToast('Imported prior study comparison info.');
                      }}
                      className="text-[10px] text-purple-700 hover:underline font-bold cursor-pointer"
                    >
                      Use Most Recent
                    </button>
                  )}
                </div>

                {priorAppointments.length === 0 ? (
                  <div className="text-[10px] text-slate-400 italic">No prior studies found for this MRN.</div>
                ) : (
                  <div className="space-y-1">
                    {priorAppointments.map((pa) => (
                      <div
                        key={pa.id}
                        className="bg-slate-50 p-2 rounded-lg border border-slate-200 flex items-center justify-between text-[10px]"
                      >
                        <div>
                          <span className="font-bold text-slate-800">{pa.service.name}</span>
                          <div className="text-slate-400 font-mono">{pa.date} • #{pa.tokenNumber}</div>
                        </div>
                        <button
                          onClick={() => {
                            setComparison(`Compared with prior ${pa.service.name} dated ${pa.date}.`);
                            showToast(`Linked prior ${pa.service.name}`);
                          }}
                          className="px-1.5 py-0.5 rounded bg-white hover:bg-slate-100 text-purple-700 font-bold border border-slate-300 cursor-pointer"
                        >
                          Link
                        </button>
                      </div>
                    ))}
                  </div>
                )}
              </div>

              {/* Reject Back to Tech Button */}
              {!isFinalized && (
                <button
                  onClick={() => {
                    setRejectCategory('Patient Motion Artifact');
                    setRejectNotes('');
                    setRejectModalOpen(true);
                  }}
                  className="w-full flex items-center justify-center space-x-1.5 py-2 rounded-xl bg-slate-50 hover:bg-rose-50 text-slate-700 hover:text-rose-700 border border-slate-300 hover:border-rose-300 transition-colors cursor-pointer text-xs font-bold shadow-xs"
                >
                  <RotateCcw className="w-3.5 h-3.5 text-rose-600" />
                  <span>Reject to Tech for Re-Scan</span>
                </button>
              )}
            </div>
          )}
        </div>

        {/* ========================================================================= */}
        {/* RIGHT COLUMN: DIAGNOSTIC REPORT WORKBENCH (8 COLS) */}
        {/* ========================================================================= */}
        <div className="lg:col-span-8 space-y-4">
          {!apt ? (
            <div className="bg-white p-16 rounded-3xl border border-slate-200 text-center text-slate-500 shadow-sm">
              <FileText className="w-12 h-12 mx-auto mb-3 opacity-50 text-purple-500" />
              <h3 className="text-base font-bold text-slate-900">No Study Selected</h3>
              <p className="text-xs mt-1">Select a study from the reading worklist on the left to begin reporting.</p>
            </div>
          ) : (
            <div className="bg-white rounded-3xl border border-slate-200 p-6 shadow-sm space-y-5">
              {/* Study Header & Mode Switcher */}
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-200 pb-4">
                <div>
                  <div className="flex items-center space-x-2 flex-wrap gap-y-1">
                    <h2 className="text-lg font-black text-slate-900">{apt.service.name}</h2>
                    <span
                      className="text-xs font-bold text-white px-2 py-0.5 rounded font-mono shadow-xs"
                      style={{ backgroundColor: apt.modality.color }}
                    >
                      {apt.modality.code}
                    </span>
                    {criticalFlag && (
                      <span className="bg-rose-600 text-white text-xs px-2.5 py-0.5 rounded-md font-black uppercase tracking-wider inline-flex items-center gap-1 shadow-xs animate-pulse">
                        <Flame className="w-3.5 h-3.5" /> Critical Finding
                      </span>
                    )}
                  </div>
                  <p className="text-xs text-slate-500 mt-1">
                    Accession: <span className="font-mono font-bold text-slate-800">ACC-{apt.id.slice(-6).toUpperCase()}</span> | Token:{' '}
                    <span className="font-mono font-bold text-cyan-700">#{apt.tokenNumber}</span> | Room: {apt.roomNumber}
                  </p>
                </div>

                {/* Template Selector & Tool Buttons */}
                {!isFinalized && (
                  <div className="flex items-center space-x-2">
                    {/* Templates Menu */}
                    <div className="relative">
                      <select
                        onChange={(e) => {
                          const tpl = templates.find((t) => t.id === e.target.value);
                          if (tpl) handleApplyTemplate(tpl);
                        }}
                        className="bg-slate-50 text-slate-800 text-xs px-3 py-1.5 rounded-xl border border-slate-300 font-bold focus:outline-none focus:ring-1 focus:ring-purple-500 cursor-pointer"
                        defaultValue=""
                      >
                        <option value="" disabled>
                          Load Template ({templates.filter((t) => t.modalityId === apt.modalityId).length})...
                        </option>
                        {templates.map((tpl) => (
                          <option key={tpl.id} value={tpl.id}>
                            {tpl.name}
                          </option>
                        ))}
                      </select>
                    </div>

                    <button
                      onClick={() => {
                        setNewTemplateName(`${apt.service.name} Standard`);
                        setSaveTemplateModalOpen(true);
                      }}
                      title="Save Current Report as New Template"
                      className="p-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs border border-slate-300 transition-colors cursor-pointer"
                    >
                      <Plus className="w-4 h-4" />
                    </button>
                  </div>
                )}
              </div>

              {/* Quick Macro Pills for Common Diagnoses */}
              {!isFinalized && (
                <div className="space-y-1.5 bg-slate-50 p-3 rounded-2xl border border-slate-200">
                  <div className="flex items-center justify-between text-[11px] font-bold text-slate-600">
                    <span className="flex items-center gap-1.5">
                      <Sparkles className="w-3.5 h-3.5 text-purple-600" /> Rapid Diagnostic Macro Inserter ({apt.modality.code}):
                    </span>
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={toggleDictation}
                        className={`px-2 py-0.5 rounded-md text-[10px] font-bold flex items-center gap-1 cursor-pointer transition-colors ${
                          isDictating
                            ? 'bg-rose-600 text-white animate-pulse'
                            : 'bg-white text-slate-700 border border-slate-300 hover:bg-slate-100'
                        }`}
                      >
                        {isDictating ? <MicOff className="w-3 h-3" /> : <Mic className="w-3 h-3 text-purple-600" />}
                        <span>{isDictating ? 'Recording...' : 'Voice Dictate'}</span>
                      </button>

                      <button
                        onClick={handleAiCleanup}
                        className="px-2 py-0.5 rounded-md text-[10px] font-bold bg-purple-600 hover:bg-purple-500 text-white flex items-center gap-1 cursor-pointer transition-colors shadow-2xs"
                      >
                        <Sparkles className="w-3 h-3" />
                        <span>AI RadLex Clean</span>
                      </button>
                    </div>
                  </div>

                  <div className="flex flex-wrap gap-1.5 pt-1">
                    {getMacrosForModality(apt.modality.code).map((m, idx) => (
                      <button
                        key={idx}
                        onClick={() => handleInsertMacro(m.title, m.findings, m.impression, m.rec)}
                        className="px-2.5 py-1 rounded-lg bg-white hover:bg-purple-100 hover:text-purple-900 text-slate-700 text-xs font-semibold border border-slate-300 transition-all cursor-pointer shadow-2xs text-left"
                      >
                        + {m.title}
                      </button>
                    ))}
                  </div>
                </div>
              )}

              {/* Critical / Urgent Alert Notification Banner */}
              <div className="flex items-center justify-between p-3.5 rounded-2xl bg-slate-50 border border-slate-200">
                <div className="flex items-center space-x-3">
                  <AlertTriangle className={`w-5 h-5 ${criticalFlag ? 'text-rose-600' : 'text-slate-400'}`} />
                  <div>
                    <div className="text-xs font-bold text-slate-900">Critical / Urgent Finding Alert Protocol</div>
                    <div className="text-[11px] text-slate-500">
                      Flags life-threatening or urgent findings requiring direct telephonic escalation to treating physician.
                    </div>
                  </div>
                </div>

                <div className="flex items-center space-x-2">
                  {criticalFlag && !isFinalized && (
                    <button
                      onClick={() => setCriticalModalOpen(true)}
                      className="px-2.5 py-1 rounded-lg bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs flex items-center gap-1 shadow-xs cursor-pointer animate-pulse"
                    >
                      <PhoneCall className="w-3.5 h-3.5" />
                      <span>Log Escalation Call</span>
                    </button>
                  )}

                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={criticalFlag}
                      disabled={isFinalized}
                      onChange={(e) => {
                        setCriticalFlag(e.target.checked);
                        if (e.target.checked) setCriticalModalOpen(true);
                      }}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-rose-600"></div>
                  </label>
                </div>
              </div>

              {/* Critical Call Logged Banner */}
              {criticalEscalatedLog && (
                <div className="p-3 rounded-xl bg-rose-50 border border-rose-300 text-xs text-rose-900 flex items-start space-x-2">
                  <CheckCircle2 className="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                  <div>
                    <strong>Critical Telephonic Escalation Logged:</strong>
                    <div className="text-[11px] text-rose-800 mt-0.5 whitespace-pre-line">{criticalEscalatedLog}</div>
                  </div>
                </div>
              )}

              {/* Diagnostic Report Form Fields */}
              <div className="space-y-4">
                {/* Clinical Indication */}
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Clinical Indication / Presenting Symptoms
                  </label>
                  <textarea
                    rows={2}
                    value={clinicalHistory}
                    disabled={isFinalized}
                    onChange={(e) => setClinicalHistory(e.target.value)}
                    className="w-full bg-white text-slate-900 p-3 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:bg-slate-50 disabled:opacity-85"
                    placeholder="Clinical presentation, clinical history, relevant past surgeries..."
                  />
                </div>

                {/* Technique & Protocol */}
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                      Imaging Technique & Protocol
                    </label>
                    {apt.doseLog?.contrastAgent && !isFinalized && (
                      <button
                        type="button"
                        onClick={() => {
                          const contrastStr = `\n[Technique Protocol]: Administered ${apt.doseLog?.contrastAgent} (${apt.doseLog?.contrastVolumeMl} mL IV) at ${apt.doseLog?.contrastFlowRate || '3.5 mL/s'} via ${apt.doseLog?.cannulaSite || 'IV cannula'} with ${apt.doseLog?.salineFlushMl || 30} mL saline chase. Total radiation dose: CTDIvol ${apt.doseLog?.doseValue} ${apt.doseLog?.doseUnit}${apt.doseLog?.dlpValue ? `, DLP ${apt.doseLog?.dlpValue} mGy*cm` : ''}.`;
                          setTechnique(prev => (prev ? prev.trim() + ' ' + contrastStr : contrastStr));
                          showToast('Inserted IV contrast & dosimetry protocol.');
                        }}
                        className="text-[10px] text-cyan-700 hover:text-cyan-900 font-bold flex items-center gap-1 hover:underline cursor-pointer"
                      >
                        <Droplet className="w-3 h-3 text-cyan-600" />
                        <span>Insert Contrast & Dose Data</span>
                      </button>
                    )}
                  </div>
                  <textarea
                    rows={2}
                    value={technique}
                    disabled={isFinalized}
                    onChange={(e) => setTechnique(e.target.value)}
                    className="w-full bg-white text-slate-900 p-3 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:bg-slate-50 disabled:opacity-85"
                    placeholder="Sequences, views, reformations, slice thickness, IV contrast protocol..."
                  />
                </div>

                {/* Comparison */}
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Comparison Imaging
                  </label>
                  <input
                    type="text"
                    value={comparison}
                    disabled={isFinalized}
                    onChange={(e) => setComparison(e.target.value)}
                    className="w-full bg-white text-slate-900 px-3 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:bg-slate-50 disabled:opacity-85"
                    placeholder="Prior comparison dates or 'No previous imaging available'..."
                  />
                </div>

                {/* Findings */}
                <div>
                  <div className="flex items-center justify-between mb-1">
                    <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider">
                      Detailed Diagnostic Findings
                    </label>
                    <span className="text-[11px] font-mono text-slate-400">{findings.length} chars</span>
                  </div>
                  <textarea
                    rows={7}
                    value={findings}
                    disabled={isFinalized}
                    onChange={(e) => setFindings(e.target.value)}
                    className="w-full bg-white text-slate-900 p-3 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 font-mono leading-relaxed disabled:bg-slate-50 disabled:opacity-85"
                    placeholder="Systematic anatomical review and detailed radiological observations..."
                  />
                </div>

                {/* Impression (Crucial Diagnostic Conclusion) */}
                <div>
                  <label className="block text-xs font-bold text-purple-900 uppercase tracking-wider mb-1 flex items-center justify-between">
                    <span>Impression & Conclusion *</span>
                    <span className="text-[10px] text-purple-700 font-normal">Primary diagnostic takeaway</span>
                  </label>
                  <textarea
                    rows={3}
                    value={impression}
                    disabled={isFinalized}
                    onChange={(e) => setImpression(e.target.value)}
                    className="w-full bg-purple-50/40 text-slate-950 p-3 rounded-xl border-2 border-purple-300 text-xs focus:outline-none focus:ring-2 focus:ring-purple-500 font-semibold leading-snug disabled:bg-slate-50 disabled:opacity-85"
                    placeholder="Definitive diagnostic conclusion / summary of abnormalities..."
                  />
                </div>

                {/* Recommendations */}
                <div>
                  <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1">
                    Recommendations / Clinical Correlation
                  </label>
                  <input
                    type="text"
                    value={recommendations}
                    disabled={isFinalized}
                    onChange={(e) => setRecommendations(e.target.value)}
                    className="w-full bg-white text-slate-900 px-3 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:bg-slate-50 disabled:opacity-85"
                    placeholder="Follow-up timing (e.g. repeat in 6 months), complementary imaging, or specialty referral..."
                  />
                </div>
              </div>

              {/* Bottom Actions Bar */}
              <div className="border-t border-slate-200 pt-4 flex flex-wrap items-center justify-between gap-3">
                {isFinalized ? (
                  <div className="flex flex-wrap items-center justify-between w-full gap-3">
                    <div className="flex items-center space-x-2 text-emerald-800 font-semibold text-xs">
                      <Lock className="w-4 h-4 text-emerald-600" />
                      <span>
                        Finalized & Electronically Signed by {apt.report?.signedBy || 'Consultant Radiologist'} ({apt.report?.signedAt})
                      </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                      <button
                        onClick={() => setAddendumModalOpen(true)}
                        className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-purple-50 hover:bg-purple-100 text-purple-800 text-xs font-bold border border-purple-300 cursor-pointer shadow-xs"
                      >
                        <Plus className="w-3.5 h-3.5" />
                        <span>Add Signed Addendum</span>
                      </button>

                      <button
                        onClick={() => onReleaseReport(apt.id, 'portal')}
                        className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold border border-slate-300 cursor-pointer shadow-xs"
                      >
                        <Send className="w-3.5 h-3.5 text-cyan-600" />
                        <span>Publish to Portal</span>
                      </button>

                      <button
                        onClick={() => onReleaseReport(apt.id, 'email')}
                        className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 text-xs font-semibold border border-slate-300 cursor-pointer shadow-xs"
                      >
                        <Send className="w-3.5 h-3.5 text-purple-600" />
                        <span>Email to Doctor</span>
                      </button>

                      {apt.referrer?.phone && (
                        <a
                          href={`https://wa.me/${apt.referrer.phone.replace(/[^0-9]/g, '')}?text=${encodeURIComponent(`Dr. ${apt.referrer.name}, Radiology Report for patient ${apt.patient.name} (${apt.service?.name || 'Radiology Study'}, Token: ${apt.tokenNumber}) has been finalized and verified by Amad Diagnostic Centre. Review online: https://portal.amaddiagnosticcentre.com.pk/report/${apt.patient.mrn}`)}`}
                          target="_blank"
                          rel="noreferrer"
                          onClick={() => onReleaseReport(apt.id, 'portal')}
                          className="flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-emerald-50 hover:bg-emerald-100 text-emerald-800 text-xs font-bold border border-emerald-300 cursor-pointer shadow-xs"
                        >
                          <MessageSquare className="w-3.5 h-3.5 text-emerald-600" />
                          <span>WhatsApp to Doctor</span>
                        </a>
                      )}

                      <button
                        onClick={() => generateRadiologyReportPdf(apt, apt.report!)}
                        className="flex items-center space-x-1.5 px-3.5 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold shadow-md shadow-cyan-600/20 cursor-pointer"
                      >
                        <Download className="w-3.5 h-3.5" />
                        <span>Download PDF</span>
                      </button>
                    </div>
                  </div>
                ) : (
                  <div className="flex items-center justify-end w-full space-x-3">
                    <button
                      onClick={() => handleSave(false)}
                      className="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 transition-all cursor-pointer shadow-xs"
                    >
                      Save Draft
                    </button>
                    <button
                      onClick={() => handleSave(true)}
                      className="flex items-center space-x-2 px-5 py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md shadow-purple-600/30 transition-all cursor-pointer"
                    >
                      <CheckCircle2 className="w-4 h-4" />
                      <span>Sign & Finalize Diagnostic Report</span>
                    </button>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* ========================================================================= */}
      {/* PACS DIAGNOSTIC VIEWER MODAL */}
      {/* ========================================================================= */}
      {pacsModalOpen && apt && (
        <RadiologistPacsModal
          appointment={apt}
          onClose={() => setPacsModalOpen(false)}
          onCaptureKeyImage={(slice) => {
            showToast(`Key Image at slice #${slice} bookmarked to report.`);
          }}
        />
      )}

      {/* ========================================================================= */}
      {/* CRITICAL FINDING ESCALATION MODAL */}
      {/* ========================================================================= */}
      {criticalModalOpen && apt && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center">
                  <Flame className="w-4 h-4 text-rose-600" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Critical Finding Escalation Log</h3>
                  <p className="text-[11px] text-slate-500">Medicolegal Audit Communication Record</p>
                </div>
              </div>
              <button onClick={() => setCriticalModalOpen(false)} className="p-1 rounded-lg text-slate-400 hover:text-slate-700">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Physician Contacted</label>
                <input
                  type="text"
                  value={criticalDoctorName}
                  onChange={(e) => setCriticalDoctorName(e.target.value)}
                  placeholder="Doctor Name"
                  className="w-full bg-white text-slate-900 p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Contact Phone / Pager</label>
                <input
                  type="text"
                  value={criticalPhone}
                  onChange={(e) => setCriticalPhone(e.target.value)}
                  placeholder="+92 300 1234567"
                  className="w-full bg-white text-slate-900 p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
                />
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Immediate Clinical Advice Given</label>
                <textarea
                  rows={2}
                  value={criticalAdvice}
                  onChange={(e) => setCriticalAdvice(e.target.value)}
                  placeholder="Urgent surgical consult, admission, CT angiogram..."
                  className="w-full bg-white text-slate-900 p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
                />
              </div>

              <div className="flex items-center space-x-2 p-2 bg-slate-50 rounded-xl border border-slate-200">
                <input
                  type="checkbox"
                  id="readback"
                  checked={criticalReadback}
                  onChange={(e) => setCriticalReadback(e.target.checked)}
                  className="accent-rose-600 w-4 h-4 cursor-pointer"
                />
                <label htmlFor="readback" className="text-[11px] text-slate-700 font-semibold cursor-pointer">
                  Direct Verbal Read-Back Verified by Receiving Clinician
                </label>
              </div>
            </div>

            <div className="flex space-x-2 pt-2 border-t border-slate-200">
              <button
                onClick={() => setCriticalModalOpen(false)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300"
              >
                Cancel
              </button>
              <button
                onClick={() => {
                  const log = `Critical findings communicated telephonically to ${criticalDoctorName} (${criticalPhone}) at ${new Date().toLocaleTimeString()} on ${new Date().toLocaleDateString()}. Read-back verified: ${criticalReadback ? 'YES' : 'NO'}. Recommended: ${criticalAdvice}`;
                  setCriticalEscalatedLog(log);
                  setCriticalFlag(true);
                  setCriticalModalOpen(false);
                  showToast('Critical telephone notification recorded in report.');
                }}
                className="flex-1 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md shadow-rose-600/30"
              >
                Confirm Escalation Log
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* STRUCTURED REJECTION TO TECH MODAL */}
      {/* ========================================================================= */}
      {rejectModalOpen && apt && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-xl bg-rose-50 text-rose-700 border border-rose-200 flex items-center justify-center">
                  <RotateCcw className="w-4 h-4 text-rose-600" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Reject Study to Technologist</h3>
                  <p className="text-[11px] text-slate-500">
                    #{apt.tokenNumber} • {apt.patient.name} ({apt.service.name})
                  </p>
                </div>
              </div>
              <button onClick={() => setRejectModalOpen(false)} className="p-1 rounded-lg text-slate-400 hover:text-slate-700">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">QA Deficiency Category</label>
                <select
                  value={rejectCategory}
                  onChange={(e) => setRejectCategory(e.target.value)}
                  className="w-full bg-white text-slate-800 p-2 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500 cursor-pointer"
                >
                  <option value="Patient Motion Artifact">Patient Motion Artifact / Image Blurring</option>
                  <option value="Inadequate Anatomical Coverage">Inadequate Anatomical Coverage / Missing Views</option>
                  <option value="Contrast Sub-optimal Timing">Sub-optimal IV Contrast Timing / Washout Phase Missed</option>
                  <option value="Incorrect Patient Positioning">Incorrect Patient Positioning / Centering Off</option>
                  <option value="Metal / Foreign Body Artifact">Metal / Foreign Body Artifact (Immobilization required)</option>
                  <option value="Sub-optimal Exposure / Penetration">Sub-optimal Exposure / Penetration</option>
                </select>
              </div>

              <div>
                <label className="block font-semibold text-slate-700 mb-1">Technologist Re-Scan Instructions</label>
                <textarea
                  rows={3}
                  value={rejectNotes}
                  onChange={(e) => setRejectNotes(e.target.value)}
                  placeholder="e.g. Please repeat T2 axial sequence with head strap immobilization. Ensure coverage down to L5-S1 junction."
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-rose-500"
                />
              </div>
            </div>

            <div className="flex space-x-2 pt-2 border-t border-slate-200">
              <button
                onClick={() => setRejectModalOpen(false)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmReject}
                className="flex-1 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs shadow-md shadow-rose-600/30"
              >
                Send Rejection to Tech
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* ADD SIGNED ADDENDUM MODAL */}
      {/* ========================================================================= */}
      {addendumModalOpen && apt && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center">
                  <Plus className="w-4 h-4 text-purple-600" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Add Official Report Addendum</h3>
                  <p className="text-[11px] text-slate-500">Version {(apt.report?.version || 1) + 1} Amendment</p>
                </div>
              </div>
              <button onClick={() => setAddendumModalOpen(false)} className="p-1 rounded-lg text-slate-400 hover:text-slate-700">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-2 text-xs">
              <label className="block font-semibold text-slate-700">
                Addendum Diagnostic Remarks / Clinical Correlation:
              </label>
              <textarea
                rows={4}
                value={addendumText}
                onChange={(e) => setAddendumText(e.target.value)}
                placeholder="e.g. Supplementary review following receipt of prior outside MRI. Previously noted nodule is stable and unchanged since 2024."
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>

            <div className="flex space-x-2 pt-2 border-t border-slate-200">
              <button
                onClick={() => setAddendumModalOpen(false)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveAddendum}
                className="flex-1 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md shadow-purple-600/30"
              >
                Sign & Append Addendum
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* SAVE CUSTOM TEMPLATE MODAL */}
      {/* ========================================================================= */}
      {saveTemplateModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-5 shadow-2xl space-y-4">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center">
                  <BookOpen className="w-4 h-4 text-purple-600" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-sm">Save Current as Template</h3>
                  <p className="text-[11px] text-slate-500">Reusable structured reporting template</p>
                </div>
              </div>
              <button onClick={() => setSaveTemplateModalOpen(false)} className="p-1 rounded-lg text-slate-400 hover:text-slate-700">
                <X className="w-4 h-4" />
              </button>
            </div>

            <div className="space-y-2 text-xs">
              <label className="block font-semibold text-slate-700">Template Name</label>
              <input
                type="text"
                value={newTemplateName}
                onChange={(e) => setNewTemplateName(e.target.value)}
                placeholder="e.g. MRI Brain Stroke Protocol"
                className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>

            <div className="flex space-x-2 pt-2 border-t border-slate-200">
              <button
                onClick={() => setSaveTemplateModalOpen(false)}
                className="flex-1 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300"
              >
                Cancel
              </button>
              <button
                onClick={handleSaveCustomTemplate}
                className="flex-1 py-2 rounded-xl bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs shadow-md shadow-purple-600/30"
              >
                Save Template
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ========================================================================= */}
      {/* LIVE PDF PREVIEW MODAL */}
      {/* ========================================================================= */}
      {pdfPreviewModalOpen && apt && (
        <div className="fixed inset-0 z-50 bg-slate-950/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-6">
          <div className="bg-slate-100 border border-slate-300 rounded-3xl max-w-3xl w-full max-h-[90vh] shadow-2xl flex flex-col overflow-hidden text-slate-900">
            {/* Top Bar */}
            <div className="bg-white px-5 py-3 border-b border-slate-200 flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <FileText className="w-5 h-5 text-cyan-600" />
                <span className="font-bold text-sm text-slate-900">Official Diagnostic Report Preview</span>
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => {
                    const tempRep: RadiologyReport = {
                      id: apt.report?.id || `rep-${apt.id}`,
                      appointmentId: apt.id,
                      version: apt.report?.version || 1,
                      type: 'final',
                      clinicalHistory,
                      technique,
                      comparison,
                      findings,
                      impression,
                      recommendations,
                      criticalFlag,
                      authoredBy: 'Dr. M. Raza, MBBS, FCPS',
                      signedBy: 'Dr. M. Raza, MBBS, FCPS (Consultant Radiologist)',
                      signedAt: new Date().toLocaleDateString(),
                      releases: [],
                    };
                    generateRadiologyReportPdf(apt, tempRep);
                  }}
                  className="flex items-center space-x-1 px-3 py-1.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs cursor-pointer shadow-xs"
                >
                  <Download className="w-3.5 h-3.5" />
                  <span>Download PDF</span>
                </button>
                <button onClick={() => setPdfPreviewModalOpen(false)} className="p-1.5 text-slate-400 hover:text-slate-700">
                  <X className="w-5 h-5" />
                </button>
              </div>
            </div>

            {/* Document Body (Simulated A4 Paper) */}
            <div className="flex-1 overflow-y-auto p-4 sm:p-8 flex justify-center">
              <div className="bg-white rounded-xl border border-slate-300 p-8 max-w-2xl w-full shadow-md text-xs font-sans space-y-4">
                {/* Header Letterhead */}
                <div className="bg-slate-900 text-white p-4 rounded-lg flex justify-between items-center">
                  <div>
                    <div className="font-black text-sm tracking-wide">AMAD DIAGNOSTIC CENTRE (ADC)</div>
                    <div className="text-[10px] text-slate-300">Radiology & Advanced Imaging Information System | ISO 9001:2015</div>
                    <div className="text-[9px] text-slate-400">Plot 14-B, Executive Sector, Islamabad, Pakistan • +92 51 2223344</div>
                  </div>
                  <div className="text-right font-mono text-[10px]">
                    <div>ACC: ACC-{apt.id.slice(-6).toUpperCase()}</div>
                    <div className="text-cyan-400">TOKEN: #{apt.tokenNumber}</div>
                  </div>
                </div>

                {/* Patient Demographics Table */}
                <div className="bg-slate-50 p-3 rounded-lg border border-slate-200 grid grid-cols-2 gap-2 text-[11px]">
                  <div>
                    <div><strong>Patient:</strong> {apt.patient.name}</div>
                    <div><strong>MRN:</strong> {apt.patient.mrn}</div>
                    <div><strong>Age/Gender:</strong> {apt.patient.age}y / {apt.patient.gender.toUpperCase()}</div>
                  </div>
                  <div>
                    <div><strong>Ref. Doctor:</strong> {apt.referrer?.name || 'Self / Walk-In'}</div>
                    <div><strong>Date:</strong> {apt.date}</div>
                    <div><strong>Modality:</strong> {apt.modality.name} ({apt.modality.code})</div>
                  </div>
                </div>

                <div className="border-b-2 border-cyan-600 pb-1">
                  <h3 className="font-black text-sm text-slate-900 uppercase">EXAM: {apt.service.name}</h3>
                </div>

                {/* Report Sections */}
                <div className="space-y-3 text-[11px] leading-relaxed">
                  <div>
                    <div className="font-bold text-slate-800 uppercase text-[10px]">Clinical Indication:</div>
                    <div className="text-slate-700">{clinicalHistory || 'Routine evaluation.'}</div>
                  </div>

                  <div>
                    <div className="font-bold text-slate-800 uppercase text-[10px]">Technique & Protocol:</div>
                    <div className="text-slate-700">{technique}</div>
                  </div>

                  <div>
                    <div className="font-bold text-slate-800 uppercase text-[10px]">Comparison:</div>
                    <div className="text-slate-700">{comparison}</div>
                  </div>

                  <div>
                    <div className="font-bold text-slate-800 uppercase text-[10px]">Detailed Findings:</div>
                    <div className="text-slate-800 whitespace-pre-line font-mono text-[10.5px] bg-slate-50 p-2.5 rounded border border-slate-200">
                      {findings || 'No abnormalities documented.'}
                    </div>
                  </div>

                  <div className="bg-purple-50 p-3 rounded-lg border border-purple-200">
                    <div className="font-black text-purple-950 uppercase text-[10px]">Impression & Conclusion:</div>
                    <div className="font-bold text-slate-950 mt-0.5">{impression || 'Pending interpretation.'}</div>
                  </div>

                  {recommendations && (
                    <div>
                      <div className="font-bold text-slate-800 uppercase text-[10px]">Recommendations:</div>
                      <div className="text-slate-700">{recommendations}</div>
                    </div>
                  )}
                </div>

                {/* Signature Block */}
                <div className="pt-4 border-t border-slate-200 flex justify-between items-end text-[10px]">
                  <div>
                    <div className="font-mono text-slate-400">Electronic Verification Hash: 9F8A-7C2B-44E1</div>
                    <div className="text-slate-400">Authenticated via ADC Secure PACS Gateway</div>
                  </div>
                  <div className="text-right">
                    <div className="font-bold text-slate-900">Dr. M. Raza, MBBS, FCPS</div>
                    <div className="text-slate-500">Consultant Radiologist</div>
                    <div className="text-purple-700 font-bold">Electronically Signed</div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// =============================================================================
// SUB-COMPONENT: RADIOLOGIST PACS DIAGNOSTIC CONSOLE MODAL
// =============================================================================
interface PacsModalProps {
  appointment: Appointment;
  onClose: () => void;
  onCaptureKeyImage: (slice: number) => void;
}

const RadiologistPacsModal: React.FC<PacsModalProps> = ({
  appointment,
  onClose,
  onCaptureKeyImage,
}) => {
  const [sliceIndex, setSliceIndex] = useState(1);
  const [windowPreset, setWindowPreset] = useState<'soft_tissue' | 'bone' | 'lung' | 'brain' | 'invert'>('soft_tissue');
  const [zoomLevel, setZoomLevel] = useState(100);
  const [isPlayingCine, setIsPlayingCine] = useState(false);
  const maxSlices = appointment.doseLog?.sliceCount || (appointment.modality.code === 'CT' ? 32 : appointment.modality.code === 'MR' ? 24 : 4);

  // Cine loop
  useEffect(() => {
    let interval: any = null;
    if (isPlayingCine && maxSlices > 1) {
      interval = setInterval(() => {
        setSliceIndex((prev) => (prev >= maxSlices ? 1 : prev + 1));
      }, 140);
    }
    return () => {
      if (interval) clearInterval(interval);
    };
  }, [isPlayingCine, maxSlices]);

  return (
    <div className="fixed inset-0 z-50 bg-slate-950/85 backdrop-blur-sm flex items-center justify-center p-3 sm:p-6">
      <div className="bg-slate-900 border border-slate-800 rounded-3xl max-w-5xl w-full h-[92vh] shadow-2xl flex flex-col overflow-hidden text-slate-100">
        {/* Top Header */}
        <div className="bg-slate-950 px-5 py-3 border-b border-slate-800 flex items-center justify-between">
          <div className="flex items-center space-x-3">
            <div className="w-8 h-8 rounded-lg bg-purple-950 text-purple-400 border border-purple-800 flex items-center justify-center">
              <Eye className="w-4 h-4" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <span className="font-mono font-bold text-purple-400 text-xs">AMAD PACS-DIAGNOSTIC v4.2</span>
                <span className="text-[10px] bg-slate-800 text-slate-300 px-1.5 py-0.2 rounded font-mono">
                  {appointment.modality.code} • #{appointment.tokenNumber}
                </span>
              </div>
              <h2 className="text-sm font-bold text-slate-100">
                {appointment.patient.name} <span className="text-slate-400 font-normal">({appointment.patient.mrn})</span>
              </h2>
            </div>
          </div>

          <div className="flex items-center space-x-2">
            <button
              onClick={() => onCaptureKeyImage(sliceIndex)}
              className="px-2.5 py-1 rounded-lg bg-cyan-900 hover:bg-cyan-800 text-cyan-300 text-xs font-bold border border-cyan-700 cursor-pointer"
            >
              Bookmark Key Slice #{sliceIndex}
            </button>
            <button onClick={onClose} className="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 cursor-pointer">
              <X className="w-5 h-5" />
            </button>
          </div>
        </div>

        {/* Viewer Main Body */}
        <div className="flex-1 flex flex-col md:flex-row overflow-hidden">
          {/* Central Image Canvas */}
          <div className="flex-1 bg-black flex flex-col items-center justify-center relative p-4 select-none overflow-hidden">
            {/* Overlay Top Left */}
            <div className="absolute top-4 left-4 text-[11px] font-mono text-cyan-400/90 leading-tight pointer-events-none space-y-0.5">
              <div>{appointment.patient.name.toUpperCase()}</div>
              <div>MRN: {appointment.patient.mrn}</div>
              <div>AGE: {appointment.patient.age}y ({appointment.patient.gender[0].toUpperCase()})</div>
              <div>STUDY: {appointment.service.name}</div>
            </div>

            {/* Overlay Top Right */}
            <div className="absolute top-4 right-4 text-[11px] font-mono text-cyan-400/90 leading-tight text-right pointer-events-none space-y-0.5">
              <div>AMAD DIAGNOSTIC CENTRE</div>
              <div>MODALITY: {appointment.modality.code}</div>
              <div>ROOM: {appointment.roomNumber}</div>
              <div>KVp: {appointment.doseLog?.kvp || 120} | mA: {appointment.doseLog?.mas || 200}</div>
            </div>

            {/* Visualizer */}
            <div
              className="transition-transform duration-100 flex items-center justify-center"
              style={{ transform: `scale(${zoomLevel / 100})` }}
            >
              <RadiologicalVisualizer
                modality={appointment.modality.code}
                sliceIndex={sliceIndex}
                maxSlices={maxSlices}
                windowPreset={windowPreset}
              />
            </div>

            {/* Overlay Bottom Left */}
            <div className="absolute bottom-4 left-4 text-[11px] font-mono text-cyan-400/90 leading-tight pointer-events-none space-y-0.5">
              <div>SLICE: {sliceIndex} / {maxSlices}</div>
              <div>WINDOW: {windowPreset.toUpperCase()}</div>
              <div>ZOOM: {zoomLevel}%</div>
            </div>

            {/* Overlay Bottom Right */}
            <div className="absolute bottom-4 right-4 text-[11px] font-mono text-cyan-400/90 leading-tight text-right pointer-events-none space-y-0.5">
              <div>DOSE: {appointment.doseLog?.doseValue || 'N/A'} {appointment.doseLog?.doseUnit || ''}</div>
              <div>CONTRAST: {appointment.doseLog?.contrastAgent || 'NONE'}</div>
            </div>
          </div>

          {/* Right Control Toolbar */}
          <div className="w-full md:w-80 bg-slate-900 border-t md:border-t-0 md:border-l border-slate-800 p-4 flex flex-col justify-between overflow-y-auto space-y-4 text-xs">
            <div className="space-y-4">
              {/* Window Presets */}
              <div className="space-y-1.5">
                <label className="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                  <Sliders className="w-3.5 h-3.5 text-cyan-400" /> Window / Level Presets
                </label>
                <div className="grid grid-cols-2 gap-1.5">
                  {[
                    { id: 'soft_tissue', label: 'Soft Tissue' },
                    { id: 'bone', label: 'Bone Window' },
                    { id: 'lung', label: 'Lung Window' },
                    { id: 'brain', label: 'Brain / Neuro' },
                    { id: 'invert', label: 'Invert Grayscale' },
                  ].map((preset) => (
                    <button
                      key={preset.id}
                      onClick={() => setWindowPreset(preset.id as any)}
                      className={`px-2.5 py-1.5 rounded-lg text-xs font-semibold transition-colors cursor-pointer text-left ${
                        windowPreset === preset.id
                          ? 'bg-cyan-500 text-slate-950 font-bold'
                          : 'bg-slate-800 text-slate-300 hover:bg-slate-700'
                      }`}
                    >
                      {preset.label}
                    </button>
                  ))}
                </div>
              </div>

              {/* Slice Scrubbing & Cine Loop */}
              <div className="space-y-2 bg-slate-950 p-3 rounded-xl border border-slate-800">
                <div className="flex items-center justify-between">
                  <span className="text-[11px] font-bold text-slate-300">Slice Scrubbing</span>
                  <span className="font-mono text-cyan-400 font-bold">{sliceIndex} / {maxSlices}</span>
                </div>
                <input
                  type="range"
                  min="1"
                  max={maxSlices}
                  value={sliceIndex}
                  onChange={(e) => setSliceIndex(Number(e.target.value))}
                  className="w-full accent-cyan-400 cursor-pointer"
                />
                <div className="flex items-center justify-between pt-1">
                  <button
                    onClick={() => setIsPlayingCine(!isPlayingCine)}
                    className={`px-3 py-1 rounded-lg text-xs font-bold transition-colors cursor-pointer flex items-center gap-1.5 ${
                      isPlayingCine ? 'bg-amber-500 text-slate-950' : 'bg-slate-800 text-slate-200 hover:bg-slate-700'
                    }`}
                  >
                    <Play className="w-3 h-3 fill-current" />
                    <span>{isPlayingCine ? 'Pause Cine' : 'Play Cine Loop'}</span>
                  </button>

                  <button
                    onClick={() => {
                      setSliceIndex(1);
                      setZoomLevel(100);
                      setWindowPreset('soft_tissue');
                    }}
                    className="p-1 text-slate-400 hover:text-white"
                    title="Reset View"
                  >
                    <RefreshCw className="w-3.5 h-3.5" />
                  </button>
                </div>
              </div>

              {/* Zoom Controls */}
              <div className="flex items-center justify-between bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                <span className="text-[11px] font-bold text-slate-300">Zoom: {zoomLevel}%</span>
                <div className="flex items-center space-x-1.5">
                  <button
                    onClick={() => setZoomLevel(Math.max(50, zoomLevel - 20))}
                    className="p-1 bg-slate-800 hover:bg-slate-700 rounded text-slate-200 cursor-pointer"
                  >
                    <ZoomOut className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => setZoomLevel(Math.min(250, zoomLevel + 20))}
                    className="p-1 bg-slate-800 hover:bg-slate-700 rounded text-slate-200 cursor-pointer"
                  >
                    <ZoomIn className="w-3.5 h-3.5" />
                  </button>
                  <button
                    onClick={() => setZoomLevel(100)}
                    className="px-1.5 py-0.5 bg-slate-800 hover:bg-slate-700 rounded text-[10px] text-slate-300 font-mono cursor-pointer"
                  >
                    1:1
                  </button>
                </div>
              </div>

              {/* DICOM Header Tags */}
              <div className="space-y-1 bg-slate-950/60 p-3 rounded-xl border border-slate-800 font-mono text-[10px] text-slate-400">
                <div className="text-slate-300 font-sans font-bold text-[11px] mb-1">DICOM Header Tags</div>
                <div>(0008,0060) Modality: {appointment.modality.code}</div>
                <div>(0018,0050) Slice Thickness: 1.0 mm</div>
                <div>(0018,0060) kVp: {appointment.doseLog?.kvp || 120}</div>
                <div>(0028,0010) Rows / Columns: 512 x 512</div>
                <div>(0020,0011) Series Number: 1</div>
              </div>
            </div>

            <button
              onClick={onClose}
              className="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs border border-slate-700 cursor-pointer"
            >
              Return to Reporting Editor
            </button>
          </div>
        </div>
      </div>
    </div>
  );
};
