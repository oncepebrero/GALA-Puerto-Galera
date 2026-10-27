-- ==========================================================================
-- GALA  |  FRESH DATABASE with sample events  |  MySQL 5.7+ / 8.x / MariaDB 10.3+
-- --------------------------------------------------------------------------
-- WARNING: this file DELETES the "gala" database (all feedback, events and staff accounts)
-- and creates it again, clean, with 9 sample events (dates are relative to today).
--
-- Laragon: Menu > MySQL > phpMyAdmin > Import this file     (or: mysql -u root < database/gala_fresh.sql)
-- Then open /admin/ to create your staff account, and import gala_sample_data.sql if you want sample ratings and photos.
--
-- The website also repairs itself: a missing database, table or column is created automatically,
-- and /admin/repair.php can check or rebuild everything from the browser.
-- ==========================================================================
DROP DATABASE IF EXISTS gala;
CREATE DATABASE gala CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gala;

-- Visitor feedback: ratings, suggestions, concerns, optional photo.
-- Every column has a default, so a short hand-written INSERT works (see add_feedback_template.sql).
CREATE TABLE feedback (
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
CREATE TABLE events (
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
CREATE TABLE admins (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50) NOT NULL,
  pass_hash  VARCHAR(255) NOT NULL DEFAULT '',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Slows down repeated wrong passwords.
CREATE TABLE login_attempts (
  id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash      CHAR(64) NOT NULL DEFAULT '',
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ip_time (ip_hash, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample events. Manage them in the staff area (Events tab): add, edit, verify, hide, delete.
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Sunset Beach Music Night','Event',CURDATE() + INTERVAL 6 DAY,NULL,'Sabang Beach, Puerto Galera','Sabang Beach Association','Local bands, grilled seafood and a sunset over the bay. Bring a mat or a chair and enjoy the evening on the sand.',NULL,1,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Coastal Clean-up and Reef Care Day','Event',CURDATE() + INTERVAL 12 DAY,NULL,'Sabang, Puerto Galera','Local dive and resort community','Gloves on, bags out. Volunteers clean the shoreline in the morning and learn reef-friendly habits from local divers in the afternoon.',NULL,1,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Indigenous Peoples Month: Cultural Exchange','Event',CURDATE() - INTERVAL 20 DAY,CURDATE() + INTERVAL 10 DAY,'Poblacion, Puerto Galera','Puerto Galera Tourism Office','A month of talks, exhibits and community-led activities celebrating Mangyan culture. Join through the community or the Tourism Office, and ask before taking photographs.',NULL,1,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Weekday Island-hopping Offers','Promotion',CURDATE() - INTERVAL 5 DAY,CURDATE() + INTERVAL 50 DAY,'Participating boat operators','Participating operators','Ask partner operators about weekday island-hopping packages to Banana Beach, Medio Island and the bays around Puerto Galera.',NULL,1,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Smart Tourism Workshop for Local Operators','Workshop',CURDATE() + INTERVAL 25 DAY,NULL,'Puerto Galera (venue to be announced)','Puerto Galera Tourism Office','A hands-on session on digital listings, visitor feedback and sustainable tour design for guides, resorts and boat operators.',NULL,0,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Dive Safety and Marine Conservation Forum','Event',CURDATE() + INTERVAL 40 DAY,NULL,'Sabang, Puerto Galera','Local dive operators','Dive professionals share reef-protection practices, safety briefings and what visitors can do to keep the Verde Island Passage thriving.',NULL,0,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Parol and Lights Night','Event',CURDATE() + INTERVAL 48 DAY,NULL,'Poblacion, Puerto Galera','Puerto Galera community groups','Handmade lanterns, music and food along the waterfront as the town lights up for Christmas.',NULL,0,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Mangyan Weaving Demonstration','Event',NULL,NULL,'Venue to be announced','Community cultural group','A hands-on look at traditional Mangyan weaving. The date is still being confirmed, so please check with the Tourism Office.',NULL,0,1,'sample');
INSERT INTO events (title,type,starts_on,ends_on,venue,organizer,details,link,verified,published,source) VALUES ('Sustainable Tourism Improvements Briefing','Announcement',CURDATE() - INTERVAL 38 DAY,NULL,'Puerto Galera','Puerto Galera Tourism Office','An update on environmental fees, visitor registration and community projects planned for the coming season.',NULL,1,1,'sample');
