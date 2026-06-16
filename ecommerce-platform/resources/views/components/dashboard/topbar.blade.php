<div class="sticky top-0 z-10 flex h-16 shrink-0 bg-white border-b border-slate-200 shadow-sm">
    <!-- Mobile sidebar toggle -->
    <button type="button" class="border-r border-slate-200 px-4 text-slate-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500 md:hidden">
        <span class="sr-only">Open sidebar</span>
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7" />
        </svg>
    </button>
    
    <div class="flex flex-1 justify-between px-4 sm:px-6 lg:px-8">
        <div class="flex flex-1 items-center">
            <!-- Search / Breadcrumbs could go here -->
            <div class="text-sm text-slate-500 font-medium hidden sm:block">
                Welcome back, <span class="text-slate-900 font-semibold">{{ auth()->user()?->name ?? 'Guest' }}</span>
            </div>
        </div>
        
        <div class="ml-4 flex items-center md:ml-6 space-x-4">
            
            <!-- Notifications Dropdown -->
            <button type="button" class="rounded-full bg-white p-1 text-slate-400 hover:text-slate-500 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                <span class="sr-only">View notifications</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                </svg>
            </button>

            <!-- Profile dropdown -->
            <div class="relative ml-3" x-data="{ open: false }">
                <div>
                    <button @click="open = !open" type="button" class="flex max-w-xs items-center rounded-full bg-white text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                        <span class="sr-only">Open user menu</span>
                        <img class="h-8 w-8 rounded-full border border-slate-200" src="https://ui-avatars.com/api/?name={{ urlencode(auth()->user()?->name ?? 'Admin') }}&background=6366f1&color=fff" alt="">
                    </button>
                </div>
                
                <div x-show="open" 
                     @click.away="open = false"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute right-0 z-10 mt-2 w-48 origin-top-right rounded-md bg-white py-1 shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none" 
                     style="display: none;">
                    
                    <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Your Profile</a>
                    <a href="#" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Settings</a>
                    
                    <form method="POST" action="/logout">
                        @csrf
                        <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
