<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class DemoProductSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo products are restricted to local development and tests.');
        }

        foreach ([
            ['barcode' => '000001', 'name' => 'صنف تجريبي', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00'],
            ['barcode' => '000002', 'name' => 'صنف تجريبي بالوزن', 'unit' => 'كيلو', 'price' => '11.50', 'tax_rate' => '15.00'],
            ['barcode' => '000003', 'name' => 'صنف تجريبي دون ضريبة', 'unit' => 'حبة', 'price' => '10.00', 'tax_rate' => '0.00'],
        ] as $product) {
            Product::firstOrCreate(['barcode' => $product['barcode']], $product);
        }
    }
}
