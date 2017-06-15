# Magento 2 Sass Preprocessor by SplashLab

This extension allows compiling Sass files in Magento 2 themes, just like LESS files. It works with the normal Magento deploy process, normalizing module structures using `@magento_import`, as expected. It uses the leafo/scssphp

Based on this official sample project: https://github.com/magento/magento2-samples/tree/master/module-sample-scss

## Install via composer

Edit your project's `composer.json` to add the GitHub repo and require the project.

```
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/splashlab/magento-2-sass-preprocessor"
        }
    ],
    "require": {
        "splashlab/magento-2-sass-preprocessor": "dev-master"
    }
}
```

```
composer install
php bin/magento setup:upgrade
php bin/magento setup:static-content:deploy
```

## Tests

Unit tests could be found in the [Test/Unit](Test/Unit) directory.

