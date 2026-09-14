<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use HasFactory, SoftDeletes;
    protected $fillable = [
        'name','user_id','gender','dob','description','business_id','created_by',
        'mrn','cnic','blood_group','allergies','chronic_conditions','emergency_contact',
        'email','phone','age'
    ];

    protected static function booted()
    {
        static::creating(function (Customer $patient) {
            if (empty($patient->mrn)) {
                $patient->mrn = static::nextMrn((int) $patient->business_id);
            }
        });
    }

    /**
     * MRN-{random} — collision-safe via retry loop WITHIN the tenant: MRNs
     * are clinically unique per tenant, never across tenants (a shared
     * global namespace would imply cross-tenant patient matching).
     */
    public static function nextMrn(int $businessId): string
    {
        do {
            $candidate = 'MRN-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('business_id', $businessId)->where('mrn', $candidate)->exists());

        return $candidate;
    }

    public function customer()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

}
