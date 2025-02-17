<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/deploy', function () {
    // Only allow in development/staging
    if (!app()->environment('production')) {
        try {
            // Store output
            $output = [];
            
            // Clear all cache
            $output[] = Artisan::call('optimize:clear');
            
            // Install dependencies
            $output[] = shell_exec('composer install --no-dev --optimize-autoloader');
            
            // Cache configuration
            $output[] = Artisan::call('config:cache');
            $output[] = Artisan::call('route:cache');
            $output[] = Artisan::call('view:cache');
            
            // Run migrations
            $output[] = Artisan::call('migrate', ['--force' => true]);
            
            // Create storage link
            $output[] = Artisan::call('storage:link');
            
            // Install and build frontend assets
            $output[] = shell_exec('npm install');
            $output[] = shell_exec('npm run build');
            
            return response()->json([
                'success' => true,
                'message' => 'Deployment completed successfully',
                'output' => $output
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Deployment failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    return response()->json([
        'success' => false,
        'message' => 'This route is not available in production'
    ], 403);
})->name('deploy');

