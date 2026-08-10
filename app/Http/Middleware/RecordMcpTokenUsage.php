<?php

namespace App\Http\Middleware;

use App\Models\Passport\Token;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Passport\AccessToken;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

class RecordMcpTokenUsage
{
    /**
     * Skip rewriting last_used_* when the same client fingerprint hit within this window.
     */
    private const ThrottleSeconds = 60;

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();
        if (! $user instanceof User) {
            return $response;
        }

        $accessToken = $user->token();
        if (! $accessToken instanceof AccessToken) {
            return $response;
        }

        $tokenId = $accessToken->oauth_access_token_id ?? null;
        if (! is_string($tokenId) || $tokenId === '') {
            return $response;
        }

        $token = Passport::token()->newQuery()->find($tokenId);
        if (! $token instanceof Token || $token->revoked) {
            return $response;
        }

        $ip = $request->ip();
        $userAgent = $this->truncateUserAgent($request->userAgent());

        if ($this->shouldSkipUpdate($token->last_used_at, $token->last_used_ip, $token->last_used_user_agent, $ip, $userAgent)) {
            return $response;
        }

        $token->forceFill([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
            'last_used_user_agent' => $userAgent,
        ])->save();

        return $response;
    }

    private function shouldSkipUpdate(
        ?Carbon $lastUsedAt,
        ?string $lastUsedIp,
        ?string $lastUsedUserAgent,
        ?string $ip,
        ?string $userAgent,
    ): bool {
        if ($lastUsedAt === null) {
            return false;
        }

        if ($lastUsedIp !== $ip || $lastUsedUserAgent !== $userAgent) {
            return false;
        }

        return $lastUsedAt->gt(now()->subSeconds(self::ThrottleSeconds));
    }

    private function truncateUserAgent(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        return mb_substr($userAgent, 0, 512);
    }
}
