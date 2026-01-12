<?php

return [
    /*
    |--------------------------------------------------------------------------
    | LHDN e-Invoice API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for Malaysia's LHDN e-Invoice system integration.
    | Make sure to set these values in your .env file.
    |
    | NOTE: These are global fallback values. Individual tenants can have
    | their own client_id and client_secret configured in the database.
    | Tenant-specific credentials will take precedence over these values.
    |
    */

    'base_url' => env('EINVOICE_BASE_URL', 'https://api.myinvois.hasil.gov.my'),

    'client_id' => env('EINVOICE_CLIENT_ID'),

    'client_secret' => env('EINVOICE_CLIENT_SECRET'),

    'environment' => env('EINVOICE_ENVIRONMENT', 'sandbox'), // sandbox or production

    /*
    |--------------------------------------------------------------------------
    | Default Values
    |--------------------------------------------------------------------------
    |
    | Default values for e-Invoice generation
    |
    */

    'default_currency' => 'MYR',

    'default_country_code' => 'MYS',

    'default_state_code' => '14', // Selangor

    'default_city' => 'Kuala Lumpur',

    'default_postal_code' => '50000',

    /*
    |--------------------------------------------------------------------------
    | Invoice Type Codes
    |--------------------------------------------------------------------------
    |
    | Different types of invoices supported by the system
    |
    */

    'invoice_types' => [
        'standard' => '01',
        'credit_note' => '02',
        'debit_note' => '03',
        'refund_note' => '11',
    ],

    /*
    |--------------------------------------------------------------------------
    | Tax Categories
    |--------------------------------------------------------------------------
    |
    | Tax categories for different types of services
    |
    */

    'tax_categories' => [
        'exempt' => 'E',      // Tax exempt (typical for childcare services)
        'zero_rated' => 'Z',  // Zero-rated
        'standard' => 'S',    // Standard rate
        'out_of_scope' => 'O', // Out of scope
    ],

    /*
    |--------------------------------------------------------------------------
    | Unit Codes
    |--------------------------------------------------------------------------
    |
    | Standard unit codes for invoice line items
    |
    */

    'unit_codes' => [
        'each' => 'C62',      // Unit (each)
        'month' => 'MON',     // Month
        'day' => 'DAY',       // Day
        'hour' => 'HUR',      // Hour
        'service' => 'C62',   // Service unit
    ],

    /*
    |--------------------------------------------------------------------------
    | Supplier Information (Fallback)
    |--------------------------------------------------------------------------
    |
    | Default supplier information used when tenant information is not available.
    | In production, this will be overridden by tenant-specific data.
    |
    */

    'supplier_tin' => env('EINVOICE_SUPPLIER_TIN'),

    'supplier_name' => env('EINVOICE_SUPPLIER_NAME', 'Your Company Name'),

    'supplier_address_line_1' => env('EINVOICE_SUPPLIER_ADDRESS_1', 'Your Address Line 1'),

    'supplier_address_line_2' => env('EINVOICE_SUPPLIER_ADDRESS_2', ''),

    'supplier_city' => env('EINVOICE_SUPPLIER_CITY', 'Kuala Lumpur'),

    'supplier_state' => env('EINVOICE_SUPPLIER_STATE', 'Selangor'),

    'supplier_postal_code' => env('EINVOICE_SUPPLIER_POSTAL_CODE', '50000'),

    'supplier_country' => env('EINVOICE_SUPPLIER_COUNTRY', 'MYS'),

    'supplier_email' => env('EINVOICE_SUPPLIER_EMAIL', 'info@yourcompany.com'),

    'supplier_phone' => env('EINVOICE_SUPPLIER_PHONE', '+60123456789'),

    'supplier_registration_number' => env('EINVOICE_SUPPLIER_REGISTRATION_NUMBER'),

    'supplier_registration_number_scheme' => env('EINVOICE_SUPPLIER_REGISTRATION_NUMBER_SCHEME', 'BRN'), // Business Registration Number

    'supplier_business_activity_code' => env('EINVOICE_SUPPLIER_BUSINESS_ACTIVITY_CODE', '85100'), // Child day-care activities

    'supplier_business_activity_description' => env('EINVOICE_SUPPLIER_BUSINESS_ACTIVITY_DESCRIPTION', 'Child day-care activities'),

    /*
    |--------------------------------------------------------------------------
    | MyInvois Client Configuration
    |--------------------------------------------------------------------------
    |
    | Your MyInvois client credentials. The TIN used here must match
    | the supplier_tin for successful document submission.
    |
    */

    'myinvois_tin' => env('EINVOICE_MYINVOIS_TIN', env('EINVOICE_SUPPLIER_TIN')), // Should be same as SUPPLIER_TIN
];
