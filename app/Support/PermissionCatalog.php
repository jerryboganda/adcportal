<?php

namespace App\Support;

use App\Models\Permission;

/**
 * The single source of truth for the tenant permission taxonomy.
 *
 * Permission names are a STABLE SECURITY CONTRACT — never rename an entry
 * (enforcement strings are checked verbatim by controllers). Only add new
 * entries. Human-readable labels/groups live here, decoupled from the keys.
 *
 * The catalog is also the authority for the default role bundles shipped to
 * every tenant (TenantBootstrap for new tenants, the RBAC migration for
 * existing ones), so both paths stay byte-identical.
 */
final class PermissionCatalog
{
    /** Roles the platform provisions for every tenant (system roles). */
    public const SYSTEM_ROLES = ['admin', 'radiologist', 'technician', 'receptionist', 'billing'];

    /** Roles that may never be deleted and keep the lockout floor on save. */
    public const UNDELETABLE_ROLES = ['admin'];

    /**
     * Permissions the admin role can never lose, so a tenant administrator
     * cannot lock themselves (or the RBAC screens) out of the system.
     */
    public const ADMIN_LOCKOUT_FLOOR = [
        'role view', 'role manage', 'user manage', 'setting manage', 'user logs history',
    ];

    /** UI display order of permission groups. */
    public const GROUP_ORDER = [
        'Modules',
        'Scheduling',
        'Patients',
        'Study Workflow',
        'Reporting',
        'Billing',
        'Catalog & Forms',
        'Administration',
        'Access Control',
    ];

    /**
     * @return array<string, array{label: string, group: string, dangerous: bool, implies: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            // ==================== Modules (nav-level toggles) ====================
            'reception view' => ['label' => 'Reception Desk module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],
            'technologist view' => ['label' => 'Technologist Worklist module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],
            'reports view' => ['label' => 'Radiology Reports module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],
            'billing view' => ['label' => 'Billing & POS module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],
            'queue view' => ['label' => 'Live Queue TV module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],
            'inventory view' => ['label' => 'Consumables & Contrast module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],
            'catalog view' => ['label' => 'Catalog & Forms module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],
            'doctors view' => ['label' => 'Doctor Network module', 'group' => 'Modules', 'dangerous' => false, 'implies' => []],

            // ==================== Scheduling ====================
            'appointment manage' => ['label' => 'View study list & schedule', 'group' => 'Scheduling', 'dangerous' => false, 'implies' => []],
            'appointment create' => ['label' => 'Book studies', 'group' => 'Scheduling', 'dangerous' => false, 'implies' => ['appointment manage']],
            'appointment edit' => ['label' => 'Edit & reschedule studies', 'group' => 'Scheduling', 'dangerous' => false, 'implies' => ['appointment manage']],
            'appointment delete' => ['label' => 'Delete studies', 'group' => 'Scheduling', 'dangerous' => true, 'implies' => ['appointment manage']],

            // ==================== Patients ====================
            'customer manage' => ['label' => 'View patients', 'group' => 'Patients', 'dangerous' => false, 'implies' => []],
            'customer create' => ['label' => 'Register patients', 'group' => 'Patients', 'dangerous' => false, 'implies' => ['customer manage']],
            'customer edit' => ['label' => 'Edit patient demographics', 'group' => 'Patients', 'dangerous' => false, 'implies' => ['customer manage']],
            'customer delete' => ['label' => 'Delete patients', 'group' => 'Patients', 'dangerous' => true, 'implies' => ['customer manage']],

            // ==================== Study workflow ====================
            'study checkin' => ['label' => 'Check-in, call & no-show studies', 'group' => 'Study Workflow', 'dangerous' => false, 'implies' => []],
            'study screen' => ['label' => 'Perform safety screening', 'group' => 'Study Workflow', 'dangerous' => false, 'implies' => []],
            'study acquire' => ['label' => 'Acquire images & log dose', 'group' => 'Study Workflow', 'dangerous' => false, 'implies' => []],
            'study assign' => ['label' => 'Assign radiologist to studies', 'group' => 'Study Workflow', 'dangerous' => false, 'implies' => ['appointment manage']],
            'study cancel' => ['label' => 'Cancel studies', 'group' => 'Study Workflow', 'dangerous' => true, 'implies' => ['appointment manage']],

            // ==================== Reporting ====================
            'report manage' => ['label' => 'View reports & download PDF', 'group' => 'Reporting', 'dangerous' => false, 'implies' => []],
            'report create' => ['label' => 'Create reports', 'group' => 'Reporting', 'dangerous' => false, 'implies' => ['report manage']],
            'report edit' => ['label' => 'Edit reports', 'group' => 'Reporting', 'dangerous' => false, 'implies' => ['report manage']],
            'report sign' => ['label' => 'Sign / finalize reports', 'group' => 'Reporting', 'dangerous' => false, 'implies' => ['report edit']],
            'report release' => ['label' => 'Release reports to patients & referrers', 'group' => 'Reporting', 'dangerous' => false, 'implies' => ['report manage']],

            // ==================== Billing ====================
            'invoice manage' => ['label' => 'View invoices', 'group' => 'Billing', 'dangerous' => false, 'implies' => []],
            'invoice create' => ['label' => 'Create invoices', 'group' => 'Billing', 'dangerous' => false, 'implies' => ['invoice manage']],
            'invoice edit' => ['label' => 'Edit invoices & add line items', 'group' => 'Billing', 'dangerous' => false, 'implies' => ['invoice manage']],
            'invoice payment' => ['label' => 'Collect payments', 'group' => 'Billing', 'dangerous' => false, 'implies' => ['invoice manage']],
            'invoice delete' => ['label' => 'Void invoices', 'group' => 'Billing', 'dangerous' => true, 'implies' => ['invoice manage']],
            'payment method manage' => ['label' => 'View payment methods', 'group' => 'Billing', 'dangerous' => false, 'implies' => []],
            'payment method create' => ['label' => 'Create payment methods', 'group' => 'Billing', 'dangerous' => false, 'implies' => ['payment method manage']],
            'payment method edit' => ['label' => 'Edit payment methods', 'group' => 'Billing', 'dangerous' => false, 'implies' => ['payment method manage']],
            'payment method delete' => ['label' => 'Delete payment methods', 'group' => 'Billing', 'dangerous' => true, 'implies' => ['payment method manage']],

            // ==================== Catalog & forms ====================
            'modality manage' => ['label' => 'View modalities', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => []],
            'modality create' => ['label' => 'Create modalities', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['modality manage']],
            'modality edit' => ['label' => 'Edit modalities', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['modality manage']],
            'modality delete' => ['label' => 'Delete modalities', 'group' => 'Catalog & Forms', 'dangerous' => true, 'implies' => ['modality manage']],
            'room manage' => ['label' => 'View imaging rooms', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => []],
            'room create' => ['label' => 'Create imaging rooms', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['room manage']],
            'room edit' => ['label' => 'Edit imaging rooms', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['room manage']],
            'room delete' => ['label' => 'Delete imaging rooms', 'group' => 'Catalog & Forms', 'dangerous' => true, 'implies' => ['room manage']],
            'service create' => ['label' => 'Create services', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => []],
            'service edit' => ['label' => 'Edit services', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => []],
            'service delete' => ['label' => 'Delete services', 'group' => 'Catalog & Forms', 'dangerous' => true, 'implies' => []],
            'referrer manage' => ['label' => 'View referrers', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => []],
            'referrer create' => ['label' => 'Create referrers', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['referrer manage']],
            'referrer edit' => ['label' => 'Edit referrers', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['referrer manage']],
            'referrer delete' => ['label' => 'Delete referrers', 'group' => 'Catalog & Forms', 'dangerous' => true, 'implies' => ['referrer manage']],
            'report template manage' => ['label' => 'View report templates', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => []],
            'report template create' => ['label' => 'Create report templates', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['report template manage']],
            'report template edit' => ['label' => 'Edit report templates', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['report template manage']],
            'report template delete' => ['label' => 'Delete report templates', 'group' => 'Catalog & Forms', 'dangerous' => true, 'implies' => ['report template manage']],
            'clinic manage' => ['label' => 'View clinic profile', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => []],
            'clinic edit' => ['label' => 'Edit clinic profile', 'group' => 'Catalog & Forms', 'dangerous' => false, 'implies' => ['clinic manage']],

            // ==================== Administration ====================
            'user manage' => ['label' => 'View staff users', 'group' => 'Administration', 'dangerous' => false, 'implies' => []],
            'user create' => ['label' => 'Create staff users', 'group' => 'Administration', 'dangerous' => false, 'implies' => ['user manage']],
            'user edit' => ['label' => 'Edit staff users', 'group' => 'Administration', 'dangerous' => false, 'implies' => ['user manage']],
            'user delete' => ['label' => 'Delete staff users', 'group' => 'Administration', 'dangerous' => true, 'implies' => ['user manage']],
            'setting manage' => ['label' => 'Manage clinic settings, PACS & gateways', 'group' => 'Administration', 'dangerous' => false, 'implies' => []],
            'user logs history' => ['label' => 'View audit logs', 'group' => 'Administration', 'dangerous' => false, 'implies' => []],

            // ==================== Access control ====================
            'role view' => ['label' => 'View roles & permission matrix', 'group' => 'Access Control', 'dangerous' => false, 'implies' => []],
            'role manage' => ['label' => 'Manage roles, permissions & overrides', 'group' => 'Access Control', 'dangerous' => true, 'implies' => ['role view']],
        ];
    }

    /** @return list<string> every catalog permission name */
    public static function names(): array
    {
        return array_keys(self::definitions());
    }

    public static function isValid(string $name): bool
    {
        return array_key_exists($name, self::definitions());
    }

    public static function definition(string $name): ?array
    {
        return self::definitions()[$name] ?? null;
    }

    /**
     * Complete a permission set with its dependencies (granting `report sign`
     * pulls in `report edit` + `report manage`). Unknown names are rejected.
     *
     * @param  list<string>  $names
     * @return list<string> sorted, de-duplicated, dependency-complete set
     */
    public static function expand(array $names): array
    {
        $resolved = [];
        $stack = $names;
        while ($stack !== []) {
            $name = array_pop($stack);
            if (! self::isValid($name) || isset($resolved[$name])) {
                continue;
            }
            $resolved[$name] = true;
            foreach (self::definition($name)['implies'] as $parent) {
                $stack[] = $parent;
            }
        }

        $names = array_keys($resolved);
        sort($names, SORT_STRING);

        return $names;
    }

    /**
     * Default permission bundles for the five system roles. The admin bundle
     * is the full catalog. These lists are the contract for new tenants
     * (TenantBootstrap) AND the backfill for existing tenants (migration) —
     * they intentionally reproduce the pre-RBAC behavior exactly.
     *
     * @return array<string, list<string>>
     */
    public static function defaultBundles(): array
    {
        return [
            'admin' => self::names(),
            'radiologist' => [
                'reports view', 'queue view',
                'appointment manage', 'report manage', 'report create', 'report edit', 'report sign', 'report release',
            ],
            'technician' => [
                'technologist view', 'queue view',
                'appointment manage', 'study checkin', 'study screen', 'study acquire', 'report manage',
            ],
            'receptionist' => [
                'reception view', 'billing view', 'queue view',
                'appointment manage', 'appointment create', 'appointment edit',
                'study checkin', 'study screen', 'study cancel',
                'customer manage', 'customer create', 'customer edit',
                'referrer manage', 'referrer create', 'referrer edit',
                'invoice create', 'invoice payment', 'report release',
            ],
            'billing' => [
                'billing view', 'queue view',
                'invoice manage', 'invoice create', 'invoice edit', 'invoice delete', 'invoice payment',
                'customer manage', 'customer create', 'customer edit',
            ],
        ];
    }

    /**
     * Sync the `permissions` table with this catalog: create missing rows and
     * refresh labels/groups. Idempotent — safe to run on every deploy.
     *
     * Soft-deleted rows (Permission uses SoftDeletes) are RESTORED rather
     * than re-created: the unique `name` index spans trashed rows, so a
     * create would collide with any previously retired permission.
     */
    public static function sync(): void
    {
        foreach (self::definitions() as $name => $meta) {
            $permission = Permission::withTrashed()->where('name', $name)->first();

            if (! $permission) {
                Permission::create([
                    'name' => $name,
                    'display_name' => $meta['label'],
                    'guard_name' => 'web',
                    'module' => $meta['group'],
                ]);

                continue;
            }

            if ($permission->trashed()) {
                $permission->restore();
            }

            if ($permission->display_name !== $meta['label'] || $permission->module !== $meta['group']) {
                $permission->forceFill([
                    'display_name' => $meta['label'],
                    'module' => $meta['group'],
                ])->saveQuietly();
            }
        }
    }
}
