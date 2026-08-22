<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['provider', 'display_name', 'is_enabled', 'mode', 'credentials'])]
class PaymentGateway extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            // Laravel's encrypted cast - API keys/secrets are encrypted
            // with APP_KEY before being written to the database, and
            // transparently decrypted on read. Losing APP_KEY makes these
            // unrecoverable, same trade-off as any other encrypted column.
            'credentials' => 'encrypted:array',
        ];
    }

    public function credential(string $key): ?string
    {
        return $this->credentials[$key] ?? null;
    }
}
