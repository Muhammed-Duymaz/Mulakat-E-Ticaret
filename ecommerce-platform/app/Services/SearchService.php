<?php

namespace App\Services;

use App\Services\Search\MysqlSearchEngine;
use App\Services\Search\SearchEngineInterface;
use Exception;

/**
 * SearchService
 *
 * Factory/Manager class that resolves and returns the configured search engine.
 */
class SearchService
{
    /**
     * Resolve the active search engine.
     * 
     * In a real application, you would bind this in a Service Provider
     * based on config('services.search.driver'), e.g. 'mysql', 'elasticsearch'.
     */
    public function engine(): SearchEngineInterface
    {
        $driver = config('services.search.driver', 'mysql');

        return match ($driver) {
            'mysql' => new MysqlSearchEngine(),
            // 'elasticsearch' => new ElasticsearchEngine(),
            // 'algolia' => new AlgoliaEngine(),
            default => throw new Exception("Unsupported search driver: {$driver}"),
        };
    }
}
