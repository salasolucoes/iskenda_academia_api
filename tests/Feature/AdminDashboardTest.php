<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Course;
use Infrastructure\Persistence\Eloquent\Models\Enrollment;
use Infrastructure\Persistence\Eloquent\Models\Order;
use Infrastructure\Persistence\Eloquent\Models\PaymentVoucher;
use Infrastructure\Persistence\Eloquent\Models\Ticket;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();
    $this->admin = User::factory()->admin()->create(['email_verified_at' => now()]);
    $this->token = $this->admin->createToken('auth-token')->plainTextToken;
    $this->headers = ['Authorization' => "Bearer {$this->token}"];
});

test('admin dashboard returns 200 with correct structure', function () {
    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'total_students',
                'total_instructors',
                'total_courses',
                'published_courses',
                'total_revenue_cents',
                'pending_vouchers',
                'open_tickets',
                'active_enrollments',
            ],
        ]);
});

test('admin dashboard counts students and instructors', function () {
    User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
    User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);
    User::factory()->instructor()->create(['email_verified_at' => now()]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_students', 2)
        ->assertJsonPath('data.total_instructors', 1);
});

test('admin dashboard counts courses by status', function () {
    Course::factory()->published()->create();
    Course::factory()->published()->create();
    Course::factory()->create(['status' => 'draft']);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_courses', 3)
        ->assertJsonPath('data.published_courses', 2);
});

test('admin dashboard sums revenue from completed orders', function () {
    Order::create([
        'id' => (string) Str::uuid(),
        'student_id' => User::factory()->create()->id,
        'total_cents' => 50000,
        'payment_method' => 'wallet',
        'status' => 'completed',
    ]);
    Order::create([
        'id' => (string) Str::uuid(),
        'student_id' => User::factory()->create()->id,
        'total_cents' => 30000,
        'payment_method' => 'wallet',
        'status' => 'completed',
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.total_revenue_cents', 80000);
});

test('admin dashboard counts pending vouchers', function () {
    $student = User::factory()->create(['email_verified_at' => now()]);
    PaymentVoucher::create([
        'id' => (string) Str::uuid(),
        'student_id' => $student->id,
        'status' => 'pending',
        'amount_cents' => 10000,
        'file_path' => 'vouchers/test.pdf',
        'file_hash' => 'abc123',
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.pending_vouchers', 1);
});

test('admin dashboard counts open tickets', function () {
    $student = User::factory()->create(['email_verified_at' => now()]);
    Ticket::create([
        'id' => (string) Str::uuid(),
        'student_id' => $student->id,
        'subject' => 'Ajuda',
        'description' => 'Preciso de ajuda',
        'priority' => 'medium',
        'status' => 'open',
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.open_tickets', 1);
});

test('admin dashboard counts active enrollments', function () {
    $course = Course::factory()->create();
    $student = User::factory()->create(['email_verified_at' => now()]);
    Enrollment::factory()->active()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
    ]);

    $response = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(200)
        ->assertJsonPath('data.active_enrollments', 1);
});

test('admin dashboard caches results', function () {
    User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

    $response1 = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');
    $response1->assertJsonPath('data.total_students', 1);

    User::factory()->create(['role' => 'student', 'email_verified_at' => now()]);

    $response2 = $this->withHeaders($this->headers)
        ->getJson('/api/v1/admin/dashboard');
    $response2->assertJsonPath('data.total_students', 1);
});

test('unauthenticated user cannot access admin dashboard', function () {
    $response = $this->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(401);
});

test('instructor cannot access admin dashboard', function () {
    $instructor = User::factory()->instructor()->create(['email_verified_at' => now()]);
    $token = $instructor->createToken('auth-token')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
        ->getJson('/api/v1/admin/dashboard');

    $response->assertStatus(403);
});
