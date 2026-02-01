<?php

namespace Amwal\Pay\Helper;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\QuoteIdMaskFactory;
use Magento\Customer\Model\Group;
use Magento\Directory\Model\AllowedCountries;
use Magento\Framework\Exception\LocalizedException;

class ApplePayQuoteManager
{
    protected $quoteRepository;
    protected $quoteIdMaskFactory;
    protected $allowedCountries;

    public function __construct(
        CartRepositoryInterface $quoteRepository,
        QuoteIdMaskFactory $quoteIdMaskFactory,
        AllowedCountries $allowedCountries
    ) {
        $this->quoteRepository    = $quoteRepository;
        $this->quoteIdMaskFactory = $quoteIdMaskFactory;
        $this->allowedCountries   = $allowedCountries;
    }

    /**
     * Load quote using masked or numeric id
     */
    public function loadQuote($cartId)
    {
        if (!$cartId) {
            throw new LocalizedException(__('Missing quote id'));
        }

        if (!is_numeric($cartId)) {
            $mask = $this->quoteIdMaskFactory->create()
                ->load($cartId, 'masked_id');
            $quoteId = (int) $mask->getQuoteId();
        } else {
            $quoteId = (int) $cartId;
        }

        $quote = $this->quoteRepository->getActive($quoteId);
        if (!$quote->getId()) {
            throw new LocalizedException(__('Quote not found'));
        }

        return $quote;
    }

    /**
     * Prepare customer (guest or logged-in)
     */
    public function prepareCustomer($quote, $email)
    {
        if (!$email) {
            throw new LocalizedException(__('Customer email missing'));
        }

        $quote->setCustomerEmail($email);

        if (!$quote->getCustomerId()) {
            $quote->setCustomerId(null);
            $quote->setCustomerIsGuest(true);
            $quote->setCustomerGroupId(Group::NOT_LOGGED_IN_ID);
            $quote->setCheckoutMethod('guest');
        } else {
            $quote->setCustomerIsGuest(false);
            $quote->setCheckoutMethod(null);
        }
    }

    /**
     * Apply Apple Pay shipping & billing addresses
     */
    public function applyAddresses($quote, array $appleShipping)
    {
        if (empty($appleShipping['countryId'])) {
            throw new LocalizedException(__('Shipping address missing'));
        }

        $shipping = $quote->getShippingAddress();
        $shipping->setCustomerAddressId(null)
            ->setSaveInAddressBook(0)
            ->addData([
                'firstname'  => $appleShipping['firstname'] ?? 'Customer',
                'lastname'   => $appleShipping['lastname'] ?? 'ApplePay',
                'street'     => $appleShipping['street'] ?? ['N/A'],
                'city'       => $appleShipping['city'] ?? 'N/A',
                'region'     => $appleShipping['region'] ?? '',
                'postcode'   => $appleShipping['postcode'] ?? '0000',
                'country_id' => $appleShipping['countryId'],
                'telephone'  => $appleShipping['telephone'] ?? '0000000000',
                'email'      => $quote->getCustomerEmail()
            ]);

        if (!in_array(
            $shipping->getCountryId(),
            $this->allowedCountries->getAllowedCountries()
        )) {
            throw new LocalizedException(__('Shipping country is not allowed'));
        }

        $email = $quote->getCustomerEmail() ?: $this->getRequest()->getParam('email');
        $isLoggedIn = (bool) $quote->getCustomerId();
        $shipping->setSameAsBilling(1);
        $billing = $quote->getBillingAddress();
            $billing->setCustomerId($isLoggedIn ? $quote->getCustomerId() : null);
            $billing->setCustomerAddressId(null);
            $billing->setSaveInAddressBook(0);

            $billing->addData([
                'firstname'  => $shipping->getFirstname(),
                'lastname'   => $shipping->getLastname(),
                'street'     => $shipping->getStreet(),
                'city'       => $shipping->getCity(),
                'region'     => $shipping->getRegion(),
                'region_id'  => $shipping->getRegionId(),
                'postcode'   => $shipping->getPostcode(),
                'country_id' => $shipping->getCountryId(),
                'telephone'  => $shipping->getTelephone() ?? '0000000000',
                'email'      => $email
            ]);

            $shipping->setSameAsBilling(1);
    }

    /**
     * Apply shipping method
     */
    public function applyShippingMethod($quote, array $appleMethod = [])
    {
        $shipping = $quote->getShippingAddress();
        $shipping->setCollectShippingRates(true);
        
        if (!empty($appleMethod['carrier_code']) && !empty($appleMethod['method_code'])) {
            $shipping->setShippingMethod(
                $appleMethod['carrier_code'] . '_' . $appleMethod['method_code']
            );
        } else {
            $quote->collectTotals();
            $rates = $shipping->getAllShippingRates();
            if (empty($rates)) {
                throw new LocalizedException(__('No shipping methods available'));
            }
            $shipping->setShippingMethod($rates[0]->getCode());
        }
    }

    /**
     * Save & collect totals
     */
    public function finalize($quote)
    {
        $quote->collectTotals();
        $this->quoteRepository->save($quote);
    }
}
