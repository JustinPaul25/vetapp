<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\PhilippineMobileNumber;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationController extends Controller
{
    /**
     * Show the registration form (Step 1)
     */
    public function create(): Response
    {
        return Inertia::render('auth/RegisterMultiStep');
    }

    /**
     * Handle Step 1: Store name, email, and contact number
     */
    public function storeStep1(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique(User::class),
            ],
            'mobile_number' => ['required', new PhilippineMobileNumber()],
        ]);

        // Store in session for multi-step process
        $request->session()->put('registration.step1', $validated);

        // Create temporary user without password for email verification
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'mobile_number' => $validated['mobile_number'],
            'password' => Hash::make(uniqid('temp_', true)), // Temporary password
        ]);

        // Log in the user for email verification
        Auth::login($user);

        // Send email verification
        event(new Registered($user));
        $user->sendEmailVerificationNotification();

        return redirect()->route('register.verify-email')
            ->with('status', 'verification-code-sent');
    }

    /**
     * Show email verification step (Step 2)
     */
    public function showVerifyEmail(Request $request): Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('register.address');
        }

        return Inertia::render('auth/RegisterVerifyEmail', [
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Verify email using code
     */
    public function verifyCode(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('register.address');
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

        return redirect()->route('register.address')
            ->with('status', 'email-verified');
    }

    /**
     * Resend verification email
     */
    public function resendVerificationEmail(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('register.address');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'verification-code-sent');
    }

    /**
     * Show address step (Step 3) - after email verification
     */
    public function showAddress(Request $request): Response
    {
        if (!$request->user()->hasVerifiedEmail()) {
            return redirect()->route('register.verify-email');
        }

        return Inertia::render('auth/RegisterAddress');
    }

    /**
     * Handle Step 3: Store address information
     */
    public function storeAddress(Request $request)
    {
        $validated = $request->validate([
            'province' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:255'],
            'barangay' => ['required', 'string', 'max:255'],
            'street' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        // Store in session
        $request->session()->put('registration.address', $validated);

        // Update user with address
        $user = $request->user();
        $user->update([
            'province' => $validated['province'],
            'city' => $validated['city'],
            'barangay' => $validated['barangay'],
            'street' => $validated['street'],
            'address' => implode(', ', [
                $validated['street'],
                $validated['barangay'],
                $validated['city'],
                $validated['province']
            ]),
            'lat' => $validated['lat'] ?? null,
            'long' => $validated['lng'] ?? null,
        ]);

        return redirect()->route('register.password');
    }

    /**
     * Show password step (Step 4)
     */
    public function showPassword(Request $request): Response
    {
        if (!$request->user()->hasVerifiedEmail()) {
            return redirect()->route('register.verify-email');
        }

        return Inertia::render('auth/RegisterPassword');
    }

    /**
     * Handle Step 4: Store password
     */
    public function storePassword(Request $request)
    {
        $validated = $request->validate([
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        // Store in session
        $request->session()->put('registration.password', $validated['password']);

        // Update user with password
        $user = $request->user();
        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Assign client role
        $user->assignRole('client');

        return redirect()->route('register.review');
    }

    /**
     * Show review step (Step 5)
     */
    public function showReview(Request $request): Response
    {
        if (!$request->user()->hasVerifiedEmail()) {
            return redirect()->route('register.verify-email');
        }

        $user = $request->user();

        return Inertia::render('auth/RegisterReview', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'mobile_number' => $user->mobile_number,
                'province' => $user->province,
                'city' => $user->city,
                'barangay' => $user->barangay,
                'street' => $user->street,
            ],
        ]);
    }

    /**
     * Handle Step 5: Finalize registration
     */
    public function finalize(Request $request)
    {
        $validated = $request->validate([
            'confirmed' => ['required', 'accepted'],
        ]);

        // Clear registration session data
        $request->session()->forget('registration');

        // User is already created and updated, just redirect to dashboard
        return redirect()->route('dashboard')
            ->with('success', 'Registration completed successfully!');
    }
}