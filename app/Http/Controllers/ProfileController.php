<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function showCompleteForm()
    {
        $user = Auth::user();

        // Redirect if profile is already completed
        if ($user->profile_completed) {
            return redirect('/app')->with('info', 'Your profile is already completed.');
        }

        return view('profile.complete', compact('user'));
    }

    public function complete(Request $request)
    {
        $user = Auth::user();

        // Redirect if profile is already completed
        if ($user->profile_completed) {
            return redirect('/app')->with('info', 'Your profile is already completed.');
        }

        $request->validate([
            'phone' => 'required|string|max:20',
            'nric' => 'nullable|string|max:20',
            'passport' => 'nullable|string|max:20',
            'occupation' => 'nullable|string|max:100',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'state_code' => 'required|string|max:10',
            'photo' => 'required|file|image|mimes:jpeg,png,webp|max:5120',
            'mykad' => 'required|file|mimes:jpeg,png,webp,pdf|max:10240',
            'immunization_card' => 'required|file|mimes:jpeg,png,webp,pdf|max:10240',
        ], [
            'phone.required' => 'Phone number is required.',
            'address.required' => 'Address is required.',
            'city.required' => 'City is required.',
            'postal_code.required' => 'Postal code is required.',
            'state_code.required' => 'State is required.',
            'photo.required' => 'Photo is required.',
            'photo.image' => 'Photo must be an image file.',
            'photo.mimes' => 'Photo must be in JPEG, PNG, or WebP format.',
            'photo.max' => 'Photo size must not exceed 5MB.',
            'mykad.required' => 'MyKad document is required.',
            'mykad.mimes' => 'MyKad must be in JPEG, PNG, WebP, or PDF format.',
            'mykad.max' => 'MyKad file size must not exceed 10MB.',
            'immunization_card.required' => 'Immunization card is required.',
            'immunization_card.mimes' => 'Immunization card must be in JPEG, PNG, WebP, or PDF format.',
            'immunization_card.max' => 'Immunization card file size must not exceed 10MB.',
        ]);

        // Validate that either NRIC or Passport is provided
        if (empty($request->nric) && empty($request->passport)) {
            return back()->withErrors([
                'nric' => 'Either NRIC or Passport is required.',
                'passport' => 'Either NRIC or Passport is required.',
            ])->withInput();
        }

        // Update user profile using the new relationship structure
        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'phone' => $request->phone,
                'nric' => $request->nric,
                'passport' => $request->passport,
                'occupation' => $request->occupation,
            ]
        );

        $user->userAddress()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'address' => $request->address,
                'city' => $request->city,
                'postal_code' => $request->postal_code,
                'state_code' => $request->state_code,
            ]
        );

        // Handle Spatie Media Library file uploads
        if ($request->hasFile('photo')) {
            $user->clearMediaCollection('photo');
            $user->addMediaFromRequest('photo')->toMediaCollection('photo', 'private');
        }

        if ($request->hasFile('mykad')) {
            $user->clearMediaCollection('mykad');
            $user->addMediaFromRequest('mykad')->toMediaCollection('mykad', 'private');
        }

        if ($request->hasFile('immunization_card')) {
            $user->clearMediaCollection('immunization_card');
            $user->addMediaFromRequest('immunization_card')->toMediaCollection('immunization_card', 'private');
        }

        // Mark profile as completed
        $user->update(['profile_completed' => true]);

        return redirect('/app')->with('success', 'Profile completed successfully! Welcome to the platform.');
    }
}
