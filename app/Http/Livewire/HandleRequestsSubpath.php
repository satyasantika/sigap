<?php

namespace App\Http\Livewire;

use Livewire\Mechanisms\HandleRequests\HandleRequests;

class HandleRequestsSubpath extends HandleRequests
{
    public function getUpdateUri(): string
    {
        $route = $this->updateRoute ?? $this->findUpdateRoute();

        return (string) app("url")->toRoute($route, [], true);
    }
}
