import React from 'react';
import { Printer, X, Download, ShieldCheck, Building2, Phone, Mail, FileText, CheckCircle2, AlertCircle } from 'lucide-react';
import { Invoice } from '../types';
import { generateInvoicePdf } from '../utils/pdfGenerator';

interface PrintableInvoiceModalProps {
  invoice: Invoice | null;
  onClose: () => void;
}

export const PrintableInvoiceModal: React.FC<PrintableInvoiceModalProps> = ({ invoice, onClose }) => {
  if (!invoice) return null;

  const handlePrint = () => {
    window.print();
  };

  const isPaid = invoice.status === 'paid';
  const isPartial = invoice.status === 'partial';
  const isVoid = invoice.status === 'void';

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-2 sm:p-4 print:p-0 print:bg-white print:static print:inset-auto">
      {/* Modal Container */}
      <div className="bg-white border border-slate-200 rounded-2xl max-w-3xl w-full shadow-2xl overflow-hidden flex flex-col my-auto print:border-none print:shadow-none print:max-w-none print:w-full print:m-0 print:rounded-none">
        
        {/* Top Control Bar (Hidden on actual print) */}
        <div className="p-3.5 sm:p-4 bg-slate-900 text-white flex items-center justify-between print:hidden">
          <div className="flex items-center space-x-2.5">
            <div className="w-8 h-8 rounded-lg bg-cyan-600/30 border border-cyan-400/40 flex items-center justify-center text-cyan-300">
              <Printer className="w-4 h-4" />
            </div>
            <div>
              <h3 className="font-bold text-sm text-slate-100 flex items-center gap-2">
                <span>Print-Ready Tax Invoice</span>
                <span className="font-mono text-xs px-2 py-0.5 bg-slate-800 rounded border border-slate-700 text-cyan-300">
                  {invoice.invoiceNumber}
                </span>
              </h3>
              <p className="text-[11px] text-slate-400">Simplified, clean formatting for standard A4 & letter printers</p>
            </div>
          </div>

          <div className="flex items-center space-x-2">
            <button
              onClick={() => generateInvoicePdf(invoice)}
              className="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold flex items-center space-x-1.5 border border-slate-700 transition-colors cursor-pointer"
              title="Save as PDF"
            >
              <Download className="w-3.5 h-3.5 text-cyan-400" />
              <span className="hidden sm:inline">Export PDF</span>
            </button>
            <button
              onClick={handlePrint}
              className="px-4 py-1.5 rounded-lg bg-cyan-600 hover:bg-cyan-500 text-white text-xs font-bold flex items-center space-x-1.5 shadow-md shadow-cyan-600/30 transition-colors cursor-pointer"
            >
              <Printer className="w-3.5 h-3.5" />
              <span>Print Invoice</span>
            </button>
            <button
              onClick={onClose}
              className="p-1.5 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition-colors cursor-pointer"
              aria-label="Close modal"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>

        {/* Printable Document Paper Area */}
        <div id="printable-invoice-content" className="p-6 sm:p-10 bg-white text-slate-800 space-y-6 text-xs print:p-4 print:text-black">
          
          {/* Header & Clinic Branding */}
          <div className="flex flex-col sm:flex-row justify-between items-start border-b-2 border-slate-900 pb-5 gap-4">
            <div>
              <div className="flex items-center space-x-2.5">
                <div className="w-9 h-9 rounded-lg bg-slate-900 text-white font-black text-lg flex items-center justify-center tracking-wider">
                  ADC
                </div>
                <div>
                  <h1 className="text-lg font-black text-slate-900 tracking-tight leading-none uppercase">
                    Amad Diagnostic Centre
                  </h1>
                  <p className="text-[11px] text-slate-600 font-semibold tracking-wide mt-0.5">
                    Radiology & Advanced Diagnostic Imaging
                  </p>
                </div>
              </div>
              <div className="mt-3 text-[11px] text-slate-600 space-y-0.5">
                <p>Plot 14-B, Executive Sector, Islamabad, Pakistan</p>
                <p>Phone: +92 51 2223344, +92 51 2223345 • UAN: 111-232-232</p>
                <p>Email: billing@amaddiagnosticcentre.com.pk • Web: portal.amaddiagnosticcentre.com.pk</p>
                <p className="font-mono text-[10px] text-slate-500 pt-0.5">NTN: 8492019-3 | STRN: 3277876123456 | IHRA Reg #: IHRA-ISB-RAD-0441</p>
              </div>
            </div>

            <div className="sm:text-right border-t sm:border-t-0 pt-3 sm:pt-0 border-slate-200 w-full sm:w-auto">
              <span className="inline-block font-black text-base uppercase tracking-widest text-slate-900 bg-slate-100 px-3 py-1 rounded border border-slate-300">
                TAX INVOICE
              </span>
              <div className="mt-2.5 space-y-1 text-xs">
                <div>
                  <span className="text-slate-500 font-medium">Invoice No: </span>
                  <span className="font-mono font-bold text-slate-900">{invoice.invoiceNumber}</span>
                </div>
                <div>
                  <span className="text-slate-500 font-medium">Date & Time: </span>
                  <span className="font-semibold text-slate-800">{invoice.createdAt}</span>
                </div>
                <div>
                  <span className="text-slate-500 font-medium">Token ID: </span>
                  <span className="font-mono font-bold text-slate-900 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-300">
                    #{invoice.appointmentToken}
                  </span>
                </div>
                <div>
                  <span className="text-slate-500 font-medium">Status: </span>
                  <span className={`font-bold uppercase text-[11px] px-2 py-0.5 rounded inline-block ${
                    isPaid ? 'bg-emerald-100 text-emerald-800' :
                    isPartial ? 'bg-amber-100 text-amber-800' :
                    isVoid ? 'bg-slate-200 text-slate-600 line-through' :
                    'bg-rose-100 text-rose-800'
                  }`}>
                    {invoice.status}
                  </span>
                </div>
              </div>
            </div>
          </div>

          {/* Patient Demographics & Billing Information */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200 print:bg-slate-50/50">
            <div className="space-y-1.5">
              <h4 className="font-bold text-slate-900 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 pb-1">
                Patient Demographics
              </h4>
              <div className="grid grid-cols-3 gap-1">
                <span className="text-slate-500 font-medium">Full Name:</span>
                <span className="col-span-2 font-bold text-slate-900 text-sm">{invoice.patient.name}</span>
              </div>
              <div className="grid grid-cols-3 gap-1">
                <span className="text-slate-500 font-medium">MRN:</span>
                <span className="col-span-2 font-mono font-bold text-slate-800">{invoice.patient.mrn}</span>
              </div>
              <div className="grid grid-cols-3 gap-1">
                <span className="text-slate-500 font-medium">Age / Gender:</span>
                <span className="col-span-2 font-semibold text-slate-800">
                  {invoice.patient.age} Years / {invoice.patient.gender?.toUpperCase() || 'N/A'}
                </span>
              </div>
              <div className="grid grid-cols-3 gap-1">
                <span className="text-slate-500 font-medium">Contact Phone:</span>
                <span className="col-span-2 font-mono text-slate-800">{invoice.patient.phone || 'N/A'}</span>
              </div>
            </div>

            <div className="space-y-1.5">
              <h4 className="font-bold text-slate-900 text-xs uppercase tracking-wider text-slate-500 border-b border-slate-200 pb-1">
                Account & Billing Details
              </h4>
              <div className="grid grid-cols-3 gap-1">
                <span className="text-slate-500 font-medium">Referring Doctor:</span>
                <span className="col-span-2 font-semibold text-slate-900">Dr. Tariq Mehmood (FCPS) / Walk-in</span>
              </div>
              <div className="grid grid-cols-3 gap-1">
                <span className="text-slate-500 font-medium">Payment Mode:</span>
                <span className="col-span-2 font-bold text-slate-800 capitalize">
                  {invoice.payments.length > 0 ? invoice.payments.map(p => p.method).join(', ') : 'Pending'}
                </span>
              </div>
              {invoice.notes && (
                <div className="grid grid-cols-3 gap-1">
                  <span className="text-slate-500 font-medium">Remarks / Panel:</span>
                  <span className="col-span-2 italic text-slate-700">{invoice.notes}</span>
                </div>
              )}
            </div>
          </div>

          {/* Itemized Services & Consumables Table */}
          <div className="space-y-2">
            <h4 className="font-bold text-slate-900 text-xs uppercase tracking-wider">Itemized Diagnostic Services & Charges</h4>
            <table className="w-full text-left border-collapse border border-slate-300">
              <thead>
                <tr className="bg-slate-100 text-slate-700 font-bold border-b border-slate-300">
                  <th className="p-2.5 border-r border-slate-300 w-10 text-center">#</th>
                  <th className="p-2.5 border-r border-slate-300">Service / Procedure / Consumable Description</th>
                  <th className="p-2.5 border-r border-slate-300 w-16 text-center">Qty</th>
                  <th className="p-2.5 border-r border-slate-300 w-28 text-right">Unit Rate (PKR)</th>
                  <th className="p-2.5 border-r border-slate-300 w-24 text-right">Discount</th>
                  <th className="p-2.5 w-28 text-right">Amount (PKR)</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-200">
                {invoice.items.map((item, idx) => (
                  <tr key={item.id || idx} className="hover:bg-slate-50">
                    <td className="p-2.5 border-r border-slate-200 text-center font-mono text-slate-500">{idx + 1}</td>
                    <td className="p-2.5 border-r border-slate-200">
                      <div className="font-bold text-slate-900">{item.description}</div>
                      <div className="text-[10px] text-slate-500">Diagnostic Radiology Procedure</div>
                    </td>
                    <td className="p-2.5 border-r border-slate-200 text-center font-mono font-medium">{item.quantity}</td>
                    <td className="p-2.5 border-r border-slate-200 text-right font-mono">{item.unitPrice.toLocaleString()}</td>
                    <td className="p-2.5 border-r border-slate-200 text-right font-mono text-emerald-700">
                      {item.discount > 0 ? `-${item.discount.toLocaleString()}` : '-'}
                    </td>
                    <td className="p-2.5 text-right font-mono font-bold text-slate-900">{item.lineTotal.toLocaleString()}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {/* Calculations & Settlement Summary */}
          <div className="flex flex-col sm:flex-row justify-between items-start gap-6 pt-2">
            {/* Left: Payment Audit Trail */}
            <div className="w-full sm:w-1/2 space-y-2">
              <h5 className="font-bold text-slate-900 text-[11px] uppercase tracking-wider text-slate-500">
                Payment Transactions & Receipts
              </h5>
              {invoice.payments.length === 0 ? (
                <p className="text-slate-400 italic text-[11px] p-2 bg-slate-50 rounded border border-slate-200">
                  No payments recorded yet against this invoice.
                </p>
              ) : (
                <div className="border border-slate-200 rounded-lg overflow-hidden">
                  <table className="w-full text-[11px]">
                    <thead className="bg-slate-50 text-slate-600 font-semibold border-b border-slate-200">
                      <tr>
                        <th className="p-1.5 text-left">Time</th>
                        <th className="p-1.5 text-left">Mode</th>
                        <th className="p-1.5 text-left">Ref #</th>
                        <th className="p-1.5 text-right">Amount (PKR)</th>
                      </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                      {invoice.payments.map((p, pIdx) => (
                        <tr key={p.id || pIdx}>
                          <td className="p-1.5 text-slate-500 font-mono text-[10px]">{p.paidAt || 'Standard'}</td>
                          <td className="p-1.5 font-bold uppercase text-[10px]">{p.method}</td>
                          <td className="p-1.5 font-mono text-slate-600 text-[10px]">{p.reference || 'N/A'}</td>
                          <td className="p-1.5 text-right font-mono font-bold text-emerald-700">
                            Rs. {p.amount.toLocaleString()}
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {/* Barcode & Security Marker */}
              <div className="pt-3 flex items-center space-x-3 text-slate-600">
                <div className="font-mono text-xs tracking-widest font-black py-1 px-2 border border-slate-300 rounded bg-slate-50">
                  *INV-{invoice.invoiceNumber}*
                </div>
                <div className="text-[10px] text-slate-500">
                  <span>Authorized by ADC Electronic Health Records System</span>
                </div>
              </div>
            </div>

            {/* Right: Net Calculations */}
            <div className="w-full sm:w-5/12 bg-slate-50 p-4 rounded-xl border border-slate-200 space-y-2 text-xs print:bg-transparent">
              <div className="flex justify-between text-slate-600">
                <span>Subtotal (Gross Fee):</span>
                <span className="font-mono font-semibold">Rs. {invoice.subtotal.toLocaleString()}</span>
              </div>
              {invoice.discountTotal > 0 && (
                <div className="flex justify-between text-emerald-700 font-semibold">
                  <span>Special Concession / Discount:</span>
                  <span className="font-mono">-Rs. {invoice.discountTotal.toLocaleString()}</span>
                </div>
              )}
              <div className="flex justify-between font-bold text-slate-900 text-sm pt-2 border-t-2 border-slate-300">
                <span>Net Total Payable:</span>
                <span className="font-mono">Rs. {invoice.total.toLocaleString()}</span>
              </div>
              <div className="flex justify-between font-bold text-emerald-800 pt-1">
                <span>Total Amount Paid:</span>
                <span className="font-mono">Rs. {invoice.paidTotal.toLocaleString()}</span>
              </div>
              <div className="flex justify-between font-bold text-slate-900 pt-1 border-t border-slate-200">
                <span>Balance Due / Outstanding:</span>
                <span className={`font-mono text-sm ${invoice.balanceDue > 0 ? 'text-amber-700' : 'text-slate-500'}`}>
                  Rs. {invoice.balanceDue.toLocaleString()}
                </span>
              </div>
              {invoice.balanceDue === 0 && (
                <div className="text-center font-bold text-emerald-800 text-[11px] bg-emerald-100 py-1 rounded mt-2 border border-emerald-300">
                  ✓ PAID IN FULL — NO DUES REMAINING
                </div>
              )}
            </div>
          </div>

          {/* Terms & Signatures */}
          <div className="border-t border-slate-300 pt-6 mt-6">
            <div className="grid grid-cols-2 gap-8 text-[11px] text-slate-500">
              <div>
                <p className="font-bold text-slate-700 mb-1">Terms & Conditions:</p>
                <ol className="list-decimal pl-4 space-y-0.5 text-[10px]">
                  <li>Please retain this official computerized bill for collecting printed radiology reports and films.</li>
                  <li>Online reports and DICOM imaging are accessible via portal.amaddiagnosticcentre.com.pk with MRN.</li>
                  <li>Diagnostic fee once paid is non-refundable once examination acquisition has commenced.</li>
                </ol>
              </div>

              <div className="flex flex-col justify-end items-end text-right">
                <div className="w-48 border-b border-slate-400 pb-1 mb-1"></div>
                <p className="font-bold text-slate-800 text-xs">Accounts Officer / Cashier</p>
                <p className="text-[10px] text-slate-400">Amad Diagnostic Centre, Islamabad</p>
              </div>
            </div>
          </div>

        </div>

        {/* Footer actions for modal view (Hidden on print) */}
        <div className="p-3 bg-slate-50 border-t border-slate-200 flex justify-end space-x-2 print:hidden">
          <button
            onClick={onClose}
            className="px-4 py-2 rounded-xl bg-white border border-slate-300 text-slate-700 hover:bg-slate-100 font-semibold text-xs transition-colors cursor-pointer"
          >
            Close
          </button>
          <button
            onClick={handlePrint}
            className="px-5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-bold text-xs flex items-center space-x-1.5 shadow-md transition-colors cursor-pointer"
          >
            <Printer className="w-3.5 h-3.5 text-cyan-400" />
            <span>Print Invoice Now</span>
          </button>
        </div>

      </div>
    </div>
  );
};
