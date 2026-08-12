<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in — {{ $settings->dairy_name ?? 'DairyPro' }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Inter','ui-sans-serif'], serif: ['Playfair Display','ui-serif'] },
            colors: {
                surface: '#fbf9f5', ink: '#1b1c1a', 'ink-variant': '#4a4a4a', outline: '#737973', 'outline-variant': '#e5e4e0',
                forest: { 50:'#eef4ef', 300:'#b4cdb8', 700:'#1b3022', 800:'#0f2317', 900:'#061b0e' },
                clay: '#ba1a1a',
            },
            borderRadius: { bento: '1.5rem', control: '1rem' },
        } } }
    </script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js" defer></script>
    <style>body{font-family:'Inter',sans-serif} h1,h2,.font-serif{font-family:'Playfair Display',serif}</style>
</head>
<body class="bg-forest-900 min-h-screen flex items-center justify-center p-4 relative overflow-hidden">
    <div class="absolute -top-32 -left-32 h-96 w-96 rounded-full bg-forest-700/30 blur-3xl"></div>
    <div class="absolute -bottom-32 -right-32 h-96 w-96 rounded-full bg-forest-300/10 blur-3xl"></div>

    <div class="relative w-full max-w-md">
        <div class="flex flex-col items-center mb-8">
            <img src="{{ asset('images/dairy_pro_logo.png') }}" alt="{{ $settings->dairy_name ?? 'DairyPro' }}"
                 class="h-14 w-14 rounded-2xl object-cover shadow-lg mb-4"
                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
            <span class="h-14 w-14 rounded-2xl bg-gradient-to-br from-forest-300 to-forest-700 items-center justify-center shadow-lg mb-4 hidden">
                <i data-lucide="milk" class="h-7 w-7 text-white"></i>
            </span>
            <h1 class="font-serif text-2xl font-bold text-white">{{ $settings->dairy_name ?? 'DairyPro' }}</h1>
            <p class="text-forest-300/80 text-sm mt-1">Dairy business management, simplified.</p>
        </div>

        <div class="bg-white rounded-bento shadow-2xl p-8">
            <h2 class="font-serif text-xl font-bold text-ink mb-1">Welcome back</h2>
            <p class="text-sm text-outline mb-6">Sign in to your dashboard.</p>

            @if ($errors->any())
                <div class="mb-5 flex items-start gap-2 rounded-control border border-clay/30 bg-red-50 px-4 py-3 text-sm text-clay">
                    <i data-lucide="alert-triangle" class="h-4 w-4 mt-0.5 shrink-0"></i>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Email address</label>
                    <div class="relative">
                        <i data-lucide="mail" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="email" name="email" value="{{ old('email') }}" required autofocus
                               class="w-full rounded-control border border-outline-variant pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700"
                               placeholder="admin@gorav.click">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-ink-variant mb-1.5 uppercase tracking-wide">Password</label>
                    <div class="relative">
                        <i data-lucide="lock" class="h-4 w-4 text-outline absolute left-3.5 top-1/2 -translate-y-1/2"></i>
                        <input type="password" name="password" required
                               class="w-full rounded-control border border-outline-variant pl-10 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-forest-700 focus:border-forest-700"
                               placeholder="••••••••">
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-ink-variant">
                    <input type="checkbox" name="remember" class="rounded border-outline-variant text-forest-700 focus:ring-forest-700">
                    Remember me
                </label>
                <button type="submit"
                        class="w-full flex items-center justify-center gap-2 rounded-control bg-forest-700 hover:bg-forest-800 text-white font-semibold text-sm py-3 transition shadow-lg shadow-forest-900/20">
                    Sign in <i data-lucide="arrow-right" class="h-4 w-4"></i>
                </button>
            </form>

            <p class="text-xs text-outline text-center mt-6">
                Demo login: <span class="font-mono text-ink-variant">admin@gorav.click</span> / <span class="font-mono text-ink-variant">password</span>
            </p>
        </div>

        <p class="text-center text-xs text-forest-300/70 mt-8">
            {{ $settings->dairy_name ?? 'DairyPro' }} &copy; {{ now()->year }} — Built by
            <a href="{{ config('app.brand_url') }}" class="font-semibold"><span class="text-white">Gorav</span><span class="text-forest-300">AI</span></a>
        </p>
    </div>
    <script>document.addEventListener('DOMContentLoaded', () => lucide.createIcons());</script>
</body>
</html>
