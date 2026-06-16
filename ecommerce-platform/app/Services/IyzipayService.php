<?php

namespace App\Services;

use App\Models\Order;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * IyzipayService
 *
 * Wraps the iyzico/iyzipay-php SDK to handle 3D Secure payment flows.
 *
 * Flow:
 * 1. initialize3DS: Builds the request, sends to Iyzipay, returns raw HTML form.
 * 2. finalize3DS: Called on the callback endpoint to verify and complete the charge.
 *
 * Note: Requires `iyzico/iyzipay-php` package to be installed via composer.
 */
class IyzipayService
{
    private \Iyzipay\Options $options;

    public function __construct()
    {
        // Ideally, load these from config/services.php
        $this->options = new \Iyzipay\Options();
        $this->options->setApiKey(config('services.iyzipay.api_key', 'sandbox-api-key'));
        $this->options->setSecretKey(config('services.iyzipay.secret_key', 'sandbox-secret-key'));
        $this->options->setBaseUrl(config('services.iyzipay.base_url', 'https://sandbox-api.iyzipay.com'));
    }

    /**
     * Initialize a 3D Secure payment and get the HTML form.
     *
     * @param Order $order
     * @param array $cardData  ['cardHolderName', 'cardNumber', 'expireMonth', 'expireYear', 'cvc']
     * @param string $ipAddress Customer IP
     * @return string Raw HTML string containing the 3D Secure form
     * @throws Exception if initialization fails
     */
    public function initialize3DS(Order $order, array $cardData, string $ipAddress): string
    {
        $request = new \Iyzipay\Request\CreatePaymentRequest();
        $request->setLocale(\Iyzipay\Model\Locale::TR);
        $request->setConversationId($order->order_number);
        
        // Prices must be strings formatted to 2 decimals
        $request->setPrice(number_format($order->subtotal, 2, '.', ''));
        $request->setPaidPrice(number_format($order->grand_total, 2, '.', ''));
        $request->setCurrency(\Iyzipay\Model\Currency::TL);
        $request->setInstallment(1);
        $request->setBasketId($order->order_number);
        $request->setPaymentChannel(\Iyzipay\Model\PaymentChannel::WEB);
        $request->setPaymentGroup(\Iyzipay\Model\PaymentGroup::PRODUCT);

        // Callback URL (Route needs to be absolute)
        $request->setCallbackUrl(url('/api/v1/payments/iyzico/callback'));

        // Payment Card
        $paymentCard = new \Iyzipay\Model\PaymentCard();
        $paymentCard->setCardHolderName($cardData['cardHolderName']);
        $paymentCard->setCardNumber($cardData['cardNumber']);
        $paymentCard->setExpireMonth($cardData['expireMonth']);
        $paymentCard->setExpireYear($cardData['expireYear']);
        $paymentCard->setCvc($cardData['cvc']);
        $paymentCard->setRegisterCard(0);
        $request->setPaymentCard($paymentCard);

        // Buyer Data
        $user = $order->user;
        $buyer = new \Iyzipay\Model\Buyer();
        $buyer->setId((string) $user->id);
        $buyer->setName($user->name);
        $buyer->setSurname($user->name); // Split logic can be added if needed
        $buyer->setGsmNumber($user->phone ?? '+905555555555');
        $buyer->setEmail($user->email);
        $buyer->setIdentityNumber('11111111111'); // Requirement for Iyzipay, should be collected from user
        $buyer->setRegistrationAddress($order->shipping_address['address_line1']);
        $buyer->setIp($ipAddress);
        $buyer->setCity($order->shipping_address['city']);
        $buyer->setCountry($order->shipping_address['country_code']);
        $request->setBuyer($buyer);

        // Shipping Address
        $shippingAddress = new \Iyzipay\Model\Address();
        $shippingAddress->setContactName($order->shipping_address['recipient_name']);
        $shippingAddress->setCity($order->shipping_address['city']);
        $shippingAddress->setCountry($order->shipping_address['country_code']);
        $shippingAddress->setAddress($order->shipping_address['address_line1']);
        $request->setShippingAddress($shippingAddress);

        // Billing Address (Using shipping address as billing for simplicity)
        $billingAddress = new \Iyzipay\Model\Address();
        $billingAddress->setContactName($order->shipping_address['recipient_name']);
        $billingAddress->setCity($order->shipping_address['city']);
        $billingAddress->setCountry($order->shipping_address['country_code']);
        $billingAddress->setAddress($order->shipping_address['address_line1']);
        $request->setBillingAddress($billingAddress);

        // Basket Items (Important for sub-merchant splits, but we configure generic items here)
        $basketItems = [];
        foreach ($order->items as $item) {
            $basketItem = new \Iyzipay\Model\BasketItem();
            $basketItem->setId((string) $item->id);
            $basketItem->setName($item->product_name);
            $basketItem->setCategory1('E-Commerce'); // Could map to actual categories
            $basketItem->setItemType(\Iyzipay\Model\BasketItemType::PHYSICAL);
            $basketItem->setPrice(number_format($item->line_total, 2, '.', ''));
            
            // Sub-merchant splitting logic could be added here
            // $basketItem->setSubMerchantKey($item->vendor->sub_merchant_key);
            // $basketItem->setSubMerchantPrice(number_format($item->vendor_payout, 2, '.', ''));
            
            $basketItems[] = $basketItem;
        }
        $request->setBasketItems($basketItems);

        // Initialize 3DS
        $initialize = \Iyzipay\Model\ThreedsInitialize::create($request, $this->options);

        if ($initialize->getStatus() !== 'success') {
            Log::error('Iyzipay 3DS Initialization Failed', [
                'errorCode'    => $initialize->getErrorCode(),
                'errorMessage' => $initialize->getErrorMessage(),
                'order_number' => $order->order_number
            ]);
            
            throw new Exception('Payment initialization failed: ' . $initialize->getErrorMessage());
        }

        // Return the HTML content containing the auto-submitting form
        return $initialize->getHtmlContent();
    }

    /**
     * Finalize the 3D Secure payment after the bank redirects back.
     *
     * @param string $paymentId
     * @param string $conversationId  (mapped back to our order_number)
     * @return array ['success' => bool, 'error' => string|null, 'order_number' => string]
     */
    public function finalize3DS(string $paymentId, ?string $conversationId = null): array
    {
        $request = new \Iyzipay\Request\CreateThreedsPaymentRequest();
        $request->setLocale(\Iyzipay\Model\Locale::TR);
        $request->setPaymentId($paymentId);
        
        if ($conversationId) {
            $request->setConversationId($conversationId);
        }

        $payment = \Iyzipay\Model\ThreedsPayment::create($request, $this->options);

        if ($payment->getStatus() !== 'success') {
            Log::error('Iyzipay 3DS Payment Failed', [
                'errorCode'    => $payment->getErrorCode(),
                'errorMessage' => $payment->getErrorMessage(),
                'paymentId'    => $paymentId,
            ]);

            return [
                'success'      => false,
                'error'        => $payment->getErrorMessage(),
                'order_number' => $conversationId
            ];
        }

        return [
            'success'      => true,
            'error'        => null,
            'order_number' => $payment->getConversationId() ?? $conversationId,
            'payment_ref'  => $payment->getPaymentId(),
        ];
    }
}
