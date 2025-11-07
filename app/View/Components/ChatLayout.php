<?php

namespace App\View\Components;

use Illuminate\View\Component;

class ChatLayout extends Component
{
    public $flovatar;
    public $assistant;
    public function __construct($flovatar, $assistant)
    {
        //
        $this->flovatar = $flovatar;
        $this->assistant = $assistant;
    }

    /**
     * Get the view / contents that represents the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('layouts.chat');
    }
}
