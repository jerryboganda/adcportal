<?php

namespace App\Http\Controllers;

use App\DataTables\BusinessDataTable;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Location;
use App\Models\Category;
use App\Models\Service;
use App\Models\Setting;
use App\Models\File;
use App\Models\Staff;
use App\Models\CustomField;
use App\Models\BusinessHoliday;
use App\Events\DestroyBusiness;
use App\Events\DefaultData;
use Illuminate\Support\Facades\Hash;

class BusinessController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(BusinessDataTable $dataTable)
    {
        if (Auth::user()->isAbleTo('business manage')) {
            return $dataTable->render('business.index');
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        if (Auth::user()->isAbleTo('business create')) {
            return view('business.create');
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // Single-clinic app: creating additional businesses is not allowed.
        return redirect()->back()->with('error', __('This is a single-clinic application; the clinic record already exists.'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Business $business)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id = null)
    {
        $id = $id ?? getActiveBusiness();

        if (Auth::user()->isAbleTo('business edit')) {
            $business = Business::find($id) ?? Business::first();
            return view('business.edit', compact('business'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id = null)
    {
        $id = $id ?? getActiveBusiness();

        if (Auth::user()->isAbleTo('business edit')) {
            $business = Business::find($id) ?? Business::first();

            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            $business->name = $request->name;
            $business->slug = $request->slug;
            $business->save();

            return redirect()->back()->with('success', __('Business updated successfully!'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function BusinessThemeUpdate(Request $request)
    {
        if (Auth::user()->isAbleTo('business edit')) {
            $business = Business::find($request->business_id);
            $validator = \Validator::make(
                $request->all(),
                [
                    // 'form_type' => 'required',
                    'layouts' => 'required',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }
            if ($request->form_type) {
                $business->form_type = $request->form_type;
            }
            $business->layouts = $request->layouts;
            $business->theme_color = (!empty($request->theme_color) && $request->form_type != 'website') ? $request->theme_color : '';
            $business->save();

            if (!empty($request->layouts)) {
                event(new DefaultData(\Auth::user()->id, $business->id, $request->layouts));
            }
            $tab = 12;
            return redirect()->back()->with('success', __('Business theme updated successfully!'))->with('tab', $tab);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($business_id)
    {
        // Single-clinic app: deleting THE clinic is not allowed.
        return redirect()->route('dashboard')->with('error', __("You can't delete the clinic in a single-clinic application."));
    }

    public function businessCheck(Request $request)
    {
        if (isset($request->slug)) {
            $business = Business::where('slug', $request->slug)->where('id', '!=', $request->business)->exists();
            if (!$business) {
                return response()->json(['success' => __('This Slug is Available.')]);
            }
        }
        return response()->json(['error' => __('This Slug Not Available.')]);
    }

    public function change($business_id)
    {
        // Single-clinic app: business switching removed.
        return redirect()->route('appointment.dashboard');
    }

    public function businessManage($id = null)
    {
        $id = $id ?? getActiveBusiness();

        if (Auth::user()->isAbleTo('business update')) {
            $business = Business::find($id) ?? Business::first();

            if (!$business) {
                return redirect()->route('dashboard')->with('error', __('No clinic record found.'));
            }

            $company_settings = getCompanyAllSetting($business->created_by, $id);
            $business_url = route('appointments.form', $business->slug);

            $serverName = str_replace(
                [
                    'http://',
                    'https://',
                ],
                '',
                env('APP_URL')
            );
            $serverIp = gethostbyname($serverName);

            if ($serverIp == $_SERVER['REMOTE_ADDR']) {
                $serverIp;
            } else {
                $serverIp = request()->server('REMOTE_ADDR');
            }

            if (!empty($company_settings['enable_subdomain']) && $company_settings['enable_subdomain'] == 'on') {
                // Remove the http://, www., and slash(/) from the URL
                $input = env('APP_URL');

                // If URI is like, eg. www.way2tutorial.com/
                $input = trim($input, '/');
                // If not have http:// or https:// then prepend it
                if (!preg_match('#^http(s)?://#', $input)) {
                    $input = 'http://' . $input;
                }

                $urlParts = parse_url($input);

                // Remove www.
                $subdomain_name = preg_replace('/^www\./', '', $urlParts['host']);
                // Output way2tutorial.com
            } else {
                $subdomain_name = str_replace(
                    [
                        'http://',
                        'https://',
                    ],
                    '',
                    env('APP_URL')
                );
            }

            $subdomain_Ip = '';
            $subdomainPointing = '';
            $domainip = '';
            $domainPointing = '';

            $locations = Location::where('business_id', $id)->where('created_by', creatorId())->get();
            $categories = Category::where('business_id', $id)->where('created_by', creatorId())->get();
            $services = Service::where('business_id', $id)->where('created_by', creatorId())->get();
            $staffes = Staff::where('business_id', $id)->where('created_by', creatorId())->get();
            $businessholidays = BusinessHoliday::where('business_id', $id)->where('created_by', creatorId())->get();
            $files = File::where('created_by', creatorId())->where('business_id', $id)->first();

            $excludedTypes = ['checkbox', 'radio', 'time', 'select'];
            $custom_fields = CustomField::where('created_by', creatorId())
                ->where('business_id', $id)
                ->whereNotIn('type', $excludedTypes)
                ->get();
            $custom_field = company_setting('custom_field_enable', creatorId(), $id);

            //PWA — Single-clinic app: PWA add-on removed.
            $pwa_data = '';

            return view('business.manage', compact('business', 'locations', 'categories', 'services', 'staffes', 'businessholidays', 'business_url', 'subdomain_Ip', 'subdomainPointing', 'domainip', 'domainPointing', 'serverIp', 'subdomain_name', 'company_settings', 'files', 'custom_field', 'custom_fields', 'pwa_data'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function domainsetting($id, Request $request)
    {
        if (Auth::user()->isAbleTo('business update')) {
            $business = Business::find($id);
            $post = $request->all();
            unset($post['_token']);

            if ($request->enable_domain == 'enable_domain') {
                // Remove the http://, www., and slash(/) from the URL
                $input = $request->domains;
                // If URI is like, eg. www.way2tutorial.com/
                $input = trim($input, '/');
                // If not have http:// or https:// then prepend it
                if (!preg_match('#^http(s)?://#', $input)) {
                    $input = 'http://' . $input;
                }

                $urlParts = parse_url($input);
                // Remove www.
                $domain_name = preg_replace('/^www\./', '', $urlParts['host'] ?? null);

                // Output way2tutorial.com
            }
            if ($request->enable_domain == 'enable_subdomain') {
                // Remove the http://, www., and slash(/) from the URL
                $input = env('APP_URL');

                // If URI is like, eg. www.way2tutorial.com/
                $input = trim($input, '/');
                // If not have http:// or https:// then prepend it
                if (!preg_match('#^http(s)?://#', $input)) {
                    $input = 'http://' . $input;
                }

                $urlParts = parse_url($input);

                // Remove www.
                $subdomain_name = preg_replace('/^www\./', '', $urlParts['host']);
                // Output way2tutorial.com
                $subdomain_name = $request->subdomain . '.' . $subdomain_name;
            }

            if ($request->enable_domain == 'enable_domain') {
                $post['domains'] = $domain_name;
            }

            $post['enable_businesslink'] = ($request->enable_domain == 'enable_businesslink' || empty($request->enable_domain)) ? 'on' : 'off';
            $post['enable_domain'] = ($request->enable_domain == 'enable_domain') ? 'on' : 'off';
            $post['enable_subdomain'] = ($request->enable_domain == 'enable_subdomain') ? 'on' : 'off';

            if ($request->enable_domain == 'enable_subdomain') {
                $post['subdomain'] = $subdomain_name;
            }

            foreach ($post as $key => $value) {
                // Define the data to be updated or inserted
                $data = [
                    'key' => $key,
                    'business' => $id,
                    'created_by' => $business->created_by,
                ];

                // Check if the record exists, and update or insert accordingly
                Setting::updateOrInsert($data, ['value' => $value]);
            }
            // Settings Cache forget
            comapnySettingCacheForget();
            $tab = 7;
            return redirect()->back()->with('success', __('Custom setting save sucessfully.'))->with('tab', $tab);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function slotCapacitysetting($id, Request $request)
    {
        if (Auth::user()->isAbleTo('business update')) {
            $business = Business::find($id);
            $validator = \Validator::make(
                $request->all(),
                [
                    'maximum_slot' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $data = [
                'key' => 'maximum_slot',
                'business' => $id,
                'created_by' => $business->created_by,
            ];

            // Check if the record exists, and update or insert accordingly
            Setting::updateOrInsert($data, ['value' => $request->maximum_slot]);
            // Settings Cache forget
            comapnySettingCacheForget();
            $tab = 8;
            return redirect()->back()->with('success', __('Slot capacity setting save sucessfully.'))->with('tab', $tab);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function appointmentRemindersetting($id, Request $request)
    {
        if (Auth::user()->isAbleTo('business update')) {
            $business = Business::find($id);
            $validator = \Validator::make(
                $request->all(),
                [
                    'reminder_interval' => 'required',
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();
                return redirect()->back()->with('error', $messages->first());
            }

            $data = [
                'key' => 'reminder_interval',
                'business' => $id,
                'created_by' => $business->created_by,
            ];

            // Check if the record exists, and update or insert accordingly
            Setting::updateOrInsert($data, ['value' => $request->reminder_interval]);
            // Settings Cache forget
            comapnySettingCacheForget();
            $tab = 8;
            return redirect()->back()->with('success', __('Appointment Reminder setting save sucessfully.'))->with('tab', $tab);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }


    public function ManageBusiness(Request $request)
    {
        return redirect()->route('business.manage', getActiveBusiness());
    }
}
