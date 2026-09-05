import React, { useState } from 'react';
import {
  Radiation,
  CheckCircle2,
  X,
  Droplet,
  FileCheck,
  Layers,
  Sparkles,
  ShieldCheck,
  AlertTriangle,
  Boxes,
  Activity
} from 'lucide-react';
import { Appointment, DoseLog, InventoryItem } from '../types';

interface DoseCaptureModalProps {
  appointment: Appointment;
  inventoryItems?: InventoryItem[];
  onCompleteAcquisition: (aptId: string, doseLog: DoseLog) => void;
  onClose: () => void;
}

export const DoseCaptureModal: React.FC<DoseCaptureModalProps> = ({
  appointment,
  inventoryItems = [],
  onCompleteAcquisition,
  onClose,
}) => {
  const isCt = appointment.modality.code === 'CT';
  const isMri = appointment.modality.code === 'MR';
  const isXray = appointment.modality.code === 'DX';
  const isMammo = appointment.modality.code === 'MG';
  const isUltrasound = appointment.modality.code === 'US';

  const defaultUnit = isCt
    ? 'mGy (CTDIvol)'
    : isXray
    ? 'dGy*cm² (DAP)'
    : isMammo
    ? 'mGy (AGD)'
    : isMri
    ? 'W/kg (SAR Peak)'
    : 'MI / TIs';

  const [doseValue, setDoseValue] = useState<number>(
    isCt ? 7.8 : isXray ? 0.14 : isMammo ? 1.45 : isMri ? 1.8 : 0.4
  );
  const [doseUnit, setDoseUnit] = useState<string>(defaultUnit);
  const [dlpValue, setDlpValue] = useState<number>(isCt ? 345 : 0);
  const [kvp, setKvp] = useState<number>(isCt ? 120 : isXray ? 85 : isMammo ? 28 : 0);
  const [mas, setMas] = useState<number>(isCt ? 220 : isXray ? 12 : isMammo ? 95 : 0);
  const [seriesCount, setSeriesCount] = useState<number>(isCt ? 4 : isMri ? 5 : isMammo ? 4 : isUltrasound ? 2 : 2);
  const [sliceCount, setSliceCount] = useState<number>(isCt ? 280 : isMri ? 120 : isMammo ? 4 : isUltrasound ? 32 : 2);
  
  // Available contrast media items from live inventory
  const contrastInventory = inventoryItems.filter(
    i => i.category === 'contrast_ct' || i.category === 'contrast_mri'
  );

  const defaultAgent = appointment.service.requiresContrast
    ? (isMri
        ? contrastInventory.find(i => i.category === 'contrast_mri')?.name || 'Gadobutrol (Gadovist)'
        : contrastInventory.find(i => i.category === 'contrast_ct')?.name || 'Iohexol (Omnipaque 350)')
    : 'None';

  const [contrastAgent, setContrastAgent] = useState<string>(defaultAgent);
  const [contrastVolumeMl, setContrastVolumeMl] = useState<number>(appointment.service.requiresContrast ? (isMri ? 15 : 75) : 0);
  const [contrastFlowRate, setContrastFlowRate] = useState<string>(appointment.service.requiresContrast ? '3.5 mL/s' : 'N/A');
  const [cannulaSite, setCannulaSite] = useState<string>(appointment.service.requiresContrast ? 'Right Antecubital (20G)' : 'None');
  const [salineFlushMl, setSalineFlushMl] = useState<number>(appointment.service.requiresContrast ? 30 : 0);
  
  // Selected inventory item object
  const selectedInventoryItem = contrastInventory.find(
    i => i.name.toLowerCase() === contrastAgent.toLowerCase() || contrastAgent.toLowerCase().includes(i.name.toLowerCase())
  );
  
  const [techniqueNotes, setTechniqueNotes] = useState(
    isCt
      ? 'Standard volumetric helical scan with 1.0mm axial reconstructions and coronal/sagittal reformats. Patient held breath well.'
      : isMri
      ? 'T1, T2, and STIR multi-planar sequences acquired with dedicated spine matrix coil. No motion artifacts.'
      : isXray
      ? 'PA and Lateral projections acquired in full inspiratory effort. Optimal contrast resolution.'
      : isMammo
      ? 'Standard CC and MLO views of bilateral breasts acquired under standard automatic exposure control.'
      : 'Target organ scanned in real-time B-mode and color Doppler. Representative static clips and images stored.'
  );
  const [qcPassed, setQcPassed] = useState<boolean>(true);
  const [techName, setTechName] = useState('Amad Certified Technologist');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const doseLog: DoseLog = {
      appointmentId: appointment.id,
      doseValue,
      doseUnit,
      dlpValue: isCt ? dlpValue : undefined,
      kvp: kvp > 0 ? kvp : undefined,
      mas: mas > 0 ? mas : undefined,
      seriesCount,
      sliceCount,
      contrastAgent: contrastAgent === 'None' ? undefined : contrastAgent,
      contrastVolumeMl: contrastAgent === 'None' ? 0 : contrastVolumeMl,
      contrastFlowRate: contrastAgent === 'None' ? undefined : contrastFlowRate,
      cannulaSite: contrastAgent === 'None' ? undefined : cannulaSite,
      salineFlushMl: contrastAgent === 'None' ? undefined : salineFlushMl,
      techniqueNotes: techniqueNotes.trim() || undefined,
      qcPassed,
      recordedAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      recordedBy: techName,
    };

    onCompleteAcquisition(appointment.id, doseLog);
    onClose();
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white border border-slate-200 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4 max-h-[90vh] flex flex-col">
        {/* Modal Header */}
        <div className="flex items-center justify-between border-b border-slate-200 pb-3">
          <div className="flex items-center space-x-3">
            <div className="w-10 h-10 rounded-xl bg-purple-50 text-purple-700 border border-purple-200 flex items-center justify-center shadow-xs">
              <Radiation className="w-5 h-5 text-purple-600" />
            </div>
            <div>
              <h2 className="font-bold text-slate-900 text-base">Complete Scan & Log Acquisition Dose</h2>
              <p className="text-xs text-slate-500">
                Study #{appointment.tokenNumber} • <span className="text-slate-800 font-semibold">{appointment.patient.name}</span> ({appointment.patient.mrn})
              </p>
            </div>
          </div>
          <button onClick={onClose} className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4 overflow-y-auto pr-1 flex-1">
          {/* Study Summary Banner */}
          <div className="bg-slate-50 p-3 rounded-xl border border-slate-200 flex items-center justify-between text-xs text-slate-700">
            <div>
              <div className="font-semibold text-slate-900">{appointment.service.name}</div>
              <div className="text-slate-500 mt-0.5 font-mono">{appointment.roomNumber}</div>
            </div>
            <span
              className="px-2 py-0.5 rounded text-[10px] font-bold text-white font-mono shadow-xs"
              style={{ backgroundColor: appointment.modality.color }}
            >
              {appointment.modality.code}
            </span>
          </div>

          {/* Radiation Dose & Exposure Parameters */}
          <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3">
            <div className="flex items-center justify-between">
              <label className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <Radiation className="w-3.5 h-3.5 text-amber-600" />
                {!isMri && !isUltrasound ? 'Radiation Dose & Exposure Index' : 'Acoustic / Magnetic Safety Index'}
              </label>
              <span className="text-[10px] text-slate-500 font-medium">ALARA Compliant</span>
            </div>

            <div className="grid grid-cols-2 gap-2.5">
              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">
                  {isCt ? 'CTDIvol Index' : isXray ? 'DAP Value' : isMammo ? 'Avg Glandular Dose (AGD)' : 'Peak Metric'}
                </span>
                <input
                  type="number"
                  step="0.01"
                  value={doseValue}
                  onChange={(e) => setDoseValue(Number(e.target.value))}
                  className="w-full bg-white text-slate-900 font-mono font-bold p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
                />
              </div>
              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Standard Units</span>
                <input
                  type="text"
                  value={doseUnit}
                  onChange={(e) => setDoseUnit(e.target.value)}
                  className="w-full bg-white text-slate-800 p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 font-mono"
                />
              </div>
            </div>

            {isCt && (
              <div className="grid grid-cols-3 gap-2 pt-1 border-t border-slate-200">
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">DLP (mGy*cm)</span>
                  <input
                    type="number"
                    value={dlpValue}
                    onChange={(e) => setDlpValue(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 font-mono p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
                  />
                </div>
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">Tube Voltage (kVp)</span>
                  <input
                    type="number"
                    value={kvp}
                    onChange={(e) => setKvp(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 font-mono p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
                  />
                </div>
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">Current (mA / mAs)</span>
                  <input
                    type="number"
                    value={mas}
                    onChange={(e) => setMas(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 font-mono p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
                  />
                </div>
              </div>
            )}

            {!isCt && (isXray || isMammo) && (
              <div className="grid grid-cols-2 gap-2 pt-1 border-t border-slate-200">
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">Tube Voltage (kVp)</span>
                  <input
                    type="number"
                    value={kvp}
                    onChange={(e) => setKvp(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 font-mono p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
                  />
                </div>
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">Exposure (mAs)</span>
                  <input
                    type="number"
                    value={mas}
                    onChange={(e) => setMas(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 font-mono p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
                  />
                </div>
              </div>
            )}
          </div>

          {/* Series & Slices Count */}
          <div className="grid grid-cols-2 gap-2.5">
            <div>
              <span className="text-[11px] text-slate-600 font-medium block mb-1 flex items-center gap-1">
                <Layers className="w-3 h-3 text-slate-500" /> Number of Series
              </span>
              <input
                type="number"
                min="1"
                value={seriesCount}
                onChange={(e) => setSeriesCount(Number(e.target.value))}
                className="w-full bg-white text-slate-900 font-mono p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>
            <div>
              <span className="text-[11px] text-slate-600 font-medium block mb-1 flex items-center gap-1">
                <FileCheck className="w-3 h-3 text-slate-500" /> Total Slices / Images
              </span>
              <input
                type="number"
                min="1"
                value={sliceCount}
                onChange={(e) => setSliceCount(Number(e.target.value))}
                className="w-full bg-white text-slate-900 font-mono p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
              />
            </div>
          </div>

          {/* Contrast Administration */}
          <div className="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-2.5">
            <div className="flex items-center justify-between">
              <label className="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center gap-1.5">
                <Droplet className="w-3.5 h-3.5 text-cyan-600" />
                IV Contrast & Injector Log
              </label>
              <div className="flex items-center space-x-1.5">
                {selectedInventoryItem && (
                  <span className="text-[10px] px-2 py-0.5 rounded-full bg-cyan-100 text-cyan-800 font-bold flex items-center space-x-1">
                    <Boxes className="w-3 h-3" />
                    <span>Live Stock: {selectedInventoryItem.currentStock} {selectedInventoryItem.unit}</span>
                  </span>
                )}
                {appointment.service.requiresContrast && (
                  <span className="text-[10px] px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 font-bold">Contrast Required</span>
                )}
              </div>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Contrast Media Catalog Item</span>
                <select
                  value={contrastAgent}
                  onChange={(e) => {
                    const agent = e.target.value;
                    setContrastAgent(agent);
                    if (agent === 'None') {
                      setContrastVolumeMl(0);
                      setContrastFlowRate('N/A');
                      setCannulaSite('None');
                    } else if (contrastVolumeMl === 0) {
                      setContrastVolumeMl(isMri ? 15 : 75);
                      setContrastFlowRate('3.5 mL/s');
                      setCannulaSite('Right Antecubital (20G)');
                    }
                  }}
                  className="w-full bg-white text-slate-800 p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 cursor-pointer font-medium"
                >
                  <option value="None">None (Plain / Non-Contrast Study)</option>
                  {contrastInventory.length > 0 ? (
                    contrastInventory.map(item => (
                      <option key={item.id} value={item.name}>
                        {item.name} ({item.currentStock} in stock • Lot {item.batches[0]?.batchNumber || 'N/A'})
                      </option>
                    ))
                  ) : (
                    <>
                      <option value="Iohexol (Omnipaque 350)">Iohexol (Omnipaque 350)</option>
                      <option value="Iopromide (Ultravist 370)">Iopromide (Ultravist 370)</option>
                      <option value="Iodixanol (Visipaque 320)">Iodixanol (Visipaque 320 - Iso-osmolar)</option>
                      <option value="Gadobutrol (Gadovist)">Gadobutrol (Gadovist)</option>
                      <option value="Gadoterate (Dotarem)">Gadoterate (Dotarem)</option>
                    </>
                  )}
                </select>
              </div>

              <div>
                <span className="text-[11px] text-slate-600 font-medium block mb-1">Volume Administered (mL)</span>
                <input
                  type="number"
                  value={contrastVolumeMl}
                  disabled={contrastAgent === 'None'}
                  onChange={(e) => setContrastVolumeMl(Number(e.target.value))}
                  className="w-full bg-white text-slate-900 font-mono p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 disabled:bg-slate-100 disabled:opacity-50 font-bold"
                />
              </div>
            </div>

            {contrastAgent !== 'None' && selectedInventoryItem && (
              <div className="bg-white p-2.5 rounded-lg border border-slate-200 text-xs flex items-center justify-between">
                <div className="flex items-center space-x-2">
                  <span className="text-[10px] font-bold text-slate-400 uppercase">Active Consignment Lot:</span>
                  <span className="font-mono font-bold text-slate-800 text-[11px]">
                    {selectedInventoryItem.batches[0]?.batchNumber || 'BATCH-AUTO'}
                  </span>
                  <span className="text-[10px] text-slate-500">
                    (Exp: {selectedInventoryItem.batches[0]?.expiryDate || '2026-12-31'})
                  </span>
                </div>
                <span className="text-[10px] text-cyan-800 font-semibold">
                  Auto-deducts 1 {selectedInventoryItem.unit} on completion
                </span>
              </div>
            )}

            {contrastAgent !== 'None' && (
              <div className="grid grid-cols-3 gap-2 pt-1 border-t border-slate-200">
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">Injection Flow Rate</span>
                  <input
                    type="text"
                    value={contrastFlowRate}
                    onChange={(e) => setContrastFlowRate(e.target.value)}
                    className="w-full bg-white text-slate-900 p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 font-mono"
                  />
                </div>
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">Access Site & Cannula</span>
                  <input
                    type="text"
                    value={cannulaSite}
                    onChange={(e) => setCannulaSite(e.target.value)}
                    className="w-full bg-white text-slate-900 p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500"
                  />
                </div>
                <div>
                  <span className="text-[10px] text-slate-600 font-medium block mb-0.5">Saline Flush (mL)</span>
                  <input
                    type="number"
                    value={salineFlushMl}
                    onChange={(e) => setSalineFlushMl(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 font-mono p-1.5 rounded-md border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 font-bold"
                  />
                </div>
              </div>
            )}
          </div>

          {/* Technique Notes */}
          <div>
            <label className="block text-xs font-semibold text-slate-700 mb-1">
              Acquisition Technique & Quality Assurance Remarks
            </label>
            <textarea
              rows={2}
              value={techniqueNotes}
              onChange={(e) => setTechniqueNotes(e.target.value)}
              placeholder="e.g. Good breath-hold, no patient motion artifacts, coronal reformats generated."
              className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 resize-none"
            />
          </div>

          {/* QC Status & Technologist Sign-off */}
          <div className="grid grid-cols-2 gap-2.5 pt-1">
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Technologist Signature
              </label>
              <input
                type="text"
                value={techName}
                onChange={(e) => setTechName(e.target.value)}
                className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-purple-500 font-medium"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">
                Image Quality Assurance
              </label>
              <label className="flex items-center space-x-2 p-2 bg-emerald-50 rounded-lg border border-emerald-200 cursor-pointer text-xs font-semibold text-emerald-800">
                <input
                  type="checkbox"
                  checked={qcPassed}
                  onChange={(e) => setQcPassed(e.target.checked)}
                  className="rounded text-emerald-600 focus:ring-emerald-500 w-4 h-4"
                />
                <ShieldCheck className="w-4 h-4 text-emerald-600" />
                <span>QC Passed — Ready for PACS</span>
              </label>
            </div>
          </div>

          <div className="border-t border-slate-200 pt-3 flex space-x-2">
            <button
              type="button"
              onClick={onClose}
              className="flex-1 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer shadow-xs"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/30 cursor-pointer"
            >
              <CheckCircle2 className="w-4 h-4" />
              <span>Complete & Push to Reading Worklist</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

