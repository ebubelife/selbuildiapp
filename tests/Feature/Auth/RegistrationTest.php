<?php

namespace Tests\Feature\Auth;

use App\Notifications\WelcomeEmail;
use Database\Seeders\CountrySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CountrySeeder::class);
    }

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.register');
    }

    public function test_new_customers_can_register(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'test@example.com')
            ->set('phone', '+237600000000')
            ->set('country', 'France')
            ->set('city', 'Paris')
            ->set('project_country', 'Cameroon')
            ->set('account_type', 'diaspora_buyer')
            ->set('preferred_currency', 'EUR')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = auth()->user();
        $this->assertSame('customer', $user->role);
        $this->assertSame('Test User', $user->name);
        $this->assertSame('Test', $user->first_name);
        $this->assertSame('User', $user->last_name);
        $this->assertSame('diaspora_buyer', $user->account_type);
        $this->assertSame('EUR', $user->preferred_currency);
        $this->assertSame('Cameroon', $user->project_country);
    }

    public function test_registering_sends_a_welcome_email(): void
    {
        Notification::fake();

        Volt::test('pages.auth.register')
            ->set('first_name', 'Test')
            ->set('last_name', 'User')
            ->set('email', 'test@example.com')
            ->set('phone', '+237600000000')
            ->set('country', 'France')
            ->set('city', 'Paris')
            ->set('project_country', 'Cameroon')
            ->set('account_type', 'diaspora_buyer')
            ->set('preferred_currency', 'EUR')
            ->set('password', 'password')
            ->set('password_confirmation', 'password')
            ->call('register');

        Notification::assertSentTo(auth()->user(), WelcomeEmail::class);
    }

    public function test_customer_registration_requires_core_fields(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('email', 'test@example.com')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['first_name', 'last_name', 'phone', 'country', 'city', 'project_country']);
        $this->assertGuest();
    }

    public function test_new_suppliers_can_register_with_a_pending_supplier_profile(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'supplier')
            ->set('name', 'Test Supplier')
            ->set('business_name', 'Test Building Supplies')
            ->set('email', 'supplier@example.com')
            ->set('phone', '+237600000000')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = auth()->user();
        $this->assertSame('supplier', $user->role);
        $this->assertNotNull($user->supplierProfile);
        $this->assertSame('Test Building Supplies', $user->supplierProfile->business_name);
        $this->assertFalse($user->supplierProfile->isVerified());
    }

    public function test_supplier_registration_requires_a_business_name(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'supplier')
            ->set('name', 'Test Supplier')
            ->set('email', 'supplier@example.com')
            ->set('phone', '+237600000000')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['business_name' => 'required']);
        $this->assertGuest();
    }

    public function test_new_contractors_can_register_with_a_pending_contractor_profile(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $component = Volt::test('pages.auth.register')
            ->set('role', 'contractor')
            ->set('first_name', 'Test')
            ->set('last_name', 'Contractor')
            ->set('business_name', 'Test Contracting Co')
            ->set('email', 'contractor@example.com')
            ->set('phone', '+237600000000')
            ->set('country', 'Cameroon')
            ->set('city', 'Douala')
            ->set('business_address', '12 Rue Industrielle')
            ->set('specialization', 'General Contractor')
            ->set('years_experience', 8)
            ->set('id_document', UploadedFile::fake()->create('id.pdf', 500, 'application/pdf'))
            ->set('photo', UploadedFile::fake()->image('logo.jpg'))
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();

        $user = auth()->user();
        $this->assertSame('contractor', $user->role);
        $this->assertTrue($user->isContractor());
        $this->assertNull($user->supplierProfile);

        $profile = $user->contractorProfile;
        $this->assertNotNull($profile);
        $this->assertSame('Test Contracting Co', $profile->business_name);
        $this->assertSame('General Contractor', $profile->specialization);
        $this->assertSame(8, $profile->years_experience);
        $this->assertSame('pending', $profile->verification_status);
        $this->assertNotNull($profile->id_document_path);
        Storage::disk('local')->assertExists($profile->id_document_path);
        Storage::disk('public')->assertExists($profile->photo_path);
    }

    public function test_contractor_registration_requires_an_identification_document(): void
    {
        $component = Volt::test('pages.auth.register')
            ->set('role', 'contractor')
            ->set('first_name', 'Test')
            ->set('last_name', 'Contractor')
            ->set('business_name', 'Test Contracting Co')
            ->set('email', 'contractor@example.com')
            ->set('phone', '+237600000000')
            ->set('country', 'Cameroon')
            ->set('city', 'Douala')
            ->set('business_address', '12 Rue Industrielle')
            ->set('specialization', 'General Contractor')
            ->set('password', 'password')
            ->set('password_confirmation', 'password');

        $component->call('register');

        $component->assertHasErrors(['id_document' => 'required']);
        $this->assertGuest();
    }
}
