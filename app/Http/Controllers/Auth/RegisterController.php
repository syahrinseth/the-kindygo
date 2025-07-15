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
    
    public function showTenantRegistrationForm($tenantSlug)
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();
        // Use withoutGlobalScope to bypass TenantScope since user is not authenticated yet
        $centres = $tenant->centres()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->get();
        
        return view('auth.tenant-register', compact('tenant', 'centres'));
    }

    public function registerToTenant(Request $request, $tenantSlug)
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'centre_ids' => 'required|array|min:1',
            'centre_ids.*' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($tenant) {
                    if (!$tenant->centres()->withoutGlobalScope(\App\Models\Scopes\TenantScope::class)
                    ->where('id', $value)->exists()) {
                        $fail('The selected centre does not belong to this tenant.');
                    }
                },
            ],
        ]);

        // Wrap creation in a transaction
        $user = DB::transaction(function () use ($request, $tenant) {
            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'phone' => $request->phone,
            ]);

            // Check if Parent role exists, if not create it
            $parentRole = Role::firstOrCreate(['name' => 'Parent']);
            
            // Assign the Parent role to the new user
            $user->assignRole($parentRole);

            // Attach user to tenant as Parent
            $tenant->addUser($user);

            // Attach user to selected centres
            $user->centres()->attach($request->centre_ids);

            return $user;
        });

        // Log the user in
        Auth::login($user);

        // Redirect Parent users to profile completion form
        return redirect()->route('profile.complete')
            ->with('success', 'Registration successful! Please complete your profile to continue.');
    }
}