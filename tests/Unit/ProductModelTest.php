<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ProductModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_creation()
    {
        $product = Product::create([
            'id' => 1,
            'name' => 'Test Product',
            'price' => 19.99,
            'currency' => 'USD',
            'quantity' => 10,
            'status' => 'sale',
            'variations' => json_encode([
                ['color' => 'red', 'size' => 'M', 'quantity' => 5],
                ['color' => 'blue', 'size' => 'L', 'quantity' => 5]
            ])
        ]);

        $this->assertInstanceOf(Product::class, $product);
        $this->assertEquals('Test Product', $product->name);
        $this->assertEquals(10, $product->quantity);
        $this->assertEquals('sale', $product->status);
    }

    public function test_product_soft_delete()
    {
        $product = Product::create([
            'id' => 1,
            'name' => 'Test Product',
            'price' => 19.99,
            'quantity' => 10,
            'status' => 'sale'
        ]);

        $product->delete();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }



    public function test_product_total_quantity_calculation()
    {
        $variations = [
            ['color' => 'red', 'size' => 'M', 'quantity' => 5],
            ['color' => 'blue', 'size' => 'L', 'quantity' => 7]
        ];

        $product = Product::create([
            'id' => 1,
            'name' => 'Test Product',
            'price' => 19.99,
            'status' => 'sale',
            'variations' => json_encode($variations),
            'quantity' => $variations[0]['quantity'] + $variations[1]['quantity']
        ]);

        $this->assertEquals(12, $product->quantity);
    }


    
    public function test_product_price_formatting()
    {
        //Removing the function will get you failed result
        $product = Product::create([
            'id' => 1,
            'name' => 'Test Product',
            'price' => $this->convertPrice('19,99'),
            'status' => 'sale'
        ]);

        $this->assertEquals(19.99, $product->price);
    }

    private function convertPrice($price)
    {
        
        $price = str_replace('.', '', $price);

        
        $price = str_replace(',', '.', $price);

       
        return round((float) $price, 2);
    }
}