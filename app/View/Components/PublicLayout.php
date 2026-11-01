<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class PublicLayout extends Component
{
    /**
     * @param  bool  $overlay  float the header over the hero (landing page)
     */
    public function __construct(
        public ?string $title = null,
        public bool $overlay = false,
    ) {}

    public function render(): View
    {
        return view('layouts.public');
    }
}
