<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }} - Join Childcare Center</title>

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center">
            <h1 class="text-3xl font-extrabold text-gray-900 sm:text-4xl">
                Join Your Childcare Center
            </h1>
            <p class="mt-4 text-lg text-gray-600">
                Select your childcare center to register as a parent
            </p>
        </div>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($tenants as $tenant)
                <div class="bg-white overflow-hidden shadow rounded-lg hover:shadow-lg transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="h-12 w-12 bg-blue-600 rounded-lg flex items-center justify-center">
                                <span class="text-white font-bold text-xl">{{ substr($tenant->name, 0, 1) }}</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-medium text-gray-900">{{ $tenant->name }}</h3>
                                @if($tenant->address_1)
                                    <p class="text-sm text-gray-500">{{ $tenant->address_1 }}</p>
                                @endif
                            </div>
                        </div>
                        
                        @if($tenant->email)
                            <p class="mt-4 text-sm text-gray-600">{{ $tenant->email }}</p>
                        @endif
                        
                        <div class="mt-6">
                            <a href="{{ route('tenant.register.form', $tenant->slug) }}" 
                               class="w-full inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                                Register as Parent
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12">
                    <p class="text-gray-500">No childcare centers are currently available for registration.</p>
                    <div class="mt-4">
                        <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-500">
                            Return to Login
                        </a>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</body>
</html>
