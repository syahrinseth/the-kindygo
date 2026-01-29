<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verify Email - {{ config('app.name', 'KindyGo') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased">
    <div class="min-h-screen bg-gray-50">
        {{-- Brand Header --}}
        <div class="bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-4xl mx-auto px-6 py-4">
                <div class="flex items-center space-x-3">
                    <div class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 shadow-md">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                    </div>
                    <div>
                        <h1 class="text-lg font-bold text-gray-900">{{ config('app.name', 'KindyGo') }}</h1>
                        <p class="text-xs text-gray-500">Email Verification</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Main Content --}}
        <div class="max-w-2xl mx-auto px-6 py-12">
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8">
                {{-- Email Icon --}}
                <div class="flex justify-center mb-6">
                    <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-100">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                </div>

                {{-- Heading --}}
                <div class="text-center mb-6">
                    <h2 class="text-2xl font-bold text-gray-900 mb-2">Verify Your Email Address</h2>
                    <p class="text-gray-600">We've sent a verification link to</p>
                    <p class="text-blue-600 font-semibold mt-1">{{ auth()->user()->email }}</p>
                </div>

                {{-- Success Message --}}
                @if (session('status') === 'verification-link-sent')
                    <div class="rounded-lg bg-green-50 border border-green-200 p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-green-800">A new verification link has been sent to your email address!</p>
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Instructions --}}
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 mb-1">Next Steps:</h3>
                            <ol class="text-sm text-blue-700 list-decimal list-inside space-y-1">
                                <li>Check your email inbox for the verification link</li>
                                <li>Click the link to verify your email address</li>
                                <li>Return here to continue your registration</li>
                            </ol>
                            <p class="text-xs text-blue-600 mt-3">
                                <strong>Note:</strong> Don't forget to check your spam folder if you don't see the email.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Action Buttons --}}
                <div class="space-y-3">
                    {{-- Resend Verification Email --}}
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg transition-colors shadow-sm flex items-center justify-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                            </svg>
                            Resend Verification Email
                        </button>
                    </form>

                    {{-- Edit Email (Go Back to Step 1) --}}
                    @if(auth()->user()->current_tenant_id)
                        @php
                            $tenant = auth()->user()->currentTenant();
                        @endphp
                        @if($tenant)
                            <a href="{{ route('tenant.register.form', ['tenant' => $tenant->slug]) }}" class="w-full bg-white hover:bg-gray-50 text-gray-700 font-semibold py-3 px-4 rounded-lg border-2 border-gray-300 transition-colors flex items-center justify-center gap-2">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                                Edit Email Address
                            </a>
                        @endif
                    @endif

                    {{-- Logout --}}
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full text-gray-500 hover:text-gray-700 font-medium py-2 px-4 rounded-lg hover:bg-gray-100 transition-colors text-sm">
                            Logout
                        </button>
                    </form>
                </div>
            </div>

            {{-- Help Text --}}
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-500">
                    Having trouble? Contact support at 
                    <a href="mailto:support@kindygo.com" class="text-blue-600 hover:text-blue-700 font-medium">support@kindygo.com</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
