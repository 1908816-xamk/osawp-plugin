# OriginStamp Attachments for WordPress

Plugin Name: OriginStamp Attachments for WordPress<br>
Description: Creates a tamper-proof timestamp of your media attachment files using OriginStamp API. This is not an original plugin by OriginStamp.<br>
Version: 1.0.3<br>
Requires PHP: 7.4<br>
Author: Henri Tikkanen<br>
Author URI: https://github.com/henritik/<br>
License: The MIT License (MIT)<br>
Tested up to: WordPress 6.4.1<br>

### Description

**[OriginStamp](https://originstamp.com/)** is a web-based, trusted timestamping service that uses the decentralized blockchain to store anonymous, tamper-proof timestamps for any digital content.
OriginStamp allows users to hash files, emails, or plain text, and subsequently store the created hashes in the blockchain as well as retrieve and verify timestamps
that have been committed to the blockchain. OriginStamp is free of charge and easy to use. It enables anyone, e.g., students, researchers, authors, journalists, or 
artists, to prove that they were the originator of certain information at a given point in time.

This plugin sends a hash value of your media attachment files, like images and videos, to OriginStamp API. Then they will be saved to several blockchains as SHA256 encoded format,
to proof the originality of your media files. This proof is verifiable to anyone who have a copy of the original data. You can choose wether 
you like to send all new uploads to OriginStamp or manually send just particular files in the Media Library. However, you can send the file with same hash value only once. 
If you need to modify your original file and send a new version, you should create a new upload of it.

### Installation

1. Download zipped plugin files.
2. Visit **Plugins > Add New > Upload Plugin**, search the zip file from your computer and click **Install Now**.
3. Activate the plugin.

### Upgrade Notice
In order to update the plugin form an earlier version, please do the installation steps 1-2 and allow WordPress to replace existing files.

### Settings

Visit **Settings > OriginStamp Attachments** and adjust your configuration.

There are only two mandatory setting fields.<br>
The first mandatory field is API key, that needs to be filled by the key created at **[OriginStamp](https://originstamp.com/)**.<br>
This key will be used to send requests to OriginStamp.<br>
The second mandatory field is API version, that is prefilled with the current version at the moment when plugin version is released.

The plugin will create a data table in the local WordPress database to store hash values with timestamps, original post titles and URLs.
If the data table is not created (you will receive an error in the settings page), the plugin won`t function properly.

If you delete the plugin, the created data table will be removed automatically. So please do a backup if you wish to keep data stored in the database.

### Changelog

#### 1.0.3
- Tested with WP 6.4.1
- Added permission callback for REST route
  
#### 1.0.2
- Changed webhook "currency" value from Bitcoin to Ethereum

#### 1.0.1
- Added an API route for requesting a proof file
- Added a download proof option in attachment edit view for stamped files
- Some minor fixes

#### 1.0.0
- Initial release

### Donate and support

If you want to support my work, you can donate any amounts of Ethereums to: 0x8f2e099eF440FC7892e696791b43485260D919Ed<br>
Or support creators of the original plugin by choosing their premium plan here: **[OriginStamp](https://originstamp.com/)**
