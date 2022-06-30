# API Functions

## GiftAid

You can use the `limit` and `offset` API3 options to control how many entities these APIs will process each run.

### Update Eligible Contributions
`GiftAid.updateeligiblecontributions` - API to set "Eligible for Gift Aid" on all contributions where
it was not previously set.

This allows to to "fix" any contributions that were created with an older version of the extension.

This will not touch contributions already in a batch.

Note that a contribution's eligibility is based on the financial types of the line items (unless globally enabled) only; it is not based on declaration of the donor: eligibility of a contribution does not mean it is claimable.

#### Parameters
- `contribution_id` (int): Contribution ID: Optional contribution ID to update.
- `recalculate_amount` (bool): Recalculate amounts:
   - If missing/false (default), only calculate amounts (and eligibility) for contributions that do not have eligibility known.
   - If true, recalculate Gift Aid amounts (but not eligibility) for contributions that have the eligible flag set.
- `no_log` (bool): If true, temporarily disable logging while making these changes. If you are running a bulk data correction and only want the logs to show end-user/admin changes, you can turn off logging.

### Recalculate Contribution Amounts
`GiftAid.recalculatecontributionamounts` - API to recalculate gift aid amounts for contributions.

This is also done by updateeligiblecontributions but there are two main differences:
1. The eligibility status is not changed.
2. If you specify a batch name as parameter the amounts will be recalculated for all contributions with that batch name.

- `batch_name` (string): The batch name (note that you see the label in the UI but you need the name which you can find from the `gift_aid_batch_name` option group).
- `no_log` (bool): If true, temporarily disable logging while making these changes. If you are running a bulk data correction and only want the logs to show end-user/admin changes, you can turn off logging.

### Update Declarations
`GiftAid.updatedeclarations` - API to update existing declarations that may be invalid (eg. were imported, missing address etc.).

#### Parameters
- `contact_id` (int/array): Contact IDs to check and update declarations. Use the API3 ['IN' => [123,223]] format for multiple contact IDs.
- `start_date` (string): Declaration start date - if not set defaults to existing date or contact created_date.
- `given_date` (string): Declaration given date - if not set defaults to existing date or start_date or contact created_date.
- `source` (string): Source text if existing declaration source text is empty.
- `no_log` (bool): If true, temporarily disable logging while making these changes. If you are running a bulk data correction and only want the logs to show end-user/admin changes, you can turn off logging.
- `has_start_date` (bool): If true, only update declarations that do have a start_date set. This is useful for updating ones with a start_date differently to ones without (eg. adding different source text).
- `no_start_date` (bool): If true, only update declarations that do not have a start_date set. This is useful for updating ones with a start_date differently to ones without (eg. adding different source text).
- `replace_address` (bool): If true, the address on the declaration will be replaced with the current address. (This is rarely appropriate, but was a previous default behaviour.)
- `reformat_address` (bool): If true, reformat the address on the declaration to current standards.  This is useful if the address on the declaration is not in the right format, possibly because the declaration is old or was entered manually.
- `all` (bool): If true, update all of a contact's declarations.  Default is to only update the first which may or may not be current.

If the address on the declaration is missing, it will be populated with the contact's current address.

### Ensure Data Structures
**WARNING: This will make changes to custom fields, option groups, profiles without asking and may lead to data loss! Always run on a test site first**.

`GiftAid.Ensuredatastructures` - API to validate and update data structures (custom fields, option groups, profiles) required by the GiftAid extension.

This should be done automatically by the extension upgrader but if necessary you can use this API to do so.
