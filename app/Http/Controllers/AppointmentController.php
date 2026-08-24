<?php

namespace App\Http\Controllers;

use App\DataTables\AppointmentDataTable;
use App\Events\AdditionalServicePayment;
use App\Events\CreateAppoinment;
use App\Events\AppointmentPaymentData;
use App\Events\AppointmentStatus;
use App\Events\DeleteAppointment;
use App\Models\Appointment;
use App\Models\AppointmentPayment;
use App\Models\Location;
use App\Models\Service;
use App\Models\Staff;
use App\Models\Customer;
use App\Models\ContactUs;
use App\Models\BusinessHours;
use App\Models\Business;
use App\Models\BusinessHoliday;
use App\Models\User;
use App\Models\Role;
use App\Models\Setting;
use App\Models\File;
use App\Models\CustomField;
use App\Models\EmailTemplate;
use App\Models\CustomStatus;
use App\Models\Category;
use App\Models\ThemeSetting;
use App\Models\Testimonial;
use App\Models\Blog;
use App\Models\Referrer;
use Exception;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Cookie;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    /**
     * Display a listing of the resource.
     */

    public function index(Request $request, AppointmentDataTable $dataTable)
    {
        if (Auth::user()->isAbleTo('appointment manage')) {
            $business = Business::find(($request->business) ? $request->business : getActiveBusiness());
            if (!empty($business)) {
                $service = Service::where('created_by', $business->created_by)->where('business_id', $business->id)->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select Service'])->pluck('name', 'id');
                $company_settings = getCompanyAllSetting($business->created_by, $business->id);
                $dataTable->getBusinessAndSettings($business, $company_settings);
                return $dataTable->with('request', $request)->render('appointment.index', compact('service'));
            } else {
                return abort(404);
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (Auth::user()->isAbleTo('appointment create')) {
            $location = Location::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select Location'])->pluck('name', 'id');

            $service = Service::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select Service'])->pluck('name', 'id');

            $customer = Customer::where('created_by', creatorId())->where('business_id', getActiveBusiness())->get()->pluck('name', 'user_id')->prepend('select customer');


            $staff = Staff::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select Staff'])->pluck('name', 'id');


            $customer = Customer::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'user_id')->get()->prepend(['user_id' => null, 'name' => 'Select Customer'])->pluck('name', 'user_id');


            $busineshours = BusinessHours::where('created_by', creatorId())
                ->where('business_id', getActiveBusiness())
                ->where('day_off', 'on')
                ->select('day_name')
                ->get()
                ->pluck('day_name')
                ->map(function ($day) {
                    return date('w', strtotime($day));
                })
                ->toArray();

            $businesholiday = BusinessHoliday::where('created_by', creatorId())
                ->where('business_id', getActiveBusiness())
                ->pluck('date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('d-m-Y');
                })
                ->toArray();
            // $combinedArray = array_merge($busineshours, $businesholiday);
            $combinedArray = $busineshours;

            $referrers = Referrer::where('business_id', getActiveBusiness())->where('is_active', true)->orderBy('name')->pluck('name', 'id')->prepend(__('Select Referring Doctor'), '');

            return view('appointment.create', compact('location', 'service', 'staff', 'customer', 'busineshours', 'busineshours', 'businesholiday', 'combinedArray', 'referrers'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if (Auth::user()->isAbleTo('appointment create')) {
            $rules = [
                'location' => 'required',
                'service' => 'required',
                'staff' => 'nullable',
                'appointment_date' => 'required',
                'duration' => 'required',
            ];

            if ($request->has('new_customer') && $request->new_customer == 'on') {
                $rules['customer_name'] = 'required';
                $rules['customer_email'] = 'required|email';
                $rules['customer_gender'] = 'required';
                $rules['customer_dob'] = 'required';
                $rules['customer_phone'] = 'required';
            } else {
                $rules['customer'] = 'required';
            }

            $validator = \Validator::make($request->all(), $rules);

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $customer_id = $request->customer;

            if ($request->has('new_customer') && $request->new_customer == 'on') {
                $user = User::where('email', $request->customer_email)->where('created_by', creatorId())->first();
                if (!$user) {
                    $user = User::create([
                        'name' => $request->customer_name,
                        'email' => $request->customer_email,
                        'mobile_no' => $request->customer_phone,
                        'email_verified_at' => date('Y-m-d H:i:s'),
                        'password' => null,
                        'type' => 'customer',
                        'lang' => 'en',
                        'active_status' => 1,
                        'active_business' => getActiveBusiness(),
                        'business_id' => getActiveBusiness(),
                        'created_by' => creatorId(),
                    ]);
                    
                    $role_r = Role::where('name', 'customer')->where('created_by', creatorId())->first();
                    if ($role_r) {
                        $user->addRole($role_r);
                    }
                }

                $customer = Customer::where('user_id', $user->id)->where('business_id', getActiveBusiness())->first();
                if (!$customer) {
                    $customer = Customer::create([
                        'name' => $request->customer_name,
                        'user_id' => $user->id,
                        'gender' => $request->customer_gender,
                        'dob' => $request->customer_dob,
                        'business_id' => getActiveBusiness(),
                        'created_by' => creatorId(),
                    ]);
                }
                $customer_id = $user->id;
            }

            $default_status = company_setting('default_status', creatorId(), getActiveBusiness());
            $service = Service::find($request->service);

            $appointment = new Appointment();
            $appointment->customer_id = $customer_id;
            $appointment->location_id = $request->location;
            $appointment->service_id = $request->service;
            $appointment->staff_id = $request->staff;
            $appointment->date = !empty($request->appointment_date) ? $request->appointment_date : '';
            $appointment->time = !empty($request->duration) ? $request->duration : '';
            $appointment->notes = !empty($request->notes) ? $request->notes : '';
            $appointment->referrer_id = !empty($request->referrer_id) ? $request->referrer_id : null;
            $appointment->referred_by = !empty($request->referred_by) ? $request->referred_by : null;
            $appointment->appointment_status = !empty($default_status) ? $default_status : 'Pending';
            $appointment->payment_type = !empty($request->payment_type) ? $request->payment_type : 'Manually';
            $appointment->business_id = getActiveBusiness();
            $appointment->created_by = creatorId();
            $appointment->save();

            $payment = AppointmentPayment::create([
                'appointment_id' => $appointment->id,
                'payment_type' => $appointment->payment_type,
                'amount' => $service->price,
                'payment_date' => now(),
                'business_id' => $appointment->business_id,
                'created_by' => $appointment->created_by,
            ]);


            $appointment_number = Appointment::appointmentNumberFormat($appointment->id, $appointment->created_by, $appointment->business_id);
            //Email notification
            $company_settings = getCompanyAllSetting();

            if ((!empty($company_settings['Create Appointment']) && $company_settings['Create Appointment'] == true)) {
                $business = Business::where('id', getActiveBusiness())->first();
                $trackingUrl = route('find.appointment', ['businessSlug' => $business->slug]);
                $uArr = [
                    'company_name' => $appointment->business->name ?? '',
                    'service' => $appointment->ServiceData ? $appointment->ServiceData->name : '-',
                    'location' => $appointment->LocationData ? $appointment->LocationData->name : '-',
                    'staff' => $appointment->StaffData->user ? $appointment->StaffData->user->name : '-',
                    'appointment_date' => $request->input('appointment_date'),
                    'appointment_time' => $request->input('duration'),
                    'appointment_number' => $appointment_number,
                    'tracking_url' => $trackingUrl,

                ];
                $resp = EmailTemplate::sendEmailTemplate('Create Appointment', [$appointment->CustomerData->customer->email], $uArr);
            }
            event(new CreateAppoinment($appointment, $request));

            return redirect()->route('appointment.index')->with('success', __('Appointment successfully created.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));

            // return redirect()->back()->with('success', __('Appointment successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $appointment = Appointment::with('payment')->where('id', $id)->first();

        return view('appointment.show', compact('appointment'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Appointment $appointment)
    {
        if (Auth::user()->isAbleTo('appointment edit')) {
            $location = Location::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select Location'])->pluck('name', 'id');

            $service = Service::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select Service'])->pluck('name', 'id');

            $customer = Customer::where('created_by', creatorId())->where('business_id', getActiveBusiness())->get()->pluck('name', 'user_id')->prepend('select customer');


            $staff = Staff::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'id')->get()->prepend(['id' => null, 'name' => 'Select Staff'])->pluck('name', 'id');

            $customer = Customer::where('created_by', creatorId())->where('business_id', getActiveBusiness())->select('name', 'user_id')->get()->prepend(['user_id' => null, 'name' => 'Select Customer'])->pluck('name', 'user_id');


            $busineshours = BusinessHours::where('created_by', creatorId())
                ->where('business_id', getActiveBusiness())
                ->where('day_off', 'on')
                ->select('day_name')
                ->get()
                ->pluck('day_name')
                ->map(function ($day) {
                    return date('w', strtotime($day));
                })
                ->toArray();

            $businesholiday = BusinessHoliday::where('created_by', creatorId())
                ->where('business_id', getActiveBusiness())
                ->pluck('date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('d-m-Y');
                })
                ->toArray();
            // $combinedArray = array_merge($busineshours, $businesholiday);
            $combinedArray = $busineshours;

            $timeSlots = timeSlot($appointment->service_id, $appointment->date);

            $referrers = Referrer::where('business_id', getActiveBusiness())->where('is_active', true)->orderBy('name')->pluck('name', 'id')->prepend(__('Select Referring Doctor'), '');

            return view('appointment.edit', compact('location', 'service', 'staff', 'customer', 'busineshours', 'busineshours', 'appointment', 'timeSlots', 'combinedArray', 'businesholiday', 'referrers'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Appointment $appointment)
    {
        if (Auth::user()->isAbleTo('appointment edit')) {

            $validator = \Validator::make(
                $request->all(),
                [
                    // 'customer' => 'required',
                    'location' => 'required',
                    'service' => 'required',
                    'staff' => 'nullable|exists:staff,id',
                    'appointment_date' => 'required',
                    'duration' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $service = Service::find($request->service);

            $appointment->customer_id = $request->customer;
            $appointment->location_id = $request->location;
            $appointment->service_id = $request->service;
            $appointment->staff_id = $request->staff;
            $appointment->date = !empty($request->appointment_date) ? $request->appointment_date : '';
            $appointment->time = !empty($request->duration) ? $request->duration : '';
            $appointment->notes = !empty($request->notes) ? $request->notes : '';
            $appointment->referrer_id = !empty($request->referrer_id) ? $request->referrer_id : null;
            $appointment->referred_by = !empty($request->referred_by) ? $request->referred_by : null;
            $appointment->save();

            $AppointmentPayment = AppointmentPayment::where('appointment_id', $appointment->id)->first();
            $AppointmentPayment->amount = $service->price;
            $AppointmentPayment->save();

            return redirect()->back()->with('success', __('Appointment updated successfully!'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Appointment $appointment)
    {
        if (Auth::user()->isAbleTo('appointment delete')) {
            event(new DeleteAppointment($appointment));
            $appointment->delete();
            return redirect()->back()->with('error', __('Appointment successfully delete.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function appointmentDuration(Request $request)
    {
        $request->validate([
            'service' => ['required', 'integer'],
            'date' => ['required', 'date_format:d-m-Y'],
            'staff' => ['nullable', 'integer'],
        ]);

        $service = Service::find($request->service);

        if (empty($service)) {
            return response()->json(['error' => __('Service not found!')], 404);
        }

        if (!empty($request->service) && !empty($request->date)) {
            // Cached AvailabilityService (60s slot cache + indexed booked query).
            return response()->json([
                'timeSlots' => timeSlot((int) $request->service, $request->date, null, null, $request->staff ? (int) $request->staff : null),
                'result' => 'success',
            ]);
        }

        return response()->json(['result' => 'error']);
    }


    public function appointmentForm(Request $request, $slug = null, $appointment = null)
    {
        $slug = $request->slug;

        $business = Business::where('slug', $slug)->first();
        if ($business) {
            $categories = Category::where('business_id', $business->id)
                ->where('created_by', $business->created_by)
                ->orderBy('id', 'asc')
                ->get();
            $services = Service::where('business_id', $business->id)->get();
            $locations = Location::where('business_id', $business->id)->get();
            $staffs = Staff::where('business_id', $business->id)->get();

            if (false) {
                // Single-clinic app: FlexibleDays / ServiceSlotScheduler add-ons removed.
                $busineshours = [];
            } else {
                $busineshours = BusinessHours::where('created_by', $business->created_by)
                    ->where('business_id', $business->id)
                    ->where('day_off', 'on')
                    ->select('day_name')
                    ->get()
                    ->pluck('day_name')
                    ->map(function ($day) {
                        return date('w', strtotime($day));
                    })
                    ->toArray();
            }
            $businesholiday = BusinessHoliday::where('created_by', $business->created_by)
                ->where('business_id', $business->id)
                ->pluck('date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('d-m-Y');
                })
                ->toArray();
            // $combinedArray = array_merge($busineshours, $businesholiday);
            $combinedArray = $busineshours;
            $files = File::where('business_id', $business->id)->where('created_by', $business->created_by)->first();

            $company_settings = getCompanyAllSetting($business->created_by, $business->id);
            $bookingModes = isset($company_settings['booking_mode']) ? explode(',', $company_settings['booking_mode']) : [];
            $customCss = isset($company_settings['custom_css']) ? $company_settings['custom_css'] : null;
            $customJs = isset($company_settings['custom_js']) ? $company_settings['custom_js'] : null;

            $custom_field = company_setting('custom_field_enable', $business->created_by, $business->id);

            $excludedTypes = ['checkbox', 'radio', 'time', 'select'];
            $custom_fields = CustomField::where('created_by', $business->created_by)
                ->where('business_id', $business->id)
                ->whereNotIn('type', $excludedTypes)
                ->get();
            $options = [];
            foreach ($custom_fields as $customs) {
                if ($customs->type == 'checkbox' || $customs->type == 'radio' || $customs->type == 'select') {
                    $options[$customs->id] = json_decode($customs->option, true) ?? [];
                } else {
                    $options[$customs->id] = []; // Initialize empty array for non-checkbox fields
                }
            }

            $workingDays = BusinessHours::where('created_by', $business->created_by)
                ->where('business_id', $business->id)
                ->get();

            // Get currency symbol from business settings
            $currency_symbol = company_setting('currency_symbol', $business->created_by, $business->id) ?? company_setting('currency', $business->created_by, $business->id) ?? '$';

            $pixelScript = [];
            // Single-clinic app: TrackingPixel add-on removed.
            if (!empty($appointment)) {
                $appointments = Appointment::find($appointment);
                if (!empty($appointments)) {
                    $number = Appointment::appointmentNumberFormat($appointment, $business->created_by, $business->id);
                }
                if ($appointment != 'failed' && $appointments != null && (strpos($number, isset($company_settings['appointment_prefix']) ? $company_settings['appointment_prefix'] : '#APP') === 0)) {
                    $appointment_number = $number;
                } elseif ($appointment == 'failed') {
                    $appointment_number = 'failed';
                } else {
                    $appointment_number = '';
                }
            } else {
                $appointment_number = '';
            }
            if ($business->form_type == 'form-layout') {
                return view('form_layout.' . $business->layouts . '.index', compact('slug', 'business', 'categories', 'services', 'locations', 'staffs', 'customCss', 'customJs', 'combinedArray', 'files', 'custom_field', 'custom_fields', 'options', 'businesholiday', 'appointment_number', 'pixelScript', 'company_settings', 'bookingModes', 'currency_symbol'));
            } else {
                // Single-clinic app: module-based web layouts removed; fall back to the core form layout.
                return view('form_layout.' . $business->layouts . '.index', compact('slug', 'business', 'categories', 'services', 'locations', 'staffs', 'customCss', 'customJs', 'combinedArray', 'files', 'custom_field', 'custom_fields', 'options', 'businesholiday', 'appointment_number', 'pixelScript', 'company_settings', 'bookingModes', 'currency_symbol'));
            }
        }

        // return view('embeded_appointment.index',compact('slug','business','services','locations','staffs','customCss','customJs','combinedArray','files','custom_field','custom_fields'));
    }



    public function appointmentFormSubmit(\App\Http\Requests\StoreBookingRequest $request)
    {
        $business = Business::find($request->business_id);
        $service  = Service::find($request->service);

        if (empty($business) || empty($service)) {
            $redirecturl = route('appointments.form', ['slug' => $business->slug ?? '', 'appointment' => 'failed']);

            return response()->json(['status' => 'failed', 'message' => __('Invalid booking request.'), 'url' => $redirecturl]);
        }

        $result = app(\App\Services\BookingService::class)->book($business, $service, $request->all());

        return response()->json($result);
    }

    public function appointmentStatusChange($id)
    {
        $appointment = Appointment::find($id);

        $CustomStatus = CustomStatus::where('created_by', creatorId())->where('business_id', getActiveBusiness())->pluck('title', 'id')->prepend('Pending', '0');

        return view('appointment.change-status', compact('appointment', 'CustomStatus'));
    }

    public function appointmentStatusUpdate(Request $request)
    {

        $appointment = Appointment::find($request->appointment_id);
        $appointment->appointment_status = $request->status;
        $appointment->save();

        $appointment_number = Appointment::appointmentNumberFormat($appointment->id, $appointment->created_by, $appointment->business_id);
        //Email notification
        $company_settings = getCompanyAllSetting();


        event(new AppointmentStatus($appointment, $request));


        if ((!empty($company_settings['Appointment Status Change']) && $company_settings['Appointment Status Change'] == true)) {
            $uArr = [
                'company_name' => $appointment->business->name ?? '',
                'service' => $appointment->ServiceData ? $appointment->ServiceData->name : '-',
                'appointment_date' => $appointment->date,
                'appointment_time' => $appointment->time,
                'appointment_number' => $appointment_number,
            ];

            $resp = EmailTemplate::sendEmailTemplate('Appointment Status Change', [$appointment->CustomerData ? $appointment->CustomerData->customer->email : $appointment->email], $uArr);

            // return redirect()->route('appointment.index')->with('success', __('Appointment successfully created.'). ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
            return redirect()->back()->with('success', __('Appointment status change successfully.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
        }


        return redirect()->back()->with('success', __('Appointment status change successfully.'));
    }

    public function appointmentDone(Request $request, $slug, $id)
    {
        $appointment = Appointment::find($id);
        if (!empty($appointment)) {
            $company_settings = getCompanyAllSetting($appointment->created_by, $appointment->business_id);
            $customCss = isset($company_settings['custom_css']) ? $company_settings['custom_css'] : null;
            $customJs = isset($company_settings['custom_js']) ? $company_settings['custom_js'] : null;

            $appointment_number = Appointment::appointmentNumberFormat($appointment->id, $appointment->created_by, $appointment->business_id);

            //Email notification
            if ((!empty($company_settings['Create Appointment']) && $company_settings['Create Appointment'] == true)) {
                $trackingUrl = route('find.appointment', ['businessSlug' => $appointment->business->slug]);
                $uArr = [
                    'company_name' => $appointment->business->name ?? '',
                    'service' => $appointment->ServiceData ? $appointment->ServiceData->name : '-',
                    'location' => $appointment->LocationData ? $appointment->LocationData->name : '-',
                    'staff' => $appointment->StaffData->user ? $appointment->StaffData->user->name : '-',
                    'appointment_date' => $appointment->date,
                    'appointment_time' => $appointment->time,
                    'appointment_number' => $appointment_number,
                    `'tracking_url' => $trackingUrl,`
                ];
                $resp = EmailTemplate::sendEmailTemplate('Create Appointment', [$appointment->CustomerData ? $appointment->CustomerData->customer->email : $appointment->email], $uArr, $appointment->created_by, $appointment->business_id);
                return view('embeded_appointment.appointment', compact('appointment_number', 'slug', 'customCss', 'customJs'))->with('success', __('Appointment successfully created.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
            }
            return view('embeded_appointment.appointment', compact('appointment_number', 'slug', 'customCss', 'customJs'));
        }
    }

    public function appointmentCalendar(Request $request)
    {
        $appointments = [];
        $type = [];
        $type = 'appointment';

        if ($request->get('calendar_type') == 'google_calendar') {
            $appointments = CalendarUtility::getCalendarData($type);
            $weekStartDay = company_setting('week_start_day', auth()->user()->id, getActiveBusiness());
            $weekStartDay = isset($weekStartDay) ? $weekStartDay : '0';
            if (isset($appointments['error'])) {
                return redirect()->back()->with('error', $appointments['error']);
            }
        } elseif ($request->get('calendar_type') == 'outlook_calendar') {
            $appointments = OutlookUtility::getOutlookCalendarData($type);
            $weekStartDay = company_setting('week_start_day', auth()->user()->id, getActiveBusiness());
            $weekStartDay = isset($weekStartDay) ? $weekStartDay : '0';
            if (isset($appointments['error'])) {
                return redirect()->back()->with('error', $appointments['error']);
            }
        } else {
            if ($type == "appointment" || $type == null || $type == []) {
                $appointments = Appointment::where('business_id', getActiveBusiness())->where('created_by', creatorId())->get();

                $appointments = $appointments->map(function ($appointment) {
                    $carbonDate = Carbon::parse($appointment['date']);
                    $appointment['title'] = $appointment['time'];
                    $appointment['start'] = $carbonDate->format('Y-m-d');
                    $appointment['end'] = $carbonDate->format('Y-m-d');
                    $appointment['time'] = $appointment['time'];
                    $appointment['url'] = route('appointment.details', $appointment->id);
                    return $appointment;
                });

                $weekStartDay = company_setting('week_start_day', auth()->user()->id, getActiveBusiness());
                $weekStartDay = isset($weekStartDay) ? $weekStartDay : '0';
            } else {
                unset($type['appointment']);
            }
        }
        return view('appointment.calendar', compact('appointments', 'weekStartDay'));
    }

    public function appointmentDetails($id)
    {
        $appointments = Appointment::find($id);
        return view('appointment.appointment_details', compact('appointments'));
    }

    public function appointmentAttachmentDelete($id)
    {
        $appointment = Appointment::find($id);

        if (!empty($appointment->attachment)) {
            delete_file($appointment->$appointment);
            $appointment->attachment = null;
            $appointment->save();
        }
        return redirect()->back()->with('error', __('Attachment successfully delete.'));
    }

    public function appointmentRtlSetting(Request $request)
    {
        $status = $request->status;
        Cookie::queue('THEME_RTL', $status, 120);

        return response()->json(['success' => 'Status change successfully.']);
    }

    public function checkUser(Request $request)
    {
        $email = $request->email;
        $user = User::where('email', $email)->where('type', 'customer')->first();
        if (!empty($request->password) && !empty($user)) {
            $check_password = Hash::check($request->password, $user->password);
            if ($check_password) {
                $customer = Customer::where('user_id', $user->id)->first();
            } else {
                return response()->json(['status' => 'error', 'message' => 'Enter correct password.']);
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'Please enter valid email.']);
        }
    }

    /**
     * Get services by category - AJAX endpoint for booking form
     * Filters services based on selected category
     */
    public function getServicesByCategory(Request $request)
    {
        if (!empty($request->category_id) && !empty($request->business_slug)) {
            $business = Business::where('slug', $request->business_slug)->first();

            if (!$business) {
                return response()->json(['error' => __('Business not found!')], 404);
            }

            $services = Service::where('business_id', $business->id)
                ->where('category_id', $request->category_id)
                ->orderBy('name', 'asc')
                ->get(['id', 'name', 'price', 'duration']);

            return response()->json([
                'success' => true,
                'services' => $services
            ]);
        } else {
            return response()->json([
                'error' => __('Category or business not specified!')
            ], 400);
        }

    }

    /**
     * Show reports popup for an appointment
     */
    public function showReports($id)
    {
        $appointment = Appointment::with('reports')->find($id);

        if (!$appointment) {
            return response()->json(['error' => __('Appointment not found!')], 404);
        }

        return view('appointment.reports', compact('appointment'));
    }

    /**
     * Upload a report for an appointment
     */
    public function uploadReport(Request $request, $id)
    {
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['error' => __('Appointment not found!')], 404);
        }

        $validator = \Validator::make($request->all(), [
            'report_file' => 'required|file|max:20480|mimes:pdf,jpg,jpeg,png,gif,doc,docx,xls,xlsx'
        ]);

        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()->first()], 422);
        }

        if ($request->hasFile('report_file')) {
            $file = $request->file('report_file');
            $originalName = $file->getClientOriginalName();
            $extension = $file->getClientOriginalExtension();
            $fileSize = $file->getSize();
            $mimeType = $file->getMimeType();

            // Create storage path
            $storagePath = 'appointment_reports/' . $id;
            $fileName = time() . '_' . preg_replace('/[^A-Za-z0-9\-\_\.]/', '_', $originalName);

            // Store the file
            $path = $file->storeAs($storagePath, $fileName, 'public');

            if ($path) {
                // Create report record
                $report = \App\Models\AppointmentReport::create([
                    'appointment_id' => $id,
                    'file_name' => $originalName,
                    'file_path' => $path,
                    'file_size' => $fileSize,
                    'file_type' => $mimeType,
                    'created_by' => Auth::id(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => __('Report uploaded successfully!'),
                    'report' => [
                        'id' => $report->id,
                        'file_name' => $report->file_name,
                        'file_size' => $report->formatted_file_size,
                        'created_at' => $report->created_at->format('d M Y, h:i A'),
                    ]
                ]);
            }
        }

        return response()->json(['error' => __('Failed to upload report!')], 500);
    }

    /**
     * Delete a report
     */
    public function deleteReport($reportId)
    {
        $report = \App\Models\AppointmentReport::find($reportId);

        if (!$report) {
            return response()->json(['error' => __('Report not found!')], 404);
        }

        // Delete the file from storage
        if (\Storage::disk('public')->exists($report->file_path)) {
            \Storage::disk('public')->delete($report->file_path);
        }

        // Delete the database record
        $report->delete();

        return response()->json([
            'success' => true,
            'message' => __('Report deleted successfully!')
        ]);
    }

    /**
     * Download a report
     */
    public function downloadReport($reportId)
    {
        $report = \App\Models\AppointmentReport::find($reportId);

        if (!$report) {
            return redirect()->back()->with('error', __('Report not found!'));
        }

        $filePath = storage_path('app/public/' . $report->file_path);

        if (file_exists($filePath)) {
            return response()->download($filePath, $report->file_name);
        }

        return redirect()->back()->with('error', __('File not found!'));
    }

    /**
     * Print Token - Generate and print thermal receipt for appointment
     */
    public function printToken($id)
    {
        $appointment = Appointment::with(['ServiceData', 'StaffData', 'LocationData', 'CustomerData', 'ReferrerData'])->find($id);
        
        if (!$appointment) {
            return redirect()->back()->with('error', __('Appointment not found!'));
        }

        // Get business info
        $business = Business::find($appointment->business_id);
        
        // Assign token number if not already assigned
        if (empty($appointment->token_number)) {
            // Use database transaction with lock to prevent duplicate tokens
            DB::transaction(function () use ($appointment) {
                // Get today's date
                $today = Carbon::today()->toDateString();
                
                // Get the max token number for today (using date field or created_at)
                $maxToken = Appointment::where('business_id', $appointment->business_id)
                    ->whereDate('date', $today)
                    ->whereNotNull('token_number')
                    ->lockForUpdate()
                    ->max('token_number');
                
                // Assign next token number
                $appointment->token_number = ($maxToken ?? 0) + 1;
                $appointment->save();
            });
            
            // Refresh to get the updated token
            $appointment->refresh();
        }

        return view('appointment.print_token', compact('appointment', 'business'));
    }

}
