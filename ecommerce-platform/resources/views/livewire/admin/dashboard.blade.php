<div>
    @if (session()->has('message'))
        <div class="mb-6 rounded-xl bg-green-50 p-4 border border-green-200">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                </div>
            </div>
        </div>
    @endif

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4 mb-8">
        
        <!-- Total Sales -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <dt class="truncate text-sm font-medium text-slate-500">Total Platform Sales</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
                ₺{{ number_format($stats['total_sales'], 2) }}
            </dd>
        </div>

        <!-- Total Commission -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <dt class="truncate text-sm font-medium text-slate-500">Total Commission Earned</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-indigo-600">
                ₺{{ number_format($stats['total_commission'], 2) }}
            </dd>
        </div>

        <!-- Active Vendors -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200 p-6">
            <dt class="truncate text-sm font-medium text-slate-500">Active Vendors</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-slate-900">
                {{ $stats['active_vendors'] }}
            </dd>
        </div>

        <!-- Pending Approvals -->
        <div class="overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-amber-200 bg-amber-50 p-6">
            <dt class="truncate text-sm font-medium text-amber-800">Pending Approvals</dt>
            <dd class="mt-2 text-3xl font-bold tracking-tight text-amber-900">
                {{ $stats['pending_vendors'] }}
            </dd>
        </div>
    </div>

    <!-- Data Table: Pending Vendors -->
    <div class="bg-white rounded-xl shadow-sm ring-1 ring-slate-200 overflow-hidden">
        <div class="border-b border-slate-200 px-6 py-5">
            <h3 class="text-lg font-semibold leading-6 text-slate-900">Pending Vendor Approvals</h3>
            <p class="mt-1 text-sm text-slate-500">Review and approve new sellers before they can list products.</p>
        </div>
        
        @if($pendingVendors->isEmpty())
            <div class="p-6 text-center text-slate-500 text-sm">
                No vendors are currently pending approval.
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="py-3.5 pl-6 pr-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Store Name</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Owner</th>
                            <th scope="col" class="px-3 py-3.5 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Registered At</th>
                            <th scope="col" class="relative py-3.5 pl-3 pr-6">
                                <span class="sr-only">Actions</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 bg-white">
                        @foreach($pendingVendors as $vendor)
                            <tr>
                                <td class="whitespace-nowrap py-4 pl-6 pr-3 text-sm font-medium text-slate-900">
                                    {{ $vendor->store_name }}
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                    {{ $vendor->name }} <br>
                                    <span class="text-xs text-slate-400">{{ $vendor->email }}</span>
                                </td>
                                <td class="whitespace-nowrap px-3 py-4 text-sm text-slate-500">
                                    {{ $vendor->created_at->format('d M Y') }}
                                </td>
                                <td class="relative whitespace-nowrap py-4 pl-3 pr-6 text-right text-sm font-medium">
                                    <button wire:click="approveVendor({{ $vendor->id }})" class="text-indigo-600 hover:text-indigo-900 font-semibold bg-indigo-50 px-3 py-1 rounded-md transition-colors">
                                        Approve
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
