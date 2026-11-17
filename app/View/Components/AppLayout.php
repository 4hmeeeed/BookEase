<?php

namespace App\View\Components;

use Illuminate\View\Component;
use Illuminate\View\View;

class AppLayout extends Component
{
    /**
     * Get the view / contents that represents the component.
     */
    public function render(): View
    {
        // Staff and the owner work in the console; guests keep the resort's top navigation.
        return view(auth()->user()?->isGuest() === false ? 'layouts.console' : 'layouts.app');
    }
}
