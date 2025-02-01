<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class UserController extends Controller
{
    protected $auth;

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    // Verify and sync user
    public function verifyAndSyncUser(Request $request)
    {
        try {
            $bearerToken = $request->bearerToken();
            if (!$bearerToken) {
                return response()->json(['status' => false, 'message' => 'No token provided'], 401);
            }

            $verifiedIdToken = $this->auth->verifyIdToken($bearerToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            $firebaseUser = $this->auth->getUser($uid);

            $user = User::updateOrCreate(
                ['firebase_uid' => $uid],  // Primary key for finding user
                [
                    'name' => $firebaseUser->displayName ?? '',
                    'email' => $firebaseUser->email,
                    'phone' => $firebaseUser->phoneNumber,
                    'is_active' => true
                ]
            );

            return response()->json([
                'status' => true,
                'data' => $user
            ]);

        } catch (FailedToVerifyToken $e) {
            return response()->json(['status' => false, 'message' => 'Invalid token'], 401);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 500);
        }
    }

    // Update FCM token
    public function updateFcmToken(Request $request)
    {
        try {
            $bearerToken = $request->bearerToken();
            $verifiedIdToken = $this->auth->verifyIdToken($bearerToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            $user = User::where('firebase_uid', $uid)->first();
            $user->update(['fcm_token' => $request->fcm_token]);

            return response()->json([
                'status' => true,
                'message' => 'FCM token updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Get user profile
    public function profile(Request $request)
    {
        try {
            $bearerToken = $request->bearerToken();
            $verifiedIdToken = $this->auth->verifyIdToken($bearerToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            $user = User::where('firebase_uid', $uid)->first();

            return response()->json([
                'status' => true,
                'data' => $user
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Deactivate user
    public function deactivate(Request $request)
    {
        try {
            $bearerToken = $request->bearerToken();
            $verifiedIdToken = $this->auth->verifyIdToken($bearerToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            $user = User::where('firebase_uid', $uid)->first();
            $user->update(['is_active' => false]);

            return response()->json([
                'status' => true,
                'message' => 'User deactivated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 400);
        }
    }

    // Create User
    public function create(Request $request)
    {
        try {
            $userProperties = [
                'email' => $request->email,
                'password' => $request->password,
                'displayName' => $request->name,
            ];

            $user = $this->auth->createUser($userProperties);

            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
                'data' => $user
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // Update User
    public function update(Request $request)
    {
        try {
            $bearerToken = $request->bearerToken();
            $verifiedIdToken = $this->auth->verifyIdToken($bearerToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            $properties = [];
            if ($request->has('displayName')) $properties['displayName'] = $request->displayName;
            if ($request->has('photoURL')) $properties['photoUrl'] = $request->photoURL;
            if ($request->has('email')) $properties['email'] = $request->email;
            if ($request->has('password')) $properties['password'] = $request->password;

            $updatedUser = $this->auth->updateUser($uid, $properties);

            return response()->json([
                'status' => true,
                'message' => 'User updated successfully',
                'data' => $updatedUser
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    // Delete User
    public function delete(Request $request)
    {
        try {
            $bearerToken = $request->bearerToken();
            $verifiedIdToken = $this->auth->verifyIdToken($bearerToken);
            $uid = $verifiedIdToken->claims()->get('sub');

            $this->auth->deleteUser($uid);

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
