<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyProfile extends Model
{
    public const SELLER_FIELDS = ['legal_name', 'vat_number', 'commercial_registration', 'street', 'building_number', 'district', 'city', 'postal_code'];

    protected $guarded = [];

    /** @return array<string, string> */
    public function sellerSnapshot(): array
    {
        /** @var array<string, string> $fields */
        $fields = $this->only(self::SELLER_FIELDS);

        return [...$fields, 'country_code' => 'SA', 'profile_updated_at' => $this->updated_at->toISOString()];
    }
}
