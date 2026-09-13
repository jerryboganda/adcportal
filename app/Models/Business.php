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
        'theme_color',
        'plan_id',
        'subscription_status',
        'trial_ends_at',
        'subscription_ends_at',
        'tenant_code',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'subscription_ends_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /** Subscribable tenants may use the product; suspended/expired may not. */
    public function isSubscribable(): bool
    {
        // NOTE: the legacy businesses.is_disable flag is inverted (1 = enabled),
        // so the SaaS gate relies on subscription_status only.

        return match ($this->subscription_status) {
            'active' => true,
            'trialing' => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            default => false,
        };
    }

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
        // Register every public booking layout with its theme variant so
        // forms()[layouts][theme_color] always resolves. Formlayout11 is the
        // fully-featured layout; the rest share the standard wizard shell.
        $forms = [];
        foreach ([
            'Formlayout1', 'Formlayout2', 'Formlayout3', 'Formlayout4', 'Formlayout5',
            'Formlayout6', 'Formlayout7', 'Formlayout8', 'Formlayout9', 'Formlayout10',
            'Formlayout11',
        ] as $layout) {
            $forms[$layout] = [
                "color1-{$layout}" => [
                    'img_path' => get_file("form_layouts/{$layout}/images/form.png"),
                    'color' => $layout === 'Formlayout11' ? '#4F46E5' : '#0F4C81',
                    'theme_name' => "{$layout}-v1",
                ],
            ];
        }

        return $forms;
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
