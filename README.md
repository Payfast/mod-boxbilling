PayFast BoxBilling Payment v1.1.2 for BoxBilling v3.6.* and v1.1.2 for BoxBilling 4.*
-------------------------------------------------------------------------------------
Copyright (c) 2013 - 2016 PayFast (Pty) Ltd

LICENSE:
 
This payment module is free software; you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published
by the Free Software Foundation; either version 3 of the License, or (at
your option) any later version.

This payment module is distributed in the hope that it will be useful, but
WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY
or FITNESS FOR A PARTICULAR PURPOSE. See the GNU Lesser General Public
License for more details.

Please see http://www.opensource.org/licenses/ for a copy of the GNU Lesser
General Public License.

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

