<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ImportProducts extends Command
{
    /**
     * @var string
     */
    protected $signature = 'import:products';

    /**
     * @var string
     */
    protected $description = 'Imports products into database';

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
    
        array_shift($lines); // Skip the header row
    
        $i = 0;
    
        DB::transaction(function () use ($lines, &$i) {
            foreach ($lines as $line) {
                // Use str_getcsv to correctly parse the line
                $fields = str_getcsv($line);

                echo "<pre>";
                print_r($fields);
                echo "</pre>";
    
                // Reinitialize $encodedVariations for each product
                $encodedVariations = null;
    
                // Extract and process the fields as needed
                $productId = $fields[0];
                $productName = $fields[1] ?? '';
                $productSku = $fields[2] ?? null; 
                $productPrice = isset($fields[3]) && is_numeric($fields[3]) ? $fields[3] : 0.00;
                $productCurrency = isset($fields[4]) ? $fields[4] : '';
                $status = !empty($fields[7]) ? $fields[7] : NULL;
    
                if(!empty($fields[5])){
                    if($this->isJson($fields[5])){
                        $encodedVariations = $fields[5];
                    }else{
                        $encodedVariations = NULL;
                    }
                }
    
                if (empty($productSku)) {
                    $this->warn("Skipping product '{$productName}' because SKU is empty.");
                    continue;
                }
    

    
                if (!preg_match('/^[A-Z]{3}$/', $productCurrency)) {
                    $this->warn("Invalid currency '{$productCurrency}' for product '{$productName}'. Skipping currency.");
                    $productCurrency = null; 
                }
    
                $productData = [
                    'id' => $productId,
                    'name' => $productName,
                    'sku' => $productSku,
                    'price' => $productPrice,
                    'currency' => $productCurrency,
                    'variations' => $encodedVariations,
                    'quantity' => $fields[6] ?? 0,
                    'status' => $status
                ];

                // print_r($productData);


                $product = Product::where('sku', $productSku)->first();
                
                if ($product) {
                    $product->update($productData);
                    $this->info("Updated existing product with SKU: {$productSku}");
                } else {
                    Product::create($productData);
                    $this->info("Created new product with SKU: {$productSku}");
                }

                $i++;
            }
        });

        $this->info('Processed ' . $i . ' products.');
    }

    public function isJson($string) {
        json_decode($string);
        if(json_last_error() === JSON_ERROR_NONE){
            return true;
        }else{
            return false;
        }
        
    }

 
}
