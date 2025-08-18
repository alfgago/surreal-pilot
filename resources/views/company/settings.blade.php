@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-900 py-8">
  <div class="max-w-3xl mx-auto px-4">
    <h1 class="text-2xl font-bold text-white mb-6">Company Settings</h1>

    @if(session('success'))
      <div class="mb-4 p-3 bg-green-900 text-green-100 rounded">{{ session('success') }}</div>
    @endif

    <div class="bg-gray-800 border border-gray-700 rounded p-6 mb-8">
      <form method="POST" action="/company/settings">
        @csrf
        @method('PATCH')
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-1">Company Name</label>
          <input name="name" value="{{ old('name', $company->name) }}" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" />
        </div>
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-1">Plan</label>
          <input name="plan" value="{{ old('plan', $company->plan) }}" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" />
        </div>
        <button class="bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">Save</button>
      </form>
    </div>

    <h2 class="text-xl font-semibold text-white mb-4">Invite User</h2>
    <div class="bg-gray-800 border border-gray-700 rounded p-6">
      <form method="POST" action="/company/invite">
        @csrf
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-1">Email</label>
          <input name="email" type="email" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" required />
        </div>
        <div class="mb-4">
          <label class="block text-sm text-gray-300 mb-1">Role</label>
          <input name="role" placeholder="member" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" />
        </div>
        <button class="bg-purple-600 hover:bg-purple-700 px-4 py-2 rounded">Invite</button>
      </form>
    </div>
  </div>
  </div>
@endsection


