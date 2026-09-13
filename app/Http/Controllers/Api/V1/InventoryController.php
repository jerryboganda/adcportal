<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\ApiShape;
use App\Models\AdverseReaction;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Clinical inventory: SKU registry, stock movements (server-authoritative
 * stock math via InventoryService) and contrast adverse-reaction register.
 */
class InventoryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $items = InventoryItem::forClinic($this->tenantId())->orderBy('name')->get();
        $transactions = InventoryTransaction::where('business_id', $this->tenantId())
            ->with('performer')
            ->orderByDesc('id')
            ->limit(500)
            ->get();
        $reactions = AdverseReaction::where('business_id', $this->tenantId())
            ->orderByDesc('id')
            ->get();

        return $this->ok([
            'items' => $items->map(fn ($i) => ApiShape::inventoryItem($i))->all(),
            'transactions' => $transactions->map(fn ($t) => ApiShape::inventoryTransaction($t))->all(),
            'reactions' => $reactions->map(fn ($r) => ApiShape::adverseReaction($r))->all(),
        ]);
    }

    public function storeItem(Request $request): JsonResponse
    {
        $this->denyUnless('setting manage');

        $validated = $this->validateItem($request);

        $item = InventoryItem::create([
            'code' => strtoupper(trim($validated['code'])),
            'name' => $validated['name'],
            'generic_name' => $validated['genericName'] ?? $validated['name'],
            'category' => $validated['category'],
            'modality' => $validated['modality'] ?? 'ALL',
            'unit' => $validated['unit'] ?? null,
            'current_stock' => (int) ($validated['currentStock'] ?? 0),
            'min_threshold' => (int) ($validated['minThreshold'] ?? 0),
            'unit_cost' => (float) ($validated['unitCost'] ?? 0),
            'selling_price' => (float) ($validated['sellingPrice'] ?? 0),
            'is_billable' => (float) ($validated['sellingPrice'] ?? 0) > 0,
            'requires_cold_chain' => $validated['category'] === 'contrast_mri',
            'supplier' => $validated['supplier'] ?? null,
            'storage_location' => $validated['storageLocation'] ?? null,
            'batches' => $validated['batches'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'business_id' => $this->tenantId(),
        ]);

        $this->audit('inventory_created', $item, [
            'summary' => "Created inventory SKU {$item->code} ({$item->name}) with opening stock {$item->current_stock}.",
        ]);

        return response()->json(['data' => ['item' => ApiShape::inventoryItem($item)]], 201);
    }

    public function storeTransaction(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'itemId' => ['required', 'integer'],
            'type' => ['required', 'in:usage_study,stock_in,adjustment,wastage,expired_discard'],
            'quantity' => ['required', 'integer', 'min:1'],
            'batchNumber' => ['nullable', 'string', 'max:60'],
            'direction' => ['nullable', 'in:in,out'], // for type=adjustment
            'tokenNumber' => ['nullable', 'string', 'max:30'],
            'patientName' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->denyUnless('study acquire');

        $item = InventoryItem::forClinic($this->tenantId())->findOrFail($validated['itemId']);

        $tx = app(InventoryService::class)->recordTransaction($item, [
            'type' => $validated['type'],
            'quantity' => (int) $validated['quantity'],
            'batch_number' => $validated['batchNumber'] ?? null,
            'direction' => $validated['direction'] ?? 'out',
            'token_number' => $validated['tokenNumber'] ?? null,
            'patient_name' => $validated['patientName'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->audit('inventory_movement', $item, [
            'summary' => sprintf('%s: %s %s %s %s.', ucfirst(str_replace('_', ' ', $validated['type'])), $validated['quantity'], $item->unit ?? 'units', in_array($validated['type'], ['usage_study', 'wastage', 'expired_discard']) ? 'out of' : 'into', $item->name),
        ]);

        return response()->json([
            'data' => [
                'transaction' => ApiShape::inventoryTransaction($tx->fresh('performer')),
                'item' => ApiShape::inventoryItem($item->fresh()),
            ],
        ], 201);
    }

    public function storeAdverseReaction(Request $request): JsonResponse
    {
        $this->denyUnless('study screen');

        $validated = $request->validate([
            'appointmentId' => ['nullable', 'integer'],
            'tokenNumber' => ['required', 'string', 'max:30'],
            'patientName' => ['required', 'string', 'max:255'],
            'modality' => ['required', 'in:CT,MRI'],
            'contrastAgent' => ['required', 'string', 'max:255'],
            'batchNumber' => ['nullable', 'string', 'max:60'],
            'administeredVolume' => ['nullable', 'string', 'max:30'],
            'severity' => ['required', 'in:mild,moderate,severe_anaphylaxis,extravasation'],
            'symptoms' => ['required', 'array', 'min:1'],
            'symptoms.*' => ['string', 'max:255'],
            'treatmentGiven' => ['required', 'string', 'max:2000'],
            'outcome' => ['required', 'in:resolved_on_site,referred_to_er,under_observation'],
            'supervisingDoctor' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $reaction = AdverseReaction::create([
            'appointment_id' => $validated['appointmentId'] ?? null,
            'token_number' => strtoupper($validated['tokenNumber']),
            'patient_name' => $validated['patientName'],
            'modality' => $validated['modality'],
            'contrast_agent' => $validated['contrastAgent'],
            'batch_number' => $validated['batchNumber'] ?? null,
            'administered_volume' => $validated['administeredVolume'] ?? null,
            'severity' => $validated['severity'],
            'symptoms' => array_values($validated['symptoms']),
            'treatment_given' => $validated['treatmentGiven'],
            'outcome' => $validated['outcome'],
            'reported_by' => Auth::user()->name,
            'supervising_doctor' => $validated['supervisingDoctor'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'business_id' => $this->tenantId(),
        ]);

        $this->audit('adverse_reaction_reported', $reaction, [
            'summary' => "{$validated['severity']} contrast reaction for {$validated['patientName']} (#{$reaction->token_number}) — outcome: {$validated['outcome']}.",
        ]);

        return response()->json(['data' => ['reaction' => ApiShape::adverseReaction($reaction)]], 201);
    }

    private function validateItem(Request $request): array
    {
        return $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:ris_inventory_items,code'],
            'name' => ['required', 'string', 'max:255'],
            'genericName' => ['nullable', 'string', 'max:255'],
            'category' => ['required', 'in:contrast_ct,contrast_mri,cannula_syringes,ppe_safety,pharmacy_emergency,general_consumable'],
            'modality' => ['nullable', 'in:CT,MRI,XRAY,US,ALL'],
            'unit' => ['nullable', 'string', 'max:50'],
            'currentStock' => ['nullable', 'integer', 'min:0'],
            'minThreshold' => ['nullable', 'integer', 'min:0'],
            'unitCost' => ['nullable', 'numeric', 'min:0'],
            'sellingPrice' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'storageLocation' => ['nullable', 'string', 'max:255'],
            'batches' => ['nullable', 'array'],
            'batches.*.batchNumber' => ['required_with:batches', 'string', 'max:60'],
            'batches.*.expiryDate' => ['required_with:batches', 'date'],
            'batches.*.quantity' => ['required_with:batches', 'integer', 'min:0'],
            'batches.*.receivedDate' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
