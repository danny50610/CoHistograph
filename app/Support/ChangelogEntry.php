<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\HtmlString;

final readonly class ChangelogEntry
{
    public function __construct(
        public string $version,
        public CarbonImmutable $date,
        public HtmlString $html,
    ) {}

    public function githubUrl(): string
    {
        return 'https://github.com/danny50610/CoHistograph/releases/tag/'.$this->version;
    }
}
