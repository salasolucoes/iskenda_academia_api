<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Cart;
use Infrastructure\Persistence\Eloquent\Models\CartItem;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\StudentWallet;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
});

test('student can view empty cart', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/cart');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('student can add published course to cart', function () {
    $course = Course::factory()->published()->create();

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/cart/items', [
            'course_id' => $course->id,
        ]);

    $response->assertStatus(201);
});

test('student cannot add draft course to cart', function () {
    $course = Course::factory()->create(['status' => 'draft']);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/cart/items', [
            'course_id' => $course->id,
        ]);

    $response->assertStatus(422);
});

test('student can remove item from cart', function () {
    $course = Course::factory()->published()->create();
    $cart = Cart::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
    ]);
    $item = CartItem::create([
        'id' => Str::uuid(),
        'cart_id' => $cart->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->deleteJson("/api/v1/cart/items/{$item->id}");

    $response->assertStatus(200);
});

test('student can checkout with wallet', function () {
    $course = Course::factory()->published()->create(['price_cents' => 1000]);

    StudentWallet::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'balance_cents' => 1000,
    ]);

    $cart = Cart::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
    ]);
    CartItem::create([
        'id' => Str::uuid(),
        'cart_id' => $cart->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/cart/checkout');

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['order_id', 'enrollment_ids']]);
});

test('checkout with insufficient balance returns error', function () {
    $course = Course::factory()->published()->create(['price_cents' => 5000]);

    StudentWallet::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'balance_cents' => 100,
    ]);

    $cart = Cart::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
    ]);
    CartItem::create([
        'id' => Str::uuid(),
        'cart_id' => $cart->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/cart/checkout');

    $response->assertStatus(500);
});

test('checkout with empty cart returns error', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/cart/checkout');

    $response->assertStatus(422);
});
