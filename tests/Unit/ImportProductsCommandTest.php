<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

class ImportProductsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Product::truncate();

        if (!File::exists(storage_path())) {
            File::makeDirectory(storage_path());
        }
    }

    protected function tearDown(): void
    {
        if (File::exists(storage_path('products.csv'))) {
            File::delete(storage_path('products.csv'));
        }
        parent::tearDown();
    }

    public function test_import_products_command()
    {
        $csvContent = "ID,Name,SKU,Price,Currency,Variations,quantity,Status\n";
        $csvContent .= "1,Test Product,112233UIX,19.99,USD,\"[{color:red,size:M,quantity:5}]\",10,sale";
        File::put(storage_path('products.csv'), $csvContent);

        $this->artisan('import:products');

        $this->assertDatabaseHas('products', [
            'id' => 1
        ]);
    }

    public function test_import_products_with_variations()
    {



        $file = fopen(storage_path('products.csv'), 'w');
        fputcsv($file, ['ID', 'Name', 'SKU', 'Price', 'Currency', 'Variations', 'Quantity', 'Status']);
        fputcsv($file, [1, 'Test Product', '112233UIX', 19.99, 'USD', json_encode([['color' => 'red', 'size' => 'M', 'quantity' => 5], ['color' => 'blue', 'size' => 'L', 'quantity' => 7]]), 12, 'sale']);
        fclose($file);

            
        $this->artisan('import:products');
    
        $product = Product::first()->toArray();

       
        $variations = json_decode($product['variations'], true);


        $this->assertCount(2, $variations);
        $this->assertEquals(5, $variations[0]['quantity']);
        $this->assertEquals(7, $variations[1]['quantity']);
        $this->assertEquals(12, $product['quantity']); // Make sure this matches what you expect
    }
    
    

    public function test_import_products_with_comma_price()
    {
        $csvContent = "ID,Name,SKU,Price,Currency,Variations,quantity,Status\n";
        $csvContent .= "1,Test Product,112233UIX,19,99,USD,\"[{color:red,size:M,quantity:5},{color:blue,size:L,quantity:7}]\",10,sale";
        File::put(storage_path('products.csv'), $csvContent);

        $this->artisan('import:products');

        $this->assertDatabaseHas('products', [
            'id' => 1,
            'price' => 19.00
           
        ]);
    }
}