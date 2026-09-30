<?php

return [
    // Set true in production after tenant-specific Microsoft sign-in and
    // Conditional Access MFA have been verified. This fails closed: local
    // password sign-in is then unavailable even if SSO is misconfigured.
    'require_microsoft_sso' => (bool) env('REQUIRE_MICROSOFT_SSO', false),
];
