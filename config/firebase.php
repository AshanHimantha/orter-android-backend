<?php

return [
    'credentials' => storage_path('app/firebase-service-account.json'),
    'fcm' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
    ],
];
