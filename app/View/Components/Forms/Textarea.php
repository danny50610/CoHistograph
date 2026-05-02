<?php

namespace App\View\Components\Forms;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Textarea extends Component
{
    public function __construct(
        public string $id = '',
        public string $label = '',
        public string $placeholder = '',
        public int $rows = 8,
        public bool $required = false,
        public ?string $helpText = null
    ) {}

    public function render(): View|Closure|string
    {
        return view('components.forms.textarea');
    }
}
