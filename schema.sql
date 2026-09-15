-- Job tracker -- schema only, no rows.
--
--   mysql -u root --default-character-set=utf8mb4 < schema.sql
--
-- utf8mb4 throughout: notes carry em dashes, accented company names and the odd
-- Hebrew string, and the MySQL client's latin1 default double-encodes all three.

CREATE DATABASE IF NOT EXISTS `job_tracker`
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `job_tracker`;

CREATE TABLE IF NOT EXISTS `applications` (
  `id`               int NOT NULL AUTO_INCREMENT,
  `company`          varchar(120) NOT NULL,
  `roles`            varchar(255) DEFAULT '',
  `requested_salary` varchar(60)  NOT NULL DEFAULT '',
  `apply_url`        varchar(500) NOT NULL DEFAULT '',   -- the employer's form
  `posting_url`      varchar(500) DEFAULT NULL,          -- the job description
  `email_url`        varchar(500) DEFAULT NULL,          -- the confirmation mail
  `prep_url`         varchar(500) NOT NULL DEFAULT '',   -- interview prep doc
  `stack_url`        varchar(500) NOT NULL DEFAULT '',   -- tech-stack glossary for the round
  `applied`          date DEFAULT NULL,
  `status`           enum('Interviewing',
                          'Applied - awaiting reply',
                          'CV prepared - not submitted',
                          'Rejected',
                          'Offer',
                          'Withdrawn') NOT NULL DEFAULT 'Applied - awaiting reply',
  `interviewed`      tinyint(1) NOT NULL DEFAULT '0',
  `last_update`      date DEFAULT NULL,
  `cv_generated`     tinyint(1) NOT NULL DEFAULT '0',
  `folders`          varchar(255) DEFAULT '',            -- space-separated doc folder slugs
  `notes`            text,                               -- dated log, one update per line
  `created_at`       timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  -- one row per company per role: re-running the pipeline must not duplicate a row
  UNIQUE KEY `uq_company_roles` (`company`, `roles`(80)),
  KEY `idx_status`  (`status`),
  KEY `idx_applied` (`applied`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
