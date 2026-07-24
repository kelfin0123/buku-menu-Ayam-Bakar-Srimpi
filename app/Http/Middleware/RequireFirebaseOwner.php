<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class RequireFirebaseOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $segments = explode('.', $token);
            $header = json_decode(JWT::urlsafeB64Decode($segments[0]), true);
            $kid = $header['kid'] ?? null;
            $certificates = Cache::remember(
                'firebase-auth-certificates',
                now()->addMinutes(30),
                fn () => Http::timeout(10)
                    ->get('https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com')
                    ->throw()
                    ->json(),
            );
            abort_unless($kid && isset($certificates[$kid]), 401);
            $claims = (array) JWT::decode(
                $token,
                new Key($certificates[$kid], 'RS256'),
            );
            $projectId = (string) config('firebase.project_id');
            abort_unless(
                ($claims['aud'] ?? null) === $projectId &&
                ($claims['iss'] ?? null) === "https://securetoken.google.com/{$projectId}",
                401,
            );
            $isOwnerClaim = ($claims['role'] ?? null) === 'owner' ||
                ($claims['owner'] ?? false) === true;
            if (! $isOwnerClaim) {
                $uid = $claims['sub'] ?? '';
                $userDocument = Http::withToken($token)
                    ->acceptJson()
                    ->timeout(10)
                    ->get(sprintf(
                        'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/users/%s',
                        rawurlencode($projectId),
                        rawurlencode($uid),
                    ));
                $firestoreRole = $userDocument->json('fields.role.stringValue');
                abort_unless($userDocument->successful() && $firestoreRole === 'owner', 403);
            }
            $request->attributes->set('firebase_uid', $claims['sub'] ?? null);
        } catch (\Throwable $exception) {
            $status = method_exists($exception, 'getStatusCode')
                ? $exception->getStatusCode()
                : 401;

            return response()->json([
                'message' => $status === 403
                    ? 'Owner access required.'
                    : 'Unauthenticated.',
            ], $status);
        }

        return $next($request);
    }
}
