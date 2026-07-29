<?php

namespace App\Services\Migration;

use App\Enums\ChildEnrolmentStatus;
use App\Enums\ChildEnrolmentType;
use App\Enums\ChildStatus;
use App\Enums\Gateway;
use App\Enums\InvoiceStatus;
use App\Enums\MalaysianState;
use App\Enums\PaymentStatus;
use App\Enums\ProductType;

class StatusMapper
{
    /**
     * Map legacy child status (int) to ChildEnrolmentStatus enum.
     *
     * @see docs/migration/02-DATA-MAPPING.md section 3.2
     */
    public static function childStatusToEnrolmentStatus(int $legacyStatus): ChildEnrolmentStatus
    {
        return match ($legacyStatus) {
            1, 2 => ChildEnrolmentStatus::ACTIVE,
            3 => ChildEnrolmentStatus::COMPLETED,
            4 => ChildEnrolmentStatus::PENDING,
            5 => ChildEnrolmentStatus::LEGACY_FUTURE_RETURN,
            6 => ChildEnrolmentStatus::LEGACY_SUSPENDED,
            7 => ChildEnrolmentStatus::LEGACY_REGISTERED,
            8 => ChildEnrolmentStatus::LEGACY_UNREGISTERED,
            9 => ChildEnrolmentStatus::LEGACY_TRAIL_1_MONTH,
            10 => ChildEnrolmentStatus::CANCELLED,
            11 => ChildEnrolmentStatus::LEGACY_TRAIL_5_DAYS,
            default => ChildEnrolmentStatus::INACTIVE,
        };
    }

    /**
     * Map legacy child status (int) to ChildStatus enum for tenant_child pivot.
     *
     * @see docs/migration/02-DATA-MAPPING.md section 3.3
     */
    public static function childStatusToChildStatus(int $legacyStatus): ChildStatus
    {
        return match ($legacyStatus) {
            1 => ChildStatus::NEW,
            2 => ChildStatus::RETURN,
            3 => ChildStatus::ALUMNI,
            4 => ChildStatus::FUTURE,
            5 => ChildStatus::FUTURE_RETURN,
            6 => ChildStatus::SUSPENDED,
            7 => ChildStatus::REGISTERED,
            8, 10 => ChildStatus::INACTIVE,
            9 => ChildStatus::TRIAL_1_MONTH,
            11 => ChildStatus::TRIAL_5_DAYS,
            default => ChildStatus::INACTIVE,
        };
    }

    /**
     * Map legacy invoice payment_status (int) to InvoiceStatus enum.
     *
     * @see docs/migration/02-DATA-MAPPING.md section 5.1
     */
    public static function invoiceStatus(int $legacyStatus): InvoiceStatus
    {
        return match ($legacyStatus) {
            1 => InvoiceStatus::PENDING,
            2 => InvoiceStatus::OVERDUE,
            3 => InvoiceStatus::PARTIALLY_PAID,
            4 => InvoiceStatus::PENDING,
            5 => InvoiceStatus::CANCELLED,
            6 => InvoiceStatus::PENDING,
            7 => InvoiceStatus::PAID,
            8 => InvoiceStatus::REFUNDED,
            9 => InvoiceStatus::CANCELLED,
            10 => InvoiceStatus::PAID,
            11 => InvoiceStatus::PAID,
            12 => InvoiceStatus::DRAFT,
            default => InvoiceStatus::PENDING,
        };
    }

    /**
     * Map legacy payment_method (int) to Gateway enum.
     * Methods 2,3,5,6,7,8 → BANK_TRANSFER; 1 → BILLPLZ; 4,9 → CHIP.
     *
     * @see docs/migration/02-DATA-MAPPING.md section 5.3
     */
    public static function paymentGateway(int $legacyMethod): Gateway
    {
        return match ($legacyMethod) {
            1 => Gateway::BILLPLZ,
            2, 3, 5, 6, 7, 8 => Gateway::BANK_TRANSFER,
            4, 9 => Gateway::CHIP,
            default => Gateway::BANK_TRANSFER,
        };
    }

    /**
     * Map legacy paid_status (tinyint) to PaymentStatus enum.
     */
    public static function paymentStatus(int $legacyStatus): PaymentStatus
    {
        return match ($legacyStatus) {
            1 => PaymentStatus::PAID,
            0 => PaymentStatus::UNPAID,
            default => PaymentStatus::PENDING,
        };
    }

    /**
     * Map legacy product_type (int) to ProductType enum.
     *
     * @see docs/migration/02-DATA-MAPPING.md section 4.1
     */
    public static function productType(int $legacyType): ProductType
    {
        return match ($legacyType) {
            1 => ProductType::PROGRAMME,
            2 => ProductType::EVENT,
            3 => ProductType::MERCHANDISE,
            4 => ProductType::OVERTIME,
            5 => ProductType::STAYIN,
            6 => ProductType::SERVICE,
            7 => ProductType::DEPOSIT,
            default => ProductType::OTHERS,
        };
    }

    /**
     * Map legacy state ID (int) to MalaysianState enum value.
     * Note: Legacy IDs don't match enum values directly.
     *
     * @see docs/migration/02-DATA-MAPPING.md section 2.4.1
     */
    public static function state(?int $legacyStateId): ?string
    {
        if ($legacyStateId === null) {
            return null;
        }

        $mapping = match ($legacyStateId) {
            1 => MalaysianState::JOHOR,
            2 => MalaysianState::KEDAH,
            3 => MalaysianState::KELANTAN,
            4 => MalaysianState::MELAKA,
            5 => MalaysianState::NEGERI_SEMBILAN,
            6 => MalaysianState::PAHANG,
            7 => MalaysianState::PULAU_PINANG,
            8 => MalaysianState::PERAK,
            9 => MalaysianState::PERLIS,
            10 => MalaysianState::SELANGOR,
            11 => MalaysianState::TERENGGANU,
            12 => MalaysianState::WP_KUALA_LUMPUR,
            13 => MalaysianState::WP_LABUAN,
            14 => MalaysianState::WP_PUTRAJAYA,
            15 => MalaysianState::SABAH,
            16 => MalaysianState::SARAWAK,
            default => null,
        };

        return $mapping?->value;
    }

    /**
     * Map legacy gender (int) to string.
     */
    public static function gender(?int $legacyGender): ?string
    {
        return match ($legacyGender) {
            1 => 'female',
            2 => 'male',
            default => null,
        };
    }

    /**
     * Map legacy race (int) to string name.
     */
    public static function race(?int $legacyRaceId): ?string
    {
        return match ($legacyRaceId) {
            1 => 'Malay',
            2 => 'Chinese',
            3 => 'Indian',
            4 => 'Orang Asli',
            5 => 'Sabahan',
            6 => 'Sarawakian',
            default => null,
        };
    }

    /**
     * Map legacy religion (int) to string name.
     */
    public static function religion(?int $legacyReligionId): ?string
    {
        return match ($legacyReligionId) {
            1 => 'Islam',
            2 => 'Buddha',
            3 => 'Other',
            default => null,
        };
    }

    /**
     * Map legacy enrolment type to ChildEnrolmentType enum.
     */
    public static function enrolmentType(?string $legacyType): ChildEnrolmentType
    {
        return match ($legacyType) {
            'trial' => ChildEnrolmentType::TRIAL,
            'regular', null => ChildEnrolmentType::FULL_TIME,
            default => ChildEnrolmentType::FULL_TIME,
        };
    }

    /**
     * Map legacy centre status string to valid enum value (active, inactive, pending).
     * Preserves original legacy status in meta_data for reference.
     */
    public static function centreStatus(?string $legacyStatus): string
    {
        return match ($legacyStatus) {
            'active' => 'active',
            'close', 'closed' => 'inactive',
            'licensee' => 'active',
            'pending' => 'pending',
            default => 'pending',
        };
    }

    /**
     * Map legacy user_status (int) to descriptive name.
     */
    public static function userStatusName(int $legacyStatus): string
    {
        return match ($legacyStatus) {
            1 => 'Normal',
            2 => 'Staff',
            3 => 'Family',
            default => 'Unknown',
        };
    }

    /**
     * Determine if a child status is considered "active" for date_start generation.
     */
    public static function isActiveChildStatus(int $legacyStatus): bool
    {
        return in_array($legacyStatus, [1, 2, 7, 9, 11]);
    }
}
