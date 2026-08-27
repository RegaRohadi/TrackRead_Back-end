<?php

namespace Tests\Feature;

use App\Models\Book;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookOwnershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_book_and_it_is_scoped_to_their_account(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Book::create([
            'name' => 'Book from other user',
            'author' => 'Alice',
            'genre' => 'Fiction',
            'user_id' => $otherUser->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')->postJson('/api/v1/books', [
            'name' => 'My personal book',
            'author' => 'Bob',
            'genre' => 'Science',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('books', ['user_id' => $user->id, 'name' => 'My personal book']);

        $listResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/books');

        $listResponse->assertOk();
        $this->assertCount(1, $listResponse->json('data'));
        $this->assertSame('My personal book', $listResponse->json('data.0.name'));
    }
}
