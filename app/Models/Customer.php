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

    /**
     * Register a patient through the ONE path every entry point uses.
     *
     * A patient in this system is a `customer` user identity plus the clinical
     * Customer row linked through `user_id` (the booking, reception and
     * reporting flows all depend on that pairing). Creating a Customer row
     * directly violates `customers.user_id NOT NULL`, which is exactly the
     * kind of drift a single entry point prevents.
     *
     * `email` is unique platform-wide; a walk-in without one gets a synthetic
     * address so the identity still exists and can later be claimed.
     *
     * @param  array<string,mixed>  $attributes  name, email, phone, age, gender, bloodGroup, allergies…
     */
    public static function register(array $attributes, int $businessId, ?int $createdBy = null): self
    {
        $createdBy ??= auth()->id();
        $email = trim((string) ($attributes['email'] ?? ''));

        $synthetic = fn () => 'walkin.'.now()->timestamp.'.'.random_int(1000, 9999).'@patients.local';
        if ($email === '') {
            $email = $synthetic();
        }
        while (User::where('email', $email)->exists()) {
            $email = $synthetic();
        }

        $user = User::create([
            'name' => $attributes['name'],
            'email' => $email,
            'password' => \Illuminate\Support\Str::password(16),
            'type' => 'customer',
            'active_status' => 1,
            'is_enable_login' => 0,
            'business_id' => $businessId,
            'created_by' => $createdBy,
        ]);

        return static::create([
            'name' => $attributes['name'],
            'user_id' => $user->id,
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? null,
            'age' => $attributes['age'] ?? null,
            'gender' => $attributes['gender'] ?? 'other',
            'blood_group' => $attributes['bloodGroup'] ?? null,
            'allergies' => $attributes['allergies'] ?? null,
            'business_id' => $businessId,
            'created_by' => $createdBy,
        ]);
    }

}
