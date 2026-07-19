<?php

// Role → capability matrix. administrator is resolved via Gate::before and
// implicitly holds every capability, so it is not listed here.

$staff = [
    'access_hub',
    'view_members',
    'view_member_details',
    'view_animals',
    'log_welfare',
    'log_sessions',
    'request_leave',
    'own_timeclock',
];

$manager = array_merge($staff, [
    'edit_members',
    'create_members',
    'delete_members',
    'edit_animals',
    'approve_leave',
    'view_all_leave',
    'view_all_timeclock',
    'edit_timeclock',
    'manage_incidents',
    'view_all_incidents',
    'build_forms',
    'view_reports',
    'export_reports',
    'manage_vehicles',
    'view_vehicles',
    'upload_documents',
    'manage_compliance',
    'view_all_compliance',
    'manage_directory',
    'view_audit_log',
]);

return [
    'roles' => [
        'administrator' => [], // full access via Gate::before
        'manager' => $manager,
        'staff' => $staff,
        'volunteer' => [
            'access_hub',
            'view_members',
            'view_animals',
            'log_welfare',
            'request_leave',
            'own_timeclock',
        ],
        'safeguarding_lead' => array_merge($staff, ['access_safeguarding']),
    ],
];
