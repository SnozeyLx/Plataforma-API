<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ForgotPasswordController extends Controller
{
    public function send(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        // Do NOT expose whether the email exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'If the email is registered, you will receive a password reset link.'
            ]);
        }

        $status = Password::sendResetLink(['email' => $request->email]);

        return $status === Password::RESET_LINK_SENT
            ? response()->json([
                'success' => true,
                'message' => 'If the email is registered, you will receive a password reset link.'
            ])
            : response()->json([
                'success' => false,
                'message' => 'Unable to send password reset link.'
            ], 400);
    }
}
