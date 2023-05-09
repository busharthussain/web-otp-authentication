<?php

namespace bushart\otploginauthentication;
use Carbon\Carbon;
use bushart\otploginauthentication\Mail\OtpMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;

trait OtpAuthentication
{
    use AuthenticatesUsers;
    // Return View of OTP Login Page
    public function login(Request $request)
    {
        if ($request->method() == 'POST'){
            $this->validateLogin($request);
            $user = User::where('email', $request->input('email'))->first();
            $errorMessage = 'These credentials do not match our records.';
            $credentials = $this->getLoginCredentials();
            $messageType = 'email';
            if (!Auth::attempt($credentials)) {
                RateLimiter::hit($this->throttleKey($request), $seconds = 60);
                $leftAttemtps = $this->limiter()->retriesLeft($this->throttleKey($request), 6);
                if ($leftAttemtps <= 0) {
                    $leftAttemtps = 0;
                }
                if (empty($leftAttemtps) && $leftAttemtps == 0) {
                    $this->incrementLoginAttempts($request);
                    $errorMessage = 'Your account has been locked.';
                    $messageType = 'message';
                }

                return redirect()->to('login')->with($messageType, $errorMessage);
            }
        }



        return view('auth.otp-login');
    }

    /**
     * Get the rate limiting throttle key for the request.
     *
     * @return string
     */
    public function throttleKey($request)
    {
        return Str::lower($request->input('username'));
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @return void
     * @throws Exception
     */
    public function checkTooManyFailedAttempts($request)
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        $this->errorMessage = "You have exceeded the maximum number of login attempts.";

        return $this->errorMessage;
    }

    // Generate OTP
    public function generate(Request $request)
    {
        $email = false;
        $value = $request->value;
        $message = 'Email or Phone does not exist';
        if (filter_var($request->value, FILTER_VALIDATE_EMAIL)) {
            $email = true;
        }
        # Validate Data
        $userId = '';
        $user = $email ? User::where('email', $value)->first() : User::where('mobile_no', $value)->first();
        if (empty($user)){
            return redirect()->back()->with('error',  $message);
        }

        # Generate An OTP
        $verificationCode = $this->generateOtp($value,$email,$user);
        $otp = $verificationCode['otp'];
        if ($email){
            Mail::to('busharthussain163@gmail.com')->send(new OtpMail($otp));
        }else{
            $responce = $this->TwilioOtp($value,$otp);
            $message = !empty($responce) ? $responce['message'] : '';
            if (!empty($responce) && $responce['success'] == false){
                return redirect()->back()->with('error',  $message);
            }
        }
        $message = "sent Your OTP";
        $userId = $verificationCode['user_id'];

        # Return With OTP

        return redirect()->route('otp.verification', ['user_id' => $userId])->with('success',  $message);
    }

    public function generateOtp($value,$email,$user)
    {
        # User Does not Have Any Existing OTP

        $now = Carbon::now();

        // Create a New OTP
        $otp = rand(123456, 999999);
        $user->expire_at = Carbon::now()->addMinutes(10);
        $user->otp = $otp;
        $user->save();

        $verificationCode = [
            'user_id' => $user->id,
            'otp' => $otp,
            'expire_at' => $user->expire_at
        ];

        return $verificationCode;
    }

    public function verification($user_id)
    {
        return view('auth.otp-verification')->with([
            'user_id' => $user_id
        ]);
    }

    public function loginWithOtp(Request $request)
    {
        #Validation
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp' => 'required'
        ]);

        #Validation Logic
        $verificationCode   = User::where('id', $request->user_id)->where('otp', $request->otp)->first();

        $now = Carbon::now();
        if (!$verificationCode) {
            return redirect()->back()->with('error', 'Your OTP is not correct');
        }elseif($verificationCode && $now->isAfter($verificationCode->expire_at)){
            return redirect()->route('otp')->with('error', 'Your OTP has been expired');
        }

        $user = User::whereId($request->user_id)->first();

        if($user){
            // Expire The OTP
            $verificationCode->update([
                'expire_at' => Carbon::now()
            ]);

            Auth::login($user);

            return redirect('/home');
        }

        return redirect()->route('otp')->with('message', 'Your Otp is not correct');
    }
    /**
     * Get the login username to be used by the controller.
     *
     * @return string
     */
    public function username()
    {
        return 'email';
    }

    /**
     * This is use to send otp on mobile number
     *
     * @param $toNumber
     * @param $otp
     * @return string
     */
    function TwilioOtp($toNumber,$otp)
    {
        $success = false;
        $responce = [];
        try {
            $accountSid = getenv('TWILIO_ACCOUNT_SID');
            $authToken = getenv('TWILIO_AUTH_TOKEN');
            $fromNumber = getenv('TWILIO_NUMBER');
            $message = 'Use the following OTP to complete your Login procedures. OTP is valid for 10 minutes Otp: ' . $otp;

            $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $accountSid . '/Messages.json';

            $postFields = array(
                'From' => $fromNumber,
                'To' => $toNumber,
                'Body' => $message,
            );

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postFields));
            curl_setopt($ch, CURLOPT_USERPWD, $accountSid . ':' . $authToken);

            $result = curl_exec($ch);

            curl_close($ch);

            // Handle response
            if (!empty($result)) {
                $response = json_decode($result, true);
                if (!empty($response['sid'])) {
                    $success = true;
                    $message = 'SMS sent successfully';
                } else {
                    $message = 'Error sending SMS: ' . $result;
                }
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }
        $responce['success'] = $success;
        $responce['message'] = $message;

        return $responce;
    }

    /**
     * Validate the user login request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return void
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function getLoginCredentials()
    {
        return [
            "email" => request("email"),
            "password" => request("password"),
        ];
    }
}