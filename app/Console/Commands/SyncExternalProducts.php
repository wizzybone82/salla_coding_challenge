<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class SyncExternalProducts extends Command
{
    protected $signature = 'products:sync-external';
    protected $description = 'Sync products with external API';

    public function handle()
    {
        $this->info('Starting external product sync...');

        try {
            $response = Http::get('https://5fc7a13cf3c77600165d89a8.mockapi.io/api/v5/products');

            if ($response->successful()) {
                $externalProducts = $response->json();

                foreach ($externalProducts as $externalProduct) {
                    $this->syncProduct($externalProduct);
                }

                $this->info('External product sync completed successfully.');
            } else {
                $this->error('Failed to fetch products from external API.');
                Log::error('External API sync failed: ' . $response->body());
            }
        } catch (\Exception $e) {
            $this->error('An error occurred during external product sync.');
            Log::error('External API sync error: ' . $e->getMessage());
        }
    }

    private function syncProduct($externalProduct)
    {
        $processedVariations = $this->processVariations($externalProduct['variations'] ?? null);
        $totalQuantity = $this->calculateTotalQuantity($processedVariations);

        $product = Product::find($externalProduct['id']);

        if ($product) {
            // Update existing product
            $product->update([
                'name' => $externalProduct['name'],
                'price' => $externalProduct['price'],
                'currency' => $externalProduct['currency'] ?? 'SAR',
                'quantity' => $totalQuantity,
                'status' => 'sale',
                'variations' => $processedVariations,
            ]);
            $this->info("Updated product: {$product->id}");
        } else {
            // Create new product
            $product = Product::create([
                'id' => $externalProduct['id'],
                'name' => $externalProduct['name'],
                'price' => $externalProduct['price'],
                'currency' => $externalProduct['currency'] ?? 'USD',
                'quantity' => $totalQuantity,
                'status' => 'sale',
                'variations' => $processedVariations,
            ]);
            $this->info("Created new product: {$product->id}");
        }
    }

    private function processVariations($variations)
    {
        if (empty($variations)) {
            return null;
        }

        // Ensure each variation has quantity and availability
        foreach ($variations as &$variation) {
            $variation['quantity'] = isset($variation['quantity']) ? (int)$variation['quantity'] : 0;
            $variation['available'] = $variation['quantity'] > 0;
        }

        return json_encode($variations);
    }

    private function calculateTotalQuantity($variationsJson)
    {
        if (empty($variationsJson)) {
            return 0;
        }

        $variations = json_decode($variationsJson, true);
        return array_sum(array_column($variations, 'quantity'));
    }
}