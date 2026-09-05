import React from 'react';

interface VisualizerProps {
  modality: string;
  sliceIndex: number;
  maxSlices: number;
  windowPreset: string;
}

export const RadiologicalVisualizer: React.FC<VisualizerProps> = ({
  modality,
  sliceIndex,
  maxSlices,
  windowPreset,
}) => {
  const isInverted = windowPreset === 'invert';
  const isBone = windowPreset === 'bone';
  const isLung = windowPreset === 'lung';
  const isBrain = windowPreset === 'brain';

  // Dynamic filter effects based on window preset
  const filterStyle = isBone
    ? 'contrast(180%) brightness(120%)'
    : isLung
    ? 'contrast(140%) brightness(85%)'
    : isBrain
    ? 'contrast(200%) brightness(110%)'
    : isInverted
    ? 'invert(1) contrast(120%)'
    : 'contrast(120%) brightness(100%)';

  return (
    <div
      className="w-72 h-72 sm:w-96 sm:h-96 rounded-2xl bg-slate-950 border border-slate-800 flex items-center justify-center p-2 transition-all overflow-hidden relative shadow-inner"
      style={{ filter: filterStyle }}
    >
      {/* CT Scan Slice Rendering */}
      {modality === 'CT' && (
        <svg viewBox="0 0 300 300" className="w-full h-full text-slate-300">
          <ellipse cx="150" cy="150" rx="130" ry="110" fill="#111827" stroke="#374151" strokeWidth="3" />
          {/* Lungs or Abdominal Cavity depending on slice */}
          <path d="M 60 130 C 60 80, 110 80, 120 120 C 130 160, 90 200, 65 180 Z" fill="#030712" stroke="#4b5563" strokeWidth="2" />
          <path d="M 240 130 C 240 80, 190 80, 180 120 C 170 160, 210 200, 235 180 Z" fill="#030712" stroke="#4b5563" strokeWidth="2" />
          {/* Spine Vertebra & Ribs */}
          <circle cx="150" cy="220" r="14" fill="#e2e8f0" />
          <path d="M 140 220 L 150 245 L 160 220 Z" fill="#cbd5e1" />
          {/* Aorta / Heart Silhouette */}
          <circle cx="150" cy="150" r="22" fill="#1e293b" stroke="#64748b" strokeWidth="1.5" />
          {/* Rib segments */}
          {Array.from({ length: 6 }).map((_, i) => (
            <React.Fragment key={i}>
              <ellipse cx={45 + i * 5} cy={90 + i * 20} rx="4" ry="10" fill="#f8fafc" transform={`rotate(${i * 10} ${45 + i * 5} ${90 + i * 20})`} />
              <ellipse cx={255 - i * 5} cy={90 + i * 20} rx="4" ry="10" fill="#f8fafc" transform={`rotate(${-i * 10} ${255 - i * 5} ${90 + i * 20})`} />
            </React.Fragment>
          ))}
          {/* Dynamic Slice indicator marker */}
          <text x="15" y="25" fill="#38bdf8" fontSize="10" fontFamily="monospace">
            CT AXIAL [z = {(sliceIndex * 3.5).toFixed(1)}mm]
          </text>
        </svg>
      )}

      {/* MRI Scan Slice Rendering */}
      {modality === 'MR' && (
        <svg viewBox="0 0 300 300" className="w-full h-full text-slate-300">
          <rect x="20" y="20" width="260" height="260" rx="20" fill="#0f172a" stroke="#334155" strokeWidth="2" />
          {/* Sagittal Spine View */}
          <path d="M 120 40 Q 140 150 120 260" fill="none" stroke="#64748b" strokeWidth="28" strokeLinecap="round" />
          {/* Vertebral Bodies */}
          {Array.from({ length: 5 }).map((_, i) => (
            <React.Fragment key={i}>
              <rect x="135" y={60 + i * 38} width="32" height="24" rx="4" fill="#e2e8f0" stroke="#94a3b8" />
              {/* Intervertebral Disc */}
              <rect x="137" y={86 + i * 38} width="28" height="8" rx="2" fill="#38bdf8" opacity="0.8" />
            </React.Fragment>
          ))}
          {/* Spinal Cord & Thecal Sac */}
          <path d="M 125 40 Q 135 150 125 260" fill="none" stroke="#f1f5f9" strokeWidth="6" />
          <text x="30" y="40" fill="#c084fc" fontSize="10" fontFamily="monospace">
            MR T2 SAGITTAL [Echo {sliceIndex}]
          </text>
        </svg>
      )}

      {/* Digital X-Ray Rendering */}
      {modality === 'DX' && (
        <svg viewBox="0 0 300 300" className="w-full h-full text-slate-300">
          <rect x="20" y="20" width="260" height="260" fill="#020617" />
          {/* Thoracic cage & lungs */}
          <path d="M 70 80 C 70 50, 110 50, 125 80 C 135 120, 130 200, 60 210 Z" fill="#0f172a" stroke="#475569" strokeWidth="1.5" />
          <path d="M 230 80 C 230 50, 190 50, 175 80 C 165 120, 170 200, 240 210 Z" fill="#0f172a" stroke="#475569" strokeWidth="1.5" />
          {/* Mediastinum and Heart Silhouette */}
          <path d="M 125 80 Q 150 70 175 80 Q 165 140 190 190 Q 140 210 125 180 Z" fill="#64748b" opacity="0.8" />
          {/* Clavicles */}
          <path d="M 50 60 Q 100 70 140 80" stroke="#f8fafc" strokeWidth="6" fill="none" strokeLinecap="round" />
          <path d="M 250 60 Q 200 70 160 80" stroke="#f8fafc" strokeWidth="6" fill="none" strokeLinecap="round" />
          {/* Spine shadow */}
          <line x1="150" y1="40" x2="150" y2="250" stroke="#cbd5e1" strokeWidth="10" strokeDasharray="14,4" opacity="0.7" />
          <text x="30" y="40" fill="#38bdf8" fontSize="10" fontFamily="monospace">
            CHEST PA ERECT [kVp 120]
          </text>
        </svg>
      )}

      {/* Mammography Rendering */}
      {modality === 'MG' && (
        <svg viewBox="0 0 300 300" className="w-full h-full text-slate-300">
          <rect x="20" y="20" width="260" height="260" fill="#020617" />
          {/* Breast Silhouette MLO View */}
          <path d="M 40 40 L 40 260 C 140 260, 240 200, 220 140 C 200 80, 130 40, 40 40 Z" fill="#1e293b" stroke="#64748b" strokeWidth="2" />
          {/* Pectoral Muscle */}
          <polygon points="40,40 140,40 40,160" fill="#94a3b8" opacity="0.6" />
          {/* Fibroglandular Tissue density pattern */}
          <path d="M 70 100 Q 130 130 110 180 Q 90 220 50 200" fill="#cbd5e1" opacity="0.5" />
          <circle cx="120" cy="150" r="16" fill="#f8fafc" opacity="0.7" />
          <text x="30" y="35" fill="#f43f5e" fontSize="10" fontFamily="monospace">
            R-MLO DIGITAL MAMMO
          </text>
        </svg>
      )}

      {/* Ultrasound Rendering */}
      {modality === 'US' && (
        <svg viewBox="0 0 300 300" className="w-full h-full text-slate-300">
          <rect x="20" y="20" width="260" height="260" fill="#030712" />
          {/* Ultrasound Sector Fan */}
          <path d="M 150 40 L 40 250 A 180 180 0 0 0 260 250 Z" fill="#0f172a" stroke="#334155" strokeWidth="2" />
          {/* Organ parenchyma echogenicity */}
          <ellipse cx="140" cy="160" rx="55" ry="35" fill="#334155" stroke="#64748b" strokeDasharray="3,3" />
          {/* Vessel lumen (Anechoic black) */}
          <circle cx="155" cy="150" r="12" fill="#000000" stroke="#38bdf8" strokeWidth="1.5" />
          <text x="30" y="35" fill="#10b981" fontSize="10" fontFamily="monospace">
            ABDOMINAL 2D B-MODE [5.0 MHz]
          </text>
        </svg>
      )}
    </div>
  );
};
