<?php

namespace App\Services;

use App\Models\Tenant;
use Carbon\Carbon;
use DOMDocument;
use DOMElement;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Support\Facades\Log;
use Klsheng\Myinvois\MyInvoisClient;

class EInvoiceSDKService
{
    private MyInvoisClient $client;

    private ?string $accessToken = null;

    private $prodMode;

    private ?Tenant $tenant;

    public function __construct(Tenant $tenant)
    {
        // Set the tenant - this is required for multi-tenancy support
        $this->tenant = $tenant;

        // Validate tenant has required e-invoice data
        if ($this->tenant) {
            $this->validateTenantEInvoiceData();
        }

        // Get client credentials from tenant if available, otherwise use global config
        $clientId = $this->tenant?->getEInvoiceClientId() ?? config('einvoice.client_id') ?? '';
        $clientSecret = $this->tenant?->getEInvoiceClientSecret() ?? config('einvoice.client_secret') ?? '';
        $this->prodMode = $this->tenant?->isEInvoiceProduction() ?? (config('einvoice.environment') === 'production');

        $this->client = new MyInvoisClient($clientId, $clientSecret, $this->prodMode);
    }

    /**
     * Create a new instance for a specific tenant.
     *
     * @throws Exception
     */
    public static function forTenant(Tenant $tenant): static
    {
        return new static($tenant);
    }

    /**
     * Get the current tenant.
     */
    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    /**
     * Set the tenant for this service instance.
     *
     * @return $this
     *
     * @throws Exception
     */
    public function setTenant(Tenant $tenant): self
    {
        $this->tenant = $tenant;
        $this->validateTenantEInvoiceData();

        return $this;
    }

    /**
     * Validate that the tenant has all required e-invoice data configured.
     *
     * @throws Exception
     */
    private function validateTenantEInvoiceData(): void
    {
        if (! $this->tenant) {
            throw new Exception('Tenant is required for e-Invoice operations');
        }

        $requiredFields = [
            'tax_identification_number' => 'Tax Identification Number (TIN)',
            'business_id_type' => 'Business ID Type',
            'business_id_value' => 'Business ID Value',
            'business_activity_code' => 'Business Activity Code (MSIC)',
            'country' => 'Country',
        ];

        $missingFields = [];

        foreach ($requiredFields as $field => $label) {
            if (empty($this->tenant->$field)) {
                $missingFields[] = $label;
            }
        }

        // Check for e-Invoice API credentials
        if (empty($this->tenant->getEInvoiceClientId()) && empty(config('einvoice.client_id'))) {
            $missingFields[] = 'E-Invoice Client ID';
        }

        if (empty($this->tenant->getEInvoiceClientSecret()) && empty(config('einvoice.client_secret'))) {
            $missingFields[] = 'E-Invoice Client Secret';
        }

        if (! empty($missingFields)) {
            throw new Exception('Tenant is missing required e-Invoice configuration: '.implode(', ', $missingFields));
        }

        // Validate TIN format (basic validation)
        if (! preg_match('/^[A-Z0-9]{10,20}$/', $this->tenant->tax_identification_number)) {
            throw new Exception('Invalid TIN format. TIN should be 10-20 alphanumeric characters.');
        }

        // Validate business ID based on type
        $this->validateTenantBusinessId();
    }

    /**
     * Validate tenant's business ID based on the selected type.
     *
     * @throws Exception
     */
    private function validateTenantBusinessId(): void
    {
        if (! $this->tenant->business_id_type || ! $this->tenant->business_id_value) {
            return; // Already validated in validateTenantEInvoiceData
        }

        switch ($this->tenant->business_id_type) {
            case 'NRIC':
                if (! preg_match('/^[0-9]{12}$/', $this->tenant->business_id_value)) {
                    throw new Exception('Invalid NRIC format. NRIC must be exactly 12 digits without dashes.');
                }
                break;
            case 'BRN':
                if (! preg_match('/^[0-9A-Z\-]{5,20}$/', $this->tenant->business_id_value)) {
                    throw new Exception('Invalid BRN format. BRN should be 5-20 characters long.');
                }
                break;
            case 'PASSPORT':
                if (! preg_match('/^[A-Z0-9]{5,15}$/', $this->tenant->business_id_value)) {
                    throw new Exception('Invalid Passport format. Passport should be 5-15 alphanumeric characters.');
                }
                break;
                // Add more validation for other ID types as needed
        }
    }

    /**
     * Get tenant's TIN for e-Invoice operations.
     *
     * @throws Exception
     */
    public function getTenantTIN(): string
    {
        if (! $this->tenant || ! $this->tenant->tax_identification_number) {
            throw new Exception('Tenant TIN not configured');
        }

        return $this->tenant->tax_identification_number;
    }

    /**
     * Get tenant's business ID type and value for LHDN validation.
     *
     * @throws Exception
     */
    public function getTenantBusinessId(): array
    {
        if (! $this->tenant || ! $this->tenant->business_id_type || ! $this->tenant->business_id_value) {
            throw new Exception('Tenant business ID not configured');
        }

        return [
            'type' => $this->tenant->business_id_type,
            'value' => $this->tenant->business_id_value,
        ];
    }

    /**
     * Get tenant's business information for supplier data in e-Invoice.
     *
     * @throws Exception
     */
    public function getTenantSupplierData(): array
    {
        if (! $this->tenant) {
            throw new Exception('Tenant not configured');
        }

        return [
            'name' => $this->tenant->name,
            'tin' => $this->tenant->tax_identification_number,
            'business_id_type' => $this->tenant->business_id_type,
            'business_id_value' => $this->tenant->business_id_value,
            'msic_code' => $this->tenant->business_activity_code,
            'business_activity_description' => $this->tenant->business_activity_description,
            'address_1' => $this->tenant->address_1,
            'address_2' => $this->tenant->address_2,
            'city' => $this->tenant->city,
            'state' => $this->tenant->state,
            'postal_code' => $this->tenant->postal_code,
            'country' => $this->tenant->country,
            'state_code' => $this->tenant->state_code,
            'phone' => $this->tenant->phone,
            'email' => $this->tenant->email,
        ];
    }

    /**
     * Get access token by authenticating directly with LHDN API.
     *
     * @throws Exception
     */
    private function getAccessToken(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        try {
            $tokenUrl = $this->prodMode
                ? 'https://api.myinvois.hasil.gov.my/connect/token'
                : 'https://preprod-api.myinvois.hasil.gov.my/connect/token';

            // Use tenant-specific credentials
            $clientId = $this->tenant?->getEInvoiceClientId() ?? config('einvoice.client_id');
            $clientSecret = $this->tenant?->getEInvoiceClientSecret() ?? config('einvoice.client_secret');

            $guzzle = new Client;
            $response = $guzzle->post($tokenUrl, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => 'InvoicingAPI',
                ],
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'onbehalfof' => $this->tenant->tax_identification_number,
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (! isset($data['access_token'])) {
                throw new Exception('Failed to get access token from LHDN API');
            }

            $this->accessToken = $data['access_token'];

            return $this->accessToken;

        } catch (ClientException $e) {
            $response = $e->getResponse();
            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            $error = $data['error'] ?? 'unknown_error';
            $description = $data['error_description'] ?? 'No description available';

            throw new Exception("Authentication failed: {$error} - {$description}");
        }
    }

    /**
     * Submit invoice to LHDN e-Invoice API using the official SDK.
     *
     * @throws Exception
     */
    public function submitInvoice(array $invoiceData): array
    {
        try {
            // Log the credentials being used (without exposing secrets)
            Log::info('Attempting e-Invoice submission for tenant', [
                'tenant_id' => $this->tenant->id ?? 'unknown',
                'tenant_name' => $this->tenant->name ?? 'unknown',
                'client_id' => $this->tenant?->getEInvoiceClientId() ?? config('einvoice.client_id'),
                'environment' => $this->tenant?->getEInvoiceEnvironment() ?? config('einvoice.environment'),
                'has_client_secret' => ! empty($this->tenant?->getEInvoiceClientSecret() ?? config('einvoice.client_secret')),
                'using_tenant_credentials' => $this->tenant?->hasEInvoiceCredentials() ?? false,
            ]);

            // Extract and validate supplier TIN
            if (isset($invoiceData['Invoice']['AccountingSupplierParty']['Party']['PartyIdentification'])) {
                foreach ($invoiceData['Invoice']['AccountingSupplierParty']['Party']['PartyIdentification'] as $partyId) {
                    $supplierTIN = $partyId['ID']['_'];
                    $this->validateSupplierTIN($supplierTIN);
                    break; // Only validate the first TIN found
                }
            }

            // Validate required fields before submission
            $this->validateInvoiceData($invoiceData);
            // Convert to UBL XML format
            $ublXml = $this->convertToUBLXml($invoiceData);

            // Log a portion of the generated XML for debugging
            $dom = new DOMDocument;
            $dom->loadXML($ublXml);
            $dom->formatOutput = true;
            $formattedXml = $dom->saveXML();

            // Extract supplier information from XML for verification
            if (preg_match('/<cac:AccountingSupplierParty>.*?<\/cac:AccountingSupplierParty>/s', $formattedXml, $supplierMatches)) {
                Log::info('E-Invoice XML supplier section', [
                    'supplier_xml' => $supplierMatches[0],
                ]);
            }

            // Get the invoice ID for the code number
            $invoiceId = $invoiceData['Invoice']['ID'] ?? 'UNKNOWN';

            // Use direct API submission instead of SDK due to authentication issues
            $response = $this->submitDirectly($invoiceId, $ublXml);

            Log::info('E-Invoice submission response for tenant', [
                'tenant_id' => $this->tenant->id ?? 'unknown',
                'response' => $response,
            ]);

            // Check for different types of errors in response
            if (isset($response['error'])) {
                $error = $response['error'];
                Log::error('E-Invoice API error', [
                    'error' => $error,
                    'invoice_id' => $invoiceData['Invoice']['ID'] ?? 'unknown',
                ]);

                // Format error message based on error type
                $errorMessage = $this->formatValidationError($error);
                throw new Exception($errorMessage);
            }

            // Check for submission errors (when submission fails)
            if (isset($response['rejectedDocuments']) && ! empty($response['rejectedDocuments'])) {
                $rejectedDoc = $response['rejectedDocuments'][0];
                Log::error('E-Invoice document rejected', [
                    'rejected_document' => $rejectedDoc,
                    'invoice_id' => $invoiceData['Invoice']['ID'] ?? 'unknown',
                ]);

                $errorMessage = "Invoice was rejected by LHDN:\n";
                if (isset($rejectedDoc['error'])) {
                    $errorMessage .= $this->formatValidationError($rejectedDoc['error']);
                } else {
                    $errorMessage .= 'Document rejected for unknown reasons. Please check the invoice data.';
                }
                throw new Exception($errorMessage);
            }

            if (! $response || ! isset($response['submissionUid'])) {
                Log::error('E-Invoice submission failed using direct API', [
                    'response' => $response,
                ]);
                throw new Exception('Failed to submit invoice: Invalid response from LHDN');
            }

            return $response;

        } catch (ConnectException $e) {
            Log::error('E-Invoice connection error', [
                'message' => $e->getMessage(),
            ]);
            throw new Exception('Unable to connect to LHDN e-Invoice system. Please check your internet connection and try again.');
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            Log::error('E-Invoice client error', [
                'status_code' => $statusCode,
                'response_body' => $body,
                'message' => $e->getMessage(),
            ]);

            // Handle specific HTTP error codes
            switch ($statusCode) {
                case 400:
                    throw new Exception('Bad Request: The invoice data is invalid. Please check your invoice details and try again.');
                case 401:
                    throw new Exception('Authentication Failed: Invalid LHDN credentials or expired token. Please check your API configuration in the .env file (EINVOICE_CLIENT_ID and EINVOICE_CLIENT_SECRET). You may need to regenerate your API credentials in the MyInvois portal.');
                case 403:
                    throw new Exception('Access Denied: You do not have permission to submit invoices. Please ensure your MyInvois account has the correct permissions.');
                case 404:
                    throw new Exception('Service Not Found: The LHDN e-Invoice service is unavailable. Please try again later.');
                case 422:
                    // Parse validation errors from response body
                    $responseData = json_decode($body, true);
                    if ($responseData && isset($responseData['error'])) {
                        $errorMessage = $this->formatValidationError($responseData['error']);
                        throw new Exception('Validation Error: '.$errorMessage);
                    }
                    throw new Exception('Validation Error: The invoice data failed LHDN validation. Please check your invoice details.');
                case 429:
                    throw new Exception('Rate Limit Exceeded: Too many requests. Please wait a moment and try again.');
                default:
                    throw new Exception('LHDN API Error (Code: '.$statusCode.'): '.$e->getMessage());
            }

        } catch (ServerException $e) {
            Log::error('E-Invoice server error', [
                'message' => $e->getMessage(),
            ]);
            throw new Exception('LHDN Server Error: The e-Invoice system is experiencing technical difficulties. Please try again later.');
        } catch (Exception $e) {
            Log::error('E-Invoice SDK submission error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // If it's already a formatted error message, don't wrap it
            if (strpos($e->getMessage(), 'E-Invoice') === 0 ||
                strpos($e->getMessage(), 'Authentication') === 0 ||
                strpos($e->getMessage(), 'Access Denied') === 0) {
                throw $e;
            }

            throw new Exception('E-Invoice submission failed: '.$e->getMessage());
        }
    }

    /**
     * Convert invoice data array to proper UBL 2.1 XML format.
     */
    private function convertToUBLXml(array $invoiceData): string
    {
        $xml = new DOMDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;

        // Create root Invoice element with proper namespaces
        $invoice = $xml->createElement('Invoice');
        $invoice->setAttribute('xmlns', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');
        $invoice->setAttribute('xmlns:cac', 'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
        $invoice->setAttribute('xmlns:cbc', 'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');
        $xml->appendChild($invoice);

        $invoiceData = $invoiceData['Invoice'];

        // Add UBL Version ID
        $invoice->appendChild($xml->createElement('cbc:UBLVersionID', '2.1'));

        // Add Customization ID (MyInvois specific)
        $invoice->appendChild($xml->createElement('cbc:CustomizationID', 'MY:10.0'));

        // Add Profile ID
        $invoice->appendChild($xml->createElement('cbc:ProfileID', 'reporting:1.0'));

        // Add basic invoice elements in correct order
        $invoice->appendChild($xml->createElement('cbc:ID', $invoiceData['ID']));
        $invoice->appendChild($xml->createElement('cbc:IssueDate', $invoiceData['IssueDate']));

        // Add IssueTime if provided
        if (isset($invoiceData['IssueTime'])) {
            $invoice->appendChild($xml->createElement('cbc:IssueTime', $invoiceData['IssueTime']));
        }

        $invoice->appendChild($xml->createElement('cbc:DueDate', $invoiceData['DueDate']));

        // Add InvoiceTypeCode with attributes
        $invoiceTypeCode = $xml->createElement('cbc:InvoiceTypeCode', $invoiceData['InvoiceTypeCode']['_']);
        $invoiceTypeCode->setAttribute('listVersionID', $invoiceData['InvoiceTypeCode']['listVersionID']);
        $invoice->appendChild($invoiceTypeCode);

        $invoice->appendChild($xml->createElement('cbc:DocumentCurrencyCode', $invoiceData['DocumentCurrencyCode']));

        // Add AccountingSupplierParty
        $supplierParty = $xml->createElement('cac:AccountingSupplierParty');
        $this->addPartyData($xml, $supplierParty, $invoiceData['AccountingSupplierParty']['Party']);
        $invoice->appendChild($supplierParty);

        // Add AccountingCustomerParty
        $customerParty = $xml->createElement('cac:AccountingCustomerParty');
        $this->addPartyData($xml, $customerParty, $invoiceData['AccountingCustomerParty']['Party']);
        $invoice->appendChild($customerParty);

        // Add TaxTotal (must come before InvoiceLines)
        if (isset($invoiceData['TaxTotal'])) {
            $taxTotal = $xml->createElement('cac:TaxTotal');
            $this->addTaxTotalData($xml, $taxTotal, $invoiceData['TaxTotal']);
            $invoice->appendChild($taxTotal);
        }

        // Add LegalMonetaryTotal (must come before InvoiceLines)
        $legalMonetaryTotal = $xml->createElement('cac:LegalMonetaryTotal');
        $this->addLegalMonetaryTotalData($xml, $legalMonetaryTotal, $invoiceData['LegalMonetaryTotal']);
        $invoice->appendChild($legalMonetaryTotal);

        // Add InvoiceLines (must come last)
        foreach ($invoiceData['InvoiceLine'] as $lineData) {
            $invoiceLine = $xml->createElement('cac:InvoiceLine');
            $this->addInvoiceLineData($xml, $invoiceLine, $lineData);
            $invoice->appendChild($invoiceLine);
        }

        return $xml->saveXML();
    }

    /**
     * Recursively convert array to XML elements.
     */
    private function arrayToXml(array $data, DOMElement $parent, DOMDocument $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                if (is_numeric($key)) {
                    // Handle numeric keys (array items)
                    $this->arrayToXml($value, $parent, $xml);
                } else {
                    // Create element with proper namespace prefix
                    $elementName = $this->getProperElementName($key);
                    $element = $xml->createElement($elementName);
                    $parent->appendChild($element);

                    // Handle special attributes
                    if (isset($value['_'])) {
                        // This is a value with attributes
                        $element->nodeValue = htmlspecialchars((string) $value['_']);
                        foreach ($value as $attrKey => $attrValue) {
                            if ($attrKey !== '_' && ! is_array($attrValue)) {
                                $element->setAttribute($attrKey, (string) $attrValue);
                            }
                        }
                    } else {
                        $this->arrayToXml($value, $element, $xml);
                    }
                }
            } else {
                // Create leaf element
                $elementName = $this->getProperElementName($key);
                $element = $xml->createElement($elementName, htmlspecialchars((string) $value));
                $parent->appendChild($element);
            }
        }
    }

    /**
     * Get proper element name with correct namespace prefix.
     */
    private function getProperElementName(string $key): string
    {
        // Common aggregate components (complex elements)
        $aggregateComponents = [
            'AccountingSupplierParty', 'AccountingCustomerParty', 'InvoiceLine',
            'Item', 'Price', 'ClassifiedTaxCategory', 'TaxCategory',
            'PartyName', 'PostalAddress', 'Country', 'PartyTaxScheme',
            'PartyLegalEntity', 'Contact', 'TaxScheme', 'LegalMonetaryTotal',
            'TaxTotal', 'TaxSubtotal', 'AllowanceCharge', 'Party',
            'PartyIdentification', 'CommodityClassification', 'ItemPriceExtension',
        ];

        // Basic components (simple elements)
        $basicComponents = [
            'ID', 'IssueDate', 'IssueTime', 'DueDate', 'InvoiceTypeCode', 'DocumentCurrencyCode',
            'LineExtensionAmount', 'TaxExclusiveAmount', 'TaxInclusiveAmount',
            'PayableAmount', 'CompanyID', 'Name', 'StreetName', 'CityName',
            'PostalZone', 'IdentificationCode', 'TaxAmount', 'TaxableAmount',
            'Percent', 'InvoicedQuantity', 'PriceAmount', 'Description',
            'BaseQuantity', 'ChargeIndicator', 'AllowanceChargeReason', 'Amount',
            'Telephone', 'ElectronicMail', 'RegistrationName', 'AllowanceTotalAmount',
            'ItemClassificationCode', 'CountrySubentityCode', 'IndustryClassificationCode',
        ];

        if (in_array($key, $aggregateComponents)) {
            return 'cac:'.$key;
        } elseif (in_array($key, $basicComponents)) {
            return 'cbc:'.$key;
        } else {
            // Default to basic component
            return 'cbc:'.$key;
        }
    }

    /**
     * Add party data (supplier/customer) to XML element.
     */
    private function addPartyData(DOMDocument $xml, DOMElement $parent, array $partyData): void
    {
        $party = $xml->createElement('cac:Party');

        // Industry Classification Code (MSIC) - required for supplier
        if (isset($partyData['IndustryClassificationCode'])) {
            $industryCode = $xml->createElement('cbc:IndustryClassificationCode', $partyData['IndustryClassificationCode']['ID']);
            // Add name attribute from Description if available (LHDN requirement for CF701)
            // Note: LHDN schema uses lowercase 'name' attribute per documentation
            if (isset($partyData['IndustryClassificationCode']['Description'])) {
                $industryCode->setAttribute('name', $partyData['IndustryClassificationCode']['Description']);
            }
            $party->appendChild($industryCode);
        }

        // Party Identification
        if (isset($partyData['PartyIdentification']) && count($partyData['PartyIdentification']) > 0) {
            foreach ($partyData['PartyIdentification'] as $partyId) {
                $partyIdentification = $xml->createElement('cac:PartyIdentification');
                $id = $xml->createElement('cbc:ID', $partyId['ID']['_']);
                $id->setAttribute('schemeID', $partyId['ID']['schemeID']);
                $partyIdentification->appendChild($id);
                $party->appendChild($partyIdentification);
            }
        }

        // Party Name
        if (isset($partyData['PartyName'])) {
            $partyName = $xml->createElement('cac:PartyName');
            $partyName->appendChild($xml->createElement('cbc:Name', $partyData['PartyName']['Name']));
            $party->appendChild($partyName);
        }

        // Postal Address
        if (isset($partyData['PostalAddress'])) {
            $postalAddress = $xml->createElement('cac:PostalAddress');

            // UBL 2.1 PostalAddress can only contain: AddressLine, Country, LocationCoordinate
            // Put all address information into AddressLine elements (LHDN compliant)

            // Collect all address parts for AddressLine elements
            $addressParts = [];

            // City name (required by LHDN)
            if (! empty($partyData['PostalAddress']['CityName'])) {
                $cityName = $xml->createElement('cbc:CityName', $partyData['PostalAddress']['CityName']);
                $postalAddress->appendChild($cityName);
            }

            // Postal zone
            if (! empty($partyData['PostalAddress']['PostalZone'])) {
                $postalZone = $xml->createElement('cbc:PostalZone', $partyData['PostalAddress']['PostalZone']);
                $postalAddress->appendChild($postalZone);
            }

            // State/country subentity
            if (! empty($partyData['PostalAddress']['CountrySubentityCode'])) {
                $countrySubentity = $xml->createElement('cbc:CountrySubentityCode', $partyData['PostalAddress']['CountrySubentityCode']);
                $postalAddress->appendChild($countrySubentity);
            }

            // Main address line
            if (! empty($partyData['PostalAddress']['AddressLine']['Line'])) {
                $addressParts[] = $partyData['PostalAddress']['AddressLine']['Line'];
            }

            // Add each address part as a separate AddressLine
            foreach ($addressParts as $part) {
                $addressLine = $xml->createElement('cac:AddressLine');
                $addressLine->appendChild($xml->createElement('cbc:Line', $part));
                $postalAddress->appendChild($addressLine);
            }

            // Country (this is the only other allowed element in PostalAddress)
            if (isset($partyData['PostalAddress']['Country'])) {
                $country = $xml->createElement('cac:Country');
                $identificationCode = $xml->createElement('cbc:IdentificationCode', $partyData['PostalAddress']['Country']['IdentificationCode']['_']);
                if (isset($partyData['PostalAddress']['Country']['IdentificationCode']['listID'])) {
                    $identificationCode->setAttribute('listID', $partyData['PostalAddress']['Country']['IdentificationCode']['listID']);
                }
                if (isset($partyData['PostalAddress']['Country']['IdentificationCode']['listAgencyID'])) {
                    $identificationCode->setAttribute('listAgencyID', $partyData['PostalAddress']['Country']['IdentificationCode']['listAgencyID']);
                }
                $country->appendChild($identificationCode);
                $postalAddress->appendChild($country);
            }

            $party->appendChild($postalAddress);
        }

        // Party Tax Scheme
        if (isset($partyData['PartyTaxScheme'])) {
            $partyTaxScheme = $xml->createElement('cac:PartyTaxScheme');
            $companyId = $xml->createElement('cbc:CompanyID', $partyData['PartyTaxScheme']['CompanyID']['_']);
            $companyId->setAttribute('schemeID', $partyData['PartyTaxScheme']['CompanyID']['schemeID']);
            $partyTaxScheme->appendChild($companyId);

            $taxScheme = $xml->createElement('cac:TaxScheme');
            $taxSchemeId = $xml->createElement('cbc:ID', $partyData['PartyTaxScheme']['TaxScheme']['ID']['_']);
            if (isset($partyData['PartyTaxScheme']['TaxScheme']['ID']['schemeID'])) {
                $taxSchemeId->setAttribute('schemeID', $partyData['PartyTaxScheme']['TaxScheme']['ID']['schemeID']);
            }
            if (isset($partyData['PartyTaxScheme']['TaxScheme']['ID']['schemeAgencyID'])) {
                $taxSchemeId->setAttribute('schemeAgencyID', $partyData['PartyTaxScheme']['TaxScheme']['ID']['schemeAgencyID']);
            }
            $taxScheme->appendChild($taxSchemeId);
            $partyTaxScheme->appendChild($taxScheme);

            $party->appendChild($partyTaxScheme);
        }

        // Party Legal Entity
        if (isset($partyData['PartyLegalEntity'])) {
            $partyLegalEntity = $xml->createElement('cac:PartyLegalEntity');
            $partyLegalEntity->appendChild($xml->createElement('cbc:RegistrationName', $partyData['PartyLegalEntity']['RegistrationName']));

            if (isset($partyData['PartyLegalEntity']['CompanyID'])) {
                $companyId = $xml->createElement('cbc:CompanyID', $partyData['PartyLegalEntity']['CompanyID']['_']);
                $companyId->setAttribute('schemeID', $partyData['PartyLegalEntity']['CompanyID']['schemeID']);
                $partyLegalEntity->appendChild($companyId);
            }

            $party->appendChild($partyLegalEntity);
        }

        // Contact - Re-enabled as it's required by LHDN
        if (isset($partyData['Contact'])) {
            $contact = $xml->createElement('cac:Contact');
            if (isset($partyData['Contact']['Telephone'])) {
                $contact->appendChild($xml->createElement('cbc:Telephone', $partyData['Contact']['Telephone']));
            }
            if (isset($partyData['Contact']['ElectronicMail'])) {
                $contact->appendChild($xml->createElement('cbc:ElectronicMail', $partyData['Contact']['ElectronicMail']));
            }
            $party->appendChild($contact);
        }

        $parent->appendChild($party);
    }

    /**
     * Add tax total data to XML element.
     */
    private function addTaxTotalData(DOMDocument $xml, DOMElement $parent, array $taxData): void
    {
        // Tax Amount
        $taxAmount = $xml->createElement('cbc:TaxAmount', $taxData['TaxAmount']['_']);
        $taxAmount->setAttribute('currencyID', $taxData['TaxAmount']['currencyID']);
        $parent->appendChild($taxAmount);

        // Tax Subtotal
        if (isset($taxData['TaxSubtotal'])) {
            $taxSubtotal = $xml->createElement('cac:TaxSubtotal');

            $taxableAmount = $xml->createElement('cbc:TaxableAmount', $taxData['TaxSubtotal']['TaxableAmount']['_']);
            $taxableAmount->setAttribute('currencyID', $taxData['TaxSubtotal']['TaxableAmount']['currencyID']);
            $taxSubtotal->appendChild($taxableAmount);

            $taxAmountSub = $xml->createElement('cbc:TaxAmount', $taxData['TaxSubtotal']['TaxAmount']['_']);
            $taxAmountSub->setAttribute('currencyID', $taxData['TaxSubtotal']['TaxAmount']['currencyID']);
            $taxSubtotal->appendChild($taxAmountSub);

            $taxCategory = $xml->createElement('cac:TaxCategory');
            $taxCategory->appendChild($xml->createElement('cbc:ID', $taxData['TaxSubtotal']['TaxCategory']['ID']));
            if (isset($taxData['TaxSubtotal']['TaxCategory']['Percent'])) {
                $taxCategory->appendChild($xml->createElement('cbc:Percent', $taxData['TaxSubtotal']['TaxCategory']['Percent']));
            }

            $taxScheme = $xml->createElement('cac:TaxScheme');
            $taxSchemeId = $xml->createElement('cbc:ID', $taxData['TaxSubtotal']['TaxCategory']['TaxScheme']['ID']['_']);
            if (isset($taxData['TaxSubtotal']['TaxCategory']['TaxScheme']['ID']['schemeID'])) {
                $taxSchemeId->setAttribute('schemeID', $taxData['TaxSubtotal']['TaxCategory']['TaxScheme']['ID']['schemeID']);
            }
            if (isset($taxData['TaxSubtotal']['TaxCategory']['TaxScheme']['ID']['schemeAgencyID'])) {
                $taxSchemeId->setAttribute('schemeAgencyID', $taxData['TaxSubtotal']['TaxCategory']['TaxScheme']['ID']['schemeAgencyID']);
            }
            $taxScheme->appendChild($taxSchemeId);
            $taxCategory->appendChild($taxScheme);

            $taxSubtotal->appendChild($taxCategory);
            $parent->appendChild($taxSubtotal);
        }
    }

    /**
     * Add legal monetary total data to XML element.
     */
    private function addLegalMonetaryTotalData(DOMDocument $xml, DOMElement $parent, array $monetaryData): void
    {
        foreach ($monetaryData as $key => $value) {
            $element = $xml->createElement('cbc:'.$key, $value['_']);
            $element->setAttribute('currencyID', $value['currencyID']);
            $parent->appendChild($element);
        }
    }

    /**
     * Add invoice line data to XML element.
     */
    private function addInvoiceLineData(DOMDocument $xml, DOMElement $parent, array $lineData): void
    {
        // Line ID
        $parent->appendChild($xml->createElement('cbc:ID', $lineData['ID']));

        // Invoiced Quantity
        $invoicedQuantity = $xml->createElement('cbc:InvoicedQuantity', $lineData['InvoicedQuantity']['_']);
        $invoicedQuantity->setAttribute('unitCode', $lineData['InvoicedQuantity']['unitCode']);
        $parent->appendChild($invoicedQuantity);

        // Line Extension Amount
        $lineExtensionAmount = $xml->createElement('cbc:LineExtensionAmount', $lineData['LineExtensionAmount']['_']);
        $lineExtensionAmount->setAttribute('currencyID', $lineData['LineExtensionAmount']['currencyID']);
        $parent->appendChild($lineExtensionAmount);

        // Allowance Charge (if exists) - must come before Item
        if (isset($lineData['AllowanceCharge']) && $lineData['AllowanceCharge']) {
            $allowanceCharge = $xml->createElement('cac:AllowanceCharge');
            $allowanceCharge->appendChild($xml->createElement('cbc:ChargeIndicator', $lineData['AllowanceCharge']['ChargeIndicator'] ? 'true' : 'false'));
            $allowanceCharge->appendChild($xml->createElement('cbc:AllowanceChargeReason', $lineData['AllowanceCharge']['AllowanceChargeReason']));

            $amount = $xml->createElement('cbc:Amount', $lineData['AllowanceCharge']['Amount']['_']);
            $amount->setAttribute('currencyID', $lineData['AllowanceCharge']['Amount']['currencyID']);
            $allowanceCharge->appendChild($amount);

            $parent->appendChild($allowanceCharge);
        }

        // Item
        if (isset($lineData['Item'])) {
            $item = $xml->createElement('cac:Item');
            $item->appendChild($xml->createElement('cbc:Description', $lineData['Item']['Description']));

            // Commodity Classification - Required by LHDN
            if (isset($lineData['Item']['CommodityClassification'])) {
                $commodityClassification = $xml->createElement('cac:CommodityClassification');
                $itemClassificationCode = $xml->createElement('cbc:ItemClassificationCode', $lineData['Item']['CommodityClassification']['ItemClassificationCode']['_']);
                $itemClassificationCode->setAttribute('listID', $lineData['Item']['CommodityClassification']['ItemClassificationCode']['listID']);
                $commodityClassification->appendChild($itemClassificationCode);
                $item->appendChild($commodityClassification);
            }

            // Classified Tax Category
            if (isset($lineData['Item']['ClassifiedTaxCategory'])) {
                $classifiedTaxCategory = $xml->createElement('cac:ClassifiedTaxCategory');
                $classifiedTaxCategory->appendChild($xml->createElement('cbc:ID', $lineData['Item']['ClassifiedTaxCategory']['ID']));

                if (isset($lineData['Item']['ClassifiedTaxCategory']['Percent'])) {
                    $classifiedTaxCategory->appendChild($xml->createElement('cbc:Percent', $lineData['Item']['ClassifiedTaxCategory']['Percent']));
                }

                $taxScheme = $xml->createElement('cac:TaxScheme');
                $taxSchemeId = $xml->createElement('cbc:ID', $lineData['Item']['ClassifiedTaxCategory']['TaxScheme']['ID']['_']);
                if (isset($lineData['Item']['ClassifiedTaxCategory']['TaxScheme']['ID']['schemeID'])) {
                    $taxSchemeId->setAttribute('schemeID', $lineData['Item']['ClassifiedTaxCategory']['TaxScheme']['ID']['schemeID']);
                }
                if (isset($lineData['Item']['ClassifiedTaxCategory']['TaxScheme']['ID']['schemeAgencyID'])) {
                    $taxSchemeId->setAttribute('schemeAgencyID', $lineData['Item']['ClassifiedTaxCategory']['TaxScheme']['ID']['schemeAgencyID']);
                }
                $taxScheme->appendChild($taxSchemeId);
                $classifiedTaxCategory->appendChild($taxScheme);

                $item->appendChild($classifiedTaxCategory);
            }

            $parent->appendChild($item);
        }

        // Price - must come after Item in UBL 2.1
        if (isset($lineData['Price'])) {
            $price = $xml->createElement('cac:Price');

            $priceAmount = $xml->createElement('cbc:PriceAmount', $lineData['Price']['PriceAmount']['_']);
            $priceAmount->setAttribute('currencyID', $lineData['Price']['PriceAmount']['currencyID']);
            $price->appendChild($priceAmount);

            if (isset($lineData['Price']['BaseQuantity'])) {
                $baseQuantity = $xml->createElement('cbc:BaseQuantity', $lineData['Price']['BaseQuantity']['_']);
                $baseQuantity->setAttribute('unitCode', $lineData['Price']['BaseQuantity']['unitCode']);
                $price->appendChild($baseQuantity);
            }

            $parent->appendChild($price);
        }

        // Item Price Extension - Required by LHDN (must come after Price)
        if (isset($lineData['ItemPriceExtension'])) {
            $itemPriceExtension = $xml->createElement('cac:ItemPriceExtension');
            $amount = $xml->createElement('cbc:Amount', $lineData['ItemPriceExtension']['Amount']['_']);
            $amount->setAttribute('currencyID', $lineData['ItemPriceExtension']['Amount']['currencyID']);
            $itemPriceExtension->appendChild($amount);
            $parent->appendChild($itemPriceExtension);
        }
    }

    /**
     * Submit document directly to LHDN API (bypassing SDK authentication issues).
     *
     * @throws Exception
     */
    private function submitDirectly(string $invoiceId, string $ublXml): array
    {
        try {
            // Get access token
            $accessToken = $this->getAccessToken();

            // Prepare document in the same format as the SDK helper
            $document = [
                'format' => 'XML',
                'codeNumber' => $invoiceId,
                'document' => base64_encode($ublXml),
                'documentHash' => hash('sha256', $ublXml),
            ];

            // Submit to LHDN API
            $apiUrl = $this->prodMode
                ? 'https://api.myinvois.hasil.gov.my/api/v1.0/documentsubmissions'
                : 'https://preprod-api.myinvois.hasil.gov.my/api/v1.0/documentsubmissions';

            $guzzle = new Client;
            $response = $guzzle->post($apiUrl, [
                'json' => [
                    'documents' => [$document],
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = $response->getBody()->getContents();

            return json_decode($body, true);

        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            Log::error('Direct API submission failed', [
                'status_code' => $statusCode,
                'response_body' => $body,
            ]);

            // Parse the error response
            $errorData = json_decode($body, true);
            if ($errorData && isset($errorData['error'])) {
                if (is_array($errorData['error'])) {
                    throw new Exception('LHDN API Error: '.$this->formatValidationError($errorData['error']));
                } else {
                    throw new Exception('LHDN API Error: '.$errorData['error']);
                }
            }

            throw new Exception('LHDN API Error (Code: '.$statusCode.'): '.$body);
        }
    }

    /**
     * Get document status from LHDN using the official SDK.
     *
     * @throws Exception
     */
    public function getDocumentStatus(string $uuid): array
    {
        try {
            // Ensure we have access token
            if (! $this->client->getAccessToken()) {
                $this->client->login();
            }

            $response = $this->client->getDocument($uuid);

            if (! $response) {
                throw new Exception('Failed to get document status: Invalid response from LHDN');
            }

            return $response;

        } catch (Exception $e) {
            Log::error('E-Invoice SDK status check error', [
                'uuid' => $uuid,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Cancel an e-Invoice document using the official SDK.
     *
     * @throws Exception
     */
    public function cancelDocument(string $uuid, string $reason): array
    {
        try {
            // Ensure we have access token
            if (! $this->client->getAccessToken()) {
                $this->client->login();
            }

            $response = $this->client->cancelDocument($uuid, $reason);

            if (! $response) {
                throw new Exception('Failed to cancel document: Invalid response from LHDN');
            }

            return $response;

        } catch (Exception $e) {
            Log::error('E-Invoice SDK cancel error', [
                'uuid' => $uuid,
                'reason' => $reason,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get recent submitted documents using the official SDK.
     *
     * @throws Exception
     */
    public function getRecentDocuments(int $pageNo = 1, int $pageSize = 20): array
    {
        try {
            // Ensure we have access token
            if (! $this->client->getAccessToken()) {
                $this->client->login();
            }

            $response = $this->client->getRecentDocuments($pageNo, $pageSize);

            if (! $response) {
                throw new Exception('Failed to get recent documents: Invalid response from LHDN');
            }

            return $response;

        } catch (Exception $e) {
            Log::error('E-Invoice SDK recent documents error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate taxpayer TIN using the official SDK with tenant data.
     *
     * @param  string|null  $tin  Override TIN (optional, uses tenant TIN if not provided)
     * @param  string|null  $idType  Override ID type (optional, uses tenant business_id_type if not provided)
     * @param  string|null  $idValue  Override ID value (optional, uses tenant business_id_value if not provided)
     *
     * @throws Exception
     */
    public function validateTaxpayerTin(?string $tin = null, ?string $idType = null, ?string $idValue = null): array
    {
        try {
            // Use tenant data if parameters not provided
            $tin = $tin ?? $this->getTenantTIN();

            if (! $idType || ! $idValue) {
                $businessId = $this->getTenantBusinessId();
                $idType = $idType ?? $businessId['type'];
                $idValue = $idValue ?? $businessId['value'];
            }

            // Ensure we have access token
            if (! $this->client->getAccessToken()) {
                $this->client->login();
            }

            $response = $this->client->validateTaxPayerTin($tin, $idType, $idValue);

            if (! $response) {
                throw new Exception('Failed to validate taxpayer TIN: Invalid response from LHDN');
            }

            return $response;

        } catch (Exception $e) {
            Log::error('E-Invoice SDK taxpayer validation error for tenant', [
                'tenant_id' => $this->tenant->id ?? 'unknown',
                'tin' => $tin ?? 'unknown',
                'id_type' => $idType ?? 'unknown',
                'id_value' => $idValue ?? 'unknown',
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Validate taxpayer TIN using direct API authentication with tenant data.
     *
     * @param  string|null  $tin  Override TIN (optional, uses tenant TIN if not provided)
     * @param  string|null  $idType  Override ID type (optional, uses tenant business_id_type if not provided)
     * @param  string|null  $idValue  Override ID value (optional, uses tenant business_id_value if not provided)
     *
     * @throws Exception
     */
    public function validateTaxpayerTinDirect(?string $tin = null, ?string $idType = null, ?string $idValue = null): bool
    {
        // Use tenant data if parameters not provided
        $tin = $tin ?? $this->getTenantTIN();

        if (! $idType || ! $idValue) {
            $businessId = $this->getTenantBusinessId();
            $idType = $idType ?? $businessId['type'];
            $idValue = $idValue ?? $businessId['value'];
        }

        $accessToken = $this->getAccessToken();
        $apiUrl = $this->prodMode
            ? 'https://api.myinvois.hasil.gov.my/api/v1.0/taxpayer/validate'
            : 'https://preprod-api.myinvois.hasil.gov.my/api/v1.0/taxpayer/validate';

        $guzzle = new Client;
        try {
            $response = $guzzle->get($apiUrl."/$tin", [
                'query' => [
                    'tin' => $tin,
                    'idType' => $idType,
                    'idValue' => $idValue,
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);
            $body = $response->getBody()->getContents();

            return $body ? false : true;
        } catch (ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            throw new Exception('TIN validation failed (HTTP '.$statusCode.'): '.$body);
        } catch (Exception $e) {
            throw new Exception('TIN validation failed: '.$e->getMessage());
        }
    }

    /**
     * Get document types available in the system.
     *
     * @throws Exception
     */
    public function getDocumentTypes(): array
    {
        try {
            // Ensure we have access token
            if (! $this->client->getAccessToken()) {
                $this->client->login();
            }

            $response = $this->client->getAllDocumentTypes();

            if (! $response) {
                throw new Exception('Failed to get document types: Invalid response from LHDN');
            }

            return $response;

        } catch (Exception $e) {
            Log::error('E-Invoice SDK document types error', [
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get the raw MyInvoisClient for advanced operations.
     */
    public function getClient(): MyInvoisClient
    {
        return $this->client;
    }

    /**
     * Format LHDN validation error for user-friendly display.
     */
    private function formatValidationError(array $error): string
    {
        // Handle different error types
        if (isset($error['code'])) {
            switch ($error['code']) {
                case '401':
                    return 'Authentication Error: Invalid credentials or expired token. Please check your LHDN API configuration.';
                case '403':
                    return "Access Denied: You don't have permission to submit invoices. Please check your LHDN account permissions.";
                case '429':
                    return 'Rate Limit Exceeded: Too many requests sent to LHDN. Please wait a moment and try again.';
                case '500':
                    return 'LHDN Server Error: The LHDN system is experiencing technical difficulties. Please try again later.';
            }
        }

        $message = "E-Invoice Validation Error:\n\n";

        // Main error message
        if (isset($error['message'])) {
            $message .= 'Error: '.$error['message']."\n";
        }

        if (isset($error['code'])) {
            $message .= 'Code: '.$error['code']."\n";
        }

        if (isset($error['target'])) {
            $message .= 'Target: '.$error['target']."\n";
        }

        // Handle detailed validation errors
        if (isset($error['details']) && is_array($error['details'])) {
            $message .= "\nDetailed Issues:\n";

            foreach ($error['details'] as $index => $detail) {
                $detailNum = $index + 1;
                $message .= "\n{$detailNum}. ";

                if (isset($detail['message'])) {
                    // Clean up the validation message for better readability
                    $detailMessage = $detail['message'];

                    // Handle specific LHDN validation codes
                    if (isset($detail['code'])) {
                        switch ($detail['code']) {
                            case 'CF358':
                                $message .= "Supplier TIN Validation Failed:\n   {$detailMessage}\n   Please ensure your supplier TIN is correctly configured and registered with LHDN.";

                                continue 2;
                            case 'CF359':
                                $message .= "Customer TIN Validation Failed:\n   {$detailMessage}\n   Please verify the customer's identification number (TIN/NRIC/Passport).";

                                continue 2;
                        }
                    }

                    // Check for supplier identification errors in message
                    if (stripos($detailMessage, 'identification number is not valid') !== false &&
                        stripos($detailMessage, 'supplier') !== false) {
                        $message .= "Supplier TIN Validation Failed:\n   {$detailMessage}\n   ";
                        $message .= "Check:\n";
                        $message .= "   - Supplier TIN is set in .env (EINVOICE_SUPPLIER_TIN)\n";
                        $message .= "   - TIN is registered with LHDN MyInvois\n";
                        $message .= '   - TIN matches your LHDN account credentials';

                        continue;
                    }

                    // Extract element name from complex validation messages
                    if (preg_match("/The element '(\w+)'/", $detailMessage, $matches)) {
                        $elementName = $matches[1];
                        $message .= "Issue with '{$elementName}' element:\n   ";
                    }

                    // Simplify common validation messages
                    $detailMessage = str_replace(
                        [
                            'in namespace \'urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2\'',
                            'in namespace \'urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2\'',
                            'List of possible elements expected:',
                            'has invalid child element',
                        ],
                        [
                            '',
                            '',
                            'Expected elements:',
                            'contains invalid element',
                        ],
                        $detailMessage
                    );

                    $message .= trim($detailMessage);
                }

                if (isset($detail['target'])) {
                    $message .= "\n   Field: ".$detail['target'];
                }

                if (isset($detail['propertyPath'])) {
                    $message .= "\n   Path: ".$detail['propertyPath'];
                }
            }
        }

        $message .= "\n\nPlease check your invoice data and try again.";
        $message .= "\nIf the problem persists, contact technical support.";

        return $message;
    }

    /**
     * Validate invoice data before submission.
     *
     * @throws Exception
     */
    private function validateInvoiceData(array $data): void
    {
        // Check if this is UBL format (nested structure) or flat format
        if (isset($data['Invoice'])) {
            $invoiceData = $data['Invoice'];

            // Validate UBL structure required fields
            $required = [
                'ID' => 'Invoice number',
                'IssueDate' => 'Issue date',
                'IssueTime' => 'Issue time',
                'AccountingSupplierParty' => 'Supplier information',
                'AccountingCustomerParty' => 'Customer information',
                'InvoiceLine' => 'Invoice line items',
            ];

            foreach ($required as $field => $label) {
                if (empty($invoiceData[$field])) {
                    throw new Exception("Missing required field: {$label}");
                }
            }
        } else {
            // Validate flat structure (legacy support)
            $required = [
                'invoice_number' => 'Invoice number',
                'issue_date' => 'Issue date',
                'issue_time' => 'Issue time',
                'supplier' => 'Supplier information',
                'customer' => 'Customer information',
                'invoice_lines' => 'Invoice line items',
            ];

            foreach ($required as $field => $label) {
                if (empty($data[$field])) {
                    throw new Exception("Missing required field: {$label}");
                }
            }
        }

        // Skip detailed validation for UBL format as it's already structured correctly
        if (isset($data['Invoice'])) {
            // Basic validation for UBL format
            $invoiceData = $data['Invoice'];

            // Validate that required nested structures exist
            if (empty($invoiceData['AccountingSupplierParty']['Party'])) {
                throw new Exception('Missing supplier party information');
            }

            if (empty($invoiceData['AccountingCustomerParty']['Party'])) {
                throw new Exception('Missing customer party information');
            }

            if (empty($invoiceData['InvoiceLine']) || ! is_array($invoiceData['InvoiceLine'])) {
                throw new Exception('Invoice must have at least one line item');
            }

            // Validate date format
            try {
                $date = Carbon::parse($invoiceData['IssueDate']);
                $today = Carbon::today();
                if ($date->gt($today)) {
                    throw new Exception('Invoice date cannot be in the future (issue date: '.$date->format('Y-m-d').', today: '.$today->format('Y-m-d').')');
                }
            } catch (Exception $e) {
                if (strpos($e->getMessage(), 'Invoice date cannot be in the future') !== false) {
                    throw $e; // Re-throw our custom message
                }
                throw new Exception('Invalid issue date format: '.$e->getMessage());
            }

            return; // Skip legacy validation for UBL format
        }

        // Legacy validation for flat format
        // Validate supplier required fields
        $supplierRequired = ['name', 'tin', 'msic_code', 'address'];
        foreach ($supplierRequired as $field) {
            if (empty($data['supplier'][$field])) {
                throw new Exception("Missing required supplier field: {$field}");
            }
        }

        // Validate customer required fields
        $customerRequired = ['name', 'address'];
        foreach ($customerRequired as $field) {
            if (empty($data['customer'][$field])) {
                throw new Exception("Missing required customer field: {$field}");
            }
        }

        // Validate invoice lines
        if (empty($data['invoice_lines']) || ! is_array($data['invoice_lines'])) {
            throw new Exception('Invoice must have at least one line item');
        }

        foreach ($data['invoice_lines'] as $index => $line) {
            $lineRequired = ['description', 'quantity', 'unit_price', 'line_total'];
            foreach ($lineRequired as $field) {
                if (! isset($line[$field])) {
                    throw new Exception("Missing required field '{$field}' in invoice line ".($index + 1));
                }
            }
        }

        // Validate date format
        try {
            $date = Carbon::parse($data['issue_date']);
            $today = Carbon::today();
            if ($date->gt($today)) {
                throw new Exception('Invoice date cannot be in the future (issue date: '.$date->format('Y-m-d').', today: '.$today->format('Y-m-d').')');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Invoice date cannot be in the future') !== false) {
                throw $e; // Re-throw our custom message
            }
            throw new Exception('Invalid issue date format: '.$e->getMessage());
        }
    }

    /**
     * Validate supplier TIN before submission using tenant data.
     *
     * @param  string|null  $tin  Override TIN (optional, uses tenant TIN if not provided)
     *
     * @throws Exception
     */
    private function validateSupplierTIN(?string $tin = null): void
    {
        try {
            $tin = $tin ?? $this->getTenantTIN();
            $businessId = $this->getTenantBusinessId();

            Log::info('Validating supplier TIN for tenant', [
                'tenant_id' => $this->tenant->id,
                'tenant_name' => $this->tenant->name,
                'tin' => $tin,
                'business_id_type' => $businessId['type'],
            ]);

            // For production, validate with LHDN using tenant's business ID
            $isValid = $this->validateTaxpayerTinDirect($tin, $businessId['type'], $businessId['value']);

            if (! $isValid) {
                throw new Exception("TIN {$tin} with {$businessId['type']} {$businessId['value']} is not registered or not active in LHDN MyInvois system");
            }

            Log::info('Supplier TIN validation successful for tenant', [
                'tenant_id' => $this->tenant->id,
                'tin' => $tin,
            ]);

        } catch (Exception $e) {
            Log::error('Supplier TIN validation failed for tenant', [
                'tenant_id' => $this->tenant->id ?? 'unknown',
                'tin' => $tin ?? 'unknown',
                'error' => $e->getMessage(),
            ]);
            throw new Exception("TIN Validation Failed: {$e->getMessage()}");
        }
    }

    /**
     * Test TIN validation directly using tenant data.
     *
     * @param  string|null  $tin  Override TIN (optional, uses tenant TIN if not provided)
     * @param  string|null  $idType  Override ID type (optional, uses tenant business_id_type if not provided)
     * @param  string|null  $idValue  Override ID value (optional, uses tenant business_id_value if not provided)
     *
     * @throws Exception
     */
    public function testTINValidation(?string $tin = null, ?string $idType = null, ?string $idValue = null): bool
    {
        try {
            // Use tenant data if parameters not provided
            $tin = $tin ?? $this->getTenantTIN();

            if (! $idType || ! $idValue) {
                $businessId = $this->getTenantBusinessId();
                $idType = $idType ?? $businessId['type'];
                $idValue = $idValue ?? $businessId['value'];
            }

            $accessToken = $this->getAccessToken();

            $apiUrl = $this->prodMode
                ? 'https://api.myinvois.hasil.gov.my/api/v1.0/taxpayer/validate'
                : 'https://preprod-api.myinvois.hasil.gov.my/api/v1.0/taxpayer/validate';

            $guzzle = new Client;
            $response = $guzzle->get($apiUrl."/{$tin}", [
                'query' => [
                    'idType' => $idType,
                    'idValue' => $idValue,
                ],
                'headers' => [
                    'Authorization' => 'Bearer '.$accessToken,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $body = $response->getBody()->getContents();
            $result = $body ? false : true;

            Log::info('TIN Validation Test Result for tenant', [
                'tenant_id' => $this->tenant->id ?? 'unknown',
                'tenant_name' => $this->tenant->name ?? 'unknown',
                'tin' => $tin,
                'id_type' => $idType,
                'response' => $result,
            ]);

            return $result;

        } catch (Exception $e) {
            Log::error('TIN Validation Test Failed for tenant', [
                'tenant_id' => $this->tenant->id ?? 'unknown',
                'tin' => $tin ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
