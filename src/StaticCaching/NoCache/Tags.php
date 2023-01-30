<?php

namespace Statamic\StaticCaching\NoCache;

class Tags extends \Statamic\Tags\Tags
{
    public static $handle = 'nocache';
    public static $stack = 0;

    /**
     * @var Session
     */
    private $nocache;

    public function __construct(Session $nocache)
    {
        $this->nocache = $nocache;
    }

    public function index()
    {
        if ($this->renderer) {
            return $this
                ->nocache
                ->pushRenderer($this->renderer, $this->context->all())
                ->placeholder();
        }

        return $this
            ->nocache
            ->pushRegion($this->content, $this->context->all(), 'antlers.html')
            ->placeholder();
    }
}
