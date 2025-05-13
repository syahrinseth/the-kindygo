<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class RegisterController extends Controller
{
    public function showRegistrationForm(Request $request)
    {
        // Store redirect URL in session if provided
        if ($request->has('redirect')) {
            session(['url.intended' => $request->redirect]);
        }
        
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        // Wrap creation in a transaction
        $data = DB::transaction(function () use ($request) {
            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
            ]);

            // Assign the Admin role to the new user
            $user->assignRole('Admin');

            // Create a personal tenant for the user
            $tenant = Tenant::create([
                'name' => "{$user->name}'s Company",
                'slug' => str($user->name)->slug() . '-company',
                'user_id' => $user->id,
                'personal_tenant' => true,
                'email' => $user->email,
            ]);

            return ['user' => $user, 'tenant' => $tenant];
        });

        $user = $data['user'];
        $tenant = $data['tenant'];

        // Add user to the tenant
        $tenant->addUser($user);

        Auth::login($user);

        // Redirect to the intended URL if it exists, otherwise go to tenant dashboard
        return redirect()->intended(route('filament.app.tenant'))
            ->with('success', 'Registration successful! Welcome to the platform.');
    }
}