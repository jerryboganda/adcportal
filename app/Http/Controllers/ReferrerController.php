<?php

namespace App\Http\Controllers;

use App\Models\Referrer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReferrerController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        if (Auth::user()->isAbleTo('referrer manage')) {
            $referrers = Referrer::where('business_id', getActiveBusiness())
                ->orderBy('name')
                ->get();
            return view('referrer.index', compact('referrers'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (Auth::user()->isAbleTo('referrer create')) {
            return view('referrer.create');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (Auth::user()->isAbleTo('referrer create')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|string|max:255',
                    'specialty' => 'nullable|string|max:255',
                    'clinic' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:50',
                    'email' => 'nullable|email|max:255',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            Referrer::create([
                'name' => $request->name,
                'specialty' => $request->specialty,
                'clinic' => $request->clinic,
                'phone' => $request->phone,
                'email' => $request->email,
                'is_active' => $request->has('is_active') ? 1 : 1,
                'business_id' => getActiveBusiness(),
                'created_by' => creatorId(),
            ]);

            return redirect()->back()->with('success', __('Referrer created successfully!'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Referrer $referrer)
    {
        if (Auth::user()->isAbleTo('referrer edit')) {
            return view('referrer.edit', compact('referrer'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Referrer $referrer)
    {
        if (Auth::user()->isAbleTo('referrer edit')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|string|max:255',
                    'specialty' => 'nullable|string|max:255',
                    'clinic' => 'nullable|string|max:255',
                    'phone' => 'nullable|string|max:50',
                    'email' => 'nullable|email|max:255',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $referrer->update([
                'name' => $request->name,
                'specialty' => $request->specialty,
                'clinic' => $request->clinic,
                'phone' => $request->phone,
                'email' => $request->email,
                'is_active' => $request->has('is_active') ? 1 : 0,
            ]);

            return redirect()->back()->with('success', __('Referrer updated successfully!'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Referrer $referrer)
    {
        if (Auth::user()->isAbleTo('referrer delete')) {
            $referrer->delete();
            return redirect()->back()->with('success', __('Referrer deleted successfully!'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Toggle referrer active status
     */
    public function toggleStatus(Referrer $referrer)
    {
        if (Auth::user()->isAbleTo('referrer edit')) {
            $referrer->is_active = !$referrer->is_active;
            $referrer->save();
            return redirect()->back()->with('success', __('Referrer status updated!'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
}
