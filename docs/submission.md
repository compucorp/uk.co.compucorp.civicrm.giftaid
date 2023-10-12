# Submission to HMRC

## Manual method (spreadsheet)

This extension provides a **Gift Aid Report** template which provides all the information required for submitting to HMRC
in the right formats.

- Special characters are removed from First name and Last name.
- Address is formatted into "House name or number" and Postcode.
- Amount fields do not have the currency symbol.

If you don't have the report setup, go to *Administer->CiviReport->Create new report from template* and find the Gift Aid report.

The report can be filtered by batch. Use the batch filter to select and then export to CSV.

## Automatic method (install Gift Aid online extension)

You can install and configure the [Gift Aid online](https://github.com/mattwire/uk.co.vedaconsulting.module.giftaidonline) extension to submit batches automatically.
