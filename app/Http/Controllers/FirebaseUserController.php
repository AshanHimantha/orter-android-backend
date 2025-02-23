<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class FirebaseUserController extends Controller
{
    protected $auth;

    public function __construct()
    {
        $this->auth = app('firebase.auth');
    }

    public function index()
    {
        try {
            $users = $this->auth->listUsers();
            $formattedUsers = [];

            foreach ($users as $user) {
                $formattedUsers[] = [
                    'uid' => $user->uid,
                    'email' => $user->email,
                    'displayName' => $user->displayName,
                    'phoneNumber' => $user->phoneNumber,
                    'emailVerified' => $user->emailVerified,
                    'disabled' => $user->disabled,
                    'metadata' => [
                        'createdAt' => $user->metadata->createdAt,
                        'lastLoginAt' => $user->metadata->lastLoginAt
                    ]
                ];
            }

            return response()->json([
                'status' => true,
                'data' => $formattedUsers
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching Firebase users:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error fetching users'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required|min:6',
                'display_name' => 'required|string',
                'phone_number' => 'nullable|string'
            ]);

            $userProperties = [
                'email' => $request->email,
                'emailVerified' => false,
                'password' => $request->password,
                'displayName' => $request->display_name,
                'disabled' => false
            ];

            if ($request->phone_number) {
                $userProperties['phoneNumber'] = $request->phone_number;
            }

            $createdUser = $this->auth->createUser($userProperties);

            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
                'data' => [
                    'uid' => $createdUser->uid,
                    'email' => $createdUser->email,
                    'displayName' => $createdUser->displayName,
                    'phoneNumber' => $createdUser->phoneNumber
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error creating Firebase user:', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error creating user'
            ], 500);
        }
    }

    public function update(Request $request, $uid)
    {
        try {
            $request->validate([
                'email' => 'nullable|email',
                'password' => 'nullable|min:6',
                'display_name' => 'nullable|string',
                'phone_number' => 'nullable|string',
                'disabled' => 'nullable|boolean'
            ]);

            $properties = [];

            if ($request->has('email')) {
                $properties['email'] = $request->email;
            }
            if ($request->has('password')) {
                $properties['password'] = $request->password;
            }
            if ($request->has('display_name')) {
                $properties['displayName'] = $request->display_name;
            }
            if ($request->has('phone_number')) {
                $properties['phoneNumber'] = $request->phone_number;
            }
            if ($request->has('disabled')) {
                $properties['disabled'] = $request->disabled;
            }

            $updatedUser = $this->auth->updateUser($uid, $properties);

            return response()->json([
                'status' => true,
                'message' => 'User updated successfully',
                'data' => [
                    'uid' => $updatedUser->uid,
                    'email' => $updatedUser->email,
                    'displayName' => $updatedUser->displayName,
                    'phoneNumber' => $updatedUser->phoneNumber,
                    'disabled' => $updatedUser->disabled
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating Firebase user:', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error updating user'
            ], 500);
        }
    }

    public function destroy($uid)
    {
        try {
            $this->auth->deleteUser($uid);

            // Also delete from local database if exists
            User::where('firebase_uid', $uid)->delete();

            return response()->json([
                'status' => true,
                'message' => 'User deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error deleting Firebase user:', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error deleting user'
            ], 500);
        }
    }

    public function show($uid)
    {
        try {
            $user = $this->auth->getUser($uid);

            return response()->json([
                'status' => true,
                'data' => [
                    'uid' => $user->uid,
                    'email' => $user->email,
                    'displayName' => $user->displayName,
                    'phoneNumber' => $user->phoneNumber,
                    'emailVerified' => $user->emailVerified,
                    'disabled' => $user->disabled,
                    'metadata' => [
                        'createdAt' => $user->metadata->createdAt,
                        'lastLoginAt' => $user->metadata->lastLoginAt
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching Firebase user:', [
                'uid' => $uid,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'status' => false,
                'message' => 'Error fetching user'
            ], 500);
        }
    }
}