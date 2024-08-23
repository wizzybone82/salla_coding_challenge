<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class ImportProducts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'import:products';

    /**
     * @var string
     */
    protected $description = 'Imports products into the database and soft deletes outdated products';

    /**
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return mixed
     */
    public function handle()
    {
        $contents = file_get_contents(public_path('products.csv'));
        $lines = explode("\n", $contents);

        array_shift($lines); 

        $i = 0;
        $processedSkus = [];

        
        $existingSkus = Product::pluck('sku')->toArray();

        DB::transaction(function () use ($lines, &$i, &$processedSkus) {
            foreach ($lines as $line) {
                
                $fields = str_getcsv($line);

               
                $encodedVariations = null;

                
                $productId = $fields[0];
                $productName = $fields[1] ?? '';
                $productSku = $fields[2] ?? null; 
                $productPrice = isset($fields[3])?$this->convertPrice($fields[3]):0.00;
                $productCurrency = isset($fields[4]) ? $fields[4] : '';
                $status = !empty($fields[7]) ? $fields[7] : NULL;

                if (!empty($fields[5])) {
                    if ($this->isJson($fields[5])) {
                        $encodedVariations = $this->processVariations($fields[5] ?? null, $this->convertToInt($fields[6]) ?? 0);

                    } else {
                        $encodedVariations = NULL;
                    }
                }

                if (empty($productSku)) {
                    $this->warn("Skipping product '{$productName}' because SKU is empty.");
                    continue;
                }

                
                $processedSkus[] = $productSku;

                if (!preg_match('/^[A-Z]{3}$/', $productCurrency)) {
                    $this->warn("Invalid currency '{$productCurrency}' for product '{$productName}'. Skipping currency.");
                    $productCurrency = null; 
                }

                $productData = [
                    'id' => $productId,
                    'name' => $productName,
                    'sku' => $productSku,
                    'price' => round($productPrice,2),
                    'currency' => $productCurrency,
                    'variations' => $encodedVariations,
                    'quantity' => $fields[6] ?? 0,
                    'status' => $status
                ];

                $product = Product::withTrashed()->where('sku', $productSku)->first();

                
                if (strtolower($status) === 'deleted') {
                    if ($product) {
                        $product->status = 'deleted_due_to_sync';
                        $product->deleted_at = Carbon::now();
                        $product->variations = $encodedVariations;
                        $product->save();
                        $this->info("Soft deleted product with SKU: {$productSku} due to deleted status in file");
                    }
                    continue; // Skip further processing for this product
                }

                if ($product) {
                    if ($product->trashed()) {
                        $product->restore(); // Restore if it was soft deleted
                    }
                    $product->update($productData);
                    $this->info("Updated existing product with SKU: {$productSku}");
                } else {
                    Product::create($productData);
                    $this->info("Created new product with SKU: {$productSku}");
                }

                $i++;
            }
        });

       
        $skusToDelete = array_diff($existingSkus, $processedSkus);

        foreach ($skusToDelete as $sku) {
            $productToDelete = Product::where('sku', $sku)->first();
            if ($productToDelete) {
                $productToDelete->status = 'deleted_due_to_sync';
                $productToDelete->deleted_at = Carbon::now();
                $productToDelete->save();
                $this->info("Soft deleted product with SKU: {$sku}");
            }
        }

        $this->info('Processed ' . $i . ' products.');
    }

    public function isJson($string) {
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }


    /**
     * Convert string to integer
     *
     * @param string $value
     * @return int
    */

    private function convertToInt($value)
    {
        return (int) preg_replace('/[^0-9]/', '', $value);
    }


    /**
     * Process variations based on input data
     *
     * @param string|null $variationsJson
     * @param int $totalQuantity
     * @return string|null
    */

    private function processVariations($variationsJson, $totalQuantity)
    {
        if (empty($variationsJson) || !$this->isJson($variationsJson)) {
            return null;
        }


        $variations = json_decode($variationsJson, true);
      
        $variationCount = count($variations);

        if ($variationCount === 0) {
            return null;
        }
        $hasQuantityAndAvailability = isset($variations[0]['quantity']) && isset($variations[0]['available']);

        if ($hasQuantityAndAvailability) {
           
            foreach ($variations as &$variation) {
                $variation['quantity'] = $this->convertToInt($variation['quantity']);
                $variation['available'] = filter_var($variation['available'], FILTER_VALIDATE_BOOLEAN);
            }
        } else {
          
            $variationCount = count($variations);
            $quantityPerVariation = floor($totalQuantity / $variationCount);
            $remainingQuantity = $totalQuantity % $variationCount;

            foreach ($variations as &$variation) {
                $variation['quantity'] = $quantityPerVariation;
                if ($remainingQuantity > 0) {
                    $variation['quantity']++;
                    $remainingQuantity--;
                }
                $variation['available'] = ($variation['quantity'] > 0);
            }
        }



        return json_encode($variations);
    }



    /**
     * Convert price from string to decimal
     *
     * @param string $price
     * @return float
    */

    private function convertPrice($price)
    {
        
        $price = str_replace('.', '', $price);

        
        $price = str_replace(',', '.', $price);

       
        return round((float) $price, 2);
    }
}