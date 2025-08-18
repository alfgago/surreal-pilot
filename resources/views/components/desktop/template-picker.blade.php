<div x-data="{ open: false, templates: [], loading: false }" @open-template-picker.window="open=true; fetchTemplates()">
  <div x-show="open" x-cloak class="fixed inset-0 bg-black/60 flex items-center justify-center z-50">
    <div class="bg-gray-800 border border-gray-700 rounded-lg w-full max-w-3xl p-4">
      <div class="flex items-center justify-between mb-3">
        <h3 class="text-white font-semibold">Start from a template</h3>
        <button @click="open=false" class="text-gray-400 hover:text-gray-200">✕</button>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-3" x-show="!loading">
        <template x-for="tpl in templates" :key="tpl.id">
          <div class="border border-gray-700 rounded-md overflow-hidden">
            <div class="bg-gray-900 p-3">
              <div class="text-white font-medium" x-text="tpl.name"></div>
              <div class="text-xs text-gray-400" x-text="tpl.description"></div>
            </div>
            <div class="p-3 flex items-center justify-between">
              <span class="text-xs px-2 py-1 rounded bg-gray-700 text-gray-300" x-text="tpl.engine_type === 'playcanvas' ? 'Web & Mobile' : 'Unreal'"></span>
              <button class="px-3 py-1 bg-accent text-black rounded text-sm" @click="$dispatch('create-from-template', tpl)">Use template</button>
            </div>
          </div>
        </template>
      </div>
      <div x-show="loading" class="text-gray-300 text-sm">Loading templates...</div>
    </div>
  </div>
</div>
<script>
function fetchTemplates(){
  const el = document.querySelector('[x-data]');
  if(!el) return;
  el.__x.$data.loading = true;
  fetch('/api/prototype/templates', { headers: { 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(j => { el.__x.$data.templates = j?.data || []; })
    .catch(()=>{})
    .finally(()=>{ el.__x.$data.loading = false; });
}
</script>
