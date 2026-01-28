<?php

namespace App\Listeners;

use App\Events\CompanyMenuEvent;

class CompanyMenuListener
{
    /**
     * Handle the event.
     */
    public function handle(CompanyMenuEvent $event): void
    {
        $module = 'Base';
        $menu = $event->menu;
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
            'title' => __('Appointment Dashboard'),
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
            'title' => __('Business'),
            'icon' => 'credit-card',
            'name' => 'business',
            'parent' => null,
            'order' => 100,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'business manage',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Create Business'),
            'icon' => '',
            'name' => 'role',
            'parent' => 'business',
            'order' => 10,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => '',
            'module' => $module,
            'permission' => 'business create',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Edit Business'),
            'icon' => '',
            'name' => 'role',
            'parent' => 'business',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'manage.business',
            'module' => $module,
            'permission' => 'business update',
            'group' => 'base'
        ]);
        $menu->add([
            'title' => __('Businesses'),
            'icon' => '',
            'name' => 'role',
            'parent' => 'business',
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'business.index',
            'module' => $module,
            'permission' => 'business manage',
            'group' => 'base'
        ]);

        $menu->add([
            'title' => __('Customers'),
            'icon' => 'user',
            'name' => 'customers',
            'parent' => null,
            'order' => 150,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'customer.index',
            'module' => $module,
            'permission' => 'customer manage',
            'group' => 'base'
        ]);
        if(in_array('TeamBooking',$event->menu->modules))
        {
            $menu->add([
                'title' => __('Appointments'),
                'icon' => 'credit-card custom-icon appointments',
                'name' => 'appointments',
                'parent' => null,
                'order' => 170,
                'ignore_if' => [],
                'depend_on' => [],
                'route' => 'appointments.index',
                'module' => $module,
                'permission' => 'appointment manage',
                'group' => 'appointments'
            ]);
        }else{
            $menu->add([
                'title' => __('Appointments'),
                'icon' => 'credit-card custom-icon appointments',
                'name' => 'appointments',
                'parent' => null,
                'order' => 170,
                'ignore_if' => [],
                'depend_on' => [],
                'route' => 'appointment.index',
                'module' => $module,
                'permission' => 'appointment manage',
                'group' => 'appointments'
            ]);
        }
        $menu->add([
            'title' => __('Appointment Calendar'),
            'icon' => 'calendar custom-icon calender',
            'name' => 'appointment-calendar',
            'parent' => null,
            'order' => 200,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'appointment.calendar',
            'module' => $module,
            'permission' => 'appointment manage',
            'group' => 'appointments'
        ]);
        $menu->add([
            'title' => __('Custom Status'),
            'icon' => 'tag',
            'name' => 'custom-status',
            'parent' => null,
            'order' => 210,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'custom-status.index',
            'module' => $module,
            'permission' => 'status manage',
            'group' => 'appointments'
        ]);
        $menu->add([
            'title' => __('Referring Doctors'),
            'icon' => 'stethoscope',
            'name' => 'referrers',
            'parent' => null,
            'order' => 215,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'referrer.index',
            'module' => $module,
            'permission' => 'referrer manage',
            'group' => 'appointments'
        ]);
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
            'group' => 'contacts & reports'
        ]);
        $menu->add([
            'title' => __('Subscribers'),
            'icon' => 'mail',
            'name' => 'subscribers',
            'parent' => null,
            'order' => 280,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'subscribes.index',
            'module' => $module,
            'permission' => 'subscriber manage',
            'group' => 'contacts & reports'
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
        $menu->add([
            'title' => __('Setup Subscription Plan'),
            'icon' => '',
            'name' => 'setup-subscription-plan',
            'parent' => 'settings',
            'order' => 20,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'plans.index',
            'module' => $module,
            'permission' => 'plan manage',
            'group' => 'others'
        ]);
        $menu->add([
            'title' => __('Order'),
            'icon' => '',
            'name' => 'order',
            'parent' => 'settings',
            'order' => 30,
            'ignore_if' => [],
            'depend_on' => [],
            'route' => 'plan.order.index',
            'module' => $module,
            'permission' => 'plan orders',
            'group' => 'others'
        ]);
    }
}
