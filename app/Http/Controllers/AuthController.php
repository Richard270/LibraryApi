<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Models\User;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed'
        ]);
        if ($validator->fails()) return $this->getResponse500([$validator->errors()]);
        DB::beginTransaction();
        try {
            $user = new User();
            $user->name = $request->name;
            $user->email = $request->email;
            $user->password = Hash::make($request->password);
            $user->save();
            DB::commit();
            return $this->getResponse201('user account', 'created', $user);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }  
    }

    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);
        if ($validator->fails()) return $this->getResponse500([$validator->errors()]);
        $user = User::where('email', '=', $request->email)->first();
        if (!isset($user->id)) return $this->getResponse401();
        if (!Hash::check($request->password, $user->password)) return $this->getResponse401();
        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'message' => "Successful authentication",
            'access_token' => $token,
        ], 200);
    }

    public function userProfile()
    {
        return $this->getResponse200(auth()->user());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete(); //Revoke current token
        return response()->json([
            'message' => "Logout successful"
        ], 200);
    }

    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|confirmed'
        ]);
        if ($validator->fails()) return $this->getResponse500([$validator->errors()]);
        DB::beginTransaction();
        try {
            $user = $request->user();
            $user->tokens()->delete();
            $user->password = Hash::make($request->password);
            $user->save();
            DB::commit();
            return response()->json([
                'message' => "Your password has been successfully updated!"
            ], 200);
        } catch (Exception $e) {
            DB::rollBack();
            return $this->getResponse500([$e->getMessage()]);
        }  
    }
}
