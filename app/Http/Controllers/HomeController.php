<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\User;
use App\Models\Business;
use App\Models\Location;
use App\Models\Staff;
use App\Models\BusinessHours;
use App\Models\BusinessHoliday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\File;
use App\Models\CustomField;
use Carbon\Carbon;
use App\Models\AppointmentPayment;
use App\Models\CustomStatus;
use Illuminate\Routing\Controller;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */

    public function __construct()
    {
        if (!file_exists(storage_path() . "/installed")) {
            header('location:install');
            die;
        }
    }

    public function index($slug = null, $appointment = null)
    {
        if (Auth::check()) {
            return redirect('dashboard');
        } else {
            if (!file_exists(storage_path() . "/installed")) {
                header('location:install');
                die;
            }

            // Single-clinic app: the home page IS the clinic's public booking page.
            $business = Business::first();
            if (!$business) {
                return redirect('login');
            }

            $services = Service::where('business_id', $business->id)->get();
            $locations = Location::where('business_id', $business->id)->get();
            $staffs = Staff::where('business_id', $business->id)->get();
            $categories = \App\Models\Category::where('business_id', $business->id)
                ->where('created_by', $business->created_by)
                ->orderBy('id', 'asc')
                ->get();

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

            $businesholiday = BusinessHoliday::where('created_by', $business->created_by)
                ->where('business_id', $business->id)
                ->pluck('date')
                ->map(function ($date) {
                    return Carbon::parse($date)->format('d-m-Y');
                })
                ->toArray();
            // $combinedArray = array_merge($busineshours, $businesholiday);
            $combinedArray = $busineshours;

            $company_settings = getCompanyAllSetting($business->created_by, $business->id);
            $customCss = isset($company_settings['custom_css']) ? $company_settings['custom_css'] : null;
            $customJs = isset($company_settings['custom_js']) ? $company_settings['custom_js'] : null;
            $bookingModes = isset($company_settings['booking_mode']) ? explode(',', $company_settings['booking_mode']) : [];
            $files = File::where('business_id', $business->id)->where('created_by', $business->created_by)->first();

            $custom_field = company_setting('custom_field_enable', $business->created_by, $business->id);

            $custom_fields = CustomField::where('created_by', $business->created_by)->where('business_id', $business->id)->get();

            $pixelScript = [];

            $number = Appointment::appointmentNumberFormat($appointment, $business->id);
            if ($appointment != 'failed' && $appointment != null && (strpos($number, '#APP') === 0)) {
                $appointment_number = $number;
            } elseif ($appointment == 'failed') {
                $appointment_number = 'failed';
            } else {
                $appointment_number = '';
            }

            return view('form_layout.' . $business->layouts . '.index', compact('slug', 'business', 'services', 'locations', 'staffs', 'categories', 'customCss', 'customJs', 'combinedArray', 'files', 'custom_field', 'custom_fields', 'businesholiday', 'pixelScript', 'appointment_number', 'company_settings', 'bookingModes'));
        }
    }

    public function AppointmentDashboard(Request $request)
    {
        if (Auth::check()) {
            $user = Auth::user();
            $business = Business::find(getActiveBusiness());
            $total_appointment = Appointment::where('business_id', getActiveBusiness())->where('created_by', creatorId())->count();
            $total_pending_appointment = Appointment::where('business_id', getActiveBusiness())->where('created_by', creatorId())->where('appointment_status', 'Pending')->count();
            $revenue = AppointmentPayment::where('business_id', getActiveBusiness())->where('created_by', creatorId())->sum('amount');

            if (isset($request->date)) {
                $chartData = $this->getDashboardChart(['duration' => $request->date]);
            } else {
                $chartData = $this->getDashboardChart(['duration' => 'week']);
            }

            $staffs = Staff::where('business_id', getActiveBusiness())->where('created_by', creatorId())->get();
            $staffsColorMapping = $this->getStaffColorMapping($staffs);

            // Assign color to each staff member
            $staffs = $staffs->map(function ($staff) use ($staffsColorMapping) {
                $staff->color = $staffsColorMapping[$staff->user_id] ?? '#CEEDC1'; // Default color
                return $staff;
            });

            $staff_id = $request->staff ? $request->staff : null;
            if (!empty($staff_id)) {
                $appointments = Appointment::where('staff_id', $staff_id)->where('business_id', getActiveBusiness())->where('created_by', creatorId())->get();
            } else {
                $appointments = Appointment::where('business_id', getActiveBusiness())->where('created_by', creatorId())->get();
            }
            $appointments = $appointments->map(function ($appointment) use ($staffsColorMapping) {
                $carbonDate = Carbon::parse($appointment['date']);
                $appointment['title'] = $appointment['time'];
                $appointment['start'] = $carbonDate->format('Y-m-d');
                $appointment['end'] = $carbonDate->format('Y-m-d');
                $appointment['time'] = $appointment['time'];
                $appointment['url'] = route('appointment.details', $appointment->id);
                $appointment['color'] = $staffsColorMapping[$appointment->staff_id] ?? '#CEEDC1';
                return $appointment;
            });

            $weekStartDay = company_setting('week_start_day', auth()->user()->id, getActiveBusiness());
            $weekStartDay = isset($weekStartDay) ? $weekStartDay : '0';

            $company_settings = getCompanyAllSetting();
            $today_appointments = Appointment::with(['user', 'StaffData.user'])
                ->where('date', today()->format('d-m-Y'))
                ->where('business_id', getActiveBusiness())
                ->where('created_by', creatorId())
                ->get();

            $compact = ['total_appointment', 'revenue', 'business', 'total_pending_appointment', 'chartData', 'staffs', 'weekStartDay', 'appointments', 'staff_id', 'company_settings', 'today_appointments'];
            return view('appointment-dashboard', compact($compact));
        } else {
            return redirect()->route('start');
        }
    }

    public function findAppointment(Request $request)
    {
        // Single-clinic app: no slug needed.
        $business = Business::first();

        if ($business) {
            return view('find_appointment', compact('business'));
        } else {
            abort(404);
        }
    }

    public function trackAppointment(Request $request)
    {
        $business = Business::first();
        if (!$business) {
            abort(404);
        }
        $company_settings = getCompanyAllSetting($business->created_by, $business->id);
        $appointmentId = Appointment::where('id', $request->appointment_number)->first();

        if (isset($appointmentId) && $appointmentId != NULL) {
            if (is_null($appointmentId->customer_id)) {
                $checkEmailQuery = Appointment::where('id', $request->appointment_number)
                    ->where('email', $request->email);

                if ($request->has('name')) {
                    $checkEmailQuery->where('name', $request->name);
                }

                $checkEmail = $checkEmailQuery->first();
            } else {
                $checkEmail = Customer::select('customers.*', 'users.name as user_name', 'users.email as user_email')
                    ->join('users', 'users.id', '=', 'customers.user_id')
                    ->join('appointments', 'appointments.customer_id', '=', 'customers.user_id')
                    ->where('users.email', $request->email)
                    ->where('appointments.id', $request->appointment_number)
                    ->first();
            }


            if (isset($checkEmail) && $checkEmail !== NULL) {
                $appointmentDetails = Appointment::with('reports')->find($request->appointment_number);
                $allTrackingStatus = CustomStatus::where('business_id', $business->id)->orderby('id', 'asc')->get();


                $iconArray = CustomStatus::icon();
                $pendingStatus = new CustomStatus();
                $pendingStatus->id = 0;
                $pendingStatus->title = 'Pending';
                $allTrackingStatus->prepend($pendingStatus);
                $currentTrackingStatus = Appointment::where('id', $request->appointment_number)->first();
                if ($appointmentDetails !== 'null') {
                    return view('appointment_tracking', compact('appointmentDetails', 'allTrackingStatus', 'currentTrackingStatus', 'iconArray', 'company_settings'));
                } else {
                    return redirect()->back()->with('error', __('Something Went Wrong!'));
                }
            } else {
                return redirect()->back()->with('error-alert', __('Please Enter Valid Email Address!'));
            }
        } else {
            return redirect()->back()->with('error-alert', __('Sorry, Appointment Not Found!'));
        }
    }

    private function getStaffColorMapping($staffs)
    {
        $colors = Staff::ColorCode();
        $staffsColorMapping = [];

        foreach ($staffs as $index => $staff) {
            $colorIndex = $index % count($colors); // Ensure we wrap around if we have more staff than colors
            $staffsColorMapping[$staff->user_id] = $colors[$colorIndex];
        }
        return $staffsColorMapping;
    }

    public function getDashboardChart($arrParam)
    {
        $arrDuration = [];
        $dates = [];

        if (isset($arrParam['duration']) && $arrParam['duration'] == 'week') {
            $previous_week = strtotime("-1 week +1 day");
            for ($i = 0; $i < 7; $i++) {
                $dateKey = date('Y-m-d', $previous_week);
                $arrDuration[$dateKey] = date('d-M', $previous_week);
                $dates[] = $dateKey;
                $previous_week = strtotime(date('Y-m-d', $previous_week) . " +1 day");
            }
        } elseif (isset($arrParam['duration']) && strpos($arrParam['duration'], 'to') !== false) {
            // Custom date range handling
            [$startDate, $endDate] = explode(' to ', $arrParam['duration']);
            $startDate = date_create($startDate);
            $endDate = date_create($endDate);
            while ($startDate <= $endDate) {
                $dateKey = $startDate->format('Y-m-d');
                $arrDuration[$dateKey] = $startDate->format('d-M');
                $dates[] = $dateKey;
                $startDate->modify('+1 day');
            }
        }

        $orders = Appointment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as total')
        )
            ->where('business_id', getActiveBusiness())
            ->whereIn(DB::raw('DATE(created_at)'), $dates)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();

        $revenue = AppointmentPayment::select(
            DB::raw('DATE(payment_date) as date'),
            DB::raw('count(*) as total'),
            DB::raw('SUM(amount) as total_amount')
        )
            ->where('business_id', getActiveBusiness())
            ->whereIn(DB::raw('DATE(payment_date)'), $dates)
            ->groupBy(DB::raw('DATE(payment_date)'))
            ->get();
        // Initialize an empty $arrTask array
        $arrTask = ['label' => [], 'data' => [], 'revenue' => []];

        $orderMap = [];
        $revenueMap = [];

        foreach ($orders as $order) {
            $orderMap[$order->date] = $order->total;
        }
        foreach ($revenue as $rev) {
            $revenueMap[$rev->date] = $rev->total_amount;
        }

        foreach ($dates as $date) {
            $label = $arrDuration[$date];
            $totalAppointments = isset($orderMap[$date]) ? $orderMap[$date] : 0;
            $totalRevenue = isset($revenueMap[$date]) ? $revenueMap[$date] : 0;

            $arrTask['label'][] = $label;
            $arrTask['data'][] = $totalAppointments;
            $arrTask['revenue'][] = $totalRevenue;
        }
        return $arrTask;
    }



    public function Dashboard(Request $request)
    {
        if (Auth::check()) {
            // Single-clinic app: super-admin dashboard removed; always show the clinic dashboard.
            $total_business = getBusiness()->count();
            $total_service = Service::where('business_id', getActiveBusiness())->where('created_by', creatorId())->count();
            $total_appointment = Appointment::where('business_id', getActiveBusiness())->where('created_by', creatorId())->count();
            $total_staff = User::where('type', 'staff')->where('business_id', getActiveBusiness())->where('created_by', creatorId())->count();
            $total_location = Location::where('business_id', getActiveBusiness())->where('created_by', creatorId())->count();
            $total_appointment_payment = AppointmentPayment::where('business_id', getActiveBusiness())->where('created_by', creatorId())->sum('amount');

            $latest_services = Service::where('business_id', getActiveBusiness())
                ->where('created_by', creatorId())
                ->latest()
                ->take(5)
                ->get();



            $latest_appointments = Appointment::where('business_id', getActiveBusiness())
                ->where('created_by', creatorId())
                ->latest()
                ->take(4)
                ->get();

            $business = Business::find(getActiveBusiness());
            $latest_businesses = Business::where('created_by', creatorId())->latest()->take(5)->get();

            $getCurrencyFormatSymbol = get_currency_format_and_symbol($business->created_by, $business->id);

            $chartData = $this->getAppointmentChart(['duration' => 'week']);

            //staff report
            $staffOption = $request->options;
            $businessId = getActiveBusiness();
            $creatorId = creatorId();
            $statuses = CustomStatus::where('created_by', $creatorId)
                ->where('business_id', $businessId)
                ->get();
            $pendingStatus = new CustomStatus();
            $pendingStatus->id = 0;
            $pendingStatus->title = 'Pending';
            $statuses->prepend($pendingStatus);

            if (!empty($staffOption)) {
                $staffs = Staff::select('name', 'user_id')
                    ->whereIn('user_id', $staffOption)
                    ->where('created_by', $creatorId)
                    ->where('business_id', $businessId)
                    ->get();
            } else {
                $staffs = Staff::select('name', 'user_id')
                    ->where('created_by', $creatorId)
                    ->where('business_id', $businessId)
                    ->get();
            }

            $staffStatusAppointments = Appointment::select('staff_id', 'appointment_status', DB::raw('count(*) as appointment_count'), DB::raw('SUM(appointment_payments.amount) as total_revenue'))
                ->join('appointment_payments', 'appointments.id', '=', 'appointment_payments.appointment_id')
                ->whereIn('staff_id', $staffs->pluck('user_id'))
                ->where('appointments.created_by', $creatorId)
                ->where('appointments.business_id', $businessId)
                ->groupBy('staff_id', 'appointment_status')
                ->get()
                ->groupBy('staff_id')  // Group by staff_id to create a collection grouped by staff
                ->map(function ($group) {
                    return $group->keyBy('appointment_status'); // Key each group by appointment_status
                });



            $staffStatusAppointments->each(function ($staffAppointments) {
                if ($staffAppointments->has('Pending')) {
                    $staffAppointments[0] = $staffAppointments['Pending'];
                    unset($staffAppointments['Pending']);
                }
            });

            $totalAppointments = $staffStatusAppointments->flatten(1)->sum('appointment_count');
            $totalRevenue = $staffStatusAppointments->flatten(1)->sum('total_revenue');
            $getCompanySettings = getCompanyAllSetting();

            // Appointments by Service
            $appointments = Appointment::where('business_id', getActiveBusiness())
                ->where('created_by', creatorId())
                ->with('ServiceData')
                ->select('service_id', DB::raw('count(*) as total'))
                ->groupBy('service_id')
                ->get();
            $colors = Appointment::ColorCode();
            $servicesChart = [];
            foreach ($appointments as $index => $appointment) {
                $servicesChart[] = [
                    'name' => $appointment->ServiceData->name ?? '-',
                    'value' => $appointment->total,
                    'color' => $colors[$index % count($colors)]
                ];
            }

            if (!empty($staffOption)) {
                $view = view('analytics_filter', compact('staffs', 'statuses', 'staffStatusAppointments', 'totalAppointments', 'totalRevenue'))->render();
                return response()->json(['html' => $view]);
            } else {
                $compact = ['total_business', 'total_service', 'total_appointment', 'total_staff', 'latest_services', 'latest_appointments', 'business', 'chartData', 'total_location', 'total_appointment_payment', 'staffs', 'statuses', 'staffStatusAppointments', 'totalAppointments', 'totalRevenue', 'getCompanySettings', 'getCurrencyFormatSymbol', 'latest_businesses', 'servicesChart'];
            }

            return view('dashboard', compact($compact));
        } else {

            return redirect()->route('start');
        }
    }

    public function getAppointmentChart($arrParam)
    {
        $arrDuration = [];
        if ($arrParam['duration']) {
            if ($arrParam['duration'] == 'week') {
                $previous_week = strtotime("-1 week +1 day");
                for ($i = 0; $i < 7; $i++) {
                    $arrDuration[date('Y-m-d', $previous_week)] = date('d-M', $previous_week);
                    $previous_week = strtotime(date('Y-m-d', $previous_week) . " +1 day");
                }
            }
        }

        // Create an array of dates from your $arrDuration array
        $dates = array_keys($arrDuration);

        $orders = Appointment::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('count(*) as total')
        )
            ->where('business_id', getActiveBusiness())
            ->whereIn(DB::raw('DATE(created_at)'), $dates)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->get();
        // Initialize an empty $arrTask array
        $arrTask = ['label' => [], 'data' => []];

        foreach ($dates as $date) {
            $label = $arrDuration[$date];
            $total = 0;

            foreach ($orders as $item) {
                if ($item->date == $date) {
                    $total = $item->total;
                    break;
                }
            }

            $arrTask['label'][] = $label;
            $arrTask['data'][] = $total;
        }
        return $arrTask;
    }
}
