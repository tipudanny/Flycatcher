<?php

return [
    /*
     * Path to your Firebase service account JSON file.
     * Download from: Firebase Console → Project Settings → Service Accounts
     *                → Generate new private key
     *
     * Store at: backend/storage/firebase-credentials.json
     * Add that path to .gitignore — never commit credentials.
     */
    'credentials' => env('FIREBASE_CREDENTIALS', 'storage/firebase-credentials.json'),

    /*
     * Your Realtime Database URL, e.g.:
     * https://your-project-default-rtdb.firebaseio.com
     */
    'database_url' => env('FIREBASE_DATABASE_URL'),
];
