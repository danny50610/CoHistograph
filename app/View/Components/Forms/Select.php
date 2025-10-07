<?php

namespace App\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Select extends Component
{
    public function __construct(
        public string $value = '',
        public string $id = '',
        public string $label = '',
        public bool $required = false,
        public bool $readonly = false,
        public ?string $helpText = null,
        public array $options = [],
    ) {
    }

    public function render(): View|Closure|string
    {
        return view('components.forms.select');
    }
}
