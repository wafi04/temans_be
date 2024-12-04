<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

class AuthController extends Controller
{
    /**
     * Handle an incoming registration request.
     */
    public function register(Request $request)
    {
         try {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', 'string', Rules\Password::defaults()],
        ]);

        // Tentukan role berdasarkan email domain
        $role = str_ends_with($request->email, '@admin.com') ? 'admin' : 'user';

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $role 
        ]);

        event(new Registered($user));

        Auth::login($user);
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Registration successful',
            'data' => [
                'user' => $user,
                'token' => $token,
                'role' => $role 
            ]
        ], 201);

    } catch (ValidationException $e) {
        return response()->json([
            'status' => false,
            'message' => 'Validation failed',
            'errors' => $e->errors()
        ], 422);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Registration failed',
            'error' => $e->getMessage()
        ], 500);
    }
    }

    /**
     * Handle an incoming authentication request.
     */
 public function login(Request $request)
{
    try {
        // Validasi input
        $validator = Validator::make($request->all(), [
            'email' => [
                'required', 
                'string', 
                'email',
                'exists:users,email'  // Pastikan email ada di database
            ],
            'password' => [
                'required', 
                'string', 
                'min:6'  // Contoh panjang minimal password
            ]
        ], [
            // Custom error messages
            'email.required' => 'Email tidak boleh kosong.',
            'email.email' => 'Format email tidak valid.',
            'email.exists' => 'Email tidak terdaftar.',
            'password.required' => 'Password tidak boleh kosong.',
            'password.min' => 'Password minimal 6 karakter.'
        ]);

        // Jika validasi gagal
        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validasi gagal',
                'errors' => $validator->errors()
            ], 422);
        }

        // Coba autentikasi
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'status' => false,
                'message' => 'Login gagal',
                'errors' => [
                    'email' => ['Email atau password salah']
                ]
            ], 401);
        }

        $user = $request->user();
        $token = $user->createToken('auth-token')->plainTextToken;

        $response = response()->json([
            'status' => true,
            'message' => 'Login berhasil',
            'data' => [
                'user' => $user,
                'token' => $token
            ]
        ]);

        // Tambahkan token ke cookie yang aman
        $response->cookie(
            'token',
            $token,
            60 * 24 * 30,    // 30 hari
            '/',
            null,
            true,   // secure (HTTPS only)
            true,   // httpOnly
            false,
            'Strict'
        );

        return $response;

    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Terjadi kesalahan',
            'error' => $e->getMessage()
        ], 500);
    }
}

    /**
     * Destroy an authenticated session.
     */
    public function logout(Request $request)
    {
        try {
            // Revoke the token that was used to authenticate the current request
            $request->user()->currentAccessToken()->delete();
            
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return response()->json([
                'status' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Logout failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get the authenticated user.
     */

public function getUser(Request $request)
{
    try {
        // Dengan Sanctum, kita tidak perlu mengecek token manual
        // Karena middleware 'auth:sanctum' sudah menangani itu
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        return response()->json([
            'status' => true,
            'data' => $user
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'status' => false,
            'message' => 'Failed to get user data',
            'error' => $e->getMessage()
        ], 500);
    }
}


}