<?php

namespace App\Enums;

enum EInvoiceClassificationCode: string
{
    case BREASTFEEDING_EQUIPMENT = '001';
    case CHILD_CARE_CENTRES_AND_KINDERGARTENS_FEES = '002';
    case COMPUTER_SMARTPHONE_OR_TABLET = '003';
    case CONSOLIDATED_E_INVOICE = '004';
    case CONSTRUCTION_MATERIALS = '005';
    case DISBURSEMENT = '006';
    case DONATION = '007';
    case E_COMMERCE_TO_BUYER = '008';
    case E_COMMERCE_SELF_BILLED = '009';
    case EDUCATION_FEES = '010';
    case GOODS_ON_CONSIGNMENT_CONSIGNOR = '011';
    case GOODS_ON_CONSIGNMENT_CONSIGNEE = '012';
    case GYM_MEMBERSHIP = '013';
    case INSURANCE_EDUCATION_AND_MEDICAL = '014';
    case INSURANCE_TAKAFUL_OR_LIFE = '015';
    case INTEREST_AND_FINANCING_EXPENSES = '016';
    case INTERNET_SUBSCRIPTION = '017';
    case LAND_AND_BUILDING = '018';
    case MEDICAL_EXAMINATION_LEARNING_DISABILITIES = '019';
    case MEDICAL_EXAMINATION_OR_VACCINATION = '020';
    case MEDICAL_EXPENSES_SERIOUS_DISEASES = '021';
    case OTHERS = '022';
    case PETROLEUM_OPERATIONS = '023';
    case PRIVATE_RETIREMENT_SCHEME = '024';
    case MOTOR_VEHICLE = '025';
    case SUBSCRIPTION_BOOKS_JOURNALS = '026';
    case REIMBURSEMENT = '027';
    case RENTAL_OF_MOTOR_VEHICLE = '028';
    case EV_CHARGING_FACILITIES = '029';
    case REPAIR_AND_MAINTENANCE = '030';
    case RESEARCH_AND_DEVELOPMENT = '031';
    case FOREIGN_INCOME = '032';
    case SELF_BILLED_BETTING_AND_GAMING = '033';
    case SELF_BILLED_IMPORTATION_OF_GOODS = '034';
    case SELF_BILLED_IMPORTATION_OF_SERVICES = '035';
    case SELF_BILLED_OTHERS = '036';
    case SELF_BILLED_MONETARY_PAYMENT_TO_AGENTS = '037';
    case SPORTS_EQUIPMENT = '038';
    case SUPPORTING_EQUIPMENT_FOR_DISABLED = '039';
    case VOLUNTARY_CONTRIBUTION_PROVIDENT_FUND = '040';
    case DENTAL_EXAMINATION_OR_TREATMENT = '041';
    case FERTILITY_TREATMENT = '042';
    case TREATMENT_AND_HOME_CARE_NURSING = '043';
    case VOUCHERS_GIFT_CARDS_LOYALTY_POINTS = '044';
    case SELF_BILLED_NON_MONETARY_PAYMENT_TO_AGENTS = '045';

    /**
     * Get the description for the classification code.
     */
    public function getDescription(): string
    {
        return match ($this) {
            self::BREASTFEEDING_EQUIPMENT => 'Breastfeeding equipment',
            self::CHILD_CARE_CENTRES_AND_KINDERGARTENS_FEES => 'Child care centres and kindergartens fees',
            self::COMPUTER_SMARTPHONE_OR_TABLET => 'Computer, smartphone or tablet',
            self::CONSOLIDATED_E_INVOICE => 'Consolidated e-Invoice',
            self::CONSTRUCTION_MATERIALS => 'Construction materials (as specified under Fourth Schedule of the Lembaga Pembangunan Industri Pembinaan Malaysia Act 1994)',
            self::DISBURSEMENT => 'Disbursement',
            self::DONATION => 'Donation',
            self::E_COMMERCE_TO_BUYER => 'e-Commerce - e-Invoice to buyer / purchaser',
            self::E_COMMERCE_SELF_BILLED => 'e-Commerce - Self-billed e-Invoice to seller, logistics, etc.',
            self::EDUCATION_FEES => 'Education fees',
            self::GOODS_ON_CONSIGNMENT_CONSIGNOR => 'Goods on consignment (Consignor)',
            self::GOODS_ON_CONSIGNMENT_CONSIGNEE => 'Goods on consignment (Consignee)',
            self::GYM_MEMBERSHIP => 'Gym membership',
            self::INSURANCE_EDUCATION_AND_MEDICAL => 'Insurance - Education and medical benefits',
            self::INSURANCE_TAKAFUL_OR_LIFE => 'Insurance - Takaful or life insurance',
            self::INTEREST_AND_FINANCING_EXPENSES => 'Interest and financing expenses',
            self::INTERNET_SUBSCRIPTION => 'Internet subscription',
            self::LAND_AND_BUILDING => 'Land and building',
            self::MEDICAL_EXAMINATION_LEARNING_DISABILITIES => 'Medical examination for learning disabilities and early intervention or rehabilitation treatments of learning disabilities',
            self::MEDICAL_EXAMINATION_OR_VACCINATION => 'Medical examination or vaccination expenses',
            self::MEDICAL_EXPENSES_SERIOUS_DISEASES => 'Medical expenses for serious diseases',
            self::OTHERS => 'Others',
            self::PETROLEUM_OPERATIONS => 'Petroleum operations (as defined in Petroleum (Income Tax) Act 1967)',
            self::PRIVATE_RETIREMENT_SCHEME => 'Private retirement scheme or deferred annuity scheme',
            self::MOTOR_VEHICLE => 'Motor vehicle',
            self::SUBSCRIPTION_BOOKS_JOURNALS => 'Subscription of books / journals / magazines / newspapers / other similar publications',
            self::REIMBURSEMENT => 'Reimbursement',
            self::RENTAL_OF_MOTOR_VEHICLE => 'Rental of motor vehicle',
            self::EV_CHARGING_FACILITIES => 'EV charging facilities (Installation, rental, sale / purchase or subscription fees)',
            self::REPAIR_AND_MAINTENANCE => 'Repair and maintenance',
            self::RESEARCH_AND_DEVELOPMENT => 'Research and development',
            self::FOREIGN_INCOME => 'Foreign income',
            self::SELF_BILLED_BETTING_AND_GAMING => 'Self-billed - Betting and gaming',
            self::SELF_BILLED_IMPORTATION_OF_GOODS => 'Self-billed - Importation of goods',
            self::SELF_BILLED_IMPORTATION_OF_SERVICES => 'Self-billed - Importation of services',
            self::SELF_BILLED_OTHERS => 'Self-billed - Others',
            self::SELF_BILLED_MONETARY_PAYMENT_TO_AGENTS => 'Self-billed - Monetary payment to agents, dealers or distributors',
            self::SPORTS_EQUIPMENT => 'Sports equipment, rental / entry fees for sports facilities, registration in sports competition or sports training fees imposed by associations / sports clubs / companies registered with the Sports Commissioner or Companies Commission of Malaysia and carrying out sports activities as listed under the Sports Development Act 1997',
            self::SUPPORTING_EQUIPMENT_FOR_DISABLED => 'Supporting equipment for disabled person',
            self::VOLUNTARY_CONTRIBUTION_PROVIDENT_FUND => 'Voluntary contribution to approved provident fund',
            self::DENTAL_EXAMINATION_OR_TREATMENT => 'Dental examination or treatment',
            self::FERTILITY_TREATMENT => 'Fertility treatment',
            self::TREATMENT_AND_HOME_CARE_NURSING => 'Treatment and home care nursing, daycare centres and residential care centers',
            self::VOUCHERS_GIFT_CARDS_LOYALTY_POINTS => 'Vouchers, gift cards, loyalty points, etc',
            self::SELF_BILLED_NON_MONETARY_PAYMENT_TO_AGENTS => 'Self-billed - Non-monetary payment to agents, dealers or distributors',
        };
    }

    /**
     * Get all classification codes as an array for select options.
     */
    public static function toSelectArray(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->value.' - '.$case->getDescription();
        }

        return $options;
    }

    /**
     * Get the most relevant classification code for childcare services.
     */
    public static function getChildcareDefault(): self
    {
        return self::CHILD_CARE_CENTRES_AND_KINDERGARTENS_FEES;
    }

    /**
     * Check if the classification code is for childcare services.
     */
    public function isChildcareRelated(): bool
    {
        return $this === self::CHILD_CARE_CENTRES_AND_KINDERGARTENS_FEES;
    }

    /**
     * Get classification codes that are commonly used for self-billing.
     */
    public static function getSelfBilledCodes(): array
    {
        return [
            self::SELF_BILLED_BETTING_AND_GAMING,
            self::SELF_BILLED_IMPORTATION_OF_GOODS,
            self::SELF_BILLED_IMPORTATION_OF_SERVICES,
            self::SELF_BILLED_OTHERS,
            self::SELF_BILLED_MONETARY_PAYMENT_TO_AGENTS,
            self::SELF_BILLED_NON_MONETARY_PAYMENT_TO_AGENTS,
        ];
    }

    /**
     * Check if the classification code is for self-billing scenarios.
     */
    public function isSelfBilled(): bool
    {
        return in_array($this, self::getSelfBilledCodes());
    }
}
