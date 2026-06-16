<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProductResource
 *
 * Transforms a Product model into a consistent JSON API response.
 * Conditionally includes heavy relationships (variants, reviews)
 * only when they are already loaded — preventing accidental N+1s.
 *
 * Listing response (lightweight):
 *   id, name, slug, price, discount_price, effective_price,
 *   discount_percentage, is_in_stock, average_rating, review_count,
 *   featured_image, category{id,name,slug}, brand{id,name,slug}
 *
 * Detail response (full — loaded by findBySlug):
 *   + description, sku, weight, tags, vendor,
 *     images[], variant_options[values[]], variants[option_values[]], reviews[]
 */
class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // ── Core Fields ───────────────────────────────────────────────
            'id'                  => $this->id,
            'name'                => $this->name,
            'slug'                => $this->slug,
            'sku'                 => $this->when($this->relationLoaded('variants'), $this->sku),
            'short_description'   => $this->short_description,

            // ── Pricing ───────────────────────────────────────────────────
            'price'               => (float) $this->price,
            'discount_price'      => $this->discount_price ? (float) $this->discount_price : null,
            'effective_price'     => (float) $this->effective_price,
            'discount_percentage' => $this->discount_percentage,
            'currency'            => 'TRY',

            // ── Stock ─────────────────────────────────────────────────────
            'is_in_stock'         => $this->is_in_stock,
            'has_variants'        => (bool) $this->has_variants,
            'stock'               => $this->when(!$this->has_variants, $this->stock),

            // ── Metrics ───────────────────────────────────────────────────
            'average_rating'      => (float) $this->average_rating,
            'review_count'        => (int)   $this->review_count,
            'views'               => (int)   $this->views,

            // ── Images ────────────────────────────────────────────────────
            'featured_image_url'  => $this->featured_image_url,
            'images'              => $this->when(
                $this->relationLoaded('images'),
                fn () => $this->images->map(fn ($img) => [
                    'id'         => $img->id,
                    'url'        => asset('storage/' . $img->path),
                    'alt_text'   => $img->alt_text,
                    'is_featured'=> (bool) $img->is_featured,
                    'variant_id' => $img->variant_id,
                    'sort_order' => $img->sort_order,
                ])
            ),

            // ── Relations (always-safe, conditional load) ─────────────────
            'category'            => $this->when(
                $this->relationLoaded('category') && $this->category,
                fn () => [
                    'id'   => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ]
            ),

            'brand'               => $this->when(
                $this->relationLoaded('brand') && $this->brand,
                fn () => [
                    'id'   => $this->brand->id,
                    'name' => $this->brand->name,
                    'slug' => $this->brand->slug,
                    'logo' => $this->brand->logo ? asset('storage/' . $this->brand->logo) : null,
                ]
            ),

            'vendor'              => $this->when(
                $this->relationLoaded('vendor') && $this->vendor,
                fn () => [
                    'id'         => $this->vendor->id,
                    'name'       => $this->vendor->name,
                    'store_name' => $this->vendor->store_name,
                    'store_slug' => $this->vendor->store_slug,
                    'store_logo' => $this->vendor->store_logo
                                        ? asset('storage/' . $this->vendor->store_logo)
                                        : null,
                ]
            ),

            // ── Variant Options (for building selectors in the UI) ─────────
            'variant_options'     => $this->when(
                $this->relationLoaded('variantOptions'),
                fn () => $this->variantOptions->map(fn ($option) => [
                    'id'         => $option->id,
                    'name'       => $option->name,
                    'sort_order' => $option->sort_order,
                    'values'     => $option->values->map(fn ($val) => [
                        'id'         => $val->id,
                        'value'      => $val->value,
                        'color_hex'  => $val->color_hex,
                        'image'      => $val->image ? asset('storage/' . $val->image) : null,
                        'sort_order' => $val->sort_order,
                    ]),
                ])
            ),

            // ── Variants (SKU-level combinations) ─────────────────────────
            'variants'            => $this->when(
                $this->relationLoaded('variants'),
                fn () => $this->variants->map(fn ($variant) => [
                    'id'              => $variant->id,
                    'sku'             => $variant->sku,
                    'price'           => $variant->price ? (float) $variant->price : null,
                    'discount_price'  => $variant->discount_price ? (float) $variant->discount_price : null,
                    'effective_price' => (float) $variant->effective_price,
                    'stock'           => $variant->stock,
                    'is_in_stock'     => $variant->is_in_stock,
                    'is_active'       => (bool) $variant->is_active,
                    'image'           => $variant->image ? asset('storage/' . $variant->image) : null,
                    'label'           => $variant->label,
                    'option_values'   => $variant->relationLoaded('optionValues')
                        ? $variant->optionValues->map(fn ($v) => [
                            'option_id'   => $v->variant_option_id,
                            'option_name' => $v->variantOption?->name,
                            'value_id'    => $v->id,
                            'value'       => $v->value,
                            'color_hex'   => $v->color_hex,
                          ])
                        : [],
                ])
            ),

            // ── Full Description (detail page only) ────────────────────────
            'description'         => $this->when(
                $this->relationLoaded('variantOptions'), // proxy for "detail loaded"
                $this->description
            ),
            'tags'                => $this->tags ?? [],
            'weight'              => $this->weight,
            'meta_title'          => $this->meta_title,
            'meta_description'    => $this->meta_description,

            // ── Reviews (latest 10, detail page) ──────────────────────────
            'reviews'             => $this->when(
                $this->relationLoaded('reviews'),
                fn () => $this->reviews->map(fn ($review) => [
                    'id'                   => $review->id,
                    'rating'               => $review->rating,
                    'title'                => $review->title,
                    'body'                 => $review->body,
                    'images'               => $review->images ?? [],
                    'helpful_count'        => $review->helpful_count,
                    'is_verified_purchase' => $review->is_verified_purchase,
                    'created_at'           => $review->created_at?->toDateString(),
                    'user'                 => [
                        'id'     => $review->user?->id,
                        'name'   => $review->user?->name,
                        'avatar' => $review->user?->avatar
                                        ? asset('storage/' . $review->user->avatar)
                                        : null,
                    ],
                ])
            ),

            // ── Timestamps ────────────────────────────────────────────────
            'published_at'        => $this->published_at?->toDateTimeString(),
            'created_at'          => $this->created_at?->toDateTimeString(),
            'updated_at'          => $this->updated_at?->toDateTimeString(),
        ];
    }
}
