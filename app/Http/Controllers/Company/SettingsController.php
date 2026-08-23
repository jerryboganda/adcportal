<?php

namespace App\Http\Controllers\Company;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index($settings)
    {

        $timezones = config('timezones');
        return view('company.settings.index',compact('settings','timezones'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        if(Auth::user()->isAbleTo('setting manage'))
        {
            $post = $request->all();
            $company_settings = getCompanyAllSetting();

            unset($post['_token']);
            unset($post['_method']);

            if(!isset($post['site_rtl'])){
                $post['site_rtl'] = 'off';
            }
            if(!isset($post['site_transparent'])){
                $post['site_transparent'] = 'off';
            }
            if(!isset($post['cust_darklayout'])){
                $post['cust_darklayout'] = 'off';
            }
            if (isset($request->color) && $request->color_flag == 'false') {
                $post['color'] = $request->color;
            } else {
                $post['color'] = $request->custom_color;
            }
            unset($post['custom_color']);


            if($request->hasFile('logo_dark'))
            {
                $logo_dark =  'logo_dark_'.time().'.png';
                $uplaod = upload_file($request,'logo_dark',$logo_dark,'logo');
                if($uplaod['flag'] == 1)
                {
                    $post['logo_dark'] = $uplaod['url'];

                    $old_logo_dark = isset($company_settings['logo_dark']) ? $company_settings['logo_dark'] : '';
                    if(!empty($old_logo_dark) && check_file($old_logo_dark))
                    {
                        delete_file($old_logo_dark);
                    }
                }else{
                    return redirect()->back()->with('error',$uplaod['msg']);
                }
            }
            if($request->hasFile('logo_light'))
            {
                $logo_light =  'logo_light_'.time().'.png';
                $uplaod = upload_file($request,'logo_light',$logo_light,'logo');
                if($uplaod['flag'] == 1)
                {
                    $post['logo_light'] = $uplaod['url'];

                    $old_logo_light = isset($company_settings['logo_light']) ? $company_settings['logo_light'] : '';
                    if(!empty($old_logo_light) && check_file($old_logo_light))
                    {
                        delete_file($old_logo_light);
                    }
                }else{
                    return redirect()->back()->with('error',$uplaod['msg']);
                }
            }
            if($request->hasFile('favicon'))
            {
                $favicon =  'favicon_'.time().'.png';
                $uplaod = upload_file($request,'favicon',$favicon,'logo');
                if($uplaod['flag'] == 1){
                    $post['favicon'] = $uplaod['url'];

                    $old_favicon = isset($company_settings['favicon']) ? $company_settings['favicon'] : '';
                    if(!empty($old_favicon) && check_file($old_favicon))
                    {
                        delete_file($old_favicon);
                    }
                }else{
                    return redirect()->back()->with('error',$uplaod['msg']);
                }
            }

            // DELETE ALL DUPLICATES FIRST - Prevent duplicate settings
            $businessId = getActiveBusiness();
            $userId = creatorId();
            $allExisting = Setting::where('business', $businessId)->where('created_by', $userId)->get();
            $grouped = $allExisting->groupBy('key');
            foreach ($grouped as $key => $settingsGroup) {
                if ($settingsGroup->count() > 1) {
                    $keep = $settingsGroup->sortByDesc('updated_at')->first();
                    foreach ($settingsGroup->where('id', '!=', $keep->id) as $duplicate) {
                        $duplicate->delete();
                    }
                }
            }
            
            // CRITICAL FIX: Preserve existing logo values if they weren't uploaded in this request
            $logoKeys = ['logo_dark', 'logo_light', 'favicon'];
            foreach ($logoKeys as $logoKey) {
                if (!isset($post[$logoKey]) && !empty($company_settings[$logoKey])) {
                    $post[$logoKey] = $company_settings[$logoKey];
                }
            }
            
            foreach ($post as $key => $value) {
                // Define the data to be updated or inserted
                $data = [
                    'key' => $key,
                    'business' => $businessId,
                    'created_by' => $userId,
                ];

                // Check if the record exists, and update or insert accordingly
                Setting::updateOrInsert($data, ['value' => $value]);
            }
            // Settings Cache forget
            comapnySettingCacheForget();
            
            session()->put('success_message', __('Setting save sucessfully.'));
            return redirect()->back();
        }
        else
        {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function SystemStore(Request $request)
    {
        if (Auth::user()->isAbleTo('setting manage')) {
            $post = $request->all();
            unset($post['_token']);
            unset($post['_method']);
            
            // DELETE ALL DUPLICATES FIRST
            $businessId = getActiveBusiness();
            $userId = creatorId();
            $allExisting = Setting::where('business', $businessId)->where('created_by', $userId)->get();
            $grouped = $allExisting->groupBy('key');
            foreach ($grouped as $key => $settingsGroup) {
                if ($settingsGroup->count() > 1) {
                    $keep = $settingsGroup->sortByDesc('updated_at')->first();
                    foreach ($settingsGroup->where('id', '!=', $keep->id) as $duplicate) {
                        $duplicate->delete();
                    }
                }
            }

            foreach ($post as $key => $value) {
                // Define the data to be updated or inserted
                $data = [
                    'key' => $key,
                    'business' => $businessId,
                    'created_by' => $userId,
                ];

                // Check if the record exists, and update or insert accordingly
                Setting::updateOrInsert($data, ['value' => $value]);
            }

            // Settings Cache forget
            AdminSettingCacheForget();
            comapnySettingCacheForget();
            
            session()->put('success_message', __('Setting save sucessfully.'));
            return redirect()->back();
        } else {
            session()->put('error_message', __('Permission denied.'));
            return redirect()->back();
        }
    }

    // currency setting store
    public function saveCompanyCurrencySettings(Request $request)
    {
        $post = $request->all();
        unset($post['_token']);
        unset($post['_method']);
        if (isset($post['defult_currancy'])) {
            $data = explode('-', $post['defult_currancy']);
            $post['defult_currancy_symbol'] = $data[0];
            $post['defult_currancy']        = $data[1];
        } else {
            $post['defult_currancy']        = 'USD';
            $post['defult_currancy_symbol'] = '$';
        }
        if (isset($post['site_currency_symbol_position'])) {
            $post['site_currency_symbol_position'] = !empty($request->site_currency_symbol_position) ? $request->site_currency_symbol_position : 'pre';
        }
        foreach ($post as $key => $value) {
            // Define the data to be updated or inserted
            $data = [
                'key' => $key,
                'business' => getActiveBusiness(),
                'created_by' => creatorId(),
            ];

            // Check if the record exists, and update or insert accordingly
            Setting::updateOrInsert($data, ['value' => $value]);
        }
        // Settings Cache forget
        AdminSettingCacheForget();
        comapnySettingCacheForget();
        return redirect()->back()->with('success', __('Currency Setting save successfully.'));
    }

    public function CompanySettingStore(Request $request)
    {
        $validator = \Validator::make($request->all(),
        [
            'company_name' => 'required',
            'company_address' => 'required',
            'company_city' => 'required',
            'company_state' => 'required',
            'company_zipcode' => 'required',
            'company_country' => 'required',
            'company_telephone' => 'required',
            'company_email' => 'required',
            'company_email_from_name' => 'required',
        ]);
        if($validator->fails()){
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }
        else
        {
            $post = $request->all();
            unset($post['_token']);
            unset($post['_method']);

            if(isset($request->vat_gst_number_switch) && $request->vat_gst_number_switch == 'on')
            {
                $post['vat_gst_number_switch'] = 'on';
                $post['tax_type'] =  !empty($request->tax_type) ? $request->tax_type : 'VAT';
                $post['vat_number'] =  !empty($request->vat_number) ? $request->vat_number : '';
            }
            else
            {
                $post['vat_gst_number_switch'] = 'off';
                $post['tax_type'] = '';
                $post['vat_number'] = '';
            }

            foreach ($post as $key => $value) {
                // Define the data to be updated or inserted
                $data = [
                    'key' => $key,
                    'business' => getActiveBusiness(),
                    'created_by' => creatorId(),
                ];

                // Check if the record exists, and update or insert accordingly
                Setting::updateOrInsert($data, ['value' => $value]);
            }
            // Settings Cache forget
            comapnySettingCacheForget();
            return redirect()->back()->with('success','Company setting save sucessfully.');
        }

    }

    public function CustomJsStore(Request $request)
    {
        $validator = \Validator::make($request->all(),
        [
            'custom_js' => 'required',
        ]);

        if($validator->fails()){
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $customJs = $request->custom_js;

        // Remove existing <script> tags
        $customJs = preg_replace('/<script.*?>.*?<\/script>/si', '', $customJs);

        $customJs = htmlspecialchars($customJs);

        // Define the data to be updated or inserted
        $data = [
            'key' => 'custom_js',
            'business' => getActiveBusiness(),
            'created_by' => creatorId(),
        ];

        // Check if the record exists, and update or insert accordingly
        Setting::updateOrInsert($data, ['value' => $customJs]);

        // Settings Cache forget
        comapnySettingCacheForget();
        return redirect()->back()->with('success','Custom JS save sucessfully.');

    }

    public function CustomCssStore(Request $request)
    {
        $validator = \Validator::make($request->all(),
        [
            'custom_css' => 'required',
        ]);

        if($validator->fails()){
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $customCss = $request->custom_css;

        // Remove existing <style> tags
        $customCss = preg_replace('/<style.*?>.*?<\/style>/si', '', $customCss);

        $customCss = htmlspecialchars($customCss);

        // Define the data to be updated or inserted
        $data = [
            'key' => 'custom_css',
            'business' => getActiveBusiness(),
            'created_by' => creatorId(),
        ];

        // Check if the record exists, and update or insert accordingly
        Setting::updateOrInsert($data, ['value' => $customCss]);

        // Settings Cache forget
        comapnySettingCacheForget();
        return redirect()->back()->with('success','Custom CSS save sucessfully.');

    }

    public function weekStore(Request $request)
    {
        $validator = \Validator::make($request->all(),
        [
            'week_start_day' => 'required',
        ]);

        if($validator->fails()){
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }


        // Define the data to be updated or inserted
        $data = [
            'key' => 'week_start_day',
            'business' => getActiveBusiness(),
            'created_by' => creatorId(),
        ];

        // Check if the record exists, and update or insert accordingly
        Setting::updateOrInsert($data, ['value' => $request->week_start_day]);

        // Settings Cache forget
        comapnySettingCacheForget();
        return redirect()->back()->with('success','Week setting save sucessfully.');
    }

    public function DefaultStatusStore(Request $request)
    {
        $validator = \Validator::make($request->all(),
        [
            'default_status' => 'required',
        ]);

        if($validator->fails()){
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }


        // Define the data to be updated or inserted
        $data = [
            'key' => 'default_status',
            'business' => getActiveBusiness(),
            'created_by' => creatorId(),
        ];

        // Check if the record exists, and update or insert accordingly
        Setting::updateOrInsert($data, ['value' => $request->default_status]);

        // Settings Cache forget
        comapnySettingCacheForget();
        return redirect()->back()->with('success','Default appointment status save sucessfully.');
    }

    public function bookingModeStore(Request $request)
    {
        $validator = \Validator::make($request->all(), [
            'booking_mode' => 'required|array', 
        ]);

        if ($validator->fails()) {
            $messages = $validator->getMessageBag();
            return redirect()->back()->with('error', $messages->first());
        }

        $selectedModes = array_keys(array_filter($request->booking_mode, function ($value) {
            return $value != 0;
        }));

        if (empty($selectedModes)) {
            return redirect()->back()->with('error', __('Please select at least one booking mode.'));
        }

        $bookingModeValues = implode(',', $selectedModes);

        $data = [
            'key' => 'booking_mode',
            'business' => getActiveBusiness(),
            'created_by' => creatorId(),
        ];

        Setting::updateOrInsert($data, ['value' => $bookingModeValues]);

        comapnySettingCacheForget();

        return redirect()->back()->with('success', __('Booking Mode saved successfully.'));
    }


    public function companyupdateNoteValue(Request $request)
    {
        $symbol_position = 'pre';
        $symbol = '$';
        $format = '1';
        $price  = '10000';
        $number = explode('.', $price);
        $length = strlen(trim($number[0]));
        $currency_symbol = explode('-',$request->defult_currancy);

        if ($length > 3) {
            $decimal_separator  = isset($request->float_number) && $request->float_number == 'dot' ? '.' : ',';
            $thousand_separator = isset($request->thousand_separator) && $request->thousand_separator == 'dot' ? '.' : ',';
        } else {
            $decimal_separator  = isset($request->decimal_separator) && $request->decimal_separator === 'dot'  ? '.' : ',';
            $thousand_separator = isset($request->thousand_separator) && $request->thousand_separator === 'dot' ? '.' : ',';
        }
        if (isset($request->site_currency_symbol_position) && $request->site_currency_symbol_position == "post") {
            $symbol_position = 'post';
        }

        if (isset($request->defult_currancy)) {
            $symbol = $request->defult_currancy;
        }

        if (isset($request->currency_format)) {
            $format = $request->currency_format;
        }
        if (isset($request->currency_space)) {
            $currency_space = isset($request->currency_space) ? $request->currency_space : '';
        }
        if (isset($request->site_currency_symbol_name)) {
            $symbol = $request->site_currency_symbol_name == 'symbol' ? $currency_symbol[0] : $currency_symbol[1];
        }
        $formatted_price = (
            ($symbol_position == "pre")  ?  $symbol : '') . (isset($currency_space) && $currency_space == 'withspace' ? ' ' : '')
            . number_format($price, $format, $decimal_separator, $thousand_separator) . (isset($currency_space) && $currency_space == 'withspace' ? ' ' : '') .
            (($symbol_position == "post") ?  $symbol : '');
        return response()->json(['success' => true,'formatted_price' => $formatted_price]);
    }

}
