<?php

namespace App\Services\Search;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * SearchEngineInterface
 *
 * Defines the contract for product search operations.
 * Allows seamless switching between MySQL, Elasticsearch, Algolia, etc.
 */
interface SearchEngineInterface
{
    /**
     * Perform a full search query, returning paginated product results.
     * 
     * @param string $term The search keyword
     * @param array $filters Additional filters (category_id, brand_id, etc.)
     * @param int $perPage
     * @return LengthAwarePaginator|Collection
     */
    public function search(string $term, array $filters = [], int $perPage = 20);

    /**
     * Return ultra-fast suggestions for an autocomplete dropdown.
     * Usually returns minimal data (id, name, slug, thumbnail, price).
     * 
     * @param string $term
     * @param int $limit
     * @return array
     */
    public function autocomplete(string $term, int $limit = 5): array;
}
