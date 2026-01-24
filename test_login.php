<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Http\Request;

$request = Request::create('/api/v1/admin/login', 'POST', ['email' => 'admin@sorsutalk.local', 'password' => 'admin123']);
$request->setLaravelSession($app['session']->driver());
$request->session()->start();

$controller = new App\Http\Controllers\AuthController();
$response = $controller->adminLogin($request);

echo "Status: " . $response->status() . "\n";
echo "Body: " . json_encode($response->getData(true), JSON_PRETTY_PRINT) . "\n";
