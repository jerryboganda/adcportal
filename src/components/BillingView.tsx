import React, { useState } from 'react';
import {
  CreditCard,
  DollarSign,
  Download,
  PlusCircle,
  Search,
  CheckCircle2,
  AlertCircle,
  Receipt,
  Printer,
  Wallet,
  Clock,
  Building2,
  Tag,
  FileSpreadsheet,
  Layers,
  ChevronRight,
  Sparkles,
  RefreshCw,
  Ban,
  History,
  ShieldCheck,
  Percent,
  Check
} from 'lucide-react';
import { Invoice, Appointment, Patient, InvoiceItem, InvoicePayment } from '../types';
import { generateInvoicePdf, generateShiftClosingPdf, ShiftClosingData } from '../utils/pdfGenerator';
import { PrintableInvoiceModal } from './PrintableInvoiceModal';

interface BillingViewProps {
  invoices: Invoice[];
  appointments: Appointment[];
  patients: Patient[];
  onRecordPayment: (
    invoiceId: string,
    amount: number,
    method: 'cash' | 'card' | 'bank' | 'mobile' | 'insurance',
    reference: string
  ) => void;
  onCreateInvoice: (
    appointmentId: string,
    discount: number,
    notes: string,
    extraItems?: InvoiceItem[],
    initialPayment?: { amount: number; method: 'cash' | 'card' | 'bank' | 'mobile' | 'insurance'; reference: string }
  ) => void;
  onAddInvoiceItem?: (invoiceId: string, item: InvoiceItem) => void;
  onVoidInvoice?: (invoiceId: string, reason: string) => void;
}

export const BillingView: React.FC<BillingViewProps> = ({
  invoices,
  appointments,
  patients,
  onRecordPayment,
  onCreateInvoice,
  onAddInvoiceItem,
  onVoidInvoice,
}) => {
  // Search & Filters
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState<string>('all');
  const [methodFilter, setMethodFilter] = useState<string>('all');
  const [dateFilter, setDateFilter] = useState<'today' | 'all'>('today');

  // Modals state
  const [paymentModalInvoice, setPaymentModalInvoice] = useState<Invoice | null>(null);
  const [payAmount, setPayAmount] = useState<number>(0);
  const [payMethod, setPayMethod] = useState<'cash' | 'card' | 'bank' | 'mobile' | 'insurance'>('cash');
  const [payRef, setPayRef] = useState('');
  const [cashTendered, setCashTendered] = useState<number>(0);

  // Full A4 Clean Printable Invoice Modal
  const [printableInvoice, setPrintableInvoice] = useState<Invoice | null>(null);

  // Thermal Slip Modal
  const [thermalReceiptInvoice, setThermalReceiptInvoice] = useState<Invoice | null>(null);

  // History Ledger Modal
  const [historyInvoice, setHistoryInvoice] = useState<Invoice | null>(null);

  // Add Item Modal
  const [addItemInvoice, setAddItemInvoice] = useState<Invoice | null>(null);
  const [newItemDesc, setNewItemDesc] = useState('');
  const [newItemPrice, setNewItemPrice] = useState<number>(1000);
  const [newItemQty, setNewItemQty] = useState<number>(1);

  // Void Invoice Modal
  const [voidInvoiceModal, setVoidInvoiceModal] = useState<Invoice | null>(null);
  const [voidReason, setVoidReason] = useState('Patient cancelled examination before acquisition');

  // Create Invoice Modal State
  const [createModalOpen, setCreateModalOpen] = useState(false);
  const [selectedAptId, setSelectedAptId] = useState('');
  const [discountType, setDiscountType] = useState<'fixed' | 'percent'>('fixed');
  const [discountValue, setDiscountValue] = useState<number>(0);
  const [discountReason, setDiscountReason] = useState('Doctor Courtesy / Referral');
  const [selectedAddons, setSelectedAddons] = useState<{ id: string; name: string; price: number; selected: boolean }[]>([
    { id: 'add-contrast', name: 'IV Contrast Omnipaque 50ml (Non-Ionic)', price: 2500, selected: false },
    { id: 'add-cannula', name: 'IV Cannula 20G & Infusion Tray Kit', price: 350, selected: false },
    { id: 'add-film', name: 'High-Definition Laser Film Print (14x17")', price: 500, selected: false },
    { id: 'add-stat', name: 'STAT Urgent 60-Minute Reporting Surcharge', price: 1000, selected: false },
    { id: 'add-media', name: 'DICOM Archive CD/DVD Disc Export', price: 200, selected: false },
  ]);
  const [isPanelBilling, setIsPanelBilling] = useState(false);
  const [panelProvider, setPanelProvider] = useState('Sehat Sahulat Program (Federal Panel)');
  const [panelAuthCode, setPanelAuthCode] = useState('');
  const [invoiceNotes, setInvoiceNotes] = useState('');
  const [collectUpfront, setCollectUpfront] = useState(true);
  const [upfrontMethod, setUpfrontMethod] = useState<'cash' | 'card' | 'bank' | 'mobile' | 'insurance'>('cash');
  const [upfrontAmount, setUpfrontAmount] = useState<number>(0);

  // Shift Closing & Reconciliation State
  const [shiftClosingOpen, setShiftClosingOpen] = useState(false);
  const [shiftCashier, setShiftCashier] = useState('Amina Khan (Billing Officer)');
  const [shiftName, setShiftName] = useState('Morning Shift (08:00 AM - 04:00 PM)');
  const [supervisorName, setSupervisorName] = useState('Mr. Tariq Mehmood (Accounts Incharge)');
  const [cardBatchRef, setCardBatchRef] = useState('POS-BATCH-88910');
  const [shiftNotes, setShiftNotes] = useState('Counter drawer balanced with physical cash count.');
  const [denominations, setDenominations] = useState<{ [key: number]: number }>({
    5000: 0,
    1000: 0,
    500: 0,
    100: 0,
    50: 0,
    20: 0,
    10: 0,
  });

  // Calculate Aggregates
  const totalInvoiced = invoices.filter(i => i.status !== 'void').reduce((acc, curr) => acc + curr.total, 0);
  const totalPaid = invoices.filter(i => i.status !== 'void').reduce((acc, curr) => acc + curr.paidTotal, 0);
  const totalDue = invoices.filter(i => i.status !== 'void').reduce((acc, curr) => acc + curr.balanceDue, 0);
  const totalDiscounts = invoices.filter(i => i.status !== 'void').reduce((acc, curr) => acc + curr.discountTotal, 0);

  // Collections by Tender
  const allPayments = invoices.flatMap(i => (i.status !== 'void' ? i.payments : []));
  const cashCollected = allPayments.filter(p => p.method === 'cash').reduce((sum, p) => sum + p.amount, 0);
  const cardCollected = allPayments.filter(p => p.method === 'card').reduce((sum, p) => sum + p.amount, 0);
  const bankCollected = allPayments.filter(p => p.method === 'bank').reduce((sum, p) => sum + p.amount, 0);
  const mobileCollected = allPayments.filter(p => p.method === 'mobile').reduce((sum, p) => sum + p.amount, 0);
  const insuranceCollected = allPayments.filter(p => p.method === 'insurance').reduce((sum, p) => sum + p.amount, 0);

  // Physical Cash Calculation for Shift Closing
  const physicalCashCounted = Object.entries(denominations).reduce(
    (sum, [denom, count]) => sum + Number(denom) * (count || 0),
    0
  );
  const cashDiscrepancy = physicalCashCounted - cashCollected;

  // Filter Invoices
  const filteredInvoices = invoices.filter((inv) => {
    const matchSearch =
      inv.invoiceNumber.toLowerCase().includes(searchTerm.toLowerCase()) ||
      inv.patient.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      inv.patient.mrn.toLowerCase().includes(searchTerm.toLowerCase()) ||
      inv.appointmentToken.toLowerCase().includes(searchTerm.toLowerCase()) ||
      inv.items.some(it => it.description.toLowerCase().includes(searchTerm.toLowerCase()));

    let matchStatus = true;
    if (statusFilter === 'paid') matchStatus = inv.status === 'paid';
    else if (statusFilter === 'partial') matchStatus = inv.status === 'partial';
    else if (statusFilter === 'issued') matchStatus = inv.status === 'issued';
    else if (statusFilter === 'void') matchStatus = inv.status === 'void';
    else if (statusFilter === 'panel') matchStatus = inv.notes?.toLowerCase().includes('panel') || inv.payments.some(p => p.method === 'insurance');

    let matchMethod = true;
    if (methodFilter !== 'all') {
      matchMethod = inv.payments.some(p => p.method === methodFilter);
    }

    return matchSearch && matchStatus && matchMethod;
  });

  // Eligible appointments for invoicing
  const existingInvoicedAptIds = new Set(invoices.map(i => i.appointmentId));
  const unInvoicedAppointments = appointments.filter(a => !existingInvoicedAptIds.has(a.id));

  // Handlers
  const handleOpenPayment = (inv: Invoice) => {
    setPaymentModalInvoice(inv);
    setPayAmount(inv.balanceDue);
    setCashTendered(inv.balanceDue);
    setPayMethod('cash');
    setPayRef(`RCP-${Date.now().toString().slice(-5)}`);
  };

  const submitPayment = () => {
    if (!paymentModalInvoice || payAmount <= 0) return;
    onRecordPayment(
      paymentModalInvoice.id,
      payAmount,
      payMethod,
      payRef || `REC-${Date.now().toString().slice(-6)}`
    );
    setPaymentModalInvoice(null);
  };

  const handleOpenThermalReceipt = (inv: Invoice) => {
    setThermalReceiptInvoice(inv);
  };

  const handlePrintReceipt = () => {
    window.print();
  };

  const submitNewInvoice = () => {
    if (!selectedAptId) return;
    const apt = appointments.find(a => a.id === selectedAptId);
    if (!apt) return;

    // Calculate discount
    let calculatedDiscount = 0;
    if (discountType === 'percent') {
      calculatedDiscount = Math.round((apt.service.price * Math.min(100, Math.max(0, discountValue))) / 100);
    } else {
      calculatedDiscount = Math.min(apt.service.price, Math.max(0, discountValue));
    }

    // Build extra items
    const extraItems: InvoiceItem[] = selectedAddons
      .filter(ad => ad.selected)
      .map(ad => ({
        id: `addon-${Date.now()}-${ad.id}`,
        description: ad.name,
        quantity: 1,
        unitPrice: ad.price,
        discount: 0,
        lineTotal: ad.price,
      }));

    const finalNotes = isPanelBilling
      ? `PANEL: ${panelProvider} | Auth #${panelAuthCode || 'DIRECT-VERIFIED'} | ${invoiceNotes}`
      : `${discountValue > 0 ? `Discount Reason: ${discountReason} | ` : ''}${invoiceNotes}`;

    const totalBeforeInitialPay = apt.service.price - calculatedDiscount + extraItems.reduce((s, i) => s + i.lineTotal, 0);

    const initialPayObj = collectUpfront && upfrontAmount > 0
      ? {
          amount: Math.min(totalBeforeInitialPay, upfrontAmount),
          method: upfrontMethod,
          reference: isPanelBilling ? `PANEL-AUTH-${panelAuthCode || '9910'}` : `POS-INIT-${Date.now().toString().slice(-4)}`,
        }
      : undefined;

    onCreateInvoice(selectedAptId, calculatedDiscount, finalNotes, extraItems, initialPayObj);

    // Reset modal
    setCreateModalOpen(false);
    setSelectedAptId('');
    setDiscountValue(0);
    setInvoiceNotes('');
    setIsPanelBilling(false);
    setPanelAuthCode('');
    setSelectedAddons(prev => prev.map(a => ({ ...a, selected: false })));
  };

  const submitAddItem = () => {
    if (!addItemInvoice || !newItemDesc.trim() || newItemPrice <= 0 || !onAddInvoiceItem) return;
    const item: InvoiceItem = {
      id: `item-${Date.now()}`,
      description: newItemDesc.trim(),
      quantity: newItemQty || 1,
      unitPrice: newItemPrice,
      discount: 0,
      lineTotal: newItemPrice * (newItemQty || 1),
    };
    onAddInvoiceItem(addItemInvoice.id, item);
    setAddItemInvoice(null);
    setNewItemDesc('');
    setNewItemPrice(1000);
    setNewItemQty(1);
  };

  const submitVoidInvoice = () => {
    if (!voidInvoiceModal || !onVoidInvoice) return;
    onVoidInvoice(voidInvoiceModal.id, voidReason);
    setVoidInvoiceModal(null);
  };

  const handleExportCsv = () => {
    const headers = ['Invoice #', 'Date', 'Patient Name', 'MRN', 'Token', 'Services', 'Net Total', 'Paid', 'Balance', 'Status', 'Payments'];
    const rows = filteredInvoices.map(i => [
      i.invoiceNumber,
      i.createdAt,
      `"${i.patient.name}"`,
      i.patient.mrn,
      i.appointmentToken,
      `"${i.items.map(it => it.description).join('; ')}"`,
      i.total,
      i.paidTotal,
      i.balanceDue,
      i.status.toUpperCase(),
      `"${i.payments.map(p => `${p.method}:${p.amount}`).join('; ')}"`,
    ]);

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `ADC-Invoices-${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  };

  const handleGenerateShiftClosingPdf = () => {
    const shiftData: ShiftClosingData = {
      shiftDate: new Date().toISOString().split('T')[0],
      shiftName,
      cashierName: shiftCashier,
      openedAt: '08:00 AM',
      closedAt: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
      invoicesCount: invoices.filter(i => i.status !== 'void').length,
      totalInvoiced,
      totalCollected: totalPaid,
      totalDiscounts,
      cashCollected,
      cardCollected,
      bankCollected,
      mobileCollected,
      insuranceCollected,
      countedCash: physicalCashCounted,
      discrepancy: cashDiscrepancy,
      cardSettlementRef: cardBatchRef,
      notes: shiftNotes,
      supervisorName,
    };
    generateShiftClosingPdf(shiftData);
  };

  return (
    <div className="space-y-6">
      {/* Top Banner & Primary Action Toolbar */}
      <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 bg-white p-5 rounded-2xl border border-slate-200 shadow-sm">
        <div>
          <div className="flex items-center space-x-2.5">
            <h1 className="text-xl font-bold text-slate-900 tracking-tight">Clinical Billing & Point of Sale (POS)</h1>
            <span className="bg-emerald-50 text-emerald-700 text-xs px-2.5 py-1 rounded-md border border-emerald-200 font-semibold whitespace-nowrap inline-flex items-center gap-1">
              <DollarSign className="w-3.5 h-3.5 text-emerald-600" /> Direct Invoicing & POS
            </span>
          </div>
          <p className="text-slate-500 text-xs mt-1">
            Automated procedure pricing, itemized consumables, multi-tender transactions, 80mm thermal receipts, and end-of-day register reconciliation.
          </p>
        </div>

        <div className="flex flex-wrap items-center gap-2.5">
          <button
            onClick={() => setShiftClosingOpen(true)}
            className="flex items-center space-x-1.5 px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-bold text-xs transition-all cursor-pointer shadow-xs"
            title="Daily Shift Cash Closing & Register Reconciliation"
          >
            <ShieldCheck className="w-4 h-4 text-cyan-600" />
            <span>Shift Cash Register</span>
          </button>

          <button
            onClick={handleExportCsv}
            className="flex items-center space-x-1.5 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-300 font-bold text-xs transition-all cursor-pointer shadow-xs"
            title="Export CSV Statement"
          >
            <FileSpreadsheet className="w-4 h-4 text-emerald-600" />
            <span>Export CSV</span>
          </button>

          <button
            onClick={() => {
              setCreateModalOpen(true);
              if (unInvoicedAppointments.length > 0) {
                setSelectedAptId(unInvoicedAppointments[0].id);
                setUpfrontAmount(unInvoicedAppointments[0].service.price);
              }
            }}
            className="flex items-center space-x-2 px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md shadow-emerald-600/20 transition-all cursor-pointer"
          >
            <PlusCircle className="w-4 h-4" />
            <span>Create Study Invoice</span>
          </button>
        </div>
      </div>

      {/* KPI Cards & Financial Metrics */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white p-4.5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs text-slate-500 font-semibold uppercase tracking-wider">Total Invoiced</span>
            <Receipt className="w-4 h-4 text-slate-400" />
          </div>
          <div className="text-2xl font-black text-slate-900 mt-1.5">Rs. {totalInvoiced.toLocaleString()}</div>
          <div className="text-[11px] text-slate-400 mt-1 flex items-center justify-between">
            <span>{invoices.filter(i => i.status !== 'void').length} active invoices</span>
            <span className="font-semibold text-slate-600">Discounts: Rs. {totalDiscounts.toLocaleString()}</span>
          </div>
        </div>

        <div className="bg-white p-4.5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs text-emerald-700 font-semibold uppercase tracking-wider">Total Collections (Paid)</span>
            <CheckCircle2 className="w-4 h-4 text-emerald-600" />
          </div>
          <div className="text-2xl font-black text-emerald-600 mt-1.5">Rs. {totalPaid.toLocaleString()}</div>
          <div className="text-[11px] text-emerald-600/80 font-medium mt-1">
            {totalInvoiced > 0 ? ((totalPaid / totalInvoiced) * 100).toFixed(1) : '100'}% collection efficiency
          </div>
        </div>

        <div className="bg-white p-4.5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs text-amber-700 font-semibold uppercase tracking-wider">Outstanding Receivables</span>
            <AlertCircle className="w-4 h-4 text-amber-600" />
          </div>
          <div className="text-2xl font-black text-amber-600 mt-1.5">Rs. {totalDue.toLocaleString()}</div>
          <div className="text-[11px] text-amber-600/80 font-medium mt-1">
            {invoices.filter(i => i.balanceDue > 0 && i.status !== 'void').length} pending study balances
          </div>
        </div>

        <div className="bg-white p-4.5 rounded-2xl border border-slate-200 shadow-sm">
          <div className="flex items-center justify-between">
            <span className="text-xs text-cyan-700 font-semibold uppercase tracking-wider">Cash in Counter Drawer</span>
            <Wallet className="w-4 h-4 text-cyan-600" />
          </div>
          <div className="text-2xl font-black text-cyan-600 mt-1.5">Rs. {cashCollected.toLocaleString()}</div>
          <div className="text-[11px] text-slate-500 mt-1">
            Card POS: <span className="font-semibold text-slate-800">Rs. {cardCollected.toLocaleString()}</span>
          </div>
        </div>
      </div>

      {/* Tender Channels Breakdown Banner */}
      <div className="bg-slate-50 border border-slate-200 rounded-2xl p-3.5 flex flex-wrap items-center justify-between gap-3 text-xs">
        <div className="flex items-center space-x-2 text-slate-600 font-semibold">
          <Layers className="w-4 h-4 text-slate-500" />
          <span>Shift Tender Breakdown:</span>
        </div>
        <div className="flex flex-wrap items-center gap-3">
          <span className="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 font-medium">
            💵 Cash: <strong className="text-emerald-700 font-mono">Rs. {cashCollected.toLocaleString()}</strong>
          </span>
          <span className="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 font-medium">
            💳 Card POS: <strong className="text-cyan-700 font-mono">Rs. {cardCollected.toLocaleString()}</strong>
          </span>
          <span className="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 font-medium">
            📱 Raast / Bank: <strong className="text-indigo-700 font-mono">Rs. {bankCollected.toLocaleString()}</strong>
          </span>
          <span className="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 font-medium">
            📲 Mobile: <strong className="text-purple-700 font-mono">Rs. {mobileCollected.toLocaleString()}</strong>
          </span>
          <span className="px-2.5 py-1 rounded-lg bg-white border border-slate-200 text-slate-700 font-medium">
            🏢 Panel: <strong className="text-amber-700 font-mono">Rs. {insuranceCollected.toLocaleString()}</strong>
          </span>
        </div>
      </div>

      {/* Search & Filter Toolbar */}
      <div className="bg-white p-4 rounded-2xl border border-slate-200 shadow-sm flex flex-col md:flex-row items-center justify-between gap-3">
        <div className="relative w-full md:w-80">
          <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
          <input
            type="text"
            value={searchTerm}
            onChange={(e) => setSearchTerm(e.target.value)}
            placeholder="Search invoice #, patient, MRN, token, service..."
            className="w-full bg-slate-50 text-slate-900 pl-9 pr-3 py-2 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500 shadow-xs"
          />
        </div>

        <div className="flex flex-wrap items-center gap-2.5 w-full md:w-auto">
          <div className="flex items-center space-x-1.5">
            <span className="text-xs text-slate-500 font-medium">Status:</span>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="bg-slate-50 text-slate-800 px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer"
            >
              <option value="all">All Invoices ({invoices.length})</option>
              <option value="paid">Paid in Full ({invoices.filter(i => i.status === 'paid').length})</option>
              <option value="partial">Partially Paid ({invoices.filter(i => i.status === 'partial').length})</option>
              <option value="issued">Unpaid / Issued ({invoices.filter(i => i.status === 'issued').length})</option>
              <option value="panel">Corporate / Panel</option>
              <option value="void">Voided / Cancelled</option>
            </select>
          </div>

          <div className="flex items-center space-x-1.5">
            <span className="text-xs text-slate-500 font-medium">Tender:</span>
            <select
              value={methodFilter}
              onChange={(e) => setMethodFilter(e.target.value)}
              className="bg-slate-50 text-slate-800 px-3 py-2 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer"
            >
              <option value="all">All Channels</option>
              <option value="cash">Cash Tender</option>
              <option value="card">Card POS</option>
              <option value="bank">Bank / Raast</option>
              <option value="mobile">Easypaisa / JazzCash</option>
              <option value="insurance">Insurance / Panel</option>
            </select>
          </div>
        </div>
      </div>

      {/* Invoices Table */}
      <div className="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div className="w-full overflow-x-hidden">
          <table className="w-full text-left text-xs table-auto">
            <thead className="bg-slate-100/90 text-slate-600 uppercase font-bold text-[11px] tracking-wider border-b border-slate-200">
              <tr>
                <th className="py-2.5 px-3.5 whitespace-nowrap">Invoice # / Date</th>
                <th className="py-2.5 px-3.5">Patient & MRN</th>
                <th className="py-2.5 px-3.5">Services & Consumables</th>
                <th className="py-2.5 px-3.5 whitespace-nowrap">Net Total</th>
                <th className="py-2.5 px-3.5 whitespace-nowrap">Paid / Balance</th>
                <th className="py-2.5 px-3.5 whitespace-nowrap">Status</th>
                <th className="py-2.5 px-3.5 text-right whitespace-nowrap">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-100 text-slate-700">
              {filteredInvoices.length === 0 ? (
                <tr>
                  <td colSpan={7} className="py-12 text-center text-slate-400">
                    <Receipt className="w-8 h-8 mx-auto text-slate-300 mb-2" />
                    <p className="font-semibold text-slate-600">No invoices match your query</p>
                    <p className="text-xs text-slate-400 mt-0.5">Try adjusting search keywords or status filter</p>
                  </td>
                </tr>
              ) : (
                filteredInvoices.map((inv) => {
                  const isVoided = inv.status === 'void';
                  const isPanel = inv.notes?.toLowerCase().includes('panel') || inv.payments.some(p => p.method === 'insurance');

                  return (
                    <tr
                      key={inv.id}
                      className={`hover:bg-slate-50/80 transition-colors ${
                        isVoided ? 'opacity-60 bg-slate-50/50 line-through decoration-slate-400' : ''
                      }`}
                    >
                      {/* Invoice Number & Date */}
                      <td className="py-2.5 px-3.5 whitespace-nowrap">
                        <div className="flex items-center space-x-1.5">
                          <span className="font-mono font-bold text-slate-900 text-xs">{inv.invoiceNumber}</span>
                          {isPanel && (
                            <span className="bg-indigo-50 text-indigo-700 text-[9px] px-1.5 py-0.5 rounded font-bold border border-indigo-200">
                              PANEL
                            </span>
                          )}
                        </div>
                        <div className="text-[10px] text-slate-500 flex items-center gap-1 mt-0.5">
                          <Clock className="w-3 h-3 text-slate-400" />
                          <span>{inv.createdAt}</span>
                        </div>
                      </td>

                      {/* Patient & MRN */}
                      <td className="py-2.5 px-3.5">
                        <div className="font-bold text-slate-900 truncate max-w-[160px]">{inv.patient.name}</div>
                        <div className="text-[10px] text-slate-500 font-mono whitespace-nowrap flex items-center gap-1">
                          <span>{inv.patient.mrn}</span>
                          <span>•</span>
                          <span className="font-bold text-cyan-700">#{inv.appointmentToken}</span>
                        </div>
                      </td>

                      {/* Services & Consumables */}
                      <td className="py-2.5 px-3.5">
                        <div className="space-y-0.5 max-w-[240px]">
                          {inv.items.map((item, idx) => (
                            <div key={item.id || idx} className="text-xs text-slate-800 font-medium flex items-center justify-between gap-2">
                              <span className="truncate" title={item.description}>
                                {item.quantity > 1 ? `${item.quantity}x ` : ''}
                                {item.description}
                              </span>
                              <span className="text-[10px] text-slate-500 font-mono whitespace-nowrap">
                                Rs. {item.lineTotal.toLocaleString()}
                              </span>
                            </div>
                          ))}
                          {inv.notes && (
                            <div className="text-[10px] text-slate-400 italic truncate" title={inv.notes}>
                              {inv.notes}
                            </div>
                          )}
                        </div>
                      </td>

                      {/* Net Total & Discount */}
                      <td className="py-2.5 px-3.5 whitespace-nowrap">
                        <div className="font-bold text-slate-900 font-mono text-xs">Rs. {inv.total.toLocaleString()}</div>
                        {inv.discountTotal > 0 && (
                          <div className="text-[9px] text-emerald-600 font-semibold flex items-center gap-0.5">
                            <Tag className="w-2.5 h-2.5" />
                            <span>Disc: -Rs. {inv.discountTotal.toLocaleString()}</span>
                          </div>
                        )}
                      </td>

                      {/* Paid / Balance */}
                      <td className="py-2.5 px-3.5 whitespace-nowrap">
                        <div className="text-emerald-700 font-mono font-bold text-xs">
                          Paid: Rs. {inv.paidTotal.toLocaleString()}
                        </div>
                        {inv.balanceDue > 0 ? (
                          <div className="text-amber-700 font-mono font-bold text-[10px]">
                            Due: Rs. {inv.balanceDue.toLocaleString()}
                          </div>
                        ) : (
                          <div className="text-[9px] text-slate-400 font-semibold">Cleared in Full</div>
                        )}
                      </td>

                      {/* Status */}
                      <td className="py-2.5 px-3.5 whitespace-nowrap">
                        <span
                          className={`inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold whitespace-nowrap ${
                            inv.status === 'paid'
                              ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                              : inv.status === 'partial'
                              ? 'bg-amber-50 text-amber-700 border border-amber-200'
                              : inv.status === 'void'
                              ? 'bg-slate-100 text-slate-500 border border-slate-300'
                              : 'bg-rose-50 text-rose-700 border border-rose-200'
                          }`}
                        >
                          {inv.status.toUpperCase()}
                        </span>
                      </td>

                      {/* Actions */}
                      <td className="py-2.5 px-3.5 text-right whitespace-nowrap">
                        <div className="flex items-center justify-end space-x-1.5">
                          {/* Collect button if balance > 0 */}
                          {!isVoided && inv.balanceDue > 0 && (
                            <button
                              onClick={() => handleOpenPayment(inv)}
                              className="px-2.5 py-1 rounded bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-[11px] transition-all shadow-xs cursor-pointer whitespace-nowrap inline-flex items-center gap-1"
                            >
                              <Wallet className="w-3 h-3" />
                              <span>Collect</span>
                            </button>
                          )}

                          {/* Print Standard Clean Invoice */}
                          <button
                            onClick={() => setPrintableInvoice(inv)}
                            title="Print Clean Tax Invoice (A4 / Letter)"
                            className="px-2 py-1 rounded bg-slate-800 hover:bg-slate-900 text-white font-medium text-[11px] border border-slate-700 transition-all shadow-xs cursor-pointer whitespace-nowrap inline-flex items-center gap-1"
                          >
                            <Printer className="w-3 h-3 text-cyan-400" />
                            <span>Print</span>
                          </button>

                          {/* Thermal POS Slip (80mm) */}
                          <button
                            onClick={() => handleOpenThermalReceipt(inv)}
                            title="Print 80mm Thermal Receipt"
                            className="p-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 transition-colors cursor-pointer"
                          >
                            <Receipt className="w-3.5 h-3.5" />
                          </button>

                          {/* Download PDF Invoice */}
                          <button
                            onClick={() => generateInvoicePdf(inv)}
                            title="Download Official A4 Tax Invoice (PDF)"
                            className="p-1.5 rounded bg-slate-100 hover:bg-slate-200 text-cyan-700 border border-slate-200 transition-colors cursor-pointer"
                          >
                            <Download className="w-3.5 h-3.5" />
                          </button>

                          {/* Add Item */}
                          {!isVoided && onAddInvoiceItem && (
                            <button
                              onClick={() => setAddItemInvoice(inv)}
                              title="Add Billable Consumable / Extra Item"
                              className="p-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 transition-colors cursor-pointer"
                            >
                              <PlusCircle className="w-3.5 h-3.5" />
                            </button>
                          )}

                          {/* Payment Ledger / History */}
                          <button
                            onClick={() => setHistoryInvoice(inv)}
                            title="View Payment Audit Ledger"
                            className="p-1.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-600 border border-slate-200 transition-colors cursor-pointer"
                          >
                            <History className="w-3.5 h-3.5" />
                          </button>

                          {/* Void Invoice */}
                          {!isVoided && onVoidInvoice && inv.paidTotal === 0 && (
                            <button
                              onClick={() => setVoidInvoiceModal(inv)}
                              title="Void Invoice"
                              className="p-1.5 rounded bg-slate-100 hover:bg-rose-50 text-rose-600 border border-slate-200 transition-colors cursor-pointer"
                            >
                              <Ban className="w-3.5 h-3.5" />
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

      {/* ------------------------------------------------------------ */}
      {/* MODAL 1: RECORD PAYMENT DRAWER / MODAL                       */}
      {/* ------------------------------------------------------------ */}
      {paymentModalInvoice && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2">
                <div className="w-8 h-8 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center font-bold">
                  <Wallet className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-base">Record Payment Receipt</h3>
                  <div className="text-[11px] text-slate-500 font-mono">{paymentModalInvoice.invoiceNumber}</div>
                </div>
              </div>
              <button
                onClick={() => setPaymentModalInvoice(null)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
              >
                ✕
              </button>
            </div>

            {/* Patient & Financial Summary Box */}
            <div className="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-1.5 text-xs">
              <div className="flex justify-between">
                <span className="text-slate-500">Patient:</span>
                <span className="font-bold text-slate-900">{paymentModalInvoice.patient.name}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">MRN & Token:</span>
                <span className="font-mono text-slate-700">{paymentModalInvoice.patient.mrn} • #{paymentModalInvoice.appointmentToken}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Total Invoiced:</span>
                <span className="font-mono font-semibold text-slate-800">Rs. {paymentModalInvoice.total.toLocaleString()}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-500">Already Paid:</span>
                <span className="font-mono font-bold text-emerald-600">Rs. {paymentModalInvoice.paidTotal.toLocaleString()}</span>
              </div>
              <div className="flex justify-between font-bold border-t border-slate-200 pt-1.5 text-amber-700">
                <span>Remaining Balance Due:</span>
                <span className="font-mono text-sm">Rs. {paymentModalInvoice.balanceDue.toLocaleString()}</span>
              </div>
            </div>

            {/* Payment Fields */}
            <div className="space-y-3">
              <div>
                <label className="block text-xs font-bold text-slate-800 mb-1">
                  Payment Amount to Collect (PKR) *
                </label>
                <div className="relative">
                  <input
                    type="number"
                    value={payAmount}
                    max={paymentModalInvoice.balanceDue}
                    onChange={(e) => {
                      const val = Number(e.target.value);
                      setPayAmount(val);
                      setCashTendered(val);
                    }}
                    className="w-full bg-white text-slate-900 font-mono font-bold text-lg p-3 rounded-xl border border-slate-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 shadow-xs"
                  />
                  <div className="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center space-x-1">
                    <button
                      type="button"
                      onClick={() => {
                        setPayAmount(paymentModalInvoice.balanceDue);
                        setCashTendered(paymentModalInvoice.balanceDue);
                      }}
                      className="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold cursor-pointer"
                    >
                      Full Balance
                    </button>
                    {paymentModalInvoice.balanceDue > 1000 && (
                      <button
                        type="button"
                        onClick={() => {
                          const half = Math.round(paymentModalInvoice.balanceDue / 2);
                          setPayAmount(half);
                          setCashTendered(half);
                        }}
                        className="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-bold cursor-pointer"
                      >
                        50%
                      </button>
                    )}
                  </div>
                </div>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Payment Channel / Tender Method
                </label>
                <select
                  value={payMethod}
                  onChange={(e) => setPayMethod(e.target.value as any)}
                  className="w-full bg-white text-slate-800 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-emerald-500 cursor-pointer shadow-xs"
                >
                  <option value="cash">💵 Cash (Counter Drawer)</option>
                  <option value="card">💳 Credit / Debit Card (POS Machine)</option>
                  <option value="bank">📱 Direct Bank Transfer / Raast Instant QR</option>
                  <option value="mobile">📲 Easypaisa / JazzCash</option>
                  <option value="insurance">🏢 Corporate / Insurance Panel Direct</option>
                </select>
              </div>

              {/* Cash Change Calculator (if method is cash) */}
              {payMethod === 'cash' && (
                <div className="bg-emerald-50/70 border border-emerald-200 p-3 rounded-xl space-y-2 text-xs">
                  <div className="flex items-center justify-between">
                    <span className="font-semibold text-emerald-800">Physical Cash Handed by Patient:</span>
                    <input
                      type="number"
                      value={cashTendered}
                      onChange={(e) => setCashTendered(Number(e.target.value))}
                      className="w-28 bg-white border border-emerald-300 rounded-lg p-1 text-right font-mono font-bold text-slate-900 focus:outline-none focus:ring-1 focus:ring-emerald-500"
                    />
                  </div>
                  <div className="flex justify-between items-center text-xs font-bold pt-1 border-t border-emerald-200/80">
                    <span className="text-emerald-900">Change to Return to Patient:</span>
                    <span className="font-mono text-sm text-emerald-700">
                      Rs. {Math.max(0, cashTendered - payAmount).toLocaleString()}
                    </span>
                  </div>
                </div>
              )}

              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Transaction / Slip Reference (Optional)
                </label>
                <input
                  type="text"
                  value={payRef}
                  onChange={(e) => setPayRef(e.target.value)}
                  placeholder="e.g. POS Auth Code, Raast Txn ID, Slip #"
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs focus:outline-none focus:ring-1 focus:ring-emerald-500 shadow-xs font-mono"
                />
              </div>
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setPaymentModalInvoice(null)}
                className="flex-1 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer shadow-xs"
              >
                Cancel
              </button>
              <button
                onClick={submitPayment}
                className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs cursor-pointer shadow-md shadow-emerald-600/20"
              >
                <CheckCircle2 className="w-4 h-4" />
                <span>Confirm Receipt</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ------------------------------------------------------------ */}
      {/* MODAL 2: CREATE STUDY INVOICE MODAL                          */}
      {/* ------------------------------------------------------------ */}
      {createModalOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-xl w-full p-6 shadow-2xl space-y-4 max-h-[92vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2">
                <div className="w-8 h-8 rounded-xl bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold">
                  <Receipt className="w-4 h-4" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-base">Generate New Study Invoice</h3>
                  <p className="text-[11px] text-slate-500">Automated procedure billing with itemized consumables & discounts</p>
                </div>
              </div>
              <button
                onClick={() => setCreateModalOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
              >
                ✕
              </button>
            </div>

            <div className="space-y-4">
              {/* Step 1: Select Appointment */}
              <div>
                <label className="block text-xs font-bold text-slate-800 mb-1">
                  1. Select Study / Appointment to Bill *
                </label>
                {unInvoicedAppointments.length === 0 ? (
                  <div className="p-3 rounded-xl bg-slate-50 border border-slate-200 text-slate-500 text-xs">
                    All currently scheduled studies already have invoices generated.
                  </div>
                ) : (
                  <select
                    value={selectedAptId}
                    onChange={(e) => {
                      setSelectedAptId(e.target.value);
                      const apt = appointments.find(a => a.id === e.target.value);
                      if (apt) {
                        setUpfrontAmount(apt.service.price);
                      }
                    }}
                    className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold focus:outline-none focus:ring-1 focus:ring-cyan-500 cursor-pointer shadow-xs"
                  >
                    <option value="">Select an appointment...</option>
                    {unInvoicedAppointments.map((apt) => (
                      <option key={apt.id} value={apt.id}>
                        [{apt.tokenNumber}] {apt.patient.name} ({apt.patient.mrn}) — {apt.service.name} (Rs. {apt.service.price.toLocaleString()})
                      </option>
                    ))}
                  </select>
                )}
              </div>

              {/* Step 2: Add-on Consumables / Surcharges */}
              <div>
                <label className="block text-xs font-bold text-slate-800 mb-1.5">
                  2. Optional Consumables & Service Add-ons
                </label>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                  {selectedAddons.map((addon, index) => (
                    <label
                      key={addon.id}
                      className={`flex items-center space-x-2.5 p-2.5 rounded-xl border text-xs cursor-pointer transition-all ${
                        addon.selected
                          ? 'bg-cyan-50/80 border-cyan-300 text-cyan-900 font-semibold'
                          : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'
                      }`}
                    >
                      <input
                        type="checkbox"
                        checked={addon.selected}
                        onChange={(e) => {
                          const checked = e.target.checked;
                          setSelectedAddons(prev =>
                            prev.map((a, i) => (i === index ? { ...a, selected: checked } : a))
                          );
                        }}
                        className="rounded text-cyan-600 focus:ring-cyan-500 cursor-pointer"
                      />
                      <div className="flex-1 truncate">
                        <div className="truncate">{addon.name}</div>
                        <div className="font-mono text-[11px] text-cyan-700 font-bold">+Rs. {addon.price.toLocaleString()}</div>
                      </div>
                    </label>
                  ))}
                </div>
              </div>

              {/* Step 3: Corporate Panel / Insurance Option */}
              <div className="bg-slate-50 p-3.5 rounded-2xl border border-slate-200 space-y-2.5">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2">
                    <Building2 className="w-4 h-4 text-indigo-600" />
                    <span className="text-xs font-bold text-slate-800">Corporate Panel / Insurance Scheme</span>
                  </div>
                  <label className="relative inline-flex items-center cursor-pointer">
                    <input
                      type="checkbox"
                      checked={isPanelBilling}
                      onChange={(e) => setIsPanelBilling(e.target.checked)}
                      className="sr-only peer"
                    />
                    <div className="w-9 h-5 bg-slate-300 peer-focus:outline-none rounded-sm peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-sm after:h-4 after:w-4 after:transition-all peer-checked:bg-indigo-600"></div>
                  </label>
                </div>

                {isPanelBilling && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-1 text-xs">
                    <div>
                      <label className="block text-[11px] font-semibold text-slate-600 mb-1">Panel Provider *</label>
                      <select
                        value={panelProvider}
                        onChange={(e) => setPanelProvider(e.target.value)}
                        className="w-full bg-white text-slate-800 p-2 rounded-lg border border-slate-300 text-xs font-semibold focus:ring-1 focus:ring-indigo-500"
                      >
                        <option value="Sehat Sahulat Program (Federal Panel)">Sehat Sahulat Program (Federal Panel)</option>
                        <option value="State Life Insurance Corp">State Life Insurance Corp</option>
                        <option value="Armed Forces / Fauji Foundation">Armed Forces / Fauji Foundation Panel</option>
                        <option value="EOBI / Social Security">EOBI / Social Security</option>
                        <option value="Allied Bank Corporate Panel">Allied Bank Corporate Panel</option>
                        <option value="OGDCL Medical Panel">OGDCL Medical Panel</option>
                        <option value="Pak Red Crescent">Pak Red Crescent Society</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-[11px] font-semibold text-slate-600 mb-1">Approval / Policy #</label>
                      <input
                        type="text"
                        value={panelAuthCode}
                        onChange={(e) => setPanelAuthCode(e.target.value)}
                        placeholder="e.g. SSP-PK-88219"
                        className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs font-mono focus:ring-1 focus:ring-indigo-500"
                      />
                    </div>
                  </div>
                )}
              </div>

              {/* Step 4: Discounts & Concessions */}
              <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Discount Type</label>
                  <select
                    value={discountType}
                    onChange={(e) => setDiscountType(e.target.value as any)}
                    className="w-full bg-white text-slate-800 p-2.5 rounded-xl border border-slate-300 text-xs focus:ring-1 focus:ring-cyan-500"
                  >
                    <option value="fixed">Fixed PKR Amount</option>
                    <option value="percent">Percentage (%)</option>
                  </select>
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">
                    {discountType === 'percent' ? 'Discount Percentage (%)' : 'Discount (PKR)'}
                  </label>
                  <input
                    type="number"
                    min={0}
                    value={discountValue}
                    onChange={(e) => setDiscountValue(Number(e.target.value))}
                    placeholder="0"
                    className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-mono font-bold focus:ring-1 focus:ring-cyan-500 shadow-xs"
                  />
                </div>

                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Concession Reason</label>
                  <select
                    value={discountReason}
                    onChange={(e) => setDiscountReason(e.target.value)}
                    className="w-full bg-white text-slate-800 p-2.5 rounded-xl border border-slate-300 text-xs focus:ring-1 focus:ring-cyan-500"
                  >
                    <option value="Doctor Courtesy / Referral">Doctor Courtesy / Referral</option>
                    <option value="Staff / Family Concession">Staff / Family Concession</option>
                    <option value="Senior Citizen Concession">Senior Citizen Concession</option>
                    <option value="Zakat / Deserving Patient">Zakat / Deserving Patient Fund</option>
                    <option value="Promotional Health Scheme">Promotional Health Scheme</option>
                  </select>
                </div>
              </div>

              {/* Step 5: Remarks / Notes */}
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">
                  Billing Remarks / Notes (Optional)
                </label>
                <input
                  type="text"
                  value={invoiceNotes}
                  onChange={(e) => setInvoiceNotes(e.target.value)}
                  placeholder="e.g. Special contrast protocol, VIP walk-in..."
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs focus:ring-1 focus:ring-cyan-500 shadow-xs"
                />
              </div>

              {/* Step 6: Upfront Payment Collection */}
              <div className="bg-emerald-50/70 border border-emerald-200 p-3.5 rounded-2xl space-y-2 text-xs">
                <div className="flex items-center justify-between">
                  <div className="flex items-center space-x-2 text-emerald-900 font-bold">
                    <Wallet className="w-4 h-4 text-emerald-700" />
                    <span>Collect Immediate Payment at Creation?</span>
                  </div>
                  <input
                    type="checkbox"
                    checked={collectUpfront}
                    onChange={(e) => setCollectUpfront(e.target.checked)}
                    className="rounded text-emerald-600 focus:ring-emerald-500 cursor-pointer"
                  />
                </div>

                {collectUpfront && (
                  <div className="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1">
                    <div>
                      <label className="block text-[11px] font-semibold text-slate-600 mb-1">Payment Method</label>
                      <select
                        value={upfrontMethod}
                        onChange={(e) => setUpfrontMethod(e.target.value as any)}
                        className="w-full bg-white text-slate-800 p-2 rounded-lg border border-slate-300 text-xs font-semibold"
                      >
                        <option value="cash">💵 Cash (Counter Drawer)</option>
                        <option value="card">💳 Card (POS Machine)</option>
                        <option value="bank">📱 Raast / Bank Transfer</option>
                        <option value="mobile">📲 Easypaisa / JazzCash</option>
                        <option value="insurance">🏢 Corporate / Insurance Panel</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-[11px] font-semibold text-slate-600 mb-1">Amount to Collect (PKR)</label>
                      <input
                        type="number"
                        value={upfrontAmount}
                        onChange={(e) => setUpfrontAmount(Number(e.target.value))}
                        className="w-full bg-white text-slate-900 p-2 rounded-lg border border-slate-300 text-xs font-mono font-bold"
                      />
                    </div>
                  </div>
                )}
              </div>
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setCreateModalOpen(false)}
                className="flex-1 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer shadow-xs"
              >
                Cancel
              </button>
              <button
                disabled={!selectedAptId}
                onClick={submitNewInvoice}
                className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2.5 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs cursor-pointer shadow-md disabled:opacity-50"
              >
                <Receipt className="w-4 h-4" />
                <span>Generate Official Invoice</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ------------------------------------------------------------ */}
      {/* MODAL 3: 80MM THERMAL RECEIPT PREVIEW (PRINT)                */}
      {/* ------------------------------------------------------------ */}
      {thermalReceiptInvoice && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-sm w-full p-5 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-200 pb-2.5">
              <div className="flex items-center space-x-2">
                <Printer className="w-4 h-4 text-slate-700" />
                <h3 className="font-bold text-slate-900 text-sm">80mm POS Thermal Slip Preview</h3>
              </div>
              <button
                onClick={() => setThermalReceiptInvoice(null)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer text-xs"
              >
                ✕
              </button>
            </div>

            {/* Thermal Slip Visual Simulation */}
            <div className="bg-slate-50 border border-dashed border-slate-300 p-4 rounded-xl font-mono text-[11px] text-slate-800 space-y-2 shadow-inner">
              {/* Slip Header */}
              <div className="text-center space-y-0.5 border-b border-dashed border-slate-300 pb-2">
                <div className="font-bold text-xs text-slate-900">AMAD DIAGNOSTIC CENTRE</div>
                <div className="text-[10px] text-slate-500">Radiology & Advanced Imaging</div>
                <div className="text-[9px] text-slate-400">Plot 14-B, Islamabad | Tel: 051-2223344</div>
                <div className="text-[9px] text-slate-400">NTN: 8492019-3 | ISO 9001:2015</div>
              </div>

              {/* Patient & Token Info */}
              <div className="border-b border-dashed border-slate-300 py-1.5 space-y-0.5 text-[10px]">
                <div className="flex justify-between">
                  <span>INVOICE #:</span>
                  <span className="font-bold">{thermalReceiptInvoice.invoiceNumber}</span>
                </div>
                <div className="flex justify-between">
                  <span>TOKEN #:</span>
                  <span className="font-bold text-cyan-700">#{thermalReceiptInvoice.appointmentToken}</span>
                </div>
                <div className="flex justify-between">
                  <span>MRN / PATIENT:</span>
                  <span className="font-bold truncate max-w-[130px]">{thermalReceiptInvoice.patient.name}</span>
                </div>
                <div className="flex justify-between text-slate-500">
                  <span>DATE/TIME:</span>
                  <span>{thermalReceiptInvoice.createdAt}</span>
                </div>
              </div>

              {/* Items List */}
              <div className="border-b border-dashed border-slate-300 py-1.5 space-y-1 text-[10px]">
                {thermalReceiptInvoice.items.map((it, idx) => (
                  <div key={idx} className="flex justify-between">
                    <span className="truncate max-w-[150px]">{it.description}</span>
                    <span className="font-bold">Rs. {it.lineTotal.toLocaleString()}</span>
                  </div>
                ))}
              </div>

              {/* Totals */}
              <div className="py-1 space-y-0.5 text-[11px]">
                <div className="flex justify-between">
                  <span>SUBTOTAL:</span>
                  <span>Rs. {thermalReceiptInvoice.subtotal.toLocaleString()}</span>
                </div>
                {thermalReceiptInvoice.discountTotal > 0 && (
                  <div className="flex justify-between text-emerald-700">
                    <span>DISCOUNT:</span>
                    <span>-Rs. {thermalReceiptInvoice.discountTotal.toLocaleString()}</span>
                  </div>
                )}
                <div className="flex justify-between font-bold text-slate-900 text-xs pt-1 border-t border-slate-300">
                  <span>TOTAL AMOUNT:</span>
                  <span>Rs. {thermalReceiptInvoice.total.toLocaleString()}</span>
                </div>
                <div className="flex justify-between font-bold text-emerald-700">
                  <span>PAID TOTAL:</span>
                  <span>Rs. {thermalReceiptInvoice.paidTotal.toLocaleString()}</span>
                </div>
                {thermalReceiptInvoice.balanceDue > 0 ? (
                  <div className="flex justify-between font-bold text-amber-700">
                    <span>BALANCE DUE:</span>
                    <span>Rs. {thermalReceiptInvoice.balanceDue.toLocaleString()}</span>
                  </div>
                ) : (
                  <div className="text-center font-bold text-emerald-700 py-0.5 text-[10px] bg-emerald-50 rounded">
                    *** PAID IN FULL ***
                  </div>
                )}
              </div>

              {/* Barcode representation */}
              <div className="text-center pt-2 border-t border-dashed border-slate-300 space-y-1">
                <div className="tracking-widest font-black text-xs text-slate-600">||||| | |||| |||||| || |</div>
                <div className="text-[9px] text-slate-400 font-sans">
                  Keep receipt for report dispatch. Reports available on portal with MRN.
                </div>
              </div>
            </div>

            <div className="flex space-x-2 pt-1">
              <button
                onClick={() => setThermalReceiptInvoice(null)}
                className="flex-1 px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer"
              >
                Close
              </button>
              <button
                onClick={handlePrintReceipt}
                className="flex-1 flex items-center justify-center space-x-1.5 px-3 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs cursor-pointer shadow-md"
              >
                <Printer className="w-3.5 h-3.5" />
                <span>Print Slip</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ------------------------------------------------------------ */}
      {/* MODAL 4: ADD EXTRA BILLABLE ITEM / CONSUMABLE                 */}
      {/* ------------------------------------------------------------ */}
      {addItemInvoice && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2">
                <PlusCircle className="w-5 h-5 text-cyan-600" />
                <h3 className="font-bold text-slate-900 text-base">Add Consumable / Extra Charge</h3>
              </div>
              <button
                onClick={() => setAddItemInvoice(null)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
              >
                ✕
              </button>
            </div>

            <div className="space-y-3">
              <div>
                <label className="block text-xs font-semibold text-slate-700 mb-1">Item Description *</label>
                <input
                  type="text"
                  value={newItemDesc}
                  onChange={(e) => setNewItemDesc(e.target.value)}
                  placeholder="e.g. Extra Film Printing 14x17, IV Contrast 100ml"
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs focus:ring-1 focus:ring-cyan-500 shadow-xs"
                />
              </div>

              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Unit Price (PKR) *</label>
                  <input
                    type="number"
                    min={0}
                    value={newItemPrice}
                    onChange={(e) => setNewItemPrice(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-mono font-bold focus:ring-1 focus:ring-cyan-500 shadow-xs"
                  />
                </div>
                <div>
                  <label className="block text-xs font-semibold text-slate-700 mb-1">Quantity</label>
                  <input
                    type="number"
                    min={1}
                    value={newItemQty}
                    onChange={(e) => setNewItemQty(Number(e.target.value))}
                    className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-mono font-bold focus:ring-1 focus:ring-cyan-500 shadow-xs"
                  />
                </div>
              </div>
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setAddItemInvoice(null)}
                className="flex-1 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer"
              >
                Cancel
              </button>
              <button
                disabled={!newItemDesc.trim() || newItemPrice <= 0}
                onClick={submitAddItem}
                className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2 rounded-xl bg-cyan-600 hover:bg-cyan-500 text-white font-bold text-xs cursor-pointer shadow-md disabled:opacity-50"
              >
                <PlusCircle className="w-4 h-4" />
                <span>Add to Invoice</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ------------------------------------------------------------ */}
      {/* MODAL 5: PAYMENT TRANSACTION LEDGER AUDIT                    */}
      {/* ------------------------------------------------------------ */}
      {historyInvoice && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2">
                <History className="w-5 h-5 text-indigo-600" />
                <div>
                  <h3 className="font-bold text-slate-900 text-base">Payment Audit Ledger</h3>
                  <div className="text-[11px] text-slate-500 font-mono">{historyInvoice.invoiceNumber}</div>
                </div>
              </div>
              <button
                onClick={() => setHistoryInvoice(null)}
                className="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
              >
                ✕
              </button>
            </div>

            <div className="space-y-2.5 max-h-72 overflow-y-auto">
              {historyInvoice.payments.length === 0 ? (
                <div className="py-6 text-center text-slate-400 text-xs">
                  No payments recorded against this invoice yet.
                </div>
              ) : (
                historyInvoice.payments.map((p, idx) => (
                  <div key={p.id || idx} className="p-3 rounded-xl bg-slate-50 border border-slate-200 space-y-1 text-xs">
                    <div className="flex justify-between items-center font-bold">
                      <span className="text-slate-900">Receipt #{idx + 1}</span>
                      <span className="text-emerald-700 font-mono text-sm">Rs. {p.amount.toLocaleString()}</span>
                    </div>
                    <div className="flex justify-between text-slate-500 text-[11px]">
                      <span>Method: <strong className="text-slate-700 uppercase">{p.method}</strong></span>
                      <span>{p.paidAt}</span>
                    </div>
                    <div className="flex justify-between text-slate-500 text-[10px]">
                      <span>Ref: <span className="font-mono">{p.reference || 'N/A'}</span></span>
                      <span>Cashier: {p.receivedBy}</span>
                    </div>
                  </div>
                ))
              )}
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setHistoryInvoice(null)}
                className="flex-1 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer"
              >
                Close Ledger
              </button>
              <button
                onClick={() => {
                  const inv = historyInvoice;
                  setHistoryInvoice(null);
                  setPrintableInvoice(inv);
                }}
                className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs cursor-pointer shadow-md"
              >
                <Printer className="w-3.5 h-3.5 text-cyan-400" />
                <span>Print Invoice</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ------------------------------------------------------------ */}
      {/* MODAL 6: VOID INVOICE MODAL                                  */}
      {/* ------------------------------------------------------------ */}
      {voidInvoiceModal && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-md w-full p-6 shadow-2xl space-y-4 animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center space-x-2 text-rose-600 border-b border-slate-200 pb-3">
              <Ban className="w-5 h-5" />
              <h3 className="font-bold text-slate-900 text-base">Void Invoice {voidInvoiceModal.invoiceNumber}</h3>
            </div>

            <p className="text-xs text-slate-600">
              Are you sure you want to void this invoice for <strong>{voidInvoiceModal.patient.name}</strong>? This action is logged for financial auditing.
            </p>

            <div>
              <label className="block text-xs font-semibold text-slate-700 mb-1">Reason for Voiding *</label>
              <select
                value={voidReason}
                onChange={(e) => setVoidReason(e.target.value)}
                className="w-full bg-white text-slate-800 p-2.5 rounded-xl border border-slate-300 text-xs font-semibold"
              >
                <option value="Patient cancelled examination before acquisition">Patient cancelled examination before acquisition</option>
                <option value="Duplicate invoice mistakenly generated">Duplicate invoice mistakenly generated</option>
                <option value="Incorrect patient selected">Incorrect patient selected</option>
                <option value="Changed to panel billing with separate authorization">Changed to panel billing with separate authorization</option>
              </select>
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setVoidInvoiceModal(null)}
                className="flex-1 px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer"
              >
                Back
              </button>
              <button
                onClick={submitVoidInvoice}
                className="flex-1 px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs cursor-pointer shadow-md"
              >
                Confirm Void
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ------------------------------------------------------------ */}
      {/* MODAL 7: DAILY SHIFT CASH CLOSING & POS RECONCILIATION       */}
      {/* ------------------------------------------------------------ */}
      {shiftClosingOpen && (
        <div className="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4">
          <div className="bg-white border border-slate-200 rounded-3xl max-w-2xl w-full p-6 shadow-2xl space-y-4 max-h-[92vh] overflow-y-auto animate-in fade-in zoom-in-95 duration-150">
            <div className="flex items-center justify-between border-b border-slate-200 pb-3">
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-2xl bg-cyan-100 text-cyan-700 flex items-center justify-center font-bold">
                  <ShieldCheck className="w-5 h-5" />
                </div>
                <div>
                  <h3 className="font-bold text-slate-900 text-base">Daily Shift Cash Closing & Reconciliation</h3>
                  <p className="text-[11px] text-slate-500">Official cash register drawer audit & POS terminal batch settlement</p>
                </div>
              </div>
              <button
                onClick={() => setShiftClosingOpen(false)}
                className="p-1.5 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 cursor-pointer"
              >
                ✕
              </button>
            </div>

            {/* Shift Details Form */}
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2.5 text-xs">
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-1">Shift Name</label>
                <input
                  type="text"
                  value={shiftName}
                  onChange={(e) => setShiftName(e.target.value)}
                  className="w-full bg-slate-50 text-slate-800 p-2 rounded-xl border border-slate-300 text-xs font-semibold"
                />
              </div>
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-1">Cashier On Duty</label>
                <input
                  type="text"
                  value={shiftCashier}
                  onChange={(e) => setShiftCashier(e.target.value)}
                  className="w-full bg-slate-50 text-slate-800 p-2 rounded-xl border border-slate-300 text-xs font-semibold"
                />
              </div>
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-1">Accounts Supervisor</label>
                <input
                  type="text"
                  value={supervisorName}
                  onChange={(e) => setSupervisorName(e.target.value)}
                  className="w-full bg-slate-50 text-slate-800 p-2 rounded-xl border border-slate-300 text-xs font-semibold"
                />
              </div>
            </div>

            {/* System Revenue Summary Box */}
            <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-2 text-xs">
              <div className="font-bold text-slate-900 flex justify-between border-b border-slate-200 pb-1.5">
                <span>System Collections Summary</span>
                <span>PKR Amount</span>
              </div>
              <div className="flex justify-between">
                <span className="text-slate-600">Total Invoiced (Gross):</span>
                <span className="font-mono font-semibold">Rs. {totalInvoiced.toLocaleString()}</span>
              </div>
              <div className="flex justify-between text-emerald-700">
                <span>Total Discounts Given:</span>
                <span className="font-mono font-semibold">- Rs. {totalDiscounts.toLocaleString()}</span>
              </div>
              <div className="flex justify-between font-bold text-slate-900 border-t border-slate-200 pt-1">
                <span>Total Shift Collections Realized:</span>
                <span className="font-mono text-sm text-emerald-600">Rs. {totalPaid.toLocaleString()}</span>
              </div>
            </div>

            {/* Physical Cash Drawer Denomination Counter */}
            <div className="bg-cyan-50/50 p-4 rounded-2xl border border-cyan-200 space-y-3">
              <div className="flex items-center justify-between">
                <div className="flex items-center space-x-1.5 text-cyan-900 font-bold text-xs">
                  <Wallet className="w-4 h-4 text-cyan-700" />
                  <span>Physical Cash Drawer Denomination Count</span>
                </div>
                <div className="text-[11px] text-cyan-800 font-semibold">
                  Expected in Drawer: <strong className="font-mono">Rs. {cashCollected.toLocaleString()}</strong>
                </div>
              </div>

              <div className="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                {[5000, 1000, 500, 100, 50, 20, 10].map((denom) => (
                  <div key={denom} className="bg-white p-2 rounded-xl border border-cyan-200 flex items-center justify-between shadow-2xs">
                    <span className="font-bold font-mono text-slate-700">x Rs.{denom}</span>
                    <input
                      type="number"
                      min={0}
                      value={denominations[denom] || ''}
                      placeholder="0"
                      onChange={(e) => {
                        const val = Math.max(0, Number(e.target.value));
                        setDenominations(prev => ({ ...prev, [denom]: val }));
                      }}
                      className="w-14 bg-slate-50 border border-slate-300 rounded p-1 text-center font-mono font-bold text-slate-900 text-xs focus:ring-1 focus:ring-cyan-500"
                    />
                  </div>
                ))}
              </div>

              {/* Physical Count vs System Variance Box */}
              <div className="bg-white p-3 rounded-xl border border-cyan-200 flex flex-wrap items-center justify-between gap-2 text-xs">
                <div>
                  <span className="text-slate-500">Physical Counted Cash: </span>
                  <strong className="font-mono text-sm text-slate-900">Rs. {physicalCashCounted.toLocaleString()}</strong>
                </div>
                <div>
                  <span className="text-slate-500">Variance: </span>
                  {cashDiscrepancy === 0 ? (
                    <span className="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold font-mono text-xs">
                      BALANCED (Rs. 0)
                    </span>
                  ) : cashDiscrepancy > 0 ? (
                    <span className="px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 font-bold font-mono text-xs">
                      +Rs. {cashDiscrepancy.toLocaleString()} (Surplus)
                    </span>
                  ) : (
                    <span className="px-2 py-0.5 rounded bg-rose-100 text-rose-800 font-bold font-mono text-xs">
                      -Rs. {Math.abs(cashDiscrepancy).toLocaleString()} (Shortage)
                    </span>
                  )}
                </div>
              </div>
            </div>

            {/* POS Batch Ref & Handover Remarks */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-1">POS Card Terminal Batch Ref</label>
                <input
                  type="text"
                  value={cardBatchRef}
                  onChange={(e) => setCardBatchRef(e.target.value)}
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs font-mono"
                />
              </div>
              <div>
                <label className="block text-[11px] font-semibold text-slate-600 mb-1">Cashier Handover Remarks</label>
                <input
                  type="text"
                  value={shiftNotes}
                  onChange={(e) => setShiftNotes(e.target.value)}
                  placeholder="e.g. Handed over key & Rs. 18,500 to evening shift."
                  className="w-full bg-white text-slate-900 p-2.5 rounded-xl border border-slate-300 text-xs"
                />
              </div>
            </div>

            <div className="border-t border-slate-200 pt-3 flex space-x-2">
              <button
                onClick={() => setShiftClosingOpen(false)}
                className="flex-1 px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs border border-slate-300 cursor-pointer shadow-xs"
              >
                Close
              </button>
              <button
                onClick={handleGenerateShiftClosingPdf}
                className="flex-1 flex items-center justify-center space-x-1.5 px-4 py-2.5 rounded-xl bg-cyan-700 hover:bg-cyan-600 text-white font-bold text-xs cursor-pointer shadow-md"
              >
                <Download className="w-4 h-4" />
                <span>Download Shift Settlement PDF</span>
              </button>
            </div>
          </div>
        </div>
      )}

      {/* ------------------------------------------------------------ */}
      {/* MODAL 7: FULL PRINT-FRIENDLY INVOICE PREVIEW / PRINT MODAL   */}
      {/* ------------------------------------------------------------ */}
      {printableInvoice && (
        <PrintableInvoiceModal
          invoice={printableInvoice}
          onClose={() => setPrintableInvoice(null)}
        />
      )}
    </div>
  );
};
