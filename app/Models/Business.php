<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'slug',
        'is_disable',
        'created_by',
        'form_type',
        'layouts',
        'theme_color'
    ];

    protected static function boot()
    {
        parent::boot();

        static::created(function ($business) {

            $business->slug = $business->createSlug($business->name);

            $business->save();
        });
    }
    public static function pwa_business($slug)
    {
        // Single-clinic app: PWA add-on removed.
        return [];
    }

    private function createSlug($name)
    {
        if (static::whereSlug($slug = \Str::slug($name))->exists()) {

            $max = static::whereName($name)->latest('id')->skip(1)->value('slug');

            if (isset($max[-1]) && is_numeric($max[-1])) {

                return preg_replace_callback('/(\d+)$/', function ($mathces) {

                    return $mathces[1] + 1;
                }, $max);
            }
            return "{$slug}-2";
        }
        return $slug;
    }

        public static function forms()
    {
        return [
            'Formlayout11' => [
                'color1-Formlayout11' => [
                    'img_path' => get_file('form_layouts/Formlayout11/images/form.png'),
                    'color' => '#4F46E5',
                    'theme_name' => 'Formlayout11-v1'
                ],
            ],
        ];
    }


    // Define relationship between Business and Appointments
    public function appointments()
    {
        return $this->hasMany(Appointment::class, 'business_id');
    }

    // Define relationship between Business and Services
    public function services()
    {
        return $this->hasMany(Service::class, 'business_id');
    }
}
