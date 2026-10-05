<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Infrastructure\Persistence\Eloquent\Models\Ticket;
use Infrastructure\Persistence\Eloquent\Models\TicketMessage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->student = User::factory()->create(['email_verified_at' => now()]);
    $this->token = $this->student->createToken('auth-token')->plainTextToken;
});

test('student can create a ticket', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/tickets', [
            'subject' => 'Problema com o curso',
            'description' => 'Não consigo acessar a aula 3.',
            'priority' => 'medium',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'subject', 'status']]);
});

test('student can list their tickets', function () {
    Ticket::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'subject' => 'Test Ticket',
        'description' => 'Description',
        'priority' => 'low',
        'status' => 'open',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson('/api/v1/tickets');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('student can view ticket details with messages', function () {
    $ticket = Ticket::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'subject' => 'Test Ticket',
        'description' => 'Description',
        'priority' => 'low',
        'status' => 'open',
    ]);

    TicketMessage::create([
        'id' => Str::uuid(),
        'ticket_id' => $ticket->id,
        'author_id' => $this->student->id,
        'body' => 'Help me please!',
        'is_internal' => false,
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/tickets/{$ticket->id}");

    $response->assertStatus(200)
        ->assertJsonStructure(['data' => ['ticket', 'messages']]);
});

test('student can reply to their ticket', function () {
    $ticket = Ticket::create([
        'id' => Str::uuid(),
        'student_id' => $this->student->id,
        'subject' => 'Test Ticket',
        'description' => 'Description',
        'priority' => 'low',
        'status' => 'open',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson("/api/v1/tickets/{$ticket->id}/messages", [
            'body' => 'I need more help!',
        ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['data' => ['id', 'body', 'created_at']]);
});

test('student cannot view another student ticket', function () {
    $otherStudent = User::factory()->create();
    $ticket = Ticket::create([
        'id' => Str::uuid(),
        'student_id' => $otherStudent->id,
        'subject' => 'Other ticket',
        'description' => 'Secret',
        'priority' => 'high',
        'status' => 'open',
    ]);

    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->getJson("/api/v1/tickets/{$ticket->id}");

    $response->assertStatus(404);
});

test('ticket requires subject and description', function () {
    $response = $this->withHeader('Authorization', "Bearer {$this->token}")
        ->postJson('/api/v1/tickets', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['subject', 'description', 'priority']);
});
