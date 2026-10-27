-- ==========================================================================
-- GALA  |  SAMPLE FEEDBACK: 60 labeled sample entries (source = 'sample'), 12 of them with photos
-- --------------------------------------------------------------------------
-- Self-contained: creates the database and tables first if they do not exist, so it never fails on a missing table.
-- Dates are relative to today (last ~6 weeks). About 70% are approved, so they also appear on the website,
-- including "Favorite moments from our recent visitors" on the home page.
-- The photos are in uploads/feedback/ (shipped with the site).
--
-- Importing twice adds the entries twice. To start over:  DELETE FROM feedback WHERE source = 'sample';
-- (or Staff area > Tools > Remove all sample entries, which also deletes the sample photo files)
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

INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('coral-garden','Coral Garden','Rating',4,NULL,NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3674233 SECOND,NOW() - INTERVAL 3670633 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('escarceo-point-lighthouse','Escarceo Point Lighthouse','Rating',5,NULL,NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3666960 SECOND,NOW() - INTERVAL 3663360 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('permida-paraoayan','Permida Paraoayan','Rating',4,'The trek was fun and the pool at the end was so refreshing.','Lyn','82ae109427032fd5f0297a5b0ff7a525.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3634503 SECOND,NOW() - INTERVAL 3630903 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('baclayan-mangyan-settlement','Baclayan Mangyan Settlement','Concern',1,'Some litter was left on the shore when we visited. Please remind visitors to take their trash back.','Jessa',NULL,'sample','rejected',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3624621 SECOND,NOW() - INTERVAL 3621021 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('ambang-mangyan-community','Ambang Mangyan Community','Rating',3,NULL,NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3619257 SECOND,NOW() - INTERVAL 3615657 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('medio-island-trail','Medio Island Trail','Rating',4,'Worth the walk. The kids loved splashing around.','Jessa','d4ed761d3a22e5505476c41fa32df592.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3590522 SECOND,NOW() - INTERVAL 3586922 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('black-rice-and-the-cross-with-two-canyons','Black Rice and the Cross with Two Canyons','Rating',5,'A nice break from the beach, short visit but memorable.',NULL,'b28e4cf0017c55292733c6a31fa2fa49.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3576837 SECOND,NOW() - INTERVAL 3573237 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('villaflor-hot-spring','Villaflor Hot Spring','Rating',3,'Nice place, a bit crowded when we arrived but still enjoyable.','Jomar',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3544227 SECOND,NOW() - INTERVAL 3540627 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('black-rice-and-the-cross-with-two-canyons','Black Rice and the Cross with Two Canyons','Concern',NULL,'Some litter was left on the shore when we visited. Please remind visitors to take their trash back.','Kyla',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3539777 SECOND,NOW() - INTERVAL 3536177 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('coral-cove','Coral Cove','Rating',5,NULL,'Rico',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3506923 SECOND,NOW() - INTERVAL 3503323 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('excavation-museum','Excavation Museum','Suggestion',NULL,'It would help to show typical entrance fees and opening hours on the page.','Jessa',NULL,'sample','rejected',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3377462 SECOND,NOW() - INTERVAL 3373862 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('tabinay-hot-spring','Tabinay Hot Spring','Concern',NULL,'Prices felt higher than posted, please remind operators to display fees.','Paolo',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3222308 SECOND,NOW() - INTERVAL 3218708 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('tukuran-falls','Tukuran Falls','Suggestion',NULL,'It would help to show typical entrance fees and opening hours on the page.',NULL,NULL,'sample','rejected',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3179892 SECOND,NOW() - INTERVAL 3176292 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('puerto-galera-yacht-club','Puerto Galera Yacht Club','Rating',3,'Pretty, but go early because it gets busy by noon.',NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3155144 SECOND,NOW() - INTERVAL 3151544 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('van-vianen-art-gallery','Van Vianen Art Gallery','Rating',3,'Good overall. Facilities could be a little cleaner.','Ian',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3097356 SECOND,NOW() - INTERVAL 3093756 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('hole-in-the-wall','Hole in the Wall','Rating',5,'Worth every peso. We saw so many corals and a few turtles.','Marco','c05ed5c1894c37c4dc37caf7699d3549.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3085043 SECOND,NOW() - INTERVAL 3081443 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('aninuan-falls','Aninuan Falls','Rating',4,'Lush and quiet. Go early to enjoy it without the crowd.','Cathy',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3060937 SECOND,NOW() - INTERVAL 3057337 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('x-treme-sports-and-adventure','X-treme Sports and Adventure','Rating',5,'Lush and quiet. Go early to enjoy it without the crowd.','Kyla','7de1b0d6a4630aeee0c16d89a593d7fe.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 3034250 SECOND,NOW() - INTERVAL 3030650 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('medio-island-trail','Medio Island Trail','Suggestion',NULL,'A few more trash bins near the entrance would keep the area clean.',NULL,NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2991075 SECOND,NULL,NULL);
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('the-verde-island-passage','The Verde Island Passage','Rating',4,'Calm conditions and a well-organised briefing from the operator.','Enzo',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2929182 SECOND,NOW() - INTERVAL 2925582 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('ponderosa-golf-and-country-club','Ponderosa Golf and Country Club, Inc.','Rating',5,'The trek was fun and the pool at the end was so refreshing.',NULL,'c227cb5521da9f762ab4a327135de82a.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2928968 SECOND,NOW() - INTERVAL 2925368 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('timbugan-cockpit-sports-center','Timbugan Cockpit & Sports Center','Rating',5,'Worth the walk. The kids loved splashing around.','Trixie',NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2928367 SECOND,NULL,NULL);
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('zobel-beach','Zobel Beach','Rating',5,'One of the prettiest spots we visited this trip.','Miguel',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2814400 SECOND,NOW() - INTERVAL 2810800 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('van-vianen-art-gallery','Van Vianen Art Gallery','Rating',2,NULL,'Gab',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2792257 SECOND,NOW() - INTERVAL 2788657 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('st-christopher','St. Christopher','Suggestion',NULL,'Consider adding a simple map of the trail and rest areas.','Marco',NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2543272 SECOND,NULL,NULL);
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('sabang-wrecks','Sabang Wrecks','Suggestion',5,'It would be great to list the nearest toilets and places to eat.',NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2391306 SECOND,NOW() - INTERVAL 2387706 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('the-verde-island-passage','The Verde Island Passage','Rating',5,'Beautiful reef, fantastic first dive for me.','Jomar',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2379655 SECOND,NOW() - INTERVAL 2376055 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('turtle-rock','Turtle Rock','Rating',5,'Great visibility and lots of colourful fish. Our guide was careful and friendly.','Gab','f97618d25c8b6a9d383ea6261585366b.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2313058 SECOND,NOW() - INTERVAL 2309458 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('muelle-cultural-and-heritage-park','Muelle Cultural and Heritage Park','Rating',5,NULL,'Kyla',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2267429 SECOND,NOW() - INTERVAL 2263829 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('ponderosa-golf-and-country-club','Ponderosa Golf and Country Club, Inc.','Rating',4,'Beautiful falls, bring water shoes. Our guide knew the trail well.','Enzo',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2165842 SECOND,NOW() - INTERVAL 2162242 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('the-verde-island-passage','The Verde Island Passage','Concern',NULL,'Some litter was left on the shore when we visited. Please remind visitors to take their trash back.',NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 2120881 SECOND,NOW() - INTERVAL 2117281 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('minolo-beach','Minolo Beach','Rating',5,'One of the prettiest spots we visited this trip.','Dan','0a874b0889769693fa64b59e5d8bf316.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1922462 SECOND,NOW() - INTERVAL 1918862 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('the-wall','The Wall','Rating',4,'Easily the highlight of our weekend in Puerto Galera.',NULL,'02b78d7d52a9725da2d8a42f19f3721a.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1826128 SECOND,NOW() - INTERVAL 1822528 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('coral-garden','Coral Garden','Rating',4,'Easily the highlight of our weekend in Puerto Galera.','Joyce',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1663843 SECOND,NOW() - INTERVAL 1660243 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('x-treme-sports-and-adventure','X-treme Sports and Adventure','Rating',4,NULL,'Miguel',NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1655314 SECOND,NULL,NULL);
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('talipanan-falls','Talipanan Falls','Suggestion',3,'A few more trash bins near the entrance would keep the area clean.','Tin',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1640189 SECOND,NOW() - INTERVAL 1636589 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('shark-cave','Shark Cave','Suggestion',4,'Please add clearer signs from the main road, we got lost for a bit.',NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1573515 SECOND,NOW() - INTERVAL 1569915 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('python-cave','Python Cave','Rating',5,'Worth the walk. The kids loved splashing around.','Dan',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1485281 SECOND,NOW() - INTERVAL 1481681 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('dungon-point','Dungon Point','Rating',3,'Pretty, but go early because it gets busy by noon.','Paolo',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1337404 SECOND,NOW() - INTERVAL 1333804 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('villaflor-hot-spring','Villaflor Hot Spring','Suggestion',NULL,'A few more trash bins near the entrance would keep the area clean.',NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1187885 SECOND,NOW() - INTERVAL 1184285 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('palangan-munti-beach','Palangan Munti Beach','Rating',5,'Perfect for a lazy day. Bring snacks and sunscreen.','Nica','fda2301ee50716cfec54f5e49a7ca20b.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1091368 SECOND,NOW() - INTERVAL 1087768 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('muelle-cultural-and-heritage-park','Muelle Cultural and Heritage Park','Rating',5,'Fascinating bit of local history. Take time to read the markers.','Mika','ad799869b89eb0d64a69073bd5c6698d.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1034303 SECOND,NOW() - INTERVAL 1030703 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('st-christopher','St. Christopher','Rating',5,NULL,'Kyla',NULL,'sample','rejected',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 1014165 SECOND,NOW() - INTERVAL 1010565 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('villaflor-hot-spring','Villaflor Hot Spring','Rating',4,'Beautiful falls, bring water shoes. Our guide knew the trail well.','Gab',NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 991552 SECOND,NULL,NULL);
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('coral-garden','Coral Garden','Concern',NULL,'Boats were too close to the swimming area, which felt unsafe for children.','Kyla',NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 826162 SECOND,NULL,NULL);
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('tukuran-falls','Tukuran Falls','Rating',5,'Beautiful falls, bring water shoes. Our guide knew the trail well.','Marco',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 781252 SECOND,NOW() - INTERVAL 777652 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('centro-beach','Centro Beach','Rating',4,'Perfect for a lazy day. Bring snacks and sunscreen.','Ana',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 760295 SECOND,NOW() - INTERVAL 756695 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('muelle-cultural-and-heritage-park','Muelle Cultural and Heritage Park','Rating',5,'A nice break from the beach, short visit but memorable.','Ian',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 696059 SECOND,NOW() - INTERVAL 692459 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('palangan-munti-beach','Palangan Munti Beach','Rating',5,NULL,'Tin',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 680895 SECOND,NOW() - INTERVAL 677295 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('minolo-beach','Minolo Beach','Rating',5,'Clear water and a relaxed crowd. Great for a family swim.','Paolo',NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 588201 SECOND,NULL,NULL);
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('puerto-galera-yacht-club','Puerto Galera Yacht Club','Rating',5,'Lush and quiet. Go early to enjoy it without the crowd.','Ian','2082ecb2df3c02346f344a79abf198e3.jpg','sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 533853 SECOND,NOW() - INTERVAL 530253 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('coral-garden','Coral Garden','Rating',4,'Easily the highlight of our weekend in Puerto Galera.',NULL,NULL,'sample','rejected',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 499012 SECOND,NOW() - INTERVAL 495412 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('muelle-cultural-and-heritage-park','Muelle Cultural and Heritage Park','Rating',5,'A peaceful stop with lovely views. Good photo spot.',NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 486944 SECOND,NOW() - INTERVAL 483344 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('banana-beach','Banana Beach','Suggestion',3,'Consider adding a simple map of the trail and rest areas.','Ian',NULL,'sample','rejected',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 431851 SECOND,NOW() - INTERVAL 428251 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('escarceo-point-lighthouse','Escarceo Point Lighthouse','Rating',5,NULL,NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 417506 SECOND,NOW() - INTERVAL 413906 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('shark-cave','Shark Cave','Concern',NULL,'Boats were too close to the swimming area, which felt unsafe for children.','Lyn',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 316841 SECOND,NOW() - INTERVAL 313241 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('coral-garden','Coral Garden','Concern',NULL,'Prices felt higher than posted, please remind operators to display fees.','Enzo',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 292652 SECOND,NOW() - INTERVAL 289052 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('muelle-cultural-and-heritage-park','Muelle Cultural and Heritage Park','Rating',3,NULL,NULL,NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 219085 SECOND,NOW() - INTERVAL 215485 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('wreck-point','Wreck Point','Rating',4,NULL,'Joyce',NULL,'sample','approved',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL 180908 SECOND,NOW() - INTERVAL 177308 SECOND,'sample');
INSERT INTO feedback (place_slug,place_name,kind,rating,comment,nickname,photo,source,status,visitor_hash,created_at,moderated_at,moderated_by) VALUES ('boulders','Boulders','Rating',5,'Worth every peso. We saw so many corals and a few turtles.','Gab',NULL,'sample','pending',SHA2(CONCAT('sample-',UUID()),256),NOW() - INTERVAL -3864 SECOND,NULL,NULL);
