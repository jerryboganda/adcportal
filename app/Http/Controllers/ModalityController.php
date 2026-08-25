<?php

namespace App\Http\Controllers;

use App\Models\Modality;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ModalityController extends Controller
{
    public function index()
    {
        if (! Auth::user()->isAbleTo('modality manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        // Combined masters page: modalities + their scan rooms + downtime windows.
        $modalities = Modality::forClinic()->withCount('procedures')->orderBy('name')->get();
        $rooms = \App\Models\Room::forClinic()->with(['modality', 'locationData'])->orderBy('name')->get();
        $downtimes = \App\Models\EquipmentDowntime::whereIn('room_id', $rooms->pluck('id'))
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        return view('modality.index', compact('modalities', 'rooms', 'downtimes'));
    }

    public function store(Request $request)
    {
        if (! Auth::user()->isAbleTo('modality create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:modalities,code,NULL,id,business_id,'.getActiveBusiness(),
            'color' => 'nullable|string|max:9',
            'buffer_minutes' => 'nullable|integer|min:0|max:240',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        Modality::create([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'color' => $request->color ?: '#0080b6',
            'buffer_minutes' => (int) $request->buffer_minutes,
            'is_active' => $request->boolean('is_active', true),
            'business_id' => getActiveBusiness(),
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Modality created successfully!'));
    }

    public function update(Request $request, Modality $modality)
    {
        if (! Auth::user()->isAbleTo('modality edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:10|unique:modalities,code,'.$modality->id.',id,business_id,'.getActiveBusiness(),
            'buffer_minutes' => 'nullable|integer|min:0|max:240',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $modality->update([
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'color' => $request->color ?: '#0080b6',
            'buffer_minutes' => (int) $request->input('buffer_minutes', 0),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with('success', __('Modality updated successfully!'));
    }

    public function destroy(Modality $modality)
    {
        if (! Auth::user()->isAbleTo('modality delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        if ($modality->procedures()->exists()) {
            return redirect()->back()->with('error', __('Cannot delete a modality that has procedures assigned.'));
        }

        $modality->delete();

        return redirect()->back()->with('success', __('Modality deleted successfully!'));
    }
}
