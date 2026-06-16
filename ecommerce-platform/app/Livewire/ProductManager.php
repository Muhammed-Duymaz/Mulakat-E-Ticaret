<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class ProductManager extends Component
{
    use WithFileUploads;

    // Base Product Fields
    public $name = '';
    public $category_id = '';
    public $brand_id = '';
    public $sku = '';
    public $short_description = '';
    public $description = '';
    public $price = '';
    public $discount_price = '';
    public $stock = '';
    public $status = 'draft';

    // Featured Image
    public $featured_image;

    // Dynamic Variants Array
    // Structure: [['sku' => '', 'size' => '', 'color' => '', 'price' => '', 'stock' => '']]
    public $variants = [];

    // Form State
    public $hasVariants = false;

    public function mount()
    {
        // Initialize with one empty variant if toggled
        $this->variants = [
            ['sku' => '', 'size' => '', 'color' => '', 'price' => '', 'stock' => '']
        ];
    }

    public function addVariant()
    {
        $this->variants[] = ['sku' => '', 'size' => '', 'color' => '', 'price' => '', 'stock' => ''];
    }

    public function removeVariant($index)
    {
        unset($this->variants[$index]);
        $this->variants = array_values($this->variants); // Re-index array
        
        if (count($this->variants) === 0) {
            $this->hasVariants = false;
            $this->addVariant(); // Keep at least one ready
        }
    }

    public function toggleVariants()
    {
        $this->hasVariants = !$this->hasVariants;
    }

    public function saveProduct()
    {
        $this->validate([
            'name'              => 'required|string|max:255',
            'category_id'       => 'required|exists:categories,id',
            'price'             => 'required_if:hasVariants,false|numeric|min:0',
            'stock'             => 'required_if:hasVariants,false|integer|min:0',
            'featured_image'    => 'nullable|image|max:2048',
            
            // Validate variants only if toggled
            'variants.*.sku'    => 'required_if:hasVariants,true|string',
            'variants.*.size'   => 'required_if:hasVariants,true|string',
            'variants.*.price'  => 'required_if:hasVariants,true|numeric|min:0',
            'variants.*.stock'  => 'required_if:hasVariants,true|integer|min:0',
        ]);

        $user = Auth::user();

        // Save logic (Normally delegated to Repository/Service)
        // Kept inline here for the architecture demo simplicity
        
        $product = Product::create([
            'vendor_id'         => $user->isVendor() ? $user->id : null, // Admin creates globally or assign
            'category_id'       => $this->category_id,
            'brand_id'          => $this->brand_id ?: null,
            'name'              => $this->name,
            'slug'              => Str::slug($this->name) . '-' . time(),
            'sku'               => $this->hasVariants ? null : $this->sku,
            'short_description' => $this->short_description,
            'description'       => $this->description,
            'price'             => $this->hasVariants ? 0 : $this->price,
            'discount_price'    => $this->discount_price ?: null,
            'stock'             => $this->hasVariants ? 0 : $this->stock,
            'has_variants'      => $this->hasVariants,
            'status'            => $this->status,
        ]);

        // Handle Image Upload
        if ($this->featured_image) {
            $path = $this->featured_image->store('products', 'public');
            $product->images()->create([
                'path' => $path,
                'is_featured' => true,
            ]);
        }

        // Handle Variants
        if ($this->hasVariants) {
            $totalStock = 0;
            
            foreach ($this->variants as $v) {
                // In a real app, 'size' and 'color' would map to VariantOption/Values
                // Here we store them in a simplified manner or use a label
                $variant = $product->variants()->create([
                    'sku'    => $v['sku'],
                    'price'  => $v['price'],
                    'stock'  => $v['stock'],
                    'label'  => "Size: {$v['size']} | Color: {$v['color']}",
                    'is_active' => true,
                ]);
                $totalStock += $v['stock'];
            }
            
            // Update parent stock to sum of variants
            $product->update(['stock' => $totalStock]);
        }

        session()->flash('message', 'Product created successfully!');
        
        // Reset form
        $this->reset(['name', 'category_id', 'brand_id', 'sku', 'short_description', 'description', 'price', 'discount_price', 'stock', 'featured_image', 'hasVariants']);
        $this->mount();
    }

    public function render()
    {
        return view('livewire.product-manager', [
            'categories' => Category::all(),
            'brands'     => Brand::all(),
        ])->layout('components.layouts.app', ['header' => 'Create New Product']);
    }
}
