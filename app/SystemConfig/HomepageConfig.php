<?php

namespace App\SystemConfig;

final readonly class HomepageConfig implements SystemConfigValue
{
    public const DEFAULT_TAGLINE = '一個協作式平台，用於構建、管理及探索歷史事件知識圖譜。';

    public function __construct(
        public string $tagline,
    ) {}

    public static function rules(): array
    {
        return [
            'tagline' => ['required', 'string', 'max:500'],
        ];
    }

    public static function fromValidated(array $data): static
    {
        return new self(
            tagline: $data['tagline'],
        );
    }

    public static function defaults(): static
    {
        return new self(
            tagline: self::DEFAULT_TAGLINE,
        );
    }

    public function toArray(): array
    {
        return [
            'tagline' => $this->tagline,
        ];
    }
}
