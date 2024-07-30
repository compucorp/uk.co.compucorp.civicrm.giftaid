## Information

Releases use the following numbering system:
**{major}.{minor}.{incremental}**

* major: Major refactoring or rewrite - make sure you read and test very carefully!
* minor: Breaking change in some circumstances, or a new feature. Read carefully and make sure you understand the impact of the change.
* incremental: A "safe" change / improvement. Should *always* be safe to upgrade.

* **[BC]**: Items marked with [BC] indicate a breaking change that will require updates to your code if you are using that code in your extension.

## Release 3.6 (2024-07-30)

* If contribution eligible amount is 0 we force contribution eligibility to "No".
* Performance improvements.
* Various internal code improvements (move to API4, remove legacy code etc.).
* Add composer.json.
* Smarty3+ compatibility.
* PHP8 fixes.
* Add upgrade for historical batches.
* Save batch name instead of id in value.

## Release 3.5.3 (2023-07-31)

* [!36](https://lab.civicrm.org/extensions/ukgiftaid/-/merge_requests/36) Add route for contribution tasks so they can be used with searchkit.
* [!38](https://lab.civicrm.org/extensions/ukgiftaid/-/merge_requests/38) Allow inactive financial types to be selected for eligible configuration.
* [!39](https://lab.civicrm.org/extensions/ukgiftaid/-/merge_requests/39) Add Api4 Contribution.UpgradeGiftAid action.

## Release 3.5.2 (2022-09-26)

* Fix [#34](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/34) Report results in fatal error if contribution column is not not selected.

## Release 3.5.1 (2022-05-16)

* Fix [#28](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/28) Fix broken logic in 3.5 with declarations.

## Release 3.5

* Fix GiftAid report fatal error.
* [!28](https://lab.civicrm.org/extensions/ukgiftaid/-/merge_requests/28) Add options to summarise by contact and sort by sort_name.
* Add system check for version if ukgiftaidonline is installed.
* Clear session data when running each test.
* API4 conversion.
* Standardise on lowercase names for custom fields.
* Use shared function to get addresses from gift aid declaration.
* phpunit8 compatibility for tests.

## Release 3.4.9

* Remove broken `GiftAid.Makepastyearsubmissions` scheduled job (the API call was actually removed a year ago).
* Drop support for CiviCRM < 5.35. Add support for CiviCRM 5.44+.

## Release 3.4.8

* Fix exporting when batchID filter is specified via URL.

## Release 3.4.7

* When upgrading check and update customfield datatypes (`eligible_for_gift_aid` was `varchar` instead of `int` on some old installs).
* Don't add contributions with a future "receive date" to batch. HMRC does not allow this!
* Fix getting eligible financial types setting.
* Fix [#19](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/19) Find contribution with batch name bring no result.
* Ensure that we create a GiftAid report instance.
* Allow filtering report via batch_id URL parameter.
* Fix [#17](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/17) Amount totals in Gift Aid Report are for 50 record subset, not total records in Batch.
* Fix [#25](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/25) consider addresses from declarations in the next 4 years.

## Release 3.4.6

* Add `GiftAid.Ensuredatastructures` API.
* [#20](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/20) Add `GiftAid.Getcontributioneligibility` API.
* Fix [#21](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/21) Enlarge source to prevent data too long issues.
* Make sure report totals are calculated correctly if decimal separator is not dot for currency
* Fix [#15](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/15) House name or number is now first line of address per HMRC/Charities Online guidance.

## Release 3.4.5

* Fix [#10](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/10) - issues with Gift Aid profile description and fields.

## Release 3.4.4
**Requires CiviCRM 5.25 minimum or you won't see the batch name anymore in the contribution detail** - *alterCustomFieldDisplayValue hook does not exist.*

* Replace deprecated hook definitions.
* Remove deprecated trapException on executeQuery.
* Switch "Add to Batch" to use API and supported batch params.
* If deleting a batch clean up giftaid batch info so we don't have problems adding them to a new batch.
* Add API to recalculate amounts for contributions already added to a batch (`GiftAid.recalculatecontributionamounts`).
* Fix ukgiftaidonline menu items sometimes not showing: [ukgiftaidonline#1](https://lab.civicrm.org/extensions/ukgiftaidsubmission/-/issues/1).
* Fix searching for batch via reports.
* Improve GiftAid Report for submission to HMRC [!12](https://lab.civicrm.org/extensions/ukgiftaid/-/merge_requests/12)
* Improve address validation for house names.
* Fix get address for report when contribution receive date is a few seconds after declaration but on same day.

## Release 3.4.3

* Fix [#9](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/4) Fix contributions marked not-eligible when line items missing

## Release 3.4.2

* Fix [#4](https://lab.civicrm.org/extensions/ukgiftaid/-/issues/4) - Individual donation marked as "NO" from backend, gets included in batch.
* Remove unused lineitems display from add/remove to batch.
* Add 'View' action to contributions on add/remove batch list.

## Release 3.4.1

* Add GiftAid.Updatedeclarations API
* Use title that user entered when adding to batch
* Disable logging when fixing declarations/contributions via API
* Fix crash with disable then enable extension

## Release 3.4
This release adds unit tests, fixes multiple issues, improves documentation and adds a field "Given Date" to the declaration.

* Update empty address when updating declaration
* Add date declaration was given, as well as the start date. (Currently
  defaults to the same.)
* Fix bug when recording a not-eligible declaration and a future
  is-eligible declaration exists.
* Fix #3 - generate correct link for gift aid declaration tab
* Don't create 'Yes, in past 4 years' optionvalue for contributions on install....
* Fix issues with Contribution customfield metadata in installer
* Trigger postInstall hook which is required on install to set revision so upgrader steps are not run
* Followup re gitlab issue #2 - fix problem with shared field name
* Fix license link
* Update documentation
* Fix gitlab issue #2: cannot reinstall after uninstall
* Add eligibility flowchart (graphviz .dot format source)
* Don't worry about calculating gift aid fields twice
* Add unit tests
* fix issue #24 setDeclaration called with missing eligible_for_gift_aid param
* Identify eligibility by line items not contribution financial type. Fixes issue #19
* Fix install wrongly setting table-level default batch; add first two tests
* Improve code readability and avoid duplicated method calls
* Simplify fetching list of entity_ids

## Release 3.3.11

* Use "Primary" address instead of "Home" address for declarations.
* Remove code to handle multiple charities - it is untested and probably doesn't work anymore and adds complexity to the code.

## Release 3.3.10

* Only display eligible but no declaration message if logged in and has access to civicontribute.
* Use session to track giftaid selections on form so it works with confirmation page.

## Release 3.3.9

* Fix crash on contribution thankyou page when a new contact is created.

## Release 3.3.8

* Refactor GiftAid report to fix multiple issues and show batches with multiple financial types.

## Release 3.3.7

* Allow editing address on the declaration.

## Release 3.3.6

* Rework "Remove from Batch" to improve performance and ensure that what is shown on the screen is what is added to the batch.
* Rework "Add to Batch" task to improve performance and ensure that what is shown on the screen is what is added to the batch.
* Update GiftAid.updateeligiblecontributions API and [document](api.md).

## Release 3.3.5

* Update and refactor how we create/update declarations.
* Added [documentation for declarations](declaration.md) to explain how the declarations are created/updated and what the fields mean.

## Release 3.3.4

* Fix issues with setting "Eligible for gift aid" on contributions.
* Added [documentation for contributions](contributions.md) to explain how the gift aid fields on contributions work.

## Release 3.3.3

* Include first donation in the batch
* Due to the timestamp on the declaration is created after the contribution hence the first donation doesn't gets included in batch. Set the timestamp as the date rather than time.
* Clear batch_name if we created a new contribution in a recur series (it's copied across by default by Contribution.repeattransaction).
* Check and set label for 'Eligible amount' field on contribution.
* Always make sure current declaration is set if we have one - fixes issue with overwriting declaration with 'No'.
* Fix [#5](https://github.com/mattwire/uk.co.compucorp.civicrm.giftaid/issues/5) Donations included in batch although financial types disabled in settings.
* Trigger create of new gift aid declaration from contribution form if required.

## Release 3.3.2

* Handle transitions between the 3 declaration states without losing information - create a new declaration when state is changed.
* Refactor creating/updating declaration when contribution is created/updated.
* Properly escape SQL parameters when updating gift aid declaration.
* Extract code to check if charity column exists.

## Release 3.3.1

* Major performance improvement to "Add to Batch".

## Release 3.3
**In this release we update profiles to use the declaration eligibility field instead of the contribution.
This allows us to create a new declaration (as it will be the user filling in a profile via contribution page etc.)
 and means we don't create a declaration when time a contribution is created / imported with the "eligible" flag set to Yes.**

**IMPORTANT: Make sure you run the extension upgrades (3104).**

* Fix status message on AddToBatch.
* Fix crash on enable/disable extension.
* Fix creating declarations every time we update a contribution.
* Refactor insert/updateDeclaration.
* Refactor loading of optiongroups/values - we load them in the upgrader in PHP meaning that we always ensure they are up to date with the latest extension.
* Add documentation in mkdocs format (just extracted from README for now).
* Make sure we properly handle creating/ending and creating a declaration again (via eg. contribution page).
* Allow for both declaration eligibility and individual contribution eligibility to be different on same profile (add both fields).
* Fix PHP notice in GiftAid report.
* Match on OptionValue value when running upgrader as name is not always consistent.

## Release 3.2
* Be stricter checking eligible_for_gift_aid variable type
* Fix issues with entity definition and regenerate
* Fix PHP notice
* Refactor addtobatch for performance, refactor upgrader for reliability
* Add API to update the eligible_for_gift_aid flag on contributions

## Release 3.1
* Be stricter checking eligible_for_gift_aid variable type


