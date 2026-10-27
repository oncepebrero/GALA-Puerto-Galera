<?php
/**
 * SAMPLE events and offers. They are copied into the database once (when the events table is first created) and can be
 * edited, verified, hidden or deleted in the staff area (Events tab). Dates are relative to today, so the sample
 * always shows a healthy mix of ongoing, upcoming, past and "date to be confirmed" items.
 * Fields: title, type (Event | Workshop | Promotion | Announcement), date, end (optional), venue, organizer, text, link, verified.
 */
$d = fn(int $days) => date('Y-m-d', strtotime(($days >= 0 ? '+' : '') . $days . ' days'));
return [
    ['title' => 'Sunset Beach Music Night', 'type' => 'Event', 'date' => $d(6), 'end' => null, 'verified' => true,
     'venue' => 'Sabang Beach, Puerto Galera', 'organizer' => 'Sabang Beach Association',
     'text' => 'Local bands, grilled seafood and a sunset over the bay. Bring a mat or a chair and enjoy the evening on the sand.'],
    ['title' => 'Coastal Clean-up and Reef Care Day', 'type' => 'Event', 'date' => $d(12), 'end' => null, 'verified' => true,
     'venue' => 'Sabang, Puerto Galera', 'organizer' => 'Local dive and resort community',
     'text' => 'Gloves on, bags out. Volunteers clean the shoreline in the morning and learn reef-friendly habits from local divers in the afternoon.'],
    ['title' => 'Indigenous Peoples Month: Cultural Exchange', 'type' => 'Event', 'date' => $d(-20), 'end' => $d(10), 'verified' => true,
     'venue' => 'Poblacion, Puerto Galera', 'organizer' => 'Puerto Galera Tourism Office',
     'text' => 'A month of talks, exhibits and community-led activities celebrating Mangyan culture. Join through the community or the Tourism Office, and ask before taking photographs.'],
    ['title' => 'Weekday Island-hopping Offers', 'type' => 'Promotion', 'date' => $d(-5), 'end' => $d(50), 'verified' => true,
     'venue' => 'Participating boat operators', 'organizer' => 'Participating operators',
     'text' => 'Ask partner operators about weekday island-hopping packages to Banana Beach, Medio Island and the bays around Puerto Galera.'],
    ['title' => 'Smart Tourism Workshop for Local Operators', 'type' => 'Workshop', 'date' => $d(25), 'end' => null, 'verified' => false,
     'venue' => 'Puerto Galera (venue to be announced)', 'organizer' => 'Puerto Galera Tourism Office',
     'text' => 'A hands-on session on digital listings, visitor feedback and sustainable tour design for guides, resorts and boat operators.'],
    ['title' => 'Dive Safety and Marine Conservation Forum', 'type' => 'Event', 'date' => $d(40), 'end' => null, 'verified' => false,
     'venue' => 'Sabang, Puerto Galera', 'organizer' => 'Local dive operators',
     'text' => 'Dive professionals share reef-protection practices, safety briefings and what visitors can do to keep the Verde Island Passage thriving.'],
    ['title' => 'Parol and Lights Night', 'type' => 'Event', 'date' => $d(48), 'end' => null, 'verified' => false,
     'venue' => 'Poblacion, Puerto Galera', 'organizer' => 'Puerto Galera community groups',
     'text' => 'Handmade lanterns, music and food along the waterfront as the town lights up for Christmas.'],
    ['title' => 'Mangyan Weaving Demonstration', 'type' => 'Event', 'date' => null, 'end' => null, 'verified' => false,
     'venue' => 'Venue to be announced', 'organizer' => 'Community cultural group',
     'text' => 'A hands-on look at traditional Mangyan weaving. The date is still being confirmed, so please check with the Tourism Office.'],
    ['title' => 'Sustainable Tourism Improvements Briefing', 'type' => 'Announcement', 'date' => $d(-38), 'end' => null, 'verified' => true,
     'venue' => 'Puerto Galera', 'organizer' => 'Puerto Galera Tourism Office',
     'text' => 'An update on environmental fees, visitor registration and community projects planned for the coming season.'],
];
