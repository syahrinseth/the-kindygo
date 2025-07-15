@extends('layouts.auth')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <div class="mx-auto h-12 w-auto flex justify-center">
                <div class="h-12 w-12 bg-blue-600 rounded-lg flex items-center justify-center">
                    <span class="text-white font-bold text-xl">{{ substr($tenant->name, 0, 1) }}</span>
                </div>
            </div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                Join {{ $tenant->name }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                Register as a Parent to access your child's information
            </p>
        </div>

        <form class="mt-8 space-y-6" action="{{ route('tenant.register', $tenant->slug) }}" method="POST">
            @csrf
            
            <div class="rounded-md shadow-sm -space-y-px">
                <div>
                    <label for="name" class="sr-only">Full Name</label>
                    <input id="name" name="name" type="text" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-t-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('name') border-red-500 @enderror" 
                           placeholder="Full Name" value="{{ old('name') }}">
                    @error('name')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="email" class="sr-only">Email address</label>
                    <input id="email" name="email" type="email" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('email') border-red-500 @enderror" 
                           placeholder="Email address" value="{{ old('email') }}">
                    @error('email')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="phone" class="sr-only">Phone Number</label>
                    <input id="phone" name="phone" type="tel" 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('phone') border-red-500 @enderror" 
                           placeholder="Phone Number (Optional)" value="{{ old('phone') }}">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="password" class="sr-only">Password</label>
                    <input id="password" name="password" type="password" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm @error('password') border-red-500 @enderror" 
                           placeholder="Password">
                    @error('password')
                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                    @enderror
                </div>
                
                <div>
                    <label for="password_confirmation" class="sr-only">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required 
                           class="appearance-none rounded-none relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 rounded-b-md focus:outline-none focus:ring-blue-500 focus:border-blue-500 focus:z-10 sm:text-sm" 
                           placeholder="Confirm Password">
                </div>
            </div>

            <!-- Centre Selection -->
            <div class="mt-6">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-sm font-medium text-gray-700">Select Centres *</label>
                    @if($centres->count() > 1)
                        <button type="button" id="selectAllCentres" class="text-xs text-blue-600 hover:text-blue-500">
                            Select All
                        </button>
                    @endif
                </div>
                <div class="max-h-40 overflow-y-auto border border-gray-300 rounded-md p-3 bg-white">
                    @if($centres->count() > 0)
                        @foreach($centres as $centre)
                            <div class="flex items-start mb-3 last:mb-0">
                                <input id="centre_{{ $centre->id }}" name="centre_ids[]" type="checkbox" value="{{ $centre->id }}"
                                       class="centre-checkbox h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded mt-0.5"
                                       {{ in_array($centre->id, old('centre_ids', [])) ? 'checked' : '' }}>
                                <label for="centre_{{ $centre->id }}" class="ml-3 block text-sm cursor-pointer">
                                    <span class="text-gray-900 font-medium">{{ $centre->name }}</span>
                                    @if($centre->address_1)
                                        <span class="text-gray-500 text-xs block mt-1">{{ $centre->address_1 }}{{ $centre->city ? ', ' . $centre->city : '' }}</span>
                                    @endif
                                    @if($centre->phone)
                                        <span class="text-gray-500 text-xs block">📞 {{ $centre->phone }}</span>
                                    @endif
                                </label>
                            </div>
                        @endforeach
                    @else
                        <p class="text-sm text-gray-500">No centres available for this tenant.</p>
                    @endif
                </div>
                @error('centre_ids')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
                @error('centre_ids.*')
                    <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Register as Parent
                </button>
            </div>

            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Already have an account? 
                    <a href="{{ route('login') }}" class="font-medium text-blue-600 hover:text-blue-500">
                        Sign in here
                    </a>
                </p>
                <p class="mt-2 text-sm text-gray-600">
                    <a href="{{ route('tenant.directory') }}" class="font-medium text-blue-600 hover:text-blue-500">
                        Choose a different center
                    </a>
                </p>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAllButton = document.getElementById('selectAllCentres');
    const checkboxes = document.querySelectorAll('.centre-checkbox');
    
    if (selectAllButton && checkboxes.length > 0) {
        selectAllButton.addEventListener('click', function() {
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allChecked;
            });
            
            selectAllButton.textContent = allChecked ? 'Select All' : 'Deselect All';
        });
        
        // Update button text based on current state
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);
                selectAllButton.textContent = allChecked ? 'Deselect All' : 'Select All';
            });
        });
    }
});
</script>
@endsection
