<?php

namespace App\Http\Controllers;

use App\Events\CreateService;
use App\Models\Service;
use App\Models\Business;
use App\Models\category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Events\saveTax;
use App\Events\UpdateService;
use App\Events\StoreCompoundService;
use Illuminate\Support\Facades\Validator;

class ServiceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if (Auth::user()->isAbleTo('service create')) {
            $business = Business::find($request->business_id);
            $category = category::where('created_by', creatorId())->where('business_id', $business->id)->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select category'])->pluck('name', 'id');

            return view('service.create', compact('business', 'category'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (Auth::user()->isAbleTo('service create')) {
            $rules = [
                'name' => 'required',
                'category' => 'required',
                'service_image' => 'required',
                // 'duration' => 'required',
            ];
            $business = Business::find($request->business_id);

            // Single-clinic app: RepeatAppointments add-on removed.
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $service                   = new Service();
            $service->name             = $request->name;
            $service->category_id      = $request->category;
            $service->is_free          = isset($request->is_service_free) ? '1' : '0';
            $service->price            = isset($request->price) ? $request->price : '0';
            $service->duration         = $request->duration;
            $service->description      = !empty($request->description) ? $request->description : '';
            $service->business_id      = !empty($business) ? $business->id : 0;
            $service->created_by       = creatorId();
            if ($request->hasFile('service_image')) {
                $filenameWithExt = $request->file('service_image')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('service_image')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $uplaod = upload_file($request, 'service_image', $fileNameToStore, 'Service');
                if ($uplaod['flag'] == 1) {
                    $url = $uplaod['url'];
                } else {
                    return redirect()->back()->with('error', $uplaod['msg']);
                }
            }
            $service->image  = !empty($request->service_image) ? $url : '';

            // Single-clinic app: RepeatAppointments add-on removed.
            $service->save();
            $tab = 2;

            // Common Event
            event(new CreateService($service, $request->all()));

            return redirect()->back()->with('success', __('Service successfully created.'))->with('tab', $tab);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Service $service)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Service $service)
    {
        if (Auth::user()->isAbleTo('service edit')) {
            $category = category::where('created_by', creatorId())->where('business_id', $service->business_id)->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select category'])->pluck('name', 'id');
            $business = Business::find($service->business_id);
            return view('service.edit', compact('service', 'category'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Service $service)
    {
        if (Auth::user()->isAbleTo('service edit')) {

            $rules = [
                'name' => 'required',
                'category' => 'required',
                // 'price' => 'required',
                // 'duration' => 'required',
            ];

            // Single-clinic app: RepeatAppointments add-on removed.
            $validator = Validator::make($request->all(), $rules);
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }


            $service->name          = $request->name;
            $service->category_id   = $request->category;
            $service->is_free          = isset($request->is_service_free) ? '1' : '0';
            $service->price            = isset($request->is_service_free) ? '0' :$request->price;
            $service->duration         = $request->duration;
            $service->description   = $request->description;

            if ($request->hasFile('service_image')) {
                $filenameWithExt = $request->file('service_image')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('service_image')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $uplaod = upload_file($request, 'service_image', $fileNameToStore, 'Service');
                if ($uplaod['flag'] == 1) {
                    if (!empty($service->image)) {
                        delete_file($service->image);
                    }
                    $url = $uplaod['url'];
                } else {
                    $tab = 2;
                    return redirect()->back()->with('error', $uplaod['msg'])->with('tab', $tab);
                }
                $service->image  = !empty($request->service_image) ? $url : '';
            }

            // Single-clinic app: RepeatAppointments add-on removed.
            $service->save();
            $tab = 2;

            // Common Event
            event(new UpdateService($service, $request->all()));

            return redirect()->back()->with('success', __('Service updated successfully!'))->with('tab', $tab);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Service $service)
    {
        if (Auth::user()->isAbleTo('location delete')) {
            if (!empty($service->image)) {
                delete_file($service->image);
            }
            $service->delete();
            $tab = 2;
            return redirect()->back()->with('error', __('Service successfully delete.'))->with('tab', $tab);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    // function for online appointmnt button
    public function createOnlineAppointment(Request $request, $serviceId)
    {
        $service = Service::where('id', $serviceId)->first();
        if ($service) {
            return view('online_appointment.create_online_appointment', compact('service'));
        } else {
            return response()->json(['error' => __('Service Not Found !!')], 401);
        }
    }

    public function saveOnlineMeetingSetting(Request $request, $serviceId)
    {
        $service = Service::where('id', $serviceId)->first();
        if ($service) {
            $onlineMeetings = [];
            if ($request->has('zoom_meeting')) {
                $onlineMeetings[] = 'zoom meeting';
            }
            if ($request->has('google_meet')) {
                $onlineMeetings[] = 'google meet';
            }

            $onlineMeetingsString = implode(',', $onlineMeetings);

            $service->online_appointments = $onlineMeetingsString;
            $service->save();
            $tab = 2;
            return redirect()->back()->with('success', 'Settings saved successfully!')->with('tab',$tab);
        } else {
            $tab = 2;
            return redirect()->back()->with('error', 'Service Not Found !!')->with('tab',$tab);
        }
    }

    public function checkServiceOnlineMeeting(Request $request)
    {
        if (!empty($request->service)) {
            $service = Service::where('id', $request->service)->first();
            if ($service) {
                if ((!empty($service->online_appointments))) {
                    $html = '';
                    $loadStep = '';
                    $loadStep = view('online_appointment.online_appointment_step', compact('service'))->render();
                    $html = view('online_appointment.show_online_appointment', compact('service'))->render();

                    $return['loadStep'] = $loadStep;
                    $return['html'] = $html;
                    return response()->json($return);
                }
            } else {
                return response()->json([], 400);
            }
        } else {
            return response()->json([], 400);
        }
    }


    public function checkServiceOnlineMeetingFormLayout(Request $request)
    {
        if (!empty($request->service)) {
            $service = Service::where('id', $request->service)->first();
            if ($service) {
                $business = Business::where('id', $service->business_id)->first();
                if ((!empty($service->online_appointments))) {
                    $html = '';
                    $loadStep = '';
                    $loadStep = view('form_layout.online_appointment.online_appointment_step', compact('service', 'business'))->render();
                    $html = view('form_layout.online_appointment.show_online_appointment', compact('service', 'business'))->render();

                    $return['loadStep'] = $loadStep;
                    $return['html'] = $html;
                    return response()->json($return);
                }
            } else {
                return response()->json([], 400);
            }
        } else {
            return response()->json([], 400);
        }
    }
}
