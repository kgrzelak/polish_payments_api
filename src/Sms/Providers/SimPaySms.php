<?php

/**
 * Created with love by: Patryk Vizauer (patryqhyper.pl)
 * Date: 08.06.2022 22:39
 * Using: PhpStorm
 */

namespace PatryQHyper\Payments\Sms\Providers;

use PatryQHyper\Payments\Exceptions\InvalidSmsCodeException;
use PatryQHyper\Payments\Exceptions\PaymentException;
use PatryQHyper\Payments\Exceptions\UsedSmsCodeException;
use PatryQHyper\Payments\Sms\SmsAbstract;

class SimPaySms extends SmsAbstract
{
    private string $apiKey;
    private string $apiPassword;

    public function __construct(string $apiKey, string $apiPassword)
    {
        $this->apiKey = $apiKey;
        $this->apiPassword = $apiPassword;
    }

    public function check(string $serviceId, int $number, string $code)
    {
        $request = $this->doRequest(sprintf('https://api.simpay.pl/sms/%s', $serviceId), [
            'headers' => [
                'X-SIM-KEY' => $this->apiKey,
                'X-SIM-PASSWORD' => $this->apiPassword
            ],
            'json' => [
                'code' => $code,
                'number' => $number
            ]
        ], 'POST', false, false);

        if ($request->getStatusCode() == 404)
            throw new InvalidSmsCodeException();

        $body = json_decode($request->getBody());

        if (!isset($body->success) || !$body->success)
            throw new PaymentException(sprintf('SimPay error: %s', $body->message));

        if ($body->data->used)
            throw new UsedSmsCodeException();

        return true;
    }

    public function getSmsNumbersToService(string $serviceId, int $page = 1, int $limit = 15)
    {
        $request = $this->doRequest(sprintf('https://api.simpay.pl/sms/%s/numbers', $serviceId), [
            'query' => [
                'page' => $page,
                'limit' => $limit,
            ],
            'headers' => [
                'X-SIM-KEY' => $this->apiKey,
                'X-SIM-PASSWORD' => $this->apiPassword,
            ],
        ], useFileGetContentsToGet: false, getBody: false);
        if ($request->getStatusCode() == 200) {
            return json_decode($request->getBody());
        }

        throw new PaymentException('SimPay error:' . $request->getBody());
    }
    public function getSmsNumberToService(string $serviceId, int $number)
    {
        $request = $this->doRequest(sprintf('https://api.simpay.pl/sms/%s/numbers/%d', $serviceId, $number), [
            'headers' => [
                'X-SIM-KEY' => $this->apiKey,
                'X-SIM-PASSWORD' => $this->apiPassword,
            ],
        ], useFileGetContentsToGet: false, getBody: false);
        if ($request->getStatusCode() == 200) {
            return json_decode($request->getBody())->data;
        }

        throw new PaymentException('SimPay error:' . $request->getBody());
    }
}