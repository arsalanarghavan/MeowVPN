# L2TP production SSH checklist — v24

Beyond CI mock [`L2tpProvisionerSshMockTest`](../../backend/tests/Feature/L2tp/L2tpProvisionerSshMockTest.php):

## Preconditions

- `SVP_MODULE_L2TP=true`
- Staging server with SSH key access
- `l2tp_add` mutate from dashboard tab `l2tp_servers`

## Steps

1. Add server via UI — verify row in `svp_l2tp_servers`
2. SSH to host — confirm chap-secrets / ipsec entry (per provisioner)
3. Bot flow — L2TP menu visible when module on
4. `l2tp_delete` — confirm cleanup on host

## Evidence

`docs/evidence/l2tp-production-YYYY-MM-DD.log`

Operator / date: 2026-06-13
