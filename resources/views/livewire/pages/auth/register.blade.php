<?php

use App\Models\User;
use App\Services\CartService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts.guest', ['maxWidth' => 'sm:max-w-xl'])] class extends Component
{
    use WithFileUploads;

    public string $role = 'customer';

    // Supplier only - customer/contractor use first_name + last_name instead.
    public string $name = '';

    public string $first_name = '';
    public string $last_name = '';
    public string $email = '';
    public string $phone = '';
    public string $password = '';
    public string $password_confirmation = '';

    // Shared by customer (country of residence) and contractor (operating country).
    public string $country = '';
    public string $city = '';

    // Customer only.
    public string $project_country = '';
    public string $account_type = 'individual';
    public string $preferred_currency = 'XAF';

    // Business name is shared by supplier and contractor - only one role's
    // form is visible/submitted at a time, so there's no field collision.
    public string $business_name = '';

    // Contractor only.
    public string $business_address = '';
    public string $specialization = '';
    public ?int $years_experience = null;
    public string $registration_no = '';
    public string $license_no = '';
    public $id_document;
    public $photo;

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $rules = [
            'role' => ['required', 'in:customer,contractor,supplier'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'phone' => ['required', 'string', 'max:30'],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ];

        if (in_array($this->role, ['customer', 'contractor'], true)) {
            $rules['first_name'] = ['required', 'string', 'max:255'];
            $rules['last_name'] = ['required', 'string', 'max:255'];
            $rules['country'] = ['required', 'string', 'max:255'];
            $rules['city'] = ['required', 'string', 'max:255'];
        }

        if ($this->role === 'customer') {
            $rules['project_country'] = ['required', 'string', 'max:255'];
            $rules['account_type'] = ['required', 'in:individual,diaspora_buyer,property_developer'];
            $rules['preferred_currency'] = ['required', 'in:XAF,USD,EUR,GBP'];
        }

        if ($this->role === 'contractor') {
            $rules['business_name'] = ['required', 'string', 'max:255'];
            $rules['business_address'] = ['required', 'string', 'max:255'];
            $rules['specialization'] = ['required', 'string', 'max:255'];
            $rules['years_experience'] = ['nullable', 'integer', 'min:0', 'max:80'];
            $rules['registration_no'] = ['nullable', 'string', 'max:255'];
            $rules['license_no'] = ['nullable', 'string', 'max:255'];
            $rules['id_document'] = ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'];
            $rules['photo'] = ['nullable', 'image', 'max:2048'];
        }

        if ($this->role === 'supplier') {
            $rules['name'] = ['required', 'string', 'max:255'];
            $rules['business_name'] = ['required', 'string', 'max:255'];
        }

        $validated = $this->validate($rules);

        // Auth::login() below regenerates the session ID internally
        // (SessionGuard::updateSession()) as soon as it runs, so the guest
        // session ID has to be captured before it runs, not after.
        $guestSessionId = Session::getId();

        $name = $this->role === 'supplier'
            ? $validated['name']
            : trim($validated['first_name'].' '.$validated['last_name']);

        $user = User::create([
            'name' => $name,
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => $validated['role'],
            'phone' => $validated['phone'],
            'country' => $validated['country'] ?? null,
            'project_country' => $validated['project_country'] ?? null,
            'city' => $validated['city'] ?? null,
            'account_type' => $validated['account_type'] ?? null,
            'preferred_currency' => $validated['preferred_currency'] ?? 'XAF',
        ]);

        if ($this->role === 'supplier') {
            $user->supplierProfile()->create([
                'business_name' => $validated['business_name'],
                'slug' => Str::slug($validated['business_name']).'-'.Str::lower(Str::random(5)),
            ]);
        }

        if ($this->role === 'contractor') {
            // The ID document is KYC material - it goes on the private
            // "local" disk, never "public", so it's never reachable by URL.
            $idDocumentPath = $this->id_document->store('contractor-documents', 'local');
            $photoPath = $this->photo?->store('contractor-photos', 'public');

            $user->contractorProfile()->create([
                'business_name' => $validated['business_name'],
                'business_address' => $validated['business_address'],
                'specialization' => $validated['specialization'],
                'years_experience' => $validated['years_experience'] ?? null,
                'registration_no' => $validated['registration_no'] ?? null,
                'license_no' => $validated['license_no'] ?? null,
                'id_document_path' => $idDocumentPath,
                'photo_path' => $photoPath,
                'verification_status' => 'pending',
            ]);
        }

        event(new Registered($user));

        Auth::login($user);

        app(CartService::class)->mergeSessionCartInto($user, $guestSessionId);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div x-data="{ role: @entangle('role') }">
    <h1 class="font-heading text-2xl font-bold text-navy-900">Create your account</h1>
    <p class="mt-1 text-sm text-navy-500">Start sourcing building materials from verified suppliers.</p>

    <!-- Account type tabs -->
    <div class="mt-6 relative grid grid-cols-3 gap-1 rounded-xl bg-navy-50 p-1">
        <div
            class="absolute inset-y-1 w-[calc(33.333%-0.167rem)] rounded-lg bg-white shadow-sm transition-transform duration-300 ease-out"
            :class="{
                'translate-x-0': role === 'customer',
                'translate-x-[calc(100%+0.25rem)]': role === 'contractor',
                'translate-x-[calc(200%+0.5rem)]': role === 'supplier',
            }"
        ></div>

        <button
            type="button"
            @click="role = 'customer'"
            aria-label="Customer"
            class="relative z-10 flex items-center justify-center gap-1.5 rounded-lg py-2.5 text-sm font-semibold transition-colors duration-200"
            :class="role === 'customer' ? 'text-navy-900' : 'text-navy-400 hover:text-navy-600'"
        >
            <x-icon name="cart" class="w-4 h-4" />
            <span class="hidden sm:inline">Customer</span>
        </button>
        <button
            type="button"
            @click="role = 'contractor'"
            aria-label="Contractor"
            class="relative z-10 flex items-center justify-center gap-1.5 rounded-lg py-2.5 text-sm font-semibold transition-colors duration-200"
            :class="role === 'contractor' ? 'text-navy-900' : 'text-navy-400 hover:text-navy-600'"
        >
            <x-icon name="tools" class="w-4 h-4" />
            <span class="hidden sm:inline">Contractor</span>
        </button>
        <button
            type="button"
            @click="role = 'supplier'"
            aria-label="Supplier"
            class="relative z-10 flex items-center justify-center gap-1.5 rounded-lg py-2.5 text-sm font-semibold transition-colors duration-200"
            :class="role === 'supplier' ? 'text-navy-900' : 'text-navy-400 hover:text-navy-600'"
        >
            <x-icon name="shield" class="w-4 h-4" />
            <span class="hidden sm:inline">Supplier</span>
        </button>
    </div>

    <p
        x-show="role === 'supplier'"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="mt-3 text-xs text-navy-500 bg-gold-50 border border-gold-100 rounded-lg px-3 py-2"
        x-cloak
    >
        Supplier accounts go through a short verification step before you can list materials.
    </p>

    <p
        x-show="role === 'contractor'"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 -translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        class="mt-3 text-xs text-navy-500 bg-gold-50 border border-gold-100 rounded-lg px-3 py-2"
        x-cloak
    >
        Contractor accounts are verified before listings/orders unlock full features, and can group orders into Projects to track spend per build.
    </p>

    <form wire:submit="register" enctype="multipart/form-data" class="mt-6 {{ $errors->any() ? 'animate-shake' : '' }}">
        <!-- Name: supplier gets a single field, customer/contractor get first + last -->
        <div x-show="role === 'supplier'" x-cloak>
            <x-input-label for="name" :value="__('Your Name')" />
            <x-text-input wire:model="name" id="name" class="block mt-1 w-full" type="text" name="name" autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <div x-show="role !== 'supplier'" x-cloak class="grid sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="first_name" value="First Name" />
                <x-text-input wire:model="first_name" id="first_name" class="block mt-1 w-full" type="text" autocomplete="given-name" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="last_name" value="Last Name" />
                <x-text-input wire:model="last_name" id="last_name" class="block mt-1 w-full" type="text" autocomplete="family-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <!-- Business Name (supplier + contractor) -->
        <div
            x-show="role === 'supplier' || role === 'contractor'"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0 -translate-y-2"
            x-transition:enter-end="opacity-100 translate-y-0"
            class="mt-4"
            x-cloak
        >
            <x-input-label for="business_name" x-text="role === 'contractor' ? 'Business / Company Name' : 'Business Name'" />
            <x-text-input wire:model="business_name" id="business_name" class="block mt-1 w-full" type="text" name="business_name" autocomplete="organization" />
            <x-input-error :messages="$errors->get('business_name')" class="mt-2" />
        </div>

        <!-- Email + Phone -->
        <div class="mt-4 grid sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input wire:model="email" id="email" class="block mt-1 w-full" type="email" name="email" required autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="phone" value="Phone Number" />
                <x-text-input wire:model="phone" id="phone" class="block mt-1 w-full" type="tel" autocomplete="tel" />
                <x-input-error :messages="$errors->get('phone')" class="mt-2" />
            </div>
        </div>

        <!-- Country + City (customer + contractor) -->
        <div x-show="role !== 'supplier'" x-cloak class="mt-4 grid sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="country" x-text="role === 'contractor' ? 'Country' : 'Country of Residence'" />
                <x-text-input wire:model="country" id="country" class="block mt-1 w-full" type="text" />
                <x-input-error :messages="$errors->get('country')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="city" x-text="role === 'contractor' ? 'City / Operating Location' : 'City / Project Location'" />
                <x-text-input wire:model="city" id="city" class="block mt-1 w-full" type="text" />
                <x-input-error :messages="$errors->get('city')" class="mt-2" />
            </div>
        </div>

        <!-- Customer-only fields -->
        <div x-show="role === 'customer'" x-cloak>
            <div class="mt-4">
                <x-input-label for="project_country" value="Country Where the Construction Project is Located" />
                <x-text-input wire:model="project_country" id="project_country" class="block mt-1 w-full" type="text" />
                <x-input-error :messages="$errors->get('project_country')" class="mt-2" />
            </div>

            <div class="mt-4 grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="account_type" value="Account Type" />
                    <select wire:model="account_type" id="account_type" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                        <option value="individual">Individual</option>
                        <option value="diaspora_buyer">Diaspora Buyer</option>
                        <option value="property_developer">Property Developer</option>
                    </select>
                    <x-input-error :messages="$errors->get('account_type')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="preferred_currency" value="Preferred Currency" />
                    <select wire:model="preferred_currency" id="preferred_currency" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                        <option value="XAF">XAF</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                    </select>
                    <x-input-error :messages="$errors->get('preferred_currency')" class="mt-2" />
                </div>
            </div>
        </div>

        <!-- Contractor-only fields -->
        <div x-show="role === 'contractor'" x-cloak>
            <div class="mt-4">
                <x-input-label for="business_address" value="Business Address" />
                <x-text-input wire:model="business_address" id="business_address" class="block mt-1 w-full" type="text" />
                <x-input-error :messages="$errors->get('business_address')" class="mt-2" />
            </div>

            <div class="mt-4 grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="specialization" value="Type of Contractor / Specialization" />
                    <select wire:model="specialization" id="specialization" class="mt-1 block w-full rounded-lg border-navy-200 focus:border-gold-500 focus:ring-gold-500 text-sm">
                        <option value="">Select one</option>
                        <option>General Contractor</option>
                        <option>Electrical</option>
                        <option>Plumbing</option>
                        <option>Roofing</option>
                        <option>Masonry</option>
                        <option>Architecture</option>
                        <option>Other</option>
                    </select>
                    <x-input-error :messages="$errors->get('specialization')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="years_experience" value="Years of Experience" />
                    <x-text-input wire:model="years_experience" id="years_experience" class="block mt-1 w-full" type="number" min="0" max="80" />
                    <x-input-error :messages="$errors->get('years_experience')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4 grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="registration_no" value="Business Registration Number (optional)" />
                    <x-text-input wire:model="registration_no" id="registration_no" class="block mt-1 w-full" type="text" />
                    <x-input-error :messages="$errors->get('registration_no')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="license_no" value="Professional License / Certification (optional)" />
                    <x-text-input wire:model="license_no" id="license_no" class="block mt-1 w-full" type="text" />
                    <x-input-error :messages="$errors->get('license_no')" class="mt-2" />
                </div>
            </div>

            <div class="mt-4 grid sm:grid-cols-2 gap-4">
                <div>
                    <x-input-label for="id_document" value="Identification Document" />
                    <input wire:model="id_document" id="id_document" type="file" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm text-navy-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-navy-50 file:text-navy-700 file:text-sm file:font-semibold hover:file:bg-navy-100" />
                    <p class="mt-1 text-xs text-navy-400" wire:loading wire:target="id_document">Uploading&hellip;</p>
                    <x-input-error :messages="$errors->get('id_document')" class="mt-2" />
                </div>
                <div>
                    <x-input-label for="photo" value="Profile Photo / Company Logo (optional)" />
                    <input wire:model="photo" id="photo" type="file" accept="image/*" class="mt-1 block w-full text-sm text-navy-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:bg-navy-50 file:text-navy-700 file:text-sm file:font-semibold hover:file:bg-navy-100" />
                    <p class="mt-1 text-xs text-navy-400" wire:loading wire:target="photo">Uploading&hellip;</p>
                    <x-input-error :messages="$errors->get('photo')" class="mt-2" />
                </div>
            </div>
        </div>

        <!-- Password -->
        <div class="mt-4 grid sm:grid-cols-2 gap-4">
            <div>
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input wire:model="password" id="password" class="block mt-1 w-full"
                                type="password"
                                name="password"
                                required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" class="mt-2" />
            </div>
            <div>
                <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
                <x-text-input wire:model="password_confirmation" id="password_confirmation" class="block mt-1 w-full"
                                type="password"
                                name="password_confirmation" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
            </div>
        </div>

        <div class="mt-6">
            <x-primary-button class="w-full justify-center py-3" wire:loading.attr="disabled" wire:target="register">
                <span wire:loading.remove wire:target="register" x-text="{
                    customer: 'Create Account',
                    contractor: 'Create Contractor Account',
                    supplier: 'Create Supplier Account',
                }[role]"></span>
                <span wire:loading wire:target="register">{{ __('Creating account...') }}</span>
            </x-primary-button>
        </div>
    </form>

    <p class="mt-8 text-center text-sm text-navy-500">
        Already have an account?
        <a href="{{ route('login') }}" wire:navigate class="font-semibold text-navy-700 hover:text-gold-600 transition-colors">Log in</a>
    </p>
</div>
