<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\PosStation;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StoreFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_account_command_normalizes_email_and_assigns_the_selected_role(): void
    {
        $this->artisan('store:create-user')
            ->expectsQuestion('اسم الموظف', 'موظف اختبار')
            ->expectsQuestion('البريد الإلكتروني', 'Staff@TLI.Test')
            ->expectsQuestion('الدور', UserRole::Accountant->value)
            ->expectsQuestion('كلمة المرور', 'TestPassword1234')
            ->assertSuccessful();

        $user = User::where('email', 'staff@tli.test')->firstOrFail();
        $this->assertSame(UserRole::Accountant, $user->role);
        $this->assertTrue(Hash::check('TestPassword1234', $user->password));
    }

    public function test_staff_authentication_is_required_and_public_registration_is_disabled(): void
    {
        $this->get('/pos')->assertRedirect('/login');
        $this->getJson('/pos/products?barcode=000001')->assertUnauthorized();
        $this->get('/register')->assertNotFound();
        $this->post('/register')->assertNotFound();

        $user = User::factory()->create();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/pos');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_cashier_cannot_create_products_and_owner_preserves_leading_zero_barcodes(): void
    {
        $input = ['barcode' => '000001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00'];
        $this->actingAs(User::factory()->create())->postJson('/products', $input)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => UserRole::Owner]))->post('/products', $input)->assertRedirect('/products');
        $this->assertDatabaseHas('products', $input);
        $this->postJson('/products', $input)->assertUnprocessable()->assertJsonValidationErrors('barcode');
        $this->getJson('/pos/products?barcode=000001')->assertOk()->assertJsonPath('products.0.barcode', '000001');
    }

    public function test_quote_uses_server_prices_and_tax_and_has_no_sale_side_effects(): void
    {
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $product = Product::create(['barcode' => '000001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        $this->actingAs(User::factory()->create())->postJson('/pos/quote', [
            'station_id' => $station->id,
            'items' => [['product_id' => $product->id, 'quantity' => '2', 'price' => '0.01', 'tax_rate' => '0.00']],
            'totals' => ['total' => '0.02'],
        ])->assertOk()->assertJsonPath('totals', ['net' => '200.00', 'tax' => '30.00', 'total' => '230.00'])
            ->assertJsonPath('lines.0.unit_price', '115.00');

        $this->assertDatabaseCount('products', 1);
        $this->assertSame('115.00', $product->fresh()->price);
    }

    public function test_quote_rejects_invalid_quantities_duplicate_products_and_inactive_records(): void
    {
        $station = PosStation::create(['code' => 'POS-1', 'name' => 'صندوق 1']);
        $product = Product::create(['barcode' => '000001', 'name' => 'صنف اختبار', 'unit' => 'حبة', 'price' => '115.00', 'tax_rate' => '15.00']);
        $this->actingAs(User::factory()->create());
        foreach (['0', '-1', '0.0001', '10001', '1e3', 1] as $quantity) {
            $this->postJson('/pos/quote', ['station_id' => $station->id, 'items' => [['product_id' => $product->id, 'quantity' => $quantity]]])
                ->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        }
        $item = ['product_id' => $product->id, 'quantity' => '1'];
        $this->postJson('/pos/quote', ['station_id' => $station->id, 'items' => [$item, $item]])->assertUnprocessable();
        $product->update(['is_active' => false]);
        $this->getJson('/pos/products?barcode=000001')->assertNotFound();
        $this->postJson('/pos/quote', ['station_id' => $station->id, 'items' => [$item]])->assertUnprocessable();
        $product->update(['is_active' => true]);
        $station->update(['is_active' => false]);
        $this->postJson('/pos/quote', ['station_id' => $station->id, 'items' => [$item]])->assertUnprocessable()->assertJsonValidationErrors('station_id');
    }
}
