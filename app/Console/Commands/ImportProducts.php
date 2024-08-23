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

        array_shift($lines); // Skip the header row

        $i = 0;
        $processedSkus = [];

        // Get all current product SKUs from the database
        $existingSkus = Product::pluck('sku')->toArray();

        DB::transaction(function () use ($lines, &$i, &$processedSkus) {
            foreach ($lines as $line) {
                // Use str_getcsv to correctly parse the line
                $fields = str_getcsv($line);

                // Reinitialize $encodedVariations for each product
                $encodedVariations = null;

                // Extract and process the fields as needed
                $productId = $fields[0];
                $productName = $fields[1] ?? '';
                $productSku = $fields[2] ?? null; 
                $productPrice = isset($fields[3])?$this->convertPrice($fields[3]):0.00;
                $productCurrency = isset($fields[4]) ? $fields[4] : '';
                $status = !empty($fields[7]) ? $fields[7] : NULL;

                if (!empty($fields[5])) {
                    if ($this->isJson($fields[5])) {
                        $encodedVariations = $fields[5];
                    } else {
                        $encodedVariations = NULL;
                    }
                }

                if (empty($productSku)) {
                    $this->warn("Skipping product '{$productName}' because SKU is empty.");
                    continue;
                }

                // Add the processed SKU to the list
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

                // Handle products flagged as deleted in the file
                if (strtolower($status) === 'deleted') {
                    if ($product) {
                        $product->status = 'deleted_due_to_sync';
                        $product->deleted_at = Carbon::now();
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

        // Identify and soft delete outdated products
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

    private function convertPrice($price)
    {
        // Remove any thousand separators (assuming they're periods)
        $price = str_replace('.', '', $price);

        // Replace comma with dot for decimal point
        $price = str_replace(',', '.', $price);

        // Convert to float and round to 2 decimal places
        return round((float) $price, 2);
    }
}