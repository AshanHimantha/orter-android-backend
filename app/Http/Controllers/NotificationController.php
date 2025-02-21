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

            // Load Firebase credentials
            $firebaseCredentials = json_decode(file_get_contents(config('firebase.credentials')), true);
            
            if (!$firebaseCredentials) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase credentials not found or invalid'
                ], 500);
            }

            // Prepare JWT claim
            $issuedAt = time();
            $expirationTime = $issuedAt + 3600;
            $payload = [
                'iss' => $firebaseCredentials['client_email'],
                'sub' => $firebaseCredentials['client_email'],
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $issuedAt,
                'exp' => $expirationTime,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging'
            ];

            // Get access token
            $jwt = JWT::encode($payload, $firebaseCredentials['private_key'], 'RS256');
            $tokenResponse = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if (!$tokenResponse->successful()) {
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
                    'data' => [
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
                        'status' => 'done'
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
