<?php

return [
    'banned_words' => [
        'profanity',
        'inappropriate',
        'offensive',
    ],

    'personal_info_patterns' => [
        '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
        '/\b\d{3}[-.]?\d{3}[-.]?\d{4}\b/',
        '/\b\d{11}\b/',
        '/@[\w]+/',
        '/instagram\.com\/[\w]+/',
        '/facebook\.com\/[\w]+/',
        '/twitter\.com\/[\w]+/',
        '/linkedin\.com\/[\w]+/',
    ],

    'report_threshold' => 3,

    'session_expiry_duration' => 3600,
];
