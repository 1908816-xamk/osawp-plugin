=== OriginStamp ===

Plugin Name: OriginStamp
Plugin URI: https://developer.wordpress.org/plugins/originstamp
Contributors: Eugen Stroh, Thomas Hepp, Bela Gipp, André Gernandt
Tags: time-stamping, verification, bitcoin
License: MIT
Requires at least: WordPress 4.7
Tested up to: WordPress 4.9.4
Stable tag: 0.0.7

OriginStamp is an automated time-stamping solution for your WordPress posts using the OriginStamp API service.


== Description ==

OriginStamp is a web-based, trusted timestamping service that uses the decentralized Bitcoin block chain to store anonymous, tamper-proof time stamps for any digital content.
OriginStamp is free of charge and allows users to prove that they were the originator of certain information at a given point in time.
This plugin embeds our OriginStamp service(http://www.originstamp.org/) as 3rd party service into WordPress.
Each time you create or modify a new blog entry, an unique fingerprint is calculated.
The original data cannot be guessed from the fingerprint. Therefore only the fingerprint (SHA-256) is submitted to OriginStamp.

To sum up:

1. You can timestamp your post made with WordPress to be able to prove the existence of the information contained in the post in a certain point of time.
2. With a copy of the post title and the post content you can prove to anybody, that you were in possession of this data at a certain point in time.


== Installation ==

= From your WordPress dashboard =

1. Visit 'Plugins > Add New'
2. Search for 'OriginStamp'
3. Activate OriginStamp from your Plugins page.

= From WordPress.org =

1. Download OriginStamp.
2. Upload the 'originstamp' directory to your '/wp-content/plugins/' directory, using your favorite method (ftp, sftp, scp, etc...)
3. Activate OriginStamp from your Plugins page.

= Settings =

Visit 'Settings > OriginStamp' and adjust your configuration.

There are two settings fields.
The mandatory api key field is mandatory needs to filled by the key created at https://originstamp.org/dev
This key will be used to send requests to OriginStamp.
An email field that you can enter to obtain a copy of the data that was hashed via email and store it.
Note that your email is only used internally, you will receive email from your WordPress server, not from us.
We do not send the email address elsewhere.
If you do not want to receive any emails, just leave the email field empty.
If you've entered your email address and decide that you do not want to receive email anyway, just delete the email field and hit 'Save changes' at the bottom of the settings page.

The plugin will create a data table in the local WordPress database to store content of posts. If the data table is not created (you will receive an error in the settings page), the plugin won` function properly. In this case you can create this table manually.

If you delete the plugin, the created data table will be removed automatically. So do a backup if you want to keep data stored in it.


== Upgrade Notice ==

In order to update the plugin form an earlier version replace the old plugin file.

== Changelog ==

= 0.0.1 =
* Initial release

= 0.0.2 =
* Customized for changed OriginStamp API.

= 0.0.3 =
* Usage of new API added.
* Email delivery added.

= 0.0.4 =
* Table of timestamps implemented.

= 0.0.5 =
* DB table implemented to store hashed data.

= 0.0.6 =
* Stable version for official WordPress Plugin repository.
* Some security issues fixes.
* Some minor fixes.

= 0.0.7 =
* A bug fix, where hahs table was shown in wp_head.
* Validators added to API key and email address to avoid errors.

== Screenshots ==

1. OriginStamp settings section screenshot-1.png.
2. Table with hashes retrieved via OriginStamp API screenshot-2.png.
3. Start a new blog post screenshot-3.png.
4. New timestamps are showed in the hash table on settings page screenshot-4.png.
5. Email with hash and content that was hashed screenshot-5.png.
6. Download the content that corresponds to a certain screenshot-6.png.
7. Verify the sha256 value by copy and pasting the content that we have sent you or the file content into any online sha256 calculator screenshot-7.png.
8. Go to the OriginStamp page an check the status of your timestamp screenshot-8.png.

== Donate ==

If you want to support our project, you can donate any amounts of Bitcoins to: 1Stampap27ZbxugEmWPbxNXJxzmYWn2op
There is also a possibility to donate via PayPal, check our FAQ section 'Why is it free?' at: https://app.originstamp.org/faq