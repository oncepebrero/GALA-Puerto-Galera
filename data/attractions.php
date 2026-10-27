<?php
/**
 * GALÁ attraction inventory.
 *
 * SOURCE: "GALÁ | Puerto Galera Tourism Discovery System – Reference Information
 * Inventory (A–G)" (names, categories, one-line descriptions) and the "Reference
 * Request" table (locality / barangay labels). This is a PRELIMINARY research
 * inventory, not an official or verified publication.
 *
 * HOW TO EDIT
 *  - Add or change a place by editing one line below.
 *  - Verified facts go in the optional 'verified' array once confirmed with the
 *    Puerto Galera Tourism Office or the operator. Supported keys:
 *      hours, fees, transport, facilities, accessibility, contact, lat, lng, last_verified
 *    A place only gets a map pin from GALÁ if BOTH lat and lng are set.
 *  - Set 'published' => false to hide a place from every public page.
 *  - Nothing here is a verified coordinate, fee or schedule unless it sits in 'verified'.
 *
 * FIELDS
 *  slug, name, cat (beach|diving|culture|nature), kind (sub-type label),
 *  area (grouping used for filters), locality (text exactly as listed),
 *  summary (inventory description), tags (short descriptors taken from the summary),
 *  features (diving only: Reef|Wall|Wreck|Cave|Drift|Macro|Pelagic),
 *  map (search phrase for map links), note (optional respectful/legal caution),
 *  sensitive ('indigenous'|'review'), featured, published.
 */

$P = [];
$add = function (array $p) use (&$P) {
    $p += ['kind' => '', 'barangay' => null, 'features' => [], 'tags' => [], 'note' => null, 'sensitive' => null,
           'featured' => false, 'published' => true, 'map' => null, 'verified' => []];
    $P[] = $p;
};

/* ------------------------------------------------------------------ B. Beaches */
$beach = function (string $name, string $brgy, string $summary, array $tags, bool $featured = false) use ($add) {
    $add(['slug' => slugify($name), 'name' => $name, 'cat' => 'beach', 'kind' => 'Beach', 'barangay' => $brgy, 'area' => $brgy,
          'locality' => 'Barangay ' . $brgy, 'summary' => $summary, 'tags' => $tags, 'featured' => $featured,
          'map' => "$name, $brgy, Puerto Galera, Oriental Mindoro"]);
};
$beach('Cely Beach', 'Balatero', 'Peaceful beach, clean shoreline, and a serene atmosphere.', ['Quiet', 'Clean shoreline']);
$beach('Bulabod Beach', 'Dulangan', 'Beach with a stream flowing into the sea.', ['Stream meets the sea']);
$beach('Dalaruan Beach', 'Sabang', 'Coastal area described as suitable for swimming.', ['Swimming']);
$beach('Calumpang Beach', 'Palangan', 'Community beach used for swimming and fishing.', ['Swimming', 'Community beach']);
$beach('Aplayang Munti Beach', 'Dulangan', 'Small beach for quiet seaside relaxation.', ['Quiet', 'Small beach']);
$beach('Banana Beach', 'Balatero', 'Swimming spot and island-hopping stop.', ['Swimming', 'Island-hopping stop']);
$beach('Minolo Beach', 'San Isidro', 'Scenic beach for swimming and light snorkeling.', ['Swimming', 'Light snorkeling'], true);
$beach('Zobel Beach', 'Aninuan', 'Quiet coastal setting with a relaxed atmosphere.', ['Quiet']);
$beach('Centro Beach', 'Palangan', 'Suitable for picnics and quick swims.', ['Picnics', 'Quick swims']);
$beach('Small Tabinay Beach', 'Tabinay', 'Family outings, swimming, and relaxation.', ['Family outings', 'Swimming']);
$beach('Palangan Munti Beach', 'Palangan', 'Smaller, quieter beach for swimming.', ['Quiet', 'Swimming']);
$beach('Encenada Beach', 'Palangan', 'Sheltered cove for swimming and sunbathing.', ['Swimming', 'Sunbathing', 'Sheltered cove']);

/* ------------------------------------------------------------------ C. Diving sites and marine attractions */
$dive = function (string $name, string $area, string $locality, string $summary, array $features, string $map, bool $featured = false) use ($add) {
    $add(['slug' => slugify($name), 'name' => $name, 'cat' => 'diving', 'kind' => 'Dive site', 'area' => $area,
          'locality' => $locality, 'summary' => $summary, 'features' => $features, 'tags' => $features,
          'featured' => $featured, 'map' => $map]);
};
$bay = 'Puerto Galera Bay'; $sab = 'Sabang'; $esc = 'Escarceo Point'; $off = 'Offshore and the channel';
$dive('Coral Garden', $bay, 'Puerto Galera Bay, near Medio Island', 'Shallow reef with hard and soft corals; described as suitable for beginners.', ['Reef'], 'Medio Island, Puerto Galera, Oriental Mindoro', true);
$dive('Giant Clam Garden', $bay, 'Puerto Galera Bay, Puerto Galera', 'Large clams and colorful reef life.', ['Reef'], 'Puerto Galera Bay, Oriental Mindoro');
$dive('Manila Channel', $sab, 'Near the channel west of Sabang, Puerto Galera', 'Drift diving, sandy patches, and macro life.', ['Drift', 'Macro'], 'Sabang, Puerto Galera, Oriental Mindoro');
$dive('Sinandigan Wall', 'Sinandigan', 'Sinandigan coast, Puerto Galera', 'Deep wall, strong currents, and larger fish.', ['Wall', 'Pelagic'], 'Sinandigan, Puerto Galera, Oriental Mindoro');
$dive('Coral Cove', $esc, 'Near Escarceo Point, Puerto Galera', 'Macro life and a sloping reef leading to a wall.', ['Reef', 'Macro', 'Wall'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('Boulders', $esc, 'Near Escarceo Point, Puerto Galera', 'Rock formations, swim-throughs, and caves.', ['Cave'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('Dungon Point', $sab, 'Near Sabang, Puerto Galera', 'Wall and slope with strong currents and pelagic marine life.', ['Wall', 'Pelagic'], 'Sabang, Puerto Galera, Oriental Mindoro');
$dive("Ernie's Cave", $sab, 'Near Sabang, Puerto Galera', 'Swim-throughs and cave-like formations.', ['Cave'], 'Sabang, Puerto Galera, Oriental Mindoro');
$dive('Fish Bowl', $esc, 'Near Escarceo Point, Puerto Galera', 'High fish concentrations in swirling currents.', ['Reef', 'Pelagic'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('Hole in the Wall', $esc, 'Near Escarceo Point, Puerto Galera', 'Natural swim-through along a wall.', ['Wall', 'Cave'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('Japanese Wreck', $esc, 'Near Escarceo Point, Puerto Galera', 'Historic wreck with coral-covered structures.', ['Wreck'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('Kilima Drift', 'Kilima dive area', 'Near the Kilima dive area, Puerto Galera', 'Drift diving and pelagic encounters.', ['Drift', 'Pelagic'], 'Puerto Galera, Oriental Mindoro');
$dive('Sabang Wrecks', $sab, 'Sabang Bay, Puerto Galera', 'Multiple wreck sites for underwater exploration.', ['Wreck'], 'Sabang Bay, Puerto Galera, Oriental Mindoro');
$dive('Shark Cave', $esc, 'Near Escarceo Point, Puerto Galera', 'Cavern associated with reef sharks and schools of fish.', ['Cave', 'Pelagic'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('St. Christopher', $sab, 'Near Sabang and La Laguna, Puerto Galera', 'Small wreck with corals and fish.', ['Wreck', 'Reef'], 'Sabang, Puerto Galera, Oriental Mindoro');
$dive('Sweetlips Corner', $off, "Puerto Galera's offshore dive area", 'Underwater formation associated with yellow-ribbon sweetlips.', ['Reef'], 'Puerto Galera, Oriental Mindoro');
$dive('The Atoll', $esc, 'Near Escarceo Point, Puerto Galera', 'Circular reef formation with marine life.', ['Reef'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('The Hill', $off, 'Offshore near the channel west of Puerto Galera', 'Small underwater hill with corals and schooling fish.', ['Reef'], 'Puerto Galera, Oriental Mindoro');
$dive('The Verde Island Passage', $off, 'Waters between Puerto Galera, Mindoro, and Batangas', 'Diving destination noted for marine biodiversity.', ['Reef', 'Pelagic'], 'Verde Island Passage', true);
$dive('The Wall', $off, "Puerto Galera's offshore dive area", 'Steep underwater wall with soft corals.', ['Wall'], 'Puerto Galera, Oriental Mindoro');
$dive('Turtle Rock', $esc, 'Near Escarceo Point, Puerto Galera', 'Deep dive site with reported sea turtle sightings.', ['Reef', 'Pelagic'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('West Escarceo', $esc, 'Near Escarceo Point, Puerto Galera', 'Deep wall dive with dramatic drop-offs.', ['Wall'], 'Escarceo Point, Puerto Galera, Oriental Mindoro');
$dive('Wreck Point', $sab, 'Sabang Bay, Puerto Galera', 'Small wreck with coral growth and reef fish.', ['Wreck', 'Reef'], 'Sabang Bay, Puerto Galera, Oriental Mindoro');

/* ------------------------------------------------------------------ D. Culture, arts and heritage */
$cul = function (string $name, string $kind, string $area, string $locality, string $summary, array $tags, array $x = []) use ($add) {
    $add($x + ['slug' => slugify($name), 'name' => $name, 'cat' => 'culture', 'kind' => $kind, 'area' => $area, 'locality' => $locality,
               'summary' => $summary, 'tags' => $tags, 'map' => "$name, $area, Puerto Galera, Oriental Mindoro"]);
};
$cul('Van Vianen Art Gallery', 'Art space', 'Poblacion', 'Poblacion, Puerto Galera, Oriental Mindoro', 'Art space associated with community development.', ['Art', 'Community']);
$cul('Muelle Cultural and Heritage Park', 'Heritage site', 'Poblacion', 'Muelle, Poblacion, Puerto Galera, Oriental Mindoro', 'Waterfront heritage site highlighting local history and maritime traditions.', ['Heritage', 'Waterfront'], ['featured' => true]);
$cul('Escarceo Point Lighthouse', 'Landmark', 'Sinandigan', 'Sitio Escarceo, Barangay Sinandigan, Puerto Galera, Oriental Mindoro', 'Historic coastal navigation landmark.', ['Landmark', 'Coastal'], ['featured' => true, 'map' => 'Escarceo Point Lighthouse, Puerto Galera, Oriental Mindoro']);
$cul('Birhen Maria Ina ng Kalikasan Grotto', 'Religious site', 'Dulangan', 'Barangay Dulangan, Puerto Galera, Oriental Mindoro', 'Grotto dedicated to nature and the environment.', ['Religious site', 'Environment']);
$cul('Immaculate Conception Church', 'Church', 'Poblacion', 'Barangay Poblacion, Puerto Galera, Oriental Mindoro', 'Church and pilgrimage destination.', ['Church', 'Pilgrimage']);
$cul('Black Rice and the Cross with Two Canyons', 'Memorial', 'Poblacion', 'Muelle, Barangay Poblacion, Puerto Galera, Oriental Mindoro', 'Memorial structures associated with the El Canonero de Mariveles shipwreck.', ['Memorial', 'Shipwreck history'], ['map' => 'Muelle, Poblacion, Puerto Galera, Oriental Mindoro']);
$cul('Excavation Museum', 'Museum', 'Poblacion', 'Immaculate Conception Church, Barangay Poblacion, Puerto Galera, Oriental Mindoro', 'Museum described as displaying artifacts associated with the Yang, Ming, and Tsung dynasties.', ['Museum', 'Artifacts'], ['map' => 'Immaculate Conception Church, Poblacion, Puerto Galera, Oriental Mindoro']);
$ind = 'Mangyan communities decide how, when and whether visitors are welcome. GALÁ does not publish photographs, ceremonies or traditional knowledge for this listing, and it shows no map pin or directions. Arrange any visit through the community or the Puerto Galera Tourism Office, ask before taking photographs, and follow the guidance you are given.';
$cul('Ambang Mangyan Community', 'Community', 'Villaflor', 'Sitio Ambang, Barangay Villaflor, Puerto Galera, Oriental Mindoro', 'Community where visitors can learn about Mangyan ways of life; coordinate visits respectfully.', ['Mangyan community', 'Visit by arrangement'], ['sensitive' => 'indigenous', 'note' => $ind, 'map' => null]);
$cul('Iraya Mangyan Village', 'Community', 'Aninuan', 'Sitio Talipanan, Barangay Aninuan, Puerto Galera, Oriental Mindoro', 'Village with traditional-style houses and community facilities.', ['Mangyan community', 'Visit by arrangement'], ['sensitive' => 'indigenous', 'note' => $ind, 'map' => null]);
$cul('Baclayan Mangyan Settlement', 'Community', 'Baclayan', 'Barangay Baclayan, Puerto Galera, Oriental Mindoro', 'Home of Iraya Mangyan communities.', ['Mangyan community', 'Visit by arrangement'], ['sensitive' => 'indigenous', 'note' => $ind, 'map' => null]);

/* ------------------------------------------------------------------ E. Nature, adventure and recreation */
$nat = function (string $name, string $kind, string $cat_kind, string $area, string $locality, string $summary, array $tags, array $x = []) use ($add) {
    $add($x + ['slug' => slugify($name), 'name' => $name, 'cat' => 'nature', 'kind' => $kind, 'type' => $cat_kind, 'area' => $area,
               'locality' => $locality, 'summary' => $summary, 'tags' => $tags,
               'map' => "$name, $area, Puerto Galera, Oriental Mindoro"]);
};
$nat('Medio Island Trail', 'Trail', 'Nature', 'San Antonio', 'Medio Island, Barangay San Antonio, Puerto Galera', 'Forested paths and elevated viewpoints.', ['Trail', 'Viewpoints'], ['map' => 'Medio Island, Puerto Galera, Oriental Mindoro']);
$nat('Permida Paraoayan', 'Ecotourism', 'Nature', 'Baclayan', 'Brgy. Baclayan, Puerto Galera', 'Community-based ecotourism, river trekking, forest walks, and local guides.', ['Ecotourism', 'River trekking', 'Local guides']);
$nat('Tukuran Falls', 'Waterfall', 'Nature', 'Villaflor', 'Barangay Villaflor, Puerto Galera', 'Multi-tiered waterfall accessible by trekking or boat.', ['Waterfall', 'Trek or boat'], ['featured' => true]);
$nat('Python Cave', 'Cave', 'Nature', 'To be confirmed', 'Locality to be confirmed', 'Natural cave with rock formations.', ['Cave'], ['map' => 'Python Cave, Puerto Galera, Oriental Mindoro']);
$nat('Villaflor Hot Spring', 'Hot spring', 'Nature', 'Villaflor', 'Barangay Villaflor, Puerto Galera', 'Developed hot spring resort and warm pools.', ['Hot spring', 'Resort']);
$nat('Tabinay Hot Spring', 'Hot spring', 'Nature', 'Tabinay', 'Barangay Tabinay, Puerto Galera', 'Natural hot spring for relaxation.', ['Hot spring', 'Relaxation']);
$nat('Talipanan Falls', 'Waterfall', 'Nature', 'Aninuan', 'Barangay Aninuan, Puerto Galera', 'Forest-surrounded waterfall reached by trekking.', ['Waterfall', 'Trek']);
$nat('Aninuan Falls', 'Waterfall', 'Nature', 'Aninuan', 'Barangay Aninuan, Puerto Galera', 'Waterfall in a forested setting.', ['Waterfall']);
$nat('Tamaraw Falls', 'Waterfall', 'Nature', 'Villaflor', 'Barangay Villaflor, Puerto Galera', 'Recognizable roadside waterfall with cascading water.', ['Waterfall', 'Roadside']);
$nat('X-treme Sports and Adventure', 'Adventure', 'Recreation', 'Santo Niño', 'Barangay Santo Niño, Puerto Galera', 'Listed for outdoor challenges and adventure activities.', ['Adventure']);
$nat('Ponderosa Golf and Country Club, Inc.', 'Golf', 'Recreation', 'San Isidro', 'Barangay San Isidro, Puerto Galera', 'Golf and group recreation.', ['Golf', 'Groups'], ['slug' => 'ponderosa-golf-and-country-club']);
$nat('Puerto Galera Yacht Club', 'Marina', 'Recreation', 'Poblacion', 'Muelle, Barangay Poblacion, Puerto Galera', 'Marina, sailing, and marine services.', ['Marina', 'Sailing']);
$nat('Hype Genesis', 'Boat cruise', 'Recreation', 'Sabang', 'Sabang, Puerto Galera', 'Party boat and entertainment cruises.', ['Party boat', 'Cruises']);
$nat('Timbugan Cockpit & Sports Center', 'Sports venue', 'Recreation', 'To be confirmed', 'Locality to be confirmed', 'Local sporting and community venue.', ['Sports', 'Community venue'], [
    'slug' => 'timbugan-cockpit-sports-center', 'sensitive' => 'review',
    'note' => 'The name suggests a cockpit. Confirm what events are held, who may attend and which rules apply before visiting. GALÁ does not promote any activity here.']);
$nat('Ka Tuna Cockfight Arena', 'Sports venue', 'Recreation', 'Poblacion', 'Poblacion, Puerto Galera', 'Listed venue for cockfighting events; check applicable laws, eligibility, and suitability before any promotion.', ['Listed venue'], [
    'sensitive' => 'review', 'published' => false,
    'note' => 'Held back from public pages pending review with the Puerto Galera Tourism Office (applicable laws, eligibility and suitability for promotion). Set published to true in data/attractions.php once cleared.']);
$nat('Shooting Range', 'Sports venue', 'Recreation', 'Santo Niño', 'Barangay Santo Niño, Puerto Galera', 'Listed supervised target-shooting recreation; verify operator rules, eligibility, and safety requirements.', ['Supervised', 'Target shooting'], [
    'aka' => 'SDS Inc. Puerto Galera Shooting Range', 'sensitive' => 'review',
    'note' => 'Eligibility, permits, supervision and safety rules are set by the operator and the law. Confirm them with the operator before you plan a visit. GALÁ has not verified them.']);

return $P;
