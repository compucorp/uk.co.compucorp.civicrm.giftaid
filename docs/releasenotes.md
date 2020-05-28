## Unrleased yet.

* Add date declaration was given, as well as the start date. (Currently
  defaults to the same.)

* Fix bug when recording a not-eligible declaration and a future
  is-eligible declaration exists.

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


