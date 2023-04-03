<?php

namespace Statamic\Stache;

use Illuminate\Database\Connectors\ConnectionFactory;

class Database
{
    protected $connection;

    protected $schema;

    public function __construct()
    {
        // $database = storage_path('framework/cache/stache.sqlite');
        // if (! file_exists($database)) {
        //     touch($database);
        // }

        // $this->connection = app(ConnectionFactory::class)->make([
        //     'driver' => 'sqlite',
        //     'database' => $database,
        // ]);

        $this->connection = app(ConnectionFactory::class)->make([
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'stache',
            'username' => 'root',
            'password' => null,
        ]);

        $this->schema = $this->connection->getSchemaBuilder();
    }

    public function create($table)
    {
        $this->schema->create($table, function ($table) {
            $table->string('id')->primary();
            $table->string('value')->nullable()->index();
        });
    }

    public function get($table)
    {
        if (! $this->has($table)) {
            return null;
        }

        return $this->connection->table($table)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->id => $item->value];
            })
            ->all();
    }

    public function has($table)
    {
        return $this->schema->hasTable($table);
    }

    public function forever($table, $items)
    {
        if (! $this->has($table)) {
            $this->create($table);
        }

        $this->connection->table($table)->truncate();

        collect($items)->each(function ($value, $id) use ($table) {
            $this->connection->table($table)->insert([
                'id' => $id,
                'value' => $value,
            ]);
        });
    }

    public function query($table)
    {
        return $this->connection->table($table);
    }

    public function forget($table)
    {
        $this->schema->drop($table);
    }
}
