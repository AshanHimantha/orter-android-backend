<?php

return [
    'credentials' => str_replace('/', DIRECTORY_SEPARATOR, storage_path('app/firebase-service-account.json')),
    'project_id' => env('FIREBASE_PROJECT_ID', 'orterclothing-5d6b9'),
    'database_url' => env('FIREBASE_DATABASE_URL'),
    'fcm' => [
        'project_id' => env('FIREBASE_PROJECT_ID', 'orterclothing-5d6b9')
    ]
];
