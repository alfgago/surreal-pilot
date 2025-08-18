@php($title = 'Web & Mobile Games – SurrealPilot')
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-[#111217] text-slate-100">
  @include('components.app.header')

  <section class="max-w-6xl mx-auto px-6 py-12">
    <h1 class="text-4xl font-extrabold">Quickly prototype web & mobile games</h1>
    <p class="mt-4 text-slate-300 max-w-3xl">Start from proven templates, iterate entirely via prompts, and share instant preview links. Publish to a global CDN with one click.</p>

    <div class="mt-10 grid md:grid-cols-3 gap-6">
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">Template start</h3>
        <p class="mt-2 text-slate-300">FPS, third-person, or platformer starter kits.</p>
      </div>
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">Prompt editing</h3>
        <p class="mt-2 text-slate-300">Add entities, components, and scripts through chat.</p>
      </div>
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">One-click publish</h3>
        <p class="mt-2 text-slate-300">Optimized static builds to CDN with proper MIME/compression.</p>
      </div>
    </div>

    <div class="mt-10">
      <a class="px-5 py-3 rounded-lg bg-[#F8B14F] text-black font-semibold" href="/register">Try it now</a>
    </div>
  </section>
</body>
</html>

