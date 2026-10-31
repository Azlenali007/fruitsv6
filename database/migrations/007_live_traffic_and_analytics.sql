-- ==============================================================================
-- Migration: 007_live_traffic_and_analytics.sql
-- Project: N.A Fresh Fruits & Coconuts
-- Purpose: Adds privacy-conscious, first-party live traffic & visitor analytics tables
-- ==============================================================================

-- 1. Unique Visitors Table
CREATE TABLE IF NOT EXISTS `analytics_visitors` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `visitor_id` VARCHAR(64) NOT NULL UNIQUE,
    `first_seen_at` DATETIME NOT NULL,
    `last_seen_at` DATETIME NOT NULL,
    `total_visits` INT UNSIGNED NOT NULL DEFAULT 1,
    `device_type` ENUM('mobile', 'desktop', 'tablet') NOT NULL DEFAULT 'mobile',
    `browser` VARCHAR(50) NOT NULL DEFAULT 'Unknown',
    `os` VARCHAR(50) NOT NULL DEFAULT 'Unknown',
    `city` VARCHAR(100) NULL DEFAULT 'Jaora',
    `region` VARCHAR(100) NULL DEFAULT 'Madhya Pradesh',
    `country` VARCHAR(50) NOT NULL DEFAULT 'India',
    INDEX `idx_vis_last_seen` (`last_seen_at`),
    INDEX `idx_vis_first_seen` (`first_seen_at`),
    INDEX `idx_vis_device` (`device_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Visitor Sessions Table (Visits)
CREATE TABLE IF NOT EXISTS `analytics_sessions` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_token` VARCHAR(64) NOT NULL UNIQUE,
    `visitor_id` VARCHAR(64) NOT NULL,
    `source_type` VARCHAR(40) NOT NULL DEFAULT 'direct', -- whatsapp, instagram, youtube, facebook, google_search, google_ads, meta_ads, qr_code, referral, direct
    `source_name` VARCHAR(100) NOT NULL DEFAULT 'Direct / Unknown',
    `utm_source` VARCHAR(100) NULL,
    `utm_medium` VARCHAR(100) NULL,
    `utm_campaign` VARCHAR(100) NULL,
    `referrer_url` VARCHAR(500) NULL,
    `entry_path` VARCHAR(255) NOT NULL DEFAULT '/',
    `current_path` VARCHAR(255) NOT NULL DEFAULT '/',
    `started_at` DATETIME NOT NULL,
    `last_active_at` DATETIME NOT NULL,
    `page_views_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `is_returning` TINYINT(1) NOT NULL DEFAULT 0,
    INDEX `idx_sess_vis` (`visitor_id`),
    INDEX `idx_sess_active` (`is_active`, `last_active_at`),
    INDEX `idx_sess_started` (`started_at`),
    INDEX `idx_sess_source` (`source_type`),
    INDEX `idx_sess_campaign` (`utm_campaign`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Individual Page Views Table
CREATE TABLE IF NOT EXISTS `analytics_page_views` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `session_token` VARCHAR(64) NOT NULL,
    `visitor_id` VARCHAR(64) NOT NULL,
    `url_path` VARCHAR(255) NOT NULL,
    `page_title` VARCHAR(255) NULL,
    `viewed_at` DATETIME NOT NULL,
    INDEX `idx_pv_path` (`url_path`),
    INDEX `idx_pv_viewed` (`viewed_at`),
    INDEX `idx_pv_sess` (`session_token`),
    INDEX `idx_pv_vis` (`visitor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
