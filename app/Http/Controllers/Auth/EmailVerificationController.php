<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;

class EmailVerificationController extends Controller
{
    /**
     * Verify email using code
     */
    public function verifyCode(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:6', 'regex:/^[0-9]{6}$/'],
        ]);

        $user = $request->user();
        $cacheKey = "verification_code_{$user->getKey()}";
        $storedCode = Cache::get($cacheKey);

        if (!$storedCode || $storedCode !== $validated['code']) {
            return back()->withErrors([
                'code' => 'Invalid or expired verification code. Please request a new code.',
            ]);
        }

        // Verify the email
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new \Illuminate\Auth\Events\Verified($user));
        }

        // Clear the verification code from cache
        Cache::forget($cacheKey);

        return redirect()->route('dashboard')
            ->with('status', 'email-verified');
    }

    /**
     * Resend verification email
     */
    public function resendVerificationCode(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-code-sent');
    }
}
