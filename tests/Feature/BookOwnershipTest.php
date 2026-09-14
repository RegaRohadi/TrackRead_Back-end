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

    public function test_reading_stats_and_status_filtering(): void
    {
        $user = User::factory()->create();

        Book::create([
            'name' => 'Book 1',
            'user_id' => $user->id,
            'pages' => 200,
            'pages_read' => 200,
            'status' => 'finished',
        ]);

        Book::create([
            'name' => 'Book 2',
            'user_id' => $user->id,
            'pages' => 300,
            'pages_read' => 100,
            'status' => 'currently_reading',
        ]);

        Book::create([
            'name' => 'Book 3',
            'user_id' => $user->id,
            'pages' => 150,
            'pages_read' => 0,
            'status' => 'to_read',
        ]);

        $statsResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/books/stats');
        $statsResponse->assertOk();
        $statsResponse->assertJsonPath('data.total_books', 3);
        $statsResponse->assertJsonPath('data.status_breakdown.finished', 1);
        $statsResponse->assertJsonPath('data.status_breakdown.currently_reading', 1);
        $statsResponse->assertJsonPath('data.status_breakdown.to_read', 1);
        $statsResponse->assertJsonPath('data.total_pages', 650);
        $statsResponse->assertJsonPath('data.total_pages_read', 300);

        // Test filtering by status
        $filterResponse = $this->actingAs($user, 'sanctum')->getJson('/api/v1/books?status=finished');
        $filterResponse->assertOk();
        $filterResponse->assertJsonCount(1, 'data');
        $filterResponse->assertJsonPath('data.0.name', 'Book 1');
    }
}
