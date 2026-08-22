<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AddressBookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_redirected_away_from_the_address_book(): void
    {
        $this->get(route('addresses.index'))->assertRedirect(route('login'));
    }

    public function test_adding_the_first_address_makes_it_the_default_automatically(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);

        Volt::test('addresses.index')
            ->set('recipient_name', 'Test Customer')
            ->set('phone', '+237600000000')
            ->set('country', 'Cameroon')
            ->set('city', 'Douala')
            ->set('street', '123 Rue de la Paix')
            ->call('save');

        $this->assertDatabaseHas('addresses', [
            'recipient_name' => 'Test Customer',
            'is_default' => true,
        ]);
    }

    public function test_setting_a_new_default_unsets_the_previous_one(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $first = $user->addresses()->create([
            'recipient_name' => 'First', 'phone' => '+237600000000',
            'country' => 'Cameroon', 'city' => 'Douala', 'street' => 'Street A',
            'is_default' => true,
        ]);
        $second = $user->addresses()->create([
            'recipient_name' => 'Second', 'phone' => '+237600000001',
            'country' => 'Cameroon', 'city' => 'Yaoundé', 'street' => 'Street B',
            'is_default' => false,
        ]);

        $this->actingAs($user);

        Volt::test('addresses.index')->call('makeDefault', $second->id);

        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_editing_an_address_updates_it_in_place(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $address = $user->addresses()->create([
            'recipient_name' => 'Old Name', 'phone' => '+237600000000',
            'country' => 'Cameroon', 'city' => 'Douala', 'street' => 'Old Street',
            'is_default' => true,
        ]);

        $this->actingAs($user);

        Volt::test('addresses.index')
            ->call('edit', $address->id)
            ->set('recipient_name', 'New Name')
            ->set('street', 'New Street')
            ->call('save');

        $this->assertDatabaseCount('addresses', 1);
        $this->assertSame('New Name', $address->fresh()->recipient_name);
        $this->assertSame('New Street', $address->fresh()->street);
    }

    public function test_deleting_the_default_address_promotes_another_one(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $first = $user->addresses()->create([
            'recipient_name' => 'First', 'phone' => '+237600000000',
            'country' => 'Cameroon', 'city' => 'Douala', 'street' => 'Street A',
            'is_default' => true,
        ]);
        $second = $user->addresses()->create([
            'recipient_name' => 'Second', 'phone' => '+237600000001',
            'country' => 'Cameroon', 'city' => 'Yaoundé', 'street' => 'Street B',
            'is_default' => false,
        ]);

        $this->actingAs($user);

        Volt::test('addresses.index')->call('delete', $first->id);

        $this->assertDatabaseCount('addresses', 1);
        $this->assertTrue($second->fresh()->is_default);
    }

    public function test_a_user_cannot_edit_another_users_address(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $address = $owner->addresses()->create([
            'recipient_name' => 'Owner', 'phone' => '+237600000000',
            'country' => 'Cameroon', 'city' => 'Douala', 'street' => 'Street A',
        ]);

        $intruder = User::factory()->create(['role' => 'customer']);
        $this->actingAs($intruder);

        $this->expectException(ModelNotFoundException::class);

        Volt::test('addresses.index')->call('edit', $address->id);
    }
}
