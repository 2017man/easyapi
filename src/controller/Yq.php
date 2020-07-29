<?php

namespace app\api\controller;

use app\api\controller\Api;
use Think\Db;


/**
 * Class CouponController
 * @package Home\Controller
 * @name 疫情数据
 */
class Yq extends Api
{

    use Send;

    /**
     * 不需要鉴权方法
     * index、save不需要鉴权
     * ['index','save']
     * 所有方法都不需要鉴权
     * [*]
     */
    protected $noAuth = ['data',];

    public function data()
    {
        header('Content-type:text/html;charset=utf-8');
        //配置您申请的appkey
        $apicode = "aa2fc06c510248aa9316f6d27891d3f1";
        $qg_url = "https://api.yonyoucloud.com/apis/dst/ncov/country";
        $sj_url = "https://api.yonyoucloud.com/apis/dst/ncov/wholeworld";
        $gs_url = "https://api.yonyoucloud.com/apis/dst/ncov/spreadQuery";
        $method = "GET";

        $cache_key = 'yy_api_data';
        $params = array();

        $header = array();
        $header[] = "apicode:" . $apicode;
        $header[] = "content-type:application/json";

        if (!empty(cache($cache_key))) {
            $data = cache($cache_key);
        } else {
            $qg_content = $this->linkcurl($qg_url, $method, $params, $header);
            $sj_content = $this->linkcurl($sj_url, $method, $params, $header);
            $gs_content = $this->linkcurl($gs_url, $method, $params, $header);

            $qg_result = json_decode($qg_content, true);
            $sj_result = json_decode($sj_content, true);
            $gs_result = json_decode($gs_content, true);
            $data = [];
            $msg = '疫情数据';
            if ($qg_result) {
                if ($qg_result['code'] == '200') {
                    $data['data']['country'] = $qg_result['data'];
                } else {
                    $msg = $qg_result['reason'];
                    $data['result'] = 'error';
                    $data['message'] = $qg_result['reason'];
                }
            } else {
                return self::returnMsg('401', '请求失败', $data);
            }

            if ($sj_result) {
                if ($sj_result['code'] == '200') {
                    $data['data']['world'] = $sj_result['data'];
                } else {
                    $msg = $sj_result['reason'];
                    $data['result'] = 'error';
                    $data['message'] = $sj_result['reason'];
                }
            } else {
                return self::returnMsg('401', '请求失败', $data);
            }

            if ($gs_result) {
                if ($gs_result['code'] == '200') {
                    $data_gs = $gs_result['newslist'];
                    $gs_key = array_search('甘肃省', array_column($data_gs, 'provinceName'));
                    $data['data']['gs'] = $data_gs[$gs_key];
                } else {
                    $msg = $gs_result['reason'];
                    $data['result'] = 'error';
                    $data['message'] = $gs_result['reason'];
                }
            } else {
                return self::returnMsg('401', '请求失败', $data);
            }
            cache($cache_key, $data, 60 * 60);
        }


        return self::returnMsg('200', $msg, $data);
    }

    /**
     * 请求接口返回内容
     * @param string $url [请求的URL地址]
     * @param string $params [请求的参数]
     * @param int $ipost [是否采用POST形式]
     * @return  string
     */
    public function linkcurl($url, $method, $params = false, $header = false)
    {
        $httpInfo = array();
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_FAILONERROR, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        if (1 == strpos("$" . $url, "https://")) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
        } else if ($params) {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
        }
        $response = curl_exec($ch);
        if ($response === FALSE) {
            //echo "cURL Error: " . curl_error($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $httpInfo = array_merge($httpInfo, curl_getinfo($ch));
        curl_close($ch);
        return $response;
    }


}
