Asterisk Connector for Vtiger 7.3
==========

This patch makes Vtiger 7.3 compatible with [SPAsteriskConnector](https://salesplatform24.com/?page_id=2831)

# Installation

1. Open  your Vtiger CRM folder (e.g., /var/www/vtigercrm/)

2. Download the patch: 
```
wget https://raw.githubusercontent.com/jaimey/asterisk-connector-vtiger-7.3/main/patch/0001-feat-make-SPAsteriskConnector-compatible-with-Vtiger.patch
```

3. Check the patch compatibility:
```
patch --dry-run -p 1 < 0001-feat-make-SPAsteriskConnector-compatible-with-Vtiger.patch
```
You should see output like:
```
    checking file file1
    checking file file2
```
**Warning! If you see something like: “Hunk #1 FAILED”, then do not proceed with the update!**

4. Apply the patch:
```
patch -p 1 < 0001-feat-make-SPAsteriskConnector-compatible-with-Vtiger.patch
```

Continue with the Vtiger integration with Asterisk tutorial. 
https://salesplatform24.com/?page_id=2831

Based on [this patch](https://sourceforge.net/projects/salesplatform/files/patches/7.0.1/)
