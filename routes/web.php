<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/docs/api', 301);

Route::post('/broadcasting/auth', function () {
    $user = auth()->user();
    if (! $user) {
        abort(403);
    }

    $channelName = request()->input('channel_name');

    $channels = [
        'private-admin' => fn () => $user->role === 'admin',
        'private-instructor.'.$user->id => fn () => $user->role === 'instructor',
        'private-user.'.$user->id => fn () => true,
    ];

    if (isset($channels[$channelName]) && ($channels[$channelName])()) {
        return response()->json(['channel_data' => ['user_id' => $user->id]]);
    }

    abort(403);
})->middleware('auth:sanctum');

// Route::get('/', function () {
//     return view('welcome');
// });
