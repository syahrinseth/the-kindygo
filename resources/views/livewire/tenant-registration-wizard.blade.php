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
                    <p class="text-xs text-gray-500">Parent Registration</p>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto py-8">
    {{-- Progress Bar --}}
    <div class="bg-white rounded-lg shadow-sm p-6 mx-6">
        <div class="flex items-center justify-between mb-2">
                @foreach([1, 2, 3, 4] as $step)
                    <div class="flex items-center {{ $step < 4 ? 'flex-1' : '' }}">
                        <div class="flex flex-col items-center">
                            <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 transition-colors
                                {{ $currentStep > $step ? 'bg-blue-600 border-blue-600 text-white' : '' }}
                                {{ $currentStep === $step ? 'border-blue-600 text-blue-600 bg-white' : '' }}
                                {{ $currentStep < $step ? 'border-gray-300 text-gray-300 bg-white' : '' }}">
                                @if($currentStep > $step)
                                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                @else
                                    <span class="text-sm font-semibold">{{ $step }}</span>
                                @endif
                            </div>
                            <span class="text-xs mt-2 text-center font-medium {{ $currentStep >= $step ? 'text-blue-600' : 'text-gray-400' }}">
                                @if($step === 1) Basic Info
                                @elseif($step === 2) Details
                                @elseif($step === 3) Children
                                @else Agreement
                                @endif
                            </span>
                        </div>
                        @if($step < 4)
                            <div class="flex-1 h-0.5 mx-2 transition-colors {{ $currentStep > $step ? 'bg-blue-600' : 'bg-gray-300' }}"></div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Form Content --}}
    <div class="max-w-4xl mx-auto px-6 pb-12">
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-8">
            @if($currentStep === 1)
                {{-- Step 1: Basic Information --}}
                <div class="space-y-6">
                    @if($errors->any())
                        <div class="rounded-lg bg-red-50 border border-red-200 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc pl-5 space-y-1">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="border-b border-gray-200 pb-4">
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Basic Information</h3>
                        <p class="text-sm text-gray-600">Please provide your basic information to get started.</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            Full Name
                        </label>
                        <input type="text" wire:model="name" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Enter your full name">
                        @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                            </svg>
                            MyKad Number
                        </label>
                        <input type="text" wire:model="mykad_number" maxlength="12" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., 900101011234">
                        <p class="mt-1.5 text-xs text-gray-500 flex items-center">
                            <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            Enter your 12-digit MyKad number without dashes
                        </p>
                        @error('mykad_number') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                            </svg>
                            Phone Number
                        </label>
                        <input type="tel" wire:model="phone" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., +60123456789">
                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Email Address
                        </label>
                        <input type="email" wire:model="email" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="your.email@example.com">
                        @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    @if(!Auth::check())
                        <div class="pt-4 border-t border-gray-100">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                Password
                            </label>
                            <input type="password" wire:model="password" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Enter a secure password">
                            <p class="mt-1.5 text-xs text-gray-500 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                Must be at least 8 characters
                            </p>
                            @error('password') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                Confirm Password
                            </label>
                            <input type="password" wire:model="password_confirmation" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Re-enter your password">
                            @error('password_confirmation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    @endif

                    <div class="pt-4 border-t border-gray-100">
                        <label class="block text-sm font-semibold text-gray-700 mb-3">
                            <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            Select Centre(s)
                        </label>
                        <div class="space-y-2 p-4 bg-gradient-to-br from-gray-50 to-blue-50 rounded-xl border border-gray-200 max-h-60 overflow-y-auto">
                            @php
                                $centres = \App\Models\Centre::withoutGlobalScope(\App\Models\Scopes\TenantScope::class)->where('tenant_id', $tenant->id)->get();
                            @endphp

                            @forelse($centres as $centre)
                                <label class="flex items-start p-4 rounded-lg bg-white hover:bg-blue-50 border-2 border-transparent hover:border-blue-300 transition-all cursor-pointer group shadow-sm">
                                    <input
                                        type="checkbox"
                                        wire:model="centre_ids"
                                        value="{{ $centre->id }}"
                                        class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1 flex-shrink-0 w-5 h-5"
                                    >
                                    <div class="ml-3 flex-1">
                                        <span class="font-semibold text-gray-900 group-hover:text-blue-700 transition-colors">{{ $centre->name }}</span>
                                        @if($centre->address)
                                            <p class="text-xs text-gray-500 mt-1 flex items-start">
                                                <svg class="w-3.5 h-3.5 mr-1 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path>
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                                {{ $centre->address }}
                                            </p>
                                        @endif
                                    </div>
                                </label>
                            @empty
                                <div class="text-center py-8 text-gray-500 bg-white rounded-lg">
                                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <p class="mt-2 text-sm font-medium">No centres available</p>
                                </div>
                            @endforelse
                        </div>
                        <p class="mt-2 text-xs text-gray-600 flex items-start bg-blue-50 p-3 rounded-lg border border-blue-100">
                            <svg class="w-4 h-4 mr-2 mt-0.5 flex-shrink-0 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                            <span class="text-blue-800"><strong>Important:</strong> Select one or more centres you'd like to register with. This selection cannot be changed later.</span>
                        </p>
                        @error('centre_ids') <p class="mt-2 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>
                </div>

            @elseif($currentStep === 2)
                {{-- Step 2: Parent Details --}}
                <div class="space-y-6">
                    @if($errors->any())
                        <div class="rounded-md bg-red-50 border border-red-200 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc pl-5 space-y-1">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(!empty($centre_ids))
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 mb-6">
                            <div class="flex items-start space-x-3">
                                <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                                </svg>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-700 mb-1">Selected Centres (Locked)</p>
                                    <div class="flex flex-wrap gap-2">
                                        @foreach(\App\Models\Centre::whereIn('id', $centre_ids)->get() as $centre)
                                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                {{ $centre->name }}
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="border-b border-gray-200 pb-4">
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Personal Details</h3>
                        <p class="text-sm text-gray-600">Tell us more about yourself</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            Occupation
                        </label>
                        <input type="text" wire:model="occupation" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., Teacher, Engineer, Business Owner">
                        @error('occupation') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="p-4 bg-blue-50 border border-blue-100 rounded-xl space-y-4">
                        <div class="flex items-start space-x-2 mb-3">
                            <svg class="w-5 h-5 text-blue-600 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <div>
                                <h4 class="text-sm font-semibold text-blue-900">Document Uploads</h4>
                                <p class="text-xs text-blue-700 mt-0.5">All uploads are optional and can be completed later</p>
                            </div>
                        </div>

                        <div class="bg-white p-4 rounded-lg">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                                Profile Photo
                            </label>
                            <input type="file" wire:model="profile_photo" accept="image/*" class="w-full text-sm text-gray-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-blue-600 file:to-blue-700 file:text-white hover:file:from-blue-700 hover:file:to-blue-800 file:cursor-pointer file:transition-all">
                            <p class="mt-1.5 text-xs text-gray-500 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                Maximum file size: 5MB
                            </p>
                            @error('profile_photo') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="bg-white p-4 rounded-lg">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                                MyKad Image
                            </label>
                            <input type="file" wire:model="mykad_image" accept="image/*,.pdf" class="w-full text-sm text-gray-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-blue-600 file:to-blue-700 file:text-white hover:file:from-blue-700 hover:file:to-blue-800 file:cursor-pointer file:transition-all">
                            <p class="mt-1.5 text-xs text-gray-500 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                Formats: JPG, PNG, PDF • Max: 10MB
                            </p>
                            @error('mykad_image') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="bg-white p-4 rounded-lg">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                                </svg>
                                Child Immunization Card
                            </label>
                            <input type="file" wire:model="immunization_card" accept="image/*,.pdf" class="w-full text-sm text-gray-600 file:mr-4 file:py-2.5 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-gradient-to-r file:from-blue-600 file:to-blue-700 file:text-white hover:file:from-blue-700 hover:file:to-blue-800 file:cursor-pointer file:transition-all">
                            <p class="mt-1.5 text-xs text-gray-500 flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                Formats: JPG, PNG, PDF • Max: 10MB
                            </p>
                            @error('immunization_card') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200">
                        <h3 class="text-xl font-bold text-gray-900 mb-1">Address Information</h3>
                        <p class="text-sm text-gray-600 mb-6">Your residential address details</p>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <svg class="inline-block w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                            </svg>
                            Address
                        </label>
                        <input type="text" wire:model="address" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Street address, apartment, suite, etc.">
                        @error('address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Postal Code</label>
                            <input type="text" wire:model="postal_code" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., 50000">
                            @error('postal_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">City</label>
                            <input type="text" wire:model="city" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., Kuala Lumpur">
                            @error('city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">State</label>
                            <select wire:model="state" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors">
                                <option value="">Select State</option>
                                @foreach(\App\Enums\MalaysianState::options() as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                @endforeach
                            </select>
                            @error('state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200">
                        <div class="flex items-start space-x-2 mb-6">
                            <svg class="w-5 h-5 text-gray-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Office Information</h3>
                                <p class="text-sm text-gray-600 mt-1">Optional - Provide your workplace details if applicable</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Office Name</label>
                            <input type="text" wire:model="office_name" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Company or organization name">
                            @error('office_name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Office Address</label>
                            <input type="text" wire:model="office_address" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Office street address">
                            @error('office_address') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Office Postal Code</label>
                                <input type="text" wire:model="office_postal_code" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., 50000">
                                @error('office_postal_code') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Office City</label>
                                <input type="text" wire:model="office_city" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., Petaling Jaya">
                                @error('office_city') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>

                            <div>
                                <label class="block text-sm font-semibold text-gray-700 mb-2">Office State</label>
                                <select wire:model="office_state" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors">
                                    <option value="">Select State</option>
                                    @foreach(\App\Enums\MalaysianState::options() as $code => $name)
                                        <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                @error('office_state') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="pt-6 border-t border-gray-200">
                        <div class="p-4 bg-blue-50 border border-blue-200 rounded-xl">
                            <label class="flex items-start cursor-pointer group">
                                <input type="checkbox" wire:model="information_confirmed" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1 w-5 h-5 cursor-pointer">
                                <span class="ml-3 text-sm font-medium text-gray-800 group-hover:text-blue-700 transition-colors">I confirm that all information provided is accurate and complete</span>
                            </label>
                            @error('information_confirmed') <p class="mt-2 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>
                </div>

            @elseif($currentStep === 3)
                {{-- Step 3: Child Information --}}
                <div class="space-y-6">
                    @if($errors->any())
                        <div class="rounded-md bg-red-50 border border-red-200 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc pl-5 space-y-1">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="border-b border-gray-200 pb-4">
                        <div class="flex items-start space-x-2">
                            <svg class="w-6 h-6 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Child Information</h3>
                                <p class="text-sm text-gray-600 mt-1">Add your children's details. This step is optional and can be completed later.</p>
                            </div>
                        </div>
                    </div>

                    <div id="children-container" class="space-y-4">
                        @foreach($children as $index => $child)
                            <div class="p-6 border-2 border-gray-200 rounded-xl bg-gradient-to-br from-white to-gray-50 hover:border-blue-300 transition-all">
                                <div class="flex justify-between items-center mb-5">
                                    <div class="flex items-center space-x-2">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm shadow-md">
                                            {{ $index + 1 }}
                                        </div>
                                        <h4 class="font-bold text-gray-900">Child {{ $index + 1 }}</h4>
                                    </div>
                                    <button type="button" wire:click="$set('children.{{ $index }}', null)" class="inline-flex items-center px-3 py-1.5 text-sm font-semibold text-red-600 hover:text-white hover:bg-red-600 border-2 border-red-600 rounded-lg transition-all">
                                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                        Remove
                                    </button>
                                </div>

                                {{-- Basic Information --}}
                                <div class="space-y-4">
                                    <div class="flex items-center space-x-2 pb-2 border-b border-gray-200">
                                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                        </svg>
                                        <h5 class="text-sm font-bold text-gray-800">Basic Information</h5>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                First Name <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" wire:model="children.{{ $index }}.first_name" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., Muhammad">
                                            @error("children.{$index}.first_name") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Middle Name</label>
                                            <input type="text" wire:model="children.{{ $index }}.patronymic" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="Optional">
                                            @error("children.{$index}.patronymic") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                Last Name <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" wire:model="children.{{ $index }}.last_name" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., Abdullah">
                                            @error("children.{$index}.last_name") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                Gender <span class="text-red-500">*</span>
                                            </label>
                                            <select wire:model="children.{{ $index }}.gender" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors">
                                                <option value="">Select Gender</option>
                                                <option value="male">Male</option>
                                                <option value="female">Female</option>
                                            </select>
                                            @error("children.{$index}.gender") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                Date of Birth <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" wire:model="children.{{ $index }}.date_of_birth" max="{{ now()->format('Y-m-d') }}" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors">
                                            @error("children.{$index}.date_of_birth") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Place of Birth</label>
                                            <input type="text" wire:model="children.{{ $index }}.place_of_birth" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., Kuala Lumpur">
                                            @error("children.{$index}.place_of_birth") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Race</label>
                                            <select wire:model="children.{{ $index }}.race" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors">
                                                <option value="">Select Race</option>
                                                <option value="Malay">Malay</option>
                                                <option value="Chinese">Chinese</option>
                                                <option value="Indian">Indian</option>
                                                <option value="Bumiputera Sabah">Bumiputera Sabah</option>
                                                <option value="Bumiputera Sarawak">Bumiputera Sarawak</option>
                                                <option value="Orang Asli">Orang Asli</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            @error("children.{$index}.race") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Religion</label>
                                            <select wire:model="children.{{ $index }}.religion" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors">
                                                <option value="">Select Religion</option>
                                                <option value="Islam">Islam</option>
                                                <option value="Christianity">Christianity</option>
                                                <option value="Buddhism">Buddhism</option>
                                                <option value="Hinduism">Hinduism</option>
                                                <option value="Sikhism">Sikhism</option>
                                                <option value="Taoism">Taoism</option>
                                                <option value="Other">Other</option>
                                                <option value="No Religion">No Religion</option>
                                            </select>
                                            @error("children.{$index}.religion") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Position in Family</label>
                                            <input type="number" wire:model="children.{{ $index }}.position_of_child" min="1" max="20" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="1">
                                            @error("children.{$index}.position_of_child") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                </div>

                                {{-- Identification --}}
                                <div class="space-y-4 mt-6 pt-6 border-t border-gray-200">
                                    <div class="flex items-center space-x-2 pb-2 border-b border-gray-200">
                                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                        </svg>
                                        <h5 class="text-sm font-bold text-gray-800">Identification</h5>
                                    </div>

                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">
                                                MyKid Number <span class="text-red-500">*</span>
                                            </label>
                                            <input type="text" wire:model="children.{{ $index }}.mykid_no" maxlength="12" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., 150101010001">
                                            <p class="mt-1.5 text-xs text-gray-500">12-digit MyKid identification number</p>
                                            @error("children.{$index}.mykid_no") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                        <div>
                                            <label class="block text-sm font-semibold text-gray-700 mb-2">Birth Certificate Number</label>
                                            <input type="text" wire:model="children.{{ $index }}.cert_number" class="w-full px-4 py-3 rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500 transition-colors" placeholder="e.g., A12345678">
                                            <p class="mt-1.5 text-xs text-gray-500">Birth certificate registration number</p>
                                            @error("children.{$index}.cert_number") <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <button type="button" wire:click="addChild" class="w-full inline-flex items-center justify-center px-6 py-4 border-2 border-dashed border-gray-300 rounded-xl text-sm font-semibold text-gray-600 bg-white hover:bg-blue-50 hover:border-blue-400 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all group">
                        <svg class="w-5 h-5 mr-2 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Add Another Child
                    </button>
                </div>

            @elseif($currentStep === 4)
                {{-- Step 4: Agreement --}}
                <div class="space-y-6">
                    @if($errors->any())
                        <div class="rounded-md bg-red-50 border border-red-200 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc pl-5 space-y-1">
                                            @foreach($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="border-b border-gray-200 pb-4">
                        <div class="flex items-start space-x-2">
                            <svg class="w-6 h-6 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <h3 class="text-xl font-bold text-gray-900">Terms and Conditions</h3>
                                <p class="text-sm text-gray-600 mt-1">Please review and accept the following to complete your registration</p>
                            </div>
                        </div>
                    </div>

                    <div class="p-6 border-2 border-gray-200 rounded-xl bg-gradient-to-br from-white to-blue-50 hover:border-blue-300 transition-all">
                        <label class="flex items-start cursor-pointer group">
                            <input type="checkbox" wire:model="tnc_accepted" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500 mt-1 w-5 h-5 cursor-pointer">
                            <div class="ml-4 flex-1">
                                <div class="flex items-start space-x-2">
                                    <svg class="w-5 h-5 text-blue-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    <div>
                                        <span class="font-bold text-gray-900 group-hover:text-blue-700 transition-colors">I have read and accept the
                                            <a href="{{ route('terms') }}" target="_blank" class="text-blue-600 hover:text-blue-800 underline font-bold" onclick="event.stopPropagation();">
                                                Terms and Conditions
                                            </a>
                                        </span>
                                        <span class="block text-sm text-gray-600 mt-1">Required to proceed with registration. Click the link to read full terms.</span>
                                    </div>
                                </div>
                            </div>
                        </label>
                        @error('tnc_accepted') <p class="mt-3 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                    </div>

                    @php
                        $activeLetter = app(\App\Actions\Undertaking\GetActiveLetterForTenantAction::class)
                            ->execute($tenant);
                    @endphp

                    @if($activeLetter)
                        <div class="border-2 border-gray-200 rounded-xl bg-white overflow-hidden">
                            <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-b-2 border-green-200 p-6">
                                <div class="flex items-start space-x-3">
                                    <svg class="w-6 h-6 text-green-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                    </svg>
                                    <div class="flex-1">
                                        <h4 class="text-lg font-bold text-gray-900">{{ $activeLetter->title }}</h4>
                                        <p class="text-sm text-gray-600 mt-1">Version {{ $activeLetter->version }} • {{ $tenant->name }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="p-6 max-h-96 overflow-y-auto bg-gray-50">
                                <div class="prose prose-sm max-w-none">
                                    {!! $activeLetter->content !!}
                                </div>
                            </div>

                            <div class="bg-white border-t-2 border-gray-200 p-6">
                                <label class="flex items-start cursor-pointer group">
                                    <input type="checkbox" wire:model="undertaking_accepted" class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-500 focus:ring-green-500 mt-1 w-5 h-5 cursor-pointer">
                                    <div class="ml-4 flex-1">
                                        <span class="font-bold text-gray-900 group-hover:text-green-700 transition-colors">
                                            I have read and accept the Letter of Undertaking (Version {{ $activeLetter->version }})
                                        </span>
                                        <span class="block text-sm text-gray-600 mt-1">Required to complete registration.</span>
                                    </div>
                                </label>
                                @error('undertaking_accepted') <p class="mt-3 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                            </div>
                        </div>
                    @else
                        <div class="p-6 border-2 border-gray-200 rounded-xl bg-gradient-to-br from-white to-green-50 hover:border-green-300 transition-all">
                            <label class="flex items-start cursor-pointer group">
                                <input type="checkbox" wire:model="undertaking_accepted" class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-500 focus:ring-green-500 mt-1 w-5 h-5 cursor-pointer">
                                <div class="ml-4 flex-1">
                                    <div class="flex items-start space-x-2">
                                        <svg class="w-5 h-5 text-green-500 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                                        </svg>
                                        <div>
                                            <span class="font-bold text-gray-900 group-hover:text-green-700 transition-colors">I acknowledge and accept the terms of service</span>
                                            <span class="block text-sm text-gray-600 mt-1">No active letter of undertaking is currently required.</span>
                                        </div>
                                    </div>
                                </div>
                            </label>
                            @error('undertaking_accepted') <p class="mt-3 text-sm text-red-600 font-medium">{{ $message }}</p> @enderror
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Navigation Buttons --}}
    <div class="sticky bottom-0 z-10 bg-white border-t border-gray-200 shadow-xl px-6 py-5 max-w-4xl mx-auto rounded-t-lg">
        <div class="flex items-center justify-between">
            <div>
                @if($currentStep > 1)
                    <button
                        type="button"
                        wire:click="previousStep"
                        class="inline-flex items-center px-5 py-2.5 border-2 border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        Back
                    </button>
                @endif
            </div>

            <div class="flex items-center space-x-4">
                <span class="text-sm font-medium text-gray-600 bg-gray-100 px-3 py-1.5 rounded-full">
                    Step {{ $currentStep }} of 4
                </span>

                @if($currentStep < 4)
                    <button
                        type="button"
                        wire:click="submitStep{{ $currentStep }}"
                        class="inline-flex items-center px-6 py-2.5 border border-transparent rounded-lg shadow-lg text-sm font-semibold text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all transform hover:scale-105">
                        Next Step
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </button>
                @else
                    <button
                        type="button"
                        wire:click="submitStep4"
                        class="inline-flex items-center px-6 py-2.5 border border-transparent rounded-lg shadow-lg text-sm font-semibold text-white bg-gradient-to-r from-green-600 to-green-700 hover:from-green-700 hover:to-green-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all transform hover:scale-105">
                        Complete Registration
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Auto-save Indicator --}}
    <div wire:loading class="fixed bottom-20 right-6 bg-blue-100 text-blue-800 px-4 py-2 rounded-lg shadow-lg">
        <div class="flex items-center space-x-2">
            <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            <span class="text-sm font-medium">Saving...</span>
        </div>
    </div>
    </div>
</div>
