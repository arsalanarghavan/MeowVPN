#!/usr/bin/env bash
# v28: §7.1 session paths + canonical /admin/* after normalizeAdminApiPath.
set -euo pipefail
REPO="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$REPO/frontend"

node --input-type=module -e "
import { normalizeAdminApiPath } from './src/lib/api-base.ts';

const cases = [
  ['/dashboard/admin/state', '/admin/state'],
  ['/dashboard/admin/mutate', '/admin/mutate'],
  ['/dashboard/admin/backup/status', '/admin/backup/status'],
  ['/dashboard/persona', '/dashboard/persona'],
  ['/dashboard/ui-preferences', '/dashboard/ui-preferences'],
  ['/dashboard/impersonate/start', '/dashboard/impersonate/start'],
  ['/dashboard/impersonate/stop', '/dashboard/impersonate/stop'],
  ['/admin/state', '/admin/state'],
  ['/admin/mutate', '/admin/mutate'],
];

for (const [input, expected] of cases) {
  const got = normalizeAdminApiPath(input);
  if (got !== expected) {
    console.error('FAIL normalizeAdminApiPath(' + JSON.stringify(input) + ') => ' + JSON.stringify(got) + ' expected ' + JSON.stringify(expected));
    process.exit(1);
  }
}
console.log('§7.1 path parity OK (' + cases.length + ' cases)');
"
