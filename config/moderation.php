<?php

return [
    /*
     * Whole words masked with asterisks (case-insensitive) before a message is
     * stored. PLACEHOLDER LIST: replace with the real words you want masked.
     */
    'banned_words' => [
        'profanity',
        'inappropriate',
        'offensive',
    ],

    /*
     * Clear personal information. Messages matching these are REJECTED by the
     * ContentFilter middleware with a 400 and never stored.
     */
    'personal_info_patterns' => [
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}\b/', // email
        '/\b\d{3}[-.\s]?\d{3}[-.\s]?\d{4}\b/',                 // 10-digit phone
        '/\b\d{11}\b/',                                         // 11-digit phone (e.g. 09xxxxxxxxx)
    ],

    /*
     * Softer signals of contact sharing. Messages matching these are DELIVERED
     * but marked is_flagged so admins can review them on the Flagged page.
     */
    'flag_patterns' => [
        '/(^|\s)@\w{3,}/',                                      // social handle, e.g. "@juan_dc"
        '/\b(instagram|facebook|fb|twitter|x|tiktok|linkedin|t)\.(com|me)\/\w+/i',
    ],

    // Distinct reporters needed before a guest is auto-banned
    'report_threshold' => (int) env('REPORT_THRESHOLD', 3),
];
