<?php
/**
 * Image status per place slug. A photo file at assets/img/places/{slug}.jpg is picked up automatically.
 * Default status for any photo that is NOT listed here is "unconfirmed".
 *
 * status:  unconfirmed = supplied as a reference, rights and exact location not confirmed (shown with a notice)
 *          licensed    = you hold a licence or permission (shown with the credit below)
 * Change the entry to 'licensed' and fill 'credit' once permission is on file.
 */
return [
    '_default' => ['status' => 'unconfirmed', 'credit' => 'Reference photo supplied by the project owner. Rights and exact location to be confirmed.'],
    // 'minolo-beach' => ['status' => 'licensed', 'credit' => 'Photo: Juan Dela Cruz, used with permission (2026)'],
];
