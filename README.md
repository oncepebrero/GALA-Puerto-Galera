# GALÁ-Explore. Enjoy. Tara Galera!
### Puerto Galera Tourism Discovery System

PHP 8.1+, HTML, CSS, vanilla JavaScript and MySQL. No frameworks. Runs locally on **Laragon**.

Place names, categories, descriptions and localities come from your A–G inventory and Reference Request table. Travel estimates and contacts come from the same documents. The site tells visitors to confirm times, fees and access with operators or the Tourism Office. Individual hours, fees and coordinates for places are not in the documents, so they are not shown; add them in `data/attractions.php` when you have them.

**What's new:** a bolder, playful front page, a redesigned Explore page, a rebuilt **Ask Gali** chat guide (bottom-right of every page, powered by `api/gali.php`), example events, wave page transitions, and an illustration for every place.

---

## 1. Run it on Laragon (Windows)

1. **Install Laragon Full** (includes Apache, MySQL, PHP 8.x). Start Laragon and press **Start All**.
2. **Copy this folder** to `C:\laragon\www\gala` (so `C:\laragon\www\gala\index.php` exists).
3. **Create the database.** Open Laragon → **Menu → MySQL → ... (or Database → HeidiSQL)**, connect (user `root`, empty password), then open and run `database\schema.sql`.
   Or in the Laragon terminal: `mysql -u root < C:\laragon\www\gala\database\schema.sql`
   The script is safe to run again; it never drops data.
4. **Open the site:** Laragon creates a pretty URL automatically: **http://gala.test** (right-click Laragon → Apache → Reload if it does not appear). `http://localhost/gala/` also works.
5. **Check setup:** open `/check.php`. It shows PHP version, extensions (`pdo_mysql`, `mbstring`), the DB connection and tables. **Delete `check.php` before publishing.**
6. **Create the staff account:** open `/admin/`. On first visit (from localhost only) you are asked to create a username and a password of 10+ characters. Use this area to approve or reject visitor feedback.

If your MySQL password is not empty or the database name differs, copy `config/config.local.sample.php` to `config/config.local.php` and set only what changes:
```php
<?php return ['db' => ['pass' => 'your-password'], 'debug' => true];
```
Set `debug` back to `false` when the site is public.

Fonts (Bricolage Grotesque, Figtree, Caveat Brush) are self-hosted in `assets/fonts/`, so the site looks right offline. Only the embedded maps need internet.

## 2. Project structure

```
index.php  explore.php  place.php  map.php  feedback.php  events.php
travel.php  responsible.php  about.php  404.php  sitemap.php  check.php
admin/index.php            staff login + feedback moderation + CSV export
api/places.php             JSON list of public places
config/config.php          settings (override in config.local.php)
data/                      ALL editable content (attractions, events, travel, FAQs, photo credits)
includes/                  bootstrap, helpers, header/footer, feedback logic, DB
database/schema.sql        MySQL tables (feedback, admins, login_attempts)
assets/css/main.css        design system   assets/js/main.js   all interactivity
assets/img/                brand/ hero/ places/ placeholders/
tools/render_scenes.py     regenerates the placeholder images
```
`config/ data/ includes/ database/ tools/` carry an `.htaccess` that denies direct web access.

## 3. Updating attraction information

Everything visitors see about a place lives in **`data/attractions.php`** (one line per place). To add a place, copy a line and change it. To record **verified** facts, add a `verified` array to that place:

```php
$beach('Minolo Beach', 'San Isidro', '…', ['Swimming'], true);   // existing line
```
and for a custom entry use `$add([...,'verified' => ['hours' => '8am–5pm', 'fees' => '₱50', 'lat' => 13.5, 'lng' => 120.9, 'last_verified' => '2026-11-02']])`.

- A place gets a **map pin with verified coordinates** only when both `lat` and `lng` are present.
- Verified rows then show a green *Verified* tag with the date; everything else stays *Not yet verified*.
- `'published' => false` hides a place everywhere. **Ka Tuna Cockfight Arena** is held back this way pending review with the Tourism Office.
- Events: `data/events.php` holds **example events** so you can see the layout. Replace them with real ones (the Upcoming / Ongoing / Past label is computed from the dates). Travel figures: `data/travel.php`. FAQs: `data/faqs.php`.

## 4. Pictures

| What | File | Notes |
|---|---|---|
| Hero | `assets/img/hero/puerto-galera-hero.jpg` (2400×1350) and `puerto-galera-hero-1200.jpg` | Replace with your licensed photograph. |
| One place | `assets/img/places/{slug}.jpg` (~1400 px wide) and optional `{slug}-sm.jpg` (~640 px) | The slug is the place name in lowercase with dashes, e.g. `cely-beach.jpg`. It is picked up automatically. |
| Category fallbacks | `assets/img/placeholders/placeholder-*.jpg` | Used only if a place has no file. |

- **Every place has an image that portrays its description** (a wreck for the wrecks, a lighthouse, a grotto, a cave, hot springs, a zipline and so on). 45 are illustrations painted by `tools/render_places.py` (run `python3 tools/render_places.py` to regenerate; needs numpy, scipy, pillow). They are artwork, not photographs, and they are not photorealistic. The footer says "Pictures are illustrations unless a photo credit says otherwise."
- **15 places use photos from your Reference Request document** (beaches, waterfalls, golf, yacht club, shooting range). Please make sure you have permission to use them, and set `licensed` plus a credit in `data/photo_credits.php`.
- Photos from that document that did not fit were not used: they were mismatched with their labels, one looked AI-generated, one had a Klook watermark, one showed identifiable people.
- **Never add photos of Mangyan community members.** Those three listings use a landscape only.

## 5. Feedback and safeguards

- Form: CSRF token, honeypot, minimum fill time, server-side validation, link/e-mail/phone/profanity filter, 5 submissions per visitor per hour, duplicate detection. All queries use prepared statements; all output is escaped.
- Everything is saved as **pending**. It appears on the site only after staff approve it in `/admin/`.
- The site **only shows "Saved"** after the INSERT succeeded. If MySQL is down, visitors see "Nothing was sent".
- Privacy: no e-mail or phone is collected; a one-way hash limits spam.
- Staff: password hashing, login throttling (5 attempts / 15 min), session regeneration, CSV export protected against spreadsheet formula injection.
- Headers: Content-Security-Policy, `X-Frame-Options`, `nosniff`, referrer policy.
- On a public server: serve over HTTPS, set `setup_key` in `config.local.php`, use a dedicated MySQL user (not `root`), delete `check.php`.

## 6. Content handling decisions

- **Mangyan communities** (Ambang, Iraya, Baclayan): listed as in the inventory, with no photos, no map pin, no directions and a "visit by arrangement" notice.
- **Shooting Range, Timbugan Cockpit & Sports Center**: shown with a "please check first" notice. **Ka Tuna Cockfight Arena**: hidden pending review.
- **Events**: example data for now (see above).
- **Travel fares/fees/contacts**: shown as given in your documents, with a note to confirm before travelling.
- **Maps**: the documents have no coordinates, so map links search by place name. Dive sites map to the nearest named landmark. Add `lat`/`lng` to a place to pin it exactly.
- **Ask Gali** only answers from the site's own data. It never invents fees, hours or coordinates.

## 7. What is not included
Staff editing of attractions in a web form (edit `data/attractions.php`), e-mail notifications, image upload by visitors, and automated tests. Fonts and map embeds need an internet connection.


## 4. Staff area (`/admin/`)

The database and its tables are now created automatically the first time MySQL is reachable, so importing `schema.sql` is optional (and older installs are upgraded in place).

- **Dashboard:** totals, items waiting for review, average rating, rating breakdown, 14-day activity, average by category, top and lowest rated places, places with the most concerns, and the latest activity.
- **Feedback:** filter by status, type, place, minimum rating, source or search text; sort; page through results; approve, reject, restore or delete one item or many at once; export the filtered list as CSV.
- **Ratings by place:** every place with its average, number of ratings, 1-to-5 star distribution, suggestions, concerns and items waiting for review.
- **Add entry:** record a rating, suggestion or concern yourself (for example from a paper survey). Marked "Staff entry".
- **Tools:** load 60 clearly-labelled **sample entries** so the dashboard has data to show, and remove them in one click. About 70% of sample entries are approved, so they also appear on public pages until removed. Also export everything and delete rejected items.
- **Staff:** change your password, add or remove staff accounts. Sessions end after two hours of inactivity.


## 5. If the staff area shows "Something went wrong"

1. Open **`/admin/repair.php`** (works on your own computer, e.g. `http://gala.test/admin/repair.php`). It checks PHP, the database connection, every table and every column, and shows exactly what is wrong.
2. Press **Repair now**. It creates anything missing and keeps your data.
3. Still broken, or you want a clean start? Use **Rebuild database** on the same page (type `REBUILD`). It deletes feedback and staff accounts and creates fresh tables.
4. On your own computer the error page now shows the real error message. Errors are also written to `logs/error.log` in the site folder.

**Database files** (`database/`):
- `gala_fresh.sql`: deletes and recreates the `gala` database, empty and clean. Import it in phpMyAdmin.
- `gala_sample_data.sql`: 60 clearly-labelled sample ratings, suggestions and concerns. Import after `gala_fresh.sql` to see the dashboard filled. Remove later with `DELETE FROM feedback WHERE source = 'sample';` or from Staff area > Tools.
- `schema.sql`: safe to import any time, never deletes anything.


## 6. Photos on feedback, and events in the database

**Photos ("share a memory").** The feedback form (`/feedback.php` and the form on every place page) has an optional photo field: one JPG, PNG or WebP. The server re-saves it as a clean JPEG (max 1600 px, location data removed), stores it in `uploads/feedback/` and keeps only the file name in the database. A photo is shown publicly only after staff approve the feedback. In the staff area you see a thumbnail, and can **Remove photo** (keeps the comment), reject or delete. Needs PHP `gd` and `fileinfo` (enabled by default in Laragon). The size limit follows PHP's `upload_max_filesize`; the form shows the real limit, and `config.php` has `feedback.max_photo_mb`.

**Events.** Events now live in the `events` table instead of `data/events.php`. In the staff area open **Events** to add, edit, delete, **mark as verified** (after confirming with the organizer) or **hide** events. The website updates immediately: the Events page, the home page and the Gali assistant all read the table. Verified events show a green badge; the others say "Check with organizer". On first run the table is filled once with the example events from `data/events.php` (marked not verified); deleting them is permanent.

**Adding feedback with SQL.** `database/add_feedback_template.sql` has ready-to-run examples (a rating, a suggestion, a concern, several ratings at once). `database/gala_sample_data.sql` loads 60 sample entries.


## 7. Favorite moments, sample events and the SQL files

**Favorite moments (home page).** The section "Favorite moments from our recent visitors" shows the newest approved ratings of 4 stars or more that have a comment or a photo (photos first), the average rating, and tappable stars that open the feedback form with that rating already chosen. It fills itself as staff approve feedback. With no feedback yet it shows a friendly invitation instead of fake reviews.

**Sample data.** Import `database/gala_sample_data.sql` (60 labeled sample entries, 12 with photos that ship in `uploads/feedback/`), or press **Load 60 sample entries** in the staff area (Dashboard or Tools). Remove them any time under Tools; this also deletes the sample photo files. Sample events: `database/gala_sample_events.sql`, or **Add sample events / Remove sample events** on the Events tab. Event dates are relative to today, so the sample always has ongoing, upcoming, past and "date to be confirmed" items. Events are created, edited, verified, hidden and deleted in the staff area (Events tab).

**SQL files** (`database/`). Every file creates the database and tables first if they are missing, so none of them fails with "unknown database" or "table doesn't exist", and they can be imported in any order:
- `gala_fresh.sql`: starts over (deletes the `gala` database, recreates it with sample events).
- `schema.sql`: safe, never deletes, adds sample events only while the events table is empty.
- `gala_sample_data.sql` / `gala_sample_events.sql`: sample feedback and events.
- `add_feedback_template.sql`: copy-and-edit examples for typing feedback by hand. Only `place_slug` is required; every other column has a default. Ratings must be 1 to 5 and `kind` must be Rating, Suggestion or Concern, otherwise MySQL refuses the row (that is on purpose).
- `queries_cookbook.sql`: ready-to-run reports (averages per place, pending items, what the home page shows, events to verify).
