-- -------------------------------------------------------------
-- TablePlus 6.7.4(642)
--
-- https://tableplus.com/
--
-- Database: kindygo
-- Generation Time: 2025-12-15 15:54:17.2460
-- -------------------------------------------------------------


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


CREATE TABLE `1_activity_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `log_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `subject_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `causer_id` bigint unsigned DEFAULT NULL,
  `causer_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `batch_uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_log_log_name_index` (`log_name`),
  KEY `subject` (`subject_id`,`subject_type`),
  KEY `causer` (`causer_id`,`causer_type`)
) ENGINE=InnoDB AUTO_INCREMENT=580177 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_attendance` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `attendance_date` datetime DEFAULT NULL,
  `child_id` int unsigned DEFAULT NULL,
  `attendance` int unsigned DEFAULT NULL,
  `user_id` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `child_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `preschool_id` int unsigned DEFAULT NULL,
  `classroom_id` int unsigned DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_audits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `event` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `auditable_id` bigint unsigned NOT NULL,
  `old_values` text COLLATE utf8mb4_unicode_ci,
  `new_values` text COLLATE utf8mb4_unicode_ci,
  `url` text COLLATE utf8mb4_unicode_ci,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(1023) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tags` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `audits_auditable_type_auditable_id_index` (`auditable_type`,`auditable_id`),
  KEY `audits_user_id_user_type_index` (`user_id`,`user_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_bookings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL,
  `child_id` int NOT NULL,
  `invoice_id` int NOT NULL,
  `amount` int NOT NULL,
  `is_redeemed` tinyint(1) NOT NULL,
  `expire_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=151 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_campuses` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssm_comp_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssm_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `add_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `add_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_child` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL,
  `fullname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `mykid_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cert_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `pob` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dob` datetime DEFAULT NULL,
  `language` int unsigned DEFAULT NULL,
  `post_of_child` int unsigned DEFAULT NULL,
  `status` int unsigned DEFAULT NULL,
  `product` int unsigned DEFAULT NULL,
  `gender` int unsigned DEFAULT NULL,
  `race` int unsigned DEFAULT NULL,
  `religion` int unsigned DEFAULT NULL,
  `allergies` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `others` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family_clinic` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `family_clinic_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diseases` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `discount` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `languages` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alumni` int unsigned DEFAULT NULL,
  `classroom_id` int DEFAULT NULL,
  `preschool_id` int DEFAULT NULL,
  `year` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `is_registered` date DEFAULT NULL,
  `other_products` text COLLATE utf8mb4_unicode_ci,
  `december_product_id` int DEFAULT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `passport_sized_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `immunization_card` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `child_birth_certificate` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `child_mykid_no_unique` (`mykid_no`)
) ENGINE=InnoDB AUTO_INCREMENT=2456 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_child_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `_token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `child_id` int DEFAULT NULL,
  `child_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `delivered_by` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `arrived_in` timestamp NULL DEFAULT NULL,
  `child_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_infant` tinyint(1) DEFAULT '0',
  `enough_milk` tinyint(1) DEFAULT '0',
  `sleep_well` tinyint(1) DEFAULT '0',
  `is_showered` tinyint(1) DEFAULT '0',
  `clear_from_flu` tinyint(1) DEFAULT '0',
  `fresh_diapers` tinyint(1) DEFAULT '0',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_child_medical_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `allergy` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_child_merchandise` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `product_id` int NOT NULL,
  `purchase_date` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_child_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_children_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_by` int NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3524 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_children_log_meta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `children_log_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `dH91i9r2_children_log_meta_children_log_id_foreign` (`children_log_id`),
  KEY `children_log_meta_key_index` (`key`),
  CONSTRAINT `dH91i9r2_children_log_meta_children_log_id_foreign` FOREIGN KEY (`children_log_id`) REFERENCES `1_children_log` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=45459 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_classroom` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preschool_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `child_years` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `child_products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `capacity` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=296 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_classroom_meta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classroom_id` int unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `qATHoVon_classroom_meta_classroom_id_foreign` (`classroom_id`),
  KEY `classroom_meta_key_index` (`key`),
  CONSTRAINT `qATHoVon_classroom_meta_classroom_id_foreign` FOREIGN KEY (`classroom_id`) REFERENCES `1_classroom` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_classroom_user` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int DEFAULT NULL,
  `classroom_id` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_classwork` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `body` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `availability` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `classrooms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=77 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_classwork_meta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `classwork_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mojoxOYJ_classwork_meta_classwork_id_foreign` (`classwork_id`),
  KEY `classwork_meta_key_index` (`key`),
  CONSTRAINT `mojoxOYJ_classwork_meta_classwork_id_foreign` FOREIGN KEY (`classwork_id`) REFERENCES `1_classwork` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=183 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_dashboard_report` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'summary',
  `report_month` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `report_year` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `include_bad_debt` tinyint(1) NOT NULL DEFAULT '1',
  `preschool` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `preschool_status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (`id`),
  KEY `dashboard_report_type_report_month_report_year_index` (`type`,`report_month`,`report_year`)
) ENGINE=InnoDB AUTO_INCREMENT=10417 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_dashboard_report_meta` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `dashboard_report_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` longtext COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `McAcYiMS_dashboard_report_meta_dashboard_report_id_foreign` (`dashboard_report_id`),
  KEY `dashboard_report_meta_key_index` (`key`),
  CONSTRAINT `McAcYiMS_dashboard_report_meta_dashboard_report_id_foreign` FOREIGN KEY (`dashboard_report_id`) REFERENCES `1_dashboard_report` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1204614 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_events` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `events_date` date DEFAULT NULL,
  `participations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_events_detail` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `events_id` int NOT NULL,
  `participations` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=82 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_gender` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_health` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `child_id` int DEFAULT NULL,
  `body_temperature` decimal(8,2) DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'child_health_screening',
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `symptoms` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `created_by` bigint NOT NULL,
  `preschool_id` bigint NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=15385 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_health_meta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `health_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `health_meta_health_id_index` (`health_id`),
  KEY `health_meta_key_index` (`key`),
  CONSTRAINT `3ApbREuz_health_meta_health_id_foreign` FOREIGN KEY (`health_id`) REFERENCES `1_health` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6388 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_invoice_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `product_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_invoice_ot_stayin` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int unsigned NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `K9wastWu_invoice_ot_stayin_invoice_id_foreign` (`invoice_id`),
  CONSTRAINT `K9wastWu_invoice_ot_stayin_invoice_id_foreign` FOREIGN KEY (`invoice_id`) REFERENCES `1_invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_invoice_payment` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_id` int NOT NULL,
  `parent_id` int unsigned DEFAULT NULL,
  `amount` double unsigned DEFAULT NULL,
  `payment_method` int unsigned DEFAULT NULL,
  `balance` double unsigned DEFAULT NULL,
  `transaction_id` int NOT NULL,
  `payment_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_invoices` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent` int unsigned DEFAULT NULL,
  `preschool` int unsigned DEFAULT NULL,
  `transaction_id` int unsigned DEFAULT NULL,
  `invoice_date` datetime DEFAULT NULL,
  `payment_method` int unsigned DEFAULT NULL,
  `payment_status` int unsigned DEFAULT NULL,
  `immediate` datetime DEFAULT NULL,
  `future_date` datetime DEFAULT NULL,
  `start_date` datetime DEFAULT NULL,
  `end_date` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `child_id` int DEFAULT NULL,
  `locked` tinyint(1) NOT NULL DEFAULT '0',
  `price` int DEFAULT NULL,
  `billplz_pending_bill_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billplz_collection_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `deposit` int NOT NULL DEFAULT '0',
  `last_mailgun_message_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `due_date` date DEFAULT NULL,
  `is_pos_invoice` tinyint(1) NOT NULL DEFAULT '0',
  `is_enrollment` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `invoices_payment_status_index` (`payment_status`),
  KEY `invoices_invoice_date_index` (`invoice_date`),
  KEY `invoices_is_enrollment_index` (`is_enrollment`),
  KEY `invoices_parent_index` (`parent`),
  KEY `invoices_preschool_index` (`preschool`),
  KEY `invoices_deleted_at_index` (`deleted_at`),
  KEY `invoices_payment_status_invoice_date_index` (`payment_status`,`invoice_date`),
  KEY `invoices_is_enrollment_invoice_date_index` (`is_enrollment`,`invoice_date`)
) ENGINE=InnoDB AUTO_INCREMENT=41805 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_media` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `collection_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `file_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `disk` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversions_disk` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size` bigint unsigned NOT NULL,
  `manipulations` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `custom_properties` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `generated_conversions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `responsive_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `order_column` int unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `media_uuid_unique` (`uuid`),
  KEY `media_model_type_model_id_index` (`model_type`,`model_id`),
  KEY `media_order_column_index` (`order_column`),
  CONSTRAINT `1_media_chk_1` CHECK (json_valid(`manipulations`)),
  CONSTRAINT `1_media_chk_2` CHECK (json_valid(`custom_properties`)),
  CONSTRAINT `1_media_chk_3` CHECK (json_valid(`generated_conversions`)),
  CONSTRAINT `1_media_chk_4` CHECK (json_valid(`responsive_images`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=173 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_model_has_permissions` (
  `permission_id` int unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`model_id`,`model_type`),
  KEY `model_has_permissions_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `mc24Xcjt_model_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `1_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_model_has_roles` (
  `role_id` int unsigned NOT NULL,
  `model_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `model_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`model_id`,`model_type`),
  KEY `model_has_roles_model_id_model_type_index` (`model_id`,`model_type`),
  CONSTRAINT `jafAxLeW_model_has_roles_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `1_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_notification` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `notification_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=1758 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_otPackage` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_password_resets` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  KEY `password_resets_email_index` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_payment_method` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_payment_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_permissions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `abilities` text COLLATE utf8mb4_unicode_ci,
  `expires_at` timestamp NULL DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`)
) ENGINE=InnoDB AUTO_INCREMENT=27802 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_preschool` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ssm_comp_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ssm_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `add_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `add_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `short_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `campus_id` int DEFAULT NULL,
  `capacity` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_preschool_subjects` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preschool_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_product` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `preschool` longtext COLLATE utf8mb4_unicode_ci COMMENT '(DC2Type:json)',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `price` int NOT NULL DEFAULT '0',
  `price_history` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `product_type` int NOT NULL DEFAULT '0',
  `recurrence` int DEFAULT NULL,
  `recurrence_months` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `year` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `product_product_type_index` (`product_type`)
) ENGINE=InnoDB AUTO_INCREMENT=489 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_product_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `categories_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_product_meta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `mQKpI7dg_product_meta_product_id_foreign` (`product_id`),
  KEY `product_meta_key_index` (`key`),
  CONSTRAINT `mQKpI7dg_product_meta_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `1_product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1666 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_product_other_items` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `child_id` int NOT NULL,
  `amount_stayin` int unsigned DEFAULT NULL,
  `amount_ot` int unsigned DEFAULT NULL,
  `dates` date DEFAULT NULL,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_product_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descriptions` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_quotation_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int unsigned NOT NULL,
  `child_id` int unsigned DEFAULT NULL,
  `quotation_id` bigint unsigned DEFAULT NULL,
  `product_id` int unsigned DEFAULT NULL,
  `preschool_id` int unsigned DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` int NOT NULL DEFAULT '0',
  `bill_date` datetime DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '0',
  `discount_amount` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotation_transactions_parent_id_foreign` (`parent_id`),
  KEY `quotation_transactions_child_id_foreign` (`child_id`),
  KEY `quotation_transactions_quotation_id_foreign` (`quotation_id`),
  KEY `quotation_transactions_product_id_foreign` (`product_id`),
  KEY `quotation_transactions_preschool_id_foreign` (`preschool_id`),
  CONSTRAINT `quotation_transactions_child_id_foreign` FOREIGN KEY (`child_id`) REFERENCES `1_child` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_transactions_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `1_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_transactions_preschool_id_foreign` FOREIGN KEY (`preschool_id`) REFERENCES `1_preschool` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_transactions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `1_product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotation_transactions_quotation_id_foreign` FOREIGN KEY (`quotation_id`) REFERENCES `1_quotations` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_quotations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `quotation_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parent_id` int unsigned NOT NULL,
  `preschool_id` int unsigned NOT NULL,
  `date` datetime NOT NULL,
  `price` int NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `quotations_parent_id_foreign` (`parent_id`),
  KEY `quotations_preschool_id_foreign` (`preschool_id`),
  CONSTRAINT `quotations_parent_id_foreign` FOREIGN KEY (`parent_id`) REFERENCES `1_users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `quotations_preschool_id_foreign` FOREIGN KEY (`preschool_id`) REFERENCES `1_preschool` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_race` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_recurrence` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_religion` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_report` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `reportcard_date` datetime NOT NULL,
  `child_id` int NOT NULL,
  `classroom_id` int NOT NULL,
  `subject_id` int NOT NULL,
  `height` int NOT NULL,
  `weight` int NOT NULL,
  `reportedby_id` int NOT NULL,
  `verifiedby_id` int NOT NULL,
  `verified_status` int NOT NULL,
  `receivedby_id` int NOT NULL,
  `received_status` int NOT NULL,
  `suggestions` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_report_detail` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int NOT NULL,
  `form_id` int NOT NULL,
  `field_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `field_value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_report_form` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_role_has_permissions` (
  `permission_id` int unsigned NOT NULL,
  `role_id` int unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `4VcGxB6L_role_has_permissions_role_id_foreign` (`role_id`),
  CONSTRAINT `4VcGxB6L_role_has_permissions_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `1_roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `MpQeBxog_role_has_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `1_permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_role_hierarchy` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fh87JeTs_role_hierarchy_role_id_foreign` (`role_id`),
  CONSTRAINT `fh87JeTs_role_hierarchy_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `1_roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_roles` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `guard_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_seatings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `preschool_id` int DEFAULT NULL,
  `year` year NOT NULL,
  `capacity` int NOT NULL,
  `baby` int DEFAULT NULL,
  `toddler` int DEFAULT NULL,
  `three_yo` int DEFAULT NULL,
  `four_yo` int DEFAULT NULL,
  `five_yo` int DEFAULT NULL,
  `six_yo` int DEFAULT NULL,
  `seven_yo` int DEFAULT NULL,
  `eight_yo` int DEFAULT NULL,
  `nine_yo` int DEFAULT NULL,
  `ten_yo` int DEFAULT NULL,
  `eleven_yo` int DEFAULT NULL,
  `twelve_yo` int DEFAULT NULL,
  `thirteen_yo` int DEFAULT NULL,
  `fourteen_yo` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `1yo_const` int DEFAULT NULL,
  `1yo_zero` int NOT NULL DEFAULT '0',
  `2yo_const` int DEFAULT NULL,
  `2yo_zero` int NOT NULL DEFAULT '0',
  `3yo_const` int DEFAULT NULL,
  `3yo_zero` int NOT NULL DEFAULT '0',
  `4yo_const` int DEFAULT NULL,
  `4yo_zero` int NOT NULL DEFAULT '0',
  `5yo_const` int DEFAULT NULL,
  `5yo_zero` int NOT NULL DEFAULT '0',
  `6yo_const` int DEFAULT NULL,
  `6yo_zero` int NOT NULL DEFAULT '0',
  `7yo_const` int DEFAULT NULL,
  `7yo_zero` int NOT NULL DEFAULT '0',
  `8yo_const` int DEFAULT NULL,
  `8yo_zero` int NOT NULL DEFAULT '0',
  `9yo_const` int DEFAULT NULL,
  `9yo_zero` int NOT NULL DEFAULT '0',
  `10yo_const` int DEFAULT NULL,
  `10yo_zero` int NOT NULL DEFAULT '0',
  `11yo_const` int DEFAULT NULL,
  `11yo_zero` int NOT NULL DEFAULT '0',
  `12yo_const` int DEFAULT NULL,
  `12yo_zero` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_setting` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billplz_collection_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billplz_x_signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billplz_api_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'tenant_setting',
  `ssm_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_setting_meta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `setting_id` bigint unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `Cf19Cv3Q_setting_meta_setting_id_foreign` (`setting_id`),
  KEY `setting_meta_key_index` (`key`),
  CONSTRAINT `Cf19Cv3Q_setting_meta_setting_id_foreign` FOREIGN KEY (`setting_id`) REFERENCES `1_setting` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_state` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_subject` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `desc` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_term_and_condition_agreement` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `terms_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `term_and_condition_agreement_user_id_foreign` (`user_id`),
  KEY `term_and_condition_agreement_terms_id_foreign` (`terms_id`),
  CONSTRAINT `term_and_condition_agreement_terms_id_foreign` FOREIGN KEY (`terms_id`) REFERENCES `1_terms_and_conditions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `term_and_condition_agreement_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `1_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=466 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_term_and_condition_preschools` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `preschool_id` int unsigned NOT NULL,
  `terms_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `term_and_condition_preschools_preschool_id_foreign` (`preschool_id`),
  KEY `term_and_condition_preschools_terms_id_foreign` (`terms_id`),
  CONSTRAINT `term_and_condition_preschools_preschool_id_foreign` FOREIGN KEY (`preschool_id`) REFERENCES `1_preschool` (`id`) ON DELETE CASCADE,
  CONSTRAINT `term_and_condition_preschools_terms_id_foreign` FOREIGN KEY (`terms_id`) REFERENCES `1_terms_and_conditions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=40 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_terms_and_conditions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` longtext COLLATE utf8mb4_unicode_ci,
  `status` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_transactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int NOT NULL,
  `child_id` int DEFAULT NULL,
  `invoice_id` int DEFAULT NULL,
  `product_id` int DEFAULT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `preschool_id` int DEFAULT NULL,
  `prev_invoice_id` int DEFAULT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `remarks` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `amount` int DEFAULT NULL,
  `bill_date` datetime DEFAULT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `waived_products` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `payment_method` int DEFAULT NULL,
  `paid_amount` int DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `billplz_bill_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billplz_collection_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payment_by` int DEFAULT NULL,
  `payment_slip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_amount` int DEFAULT NULL,
  `is_discount` tinyint(1) NOT NULL DEFAULT '0',
  `overtime_stayin` tinyint(1) NOT NULL DEFAULT '0',
  `product_type` int DEFAULT NULL,
  `product_recurring` int DEFAULT NULL,
  `paid_status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `transactions_invoice_id_index` (`invoice_id`),
  KEY `transactions_parent_id_index` (`parent_id`),
  KEY `transactions_child_id_index` (`child_id`),
  KEY `transactions_product_id_index` (`product_id`),
  KEY `transactions_type_index` (`type`),
  KEY `transactions_paid_status_index` (`paid_status`),
  KEY `transactions_deleted_at_index` (`deleted_at`),
  KEY `transactions_invoice_type_paid_index` (`invoice_id`,`type`,`paid_status`),
  KEY `transactions_invoice_type_index` (`invoice_id`,`type`),
  KEY `transactions_parent_type_index` (`parent_id`,`type`),
  KEY `transactions_created_at_index` (`created_at`),
  KEY `transactions_product_type_recurring_index` (`product_type`,`product_recurring`)
) ENGINE=InnoDB AUTO_INCREMENT=132901 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_user_device_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `token` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `push_engine` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'web_token',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `access_token_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_device_tokens_user_id_foreign` (`user_id`),
  KEY `user_device_tokens_access_token_id_foreign` (`access_token_id`),
  CONSTRAINT `user_device_tokens_access_token_id_foreign` FOREIGN KEY (`access_token_id`) REFERENCES `1_personal_access_tokens` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_device_tokens_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `1_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=495 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_user_status` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_users` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `campus` int DEFAULT NULL,
  `preschool` int DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `classroom_id` bigint DEFAULT NULL,
  `user_token` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `user_mykad_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_passport_size_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_mykad_image` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_passport_size_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_immunization_card` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `guardians` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin,
  `add_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `add_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `apple_signin` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_add_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_add_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_phone` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_postcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `company_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_by_month` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `discount_by_month_amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_by_month_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `discount_by_month_year` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `discount_histories` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `ic_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_discount_amount` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monthly_discount_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `other_preschools` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `phone_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `previous_id` int NOT NULL DEFAULT '0',
  `previous_preschool_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_add_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_add_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_company_add_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_company_add_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_company_city` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_company_postcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_company_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_email` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_ic_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_occupation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_phone_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_postcode` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `spouse_state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_condition_status` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `terms_user_information` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_status` int DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2661 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `1_users_meta` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'null',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pw40dfs2_users_meta_user_id_foreign` (`user_id`),
  KEY `users_meta_key_index` (`key`),
  CONSTRAINT `pw40dfs2_users_meta_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `1_users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52046 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;