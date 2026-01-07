@extends('layouts.guest')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto px-6">
        {{-- Header --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-6">
            <div class="flex items-center space-x-3 mb-4">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-blue-700 shadow-md">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Terms and Conditions</h1>
                    <p class="text-sm text-gray-600 mt-1">{{ config('app.name', 'KindyGo') }} Parent Registration</p>
                </div>
            </div>
            <p class="text-sm text-gray-500">Last Updated: {{ now()->format('F d, Y') }}</p>
        </div>

        {{-- Content --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 prose prose-blue max-w-none">
            <h2>1. Introduction</h2>
            <p>Welcome to {{ config('app.name', 'KindyGo') }}. These Terms and Conditions govern your use of our childcare management platform and services. By registering and using our services, you agree to be bound by these terms.</p>

            <h2>2. Definitions</h2>
            <ul>
                <li><strong>"Platform"</strong> refers to the {{ config('app.name', 'KindyGo') }} web application and associated services.</li>
                <li><strong>"User"</strong> refers to parents/guardians who register on the platform.</li>
                <li><strong>"Centre"</strong> refers to childcare facilities registered on our platform.</li>
                <li><strong>"Child"</strong> refers to the minor enrolled in the childcare centre.</li>
            </ul>

            <h2>3. User Registration</h2>
            <p>3.1. You must provide accurate, current, and complete information during registration.</p>
            <p>3.2. You are responsible for maintaining the confidentiality of your account credentials.</p>
            <p>3.3. You must be at least 18 years old to register as a parent/guardian.</p>
            <p>3.4. One account per parent/guardian is permitted.</p>

            <h2>4. Child Information</h2>
            <p>4.1. You agree to provide accurate information about your child(ren).</p>
            <p>4.2. You must update child information promptly if any changes occur.</p>
            <p>4.3. You authorize the centre to maintain and use this information for childcare purposes.</p>

            <h2>5. Payment Terms</h2>
            <p>5.1. All fees are stated in Malaysian Ringgit (MYR) unless otherwise specified.</p>
            <p>5.2. Invoices will be generated according to the centre's billing schedule.</p>
            <p>5.3. Payment must be made by the due date specified on the invoice.</p>
            <p>5.4. Late payments may incur additional charges as determined by the centre.</p>
            <p>5.5. You agree to notify the centre immediately of any billing discrepancies.</p>

            <h2>6. Privacy and Data Protection</h2>
            <p>6.1. We collect and process personal data in accordance with Malaysian Personal Data Protection Act 2010 (PDPA).</p>
            <p>6.2. Your personal information will be used solely for childcare management purposes.</p>
            <p>6.3. We implement reasonable security measures to protect your data.</p>
            <p>6.4. You have the right to access, correct, or delete your personal information.</p>

            <h2>7. User Responsibilities</h2>
            <p>7.1. You must not share your account credentials with others.</p>
            <p>7.2. You are responsible for all activities that occur under your account.</p>
            <p>7.3. You must notify us immediately of any unauthorized access to your account.</p>
            <p>7.4. You agree to use the platform only for lawful purposes.</p>

            <h2>8. Centre Policies</h2>
            <p>8.1. Each childcare centre may have additional policies and rules.</p>
            <p>8.2. You agree to comply with the specific policies of the centre(s) you register with.</p>
            <p>8.3. Centre-specific policies do not override these Terms and Conditions.</p>

            <h2>9. Liability and Disclaimers</h2>
            <p>9.1. The platform is provided "as is" without warranties of any kind.</p>
            <p>9.2. We are not liable for any indirect, incidental, or consequential damages.</p>
            <p>9.3. We do not guarantee uninterrupted or error-free service.</p>
            <p>9.4. The childcare centre is solely responsible for the care and safety of your child.</p>

            <h2>10. Account Suspension and Termination</h2>
            <p>10.1. We reserve the right to suspend or terminate accounts that violate these terms.</p>
            <p>10.2. You may request account deletion at any time.</p>
            <p>10.3. Upon termination, your access to the platform will cease immediately.</p>
            <p>10.4. Outstanding payments remain due even after account termination.</p>

            <h2>11. Intellectual Property</h2>
            <p>11.1. All content and materials on the platform are our intellectual property.</p>
            <p>11.2. You may not copy, modify, or distribute platform content without permission.</p>
            <p>11.3. The {{ config('app.name', 'KindyGo') }} name and logo are our trademarks.</p>

            <h2>12. Changes to Terms</h2>
            <p>12.1. We reserve the right to modify these terms at any time.</p>
            <p>12.2. Changes will be effective immediately upon posting to the platform.</p>
            <p>12.3. Continued use of the platform constitutes acceptance of modified terms.</p>
            <p>12.4. You will be notified of significant changes via email.</p>

            <h2>13. Governing Law</h2>
            <p>13.1. These Terms and Conditions are governed by the laws of Malaysia.</p>
            <p>13.2. Any disputes will be subject to the exclusive jurisdiction of Malaysian courts.</p>

            <h2>14. Contact Information</h2>
            <p>If you have questions about these Terms and Conditions, please contact:</p>
            <ul>
                <li>Email: support@kindygo.com</li>
                <li>Address: [Your Business Address]</li>
            </ul>

            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mt-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-800">
                            <strong>Important:</strong> By accepting these terms during registration, you acknowledge that you have read, understood, and agree to be bound by these Terms and Conditions.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Back Button --}}
        <div class="mt-6 text-center">
            <button onclick="window.close()" class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Close Window
            </button>
        </div>
    </div>
</div>
@endsection
