<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Google_Client;
use Exception;

class GoogleAuthController extends Controller
{
    /**
     * Verify Google ID Token and authenticate/register the user.
     */
    public function handleGoogleLogin(Request $request)
    {
        $request->validate([
            'credential' => 'required|string',
        ]);

        $idToken = $request->credential;
        $clientId = env('GOOGLE_CLIENT_ID');

        if (!$clientId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Google Client ID is not configured on the server.'
            ], 500);
        }

        try {
            $client = new Google_Client(['client_id' => $clientId]);
            $payload = $client->verifyIdToken($idToken);

            if (!$payload) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid Google ID Token.'
                ], 401);
            }

            // Extract user info from Google Token Payload
            $googleId = $payload['sub'] ?? null;
            $email = $payload['email'] ?? null;
            $name = $payload['name'] ?? null;
            $avatar = $payload['picture'] ?? null;

            if (!$googleId || !$email) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Could not retrieve google_id or email from token.'
                ], 400);
            }

            // Find user by google_id
            $user = User::where('google_id', $googleId)->first();

            if (!$user) {
                // Check if user already exists with the same email
                $user = User::where('email', $email)->first();

                if ($user) {
                    // Link google account to existing user
                    $user->update([
                        'google_id' => $googleId,
                        'avatar' => $avatar,
                    ]);
                } else {
                    // Create a new user
                    $user = User::create([
                        'name' => $name,
                        'email' => $email,
                        'google_id' => $googleId,
                        'avatar' => $avatar,
                        'password' => null, // Password is null since they login via Google
                    ]);
                }
            } else {
                // Update avatar or name if changed
                $user->update([
                    'name' => $name,
                    'avatar' => $avatar,
                ]);
            }

            // Generate Sanctum auth token
            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Authenticated successfully.',
                'token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                ]
            ], 200);

        } catch (Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Google verification failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Logout and revoke user token.
     */
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.'
        ], 200);
    }
}
