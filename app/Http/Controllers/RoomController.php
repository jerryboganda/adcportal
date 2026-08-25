<?php

namespace App\Http\Controllers;

use App\Models\EquipmentDowntime;
use App\Models\Modality;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomController extends Controller
{
    public function index()
    {
        if (! Auth::user()->isAbleTo('room manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        // Same combined masters page as modality.index.
        $modalities = Modality::forClinic()->withCount('procedures')->orderBy('name')->get();
        $rooms = Room::forClinic()->with(['modality', 'locationData'])->orderBy('name')->get();
        $downtimes = EquipmentDowntime::whereIn('room_id', $rooms->pluck('id'))
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at')
            ->get();

        return view('modality.index', compact('rooms', 'modalities', 'downtimes'));
    }

    public function store(Request $request)
    {
        if (! Auth::user()->isAbleTo('room create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'modality_id' => 'required|integer|exists:modalities,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'capacity_per_slot' => 'nullable|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        Room::create([
            'name' => $request->name,
            'modality_id' => $request->modality_id,
            'location_id' => $request->location_id,
            'capacity_per_slot' => max(1, (int) $request->input('capacity_per_slot', 1)),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active', true),
            'business_id' => getActiveBusiness(),
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Room created successfully!'));
    }

    public function update(Request $request, Room $room)
    {
        if (! Auth::user()->isAbleTo('room edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'modality_id' => 'required|integer|exists:modalities,id',
            'capacity_per_slot' => 'nullable|integer|min:1|max:20',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        $room->update([
            'name' => $request->name,
            'modality_id' => $request->modality_id,
            'location_id' => $request->location_id,
            'capacity_per_slot' => max(1, (int) $request->input('capacity_per_slot', 1)),
            'description' => $request->description,
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()->back()->with('success', __('Room updated successfully!'));
    }

    public function destroy(Room $room)
    {
        if (! Auth::user()->isAbleTo('room delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $room->delete();

        return redirect()->back()->with('success', __('Room deleted successfully!'));
    }

    // ==================== Downtime windows ====================

    public function storeDowntime(Request $request)
    {
        if (! Auth::user()->isAbleTo('room edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validator = \Validator::make($request->all(), [
            'room_id' => 'required|integer|exists:rooms,id',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return redirect()->back()->with('error', $validator->getMessageBag()->first());
        }

        EquipmentDowntime::create($request->only('room_id', 'reason') + [
            'starts_at' => $request->starts_at,
            'ends_at' => $request->ends_at,
        ]);

        return redirect()->back()->with('success', __('Downtime window recorded.'));
    }

    public function destroyDowntime(EquipmentDowntime $downtime)
    {
        if (! Auth::user()->isAbleTo('room edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $downtime->delete();

        return redirect()->back()->with('success', __('Downtime window removed.'));
    }
}
