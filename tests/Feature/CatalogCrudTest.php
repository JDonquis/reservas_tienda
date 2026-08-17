<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogCrudTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): User
    {
        $admin = User::factory()->create(['role' => 'superadmin']);
        Sanctum::actingAs($admin);

        return $admin;
    }

    public function test_superadmin_can_create_category_with_type(): void
    {
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);

        $this->postJson("/api/v1/admin/stores/{$store->id}/categories", [
            'name' => 'Masajes',
            'type' => 'service',
        ])->assertSuccessful();

        $this->assertDatabaseHas('categories', [
            'store_id' => $store->id,
            'name' => 'Masajes',
            'type' => 'service',
        ]);

        $response = $this->getJson("/api/v1/admin/stores/{$store->id}/categories?type=service");
        $response->assertSuccessful();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_superadmin_can_create_product_with_image(): void
    {
        Storage::fake('public');
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);

        $response = $this->post("/api/v1/admin/stores/{$store->id}/products", [
            'name' => 'Crema facial',
            'price' => 25.5,
            'is_active' => true,
            'image' => UploadedFile::fake()->image('crema.jpg'),
        ]);

        $response->assertSuccessful();

        $product = Product::first();
        $this->assertNotNull($product);
        $this->assertStringStartsWith('products/', $product->image_path);
        Storage::disk('public')->assertExists($product->image_path);
    }

    public function test_superadmin_can_create_service(): void
    {
        $this->superadmin();
        $store = Store::create(['name' => 'Tienda']);

        $this->postJson("/api/v1/admin/stores/{$store->id}/services", [
            'name' => 'Masaje relajante',
            'description' => '60 min',
            'price' => 40,
            'duration_minutes' => 60,
        ])->assertSuccessful();

        $this->assertDatabaseHas('services', [
            'store_id' => $store->id,
            'name' => 'Masaje relajante',
            'duration_minutes' => 60,
        ]);
    }

    public function test_store_owner_cannot_access_other_store_catalog(): void
    {
        $owner = User::factory()->create(['role' => 'store_owner']);
        Sanctum::actingAs($owner);

        $foreignStore = Store::create(['name' => 'Otra tienda']);
        Category::create(['store_id' => $foreignStore->id, 'name' => 'Cat', 'type' => 'product']);

        $this->getJson("/api/v1/admin/stores/{$foreignStore->id}/categories")
            ->assertStatus(403);
    }
}
