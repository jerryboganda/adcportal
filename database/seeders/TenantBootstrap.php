<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\InventoryItem;
use App\Models\Modality;
use App\Models\Permission;
use App\Models\ReportTemplate;
use App\Models\RisNotificationTemplate;
use App\Models\Role;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Provisions ONE tenant (clinic) so it is immediately usable:
 * per-clinic roles, starter masters, screening forms, report templates,
 * notification templates and the standard contrast/consumable catalog.
 * Idempotent — safe to re-run.
 */
class TenantBootstrap extends Seeder
{
    public function run(Business $business, User $admin): void
    {
        $this->seedRoles($admin);
        $this->seedMasters($business, $admin);
        $this->seedClinicProfile($business, $admin);
    }

    // ==================== RBAC ====================

    private function permissionNames(): array
    {
        return [
            'clinic manage', 'clinic edit',
            'modality manage', 'modality create', 'modality edit', 'modality delete',
            'room manage', 'room create', 'room edit', 'room delete',
            'service create', 'service edit', 'service delete',
            'customer manage', 'customer create', 'customer edit', 'customer delete',
            'referrer manage', 'referrer create', 'referrer edit', 'referrer delete',
            'appointment manage', 'appointment create', 'appointment edit', 'appointment delete',
            'study checkin', 'study screen', 'study acquire', 'study assign', 'study cancel',
            'report manage', 'report create', 'report edit', 'report sign', 'report release',
            'report template manage', 'report template create', 'report template edit', 'report template delete',
            'invoice manage', 'invoice create', 'invoice edit', 'invoice delete', 'invoice payment',
            'user manage', 'user create', 'user edit', 'user delete',
            'setting manage',
        ];
    }

    private function seedRoles(User $admin): void
    {
        // Permissions are global catalog entries; roles are per-tenant.
        foreach ($this->permissionNames() as $name) {
            Permission::firstOrCreate(['name' => $name], ['guard_name' => 'web', 'module' => 'General']);
        }

        $all = $this->permissionNames();

        $bundles = [
            'admin' => $all,
            'receptionist' => [
                'appointment manage', 'appointment create', 'appointment edit',
                'study checkin', 'study screen', 'study cancel',
                'customer manage', 'customer create', 'customer edit',
                'referrer manage', 'referrer create', 'referrer edit',
                'invoice create', 'invoice payment', 'report release',
            ],
            'technician' => [
                'appointment manage', 'study checkin', 'study screen', 'study acquire', 'report manage',
            ],
            'radiologist' => [
                'appointment manage', 'report manage', 'report create', 'report edit', 'report sign', 'report release',
            ],
            'billing' => [
                'invoice manage', 'invoice create', 'invoice edit', 'invoice delete', 'invoice payment',
                'customer manage', 'customer create', 'customer edit',
            ],
        ];

        foreach ($bundles as $roleName => $perms) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'web')
                ->where('created_by', $admin->id)
                ->first();

            if (! $role) {
                $role = Role::create([
                    'name' => $roleName,
                    'guard_name' => 'web',
                    'module' => 'Base',
                    'created_by' => $admin->id,
                ]);
            }

            foreach ($perms as $permName) {
                $permission = Permission::where('name', $permName)->first();
                if ($permission && ! $role->hasPermission($permName)) {
                    $role->givePermission($permission);
                }
            }
        }

        if (! $admin->hasRole('admin')) {
            $adminRole = Role::where('name', 'admin')->where('created_by', $admin->id)->first();
            if ($adminRole) {
                $admin->addRole($adminRole);
            }
        }

        // Laratrust caches roles/permissions per user — flush after every attach.
        if (method_exists($admin, 'flushCache')) {
            $admin->flushCache();
        }
    }

    // ==================== masters ====================

    private function seedMasters(Business $business, User $admin): void
    {
        $modalities = [
            ['name' => 'X-Ray (Digital Radiography)', 'code' => 'DX', 'color' => '#0284c7', 'buffer_minutes' => 10],
            ['name' => 'Ultrasound / Doppler', 'code' => 'US', 'color' => '#0d9488', 'buffer_minutes' => 5],
            ['name' => 'Computed Tomography (CT)', 'code' => 'CT', 'color' => '#ea580c', 'buffer_minutes' => 15],
            ['name' => 'Magnetic Resonance Imaging (MRI)', 'code' => 'MR', 'color' => '#9333ea', 'buffer_minutes' => 20],
            ['name' => 'Digital Mammography', 'code' => 'MG', 'color' => '#db2777', 'buffer_minutes' => 15],
        ];

        $modalityIds = [];
        foreach ($modalities as $m) {
            $modality = Modality::withTrashed()->updateOrCreate(
                ['code' => $m['code'], 'business_id' => $business->id],
                [...$m, 'is_active' => true, 'created_by' => $admin->id]
            );
            $modality->restore();
            $modalityIds[$m['code']] = $modality->id;
        }

        $services = [
            ['name' => 'Chest X-Ray PA & Lateral Views', 'code' => 'DX-CHEST-PA', 'modality' => 'DX', 'price' => 1800, 'duration' => 15, 'prep' => 'Remove any metal necklaces, brassieres with underwire, or upper body jewelry.', 'screening' => false, 'contrast' => 'none'],
            ['name' => 'Whole Abdomen & Pelvis Ultrasound', 'code' => 'US-ABD-PEL', 'modality' => 'US', 'price' => 3500, 'duration' => 25, 'prep' => '6 hours fasting prior to appointment. Drink 1 litre of water 1 hour before exam for full bladder.', 'screening' => false, 'contrast' => 'none'],
            ['name' => 'Carotid Doppler Bilateral', 'code' => 'US-CAROTID', 'modality' => 'US', 'price' => 4500, 'duration' => 30, 'prep' => 'No special preparation needed. Wear a loose collar shirt.', 'screening' => false, 'contrast' => 'none'],
            ['name' => 'HRCT Chest (High Resolution CT)', 'code' => 'CT-CHEST-HR', 'modality' => 'CT', 'price' => 8500, 'duration' => 20, 'prep' => 'Fasting 4 hours if contrast indicated. Bring latest Serum Creatinine report.', 'screening' => true, 'contrast' => 'intravenous'],
            ['name' => 'CT Brain Non-Contrast (NCCT)', 'code' => 'CT-BRAIN-NC', 'modality' => 'CT', 'price' => 6500, 'duration' => 15, 'prep' => 'Remove hairpins, earrings, dentures, and eyeglasses before scan.', 'screening' => false, 'contrast' => 'none'],
            ['name' => 'MRI Brain with Spectroscopy & Diffusion', 'code' => 'MR-BRAIN-SPEC', 'modality' => 'MR', 'price' => 16500, 'duration' => 45, 'prep' => 'Complete mandatory MRI safety implant checklist. No magnetic objects, watches, or credit cards.', 'screening' => true, 'contrast' => 'intravenous'],
            ['name' => 'MRI Lumbar Spine (LS Spine)', 'code' => 'MR-LUMBAR', 'modality' => 'MR', 'price' => 14500, 'duration' => 35, 'prep' => 'Wear comfortable hospital gown. Inform staff of any claustrophobia or pacemaker.', 'screening' => true, 'contrast' => 'none'],
            ['name' => 'Bilateral Full-Field Digital Mammography', 'code' => 'MG-BILATERAL', 'modality' => 'MG', 'price' => 5200, 'duration' => 20, 'prep' => 'Do not apply deodorant, antiperspirant, powder, or lotions under arms or on breasts on the day of exam.', 'screening' => false, 'contrast' => 'none'],
        ];

        foreach ($services as $s) {
            Service::updateOrCreate(
                ['code' => $s['code'], 'business_id' => $business->id],
                [
                    'name' => $s['name'],
                    'modality_id' => $modalityIds[$s['modality']],
                    'category_id' => $this->defaultCategoryId($business, $admin),
                    'price' => $s['price'],
                    'duration' => (string) $s['duration'],
                    'duration_minutes' => $s['duration'],
                    'preparation_instructions' => $s['prep'],
                    'requires_screening' => $s['screening'],
                    'contrast_type' => $s['contrast'],
                    'is_bookable_online' => true,
                    'created_by' => $admin->id,
                ]
            );
        }

        $this->seedScreeningForms($business, $admin, $modalityIds);
        $this->seedReportTemplates($business, $admin, $modalityIds);
        $this->seedNotificationTemplates($business, $admin);
        $this->seedInventoryCatalog($business, $admin);
    }

    private function defaultCategoryId(Business $business, User $admin): int
    {
        $category = \App\Models\Category::firstOrCreate(
            ['name' => 'Radiology', 'business_id' => $business->id],
            ['type' => 'service', 'created_by' => $admin->id]
        );

        return $category->id;
    }

    private function seedScreeningForms(Business $business, User $admin, array $modalityIds): void
    {
        $forms = [
            [
                'name' => 'MRI Safety Screening (Implant & Ferromagnetic Risk)',
                'slug' => 'mri-safety-screening',
                'description' => 'Mandatory patient safety questionnaire prior to entering Zone IV MRI Suite (1.5T / 3.0T).',
                'modality' => 'MR',
                'questions' => [
                    ['Do you have a cardiac pacemaker, defibrillator (ICD), or pacemaker leads?', 'ABSOLUTE CONTRAINDICATION unless MRI-Conditional with verified clearance.', true],
                    ['Do you have intracranial aneurysm clips, coils, or neurostimulator?', 'Requires ferromagnetic verification certificate.', true],
                    ['Have you ever had foreign body eye injury from grinding, welding, or shrapnel?', 'Requires orbit X-ray clearance if affirmative.', true],
                    ['Do you have cochlear implants, stapedectomy, or middle ear prosthetics?', null, true],
                    ['Do you wear any transdermal medication patch (e.g. Nicotine, Fentanyl, Clonidine)?', 'Aluminum backing can cause focal RF thermal burns; must remove before scan.', false],
                    ['Is there any possibility of pregnancy (first trimester) or severe claustrophobia?', null, false],
                ],
            ],
            [
                'name' => 'Contrast Media & Renal Safety Screening',
                'slug' => 'contrast-screening',
                'description' => 'Required before any IV Iodinated or Gadolinium-based contrast administration.',
                'modality' => null,
                'questions' => [
                    ['Have you ever had an allergic reaction, hives, or breathing difficulty from contrast dye?', 'Previous anaphylactoid reaction requires pre-medication protocol with steroids & antihistamines.', true],
                    ['Do you have known kidney disease, solitary kidney, or are you on hemodialysis?', 'Check eGFR / Serum Creatinine levels (eGFR < 30 mL/min/1.73m² risk of CIN / NSF).', true],
                    ['Do you have diabetes mellitus and take Metformin / Glucophage?', 'Metformin must be withheld for 48 hours post-contrast in renal-compromised patients.', false],
                    ['Are you currently pregnant or actively breastfeeding?', null, false],
                ],
            ],
        ];

        foreach ($forms as $f) {
            $form = ScreeningForm::updateOrCreate(
                ['slug' => $f['slug'], 'business_id' => $business->id],
                [
                    'name' => $f['name'],
                    'description' => $f['description'],
                    'modality_id' => $f['modality'] ? $modalityIds[$f['modality']] : null,
                    'is_active' => true,
                    'created_by' => $admin->id,
                ]
            );

            foreach ($f['questions'] as $i => [$text, $help, $blocking]) {
                ScreeningQuestion::updateOrCreate(
                    ['screening_form_id' => $form->id, 'question_text' => $text],
                    [
                        'help_text' => $help,
                        'answer_type' => 'boolean',
                        'risk_value' => 'yes',
                        'is_risk_blocking' => $blocking,
                        'sort_order' => $i + 1,
                    ]
                );
            }
        }
    }

    private function seedReportTemplates(Business $business, User $admin, array $modalityIds): void
    {
        $templates = [
            ['Chest X-Ray PA View (Normal Routine)', 'DX', 'Routine pre-operative / health screening evaluation.', 'Standard erect Posteroanterior (PA) view of the chest obtained at full inspiration.', "1. The trachea is central in the midline.\n2. The cardiothoracic ratio is normal (< 0.50). Normal cardiac size and contour.\n3. Both lungs are clear and well-expanded without focal consolidations, infiltrates, masses, or pneumothorax.\n4. Normal bronchovascular markings bilaterally.\n5. Both costophrenic angles and cardiophrenic sulci are sharp and clear.\n6. Mediastinal and hilar contours are within normal anatomical limits.\n7. Visualized thoracic cage bones, ribs, clavicles, and soft tissues appear unremarkable.", 'Normal study. No active cardiopulmonary pathology detected.', 'No immediate imaging follow-up required unless clinical status changes.'],
            ['HRCT Chest (Parenchymal Evaluation)', 'CT', 'Evaluation of progressive dyspnea and persistent non-productive cough.', 'High Resolution computed tomography of thorax performed in supine position with thin 1.0mm axial slices and multiplanar coronal/sagittal reformations in high-spatial frequency lung kernel.', "1. Trachea and central bronchi are patent without endobronchial lesions.\n2. Subpleural ground-glass opacities noted in basal segments bilaterally with associated fine reticular interstitial thickening and minimal traction bronchiectasis.\n3. No dense segmental consolidation, cavitation, or pulmonary nodule > 4mm.\n4. No pleural effusion or pneumothorax on either side.\n5. Mediastinal window reveals no enlarged lymph nodes (> 10mm short axis).\n6. Great vessels and cardiac chambers are of normal dimensions.\n7. Subdiaphragmatic upper abdominal sections show no acute abnormality.", 'Bilateral basal and subpleural interstitial lung changes consistent with early non-specific interstitial pneumonia (NSIP) pattern / post-inflammatory sequel.', 'Correlation with clinical autoimmune profile and Pulmonary Function Tests (PFTs/DLCO) recommended. Follow-up HRCT in 6 months.'],
            ['MRI Lumbar Spine (Degenerative Disc Disease)', 'MR', 'Low back pain radiating to left lower limb (L5/S1 dermatomal distribution).', 'Multiplanar, multisequence MRI of the lumbar spine performed on 1.5 Tesla scanner including Sagittal T1, T2, STIR, and Axial T1, T2 weighted images from L1 to S1 level.', "1. Normal lumbar lordosis preserved. Vertebral body heights and alignment are maintained.\n2. No evidence of bone marrow edema, compression fracture, or listhesis.\n3. L4-L5: Mild diffuse disc bulge causing minor indentation of the anterior thecal sac without significant neural foraminal narrowing.\n4. L5-S1: Marked reduction of disc height with disc desiccation (T2 hypointensity). A left posterolateral and paracentral disc protrusion measuring approx 4.8mm is noted, causing compression of the traversing left S1 nerve root in the lateral recess.\n5. Visualized conus medullaris terminates normally at L1 level with normal signal intensity.\n6. Facet joints and ligamenta flava appear unremarkable. Paravertebral soft tissues are normal.", 'L5-S1 left paracentral disc protrusion causing left lateral recess stenosis and left S1 traversing nerve root impingement, correlating with clinical left-sided sciatica.', 'Clinical and neurological correlation advised. Conservative physiotherapy or neurosurgical opinion if motor deficit progresses.'],
            ['Whole Abdomen Ultrasound (Normal)', 'US', 'Vague abdominal discomfort and routine health checkup.', 'Real-time B-mode and color Doppler ultrasonography of the whole abdomen and pelvis using 3.5MHz curvilinear transducer.', "1. Liver: Normal in size (13.8 cm span), homogenous echotexture with smooth margins. No focal parenchymal space-occupying lesion. Intrahepatic biliary radicals and portal vein are normal.\n2. Gallbladder: Well-distended with thin, smooth walls (2.1 mm). Lumen is completely clear without calculi, sludge, or polyps. Common Bile Duct (CBD) measures 3.8 mm (normal).\n3. Pancreas: Visualized head, body, and tail are normal in size and echogenicity without mass or pancreatic duct dilatation.\n4. Spleen: Normal size (10.2 cm), uniform echogenicity without focal lesion.\n5. Kidneys: Both kidneys are normal in size, shape, and cortical thickness. Right kidney: 10.4 cm, Left kidney: 10.8 cm. Good corticomedullary differentiation. No calculus, hydronephrosis, or solid mass.\n6. Urinary Bladder: Well-filled with smooth wall contour. No intravesical lesion or calculus.\n7. No free fluid or ascites in Morrison's pouch, pelvis, or peritoneal cavity.", 'Normal ultrasound examination of whole abdomen and pelvis. No sonographic evidence of acute intra-abdominal pathology.', 'Reassurance and symptomatic management as per treating physician.'],
            ['Bilateral Digital Mammography (BI-RADS 1)', 'MG', 'Asymptomatic routine screening mammogram.', 'Standard Cranio-Caudal (CC) and Medio-Lateral Oblique (MLO) full-field digital mammographic views of bilateral breasts.', "1. Breast Density: ACR Composition Category B (Scattered areas of fibroglandular density).\n2. Right Breast: Symmetric fibroglandular tissue distribution. No dominant solid mass, architectural distortion, or suspicious grouped microcalcifications.\n3. Left Breast: Symmetric appearance. No suspicious mass, focal asymmetry, or microcalcification clusters.\n4. Skin and nipple-areolar complexes are bilaterally normal without retraction or thickening.\n5. Axillary Regions: Bilateral benign-appearing lymph nodes with radiolucent fatty hilum. No suspicious adenopathy.", 'BI-RADS CATEGORY 1: Negative (Normal Bilateral Mammogram).', 'Continue routine annual or biennial screening mammography as per clinical guidelines.'],
        ];

        foreach ($templates as $t) {
            ReportTemplate::updateOrCreate(
                ['name' => $t[0], 'business_id' => $business->id],
                [
                    'modality_id' => $modalityIds[$t[1]],
                    'clinical_history' => $t[2],
                    'technique' => $t[3],
                    'findings' => $t[4],
                    'impression' => $t[5],
                    'recommendations' => $t[6],
                    'is_default' => false,
                    'created_by' => $admin->id,
                ]
            );
        }
    }

    private function seedNotificationTemplates(Business $business, User $admin): void
    {
        $templates = [
            ['Booking Confirmation & Exam Preparation', 'booking', 'whatsapp', 'Dear {patient_name}, your radiology appointment for {study_name} at {clinic_name} is confirmed for {time}, {date}. Token: {token}. Please follow prep instructions: {prep_notes}. Helpline: {clinic_phone}'],
            ['Waiting Room Token Call Alert', 'checkin', 'sms', 'Alert: Token {token} ({patient_name}), please proceed to {room_number} for your {modality} scan.'],
            ['Report Verified & Portal Ready Alert', 'ready', 'whatsapp', 'Dear {patient_name}, your official Radiology Report for {study_name} has been signed by {doctor_name} and is ready for collection or portal download. Access code: {mrn}'],
            ['Doctor Referral Critical Finding STAT Alert', 'critical', 'whatsapp', 'URGENT MEDICAL ALERT — {clinic_name}: Critical radiological finding identified for your patient {patient_name} (MRN: {mrn}, Study: {study_name}). Please contact the centre immediately.'],
        ];

        foreach ($templates as [$name, $category, $channel, $body]) {
            RisNotificationTemplate::updateOrCreate(
                ['name' => $name, 'business_id' => $business->id],
                [
                    'category' => $category,
                    'channel' => $channel,
                    'template_body' => $body,
                    'enabled' => true,
                ]
            );
        }
    }

    private function seedInventoryCatalog(Business $business, User $admin): void
    {
        $items = [
            ['CT-OMNI-350-100', 'Omnipaque 350 mg I/mL (Iohexol 100mL)', 'Iohexol Non-Ionic Low-Osmolar Iodinated Contrast', 'contrast_ct', 'CT', 'Vial (100mL)', 34, 15, 3850, 5500, 'GE Healthcare / Bio-Medical Express PK', 'CT Console Bay Cabinet A'],
            ['CT-ULTRA-370-100', 'Ultravist 370 mg I/mL (Iopromide 100mL)', 'Iopromide High-Concentration Iodinated Contrast', 'contrast_ct', 'CT', 'Vial (100mL)', 18, 10, 4400, 6200, 'Bayer Pakistan Diagnostics', 'CT Console Bay Cabinet A'],
            ['CT-VISI-320-100', 'Visipaque 320 mg I/mL (Iodixanol Iso-osmolar 100mL)', 'Iodixanol Iso-Osmolar Dimeric Iodinated Contrast', 'contrast_ct', 'CT', 'Vial (100mL)', 6, 8, 6500, 8900, 'GE Healthcare PK', 'CT Console Bay Locked Cabinet'],
            ['MR-DOTA-05-20', 'Dotarem 0.5 mmol/mL (Gadoterate Meglumine 20mL)', 'Gadoterate Meglumine Macrocyclic Gadolinium Contrast', 'contrast_mri', 'MRI', 'Vial (20mL)', 22, 12, 5200, 7500, 'Guerbet Pakistan / MedTech Solutions', 'MRI Prep Room Cold Cabinet'],
            ['MR-CLARIS-05-15', 'Clariscan 0.5 mmol/mL (Gadoteric Acid 15mL)', 'Gadoteric Acid Macrocyclic Paramagnetic Contrast', 'contrast_mri', 'MRI', 'Vial (15mL)', 14, 8, 4800, 6800, 'GE Healthcare PK', 'MRI Prep Room Shelf 2'],
            ['SYR-MEDRAD-200', 'Medrad Stellant Dual CT Syringe Fast Fill Kit (200mL x 2)', 'Dual Injector Syringe & Low Pressure Y-Tubing with Dual Check Valve', 'cannula_syringes', 'CT', 'Kit', 45, 20, 1950, 2800, 'Bayer Diagnostics Devices PK', 'CT Injector Cart Room 1'],
            ['CAN-BD-18G', 'BD Venflon Pro Safety IV Cannula 18G (Green)', '18 Gauge High-Flow Power Injector Rated Cannula', 'cannula_syringes', 'CT', 'Box (50)', 6, 4, 3200, 4500, 'Becton Dickinson Pakistan', 'IV Prep Station Cart'],
            ['EMERG-HYDRO-100', 'Inj. Solu-Cortef (Hydrocortisone Sodium Succinate 100mg)', 'Hydrocortisone Emergency IV Corticosteroid', 'pharmacy_emergency', 'ALL', 'Vial', 25, 10, 350, 450, 'Pfizer Pakistan Direct', 'Crash Cart Top Drawer (Red Box)'],
            ['EMERG-EPI-1MG', 'Inj. Epinephrine / Adrenaline (1:1000, 1mg/mL)', 'Adrenaline 1mg Ampoule for IM/SC Anaphylaxis', 'pharmacy_emergency', 'ALL', 'Ampoule (1mL)', 18, 8, 180, 250, 'Atco Pharma PK', 'Crash Cart Top Drawer (Adrenaline Box)'],
            ['PPE-LEAD-05', 'Radiation Protection 0.5mm Pb Equiv Frontal Lead Aprons', 'Lead Protective Vinyl Aprons with Thyroid Collar', 'ppe_safety', 'XRAY', 'Piece', 12, 10, 14500, 0, 'Bar-Ray Radiation Products', 'X-Ray & CT Console Apron Rack'],
        ];

        foreach ($items as [$code, $name, $generic, $category, $modality, $unit, $stock, $min, $cost, $price, $supplier, $location]) {
            $item = InventoryItem::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'generic_name' => $generic,
                    'category' => $category,
                    'modality' => $modality,
                    'unit' => $unit,
                    'current_stock' => $stock,
                    'min_threshold' => $min,
                    'unit_cost' => $cost,
                    'selling_price' => $price,
                    'is_billable' => $price > 0,
                    'requires_cold_chain' => $category === 'contrast_mri',
                    'supplier' => $supplier,
                    'storage_location' => $location,
                    'batches' => [
                        [
                            'batch_number' => 'SEED-'.substr($code, -4),
                            'expiry_date' => now()->addYear()->format('Y-m-d'),
                            'quantity' => $stock,
                            'received_date' => now()->subMonths(2)->format('Y-m-d'),
                        ],
                    ],
                    'business_id' => $business->id,
                ]
            );
        }
    }

    private function seedClinicProfile(Business $business, User $admin): void
    {
        $existing = Setting::where('business', $business->id)->where('key', 'ris_clinic_profile')->exists();
        if ($existing) {
            return;
        }

        Setting::create([
            'key' => 'ris_clinic_profile',
            'business' => $business->id,
            'created_by' => $admin->id,
            'value' => json_encode([
                'name' => $business->name,
                'branch' => '',
                'address' => '',
                'city' => '',
                'phone' => $admin->mobile_no ?? '',
                'emergencyPhone' => '',
                'email' => $admin->email,
                'website' => '',
                'pnraLicenseNo' => '',
                'pmcRegistrationNo' => '',
                'taxId' => '',
                'currencySymbol' => 'Rs.',
                'headerTagline' => 'Advanced Diagnostic & Interventional Radiological Imaging Centre',
                'invoiceFooterDisclaimer' => 'Computer-generated official receipt. Valid for physical report collection and online patient portal verification.',
                'reportLegalDisclaimer' => 'Radiological interpretation is an advisory clinical impression. Must be correlated with clinical examination and relevant laboratory findings.',
                'requireScreeningSignOff' => true,
                'enableCriticalFindingsAlerts' => true,
                'autoSendWhatsappReport' => false,
            ]),
        ]);
    }
}
