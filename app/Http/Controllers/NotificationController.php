<?php

namespace App\Http\Controllers;

use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    public function sendNotification(Request $request)
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'title' => 'required|string',
                'body' => 'required|string',
            ]);

            // Set timezone (critical)
            date_default_timezone_set('UTC'); // Or your server's correct timezone!

            // Load Firebase credentials
            $firebaseCredentials = json_decode(file_get_contents(config('firebase.credentials')), true);

            if (!$firebaseCredentials) {
                Log::error('Firebase credentials not found or invalid.'); //More specific log
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase credentials not found or invalid'
                ], 500);
            }

            // Fixed JWT claim timing
            $now = time();
            $payload = [
                'iss' => $firebaseCredentials['client_email'],
                'sub' => $firebaseCredentials['client_email'],
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,                 // Current time
                'exp' => $now + 3600,          // Exactly 1 hour from now
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
            ];

            // Debug log - VERY IMPORTANT FOR DIAGNOSIS
            Log::debug('JWT Details:', [
                'now (time())' => $now,
                'iat' => $payload['iat'],
                'exp' => $payload['exp'],
                'difference (exp - iat)' => $payload['exp'] - $payload['iat'],
                'now (date)' => date('Y-m-d H:i:s', $now),   // Human-readable time
                'iat (date)' => date('Y-m-d H:i:s', $payload['iat']),
                'exp (date)' => date('Y-m-d H:i:s', $payload['exp']),
                'timezone' => date_default_timezone_get() // Log the timezone
            ]);


            // Get access token with error details
            try {
                $jwt = JWT::encode($payload, $firebaseCredentials['private_key'], 'RS256');
                $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                    'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                    'assertion' => $jwt,
                ]);
            } catch (\Exception $e) {
                Log::error('JWT Encoding Error:', ['error' => $e->getMessage()]);
                return response()->json([
                    'success' => false,
                    'message' => 'JWT Encoding Error',
                    'error' => $e->getMessage()
                ], 500);
            }

            if (!$tokenResponse->successful()) {
                Log::error('Failed to obtain access token:', ['error' => $tokenResponse->json()]); //Detailed error log
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to obtain access token',
                    'error' => $tokenResponse->json()
                ], 500);
            }

            $accessToken = $tokenResponse->json()['access_token'];
            $fcmUrl = 'https://fcm.googleapis.com/v1/projects/' . config('firebase.fcm.project_id') . '/messages:send';

            // Prepare notification payload
            $data = [
                'message' => [
                    'token' => $request->token,
                    'notification' => [
                        'title' => $request->title,
                        'body' => $request->body,
                    ],
                    'data' => $request->data ?? [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'status' => 'done'
                    ],
                    'android' => [
                        'priority' => 'high',
                    ]
                ]
            ];

            // Send notification
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($fcmUrl, $data);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Notification sent successfully',
                    'data' => $response->json()
                ], 200);
            } else {
                Log::error('Failed to send notification:', ['status' => $response->status(), 'error' => $response->json()]); // Detailed error log
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to send notification',
                    'error' => $response->json()
                ], $response->status());
            }

        } catch (\Exception $e) {
            Log::error('Notification Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}