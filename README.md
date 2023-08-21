<p align="center"><img src="./src/icon.svg" width="100" height="100" alt="Alibaba OSS for Craft CMS icon"></p>

<h1 align="center">Alibaba OSS for Craft CMS</h1>

This plugin provides an [Alibaba OSS](https://www.alibabacloud.com/product/object-storage-service) integration for [Craft CMS](https://craftcms.com/).

## Requirements

This plugin requires Craft CMS 4.0 or later.

## Installation

You can install this plugin from the Plugin Store or with Composer.

#### From the Plugin Store

Go to the Plugin Store in your project’s Control Panel and search for “Alibaba OSS”. Then press **Install** in its modal window.

#### With Composer

Open your terminal and run the following commands:

```bash
# go to the project directory
cd /path/to/my-project.test

# tell Composer to load the plugin
composer require craftcms/alibaba-oss

# tell Craft to install the plugin
./craft plugin/install alibaba-oss
```

## Setup

To create a new Alibaba OSS filesystem to use with your volumes, visit **Settings** → **Filesystems**, and press **New filesystem**. Select “Alibaba OSS” for the **Filesystem Type** setting and configure as needed.

> 💡 The Access Key ID, Access Key Secret, Bucket, Region and Subfolder settings can be set to environment variables. See [Environmental Configuration](https://craftcms.com/docs/4.x/config/#environmental-configuration) in the Craft docs to learn more about that.
