<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\Product;

class SuperAdminProductControllerTest extends TestCase
{
    use RefreshDatabase;

    public function testSuperAdminCanCreateProduct()
    {
        $this->artisan('migrate:fresh --seed');

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => 'bulldozeradmin@hng.com',
            'password' => 'bulldozer',
        ]);

        $loginResponse->assertStatus(200);
        $loginResponse->assertJsonStructure([
            'status_code',
            'message',
            'access_token',
            'data' => [
                'user' => [
                    'id',
                    'email',
                    'role',
                ],
            ],
        ]);

        $accessToken = $loginResponse->json('access_token');
        $userId = $loginResponse->json('data.user.id');

        $validOrgId = Product::first()->org_id;

        $productResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $accessToken,
        ])->postJson('/api/v1/products', [
            'name' => 'okoz',
            'description' => 'boy',
            'price' => 10,
            'status' => 'active',
            'slug' => 'jkdffjk',
            'tags' => 'gk;fk',
            'quantity' => '5',
            'org_id' => $validOrgId,
        ]);

        $productResponse->assertStatus(201);
        $productResponse->assertJson([
            'success' => true,
            'status_code' => 201,
            'message' => 'Product created successfully',
            'data' => [
                'name' => 'okoz',
                'description' => 'boy',
                'price' => 10,
                'status' => 'active',
                'slug' => 'jkdffjk',
                'tags' => 'gk;fk',
                'quantity' => '5',
                'org_id' => $validOrgId,
                'is_archived' => false,
                'imageUrl' => null,
                'user_id' => $userId,
            ],
        ]);
    }
}
