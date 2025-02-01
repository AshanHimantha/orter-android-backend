<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Auth as FirebaseAuth;
use Kreait\Firebase\Exception\Auth\FailedToVerifyToken;

class FirebaseAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $idTokenString = $request->bearerToken();

        if (!$idTokenString) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        try {
            $firebaseAuth = (new Factory)
                ->withServiceAccount(config('firebase.credentials'))
                ->createAuth();

            $verifiedIdToken = $firebaseAuth->verifyIdToken($idTokenString);
            $request->attributes->set('firebaseUser', $verifiedIdToken);
        } catch (FailedToVerifyToken $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
