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
        'mrn','cnic','blood_group','allergies','chronic_conditions','emergency_contact'
    ];

    protected static function booted()
    {
        static::creating(function (Customer $customer) {
            if (empty($customer->mrn)) {
                $customer->mrn = static::nextMrn();
            }
        });
    }

    /** MRN-{seq} — collision-safe via retry loop. */
    public static function nextMrn(): string
    {
        do {
            $max = (int) static::withTrashed()->max('id') + 1;
            $candidate = 'MRN-'.str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
        } while (static::withTrashed()->where('mrn', $candidate)->exists());

        return $candidate;
    }

    public function customer()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

}
