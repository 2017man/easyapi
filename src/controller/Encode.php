<?php

namespace EasyApi\Controller;

use think\Controller;
use EasyApi\Controller\Oauth;

/**
 * 公共加密模块
 * Class Ecode
 * @package EasyApi\controller
 * base64_encode(appid:accesstoken:uid)
 */
class Encode
{
    public function md5()
    {
        $app_secret = input('key');
        $data = input('');
        unset($data['key']);
        return Oauth::makeSign($data, $app_secret);
    }

    public function base64()
    {
        $appid = input('appid');
        $access_token = input('access_token');
        $uid = input('uid');
        $str = $appid . ':' . $access_token . ':' . $uid;
        return base64_encode((string)$str);
    }

    /**
     * 生成签名
     * _字符开头的变量不参与签名
     */
    public static function makeSign($data = [], $app_secret = '')
    {
        unset($data['version']);
        unset($data['sign']);
        return self::_getOrderMd5($data, $app_secret);
    }

    /**
     * 计算ORDER的MD5签名
     */
    private static function _getOrderMd5($params = [], $app_secret = '')
    {
        ksort($params);
        $params['key'] = $app_secret;
        return urldecode(http_build_query($params));
        return strtolower(md5(urldecode(http_build_query($params))));
    }
}
