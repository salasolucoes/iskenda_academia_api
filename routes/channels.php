<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Admin — any authenticated admin
Broadcast::channel('private-admin', function ($user) {
    return $user->role === 'admin';
});

// Instructor — own channel
Broadcast::channel('private-instructor.{id}', function ($user, $id) {
    return $user->id === $id && $user->role === 'instructor';
});

// Student — own channel
Broadcast::channel('private-user.{id}', function ($user, $id) {
    return $user->id === $id;
});
