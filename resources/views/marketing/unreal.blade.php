@php($title = 'Unreal Copilot – SurrealPilot')
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
    <h1 class="text-4xl font-extrabold">Unreal Engine Copilot</h1>
    <p class="mt-4 text-slate-300 max-w-3xl">Prompt-driven Blueprint and C++ edits inside the editor. Export selection, receive a versioned patch, apply via FScopedTransaction with instant compile feedback. Undo/redo seamlessly.</p>

    <div class="mt-10 grid md:grid-cols-3 gap-6">
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">Blueprint editing</h3>
        <p class="mt-2 text-slate-300">Add nodes, connect pins, rename variables, set defaults.</p>
      </div>
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">C++ workflows</h3>
        <p class="mt-2 text-slate-300">Create classes, edit files, and run hot reload from chat.</p>
      </div>
      <div class="p-6 rounded-xl border border-slate-800 bg-slate-900">
        <h3 class="font-semibold">Compile feedback</h3>
        <p class="mt-2 text-slate-300">Automatic revert on failure; errors appear in chat thread.</p>
      </div>
    </div>

    <div class="mt-10">
      <a class="px-5 py-3 rounded-lg bg-[#F8B14F] text-black font-semibold" href="/register">Get started</a>
    </div>
  </section>
</body>
</html>

