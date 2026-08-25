<?php

namespace App\Http\Controllers;

use App\Models\Modality;
use App\Models\ReportTemplate;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportTemplateController extends Controller
{
    public function index()
    {
        if (! Auth::user()->isAbleTo('report template manage')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $templates = ReportTemplate::forClinic()
            ->with(['serviceData', 'modality'])
            ->orderBy('name')
            ->get();
        $services = Service::forClinic()->orderBy('name')->pluck('name', 'id');
        $modalities = Modality::forClinic()->orderBy('name')->pluck('name', 'id');

        return view('report-templates.index', compact('templates', 'services', 'modalities'));
    }

    public function store(Request $request)
    {
        if (! Auth::user()->isAbleTo('report template create')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'service_id' => 'nullable|integer|exists:services,id',
            'modality_id' => 'nullable|integer|exists:modalities,id',
            'clinical_history' => 'nullable|string',
            'technique' => 'nullable|string',
            'findings' => 'nullable|string',
            'impression' => 'nullable|string',
            'recommendations' => 'nullable|string',
        ]);

        ReportTemplate::create([
            ...$validated,
            'is_default' => $request->boolean('is_default'),
            'business_id' => getActiveBusiness(),
            'created_by' => creatorId(),
        ]);

        return redirect()->back()->with('success', __('Report template created.'));
    }

    public function update(Request $request, ReportTemplate $template)
    {
        if (! Auth::user()->isAbleTo('report template edit')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $template->update([
            ...$request->only(['name', 'clinical_history', 'technique', 'findings', 'impression', 'recommendations']),
            'is_default' => $request->boolean('is_default'),
        ]);

        return redirect()->back()->with('success', __('Report template updated.'));
    }

    public function destroy(ReportTemplate $template)
    {
        if (! Auth::user()->isAbleTo('report template delete')) {
            return redirect()->back()->with('error', __('Permission denied.'));
        }

        $template->delete();

        return redirect()->back()->with('success', __('Report template deleted.'));
    }
}
