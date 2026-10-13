<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;

class UniqueUserNameGenerator
{
    public function fromEmail(string $email): string
    {
        $localPart = Str::of($email)->before('@')->trim()->toString();
        $base = $this->sanitizeBase($localPart);

        $candidate = $base;
        $suffix = 2;

        while (User::query()->where('name', $candidate)->exists()) {
            $candidate = $this->withSuffix($base, (string) $suffix);
            $suffix++;
        }

        return $candidate;
    }

    private function sanitizeBase(string $localPart): string
    {
        $base = Str::of($localPart)
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->substr(0, 255)
            ->toString();

        if ($base === '') {
            return 'user';
        }

        return $base;
    }

    private function withSuffix(string $base, string $suffix): string
    {
        $separator = '-';
        $maxBaseLength = 255 - strlen($separator.$suffix);

        if ($maxBaseLength < 1) {
            return Str::substr($suffix, 0, 255);
        }

        return Str::substr($base, 0, $maxBaseLength).$separator.$suffix;
    }
}
