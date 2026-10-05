<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_permissions_protect_supplier_reads_and_writes(): void
    {
        $input = ['name' => 'مورد اختبار', 'is_active' => true];
        $this->postJson('/suppliers', $input)->assertUnauthorized();
        $cashier = User::factory()->create();
        $this->actingAs($cashier)->get('/suppliers')->assertForbidden();
        $this->postJson('/suppliers', $input)->assertForbidden();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->post('/suppliers', [...$input, 'created_by' => $cashier->id, 'updated_by' => $cashier->id])->assertRedirect();
        $supplier = Supplier::firstOrFail();
        $this->assertSame($owner->id, $supplier->created_by);
        $this->assertSame($owner->id, $supplier->updated_by);
        $this->actingAs($cashier)->get('/suppliers/'.$supplier->id.'/edit')->assertForbidden();
        $this->putJson('/suppliers/'.$supplier->id, $input)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->get('/suppliers')->assertOk();
    }

    public function test_optional_unknown_fields_remain_null_and_edits_keep_the_original_identity(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->post('/suppliers', ['name' => 'مورد اختبار', 'tax_number' => '', 'phone' => '', 'is_active' => true])->assertRedirect();
        $supplier = Supplier::firstOrFail();
        $this->assertNull($supplier->tax_number);
        $this->assertNull($supplier->phone);
        $accountant = User::factory()->create(['role' => UserRole::Accountant]);
        $this->actingAs($accountant)->put('/suppliers/'.$supplier->id, ['name' => 'اسم محدّث', 'is_active' => false, 'created_by' => $accountant->id])->assertRedirect();
        $supplier->refresh();
        $this->assertSame($owner->id, $supplier->created_by);
        $this->assertSame($accountant->id, $supplier->updated_by);
        $this->assertFalse($supplier->is_active);
        $this->assertSame('اسم محدّث', $supplier->name);
        $this->assertDatabaseCount('suppliers', 1);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('journal_entries', 0);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_search_is_bounded_and_contact_validation_preserves_the_record(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]));
        $this->postJson('/suppliers', ['name' => '', 'is_active' => true])->assertUnprocessable();
        $this->assertDatabaseCount('suppliers', 0);
        $this->post('/suppliers', ['name' => 'Alpha supplier', 'phone' => '000000001', 'is_active' => true])->assertRedirect();
        $supplier = Supplier::firstOrFail();
        $this->putJson('/suppliers/'.$supplier->id, ['name' => str_repeat('x', 121), 'is_active' => false])->assertUnprocessable();
        $this->assertTrue($supplier->fresh()->is_active);
        $this->get('/suppliers?search=alpha')->assertInertia(fn (Assert $page) => $page->component('suppliers/index')->has('suppliers.data', 1));
        $this->get('/suppliers?search=000000001')->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 1));
        $this->get('/suppliers?search=absent')->assertInertia(fn (Assert $page) => $page->has('suppliers.data', 0));
        $this->delete('/suppliers/'.$supplier->id)->assertStatus(405);
        $this->assertDatabaseCount('suppliers', 1);
    }
}
