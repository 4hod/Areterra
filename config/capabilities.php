<?php

// Capabilities are held per user, in the `user_capabilities` table.
//
// The role lists below are PRESETS only — a starting point when setting someone
// up, not a live binding. Once applied, a person's permissions are their own and
// editing a preset here does not retroactively change anyone. What the tick
// boxes on their profile say is exactly what they can do.
//
// `catalogue` is the full list of capabilities with plain-English labels; it is
// what the permissions screen renders and what the gates are defined from.

$staff = [
    'access_hub',
    'view_members',
    'view_member_details',
    'view_animals',
    'view_documents',
    'log_welfare',
    'log_sessions',
    'request_leave',
    'own_timeclock',
    'request_products',
    'report_incidents',
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
    'manage_operations',
    'view_all_compliance',
    'manage_directory',
    'view_audit_log',
    'post_announcements',
    'manage_payroll',
    'manage_supervisions',
    'manage_policies',
    'manage_finance',
    'manage_orders',
]);

$catalogue = [
    'Access' => [
        'access_hub' => 'Sign in to the Hub',
        'manage_settings' => 'Change Hub settings and manage permissions',
        'manage_directory' => 'Maintain the staff directory',
        'view_audit_log' => 'View the audit log',
    ],
    'Members' => [
        'view_members' => 'See the member list',
        'view_member_details' => 'See personal details, medical notes and GP information',
        'create_members' => 'Add new members and review referrals',
        'edit_members' => 'Edit member records, goals and reviews',
        'delete_members' => 'Archive and delete member records',
    ],
    'Daily work' => [
        'log_sessions' => 'Take the register, record transport and end-of-day notes',
        'log_welfare' => 'Record welfare checks, monitoring and vet visits',
        'view_animals' => 'See animal records',
        'edit_animals' => 'Add and edit animal records',
    ],
    'Safeguarding and incidents' => [
        'access_safeguarding' => 'Open safeguarding concerns (password re-entry required)',
        'report_incidents' => 'Report an incident',
        'manage_incidents' => 'Investigate and close incidents',
        'view_all_incidents' => 'See all incidents, not just your own',
    ],
    'Staff' => [
        'request_leave' => 'Request leave',
        'approve_leave' => 'Approve or decline leave',
        'view_all_leave' => 'See everyone\'s leave',
        'own_timeclock' => 'Clock in and out',
        'view_all_timeclock' => 'See everyone\'s timesheets',
        'edit_timeclock' => 'Correct timesheet entries',
        'manage_supervisions' => 'Record supervisions and appraisals',
        'post_announcements' => 'Post announcements',
    ],
    'Money' => [
        'manage_finance' => 'Grants, invoices, income, costs and ledger corrections',
        'manage_payroll' => 'Run and approve payroll',
        'request_products' => 'Request items to be ordered',
        'manage_orders' => 'Approve, order and receive purchases',
    ],
    'Compliance and governance' => [
        'manage_compliance' => 'Manage compliance items and risk assessments',
        'view_all_compliance' => 'See the whole compliance picture',
        'manage_policies' => 'Write and approve policies',
        'view_documents' => 'Read shared documents',
        'upload_documents' => 'Upload shared documents',
        'build_forms' => 'Build and edit forms',
    ],
    'Operations' => [
        'manage_operations' => 'Maintenance, projects, funding and insurance',
        'view_vehicles' => 'See vehicles and report defects',
        'manage_vehicles' => 'Manage vehicles and resolve defects',
    ],
    'Reporting' => [
        'view_reports' => 'View reports',
        'export_reports' => 'Export reports as CSV',
    ],
];

// Every capability the system knows about, flattened.
$all = array_merge(...array_map('array_keys', array_values($catalogue)));

return [
    'catalogue' => $catalogue,
    'all' => $all,

    // Presets offered on the permissions screen. Applying one ticks its boxes;
    // you are then free to tick or untick anything else.
    'roles' => [
        'administrator' => $all,
        'manager' => $manager,
        'staff' => $staff,
        'volunteer' => [
            'access_hub',
            'view_members',
            'view_animals',
            'view_documents',
            'log_welfare',
            'request_leave',
            'own_timeclock',
        ],
        'safeguarding_lead' => array_merge($staff, ['access_safeguarding']),
    ],
];
