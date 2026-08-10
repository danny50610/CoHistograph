<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;

class AuthorizedAppService
{
    /**
     * Active MCP authorizations aggregated by OAuth client.
     *
     * Each item contains: client_id, name, token_count, authorized_at,
     * last_used_at, last_used_ip, last_used_user_agent, expires_at.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function listForUser(User $user): Collection
    {
        $tokens = $user->tokens()
            ->with('client')
            ->where('revoked', false)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('last_used_at')
            ->orderByDesc('created_at')
            ->get()
            ->filter(fn ($token): bool => $token->can('mcp:use'))
            ->values();

        $apps = [];

        foreach ($tokens->groupBy(fn ($token): string => (string) $token->client_id) as $clientTokens) {
            /** @var Collection<int, \Laravel\Passport\Token> $clientTokens */
            $latestUsage = $clientTokens
                ->sortByDesc(fn ($token): int => $this->toCarbon($token->getAttribute('last_used_at'))?->getTimestamp() ?? 0)
                ->first();

            if ($latestUsage === null) {
                continue;
            }

            $client = $latestUsage->client;
            if (! $client instanceof Client || $client->revoked) {
                continue;
            }

            $apps[] = [
                'client_id' => (string) $client->getKey(),
                'name' => $client->name,
                'token_count' => $clientTokens->count(),
                'authorized_at' => $this->toCarbon($clientTokens->min('created_at')),
                'last_used_at' => $this->toCarbon($clientTokens->max('last_used_at')),
                'last_used_ip' => $this->nullableString($latestUsage->getAttribute('last_used_ip')),
                'last_used_user_agent' => $this->nullableString($latestUsage->getAttribute('last_used_user_agent')),
                'expires_at' => $this->toCarbon($clientTokens->max('expires_at')),
            ];
        }

        usort($apps, function (array $left, array $right): int {
            $leftSort = ($left['last_used_at'] instanceof Carbon ? $left['last_used_at']->getTimestamp() : null)
                ?? ($left['authorized_at'] instanceof Carbon ? $left['authorized_at']->getTimestamp() : null)
                ?? 0;
            $rightSort = ($right['last_used_at'] instanceof Carbon ? $right['last_used_at']->getTimestamp() : null)
                ?? ($right['authorized_at'] instanceof Carbon ? $right['authorized_at']->getTimestamp() : null)
                ?? 0;

            return $rightSort <=> $leftSort;
        });

        /** @var Collection<int, array<string, mixed>> $result */
        $result = new Collection($apps);

        return $result;
    }

    /**
     * Revoke all of the user's active MCP access tokens (and refresh tokens) for a client.
     */
    public function revokeForUser(User $user, Client $client): int
    {
        $tokens = $user->tokens()
            ->where('client_id', $client->getKey())
            ->where('revoked', false)
            ->get()
            ->filter(fn ($token): bool => $token->can('mcp:use'));

        if ($tokens->isEmpty()) {
            return 0;
        }

        return (int) DB::transaction(function () use ($tokens): int {
            $tokenIds = $tokens->modelKeys();

            Passport::token()->newQuery()
                ->whereIn('id', $tokenIds)
                ->update(['revoked' => true]);

            RefreshToken::query()
                ->whereIn('access_token_id', $tokenIds)
                ->update(['revoked' => true]);

            return count($tokenIds);
        });
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance(\DateTimeImmutable::createFromInterface($value));
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
