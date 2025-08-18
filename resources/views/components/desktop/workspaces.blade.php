<div class="workspaces-component">
  <div class="flex items-center justify-between mb-2">
    <h3 class="text-sm font-medium text-gray-300">Workspaces</h3>
    <div class="flex items-center gap-2">
      <button id="ws-refresh" class="text-xs text-blue-400 hover:text-blue-300">Refresh</button>
      <select id="ws-filter" class="bg-gray-700 text-gray-200 text-xs rounded px-2 py-1 border border-gray-600">
        <option value="all">All</option>
        <option value="unreal">Unreal</option>
        <option value="playcanvas">Web & Mobile</option>
      </select>
    </div>
  </div>
  <div id="ws-list" class="space-y-2 max-h-40 overflow-y-auto"></div>
</div>
