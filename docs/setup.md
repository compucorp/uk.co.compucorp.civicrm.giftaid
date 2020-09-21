# Setup and Configuration
## Configuration

1. Edit the settings for the ‘Gift Aid’ custom data set via **CiviCRM » Administer » Customise Data and Screens » Custom Fields**

2. Click settings and then choose the specific contribution type(s) you want gift aid to be available for i.e “Donation”

3. [3.1] Go to Gift Aid settings page via **Administer » CiviContribute » GiftAid » Settings** and select the financial types that should be considered as donation or leave it as default to enable Gift Aid for all financial types.

4. [3.0] **[Important]** A new type of Gift Aid declaration is applied to cover both present and future donations, and the donations a donor made in the four years previous to the declaration date as long as the Gift Aid claim amount does not exceed tax amount. The old 'Yes' declaration is reserved to cover any old declarations donors made. Settings for the declatation options can be modified in the 'UK Taxpayer Options' option groups listed under GiftlAid (**Administer » System Settings » Option Groups**)

5. [3.0] **[Important]** From Gift Aid 3.0 onwards, a new mechanism is introduced to marking donations from the past 4 years eligible to Gift Aid based on the new declaration type. **Present and future donations will not be affected by this new mechanism.** A scheduled job will be used to handle the 100 past donation records per run. To enable this scheduled job, please go to **CiviCRM » Administer » System Settings » Scheduled Jobs** and enable the job 'Process Gift Aid eligible donations'.

6. [3.1] Gift Aid amount is calculated automatically when the contribution is created / updated.

## Configure the Gift Aid Profile and disclaimer

You'll need to add a Gift Aid profile to any contribution forms that you want to use to collect "Gift-aid-able" contributions.

1. Go to **Administer » Custom Data and screens » profiles** to edit the info text or fields to display so it’s suits your organisation’s needs.

2. Create Online Contribution Page. Go to **Contributions » Manage Contributions Pages** to add a new contribution page. See here for more details on how to do this:

3. Add gift aid form to Contribution Form. In the profiles section of your contribution page ensure you include ‘Gift Aid’ in the top/bottom of your page. Or you can add the profile to an existing contribution page. Go to Contributions » Manage contribution pages. Select **[your contribution page] » Configure » Include profiles**.

   *Note: make sure there is no duplicated in fields in the form you are asking people to complete i.e name and address fields.*

4. Now, when new contributions are made online with Contribution pages using the Gift Aid profile, two things will happen. The system will firstly store that the donation was "eligible for Gift Aid" and secondly the system will check whether that donor has an existing Gift Aid declaration and if not will make a new record against the contact.

Having collected the relevant information about the donation you are now ready to create your batch and submit to HMRC.

#### Declaration and contribution eligibility

If you want to use the same profile to sign a declaration of "Yes" but mark that this specific contribution is not eligible add both the Individual "Uk Tax Payer" and the Contribution "Eligible for Gift Aid" field to the profile.
