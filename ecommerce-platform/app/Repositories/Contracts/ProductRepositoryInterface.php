<?php

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * ProductRepositoryInterface
 *
 * Defines the contract for all product data-access operations.
 * By depending on this interface (not the Eloquent implementation),
 * controllers and services remain decoupled from the ORM layer.
 * Swapping to a different data source (e.g. Elasticsearch) only
 * requires a new implementation — zero changes to consumers.
 */
interface ProductRepositoryInterface
{
    /**
     * Return a cursor-paginated list of products with filters applied.
     *
     * @param array{
     *   category_id?: int,
     *   brand_id?: int,
     *   vendor_id?: int,
     *   min_price?: float,
     *   max_price?: float,
     *   option_value_ids?: int[],
     *   search?: string,
     *   sort?: string,
     *   per_page?: int,
     * } $filters
     */
    public function paginateWithFilters(array $filters): CursorPaginator;

    /**
     * Find a product by its slug (for SEO-friendly product pages).
     * Eager-loads relationships needed for the detail view.
     */
    public function findBySlug(string $slug): ?Product;

    /**
     * Find a product by its primary key.
     * Throws ModelNotFoundException if not found.
     */
    public function findOrFail(int $id): Product;

    /**
     * Increment the view counter atomically (no full model hydration).
     */
    public function incrementViews(int $productId): void;

    /**
     * Recalculate and persist the product's denormalized average_rating
     * and review_count after a review is added/updated/deleted.
     */
    public function recalculateRating(int $productId): void;

    /**
     * Return featured/promoted products for the homepage.
     */
    public function getFeatured(int $limit = 12): Collection;

    /**
     * Return products related to a given product (same category/brand).
     */
    public function getRelated(Product $product, int $limit = 8): Collection;

    /**
     * Create a new product and return the persisted model.
     */
    public function create(array $data): Product;

    /**
     * Update an existing product. Returns the updated model.
     */
    public function update(int $id, array $data): Product;

    /**
     * Soft-delete a product.
     */
    public function delete(int $id): bool;
}
