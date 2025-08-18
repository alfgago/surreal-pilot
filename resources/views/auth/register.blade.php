<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <form action="/register" method="POST" class="w-full max-w-md bg-gray-800 p-6 rounded-lg border border-gray-700">
        @csrf
        <h1 class="text-2xl font-bold mb-4">Create your account</h1>
        <div class="mb-3">
            <label class="block text-sm text-gray-300 mb-1">Name</label>
            <input name="name" type="text" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" required>
        </div>
        <div class="mb-3">
            <label class="block text-sm text-gray-300 mb-1">Email</label>
            <input name="email" type="email" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" required>
        </div>
        <div class="mb-3">
            <label class="block text-sm text-gray-300 mb-1">Password</label>
            <input name="password" type="password" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm text-gray-300 mb-1">Confirm Password</label>
            <input name="password_confirmation" type="password" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" required>
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">Register</button>
        <p class="text-sm text-gray-400 mt-3">Already have an account? <a href="/login" class="text-blue-400">Sign in</a></p>
    </form>
</body>
</html>


