# Frontend fetch audit v27

Date: 2026-06-19
Warnings: 0 (threshold: 0)

All admin fetch paths use normalizeAdminApiPath helpers.
§7.1 session paths keep /dashboard/ prefix (persona, ui-preferences, impersonate).
X-WP-Nonce: absent from frontend/src
wp-json / admin-ajax: absent from frontend/src
