<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function adminLogin(Request $request): JsonResponse
    {
        try {
            $credentials = $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);

            if (Auth::guard('admin')->attempt($credentials)) {
                $admin = Auth::guard('admin')->user();

                // Generate a simple token (for development/testing)
                $token = base64_encode($admin->id . ':' . $admin->email . ':' . time());

                return response()->json([
                    'success' => true,
                    'message' => 'Login successful',
                    'data' => [
                        'admin_id' => $admin->id,
                        'name' => $admin->name,
                        'email' => $admin->email,
                        'role' => $admin->role,
                        'token' => $token,
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials',
            ], 401);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
