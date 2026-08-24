<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    // protected $fillable = [
    //     'name','category_id','price','business_id','created_by'
    // ];

    protected $guarded;

    public function Category()
    {
        return $this->hasOne(category::class, 'id', 'category_id');
    }

    // Service belongs to Business
    public function business()
    {
        return $this->belongsTo(Business::class, 'business_id');
    }

    // Service can have many appointments (if needed)
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'service_id');
    }
}
