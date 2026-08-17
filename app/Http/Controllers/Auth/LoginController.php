<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm(Request $request)
    {
        // Store redirect URL in session if provided
        if ($request->has('redirect')) {
            session(['url.intended' => $request->redirect]);
        }

        return view('auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // After successful login, ensure user's current tenant is set
            $user = Auth::user();
            if ($user && ! $user->current_tenant_id) {
                try {
                    $panel = \Filament\Facades\Filament::getPanel();
                    if (method_exists($user, 'getDefaultTenant')) {
                        $tenant = $user->getDefaultTenant($panel);
                    }
                } catch (\Throwable $e) {
                    $tenant = null;
                }

                if (empty($tenant)) {
                    $tenant = $user->tenants()->first();
                }

                if ($tenant) {
                    $user->update(['current_tenant_id' => $tenant->id]);
                    $user->setCurrentTenant($tenant);
                }
            }

            // Redirect to the intended URL if it exists, otherwise redirect based on role
            if (session()->has('url.intended')) {
                $redirectUrl = session('url.intended');
                session()->forget('url.intended');

                return redirect()->to($redirectUrl);
            }

            // Redirect based on user role: admin roles go to /admin, parents go to /parent/dashboard
            if ($user->isAdmin()) {
                return redirect('/admin');
            }

            return redirect()->route('filament.parent.pages.dashboard');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Log the user out
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
