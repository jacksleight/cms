<?php

namespace Statamic\Tags;

use Closure;
use Illuminate\Support\Collection;
use Statamic\Contracts\Data\Augmentable;
use Statamic\Contracts\View\Antlers\Parser;
use Statamic\Facades\Compare;
use Statamic\Fields\Value;
use Statamic\Support\Arr;
use Statamic\Support\Str;

class BladeDirective
{
    public function handle(Closure $renderer, $name, array $params, array $context = null)
    {
        if (func_num_args() == 2) {
            $context = $params;
            $params = [];
        }

        if ($pos = strpos($name, ':')) {
            $originalMethod = substr($name, $pos + 1);
            $method = Str::camel($originalMethod);
            $name = substr($name, 0, $pos);
        } else {
            $method = $originalMethod = 'index';
        }

        $tag = app(Loader::class)->load($name, [
            'parser'     => null,
            'params'     => $params,
            'content'    => '',
            'context'    => $context,
            'renderer'   => $renderer,
            'tag'        => $name.':'.$originalMethod,
            'tag_method' => $originalMethod,
        ]);

        $output = call_user_func([$tag, $method]);

        if (Compare::isQueryBuilder($output)) {
            $output = $output->get();
        }

        if ($output instanceof Collection) {
            $output = $output->toAugmentedArray();
        }

        if ($output instanceof Augmentable) {
            $output = $output->toAugmentedArray();
        }

        // Allow tags to return an array. We'll parse it for them.
        if (is_array($output)) {
            if (empty($output)) {
                $output = $tag->parseNoResults();
            } else {
                $output = Arr::assoc($output) ? $tag->parse($output) : $tag->parseLoop($output);
            }
        }

        if ($output instanceof Value) {
            $output = $output->antlersValue(app(Parser::class), $context);
        }

        return $output;
    }
}
