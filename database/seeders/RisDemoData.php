<?php

namespace Database\Seeders;

use App\Models\AdverseReaction;
use App\Models\AppNotification;
use App\Models\Appointment;
use App\Models\Business;
use App\Models\Customer;
use App\Models\DicomNode;
use App\Models\DoctorDispatchLog;
use App\Models\DoseLog;
use App\Models\InventoryTransaction;
use App\Models\Invoice;
use App\Models\Modality;
use App\Models\RadiologyReport;
use App\Models\ReportRelease;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\StudyScreeningAnswer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Demo tenant dataset (Amad Diagnostic Centre). Mirrors the SPA's former
 * mock dataset so a fresh install demonstrates every workflow state with
 * REAL persisted rows. Only attached to the tenant whose tenant_code matches
 * RIS_DEMO_TENANT_CODE.
 */
class RisDemoData extends Seeder
{
    public function run(Business $business, User $admin): void
    {
        $today = now()->format('Y-m-d');

        // ---------- staff ----------
        $staff = [
            ['Dr. Shahzad Khan, MBBS, FCPS', 'dr.shahzad@amaddiagnosticcentre.com.pk', 'radiologist', 'Radiology & Imaging', '+92 300 8501234', ['canSignReports' => true, 'canOverrideScreening' => true, 'canAccessPacs' => true]],
            ['Kamran Ali (Lead RT)', 'kamran.tech@amaddiagnosticcentre.com.pk', 'technologist', 'MRI & CT Suites', '+92 333 5554321', ['canAccessPacs' => true]],
            ['Amina Bilal', 'amina.reception@amaddiagnosticcentre.com.pk', 'receptionist', 'Front Desk & Registration', '+92 312 4447890', ['canVoidInvoices' => true]],
            ['Tariq Mehmood (Senior US Tech)', 'tariq.us@amaddiagnosticcentre.com.pk', 'technologist', 'Ultrasound & Doppler', '+92 345 6789012', ['canAccessPacs' => true]],
        ];

        $staffUsers = [];
        foreach ($staff as [$name, $email, $role, $department, $phone, $caps]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(env('RIS_DEMO_PASSWORD', 'AdcDemo#2026')),
                    'mobile_no' => $phone,
                    'email_verified_at' => now(),
                    'type' => 'staff',
                    'active_status' => 1,
                    'business_id' => $business->id,
                    'created_by' => $business->id,
                    'department' => $department,
                    'initials' => collect(explode(' ', $name))->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode(''),
                    'capabilities' => $caps,
                    'last_login_at' => null,
                ]
            );

            $roleName = match ($role) {
                'radiologist' => 'radiologist',
                'technologist' => 'technician',
                'receptionist' => 'receptionist',
                default => 'receptionist',
            };
            $role = \App\Models\Role::where('name', $roleName)->where('guard_name', 'web')->where('created_by', $admin->id)->first();
            if ($role && ! $user->hasRole($roleName)) {
                $user->addRole($role);
            }

            $staffUsers[$role] = $user;
        }

        $lookup = fn (string $code) => Service::where('code', $code)->where('business_id', $business->id)->first();
        $patientsByName = [];

        // ---------- patients ----------
        $patients = [
            ['Muhammad Haroon', 'm.haroon92@gmail.com', '+92 302 5557812', 'male', '1978-04-12', 48, 'B+', 'Hypertension (managed with Amlodipine 5mg). Chronic lower back radiculopathy.', 'NKDA (No Known Drug Allergies)'],
            ['Zainab Bibi', 'zainab.bibi1985@yahoo.com', '+92 333 7772190', 'female', '1985-09-24', 40, 'O+', 'Type 2 Diabetes Mellitus (HbA1c 7.1). Right flank colicky pain.', 'Penicillin (mild rash)'],
            ['Capt. (R) Asadullah Khan', 'asad.khan73@gmail.com', '+92 321 4441982', 'male', '1952-11-03', 73, 'A+', 'Coronary artery disease, mild dyspnea, suspected pulmonary fibrosis.', 'Iodinated contrast (flushing reaction in 2018)'],
            ['Fatima Noor', 'fatima.noor@outlook.com', '+92 314 6663321', 'female', '1996-02-18', 30, 'AB+', 'Severe persistent migraines, episodic visual auras, dizziness.', 'None'],
            ['Bilal Ahmed Sheikh', 'bilal.sheikh@techcraft.io', '+92 345 8889123', 'male', '1989-07-30', 37, 'O-', 'Post motor vehicle accident (MVA) 2 hours ago. Acute right chest blunt trauma.', 'None'],
            ['Nusrat Parveen', 'nusrat.p@gmail.com', '+92 300 2229988', 'female', '1968-12-05', 57, 'A+', 'Annual breast cancer screening. Maternal history of breast carcinoma.', 'Sulfa drugs'],
        ];

        foreach ($patients as [$name, $email, $phone, $gender, $dob, $age, $blood, $history, $allergies]) {
            $patient = Customer::firstOrCreate(
                ['name' => $name, 'business_id' => $business->id],
                [
                    'email' => $email,
                    'phone' => $phone,
                    'gender' => $gender,
                    'dob' => $dob,
                    'age' => $age,
                    'blood_group' => $blood,
                    'chronic_conditions' => $history,
                    'allergies' => $allergies,
                    'user_id' => User::create([
                        'name' => $name,
                        'email' => 'patient.'.$email,
                        'password' => Hash::make(Str::password(20)),
                        'type' => 'customer',
                        'active_status' => 1,
                        'is_enable_login' => 0,
                        'business_id' => $business->id,
                        'created_by' => $admin->id,
                    ])->id,
                    'created_by' => $admin->id,
                ]
            );
            $patientsByName[$name] = $patient;
        }

        // ---------- studies across the workflow ----------
        $makeStudy = function (array $data) use ($business, $admin) {
            return Appointment::create([
                ...$data,
                'business_id' => $business->id,
                'created_by' => $admin->id,
            ]);
        };

        $stamp = fn (string $hm) => now()->setTimeFromTimeString($hm);

        // 1) STAT in-progress X-ray
        $svc = $lookup('DX-CHEST-PA');
        $apt = $makeStudy([
            'customer_id' => $patientsByName['Bilal Ahmed Sheikh']->id,
            'name' => 'Bilal Ahmed Sheikh', 'email' => $patientsByName['Bilal Ahmed Sheikh']->email, 'contact' => $patientsByName['Bilal Ahmed Sheikh']->phone,
            'service_id' => $svc->id, 'referrer_id' => $this->referrerId($business, 'Dr. Rabia Khalid, MBBS'),
            'date' => $today, 'time' => '08:30:00', 'priority' => 'stat', 'workflow_state' => 'in_progress',
            'screening_required' => false, 'screening_cleared' => true,
            'room_number' => 'Room 1 (X-Ray Suite A)',
            'checked_in_at' => $stamp('08:15'), 'preparing_at' => $stamp('08:25'), 'in_progress_at' => $stamp('08:32'),
            'token_number' => 'DX-01',
            'notes' => 'STAT Trauma protocol. Patient arrived from ER with right chest pain following motor vehicle collision.',
        ]);

        // 2) Acquired MRI lumbar (cleared screening)
        $svc = $lookup('MR-LUMBAR');
        $aptMri = $makeStudy([
            'customer_id' => $patientsByName['Muhammad Haroon']->id,
            'name' => 'Muhammad Haroon', 'email' => $patientsByName['Muhammad Haroon']->email, 'contact' => $patientsByName['Muhammad Haroon']->phone,
            'service_id' => $svc->id, 'referrer_id' => $this->referrerId($business, 'Dr. Usman Farooq, MD'),
            'date' => $today, 'time' => '09:00:00', 'priority' => 'routine', 'workflow_state' => 'acquired',
            'screening_required' => true, 'screening_cleared' => true,
            'room_number' => 'Room 4 (MRI 1.5T Suite)',
            'assigned_radiologist_id' => $staffUsers['radiologist']->id,
            'checked_in_at' => $stamp('08:40'), 'preparing_at' => $stamp('08:50'), 'in_progress_at' => $stamp('09:05'), 'acquired_at' => $stamp('09:42'),
            'token_number' => 'MR-01',
            'notes' => 'Severe L5/S1 radicular symptoms x 3 weeks. MRI acquisition completed without artifacts.',
        ]);

        $mriForm = \App\Models\ScreeningForm::where('slug', 'mri-safety-screening')->where('business_id', $business->id)->first();
        if ($mriForm) {
            foreach ($mriForm->questions()->orderBy('sort_order')->get() as $q) {
                StudyScreeningAnswer::create([
                    'appointment_id' => $aptMri->id,
                    'screening_question_id' => $q->id,
                    'answer_value' => 'no',
                    'is_risk' => false,
                    'answered_by' => $staffUsers['technologist']->id,
                ]);
            }
        }

        // 3) Urgent HRCT reading, dose + contrast, risk answer overridden
        $svc = $lookup('CT-CHEST-HR');
        $aptCt = $makeStudy([
            'customer_id' => $patientsByName['Capt. (R) Asadullah Khan']->id,
            'name' => 'Capt. (R) Asadullah Khan', 'email' => $patientsByName['Capt. (R) Asadullah Khan']->email, 'contact' => $patientsByName['Capt. (R) Asadullah Khan']->phone,
            'service_id' => $svc->id, 'referrer_id' => $this->referrerId($business, 'Dr. Tariq Mahmood, FRCP'),
            'date' => $today, 'time' => '09:30:00', 'priority' => 'urgent', 'workflow_state' => 'reading',
            'screening_required' => true, 'screening_cleared' => true,
            'room_number' => 'Room 3 (CT 128-Slice)',
            'assigned_radiologist_id' => $staffUsers['radiologist']->id,
            'checked_in_at' => $stamp('09:10'), 'preparing_at' => $stamp('09:20'), 'in_progress_at' => $stamp('09:35'), 'acquired_at' => $stamp('09:55'),
            'token_number' => 'CT-01',
            'notes' => 'Interstitial lung evaluation with contrast. Prior mild allergy pre-treated successfully.',
        ]);

        $ctForm = \App\Models\ScreeningForm::where('slug', 'contrast-screening')->where('business_id', $business->id)->first();
        if ($ctForm) {
            foreach ($ctForm->questions()->orderBy('sort_order')->get() as $i => $q) {
                $isFirst = $i === 0;
                StudyScreeningAnswer::create([
                    'appointment_id' => $aptCt->id,
                    'screening_question_id' => $q->id,
                    'answer_value' => $isFirst ? 'yes' : 'no',
                    'is_risk' => $isFirst,
                    'override_reason' => $isFirst ? 'Mild flushing in 2018. Pre-medicated with Prednisone 40mg + Diphenhydramine 50mg. Approved by Dr. Shahzad.' : null,
                    'answered_by' => $staffUsers['technologist']->id,
                ]);
            }
        }

        DoseLog::create([
            'appointment_id' => $aptCt->id,
            'dose_value' => 8.4,
            'dose_unit' => 'mGy (CTDIvol)',
            'dlp_value' => 412,
            'kvp' => 120,
            'mas' => 220,
            'slice_count' => 280,
            'series_count' => 4,
            'contrast_agent' => 'Iohexol (Omnipaque 350)',
            'contrast_volume_ml' => 75,
            'contrast_flow_rate' => '3.5 mL/s',
            'cannula_site' => 'Right Antecubital (20G)',
            'saline_flush_ml' => 30,
            'technique_notes' => 'Low-dose inspiratory 1mm helical volume with multi-planar reconstruction.',
            'qc_passed' => true,
            'recorded_by' => $staffUsers['technologist']->id,
        ]);

        // 4) Reported + released ultrasound
        $svc = $lookup('US-ABD-PEL');
        $aptUs = $makeStudy([
            'customer_id' => $patientsByName['Zainab Bibi']->id,
            'name' => 'Zainab Bibi', 'email' => $patientsByName['Zainab Bibi']->email, 'contact' => $patientsByName['Zainab Bibi']->phone,
            'service_id' => $svc->id, 'referrer_id' => $this->referrerId($business, 'Dr. Rabia Khalid, MBBS'),
            'date' => $today, 'time' => '10:00:00', 'priority' => 'routine', 'workflow_state' => 'reported',
            'screening_required' => false, 'screening_cleared' => true,
            'room_number' => 'Room 2 (Ultrasound Suite)',
            'assigned_radiologist_id' => $staffUsers['radiologist']->id,
            'checked_in_at' => $stamp('09:45'), 'preparing_at' => $stamp('09:55'), 'in_progress_at' => $stamp('10:05'), 'acquired_at' => $stamp('10:28'), 'reported_at' => $stamp('10:45'),
            'token_number' => 'US-01',
            'notes' => 'Report finalized; ready for collection or portal.',
        ]);

        $report = RadiologyReport::create([
            'appointment_id' => $aptUs->id,
            'version' => 1,
            'type' => 'final',
            'clinical_history' => '40-year-old female presenting with right flank pain and dyspepsia.',
            'technique' => 'Transabdominal B-mode real-time sonography and color Doppler evaluation with 3.5MHz transducer.',
            'comparison' => 'No previous imaging available for comparison.',
            'findings' => "1. Liver: Normal size (13.2 cm) with normal homogenous parenchymal echogenicity.\n2. Gallbladder: Well-distended with thin wall. A small mobile echogenic focus measuring 4.2 mm with posterior acoustic shadowing noted in the fundus, consistent with solitary non-obstructive cholelithiasis.\n3. Common Bile Duct (CBD): Normal caliber (3.4 mm).\n4. Pancreas & Spleen: Unremarkable.\n5. Right Kidney: Measures 10.5 cm, normal.\n6. Left Kidney: Measures 10.7 cm, normal.\n7. Urinary Bladder: Adequately distended with clear lumen.",
            'impression' => 'Solitary small non-obstructive Gallbladder Calculus (4.2 mm) without sonographic signs of acute cholecystitis. Otherwise normal abdominal scan.',
            'recommendations' => 'Gastroenterology / surgical consultation for symptomatic cholelithiasis.',
            'critical_flag' => false,
            'authored_by' => $staffUsers['radiologist']->id,
            'signed_by' => $staffUsers['radiologist']->id,
            'signed_at' => $stamp('10:45'),
            'locked_at' => $stamp('10:45'),
            'business_id' => $business->id,
            'created_by' => $admin->id,
        ]);

        // 5) Checked-in mammography
        $svc = $lookup('MG-BILATERAL');
        $makeStudy([
            'customer_id' => $patientsByName['Nusrat Parveen']->id,
            'name' => 'Nusrat Parveen', 'email' => $patientsByName['Nusrat Parveen']->email, 'contact' => $patientsByName['Nusrat Parveen']->phone,
            'service_id' => $svc->id, 'referrer_id' => $this->referrerId($business, 'Dr. Rabia Khalid, MBBS'),
            'date' => $today, 'time' => '10:30:00', 'priority' => 'routine', 'workflow_state' => 'checked_in',
            'screening_required' => false, 'screening_cleared' => true,
            'room_number' => 'Room 5 (Mammography Suite)',
            'checked_in_at' => $stamp('10:18'),
            'token_number' => 'MG-01',
            'notes' => 'Annual screening exam. Patient in sub-waiting area.',
        ]);

        // 6) Booked MRI brain (screening pending)
        $svc = $lookup('MR-BRAIN-SPEC');
        $makeStudy([
            'customer_id' => $patientsByName['Fatima Noor']->id,
            'name' => 'Fatima Noor', 'email' => $patientsByName['Fatima Noor']->email, 'contact' => $patientsByName['Fatima Noor']->phone,
            'service_id' => $svc->id, 'referrer_id' => $this->referrerId($business, 'Dr. Ayesha Siddiqui, FCPS'),
            'date' => $today, 'time' => '11:15:00', 'priority' => 'urgent', 'workflow_state' => 'booked',
            'screening_required' => true, 'screening_cleared' => false,
            'room_number' => 'Room 4 (MRI 1.5T Suite)',
            'token_number' => 'MR-02',
            'notes' => 'Patient scheduled for 11:15 AM. Requires MRI safety checklist completion upon arrival.',
        ]);

        // ---------- invoices ----------
        $makeInvoice = function (Appointment $apt, Service $svc, array $payments, string $notes) use ($business, $admin) {
            $unit = (float) $svc->price;
            $invoice = Invoice::create([
                'patient_id' => $apt->customer_id,
                'appointment_id' => $apt->id,
                'notes' => $notes,
                'issued_by' => $admin->id,
                'issued_at' => now(),
                'business_id' => $business->id,
                'created_by' => $admin->id,
            ]);
            $invoice->items()->create([
                'service_id' => $svc->id,
                'description' => "{$svc->name} ({$svc->code})",
                'quantity' => 1,
                'unit_price' => $unit,
                'discount' => 0,
                'line_total' => $unit,
            ]);
            $invoice->forceFill(['subtotal' => $unit, 'discount_total' => 0, 'tax_amount' => 0, 'total' => $unit])->save();

            foreach ($payments as [$amount, $method, $reference]) {
                \App\Models\InvoicePayment::create([
                    'invoice_id' => $invoice->id,
                    'amount' => $amount,
                    'method' => $method,
                    'reference' => $reference,
                    'paid_at' => now(),
                    'received_by' => $admin->id,
                    'business_id' => $business->id,
                    'created_by' => $admin->id,
                ]);
            }
            $invoice->recalculateFromPayments();

            return $invoice;
        };

        $svc = $lookup('US-ABD-PEL');
        $makeInvoice($aptUs, $svc, [[3500, 'cash', 'RCPT-CASH-4401']], 'Paid in full via Cash at reception desk.');
        $svc = $lookup('CT-CHEST-HR');
        $invCt = Invoice::create([
            'patient_id' => $aptCt->customer_id, 'appointment_id' => $aptCt->id,
            'notes' => 'Senior citizen discount of Rs. 500 applied. Paid via Debit Card.',
            'issued_by' => $admin->id, 'issued_at' => now(), 'business_id' => $business->id, 'created_by' => $admin->id,
        ]);
        $invCt->items()->create([
            'service_id' => $svc->id, 'description' => "{$svc->name} ({$svc->code})",
            'quantity' => 1, 'unit_price' => 8500, 'discount' => 500, 'line_total' => 8000,
        ]);
        $invCt->forceFill(['subtotal' => 8500, 'discount_total' => 500, 'tax_amount' => 0, 'total' => 8000])->save();
        \App\Models\InvoicePayment::create([
            'invoice_id' => $invCt->id, 'amount' => 8000, 'method' => 'card', 'reference' => 'POS-AUTH-993812',
            'paid_at' => now(), 'received_by' => $admin->id, 'business_id' => $business->id, 'created_by' => $admin->id,
        ]);
        $invCt->recalculateFromPayments();
        $svc = $lookup('MR-LUMBAR');
        $invMri = Invoice::create([
            'patient_id' => $aptMri->customer_id, 'appointment_id' => $aptMri->id,
            'notes' => 'Advance partial deposit paid. Balance of Rs. 7,500 due at report collection.',
            'issued_by' => $admin->id, 'issued_at' => now(), 'business_id' => $business->id, 'created_by' => $admin->id,
        ]);
        $invMri->items()->create([
            'service_id' => $svc->id, 'description' => "{$svc->name} ({$svc->code})",
            'quantity' => 1, 'unit_price' => 14500, 'discount' => 0, 'line_total' => 14500,
        ]);
        $invMri->forceFill(['subtotal' => 14500, 'discount_total' => 0, 'tax_amount' => 0, 'total' => 14500])->save();
        \App\Models\InvoicePayment::create([
            'invoice_id' => $invMri->id, 'amount' => 7000, 'method' => 'bank', 'reference' => 'RAAST-TRX-88127391',
            'paid_at' => now(), 'received_by' => $admin->id, 'business_id' => $business->id, 'created_by' => $admin->id,
        ]);
        $invMri->recalculateFromPayments();

        // ---------- portal releases / dispatches ----------
        ReportRelease::create([
            'report_id' => $report->id, 'channel' => 'portal', 'released_by' => $admin->id, 'released_at' => $stamp('10:46'),
        ]);
        DoctorDispatchLog::create([
            'appointment_id' => $aptCt->id, 'token_number' => 'CT-01', 'patient_name' => 'Capt. (R) Asadullah Khan',
            'referrer_id' => $aptCt->referrer_id, 'referrer_name' => $aptCt->ReferrerData?->name, 'study_name' => $aptCt->ServiceData?->name,
            'channel' => 'whatsapp', 'recipient_contact' => $aptCt->ReferrerData?->phone, 'status' => 'pending',
            'sent_by' => 'System Auto-Dispatch', 'business_id' => $business->id,
        ]);

        // ---------- inventory usage trail ----------
        $item = \App\Models\InventoryItem::where('code', 'CT-OMNI-350-100')->where('business_id', $business->id)->first();
        if ($item) {
            InventoryTransaction::create([
                'inventory_item_id' => $item->id, 'item_name' => $item->name, 'type' => 'usage_study', 'quantity' => 1,
                'batch_number' => $item->batches[0]['batch_number'] ?? 'SEED', 'appointment_id' => $aptCt->id,
                'token_number' => 'CT-01', 'patient_name' => 'Capt. (R) Asadullah Khan',
                'notes' => 'Administered 75mL via dual power injector at 3.5mL/sec for HRCT Chest contrast study.',
                'performed_by' => $staffUsers['technologist']->id, 'business_id' => $business->id,
            ]);
        }

        AdverseReaction::create([
            'appointment_id' => $aptCt->id, 'token_number' => 'CT-01', 'patient_name' => 'Capt. (R) Asadullah Khan',
            'modality' => 'CT', 'contrast_agent' => 'Omnipaque 350 (Iohexol)', 'batch_number' => 'OPQ-2025-08',
            'administered_volume' => '75 mL', 'severity' => 'mild',
            'symptoms' => ['Mild transient flushing', 'Nausea (single episode)'],
            'treatment_given' => 'Injection Avil 2mL IV administered slowly. Vital signs monitored for 30 minutes. Pulse: 78 bpm, BP: 125/80 mmHg.',
            'outcome' => 'resolved_on_site', 'reported_by' => 'Kamran Ali (Lead RT)',
            'supervising_doctor' => 'Dr. Shahzad Khan (Consultant Radiologist)',
            'notes' => 'Patient recovered fully without respiratory distress or hemodynamic compromise.',
            'business_id' => $business->id,
        ]);

        // ---------- DICOM nodes ----------
        foreach ([
            ['Primary Core PACS Archive', 'ADC_PACS_CORE', '192.168.10.50', 104, null, true, true],
            ['MRI 1.5T Symphony Gateway', 'MR_SUITE_01', '192.168.10.104', 11112, 'MR', true, false],
            ['Somatom 128-Slice CT Scanner', 'CT_SOMATOM_01', '192.168.10.103', 104, 'CT', true, false],
        ] as [$name, $ae, $ip, $port, $mod, $wl, $st]) {
            DicomNode::updateOrCreate(
                ['ae_title' => $ae, 'business_id' => $business->id],
                [
                    'node_name' => $name, 'ip_address' => $ip, 'port' => $port, 'modality_code' => $mod,
                    'is_worklist_scp' => $wl, 'is_storage_scp' => $st, 'status' => 'unreachable',
                ]
            );
        }

        // ---------- notifications ----------
        foreach ([
            ['STAT Trauma X-Ray in Progress', 'Emergency Token DX-01 (Bilal Ahmed Sheikh) undergoing Chest X-Ray. Priority STAT Trauma protocol.', 'stat', 'critical', $apt->id, 'DX-01', 'Bilal Ahmed Sheikh', 'technologist', 'View STAT Worklist', false],
            ['MRI Acquisition Ready for Reporting', 'MRI Lumbar Spine completed for Token MR-01 (Muhammad Haroon). Study ready for reporting.', 'workflow', 'high', $aptMri->id, 'MR-01', 'Muhammad Haroon', 'reporting', 'Open Diagnostic Report', false],
            ['POS Payment Collected (Rs. 8,000)', 'Full invoice settlement received via Debit Card for HRCT Chest (Token CT-01).', 'billing', 'low', $aptCt->id, 'CT-01', 'Capt. (R) Asadullah Khan', 'billing', 'View Receipt', true],
        ] as [$title, $message, $category, $priority, $aptId, $token, $patientName, $tab, $label, $isRead]) {
            AppNotification::create([
                'title' => $title, 'message' => $message, 'category' => $category, 'priority' => $priority,
                'appointment_id' => $aptId, 'token_number' => $token, 'patient_name' => $patientName,
                'target_tab' => $tab, 'action_label' => $label, 'is_read' => $isRead, 'business_id' => $business->id,
            ]);
        }
    }

    private function referrerId(Business $business, string $name): ?int
    {
        return \App\Models\Referrer::where('name', $name)->where('business_id', $business->id)->value('id');
    }
}
