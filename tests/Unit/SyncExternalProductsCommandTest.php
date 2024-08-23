<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

class SyncExternalProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        
        parent::setUp();
        Product::truncate();
        Http::fake([
            'https://5fc7a13cf3c77600165d89a8.mockapi.io/api/v5/products' => Http::response([
                [
                    'id' => '1',
                    'name' => 'External Product',
                    'price' => 29.99,
                    'currency' => 'USD',
                    'variations' => [
                        ['color' => 'red', 'size' => 'M', 'quantity' => 5],
                        ['color' => 'blue', 'size' => 'L', 'quantity' => 3]
                    ]
                ]
            ], 200)
        ]);
    }

    public function test_sync_external_products_command()
    {
        $this->artisan('products:sync-external')
             ->expectsOutput('External product sync completed successfully.')
             ->assertExitCode(0);

        $this->assertDatabaseHas('products', [
            'id' => 1,
            'name' => 'External Product',
            'price' => 29.99,
            'currency' => 'USD',
            'quantity' => 8,
            'status' => 'sale'
        ]);

        $product = Product::find(1);
        $variations = json_decode($product->variations, true);
        $this->assertCount(2, $variations);
    }

    public function test_sync_updates_existing_product()
    {
        Product::create([
            'id' => 1,
            'name' => 'Old Name',
            'price' => 19.99,
            'currency' => 'USD',
            'quantity' => 5,
            'status' => 'sale'
        ]);

        $this->artisan('products:sync-external')->assertExitCode(0);

        $this->assertDatabaseHas('products', [
            'id' => 1,
            'name' => 'External Product',
            'price' => 29.99,
            'status' => 'sale'
        ]);
    }

    public function test_sync_creates_new_product()
    {
        $this->artisan('products:sync-external')->assertExitCode(0);

        $this->assertDatabaseHas('products', [
            'id' => 1,
            'name' => 'External Product',
            'price' => 29.99,
            'status' => 'sale'
        ]);
    }
}