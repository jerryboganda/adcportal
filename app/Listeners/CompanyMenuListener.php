<?php

namespace App\Listeners;

use App\Events\CompanyMenuEvent;

class CompanyMenuListener
{
    /**
     * Radiology clinic navigation.
     */
    public function handle(CompanyMenuEvent $event): void
    {
        $module = 'Base';
        $menu = $event->menu;

        // ---------- Base ----------
        $menu->add([
            'title' => __('Dashboard'),
            'icon' => 'home',
            'name' => 'dashboard',
            'parent' => null,
            'order' => 1,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => '',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Study Dashboard'),
            'icon' => '',
            'name' => 'appointment-dashboard',
            'parent' => 'dashboard',
            'order' => 5,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'appointment.dashboard',
            'module' => $module,
            'permission' => '',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Overview Dashboard'),
            'icon' => '',
            'name' => 'home',
            'parent' => 'dashboard',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'dashboard',
            'module' => $module,
            'permission' => '',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('User Management'),
            'icon' => 'users',
            'name' => 'user-management',
            'parent' => null,
            'order' => 50,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'user manage',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('User'),
            'icon' => '',
            'name' => 'user',
            'parent' => 'user-management',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'users.index',
            'module' => $module,
            'permission' => 'user manage',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Role'),
            'icon' => '',
            'name' => 'role',
            'parent' => 'user-management',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'roles.index',
            'module' => $module,
            'permission' => 'roles manage',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Clinic'),
            'icon' => 'credit-card',
            'name' => 'business',
            'parent' => null,
            'order' => 100,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'clinic manage',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Clinic Settings'),
            'icon' => '',
            'name' => 'clinic-settings',
            'parent' => 'business',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'business.manage',
            'module' => $module,
            'permission' => 'clinic edit',
            'group' => 'base'
        ]);

        // ---------- Radiology workflow ----------
        $menu->add([
            'title' => __('Check-in Desk'),
            'icon' => 'login custom-icon appointments',
            'name' => 'study-checkin',
            'parent' => null,
            'order' => 120,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'study.checkin',
            'module' => $module,
            'permission' => 'study checkin',
            'group' => 'radiology workflow'
        ]);
        $menu->add([
            'title' => __('Technologist Worklist'),
            'icon' => 'scan',
            'name' => 'study-technologist',
            'parent' => null,
            'order' => 130,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'study.technologist',
            'module' => $module,
            'permission' => 'study acquire',
            'group' => 'radiology workflow'
        ]);
        $menu->add([
            'title' => __('Reading Worklist'),
            'icon' => 'file-text',
            'name' => 'reports-worklist',
            'parent' => null,
            'order' => 140,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'reports.worklist',
            'module' => $module,
            'permission' => 'report manage',
            'group' => 'radiology workflow'
        ]);

        // ---------- Studies & patients ----------
        $menu->add([
            'title' => __('Studies'),
            'icon' => 'credit-card custom-icon appointments',
            'name' => 'appointments',
            'parent' => null,
            'order' => 170,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'appointment.index',
            'module' => $module,
            'permission' => 'appointment manage',
            'group' => 'studies'
        ]);
        $menu->add([
            'title' => __('Study Calendar'),
            'icon' => 'calendar custom-icon calender',
            'name' => 'appointment-calendar',
            'parent' => null,
            'order' => 180,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'appointment.calendar',
            'module' => $module,
            'permission' => 'appointment manage',
            'group' => 'studies'
        ]);
        $menu->add([
            'title' => __('Patients'),
            'icon' => 'user',
            'name' => 'customers',
            'parent' => null,
            'order' => 150,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'customer.index',
            'module' => $module,
            'permission' => 'customer manage',
            'group' => 'studies'
        ]);
        $menu->add([
            'title' => __('Referring Doctors'),
            'icon' => 'stethoscope',
            'name' => 'referrers',
            'parent' => null,
            'order' => 190,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'referrer.index',
            'module' => $module,
            'permission' => 'referrer manage',
            'group' => 'studies'
        ]);

        // ---------- Billing ----------
        $menu->add([
            'title' => __('Invoices'),
            'icon' => 'receipt',
            'name' => 'invoices',
            'parent' => null,
            'order' => 220,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'invoices.index',
            'module' => $module,
            'permission' => 'invoice manage',
            'group' => 'billing'
        ]);

        // ---------- Masters ----------
        $menu->add([
            'title' => __('Modalities & Rooms'),
            'icon' => 'box',
            'name' => 'modalities',
            'parent' => null,
            'order' => 300,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'modality.index',
            'module' => $module,
            'permission' => 'modality manage',
            'group' => 'masters'
        ]);
        $menu->add([
            'title' => __('Procedures'),
            'icon' => 'list-details',
            'name' => 'procedures',
            'parent' => null,
            'order' => 310,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'service.index',
            'module' => $module,
            'permission' => 'service create',
            'group' => 'masters'
        ]);
        $menu->add([
            'title' => __('Safety Screening'),
            'icon' => 'shield-check',
            'name' => 'screening-forms',
            'parent' => null,
            'order' => 320,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'screening-forms.index',
            'module' => $module,
            'permission' => 'report template manage',
            'group' => 'masters'
        ]);
        $menu->add([
            'title' => __('Report Templates'),
            'icon' => 'template',
            'name' => 'report-templates',
            'parent' => null,
            'order' => 330,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'report-templates.index',
            'module' => $module,
            'permission' => 'report template manage',
            'group' => 'masters'
        ]);

        // ---------- Others ----------
        $menu->add([
            'title' => __('Contacts'),
            'icon' => 'phone',
            'name' => 'contacts',
            'parent' => null,
            'order' => 270,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'contacts.index',
            'module' => $module,
            'permission' => 'contact manage',
            'group' => 'others'
        ]);
        $menu->add([
            'title' => __('Settings'),
            'icon' => 'settings',
            'name' => 'settings',
            'parent' => null,
            'order' => 2000,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'setting manage',
            'group' => 'others'
        ]);
        $menu->add([
            'title' => __('System Settings'),
            'icon' => '',
            'name' => 'system-settings',
            'parent' => 'settings',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'settings.index',
            'module' => $module,
            'permission' => 'setting manage',
            'group' => 'others'
        ]);
    }
}
