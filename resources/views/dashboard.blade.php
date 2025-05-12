<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Dashboard - {{ config('app.name', 'KindyGo') }}</title>

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex">
                    <div class="flex-shrink-0 flex items-center">
                        <span class="text-2xl font-bold text-blue-600">KindyGo</span>
                    </div>
                </div>

                <div class="flex items-center">
                    <div class="flex items-center">
                        <span class="text-gray-700 mr-4">{{ Auth::user()->name }}</span>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="text-sm text-gray-600 hover:text-gray-900 bg-gray-100 px-3 py-2 rounded-md">
                                Sign Out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto py-6 sm:px-6 lg:px-8">
        <!-- Welcome Card -->
        <div class="bg-white overflow-hidden shadow-sm rounded-lg">
            <div class="p-6">
                <h1 class="text-2xl font-semibold text-gray-800">
                    Welcome back, {{ Auth::user()->name }}!
                </h1>
                <p class="mt-2 text-gray-600">
                    This is your dashboard where you can manage your activities.
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="mt-6 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
            <!-- Action Card 1 -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Your Profile</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Update your personal information and preferences
                    </p>
                </div>
            </div>

            <!-- Action Card 2 -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Your Children</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        Manage your children's information and activities
                    </p>
                </div>
            </div>

            <!-- Action Card 3 -->
            <div class="bg-white overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900">Activity Calendar</h3>
                    <p class="mt-2 text-sm text-gray-600">
                        View and manage upcoming activities
                    </p>
                </div>
            </div>
        </div>
    </main>
</body>
</html>