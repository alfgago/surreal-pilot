<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>SurrealPilot – AI Copilot for Game Development</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#111217] text-slate-100 font-[Inter]">
  @include('components.app.header')

  <section class="max-w-7xl mx-auto px-6 py-16 grid lg:grid-cols-2 gap-12 items-center">
    <div>
      <h1 class="text-4xl md:text-5xl font-extrabold leading-tight">Build and ship games faster with an AI copilot</h1>
      <p class="mt-6 text-lg text-slate-300">SurrealPilot brings prompt-driven editing to Unreal Engine and fast JavaScript game prototyping—no heavy editors required. Iterate, preview, and publish from one place.</p>
      <div class="mt-8 flex gap-4">
        <a class="px-5 py-3 rounded-lg bg-[#F8B14F] text-black font-semibold" href="/register">Start free</a>
        <a class="px-5 py-3 rounded-lg border border-slate-700" href="#pricing">See pricing</a>
      </div>
      <div class="mt-6 flex items-center gap-4 text-slate-400 text-sm">
        <span>Unreal Engine 5.x</span>
        <span>•</span>
        <span>Desktop + Web</span>
        <span>•</span>
        <span>Multi-provider LLM</span>
      </div>
    </div>
    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-6">
      <div class="h-64 bg-gradient-to-br from-slate-800 to-slate-900 rounded-xl flex items-center justify-center text-slate-400">Product preview</div>
    </div>
  </section>

  <section class="max-w-7xl mx-auto px-6 py-16 grid md:grid-cols-3 gap-6">
    <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
      <h3 class="font-semibold text-lg">Prompt-driven edits</h3>
      <p class="mt-2 text-slate-300">Rename, refactor, connect, and tweak with natural language.</p>
    </div>
    <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
      <h3 class="font-semibold text-lg">Instant previews</h3>
      <p class="mt-2 text-slate-300">Preview Unreal patches and JavaScript games in seconds.</p>
    </div>
    <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
      <h3 class="font-semibold text-lg">One‑click publish</h3>
      <p class="mt-2 text-slate-300">Share CDN links with QA, teammates, and early testers.</p>
    </div>
  </section>

  <section id="pricing" class="max-w-7xl mx-auto px-6 py-16">
    <h2 class="text-3xl font-extrabold">Simple pricing</h2>
    <p class="text-slate-300 mt-2">Plans include credits and you can top‑up anytime.</p>
    <div class="grid md:grid-cols-3 gap-6 mt-8">
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">Starter</h3>
        <p class="mt-1 text-3xl font-extrabold">$5<span class="text-base font-semibold text-slate-300">/mo</span></p>
        <p class="mt-2 text-slate-300">10k credits/month</p>
        <a class="mt-6 inline-block px-4 py-2 rounded-lg bg-[#F8B14F] text-black font-semibold" href="/register">Choose Starter</a>
      </div>
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">Pro</h3>
        <p class="mt-1 text-3xl font-extrabold">$25<span class="text-base font-semibold text-slate-300">/mo</span></p>
        <p class="mt-2 text-slate-300">60k credits/month</p>
        <a class="mt-6 inline-block px-4 py-2 rounded-lg bg-[#F8B14F] text-black font-semibold" href="/register">Choose Pro</a>
      </div>
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">Studio</h3>
        <p class="mt-1 text-3xl font-extrabold">$50<span class="text-base font-semibold text-slate-300">/mo</span></p>
        <p class="mt-2 text-slate-300">120k credits/month • BYO API Keys</p>
        <a class="mt-6 inline-block px-4 py-2 rounded-lg bg-[#F8B14F] text-black font-semibold" href="/register">Choose Studio</a>
      </div>
    </div>
    <p class="mt-6 text-slate-400">Top‑ups: $5 for every 10k credits.</p>
  </section>

  <footer class="max-w-7xl mx-auto px-6 py-12 text-slate-400 text-sm">
    © {{ date('Y') }} SurrealPilot. All rights reserved.
  </footer>

</body>
</html>

