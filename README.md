# Yireo_RemoveSearch
A module that removes both MySQL and ElasticSearch from Magento. This is experimental and might not be perfect. Note that this module is supposed to *hack* things in your Magento site. If you are experiencing problems, feel free to open up an **Issue** on GitHub. But please take note that this module is something built for experienced developers, it takes a developers mind to properly troubleshoot things.

## Installation via composer
Installation of this module by copying things to `app/code` will **not** work: The composer `replace` will not be applied and the original namespaces `Magento\Search` and `Magento\CatalogSearch` will not be remapped to this extension. It does not work. Use composer instead.

Instead, copy the extension to some kind of other folder in your Magento root, like `package-source`, and use this folder as a composer repository:

    mkdir package-source/
    cd package-source/
    git clone git@github.com:yireo/Yireo_RemoveSearch.git
    cd -
    composer add repositories.yireo-removesearch path package-source/Yireo_RemoveSearch

First, register this module in your `composer.json` file:

    composer require yireo/magento2-remove-search --no-update

Next, run:

    composer update

Unforunately, this might work or it might not. If this fails, do the following (and beware of its consequences):

    rm -r vendor/ composer.lock
    composer install
    
Please note that a simple `composer require yireo/magento2-remove-search` will not work. Please also note that removing the `vendor/` and `composer.lock` might not be best practices, but this is the only known workaround to get the composer `replace` trick working. If you don't like it, because it does not comply to standard procedures, don't use this solution.

After this, make sure to enable the module:

    bin/magento module:enable Yireo_RemoveSearch
    
## Testing if it works
To test if things are working, make sure to play around with the following commands:

    composer show | grep -i search
    bin/magento cache:flush
    bin/magento setup:upgrade
    bin/magento setup:di:compile

