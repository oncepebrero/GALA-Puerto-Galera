-- ==========================================================================
-- GALA  |  ADD FEEDBACK BY HAND  (copy, edit, run in phpMyAdmin > SQL tab)
-- --------------------------------------------------------------------------
-- Every example works as written. Only place_slug is really needed; everything else has a default.
--   place_slug  the part after ?slug= in a place page address (e.g. minolo-beach, tukuran-falls, coral-garden)
--   place_name  optional. Leave it out and the website fills it in from the slug
--   kind        Rating | Suggestion | Concern                      (default Rating)
--   rating      1 to 5, or NULL                                    (anything else is refused)
--   status      'approved' = shown on the website | 'pending' = waits for staff (default) | 'rejected'
--   photo       NULL, or the file name of a photo that is already inside uploads/feedback/
--   source      use 'staff' for entries you type in yourself
-- The first lines create the database/tables if they are missing, so there is no "table doesn't exist" error.
-- ==========================================================================
CREATE DATABASE IF NOT EXISTS gala CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gala;

-- Visitor feedback: ratings, suggestions, concerns, optional photo.
-- Every column has a default, so a short hand-written INSERT works (see add_feedback_template.sql).
CREATE TABLE IF NOT EXISTS feedback (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  place_slug    VARCHAR(120) NOT NULL DEFAULT '',            -- the place, e.g. 'minolo-beach' (see the page address of a place)
  place_name    VARCHAR(160) NOT NULL DEFAULT '',            -- may stay empty: the website fills the name in from the slug
  kind          ENUM('Rating','Suggestion','Concern') NOT NULL DEFAULT 'Rating',
  rating        TINYINT UNSIGNED NULL,                       -- 1 to 5, or NULL
  comment       VARCHAR(900) NULL,
  nickname      VARCHAR(40) NULL,
  photo         VARCHAR(80) NULL,                            -- file name of a photo inside uploads/feedback/
  source        VARCHAR(10) NOT NULL DEFAULT 'visitor',      -- visitor | staff | sample
  status        ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',   -- only 'approved' is shown on the website
  visitor_hash  CHAR(64) NOT NULL DEFAULT '',                -- only used by the website to limit spam
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  moderated_at  TIMESTAMP NULL DEFAULT NULL,
  moderated_by  VARCHAR(50) NULL,
  PRIMARY KEY (id),
  KEY idx_place_status (place_slug, status),
  KEY idx_status_created (status, created_at),
  KEY idx_visitor_time (visitor_hash, created_at),
  KEY idx_source (source),
  CONSTRAINT chk_rating CHECK (rating IS NULL OR (rating BETWEEN 1 AND 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------------------------
-- FIX for  #1364 - Field 'event_date' doesn't have a default value
-- An older version of GALA had an events table with an 'event_date' column.
-- If your database still has that old table, it is renamed to events_old_<date_time>
-- (nothing is deleted) and the correct table is created right below.
-- On a database that is already correct, these lines do nothing.
-- --------------------------------------------------------------------------
SET @old_events := (SELECT COUNT(*) FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'event_date');
SET @fix_sql := IF(@old_events > 0,
                   CONCAT('RENAME TABLE events TO events_old_', DATE_FORMAT(NOW(), '%Y%m%d_%H%i%s')),
                   'SELECT 1');
PREPARE fix_stmt FROM @fix_sql;
EXECUTE fix_stmt;
DEALLOCATE PREPARE fix_stmt;

-- Events and offers shown on the Events page and the home page.
CREATE TABLE IF NOT EXISTS events (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  title       VARCHAR(140) NOT NULL DEFAULT '',
  type        VARCHAR(20) NOT NULL DEFAULT 'Event',          -- Event | Workshop | Promotion | Announcement
  starts_on   DATE NULL,                                     -- NULL = date to be confirmed
  ends_on     DATE NULL,
  venue       VARCHAR(160) NULL,
  organizer   VARCHAR(160) NULL,
  details     VARCHAR(900) NULL,
  link        VARCHAR(255) NULL,
  verified    TINYINT(1) NOT NULL DEFAULT 0,                 -- 1 = staff confirmed the details with the organizer
  published   TINYINT(1) NOT NULL DEFAULT 1,                 -- 0 = hidden from visitors
  source      VARCHAR(10) NOT NULL DEFAULT 'staff',          -- staff | sample
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP NULL DEFAULT NULL,
  updated_by  VARCHAR(50) NULL,
  PRIMARY KEY (id),
  KEY idx_dates (starts_on, ends_on),
  KEY idx_pub (published, verified)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Staff accounts (the first one is created in the browser at /admin/).
CREATE TABLE IF NOT EXISTS admins (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50) NOT NULL,
  pass_hash  VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slows down repeated wrong passwords.
CREATE TABLE IF NOT EXISTS login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash      CHAR(64) NOT NULL DEFAULT '',
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 1) The shortest possible rating (waits for staff approval)
INSERT INTO feedback (place_slug, rating) VALUES ('minolo-beach', 5);

-- 2) A rating with a comment, already approved (shows on the website and in "Favorite moments")
INSERT INTO feedback (place_slug, kind, rating, comment, nickname, source, status, moderated_at, moderated_by)
VALUES ('minolo-beach', 'Rating', 5, 'Calm water and soft sand. We stayed until sunset.', 'Ana', 'staff', 'approved', NOW(), 'admin');

-- 3) A suggestion (no stars)
INSERT INTO feedback (place_slug, kind, comment, nickname, source)
VALUES ('tukuran-falls', 'Suggestion', 'Please add a clear sign for the start of the trail.', 'Miguel', 'staff');

-- 4) A concern with a date in the past
INSERT INTO feedback (place_slug, kind, rating, comment, source, created_at)
VALUES ('banana-beach', 'Concern', 2, 'Some litter was left near the shore after the weekend.', 'staff', NOW() - INTERVAL 3 DAY);

-- 5) Several ratings at once
INSERT INTO feedback (place_slug, rating, source, status) VALUES
('coral-garden', 5, 'staff', 'approved'),
('coral-garden', 4, 'staff', 'approved'),
('minolo-beach', 4, 'staff', 'approved');

-- Check the result
SELECT id, place_slug, kind, rating, status, source, created_at FROM feedback ORDER BY id DESC LIMIT 10;

-- Undo everything you added with source = 'staff':
-- DELETE FROM feedback WHERE source = 'staff';
