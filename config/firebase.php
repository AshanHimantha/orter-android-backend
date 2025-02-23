<?php

return [
    'credentials' => base_path('firebase-service-account.json'),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'database_url' => env('FIREBASE_DATABASE_URL'),
];
