<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Complete Your Profile - {{ config('app.name', 'Laravel') }}</title>

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-gray-50">
    <div class="min-h-screen bg-gray-50 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-4xl mx-auto">
        <div class="text-center mb-8">
            <div class="mx-auto h-16 w-16 bg-blue-600 rounded-lg flex items-center justify-center mb-6">
                <span class="text-white font-bold text-2xl">{{ substr($user->name, 0, 1) }}</span>
            </div>
            <h1 class="text-4xl font-extrabold text-gray-900 mb-4">
                Complete Your Profile
            </h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Please fill in the required information to access the platform and manage your child's information
            </p>
        </div>

        <div class="bg-white shadow-xl rounded-lg overflow-hidden">
            <div class="px-8 py-8">
                <form class="space-y-8" action="{{ route('profile.complete') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        <!-- Contact Information Section -->
                        <div class="space-y-6">
                            <div class="border-b border-gray-200 pb-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Contact Information</h3>
                                <p class="text-sm text-gray-600">Your contact details for communication</p>
                            </div>

                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number *</label>
                                <input id="phone" name="phone" type="tel" required
                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('phone') border-red-500 @enderror"
                                       placeholder="Enter your phone number" value="{{ old('phone', $user->profile?->phone) }}">
                                @error('phone')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Identity Information Section -->
                        <div class="space-y-6">
                            <div class="border-b border-gray-200 pb-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Identity Information</h3>
                                <p class="text-sm text-gray-600">Please provide either NRIC or Passport number</p>
                            </div>

                            <div class="space-y-4">
                                <div>
                                    <label for="nric" class="block text-sm font-medium text-gray-700 mb-2">NRIC</label>
                                    <input id="nric" name="nric" type="text"
                                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('nric') border-red-500 @enderror"
                                           maxlength="14" inputmode="numeric" autocomplete="off" oninput="const digits = this.value.replace(/\D/g, '').slice(0, 12); this.value = digits.replace(/^(\d{0,6})(\d{0,2})(\d{0,4}).*$/, (_, first, second, third) => [first, second, third].filter(Boolean).join('-'));" placeholder="e.g., 900101-01-1234" value="{{ old('nric', $user->profile?->nric) }}">
                                    @error('nric')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex items-center justify-center">
                                    <span class="bg-gray-100 px-3 py-1 rounded-full text-sm text-gray-500">OR</span>
                                </div>

                                <div>
                                    <label for="passport" class="block text-sm font-medium text-gray-700 mb-2">Passport</label>
                                    <input id="passport" name="passport" type="text"
                                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('passport') border-red-500 @enderror"
                                           placeholder="Passport Number" value="{{ old('passport', $user->profile?->passport) }}">
                                    @error('passport')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <!-- Occupation Information Section -->
                        <div class="space-y-6">
                            <div>
                                <label for="occupation" class="block text-sm font-medium text-gray-700 mb-2">Occupation (Optional)</label>
                                <input id="occupation" name="occupation" type="text"
                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('occupation') border-red-500 @enderror"
                                       placeholder="e.g., Teacher, Engineer, Doctor" value="{{ old('occupation', $user->profile?->occupation) }}">
                                @error('occupation')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                        <!-- Document Upload Section -->
                        <div class="space-y-6 col-span-1 lg:col-span-2">
                            <div class="border-b border-gray-200 pb-4">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Documents & Photos</h3>
                                <p class="text-sm text-gray-600">Upload your photo, MyKad, and immunization card (if available)</p>
                            </div>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div class="col-span-1 md:col-span-3">
                                    <label for="photo" class="block text-sm font-medium text-gray-700 mb-2">Photo *</label>
                                    <div class="relative group">
                                        <input id="photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" onchange="showFileName(this, 'photo-label')" required>
                                        <label for="photo" class="flex items-center justify-center w-full h-32 border-2 border-dashed border-blue-400 rounded-lg cursor-pointer bg-blue-50 hover:bg-blue-100 transition group-hover:border-blue-600">
                                            <svg class="w-8 h-8 text-blue-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                            <span id="photo-label" class="text-blue-700 font-medium">Click to upload photo</span>
                                        </label>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Accepted: JPG, PNG, WebP. Max size: 5MB</p>
                                    @error('photo')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="col-span-1 md:col-span-3">
                                    <label for="mykad" class="block text-sm font-medium text-gray-700 mb-2">MyKad *</label>
                                    <div class="relative group">
                                        <input id="mykad" name="mykad" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" onchange="showFileName(this, 'mykad-label')" required>
                                        <label for="mykad" class="flex items-center justify-center w-full h-32 border-2 border-dashed border-blue-400 rounded-lg cursor-pointer bg-blue-50 hover:bg-blue-100 transition group-hover:border-blue-600">
                                            <svg class="w-8 h-8 text-blue-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V4a1 1 0 011-1h8a1 1 0 011 1v12m-2 4h-4a2 2 0 01-2-2v-2h8v2a2 2 0 01-2 2z"/></svg>
                                            <span id="mykad-label" class="text-blue-700 font-medium">Click to upload MyKad</span>
                                        </label>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Accepted: JPG, PNG, WebP, PDF. Max size: 10MB</p>
                                    @error('mykad')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="col-span-1 md:col-span-3">
                                    <label for="immunization_card" class="block text-sm font-medium text-gray-700 mb-2">Immunization Card *</label>
                                    <div class="relative group">
                                        <input id="immunization_card" name="immunization_card" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="sr-only" onchange="showFileName(this, 'immunization-label')" required>
                                        <label for="immunization_card" class="flex items-center justify-center w-full h-32 border-2 border-dashed border-blue-400 rounded-lg cursor-pointer bg-blue-50 hover:bg-blue-100 transition group-hover:border-blue-600">
                                            <svg class="w-8 h-8 text-blue-500 mr-2" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                                            <span id="immunization-label" class="text-blue-700 font-medium">Click to upload Immunization Card</span>
                                        </label>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Accepted: JPG, PNG, WebP, PDF. Max size: 10MB</p>
                                    @error('immunization_card')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Address Information Section -->
                    <div class="pt-8 border-t border-gray-200">
                        <div class="border-b border-gray-200 pb-4 mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Address Information</h3>
                            <p class="text-sm text-gray-600">Your residential address for official purposes</p>
                        </div>

                        <div class="grid grid-cols-1 gap-6">
                            <div>
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-2">Full Address *</label>
                                <textarea id="address" name="address" rows="4" required
                                          class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('address') border-red-500 @enderror"
                                          placeholder="Enter your complete address">{{ old('address', $user->userAddress?->address) }}</textarea>
                                @error('address')
                                    <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label for="city" class="block text-sm font-medium text-gray-700 mb-2">City *</label>
                                    <input id="city" name="city" type="text" required
                                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('city') border-red-500 @enderror"
                                           placeholder="City" value="{{ old('city', $user->userAddress?->city) }}">
                                    @error('city')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="postal_code" class="block text-sm font-medium text-gray-700 mb-2">Postal Code *</label>
                                    <input id="postal_code" name="postal_code" type="text" required
                                           class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('postal_code') border-red-500 @enderror"
                                           placeholder="Postal Code" value="{{ old('postal_code', $user->userAddress?->postal_code) }}">
                                    @error('postal_code')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="state_code" class="block text-sm font-medium text-gray-700 mb-2">State *</label>
                                    <select id="state_code" name="state_code" required
                                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('state_code') border-red-500 @enderror">
                                        <option value="">Select State</option>
                                        @foreach(\App\Enums\MalaysianState::options() as $code => $name)
                                            <option value="{{ $code }}" {{ old('state_code', $user->userAddress?->state_code?->value) == $code ? 'selected' : '' }}>{{ $name }}</option>
                                        @endforeach
                                    </select>
                                    @error('state_code')
                                        <p class="mt-2 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="pt-8 border-t border-gray-200">
                        <button type="submit"
                                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-4 px-6 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition duration-200">
                            Complete Profile & Continue
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const nricInput = document.getElementById('nric');
    const passportInput = document.getElementById('passport');

    // Clear the other field when one is filled
    nricInput.addEventListener('input', function() {
        if (this.value.trim()) {
            passportInput.value = '';
        }
    });

    passportInput.addEventListener('input', function() {
        if (this.value.trim()) {
            nricInput.value = '';
        }
    });
    // Show selected file name for custom file inputs
    window.showFileName = function(input, labelId) {
        const label = document.getElementById(labelId);
        if (input.files && input.files.length > 0) {
            label.textContent = input.files[0].name;
        } else {
            // Reset to default label
            if (labelId === 'photo-label') label.textContent = 'Click to upload photo';
            if (labelId === 'mykad-label') label.textContent = 'Click to upload MyKad';
            if (labelId === 'immunization-label') label.textContent = 'Click to upload Immunization Card';
        }
    }
});
</script>
</body>
</html>
