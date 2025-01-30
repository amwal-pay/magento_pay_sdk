# AmwalPay Payment Gateway package for Magento2 e-commerce

## Installation
1. Install the AmwalPay Payment Magento2 module via [amwal-pay/magento-payment](https://packagist.org/packages/amwal-pay/magento-payment) composer.
```bash
composer require amwal-pay/magento-payment
```

2. In the command line, run the below Magento commands to enable AmwalPay Payment Gateway module.
```bash
php -f bin/magento module:enable --clear-static-content Amwal_Pay
```
3. Then, run the below Magento commands.
```bash
php bin/magento setup:upgrade
php bin/magento setup:di:compile
php bin/magento setup:static-content:deploy -f
php bin/magento cache:clean
php bin/magento cache:flush
```

## Configuration
### AmwalPay Account
1. Merchant id. 
2. Terminal  id. 
3. Secret key.

### Magento Admin Configuration
1. Login into Magento admin panel.
2. In the left Menu → Stores → Configuration.
<a href="https://imgbox.com/YYdQtSha" target="_blank"><img src="https://thumbs2.imgbox.com/17/f4/YYdQtSha_t.png" alt="image host"/></a>
3. Expand Sales Menu → select Payment Methods →Amwal Pay, paste each key mentioned in the above AmwalPay Account section in its place in the setting page.
<a href="https://imgbox.com/Fb4VDquZ" target="_blank"><img src="https://thumbs2.imgbox.com/67/24/Fb4VDquZ_t.png" alt="image host"/></a>
<a href="https://imgbox.com/M7ANQVaP" target="_blank"><img src="https://thumbs2.imgbox.com/94/91/M7ANQVaP_t.png" alt="image host"/></a> 
4. Then, click on save.

## Checkout page 
Amwal Pay payment method will be shown for the end-user to select and start his payment process. 
<a href="https://imgbox.com/qFldcGEF" target="_blank"><img src="https://thumbs2.imgbox.com/03/b4/qFldcGEF_t.png" alt="image host"/></a>

---
# Uploading to Composer

To make your module accessible via Packagist, follow these steps:

## 1. Sign Up for Packagist

- Visit [Packagist](https://packagist.org/) and log in or create an account if you haven't already.

## 2. Submit Your Package

- Go to the [Submit](https://packagist.org/packages/submit) page on Packagist.
- Enter your repository URL: `https://github.com/amwal-pay/magento_pay_sdk`.
- Ensure your GitHub repository is **public** so Packagist can access it.

## 3. Auto-update on Packagist

To keep your package up to date, link your GitHub repository with Packagist:

1. Navigate to your repository’s **Settings** on GitHub.
2. Go to **Webhooks** and click **Add webhook**.
3. Set the **Payload URL** to `https://packagist.org/api/github`.
4. Choose `application/json` as the content type.
5. Select **Just the push event**.
6. Click **Add webhook** to save the changes.

Once configured, your package will automatically update whenever you push new changes to the repository.

---