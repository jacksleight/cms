<?php

namespace Statamic\Stache\Indexes;

use Illuminate\Support\Facades\Cache;
use Statamic\Facades\Stache;
use Statamic\Stache\Stores\ChildStore;
use Statamic\Statamic;

abstract class Index
{
    protected $store;
    protected $name;
    protected $items = [];
    protected $loaded = false;

    public function __construct($store, $name)
    {
        $this->store = $store;
        $this->name = $name;
    }

    public function name()
    {
        return $this->name;
    }

    public function items()
    {
        return collect($this->items);
    }

    public function values()
    {
        return array_values($this->items);
    }

    public function keys()
    {
        return array_keys($this->items);
    }

    public function get($key)
    {
        return $this->items[$this->prefixKey($key)] ?? null;
    }

    public function has($key)
    {
        return array_key_exists($this->prefixKey($key), $this->items);
    }

    public function put($key, $value)
    {
        $this->items[$this->prefixKey($key)] = $value;
    }

    public function push($value)
    {
        $this->items[] = $value;
    }

    public function load()
    {
        if ($this->loaded) {
            return $this;
        }

        $this->loaded = true;

        if (Statamic::isWorker()) {
            $this->loaded = false;
        }

        debugbar()->addMessage("Loading index: {$this->store->key()}/{$this->name}", 'stache');

        $this->items = Cache::get($this->cacheKey());

        if ($this->items === null) {
            $this->update();
        }

        $this->store->cacheIndexUsage($this);

        return $this;
    }

    public function update()
    {
        if (! Stache::shouldUpdateIndexes()) {
            return $this;
        }

        debugbar()->addMessage("Updating index: {$this->store->key()}/{$this->name}", 'stache');

        $this->items = collect($this->getItems())
            ->mapWithKeys(fn ($value, $key) => [$this->prefixKey($key) => $value])
            ->all();

        $this->cache();

        return $this;
    }

    public function isCached()
    {
        return Cache::has($this->cacheKey());
    }

    public function cache()
    {
        Cache::forever($this->cacheKey(), $this->items);
    }

    public function updateItem($item)
    {
        $this->load();

        $this->put($this->store->getItemKey($item), $this->getItemValue($item));

        $this->cache();
    }

    public function forgetItem($key)
    {
        $this->load();

        unset($this->items[$this->prefixKey($key)]);

        $this->cache();
    }

    abstract public function getItems();

    public function cacheKey()
    {
        return vsprintf('stache::indexes::%s::%s', [
            $this->store->key(),
            str_replace('.', '::', $this->name),
        ]);
    }

    public function clear()
    {
        $this->loaded = false;
        $this->items = null;

        Cache::forget($this->cacheKey());
    }

    public function prefixKey($key)
    {
        // Temp hack, store path lookups dont want the prefix and excluding it here was easiest
        if ($this->name === 'path') {
            return $key;
        }

        return $this->store instanceof ChildStore
            ? ($this->store->childKey().'::'.$key)
            : $key;
    }
}
