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
        $arr = [];
        $arr = [
            'Formlayout1' => [
                'color1-Formlayout1' => [
                    'img_path' => get_file('form_layouts/Formlayout1/images/form.png'),
                    'color' => '#3CA295',
                    'theme_name' => 'Formlayout1-v1'
                ],
                'color2-Formlayout1' => [
                    'img_path' => get_file('form_layouts/Formlayout1/images/form2.png'),
                    'color' => '#7469B6',
                    'theme_name' => 'Formlayout1-v2'
                ],
                'color3-Formlayout1' => [
                    'img_path' => get_file('form_layouts/Formlayout1/images/form3.png'),
                    'color' => '#944E63',
                    'theme_name' => 'Formlayout1-v3'
                ],
                'color4-Formlayout1' => [
                    'img_path' => get_file('form_layouts/Formlayout1/images/form4.png'),
                    'color' => '#114232',
                    'theme_name' => 'Formlayout1-v4'
                ],
                'color5-Formlayout1' => [
                    'img_path' => get_file('form_layouts/Formlayout1/images/form5.png'),
                    'color' => '#EE7214',
                    'theme_name' => 'Formlayout1-v5'
                ],
            ],
            'Formlayout2' => [
                'color1-Formlayout2' => [
                    'img_path' => get_file('form_layouts/Formlayout2/images/form.png'),
                    'color' => '#0075FE',
                    'theme_name' => 'Formlayout2-v1'
                ],
                'color2-Formlayout2' => [
                    'img_path' => get_file('form_layouts/Formlayout2/images/form2.png'),
                    'color' => '#FF407D',
                    'theme_name' => 'Formlayout2-v2'
                ],
                'color3-Formlayout2' => [
                    'img_path' => get_file('form_layouts/Formlayout2/images/form3.png'),
                    'color' => '#EE6B4D',
                    'theme_name' => 'Formlayout2-v3'
                ],
                'color4-Formlayout2' => [
                    'img_path' => get_file('form_layouts/Formlayout2/images/form4.png'),
                    'color' => '#634A00',
                    'theme_name' => 'Formlayout2-v4'
                ],
                'color5-Formlayout2' => [
                    'img_path' => get_file('form_layouts/Formlayout2/images/form5.png'),
                    'color' => '#00254E',
                    'theme_name' => 'Formlayout2-v5'
                ],
            ],
            'Formlayout3' => [
                'color1-Formlayout3' => [
                    'img_path' => get_file('form_layouts/Formlayout3/images/form.png'),
                    'color' => '#2760A7',
                    'theme_name' => 'Formlayout3-v1'
                ],
                'color2-Formlayout3' => [
                    'img_path' => get_file('form_layouts/Formlayout3/images/form2.png'),
                    'color' => '#59522C',
                    'theme_name' => 'Formlayout3-v2'
                ],
                'color3-Formlayout3' => [
                    'img_path' => get_file('form_layouts/Formlayout3/images/form3.png'),
                    'color' => '#F2A30F',
                    'theme_name' => 'Formlayout3-v3'
                ],
                'color4-Formlayout3' => [
                    'img_path' => get_file('form_layouts/Formlayout3/images/form4.png'),
                    'color' => '#6420AA',
                    'theme_name' => 'Formlayout3-v4'
                ],
                'color5-Formlayout3' => [
                    'img_path' => get_file('form_layouts/Formlayout3/images/form5.png'),
                    'color' => '#3D5B81',
                    'theme_name' => 'Formlayout3-v5'
                ],
            ],
            'Formlayout4' => [
                'color1-Formlayout4' => [
                    'img_path' => get_file('form_layouts/Formlayout4/images/form.png'),
                    'color' => '#603725',
                    'theme_name' => 'Formlayout4-v1'
                ],
                'color2-Formlayout4' => [
                    'img_path' => get_file('form_layouts/Formlayout4/images/form2.png'),
                    'color' => '#1B4242',
                    'theme_name' => 'Formlayout4-v2'
                ],
                'color3-Formlayout4' => [
                    'img_path' => get_file('form_layouts/Formlayout4/images/form3.png'),
                    'color' => '#87A922',
                    'theme_name' => 'Formlayout4-v3'
                ],
                'color4-Formlayout4' => [
                    'img_path' => get_file('form_layouts/Formlayout4/images/form4.png'),
                    'color' => '#26B6C6',
                    'theme_name' => 'Formlayout4-v4'
                ],
                'color5-Formlayout4' => [
                    'img_path' => get_file('form_layouts/Formlayout4/images/form5.png'),
                    'color' => '#70732D',
                    'theme_name' => 'Formlayout4-v5'
                ],
            ],
            'Formlayout5' => [
                'color1-Formlayout5' => [
                    'img_path' => get_file('form_layouts/Formlayout5/images/form.png'),
                    'color' => '#48A5EA',
                    'theme_name' => 'Formlayout5-v1'
                ],
                'color2-Formlayout5' => [
                    'img_path' => get_file('form_layouts/Formlayout5/images/form2.png'),
                    'color' => '#5C8374',
                    'theme_name' => 'Formlayout5-v2'
                ],
                'color3-Formlayout5' => [
                    'img_path' => get_file('form_layouts/Formlayout5/images/form3.png'),
                    'color' => '#DC2D4E',
                    'theme_name' => 'Formlayout5-v3'
                ],
                'color4-Formlayout5' => [
                    'img_path' => get_file('form_layouts/Formlayout5/images/form4.png'),
                    'color' => '#2D53DC',
                    'theme_name' => 'Formlayout5-v4'
                ],
                'color5-Formlayout5' => [
                    'img_path' => get_file('form_layouts/Formlayout5/images/form5.png'),
                    'color' => '#D88304',
                    'theme_name' => 'Formlayout5-v5'
                ],
            ],
            'Formlayout6' => [
                'color1-Formlayout6' => [
                    'img_path' => get_file('form_layouts/Formlayout6/images/form.png'),
                    'color' => '#F17D32',
                    'theme_name' => 'Formlayout6-v1'
                ],
                'color2-Formlayout6' => [
                    'img_path' => get_file('form_layouts/Formlayout6/images/form2.png'),
                    'color' => '#26B6C6',
                    'theme_name' => 'Formlayout6-v2'
                ],
                'color3-Formlayout6' => [
                    'img_path' => get_file('form_layouts/Formlayout6/images/form3.png'),
                    'color' => '#9368A6',
                    'theme_name' => 'Formlayout6-v3'
                ],
                'color4-Formlayout6' => [
                    'img_path' => get_file('form_layouts/Formlayout6/images/form4.png'),
                    'color' => '#4B8C49',
                    'theme_name' => 'Formlayout6-v4'
                ],
                'color5-Formlayout6' => [
                    'img_path' => get_file('form_layouts/Formlayout6/images/form5.png'),
                    'color' => '#AD0626',
                    'theme_name' => 'Formlayout6-v5'
                ],
            ],
            'Formlayout7' => [
                'color1-Formlayout7' => [
                    'img_path' => get_file('form_layouts/Formlayout7/images/form.png'),
                    'color' => '#7468F2',
                    'theme_name' => 'Formlayout7-v1'
                ],
                'color2-Formlayout7' => [
                    'img_path' => get_file('form_layouts/Formlayout7/images/form2.png'),
                    'color' => '#008143',
                    'theme_name' => 'Formlayout7-v2'
                ],
                'color3-Formlayout7' => [
                    'img_path' => get_file('form_layouts/Formlayout7/images/form3.png'),
                    'color' => '#6F8100',
                    'theme_name' => 'Formlayout7-v3'
                ],
                'color4-Formlayout7' => [
                    'img_path' => get_file('form_layouts/Formlayout7/images/form4.png'),
                    'color' => '#70105A',
                    'theme_name' => 'Formlayout7-v4'
                ],
                'color5-Formlayout7' => [
                    'img_path' => get_file('form_layouts/Formlayout7/images/form5.png'),
                    'color' => '#101470',
                    'theme_name' => 'Formlayout7-v5'
                ],
            ],
            'Formlayout8' => [
                'color1-Formlayout8' => [
                    'img_path' => get_file('form_layouts/Formlayout8/images/form.png'),
                    'color' => '#FF2F67',
                    'theme_name' => 'Formlayout8-v1'
                ],
                'color2-Formlayout8' => [
                    'img_path' => get_file('form_layouts/Formlayout8/images/form2.png'),
                    'color' => '#4F81F7',
                    'theme_name' => 'Formlayout8-v2'
                ],
                'color3-Formlayout8' => [
                    'img_path' => get_file('form_layouts/Formlayout8/images/form3.png'),
                    'color' => '#014040',
                    'theme_name' => 'Formlayout8-v3'
                ],
                'color4-Formlayout8' => [
                    'img_path' => get_file('form_layouts/Formlayout8/images/form4.png'),
                    'color' => '#4C302F',
                    'theme_name' => 'Formlayout8-v4'
                ],
                'color5-Formlayout8' => [
                    'img_path' => get_file('form_layouts/Formlayout8/images/form5.png'),
                    'color' => '#9368A6',
                    'theme_name' => 'Formlayout8-v5'
                ],
            ],
            'Formlayout9' => [
                'color1-Formlayout9' => [
                    'img_path' => get_file('form_layouts/Formlayout9/images/form.png'),
                    'color' => '#35B288',
                    'theme_name' => 'Formlayout9-v1'
                ],
                'color2-Formlayout9' => [
                    'img_path' => get_file('form_layouts/Formlayout9/images/form2.png'),
                    'color' => '#D39065',
                    'theme_name' => 'Formlayout9-v2'
                ],
                'color3-Formlayout9' => [
                    'img_path' => get_file('form_layouts/Formlayout9/images/form3.png'),
                    'color' => '#F2AA52',
                    'theme_name' => 'Formlayout9-v3'
                ],
                'color4-Formlayout9' => [
                    'img_path' => get_file('form_layouts/Formlayout9/images/form4.png'),
                    'color' => '#77B3D9',
                    'theme_name' => 'Formlayout9-v4'
                ],
                'color5-Formlayout9' => [
                    'img_path' => get_file('form_layouts/Formlayout9/images/form5.png'),
                    'color' => '#A65005',
                    'theme_name' => 'Formlayout9-v5'
                ],
            ],
            'Formlayout10' => [
                'color1-Formlayout10' => [
                    'img_path' => get_file('form_layouts/Formlayout10/images/form.png'),
                    'color' => '#07CCEC',
                    'theme_name' => 'Formlayout10-v1'
                ],
                'color2-Formlayout10' => [
                    'img_path' => get_file('form_layouts/Formlayout10/images/form2.png'),
                    'color' => '#A96E70',
                    'theme_name' => 'Formlayout10-v2'
                ],
                'color3-Formlayout10' => [
                    'img_path' => get_file('form_layouts/Formlayout10/images/form3.png'),
                    'color' => '#7A5C40',
                    'theme_name' => 'Formlayout10-v3'
                ],
                'color4-Formlayout10' => [
                    'img_path' => get_file('form_layouts/Formlayout10/images/form4.png'),
                    'color' => '#5A86BF',
                    'theme_name' => 'Formlayout10-v4'
                ],
                'color5-Formlayout10' => [
                    'img_path' => get_file('form_layouts/Formlayout10/images/form5.png'),
                    'color' => '#AD8623',
                    'theme_name' => 'Formlayout10-v5'
                ],
            ],
            'Formlayout11' => [
                'color1-Formlayout11' => [
                    'img_path' => get_file('form_layouts/Formlayout11/images/form.png'),
                    'color' => '#4F46E5',
                    'theme_name' => 'Formlayout11-v1'
                ],
            ],


        ];
        return $arr;
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
