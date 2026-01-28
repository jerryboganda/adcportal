<?php

namespace App\Http\Controllers;

use App\DataTables\AppointmentDataTable;
use App\Events\AdditionalServicePayment;
use App\Events\CreateAppoinment;
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
use Exception;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Workdo\CollaborativeServices\Entities\CollaborativeServiceUtility;
use Workdo\CompoundService\Entities\CompoundUtility;
use Workdo\ShoppingCart\Entities\ShoppingCart;
use Workdo\TrackingPixel\Entities\PixelFields;
use App\Events\AppointmentStatus;
use Workdo\FlexibleHours\Entities\FlexibleHour;
use App\Events\AppointmentPaymentData;
use App\Events\DeleteAppointment;
use Workdo\RepeatAppointments\Entities\Utility;
use Workdo\SequentialAppointment\Entities\SequentialUtility;
use Workdo\SequentialAppointment\Events\CreateSequentialAppointment;
use Workdo\FlexibleDays\Entities\FlexibleDayUtility;
use Workdo\FlexibleDays\Entities\FlexibleStaffHours;
use Cookie;
use Illuminate\Support\Facades\DB;
use Workdo\GoogleCalendar\Entities\CalendarUtility;
use Workdo\BulkAppointments\Entities\BulkAppointment;
use Workdo\FlexibleDuration\Entities\FlexibleDurationUtility;
use Workdo\OutlookCalendar\Entities\OutlookUtility;
use Workdo\ServiceSlotScheduler\Entities\ServiceScheduleDay;
use Workdo\ServiceSlotScheduler\Entities\ServiceScheduleUtility;
use Workdo\TeamBooking\Entities\TeamBooking;
use App\Facades\ModuleFacade as Module;
use Workdo\WaitingList\Entities\WaitingListUtility;

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


            return view('appointment.create', compact('location', 'service', 'staff', 'customer', 'busineshours', 'busineshours', 'businesholiday', 'combinedArray'));
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

            // if(module_is_active('CompoundService', getActiveBusiness())){
            //     $timeSlots = CompoundUtility::compoundTimeSlote($appointment->service_id, $appointment->date);
            // } else {
            $timeSlots = timeSlot($appointment->service_id, $appointment->date);
            // }


            return view('appointment.edit', compact('location', 'service', 'staff', 'customer', 'busineshours', 'busineshours', 'appointment', 'timeSlots', 'combinedArray', 'businesholiday'));
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
        $service = Service::find($request->service);

        if (empty($service)) {
            return response()->json(['error' => __('Service not found!')], 404);
        }

        $service_data = null;
        $flexible_data = null;

        if (module_is_active('CompoundService', $service->created_by) && $service->service_type == 'compound') {
            $compoundTimeSlot = CompoundUtility::compoundTimeSlote($request->service, $request->date);

            return response()->json(['timeSlots' => $compoundTimeSlot, 'result' => 'success']);
        }
        if (module_is_active('WaitingList', $service->created_by)) {
            $waitingListTimeSlot = WaitingListUtility::waitingListTimeSlote($request->service, $request->date);
            return response()->json(['timeSlots' => $waitingListTimeSlot, 'result' => 'success']);
        }

        if (module_is_active('CollaborativeServices', $service->created_by) && $service->service_type == 'collaborative') {
            $slots = CollaborativeServiceUtility::collaborativeTimeSlote($request->service, $request->date);

            return response()->json(['timeSlots' => $slots, 'result' => 'success']);
        }

        if (!empty($request->service) && !empty($request->date)) {
            if (module_is_active('FlexibleHours', $service->created_by)) {
                $flexible_data = FlexibleHour::where('staff_id', $request->staff)
                    ->where('service_id', $request->service)
                    ->get();
            }
            if (module_is_active('FlexibleDays', $service->created_by)) {
                $flexible_days = FlexibleStaffHours::where('created_by', $service->created_by)
                    ->where('business_id', $service->business_id)
                    ->where('staff_id', $request->staff)->get();
                if ($flexible_days->isNotEmpty()) {
                    $flexibleDayTimeSlot = FlexibleDayUtility::flexibleDayTimeSlot($request->service, $request->date, $request->staff, $flexible_data);
                    return response()->json(['timeSlots' => $flexibleDayTimeSlot, 'result' => 'success']);
                }
            }
            if (module_is_active('ServiceSlotScheduler', $service->created_by) && !module_is_active('FlexibleDays', $service->created_by)) {
                $ServiceDayTimeSlot = ServiceScheduleUtility::ServiceDayTimeSlot($request->service, $request->date, $request->staff, $service_data);
                return response()->json(['timeSlots' => $ServiceDayTimeSlot, 'result' => 'success']);
            }
            if (module_is_active('FlexibleDuration', $service->created_by)) {
                $ServiceDurationTimeSlot = FlexibleDurationUtility::ServiceDurationTimeSlot($request->service, $request->date, $request->staff, $request->serviceduration);
                return response()->json(['timeSlots' => $ServiceDurationTimeSlot, 'result' => 'success']);
            }
            if (module_is_active('TeamBooking', $service->created_by) && !empty($request->team)) {
                $bookingTimeSlot = TeamBooking::timeSlot($request->service, $request->date, $flexible_data, $request->team);

                return response()->json(['timeSlots' => $bookingTimeSlot, 'result' => 'success']);
            }
            return response()->json(['timeSlots' => timeSlot($request->service, $request->date, $flexible_data, $service_data), 'result' => 'success']);
        } else {
            return response()->json(['result' => 'error']);
        }
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

            if (module_is_active('FlexibleDays', $business->created_by)) {
                $busineshours = FlexibleStaffHours::where('created_by', $business->created_by)
                    ->where('business_id', $business->id)
                    ->where('day_off', 'on')
                    ->select('day_name')
                    ->get()
                    ->pluck('day_name')
                    ->map(function ($day) {
                        return date('w', strtotime($day));
                    })
                    ->toArray();
            } elseif (module_is_active('ServiceSlotScheduler', $business->created_by) && !module_is_active('FlexibleDays', $business->created_by)) {
                $busineshours = ServiceScheduleDay::where('created_by', $business->created_by)
                    ->where('business_id', $business->id)
                    ->where('day_off', 'on')
                    ->select('day_name')
                    ->get()
                    ->pluck('day_name')
                    ->map(function ($day) {
                        return date('w', strtotime($day));
                    })
                    ->toArray();
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
            if (module_is_active('TrackingPixel', $business->created_by)) {
                $pixels = PixelFields::where('created_by', $business->created_by)->where('business_id', $business->id)->get();
                foreach ($pixels as $pixel) {
                    $pixelScript[] = pixelSourceCode($pixel['platform'], $pixel['pixel_id']);
                }
            }
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

                $module = $business->layouts;


                if (module_is_active($business->layouts, $business->created_by)) {
                    $themeSetting = ThemeSetting::where('theme', $module)->where('business_id', $business->id)->pluck('value', 'key');
                    $testimonials = Testimonial::where('business_id', $business->id)->where('theme', $module)->get();
                    $blogs = Blog::where('business_id', $business->id)->where('theme', $module)->get();
                    $modules = Module::find($business->layouts);

                    return view($modules->package_name . '::form_layout.index', compact('slug', 'business', 'categories', 'services', 'locations', 'staffs', 'customCss', 'customJs', 'combinedArray', 'files', 'custom_field', 'custom_fields', 'options', 'module', 'themeSetting', 'workingDays', 'testimonials', 'blogs', 'businesholiday', 'appointment_number', 'pixelScript', 'company_settings', 'bookingModes', 'currency_symbol'));
                } else {
                    return view('web_layouts.module_not_found', compact('module'));
                    //    return redirect()->back()->with('error', __('please activate this module '.$business->layouts));
                }
            }
        }

        // return view('embeded_appointment.index',compact('slug','business','services','locations','staffs','customCss','customJs','combinedArray','files','custom_field','custom_fields'));
    }



    public function appointmentFormSubmit(Request $request)
    {

        $business = Business::find($request->business_id);
        $service = Service::find($request->service);

        if (module_is_active('CompoundService', $business->created_by) && $service->service_type == 'compound') {
            $data = CompoundUtility::storeCompoundService($request->all());
            $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $data->data]);
            return response()->json(['status' => $data->status, 'message' => $data->message, 'url' => $redirecturl]);
        }
        if (module_is_active('CollaborativeServices', $business->created_by) && $service->service_type == 'collaborative') {
            $data = CollaborativeServiceUtility::storeCollaborativeService($request->all());
            $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $data->data]);
            return response()->json(['status' => $data->status, 'message' => $data->message, 'url' => $redirecturl]);
        }
        if (module_is_active('ShoppingCart', $business->created_by) && $request->has('selectedCartIds')) {
            $data = ShoppingCart::Appointments($request->all());
            $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $data->data]);
            return response()->json(['status' => $data->status, 'message' => $data->message, 'url' => $redirecturl]);
        }

        if (module_is_active('TeamBooking', $business->created_by) && $request->person) {
            $data = TeamBooking::teamBooking($request->all());
            $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $data->data]);
            return response()->json(['status' => $data->status, 'message' => $data->message, 'url' => $redirecturl]);
        }

        if (module_is_active('BulkAppointments', $business->created_by) && $request->quantity) {
            $data = BulkAppointment::bulkAppointment($request->all());
            $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $data->data]);
            return response()->json(['status' => $data->status, 'message' => $data->message, 'url' => $redirecturl]);
        }
        if (!empty($request->service) && !empty($request->staff) && !empty($request->appointment_date) && !empty($request->email) && !empty($request->business_id) && !empty($request->payment)) {
            try {
                // Log incoming request data for debugging
                Log::info('Appointment submission received', [
                    'service_id' => $request->service,
                    'staff_id' => $request->staff,
                    'location_id' => $request->location,
                    'business_id' => $business->id,
                    'date' => $request->appointment_date,
                    'type' => $request->type,
                ]);

                $service = Service::find($request->service);

                if (module_is_active('RepeatAppointments', $business->created_by)) {
                    if (!empty($request->date) && !empty($request->booked_slot)) {
                        $event = Utility::RepeatAppointmentStore($request->all());
                        $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $event->data]);
                        return response()->json(['status' => $event->status, 'message' => $event->message, 'url' => $redirecturl]);
                    }
                }
                if (module_is_active('SequentialAppointment', $business->created_by) && $request->sequential_services) {
                    $sequential_utility = SequentialUtility::storeSequentialAppointment($request->all());
                    $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $sequential_utility->data]);
                    return response()->json(['status' => $sequential_utility->status, 'message' => $sequential_utility->message, 'url' => $redirecturl]);
                }

                // Validate that staff exists (log warning but allow null staff)
                $staff = null;
                if (!empty($request->staff)) {
                    $staff = \App\Models\Staff::find($request->staff);
                    if (!$staff) {
                        Log::warning('Staff not found', ['staff_id' => $request->staff, 'business_id' => $business->id]);
                        // Don't fail - staff might not exist but appointment can still be created
                    }
                }

                // Validate that location exists (log warning but allow null location)
                $location = null;
                if (!empty($request->location)) {
                    $location = \App\Models\Location::find($request->location);
                    if (!$location) {
                        Log::warning('Location not found', ['location_id' => $request->location, 'business_id' => $business->id]);
                        // Don't fail - location might not exist but appointment can still be created
                    }
                }

                $url = null;
                if ($request->hasFile('attachment')) {
                    $filenameWithExt = $request->file('attachment')->getClientOriginalName();
                    $filename = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                    $extension = $request->file('attachment')->getClientOriginalExtension();
                    $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                    $uplaod = upload_file($request, 'attachment', $fileNameToStore, 'Appointment');
                    if ($uplaod['flag'] == 1) {
                        $url = $uplaod['url'];
                    } else {
                        $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => 'failed']);
                        return response()->json(['status' => 'failed', 'message' => $uplaod['msg'] ?? 'The Payment has been failed.', 'url' => $redirecturl]);
                        // return response()->json(['status' => 'error', 'message' => 'The Payment has been added successfully.' ,'error' => $uplaod['msg']]);
                    }
                }

                // Handle guest user - guests don't need customer records
                // Their info will be stored directly in the appointment
                $customer = null;
                $customer_id = null;

                if ($request->type == 'guest-user') {
                    // Create/Update contact entry for marketing purposes
                    $existingContact = \App\Models\ContactUs::where('email', $request->email)
                        ->where('business_id', $business->id)
                        ->first();

                    if (!$existingContact) {
                        // Create new contact
                        \App\Models\ContactUs::create([
                            'name' => $request->name,
                            'email' => $request->email,
                            'contact' => $request->contact,
                            'subject' => 'Appointment Booking - Guest',
                            'description' => 'Contact created from appointment booking',
                            'theme' => 'default',
                            'business_id' => $business->id,
                        ]);

                        Log::info('Contact created from guest booking', [
                            'name' => $request->name,
                            'email' => $request->email,
                            'business_id' => $business->id
                        ]);
                    }
                }

                if ($request->type == 'new-user') {
                    $roles = Role::where('name', 'customer')->where('created_by', $business->created_by)->first();
                    if ($roles) {
                        $user = User::create(
                            [
                                'name' => !empty($request->name) ? $request->name : null,
                                'email' => !empty($request->email) ? $request->email : null,
                                'mobile_no' => !empty($request->contact) ? $request->contact : null,
                                'email_verified_at' => date('Y-m-d h:i:s'),
                                'password' => !empty($request->password) ? Hash::make($request->password) : null,
                                'avatar' => 'uploads/users-avatar/avatar.png',
                                'type' => 'customer',
                                'lang' => 'en',
                                'business_id' => $business->id,
                                'created_by' => $business->created_by,
                            ]
                        );
                        $user->addRole($roles);

                        $customer = new Customer();
                        $customer->name = $request->name;
                        $customer->user_id = $user->id;
                        $customer->gender = !empty($request->gender) ? $request->gender : '';
                        $customer->dob = !empty($request->dob) ? $request->dob : '';
                        $customer->description = !empty($request->description) ? $request->description : '';
                        $customer->business_id = $user->business_id;
                        $customer->created_by = $user->created_by;
                        $customer->save();
                    }
                }

                if ($request->type == 'existing-user') {
                    $email = $request->email;
                    $user = User::where('email', $email)->where('type', 'customer')->first();
                    if (!empty($request->password) && !empty($user)) {
                        $check_password = Hash::check($request->password, $user->password);
                        if ($check_password) {
                            $customer = Customer::where('user_id', $user->id)->first();
                        } else {
                            return response()->json(['status' => 'error', 'message' => 'The Payment has been added successfully.', 'error' => 'Enter correct password']);
                        }
                    } else {
                        return response()->json(['status' => 'error', 'message' => 'The Payment has been added successfully.', 'error' => 'Please enter valid email']);
                    }
                }

                $default_status = company_setting('default_status', $business->created_by, getActiveBusiness());

                $Appointment = new Appointment();
                if ($request->type == 'new-user' || $request->type == 'existing-user') {
                    $Appointment->customer_id = !empty($customer) ? $customer->user_id : null;
                } elseif ($request->type == 'guest-user') {
                    // For guests, use the customer ID directly (not user_id since guests have no user account)
                    $Appointment->customer_id = !empty($customer_id) ? $customer_id : null;
                } else {
                    $Appointment->customer_id = !empty($request->customer) ? $request->customer : null;
                }
                $Appointment->location_id = $request->location;
                $Appointment->service_id = $request->service;
                $Appointment->staff_id = $request->staff;

                if ($request->type == 'guest-user') {
                    $Appointment->name = $request->name;
                    $Appointment->email = $request->email;
                    $Appointment->contact = $request->contact;
                }

                $Appointment->date = !empty($request->appointment_date) ? $request->appointment_date : '';
                $Appointment->time = !empty($request->duration) ? $request->duration : '';
                $Appointment->notes = !empty($request->notes) ? $request->notes : '';
                $Appointment->payment_type = !empty($request->payment) ? $request->payment : 'Manually';
                $Appointment->appointment_status = !empty($default_status) ? $default_status : 'Pending';
                $Appointment->attachment = !empty($url) ? $url : null;

                $customFieldValues = [];
                $custom_field = company_setting('custom_field_enable', $business->created_by, $business->id);
                // Process the values from the request
                if (!empty($custom_field) && $custom_field == 'on') {
                    foreach ($request->values as $type => $fields) {
                        foreach ($fields as $label => $value) {
                            if (is_array($value)) {
                                if ($type === 'checkbox') {
                                    $customFieldValues[$label] = implode(',', $value);
                                } else {
                                    $customFieldValues[$label] = json_encode($value);
                                }
                            } else {
                                $customFieldValues[$label] = $value;
                            }
                        }
                    }
                }
                $Appointment->custom_field = !empty($customFieldValues) ? json_encode($customFieldValues) : null;

                $Appointment->business_id = $business->id;
                $Appointment->created_by = $business->created_by;
                $Appointment->save();
            } catch (\Exception $e) {
                Log::error('Appointment Creation Error: ' . $e->getMessage(), ['exception' => $e]);
                return response()->json(['status' => 'error', 'message' => 'Failed to create appointment.', 'error' => 'There was an error processing your booking. Please try again.']);
            }

            if (module_is_active('FlexibleHours', $business->created_by) && isset($request->flexible_id)) {
                $flexible_hour = FlexibleHour::find($request->flexible_id);
            }

            $final_amount = $service->price;

            $promo_code_id = 0;
            $after_promo_price = 0;
            if (module_is_active('PromoCodes') && $request->promo_code_id != null) {
                $promo_code_id = $request->promo_code_id;
            }
            if (module_is_active('PromoCodes') && $request->after_promo_price != null) {
                $after_promo_price = $service->price - $request->after_promo_price;
                $final_amount = $service->price - $after_promo_price;
            }

            if (module_is_active('PromoCodes') && module_is_active('ServiceTax')) {
                $after_promo_price = $service->price - $request->service_price;
                $final_amount = $request->final_amount;
            }

            if (module_is_active('FlexibleHours', $business->created_by) && isset($request->flexible_id)) {
                $flexible_hour = FlexibleHour::find($request->flexible_id);
            }

            if (module_is_active('Discount', $business->created_by) && isset($request->discount_amount)) {
                $final_amount = $service->price - $request->discount_amount;
            }

            $payment = AppointmentPayment::create([
                'appointment_id' => $Appointment->id,
                'payment_type' => $Appointment->payment_type,
                'amount' => module_is_active('FlexibleHours', $business->id) && !empty($flexible_hour) ? $flexible_hour->price : $service->price,
                'payment_date' => now(),
                'business_id' => $business->id,
                'created_by' => $business->created_by,
            ]);

            event(new AppointmentPaymentData($request->all(), $payment, $service));

            if (module_is_active('AdditionalServices', $business->created_by) && isset($request->additional_service) && isset($request->additional_service_price)) {
                event(new AdditionalServicePayment($request->all(), $payment, $service));
            }


            $appointment_number = Appointment::appointmentNumberFormat($Appointment->id, $business->created_by, $business->id);

            $company_settings = getCompanyAllSetting($Appointment->created_by, $Appointment->business_id);
            $customCss = isset($company_settings['custom_css']) ? $company_settings['custom_css'] : null;
            $customJs = isset($company_settings['custom_js']) ? $company_settings['custom_js'] : null;

            event(new CreateAppoinment($Appointment, $request));

            //Email notification
            if ((!empty($company_settings['Create Appointment']) && $company_settings['Create Appointment'] == true)) {
                $trackingUrl = route('find.appointment', ['businessSlug' => $business->slug]);
                $uArr = [
                    'company_name' => $business->name ?? '',
                    'service' => $Appointment->ServiceData ? $Appointment->ServiceData->name : '-',
                    'location' => $Appointment->LocationData ? $Appointment->LocationData->name : '-',
                    'staff' => $Appointment->StaffData->user ? $Appointment->StaffData->user->name : '-',
                    'appointment_date' => $Appointment->date,
                    'appointment_time' => $Appointment->time,
                    'appointment_number' => $appointment_number,
                    'tracking_url' => $trackingUrl,
                ];
                $resp = EmailTemplate::sendEmailTemplate('Create Appointment', [$Appointment->CustomerData ? $Appointment->CustomerData->customer->email : $Appointment->email], $uArr, $Appointment->created_by, $business->id);

                $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $Appointment->id]);
                return response()->json(['status' => 'success', 'message' => 'The Payment has been added successfully.', 'url' => $redirecturl, 'appointment_id' => $Appointment->id, 'appointment_number' => $appointment_number]);
            }

            $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => $Appointment->id]);
            return response()->json(['status' => 'success', 'message' => 'The Payment has been added successfully.', 'url' => $redirecturl, 'appointment_id' => $Appointment->id, 'appointment_number' => $appointment_number]);
        } else {
            $redirecturl = route("appointments.form", ["slug" => $business->slug, "appointment" => 'failed']);
            return response()->json(['status' => 'failed', 'message' => 'The Payment has been added successfully.', 'url' => $redirecturl]);
        }
    }

    public function appointmentStatusChange($id)
    {
        $appointment = Appointment::find($id);

        $CustomStatus = CustomStatus::where('created_by', creatorId())->where('business_id', getActiveBusiness())->pluck('title', 'id')->prepend('Pending', '0');

        if (module_is_active('WaitingList') && $appointment->appointment_status == 'Waiting List') {
            $CustomStatus = CustomStatus::where('created_by', creatorId())->where('business_id', getActiveBusiness())->pluck('title', 'id')->prepend('Waiting List', '0');
        }

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

}
