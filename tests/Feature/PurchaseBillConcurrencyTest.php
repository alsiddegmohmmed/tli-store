<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\RunsConcurrentOperations;
use Tests\TestCase;

class PurchaseBillConcurrencyTest extends TestCase
{
    use DatabaseMigrations, RunsConcurrentOperations;

    protected function tearDown(): void
    {
        // Dedicated test database only: clear committed fixtures before guarded rollback.
        DB::statement('TRUNCATE purchase_bills CASCADE');
        parent::tearDown();
    }

    public function test_concurrent_retries_and_duplicate_documents_post_one_liability(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $supplier = Supplier::create(['name' => 'مورد اختبار', 'created_by' => $owner->id, 'updated_by' => $owner->id]);
        $input = ['action' => 'purchase_bill', 'user_id' => $owner->id, 'data' => ['operation_id' => (string) Str::uuid(),
            'supplier_id' => $supplier->id, 'reference' => 'TEST-1', 'bill_date' => now('Asia/Riyadh')->toDateString(),
            'description' => 'بضاعة اختبار', 'total' => '115.10', 'printed_tax' => null, 'confirmed_unpaid' => true]];
        $results = $this->race([$input, $input]);
        $this->assertTrue($results[0]['ok']);
        $this->assertTrue($results[1]['ok']);
        $this->assertSame($results[0]['id'], $results[1]['id']);
        $input['data']['operation_id'] = (string) Str::uuid();
        $input['data']['reference'] = 'TEST-2';
        $other = $input;
        $other['data']['operation_id'] = (string) Str::uuid();
        $duplicates = $this->race([$input, $other]);
        $this->assertCount(1, array_filter($duplicates, fn ($result) => $result['ok']));
        $this->assertCount(1, array_filter($duplicates, fn ($result) => isset($result['errors']['reference'])));
        $this->assertDatabaseCount('purchase_bills', 2);
        $this->assertDatabaseCount('journal_entries', 2);
        $this->assertDatabaseCount('journal_lines', 4);
    }
}
