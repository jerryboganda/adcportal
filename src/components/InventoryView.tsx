import React, { useState, useMemo } from 'react';
import {
  Boxes,
  AlertTriangle,
  Plus,
  Search,
  Filter,
  ArrowUpRight,
  ArrowDownRight,
  RotateCcw,
  Download,
  Calendar,
  ShieldAlert,
  Activity,
  CheckCircle2,
  Clock,
  Sparkles,
  Zap,
  DollarSign,
  Building,
  RefreshCw,
  Package,
  Layers,
  ThermometerSnowflake,
  HeartPulse,
  Syringe,
  FileSpreadsheet,
  Trash2,
  Edit3,
  Check,
  X,
  ExternalLink,
  ChevronRight,
  Info,
  ShieldCheck,
  Stethoscope
} from 'lucide-react';
import {
  InventoryItem,
  InventoryTransaction,
  AdverseReactionReport,
  InventoryCategory,
  TransactionType,
  AdverseSeverity,
  AdverseOutcome,
  Appointment
} from '../types';

interface InventoryViewProps {
  inventoryItems: InventoryItem[];
  setInventoryItems: React.Dispatch<React.SetStateAction<InventoryItem[]>>;
  inventoryTransactions: InventoryTransaction[];
  setInventoryTransactions: React.Dispatch<React.SetStateAction<InventoryTransaction[]>>;
  adverseReactions: AdverseReactionReport[];
  setAdverseReactions: React.Dispatch<React.SetStateAction<AdverseReactionReport[]>>;
  appointments: Appointment[];
  role: string;
  onNavigateToTab?: (tab: string, appointmentId?: string) => void;
}

type SubTab = 'contrast' | 'syringes' | 'emergency' | 'all_items' | 'movements' | 'adverse';

export const InventoryView: React.FC<InventoryViewProps> = ({
  inventoryItems,
  setInventoryItems,
  inventoryTransactions,
  setInventoryTransactions,
  adverseReactions,
  setAdverseReactions,
  appointments,
  role,
  onNavigateToTab,
}) => {
  const [activeSubTab, setActiveSubTab] = useState<SubTab>('contrast');
  const [searchQuery, setSearchQuery] = useState('');
  const [selectedModalityFilter, setSelectedModalityFilter] = useState<'ALL' | 'CT' | 'MRI' | 'XRAY'>('ALL');
  const [stockStatusFilter, setStockStatusFilter] = useState<'ALL' | 'LOW' | 'IN_STOCK'>('ALL');

  // Modals state
  const [isStockInModalOpen, setIsStockInModalOpen] = useState(false);
  const [isAdjustModalOpen, setIsAdjustModalOpen] = useState(false);
  const [isNewItemModalOpen, setIsNewItemModalOpen] = useState(false);
  const [isAdverseModalOpen, setIsAdverseModalOpen] = useState(false);
  const [selectedItemForAction, setSelectedItemForAction] = useState<InventoryItem | null>(null);

  // Form states for Stock In
  const [stockInQty, setStockInQty] = useState<number>(10);
  const [stockInBatch, setStockInBatch] = useState<string>('');
  const [stockInExpiry, setStockInExpiry] = useState<string>('2027-12-31');
  const [stockInNotes, setStockInNotes] = useState<string>('');

  // Form states for Manual Adjustment / Usage
  const [adjustQty, setAdjustQty] = useState<number>(1);
  const [adjustType, setAdjustType] = useState<TransactionType>('usage_study');
  const [adjustBatch, setAdjustBatch] = useState<string>('');
  const [adjustPatientToken, setAdjustPatientToken] = useState<string>('');
  const [adjustNotes, setAdjustNotes] = useState<string>('');

  // Form states for New Item SKU
  const [newItemCode, setNewItemCode] = useState('');
  const [newItemName, setNewItemName] = useState('');
  const [newItemGeneric, setNewItemGeneric] = useState('');
  const [newItemCategory, setNewItemCategory] = useState<InventoryCategory>('contrast_ct');
  const [newItemModality, setNewItemModality] = useState<'CT' | 'MRI' | 'XRAY' | 'US' | 'ALL'>('CT');
  const [newItemUnit, setNewItemUnit] = useState('Vial (100mL)');
  const [newItemStock, setNewItemStock] = useState(20);
  const [newItemMin, setNewItemMin] = useState(10);
  const [newItemCost, setNewItemCost] = useState(4000);
  const [newItemSelling, setNewItemSelling] = useState(6000);
  const [newItemSupplier, setNewItemSupplier] = useState('GE Healthcare PK');
  const [newItemLocation, setNewItemLocation] = useState('CT Console Bay');
  const [newItemBatchNum, setNewItemBatchNum] = useState('');
  const [newItemExpiry, setNewItemExpiry] = useState('2027-12-31');

  // Form states for Adverse Reaction Incident Report
  const [advToken, setAdvToken] = useState('');
  const [advPatient, setAdvPatient] = useState('');
  const [advModality, setAdvModality] = useState<'CT' | 'MRI'>('CT');
  const [advAgent, setAdvAgent] = useState('Omnipaque 350 (Iohexol)');
  const [advBatch, setAdvBatch] = useState('OPQ-2025-08');
  const [advVolume, setAdvVolume] = useState('80 mL');
  const [advSeverity, setAdvSeverity] = useState<AdverseSeverity>('mild');
  const [advSymptomsInput, setAdvSymptomsInput] = useState('Mild urticaria, transient nausea, peripheral pruritus');
  const [advTreatment, setAdvTreatment] = useState('Inj. Avil 2mL IV administered slowly + 30 min vital signs observation.');
  const [advOutcome, setAdvOutcome] = useState<AdverseOutcome>('resolved_on_site');
  const [advSupervisor, setAdvSupervisor] = useState('Dr. Shahzad Mir, FRCR');
  const [advNotes, setAdvNotes] = useState('Patient stabilized with normal blood pressure and discharged accompanied by attendant.');

  // High-Level KPI Computations
  const totalSkuCount = inventoryItems.length;
  const lowStockItems = useMemo(
    () => inventoryItems.filter(item => item.currentStock <= item.minThreshold),
    [inventoryItems]
  );
  const lowStockCount = lowStockItems.length;

  const totalValuationCost = useMemo(
    () => inventoryItems.reduce((acc, curr) => acc + curr.currentStock * curr.unitCost, 0),
    [inventoryItems]
  );

  const totalValuationSelling = useMemo(
    () => inventoryItems.reduce((acc, curr) => acc + curr.currentStock * curr.sellingPrice, 0),
    [inventoryItems]
  );

  const contrastVialsTotal = useMemo(() => {
    return inventoryItems
      .filter(i => i.category === 'contrast_ct' || i.category === 'contrast_mri')
      .reduce((acc, curr) => acc + curr.currentStock, 0);
  }, [inventoryItems]);

  const emergencyPharmacyCount = useMemo(() => {
    return inventoryItems
      .filter(i => i.category === 'pharmacy_emergency')
      .reduce((acc, curr) => acc + curr.currentStock, 0);
  }, [inventoryItems]);

  // Filtered inventory list
  const filteredItems = useMemo(() => {
    return inventoryItems.filter(item => {
      // Subtab filter
      if (activeSubTab === 'contrast') {
        if (item.category !== 'contrast_ct' && item.category !== 'contrast_mri') return false;
      } else if (activeSubTab === 'syringes') {
        if (item.category !== 'cannula_syringes' && item.category !== 'ppe_safety') return false;
      } else if (activeSubTab === 'emergency') {
        if (item.category !== 'pharmacy_emergency') return false;
      }

      // Modality filter
      if (selectedModalityFilter !== 'ALL') {
        if (item.modality !== selectedModalityFilter && item.modality !== 'ALL') return false;
      }

      // Stock status filter
      if (stockStatusFilter === 'LOW' && item.currentStock > item.minThreshold) {
        return false;
      }
      if (stockStatusFilter === 'IN_STOCK' && item.currentStock <= item.minThreshold) {
        return false;
      }

      // Search Query
      if (searchQuery.trim()) {
        const q = searchQuery.toLowerCase();
        const matchName = item.name.toLowerCase().includes(q);
        const matchCode = item.code.toLowerCase().includes(q);
        const matchGeneric = item.genericName.toLowerCase().includes(q);
        const matchBatch = item.batches.some(b => b.batchNumber.toLowerCase().includes(q));
        const matchSupplier = item.supplier.toLowerCase().includes(q);
        if (!matchName && !matchCode && !matchGeneric && !matchBatch && !matchSupplier) return false;
      }

      return true;
    });
  }, [inventoryItems, activeSubTab, selectedModalityFilter, stockStatusFilter, searchQuery]);

  // Handlers
  const handleOpenStockIn = (item: InventoryItem) => {
    setSelectedItemForAction(item);
    setStockInQty(10);
    setStockInBatch(`BAT-${Date.now().toString().slice(-6)}`);
    setStockInExpiry('2027-12-31');
    setStockInNotes(`Consignment restock for ${item.name}`);
    setIsStockInModalOpen(true);
  };

  const handleConfirmStockIn = () => {
    if (!selectedItemForAction || stockInQty <= 0) return;

    const newBatch = {
      batchNumber: stockInBatch.trim() || `BAT-${Date.now().toString().slice(-4)}`,
      expiryDate: stockInExpiry,
      quantity: stockInQty,
      receivedDate: new Date().toISOString().split('T')[0],
    };

    setInventoryItems(prev =>
      prev.map(item => {
        if (item.id !== selectedItemForAction.id) return item;
        return {
          ...item,
          currentStock: item.currentStock + stockInQty,
          batches: [newBatch, ...item.batches],
        };
      })
    );

    const newTx: InventoryTransaction = {
      id: `tx-${Date.now()}`,
      itemId: selectedItemForAction.id,
      itemName: selectedItemForAction.name,
      type: 'stock_in',
      quantity: stockInQty,
      batchNumber: newBatch.batchNumber,
      timestamp: 'Just now',
      performedBy: role === 'admin' ? 'Naveed Akhtar (Admin)' : 'Radiology Staff',
      notes: stockInNotes || `Received stock consignment (+${stockInQty} ${selectedItemForAction.unit})`,
    };

    setInventoryTransactions(prev => [newTx, ...prev]);
    setIsStockInModalOpen(false);
    setSelectedItemForAction(null);
  };

  const handleOpenAdjust = (item: InventoryItem) => {
    setSelectedItemForAction(item);
    setAdjustQty(1);
    setAdjustType('usage_study');
    setAdjustBatch(item.batches[0]?.batchNumber || 'N/A');
    setAdjustPatientToken('');
    setAdjustNotes('');
    setIsAdjustModalOpen(true);
  };

  const handleConfirmAdjust = () => {
    if (!selectedItemForAction || adjustQty <= 0) return;

    const isDeduction = ['usage_study', 'wastage', 'expired_discard'].includes(adjustType);
    const qtyChange = isDeduction ? -adjustQty : adjustQty;
    const finalStock = Math.max(0, selectedItemForAction.currentStock + qtyChange);

    setInventoryItems(prev =>
      prev.map(item => {
        if (item.id !== selectedItemForAction.id) return item;
        return {
          ...item,
          currentStock: finalStock,
        };
      })
    );

    const newTx: InventoryTransaction = {
      id: `tx-${Date.now()}`,
      itemId: selectedItemForAction.id,
      itemName: selectedItemForAction.name,
      type: adjustType,
      quantity: adjustQty,
      batchNumber: adjustBatch || selectedItemForAction.batches[0]?.batchNumber || 'GEN-BATCH',
      timestamp: 'Just now',
      performedBy: role === 'technologist' ? 'Kamran Ali (Lead Tech)' : 'Clinical Staff',
      tokenNumber: adjustPatientToken ? adjustPatientToken : undefined,
      notes: adjustNotes || `${adjustType.replace('_', ' ').toUpperCase()} recorded (${adjustQty} ${selectedItemForAction.unit})`,
    };

    setInventoryTransactions(prev => [newTx, ...prev]);
    setIsAdjustModalOpen(false);
    setSelectedItemForAction(null);
  };

  const handleCreateNewSku = () => {
    if (!newItemName.trim() || !newItemCode.trim()) {
      alert('Please fill out the SKU Code and Item Name.');
      return;
    }

    const newItem: InventoryItem = {
      id: `inv-${Date.now()}`,
      code: newItemCode.trim().toUpperCase(),
      name: newItemName.trim(),
      genericName: newItemGeneric.trim() || newItemName.trim(),
      category: newItemCategory,
      modality: newItemModality,
      unit: newItemUnit,
      currentStock: newItemStock,
      minThreshold: newItemMin,
      unitCost: newItemCost,
      sellingPrice: newItemSelling,
      supplier: newItemSupplier.trim() || 'Central Diagnostic Store',
      storageLocation: newItemLocation.trim() || 'Main Console Store',
      requiresColdChain: newItemCategory === 'contrast_mri',
      isBillable: newItemSelling > 0,
      batches: [
        {
          batchNumber: newItemBatchNum.trim() || `BAT-${new Date().getFullYear()}-01`,
          expiryDate: newItemExpiry,
          quantity: newItemStock,
          receivedDate: new Date().toISOString().split('T')[0],
        },
      ],
      notes: 'Manually created clinical inventory item.',
    };

    setInventoryItems(prev => [newItem, ...prev]);

    const newTx: InventoryTransaction = {
      id: `tx-${Date.now()}`,
      itemId: newItem.id,
      itemName: newItem.name,
      type: 'stock_in',
      quantity: newItemStock,
      batchNumber: newItem.batches[0].batchNumber,
      timestamp: 'Just now',
      performedBy: 'System Administrator',
      notes: `Initial opening inventory stock created.`,
    };

    setInventoryTransactions(prev => [newTx, ...prev]);
    setIsNewItemModalOpen(false);
    // Reset form
    setNewItemName('');
    setNewItemCode('');
  };

  const handleCreateAdverseReaction = () => {
    if (!advToken.trim() || !advPatient.trim()) {
      alert('Please enter the patient name and token number.');
      return;
    }

    const report: AdverseReactionReport = {
      id: `adv-${Date.now()}`,
      tokenNumber: advToken.trim().toUpperCase(),
      patientName: advPatient.trim(),
      modality: advModality,
      contrastAgent: advAgent,
      batchNumber: advBatch.trim() || 'OPQ-2025-08',
      administeredVolume: advVolume.trim() || '80 mL',
      severity: advSeverity,
      symptoms: advSymptomsInput.split(',').map(s => s.trim()).filter(Boolean),
      treatmentGiven: advTreatment.trim(),
      outcome: advOutcome,
      reportedBy: role === 'technologist' ? 'Kamran Ali (Lead Tech)' : 'Waqas Ahmed (CT Tech)',
      reportedAt: 'Just now',
      supervisingDoctor: advSupervisor.trim() || 'Dr. Shahzad Mir, FRCR',
      notes: advNotes.trim(),
    };

    setAdverseReactions(prev => [report, ...prev]);
    setIsAdverseModalOpen(false);

    // Reset
    setAdvToken('');
    setAdvPatient('');
    alert(`Adverse reaction report recorded successfully for ${report.patientName} (${report.tokenNumber}).`);
  };

  const handleExportCSV = () => {
    const headers = ['Item Code', 'Item Name', 'Generic Name', 'Category', 'Modality', 'Current Stock', 'Unit', 'Min Threshold', 'Unit Cost (PKR)', 'Selling Price (PKR)', 'Valuation (PKR)', 'Supplier', 'Primary Batch', 'Expiry Date'];
    const rows = inventoryItems.map(i => [
      `"${i.code}"`,
      `"${i.name}"`,
      `"${i.genericName}"`,
      `"${i.category}"`,
      `"${i.modality}"`,
      i.currentStock,
      `"${i.unit}"`,
      i.minThreshold,
      i.unitCost,
      i.sellingPrice,
      i.currentStock * i.unitCost,
      `"${i.supplier}"`,
      `"${i.batches[0]?.batchNumber || 'N/A'}"`,
      `"${i.batches[0]?.expiryDate || 'N/A'}"`,
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `ADC_Radiology_Inventory_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  return (
    <div className="space-y-6 pb-12 max-w-[1680px] mx-auto px-2 sm:px-4 lg:px-6 animate-in fade-in duration-200">
      {/* Top Banner / Module Title */}
      <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 bg-gradient-to-r from-slate-900 via-slate-800 to-cyan-950 p-5 sm:p-6 rounded-2xl text-white shadow-md border border-slate-700">
        <div>
          <div className="flex items-center space-x-2.5">
            <div className="p-2.5 rounded-xl bg-cyan-500/20 text-cyan-300 border border-cyan-400/30">
              <Boxes className="w-6 h-6" />
            </div>
            <div>
              <div className="flex items-center space-x-2">
                <h1 className="text-xl sm:text-2xl font-black tracking-tight text-white">
                  Contrast Media & Clinical Consumables
                </h1>
                <span className="bg-cyan-500/20 text-cyan-300 text-xs px-2.5 py-0.5 rounded-full font-mono font-bold border border-cyan-400/30">
                  Pharmacy & Stock
                </span>
              </div>
              <p className="text-xs sm:text-sm text-slate-300 mt-1">
                Real-time stock tracking for Non-Ionic CT Contrast, MRI Gadolinium agents, Power Injector Syringes, Safety Cannulas & Crash Cart Emergency Drugs.
              </p>
            </div>
          </div>
        </div>

        {/* Top Header Quick Action Buttons */}
        <div className="flex flex-wrap items-center gap-2">
          <button
            onClick={() => setIsAdverseModalOpen(true)}
            className="flex items-center space-x-2 px-3.5 py-2 bg-rose-600/90 hover:bg-rose-600 text-white rounded-xl text-xs font-bold transition shadow-xs border border-rose-400/40 cursor-pointer"
          >
            <ShieldAlert className="w-4 h-4" />
            <span>Log Adverse Reaction</span>
          </button>

          <button
            onClick={() => setIsNewItemModalOpen(true)}
            className="flex items-center space-x-2 px-3.5 py-2 bg-cyan-600 hover:bg-cyan-500 text-white rounded-xl text-xs font-bold transition shadow-xs border border-cyan-400/40 cursor-pointer"
          >
            <Plus className="w-4 h-4" />
            <span>Add New SKU</span>
          </button>

          <button
            onClick={handleExportCSV}
            className="flex items-center space-x-1.5 px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl text-xs font-bold transition border border-slate-600 cursor-pointer"
          >
            <Download className="w-4 h-4 text-cyan-400" />
            <span>Export Stock CSV</span>
          </button>
        </div>
      </div>

      {/* KPI Stats Overview Cards */}
      <div className="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        {/* Card 1: Contrast Vials in Stock */}
        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Contrast Stock</span>
            <div className="p-1.5 rounded-lg bg-cyan-50 text-cyan-700 border border-cyan-100">
              <Package className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2">
            <div className="text-2xl font-black text-slate-900 tracking-tight">{contrastVialsTotal} <span className="text-xs font-semibold text-slate-400">Vials</span></div>
            <p className="text-[11px] text-cyan-700 font-semibold mt-0.5 flex items-center space-x-1">
              <ThermometerSnowflake className="w-3 h-3 text-cyan-600" />
              <span>CT & MRI Macrocyclic Ready</span>
            </p>
          </div>
        </div>

        {/* Card 2: Low Stock Alerts */}
        <div
          onClick={() => {
            setStockStatusFilter(stockStatusFilter === 'LOW' ? 'ALL' : 'LOW');
            setActiveSubTab('all_items');
          }}
          className={`p-4 rounded-xl border shadow-xs flex flex-col justify-between cursor-pointer transition ${
            lowStockCount > 0
              ? 'bg-amber-50/70 border-amber-300 hover:bg-amber-100/70'
              : 'bg-white border-slate-200'
          }`}
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-amber-800 uppercase tracking-wider">Reorder Alerts</span>
            <div className="p-1.5 rounded-lg bg-amber-100 text-amber-800 border border-amber-200">
              <AlertTriangle className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2">
            <div className="text-2xl font-black text-amber-900 tracking-tight">
              {lowStockCount} <span className="text-xs font-semibold text-amber-700">SKUs Below Min</span>
            </div>
            <p className="text-[11px] text-amber-800 font-medium mt-0.5">
              {lowStockCount > 0 ? 'Click to filter reorder list' : 'All items at optimal levels'}
            </p>
          </div>
        </div>

        {/* Card 3: Crash Cart Emergency Drugs */}
        <div className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Crash Cart Drugs</span>
            <div className="p-1.5 rounded-lg bg-rose-50 text-rose-700 border border-rose-100">
              <HeartPulse className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2">
            <div className="text-2xl font-black text-slate-900 tracking-tight">
              {emergencyPharmacyCount} <span className="text-xs font-semibold text-slate-400">Doses</span>
            </div>
            <p className="text-[11px] text-emerald-600 font-semibold mt-0.5 flex items-center space-x-1">
              <ShieldCheck className="w-3.5 h-3.5 text-emerald-500" />
              <span>Anaphylaxis Kits 100% Stocked</span>
            </p>
          </div>
        </div>

        {/* Card 4: Adverse Reaction Incidents */}
        <div
          onClick={() => setActiveSubTab('adverse')}
          className="bg-white p-4 rounded-xl border border-slate-200 shadow-xs flex flex-col justify-between cursor-pointer hover:border-slate-300 transition"
        >
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-slate-500 uppercase tracking-wider">Adverse Events</span>
            <div className="p-1.5 rounded-lg bg-purple-50 text-purple-700 border border-purple-100">
              <ShieldAlert className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2">
            <div className="text-2xl font-black text-purple-900 tracking-tight">
              {adverseReactions.length} <span className="text-xs font-semibold text-slate-400">Reports</span>
            </div>
            <p className="text-[11px] text-purple-700 font-semibold mt-0.5">
              100% Resolved On-Site (Zero ICU)
            </p>
          </div>
        </div>

        {/* Card 5: Inventory Valuation (PKR) */}
        <div className="col-span-2 lg:col-span-1 bg-gradient-to-br from-slate-900 to-cyan-950 p-4 rounded-xl text-white shadow-xs flex flex-col justify-between border border-cyan-900/40">
          <div className="flex items-center justify-between">
            <span className="text-xs font-bold text-cyan-300 uppercase tracking-wider">Total Stock Value</span>
            <div className="p-1.5 rounded-lg bg-white/10 text-cyan-300 border border-white/20">
              <DollarSign className="w-4 h-4" />
            </div>
          </div>
          <div className="mt-2">
            <div className="text-xl sm:text-2xl font-black text-white tracking-tight">
              Rs. {(totalValuationCost / 1000).toFixed(1)}k
            </div>
            <p className="text-[11px] text-slate-300 font-medium mt-0.5">
              Billing Potential: Rs. {(totalValuationSelling / 1000).toFixed(1)}k
            </p>
          </div>
        </div>
      </div>

      {/* Navigation Sub-Tabs & Global Search Controls */}
      <div className="bg-white rounded-2xl border border-slate-200 p-3 sm:p-4 shadow-xs space-y-4">
        <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-3 border-b border-slate-100 pb-3">
          {/* Sub-tab Pill Selectors */}
          <div className="flex flex-wrap items-center gap-1.5">
            {[
              { id: 'contrast', label: 'Contrast Media Vials', icon: Package, count: inventoryItems.filter(i => i.category === 'contrast_ct' || i.category === 'contrast_mri').length },
              { id: 'syringes', label: 'Syringes & Hardware', icon: Syringe, count: inventoryItems.filter(i => i.category === 'cannula_syringes' || i.category === 'ppe_safety').length },
              { id: 'emergency', label: 'Crash Cart Drugs', icon: HeartPulse, count: inventoryItems.filter(i => i.category === 'pharmacy_emergency').length },
              { id: 'all_items', label: 'All Catalog SKUs', icon: Layers, count: totalSkuCount },
              { id: 'movements', label: 'Usage & Restock Log', icon: RotateCcw, count: inventoryTransactions.length },
              { id: 'adverse', label: 'Adverse Reaction Registry', icon: ShieldAlert, count: adverseReactions.length, highlight: adverseReactions.length > 0 },
            ].map(tab => {
              const Icon = tab.icon;
              const isActive = activeSubTab === tab.id;
              return (
                <button
                  key={tab.id}
                  onClick={() => {
                    setActiveSubTab(tab.id as SubTab);
                    if (tab.id !== 'all_items' && stockStatusFilter === 'LOW') {
                      setStockStatusFilter('ALL');
                    }
                  }}
                  className={`flex items-center space-x-2 px-3 py-2 rounded-xl text-xs font-bold transition-all cursor-pointer ${
                    isActive
                      ? 'bg-cyan-600 text-white shadow-xs'
                      : 'bg-slate-50 hover:bg-slate-100 text-slate-700 hover:text-slate-900 border border-slate-200/80'
                  }`}
                >
                  <Icon className="w-3.5 h-3.5 shrink-0" />
                  <span>{tab.label}</span>
                  <span
                    className={`text-[10px] px-1.5 py-0.2 rounded-full font-mono ${
                      isActive
                        ? 'bg-white/20 text-white'
                        : tab.highlight
                        ? 'bg-rose-100 text-rose-800 font-bold'
                        : 'bg-slate-200 text-slate-700'
                    }`}
                  >
                    {tab.count}
                  </span>
                </button>
              );
            })}
          </div>

          {/* Secondary Quick Filter */}
          <div className="flex items-center space-x-2">
            <span className="text-xs text-slate-400 font-medium">Modality:</span>
            {(['ALL', 'CT', 'MRI', 'XRAY'] as const).map(mod => (
              <button
                key={mod}
                onClick={() => setSelectedModalityFilter(mod)}
                className={`text-[11px] font-bold px-2 py-1 rounded-lg transition-colors cursor-pointer ${
                  selectedModalityFilter === mod
                    ? 'bg-slate-800 text-white'
                    : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                }`}
              >
                {mod}
              </button>
            ))}
          </div>
        </div>

        {/* Search & Stock Filter Bar */}
        {activeSubTab !== 'movements' && activeSubTab !== 'adverse' && (
          <div className="flex flex-col sm:flex-row items-center justify-between gap-3">
            <div className="relative w-full sm:max-w-md">
              <Search className="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
              <input
                type="text"
                placeholder="Search contrast by brand, generic, batch #, or supplier..."
                value={searchQuery}
                onChange={e => setSearchQuery(e.target.value)}
                className="w-full pl-9 pr-4 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:outline-none focus:ring-2 focus:ring-cyan-500/20 focus:border-cyan-500 text-slate-900 placeholder:text-slate-400 font-medium"
              />
              {searchQuery && (
                <button
                  onClick={() => setSearchQuery('')}
                  className="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              )}
            </div>

            <div className="flex items-center space-x-2 w-full sm:w-auto justify-end">
              <button
                onClick={() => setStockStatusFilter('ALL')}
                className={`px-2.5 py-1.5 rounded-lg text-xs font-semibold cursor-pointer ${
                  stockStatusFilter === 'ALL'
                    ? 'bg-cyan-50 text-cyan-800 border border-cyan-200'
                    : 'text-slate-600 hover:bg-slate-50'
                }`}
              >
                All Levels ({inventoryItems.length})
              </button>
              <button
                onClick={() => setStockStatusFilter('LOW')}
                className={`px-2.5 py-1.5 rounded-lg text-xs font-semibold flex items-center space-x-1 cursor-pointer ${
                  stockStatusFilter === 'LOW'
                    ? 'bg-amber-100 text-amber-900 border border-amber-300 font-bold'
                    : 'text-slate-600 hover:bg-slate-50'
                }`}
              >
                <AlertTriangle className="w-3.5 h-3.5 text-amber-600" />
                <span>Low Stock Reorders ({lowStockCount})</span>
              </button>
            </div>
          </div>
        )}
      </div>

      {/* VIEW 1: Main Inventory Items Grid & Table */}
      {(activeSubTab === 'contrast' || activeSubTab === 'syringes' || activeSubTab === 'emergency' || activeSubTab === 'all_items') && (
        <div className="space-y-4">
          {filteredItems.length === 0 ? (
            <div className="bg-white rounded-2xl border border-slate-200 p-12 text-center text-slate-500">
              <Boxes className="w-12 h-12 mx-auto text-slate-300 mb-3" />
              <h3 className="text-base font-bold text-slate-800">No Inventory Items Found</h3>
              <p className="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                No stock matched your active filters or search term "{searchQuery}". Try clearing filters or adding a new SKU.
              </p>
              <button
                onClick={() => {
                  setSearchQuery('');
                  setStockStatusFilter('ALL');
                  setSelectedModalityFilter('ALL');
                }}
                className="mt-4 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl transition cursor-pointer"
              >
                Reset All Filters
              </button>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
              {filteredItems.map(item => {
                const isLow = item.currentStock <= item.minThreshold;
                const isCritical = item.currentStock <= Math.floor(item.minThreshold / 2);
                const primaryBatch = item.batches[0];

                return (
                  <div
                    key={item.id}
                    className={`bg-white rounded-2xl border transition-all duration-150 p-4 sm:p-5 flex flex-col justify-between shadow-xs ${
                      isCritical
                        ? 'border-rose-300 ring-1 ring-rose-200'
                        : isLow
                        ? 'border-amber-300'
                        : 'border-slate-200 hover:border-slate-300'
                    }`}
                  >
                    <div>
                      {/* Top Header of Card */}
                      <div className="flex items-start justify-between gap-2">
                        <div className="space-y-1">
                          <div className="flex items-center space-x-2">
                            <span className="font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200">
                              {item.code}
                            </span>
                            <span
                              className={`text-[10px] font-bold px-2 py-0.5 rounded-full ${
                                item.modality === 'CT'
                                  ? 'bg-orange-100 text-orange-800'
                                  : item.modality === 'MRI'
                                  ? 'bg-purple-100 text-purple-800'
                                  : 'bg-cyan-100 text-cyan-800'
                              }`}
                            >
                              {item.modality}
                            </span>
                            {item.requiresColdChain && (
                              <span className="text-[10px] bg-sky-50 text-sky-700 border border-sky-200 px-1.5 py-0.5 rounded flex items-center space-x-1">
                                <ThermometerSnowflake className="w-2.5 h-2.5" />
                                <span>Cold</span>
                              </span>
                            )}
                          </div>
                          <h3 className="font-bold text-sm text-slate-900 leading-tight">
                            {item.name}
                          </h3>
                          <p className="text-xs text-slate-500 font-medium">
                            {item.genericName}
                          </p>
                        </div>

                        {/* Current Stock Badge */}
                        <div className="text-right shrink-0">
                          <div
                            className={`text-2xl font-black tracking-tight leading-none ${
                              isCritical ? 'text-rose-600' : isLow ? 'text-amber-600' : 'text-slate-900'
                            }`}
                          >
                            {item.currentStock}
                          </div>
                          <span className="text-[10px] font-bold text-slate-400 block mt-0.5 uppercase">
                            {item.unit}
                          </span>
                        </div>
                      </div>

                      {/* Stock Level Progress Indicator */}
                      <div className="mt-3">
                        <div className="flex items-center justify-between text-[11px] mb-1">
                          <span className="text-slate-500 font-medium">Stock Status</span>
                          <span
                            className={`font-bold ${
                              isCritical ? 'text-rose-600' : isLow ? 'text-amber-600' : 'text-emerald-700'
                            }`}
                          >
                            {isCritical
                              ? 'CRITICAL LOW'
                              : isLow
                              ? `Low Stock (Min: ${item.minThreshold})`
                              : 'Healthy Stock'}
                          </span>
                        </div>
                        <div className="w-full h-2 bg-slate-100 rounded-full overflow-hidden">
                          <div
                            className={`h-full rounded-full transition-all duration-300 ${
                              isCritical ? 'bg-rose-500' : isLow ? 'bg-amber-500' : 'bg-emerald-500'
                            }`}
                            style={{
                              width: `${Math.min(100, Math.max(10, (item.currentStock / (item.minThreshold * 2)) * 100))}%`,
                            }}
                          />
                        </div>
                      </div>

                      {/* Batch & Storage Info */}
                      <div className="mt-3.5 pt-3 border-t border-slate-100 grid grid-cols-2 gap-2 text-xs">
                        <div className="bg-slate-50 p-2 rounded-lg border border-slate-100">
                          <span className="text-[10px] text-slate-400 block uppercase font-bold">Active Batch</span>
                          <span className="font-mono font-bold text-slate-800 text-[11px]">
                            {primaryBatch ? primaryBatch.batchNumber : 'N/A'}
                          </span>
                          <span className="text-[10px] text-slate-500 block mt-0.5">
                            Exp: {primaryBatch ? primaryBatch.expiryDate : 'N/A'}
                          </span>
                        </div>

                        <div className="bg-slate-50 p-2 rounded-lg border border-slate-100">
                          <span className="text-[10px] text-slate-400 block uppercase font-bold">Pricing / Unit</span>
                          <span className="font-bold text-slate-800 text-[11px]">
                            Cost: Rs. {item.unitCost.toLocaleString()}
                          </span>
                          {item.sellingPrice > 0 ? (
                            <span className="text-[10px] text-cyan-700 font-bold block mt-0.5">
                              Sell: Rs. {item.sellingPrice.toLocaleString()}
                            </span>
                          ) : (
                            <span className="text-[10px] text-slate-400 block mt-0.5">Internal Only</span>
                          )}
                        </div>
                      </div>

                      <div className="mt-2 text-[11px] text-slate-500 flex items-center justify-between">
                        <span className="truncate">📍 {item.storageLocation}</span>
                        <span className="truncate text-slate-400">{item.supplier}</span>
                      </div>
                    </div>

                    {/* Action Buttons */}
                    <div className="mt-4 pt-3 border-t border-slate-100 flex items-center gap-2">
                      <button
                        onClick={() => handleOpenAdjust(item)}
                        className="flex-1 flex items-center justify-center space-x-1 py-1.5 px-2.5 bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-xs rounded-xl transition cursor-pointer"
                      >
                        <Syringe className="w-3.5 h-3.5 text-slate-600" />
                        <span>Log Usage</span>
                      </button>

                      <button
                        onClick={() => handleOpenStockIn(item)}
                        className="flex-1 flex items-center justify-center space-x-1 py-1.5 px-2.5 bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs rounded-xl transition cursor-pointer shadow-xs"
                      >
                        <Plus className="w-3.5 h-3.5" />
                        <span>Restock</span>
                      </button>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </div>
      )}

      {/* VIEW 2: Usage & Restock Movement Transactions Log */}
      {activeSubTab === 'movements' && (
        <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
          <div className="p-4 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
              <h2 className="text-base font-bold text-slate-900">Inventory Transaction Audit Trail</h2>
              <p className="text-xs text-slate-500">
                Timestamped ledger of contrast injections, single-use syringe disposal, and warehouse restocks.
              </p>
            </div>
            <span className="text-xs bg-slate-100 text-slate-700 font-mono px-2.5 py-1 rounded-lg font-bold self-start">
              {inventoryTransactions.length} Total Operations
            </span>
          </div>

          <div className="overflow-x-auto">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 text-slate-600 uppercase font-bold text-[10px] tracking-wider border-b border-slate-200">
                <tr>
                  <th className="py-3 px-4">Timestamp</th>
                  <th className="py-3 px-4">Operation Type</th>
                  <th className="py-3 px-4">Item & SKU</th>
                  <th className="py-3 px-4">Quantity</th>
                  <th className="py-3 px-4">Batch Number</th>
                  <th className="py-3 px-4">Patient Token / Context</th>
                  <th className="py-3 px-4">Performed By</th>
                  <th className="py-3 px-4">Notes</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {inventoryTransactions.map(tx => (
                  <tr key={tx.id} className="hover:bg-slate-50/80 transition-colors">
                    <td className="py-3 px-4 text-slate-500 whitespace-nowrap">{tx.timestamp}</td>
                    <td className="py-3 px-4">
                      <span
                        className={`inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold ${
                          tx.type === 'stock_in'
                            ? 'bg-emerald-100 text-emerald-800'
                            : tx.type === 'usage_study'
                            ? 'bg-cyan-100 text-cyan-800'
                            : tx.type === 'wastage'
                            ? 'bg-rose-100 text-rose-800'
                            : 'bg-amber-100 text-amber-800'
                        }`}
                      >
                        {tx.type === 'stock_in' && <ArrowDownRight className="w-3 h-3 mr-1" />}
                        {tx.type === 'usage_study' && <ArrowUpRight className="w-3 h-3 mr-1" />}
                        {tx.type.replace('_', ' ').toUpperCase()}
                      </span>
                    </td>
                    <td className="py-3 px-4 font-bold text-slate-900">{tx.itemName}</td>
                    <td className="py-3 px-4 font-mono font-bold">
                      <span className={tx.type === 'stock_in' ? 'text-emerald-700' : 'text-slate-900'}>
                        {tx.type === 'stock_in' ? `+${tx.quantity}` : `-${tx.quantity}`}
                      </span>
                    </td>
                    <td className="py-3 px-4 font-mono text-slate-600">{tx.batchNumber}</td>
                    <td className="py-3 px-4">
                      {tx.tokenNumber ? (
                        <span className="font-mono bg-cyan-50 text-cyan-800 px-1.5 py-0.5 rounded font-bold border border-cyan-200">
                          #{tx.tokenNumber} {tx.patientName ? `(${tx.patientName})` : ''}
                        </span>
                      ) : (
                        <span className="text-slate-400">—</span>
                      )}
                    </td>
                    <td className="py-3 px-4 text-slate-600">{tx.performedBy}</td>
                    <td className="py-3 px-4 text-slate-500 max-w-xs truncate" title={tx.notes}>
                      {tx.notes || '—'}
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      )}

      {/* VIEW 3: Adverse Reaction Incident Hub */}
      {activeSubTab === 'adverse' && (
        <div className="space-y-4">
          <div className="bg-rose-50 border border-rose-200 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div className="flex items-start space-x-3">
              <div className="p-2 bg-rose-500 text-white rounded-xl shadow-xs">
                <ShieldAlert className="w-5 h-5" />
              </div>
              <div>
                <h3 className="font-bold text-sm text-rose-950">ACR / ESUR Contrast Reaction Registry</h3>
                <p className="text-xs text-rose-800 mt-0.5">
                  Official clinical adverse incident documentation according to Pakistan Radiation & Drug Authority protocols.
                </p>
              </div>
            </div>
            <button
              onClick={() => setIsAdverseModalOpen(true)}
              className="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl transition shadow-xs cursor-pointer flex items-center space-x-1.5 shrink-0 self-start sm:self-auto"
            >
              <Plus className="w-4 h-4" />
              <span>Record New Incident</span>
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            {adverseReactions.map(report => (
              <div
                key={report.id}
                className="bg-white rounded-2xl border border-slate-200 p-5 shadow-xs space-y-3.5 flex flex-col justify-between"
              >
                <div>
                  <div className="flex items-start justify-between gap-2">
                    <div>
                      <div className="flex items-center space-x-2">
                        <span className="font-mono bg-slate-100 text-slate-800 font-bold px-2 py-0.5 rounded text-xs">
                          Token: #{report.tokenNumber}
                        </span>
                        <span
                          className={`text-[10px] font-bold px-2 py-0.5 rounded-full uppercase ${
                            report.severity === 'severe_anaphylaxis'
                              ? 'bg-rose-100 text-rose-800'
                              : report.severity === 'moderate'
                              ? 'bg-amber-100 text-amber-800'
                              : 'bg-sky-100 text-sky-800'
                          }`}
                        >
                          {report.severity.replace('_', ' ')}
                        </span>
                        <span className="text-xs font-semibold text-slate-400">{report.reportedAt}</span>
                      </div>
                      <h4 className="font-bold text-base text-slate-900 mt-1.5">{report.patientName}</h4>
                      <p className="text-xs text-cyan-800 font-medium">
                        {report.modality} • {report.contrastAgent} ({report.administeredVolume}) • Batch: {report.batchNumber}
                      </p>
                    </div>
                  </div>

                  {/* Symptoms list */}
                  <div className="mt-3">
                    <span className="text-[10px] font-bold uppercase tracking-wider text-slate-400 block mb-1">
                      Reported Clinical Manifestations
                    </span>
                    <div className="flex flex-wrap gap-1.5">
                      {report.symptoms.map((sym, idx) => (
                        <span
                          key={idx}
                          className="bg-rose-50 text-rose-800 border border-rose-100 px-2 py-0.5 rounded-lg text-xs font-semibold"
                        >
                          {sym}
                        </span>
                      ))}
                    </div>
                  </div>

                  {/* Emergency Treatment Given */}
                  <div className="mt-3 bg-slate-50 p-3 rounded-xl border border-slate-100 space-y-1 text-xs">
                    <span className="text-[10px] font-bold uppercase text-slate-500 block">
                      Emergency Pharmacotherapy Administered
                    </span>
                    <p className="text-slate-800 font-medium leading-relaxed">{report.treatmentGiven}</p>
                  </div>
                </div>

                <div className="pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                  <div>
                    <span className="text-slate-400 block text-[10px]">Supervising Radiologist</span>
                    <span className="font-bold text-slate-800">{report.supervisingDoctor}</span>
                  </div>
                  <div className="text-right">
                    <span className="text-slate-400 block text-[10px]">Outcome Status</span>
                    <span className="font-bold text-emerald-700 flex items-center space-x-1">
                      <CheckCircle2 className="w-3.5 h-3.5 text-emerald-600" />
                      <span>{report.outcome.replace(/_/g, ' ').toUpperCase()}</span>
                    </span>
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      )}

      {/* MODAL 1: Stock In / Consignment Restock */}
      {isStockInModalOpen && selectedItemForAction && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-5 sm:p-6 space-y-4 border border-slate-200">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="font-bold text-base text-slate-900">Restock Consignment</h3>
                <p className="text-xs text-slate-500">{selectedItemForAction.name}</p>
              </div>
              <button
                onClick={() => setIsStockInModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="font-bold text-slate-700 block mb-1">
                  Received Quantity ({selectedItemForAction.unit})
                </label>
                <input
                  type="number"
                  min="1"
                  value={stockInQty}
                  onChange={e => setStockInQty(parseInt(e.target.value) || 0)}
                  className="w-full p-2.5 border border-slate-300 rounded-xl font-bold text-base text-slate-900"
                />
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Batch Number</label>
                  <input
                    type="text"
                    value={stockInBatch}
                    onChange={e => setStockInBatch(e.target.value)}
                    placeholder="e.g. OPQ-2026-99"
                    className="w-full p-2 border border-slate-300 rounded-xl font-mono"
                  />
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Expiry Date</label>
                  <input
                    type="date"
                    value={stockInExpiry}
                    onChange={e => setStockInExpiry(e.target.value)}
                    className="w-full p-2 border border-slate-300 rounded-xl"
                  />
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Invoice / Consignment Notes</label>
                <input
                  type="text"
                  value={stockInNotes}
                  onChange={e => setStockInNotes(e.target.value)}
                  placeholder="PO # / Delivery Challan reference..."
                  className="w-full p-2 border border-slate-300 rounded-xl"
                />
              </div>
            </div>

            <div className="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
              <button
                onClick={() => setIsStockInModalOpen(false)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmStockIn}
                className="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl shadow-xs"
              >
                Confirm Restock (+{stockInQty})
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 2: Manual Log Usage / Adjustment */}
      {isAdjustModalOpen && selectedItemForAction && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-md w-full p-5 sm:p-6 space-y-4 border border-slate-200">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="font-bold text-base text-slate-900">Record Consumable Usage</h3>
                <p className="text-xs text-slate-500">{selectedItemForAction.name}</p>
              </div>
              <button
                onClick={() => setIsAdjustModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div>
                <label className="font-bold text-slate-700 block mb-1">Reason / Transaction Type</label>
                <select
                  value={adjustType}
                  onChange={e => setAdjustType(e.target.value as TransactionType)}
                  className="w-full p-2 border border-slate-300 rounded-xl bg-white font-medium"
                >
                  <option value="usage_study">Clinical Scan Study Usage (Deduct)</option>
                  <option value="wastage">Accidental Spillage / Leakage (Deduct)</option>
                  <option value="expired_discard">Expired Date Discard (Deduct)</option>
                  <option value="adjustment">Physical Audit Adjustment</option>
                </select>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Quantity ({selectedItemForAction.unit})</label>
                  <input
                    type="number"
                    min="1"
                    value={adjustQty}
                    onChange={e => setAdjustQty(parseInt(e.target.value) || 1)}
                    className="w-full p-2 border border-slate-300 rounded-xl font-bold text-base"
                  />
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Patient Token # (Optional)</label>
                  <input
                    type="text"
                    value={adjustPatientToken}
                    onChange={e => setAdjustPatientToken(e.target.value)}
                    placeholder="e.g. CT-01"
                    className="w-full p-2 border border-slate-300 rounded-xl font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Clinical Notes</label>
                <input
                  type="text"
                  value={adjustNotes}
                  onChange={e => setAdjustNotes(e.target.value)}
                  placeholder="e.g. Injected 85mL via dual power injector..."
                  className="w-full p-2 border border-slate-300 rounded-xl"
                />
              </div>
            </div>

            <div className="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
              <button
                onClick={() => setIsAdjustModalOpen(false)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
              >
                Cancel
              </button>
              <button
                onClick={handleConfirmAdjust}
                className="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl shadow-xs"
              >
                Save Usage Log
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 3: Create New Item SKU */}
      {isNewItemModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-lg w-full p-5 sm:p-6 space-y-4 border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div>
                <h3 className="font-bold text-base text-slate-900">Add New Inventory SKU</h3>
                <p className="text-xs text-slate-500">Register new contrast agent or clinical consumable</p>
              </div>
              <button
                onClick={() => setIsNewItemModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">SKU Code *</label>
                  <input
                    type="text"
                    value={newItemCode}
                    onChange={e => setNewItemCode(e.target.value)}
                    placeholder="e.g. CT-ULTRA-370"
                    className="w-full p-2 border border-slate-300 rounded-xl font-mono uppercase"
                  />
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Modality</label>
                  <select
                    value={newItemModality}
                    onChange={e => setNewItemModality(e.target.value as any)}
                    className="w-full p-2 border border-slate-300 rounded-xl bg-white"
                  >
                    <option value="CT">CT Scan</option>
                    <option value="MRI">MRI</option>
                    <option value="XRAY">X-Ray</option>
                    <option value="US">Ultrasound</option>
                    <option value="ALL">All Modalities</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Brand Name *</label>
                <input
                  type="text"
                  value={newItemName}
                  onChange={e => setNewItemName(e.target.value)}
                  placeholder="e.g. Ultravist 370 mg I/mL (100mL)"
                  className="w-full p-2 border border-slate-300 rounded-xl"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Generic / Chemical Description</label>
                <input
                  type="text"
                  value={newItemGeneric}
                  onChange={e => setNewItemGeneric(e.target.value)}
                  placeholder="e.g. Iopromide Non-Ionic Low-Osmolar Contrast"
                  className="w-full p-2 border border-slate-300 rounded-xl"
                />
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Category</label>
                  <select
                    value={newItemCategory}
                    onChange={e => setNewItemCategory(e.target.value as any)}
                    className="w-full p-2 border border-slate-300 rounded-xl bg-white"
                  >
                    <option value="contrast_ct">CT Contrast Media</option>
                    <option value="contrast_mri">MRI Gadolinium Agent</option>
                    <option value="cannula_syringes">Syringes & Cannulas</option>
                    <option value="pharmacy_emergency">Crash Cart Emergency</option>
                    <option value="ppe_safety">PPE & Radiation Safety</option>
                    <option value="general_consumable">General Consumable</option>
                  </select>
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Unit Packaging</label>
                  <input
                    type="text"
                    value={newItemUnit}
                    onChange={e => setNewItemUnit(e.target.value)}
                    placeholder="Vial (100mL), Box (50)..."
                    className="w-full p-2 border border-slate-300 rounded-xl"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Opening Stock</label>
                  <input
                    type="number"
                    value={newItemStock}
                    onChange={e => setNewItemStock(parseInt(e.target.value) || 0)}
                    className="w-full p-2 border border-slate-300 rounded-xl font-bold"
                  />
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Min Reorder Alert</label>
                  <input
                    type="number"
                    value={newItemMin}
                    onChange={e => setNewItemMin(parseInt(e.target.value) || 0)}
                    className="w-full p-2 border border-slate-300 rounded-xl font-bold text-amber-700"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Cost Price (PKR)</label>
                  <input
                    type="number"
                    value={newItemCost}
                    onChange={e => setNewItemCost(parseInt(e.target.value) || 0)}
                    className="w-full p-2 border border-slate-300 rounded-xl"
                  />
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Billing Price (PKR)</label>
                  <input
                    type="number"
                    value={newItemSelling}
                    onChange={e => setNewItemSelling(parseInt(e.target.value) || 0)}
                    className="w-full p-2 border border-slate-300 rounded-xl text-cyan-800 font-bold"
                  />
                </div>
              </div>
            </div>

            <div className="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
              <button
                onClick={() => setIsNewItemModalOpen(false)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
              >
                Cancel
              </button>
              <button
                onClick={handleCreateNewSku}
                className="px-4 py-2 bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold rounded-xl shadow-xs"
              >
                Create SKU
              </button>
            </div>
          </div>
        </div>
      )}

      {/* MODAL 4: Record Adverse Reaction Incident */}
      {isAdverseModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white rounded-2xl shadow-xl max-w-lg w-full p-5 sm:p-6 space-y-4 border border-slate-200 max-h-[90vh] overflow-y-auto">
            <div className="flex items-center justify-between border-b border-slate-100 pb-3">
              <div className="flex items-center space-x-2">
                <div className="p-2 bg-rose-100 text-rose-800 rounded-lg">
                  <ShieldAlert className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="font-bold text-base text-slate-900">Document Adverse Reaction</h3>
                  <p className="text-xs text-slate-500">ACR / ESUR Contrast Reaction Protocol</p>
                </div>
              </div>
              <button
                onClick={() => setIsAdverseModalOpen(false)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="space-y-3 text-xs">
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Patient Token # *</label>
                  <input
                    type="text"
                    value={advToken}
                    onChange={e => setAdvToken(e.target.value)}
                    placeholder="e.g. CT-01"
                    className="w-full p-2 border border-slate-300 rounded-xl font-mono uppercase"
                  />
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Patient Name *</label>
                  <input
                    type="text"
                    value={advPatient}
                    onChange={e => setAdvPatient(e.target.value)}
                    placeholder="Full patient name..."
                    className="w-full p-2 border border-slate-300 rounded-xl"
                  />
                </div>
              </div>

              <div className="grid grid-cols-3 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Modality</label>
                  <select
                    value={advModality}
                    onChange={e => setAdvModality(e.target.value as any)}
                    className="w-full p-2 border border-slate-300 rounded-xl bg-white"
                  >
                    <option value="CT">CT Scan</option>
                    <option value="MRI">MRI</option>
                  </select>
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Severity Grade</label>
                  <select
                    value={advSeverity}
                    onChange={e => setAdvSeverity(e.target.value as any)}
                    className="w-full p-2 border border-slate-300 rounded-xl bg-white font-bold text-rose-800"
                  >
                    <option value="mild">Mild (Urticaria/Nausea)</option>
                    <option value="moderate">Moderate (Bronchospasm/Facial)</option>
                    <option value="severe_anaphylaxis">Severe Anaphylaxis</option>
                    <option value="extravasation">Extravasation</option>
                  </select>
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Volume Given</label>
                  <input
                    type="text"
                    value={advVolume}
                    onChange={e => setAdvVolume(e.target.value)}
                    placeholder="e.g. 80 mL"
                    className="w-full p-2 border border-slate-300 rounded-xl"
                  />
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Contrast Agent & Batch</label>
                <div className="grid grid-cols-2 gap-2">
                  <input
                    type="text"
                    value={advAgent}
                    onChange={e => setAdvAgent(e.target.value)}
                    placeholder="e.g. Omnipaque 350"
                    className="w-full p-2 border border-slate-300 rounded-xl"
                  />
                  <input
                    type="text"
                    value={advBatch}
                    onChange={e => setAdvBatch(e.target.value)}
                    placeholder="Batch # OPQ-2025-08"
                    className="w-full p-2 border border-slate-300 rounded-xl font-mono"
                  />
                </div>
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Symptoms Manifested (Comma separated)</label>
                <input
                  type="text"
                  value={advSymptomsInput}
                  onChange={e => setAdvSymptomsInput(e.target.value)}
                  placeholder="e.g. Diffuse urticaria, bronchospasm, nausea..."
                  className="w-full p-2 border border-slate-300 rounded-xl"
                />
              </div>

              <div>
                <label className="font-bold text-slate-700 block mb-1">Emergency Treatment Given</label>
                <textarea
                  rows={2}
                  value={advTreatment}
                  onChange={e => setAdvTreatment(e.target.value)}
                  placeholder="Inj. Avil, Inj. Hydrocortisone, O2 therapy..."
                  className="w-full p-2 border border-slate-300 rounded-xl"
                />
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Outcome</label>
                  <select
                    value={advOutcome}
                    onChange={e => setAdvOutcome(e.target.value as any)}
                    className="w-full p-2 border border-slate-300 rounded-xl bg-white"
                  >
                    <option value="resolved_on_site">Resolved On-Site</option>
                    <option value="under_observation">Under Active Observation</option>
                    <option value="referred_to_er">Referred to Emergency Dept</option>
                  </select>
                </div>
                <div>
                  <label className="font-bold text-slate-700 block mb-1">Supervising Doctor</label>
                  <input
                    type="text"
                    value={advSupervisor}
                    onChange={e => setAdvSupervisor(e.target.value)}
                    className="w-full p-2 border border-slate-300 rounded-xl"
                  />
                </div>
              </div>
            </div>

            <div className="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
              <button
                onClick={() => setIsAdverseModalOpen(false)}
                className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-xl"
              >
                Cancel
              </button>
              <button
                onClick={handleCreateAdverseReaction}
                className="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl shadow-xs"
              >
                File Clinical Incident
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};
