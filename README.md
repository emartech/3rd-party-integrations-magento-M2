
### Manual Install / Upgrade
- Locate the /app/code directory which should be under the magento root installation.
- If the code folder is not there, create it.
- Remove the "Emarsys" directory from "MAGENTO_ROOT/app/code" directory if exists
- Download the latest files from master branch and extract the contents
- Copy all files from the downloaded codebase to the "MAGENTO_ROOT/app/code/Emarsys/Emarsys" directory on you Magento instance


### Magento Setup
- Make sure you have the correct file and folder permissions set on your magento installation so that the magnento store can install the app.
- Refer to the Magento 2 documentation for full instructions on how to install an app, the commands should be similar to the following:
- ```bin/magento setup:upgrade``` - This tells magento to install the app.
- ```bin/magento setup:static-content:deploy``` - This tells magento to deploy static files.
- ```bin/magento cache:flush``` - This flushes the cache so the app appears in the admin area correctly.


### Composer Setup
- ```composer require emarsys/emarsys```
- ```bin/magento module:enable Emarsys_Emarsys```
- ```bin/magento setup:upgrade```
- ```bin/magento setup:di:compile```
- ```bin/magento setup:static-content:deploy```
- ```bin/magento cache:flush```


# Release Notes

## v1.0.30
- Code Style
- Possibility to install v2 in parallel
- Async product export
- Uninstall script