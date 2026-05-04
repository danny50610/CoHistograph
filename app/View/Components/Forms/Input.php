<?php

namespace App\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Input extends Component
{
    public function __construct(
        public string $value = '',
        public string $id = '',
        public string $label = '',
        public string $type = 'text',
        public string $placeholder = '',
        public bool $required = false,
        public bool $readonly = false,
        public ?string $helpText = null,
        public ?string $autocomplete = null,
        public ?string $inputMode = null,
    ) {
    }

    public function render(): View|Closure|string
    {
        return view('components.forms.input');
    }
}
