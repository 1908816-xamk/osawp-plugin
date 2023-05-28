# osawp-plugin

Plugin Name: OriginStamp attachments for WordPress
description: Creates a tamper-proof timestamp of your media attachment files using OriginStamp API. This is not an original plugin by OriginStamp.
Version: 1.0.0
Author: Henri Tikkanen
Author URI: http://www.henritikkanen.info
License: The MIT License (MIT)
Requires at least: WordPress 4.7
Tested up to: WordPress 6.2.2

== Description ==

OriginStamp is a web-based, trusted timestamping service that uses the decentralized blockchain to store anonymous, tamper-proof timestamps for any digital content.
OriginStamp allows users to hash files, emails, or plain text, and subsequently store the created hashes in the blockchain as well as retrieve and verify timestamps
that have been committed to the blockchain. OriginStamp is free of charge and easy to use. It enables anyone, e.g., students, researchers, authors, journalists, or 
artists, to prove that they were the originator of certain information at a given point in time.

This plugin sends a hash value of your media attachment files, like images and videos, to OriginStamp API. Then they will be saved to several blockchains as SHA256 encoded format,
to proof the originality of your media files. This proof is verifiable to anyone who have a copy of the original data and they also call these as timestamps. You can choose wether 
you like to send all new uploads to OriginStamp or manually send just particular files in the Media Library. However, you can send the file with same hash value only once. 
If you need to modify your original file and send a new version, you should create a new upload of it.

= Q&A =
Q: What content will be sent to OriginStamp API?
A: In this version, only SHA256 value generated of the original file and ID number of the attachment in WordPress will be sent, nothing else.

Q: When the data will be sent to OriginStamp API?
A: By default, only when you check "Send to OriginStamp" option in the media editing view and update the post. Alternatively, you can also choose
"Stamp new uploads automatically" here in the options, when all the new uploads will be send automatically. Only attachments, that haven't sent before in exactly the same form, can be sent.

Q: How I know, that my data is succesfully stamped?
A: You will see a hash code and a timestamp in media editing view, when OrigiStamp has sent the confirmation, that data is succesfully saved to one or more blockchains.
This is done by using webhooks provided by OrigiStamp API. More detailed information will be also saved to post meta of the attchment in WordPress. 
You are free to use this information in your own front-end implementations or with some other applications. You can always check statuses also from your own account in OriginStamp: 

Q: Does stamping to blockchain means that my files will be NFTs and what is the difference?
A: No, you files won't be NFTs when they have been stamped. Saving a hash value of your files to a blockchain is providing only a proof of the originality, when the basic idea behind the NFT
is to provide a proof the ownership of any digital content by using smart contracts.

Q: How to verify a timestamp?
A: In order to verify the timestamp you would have to download the data, copy the string that is stored in the text file and then use any sha256 calculator of your choice to hash the string. 
After that go to OriginStamp and search for the hash. Read more at https://docs.originstamp.com/guide/originstamp.html.

Q: Where do I get more Information?
A: Please visit at OriginStamp FAQ: https://docs.originstamp.com/

== Installation ==

1. Download zipped plugin files.
2. Visit 'Plugins > Add New > Upload Plugin', search the zip file from your computer and click 'Install Now'.
3. Activate OriginStamp from your Plugins page.

= Settings =

Visit 'Settings > OriginStamp Attachments' and adjust your configuration.

There are only two mandatory setting fields.
The first mandatory field is API key, that needs to be filled by the key created at https://originstamp.com. This key will be used to send requests to OriginStamp.
The second mandatory field is API version, that is prefilled with the current version at the moment, when the plugin is released.

The plugin will create a data table in the local WordPress database to store hash values with timestamps, original post titles and URLs. 
If the data table is not created (you will receive an error in the settings page), the plugin won`t function properly.

If you delete the plugin, the created data table will be removed automatically. So do a backup if you want to keep data stored in it.

== Upgrade Notice ==

In order to update the plugin form an earlier version replace the old plugin file.

== Changelog ==

= 1.0.0 =
* Initial release

== Donate and support ==

If you want to support my work, you can donate any amounts of Ethereums to: 0x8f2e099eF440FC7892e696791b43485260D919Ed
Or support creators of the original plugin by choosing their premium plan here: https://originstamp.com/
