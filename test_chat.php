<?php

require_once __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Company;

// Try to authenticate as a user
$user = User::with('currentCompany')->find(1); // Assuming user ID 1 exists
if ($user) {
    echo "User found: " . $user->id . "\n";
    echo "Current company ID: " . ($user->current_company_id ?? 'null') . "\n";
    echo "Current company relation: " . ($user->currentCompany ? 'exists' : 'null') . "\n";

    Auth::login($user);

    // Set the current company if it exists
    if (!$user->current_company_id) {
        echo "User has no current company. Creating a test company...\n";
        // Create a test company if none exists
        $company = new Company([
            'name' => 'Test Company',
            'slug' => 'test-company'
        ]);
        $company->save();
        $user->companies()->attach($company->id, ['role' => 'owner']);
        $user->current_company_id = $company->id;
        $user->save();
        // Reload the user with the new company
        $user = User::with('currentCompany')->find(1);
        echo "New current company ID: " . $user->current_company_id . "\n";
        echo "New current company relation: " . ($user->currentCompany ? 'exists' : 'null') . "\n";
    }

    // Create a test request to the chat streaming endpoint
    $request = new Illuminate\Http\Request([
        'messages' => [
            [
                'role' => 'user',
                'content' => 'Hello, can you help me create a simple game?'
            ]
        ],
        'conversation_id' => 1,
        'workspace_id' => 1,
        'engine_type' => 'gdevelop'
    ]);

    // Set the authenticated user on the request
    $request->setUserResolver(function () use ($user) {
        return $user;
    });

    $request->headers->set('Accept', 'text/event-stream');

    // Get the controller and call the method directly
    $controller = app()->make(App\Http\Controllers\Api\StreamingChatController::class);

    try {
        $response = $controller->stream($request);
        echo "Response type: " . get_class($response) . "\n";

        if ($response instanceof \Illuminate\Http\Response) {
            echo "Response status: " . $response->getStatusCode() . "\n";
            echo "Response content: " . $response->getContent() . "\n";
        } else {
            echo "Response is not an HTTP response\n";
            var_dump($response);
        }

    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }

} else {
    echo "User not found\n";
}

?>