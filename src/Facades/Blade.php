<?php

namespace Statamic\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static mixed renderer()
 * @method static mixed usingRenderer(Closure $renderer, Closure $callback)
 * @method static mixed render($str, $variables = [])
 * @method static string renderLoop($content, $data, $supplement = true, $context = [])
 *
 * @see \Statamic\View\Blade\Blade
 */
class Blade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return \Statamic\View\Blade\Blade::class;
    }
}
