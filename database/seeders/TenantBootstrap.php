<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\InventoryItem;
use App\Models\Modality;
use App\Models\PaymentMethod;
use App\Models\Permission;
use App\Models\ReportMacro;
use App\Models\ReportTemplate;
use App\Models\RisNotificationTemplate;
use App\Models\Role;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
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
        // `db:seed` runs inside Model::unguarded() (Laravel SeedCommand) — re-enable
        // mass-assignment protection so seeders behave like the rest of the app.
        Model::reguard();

        $this->seedRoles($admin);
        $this->seedMasters($business, $admin);
        $this->seedClinicProfile($business, $admin);
    }

    // ==================== RBAC ====================

    private function seedRoles(User $admin): void
    {
        // Permissions are global catalog entries; roles are per-tenant. The
        // taxonomy AND the default role bundles live in the catalog so new
        // tenants and the RBAC migration backfill stay byte-identical.
        \App\Support\PermissionCatalog::sync();

        $bundles = \App\Support\PermissionCatalog::defaultBundles();

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

        // `region` feeds the reporting module's modality + body-region template
        // tier, so every procedure declares WHERE it images, not just what.
        $services = [
            ['name' => 'Chest X-Ray PA & Lateral Views', 'code' => 'DX-CHEST-PA', 'modality' => 'DX', 'price' => 1800, 'duration' => 15, 'prep' => 'Remove any metal necklaces, brassieres with underwire, or upper body jewelry.', 'screening' => false, 'contrast' => 'none', 'region' => 'Chest'],
            ['name' => 'Whole Abdomen & Pelvis Ultrasound', 'code' => 'US-ABD-PEL', 'modality' => 'US', 'price' => 3500, 'duration' => 25, 'prep' => '6 hours fasting prior to appointment. Drink 1 litre of water 1 hour before exam for full bladder.', 'screening' => false, 'contrast' => 'none', 'region' => 'Abdomen'],
            ['name' => 'Carotid Doppler Bilateral', 'code' => 'US-CAROTID', 'modality' => 'US', 'price' => 4500, 'duration' => 30, 'prep' => 'No special preparation needed. Wear a loose collar shirt.', 'screening' => false, 'contrast' => 'none', 'region' => 'Vascular'],
            ['name' => 'HRCT Chest (High Resolution CT)', 'code' => 'CT-CHEST-HR', 'modality' => 'CT', 'price' => 8500, 'duration' => 20, 'prep' => 'Fasting 4 hours if contrast indicated. Bring latest Serum Creatinine report.', 'screening' => true, 'contrast' => 'intravenous', 'region' => 'Chest'],
            ['name' => 'CT Brain Non-Contrast (NCCT)', 'code' => 'CT-BRAIN-NC', 'modality' => 'CT', 'price' => 6500, 'duration' => 15, 'prep' => 'Remove hairpins, earrings, dentures, and eyeglasses before scan.', 'screening' => false, 'contrast' => 'none', 'region' => 'Brain'],
            ['name' => 'MRI Brain with Spectroscopy & Diffusion', 'code' => 'MR-BRAIN-SPEC', 'modality' => 'MR', 'price' => 16500, 'duration' => 45, 'prep' => 'Complete mandatory MRI safety implant checklist. No magnetic objects, watches, or credit cards.', 'screening' => true, 'contrast' => 'intravenous', 'region' => 'Brain'],
            ['name' => 'MRI Lumbar Spine (LS Spine)', 'code' => 'MR-LUMBAR', 'modality' => 'MR', 'price' => 14500, 'duration' => 35, 'prep' => 'Wear comfortable hospital gown. Inform staff of any claustrophobia or pacemaker.', 'screening' => true, 'contrast' => 'none', 'region' => 'Spine'],
            ['name' => 'Bilateral Full-Field Digital Mammography', 'code' => 'MG-BILATERAL', 'modality' => 'MG', 'price' => 5200, 'duration' => 20, 'prep' => 'Do not apply deodorant, antiperspirant, powder, or lotions under arms or on breasts on the day of exam.', 'screening' => false, 'contrast' => 'none', 'region' => 'Breast'],
        ];

        foreach ($services as $s) {
            Service::updateOrCreate(
                ['code' => $s['code'], 'business_id' => $business->id],
                [
                    'name' => $s['name'],
                    'modality_id' => $modalityIds[$s['modality']],
                    'body_region' => $s['region'],
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
        $this->seedReportMacros($business, $admin, $modalityIds);
        $this->seedNotificationTemplates($business, $admin);
        $this->seedInventoryCatalog($business, $admin);
        $this->seedPaymentMethods($business, $admin);
    }

    /**
     * Seed each tenant's payment-method configuration with the five legacy
     * codes so existing invoice_payments.method values validate against the
     * tenant's own catalog. Tenants rename/deactivate/add methods from the
     * admin UI afterwards — nothing here is hard-coded at the edges.
     */
    private function seedPaymentMethods(Business $business, User $admin): void
    {
        $methods = [
            ['code' => 'cash', 'name' => 'Cash (Counter Drawer)', 'sort_order' => 1],
            ['code' => 'card', 'name' => 'Credit / Debit Card (POS)', 'sort_order' => 2],
            ['code' => 'bank', 'name' => 'Bank Transfer / Raast QR', 'sort_order' => 3],
            ['code' => 'mobile', 'name' => 'Mobile Wallet (Easypaisa / JazzCash)', 'sort_order' => 4],
            ['code' => 'insurance', 'name' => 'Insurance / Corporate Panel', 'sort_order' => 5],
        ];

        foreach ($methods as $m) {
            $method = PaymentMethod::withTrashed()->updateOrCreate(
                ['code' => $m['code'], 'business_id' => $business->id],
                [...$m, 'created_by' => $admin->id]
            );
            $method->restore();
        }
    }

    private function defaultCategoryId(Business $business, User $admin): int
    {
        $category = \App\Models\Category::firstOrCreate(
            ['name' => 'Radiology', 'business_id' => $business->id],
            ['created_by' => $admin->id]
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

        // Clinical matching dimensions per seeded template:
        // [code, body_region, age_group, sex, contrast]. A null age_group is
        // the AGE-AGNOSTIC template of that procedure — the safe fallback for
        // a patient whose cohort has no dedicated variant.
        $dimensions = [
            'Chest X-Ray PA View (Normal Routine)' => ['DX-CHEST-PA', 'Chest', null, null, 'without'],
            'HRCT Chest (Parenchymal Evaluation)' => ['CT-CHEST-HR', 'Chest', null, null, 'without'],
            'MRI Lumbar Spine (Degenerative Disc Disease)' => ['MR-LUMBAR', 'Spine', null, null, 'without'],
            'Whole Abdomen Ultrasound (Normal)' => ['US-ABD-PEL', 'Abdomen', null, null, 'without'],
            'Bilateral Digital Mammography (BI-RADS 1)' => ['MG-BILATERAL', 'Breast', null, 'female', 'without'],
        ];

        // Structured skeleton for the abdominal survey: each organ is a
        // discrete field with CURATED normal phrasing, so the "Normal" quick
        // control inserts approved text instead of the UI inventing it.
        $abdomenStructure = [
            ['key' => 'liver', 'label' => 'Liver', 'type' => 'radio', 'options' => ['Normal', 'Focal lesion', 'Diffusely abnormal'], 'normalText' => 'Liver is normal in size and echotexture without focal lesion.', 'required' => true],
            ['key' => 'gallbladder', 'label' => 'Gallbladder', 'type' => 'radio', 'options' => ['Normal', 'Calculi', 'Wall thickening', 'Post-cholecystectomy'], 'normalText' => 'Gallbladder is well distended, thin walled and free of calculus or sludge.'],
            ['key' => 'cbd_mm', 'label' => 'Common bile duct', 'type' => 'measurement', 'unit' => 'mm', 'placeholder' => '3.8', 'normalText' => 'Common bile duct is of normal calibre.'],
            ['key' => 'pancreas', 'label' => 'Pancreas', 'type' => 'radio', 'options' => ['Normal', 'Not visualized', 'Abnormal'], 'normalText' => 'Pancreas is normal in size and echotexture.'],
            ['key' => 'spleen', 'label' => 'Spleen', 'type' => 'radio', 'options' => ['Normal', 'Splenomegaly', 'Focal lesion'], 'normalText' => 'Spleen is normal in size and echotexture.'],
            ['key' => 'hydronephrosis', 'label' => 'Hydronephrosis', 'type' => 'select', 'options' => ['None', 'Mild', 'Moderate', 'Severe'], 'normalText' => 'No hydronephrosis on either side.'],
            ['key' => 'bladder', 'label' => 'Urinary bladder', 'type' => 'radio', 'options' => ['Normal', 'Calculus', 'Wall thickening'], 'normalText' => 'Urinary bladder is well filled with smooth wall contour and no intravesical lesion.'],
            ['key' => 'free_fluid', 'label' => 'Free fluid / ascites', 'type' => 'checkbox', 'normalText' => 'No free fluid or ascites in the peritoneal cavity.'],
        ];

        foreach ($templates as $t) {
            [$code, $region, $ageGroup, $sex, $contrast] = $dimensions[$t[0]] ?? [null, null, null, null, null];

            ReportTemplate::updateOrCreate(
                ['name' => $t[0], 'business_id' => $business->id],
                [
                    'modality_id' => $modalityIds[$t[1]],
                    'code' => $code,
                    'body_region' => $region,
                    'age_group' => $ageGroup,
                    'sex' => $sex,
                    'contrast' => $contrast,
                    'structured_fields' => $t[0] === 'Whole Abdomen Ultrasound (Normal)' ? $abdomenStructure : null,
                    'clinical_history' => $t[2],
                    'technique' => $t[3],
                    'findings' => $t[4],
                    'impression' => $t[5],
                    'recommendations' => $t[6],
                    'scope' => 'tenant',
                    'is_default' => false,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );
        }

        $serviceIds = Service::where('business_id', $business->id)->pluck('id', 'code');

        foreach ($this->ageVariantTemplates() as $variant) {
            ReportTemplate::updateOrCreate(
                ['name' => $variant['name'], 'business_id' => $business->id],
                [
                    'modality_id' => $modalityIds[$variant['modality']],
                    'service_id' => ! empty($variant['service']) ? ($serviceIds[$variant['service']] ?? null) : null,
                    'code' => $variant['code'],
                    'body_region' => $variant['region'],
                    'age_group' => $variant['ageGroup'],
                    'contrast' => $variant['contrast'],
                    'clinical_history' => $variant['history'],
                    'technique' => $variant['technique'],
                    'findings' => $variant['findings'],
                    'impression' => $variant['impression'],
                    'recommendations' => $variant['recommendations'],
                    'scope' => 'tenant',
                    'is_default' => false,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
                ]
            );
        }
    }

    /**
     * Age-specific baselines. Each is a DRAFTING AID for a named cohort — it
     * is never an auto-confirmed normal, and the resolver only selects one when
     * the patient's age band matches exactly.
     *
     * @return list<array<string,string|null>>
     */
    private function ageVariantTemplates(): array
    {
        return [
            [
                // The age-agnostic baseline of the same procedure: an adult
                // study resolves here, a child resolves to the pediatric
                // template below — never the other way round.
                'name' => 'CT Brain (Non-Contrast) — Adult Baseline',
                'code' => 'CT-BRAIN-NC', 'modality' => 'CT', 'region' => 'Brain',
                'service' => 'CT-BRAIN-NC',
                'ageGroup' => null, 'contrast' => 'without',
                'history' => 'Head trauma, headache, focal neurological deficit, seizure or suspected acute intracranial pathology in an adult.',
                'technique' => 'Non-contrast axial CT of the brain with coronal and sagittal reformations (120 kVp, automated exposure control).',
                'findings' => "1. Grey-white differentiation is preserved. No intra- or extra-axial hemorrhage, infarct, or space-occupying lesion.\n2. Ventricles and sulci are age-appropriate; no hydrocephalus or cerebral oedema.\n3. Midline structures are central with no shift or herniation.\n4. Cranial vault and skull base show no fracture on bone windows.\n5. Visualized paranasal sinuses and mastoid air cells are normally aerated.\n6. No abnormal extra-axial collection.",
                'impression' => 'No acute intracranial abnormality on this non-contrast CT brain.',
                'recommendations' => 'Clinical correlation. MRI brain if symptoms persist or a posterior fossa/ischaemic cause is suspected.',
            ],
            [
                'name' => 'Pediatric CT Brain (Non-Contrast)',
                'code' => 'CT-BRAIN-NC', 'modality' => 'CT', 'region' => 'Brain',
                'service' => 'CT-BRAIN-NC',
                'ageGroup' => 'pediatric', 'contrast' => 'without',
                'history' => 'Head trauma, seizure, persistent headache or suspected raised intracranial pressure in a pediatric patient.',
                'technique' => 'Non-contrast axial CT of the brain with coronal and sagittal reformations, low-dose pediatric protocol (100 kVp, automated exposure control).',
                'findings' => "1. Grey-white differentiation is preserved for age. No intra- or extra-axial hemorrhage.\n2. No skull vault fracture or depressed fracture on bone windows.\n3. Ventricles and subarachnoid spaces are normal in size and configuration for age.\n4. Midline structures are central; no mass effect or midline shift.\n5. No hydrocephalus, no cerebral oedema, and no abnormal extra-axial collection.\n6. Visualized paranasal sinuses and mastoid air cells are normally aerated.",
                'impression' => 'No acute intracranial abnormality on this non-contrast pediatric CT brain.',
                'recommendations' => 'Clinical correlation and neurological observation as indicated. MRI if symptoms persist or evolve.',
            ],
            [
                'name' => 'Infant Cranial Ultrasound (Neonatal Head)',
                'code' => 'US-CRANIAL', 'modality' => 'US', 'region' => 'Brain',
                'ageGroup' => 'infant', 'contrast' => 'without',
                'history' => 'Neonatal/infant cranial ultrasound for prematurity, suspected intraventricular hemorrhage, or abnormal head circumference.',
                'technique' => 'Real-time cranial ultrasound through the anterior fontanelle in coronal and sagittal planes using a 7.5 MHz sector transducer, supplemented by mastoid fontanelle views.',
                'findings' => "1. Bilateral lateral ventricles are symmetrical and of normal size for gestational and postnatal age.\n2. No germinal matrix, intraventricular, or parenchymal hemorrhage.\n3. The periventricular white matter shows normal echogenicity without cystic change.\n4. The corpus callosum and basal ganglia are normal in appearance.\n5. No extra-axial fluid collection. The cerebellum is normal on the mastoid fontanelle view.\n6. Midline structures are central.",
                'impression' => 'Normal cranial ultrasound for age. No intracranial hemorrhage or ventriculomegaly.',
                'recommendations' => 'Routine neonatal follow-up. Repeat cranial ultrasound only if clinically indicated.',
            ],
            [
                'name' => 'Pediatric Chest X-Ray (PA View)',
                'code' => 'DX-CHEST-PA', 'modality' => 'DX', 'region' => 'Chest',
                'ageGroup' => 'pediatric', 'contrast' => 'without',
                'history' => 'Cough, fever, breathlessness or suspected lower respiratory tract infection in a pediatric patient.',
                'technique' => 'Erect PA chest radiograph obtained at adequate inspiration with standard pediatric exposure parameters.',
                'findings' => "1. Both lung fields are clear without consolidation, collapse, or hyperinflation.\n2. The heart size is within normal limits for age (cardiothoracic ratio within pediatric norms).\n3. Both costophrenic angles are clear; no pleural effusion or pneumothorax.\n4. The mediastinum and thymic silhouette are normal for age.\n5. No radio-opaque foreign body in the aerodigestive tract.\n6. The visualized skeletal thorax is normal for age.",
                'impression' => 'No acute cardiopulmonary abnormality on this pediatric chest radiograph.',
                'recommendations' => 'Symptomatic treatment and clinical review as indicated.',
            ],
        ];
    }

    /**
     * Macro / snippet library. Clinical report text lives in tenant-managed
     * data so a clinic can curate it — never hardcoded inside a UI component.
     * `null` modality = available for every study.
     *
     * @param array<string,int> $modalityIds
     */
    private function seedReportMacros(Business $business, User $admin, array $modalityIds): void
    {
        // Clinical snippet text comes from ReportMacroLibrary — the same
        // curated source the backfill migration uses, so a clinic provisioned
        // today and a clinic migrated from the earlier schema end up with
        // identical libraries.
        $macros = collect(\App\Support\ReportMacroLibrary::all())
            ->map(fn (array $m) => [
                $m['name'], $m['modality'], $m['shortcut'],
                $m['findings'], $m['impression'], $m['recommendations'],
            ])
            ->all();

        foreach ($macros as [$name, $modalityCode, $shortcut, $findings, $impression, $recommendations]) {
            ReportMacro::updateOrCreate(
                ['name' => $name, 'business_id' => $business->id],
                [
                    'modality_id' => $modalityCode ? ($modalityIds[$modalityCode] ?? null) : null,
                    'shortcut' => $shortcut,
                    'findings' => $findings,
                    'impression' => $impression,
                    'recommendations' => $recommendations,
                    'scope' => 'tenant',
                    'is_archived' => false,
                    'created_by' => $admin->id,
                    'updated_by' => $admin->id,
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
            ['CT-OMNI-350-100', 'Omnipaque 350 mg I/mL (Iohexol 100mL)', 'Iohexol Non-Ionic Low-Osmolar Iodinated Contrast', 'contrast_ct', 'CT', 'Vial (100mL)', 34, 15, 3850, 5500, 'GE Healthcare', 'CT Console Bay Cabinet A'],
            ['CT-ULTRA-370-100', 'Ultravist 370 mg I/mL (Iopromide 100mL)', 'Iopromide High-Concentration Iodinated Contrast', 'contrast_ct', 'CT', 'Vial (100mL)', 18, 10, 4400, 6200, 'Bayer', 'CT Console Bay Cabinet A'],
            ['CT-VISI-320-100', 'Visipaque 320 mg I/mL (Iodixanol Iso-osmolar 100mL)', 'Iodixanol Iso-Osmolar Dimeric Iodinated Contrast', 'contrast_ct', 'CT', 'Vial (100mL)', 6, 8, 6500, 8900, 'GE Healthcare', 'CT Console Bay Locked Cabinet'],
            ['MR-DOTA-05-20', 'Dotarem 0.5 mmol/mL (Gadoterate Meglumine 20mL)', 'Gadoterate Meglumine Macrocyclic Gadolinium Contrast', 'contrast_mri', 'MRI', 'Vial (20mL)', 22, 12, 5200, 7500, 'Guerbet', 'MRI Prep Room Cold Cabinet'],
            ['MR-CLARIS-05-15', 'Clariscan 0.5 mmol/mL (Gadoteric Acid 15mL)', 'Gadoteric Acid Macrocyclic Paramagnetic Contrast', 'contrast_mri', 'MRI', 'Vial (15mL)', 14, 8, 4800, 6800, 'GE Healthcare', 'MRI Prep Room Shelf 2'],
            ['SYR-MEDRAD-200', 'Medrad Stellant Dual CT Syringe Fast Fill Kit (200mL x 2)', 'Dual Injector Syringe & Low Pressure Y-Tubing with Dual Check Valve', 'cannula_syringes', 'CT', 'Kit', 45, 20, 1950, 2800, 'Bayer', 'CT Injector Cart Room 1'],
            ['CAN-BD-18G', 'BD Venflon Pro Safety IV Cannula 18G (Green)', '18 Gauge High-Flow Power Injector Rated Cannula', 'cannula_syringes', 'CT', 'Box (50)', 6, 4, 3200, 4500, 'Becton Dickinson', 'IV Prep Station Cart'],
            ['EMERG-HYDRO-100', 'Inj. Solu-Cortef (Hydrocortisone Sodium Succinate 100mg)', 'Hydrocortisone Emergency IV Corticosteroid', 'pharmacy_emergency', 'ALL', 'Vial', 25, 10, 350, 450, 'Pfizer', 'Crash Cart Top Drawer (Red Box)'],
            ['EMERG-EPI-1MG', 'Inj. Epinephrine / Adrenaline (1:1000, 1mg/mL)', 'Adrenaline 1mg Ampoule for IM/SC Anaphylaxis', 'pharmacy_emergency', 'ALL', 'Ampoule (1mL)', 18, 8, 180, 250, 'Atco Pharma', 'Crash Cart Top Drawer (Adrenaline Box)'],
            ['PPE-LEAD-05', 'Radiation Protection 0.5mm Pb Equiv Frontal Lead Aprons', 'Lead Protective Vinyl Aprons with Thyroid Collar', 'ppe_safety', 'XRAY', 'Piece', 12, 10, 14500, 0, 'Bar-Ray Radiation Products', 'X-Ray & CT Console Apron Rack'],
        ];

        foreach ($items as [$code, $name, $generic, $category, $modality, $unit, $stock, $min, $cost, $price, $supplier, $location]) {
            $item = InventoryItem::updateOrCreate(
                ['code' => $code, 'business_id' => $business->id],
                [
                    'name' => $name,
                    'generic_name' => $generic,
                    'category' => $category,
                    'modality' => $modality,
                    'unit' => $unit,
                    // Starter catalog ships unstocked: real stock/batches are
                    // entered by the clinic when goods actually arrive.
                    'current_stock' => 0,
                    'min_threshold' => $min,
                    'unit_cost' => $cost,
                    'selling_price' => $price,
                    'is_billable' => $price > 0,
                    'requires_cold_chain' => $category === 'contrast_mri',
                    'supplier' => $supplier,
                    'storage_location' => $location,
                    'batches' => [],
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
                'phone' => '',
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
