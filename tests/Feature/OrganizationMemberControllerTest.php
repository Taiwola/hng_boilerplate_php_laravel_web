<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Organisation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class OrganizationMemberControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected $user;
    protected $organization;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user and log them in
        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        // Create an organization
        $this->organization = Organisation::factory()->create();
    }

    /** @test */
    public function it_validates_the_organization_id_and_returns_paginated_members()
    {
        // Create a user
        $user = User::factory()->create([
            'name' => 'precious',
            'email' => 'precious@example.com',
            'password' => Hash::make('precious')
        ]);

        // Login the user
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'precious@example.com',
            'password' => 'precious'
        ]);

        $response->assertStatus(200);
        $token = $response->json('data.access_token');

        // Create an organization
        $response = $this->postJson('/api/v1/organizations', [
            'name' => 'Example Organization',
            'description' => 'This is an example organization description.',
            'email' => 'example@example.com',
            'industry' => 'Technology',
            'type' => 'Non-profit',
            'country' => 'United States',
            'address' => '123 Example St',
            'state' => 'California'
        ], [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(201);
        $organisation = $response->json('data.org_id');

        // Fetch members with valid organization ID
        $response = $this->getJson("/api/v1/organizations/{$organisation}/users?page=1&page_size=10", [
            'Authorization' => 'Bearer ' . $token
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'members' => [
                        '*' => [
                            'userId',
                            'firstName',
                            'email',
                            'phone'
                        ]
                    ],
                    'pagination' => [
                        'currentPage',
                        'pageSize',
                        'totalPages',
                        'totalItems'
                    ]
                ],
                'status_code'
            ]);
    }

    public function test_it_returns_users_when_searching_with_valid_organization_id()
    {
        // Create users belonging to the organization
        $user1 = $this->organization->users()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => bcrypt('password'),
        ]);

        $user2 = $this->organization->users()->create([
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'password' => bcrypt('password'),
        ]);

        // Search for users in the organization
        $response = $this->getJson('/api/v1/members/' . $this->organization->org_id . '/search?search=Jane');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Users retrieved successfully',
                'status_code' => 200,
                'data' => [
                    [
                        'name' => 'Jane Smith',
                        'email' => 'jane@example.com',
                    ]
                ]
            ]);
    }

    public function test_it_returns_an_error_when_organization_id_is_invalid()
    {
        // Pass an invalid organization ID
        $invalidOrgId = Str::random(10);

        $response = $this->getJson('/api/v1/members/' . $invalidOrgId . '/search');

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid organization ID',
                'status_code' => 400,
            ]);
    }

    public function test_it_returns_an_error_when_organization_does_not_exist()
    {
        // Create a non-existing organization ID
        $nonExistingOrgId = Str::uuid()->toString();

        $response = $this->getJson('/api/v1/members/' . $nonExistingOrgId . '/search');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Organization does not exist',
                'status_code' => 404,
            ]);
    }

    public function testDownloadCsv()
    {
   
        $user = $this->user;
        // Create an organization
        $organization = Organisation::factory()->create();
   
   
        // Attach user to the organization
        $organization->users()->attach($user);
   
        // Mock the storage
        Storage::fake('local');
   
        //dd($organization->org_id);
   
        // Call the download endpoint
        $response = $this->get("/api/v1/members/{$organization->org_id}/export");
   
   
        $response->assertOk();
   
        $now = Carbon::today()->isoFormat('DD_MMMM_YYYY');
        $fileName = "users_$now.csv";
        $filePath = 'csv/' . $fileName;
   
        // Assert the file was created
        $this->assertTrue(Storage::disk('local')->exists($filePath));
   
        // Assert the file content
        $csvContent = Storage::disk('local')->get($filePath);
        $lines = explode("\n", trim($csvContent));
   
        // Assert the CSV header
        $this->assertEquals('UserName,UserEmail,UserStatus,CreatedDate', $lines[0]);
   
        // Assert the response headers
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $response->assertHeader('Content-Disposition', 'attachment; filename=' . $fileName);
   
        // Clean up: Remove the file
        Storage::disk('local')->delete($filePath);
    }
}
// Added comment so i can push again
