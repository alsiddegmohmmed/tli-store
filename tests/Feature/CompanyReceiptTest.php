<?php

namespace Tests\Feature;

use App\Domain\Sales\BasketCalculator;
use App\Domain\Sales\RecordSale;
use App\Domain\Shifts\OpenShift;
use App\Domain\Stock\RecordOpeningStock;
use App\Enums\UserRole;
use App\Models\InventoryBalance;
use App\Models\PosStation;
use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CompanyReceiptTest extends TestCase
{
    use RefreshDatabase;

    private function companyData(): array
    {
        return ['legal_name' => 'شركة اختبار فقط', 'vat_number' => '300000000000003',
            'commercial_registration' => '0012345678', 'street' => 'شارع تجريبي', 'building_number' => '0123',
            'district' => 'حي تجريبي', 'city' => 'الرياض', 'postal_code' => '01234'];
    }

    private function saleFixture(): array
    {
        $cashier = User::factory()->create();
        $product = Product::create(['barcode' => '00001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        app(RecordOpeningStock::class)->execute(User::factory()->create(['role' => UserRole::Owner]), [
            'product_id' => $product->id, 'quantity' => '3', 'opening_date' => now()->toDateString(), 'reference' => 'جرد تجريبي',
        ]);
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $shift = app(OpenShift::class)->execute($cashier, ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'opening_cash' => '0.00']);
        $items = [['product_id' => $product->id, 'quantity' => '1']];
        $quote = app(BasketCalculator::class)->calculate($items, Product::all()->keyBy('id'));
        $input = ['operation_id' => (string) Str::uuid(), 'station_id' => $station->id, 'shift_id' => $shift->id, 'items' => $items,
            'quote_fingerprint' => $quote['fingerprint'], 'cash_tender' => '120', 'card_amount' => '0', 'card_confirmed' => false];
        $sale = app(RecordSale::class)->execute($cashier, $input);

        return [$cashier, $product, $sale, $input];
    }

    public function test_only_owner_can_manage_company_and_numeric_identifiers_keep_leading_zeros(): void
    {
        $this->get('/company')->assertRedirect('/login');
        foreach ([UserRole::Cashier, UserRole::Accountant] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))->get('/company')->assertForbidden();
            $this->put('/company', $this->companyData())->assertForbidden();
        }
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->put('/company', $this->companyData())->assertRedirect('/company');
        $this->assertDatabaseHas('company_profiles', [...$this->companyData(), 'id' => 1, 'updated_by' => $owner->id]);
        $this->get('/company')->assertInertia(fn (Assert $page) => $page->component('company/edit')->where('company.building_number', '0123')->where('company.postal_code', '01234'));
        $this->put('/company', [...$this->companyData(), 'legal_name' => 'الاسم المعدل'])->assertRedirect('/company');
        $this->assertDatabaseCount('company_profiles', 1);
    }

    public function test_invalid_profile_data_does_not_replace_saved_company(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->put('/company', $this->companyData());
        $this->putJson('/company', [...$this->companyData(), 'vat_number' => '100000000000003', 'building_number' => '123', 'postal_code' => '123456'])
            ->assertUnprocessable()->assertJsonValidationErrors(['vat_number', 'building_number', 'postal_code']);
        $this->assertDatabaseHas('company_profiles', $this->companyData());
    }

    public function test_receipt_and_retries_keep_sale_seller_snapshot_after_company_changes(): void
    {
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->put('/company', $this->companyData());
        [$cashier, $product, $sale, $input] = $this->saleFixture();
        $snapshot = $sale->refresh()->seller_snapshot;
        $this->put('/company', [...$this->companyData(), 'legal_name' => 'اسم جديد', 'street' => 'شارع آخر']);
        $this->assertSame($snapshot, app(RecordSale::class)->execute($cashier, $input)->seller_snapshot);
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($cashier)->get('/sales/'.$sale->id.'/receipt?width=58')->assertOk()->assertInertia(fn (Assert $page) => $page->component('sales/receipt')->where('paperWidth', '58')->where('sale.seller_snapshot.legal_name', 'شركة اختبار فقط')
                ->where('sale.seller_snapshot.street', 'شارع تجريبي')->where('sale.seller_snapshot.country_code', 'SA')
                ->where('sale.total', '115.00')->where('sale.change', '5.00')->where('sale.fiscal_status', 'not_issued'));
        }
        $this->assertSame($snapshot, $sale->refresh()->seller_snapshot);
        $this->assertSame('2.000', InventoryBalance::findOrFail($product->id)->quantity);
        foreach (['sales', 'sale_payments', 'journal_entries'] as $table) {
            $this->assertDatabaseCount($table, 1);
        }
        $this->assertDatabaseCount('stock_movements', 2);
        $newSale = app(RecordSale::class)->execute($cashier, [...$input, 'operation_id' => (string) Str::uuid()]);
        $this->assertSame('اسم جديد', $newSale->seller_snapshot['legal_name']);
        $this->assertSame('شارع آخر', $newSale->seller_snapshot['street']);
    }

    public function test_legacy_unknown_seller_is_not_backfilled_and_receipt_read_permissions_match_sale(): void
    {
        [$cashier, $product, $sale] = $this->saleFixture();
        $owner = User::factory()->create(['role' => UserRole::Owner]);
        $this->actingAs($owner)->put('/company', $this->companyData());
        $url = '/sales/'.$sale->id.'/receipt';
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->where('sale.seller_snapshot', null)->where('paperWidth', '80'));
        $this->actingAs(User::factory()->create(['role' => UserRole::Accountant]))->get($url)->assertOk();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs($cashier)->get($url)->assertOk();
        $this->getJson($url.'?width=100')->assertUnprocessable()->assertJsonValidationErrors('width');
        $this->assertNull(Sale::findOrFail($sale->id)->seller_snapshot);
        $this->assertDatabaseCount('sales', 1);
    }
}
