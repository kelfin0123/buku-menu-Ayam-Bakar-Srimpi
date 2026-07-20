<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MenuControllerSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('firebase.project_id', 'test-project');
        config()->set('firebase.products_collection', 'products');
        config()->set('firebase.credentials', null);
        config()->set('firebase.credentials_base64', null);
        config()->set('firebase.client_email', null);
        config()->set('firebase.private_key', null);
    }

    public function test_menu_reads_and_maps_products_directly_from_firestore(): void
    {
        Http::fake([
            'https://firestore.googleapis.com/v1/projects/test-project/databases/(default)/documents/products*' => Http::response([
                'documents' => [
                    [
                        'name' => 'projects/test-project/databases/(default)/documents/products/doc-1',
                        'fields' => [
                            'name' => ['stringValue' => 'Nasi Goreng'],
                            'description' => ['stringValue' => 'Enak dan hangat'],
                            'category' => ['stringValue' => 'Makanan'],
                            'price' => ['stringValue' => '20000'],
                            'stock' => ['integerValue' => '15'],
                            'image_url' => ['stringValue' => 'https://example.com/nasi.jpg'],
                            'createdAt' => ['timestampValue' => '2026-07-20T10:00:00Z'],
                        ],
                    ],
                    [
                        'name' => 'projects/test-project/databases/(default)/documents/products/doc-inactive',
                        'fields' => [
                            'name' => ['stringValue' => 'Produk Nonaktif'],
                            'isActive' => ['booleanValue' => false],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Nasi Goreng')
            ->assertSee('Makanan')
            ->assertSee('15')
            ->assertSee('doc-1')
            ->assertDontSee('Produk Nonaktif');
    }

    public function test_menu_shows_an_explicit_error_when_firestore_fails(): void
    {
        Http::fake(fn () => Http::response(['error' => ['message' => 'Permission denied']], 403));

        $this->get('/')
            ->assertOk()
            ->assertSee('Produk belum dapat dibaca dari Firebase');
    }
}
