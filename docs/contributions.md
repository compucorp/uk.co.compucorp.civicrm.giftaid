# Contributions and eligibility

Contributions have the following Gift Aid fields.

## Eligible for Gift Aid?

If the value is set on the back-end form (eg. via Add contribution) then that
value will be used.

Otherwise it is set automatically based on the enabled financial types.

This means it will always get set to Yes or No but can be optionally overridden via the UI.

## Eligible Amount

This is the amount of the contribution that is eligible for gift-aid. If there
are no financial types that are eligible for the contribution the eligible
amount will be 0 unless Gift Aid eligibility is set globally.

## Gift Aid Amount

This is the actual gift aid amount of the eligible amount, i.e. it would be 25p
for a £1 contribution that was eligible (while tax is 20%).

## Batch Name

This is set automatically when the contribution is added to a batch.

## See also:

[GiftAid.updateeligiblecontributions](api.md) - API to set "Eligible for Gift Aid" on all contributions where it was not previously set.
