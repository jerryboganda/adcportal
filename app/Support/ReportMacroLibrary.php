<?php

namespace App\Support;

/**
 * Starter reporting library for a new clinic.
 *
 * This is the single source of truth for the baseline snippet text: the tenant
 * seeder and the backfill migration both consume it, so a clinic bootstrapped
 * today and a clinic backfilled from the earlier schema get the SAME curated
 * content. Once seeded it is ordinary tenant data — editable, archivable and
 * isolatable per clinic.
 *
 * Each entry is a drafting aid. Nothing here is auto-applied or auto-finalized.
 */
final class ReportMacroLibrary
{
    /**
     * @return list<array{name: string, modality: ?string, shortcut: ?string, findings: string, impression: string, recommendations: ?string}>
     */
    public static function all(): array
    {
        return [
            [
                'name' => 'Normal Study (Generic)', 'modality' => null, 'shortcut' => '.normal',
                'findings' => 'Systematic anatomical review reveals normal tissue characteristics, contours, and alignment. No focal lesion, abnormal fluid collection, or inflammatory change is identified.',
                'impression' => 'Unremarkable radiological examination.',
                'recommendations' => 'Clinical correlation advised.',
            ],
            [
                'name' => 'No Previous Comparison Available', 'modality' => null, 'shortcut' => '.noprior',
                'findings' => 'No prior imaging of the same region is available in the PACS archive for comparison.',
                'impression' => 'No comparison study available.',
                'recommendations' => null,
            ],
            [
                'name' => 'MR Brain — Normal', 'modality' => 'MR', 'shortcut' => '.mrbrain',
                'findings' => 'Brain parenchyma demonstrates normal signal characteristics on all pulse sequences. No acute infarction, hemorrhage, or space-occupying lesion. Ventricles, cisterns, and sulci are within normal limits for age. Midline structures are central. Major intracranial flow voids are preserved.',
                'impression' => 'Unremarkable MRI brain examination. No acute intracranial pathology.',
                'recommendations' => 'Clinical follow-up as indicated.',
            ],
            [
                'name' => 'MR Lumbar Spine — L4/L5 Disc Protrusion', 'modality' => 'MR', 'shortcut' => '.mrlumbar',
                'findings' => 'Physiological lumbar lordosis is maintained. The L4-L5 intervertebral disc demonstrates a diffuse bulge with a focal posterior central/paracentral protrusion indenting the thecal sac and causing mild bilateral neuroforaminal narrowing. The conus medullaris terminates normally at the L1 level.',
                'impression' => 'L4-L5 posterior disc protrusion with mild thecal sac impingement and bilateral neural exit foraminal narrowing.',
                'recommendations' => 'Physiotherapy and neurosurgical/orthopaedic clinical correlation advised.',
            ],
            [
                'name' => 'CT Chest — Normal', 'modality' => 'CT', 'shortcut' => '.nchest',
                'findings' => 'Both lungs are well aerated with normal bronchovascular markings. No pulmonary consolidation, nodule, mass, or ground-glass opacity. Trachea and central bronchi are patent. Mediastinum and hila show no lymphadenopathy. Heart size is normal. No pleural effusion or pneumothorax.',
                'impression' => 'Normal computed tomography of the chest.',
                'recommendations' => 'Routine follow-up.',
            ],
            [
                'name' => 'CT Abdomen — Acute Appendicitis', 'modality' => 'CT', 'shortcut' => '.appendicitis',
                'findings' => 'The appendix is distended measuring 11 mm in outer diameter with circumferential mural thickening and avid mucosal hyperenhancement. Extensive periappendiceal fat stranding with localized fluid in the right iliac fossa. An appendicolith is noted at the base.',
                'impression' => 'Findings highly suspicious of acute appendicitis with localized peritonitis.',
                'recommendations' => 'Immediate surgical consultation / emergency intervention recommended.',
            ],
            [
                'name' => 'CT Brain — No Acute Intracranial Abnormality', 'modality' => 'CT', 'shortcut' => '.nctbrain',
                'findings' => 'Grey-white differentiation is preserved. No intra- or extra-axial hemorrhage, infarct, or mass lesion. Ventricles and sulci are normal for age. Midline is central. No skull fracture on bone windows.',
                'impression' => 'No acute intracranial abnormality on non-contrast CT brain.',
                'recommendations' => 'Clinical correlation; MRI if symptoms persist.',
            ],
            [
                'name' => 'DX Chest — Clear', 'modality' => 'DX', 'shortcut' => '.cxrclear',
                'findings' => 'The lung fields are clear bilaterally with no parenchymal infiltrate, consolidation, or mass. The cardiothoracic ratio is within normal limits. Both costophrenic and cardiophrenic angles are sharp. The visualized bony thorax and soft tissues are unremarkable.',
                'impression' => 'Clear chest radiograph. No acute cardiopulmonary abnormality.',
                'recommendations' => 'No immediate imaging follow-up required.',
            ],
            [
                'name' => 'DX — No Acute Fracture', 'modality' => 'DX', 'shortcut' => '.nofracture',
                'findings' => 'Cortical margins are intact without evidence of acute fracture, dislocation, or bone destruction. Joint spaces are preserved. Surrounding soft tissues show no abnormal swelling or radio-opaque foreign body.',
                'impression' => 'No radiographically detectable acute fracture or dislocation.',
                'recommendations' => 'Clinical correlation; repeat views in 7-10 days if symptoms persist.',
            ],
            [
                'name' => 'US Abdomen — Normal', 'modality' => 'US', 'shortcut' => '.usabdnormal',
                'findings' => 'Liver is normal in size, contour, and echotexture with no focal lesion. Gallbladder is well distended, thin walled, with no calculus or sludge. Common bile duct is of normal calibre. Pancreas and spleen are unremarkable. Both kidneys show normal size, corticomedullary differentiation, and no hydronephrosis or calculus. Urinary bladder is clear.',
                'impression' => 'Unremarkable whole abdomen ultrasound examination.',
                'recommendations' => 'Routine clinical management.',
            ],
            [
                'name' => 'US Abdomen — Cholelithiasis', 'modality' => 'US', 'shortcut' => '.gallstones',
                'findings' => 'The gallbladder is well distended with acoustic shadowing produced by multiple mobile echogenic calculi, the largest measuring 14 mm. Gallbladder wall thickness is normal (2.2 mm). No pericholecystic fluid. The common bile duct is of normal calibre.',
                'impression' => 'Cholelithiasis without sonographic evidence of acute cholecystitis.',
                'recommendations' => 'Gastroenterology / general surgery consultation advised.',
            ],
            [
                'name' => 'MG — BI-RADS 1 (Negative)', 'modality' => 'MG', 'shortcut' => '.biraads1',
                'findings' => 'Bilateral mammograms show predominantly fibroglandular breast density (ACR Density B). No dominant mass, architectural distortion, or suspicious clustered microcalcification. Skin and nipple-areolar complexes are normal bilaterally. Visualized axillary lymph nodes are benign in appearance.',
                'impression' => 'BI-RADS CATEGORY 1: Negative mammogram.',
                'recommendations' => 'Annual screening mammography recommended.',
            ],
            [
                'name' => 'MG — BI-RADS 2 (Benign)', 'modality' => 'MG', 'shortcut' => '.biraads2',
                'findings' => 'Bilateral symmetric breast parenchyma. A well-circumscribed, oval, radiolucent fat-containing lesion with a thin capsule in the right upper outer quadrant, consistent with a benign oil cyst / lipoma. No suspicious microcalcification or architectural distortion.',
                'impression' => 'BI-RADS CATEGORY 2: Benign findings.',
                'recommendations' => 'Routine annual screening mammography.',
            ],
        ];
    }
}
