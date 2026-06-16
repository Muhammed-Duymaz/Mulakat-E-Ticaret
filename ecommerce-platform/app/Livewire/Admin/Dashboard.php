<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use App\Models\User;
use Livewire\Component;

class Dashboard extends Component
{
    public function render()
    {
        // Simple aggregation for the Super Admin Dashboard
        $stats = [
            'total_sales'      => Order::where('status', '!=', 'cancelled')->sum('grand_total'),
            'total_commission' => Order::with('items')->get()->flatMap->items->sum(function ($item) {
                                      // Calculate platform commission cut
                                      $commissionRate = $item->commission_rate ?? 0;
                                      return $item->line_total * ($commissionRate / 100);
                                  }),
            'active_vendors'   => User::whereHas('role', fn($q) => $q->where('name', 'vendor'))->where('is_active', true)->count(),
            'pending_vendors'  => User::whereHas('role', fn($q) => $q->where('name', 'vendor'))->where('is_active', false)->count(),
        ];

        // Fetch recent pending vendors
        $pendingVendorsList = User::with('role')
            ->whereHas('role', fn($q) => $q->where('name', 'vendor'))
            ->where('is_active', false)
            ->latest()
            ->take(5)
            ->get();

        return view('livewire.admin.dashboard', [
            'stats'          => $stats,
            'pendingVendors' => $pendingVendorsList,
        ])->layout('components.layouts.app', ['header' => 'Admin Dashboard']);
    }

    public function approveVendor($vendorId)
    {
        $vendor = User::findOrFail($vendorId);
        $vendor->update(['is_active' => true]);
        
        session()->flash('message', "Vendor {$vendor->store_name} has been approved successfully.");
    }
}
