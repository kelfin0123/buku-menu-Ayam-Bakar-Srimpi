<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MenuControllerSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_page_syncs_products_from_firestore_when_products_are_missing(): void
    {
        Http::fake([
            'https://firestore.googleapis.com/v1/projects/test-project/databases/(default)/documents/products' => Http::response([
                'documents' => [
                    [
                        'name' => 'projects/test-project/databases/(default)/documents/products/doc-1',
                        'fields' => [
                            'name' => ['stringValue' => 'Nasi Goreng'],
                            'description' => ['stringValue' => 'Enak dan hangat'],
                            'category' => ['stringValue' => 'Makanan'],
                            'price' => ['integerValue' => '20000'],
                            'stock' => ['integerValue' => '15'],
                            'imageUrl' => ['stringValue' => 'https://example.com/nasi.jpg'],
                            'isActive' => ['booleanValue' => true],
                        ],
                    ],
                ],
            ], 200),
        ]);

        config()->set('services.firebase.project_id', 'test-project');
        config()->set('services.firebase.products_collection', 'products');

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Nasi Goreng');
        $response->assertSee('Makanan');
        $response->assertSee('15');
        $this->assertDatabaseHas('products', ['name' => 'Nasi Goreng']);
    }
}
