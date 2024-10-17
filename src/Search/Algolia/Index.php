<?php

namespace Statamic\Search\Algolia;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\SearchClient;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Arr;
use Statamic\Search\Documents;
use Statamic\Search\Index as BaseIndex;
use Statamic\Search\IndexNotFoundException;
use Statamic\Support\Str;

class Index extends BaseIndex
{
    protected $client;

    public function __construct(SearchClient $client, $name, $config, $locale)
    {
        $this->client = $client;

        parent::__construct($name, $config, $locale);
    }

    public function search($query)
    {
        return (new Query($this))->query($query);
    }

    protected function insertDocuments(Documents $documents)
    {
        $documents = $documents->map(function ($item, $id) {
            $item['objectID'] = $id;

            return $item;
        })->values();

        if (isset($this->config['split'])) {
            $documents = $documents->flatMap(fn ($item) => $this->splitDocument($item, $this->config['split']));
            $this->cleanupSplitDocuments($documents);
            $this->configureSplitIndex();
        }

        try {
            $this->getIndex()->saveObjects($documents);
        } catch (ConnectException $e) {
            throw new \Exception('Error connecting to Algolia. Check your API credentials.', 0, $e);
        }
    }

    protected function cleanupSplitDocuments(Documents $documents)
    {
        $objectIDs = $documents->pluck('objectID');
        $sourceIDs = $documents->pluck('sourceID')->unique();

        $filter = $sourceIDs
            ->map(fn ($sourceID) => "sourceID:'".$sourceID."'")
            ->join(' OR ');

        try {
            $response = $this->getIndex()->search('', [
                'filters' => $filter,
                'attributesToRetrieve' => ['objectID', 'sourceID'],
                'attributesToHighlight' => null,
                'distinct' => false,
            ]);
            $staleObjectIDs = collect($response['hits'])
                ->pluck('objectID')
                ->reject(fn ($objectID) => $objectIDs->contains($objectID))
                ->values()
                ->all();
            $this->getIndex()->delete($staleObjectIDs);
        } catch (ConnectException $e) {
            throw new \Exception('Error connecting to Algolia. Check your API credentials.', 0, $e);
        }
    }

    protected function configureSplitIndex()
    {
        try {
            $settings = $this->getIndex()->getSettings();
            $this->getIndex()->setSettings([
                'distinct' => true,
                'attributeForDistinct' => 'sourceID',
                'attributesForFaceting' => collect($settings['attributesForFaceting'] ?? [])
                    ->push('sourceID')
                    ->unique()
                    ->all(),
            ]);
        } catch (ConnectException $e) {
            throw new \Exception('Error connecting to Algolia. Check your API credentials.', 0, $e);
        }
    }

    protected function splitDocument($item, $field)
    {
        $maxSize = 10_000;
        $getSize = fn ($data) => mb_strlen(json_encode($data));

        $totalSize = $getSize($item);
        if ($totalSize <= $maxSize) {
            return [$item];
        }

        $content = $item[$field];
        $partial = array_merge($item, [
            'objectID' => $item['objectID'].'::chunk-0',
            'sourceID' => $item['objectID'],
            $field => '',
        ]);

        $chunkSize = $maxSize - $getSize($partial);

        $i = 0;
        while (mb_strlen($content)) {
            // The JSON encoded string will probably be longer than the unencoded string, resulting in a document
            // that's over the max size. Rather than try to predict the final size just reduce the chunk size by
            // 10 characters until it fits. This loop will probably only need to run during the first chunk, but
            // if a later chunk happens to go over it will be reduced again.
            do {
                $chunk = Str::safeTruncate($content, $chunkSize);
                $item = array_merge($partial, [
                    'objectID' => $partial['sourceID'].'::chunk-'.$i,
                    $field => $chunk,
                ]);
                $chunkSize = $chunkSize - 10;
            } while ($getSize($item) > $maxSize);
            $content = mb_substr($content, mb_strlen($chunk));
            $documents[] = $item;
            $i++;
        }

        return $documents;
    }

    public function delete($document)
    {
        $this->getIndex()->deleteObject($document->getSearchReference());
    }

    public function deleteIndex()
    {
        $this->getIndex()->delete();
    }

    public function update()
    {
        $index = $this->getIndex();
        $index->clearObjects();

        if (isset($this->config['settings'])) {
            $index->setSettings($this->config['settings']);
        }

        $this->searchables()->lazy()->each(fn ($searchables) => $this->insertMultiple($searchables));

        return $this;
    }

    public function getIndex()
    {
        $indexExisted = $this->exists();
        $index = $this->client->initIndex($this->name);

        if (! $indexExisted && isset($this->config['settings'])) {
            $index->setSettings($this->config['settings']);
        }

        return $index;
    }

    public function searchUsingApi($query, $fields = null)
    {
        $arguments = [];

        if ($fields) {
            $arguments['restrictSearchableAttributes'] = implode(',', Arr::wrap($fields));
        }

        try {
            $response = $this->getIndex()->search($query, $arguments);
        } catch (AlgoliaException $e) {
            $this->handleAlgoliaException($e);
        }

        return collect($response['hits'])->map(function ($hit) {
            $hit['reference'] = isset($this->config['split'])
                ? $hit['sourceID']
                : $hit['objectID'];

            return $hit;
        });
    }

    public function exists()
    {
        return collect($this->client->listIndices()['items'])->first(function ($index) {
            return $index['name'] == $this->name;
        }) !== null;
    }

    private function handleAlgoliaException($e)
    {
        if (Str::contains($e->getMessage(), "Index {$this->name} does not exist")) {
            throw new IndexNotFoundException("Index [{$this->name}] does not exist.");
        }

        if (preg_match('/attribute (.*) is not in searchableAttributes/', $e->getMessage(), $matches)) {
            throw new \Exception(
                "Field [{$matches[1]}] does not exist in this index's searchableAttributes list."
            );
        }

        throw $e;
    }
}
