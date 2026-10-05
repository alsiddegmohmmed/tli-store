<?php

namespace Database\Seeders;

use App\Models\PosStation;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (range(1, 4) as $number) {
            PosStation::firstOrCreate(['code' => 'POS-'.$number], ['name' => 'صندوق '.$number]);
        }
    }
}
