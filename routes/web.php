<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| This backend serves an API only — the frontend is a separate project.
| The single route below is a courtesy landing page: browsers get Laravel's
| default welcome screen, API clients get a JSON pointer to the API root.
| The health check lives at GET /up.
|
*/

Route::get('/', function (Request $request) {
    if ($request->expectsJson()) {
        return response()->json([
            'name' => config('app.name'),
            'status' => 'ok',
            'api' => url('/api/v1'),
            'health' => url('/up'),
        ]);
    }

    return view('welcome');
})->name('home');
