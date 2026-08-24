<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Traits\ApiResponser;
use App\Models\Appointment;
use App\Models\User;
use App\Models\AppointmentPayment;
use App\Models\Business;
use App\Models\Service;
use App\Models\CustomStatus;
use App\Models\Location;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use DB;
use App\Models\category;

class ApiController extends Controller
{
    use ApiResponser;

    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }
        if (!empty($request->password)) {
            $user = Auth::attempt(['email' => $request->email, 'password' => $request->password]);
            if (!$user) {
                return $this->error(['message' => 'Invalid login details']);
            }
            $user = Auth::user();
            if($user->type != 'company')
            {
                return $this->error(['message' => 'only admin can login here']);
            }


        } else {
            return $this->error(['message' => 'Invalid login details']);
        }

        $user_array['id'] = $user->id;
        $user_array['name'] = $user->name;
        $user_array['email'] = $user->email;
        $user_array['active_business'] = $user->active_business;

        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;
        $user_array['token'] = $token;
        $user_array['token_type'] = 'Bearer';
        return $this->success($user_array);

    }

    public function dashboard(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;
        $business_data = Business::find($user->active_business);
        $dashboard_data =[];

        $totalAppointment = Appointment::where('business_id',$active_business)->count();
        $totalRevenue = AppointmentPayment::where('business_id',$active_business)->sum('amount');

        $dashboard_data['total_business'] = getBusiness()->count();
        $dashboard_data['total_appointment'] = $totalAppointment;
        $dashboard_data['total_revenue'] = currency_format_with_sym($totalRevenue,$user->id,$active_business);
        $dashboard_data['business_url'] = (route('appointments.form', $business_data->slug));

        // Appointment chart
        $arrDuration = [];
        $arrParam = ['duration' => 'week'];


        if ($arrParam['duration']) {
            if ($arrParam['duration'] == 'week') {
                $previous_week = strtotime("-1 week +1 day");

                for ($i = 0; $i < 7; $i++) {
                    $arrDuration[date('Y-m-d', $previous_week)] = date('d-M', $previous_week);
                    $previous_week = strtotime(date('Y-m-d', $previous_week) . " +1 day");
                }
            }
        }
        $arrTask = [];
        $i = 0;
        $arrTask[$i]['date'] = [];
        $arrTask[$i]['appointment'] = [];
        foreach ($arrDuration as $date => $label) {
                $data = Appointment::select(\DB::raw('count(*) as total'))->where('business_id', $active_business)->whereDate('created_at', '=', $date)->first();

            $arrTask[$i]['date'] = $label;
            $arrTask[$i]['appointment'] = $data->total;
            $i++;
        }

        $dashboard_data['appointmentChart'] = $arrTask;

        //latest service
        $latest_service = [];
        $serviceData = Service::where('business_id', $active_business)->latest()->take(5)->get();
        if(!empty($serviceData))
        {
            foreach ($serviceData as $key => $value) {
                $latest_service[$key]['id']  = $value->id;
                $latest_service[$key]['name']  = $value->name;
                $latest_service[$key]['price']  = $value->price;
                $latest_service[$key]['image']  = get_file($value->image);
            }
            $dashboard_data['product'] = $latest_service;
        }else{
            $dashboard_data['product'] = 'Product Data not found!';
        }

        //latest Appointment
        $latest_Appointment = [];
        $appointmentData = Appointment::where('business_id', $active_business)->latest()->take(5)->get();
        if(!empty($appointmentData))
        {
            foreach ($appointmentData as $key => $value) {
                $appointmentNumber = Appointment::appointmentNumberFormat($value->id, $value->created_by, $value->business_id) ;

                $latest_Appointment[$key]['id']  = $value->id;
                $latest_Appointment[$key]['appointment_number']  = $appointmentNumber;
                $latest_Appointment[$key]['date']  = $value->date;
                $latest_Appointment[$key]['duration']  = $value->time;
                $latest_Appointment[$key]['customer']  = !empty($value->CustomerData) ? $value->CustomerData->name : 'Guest';
                $latest_Appointment[$key]['email']  = !empty($value->CustomerData) ? $value->CustomerData->customer->email : $value->email;
                $latest_Appointment[$key]['contact']  = !empty($value->CustomerData) ? $value->CustomerData->customer->mobile_no : $value->contact;
                $latest_Appointment[$key]['staff']  = !empty($value->StaffData) ? $value->StaffData->name : '-';
                $latest_Appointment[$key]['service']  = !empty($value->ServiceData) ? $value->ServiceData->name : '-';
                $latest_Appointment[$key]['location']  = !empty($value->LocationData) ? $value->LocationData->name : '-';
                $latest_Appointment[$key]['payment']  = !empty($value->payment_type) ? $value->payment_type : '-';
                $latest_Appointment[$key]['status']  = !empty($value->StatusData) ? $value->StatusData->title : 'Pending';
                $latest_Appointment[$key]['status_color']  = !empty($value->StatusData) ? $value->StatusData->status_color : '5bc0de';

            }
            $dashboard_data['appointment'] = $latest_Appointment;
        }else{
            $dashboard_data['appointment'] = 'Product Data not found!';
        }

        return $this->success($dashboard_data);

    }

    public function getBusinessList(Request $request)
    {

        $user = Auth::user();

        $business = getBusiness();
        $businessList =[];
        if(!empty($business))
        {
            foreach ($business as $key => $value)
            {
                $business_url = (route('appointments.form', $value->slug));

                $businessList[$key]['id'] = $value->id;
                $businessList[$key]['name'] = $value->name;
                $businessList[$key]['url'] = $business_url;
            }
        }
        return $this->success($businessList);
    }


    public function getAppointmentList(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;

        // service-list
        $serviceList = [];
        $serviceData = Service::where('business_id', $active_business)->get()->toArray();
        foreach ($serviceData as $key => $service) {
            $serviceList[$key]['id'] = $service['id'];
            $serviceList[$key]['name'] = $service['name'];
        }
        array_unshift($serviceList, ['id' => 0, 'name' => 'All']);

        if(!empty($request->service_id) && $request->service_id != '0')
        {
            $appointmentData = Appointment::forClinic($active_business)->with(Appointment::$eager)->where('service_id', $request->service_id)->paginate(10);
        }else{
            $appointmentData = Appointment::forClinic($active_business)->with(Appointment::$eager)->paginate(10);
        }

        if ($appointmentData->isNotEmpty()) {
            $appointmentList = $appointmentData->map(function ($value) {
                $appointmentNumber = Appointment::appointmentNumberFormat($value->id, $value->created_by, $value->business_id);

                return [
                    'id' => $value->id,
                    'appointment_number' => $appointmentNumber,
                    'date' => $value->date,
                    'duration' => $value->time,
                    'customer' => !empty($value->CustomerData) ? $value->CustomerData->name : 'Guest',
                    'email' => !empty($value->CustomerData) ? $value->CustomerData->customer->email : $value->email,
                    'contact' => !empty($value->CustomerData) ? $value->CustomerData->customer->mobile_no : $value->contact,
                    'staff' => !empty($value->StaffData) ? $value->StaffData->name : '-',
                    'service' => !empty($value->ServiceData) ? $value->ServiceData->name : '-',
                    'location' => !empty($value->LocationData) ? $value->LocationData->name : '-',
                    'payment' => !empty($value->payment_type) ? $value->payment_type : '-',
                    'status' => !empty($value->StatusData) ? $value->StatusData->title : 'Pending',
                    'status_color' => !empty($value->StatusData) ? $value->StatusData->status_color : '5bc0de'
                ];
            });

            return $this->success([
                    'service_list' => $serviceList,
                    'appointment_list' => $appointmentList,
                    'total' => $appointmentData->total(),
                    'per_page' => $appointmentData->perPage(),
                    'current_page' => $appointmentData->currentPage(),
                    'last_page' => $appointmentData->lastPage(),
                    'next_page_url' => $appointmentData->nextPageUrl(),
                    'prev_page_url' => $appointmentData->previousPageUrl(),
            ]);
        } else {
            return $this->error(['message' => 'Record not found!']);
        }
    }

    public function changeBusiness(Request $request)
    {
        $rules = [
            'business_id' => 'required',
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }

        $business = Business::find($request->business_id);
        if($business)
        {
            $user = Auth::user();
            $user->active_business = $request->business_id;
            $user->save();

            $user_array['active_business'] = $user->active_business;
            return $this->success($user_array);
        }
        else
        {
            return $this->error(['message' => 'Business not found!']);
        }
    }

    public function editProfile(Request $request)
    {
        $user = Auth::user();
        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required|max:120',
                'email' => ['required',
                            Rule::unique('users')->where(function ($query)  use ($user) {
                            return $query->whereNotIn('id',[$user->id])->where('created_by', $user->created_by)->where('business_id',$user->business_id);
                        })
                ],
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }

        if ($request->hasFile('profile'))
        {

            $filenameWithExt = $request->file('profile')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('profile')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $path = upload_file($request,'profile',$fileNameToStore,'users-avatar');
            if($path['flag'] == 1){
                // old img delete
                if(!empty($user->avatar) && strpos($user->avatar,'avatar.png') == false && check_file($user->avatar))
                {
                    delete_file($user->avatar);
                }
            }else{
                return $this->error(['message' => $path['msg']]);
            }
        }

        if (!empty($request->profile) && isset($path['url']))
        {
            $user->avatar =  $path['url'];
        }

        $user->name  = $request->name;
        $user->email = $request->email;
        $user->save();

        $user_array['name'] = $user->name;
        $user_array['email'] = $user->email;
        $user_array['profile'] = get_file($user->avatar);

        return $this->success($user_array);
    }

    public function changePassword(Request $request)
    {
        $user = Auth::user();

        $rules = [
            'current_password' => 'required',
            'new_password' => 'required|min:6',
            'confirm_password' => 'required|same:new_password',
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }

        $current_password = $user->password;
        if (Hash::check($request->current_password, $current_password)) {
            $user->password = Hash::make($request->new_password);
            $user->save();

            return $this->success(['message' => 'User Password updated successfully.']);
        } else {
            return $this->error(['message' => 'Please enter correct current password!']);
        }

    }

    public function editBusiness(Request $request)
    {
        $rules = [
            'name' => 'required',
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }

        $user = Auth::user();
        $active_business = $user->active_business;

        $business = Business::find($active_business);
        if($business)
        {
            $business->name = $request->name;
            if($request->slug)
            {
                $business->slug = $request->slug;
            }
            $business->save();

            return $this->success(['message' => 'Business updated successfully.']);
        }
        else
        {
            return $this->error(['message' => 'Business not found!']);
        }
    }

    public function deleteBusiness(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;
        $business = Business::find($active_business);
        if($business)
        {
            $other_business = Business::where('created_by', $user->id)->where('is_disable', 1)->where('id', '!=', $business->id)->first();
            if($other_business)
            {
                $user->active_business = $other_business->id;
                $user->save();
                $business->delete();
                return $this->success(['business_id'=>$user->active_business,'message' => 'Business deleted successfully!']);

            }
            return $this->error(['message' => 'You can not delete Business! because your other businesses are disabled']);
        }
        else
        {
            return $this->error(['message' => 'Business not found!']);
        }
    }

    public function getAppointmentStatusList(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;

        $appointmentStatus = CustomStatus::where('business_id',$active_business)->get();

        $statusList =[];
        if($appointmentStatus->isNotEmpty())
        {
            foreach ($appointmentStatus as $key => $value)
            {
                $statusList[$key]['id'] = $value->id;
                $statusList[$key]['title'] = $value->title;
            }
            return $this->success($statusList);
        }
        else{
            return $this->error(['message' => 'Record not found!']);
        }
    }

    public function changeAppointmentStatus(Request $request)
    {
        $rules = [
            'appointment_id' => 'required',
            'status' => 'required',
        ];

        $validator = \Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }

        $appointment = Appointment::find($request->appointment_id);
        if($appointment)
        {
            $appointment->appointment_status =  $request->status;
            $appointment->save();
            return $this->success(['message' => 'Appointment Status updated successfully.']);
        }
        else
        {
            return $this->error(['message' => 'Appointment not found!']);
        }
    }

    public function getAppointmentCalendarData(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;

        $startDate = Carbon::createFromDate($request->year, $request->month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($request->year, $request->month, 1)->endOfMonth();
        $appointmentData = Appointment::forClinic($active_business)
                    ->with(Appointment::$eager)
                    ->whereBetween('date_sort', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                    ->get();


        if($appointmentData->isNotEmpty())
        {
            $appointmentList=[];
            foreach ($appointmentData as $key => $value) {
                $appointmentNumber = Appointment::appointmentNumberFormat($value->id, $value->created_by, $value->business_id) ;

                $appointmentList[$key]['id']  = $value->id;
                $appointmentList[$key]['appointment_number']  = $appointmentNumber;
                $appointmentList[$key]['service']  = !empty($value->ServiceData) ? $value->ServiceData->name : '-';
                list($start, $end) = explode('-', $value->time);
                $appointmentList[$key]['from_time']  = $start;
                $appointmentList[$key]['to_time']  = $end;
                $appointmentList[$key]['date']  = $value->date;
            }
            return $this->success($appointmentList);
        }
        else
        {
            return $this->error(['message' => 'Record not found!']);
        }
    }

    public function logout(Request $request)
    {
        $user = User::find($request->user_id);
        if (!empty($user)) {
            return $this->success([
                'message' => 'User Logout',
                'logout' => $user->tokens()->delete()
            ]);
        } else {
            return $this->error([
                'message' => 'User not found'
            ]);
        }
    }

    public function getServiceList(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;

        // category-list
        $categoryData = [];
        $categories = category::where('business_id', $active_business)->get()->toArray();
        foreach ($categories as $key => $category) {
            $categoryData[$key]['id'] = $category['id'];
            $categoryData[$key]['name'] = $category['name'];
        }

        $serviceData = Service::where('business_id', $active_business)->paginate(10);

        if ($serviceData->isNotEmpty()) {
            $serviceList = $serviceData->map(function ($value) {
                return [
                    'id' => $value->id,
                    'name' => $value->name,
                    'category' => $value->Category->name,
                    'image' => check_file($value->image) ? get_file($value->image) : get_file('uploads/default/avatar.png') ,
                    'price' => currency_format_with_sym($value->price,$value->created_by, $value->business_id),
                    'duration' => $value->duration,
                    'description' => $value->description ? $value->description : ''
                ];
            });

            return $this->success([
                    'category_list' => $categoryData,
                    'service_list' => $serviceList,
                    'total' => $serviceData->total(),
                    'per_page' => $serviceData->perPage(),
                    'current_page' => $serviceData->currentPage(),
                    'last_page' => $serviceData->lastPage(),
                    'next_page_url' => $serviceData->nextPageUrl(),
                    'prev_page_url' => $serviceData->previousPageUrl(),
            ]);
        } else {
            return $this->error(['message' => 'Record not found!']);
        }
    }

    public function createService(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;

        $validator = \Validator::make(
            $request->all(),
            [
                'name' => 'required',
                'category' => 'required',
                'price' => 'required',
                'duration' => 'required',
                'service_image' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }

        if ($request->hasFile('service_image')) {
            $filenameWithExt = $request->file('service_image')->getClientOriginalName();
            $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
            $extension       = $request->file('service_image')->getClientOriginalExtension();
            $fileNameToStore = $filename . '_' . time() . '.' . $extension;

            $uplaod = upload_file($request, 'service_image', $fileNameToStore, 'Service');
            if ($uplaod['flag'] == 1) {
                $url = $uplaod['url'];
            } else {
                return $this->error(['message' => $uplaod['msg']]);
            }
        }

        $service                   = new Service();
        $service->name             = $request->name;
        $service->category_id      = $request->category;
        $service->price            = $request->price;
        $service->duration         = $request->duration;
        $service->description      = !empty($request->description) ? $request->description : '';
        $service->image            = !empty($request->service_image) ? $url : '';
        $service->business_id      = $active_business;
        $service->created_by       = creatorId();
        $service->save();

        return $this->success(['message' => 'Service successfully created.']);
    }

    public function editService(Request $request)
    {
        $service = Service::find($request->id);

        if(!empty($service))
        {
            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                    'category' => 'required',
                    'price' => 'required',
                    'duration' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return $this->error([
                    'message' => $messages->first()
                ]);
            }
            $service->name             = $request->name;
            $service->category_id      = $request->category;
            $service->price            = $request->price;
            $service->duration         = $request->duration;
            if($request->description)
            {
                $service->description      = !empty($request->description) ? $request->description : '';
            }
            if ($request->hasFile('service_image')) {
                if (!empty($service->image)) {
                    delete_file($service->image);
                }
                $filenameWithExt = $request->file('service_image')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('service_image')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $uplaod = upload_file($request, 'service_image', $fileNameToStore, 'Service');
                if ($uplaod['flag'] == 1) {
                    $url = $uplaod['url'];
                } else {
                    return $this->error(['message' => $uplaod['msg']]);
                }
                $service->image  = !empty($request->service_image) ? $url : '';
            }
            $service->save();
            return $this->success(['message' => 'Service updated successfully.']);

        }else{
            return $this->error(['message' => 'Service not found.']);
        }
    }

    public function deleteService(Request $request)
    {
        $service = Service::find($request->id);
        if(!empty($service))
        {
            if (!empty($service->image)) {
                delete_file($service->image);
            }
            $service->delete();
            return $this->success(['message' => 'Service successfully delete.']);
        }else{
            return $this->error(['message' => 'Service not found.']);
        }
    }

    public function deleteAppointment(Request $request)
    {
        $appointment = Appointment::find($request->appointment_id);
        if(!empty($appointment))
        {
            $appointment->delete();
            return $this->success(['message' => 'Appointment successfully delete.']);
        }else{
            return $this->error(['message' => 'Appointment not found.']);
        }
    }

    public function getCustomStatusList(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;

        $statusData = [];
        $statuses = CustomStatus::where('business_id', $active_business)->get()->toArray();
        foreach ($statuses as $key => $status) {
            $statusData[$key]['id'] = $status['id'];
            $statusData[$key]['title'] = $status['title'];
            $statusData[$key]['status_color'] = $status['status_color'];
        }

        if ($statuses) {
            return $this->success($statusData);
        } else {
            return $this->error(['message' => 'Record not found!']);
        }
    }

    public function createCustomStatus(Request $request)
    {
        $user = Auth::user();
        $active_business = $user->active_business;

        $validator = \Validator::make(
            $request->all(),
            [
                'title' => 'required',
                'status_color' => 'required',
            ]
        );
        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return $this->error([
                'message' => $messages->first()
            ]);
        }

        $customstatus                   = new CustomStatus();
        $customstatus->title            = $request->title;
        $customstatus->status_color     = $request->status_color;
        $customstatus->business_id      = $active_business;
        $customstatus->created_by       = creatorId();
        $customstatus->save();

        return $this->success(['message' => 'Custom Status successfully created.']);
    }

    public function editCustomStatus(Request $request)
    {
        $customstatus = CustomStatus::find($request->id);

        if(!empty($customstatus))
        {
            $validator = \Validator::make(
                $request->all(),
                [
                    'title' => 'required',
                    'status_color' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return $this->error([
                    'message' => $messages->first()
                ]);
            }
            $customstatus->title             = $request->title;
            $customstatus->status_color      = $request->status_color;
            $customstatus->save();
            return $this->success(['message' => 'Custom Status updated successfully.']);

        }else{
            return $this->error(['message' => 'Custom Status not found.']);
        }
    }

    public function deleteCustomStatus(Request $request)
    {
        $status = CustomStatus::find($request->id);
        if(!empty($status))
        {
            $status->delete();
            return $this->success(['message' => 'Custom Status successfully delete.']);
        }else{
            return $this->error(['message' => 'Custom Status not found.']);
        }
    }
}
