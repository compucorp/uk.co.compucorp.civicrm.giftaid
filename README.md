# UK GiftAid Extension for CiviCRM

### Install GiftAid Extension
See [Here](http://wiki.civicrm.org/confluence/display/CRMDOC/Extensions "CiviCRM Extensions Installation") for full instructions and information on how to set and configure extensions

### Upgrade GiftAid Extension
These instructions assume that you are only upgrading the Gift Aid module from v2.x to higer and not upgrading from v1.0 and are running version 4.4.x or higher CiviCRM.

1. If you navigate to the extension list in your install, CiviCRM should display a message stating that a newer version of the Gift Aid extension is available for download.

   Alternatively you can download the latest version of the Gift Aid extension from the Compucorp Github: [Download Gift Aid from Git](https://github.com/compucorp/uk.co.compucorp.civicrm.giftaid "GiftAid Github")

2. Once installed you should receive a message bubble indicating that your Gift Aid module code has changed and an upgrade script is required to be run.

   Click the link in the message bubble to run the Gift Aid 2.1 upgrade script.

   The upgrade script will migrate all the previous batch names into an option group called "GiftAid Batch Name" as well as remove the previous registered report and will re-register a new Gift Aid report. (there's no need to do that manually anymore! woohoo!

3. Give yourself a pat on the back... You're all done!

### Configuration

1. Edit the settings for the ‘Gift Aid’ data group here http://www.example.com/civicrm/admin/custom/group?reset=1

2. Click settings and then choose the specific contribution type(s) you want gift aid to be available for i.e “Donation”

3. [3.0] Go to Gift Aid settings page http://www.example.com/civicrm/admin/gift-aid and select the financial types that should be considered as donation or leave it as default to enable Gift Aid for all financial types.

4. [3.0] **[Important]** A new type of Gift Aid declaration is applied to covers both present& future donation and the donations a donor made in the past four years from the declaration date as long as the Gift Aid claim amount does not exceed tax amount. The old 'Yes' declaration is reserved to cover any old declarations donors made. Settings for the declatation options can be modified in the 'UK Taxpayer Options' list here: http://www.example.com/civicrm/admin/options?reset=1

5. [3.0] **[Important]** From Gift Aid 3.0 onwards, a new mechanism is introduced to marking donations from the past 4 years eligible to Gift Aid based on the new declaration type. **Present and future donations will not be affected by this new mechanism.** A scheduled job will be used to handle the 100 past donation records per run. To enable this scheduled job, please go to http://www.example.com/civicrm/admin/job and enable the job 'Make Past Year Submissions'.


### Configure the Gift Aid Profile and disclaimer

You'll need to add a Gift Aid profile to any contribution forms that you want to use to collect "Gift-aid-able" contributions.

1. Go to ‘Administer -> Custom Data and screens -> profiles’ to edit the info text or fields to display so it’s suits your organisation’s needs.

2. Create Online Contribution Page. Go to ‘Contributions -> Manage Contributions Pages’ to add a new contribution page. See here for more details on how to do this:

3. Add gift aid form to Contribution Form. In the profiles section of your contribution page ensure you include ‘Gift Aid’ in the top/bottom of your page. Or you can add the profile to an existing contribution page. Go to Contributions > Manage contribution pages. Select your contribution page > Configure > Include profiles.

   *Note: make sure there is no duplicated in fields in the form you are asking people to complete i.e name and address fields.*

4. Now, when new contributions are made online with Contribution pages using the Gift Aid profile, two things will happen. The system will firstly store that the donation was "eligible for Gift Aid" and secondly the system will check whether that donor has an existing Gift Aid declaration and if not will make a new record against the contact.

Having collected the relevant information about the donation you are now ready to create your batch and submit to HMRC.

### Creating and managing your batch

Having collected contributions with the appropriate Gift Aid profile enabled you can now create a batch to be submitted to HMRC by using GiftAid report. To do this you first need to create a batch. Instructions on how to do this are below

1. To add a contribution to the Gift Aid Batch, from the main menu go to ‘Contributions -> Find Contributions’. When you have a list of your contributions, select the contributions using the checkbox to the left of each one and then select ‘Add to Gift Aid Batch’ from the drop down list and hit Next name your batch and click the “add to batch button” at the bottom of the screen.

2. The module will indicate which contributions are eligible to be placed in a batch. These are contributions where the donor has a valid Gift Aid declaration at the time of the contribution, a valid UK address with postcode and where the contribution is not already in a batch. A valid UK address with postcode needs to be input by the donor at the point of signing up.

3. You can use the 'Process eligible donations manually' function to process eligiblity of donations instantly if some donations are not yet marked as eligible when adding to batch.


Remove contributions from Gift Aid batch - If you need to remove a contribution from a Gift Aid batch, select the contributions, then select “Remove from gift aid batch” and hit ‘Go’. Then you will see the following information summarised:

* The number of contributions selected.

* The number of contributions removed from the batch.

* The number of contributions not yet added to any batch

**Note: contributions submitted to the HMRC should not be removed from a batch**

### Submission to HMRC

Create Report - After successfully creating a batch of contributions eligible for Gift Aid go to “Reports -> All Reports “ and click “new report” which will bring you here

http://www.example.com/civicrm/report/template/list?reset=1

1. Select  UK GiftAid report option from the list and continue.

2. Select the name of the batch you have just created and click preview report.

3. Once you are happy with your report you can save/print it and submit it to the HMRC.

### Changing the basic rate of Tax
From v2.0 of the module you are now able to change the rate of tax that the module uses to calculate the Gift Aid amount reclaimable and stored in the Gift Aid amount field. The percentage used in the calculation is now stored in an option group labeled:

The GiftAid basic rate tax

You can edit this by navigating to administration>>>civigiftaid>>>basicrateoftax link in the admin menu. You can also edit the option value in the group.“Administer” system settings ‘Option groups’.

Changing the rate of tax (shown as a percentage) will not affect past Gift Aid amounts calculated, but only the new amounts calculated when new contributions are added to a batch.

### Support
CiviCRM Extension Page: https://civicrm.org/extensions/gift-aid-extension-uk

Please contact the follow email if you have any question:
<guanhuan@compucorp.co.uk>, <support@compucorp.co.uk>

Paid support for this extension is available, please contact us either via github or at <info@compucorp.co.uk>
