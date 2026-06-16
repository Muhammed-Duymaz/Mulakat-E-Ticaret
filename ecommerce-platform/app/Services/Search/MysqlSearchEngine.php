<?php

namespace App\Services\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * MysqlSearchEngine
 *
 * A robust fallback implementation using MySQL LIKE queries.
 * Searches across name, sku, short_description, and JSON tags.
 */
class MysqlSearchEngine implements SearchEngineInterface
{
    public function search(string $term, array $filters = [], int $perPage = 20)
    {
        return $this->buildQuery($term, $filters)->paginate($perPage);
    }

    public function autocomplete(string $term, int $limit = 5): array
    {
        $products = $this->buildQuery($term, [])
            ->with(['images' => fn ($q) => $q->where('is_featured', true)])
            ->select('id', 'name', 'slug', 'price', 'discount_price')
            ->limit($limit)
            ->get();

        return $products->map(function (Product $product) {
            return [
                'id'              => $product->id,
                'name'            => $product->name,
                'slug'            => $product->slug,
                'effective_price' => (float) $product->effective_price,
                'image_url'       => $product->featured_image_url,
            ];
        })->toArray();
    }

    /**
     * Build the base Eloquent query for MySQL searching.
     */
    private function buildQuery(string $term, array $filters): Builder
    {
        $query = Product::query()->active()->inStock();

        if (!empty($term)) {
            $searchTerm = '%' . $term . '%';
            $query->where(function (Builder $q) use ($searchTerm) {
                $q->where('name', 'like', $searchTerm)
                  ->orWhere('sku', 'like', $searchTerm)
                  ->orWhere('short_description', 'like', $searchTerm)
                  // MySQL JSON search for tags array
                  ->orWhereJsonContains('tags', $searchTerm);
            });
        }

        // Apply additional repository-style filters if passed
        if (!empty($filters)) {
            $query->filterBy($filters); // utilizing our existing local scope
        }

        // Prioritize relevance (very basic in MySQL, usually just order by best sellers or rating)
        $query->orderBy('views', 'desc')->orderBy('average_rating', 'desc');

        return $query;
    }
}
