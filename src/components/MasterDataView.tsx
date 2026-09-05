import React, { useState, useMemo } from 'react';
import {
  Database,
  Layers,
  FileSpreadsheet,
  Users,
  ShieldCheck,
  PlusCircle,
  Tag,
  CheckCircle2,
  Search,
  Filter,
  Download,
  Printer,
  Edit2,
  Trash2,
  Copy,
  Check,
  AlertTriangle,
  FileText,
  Clock,
  DollarSign,
  Phone,
  Mail,
  Building2,
  Stethoscope,
  X,
  Sparkles,
  Info,
  SlidersHorizontal,
  ExternalLink,
  ChevronRight,
  Eye,
  RefreshCw
} from 'lucide-react';
import { Modality, Service, Referrer, ScreeningForm, ReportTemplate, ScreeningQuestion } from '../types';

interface MasterDataViewProps {
  modalities: Modality[];
  services: Service[];
  referrers: Referrer[];
  forms: ScreeningForm[];
  templates: ReportTemplate[];
  onAddService?: (newSvc: Omit<Service, 'id'>) => void;
  onUpdateService?: (updatedSvc: Service) => void;
  onDeleteService?: (serviceId: number) => void;
  onAddModality?: (newMod: Omit<Modality, 'id'>) => void;
  onUpdateModality?: (updatedMod: Modality) => void;
  onAddReferrer?: (newRef: Omit<Referrer, 'id'>) => void;
  onUpdateReferrer?: (updatedRef: Referrer) => void;
  onDeleteReferrer?: (refId: number) => void;
  onAddForm?: (newForm: ScreeningForm) => void;
  onUpdateForm?: (updatedForm: ScreeningForm) => void;
  onAddTemplate?: (newTpl: ReportTemplate) => void;
  onUpdateTemplate?: (updatedTpl: ReportTemplate) => void;
  onDeleteTemplate?: (templateId: string) => void;
  onResetFactoryDefaults?: () => void;
  onExportBackup?: () => void;
  onImportBackup?: (snapshot: any) => void;
}

export const MasterDataView: React.FC<MasterDataViewProps> = ({
  modalities,
  services,
  referrers,
  forms,
  templates,
  onAddService,
  onUpdateService,
  onDeleteService,
  onAddModality,
  onUpdateModality,
  onAddReferrer,
  onUpdateReferrer,
  onDeleteReferrer,
  onAddForm,
  onUpdateForm,
  onAddTemplate,
  onUpdateTemplate,
  onDeleteTemplate,
  onResetFactoryDefaults,
  onExportBackup,
  onImportBackup,
}) => {
  const [activeSubTab, setActiveSubTab] = useState<'services' | 'modalities' | 'referrers' | 'forms' | 'templates'>('services');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedModalityFilter, setSelectedModalityFilter] = useState<string>('ALL');
  const [copiedId, setCopiedId] = useState<string | null>(null);

  // Modals state
  const [serviceModalOpen, setServiceModalOpen] = useState(false);
  const [editingService, setEditingService] = useState<Service | null>(null);

  const [modalityModalOpen, setModalityModalOpen] = useState(false);
  const [editingModality, setEditingModality] = useState<Modality | null>(null);

  const [referrerModalOpen, setReferrerModalOpen] = useState(false);
  const [editingReferrer, setEditingReferrer] = useState<Referrer | null>(null);

  const [templateModalOpen, setTemplateModalOpen] = useState(false);
  const [editingTemplate, setEditingTemplate] = useState<ReportTemplate | null>(null);

  const [formModalOpen, setFormModalOpen] = useState(false);
  const [editingForm, setEditingForm] = useState<ScreeningForm | null>(null);

  const [questionModalOpen, setQuestionModalOpen] = useState(false);
  const [targetFormId, setTargetFormId] = useState<string | null>(null);

  const [testFormModal, setTestFormModal] = useState<ScreeningForm | null>(null);
  const [testAnswers, setTestAnswers] = useState<Record<string, string>>({});

  const [printCatalogModalOpen, setPrintCatalogModalOpen] = useState(false);

  // Copy helper
  const handleCopy = (text: string, id: string) => {
    navigator.clipboard.writeText(text);
    setCopiedId(id);
    setTimeout(() => setCopiedId(null), 2000);
  };

  // Export Fee Schedule as CSV
  const handleExportCSV = () => {
    const headers = ['Procedure Code', 'Procedure Name', 'Modality', 'Price (PKR)', 'Duration (Mins)', 'Screening Req', 'Contrast Req', 'Preparation Instructions'];
    const rows = services.map(s => {
      const mod = modalities.find(m => m.id === s.modalityId);
      return [
        `"${s.code}"`,
        `"${s.name.replace(/"/g, '""')}"`,
        `"${mod?.code || ''}"`,
        s.price,
        s.durationMinutes,
        s.requiresScreening ? 'YES' : 'NO',
        s.requiresContrast ? 'YES' : 'NO',
        `"${s.preparationInstructions.replace(/"/g, '""')}"`
      ];
    });

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `ADC_Radiology_Fee_Schedule_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  // Filtered Services
  const filteredServices = useMemo(() => {
    return services.filter(svc => {
      const matchesSearch =
        svc.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        svc.code.toLowerCase().includes(searchQuery.toLowerCase()) ||
        svc.preparationInstructions.toLowerCase().includes(searchQuery.toLowerCase());
      
      const mod = modalities.find(m => m.id === svc.modalityId);
      const matchesModality = selectedModalityFilter === 'ALL' || mod?.code === selectedModalityFilter;

      return matchesSearch && matchesModality;
    });
  }, [services, modalities, searchQuery, selectedModalityFilter]);

  // Filtered Modalities
  const filteredModalities = useMemo(() => {
    return modalities.filter(mod =>
      mod.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      mod.code.toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [modalities, searchQuery]);

  // Filtered Referrers
  const filteredReferrers = useMemo(() => {
    return referrers.filter(ref =>
      ref.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      ref.specialty.toLowerCase().includes(searchQuery.toLowerCase()) ||
      ref.clinicName.toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [referrers, searchQuery]);

  // Filtered Forms
  const filteredForms = useMemo(() => {
    return forms.filter(f =>
      f.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
      f.slug.toLowerCase().includes(searchQuery.toLowerCase()) ||
      f.description.toLowerCase().includes(searchQuery.toLowerCase())
    );
  }, [forms, searchQuery]);

  // Filtered Templates
  const filteredTemplates = useMemo(() => {
    return templates.filter(tpl => {
      const matchesSearch =
        tpl.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
        tpl.findings.toLowerCase().includes(searchQuery.toLowerCase()) ||
        tpl.impression.toLowerCase().includes(searchQuery.toLowerCase());
      const mod = modalities.find(m => m.id === tpl.modalityId);
      const matchesModality = selectedModalityFilter === 'ALL' || mod?.code === selectedModalityFilter;
      return matchesSearch && matchesModality;
    });
  }, [templates, modalities, searchQuery, selectedModalityFilter]);

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
          <div className="flex items-center space-x-2">
            <h1 className="text-xl font-bold text-slate-900 tracking-tight">RIS Catalog & Clinical Configuration</h1>
            <span className="bg-cyan-50 text-cyan-700 text-xs px-2.5 py-1 rounded-md border border-cyan-200 font-semibold whitespace-nowrap">
              Master Registry
            </span>
          </div>
          <p className="text-slate-500 text-xs mt-0.5">
            Configure procedure services, imaging modalities, safety screening forms, reporting macros, and referring physicians.
          </p>
        </div>

        {/* Global Catalog Action Controls */}
        <div className="flex flex-wrap items-center gap-2">
          {onExportBackup && (
            <button
              onClick={onExportBackup}
              className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center space-x-1.5 border border-slate-300 transition-colors cursor-pointer"
              title="Export complete database backup as JSON"
            >
              <Download className="w-3.5 h-3.5 text-slate-600" />
              <span>Backup JSON</span>
            </button>
          )}

          {onImportBackup && (
            <label
              className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center space-x-1.5 border border-slate-300 transition-colors cursor-pointer"
              title="Restore database from JSON backup file"
            >
              <FileSpreadsheet className="w-3.5 h-3.5 text-slate-600" />
              <span>Restore JSON</span>
              <input
                type="file"
                accept=".json"
                className="hidden"
                onChange={e => {
                  const file = e.target.files?.[0];
                  if (file) {
                    const reader = new FileReader();
                    reader.onload = event => {
                      try {
                        const parsed = JSON.parse(event.target?.result as string);
                        onImportBackup(parsed);
                      } catch (err) {
                        alert('Invalid JSON backup file format.');
                      }
                    };
                    reader.readAsText(file);
                  }
                  e.target.value = '';
                }}
              />
            </label>
          )}

          {onResetFactoryDefaults && (
            <button
              onClick={onResetFactoryDefaults}
              className="px-3 py-1.5 bg-rose-50 hover:bg-rose-100 text-rose-700 rounded-lg text-xs font-semibold flex items-center space-x-1.5 border border-rose-200 transition-colors cursor-pointer"
              title="Restore pristine factory database defaults"
            >
              <RefreshCw className="w-3.5 h-3.5 text-rose-600" />
              <span>Reset Factory DB</span>
            </button>
          )}

          <button
            onClick={() => setPrintCatalogModalOpen(true)}
            className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center space-x-1.5 border border-slate-300 transition-colors cursor-pointer"
            title="Print Official Procedure Fee Schedule"
          >
            <Printer className="w-3.5 h-3.5 text-slate-600" />
            <span>Print Catalog</span>
          </button>
          
          <button
            onClick={handleExportCSV}
            className="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center space-x-1.5 border border-slate-300 transition-colors cursor-pointer"
            title="Download CSV Fee Schedule"
          >
            <Download className="w-3.5 h-3.5 text-slate-600" />
            <span>Export CSV</span>
          </button>

          {activeSubTab === 'services' && (
            <button
              onClick={() => {
                setEditingService(null);
                setServiceModalOpen(true);
              }}
              className="px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors cursor-pointer"
            >
              <PlusCircle className="w-3.5 h-3.5" />
              <span>Add Procedure</span>
            </button>
          )}

          {activeSubTab === 'modalities' && (
            <button
              onClick={() => {
                setEditingModality(null);
                setModalityModalOpen(true);
              }}
              className="px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors cursor-pointer"
            >
              <PlusCircle className="w-3.5 h-3.5" />
              <span>Add Modality Suite</span>
            </button>
          )}

          {activeSubTab === 'forms' && (
            <button
              onClick={() => {
                setEditingForm(null);
                setFormModalOpen(true);
              }}
              className="px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors cursor-pointer"
            >
              <PlusCircle className="w-3.5 h-3.5" />
              <span>Add Screening Form</span>
            </button>
          )}

          {activeSubTab === 'templates' && (
            <button
              onClick={() => {
                setEditingTemplate(null);
                setTemplateModalOpen(true);
              }}
              className="px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors cursor-pointer"
            >
              <PlusCircle className="w-3.5 h-3.5" />
              <span>Add Report Template</span>
            </button>
          )}

          {activeSubTab === 'referrers' && (
            <button
              onClick={() => {
                setEditingReferrer(null);
                setReferrerModalOpen(true);
              }}
              className="px-3.5 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors cursor-pointer"
            >
              <PlusCircle className="w-3.5 h-3.5" />
              <span>Add Referring Doctor</span>
            </button>
          )}
        </div>
      </div>

      {/* Sub-tab navigation & filter bar */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white p-3.5 rounded-xl border border-slate-200 shadow-xs">
        {/* Navigation Tabs */}
        <div className="flex flex-wrap gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs">
          <button
            onClick={() => setActiveSubTab('services')}
            className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
              activeSubTab === 'services' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Services ({services.length})
          </button>
          <button
            onClick={() => setActiveSubTab('modalities')}
            className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
              activeSubTab === 'modalities' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Modalities ({modalities.length})
          </button>
          <button
            onClick={() => setActiveSubTab('forms')}
            className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
              activeSubTab === 'forms' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Screening Forms ({forms.length})
          </button>
          <button
            onClick={() => setActiveSubTab('templates')}
            className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
              activeSubTab === 'templates' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Report Templates ({templates.length})
          </button>
          <button
            onClick={() => setActiveSubTab('referrers')}
            className={`px-3 py-1.5 rounded-lg font-semibold transition-colors cursor-pointer ${
              activeSubTab === 'referrers' ? 'bg-cyan-600 text-white shadow-xs' : 'text-slate-600 hover:text-slate-900'
            }`}
          >
            Referrers ({referrers.length})
          </button>
        </div>

        {/* Search & Modality Filter */}
        <div className="flex items-center space-x-2">
          <div className="relative">
            <Search className="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              placeholder={`Search ${activeSubTab}...`}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-cyan-500 w-44 sm:w-56"
            />
            {searchQuery && (
              <button
                onClick={() => setSearchQuery('')}
                className="absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600"
              >
                <X className="w-3 h-3" />
              </button>
            )}
          </div>

          {(activeSubTab === 'services' || activeSubTab === 'templates') && (
            <select
              value={selectedModalityFilter}
              onChange={(e) => setSelectedModalityFilter(e.target.value)}
              className="px-2.5 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 font-medium focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer"
            >
              <option value="ALL">All Modalities</option>
              {modalities.map(m => (
                <option key={m.id} value={m.code}>{m.code} - {m.name}</option>
              ))}
            </select>
          )}
        </div>
      </div>

      {/* Services Tab */}
      {activeSubTab === 'services' && (
        <div className="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
          <div className="p-4 bg-slate-50/70 border-b border-slate-200 flex items-center justify-between">
            <div className="text-xs font-semibold text-slate-700 flex items-center space-x-2">
              <span>Procedures Fee Schedule & Preparation Instructions</span>
              <span className="bg-cyan-100 text-cyan-800 text-[10px] px-2 py-0.5 rounded-sm font-bold">
                {filteredServices.length} active items
              </span>
            </div>
            <div className="text-[11px] text-slate-500">
              Changes reflect immediately in Reception Desk, Booking Modals, and Invoicing
            </div>
          </div>
          <div className="w-full overflow-x-hidden">
            <table className="w-full text-left text-xs table-auto">
              <thead className="bg-slate-100/90 text-slate-600 uppercase font-bold text-[11px] tracking-wider border-b border-slate-200">
                <tr>
                  <th className="py-3 px-4">Code / Procedure Name</th>
                  <th className="py-3 px-3 whitespace-nowrap">Modality</th>
                  <th className="py-3 px-3 whitespace-nowrap">Price (PKR)</th>
                  <th className="py-3 px-3 whitespace-nowrap">Duration</th>
                  <th className="py-3 px-3 whitespace-nowrap">Safety Protocols</th>
                  <th className="py-3 px-4">Patient Preparation Protocol</th>
                  <th className="py-3 px-4 text-right whitespace-nowrap">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 text-slate-700">
                {filteredServices.length === 0 ? (
                  <tr>
                    <td colSpan={7} className="py-8 text-center text-slate-400 text-xs">
                      No procedure services found matching your query.
                    </td>
                  </tr>
                ) : (
                  filteredServices.map((svc) => {
                    const mod = modalities.find(m => m.id === svc.modalityId);
                    return (
                      <tr key={svc.id} className="hover:bg-slate-50/80 transition-colors">
                        <td className="py-3 px-4">
                          <div className="font-bold text-slate-900 leading-snug">{svc.name}</div>
                          <div className="flex items-center space-x-1.5 mt-0.5">
                            <span className="font-mono text-[11px] text-cyan-700 font-bold">{svc.code}</span>
                            <button
                              onClick={() => handleCopy(svc.code, `code-${svc.id}`)}
                              className="text-slate-400 hover:text-slate-600"
                              title="Copy procedure code"
                            >
                              {copiedId === `code-${svc.id}` ? (
                                <Check className="w-3 h-3 text-emerald-600" />
                              ) : (
                                <Copy className="w-3 h-3" />
                              )}
                            </button>
                          </div>
                        </td>
                        <td className="py-3 px-3 whitespace-nowrap">
                          <span
                            className="text-[10px] px-2 py-0.5 rounded font-bold text-white font-mono shadow-2xs inline-block"
                            style={{ backgroundColor: mod?.color || '#64748b' }}
                          >
                            {mod?.code || 'N/A'}
                          </span>
                        </td>
                        <td className="py-3 px-3 font-mono font-bold text-slate-900 whitespace-nowrap text-xs">
                          Rs. {svc.price.toLocaleString()}
                        </td>
                        <td className="py-3 px-3 font-mono text-slate-600 whitespace-nowrap">
                          <div className="flex items-center space-x-1">
                            <Clock className="w-3 h-3 text-slate-400" />
                            <span>{svc.durationMinutes} mins</span>
                          </div>
                        </td>
                        <td className="py-3 px-3 whitespace-nowrap">
                          <div className="flex flex-wrap gap-1">
                            {svc.requiresScreening && (
                              <span className="px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 text-[10px] font-semibold border border-amber-200 flex items-center space-x-1">
                                <ShieldCheck className="w-2.5 h-2.5 text-amber-600" />
                                <span>Screening Req</span>
                              </span>
                            )}
                            {svc.requiresContrast && (
                              <span className="px-1.5 py-0.5 rounded bg-purple-50 text-purple-700 text-[10px] font-semibold border border-purple-200">
                                Contrast
                              </span>
                            )}
                            {!svc.requiresScreening && !svc.requiresContrast && (
                              <span className="text-[10px] text-slate-400">Standard</span>
                            )}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-[11px] text-slate-600 max-w-md">
                          <div className="line-clamp-2" title={svc.preparationInstructions}>
                            {svc.preparationInstructions}
                          </div>
                        </td>
                        <td className="py-3 px-4 text-right whitespace-nowrap">
                          <div className="flex items-center justify-end space-x-1">
                            <button
                              onClick={() => {
                                setEditingService(svc);
                                setServiceModalOpen(true);
                              }}
                              className="p-1.5 text-slate-500 hover:text-cyan-600 hover:bg-cyan-50 rounded-md transition-colors cursor-pointer"
                              title="Edit Procedure"
                            >
                              <Edit2 className="w-3.5 h-3.5" />
                            </button>
                            {onDeleteService && (
                              <button
                                onClick={() => {
                                  if (confirm(`Are you sure you want to remove procedure: "${svc.name}"?`)) {
                                    onDeleteService(svc.id);
                                  }
                                }}
                                className="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-md transition-colors cursor-pointer"
                                title="Delete Procedure"
                              >
                                <Trash2 className="w-3.5 h-3.5" />
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* Modalities Tab */}
      {activeSubTab === 'modalities' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            {filteredModalities.map((mod) => {
              const linkedCount = services.filter(s => s.modalityId === mod.id).length;
              return (
                <div key={mod.id} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3 relative group hover:border-slate-300 transition-all">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center space-x-3">
                      <span
                        className="w-11 h-11 rounded-xl flex items-center justify-center font-black text-white text-lg font-mono shadow-xs"
                        style={{ backgroundColor: mod.color }}
                      >
                        {mod.code}
                      </span>
                      <div>
                        <h3 className="font-bold text-slate-900 text-sm">{mod.name}</h3>
                        <span className="text-[10px] font-mono text-slate-500">ID #{mod.id}</span>
                      </div>
                    </div>
                    <span className="bg-emerald-50 text-emerald-700 border border-emerald-200 text-[10px] px-2 py-0.5 rounded-md font-bold whitespace-nowrap">
                      {mod.isActive ? 'ACTIVE SUITE' : 'INACTIVE'}
                    </span>
                  </div>

                  <div className="bg-slate-50 rounded-xl p-3 text-xs space-y-1.5 border border-slate-100">
                    <div className="flex justify-between text-slate-600">
                      <span>Inter-Slot Buffer:</span>
                      <strong className="text-slate-800 font-mono">{mod.bufferMinutes} minutes</strong>
                    </div>
                    <div className="flex justify-between text-slate-600">
                      <span>Linked Active Procedures:</span>
                      <strong className="text-cyan-700 font-mono">{linkedCount} items</strong>
                    </div>
                    <div className="flex justify-between text-slate-600">
                      <span>Theme Accent Color:</span>
                      <div className="flex items-center space-x-1.5">
                        <span className="w-3 h-3 rounded-sm border border-slate-300 inline-block" style={{ backgroundColor: mod.color }} />
                        <span className="font-mono text-[10px] text-slate-500 uppercase">{mod.color}</span>
                      </div>
                    </div>
                  </div>

                  <div className="pt-2 border-t border-slate-100 flex items-center justify-between text-xs">
                    <span className="text-slate-400 text-[11px]">Primary Imaging Suite</span>
                    <button
                      onClick={() => {
                        setEditingModality(mod);
                        setModalityModalOpen(true);
                      }}
                      className="text-cyan-600 hover:text-cyan-800 font-semibold text-xs flex items-center space-x-1 cursor-pointer"
                    >
                      <Edit2 className="w-3 h-3" />
                      <span>Configure Suite</span>
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Screening Forms Tab */}
      {activeSubTab === 'forms' && (
        <div className="space-y-4">
          {filteredForms.map((f) => (
            <div key={f.id} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-4">
              <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-100 pb-3">
                <div className="flex items-center space-x-2.5">
                  <div className="w-9 h-9 rounded-xl bg-amber-50 border border-amber-200 flex items-center justify-center">
                    <ShieldCheck className="w-5 h-5 text-amber-600" />
                  </div>
                  <div>
                    <h3 className="font-bold text-slate-900 text-sm">{f.name}</h3>
                    <p className="text-xs text-slate-500">{f.description}</p>
                  </div>
                </div>
                <div className="flex items-center space-x-2">
                  <span className="text-xs font-mono px-2 py-0.5 rounded bg-slate-100 text-slate-600 border border-slate-200">
                    {f.slug}
                  </span>
                  <button
                    onClick={() => {
                      setTestFormModal(f);
                      setTestAnswers({});
                    }}
                    className="px-2.5 py-1 bg-amber-50 hover:bg-amber-100 text-amber-800 rounded-lg text-xs font-semibold flex items-center space-x-1 border border-amber-200 transition-colors cursor-pointer"
                  >
                    <Eye className="w-3 h-3 text-amber-700" />
                    <span>Test Questionnaire</span>
                  </button>
                  <button
                    onClick={() => {
                      setTargetFormId(f.id);
                      setQuestionModalOpen(true);
                    }}
                    className="px-2.5 py-1 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 rounded-lg text-xs font-semibold flex items-center space-x-1 border border-cyan-200 transition-colors cursor-pointer"
                  >
                    <PlusCircle className="w-3 h-3" />
                    <span>Add Question</span>
                  </button>
                </div>
              </div>

              <div className="space-y-2">
                <div className="flex items-center justify-between text-[11px] font-bold text-slate-700 uppercase tracking-wider">
                  <span>Question Checklist ({f.questions.length} items)</span>
                  <span className="text-slate-400 font-normal normal-case">Blocking flags enforce clinical clearance before scanner entry</span>
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-2">
                  {f.questions.map((q, idx) => (
                    <div key={q.id} className="text-xs text-slate-800 bg-slate-50 border border-slate-200/70 p-3 rounded-xl flex items-start justify-between gap-3">
                      <div className="space-y-1 flex-1">
                        <div className="font-semibold text-slate-900 leading-snug">
                          {idx + 1}. {q.questionText}
                        </div>
                        {q.helpText && (
                          <div className="text-[11px] text-slate-500 italic">
                            {q.helpText}
                          </div>
                        )}
                        <div className="text-[10px] text-slate-400 font-mono">
                          Type: {q.answerType} • Risk condition: &quot;{q.riskValue || 'yes'}&quot;
                        </div>
                      </div>
                      <div className="shrink-0 flex flex-col items-end gap-1">
                        {q.isRiskBlocking ? (
                          <span className="text-[10px] text-rose-700 font-bold bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-sm flex items-center space-x-1">
                            <AlertTriangle className="w-2.5 h-2.5 text-rose-600" />
                            <span>Blocking Risk</span>
                          </span>
                        ) : (
                          <span className="text-[10px] text-slate-500 font-medium bg-slate-200/60 px-1.5 py-0.5 rounded">
                            Advisory
                          </span>
                        )}
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Report Templates Tab */}
      {activeSubTab === 'templates' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
            {filteredTemplates.map((tpl) => {
              const mod = modalities.find(m => m.id === tpl.modalityId);
              return (
                <div key={tpl.id} className="bg-white p-5 rounded-2xl border border-slate-200 shadow-sm space-y-3 text-xs flex flex-col justify-between">
                  <div className="space-y-3">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-2.5">
                      <div className="flex items-center space-x-2">
                        <span
                          className="text-[10px] px-2 py-0.5 rounded font-bold text-white font-mono shadow-2xs"
                          style={{ backgroundColor: mod?.color || '#64748b' }}
                        >
                          {mod?.code || 'N/A'}
                        </span>
                        <h3 className="font-bold text-slate-900 text-sm">{tpl.name}</h3>
                      </div>
                      <button
                        onClick={() => handleCopy(`${tpl.findings}\n\nIMPRESSION:\n${tpl.impression}`, `tpl-${tpl.id}`)}
                        className="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center space-x-1 transition-colors cursor-pointer"
                        title="Copy entire macro"
                      >
                        {copiedId === `tpl-${tpl.id}` ? (
                          <>
                            <Check className="w-3 h-3 text-emerald-600" />
                            <span className="text-emerald-700">Copied!</span>
                          </>
                        ) : (
                          <>
                            <Copy className="w-3 h-3 text-slate-500" />
                            <span>Copy Macro</span>
                          </>
                        )}
                      </button>
                    </div>

                    <div className="space-y-2">
                      <div>
                        <span className="text-[11px] font-bold text-slate-500 uppercase">Technique Preset:</span>
                        <p className="text-[11px] text-slate-700 bg-slate-50 p-2 rounded-lg border border-slate-100 font-mono">
                          {tpl.technique || 'Standard imaging protocol'}
                        </p>
                      </div>

                      <div>
                        <span className="text-[11px] font-bold text-purple-700 uppercase">Impression Macro:</span>
                        <div className="p-2.5 rounded-lg bg-purple-50/40 border border-purple-200/60 font-mono text-purple-950 whitespace-pre-line text-[11px] leading-relaxed max-h-36 overflow-y-auto">
                          {tpl.impression}
                        </div>
                      </div>

                      {tpl.findings && (
                        <div>
                          <span className="text-[11px] font-bold text-slate-600 uppercase">Structured Findings:</span>
                          <div className="p-2.5 rounded-lg bg-slate-50 border border-slate-200 font-mono text-slate-700 whitespace-pre-line text-[11px] leading-relaxed max-h-36 overflow-y-auto">
                            {tpl.findings}
                          </div>
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-slate-500">
                    <span className="text-[11px] font-mono">Template ID: {tpl.id}</span>
                    <div className="flex items-center space-x-2">
                      <button
                        onClick={() => {
                          setEditingTemplate(tpl);
                          setTemplateModalOpen(true);
                        }}
                        className="text-cyan-600 hover:text-cyan-800 font-semibold text-xs flex items-center space-x-1 cursor-pointer"
                      >
                        <Edit2 className="w-3 h-3" />
                        <span>Edit Macro</span>
                      </button>
                      {onDeleteTemplate && (
                        <button
                          onClick={() => {
                            if (confirm(`Delete reporting template "${tpl.name}"?`)) {
                              onDeleteTemplate(tpl.id);
                            }
                          }}
                          className="text-rose-500 hover:text-rose-700 p-1 cursor-pointer"
                          title="Delete Template"
                        >
                          <Trash2 className="w-3.5 h-3.5" />
                        </button>
                      )}
                    </div>
                  </div>
                </div>
              );
            })}
          </div>
        </div>
      )}

      {/* Referrers Tab */}
      {activeSubTab === 'referrers' && (
        <div className="space-y-4">
          <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {filteredReferrers.map((r) => (
              <div key={r.id} className="bg-white p-4 rounded-xl border border-slate-200 shadow-sm space-y-3 text-xs flex flex-col justify-between hover:border-cyan-200 transition-all">
                <div className="space-y-2">
                  <div className="flex items-start justify-between">
                    <div>
                      <div className="font-bold text-slate-900 text-sm leading-snug">{r.name}</div>
                      <div className="text-cyan-700 font-semibold text-xs mt-0.5">{r.specialty}</div>
                    </div>
                    <span className="bg-cyan-50 text-cyan-700 text-[10px] font-mono font-bold px-1.5 py-0.5 rounded">
                      ID #{r.id}
                    </span>
                  </div>

                  <div className="text-slate-600 flex items-center space-x-1.5 text-xs">
                    <Building2 className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span className="truncate">{r.clinicName}</span>
                  </div>

                  <div className="pt-2 border-t border-slate-100 space-y-1.5 font-mono text-[11px] text-slate-600">
                    <div className="flex items-center space-x-1.5">
                      <Phone className="w-3 h-3 text-slate-400 shrink-0" />
                      <span>{r.phone}</span>
                    </div>
                    <div className="flex items-center space-x-1.5">
                      <Mail className="w-3 h-3 text-slate-400 shrink-0" />
                      <span className="truncate">{r.email}</span>
                    </div>
                  </div>
                </div>

                <div className="pt-2.5 border-t border-slate-100 flex items-center justify-between">
                  <a
                    href={`https://wa.me/${r.phone.replace(/[^0-9]/g, '')}`}
                    target="_blank"
                    rel="noreferrer"
                    className="text-emerald-700 hover:text-emerald-800 text-[11px] font-semibold flex items-center space-x-1"
                  >
                    <span>WhatsApp</span>
                    <ExternalLink className="w-2.5 h-2.5" />
                  </a>
                  <div className="flex items-center space-x-1">
                    <button
                      onClick={() => {
                        setEditingReferrer(r);
                        setReferrerModalOpen(true);
                      }}
                      className="p-1 text-slate-400 hover:text-cyan-600 rounded cursor-pointer"
                      title="Edit Referrer"
                    >
                      <Edit2 className="w-3.5 h-3.5" />
                    </button>
                    {onDeleteReferrer && (
                      <button
                        onClick={() => {
                          if (confirm(`Remove referring doctor: ${r.name}?`)) {
                            onDeleteReferrer(r.id);
                          }
                        }}
                        className="p-1 text-slate-400 hover:text-rose-600 rounded cursor-pointer"
                        title="Delete Referrer"
                      >
                        <Trash2 className="w-3.5 h-3.5" />
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* ===================== MODALS ===================== */}

      {/* 1. Add / Edit Service Modal */}
      {serviceModalOpen && (
        <ServiceFormModal
          service={editingService}
          modalities={modalities}
          onSave={(svcData) => {
            if (editingService && onUpdateService) {
              onUpdateService({ ...svcData, id: editingService.id });
            } else if (onAddService) {
              onAddService(svcData);
            }
            setServiceModalOpen(false);
            setEditingService(null);
          }}
          onClose={() => {
            setServiceModalOpen(false);
            setEditingService(null);
          }}
        />
      )}

      {/* 2. Add / Edit Modality Modal */}
      {modalityModalOpen && (
        <ModalityFormModal
          modality={editingModality}
          onSave={(modData) => {
            if (editingModality && onUpdateModality) {
              onUpdateModality({ ...modData, id: editingModality.id });
            } else if (onAddModality) {
              onAddModality(modData);
            }
            setModalityModalOpen(false);
            setEditingModality(null);
          }}
          onClose={() => {
            setModalityModalOpen(false);
            setEditingModality(null);
          }}
        />
      )}

      {/* 3. Add / Edit Referrer Modal */}
      {referrerModalOpen && (
        <ReferrerFormModal
          referrer={editingReferrer}
          onSave={(refData) => {
            if (editingReferrer && onUpdateReferrer) {
              onUpdateReferrer({ ...refData, id: editingReferrer.id });
            } else if (onAddReferrer) {
              onAddReferrer(refData);
            }
            setReferrerModalOpen(false);
            setEditingReferrer(null);
          }}
          onClose={() => {
            setReferrerModalOpen(false);
            setEditingReferrer(null);
          }}
        />
      )}

      {/* 4. Add / Edit Report Template Modal */}
      {templateModalOpen && (
        <TemplateFormModal
          template={editingTemplate}
          modalities={modalities}
          onSave={(tplData) => {
            if (editingTemplate && onUpdateTemplate) {
              onUpdateTemplate({ ...tplData, id: editingTemplate.id });
            } else if (onAddTemplate) {
              const newId = `tpl-${Date.now()}`;
              onAddTemplate({ ...tplData, id: newId });
            }
            setTemplateModalOpen(false);
            setEditingTemplate(null);
          }}
          onClose={() => {
            setTemplateModalOpen(false);
            setEditingTemplate(null);
          }}
        />
      )}

      {/* 5. Add Question to Form Modal */}
      {questionModalOpen && targetFormId && (
        <AddQuestionModal
          formId={targetFormId}
          onSave={(newQ) => {
            if (onUpdateForm) {
              const targetForm = forms.find(f => f.id === targetFormId);
              if (targetForm) {
                const updated = {
                  ...targetForm,
                  questions: [...targetForm.questions, newQ]
                };
                onUpdateForm(updated);
              }
            }
            setQuestionModalOpen(false);
            setTargetFormId(null);
          }}
          onClose={() => {
            setQuestionModalOpen(false);
            setTargetFormId(null);
          }}
        />
      )}

      {/* 6. Test Questionnaire Simulator */}
      {testFormModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
            <div className="p-4 border-b border-slate-200 flex items-center justify-between">
              <div className="flex items-center space-x-2">
                <ShieldCheck className="w-5 h-5 text-cyan-600" />
                <h3 className="font-bold text-slate-900 text-sm">Questionnaire Simulator: {testFormModal.name}</h3>
              </div>
              <button
                onClick={() => setTestFormModal(null)}
                className="text-slate-400 hover:text-slate-600"
              >
                <X className="w-4 h-4" />
              </button>
            </div>
            <div className="p-5 overflow-y-auto space-y-4 text-xs">
              <p className="text-slate-500">{testFormModal.description}</p>
              <div className="space-y-3">
                {testFormModal.questions.map((q, idx) => {
                  const val = testAnswers[q.id] || '';
                  const isRisk = val === (q.riskValue || 'yes');
                  return (
                    <div key={q.id} className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                      <div className="font-semibold text-slate-900 flex items-center justify-between">
                        <span>{idx + 1}. {q.questionText}</span>
                        {q.isRiskBlocking && (
                          <span className="text-[10px] text-rose-700 bg-rose-50 border border-rose-200 px-1.5 py-0.5 rounded font-bold">
                            Blocking
                          </span>
                        )}
                      </div>
                      <div className="flex gap-2">
                        {['yes', 'no'].map((opt) => (
                          <button
                            key={opt}
                            type="button"
                            onClick={() => setTestAnswers(prev => ({ ...prev, [q.id]: opt }))}
                            className={`px-3 py-1 rounded-lg font-bold uppercase text-[11px] border cursor-pointer ${
                              val === opt
                                ? opt === (q.riskValue || 'yes')
                                  ? 'bg-rose-600 text-white border-rose-600'
                                  : 'bg-emerald-600 text-white border-emerald-600'
                                : 'bg-white text-slate-700 border-slate-200 hover:bg-slate-100'
                            }`}
                          >
                            {opt}
                          </button>
                        ))}
                      </div>
                      {isRisk && (
                        <div className="text-[11px] text-rose-700 font-bold flex items-center space-x-1">
                          <AlertTriangle className="w-3 h-3" />
                          <span>{q.isRiskBlocking ? 'CLINICAL BLOCK: Requires radiologist clearance' : 'Advisory risk flag'}</span>
                        </div>
                      )}
                    </div>
                  );
                })}
              </div>
            </div>
            <div className="p-4 border-t border-slate-200 flex justify-end">
              <button
                onClick={() => setTestFormModal(null)}
                className="px-4 py-1.5 bg-slate-800 text-white rounded-lg text-xs font-semibold"
              >
                Close Simulator
              </button>
            </div>
          </div>
        </div>
      )}

      {/* 7. Official Printable Catalog View */}
      {printCatalogModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl max-w-4xl w-full max-h-[90vh] flex flex-col shadow-2xl border border-slate-200">
            <div className="p-4 border-b border-slate-200 flex items-center justify-between print:hidden">
              <div className="flex items-center space-x-2">
                <Printer className="w-5 h-5 text-cyan-600" />
                <h3 className="font-bold text-slate-900 text-sm">Official Diagnostic Fee Schedule — Print View</h3>
              </div>
              <div className="flex items-center space-x-2">
                <button
                  onClick={() => window.print()}
                  className="px-3 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1"
                >
                  <Printer className="w-3.5 h-3.5" />
                  <span>Print Document</span>
                </button>
                <button
                  onClick={() => setPrintCatalogModalOpen(false)}
                  className="p-1.5 text-slate-400 hover:text-slate-600 rounded-md"
                >
                  <X className="w-4 h-4" />
                </button>
              </div>
            </div>
            <div className="p-6 overflow-y-auto space-y-6 text-xs text-slate-800 print:p-0">
              <div className="text-center border-b border-slate-200 pb-4">
                <h2 className="text-lg font-black text-slate-900 tracking-tight">AMAD DIAGNOSTIC CENTRE</h2>
                <p className="text-slate-600 font-medium">Radiology & Clinical Imaging Department • Islamabad</p>
                <p className="text-[11px] text-slate-500 font-mono mt-1">Official Master Procedure Schedule & Fee Registry • {new Date().toLocaleDateString()}</p>
              </div>

              <div className="space-y-6">
                {modalities.map(mod => {
                  const modServices = services.filter(s => s.modalityId === mod.id);
                  if (modServices.length === 0) return null;
                  return (
                    <div key={mod.id} className="space-y-2">
                      <div className="flex items-center space-x-2 border-b border-slate-300 pb-1">
                        <span className="font-bold text-sm text-slate-900">{mod.name} ({mod.code})</span>
                        <span className="text-[11px] text-slate-500">Slot buffer: {mod.bufferMinutes}m</span>
                      </div>
                      <div className="w-full overflow-x-hidden">
                        <table className="w-full text-left text-xs border border-slate-200">
                          <thead className="bg-slate-100 text-slate-700 font-bold border-b border-slate-200">
                            <tr>
                              <th className="p-2">Code</th>
                              <th className="p-2">Procedure Description</th>
                              <th className="p-2">Duration</th>
                              <th className="p-2">Flags</th>
                              <th className="p-2 text-right">Fee (PKR)</th>
                            </tr>
                          </thead>
                          <tbody className="divide-y divide-slate-200">
                            {modServices.map(s => (
                              <tr key={s.id}>
                                <td className="p-2 font-mono font-bold text-cyan-800">{s.code}</td>
                                <td className="p-2">
                                  <div className="font-semibold">{s.name}</div>
                                  <div className="text-[10px] text-slate-500">{s.preparationInstructions}</div>
                                </td>
                                <td className="p-2 font-mono">{s.durationMinutes}m</td>
                                <td className="p-2 text-[10px]">
                                  {[s.requiresContrast && 'Contrast', s.requiresScreening && 'Screening'].filter(Boolean).join(', ') || 'Standard'}
                                </td>
                                <td className="p-2 text-right font-mono font-bold">Rs. {s.price.toLocaleString()}</td>
                              </tr>
                            ))}
                          </tbody>
                        </table>
                      </div>
                    </div>
                  );
                })}
              </div>

              <div className="text-center pt-4 border-t border-slate-200 text-[10px] text-slate-500">
                Amad Diagnostic Centre • All fees are in PKR (Pakistani Rupees) • Regulated by Islamabad Healthcare Regulatory Authority (IHRA)
              </div>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

// ===================== SUB-MODAL COMPONENTS =====================

// 1. Service Form Modal
interface ServiceFormModalProps {
  service: Service | null;
  modalities: Modality[];
  onSave: (svcData: Omit<Service, 'id'>) => void;
  onClose: () => void;
}

const ServiceFormModal: React.FC<ServiceFormModalProps> = ({ service, modalities, onSave, onClose }) => {
  const [name, setName] = useState(service?.name || '');
  const [code, setCode] = useState(service?.code || '');
  const [modalityId, setModalityId] = useState<number>(service?.modalityId || modalities[0]?.id || 1);
  const [price, setPrice] = useState<number>(service?.price || 5000);
  const [durationMinutes, setDurationMinutes] = useState<number>(service?.durationMinutes || 20);
  const [requiresScreening, setRequiresScreening] = useState<boolean>(service?.requiresScreening || false);
  const [requiresContrast, setRequiresContrast] = useState<boolean>(service?.requiresContrast || false);
  const [preparationInstructions, setPreparationInstructions] = useState(service?.preparationInstructions || '');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim() || !code.trim()) {
      alert('Please fill in procedure name and code.');
      return;
    }
    onSave({
      name: name.trim(),
      code: code.trim().toUpperCase(),
      modalityId,
      price: Number(price),
      durationMinutes: Number(durationMinutes),
      requiresScreening,
      requiresContrast,
      preparationInstructions: preparationInstructions.trim() || 'No special preparation needed.'
    });
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl max-w-lg w-full shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
        <form onSubmit={handleSubmit} className="flex flex-col max-h-[90vh]">
          <div className="p-4 border-b border-slate-200 flex items-center justify-between">
            <div className="flex items-center space-x-2">
              <PlusCircle className="w-5 h-5 text-cyan-600" />
              <h3 className="font-bold text-slate-900 text-sm">
                {service ? 'Edit Procedure Service' : 'Add New Diagnostic Procedure'}
              </h3>
            </div>
            <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
              <X className="w-4 h-4" />
            </button>
          </div>

          <div className="p-5 overflow-y-auto space-y-4 text-xs">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Procedure Full Name</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. HRCT Chest (High Resolution CT)"
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-cyan-500 focus:outline-none"
              />
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Procedure Code</label>
                <input
                  type="text"
                  required
                  value={code}
                  onChange={(e) => setCode(e.target.value)}
                  placeholder="e.g. CT-CHEST-HR"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono uppercase focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                />
              </div>
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Modality Suite</label>
                <select
                  value={modalityId}
                  onChange={(e) => setModalityId(Number(e.target.value))}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                >
                  {modalities.map(m => (
                    <option key={m.id} value={m.id}>{m.code} - {m.name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Standard Fee (PKR)</label>
                <input
                  type="number"
                  min="0"
                  step="100"
                  required
                  value={price}
                  onChange={(e) => setPrice(Number(e.target.value))}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono font-bold focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                />
              </div>
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Slot Duration (Minutes)</label>
                <input
                  type="number"
                  min="5"
                  max="180"
                  step="5"
                  required
                  value={durationMinutes}
                  onChange={(e) => setDurationMinutes(Number(e.target.value))}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono focus:ring-1 focus:ring-cyan-500 focus:outline-none"
                />
              </div>
            </div>

            <div className="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
              <span className="font-semibold text-slate-700 block">Clinical Safety Flags</span>
              <div className="flex items-center space-x-6">
                <label className="flex items-center space-x-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={requiresScreening}
                    onChange={(e) => setRequiresScreening(e.target.checked)}
                    className="w-4 h-4 text-cyan-600 rounded border-slate-300 focus:ring-cyan-500"
                  />
                  <span className="text-slate-800">Requires Pre-Scan Safety Screening</span>
                </label>
                <label className="flex items-center space-x-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={requiresContrast}
                    onChange={(e) => setRequiresContrast(e.target.checked)}
                    className="w-4 h-4 text-purple-600 rounded border-slate-300 focus:ring-purple-500"
                  />
                  <span className="text-slate-800">Requires IV Contrast Injection</span>
                </label>
              </div>
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Patient Preparation Instructions</label>
              <textarea
                rows={3}
                value={preparationInstructions}
                onChange={(e) => setPreparationInstructions(e.target.value)}
                placeholder="e.g. 4 hours fasting prior to appointment. Bring latest Serum Creatinine report."
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs focus:ring-1 focus:ring-cyan-500 focus:outline-none"
              />
            </div>
          </div>

          <div className="p-4 border-t border-slate-200 flex justify-end space-x-2">
            <button
              type="button"
              onClick={onClose}
              className="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-lg text-xs font-semibold cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              className="px-4 py-1.5 bg-cyan-600 hover:bg-cyan-700 text-white rounded-lg text-xs font-semibold shadow-xs cursor-pointer"
            >
              {service ? 'Save Changes' : 'Create Procedure'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

// 2. Modality Form Modal
interface ModalityFormModalProps {
  modality: Modality | null;
  onSave: (modData: Omit<Modality, 'id'>) => void;
  onClose: () => void;
}

const ModalityFormModal: React.FC<ModalityFormModalProps> = ({ modality, onSave, onClose }) => {
  const [name, setName] = useState(modality?.name || '');
  const [code, setCode] = useState<Modality['code']>(modality?.code || 'DX');
  const [color, setColor] = useState(modality?.color || '#0284c7');
  const [bufferMinutes, setBufferMinutes] = useState(modality?.bufferMinutes || 10);
  const [isActive, setIsActive] = useState(modality ? modality.isActive : true);

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    onSave({
      name: name.trim(),
      code,
      color,
      bufferMinutes: Number(bufferMinutes),
      isActive
    });
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
        <form onSubmit={handleSubmit} className="flex flex-col">
          <div className="p-4 border-b border-slate-200 flex items-center justify-between">
            <h3 className="font-bold text-slate-900 text-sm">
              {modality ? 'Edit Modality Suite' : 'Add Modality Suite'}
            </h3>
            <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
              <X className="w-4 h-4" />
            </button>
          </div>
          <div className="p-5 space-y-4 text-xs">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Modality Suite Name</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. 1.5T High-Field MRI Suite"
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Modality Code</label>
                <select
                  value={code}
                  onChange={(e) => setCode(e.target.value as Modality['code'])}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono font-bold"
                >
                  <option value="MR">MR - Magnetic Resonance</option>
                  <option value="CT">CT - Computed Tomography</option>
                  <option value="US">US - Ultrasound / Doppler</option>
                  <option value="DX">DX - Digital Radiography</option>
                  <option value="MG">MG - Mammography</option>
                </select>
              </div>
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Inter-Slot Buffer (Mins)</label>
                <input
                  type="number"
                  min="0"
                  max="60"
                  required
                  value={bufferMinutes}
                  onChange={(e) => setBufferMinutes(Number(e.target.value))}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono"
                />
              </div>
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Theme Accent Color</label>
              <div className="flex items-center space-x-3">
                <input
                  type="color"
                  value={color}
                  onChange={(e) => setColor(e.target.value)}
                  className="w-10 h-8 rounded border border-slate-200 cursor-pointer p-0"
                />
                <span className="font-mono text-slate-600">{color}</span>
              </div>
            </div>
            <label className="flex items-center space-x-2 cursor-pointer pt-2">
              <input
                type="checkbox"
                checked={isActive}
                onChange={(e) => setIsActive(e.target.checked)}
                className="w-4 h-4 text-cyan-600 rounded"
              />
              <span className="font-semibold text-slate-800">Suite Active in Online Scheduling</span>
            </label>
          </div>
          <div className="p-4 border-t border-slate-200 flex justify-end space-x-2">
            <button type="button" onClick={onClose} className="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">
              Cancel
            </button>
            <button type="submit" className="px-4 py-1.5 bg-cyan-600 text-white rounded-lg text-xs font-semibold">
              Save Suite
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

// 3. Referrer Form Modal
interface ReferrerFormModalProps {
  referrer: Referrer | null;
  onSave: (refData: Omit<Referrer, 'id'>) => void;
  onClose: () => void;
}

const ReferrerFormModal: React.FC<ReferrerFormModalProps> = ({ referrer, onSave, onClose }) => {
  const [name, setName] = useState(referrer?.name || '');
  const [specialty, setSpecialty] = useState(referrer?.specialty || '');
  const [clinicName, setClinicName] = useState(referrer?.clinicName || '');
  const [phone, setPhone] = useState(referrer?.phone || '');
  const [email, setEmail] = useState(referrer?.email || '');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim()) return;
    onSave({
      name: name.trim(),
      specialty: specialty.trim() || 'General Medicine',
      clinicName: clinicName.trim() || 'Private Clinic',
      phone: phone.trim() || '+92 300 0000000',
      email: email.trim() || 'doctor@hospital.pk'
    });
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
        <form onSubmit={handleSubmit} className="flex flex-col">
          <div className="p-4 border-b border-slate-200 flex items-center justify-between">
            <h3 className="font-bold text-slate-900 text-sm">
              {referrer ? 'Edit Referring Physician' : 'Add Referring Physician'}
            </h3>
            <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
              <X className="w-4 h-4" />
            </button>
          </div>
          <div className="p-5 space-y-3 text-xs">
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Doctor Full Name & Title</label>
              <input
                type="text"
                required
                value={name}
                onChange={(e) => setName(e.target.value)}
                placeholder="e.g. Dr. Tariq Mahmood, FCPS"
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Medical Specialty</label>
              <input
                type="text"
                required
                value={specialty}
                onChange={(e) => setSpecialty(e.target.value)}
                placeholder="e.g. Pulmonology / Chest Specialist"
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div>
              <label className="block font-semibold text-slate-700 mb-1">Hospital / Clinic Affiliation</label>
              <input
                type="text"
                required
                value={clinicName}
                onChange={(e) => setClinicName(e.target.value)}
                placeholder="e.g. PIMS Hospital Islamabad"
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs"
              />
            </div>
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Contact Phone</label>
                <input
                  type="text"
                  required
                  value={phone}
                  onChange={(e) => setPhone(e.target.value)}
                  placeholder="+92 300 1234567"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono"
                />
              </div>
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Email Address</label>
                <input
                  type="email"
                  required
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="doctor@hospital.pk"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono"
                />
              </div>
            </div>
          </div>
          <div className="p-4 border-t border-slate-200 flex justify-end space-x-2">
            <button type="button" onClick={onClose} className="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">
              Cancel
            </button>
            <button type="submit" className="px-4 py-1.5 bg-cyan-600 text-white rounded-lg text-xs font-semibold">
              Save Doctor
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

// 4. Template Form Modal
interface TemplateFormModalProps {
  template: ReportTemplate | null;
  modalities: Modality[];
  onSave: (tplData: Omit<ReportTemplate, 'id'>) => void;
  onClose: () => void;
}

const TemplateFormModal: React.FC<TemplateFormModalProps> = ({ template, modalities, onSave, onClose }) => {
  const [name, setName] = useState(template?.name || '');
  const [modalityId, setModalityId] = useState<number>(template?.modalityId || modalities[0]?.id || 1);
  const [clinicalHistory, setClinicalHistory] = useState(template?.clinicalHistory || '');
  const [technique, setTechnique] = useState(template?.technique || '');
  const [findings, setFindings] = useState(template?.findings || '');
  const [impression, setImpression] = useState(template?.impression || '');
  const [recommendations, setRecommendations] = useState(template?.recommendations || '');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!name.trim() || !impression.trim()) {
      alert('Please provide template name and impression macro.');
      return;
    }
    onSave({
      name: name.trim(),
      modalityId,
      clinicalHistory: clinicalHistory.trim(),
      technique: technique.trim(),
      findings: findings.trim(),
      impression: impression.trim(),
      recommendations: recommendations.trim()
    });
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl max-w-2xl w-full max-h-[90vh] shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150 flex flex-col">
        <form onSubmit={handleSubmit} className="flex flex-col flex-1 overflow-hidden">
          <div className="p-4 border-b border-slate-200 flex items-center justify-between">
            <h3 className="font-bold text-slate-900 text-sm">
              {template ? 'Edit Radiology Report Template' : 'Add Reporting Macro Template'}
            </h3>
            <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
              <X className="w-4 h-4" />
            </button>
          </div>
          <div className="p-5 overflow-y-auto space-y-3 text-xs">
            <div className="grid grid-cols-2 gap-3">
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Template Name</label>
                <input
                  type="text"
                  required
                  value={name}
                  onChange={(e) => setName(e.target.value)}
                  placeholder="e.g. Chest X-Ray Normal PA View"
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs"
                />
              </div>
              <div>
                <label className="block font-semibold text-slate-700 mb-1">Modality</label>
                <select
                  value={modalityId}
                  onChange={(e) => setModalityId(Number(e.target.value))}
                  className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium"
                >
                  {modalities.map(m => (
                    <option key={m.id} value={m.id}>{m.code} - {m.name}</option>
                  ))}
                </select>
              </div>
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Technique Preset</label>
              <input
                type="text"
                value={technique}
                onChange={(e) => setTechnique(e.target.value)}
                placeholder="e.g. Standard erect PA view of the chest."
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono"
              />
            </div>

            <div>
              <label className="block font-semibold text-slate-700 mb-1">Structured Findings Preset</label>
              <textarea
                rows={4}
                value={findings}
                onChange={(e) => setFindings(e.target.value)}
                placeholder="Detailed anatomical breakdown findings..."
                className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs font-mono"
              />
            </div>

            <div>
              <label className="block font-semibold text-purple-700 mb-1">Impression Template (Required)</label>
              <textarea
                rows={3}
                required
                value={impression}
                onChange={(e) => setImpression(e.target.value)}
                placeholder="1. Normal study...\n2. No focal consolidation."
                className="w-full px-3 py-2 bg-purple-50/40 border border-purple-200 rounded-lg text-xs font-mono text-purple-950 font-semibold"
              />
            </div>
          </div>
          <div className="p-4 border-t border-slate-200 flex justify-end space-x-2">
            <button type="button" onClick={onClose} className="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">
              Cancel
            </button>
            <button type="submit" className="px-4 py-1.5 bg-cyan-600 text-white rounded-lg text-xs font-semibold">
              Save Template
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};

// 5. Add Question Modal
interface AddQuestionModalProps {
  formId: string;
  onSave: (q: ScreeningQuestion) => void;
  onClose: () => void;
}

const AddQuestionModal: React.FC<AddQuestionModalProps> = ({ formId, onSave, onClose }) => {
  const [questionText, setQuestionText] = useState('');
  const [helpText, setHelpText] = useState('');
  const [isRiskBlocking, setIsRiskBlocking] = useState(true);
  const [riskValue, setRiskValue] = useState('yes');

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!questionText.trim()) return;
    onSave({
      id: `q-${Date.now()}`,
      formId,
      questionText: questionText.trim(),
      helpText: helpText.trim() || undefined,
      answerType: 'boolean',
      riskValue,
      isRiskBlocking,
      sortOrder: Date.now()
    });
  };

  return (
    <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl max-w-md w-full shadow-2xl border border-slate-200 animate-in fade-in zoom-in-95 duration-150">
        <form onSubmit={handleSubmit} className="p-5 space-y-4 text-xs">
          <div className="flex items-center justify-between border-b border-slate-200 pb-3">
            <h3 className="font-bold text-slate-900 text-sm">Add Safety Screening Question</h3>
            <button type="button" onClick={onClose} className="text-slate-400 hover:text-slate-600">
              <X className="w-4 h-4" />
            </button>
          </div>

          <div>
            <label className="block font-semibold text-slate-700 mb-1">Question Text</label>
            <textarea
              rows={2}
              required
              value={questionText}
              onChange={(e) => setQuestionText(e.target.value)}
              placeholder="e.g. Do you have a cardiac pacemaker, aneurysm clip, or neurostimulator?"
              className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs"
            />
          </div>

          <div>
            <label className="block font-semibold text-slate-700 mb-1">Clinical Help / Clarification</label>
            <input
              type="text"
              value={helpText}
              onChange={(e) => setHelpText(e.target.value)}
              placeholder="e.g. Absolute MRI contraindication unless MR-Conditional card verified."
              className="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg text-xs"
            />
          </div>

          <label className="flex items-center space-x-2 cursor-pointer">
            <input
              type="checkbox"
              checked={isRiskBlocking}
              onChange={(e) => setIsRiskBlocking(e.target.checked)}
              className="w-4 h-4 text-rose-600 rounded"
            />
            <span className="font-semibold text-rose-700">Hard Blocking Risk (Halts scan until Radiologist Override)</span>
          </label>

          <div className="pt-3 border-t border-slate-200 flex justify-end space-x-2">
            <button type="button" onClick={onClose} className="px-3 py-1.5 bg-slate-100 text-slate-700 rounded-lg text-xs font-semibold">
              Cancel
            </button>
            <button type="submit" className="px-4 py-1.5 bg-cyan-600 text-white rounded-lg text-xs font-semibold">
              Add Question
            </button>
          </div>
        </form>
      </div>
    </div>
  );
};
