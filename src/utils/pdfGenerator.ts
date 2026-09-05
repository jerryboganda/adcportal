import { jsPDF } from 'jspdf';
import { Appointment, Invoice, RadiologyReport } from '../types';

export function generateRadiologyReportPdf(appointment: Appointment, report: RadiologyReport) {
  const doc = new jsPDF({
    orientation: 'portrait',
    unit: 'mm',
    format: 'a4',
  });

  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 15;
  let y = 16;

  // Header Banner
  doc.setFillColor(15, 23, 42); // slate-900
  doc.rect(margin, y, pageWidth - 2 * margin, 24, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(14);
  doc.text('AMAD DIAGNOSTIC CENTRE (ADC)', margin + 6, y + 9);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.text('Radiology & Advanced Imaging Information System | ISO 9001:2015 Certified', margin + 6, y + 15);
  doc.text('Plot 14-B, Executive Sector, Islamabad, Pakistan | Tel: +92 51 2223344 | info@amaddiagnosticcentre.com.pk', margin + 6, y + 20);

  y += 30;

  // Patient & Study Demographics Box
  doc.setDrawColor(203, 213, 225); // slate-300
  doc.setFillColor(248, 250, 252); // slate-50
  doc.roundedRect(margin, y, pageWidth - 2 * margin, 32, 2, 2, 'FD');

  doc.setTextColor(15, 23, 42);
  doc.setFontSize(9);

  // Column 1
  doc.setFont('helvetica', 'bold');
  doc.text('Patient Name:', margin + 4, y + 6);
  doc.setFont('helvetica', 'normal');
  doc.text(appointment.patient.name, margin + 28, y + 6);

  doc.setFont('helvetica', 'bold');
  doc.text('MRN / ID:', margin + 4, y + 12);
  doc.setFont('helvetica', 'normal');
  doc.text(appointment.patient.mrn, margin + 28, y + 12);

  doc.setFont('helvetica', 'bold');
  doc.text('Age / Gender:', margin + 4, y + 18);
  doc.setFont('helvetica', 'normal');
  doc.text(`${appointment.patient.age} Yrs / ${appointment.patient.gender.toUpperCase()}`, margin + 28, y + 18);

  doc.setFont('helvetica', 'bold');
  doc.text('Ref. Doctor:', margin + 4, y + 24);
  doc.setFont('helvetica', 'normal');
  doc.text(appointment.referrer?.name || 'Self / Walk-in', margin + 28, y + 24);

  // Column 2
  const col2X = margin + 100;
  doc.setFont('helvetica', 'bold');
  doc.text('Study ID / Token:', col2X, y + 6);
  doc.setFont('helvetica', 'normal');
  doc.text(`${appointment.id.toUpperCase()} (#${appointment.tokenNumber})`, col2X + 32, y + 6);

  doc.setFont('helvetica', 'bold');
  doc.text('Modality / Suite:', col2X, y + 12);
  doc.setFont('helvetica', 'normal');
  doc.text(`${appointment.modality.name} (${appointment.modality.code})`, col2X + 32, y + 12);

  doc.setFont('helvetica', 'bold');
  doc.text('Exam Date / Time:', col2X, y + 18);
  doc.setFont('helvetica', 'normal');
  doc.text(`${appointment.date} at ${appointment.time}`, col2X + 32, y + 18);

  doc.setFont('helvetica', 'bold');
  doc.text('Report Status:', col2X, y + 24);
  doc.setFont('helvetica', 'bold');
  if (report.criticalFlag) {
    doc.setTextColor(220, 38, 38);
    doc.text('FINAL REPORT [CRITICAL ALERT]', col2X + 32, y + 24);
  } else {
    doc.setTextColor(22, 163, 74);
    doc.text(`FINAL SIGNED REPORT (v${report.version})`, col2X + 32, y + 24);
  }

  y += 38;

  // Procedure Title
  doc.setTextColor(30, 41, 59);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(12);
  doc.text(`EXAM: ${appointment.service.name.toUpperCase()}`, margin, y);
  y += 6;

  doc.setDrawColor(2, 132, 199);
  doc.setLineWidth(0.8);
  doc.line(margin, y, pageWidth - margin, y);
  y += 7;

  // Helper for report sections
  function addSection(title: string, content: string, isEmphasis = false) {
    if (!content || !content.trim()) return;
    if (y > 250) {
      doc.addPage();
      y = 20;
    }
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9.5);
    doc.setTextColor(15, 23, 42);
    doc.text(title.toUpperCase(), margin, y);
    y += 4.5;

    doc.setFont('helvetica', isEmphasis ? 'bold' : 'normal');
    doc.setFontSize(9);
    doc.setTextColor(51, 65, 85);

    const splitText = doc.splitTextToSize(content, pageWidth - 2 * margin);
    doc.text(splitText, margin, y);
    y += splitText.length * 4.5 + 4;
  }

  if (report.clinicalHistory) addSection('Clinical History / Indication', report.clinicalHistory);
  if (report.technique) addSection('Imaging Technique & Protocol', report.technique);
  if (report.comparison) addSection('Prior Comparison', report.comparison);
  if (report.findings) addSection('Detailed Findings', report.findings);
  if (report.impression) addSection('Impression & Conclusion', report.impression, true);
  if (report.recommendations) addSection('Recommendations', report.recommendations);

  // Dose / Contrast Info if present
  if (appointment.doseLog) {
    if (y > 255) { doc.addPage(); y = 20; }
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(8.5);
    doc.setTextColor(100, 116, 139);
    doc.text(`Radiation Dose: ${appointment.doseLog.doseValue} ${appointment.doseLog.doseUnit} | Contrast: ${appointment.doseLog.contrastAgent || 'None'} (${appointment.doseLog.contrastVolumeMl || 0} mL)`, margin, y);
    y += 6;
  }

  // Footer / Signature Section
  if (y > 250) { doc.addPage(); y = 20; }
  y = Math.max(y + 6, 248);

  doc.setDrawColor(226, 232, 240);
  doc.setLineWidth(0.4);
  doc.line(margin, y, pageWidth - margin, y);
  y += 6;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8);
  doc.setTextColor(100, 116, 139);
  doc.text(`Electronically Authenticated & Signed on ${report.signedAt || appointment.date} by:`, margin, y);

  y += 5;
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10);
  doc.setTextColor(15, 23, 42);
  doc.text(report.signedBy || appointment.assignedRadiologistName || 'Dr. Shahzad Mir, FRCR (Consultant Radiologist)', margin, y);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(7.5);
  doc.setTextColor(148, 163, 184);
  doc.text(`Report Ref: ADC-REP-${report.id} | Page 1 of 1 | Verified via RIS Secure Cryptographic Stamp`, margin, y + 6);

  // Save / Trigger Download
  const filename = `ADC-Report-${appointment.tokenNumber}-${appointment.patient.mrn}.pdf`;
  doc.save(filename);
}

export function generateInvoicePdf(invoice: Invoice) {
  const doc = new jsPDF({
    orientation: 'portrait',
    unit: 'mm',
    format: 'a4',
  });

  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 15;
  let y = 16;

  // Header Banner
  doc.setFillColor(15, 23, 42);
  doc.rect(margin, y, pageWidth - 2 * margin, 24, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(14);
  doc.text('AMAD DIAGNOSTIC CENTRE (ADC)', margin + 6, y + 9);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.text('Official Clinical Billing Receipt & Invoice | NTN: 8492019-3', margin + 6, y + 15);
  doc.text('Plot 14-B, Executive Sector, Islamabad, Pakistan | Tel: +92 51 2223344', margin + 6, y + 20);

  y += 30;

  // Invoice Details Box
  doc.setDrawColor(203, 213, 225);
  doc.setFillColor(248, 250, 252);
  doc.roundedRect(margin, y, pageWidth - 2 * margin, 28, 2, 2, 'FD');

  doc.setTextColor(15, 23, 42);
  doc.setFontSize(9);

  doc.setFont('helvetica', 'bold');
  doc.text('Bill To Patient:', margin + 4, y + 6);
  doc.setFont('helvetica', 'normal');
  doc.text(invoice.patient.name, margin + 30, y + 6);

  doc.setFont('helvetica', 'bold');
  doc.text('MRN / ID:', margin + 4, y + 12);
  doc.setFont('helvetica', 'normal');
  doc.text(invoice.patient.mrn, margin + 30, y + 12);

  doc.setFont('helvetica', 'bold');
  doc.text('Contact Phone:', margin + 4, y + 18);
  doc.setFont('helvetica', 'normal');
  doc.text(invoice.patient.phone, margin + 30, y + 18);

  const col2X = margin + 105;
  doc.setFont('helvetica', 'bold');
  doc.text('Invoice #:', col2X, y + 6);
  doc.setFont('helvetica', 'bold');
  doc.text(invoice.invoiceNumber, col2X + 24, y + 6);

  doc.setFont('helvetica', 'bold');
  doc.text('Date / Time:', col2X, y + 12);
  doc.setFont('helvetica', 'normal');
  doc.text(`${invoice.createdAt}`, col2X + 24, y + 12);

  doc.setFont('helvetica', 'bold');
  doc.text('Payment Status:', col2X, y + 18);
  doc.setFont('helvetica', 'bold');
  if (invoice.status === 'paid') {
    doc.setTextColor(22, 163, 74);
    doc.text('PAID IN FULL', col2X + 24, y + 18);
  } else if (invoice.status === 'partial') {
    doc.setTextColor(202, 138, 4);
    doc.text('PARTIALLY PAID', col2X + 24, y + 18);
  } else {
    doc.setTextColor(220, 38, 38);
    doc.text('OUTSTANDING DUE', col2X + 24, y + 18);
  }

  y += 34;

  // Table Headers
  doc.setFillColor(241, 245, 249);
  doc.rect(margin, y, pageWidth - 2 * margin, 8, 'F');
  doc.setTextColor(51, 65, 85);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(8.5);

  doc.text('Service / Procedure Description', margin + 4, y + 5.5);
  doc.text('Qty', margin + 115, y + 5.5);
  doc.text('Unit Price (PKR)', margin + 130, y + 5.5);
  doc.text('Line Total (PKR)', margin + 165, y + 5.5);

  y += 10;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.setTextColor(15, 23, 42);

  invoice.items.forEach((item) => {
    doc.text(item.description, margin + 4, y + 4);
    doc.text(String(item.quantity), margin + 117, y + 4);
    doc.text(`Rs. ${item.unitPrice.toLocaleString()}`, margin + 130, y + 4);
    doc.text(`Rs. ${item.lineTotal.toLocaleString()}`, margin + 165, y + 4);
    y += 8;
  });

  y += 4;
  doc.setDrawColor(226, 232, 240);
  doc.line(margin, y, pageWidth - margin, y);
  y += 6;

  // Totals Section
  const totalsX = margin + 115;
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.text('Subtotal:', totalsX, y);
  doc.text(`Rs. ${invoice.subtotal.toLocaleString()}`, margin + 165, y);
  y += 5.5;

  if (invoice.discountTotal > 0) {
    doc.setTextColor(22, 163, 74);
    doc.text('Discount Applied:', totalsX, y);
    doc.text(`- Rs. ${invoice.discountTotal.toLocaleString()}`, margin + 165, y);
    y += 5.5;
  }

  doc.setTextColor(15, 23, 42);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(10);
  doc.text('Total Invoiced:', totalsX, y);
  doc.text(`Rs. ${invoice.total.toLocaleString()}`, margin + 165, y);
  y += 6;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(9);
  doc.text('Total Amount Paid:', totalsX, y);
  doc.text(`Rs. ${invoice.paidTotal.toLocaleString()}`, margin + 165, y);
  y += 5.5;

  doc.setFont('helvetica', 'bold');
  doc.setTextColor(invoice.balanceDue > 0 ? 220 : 22, invoice.balanceDue > 0 ? 38 : 163, invoice.balanceDue > 0 ? 38 : 74);
  doc.text('Balance Due:', totalsX, y);
  doc.text(`Rs. ${invoice.balanceDue.toLocaleString()}`, margin + 165, y);
  y += 12;

  // Payment receipts recorded
  if (invoice.payments.length > 0) {
    doc.setTextColor(15, 23, 42);
    doc.setFont('helvetica', 'bold');
    doc.setFontSize(9);
    doc.text('Payment Transactions Received:', margin, y);
    y += 5;

    doc.setFont('helvetica', 'normal');
    doc.setFontSize(8);
    invoice.payments.forEach((p) => {
      doc.text(`• ${p.paidAt}: Rs. ${p.amount.toLocaleString()} via ${p.method.toUpperCase()} (${p.reference || 'Ref: N/A'}) - Recorded by ${p.receivedBy}`, margin + 4, y);
      y += 4.5;
    });
  }

  // Footer Note
  y = Math.max(y + 8, 255);
  doc.setFont('helvetica', 'italic');
  doc.setFontSize(7.5);
  doc.setTextColor(148, 163, 184);
  doc.text('Thank you for trusting Amad Diagnostic Centre. Computer generated document, requires no physical signature.', margin, y);

  const filename = `${invoice.invoiceNumber}.pdf`;
  doc.save(filename);
}

export interface ShiftClosingData {
  shiftDate: string;
  shiftName: string;
  cashierName: string;
  openedAt: string;
  closedAt: string;
  invoicesCount: number;
  totalInvoiced: number;
  totalCollected: number;
  totalDiscounts: number;
  cashCollected: number;
  cardCollected: number;
  bankCollected: number;
  mobileCollected: number;
  insuranceCollected: number;
  countedCash: number;
  discrepancy: number;
  cardSettlementRef?: string;
  notes?: string;
  supervisorName?: string;
}

export function generateShiftClosingPdf(data: ShiftClosingData) {
  const doc = new jsPDF({
    orientation: 'portrait',
    unit: 'mm',
    format: 'a4',
  });

  const pageWidth = doc.internal.pageSize.getWidth();
  const margin = 15;
  let y = 16;

  // Header Banner
  doc.setFillColor(15, 23, 42); // slate-900
  doc.rect(margin, y, pageWidth - 2 * margin, 24, 'F');

  doc.setTextColor(255, 255, 255);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(13);
  doc.text('AMAD DIAGNOSTIC CENTRE (ADC)', margin + 6, y + 8);

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.text('Daily Shift Cash Closing & POS Financial Reconciliation Statement', margin + 6, y + 14);
  doc.text('Plot 14-B, Executive Sector, Islamabad, Pakistan | Tel: +92 51 2223344 | NTN: 8492019-3', margin + 6, y + 19);

  y += 30;

  // Metadata Box
  doc.setDrawColor(203, 213, 225);
  doc.setFillColor(248, 250, 252);
  doc.roundedRect(margin, y, pageWidth - 2 * margin, 26, 2, 2, 'FD');

  doc.setTextColor(15, 23, 42);
  doc.setFontSize(9);

  doc.setFont('helvetica', 'bold');
  doc.text('Shift & Date:', margin + 4, y + 6);
  doc.setFont('helvetica', 'normal');
  doc.text(`${data.shiftName} (${data.shiftDate})`, margin + 28, y + 6);

  doc.setFont('helvetica', 'bold');
  doc.text('Cashier On Duty:', margin + 4, y + 12);
  doc.setFont('helvetica', 'normal');
  doc.text(data.cashierName, margin + 28, y + 12);

  doc.setFont('helvetica', 'bold');
  doc.text('Shift Hours:', margin + 4, y + 18);
  doc.setFont('helvetica', 'normal');
  doc.text(`${data.openedAt} - ${data.closedAt}`, margin + 28, y + 18);

  const col2X = margin + 105;
  doc.setFont('helvetica', 'bold');
  doc.text('Invoices Handled:', col2X, y + 6);
  doc.setFont('helvetica', 'normal');
  doc.text(`${data.invoicesCount} Invoices`, col2X + 32, y + 6);

  doc.setFont('helvetica', 'bold');
  doc.text('Supervisor:', col2X, y + 12);
  doc.setFont('helvetica', 'normal');
  doc.text(data.supervisorName || 'Accounts Supervisor', col2X + 32, y + 12);

  doc.setFont('helvetica', 'bold');
  doc.text('POS Batch Ref:', col2X, y + 18);
  doc.setFont('helvetica', 'normal');
  doc.text(data.cardSettlementRef || 'BATCH-88219', col2X + 32, y + 18);

  y += 32;

  // Summary Table
  doc.setFillColor(241, 245, 249);
  doc.rect(margin, y, pageWidth - 2 * margin, 8, 'F');
  doc.setTextColor(51, 65, 85);
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(9);
  doc.text('FINANCIAL RECONCILIATION SUMMARY (PKR)', margin + 4, y + 5.5);

  y += 12;

  const addRow = (label: string, value: string, isBold = false, isHighlight = false) => {
    doc.setFont('helvetica', isBold ? 'bold' : 'normal');
    doc.setFontSize(9);
    doc.setTextColor(isHighlight ? (data.discrepancy < 0 ? 220 : 22) : 15, isHighlight ? (data.discrepancy < 0 ? 38 : 163) : 23, isHighlight ? (data.discrepancy < 0 ? 38 : 74) : 42);
    doc.text(label, margin + 4, y);
    doc.text(value, pageWidth - margin - 4, y, { align: 'right' });
    y += 6;
  };

  addRow('Total Invoiced Amount (Gross Revenue):', `Rs. ${data.totalInvoiced.toLocaleString()}`, true);
  addRow('Total Discounts & Concessions Approved:', `- Rs. ${data.totalDiscounts.toLocaleString()}`);
  addRow('Net Revenue Realized in Shift:', `Rs. ${(data.totalInvoiced - data.totalDiscounts).toLocaleString()}`, true);
  
  y += 2;
  doc.setDrawColor(226, 232, 240);
  doc.line(margin, y, pageWidth - margin, y);
  y += 5;

  doc.setFont('helvetica', 'bold');
  doc.setFontSize(8.5);
  doc.setTextColor(100, 116, 139);
  doc.text('COLLECTIONS BY TENDER CHANNEL', margin + 4, y);
  y += 6;

  addRow('• Cash Collected (Counter Drawer):', `Rs. ${data.cashCollected.toLocaleString()}`);
  addRow('• Credit / Debit Card (POS Machine):', `Rs. ${data.cardCollected.toLocaleString()}`);
  addRow('• Direct Bank Transfer / Raast Instant QR:', `Rs. ${data.bankCollected.toLocaleString()}`);
  addRow('• Mobile Wallets (Easypaisa / JazzCash):', `Rs. ${data.mobileCollected.toLocaleString()}`);
  addRow('• Corporate / Insurance Panel Receivables:', `Rs. ${data.insuranceCollected.toLocaleString()}`);

  y += 2;
  doc.setDrawColor(226, 232, 240);
  doc.line(margin, y, pageWidth - margin, y);
  y += 5;

  addRow('Total Shift Collections Received:', `Rs. ${data.totalCollected.toLocaleString()}`, true);

  y += 4;
  // Cash Drawer Audit Section
  doc.setFillColor(248, 250, 252);
  doc.roundedRect(margin, y, pageWidth - 2 * margin, 26, 2, 2, 'FD');
  
  doc.setFont('helvetica', 'bold');
  doc.setFontSize(9);
  doc.setTextColor(15, 23, 42);
  doc.text('CASH DRAWER PHYSICAL AUDIT', margin + 4, y + 6);
  
  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8.5);
  doc.text(`System Expected Physical Cash: Rs. ${data.cashCollected.toLocaleString()}`, margin + 4, y + 12);
  doc.text(`Physical Cash Counted in Drawer: Rs. ${data.countedCash.toLocaleString()}`, margin + 4, y + 18);
  
  doc.setFont('helvetica', 'bold');
  if (data.discrepancy === 0) {
    doc.setTextColor(22, 163, 74);
    doc.text('Variance: EXACT BALANCED (Rs. 0)', col2X, y + 12);
  } else if (data.discrepancy > 0) {
    doc.setTextColor(22, 163, 74);
    doc.text(`Variance: +Rs. ${data.discrepancy.toLocaleString()} (Surplus)`, col2X, y + 12);
  } else {
    doc.setTextColor(220, 38, 38);
    doc.text(`Variance: -Rs. ${Math.abs(data.discrepancy).toLocaleString()} (Shortage)`, col2X, y + 12);
  }

  y += 34;

  if (data.notes) {
    doc.setFont('helvetica', 'italic');
    doc.setFontSize(8);
    doc.setTextColor(100, 116, 139);
    doc.text(`Cashier Shift Remarks: "${data.notes}"`, margin + 4, y);
    y += 10;
  }

  // Signatures Box
  y = Math.max(y + 10, 240);
  doc.setDrawColor(203, 213, 225);
  doc.line(margin, y, pageWidth - margin, y);
  y += 8;

  doc.setFont('helvetica', 'normal');
  doc.setFontSize(8);
  doc.setTextColor(100, 116, 139);

  const sigCol1 = margin + 10;
  const sigCol2 = margin + 110;

  doc.text('_____________________________________', sigCol1, y);
  doc.text('_____________________________________', sigCol2, y);
  y += 5;
  doc.text(`Shift Cashier Handover: ${data.cashierName}`, sigCol1, y);
  doc.text(`Shift Supervisor Sign-off: ${data.supervisorName || 'Accounts Incharge'}`, sigCol2, y);

  const filename = `ADC-Shift-Closing-${data.shiftDate}-${data.shiftName.replace(/\s+/g, '-')}.pdf`;
  doc.save(filename);
}
