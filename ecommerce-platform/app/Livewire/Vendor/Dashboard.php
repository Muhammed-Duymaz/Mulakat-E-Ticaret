<?php

namespace App\Livewire\Vendor;

use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        $vendorId = Auth::id();

        // Vendor specific stats
        $stats = [
            'total_sales' => OrderItem::where('vendor_id', $vendorId)
                                      ->whereHas('order', fn($q) => $q->where('status', '!=', 'cancelled'))
                                      ->sum('line_total'),
            
            'net_earnings' => OrderItem::where('vendor_id', $vendorId)
                                       ->whereHas('order', fn($q) => $q->where('status', '!=', 'cancelled'))
                                       ->get()
                                       ->sum('vendor_payout'),
            
            'pending_shipments' => OrderItem::where('vendor_id', $vendorId)
                                            ->where('status', 'processing')
                                            ->count(),
                                            
            'low_stock_products' => Product::where('vendor_id', $vendorId)
                                           ->where('stock', '<', 5)
                                           ->count(),
        ];

        // Recent orders containing items belonging to this vendor
        $recentOrderItems = OrderItem::with('order.user')
            ->where('vendor_id', $vendorId)
            ->latest()
            ->take(10)
            ->get();

        return view('livewire.vendor.dashboard', [
            'stats'            => $stats,
            'recentOrderItems' => $recentOrderItems,
        ])->layout('components.layouts.app', ['header' => 'My Store Dashboard']);
    }
}
