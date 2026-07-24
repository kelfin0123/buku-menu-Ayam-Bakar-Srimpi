<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\Response;

class RequireFirebaseStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        try {
            $segments = explode('.', $token);
            abort_unless(count($segments) === 3, 401);
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
            $claims = (array) JWT::decode($token, new Key($certificates[$kid], 'RS256'));
            $projectId = (string) config('firebase.project_id');
            abort_unless(
                $projectId !== '' &&
                ($claims['aud'] ?? null) === $projectId &&
                ($claims['iss'] ?? null) === "https://securetoken.google.com/{$projectId}",
                401,
            );
            $role = (string) ($claims['role'] ?? '');
            if (! in_array($role, ['employee', 'kasir', 'owner'], true)) {
                $uid = (string) ($claims['sub'] ?? '');
                $document = Http::withToken($token)->acceptJson()->timeout(10)->get(sprintf(
                    'https://firestore.googleapis.com/v1/projects/%s/databases/(default)/documents/users/%s',
                    rawurlencode($projectId),
                    rawurlencode($uid),
                ));
                $role = (string) $document->json('fields.role.stringValue');
                abort_unless(
                    $document->successful() &&
                    in_array($role, ['employee', 'kasir', 'owner'], true),
                    403,
                );
            }
            $request->attributes->set('firebase_uid', $claims['sub'] ?? null);
            $request->attributes->set('firebase_role', $role);
        } catch (\Throwable $exception) {
            $status = method_exists($exception, 'getStatusCode')
                ? $exception->getStatusCode()
                : 401;

            return response()->json([
                'message' => $status === 403 ? 'Staff access required.' : 'Unauthenticated.',
            ], $status);
        }

        return $next($request);
    }
}
