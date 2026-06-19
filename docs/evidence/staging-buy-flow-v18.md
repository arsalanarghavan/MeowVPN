# Staging buy flow v18 — production sign-off

| Step | Result |
|------|--------|
| User selects plan in bot | OK |
| Receipt upload | OK |
| Admin approve + deliver | OK — `BuyFlowApproveDeliverTest` + prod smoke |
| Service active on panel | OK |

Evidence: manual signoff 2026-06-12 + PHPUnit `GroupAcceptanceV18Test::test_group_f_receipt_approve`

Operator / date: 2026-06-12 (production v18)
