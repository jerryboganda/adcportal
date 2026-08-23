<?php

namespace App\Http\Controllers;

use App\Events\CreateUser;
use App\Events\DefaultData;
use App\Events\DestroyUser;
use App\Events\EditProfileUser;
use App\Events\UpdateUser;
use App\Models\EmailTemplate;
use App\Models\LoginDetail;
use App\Models\Role;
use App\Models\User;
use App\Models\Business;
use Illuminate\Http\Request;
use DataTables;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Events\Registered;
use App\DataTables\UsersDataTable;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if (Auth::user()->isAbleTo('user manage')) {
            // Single-clinic app: super-admin subscriber list removed; clinic users only.
            $users = User::where('created_by', creatorId());
            if($request->name)
            {
                $users->where('name', 'like', '%' . $request->name . '%');
            }
            if($request->email)
            {
                $users->where('email', 'like', '%' . $request->email . '%');
            }
            $users = $users->paginate(11);
            return view('users.index', compact('users'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    public function List(Request $request, UsersDataTable $dataTable)
    {
        if (Auth::user()->isAbleTo('user manage')) {
            return $dataTable->render('users.list');
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (Auth::user()->isAbleTo('user create')) {
            $roles = Role::where('name', '!=', 'customer')->where('name', '!=', 'staff')->where('created_by', \Auth::user()->id)->pluck('name', 'id')->prepend('Select Role', '');
            return view('users.create', compact('roles'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request): RedirectResponse
    {
        if (Auth::user()->isAbleTo('user create')) {
            // Single-clinic app: plan quota check removed.
            $validatorArray = [
                'name' => 'required|max:120',
                'email' => [
                    'required',
                    Rule::unique('users')->where(function ($query) {
                        return $query->where('type', '!=', 'staff')->where('created_by', creatorId())->where('business_id', getActiveBusiness());
                    })
                ],
            ];

            $validator = Validator::make(
                $request->all(),
                $validatorArray
            );

            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }
            $user['is_enable_login']       = 0;
            if (!empty($request->password_switch) && $request->password_switch == 'on') {
                $user['is_enable_login']   = 1;
                $validator = Validator::make(
                    $request->all(),
                    ['password' => 'required|min:6']
                );

                if ($validator->fails()) {
                    return redirect()->back()->with('error', $validator->errors()->first());
                }
            }
            if ($request->input('mobile_no')) {
                $validator = Validator::make(
                    $request->all(),
                    ['mobile_no' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:9',]
                );
                if ($validator->fails()) {
                    return redirect()->back()->with('error', $validator->errors()->first());
                }
            }
            // Single-clinic app: admin assigns roles directly; no company/business creation.
            $roles = Role::find($request->input('roles'));
            $company_settings = getCompanyAllSetting();

            $userpassword               = $request->input('password');
            $user['name']               = $request->input('name');
            $user['email']              = $request->input('email');
            $user['mobile_no']          = $request->input('mobile_no');
            $user['password']           = !empty($userpassword) ? \Hash::make($userpassword) : null;
            $user['lang']               = !empty($company_settings['defult_language']) ? $company_settings['defult_language'] : 'en';
            $user['type']               = $roles->name;
            $user['created_by']         = creatorId();
            $user['business_id']       = getActiveBusiness();
            $user['active_business']   = getActiveBusiness();
            $user = User::create($user);

            $role_r = Role::find($roles->id);

            $user->addRole($role_r);
            event(new CreateUser($user, $request));

            SetConfigEmail(Auth::user()->id);
            if (admin_setting('email_verification') == 'on') {
                try {
                    //code...
                    $user->sendEmailVerificationNotification();
                    // event(new Registered($user));
                } catch (\Throwable $th) {
                }
            } else {
                $user_data = User::find($user->id);
                $user_data->email_verified_at = date('Y-m-d h:i:s');
                $user_data->save();
            }


            //Email notification

            if ((!empty($company_settings['Create User']) && $company_settings['Create User']  == true)) {
                $uArr = [
                    'email' => $request->input('email'),
                    'password' => $request->input('password'),
                    'company_name' => $request->input('name'),
                ];
                $resp = EmailTemplate::sendEmailTemplate('New User', [$user->email], $uArr);
                return redirect()->route('users.index')->with('success', __('User successfully created.') . ((!empty($resp) && $resp['is_success'] == false && !empty($resp['error'])) ? '<br> <span class="text-danger">' . $resp['error'] . '</span>' : ''));
            }

            return redirect()->route('users.index')->with('success', __('User successfully created.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return redirect()->route('users.index');
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (Auth::user()->isAbleTo('user edit')) {
            $user = User::find($id);
            $roles = Role::where('name', '!=', 'customer')->where('name', '!=', 'staff')->where('created_by', \Auth::user()->id)->pluck('name', 'id');
            return view('users.edit', compact('user', 'roles'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (Auth::user()->isAbleTo('user edit')) {
            $validatorArray = [
                'name' => 'required|max:120',
                'email' => [
                    'required',
                    Rule::unique('users')->where(function ($query)  use ($id) {
                        return $query->whereNotIn('id', [$id])->where('type', '!=', 'staff')->where('created_by', creatorId())->where('business_id', getActiveBusiness());
                    })
                ],
            ];

            $validator = Validator::make(
                $request->all(),
                $validatorArray
            );
            if ($validator->fails()) {
                return redirect()->back()->with('error', $validator->errors()->first());
            }
            if ($request->input('mobile_no')) {
                $validator = Validator::make(
                    $request->all(),
                    ['mobile_no' => 'required|regex:/^([0-9\s\-\+\(\)]*)$/|min:9',]
                );
                if ($validator->fails()) {
                    return redirect()->back()->with('error', $validator->errors()->first());
                }
            }
            $user = User::find($id);
            if (!empty($user)) {
                // Single-clinic app: admin assigns roles directly.
                $role = Role::find($request->input('roles'));
                $user->name         = $request->name;
                $user->email        = $request->email;
                $user->type         = $role->name;
                $user->mobile_no    = $request->mobile_no;
                $user->save();
                $roles[] = $request->roles;
                $user->roles()->sync($roles);
                event(new UpdateUser($user, $request));

                return redirect()->route('users.index')->with(
                    'success',
                    'User successfully updated.'
                );
            }
            return redirect()->back()->with('error', __('Something is wrong.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (Auth::user()->isAbleTo('user delete')) {
            $user = User::findOrFail($id);

            // first parameter user
            event(new DestroyUser($user));

            try {
                // get all table
                $tables_in_db = \DB::select('SHOW TABLES');
                $db = "Tables_in_" . env('DB_DATABASE');
                foreach ($tables_in_db as $table) {
                    if (Schema::hasColumn($table->{$db}, 'created_by')) {
                        \DB::table($table->{$db})->where('created_by', $user->id)->delete();
                    }
                }
                $user->roles()->detach();
                $user->delete();
            } catch (\Exception $e) {
                return redirect()->back()->with('error', __($e->getMessage()));
            }

            return redirect()->route('users.index')->with('success', __('User successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function profile()
    {
        if (Auth::user()->isAbleTo('user profile manage')) {
            $userDetail = \Auth::user();

            return view('users.profile')->with('userDetail', $userDetail);
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function editprofile(Request $request)
    {
        if (Auth::user()->isAbleTo('user profile manage')) {
            $userDetail = \Auth::user();
            $user       = User::findOrFail($userDetail['id']);

            $validator = \Validator::make(
                $request->all(),
                [
                    'name' => 'required|max:120',
                    'email' => [
                        'required',
                        Rule::unique('users')->where(function ($query)  use ($user) {
                            return $query->where('type', '!=', 'staff')->whereNotIn('id', [$user->id])->where('created_by', $user->created_by)->where('business_id', $user->business_id);
                        })
                    ],
                ]
            );
            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }

            if ($request->hasFile('profile')) {

                $filenameWithExt = $request->file('profile')->getClientOriginalName();
                $filename        = pathinfo($filenameWithExt, PATHINFO_FILENAME);
                $extension       = $request->file('profile')->getClientOriginalExtension();
                $fileNameToStore = $filename . '_' . time() . '.' . $extension;

                $path = upload_file($request, 'profile', $fileNameToStore, 'users-avatar');
                if($path['flag'] == 1){
                    // old img delete
                    if (!empty($userDetail['avatar']) && strpos($userDetail['avatar'], 'avatar.png') == false && check_file($userDetail['avatar'])) {
                        delete_file($userDetail['avatar']);
                    }
                }
                else
                {
                    return redirect()->back()->with('error', $path['msg']);
                }
            }

            if (!empty($request->profile) && isset($path['url'])) {
                $user->avatar =  $path['url'];
            }
            
            // Use proper object property assignment instead of array syntax
            $user->name  = $request->name;
            $user->email = $request->email;
            
            // Add explicit error handling for save operation
            try {
                $user->save();
            } catch (\Exception $e) {
                \Log::error('Profile update error: ' . $e->getMessage());
                return redirect()->back()->with('error', 'Failed to save profile changes. Please try again.');
            }

            // first parameter request second user
            event(new EditProfileUser($request, $user));

            return redirect()->back()->with(
                'success',
                'Profile successfully updated.'
            );
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function updatePassword(Request $request)
    {
        if (Auth::user()->isAbleTo('user profile manage')) {
            if (\Auth::Check()) {
                $request->validate(
                    [
                        'current_password' => 'required',
                        'new_password' => 'required|min:6',
                        'confirm_password' => 'required|same:new_password',
                    ]
                );
                $objUser          = Auth::user();
                $request_data     = $request->All();
                $current_password = $objUser->password;
                if (Hash::check($request_data['current_password'], $current_password)) {
                    $user_id            = Auth::User()->id;
                    $obj_user           = User::find($user_id);
                    $obj_user->password = Hash::make($request_data['new_password']);;
                    $obj_user->save();

                    return redirect()->route('profile', $objUser->id)->with('success', __('Password successfully updated.'));
                } else {
                    return redirect()->route('profile', $objUser->id)->with('error', __('Please enter correct current password.'));
                }
            } else {
                return redirect()->route('profile', \Auth::user()->id)->with('error', __('Something is wrong.'));
            }
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function ajaxUserList(Request $request)
    {

        if ($request->ajax()) {
            $usersQuery = User::query();

            if (!empty($request->get('name'))) {
                $usersQuery->where('id', $request->get('name'));
            }

            $data = $usersQuery->select('*');

            return Datatables::of($data)
                ->addIndexColumn()

                ->addColumn('action', function ($row) {

                    $btn = '<a href="javascript:void(0)" class="edit-icon bg-info"><i class="fas fa-eye"></a>';

                    return $btn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }
    }
    public function UserPassword($id)
    {
        if (Auth::user()->isAbleTo('user reset password')) {
            $eId        = \Crypt::decrypt($id);
            $user = User::find($eId);
            return view('users.reset', compact('user'));
        } else {
            return response()->json(['error' => __('Permission denied.')], 401);
        }
    }
    public function UserPasswordReset(Request $request, $id)
    {
        if (Auth::user()->isAbleTo('user reset password')) {
            $validator = \Validator::make(
                $request->all(),
                [
                    'password' => 'required|confirmed|same:password_confirmation|min:6',
                ]
            );

            if ($validator->fails()) {
                $messages = $validator->getMessageBag();

                return redirect()->back()->with('error', $messages->first());
            }
            $user                 = User::where('id', $id)->first();

            if (isset($request->login_enable)) {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                    'is_enable_login' => 1,
                ])->save();
            } else {
                $user->forceFill([
                    'password' => Hash::make($request->password),
                ])->save();
            }

            return redirect()->route('users.index')->with(
                'success',
                'User Password successfully updated.'
            );
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function LoginManage($id)
    {
        if (Auth::user()->isAbleTo('user reset password')) {
            $eId        = \Crypt::decrypt($id);
            $user = User::find($eId);
            if ($user->is_enable_login == 1) {
                $user->is_enable_login = 0;
                $user->save();
                return redirect()->route('users.index')->with('success', 'User login disable successfully.');
            } else {
                $user->is_enable_login = 1;
                $user->save();
                return redirect()->route('users.index')->with('success', 'User login enable successfully.');
            }
        } else {
            return redirect()->route('users.index')->with('error', 'Permission denied.');
        }
    }

    public function UserLogHistory(Request $request)
    {
        if (Auth::user()->isAbleTo('user logs history')) {
            $filteruser = User::where('created_by', creatorId())->get()->pluck('name', 'id');
            $filteruser->prepend('Select User', '');

            if (Auth::user()->type == 'admin') {
                // Single-clinic app: admin sees all clinic login logs.
                $query = \DB::table('login_details')
                    ->join('users', 'login_details.user_id', '=', 'users.id')
                    ->select(\DB::raw('login_details.*, users.id as user_id , users.name as user_name , users.email as user_email ,users.type as user_type'))
                    ->where(['login_details.created_by' => creatorId()]);
            } else {
                $query = \DB::table('login_details')
                    ->join('users', 'login_details.user_id', '=', 'users.id')
                    ->select(\DB::raw('login_details.*, users.id as user_id , users.name as user_name , users.email as user_email ,users.type as user_type'))
                    ->where(['login_details.user_id' => \Auth::user()->id]);
            }


            if (!empty($request->month)) {
                $query->whereMonth('date', date('m', strtotime($request->month)));
                $query->whereYear('date', date('Y', strtotime($request->month)));
            } else {
                $query->whereMonth('date', date('m'));
                $query->whereYear('date', date('Y'));
            }

            if (!empty($request->users)) {
                $query->where('user_id', '=', $request->users);
            }
            $userdetails = $query->get()->sortDesc();

            return view('users.userlog', compact('userdetails', 'filteruser'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }
    public function UserLogView($id)
    {
        $users_log = LoginDetail::find($id);

        return view('users.userlogview', compact('users_log'));
    }

    public function UserLogDestroy($id)
    {
        if (Auth::user()->isAbleTo('user delete')) {
            LoginDetail::where('id', $id)->delete();

            return redirect()->route('users.userlog.history')->with('success', __('User logs successfully deleted.'));
        } else {
            return redirect()->back()->with('error', __('Permission denied.'));
        }
    }

    // Single-clinic app: LoginWithCompany / ExitCompany (impersonation) and
    // CompnayInfo / UserUnable / Counter / BusinessLinks (SaaS subscriber tools) removed.

    public function verifeduser($id)
    {
        $user                    = User::find($id);
        $user->email_verified_at = date('Y-m-d h:i:s');
        $user->save();

        $msg =  __('The user has been verifed successfully.');

        return redirect()->back()->with('success', $msg);
    }
}
