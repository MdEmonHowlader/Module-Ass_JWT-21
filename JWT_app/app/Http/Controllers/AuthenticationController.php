<?php

// namespace App\Http\Controllers;

// use Illuminate\Http\Request;



namespace App\Http\Controllers;

use App\Helper\JWT_TOKEN;
use App\Mail\OTPMail;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthenticationController extends Controller {
    /**
     * Registration user
     * @param Request $request
     * @return JsonResponse
     */
    public function registration( Request $request ): JsonResponse {
        try {
            $validator = Validator::make( $request->all(), [
                'email' => 'required|email|unique:users,email',
            ], [
                'email.unique' => 'Already Have an account',
            ] );
            if ( $validator->fails() ) {
                return response()->json( ['status' => 'Failed', 'message' => $validator->errors()], 403 );
            }
            User::create(
                array_merge( $request->only( 'first_name', 'last_name', 'email' ), ['password' => Hash::make( $request->password )] )
            );
            return response()->json( ['status' => 'success', 'message' => 'User Registration Successful'], 201 );

        } catch ( \Throwable $th ) {
            // Handle other exceptions
            return response()->json( ['status' => 'failed', 'message' => 'Registration failed'] );
        }
    }

    /**
     * User Login Method
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function userLogin( Request $request ): JsonResponse {
        try {
            $user = User::where( 'email', $request->email )->first();
            if ( !$user ) {
                return response()->json( ['status' => 'Invalid', 'message' => 'Invalid Credentials'], 401 );
            }

            if ( Hash::check( $request->password, $user->password ) ) {
                $token = JWT_TOKEN::create_token( $user->email, $user->id );
                return response()->json( ['status' => 'success', 'message' => 'Login Successful', 'token' => $token], 200 );
            } else {
                return response()->json( ['status' => 'Invalid', 'message' => 'Invalid Credentials'], 401 );
            }

        } catch ( \Throwable $th ) {
            // Handle other exceptions
            return response()->json( ['status' => 'Failed', 'message' => 'Something went wrong'], 500 );
        }
    }

    /**
     * Send Otp
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function sendOtp( Request $request ): JsonResponse {
        try {
            $otp = rand( 100000, 999999 );
            $email = $request->email;

            $user = User::where( 'email', $email )->first();

            if ( !$user ) {
                return response()->json( ['status' => 'Failed', 'message' => 'Unauthorized'], 401 );
            }

            //OTP send
            Mail::to( $email )->send( new OTPMail( $otp ) );
            //update user table otp
            $user->update( ['otp' => $otp] );

            return response()->json( ['status' => 'success', 'message' => "6 digit otp code send this {$email} email. Please check your mail"], 200 );

        } catch ( \Throwable $th ) {
            // Handle other exceptions
            return response()->json( ['status' => 'Failed', 'message' => 'Something went Wrong'], 500 );
        }
    }

    /**
     * Verify Otp
     * @param Request $request
     * @return JsonResponse
     *
     */
    public function verifyOtp( Request $request ): JsonResponse {
        try {
            $otp = $request->otp;
            $email = $request->email;

            $user = User::where( 'email', $email )->where( 'otp', $otp )->first();

            //user find based on email & otp
            if ( !$user ) {
                return response()->json( ['status' => 'Failed', 'message' => 'Please enter a valid otp'], 400 );
            }

            //Otp expired after 3 minutes
            $expirationTime = strtotime( $user->updated_at ) + ( 60 * 3 );
            if ( time() > $expirationTime ) {
                //otp update
                $user->update( ['otp' => 0] );
                return response()->json( ['status' => 'Failed', 'message' => 'Your Otp expired'], 400 );
            }
            //otp update
            $user->update( ['otp' => 0] );
            //create password reset token
            $reset_token = JWT_TOKEN::reset_token( $email );
            return response()->json( ['status' => 'success', 'message' => "Your Otp verify Successfully", 'token' => $reset_token], 200 ); //expired after 5 minutes

        } catch ( \Throwable $th ) {
            // Handle other exceptions
            return response()->json( ['status' => 'Failed', 'message' => 'Unauthorized'], 500 );
        }
    }

    /**
     * Reset Password
     * @param Request $request
     * @return mixed
     *
     */
    public function resetPassword( Request $request ) {
        try {
            //Password validation
            $validator = Validator::make( $request->all(), [
                'password'         => 'required|min:4',
                'confirm_password' => 'required|same:password',
            ] );
            if ( $validator->fails() ) {
                return response()->json( ['status' => 'Failed', 'message' => $validator->errors()], 400 );
            }

            $email = $request->header( 'email' );

            User::where( 'email', $email )->update( ['password' => Hash::make( $request->password )] );
            return response()->json( ['status' => 'success', 'message' => 'Password Update Successfully'], 200 );

        } catch ( \Throwable $th ) {
            // Handle other exceptions
            // return response()->json( ['status' => 'Failed', 'message' => 'Unauthorized'], 500 );
            return response()->json( ['status' => 'Failed', 'message' => $th->getMessage()], 500 );
        }
    }
}

