<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Prajwol\LaravelSecurityFeatures\Traits\HandlesSecurityFeatures;
use Throwable;

class LaravelSecurityFeatureController extends Controller
{
    use HandlesSecurityFeatures;

    public function __construct()
    {
        $this->middleware('auth:api', ['except' => ['verify','resendOtpForEmail']]);
    }

    public function verify(Request $request)
    {
        $user = $this->verifyCode($request);
        // custom logic here...
    }

    public function verifyEmailOnlyForUser(Request $request, $user_id){
        $userClass = config('security-features.user_model');
        try {
            $selectedUser = $userClass::where('id', $user_id)->with('tenant:id')->firstOrFail();
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'User not found!']);
        }
        
        $user = $this->verifyEmailOnly($request);
        // custom logic here...
    }

    public function changeEmailAndSendOtp(Request $request, int $user_id)
    {
        $rules = [
            'email' => 'required|email',
            'new_email' => ['required', 'email', 'max:191'],
        ];

        $email_changed = false;

        if ($request->new_email !== $request->email) {
            $rules['new_email'][] = 'unique:users,email';
            $email_changed = true;
        }

        $request->validate($rules);

        $userClass = config('security-features.user_model');
        try {
            $selectedUser = $userClass::where('id', $user_id)->with('tenant:id')->firstOrFail();
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'User not found!']);
        }

        // check permission custom logic (add if needed)
        // $authUser = auth()->user();
        // if(!(
        //     $authUser->id === $user_id //update by self
        //     || is_null($authUser->tenant_id)  //update  by main admin
        //     || (
        //         $authUser->tenant->id === $selectedUser->tenant->id
        //         && $authUser->hasPermission('update_user')
        //     )// by school admin
        // ))
        //     return response()->json(['status' => false, 'message' => 'unauthorized action!!']);

        $input['email'] = $request->new_email;
        try {
            $selectedUser->update($input);
            $user_email = $selectedUser->role_name == 'superadmin' ? config('security-features.superadmin_email_to') : $selectedUser->email;
            $res = $this->generateAndSendOtp($selectedUser->id, $user_email, $email_changed);

            return response()->json($res);
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => 'Internal server error!','error'=>$e->getMessage()]);
        }
    }

    public function resendOtpForEmail(Request $request){
        $res = $this->resendOtp($request);
        return response()->json($res);
    }
}
