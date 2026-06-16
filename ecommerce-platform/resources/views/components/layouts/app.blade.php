<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Dashboard' }} - E-Commerce Platform</title>

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Tailwind CSS (via CDN for architecture demo, compile with Vite in production) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Inter', 'sans-serif'] },
                    colors: {
                        indigo: {
                            50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe',
                            300: '#a5b4fc', 400: '#818cf8', 500: '#6366f1',
                            600: '#4f46e5', 700: '#4338ca', 800: '#3730a3',
                            900: '#312e81', 950: '#1e1b4b',
                        },
                        slate: {
                            50: '#f8fafc', 100: '#f1f5f9', 200: '#e2e8f0',
                            300: '#cbd5e1', 400: '#94a3b8', 500: '#64748b',
                            600: '#475569', 700: '#334155', 800: '#1e293b',
                            900: '#0f172a', 950: '#020617',
                        }
                    }
                }
            }
        }
    </script>

    <!-- Alpine.js (Livewire 3 includes this automatically, but explicitly adding for layout standalone) -->
    @livewireStyles
</head>
<body class="h-full text-slate-800 font-sans antialiased flex overflow-hidden">
    
    <!-- Sidebar Component -->
    <x-dashboard.sidebar />

    <!-- Main Content Area -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
        
        <!-- Topbar Component -->
        <x-dashboard.topbar />

        <!-- Main Scrollable Content -->
        <main class="flex-1 overflow-y-auto px-6 py-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Page Header -->
                @if (isset($header))
                    <header class="mb-8">
                        <h1 class="text-3xl font-bold text-slate-900 tracking-tight">{{ $header }}</h1>
                    </header>
                @endif

                <!-- Slot for Livewire Views -->
                {{ $slot }}
                
            </div>
        </main>
    </div>

    @livewireScripts
</body>
</html>
