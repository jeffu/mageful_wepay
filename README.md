mageful_wepay
=============

WePay Module for Magento - Very Beta

This module was created during ecommerce hack day, www.ecommercehackday.com , to
add WePay functionality to Magento.

It currently adds a wepay payment method, which, when selected, redirects the
user form the onepage checkout screen to the WePay Payment screen. 
After successful completion on WePay, the customer is redirected to 
Magento and if integrity checks pass, the success page shows. 
Errors redirect and display on the cart page.

Online Capturing, Voiding and Invoicing are all complete.

The only option is for delayed capture currently. Capture online 
occurs on Invoice properly.


TODO
=============
* Integration to WePay's signup screens to make installation seamless
* Add option for the authorize and capture at order placement
* Add additional blocks and templates for better instruction and design
* Add to Magento Connect