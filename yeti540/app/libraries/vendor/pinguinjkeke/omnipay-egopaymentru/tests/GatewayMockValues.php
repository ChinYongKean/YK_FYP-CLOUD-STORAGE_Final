<?php

namespace Omnipay\EgopayRu;

class GatewayMockValues
{
    public static function getCancelSuccess($shopId, $orderId)
    {
        return array(
            'order' => array('number' => $orderId, 'shop_id' => $shopId),
            'status' => 'acknowledged',
            'shopref' => '',
            'error' => array('code' => 'ok', 'category' => 'system'),
            'payments' => array(
                'Payment' => array(
                    array(
                        'authorg' => 'ucs',
                        'clearing' => 'card',
                        'id' => '123123123',
                        'authcode' => 'AUTHCODE',
                        'date' => '2016-09-16T15:18:43',
                        'type' => 'card',
                        'amount' => array('amount' => '10', 'currency' => 'RUB'),
                        'doc' => array('code' => 'CA', 'number' => '510000*0008', 'holder' => 'Pupkin Vasya')
                    )
                )
            )
        );
    }

    public static function getCancelFail()
    {
        return 'INVALID_ORDER';
    }

    public static function getRejectSuccess($shopId, $orderId)
    {
        return array(
            'order' => array('number' => $orderId, 'shop_id' => $shopId),
            'status' => 'canceled',
            'shopref' => '',
            'error' => array('code' => 'ok', 'category' => 'system'),
            'payments' => array()
        );
    }

    public static function getRejectFail()
    {
        return 'INVALID_ORDER';
    }

    public static function getRefundSuccess($shopId, $orderId)
    {
        return array(
            'order' => array('number' => $orderId, 'shop_id' => $shopId),
            'status' => 'acknowledged',
            'shopref' => '',
            'error' => array('code' => 'ok', 'category' => 'system'),
            'payments' => array(
                'Payment' => array(
                    array(
                        'authorg' => 'ucs',
                        'clearing' => 'card',
                        'id' => '123123123',
                        'authcode' => 'AUTHCODE',
                        'date' => '2016-09-16T15:18:43',
                        'type' => 'card',
                        'amount' => array('amount' => '10', 'currency' => 'RUB'),
                        'doc' => array('code' => 'CA', 'number' => '510000*0008', 'holder' => 'Pupkin Vasya')
                    )
                )
            )
        );
    }

    public static function getRefundFail()
    {
        return 'ORDER_ERROR';
    }

    public static function getRegisterSuccess($redirectUrl, $session)
    {
        return array(
            'redirect_url' => $redirectUrl,
            'session' => $session
        );
    }

    public static function getRegisterFail()
    {
        return 'ALREADY_PROCESSED';
    }
    
    public static function getStatusSuccess($shopId, $orderId)
    {
        return array(
            'order' => array('shop_id' => $shopId, 'order_id' => $orderId),
            'shopref' => '',
            'documents' => array(
                array(
                    'code' => 'CA',
                    'number' => '510000*0008',
                    'exp_date' => '2020-12-01T00:00:00',
                    'desc' => array(
                        'country' => 'RO',
                        'bank' => 'ABN AMRO BANK (ROMANIA) S.A.',
                        'product' => 'MCS'
                    ),
                    'token' => '1666015238600015412'
                )
            ),
            'attempts' => array(
                'Attempt' => array(
                    array(
                        'error' => array('code' => 'network', 'category' => 'bank'),
                        'device' => '236770',
                        'secure' => 'authenticated',
                        'id' => '162428',
                        'date' => '2016-09-16T14:17:31',
                        'holder' => 'Pupkin Vasya',
                        'token' => '1666015238600015412'
                    ),
                    array(
                        'error' => array('code' => 'ok', 'category' => 'system'),
                        'device' => '236773',
                        'secure' => 'authenticated',
                        'id' => '162429',
                        'date' => '2016-09-16T15:18:26',
                        'holder' => 'Pupkin Vasya',
                        'token' => '1666015238600015412'
                    )
                )
            ),
            'customer' => array(
                'phone' => '79996264513',
                'id' => 1,
                'name' => 'Vasya Pupkin',
                'email' => 'a@b.ru'
            ),
            'devices' => array(
                'Device' => array(
                    array(
                        'info' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36',
                        'country' => 'RU',
                        'id' => '236770',
                        'ip' => '192.168.1.1'
                    ),
                    array(
                        'info' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/52.0.2743.116 Safari/537.36',
                        'country' => 'RU',
                        'device' => '236773',
                        'ip' => '192.168.1.1'
                    )
                )
            ),
            'items' => array(
                'Item' => array(
                    array(
                        'number' => '123',
                        'amount' => array('amount' => 10.0, 'currency' => 'RUB'),
                        'host' => 'shop',
                        'payment' => '626015291257',
                        'typename' => 'service'
                    )
                )
            ),
            'status' => 'acknowledged',
            'payments' => array(
                'Payment' => array(
                    array(
                        'attempt' => '162429',
                        'amount' => array('amount' => 10.0, 'currency' => 'RUB'),
                        'authcode' => 'AUTHCODE',
                        'date' => '2016-09-16T15:18:26',
                        'authorg' => 'ucs',
                        'id' => '626515292045',
                        'clearing' => 'card',
                        'salepoint' => ''
                    )
                )
            )
        );
    }

    public static function getStatusFail()
    {
        return 'INVALID_ORDER';
    }
}
