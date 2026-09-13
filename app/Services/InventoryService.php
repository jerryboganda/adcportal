<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

/**
 * Clinical inventory movements. Stock math happens HERE (single authority) —
 * never on the client — so concurrent studies cannot oversell the same vial.
 */
class InventoryService
{
    /**
     * Auto-deduct one contrast unit when a study with a recorded contrast agent
     * completes acquisition. Mirrors the portal's low-stock alert behaviour.
     */
    public function deductForStudy(Appointment $appointment): ?InventoryTransaction
    {
        $dose = $appointment->doseLog()->first();

        if (! $dose || empty($dose->contrast_agent)) {
            return null;
        }

        $agent = mb_strtolower($dose->contrast_agent);
        $category = $appointment->ServiceData?->modality_id
            ? optional(\App\Models\Modality::find($appointment->ServiceData->modality_id))->code === 'MR'
                ? 'contrast_mri'
                : 'contrast_ct'
            : null;

        $item = InventoryItem::forClinic()
            ->when($category !== null, fn ($q) => $q->where('category', $category))
            ->get()
            ->first(fn (InventoryItem $i) => $this->matchesContrast($i, $agent));

        if (! $item || $item->current_stock <= 0) {
            return null;
        }

        return $this->recordTransaction($item, [
            'type' => 'usage_study',
            'quantity' => 1,
            'batch_number' => $item->batches[0]['batch_number'] ?? 'BATCH-AUTO',
            'appointment_id' => $appointment->id,
            'token_number' => $appointment->token_number,
            'patient_name' => $appointment->patientDisplayName(),
            'notes' => sprintf(
                'Auto-deducted during %s acquisition (%s mL administered).',
                optional($appointment->ServiceData?->modality)->code ?? 'CT',
                $dose->contrast_volume_ml ?? 0
            ),
        ], performedBy: $appointment->performed_by_staff_id ?? auth()->id());
    }

    /**
     * Fuzzy match a recorded contrast agent to a catalog SKU: any significant
     * token of the agent string (e.g. "iohexol", "omnipaque", "350") that
     * appears in the item's name or generic name.
     */
    private function matchesContrast(InventoryItem $item, string $agent): bool
    {
        $haystacks = array_filter([
            mb_strtolower($item->name),
            mb_strtolower((string) $item->generic_name),
        ]);

        $tokens = preg_split('/[^a-z0-9]+/', $agent, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4) {
                continue;
            }
            foreach ($haystacks as $haystack) {
                if ($haystack !== '' && str_contains($haystack, $token)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Record a stock movement (stock_in / adjustment / wastage / expired_discard
     * / usage_study) and keep the item's on-hand quantity and batches consistent.
     */
    public function recordTransaction(InventoryItem $item, array $data, ?int $performedBy = null): InventoryTransaction
    {
        return DB::transaction(function () use ($item, $data, $performedBy) {
            $type = $data['type'];
            $qty = (int) $data['quantity'];

            $deducts = in_array($type, ['usage_study', 'wastage', 'expired_discard'], true)
                || ($type === 'adjustment' && ($data['direction'] ?? 'out') === 'out');
            $delta = $deducts ? -$qty : $qty;

            $newStock = max(0, $item->current_stock + $delta);

            $batches = $item->batches ?? [];
            if (! $deducts && ! empty($data['batch_number'])) {
                // Stock-in / positive adjustment: upsert the batch line.
                $existing = collect($batches)->first(fn ($b) => ($b['batch_number'] ?? null) === $data['batch_number']);
                if ($existing) {
                    $batches = collect($batches)
                        ->map(fn ($b) => ($b['batch_number'] ?? null) === $data['batch_number']
                            ? array_merge($b, ['quantity' => ($b['quantity'] ?? 0) + $qty])
                            : $b)
                        ->all();
                } else {
                    $batches = array_values([
                        [
                            'batch_number' => $data['batch_number'],
                            'expiry_date' => $data['expiry_date'] ?? now()->addYear()->toDateString(),
                            'quantity' => $qty,
                            'received_date' => now()->toDateString(),
                        ],
                        ...$batches,
                    ]);
                }
            }

            $item->forceFill(['current_stock' => $newStock, 'batches' => $batches])->save();

            $tx = InventoryTransaction::create([
                'inventory_item_id' => $item->id,
                'item_name' => $item->name,
                'type' => $type,
                'quantity' => $qty,
                'batch_number' => $data['batch_number'] ?? null,
                'appointment_id' => $data['appointment_id'] ?? null,
                'token_number' => $data['token_number'] ?? null,
                'patient_name' => $data['patient_name'] ?? null,
                'notes' => $data['notes'] ?? null,
                'performed_by' => $performedBy ?? auth()->id(),
                'business_id' => getActiveBusiness(),
            ]);

            if ($deducts && $newStock <= $item->min_threshold) {
                NotificationService::lowStock($item, $newStock);
            }

            return $tx;
        });
    }
}
