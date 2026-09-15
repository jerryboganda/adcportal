<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Presentation-only white-label overrides for one tenant. Nothing here is
 * authorization-relevant: the branding is looked up *after* the server has
 * resolved the tenant from the authenticated principal.
 */
class TenantBranding extends Model
{
    protected $table = 'tenant_branding';

    protected $fillable = [
        'business_id',
        'app_name',
        'primary_color',
        'accent_color',
        'logo_url',
        'favicon_url',
        'login_message',
        'report_header',
        'report_footer',
        'email_from_name',
        'email_from_address',
        'support_email',
        'support_phone',
        'updated_by',
    ];

    public function business()
    {
        return $this->belongsTo(Business::class);
    }
}
