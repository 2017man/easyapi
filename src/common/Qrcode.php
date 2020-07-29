<?php

namespace app\api\common;

use think\Controller;
use EasyWeChat\Factory;
use think\Db;

class Qrcode extends Controller
{
    /**
     * 二维码模块
     * @param string $idcard
     * @param int $state
     * @return string
     */

    //二维码生成
    public function qr_code_mem($idcard, $state)
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $config = [
            'app_id' => config("WXAPPID"),
            'secret' => config("WXSECRET"),
            'response_type' => 'array',
        ];

        $app = Factory::miniProgram($config);
        //绿码
        $line_color_green = ['r' => 87, 'g' => 172, 'b' => 108,];
        $line_color_yellow = ['r' => 255, 'g' => 143, 'b' => 31,];
        $line_color_red = ['r' => 251, 'g' => 56, 'b' => 45,];
        $line_color = $line_color_green;
        if ($state == '低风险') {
            $line_color = $line_color_green;
        } elseif ($state == '中风险') {
            $line_color = $line_color_yellow;
        } elseif ($state == '高风险') {
            $line_color = $line_color_red;
        }

        $response = $app->app_code->getUnlimit($idcard, [
            'page' => 'pages/enterprise/codeinfo',
            'width' => 600,
            'line_color' => $line_color,
        ]);

        if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
            $time = time();
            $filename = $response->saveAs('qrcode/mem/', "qrcode_" . $idcard . '_' . $time . '.png');
            $qrcode = $protocol . $_SERVER['HTTP_HOST'] . "/qrcode/mem/qrcode_" . $idcard . '_' . $time . '.png';
            Db::table('member')->where('idcard', $idcard)->update(['qr_code' => $qrcode]);
            return $qrcode;
        } else {
            return false;
        }
    }

    //企业复工
    public function qr_code_qy($idcard, $state = '')
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $config = [
            'app_id' => config("WXAPPID"),
            'secret' => config("WXSECRET"),
            'response_type' => 'array',
        ];

        $app = Factory::miniProgram($config);
        //绿码
        $line_color_green = ['r' => 87, 'g' => 172, 'b' => 108,];
        $line_color_yellow = ['r' => 255, 'g' => 143, 'b' => 31,];
        $line_color_red = ['r' => 251, 'g' => 56, 'b' => 45,];
        $line_color = $line_color_green;
        if ($state == '低风险') {
            $line_color = $line_color_green;
        } elseif ($state == '中风险') {
            $line_color = $line_color_yellow;
        } elseif ($state == '高风险') {
            $line_color = $line_color_red;
        }

        $response = $app->app_code->getUnlimit($idcard, [
            'page' => 'pages/infocode/infocode',
            'width' => 600,
            'line_color' => $line_color,
        ]);

        if ($response instanceof \EasyWeChat\Kernel\Http\StreamResponse) {
            $filename = $response->saveAs('qrcode/qiye/', "qrcode_" . $idcard . '.png');
            $qrcode = $protocol . $_SERVER['HTTP_HOST'] . "/qrcode/qiye/qrcode_" . $idcard . '.png';
            return $qrcode;
        } else {
            return false;
        }


    }
}