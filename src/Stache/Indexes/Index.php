<?php

namespace Statamic\Stache\Indexes;

use Facades\Statamic\Stache\Database;
use Statamic\Facades\Stache;
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
        // dump($this);

        return collect($this->items);
    }

    public function values()
    {
        // dump($this);

        return array_values($this->items);
    }

    public function keys()
    {
        // dump($this);

        return array_keys($this->items);
    }

    public function get($key)
    {
        return $this->items[$key] ?? null;
    }

    public function has($key)
    {
        return array_key_exists($key, $this->items);
    }

    public function put($key, $value)
    {
        $this->items[$key] = $value;
    }

    public function push($value)
    {
        $this->items[] = $value;
    }

    public function query()
    {
        return Database::query($this->tableName());
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

        // debugbar()->addMessage("Loading index: {$this->store->key()}/{$this->name}", 'stache');

        // $this->items = Database::get($this->tableName());

        // if ($this->items === null) {
        //     $this->update();
        // }

        // $this->store->cacheIndexUsage($this);

        return $this;
    }

    public function update()
    {
        if (! Stache::shouldUpdateIndexes()) {
            return $this;
        }

        debugbar()->addMessage("Updating index: {$this->store->key()}/{$this->name}", 'stache');

        $this->items = $this->getItems();

        $this->cache();

        return $this;
    }

    public function isCached()
    {
        return Database::has($this->tableName());
    }

    public function cache()
    {
        Database::forever($this->tableName(), $this->items);
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

        unset($this->items[$key]);

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

    public function tableName()
    {
        return str_replace('::', '_', $this->cacheKey());
    }

    public function clear()
    {
        $this->loaded = false;
        $this->items = null;

        Database::forget($this->tableName());
    }
}
