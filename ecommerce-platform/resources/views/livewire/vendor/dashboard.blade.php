<div>
    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        
        <!-- Total Sales -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-6 relative">
            <div class="absolute top-0 right-0 p-4 opacity-10">
                <svg class="w-12 h-12 text-slate-900" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <dt class="truncate text-sm font-medium text-slate-500">Gross Sales</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
                ₺{{ number_format($stats['total_sales'], 2) }}
            </dd>
        </div>

        <!-- Net Earnings -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-6 relative">
             <div class="absolute top-0 right-0 p-4 opacity-10">
                <svg class="w-12 h-12 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
            </div>
            <dt class="truncate text-sm font-medium text-slate-500">Net Earnings (After Comm.)</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-green-600">
                ₺{{ number_format($stats['net_earnings'], 2) }}
            </dd>
        </div>

        <!-- Pending Shipments -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <dt class="truncate text-sm font-medium text-slate-500">Pending Shipments</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-indigo-600">
                {{ $stats['pending_shipments'] }}
            </dd>
            <div class="mt-1 text-xs text-slate-400">Items waiting to be shipped</div>
        </div>

        <!-- Low Stock Alerts -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-rose-200 bg-rose-50 p-6">
            <dt class="truncate text-sm font-medium text-rose-800">Low Stock Alerts</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-rose-900">
                {{ $stats['low_stock_products'] }}
            </dd>
            <div class="mt-1 text-xs text-rose-600">Products with < 5 stock</div>
        </div>
    </div>

    <!-- Recent Order Items Table -->
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-5 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold leading-6 text-slate-900">Recent Orders (My Items)</h3>
                <p class="mt-1 text-sm text-slate-500">Recent purchases of your products.</p>
            </div>
            <a href="#" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">View all &rarr;</a>
        </div>
        
        @if($recentOrderItems->isEmpty())
            <div class="p-10 text-center flex flex-col items-center">
                <svg class="h-12 w-12 text-slate-300 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                </svg>
                <span class="text-slate-500 text-sm">No orders found yet.</span>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Order No</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Product</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Qty & Price</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                            <th scope="col" class="px-3 py-3.5 text-right text-xs font-medium text-slate-500 uppercase tracking-wider pr-6">Date</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach($recentOrderItems as $item)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm font-medium text-slate-900">
                                    {{ $item->order->order_number }}
                                </td>
                                <td class="py-4 px-3 text-sm text-slate-500">
                                    <div class="flex items-center">
                                        @if($item->product_image)
                                            <img class="h-10 w-10 rounded-md object-cover mr-3 border border-slate-200" src="{{ $item->product_image }}" alt="">
                                        @else
                                            <div class="h-10 w-10 rounded-md bg-slate-100 mr-3 border border-slate-200 flex items-center justify-center">
                                                <svg class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L28 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-slate-900">{{ $item->product_name }}</div>
                                            @if($item->variant_label)
                                                <div class="text-xs text-slate-400 mt-0.5">{{ $item->variant_label }}</div>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                    {{ $item->quantity }} x ₺{{ number_format($item->effective_price, 2) }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm">
                                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium 
                                        @if($item->status === 'processing') bg-amber-100 text-amber-800
                                        @elseif($item->status === 'shipped') bg-blue-100 text-blue-800
                                        @elseif($item->status === 'delivered') bg-green-100 text-green-800
                                        @else bg-slate-100 text-slate-800 @endif">
                                        {{ ucfirst($item->status) }}
                                    </span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500 text-right pr-6">
                                    {{ $item->created_at->format('d M, H:i') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
