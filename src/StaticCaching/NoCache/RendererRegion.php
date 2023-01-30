<?php

namespace Statamic\StaticCaching\NoCache;

use Closure;
use Laravel\SerializableClosure\SerializableClosure;
use Statamic\Facades\Blade;

class RendererRegion extends Region
{
    protected $renderer;

    public function __construct(Session $session, Closure $renderer, array $context)
    {
        $this->session = $session;
        $this->renderer = new SerializableClosure($renderer);
        $this->context = $this->filterContext($context);
        $this->key = str_random(32);
    }

    public function key(): string
    {
        return $this->key;
    }

    public function render(): string
    {
        return Blade::executeRenderer($this->renderer, $this->fragmentData());
    }
}
