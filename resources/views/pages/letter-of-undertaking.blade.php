@extends('layouts.guest')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-4xl mx-auto px-6">
        {{-- Header --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 mb-6">
            <div class="flex items-center space-x-3 mb-4">
                <div class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gradient-to-br from-green-500 to-green-700 shadow-md">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Letter of Undertaking</h1>
                    <p class="text-sm text-gray-600 mt-1">Parent/Guardian Declaration</p>
                </div>
            </div>
            <p class="text-sm text-gray-500">Last Updated: {{ now()->format('F d, Y') }}</p>
        </div>

        {{-- Content --}}
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-8 prose prose-green max-w-none">
            <p class="text-lg font-semibold text-gray-900 mb-6">I, the undersigned parent/guardian, hereby declare and undertake the following:</p>

            <h2>1. Child Information Accuracy</h2>
            <p>I confirm that all information provided about my child/children is accurate, complete, and up to date. I understand that providing false information may result in the termination of services.</p>

            <h2>2. Health and Medical Information</h2>
            <p>2.1. I will promptly inform the childcare centre of any health conditions, allergies, or medical requirements affecting my child.</p>
            <p>2.2. I authorize the centre to seek emergency medical treatment for my child if I cannot be reached.</p>
            <p>2.3. I understand that the centre staff are not medical professionals and cannot diagnose or treat medical conditions.</p>
            <p>2.4. I will provide all necessary medications with clear instructions and proper labeling.</p>

            <h2>3. Financial Obligations</h2>
            <p>3.1. I agree to pay all fees according to the payment schedule provided by the centre.</p>
            <p>3.2. I understand that late payments may result in late fees and/or suspension of services.</p>
            <p>3.3. I will provide advance notice as per centre policy if withdrawing my child from the programme.</p>
            <p>3.4. I acknowledge that fees are non-refundable except as specified in the centre's refund policy.</p>

            <h2>4. Pick-up and Drop-off Authorization</h2>
            <p>4.1. I will provide a list of authorized persons who may collect my child from the centre.</p>
            <p>4.2. I understand that the centre will not release my child to unauthorized persons.</p>
            <p>4.3. I will notify the centre immediately of any changes to authorized pick-up persons.</p>
            <p>4.4. I agree to collect my child within the centre's operating hours.</p>

            <h2>5. Communication and Updates</h2>
            <p>5.1. I agree to check the {{ config('app.name', 'KindyGo') }} platform regularly for updates and communications.</p>
            <p>5.2. I will respond promptly to messages and requests from the centre.</p>
            <p>5.3. I will keep my contact information current at all times.</p>
            <p>5.4. I authorize the centre to contact me via phone, email, or the platform for any matters concerning my child.</p>

            <h2>6. Child's Belongings</h2>
            <p>6.1. I understand that the centre is not responsible for lost, damaged, or stolen personal items.</p>
            <p>6.2. I will label all my child's belongings clearly.</p>
            <p>6.3. I will ensure my child brings only appropriate items to the centre.</p>

            <h2>7. Photography and Media</h2>
            <p>7.1. I consent to the centre taking photographs/videos of my child for:</p>
            <ul>
                <li>Educational documentation and portfolios</li>
                <li>Internal displays within the centre</li>
                <li>Progress reports shared with parents</li>
            </ul>
            <p>7.2. I understand that I can withdraw this consent at any time by notifying the centre in writing.</p>

            <h2>8. Behaviour and Discipline</h2>
            <p>8.1. I understand that the centre has behaviour management policies in place.</p>
            <p>8.2. I agree to work cooperatively with the centre to address any behavioural concerns.</p>
            <p>8.3. I acknowledge that serious or repeated behavioural issues may result in suspension or termination of enrolment.</p>

            <h2>9. Illness and Exclusion Policy</h2>
            <p>9.1. I will not send my child to the centre if they are unwell or have any contagious illness.</p>
            <p>9.2. I agree to collect my child promptly if they become ill during centre hours.</p>
            <p>9.3. I will keep my child at home for the period specified by the centre's illness policy.</p>
            <p>9.4. I will provide a medical clearance if required before my child returns to the centre.</p>

            <h2>10. Centre Policies Compliance</h2>
            <p>10.1. I have read and agree to comply with all centre policies and procedures.</p>
            <p>10.2. I understand that policies may be updated from time to time.</p>
            <p>10.3. I will be notified of any significant policy changes.</p>

            <h2>11. Liability and Indemnity</h2>
            <p>11.1. I acknowledge that while the centre takes reasonable care, accidents may occur.</p>
            <p>11.2. I agree not to hold the centre liable for minor injuries that may occur during normal activities.</p>
            <p>11.3. I understand that the centre has appropriate insurance coverage.</p>

            <h2>12. Data Privacy</h2>
            <p>12.1. I consent to the collection, use, and storage of my personal data and my child's data as outlined in the Privacy Policy.</p>
            <p>12.2. I understand that data will be used solely for childcare management purposes.</p>
            <p>12.3. I acknowledge my rights under the Personal Data Protection Act 2010 (PDPA).</p>

            <h2>13. Programme Participation</h2>
            <p>13.1. I authorize my child to participate in all regular centre activities, including outdoor play.</p>
            <p>13.2. I will be notified in advance of any special activities or excursions requiring additional consent.</p>
            <p>13.3. I understand that I may opt my child out of specific activities by notifying the centre in writing.</p>

            <h2>14. Termination of Services</h2>
            <p>14.1. Either party may terminate this agreement by providing notice as per centre policy.</p>
            <p>14.2. The centre reserves the right to terminate services immediately for serious violations.</p>
            <p>14.3. Outstanding fees must be settled upon termination.</p>

            <div class="bg-green-50 border-l-4 border-green-500 p-4 mt-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-800">
                            <strong>Declaration:</strong> By accepting this Letter of Undertaking during registration, I confirm that I have read, understood, and agree to fulfill all the obligations stated above. I understand that failure to comply may result in the termination of childcare services.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Back Button --}}
        <div class="mt-6 text-center">
            <button onclick="window.close()" class="inline-flex items-center px-6 py-3 border border-gray-300 rounded-lg shadow-sm text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-all">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Close Window
            </button>
        </div>
    </div>
</div>
@endsection
