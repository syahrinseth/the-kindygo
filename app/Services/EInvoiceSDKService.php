<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Klsheng\Myinvois\MyInvoisClient;
use Klsheng\Myinvois\Helper\MyInvoisHelper;

class EInvoiceSDKService
{
    private MyInvoisClient $client;

    public function __construct(?string $tin = null)
    {
        $clientId = config('einvoice.client_id') ?? '';
        $clientSecret = config('einvoice.client_secret') ?? '';
        $prodMode = config('einvoice.environment') === 'production';
        
        $this->client = new MyInvoisClient($clientId, $clientSecret, $prodMode);
        
        // Note: TIN is not set on the client but is included in the invoice document data
        // The TIN parameter is kept for future use or logging purposes
    }

    /**
     * Submit invoice to LHDN e-Invoice API using the official SDK.
     *
     * @param array $invoiceData
     * @return array
     * @throws \Exception
     */
    public function submitInvoice(array $invoiceData): array
    {
        try {
            // First login to get access token
            $this->client->login();
            
            // Log critical supplier information for debugging
            Log::info('E-Invoice supplier validation check', [
                'invoice_id' => $invoiceData['Invoice']['ID'] ?? 'unknown',
                'supplier_tin' => $invoiceData['Invoice']['AccountingSupplierParty']['Party']['PartyIdentification']['ID']['_'] ?? 'NOT_SET',
                'supplier_scheme' => $invoiceData['Invoice']['AccountingSupplierParty']['Party']['PartyIdentification']['ID']['schemeID'] ?? 'NOT_SET',
                'supplier_name' => $invoiceData['Invoice']['AccountingSupplierParty']['Party']['PartyName']['Name'] ?? 'NOT_SET',
                'env_supplier_tin' => config('einvoice.supplier_tin'),
                'env_myinvois_tin' => config('einvoice.myinvois_tin')
            ]);
            
            // Log the invoice data structure for debugging
            Log::info('Invoice data structure before submission', [
                'invoice_data' => $invoiceData
            ]);
            
            // The MyInvois SDK expects the document to be in proper UBL 2.1 XML format
            // Convert the array to proper UBL XML first
            $ublXml = $this->convertToUBLXml($invoiceData);
            
            // Log a portion of the generated XML for debugging
            $dom = new \DOMDocument();
            $dom->loadXML($ublXml);
            $dom->formatOutput = true;
            $formattedXml = $dom->saveXML();
            
            // Extract supplier information from XML for verification
            if (preg_match('/<cac:AccountingSupplierParty>.*?<\/cac:AccountingSupplierParty>/s', $formattedXml, $supplierMatches)) {
                Log::info('E-Invoice XML supplier section', [
                    'supplier_xml' => $supplierMatches[0]
                ]);
            }
            
            // Get the invoice ID for the code number
            $invoiceId = $invoiceData['Invoice']['ID'] ?? 'UNKNOWN';

            // Format the document properly using the SDK helper
            // The SDK expects XML content, not JSON
            $document = MyInvoisHelper::getSubmitDocument($invoiceId, $ublXml);
            
            Log::info('Formatted document for submission', [
                'document_structure' => [
                    'format' => $document['format'],
                    'codeNumber' => $document['codeNumber'],
                    'hasDocument' => isset($document['document']),
                    'hasHash' => isset($document['documentHash'])
                ]
            ]);
            
            $response = $this->client->submitDocument([$document]);
            
            Log::info('E-Invoice submission response', [
                'response' => $response
            ]);
            
            // Check for different types of errors in response
            if (isset($response['error'])) {
                $error = $response['error'];
                Log::error('E-Invoice API error', [
                    'error' => $error,
                    'invoice_id' => $invoiceData['Invoice']['ID'] ?? 'unknown'
                ]);
                
                // Format error message based on error type
                $errorMessage = $this->formatValidationError($error);
                throw new \Exception($errorMessage);
            }
            
            // Check for submission errors (when submission fails)
            if (isset($response['rejectedDocuments']) && !empty($response['rejectedDocuments'])) {
                $rejectedDoc = $response['rejectedDocuments'][0];
                Log::error('E-Invoice document rejected', [
                    'rejected_document' => $rejectedDoc,
                    'invoice_id' => $invoiceData['Invoice']['ID'] ?? 'unknown'
                ]);
                
                $errorMessage = "Invoice was rejected by LHDN:\n";
                if (isset($rejectedDoc['error'])) {
                    $errorMessage .= $this->formatValidationError($rejectedDoc['error']);
                } else {
                    $errorMessage .= "Document rejected for unknown reasons. Please check the invoice data.";
                }
                throw new \Exception($errorMessage);
            }
            
            if (!$response || !isset($response['submissionUid'])) {
                Log::error('E-Invoice submission failed using SDK', [
                    'response' => $response
                ]);
                throw new \Exception('Failed to submit invoice: Invalid response from LHDN');
            }

            return $response;
            
        } catch (\GuzzleHttp\Exception\ConnectException $e) {
            Log::error('E-Invoice connection error', [
                'message' => $e->getMessage()
            ]);
            throw new \Exception('Unable to connect to LHDN e-Invoice system. Please check your internet connection and try again.');
            
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            Log::error('E-Invoice client error', [
                'status_code' => $statusCode,
                'response_body' => $body,
                'message' => $e->getMessage()
            ]);
            
            // Handle specific HTTP error codes
            switch ($statusCode) {
                case 400:
                    throw new \Exception('Bad Request: The invoice data is invalid. Please check your invoice details and try again.');
                case 401:
                    throw new \Exception('Authentication Failed: Invalid LHDN credentials. Please check your API configuration.');
                case 403:
                    throw new \Exception('Access Denied: You do not have permission to submit invoices. Please contact your administrator.');
                case 404:
                    throw new \Exception('Service Not Found: The LHDN e-Invoice service is unavailable. Please try again later.');
                case 429:
                    throw new \Exception('Rate Limit Exceeded: Too many requests. Please wait a moment and try again.');
                default:
                    throw new \Exception('LHDN API Error (Code: ' . $statusCode . '): ' . $e->getMessage());
            }
            
        } catch (\GuzzleHttp\Exception\ServerException $e) {
            Log::error('E-Invoice server error', [
                'message' => $e->getMessage()
            ]);
            throw new \Exception('LHDN Server Error: The e-Invoice system is experiencing technical difficulties. Please try again later.');
            
        } catch (\Exception $e) {
            Log::error('E-Invoice SDK submission error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // If it's already a formatted error message, don't wrap it
            if (strpos($e->getMessage(), 'E-Invoice') === 0 || 
                strpos($e->getMessage(), 'Authentication') === 0 ||
                strpos($e->getMessage(), 'Access Denied') === 0) {
                throw $e;
            }
            
            throw new \Exception('E-Invoice submission failed: ' . $e->getMessage());
        }
    }

    /**
     * Convert invoice data array to proper UBL 2.1 XML format.
     *
     * @param array $invoiceData
     * @return string
     */
    private function convertToUBLXml(array $invoiceData): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');
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
     *
     * @param array $data
     * @param \DOMElement $parent
     * @param \DOMDocument $xml
     */
    private function arrayToXml(array $data, \DOMElement $parent, \DOMDocument $xml): void
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
                        $element->nodeValue = htmlspecialchars((string)$value['_']);
                        foreach ($value as $attrKey => $attrValue) {
                            if ($attrKey !== '_' && !is_array($attrValue)) {
                                $element->setAttribute($attrKey, (string)$attrValue);
                            }
                        }
                    } else {
                        $this->arrayToXml($value, $element, $xml);
                    }
                }
            } else {
                // Create leaf element
                $elementName = $this->getProperElementName($key);
                $element = $xml->createElement($elementName, htmlspecialchars((string)$value));
                $parent->appendChild($element);
            }
        }
    }

    /**
     * Get proper element name with correct namespace prefix.
     *
     * @param string $key
     * @return string
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
            'PartyIdentification'
        ];
        
        // Basic components (simple elements)
        $basicComponents = [
            'ID', 'IssueDate', 'DueDate', 'InvoiceTypeCode', 'DocumentCurrencyCode',
            'LineExtensionAmount', 'TaxExclusiveAmount', 'TaxInclusiveAmount',
            'PayableAmount', 'CompanyID', 'Name', 'StreetName', 'CityName',
            'PostalZone', 'IdentificationCode', 'TaxAmount', 'TaxableAmount',
            'Percent', 'InvoicedQuantity', 'PriceAmount', 'Description',
            'BaseQuantity', 'ChargeIndicator', 'AllowanceChargeReason', 'Amount',
            'Telephone', 'ElectronicMail', 'RegistrationName', 'AllowanceTotalAmount'
        ];
        
        if (in_array($key, $aggregateComponents)) {
            return 'cac:' . $key;
        } elseif (in_array($key, $basicComponents)) {
            return 'cbc:' . $key;
        } else {
            // Default to basic component
            return 'cbc:' . $key;
        }
    }

    /**
     * Add party data (supplier/customer) to XML element.
     */
    private function addPartyData(\DOMDocument $xml, \DOMElement $parent, array $partyData): void
    {
        $party = $xml->createElement('cac:Party');
        
        // Party Identification
        if (isset($partyData['PartyIdentification'])) {
            $partyIdentification = $xml->createElement('cac:PartyIdentification');
            $id = $xml->createElement('cbc:ID', $partyData['PartyIdentification']['ID']['_']);
            $id->setAttribute('schemeID', $partyData['PartyIdentification']['ID']['schemeID']);
            $partyIdentification->appendChild($id);
            $party->appendChild($partyIdentification);
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
            
            // Use AddressLine for street addresses
            if (isset($partyData['PostalAddress']['AddressLine']['Line'])) {
                $addressLine = $xml->createElement('cac:AddressLine');
                $addressLine->appendChild($xml->createElement('cbc:Line', $partyData['PostalAddress']['AddressLine']['Line']));
                $postalAddress->appendChild($addressLine);
            }
            
            // Add city, postal code, and state as additional AddressLine elements
            if (isset($partyData['PostalAddress']['CityName'])) {
                $cityAddressLine = $xml->createElement('cac:AddressLine');
                $cityAddressLine->appendChild($xml->createElement('cbc:Line', $partyData['PostalAddress']['CityName']));
                $postalAddress->appendChild($cityAddressLine);
            }
            
            if (isset($partyData['PostalAddress']['PostalZone'])) {
                $postalAddressLine = $xml->createElement('cac:AddressLine');
                $postalAddressLine->appendChild($xml->createElement('cbc:Line', $partyData['PostalAddress']['PostalZone']));
                $postalAddress->appendChild($postalAddressLine);
            }
            
            if (isset($partyData['PostalAddress']['CountrySubentityCode'])) {
                $stateAddressLine = $xml->createElement('cac:AddressLine');
                $stateAddressLine->appendChild($xml->createElement('cbc:Line', $partyData['PostalAddress']['CountrySubentityCode']));
                $postalAddress->appendChild($stateAddressLine);
            }
            
            // Country
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
        
        // Contact - Temporarily removed due to MyInvois validation issues
        // if (isset($partyData['Contact'])) {
        //     $contact = $xml->createElement('cac:Contact');
        //     if (isset($partyData['Contact']['ElectronicMail'])) {
        //         $contact->appendChild($xml->createElement('cbc:ElectronicMail', $partyData['Contact']['ElectronicMail']));
        //     }
        //     $party->appendChild($contact);
        // }
        
        $parent->appendChild($party);
    }

    /**
     * Add tax total data to XML element.
     */
    private function addTaxTotalData(\DOMDocument $xml, \DOMElement $parent, array $taxData): void
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
    private function addLegalMonetaryTotalData(\DOMDocument $xml, \DOMElement $parent, array $monetaryData): void
    {
        foreach ($monetaryData as $key => $value) {
            $element = $xml->createElement('cbc:' . $key, $value['_']);
            $element->setAttribute('currencyID', $value['currencyID']);
            $parent->appendChild($element);
        }
    }

    /**
     * Add invoice line data to XML element.
     */
    private function addInvoiceLineData(\DOMDocument $xml, \DOMElement $parent, array $lineData): void
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
        
        // Allowance Charge (if exists)
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
        
        // Price
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
    }

    /**
     * Get document status from LHDN using the official SDK.
     *
     * @param string $uuid
     * @return array
     * @throws \Exception
     */
    public function getDocumentStatus(string $uuid): array
    {
        try {
            // Ensure we have access token
            if (!$this->client->getAccessToken()) {
                $this->client->login();
            }
            
            $response = $this->client->getDocument($uuid);
            
            if (!$response) {
                throw new \Exception('Failed to get document status: Invalid response from LHDN');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('E-Invoice SDK status check error', [
                'uuid' => $uuid,
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Cancel an e-Invoice document using the official SDK.
     *
     * @param string $uuid
     * @param string $reason
     * @return array
     * @throws \Exception
     */
    public function cancelDocument(string $uuid, string $reason): array
    {
        try {
            // Ensure we have access token
            if (!$this->client->getAccessToken()) {
                $this->client->login();
            }
            
            $response = $this->client->cancelDocument($uuid, $reason);
            
            if (!$response) {
                throw new \Exception('Failed to cancel document: Invalid response from LHDN');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('E-Invoice SDK cancel error', [
                'uuid' => $uuid,
                'reason' => $reason,
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get recent submitted documents using the official SDK.
     *
     * @param int $pageNo
     * @param int $pageSize
     * @return array
     * @throws \Exception
     */
    public function getRecentDocuments(int $pageNo = 1, int $pageSize = 20): array
    {
        try {
            // Ensure we have access token
            if (!$this->client->getAccessToken()) {
                $this->client->login();
            }
            
            $response = $this->client->getRecentDocuments($pageNo, $pageSize);
            
            if (!$response) {
                throw new \Exception('Failed to get recent documents: Invalid response from LHDN');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('E-Invoice SDK recent documents error', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Validate taxpayer TIN using the official SDK.
     *
     * @param string $tin
     * @param string $idType
     * @param string $idValue
     * @return array
     * @throws \Exception
     */
    public function validateTaxpayerTin(string $tin, string $idType, string $idValue): array
    {
        try {
            // Ensure we have access token
            if (!$this->client->getAccessToken()) {
                $this->client->login();
            }
            
            $response = $this->client->validateTaxPayerTin($tin, $idType, $idValue);
            
            if (!$response) {
                throw new \Exception('Failed to validate taxpayer TIN: Invalid response from LHDN');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('E-Invoice SDK taxpayer validation error', [
                'tin' => $tin,
                'id_type' => $idType,
                'id_value' => $idValue,
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Search taxpayer TIN using the official SDK.
     *
     * @param string $taxPayerName
     * @param string $idType
     * @param string $idValue
     * @return array
     * @throws \Exception
     */
    public function searchTaxpayerTin(string $taxPayerName, string $idType, string $idValue): array
    {
        try {
            // Ensure we have access token
            if (!$this->client->getAccessToken()) {
                $this->client->login();
            }
            
            $response = $this->client->searchTaxPayerTin($taxPayerName, $idType, $idValue);
            
            if (!$response) {
                throw new \Exception('Failed to search taxpayer TIN: Invalid response from LHDN');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('E-Invoice SDK taxpayer search error', [
                'taxpayer_name' => $taxPayerName,
                'id_type' => $idType,
                'id_value' => $idValue,
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get document types available in the system.
     *
     * @return array
     * @throws \Exception
     */
    public function getDocumentTypes(): array
    {
        try {
            // Ensure we have access token
            if (!$this->client->getAccessToken()) {
                $this->client->login();
            }
            
            $response = $this->client->getAllDocumentTypes();
            
            if (!$response) {
                throw new \Exception('Failed to get document types: Invalid response from LHDN');
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('E-Invoice SDK document types error', [
                'message' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get the raw MyInvoisClient for advanced operations.
     *
     * @return MyInvoisClient
     */
    public function getClient(): MyInvoisClient
    {
        return $this->client;
    }
    
    /**
     * Format LHDN validation error for user-friendly display.
     *
     * @param array $error
     * @return string
     */
    private function formatValidationError(array $error): string
    {
        // Handle different error types
        if (isset($error['code'])) {
            switch ($error['code']) {
                case '401':
                    return "Authentication Error: Invalid credentials or expired token. Please check your LHDN API configuration.";
                case '403':
                    return "Access Denied: You don't have permission to submit invoices. Please check your LHDN account permissions.";
                case '429':
                    return "Rate Limit Exceeded: Too many requests sent to LHDN. Please wait a moment and try again.";
                case '500':
                    return "LHDN Server Error: The LHDN system is experiencing technical difficulties. Please try again later.";
            }
        }
        
        $message = "E-Invoice Validation Error:\n\n";
        
        // Main error message
        if (isset($error['message'])) {
            $message .= "Error: " . $error['message'] . "\n";
        }
        
        if (isset($error['code'])) {
            $message .= "Code: " . $error['code'] . "\n";
        }
        
        if (isset($error['target'])) {
            $message .= "Target: " . $error['target'] . "\n";
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
                        $message .= "   - TIN matches your LHDN account credentials";
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
                            'has invalid child element'
                        ],
                        [
                            '',
                            '',
                            'Expected elements:',
                            'contains invalid element'
                        ],
                        $detailMessage
                    );
                    
                    $message .= trim($detailMessage);
                }
                
                if (isset($detail['target'])) {
                    $message .= "\n   Field: " . $detail['target'];
                }
                
                if (isset($detail['propertyPath'])) {
                    $message .= "\n   Path: " . $detail['propertyPath'];
                }
            }
        }
        
        $message .= "\n\nPlease check your invoice data and try again.";
        $message .= "\nIf the problem persists, contact technical support.";
        
        return $message;
    }
}
