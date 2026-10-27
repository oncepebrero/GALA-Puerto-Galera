<?php
/**
 * Travel information. Figures are VALUES REPORTED in the reviewed reference site summary;
 * they are not confirmed current fares or fees. Reconfirm every amount, operator, schedule and procedure.
 */
return [
    'steps' => [
        ['id' => 'bus', 'title' => 'Bus from Manila to Batangas Port', 'icon' => 'bus',
         'text' => 'Departure areas named include Buendia and Cubao. Operators listed include JAM Liner, DLTB and JAC Liner.',
         'facts' => [['Typical fare', '₱200–₱320'], ['Travel time', '2–3.5 hours']]],
        ['id' => 'ferry', 'title' => 'Ferry from Batangas Port', 'icon' => 'ferry',
         'text' => 'Operators listed include Island Water, Montenegro Lines and Galerian.',
         'facts' => [['One-way fare', '₱600–₱900'], ['Crossing time', '1–2 hours']]],
        ['id' => 'fees', 'title' => 'Terminal and environmental fees', 'icon' => 'ticket',
         'text' => 'The page also described PG PASS registration for a tourist QR code, and an environmental fee collected at Balatero Port.',
         'facts' => [['Terminal fee', '₱90'], ['Environmental fee', '₱120 at Balatero Port']]],
        ['id' => 'local', 'title' => 'Getting around', 'icon' => 'ride',
         'text' => 'Tricycles and blue-and-white electric tuk-tuks are the local options named.',
         'facts' => [['Tricycle / tuk-tuk estimate', '₱200–₱300 to White Beach or Sabang']]],
    ],
    'geo' => [
        ['Location', 'Puerto Galera, Oriental Mindoro, MIMAROPA, Philippines'],
        ['Setting', 'Northern coast of Mindoro Island'],
        ['From Manila', 'About 130 km '],
        ['From Batangas City', 'About 14 nautical miles '],
        ['From Calapan City', 'About 50 km '],
    ],
    'contact' => [
        'phones' => ['0961-681-5244', '0920-988-9551'],
        'emails' => ['puertogalera2025@gmail.com', 'pgtourismoffice@gmail.com'],
        'facebook' => 'https://www.facebook.com/experiencepuertogalera',
        'about' => 'https://www.experiencepuertogalera.com/about',
        'note' => 'Contact details gathered from Experience Puerto Galera. Please confirm before you call or visit.',
    ],
    'assistance' => [
        ['Transport instructions', 'Ask the bus or ferry operators for current departures. Allow extra time on weekends and holidays.'],
        ['Fees and schedules', 'Treat amounts as estimates and confirm them with the operator or the Tourism Office.'],
        ['Tourism-office contact', 'Use the reported contacts on this page and confirm them first.'],
        ['Accessible travel', 'Ask the operator or the Tourism Office about access for wheelchairs, strollers and seniors before you go.'],
        ['Safety notices', 'Weather, sea and trail notices should come from the Tourism Office or local authorities.'],
    ],
];
