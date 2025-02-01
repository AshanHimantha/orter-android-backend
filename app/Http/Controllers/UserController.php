<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Kreait\Firebase\Auth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class UserController extends Controller
{
    protected $auth;
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    public function profile(Request $request)
    {
        try {
            $bearerToken = $request->bearerToken();
            if (!$bearerToken) {
                return response()->json(['status' => false, 'message' => 'No token provided'], 401);
            }

            // Check cache first
            $cacheKey = 'user_profile_' . md5($bearerToken);
            if ($cachedProfile = Cache::get($cacheKey)) {
                return response()->json([
                    'status' => true,
                    'data' => $cachedProfile,
                    'source' => 'cache'
                ]);
            }

            // Verify token and get user data
            $verifiedIdToken = $this->auth->verifyIdToken($bearerToken);
            $uid = $verifiedIdToken->claims()->get('sub');
            $user = $this->auth->getUser($uid);

            $userData = [
                'uid' => $user->uid,
                'email' => $user->email,
                'displayName' => $user->displayName,
                'phoneNumber' => $user->phoneNumber,
                'photoURL' => $user->photoUrl,
                'emailVerified' => $user->emailVerified
            ];

            // Cache the result
            Cache::put($cacheKey, $userData, self::CACHE_TTL);

            return response()->json([
                'status' => true,
                'data' => $userData,
                'source' => 'firebase'
            ]);

        } catch (FailedToVerifyToken $e) {
            return response()->json(['status' => false, 'message' => 'Invalid token'], 401);
        }
    }
}
