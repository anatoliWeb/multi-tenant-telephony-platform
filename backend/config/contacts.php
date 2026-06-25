<?php

return [
    'default_country' => env('CONTACTS_DEFAULT_COUNTRY'),
    'search' => [
        'max_per_page' => (int) env('CONTACTS_MAX_PER_PAGE', 100),
        'default_per_page' => (int) env('CONTACTS_DEFAULT_PER_PAGE', 20),
    ],
    'import' => [
        'max_rows' => (int) env('CONTACTS_IMPORT_MAX_ROWS', 250),
        'max_file_kb' => (int) env('CONTACTS_IMPORT_MAX_FILE_KB', 1024),
    ],
    'export' => [
        'max_rows' => (int) env('CONTACTS_EXPORT_MAX_ROWS', 5000),
    ],
];
