<?php

/**
 * Created with love by: Patryk Vizauer (patryqhyper.pl)
 * Date: 16.05.2022 21:37
 * Using: PhpStorm
 */

namespace PatryQHyper\Payments\Online\Methods;

use GuzzleHttp\Exception\TransferException;
use PatryQHyper\Payments\Exceptions\PaymentException;
use PatryQHyper\Payments\Online\PaymentAbstract;
use PatryQHyper\Payments\Online\PaymentGeneratedResponse;

class HotPayPayment extends PaymentAbstract
{
    private string $secret;
    private string $notificationPassword;
    private string $method;

    public const METHOD_TRANSFER = 'transfer';
    public const METHOD_PAYSAFECARD = 'paysafecard';

    private float $amount;
    private string $description;
    private string $redirectUrl;
    private string $orderId;
    private string $email;
    private string $personalData;

    public function __construct(string $secret, string $notificationPassword, string $method)
    {
        if (!in_array($method, [self::METHOD_PAYSAFECARD, self::METHOD_TRANSFER]))
            throw new PaymentException('invalid method');

        $this->secret = $secret;
        $this->notificationPassword = $notificationPassword;
        $this->method = $method;
    }

    public function setAmount(float $amount)
    {
        $this->amount = $amount;
        return $this;
    }

    public function setDescription(string $description)
    {
        $this->description = $description;
        return $this;
    }

    public function setRedirectUrl(string $redirectUrl)
    {
        $this->redirectUrl = $redirectUrl;
        return $this;
    }

    public function setOrderId(string $orderId)
    {
        $this->orderId = $orderId;
        return $this;
    }

    public function setEmail(string $email)
    {
        $this->email = $email;
        return $this;
    }

    public function setPersonalData(string $personalData)
    {
        $this->personalData = $personalData;
        return $this;
    }

    public function generatePayment()
    {
        $params = [
            'KWOTA' => $this->amount,
            'NAZWA_USLUGI' => $this->description,
            'ADRES_WWW' => $this->redirectUrl,
            'ID_ZAMOWIENIA' => $this->orderId,
            'SEKRET' => $this->secret
        ];

        $params['HASH'] = hash('sha256', $this->notificationPassword . ';' . implode(';', $params));

        if (isset($this->email)) $params['EMAIL'] = $this->email;
        if (isset($this->personalData)) $params['DANE_OSOBOWE'] = $this->personalData;
        $params['TYP'] = 'INIT';

        $request = $this->doRequest(sprintf('https://%s.hotpay.pl', ($this->method == self::METHOD_TRANSFER ? 'platnosc' : 'psc')), [
            'form_params' => $params,
        ], 'POST', false, false);

        $json = @json_decode($request->getBody());
        if (!$json)
            throw new PaymentException('HotPay error: invalid notification password');

        if (!$json->STATUS)
            throw new TransferException('HotPay error: ' . $json->WIADOMOSC);

        return new PaymentGeneratedResponse($json->URL);
    }

    public function generateNotificationHash(array $data)
    {
        $array = [
            $this->notificationPassword,
            $data['KWOTA'],
            $data['ID_PLATNOSCI'],
            $data['ID_ZAMOWIENIA'],
            $data['STATUS'],
            $data['SECURE'],
            $data['SEKRET'],
        ];
        return hash('sha256', implode(';', $array));
    }
}