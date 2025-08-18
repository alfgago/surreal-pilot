<header class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
  <a href="/" class="text-xl font-extrabold text-white">SurrealPilot</a>
  <nav class="hidden md:flex items-center gap-6">
    <a href="/unreal-copilot" class="text-sm {{ request()->is('unreal-copilot') ? 'text-accent' : 'text-slate-300 hover:text-white' }}">Unreal Copilot</a>
    <a href="/web-mobile-games" class="text-sm {{ request()->is('web-mobile-games') ? 'text-accent' : 'text-slate-300 hover:text-white' }}">Web & Mobile games</a>
  </nav>
  <div class="flex items-center gap-4">
    <a href="/register" class="hidden md:inline px-4 py-2 rounded-md bg-accent text-black font-semibold text-sm">Get started</a>
      <div class="relative" x-data="{open:false}">
      <button @click="open=!open" class="w-9 h-9 rounded-full bg-slate-800 grid place-items-center border border-slate-700">
        <svg class="w-5 h-5 text-slate-200" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 1118.88 4.196 9 9 0 015.12 17.804z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      </button>
      <div x-show="open" x-cloak @click.outside="open=false" class="absolute right-0 mt-2 w-56 rounded-md bg-slate-900 border border-slate-700 shadow-lg p-2">
        <a href="{{ filament()->getHomeUrl() }}" class="block px-3 py-2 text-sm text-slate-200 hover:bg-slate-800 rounded">Admin Panel</a>
          @php($company = auth()->user()?->currentCompany)
          @if ($company)
            <a href="/company/settings" class="block px-3 py-2 text-sm text-slate-200 hover:bg-slate-800 rounded">Company Settings</a>
          @endif
        <a href="/profile" class="block px-3 py-2 text-sm text-slate-200 hover:bg-slate-800 rounded">Account</a>
        <div class="border-t border-slate-700 my-2"></div>
        <a href="/logout" class="block px-3 py-2 text-sm text-slate-200 hover:bg-slate-800 rounded">Sign out</a>
      </div>
    </div>
  </div>
</header>
<style>
  .bg-accent { background-color: #F8B14F; }
  .text-accent { color: #F8B14F; }
</style>
<script src="https://unpkg.com/alpinejs" defer></script>

