<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductImageUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_image_endpoint_returns_public_url_and_path(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('product.jpg', 640, 480);

        $response = $this->postJson('/api/products/upload-image', [
                'image' => $file,
            ]);

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'image_url',
                'image_path',
            ])
            ->assertJsonPath('success', true);

        $this->assertStringContainsString('/storage/products/', $response->json('image_url'));
        $this->assertStringContainsString('products/', $response->json('image_path'));
    }
}
