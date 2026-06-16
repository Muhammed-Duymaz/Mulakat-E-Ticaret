<div class="hidden md:flex md:w-72 md:flex-col bg-slate-900 border-r border-slate-800 transition-all duration-300">
    <!-- Logo Area -->
    <div class="flex h-16 shrink-0 items-center px-6 bg-slate-950 border-b border-slate-800">
        <svg class="h-8 w-auto text-indigo-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
        </svg>
        <span class="ml-3 text-xl font-bold text-white tracking-wider">ECOMMERCE</span>
    </div>

    <!-- Navigation Area -->
    <div class="flex flex-1 flex-col overflow-y-auto pt-6 pb-4">
        <nav class="flex-1 space-y-1 px-4">
            
            <!-- SUPER ADMIN MENU -->
            @if(auth()->user()?->isAdmin())
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 mt-4 px-2">Platform Overview</div>
                
                <a href="/admin/dashboard" class="bg-indigo-600 text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg mb-1 transition-colors">
                    <svg class="mr-3 h-5 w-5 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    Dashboard
                </a>

                <a href="/admin/vendors" class="text-slate-300 hover:bg-slate-800 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg mb-1 transition-colors">
                    <svg class="mr-3 h-5 w-5 text-slate-400 group-hover:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                    </svg>
                    Vendors
                </a>
            @endif

            <!-- VENDOR MENU -->
            @if(auth()->user()?->isVendor())
                <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 mt-4 px-2">Store Management</div>
                
                <a href="/vendor/dashboard" class="bg-indigo-600 text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg mb-1 transition-colors">
                    <svg class="mr-3 h-5 w-5 text-indigo-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                    My Store
                </a>

                <a href="/vendor/products" class="text-slate-300 hover:bg-slate-800 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg mb-1 transition-colors">
                    <svg class="mr-3 h-5 w-5 text-slate-400 group-hover:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                    Products
                </a>

                <a href="/vendor/orders" class="text-slate-300 hover:bg-slate-800 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg mb-1 transition-colors">
                    <svg class="mr-3 h-5 w-5 text-slate-400 group-hover:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z" />
                    </svg>
                    Orders
                </a>
            @endif
            
            <!-- COMMON LINKS -->
            <div class="text-xs font-semibold text-slate-500 uppercase tracking-wider mb-2 mt-8 px-2">Settings</div>
            
            <a href="#" class="text-slate-300 hover:bg-slate-800 hover:text-white group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors">
                <svg class="mr-3 h-5 w-5 text-slate-400 group-hover:text-slate-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                Account
            </a>
        </nav>
    </div>
</div>
