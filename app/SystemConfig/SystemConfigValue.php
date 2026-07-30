<?php

namespace App\SystemConfig;

interface SystemConfigValue
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array;

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): static;

    public static function defaults(): static;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
