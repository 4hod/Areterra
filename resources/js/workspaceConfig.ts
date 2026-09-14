export interface WorkspaceItem {
    href: string;
    label: string;
    description: string;
    icon: string;
    cap?: string;
    group: string;
}

export type WorkspaceTone = 'teal' | 'blue' | 'purple' | 'amber' | 'rose' | 'green' | 'slate';

export interface WorkspaceDefinition {
    title: string;
    eyebrow: string;
    description: string;
    icon: string;
    tone: WorkspaceTone;
    items: WorkspaceItem[];
}

export const WORKSPACES: Record<string, WorkspaceDefinition> = {
    people: {
        title: 'People',
        eyebrow: 'Members and support',
        description: 'Member records, reviews, communication and day-to-day support in one workspace.',
        icon: '👥',
        tone: 'teal',
        items: [
            { href: '/members', label: 'Members', description: 'Profiles, support needs, contacts and history', icon: '👥', cap: 'view_members', group: 'Records' },
            { href: '/monitoring', label: 'Daily monitoring', description: 'Record member wellbeing and observations', icon: '📊', cap: 'log_welfare', group: 'Daily support' },
            { href: '/reviews', label: 'Member reviews', description: 'Plan and record structured reviews', icon: '🔄', cap: 'view_members', group: 'Records' },
            { href: '/referrals', label: 'Referrals', description: 'Review and progress new referrals', icon: '📨', cap: 'create_members', group: 'Admissions' },
            { href: '/email', label: 'Email composer', description: 'Write member-related correspondence', icon: '✉️', cap: 'view_member_details', group: 'Communication' },
            { href: '/sar-requests', label: 'SAR requests', description: 'Track subject access requests securely', icon: '🔐', cap: 'edit_members', group: 'Information' },
        ],
    },
    'animal-care': {
        title: 'Animal Care',
        eyebrow: 'Welfare and health',
        description: 'Animal profiles, daily welfare checks, monitoring and veterinary history.',
        icon: '🐾',
        tone: 'green',
        items: [
            { href: '/animals', label: 'Animal records', description: 'Care plans, feeding notes and health history', icon: '🦜', cap: 'view_animals', group: 'Records' },
            { href: '/animals', label: 'Today’s welfare', description: 'Complete and review today’s welfare checks', icon: '✅', cap: 'log_welfare', group: 'Daily care' },
            { href: '/tasks', label: 'Animal care tasks', description: 'See care actions alongside other assigned work', icon: '☑️', group: 'Actions' },
            { href: '/documents', label: 'Care documents', description: 'Open shared animal-care guidance and files', icon: '📁', cap: 'view_documents', group: 'Guidance' },
        ],
    },
    operations: {
        title: 'Operations',
        eyebrow: 'Daily delivery',
        description: 'Run the day from arrival and transport through activities and end-of-day records.',
        icon: '🧭',
        tone: 'blue',
        items: [
            { href: '/transport', label: 'Transport', description: 'Collections, drop-offs and transport outcomes', icon: '🚐', cap: 'log_sessions', group: 'Today' },
            { href: '/register', label: 'Register', description: 'Record attendance and arrival moods', icon: '📋', cap: 'log_sessions', group: 'Today' },
            { href: '/end-of-day', label: 'End of day', description: 'Close sessions with outcomes and notes', icon: '🌙', cap: 'log_sessions', group: 'Today' },
            { href: '/activities', label: 'Activities', description: 'Plan and record member activities', icon: '🎨', cap: 'log_sessions', group: 'Planning' },
            { href: '/weekly-planner', label: 'Weekly planner', description: 'Coordinate the week across the service', icon: '🗓️', group: 'Planning' },
            { href: '/calendar', label: 'Calendar', description: 'View events, leave and operational dates', icon: '📅', group: 'Planning' },
            { href: '/vehicles', label: 'Vehicles', description: 'Vehicle records, checks and defects', icon: '🚚', cap: 'view_vehicles', group: 'Resources' },
            { href: '/maintenance', label: 'Maintenance', description: 'Track premises and equipment work', icon: '🔧', cap: 'manage_operations', group: 'Resources' },
            { href: '/projects', label: 'Projects', description: 'Coordinate operational improvement work', icon: '🗂️', cap: 'manage_operations', group: 'Planning' },
            { href: '/insurance', label: 'Insurance', description: 'Policies, renewals and cover details', icon: '🛡️', cap: 'manage_operations', group: 'Resources' },
        ],
    },
    team: {
        title: 'Team',
        eyebrow: 'Staff workspace',
        description: 'People, shifts, leave, communication, development and recognition.',
        icon: '🤝',
        tone: 'purple',
        items: [
            { href: '/directory', label: 'Directory', description: 'Find staff and contact details', icon: '📖', group: 'People' },
            { href: '/announcements', label: 'Announcements', description: 'Share and read team updates', icon: '📢', group: 'Communication' },
            { href: '/recognition', label: 'Recognition', description: 'Celebrate contributions and achievements', icon: '🌟', group: 'Communication' },
            { href: '/leave', label: 'Leave', description: 'Request and manage staff leave', icon: '🌴', cap: 'request_leave', group: 'Time' },
            { href: '/timeclock', label: 'Time clock', description: 'Clock in, clock out and view timesheets', icon: '⏱️', cap: 'own_timeclock', group: 'Time' },
            { href: '/supervisions', label: 'Supervisions', description: 'Record supervision and appraisal meetings', icon: '🗣️', cap: 'manage_supervisions', group: 'Development' },
            { href: '/payroll', label: 'Payroll', description: 'Prepare and approve payroll periods', icon: '💷', cap: 'manage_payroll', group: 'Time' },
            { href: '/orders', label: 'Orders', description: 'Request and track team purchases', icon: '📦', cap: 'request_products', group: 'Resources' },
        ],
    },
    business: {
        title: 'Business',
        eyebrow: 'Finance and performance',
        description: 'Funding, grants, invoices, purchasing and service reporting.',
        icon: '💼',
        tone: 'amber',
        items: [
            { href: '/finance', label: 'Finance & grants', description: 'Income, costs, grants and financial records', icon: '💰', cap: 'manage_finance', group: 'Finance' },
            { href: '/funding', label: 'Funding', description: 'Track funding arrangements and renewals', icon: '🏦', cap: 'manage_operations', group: 'Finance' },
            { href: '/invoices', label: 'Invoices', description: 'Create and manage invoices', icon: '🧾', cap: 'manage_finance', group: 'Finance' },
            { href: '/orders', label: 'Orders', description: 'Approve, order and receive purchases', icon: '📦', cap: 'request_products', group: 'Purchasing' },
            { href: '/payroll', label: 'Payroll', description: 'Review payroll and staff costs', icon: '💷', cap: 'manage_payroll', group: 'Finance' },
            { href: '/reports', label: 'Reports', description: 'Attendance, activity and operational insight', icon: '📈', cap: 'view_reports', group: 'Performance' },
        ],
    },
    governance: {
        title: 'Governance',
        eyebrow: 'Safety and assurance',
        description: 'Policies, compliance, risks, incidents, safeguarding and audit tools.',
        icon: '⚖️',
        tone: 'rose',
        items: [
            { href: '/policies', label: 'Policies', description: 'Read, review and approve organisational policy', icon: '📜', group: 'Guidance' },
            { href: '/documents', label: 'Documents', description: 'Shared documents and read tracking', icon: '📁', cap: 'view_documents', group: 'Guidance' },
            { href: '/risk-assessments', label: 'Risk assessments', description: 'Assess, control and sign off risks', icon: '⚖️', group: 'Assurance' },
            { href: '/compliance', label: 'Compliance', description: 'Monitor compliance items and renewals', icon: '📋', cap: 'view_all_compliance', group: 'Assurance' },
            { href: '/incidents', label: 'Incidents', description: 'Report and manage incidents', icon: '🚨', cap: 'report_incidents', group: 'Safety' },
            { href: '/safeguarding', label: 'Safeguarding', description: 'Secure safeguarding concern records', icon: '🛡️', cap: 'access_safeguarding', group: 'Safety' },
            { href: '/forms', label: 'Forms', description: 'Build forms and review submissions', icon: '📝', group: 'Information' },
            { href: '/audit', label: 'System audit', description: 'Review data quality and system health', icon: '🩺', cap: 'view_reports', group: 'Audit' },
            { href: '/audit-log', label: 'Audit log', description: 'Trace changes across the hub', icon: '🧾', cap: 'view_audit_log', group: 'Audit' },
            { href: '/settings', label: 'Hub settings', description: 'Branding, integrations and configuration', icon: '⚙️', cap: 'manage_settings', group: 'Administration' },
            { href: '/settings/permissions', label: 'Permissions', description: 'Control individual staff access', icon: '🔑', cap: 'manage_settings', group: 'Administration' },
            { href: '/import', label: 'CSV import', description: 'Bring structured records into the hub', icon: '📥', cap: 'manage_settings', group: 'Administration' },
        ],
    },
};

