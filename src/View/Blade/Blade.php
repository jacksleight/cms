<?php

namespace Statamic\View\Blade;

use Closure;

class Blade
{
    protected $renderer;

    public function renderer()
    {
        return $this->renderer;
    }

    public function usingRenderer(Closure $renderer, Closure $callback)
    {
        $this->renderer = $renderer;

        $contents = $callback($this);

        $this->renderer = null;

        return $contents;
    }

    public function render($data = [], $context = [])
    {
        return $this->executeRenderer($this->renderer, array_merge($context, $data));
    }

    /**
     * Iterate over an array and call the renderer for each.
     *
     * @param  array  $data
     * @param  bool  $supplement
     * @param  array  $context
     * @return string
     */
    public function renderLoop($data, $supplement = true, $context = [])
    {
        $total = count($data);
        $i = 0;

        $contents = collect($data)->reduce(function ($carry, $item) use (&$i, $total, $supplement, $context) {
            if ($supplement) {
                $item = array_merge($item, [
                    'index' => $i,
                    'count' => $i + 1,
                    'total_results' => $total,
                    'first' => ($i === 0),
                    'last' => ($i === $total - 1),
                ]);
            }

            $i++;

            $rendered = $this->executeRenderer($this->renderer, array_merge($context, $item));

            return $carry.$rendered;
        }, '');

        return $contents;
    }

    public function executeRenderer($renderer, $variables)
    {
        ob_start();

        $renderer($variables);

        return ob_get_clean();
    }
}
