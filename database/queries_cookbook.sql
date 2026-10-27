-- ==========================================================================
-- GALA  |  QUERY COOKBOOK: ready-to-run queries for phpMyAdmin > SQL tab (reading only, nothing here deletes data)
-- The first lines create the database/tables if missing, so these queries never fail with "table doesn't exist".
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



-- How many entries of each status?
SELECT status, COUNT(*) AS total FROM feedback GROUP BY status;

-- Average rating per place (approved ratings only), best first
SELECT place_slug, COUNT(rating) AS ratings, ROUND(AVG(rating), 2) AS average
FROM feedback WHERE status = 'approved' AND rating IS NOT NULL
GROUP BY place_slug ORDER BY average DESC, ratings DESC;

-- Waiting for review
SELECT id, place_slug, kind, rating, comment, created_at FROM feedback WHERE status = 'pending' ORDER BY created_at;

-- Entries that have a photo
SELECT id, place_slug, rating, photo, status FROM feedback WHERE photo IS NOT NULL;

-- What "Favorite moments" on the home page shows (approved, 4+ stars, with a comment or photo)
SELECT id, place_slug, rating, nickname, comment, photo, created_at FROM feedback
WHERE status = 'approved' AND kind = 'Rating' AND rating >= 4 AND ((comment IS NOT NULL AND comment <> '') OR photo IS NOT NULL)
ORDER BY created_at DESC LIMIT 6;

-- Submissions per day, last 14 days
SELECT DATE(created_at) AS day, COUNT(*) AS total FROM feedback WHERE created_at >= (CURDATE() - INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY day;

-- Events: upcoming or ongoing, shown on the website
SELECT id, title, type, starts_on, ends_on, verified FROM events
WHERE published = 1 AND (ends_on >= CURDATE() OR (ends_on IS NULL AND starts_on >= CURDATE())) ORDER BY starts_on;

-- Events that still need to be verified
SELECT id, title, starts_on, organizer FROM events WHERE verified = 0 ORDER BY starts_on;

-- Approve / hide things by hand (remove the leading -- to run):
-- UPDATE feedback SET status = 'approved', moderated_at = NOW(), moderated_by = 'admin' WHERE id = 1;
-- UPDATE events SET verified = 1 WHERE id = 1;
-- UPDATE events SET published = 0 WHERE id = 1;
