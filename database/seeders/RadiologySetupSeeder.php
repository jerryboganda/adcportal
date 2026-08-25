<?php

namespace Database\Seeders;

use App\Models\Modality;
use App\Models\ScreeningForm;
use App\Models\ScreeningQuestion;
use Illuminate\Database\Seeder;

/**
 * Seeds the radiology masters so a fresh install is usable immediately:
 * standard modalities plus MRI-safety and contrast screening forms.
 */
class RadiologySetupSeeder extends Seeder
{
    public function run(): void
    {
        $admin = \App\Models\User::where('type', 'admin')->first();

        if (! $admin) {
            return;
        }

        $businessId = getActiveBusiness($admin->id);

        $modalities = [
            ['name' => 'X-Ray', 'code' => 'DX', 'color' => '#0080b6', 'buffer_minutes' => 10],
            ['name' => 'Ultrasound', 'code' => 'US', 'color' => '#21c9b0', 'buffer_minutes' => 5],
            ['name' => 'CT Scan', 'code' => 'CT', 'color' => '#fa9c30', 'buffer_minutes' => 15],
            ['name' => 'MRI', 'code' => 'MR', 'color' => '#a969ba', 'buffer_minutes' => 20],
            ['name' => 'Mammography', 'code' => 'MG', 'color' => '#df3e9d', 'buffer_minutes' => 15],
        ];

        $modalityIds = [];
        foreach ($modalities as $m) {
            $modality = Modality::withTrashed()->updateOrCreate(
                ['code' => $m['code'], 'business_id' => $businessId],
                [...$m, 'is_active' => true, 'created_by' => $admin->id]
            );
            $modality->restore();
            $modalityIds[$m['code']] = $modality->id;
        }

        // MRI safety questionnaire — the classic implant/safety battery.
        if (! ScreeningForm::where('slug', 'mri-safety-screening')->exists()) {
            $form = ScreeningForm::create([
                'name' => 'MRI Safety Screening',
                'slug' => 'mri-safety-screening',
                'description' => 'Mandatory safety questionnaire before entering the MRI suite.',
                'modality_id' => $modalityIds['MR'] ?? null,
                'is_active' => true,
                'business_id' => $businessId,
                'created_by' => $admin->id,
            ]);

            $questions = [
                'Do you have a pacemaker or implanted defibrillator?',
                'Do you have any metal implants, clips, plates, or screws?',
                'Have you ever had eye-metal injury or welding work?',
                'Do you wear any medication patch? ',
                'Could you be pregnant or possibly pregnant?',
                'Do you suffer from claustrophobia?',
            ];
            foreach ($questions as $i => $q) {
                ScreeningQuestion::create([
                    'screening_form_id' => $form->id,
                    'question_text' => $q,
                    'answer_type' => 'boolean',
                    'risk_value' => 'yes',
                    'is_risk_blocking' => true,
                    'sort_order' => $i,
                ]);
            }
        }

        // Contrast screening — kidney issues / prior reactions.
        if (! ScreeningForm::where('slug', 'contrast-screening')->exists()) {
            $form = ScreeningForm::create([
                'name' => 'Contrast Media Screening',
                'slug' => 'contrast-screening',
                'description' => 'Required before any contrast-enhanced study.',
                'modality_id' => null,
                'is_active' => true,
                'business_id' => $businessId,
                'created_by' => $admin->id,
            ]);

            $questions = [
                'Have you ever had an allergic reaction to contrast dye?',
                'Do you have known kidney disease or are you on dialysis?',
                'Do you have diabetes and take metformin?',
                'Are you currently breastfeeding?',
            ];
            foreach ($questions as $i => $q) {
                ScreeningQuestion::create([
                    'screening_form_id' => $form->id,
                    'question_text' => $q,
                    'answer_type' => 'boolean',
                    'risk_value' => 'yes',
                    'is_risk_blocking' => true,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
