<?php

namespace Tests\Feature;

use App\Filament\Resources\PaymentGateways\Pages\ManagePaymentGateways;
use App\Models\PaymentGateway;
use App\Models\User;
use Database\Seeders\PaymentGatewaySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PaymentGatewayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeder_creates_all_three_providers_disabled(): void
    {
        $this->seed(PaymentGatewaySeeder::class);

        $this->assertDatabaseCount('payment_gateways', 3);
        $this->assertDatabaseHas('payment_gateways', ['provider' => 'flutterwave', 'is_enabled' => false]);
        $this->assertDatabaseHas('payment_gateways', ['provider' => 'paystack', 'is_enabled' => false]);
        $this->assertDatabaseHas('payment_gateways', ['provider' => 'fapshi', 'is_enabled' => false]);
    }

    public function test_the_seeder_never_overwrites_an_admin_configured_gateway(): void
    {
        $this->seed(PaymentGatewaySeeder::class);

        // Updating the found model instance (not a bulk query-builder
        // update) so the 'encrypted:array' cast actually applies.
        PaymentGateway::where('provider', 'paystack')->first()->update([
            'is_enabled' => true,
            'credentials' => ['public_key' => 'pk_live_123', 'secret_key' => 'sk_live_456'],
        ]);

        $this->seed(PaymentGatewaySeeder::class);

        $paystack = PaymentGateway::where('provider', 'paystack')->first();
        $this->assertTrue($paystack->is_enabled);
        $this->assertSame('pk_live_123', $paystack->credential('public_key'));
    }

    public function test_credentials_are_encrypted_at_rest(): void
    {
        $gateway = PaymentGateway::create([
            'provider' => 'paystack',
            'display_name' => 'Paystack',
            'is_enabled' => true,
            'mode' => 'live',
            'credentials' => ['secret_key' => 'sk_live_supersecret'],
        ]);

        $rawColumnValue = DB::table('payment_gateways')->where('id', $gateway->id)->value('credentials');

        $this->assertStringNotContainsString('sk_live_supersecret', $rawColumnValue);
        $this->assertSame('sk_live_supersecret', $gateway->fresh()->credential('secret_key'));
    }

    public function test_admins_cannot_create_or_delete_gateway_rows(): void
    {
        $this->seed(PaymentGatewaySeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $gateway = PaymentGateway::where('provider', 'flutterwave')->first();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManagePaymentGateways::class)
            ->assertActionDoesNotExist('create')
            ->assertTableActionDoesNotExist('delete', record: $gateway);
    }

    public function test_an_admin_can_enable_a_gateway_and_set_its_credentials(): void
    {
        $this->seed(PaymentGatewaySeeder::class);
        $admin = User::factory()->create(['role' => 'admin']);
        $gateway = PaymentGateway::where('provider', 'fapshi')->first();

        $this->actingAs($admin, 'admin');

        Livewire::test(ManagePaymentGateways::class)
            ->callTableAction('edit', $gateway, data: [
                'is_enabled' => true,
                'mode' => 'live',
                'credentials' => [
                    'api_user' => 'user-123',
                    'api_key' => 'key-456',
                ],
            ]);

        $gateway->refresh();
        $this->assertTrue($gateway->is_enabled);
        $this->assertSame('live', $gateway->mode);
        $this->assertSame('user-123', $gateway->credential('api_user'));
        $this->assertSame('key-456', $gateway->credential('api_key'));
    }
}
