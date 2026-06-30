/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `activity_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `activity_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Machine-readable activity action key.',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional human-readable activity description.',
  `meta` json DEFAULT NULL COMMENT 'Structured contextual activity metadata.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `activity_logs_action_idx` (`action`),
  KEY `activity_logs_user_idx` (`user_id`),
  KEY `activity_logs_created_at_idx` (`created_at`),
  KEY `activity_logs_user_created_at_idx` (`user_id`,`created_at`),
  CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique cache entry key.',
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serialized cached value.',
  `expiration` bigint NOT NULL COMMENT 'Unix expiration timestamp.',
  PRIMARY KEY (`key`),
  KEY `cache_expiration_idx` (`expiration`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique distributed lock key.',
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Current lock owner identifier.',
  `expiration` bigint NOT NULL COMMENT 'Unix expiration timestamp.',
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_idx` (`expiration`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `call_events`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `call_events` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `call_log_id` bigint unsigned NOT NULL,
  `provider_event_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `occurred_at` timestamp NULL DEFAULT NULL,
  `sequence` int unsigned DEFAULT NULL,
  `payload` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_events_tenant_id_provider_id_provider_event_id_unique` (`tenant_id`,`provider_id`,`provider_event_id`),
  UNIQUE KEY `call_events_uuid_unique` (`uuid`),
  KEY `call_events_call_log_id_occurred_at_index` (`call_log_id`,`occurred_at`),
  CONSTRAINT `call_events_call_log_id_foreign` FOREIGN KEY (`call_log_id`) REFERENCES `call_logs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `call_events_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `call_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `call_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provider_call_id` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correlation_id` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `idempotency_key` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `direction` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `disposition` varchar(24) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_normalized_number` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_number` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `to_normalized_number` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `caller_user_id` bigint unsigned DEFAULT NULL,
  `callee_user_id` bigint unsigned DEFAULT NULL,
  `caller_extension_id` bigint unsigned DEFAULT NULL,
  `callee_extension_id` bigint unsigned DEFAULT NULL,
  `caller_phone_number_id` bigint unsigned DEFAULT NULL,
  `callee_phone_number_id` bigint unsigned DEFAULT NULL,
  `caller_contact_id` bigint unsigned DEFAULT NULL,
  `callee_contact_id` bigint unsigned DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `ringing_at` timestamp NULL DEFAULT NULL,
  `answered_at` timestamp NULL DEFAULT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `ringing_seconds` int unsigned NOT NULL DEFAULT '0',
  `talk_seconds` int unsigned NOT NULL DEFAULT '0',
  `billable_seconds` int unsigned NOT NULL DEFAULT '0',
  `total_seconds` int unsigned NOT NULL DEFAULT '0',
  `hangup_cause` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_code` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failure_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `billing_status` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unrated',
  `rated_at` timestamp NULL DEFAULT NULL,
  `currency` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cost_amount` decimal(12,4) DEFAULT NULL,
  `recording_available` tinyint(1) NOT NULL DEFAULT '0',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_logs_tenant_id_provider_id_provider_call_id_unique` (`tenant_id`,`provider_id`,`provider_call_id`),
  UNIQUE KEY `call_logs_uuid_unique` (`uuid`),
  KEY `call_logs_caller_user_id_foreign` (`caller_user_id`),
  KEY `call_logs_callee_user_id_foreign` (`callee_user_id`),
  KEY `call_logs_caller_extension_id_foreign` (`caller_extension_id`),
  KEY `call_logs_callee_extension_id_foreign` (`callee_extension_id`),
  KEY `call_logs_caller_phone_number_id_foreign` (`caller_phone_number_id`),
  KEY `call_logs_callee_phone_number_id_foreign` (`callee_phone_number_id`),
  KEY `call_logs_caller_contact_id_foreign` (`caller_contact_id`),
  KEY `call_logs_callee_contact_id_foreign` (`callee_contact_id`),
  KEY `call_logs_tenant_id_started_at_index` (`tenant_id`,`started_at`),
  KEY `call_logs_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `call_logs_tenant_id_direction_index` (`tenant_id`,`direction`),
  KEY `call_logs_tenant_id_caller_user_id_index` (`tenant_id`,`caller_user_id`),
  KEY `call_logs_tenant_id_callee_user_id_index` (`tenant_id`,`callee_user_id`),
  KEY `call_logs_tenant_id_from_normalized_number_index` (`tenant_id`,`from_normalized_number`),
  KEY `call_logs_tenant_id_to_normalized_number_index` (`tenant_id`,`to_normalized_number`),
  CONSTRAINT `call_logs_callee_contact_id_foreign` FOREIGN KEY (`callee_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_callee_extension_id_foreign` FOREIGN KEY (`callee_extension_id`) REFERENCES `extensions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_callee_phone_number_id_foreign` FOREIGN KEY (`callee_phone_number_id`) REFERENCES `phone_numbers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_callee_user_id_foreign` FOREIGN KEY (`callee_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_caller_contact_id_foreign` FOREIGN KEY (`caller_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_caller_extension_id_foreign` FOREIGN KEY (`caller_extension_id`) REFERENCES `extensions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_caller_phone_number_id_foreign` FOREIGN KEY (`caller_phone_number_id`) REFERENCES `phone_numbers` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_caller_user_id_foreign` FOREIGN KEY (`caller_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_logs_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `call_queue_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `call_queue_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `call_queue_id` bigint unsigned NOT NULL,
  `member_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` bigint unsigned NOT NULL,
  `extension_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `priority` int unsigned NOT NULL DEFAULT '1',
  `penalty` int unsigned NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_paused` tinyint(1) NOT NULL DEFAULT '0',
  `paused_at` timestamp NULL DEFAULT NULL,
  `pause_reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_call_at` timestamp NULL DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_queue_member_target_unique` (`tenant_id`,`call_queue_id`,`member_type`,`member_id`),
  UNIQUE KEY `call_queue_members_uuid_unique` (`uuid`),
  KEY `call_queue_members_call_queue_id_foreign` (`call_queue_id`),
  KEY `call_queue_members_extension_id_foreign` (`extension_id`),
  KEY `call_queue_members_user_id_foreign` (`user_id`),
  KEY `call_queue_member_state_index` (`tenant_id`,`call_queue_id`,`is_active`,`is_paused`,`priority`),
  CONSTRAINT `call_queue_members_call_queue_id_foreign` FOREIGN KEY (`call_queue_id`) REFERENCES `call_queues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `call_queue_members_extension_id_foreign` FOREIGN KEY (`extension_id`) REFERENCES `extensions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_queue_members_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `call_queue_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `call_queues`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `call_queues` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `strategy` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'ring_all',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `max_wait_time_seconds` int unsigned NOT NULL DEFAULT '300',
  `ring_timeout_seconds` int unsigned NOT NULL DEFAULT '20',
  `retry_delay_seconds` int unsigned NOT NULL DEFAULT '5',
  `max_attempts` int unsigned NOT NULL DEFAULT '3',
  `music_on_hold` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `announce_position` tinyint(1) NOT NULL DEFAULT '0',
  `announce_estimated_wait` tinyint(1) NOT NULL DEFAULT '0',
  `overflow_destination_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `overflow_destination_id` bigint unsigned DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `call_queues_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `call_queues_uuid_unique` (`uuid`),
  KEY `call_queues_created_by_foreign` (`created_by`),
  KEY `call_queues_updated_by_foreign` (`updated_by`),
  KEY `call_queues_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `call_queues_tenant_id_strategy_index` (`tenant_id`,`strategy`),
  KEY `call_queues_overflow_index` (`tenant_id`,`overflow_destination_type`,`overflow_destination_id`),
  CONSTRAINT `call_queues_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `call_queues_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `call_queues_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_moderation_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_moderation_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary chat moderation log ID.',
  `conversation_id` bigint unsigned DEFAULT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `actor_id` bigint unsigned DEFAULT NULL,
  `target_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Moderation action name, for example participant_blocked, message_deleted, history_imported.',
  `reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional human-readable reason for the moderation action.',
  `old_values` json DEFAULT NULL COMMENT 'Optional previous state before moderation action.',
  `new_values` json DEFAULT NULL COMMENT 'Optional new state after moderation action.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe metadata related to the moderation action.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `chat_moderation_logs_conversation_created_idx` (`conversation_id`,`created_at`),
  KEY `chat_moderation_logs_message_created_idx` (`message_id`,`created_at`),
  KEY `chat_moderation_logs_actor_created_idx` (`actor_id`,`created_at`),
  KEY `chat_moderation_logs_target_created_idx` (`target_user_id`,`created_at`),
  KEY `chat_moderation_logs_action_created_idx` (`action`,`created_at`),
  KEY `chat_moderation_logs_action_index` (`action`),
  CONSTRAINT `chat_moderation_logs_actor_id_foreign` FOREIGN KEY (`actor_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_moderation_logs_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_moderation_logs_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_moderation_logs_target_user_id_foreign` FOREIGN KEY (`target_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores chat moderation and audit actions for conversations, messages, participants and history imports.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_user_devices`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_user_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary chat user device ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public unique device identifier.',
  `user_id` bigint unsigned NOT NULL,
  `device_key` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Stable per-browser/per-device key generated by the client.',
  `device_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Human-readable device name, for example Chrome on Windows.',
  `device_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'browser' COMMENT 'Device type: browser, mobile, desktop, tablet, api, unknown.',
  `platform` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Client platform, for example Windows, macOS, iOS, Android, Linux.',
  `browser` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Browser name, for example Chrome, Firefox, Safari, Edge.',
  `app_version` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Application/frontend version used by this device.',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Last known IP address. Supports IPv4 and IPv6.',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'Last known user agent string.',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this device is currently allowed to sync chat state.',
  `last_seen_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when this device was last seen.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe device metadata.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_user_devices_uuid_unique` (`uuid`),
  UNIQUE KEY `chat_user_devices_tenant_user_device_unique` (`tenant_id`,`user_id`,`device_key`),
  KEY `chat_user_devices_user_active_idx` (`user_id`,`is_active`),
  KEY `chat_user_devices_user_last_seen_idx` (`user_id`,`last_seen_at`),
  KEY `chat_user_devices_type_active_idx` (`device_type`,`is_active`),
  KEY `chat_user_devices_device_type_index` (`device_type`),
  KEY `chat_user_devices_is_active_index` (`is_active`),
  KEY `chat_user_devices_last_seen_at_index` (`last_seen_at`),
  CONSTRAINT `chat_user_devices_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chat_user_devices_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores user devices used for chat read state, sync and presence tracking.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_webhook_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_webhook_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary webhook delivery ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `webhook_endpoint_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned DEFAULT NULL,
  `message_id` bigint unsigned DEFAULT NULL,
  `event` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Webhook event name, for example message.created, message.read, message.failed.',
  `delivery_uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public unique delivery identifier used for tracing and idempotency.',
  `payload` json NOT NULL COMMENT 'Webhook payload that was sent or will be sent.',
  `signature` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'HMAC signature generated for this webhook delivery.',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Delivery status: pending, sent, failed, retrying, cancelled.',
  `attempts` int unsigned NOT NULL DEFAULT '0' COMMENT 'Number of delivery attempts.',
  `next_retry_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when the next retry should be attempted.',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when delivery was successfully sent.',
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when delivery finally failed.',
  `response_status` smallint unsigned DEFAULT NULL COMMENT 'HTTP status code returned by the webhook endpoint.',
  `response_body` text COLLATE utf8mb4_unicode_ci COMMENT 'Response body returned by the webhook endpoint. Store truncated/safe data only.',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'Error message if webhook delivery failed.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe delivery metadata for debugging and retries.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_webhook_deliveries_delivery_uuid_unique` (`delivery_uuid`),
  KEY `chat_webhook_deliveries_endpoint_status_idx` (`webhook_endpoint_id`,`status`),
  KEY `chat_webhook_deliveries_conversation_created_idx` (`conversation_id`,`created_at`),
  KEY `chat_webhook_deliveries_message_created_idx` (`message_id`,`created_at`),
  KEY `chat_webhook_deliveries_event_status_idx` (`event`,`status`),
  KEY `chat_webhook_deliveries_retry_idx` (`status`,`next_retry_at`),
  KEY `chat_webhook_deliveries_event_index` (`event`),
  KEY `chat_webhook_deliveries_status_index` (`status`),
  KEY `chat_webhook_deliveries_next_retry_at_index` (`next_retry_at`),
  KEY `chat_webhook_deliveries_sent_at_index` (`sent_at`),
  KEY `chat_webhook_deliveries_failed_at_index` (`failed_at`),
  KEY `chat_webhook_deliveries_response_status_index` (`response_status`),
  KEY `chat_webhook_deliveries_conversation_id_id_idx` (`conversation_id`,`id`),
  KEY `chat_webhook_deliveries_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `chat_webhook_deliveries_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_webhook_deliveries_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_webhook_deliveries_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `chat_webhook_deliveries_webhook_endpoint_id_foreign` FOREIGN KEY (`webhook_endpoint_id`) REFERENCES `chat_webhook_endpoints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores outgoing chat webhook delivery attempts, statuses, responses and retry information.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `chat_webhook_endpoints`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `chat_webhook_endpoints` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary webhook endpoint ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public unique webhook endpoint identifier.',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable webhook endpoint name.',
  `url` varchar(2048) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'External URL where webhook events will be delivered.',
  `secret` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Webhook signing secret used for HMAC signatures. Store encrypted if possible.',
  `events` json NOT NULL COMMENT 'List of webhook events this endpoint is subscribed to.',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether this webhook endpoint is active.',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'Endpoint status: active, disabled, failed.',
  `failure_count` int unsigned NOT NULL DEFAULT '0' COMMENT 'Consecutive delivery failure count.',
  `created_by` bigint unsigned DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when this endpoint was last used.',
  `last_success_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of the latest successful delivery.',
  `last_failure_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of the latest failed delivery.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe webhook endpoint metadata.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete marker for webhook endpoints.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `chat_webhook_endpoints_uuid_unique` (`uuid`),
  KEY `chat_webhook_endpoints_active_status_idx` (`is_active`,`status`),
  KEY `chat_webhook_endpoints_created_by_created_idx` (`created_by`,`created_at`),
  KEY `chat_webhook_endpoints_is_active_index` (`is_active`),
  KEY `chat_webhook_endpoints_status_index` (`status`),
  KEY `chat_webhook_endpoints_last_used_at_index` (`last_used_at`),
  KEY `chat_webhook_endpoints_last_success_at_index` (`last_success_at`),
  KEY `chat_webhook_endpoints_last_failure_at_index` (`last_failure_at`),
  KEY `chat_webhook_endpoints_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `chat_webhook_endpoints_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chat_webhook_endpoints_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores external webhook endpoints subscribed to chat events.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_contact_tag`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_contact_tag` (
  `contact_id` bigint unsigned NOT NULL,
  `contact_tag_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`contact_id`,`contact_tag_id`),
  KEY `contact_contact_tag_contact_tag_id_foreign` (`contact_tag_id`),
  CONSTRAINT `contact_contact_tag_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_contact_tag_contact_tag_id_foreign` FOREIGN KEY (`contact_tag_id`) REFERENCES `contact_tags` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_emails`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_emails` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `label` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'work',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_emails_tenant_id_normalized_email_unique` (`tenant_id`,`normalized_email`),
  UNIQUE KEY `contact_emails_uuid_unique` (`uuid`),
  KEY `contact_emails_contact_id_is_primary_index` (`contact_id`,`is_primary`),
  CONSTRAINT `contact_emails_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_emails_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_phones`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_phones` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `contact_id` bigint unsigned NOT NULL,
  `label` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'work',
  `raw_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `is_sms_capable` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_phones_tenant_id_normalized_number_unique` (`tenant_id`,`normalized_number`),
  UNIQUE KEY `contact_phones_uuid_unique` (`uuid`),
  KEY `contact_phones_contact_id_is_primary_index` (`contact_id`,`is_primary`),
  KEY `contact_phones_tenant_id_raw_number_index` (`tenant_id`,`raw_number`),
  CONSTRAINT `contact_phones_contact_id_foreign` FOREIGN KEY (`contact_id`) REFERENCES `contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contact_phones_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contact_tags`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contact_tags` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contact_tags_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `contact_tags_uuid_unique` (`uuid`),
  KEY `contact_tags_tenant_id_name_index` (`tenant_id`,`name`),
  CONSTRAINT `contact_tags_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `contacts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `first_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `display_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `company_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `job_title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `contacts_uuid_unique` (`uuid`),
  KEY `contacts_created_by_foreign` (`created_by`),
  KEY `contacts_updated_by_foreign` (`updated_by`),
  KEY `contacts_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `contacts_tenant_id_display_name_index` (`tenant_id`,`display_name`),
  KEY `contacts_tenant_id_company_name_index` (`tenant_id`,`company_name`),
  KEY `contacts_tenant_id_created_by_index` (`tenant_id`,`created_by`),
  CONSTRAINT `contacts_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `contacts_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `contacts_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversation_participants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversation_participants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary participant row ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `role` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'member' COMMENT 'Participant role: owner, admin, member, viewer, support.',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'Participant status: active, invited, left, removed, blocked.',
  `access_state` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'full' COMMENT 'Access state: full, read_only, hidden, blocked.',
  `block_display_mode` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Blocked display mode: hide_chat, show_notice, show_read_only_history.',
  `can_invite` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether participant can invite new users.',
  `can_remove` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether participant can remove other users.',
  `can_send` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether participant can send messages.',
  `can_attach` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Whether participant can attach files.',
  `can_manage` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether participant can manage conversation settings.',
  `can_moderate` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether participant can moderate messages or participants.',
  `blocked_reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Reason why participant was blocked or restricted.',
  `blocked_by` bigint unsigned DEFAULT NULL,
  `blocked_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when participant was blocked or restricted.',
  `history_visibility_mode` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'from_join' COMMENT 'History visibility mode: from_join, from_date, from_message, full.',
  `history_visible_from_message_id` bigint unsigned DEFAULT NULL COMMENT 'First visible message ID for this participant. FK is added later.',
  `history_visible_from_at` timestamp NULL DEFAULT NULL COMMENT 'First visible timestamp for this participant.',
  `history_visible_until_message_id` bigint unsigned DEFAULT NULL COMMENT 'Last visible message ID for this participant. FK is added later.',
  `history_visible_until_at` timestamp NULL DEFAULT NULL COMMENT 'Last visible timestamp for this participant.',
  `joined_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when participant joined the conversation.',
  `left_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when participant left the conversation.',
  `removed_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when participant was removed from the conversation.',
  `last_read_message_id` bigint unsigned DEFAULT NULL COMMENT 'Last message read by this participant. FK is added later.',
  `last_read_at` timestamp NULL DEFAULT NULL COMMENT 'Last read timestamp for unread counter calculations.',
  `muted_until` timestamp NULL DEFAULT NULL COMMENT 'Notification mute expiration timestamp for this participant.',
  `metadata` json DEFAULT NULL COMMENT 'Optional participant-specific metadata.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_participants_unique_user` (`conversation_id`,`user_id`),
  KEY `conversation_participants_user_id_foreign` (`user_id`),
  KEY `conversation_participants_blocked_by_foreign` (`blocked_by`),
  KEY `conversation_participants_role_index` (`role`),
  KEY `conversation_participants_status_index` (`status`),
  KEY `conversation_participants_access_state_index` (`access_state`),
  KEY `conversation_participants_blocked_at_index` (`blocked_at`),
  KEY `conversation_participants_history_visible_from_message_id_index` (`history_visible_from_message_id`),
  KEY `conversation_participants_history_visible_from_at_index` (`history_visible_from_at`),
  KEY `conversation_participants_history_visible_until_message_id_index` (`history_visible_until_message_id`),
  KEY `conversation_participants_history_visible_until_at_index` (`history_visible_until_at`),
  KEY `conversation_participants_joined_at_index` (`joined_at`),
  KEY `conversation_participants_left_at_index` (`left_at`),
  KEY `conversation_participants_removed_at_index` (`removed_at`),
  KEY `conversation_participants_last_read_message_id_index` (`last_read_message_id`),
  KEY `conversation_participants_last_read_at_index` (`last_read_at`),
  KEY `conversation_participants_muted_until_index` (`muted_until`),
  KEY `conversation_participants_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `conversation_participants_blocked_by_foreign` FOREIGN KEY (`blocked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversation_participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_participants_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `conversation_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `participants_history_from_message_fk` FOREIGN KEY (`history_visible_from_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `participants_history_until_message_fk` FOREIGN KEY (`history_visible_until_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `participants_last_read_message_fk` FOREIGN KEY (`last_read_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores users participating in conversations, including roles, permissions, restrictions and read state.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `conversations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `conversations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary conversation ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public unique conversation identifier.',
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'direct' COMMENT 'Conversation type: direct, group, support, external, system.',
  `visibility` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'private' COMMENT 'Conversation visibility: private or public.',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional conversation title, mainly used for group/support chats.',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional conversation description.',
  `owner_id` bigint unsigned DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_from_conversation_id` bigint unsigned DEFAULT NULL,
  `source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'internal' COMMENT 'Conversation source: internal, api, webhook, system.',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'Conversation status: active, archived, closed, deleted.',
  `join_policy` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'invite_only' COMMENT 'Join policy: invite_only, participants_can_invite, anyone_with_permission, public_join.',
  `history_import_mode` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'History import mode: none, from_date, from_message, full.',
  `history_import_from_message_id` bigint unsigned DEFAULT NULL COMMENT 'Message ID from which history import starts. FK is added later.',
  `history_import_from_at` timestamp NULL DEFAULT NULL COMMENT 'Date/time from which history import starts.',
  `last_message_id` bigint unsigned DEFAULT NULL COMMENT 'Last message ID for quick conversation list rendering. FK is added later.',
  `last_message_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp of the latest message in this conversation.',
  `metadata` json DEFAULT NULL COMMENT 'Optional technical/admin metadata for this conversation.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete marker for archived/removed conversations.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversations_uuid_unique` (`uuid`),
  KEY `conversations_owner_id_foreign` (`owner_id`),
  KEY `conversations_created_by_foreign` (`created_by`),
  KEY `conversations_created_from_conversation_id_foreign` (`created_from_conversation_id`),
  KEY `conversations_type_index` (`type`),
  KEY `conversations_visibility_index` (`visibility`),
  KEY `conversations_source_index` (`source`),
  KEY `conversations_status_index` (`status`),
  KEY `conversations_history_import_from_message_id_index` (`history_import_from_message_id`),
  KEY `conversations_history_import_from_at_index` (`history_import_from_at`),
  KEY `conversations_last_message_id_index` (`last_message_id`),
  KEY `conversations_last_message_at_index` (`last_message_at`),
  KEY `conversations_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `conversations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_created_from_conversation_id_foreign` FOREIGN KEY (`created_from_conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_history_import_message_fk` FOREIGN KEY (`history_import_from_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_last_message_fk` FOREIGN KEY (`last_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_owner_id_foreign` FOREIGN KEY (`owner_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `conversations_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores all chat conversations: direct, group, support, external and system chats.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `extension_credentials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `extension_credentials` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension_id` bigint unsigned NOT NULL,
  `username` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret_encrypted` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `secret_hint` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `version` int unsigned NOT NULL DEFAULT '1',
  `rotated_by` bigint unsigned DEFAULT NULL,
  `rotated_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extension_credentials_tenant_id_extension_id_unique` (`tenant_id`,`extension_id`),
  KEY `extension_credentials_extension_id_foreign` (`extension_id`),
  KEY `extension_credentials_rotated_by_foreign` (`rotated_by`),
  KEY `extension_credentials_tenant_id_username_index` (`tenant_id`,`username`),
  CONSTRAINT `extension_credentials_extension_id_foreign` FOREIGN KEY (`extension_id`) REFERENCES `extensions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `extension_credentials_rotated_by_foreign` FOREIGN KEY (`rotated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `extension_credentials_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `extensions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `extensions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `provisioning_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `registration_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unknown',
  `assigned_user_id` bigint unsigned DEFAULT NULL,
  `assigned_contact_id` bigint unsigned DEFAULT NULL,
  `endpoint_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_resource_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `credential_username` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `last_provisioned_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `extensions_tenant_id_number_unique` (`tenant_id`,`number`),
  UNIQUE KEY `extensions_uuid_unique` (`uuid`),
  UNIQUE KEY `extensions_tenant_id_endpoint_key_unique` (`tenant_id`,`endpoint_key`),
  KEY `extensions_assigned_user_id_foreign` (`assigned_user_id`),
  KEY `extensions_assigned_contact_id_foreign` (`assigned_contact_id`),
  KEY `extensions_created_by_foreign` (`created_by`),
  KEY `extensions_updated_by_foreign` (`updated_by`),
  KEY `extensions_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `extensions_tenant_id_assigned_user_id_index` (`tenant_id`,`assigned_user_id`),
  KEY `extensions_tenant_id_assigned_contact_id_index` (`tenant_id`,`assigned_contact_id`),
  CONSTRAINT `extensions_assigned_contact_id_foreign` FOREIGN KEY (`assigned_contact_id`) REFERENCES `contacts` (`id`) ON DELETE SET NULL,
  CONSTRAINT `extensions_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `extensions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `extensions_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `extensions_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `external_message_mappings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `external_message_mappings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary external mapping ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `provider` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'External provider name, for example crm, bot, api_client, webhook.',
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'External message ID from the provider.',
  `external_conversation_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'External conversation/thread/ticket ID from the provider.',
  `direction` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Message direction: inbound or outbound.',
  `idempotency_key` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional idempotency key supplied by external API client.',
  `payload_hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional hash of normalized external payload.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe mapping metadata. Do not store secrets here.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `external_message_tenant_provider_external_unique` (`tenant_id`,`provider`,`external_id`),
  UNIQUE KEY `external_message_tenant_provider_idempotency_unique` (`tenant_id`,`provider`,`idempotency_key`),
  KEY `external_message_conversation_created_idx` (`conversation_id`,`created_at`),
  KEY `external_message_message_direction_idx` (`message_id`,`direction`),
  KEY `external_message_provider_direction_idx` (`provider`,`direction`),
  KEY `external_message_mappings_provider_index` (`provider`),
  KEY `external_message_mappings_external_conversation_id_index` (`external_conversation_id`),
  KEY `external_message_mappings_direction_index` (`direction`),
  KEY `external_message_mappings_idempotency_key_index` (`idempotency_key`),
  CONSTRAINT `external_message_mappings_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `external_message_mappings_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `external_message_mappings_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Maps internal chat messages to external API, CRM, bot or webhook message identifiers.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Globally unique failed job identifier.',
  `connection` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Queue connection name.',
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Queue channel name.',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serialized failed job payload.',
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Captured exception stack trace.',
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Timestamp when job permanently failed.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_failed_at_idx` (`failed_at`),
  KEY `failed_jobs_queue_idx` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ivr_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ivr_menus` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `greeting_text` text COLLATE utf8mb4_unicode_ci,
  `greeting_audio_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `repeat_count` int unsigned NOT NULL DEFAULT '1',
  `input_timeout_seconds` int unsigned NOT NULL DEFAULT '5',
  `max_invalid_attempts` int unsigned NOT NULL DEFAULT '3',
  `timeout_action_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'repeat',
  `timeout_destination_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timeout_destination_id` bigint unsigned DEFAULT NULL,
  `invalid_action_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'repeat',
  `invalid_destination_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `invalid_destination_id` bigint unsigned DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ivr_menus_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `ivr_menus_uuid_unique` (`uuid`),
  KEY `ivr_menus_created_by_foreign` (`created_by`),
  KEY `ivr_menus_updated_by_foreign` (`updated_by`),
  KEY `ivr_menus_tenant_id_status_index` (`tenant_id`,`status`),
  CONSTRAINT `ivr_menus_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ivr_menus_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ivr_menus_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ivr_options`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ivr_options` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ivr_menu_id` bigint unsigned NOT NULL,
  `digit` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `destination_id` bigint unsigned DEFAULT NULL,
  `priority` int unsigned NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ivr_menu_digit_unique` (`tenant_id`,`ivr_menu_id`,`digit`),
  UNIQUE KEY `ivr_options_uuid_unique` (`uuid`),
  KEY `ivr_options_ivr_menu_id_foreign` (`ivr_menu_id`),
  KEY `ivr_options_menu_priority_index` (`tenant_id`,`ivr_menu_id`,`is_active`,`priority`),
  CONSTRAINT `ivr_options_ivr_menu_id_foreign` FOREIGN KEY (`ivr_menu_id`) REFERENCES `ivr_menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ivr_options_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique batch identifier.',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable batch name.',
  `total_jobs` int NOT NULL COMMENT 'Total jobs assigned to batch.',
  `pending_jobs` int NOT NULL COMMENT 'Remaining pending jobs.',
  `failed_jobs` int NOT NULL COMMENT 'Number of failed jobs.',
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serialized failed job identifiers.',
  `options` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Serialized batch runtime options.',
  `cancelled_at` int DEFAULT NULL COMMENT 'Unix timestamp when batch was cancelled.',
  `created_at` int NOT NULL COMMENT 'Unix timestamp when batch was created.',
  `finished_at` int DEFAULT NULL COMMENT 'Unix timestamp when batch completed.',
  PRIMARY KEY (`id`),
  KEY `job_batches_created_at_idx` (`created_at`),
  KEY `job_batches_finished_at_idx` (`finished_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Queue channel name.',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serialized queued job payload.',
  `attempts` smallint unsigned NOT NULL COMMENT 'Number of processing attempts.',
  `reserved_at` int unsigned DEFAULT NULL COMMENT 'Unix timestamp when job was reserved.',
  `available_at` int unsigned NOT NULL COMMENT 'Unix timestamp when job becomes available.',
  `created_at` int unsigned NOT NULL COMMENT 'Unix timestamp when job was created.',
  PRIMARY KEY (`id`),
  KEY `jobs_queue_reserved_idx` (`queue`,`reserved_at`),
  KEY `jobs_available_at_idx` (`available_at`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_attachments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_attachments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary attachment ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned DEFAULT NULL,
  `disk` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'local' COMMENT 'Laravel filesystem disk where the file is stored.',
  `path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Storage path of the file on the configured disk.',
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Original client filename.',
  `mime_type` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Detected MIME type of the uploaded file.',
  `size` bigint unsigned NOT NULL COMMENT 'File size in bytes.',
  `checksum` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional file checksum for duplicate detection or integrity checks.',
  `copied_from_attachment_id` bigint unsigned DEFAULT NULL,
  `is_imported` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether this attachment was imported from another message/conversation.',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active' COMMENT 'Attachment status: active, deleted, quarantined, failed.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe attachment metadata such as image dimensions or preview info.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete marker for attachment records.',
  PRIMARY KEY (`id`),
  KEY `message_attachments_copied_from_attachment_id_foreign` (`copied_from_attachment_id`),
  KEY `message_attachments_message_created_idx` (`message_id`,`created_at`),
  KEY `message_attachments_conversation_created_idx` (`conversation_id`,`created_at`),
  KEY `message_attachments_uploaded_by_created_idx` (`uploaded_by`,`created_at`),
  KEY `message_attachments_import_source_idx` (`is_imported`,`copied_from_attachment_id`),
  KEY `message_attachments_mime_type_index` (`mime_type`),
  KEY `message_attachments_checksum_index` (`checksum`),
  KEY `message_attachments_is_imported_index` (`is_imported`),
  KEY `message_attachments_status_index` (`status`),
  KEY `message_attachments_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `message_attachments_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_attachments_copied_from_attachment_id_foreign` FOREIGN KEY (`copied_from_attachment_id`) REFERENCES `message_attachments` (`id`) ON DELETE SET NULL,
  CONSTRAINT `message_attachments_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_attachments_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `message_attachments_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores files attached to chat messages, including imported/copied attachments.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_deliveries`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_deliveries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary message delivery ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `external_recipient_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'External recipient identifier for API/webhook delivery tracking.',
  `recipient_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' COMMENT 'Recipient type: user, external, webhook, system.',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending' COMMENT 'Delivery status: pending, delivered, failed, skipped.',
  `delivered_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when the message was delivered to this recipient.',
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when delivery failed.',
  `failure_reason` text COLLATE utf8mb4_unicode_ci COMMENT 'Human-readable delivery failure reason.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe delivery metadata.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_deliveries_unique_user_message` (`message_id`,`user_id`),
  KEY `message_deliveries_conversation_status_idx` (`conversation_id`,`status`),
  KEY `message_deliveries_message_status_idx` (`message_id`,`status`),
  KEY `message_deliveries_user_status_idx` (`user_id`,`status`),
  KEY `message_deliveries_recipient_status_idx` (`recipient_type`,`status`),
  KEY `message_deliveries_external_recipient_id_index` (`external_recipient_id`),
  KEY `message_deliveries_recipient_type_index` (`recipient_type`),
  KEY `message_deliveries_status_index` (`status`),
  KEY `message_deliveries_delivered_at_index` (`delivered_at`),
  KEY `message_deliveries_failed_at_index` (`failed_at`),
  KEY `message_deliveries_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `message_deliveries_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_deliveries_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_deliveries_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `message_deliveries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores per-recipient message delivery state for local users and external integrations.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_device_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_device_reads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary message device read ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `chat_user_device_id` bigint unsigned DEFAULT NULL,
  `device_key` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Device key snapshot used when the message was read.',
  `device_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Device type snapshot: browser, mobile, desktop, tablet, api, unknown.',
  `platform` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Platform snapshot, for example Windows, macOS, iOS, Android, Linux.',
  `browser` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Browser snapshot, for example Chrome, Firefox, Safari, Edge.',
  `read_at` timestamp NOT NULL COMMENT 'Timestamp when this device read the message.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe metadata for device read tracking.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_device_reads_message_device_unique` (`message_id`,`chat_user_device_id`),
  UNIQUE KEY `message_device_reads_message_user_device_key_unique` (`message_id`,`user_id`,`device_key`),
  KEY `message_device_reads_conversation_user_idx` (`conversation_id`,`user_id`),
  KEY `message_device_reads_conversation_read_at_idx` (`conversation_id`,`read_at`),
  KEY `message_device_reads_user_read_at_idx` (`user_id`,`read_at`),
  KEY `message_device_reads_device_read_at_idx` (`chat_user_device_id`,`read_at`),
  KEY `message_device_reads_device_key_index` (`device_key`),
  KEY `message_device_reads_device_type_index` (`device_type`),
  KEY `message_device_reads_read_at_index` (`read_at`),
  KEY `message_device_reads_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `message_device_reads_chat_user_device_id_foreign` FOREIGN KEY (`chat_user_device_id`) REFERENCES `chat_user_devices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `message_device_reads_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_device_reads_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_device_reads_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `message_device_reads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores per-device read receipts for chat messages.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `message_reads`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `message_reads` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary message read ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_id` bigint unsigned NOT NULL,
  `conversation_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `read_at` timestamp NOT NULL COMMENT 'Timestamp when the user first read the message on any device.',
  `read_source` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' COMMENT 'Read source: user, device, admin, system.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `message_reads_unique_user_message` (`message_id`,`user_id`),
  KEY `message_reads_conversation_user_idx` (`conversation_id`,`user_id`),
  KEY `message_reads_conversation_read_at_idx` (`conversation_id`,`read_at`),
  KEY `message_reads_user_read_at_idx` (`user_id`,`read_at`),
  KEY `message_reads_user_source_idx` (`user_id`,`read_source`),
  KEY `message_reads_read_at_index` (`read_at`),
  KEY `message_reads_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `message_reads_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_reads_message_id_foreign` FOREIGN KEY (`message_id`) REFERENCES `messages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `message_reads_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `message_reads_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores aggregated per-user read receipts for chat messages.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `messages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary message ID.',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public unique message identifier.',
  `conversation_id` bigint unsigned NOT NULL,
  `sender_id` bigint unsigned DEFAULT NULL,
  `sender_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'user' COMMENT 'Sender type: user, admin, external, system.',
  `external_id` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'External message identifier for API/webhook idempotency.',
  `reply_to_message_id` bigint unsigned DEFAULT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'text' COMMENT 'Message type: text, file, mixed, system.',
  `body` longtext COLLATE utf8mb4_unicode_ci COMMENT 'Message text body. Nullable for file-only or system messages.',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'sent' COMMENT 'Message status: pending, sent, delivered, read, failed, deleted.',
  `is_imported` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Whether this message was imported/copied from another conversation.',
  `imported_from_conversation_id` bigint unsigned DEFAULT NULL,
  `imported_from_message_id` bigint unsigned DEFAULT NULL,
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when the message was sent.',
  `delivered_at` timestamp NULL DEFAULT NULL COMMENT 'Global delivery timestamp for simple/direct cases.',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'Global read timestamp for simple/direct cases.',
  `edited_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when the message was edited.',
  `deleted_at` timestamp NULL DEFAULT NULL COMMENT 'Soft delete marker for message-level deletion.',
  `metadata` json DEFAULT NULL COMMENT 'Optional safe technical metadata for message rendering or integrations.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `messages_uuid_unique` (`uuid`),
  UNIQUE KEY `messages_conversation_external_unique` (`conversation_id`,`external_id`),
  KEY `messages_reply_to_message_id_foreign` (`reply_to_message_id`),
  KEY `messages_imported_from_conversation_id_foreign` (`imported_from_conversation_id`),
  KEY `messages_imported_from_message_id_foreign` (`imported_from_message_id`),
  KEY `messages_conversation_created_idx` (`conversation_id`,`created_at`),
  KEY `messages_conversation_sent_idx` (`conversation_id`,`sent_at`),
  KEY `messages_conversation_status_idx` (`conversation_id`,`status`),
  KEY `messages_conversation_type_idx` (`conversation_id`,`type`),
  KEY `messages_sender_created_idx` (`sender_id`,`created_at`),
  KEY `messages_import_source_idx` (`is_imported`,`imported_from_conversation_id`),
  KEY `messages_sender_type_index` (`sender_type`),
  KEY `messages_external_id_index` (`external_id`),
  KEY `messages_type_index` (`type`),
  KEY `messages_status_index` (`status`),
  KEY `messages_is_imported_index` (`is_imported`),
  KEY `messages_sent_at_index` (`sent_at`),
  KEY `messages_delivered_at_index` (`delivered_at`),
  KEY `messages_read_at_index` (`read_at`),
  KEY `messages_deleted_at_index` (`deleted_at`),
  KEY `messages_conversation_id_id_idx` (`conversation_id`,`id`),
  KEY `messages_tenant_id_foreign` (`tenant_id`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_imported_from_conversation_id_foreign` FOREIGN KEY (`imported_from_conversation_id`) REFERENCES `conversations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_imported_from_message_id_foreign` FOREIGN KEY (`imported_from_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_reply_to_message_id_foreign` FOREIGN KEY (`reply_to_message_id`) REFERENCES `messages` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `messages_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores chat messages, including text, system, imported and external/API messages.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `notifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `notifications` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Primary notification UUID used by Laravel database notifications.',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Notification class/type name.',
  `notifiable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `notifiable_id` bigint unsigned NOT NULL,
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Notification payload stored as JSON text by Laravel database notifications.',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when the notification was read. Null means unread.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`),
  KEY `notifications_notifiable_read_idx` (`notifiable_type`,`notifiable_id`,`read_at`),
  KEY `notifications_notifiable_created_idx` (`notifiable_type`,`notifiable_id`,`created_at`),
  KEY `notifications_read_at_index` (`read_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores database notifications for users and other notifiable models.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'User email requesting password reset.',
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Password reset token.',
  `created_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when reset token was created.',
  PRIMARY KEY (`email`),
  KEY `password_reset_tokens_created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_role`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_role` (
  `permission_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`permission_id`,`role_id`),
  KEY `permission_role_role_idx` (`role_id`),
  CONSTRAINT `permission_role_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_role_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permission_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permission_user` (
  `user_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`permission_id`),
  KEY `permission_user_permission_idx` (`permission_id`),
  CONSTRAINT `permission_user_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `permission_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique machine-readable permission key.',
  `scope` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `scope_reference` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional human-readable permission description.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permissions_scope_name_unique` (`scope`,`name`),
  KEY `permissions_created_at_idx` (`created_at`),
  KEY `permissions_scope_idx` (`scope`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `personal_access_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `personal_access_tokens` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `tokenable_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tokenable_id` bigint unsigned NOT NULL,
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable token label.',
  `token` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Hashed token value.',
  `abilities` text COLLATE utf8mb4_unicode_ci COMMENT 'Serialized token abilities/scopes.',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last successful token usage timestamp.',
  `expires_at` timestamp NULL DEFAULT NULL COMMENT 'Optional token expiration timestamp.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_tokenable_idx` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_last_used_idx` (`last_used_at`),
  KEY `personal_access_tokens_created_at_idx` (`created_at`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `phone_numbers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `phone_numbers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `normalized_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `display_number` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'did',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'available',
  `assignment_status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'unassigned',
  `assigned_user_id` bigint unsigned DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `provider_name` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `provider_reference` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `country_code` varchar(8) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `capabilities` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `purchased_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `released_at` timestamp NULL DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `primary_assignment_key` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone_numbers_tenant_id_normalized_number_unique` (`tenant_id`,`normalized_number`),
  UNIQUE KEY `phone_numbers_uuid_unique` (`uuid`),
  UNIQUE KEY `phone_numbers_primary_assignment_key_unique` (`primary_assignment_key`),
  KEY `phone_numbers_assigned_user_id_foreign` (`assigned_user_id`),
  KEY `phone_numbers_created_by_foreign` (`created_by`),
  KEY `phone_numbers_updated_by_foreign` (`updated_by`),
  KEY `phone_numbers_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `phone_numbers_tenant_id_assigned_user_id_index` (`tenant_id`,`assigned_user_id`),
  KEY `phone_numbers_tenant_id_assigned_user_id_is_primary_index` (`tenant_id`,`assigned_user_id`,`is_primary`),
  KEY `phone_numbers_tenant_id_provider_reference_index` (`tenant_id`,`provider_reference`),
  CONSTRAINT `phone_numbers_assigned_user_id_foreign` FOREIGN KEY (`assigned_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `phone_numbers_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `phone_numbers_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `phone_numbers_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `queue_member_pauses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `queue_member_pauses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `call_queue_id` bigint unsigned NOT NULL,
  `call_queue_member_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `started_at` timestamp NOT NULL,
  `ended_at` timestamp NULL DEFAULT NULL,
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `queue_member_pauses_uuid_unique` (`uuid`),
  KEY `queue_member_pauses_call_queue_id_foreign` (`call_queue_id`),
  KEY `queue_member_pauses_call_queue_member_id_foreign` (`call_queue_member_id`),
  KEY `queue_member_pauses_user_id_foreign` (`user_id`),
  KEY `queue_member_pauses_lookup_index` (`tenant_id`,`call_queue_id`,`call_queue_member_id`,`ended_at`),
  CONSTRAINT `queue_member_pauses_call_queue_id_foreign` FOREIGN KEY (`call_queue_id`) REFERENCES `call_queues` (`id`) ON DELETE CASCADE,
  CONSTRAINT `queue_member_pauses_call_queue_member_id_foreign` FOREIGN KEY (`call_queue_member_id`) REFERENCES `call_queue_members` (`id`) ON DELETE CASCADE,
  CONSTRAINT `queue_member_pauses_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `queue_member_pauses_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ring_group_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ring_group_members` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ring_group_id` bigint unsigned NOT NULL,
  `member_type` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `extension_id` bigint unsigned DEFAULT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `priority` int unsigned NOT NULL DEFAULT '1',
  `delay_seconds` int unsigned NOT NULL DEFAULT '0',
  `timeout_seconds` int unsigned NOT NULL DEFAULT '20',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `metadata` json DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ring_group_members_uuid_unique` (`uuid`),
  UNIQUE KEY `ring_group_member_target_unique` (`tenant_id`,`ring_group_id`,`member_type`,`extension_id`,`user_id`),
  KEY `ring_group_members_ring_group_id_foreign` (`ring_group_id`),
  KEY `ring_group_members_extension_id_foreign` (`extension_id`),
  KEY `ring_group_members_user_id_foreign` (`user_id`),
  KEY `ring_group_members_group_priority_index` (`tenant_id`,`ring_group_id`,`is_active`,`priority`),
  CONSTRAINT `ring_group_members_extension_id_foreign` FOREIGN KEY (`extension_id`) REFERENCES `extensions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ring_group_members_ring_group_id_foreign` FOREIGN KEY (`ring_group_id`) REFERENCES `ring_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ring_group_members_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ring_group_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `ring_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ring_groups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci,
  `strategy` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'simultaneous',
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `ring_timeout_seconds` int unsigned NOT NULL DEFAULT '20',
  `max_ring_duration_seconds` int unsigned NOT NULL DEFAULT '120',
  `failover_destination_type` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `failover_destination_id` bigint unsigned DEFAULT NULL,
  `settings` json DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ring_groups_tenant_id_slug_unique` (`tenant_id`,`slug`),
  UNIQUE KEY `ring_groups_uuid_unique` (`uuid`),
  KEY `ring_groups_created_by_foreign` (`created_by`),
  KEY `ring_groups_updated_by_foreign` (`updated_by`),
  KEY `ring_groups_tenant_id_status_index` (`tenant_id`,`status`),
  KEY `ring_groups_tenant_id_strategy_index` (`tenant_id`,`strategy`),
  CONSTRAINT `ring_groups_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `ring_groups_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `ring_groups_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `role_user`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `role_user` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `scope_reference` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`user_id`,`role_id`,`scope_reference`),
  KEY `role_user_role_idx` (`role_id`),
  KEY `role_user_user_idx` (`user_id`),
  KEY `role_user_tenant_idx` (`tenant_id`),
  CONSTRAINT `role_user_role_id_foreign` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_user_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `roles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique machine-readable role name.',
  `scope` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `scope_reference` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'platform',
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Optional human-readable role description.',
  `is_system` tinyint(1) NOT NULL DEFAULT '0',
  `is_protected` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `roles_scope_reference_name_unique` (`scope_reference`,`name`),
  KEY `roles_created_at_idx` (`created_at`),
  KEY `roles_scope_tenant_idx` (`scope`,`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique session identifier.',
  `user_id` bigint unsigned DEFAULT NULL COMMENT 'Authenticated user attached to session.',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Client IP address.',
  `user_agent` text COLLATE utf8mb4_unicode_ci COMMENT 'Browser and device information.',
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Serialized session payload.',
  `last_activity` int NOT NULL COMMENT 'Unix timestamp of last session activity.',
  PRIMARY KEY (`id`),
  KEY `sessions_user_last_activity_idx` (`user_id`,`last_activity`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `scope_user_id` bigint unsigned DEFAULT NULL,
  `scope_role_id` bigint unsigned DEFAULT NULL,
  `scope_permission_id` bigint unsigned DEFAULT NULL,
  `key` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique machine-readable setting key.',
  `label` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable setting name displayed in admin UI.',
  `group` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'Logical settings category/group.',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Detailed explanation of setting purpose.',
  `type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'string' COMMENT 'Declared setting value type.',
  `value` text COLLATE utf8mb4_unicode_ci COMMENT 'Resolved runtime value for this scope.',
  `default_value` text COLLATE utf8mb4_unicode_ci COMMENT 'Fallback default value.',
  `is_frontend` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates whether frontend may consume this setting.',
  `is_backend` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates whether backend services may consume this setting.',
  `is_public` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Safe for public frontend bootstrap if needed.',
  `is_encrypted` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Reserved for encrypted-at-rest setting values.',
  `priority` int unsigned NOT NULL DEFAULT '100' COMMENT 'Explicit priority override for conflict resolution.',
  `inheritance_source` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Resolved inheritance source identifier for debugging and effective value previews.',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Controls whether setting is active.',
  `is_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Marks protected core/system configuration.',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_scope_unique` (`key`,`scope_user_id`,`scope_role_id`,`scope_permission_id`),
  KEY `system_settings_scope_role_id_foreign` (`scope_role_id`),
  KEY `system_settings_scope_permission_id_foreign` (`scope_permission_id`),
  KEY `system_settings_created_by_foreign` (`created_by`),
  KEY `system_settings_updated_by_foreign` (`updated_by`),
  KEY `settings_key_active_idx` (`key`,`is_active`),
  KEY `settings_group_active_idx` (`group`,`is_active`),
  KEY `settings_frontend_active_idx` (`is_frontend`,`is_active`),
  KEY `settings_backend_active_idx` (`is_backend`,`is_active`),
  KEY `settings_scope_idx` (`scope_user_id`,`scope_role_id`,`scope_permission_id`),
  KEY `settings_resolution_idx` (`key`,`priority`,`is_active`),
  CONSTRAINT `system_settings_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_settings_scope_permission_id_foreign` FOREIGN KEY (`scope_permission_id`) REFERENCES `permissions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_settings_scope_role_id_foreign` FOREIGN KEY (`scope_role_id`) REFERENCES `roles` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_settings_scope_user_id_foreign` FOREIGN KEY (`scope_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_settings_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `system_translations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `system_translations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `locale` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Translation locale code.',
  `group` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'general' COMMENT 'Logical translation namespace/group.',
  `key` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Machine-readable translation key.',
  `value` longtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Translated human-readable value.',
  `source` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'database' COMMENT 'Translation source identifier.',
  `description` text COLLATE utf8mb4_unicode_ci COMMENT 'Optional internal translation description.',
  `is_frontend` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates frontend visibility.',
  `is_backend` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates backend visibility.',
  `is_system` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Marks protected system translation.',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Controls whether translation is active.',
  `is_auto_generated` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Marks translations automatically created when missing key is detected.',
  `is_translated` tinyint(1) NOT NULL DEFAULT '1' COMMENT 'Indicates whether translation value was manually/properly translated.',
  `created_by` bigint unsigned DEFAULT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `system_translations_unique` (`locale`,`group`,`key`),
  KEY `system_translations_created_by_foreign` (`created_by`),
  KEY `system_translations_updated_by_foreign` (`updated_by`),
  KEY `system_translations_locale_idx` (`locale`),
  KEY `system_translations_group_idx` (`group`),
  KEY `system_translations_key_idx` (`key`),
  KEY `system_translations_source_idx` (`source`),
  KEY `system_translations_active_idx` (`is_active`),
  CONSTRAINT `system_translations_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `system_translations_updated_by_foreign` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenant_memberships`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenant_memberships` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tenant_id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `invited_by` bigint unsigned DEFAULT NULL,
  `invited_at` timestamp NULL DEFAULT NULL,
  `accepted_at` timestamp NULL DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenant_memberships_tenant_id_user_id_unique` (`tenant_id`,`user_id`),
  KEY `tenant_memberships_user_id_foreign` (`user_id`),
  KEY `tenant_memberships_invited_by_foreign` (`invited_by`),
  KEY `tenant_memberships_status_index` (`status`),
  CONSTRAINT `tenant_memberships_invited_by_foreign` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `tenant_memberships_tenant_id_foreign` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `tenant_memberships_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tenants`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `tenants` (
  `id` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'UTC',
  `locale` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `currency` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'USD',
  `settings` json DEFAULT NULL,
  `activated_at` timestamp NULL DEFAULT NULL,
  `suspended_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_slug_unique` (`slug`),
  KEY `tenants_status_index` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_denied_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_denied_permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `user_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_denied_permissions_unique` (`user_id`,`permission_id`),
  KEY `user_denied_permissions_permission_id_foreign` (`permission_id`),
  CONSTRAINT `user_denied_permissions_permission_id_foreign` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `user_denied_permissions_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `user_notification_preferences`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary notification preference ID.',
  `user_id` bigint unsigned NOT NULL,
  `preferences` json NOT NULL COMMENT 'User notification preferences as JSON, for example system/realtime/email/activity settings.',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_notification_preferences_user_unique` (`user_id`),
  CONSTRAINT `user_notification_preferences_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores per-user notification preference settings.';
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT 'Primary unique identifier.',
  `name` varchar(160) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Human-readable display name.',
  `email` varchar(190) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Unique authentication email.',
  `email_verified_at` timestamp NULL DEFAULT NULL COMMENT 'Timestamp when email was verified.',
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Securely hashed user password.',
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  KEY `users_email_verified_at_idx` (`email_verified_at`),
  KEY `users_created_at_idx` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (1,'0001_01_01_000000_create_users_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (4,'2026_04_29_195012_create_personal_access_tokens_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (5,'2026_04_30_090743_create_roles_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (6,'2026_04_30_090820_create_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (7,'2026_04_30_090839_create_role_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (8,'2026_04_30_091023_create_permission_role_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (9,'2026_04_30_091618_create_permission_user_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (10,'2026_04_30_121312_create_activity_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (11,'2026_05_03_060407_create_user_denied_permissions_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (12,'2026_05_10_120000_create_system_settings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (13,'2026_05_11_054813_create_system_translations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (14,'2026_05_16_044851_create_notifications_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (15,'2026_05_19_110000_create_user_notification_preferences_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (16,'2026_05_20_061426_create_conversations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (17,'2026_05_20_061437_create_conversation_participants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (18,'2026_05_20_061447_create_messages_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (19,'2026_05_20_061519_create_message_reads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (20,'2026_05_20_061531_create_message_deliveries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (21,'2026_05_20_061541_create_message_attachments_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (22,'2026_05_20_061551_create_external_message_mappings_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (23,'2026_05_20_061601_create_chat_webhook_endpoints_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (24,'2026_05_20_061611_create_chat_webhook_deliveries_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (25,'2026_05_20_061621_create_chat_moderation_logs_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (26,'2026_05_20_061630_add_chat_message_references_to_conversations_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (27,'2026_05_20_062810_add_chat_message_references_to_conversation_participants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (28,'2026_05_20_065435_create_chat_user_devices_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (29,'2026_05_20_065540_create_message_device_reads_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (30,'2026_05_27_120000_add_query_optimization_indexes_for_chat_lists',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (31,'2026_06_24_000000_create_tenants_table',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (32,'2026_06_24_000001_add_tenant_id_to_chat_tables',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (33,'2026_06_24_010000_add_tenant_aware_rbac_scopes',1);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (34,'2026_06_25_000000_enforce_chat_tenant_ownership_constraints',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (35,'2026_06_25_100000_create_contacts_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (36,'2026_06_25_110000_create_extensions_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (37,'2026_06_26_090000_create_phone_numbers_table',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (38,'2026_06_26_130000_create_call_logs_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (39,'2026_06_30_090000_create_ring_groups_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (40,'2026_06_30_120000_create_call_queues_tables',2);
INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES (41,'2026_06_30_180000_create_ivr_menus_tables',2);
