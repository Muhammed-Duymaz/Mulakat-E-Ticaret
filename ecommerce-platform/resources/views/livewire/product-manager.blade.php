<div>
    @if (session()->has('message'))
        <div class="mb-6 rounded-xl bg-green-50 p-4 border border-green-200">
            <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
        </div>
    @endif

    <form wire:submit.prevent="saveProduct" class="space-y-8">
        
        <!-- Basic Information Section -->
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-4">
                <h3 class="text-base font-semibold leading-6 text-slate-900">Basic Information</h3>
            </div>
            
            <div class="px-6 py-6 grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-6">
                
                <!-- Product Name -->
                <div class="sm:col-span-4">
                    <label class="block text-sm font-medium text-slate-900">Product Name <span class="text-red-500">*</span></label>
                    <div class="mt-2">
                        <input type="text" wire:model="name" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        @error('name') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Category -->
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-900">Category <span class="text-red-500">*</span></label>
                    <div class="mt-2">
                        <select wire:model="category_id" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                            <option value="">Select a category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('category_id') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                    </div>
                </div>

                <!-- Short Description -->
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium text-slate-900">Short Description</label>
                    <div class="mt-2">
                        <textarea wire:model="short_description" rows="2" class="block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3"></textarea>
                    </div>
                </div>

                <!-- Featured Image -->
                <div class="sm:col-span-6">
                    <label class="block text-sm font-medium text-slate-900">Featured Image</label>
                    <div class="mt-2 flex justify-center rounded-lg border border-dashed border-slate-900/25 px-6 py-10"
                         x-data="{ isUploading: false, progress: 0 }"
                         x-on:livewire-upload-start="isUploading = true"
                         x-on:livewire-upload-finish="isUploading = false"
                         x-on:livewire-upload-error="isUploading = false"
                         x-on:livewire-upload-progress="progress = $event.detail.progress">
                         
                        <div class="text-center">
                            @if ($featured_image)
                                <img src="{{ $featured_image->temporaryUrl() }}" class="mx-auto h-32 object-cover rounded-md mb-4">
                            @else
                                <svg class="mx-auto h-12 w-12 text-slate-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M1.5 6a2.25 2.25 0 012.25-2.25h16.5A2.25 2.25 0 0122.5 6v12a2.25 2.25 0 01-2.25 2.25H3.75A2.25 2.25 0 011.5 18V6zM3 16.06V18c0 .414.336.75.75.75h16.5A.75.75 0 0021 18v-1.94l-2.69-2.689a1.5 1.5 0 00-2.12 0l-.88.879.97.97a.75.75 0 11-1.06 1.06l-5.16-5.159a1.5 1.5 0 00-2.12 0L3 16.061zm10.125-7.81a1.125 1.125 0 112.25 0 1.125 1.125 0 01-2.25 0z" clip-rule="evenodd" />
                                </svg>
                            @endif
                            
                            <div class="mt-4 flex text-sm leading-6 text-slate-600 justify-center">
                                <label class="relative cursor-pointer rounded-md bg-white font-semibold text-indigo-600 focus-within:outline-none focus-within:ring-2 focus-within:ring-indigo-600 focus-within:ring-offset-2 hover:text-indigo-500">
                                    <span>Upload a file</span>
                                    <input type="file" wire:model="featured_image" class="sr-only">
                                </label>
                                <p class="pl-1">or drag and drop</p>
                            </div>
                            <p class="text-xs leading-5 text-slate-500">PNG, JPG, GIF up to 2MB</p>
                            
                            <!-- Progress Bar -->
                            <div x-show="isUploading" class="mt-2 w-full bg-slate-200 rounded-full h-1.5">
                                <div class="bg-indigo-600 h-1.5 rounded-full" :style="`width: ${progress}%`"></div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Pricing & Inventory Section -->
        <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
            <div class="border-b border-slate-200 bg-slate-50 px-6 py-4 flex justify-between items-center">
                <h3 class="text-base font-semibold leading-6 text-slate-900">Pricing, Inventory & Variants</h3>
                
                <!-- Toggle Variants Switch -->
                <button type="button" wire:click="toggleVariants" class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2 {{ $hasVariants ? 'bg-indigo-600' : 'bg-slate-200' }}" role="switch" aria-checked="{{ $hasVariants ? 'true' : 'false' }}">
                    <span class="sr-only">Use variants</span>
                    <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out {{ $hasVariants ? 'translate-x-5' : 'translate-x-0' }}"></span>
                </button>
            </div>
            
            <div class="px-6 py-6">
                <!-- Single Product (No Variants) -->
                @if(!$hasVariants)
                    <div class="grid grid-cols-1 gap-x-6 gap-y-6 sm:grid-cols-4">
                        <div class="sm:col-span-1">
                            <label class="block text-sm font-medium text-slate-900">Price (₺) <span class="text-red-500">*</span></label>
                            <input type="number" step="0.01" wire:model="price" class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                            @error('price') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-1">
                            <label class="block text-sm font-medium text-slate-900">Stock Quantity <span class="text-red-500">*</span></label>
                            <input type="number" wire:model="stock" class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                            @error('stock') <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                        </div>
                        <div class="sm:col-span-2">
                            <label class="block text-sm font-medium text-slate-900">SKU (Stock Keeping Unit)</label>
                            <input type="text" wire:model="sku" class="mt-2 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                        </div>
                    </div>
                @else
                    <!-- Dynamic Variants Array -->
                    <div class="space-y-4">
                        <div class="bg-indigo-50 border border-indigo-100 rounded-lg p-4 mb-4 text-sm text-indigo-800">
                            <strong>Variants Enabled:</strong> You can add multiple combinations of sizes and colors. The base product price and stock will be managed at the variant level.
                        </div>

                        @foreach($variants as $index => $variant)
                            <div class="flex items-end gap-4 p-4 border border-slate-200 rounded-lg bg-slate-50 transition-all">
                                <div class="flex-1 grid grid-cols-1 gap-4 sm:grid-cols-4">
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700">Size</label>
                                        <input type="text" placeholder="e.g. XL" wire:model="variants.{{ $index }}.size" class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                                        @error("variants.$index.size") <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700">Color</label>
                                        <input type="text" placeholder="e.g. Red" wire:model="variants.{{ $index }}.color" class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700">Price (₺) *</label>
                                        <input type="number" step="0.01" wire:model="variants.{{ $index }}.price" class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                                        @error("variants.$index.price") <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700">Stock *</label>
                                        <input type="number" wire:model="variants.{{ $index }}.stock" class="mt-1 block w-full rounded-md border-0 py-1.5 text-slate-900 shadow-sm ring-1 ring-inset ring-slate-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 px-3">
                                        @error("variants.$index.stock") <span class="text-xs text-red-500 mt-1">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                                <button type="button" wire:click="removeVariant({{ $index }})" class="text-slate-400 hover:text-red-500 mb-1" title="Remove Variant">
                                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        @endforeach

                        <button type="button" wire:click="addVariant" class="mt-2 inline-flex items-center gap-x-1.5 rounded-md bg-white px-3 py-2 text-sm font-semibold text-indigo-600 shadow-sm ring-1 ring-inset ring-slate-300 hover:bg-slate-50">
                            <svg class="-ml-0.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                            </svg>
                            Add Another Variant
                        </button>
                    </div>
                @endif
            </div>
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end">
            <button type="button" class="text-sm font-semibold leading-6 text-slate-900 mr-6">Cancel</button>
            <button type="submit" class="rounded-md bg-indigo-600 px-8 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                Save Product
            </button>
        </div>
    </form>
</div>
