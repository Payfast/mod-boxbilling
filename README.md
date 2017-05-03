PayFast BoxBilling Payment v1.1.2 for BoxBilling v3.6.* and v1.1.2 for BoxBilling 4.*
-------------------------------------------------------------------------------------
Copyright (c) 2008 PayFast (Pty) Ltd
You (being anyone who is not PayFast (Pty) Ltd) may download and use this plugin / code in your own website in conjunction with a registered and active PayFast account. If your PayFast account is terminated for any reason, you may not use this plugin / code or part thereof.
Except as expressly indicated in this licence, you may not use, copy, modify or distribute this plugin / code or part thereof in any way.

INTEGRATION:
1. Download the BoxBilling PayFast files from GitHub, unzip it to your computer and upload the files to the root installation of your BoxBilling installation.
[root]/ bb-library/Payment/Adapter/PayFast.php
2. Go to: Configuration -> Payment gateways -> New Payment gateway -> Install PayFast
3. Select Edit PayFast button, complete the configuration accordingly, and set debugging on, enable the payment option and enable the test mode. Select the ZAR currency that you created in step 2. Now update the PayFast payment option.
4. You are now ready to complete a test transaction through the sandbox testing environment.
5. Once you’ve completed a test transaction. Go back to the BoxBilling admin area.
6. Goto: Configuration -> Payment gateways -> Edit PayFast and change the ‘Enable Test Mode’ to off, click update.
7. You are now ready to start accepting live transactions through PayFast!

************************************************************************
*                                                                      *
* Please see the URL below for all information concerning this module: *
*                                                                      *
*           https://www.payfast.co.za/shopping-carts/boxbilling/       *
*                                                                      *
************************************************************************

