<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-900 text-white min-h-screen flex items-center justify-center">
    <form action="/login" method="POST" class="w-full max-w-md bg-gray-800 p-6 rounded-lg border border-gray-700">
        @csrf
        <h1 class="text-2xl font-bold mb-4">Sign in</h1>
        <div class="mb-3">
            <label class="block text-sm text-gray-300 mb-1">Email</label>
            <input name="email" type="email" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" required>
        </div>
        <div class="mb-4">
            <label class="block text-sm text-gray-300 mb-1">Password</label>
            <input name="password" type="password" class="w-full bg-gray-700 border border-gray-600 rounded px-3 py-2" required>
        </div>
        <div class="mb-4 flex items-center">
            <input id="remember" name="remember" type="checkbox" class="mr-2 rounded bg-gray-700 border-gray-600">
            <label for="remember" class="text-sm text-gray-300">Remember me</label>
        </div>
        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 px-4 py-2 rounded">Login</button>
        <p class="text-sm text-gray-400 mt-3">No account? <a href="/register" class="text-blue-400">Create one</a></p>
    </form>
</body>
</html>


