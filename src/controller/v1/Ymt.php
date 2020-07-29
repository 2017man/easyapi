<?php

namespace app\api\controller\v1;

use app\api\controller\Member;
use think\Controller;
use think\Request;
use app\api\controller\Api;
use app\api\controller\Send;
use think\Db;
use EasyWeChat\Factory;
use app\api\common\Qrcode;
use Aliyun\Sms\Api as SmsApi;
use think\Facade\Cache;

/**
 * 一码通社区+企业员工健康数据
 * Class Ymt
 * @package app\api\controller\v1
 */
class Ymt extends Api
{
    use Send;
    use Member;

    /**
     * 鉴权白名单
     * 不需要鉴权方法
     * index、save不需要鉴权
     * ['index','save']
     * 所有方法都不需要鉴权
     * [*]
     */
    protected $noAuth = ['weather', 'return_mem_edit'];

    public function __construct(Request $request)
    {
        //检测登陆
        $exp = ["ren_info", "admin_scan", "send_sms", "create_randomno", 'weather'];
        $this->check_online_action($exp);
    }

    public function send_sms()
    {

        $config = [
            'accessKeyId' => 'LTAIWO0ujW8aUAbT',
            'accessKeySecret' => 'rjdLnIQemT5HsK6ErEOmpVYpJrNSmN',
            'signName' => '崆峒健康通',
            'defaultTemplate' => 'SMS_193511229'
        ];

        $smsApi = new SmsApi($config);

        //例如模板code的模板内容为：您的验证码为：${code}，该验证码 5 分钟内有效，请勿泄漏于他人。
        $templateCode = "SMS_193511229";
        $code = $this->create_randomno(4);
        //模板参数 code为模板内容里面的变量
        $param = ['code' => $code];
        $phone = input('phone');
        $result = $smsApi->setTemplate($param, $templateCode)->send($phone);
        if ($result->Message == 'OK') {
            cache("verify_no_$phone", $code);

            $ms = ['result' => 'success', 'message' => '发送成功'];
            return self::returnMsg('200', '发送成功', $ms);
        } else {

            $ms = ['result' => 'error', 'message' => '发送失败'];
            return self::returnMsg('200', '发送失败', $ms);
        }


    }

    // 产生随机号；
    public function create_randomno($length)
    {
        $a = array();
        for ($j = 0; $j < $length; $j++) {
            $a[$j] = rand(0, 9);
        }
        $a = implode($a);
        return $a;
    }


    /**
     * 人员注册
     */
    public function mem_add()
    {
        $post = request()->post();
        $mem = $this->decode_member_info();
        unset($post['rd_session']);
        $post['add_time'] = time();
        //身份证校验
        $rule = '/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/';
        $idcard = $post['idcard'];
        $post['idcard'] = encryption($idcard, '18829207752');
        if (!empty($mem['idcard'])) {
            $err_msg = ['result' => 'error', 'message' => '该账号已注册'];
            return self::returnMsg('200', '该账号已注册', $err_msg);
        }

        if (empty($idcard)) {
            $err_msg = ['result' => 'error', 'message' => '请填写证件信息'];
            return self::returnMsg('200', '证件信息缺失', $err_msg);
        }
        if (!preg_match($rule, $idcard)) {
            $err_msg = [
                'result' => 'error',
                'message' => '请输入正确的身份证号',
            ];
            return self::returnMsg('200', '证件信息无效', $err_msg);
        } else {
            $peos = Db::table('member')->where(['idcard' => $idcard])->count();
            if ($peos >= 1) {
                $err_msg = [
                    'result' => 'error',
                    'message' => '当前人员证件信息已存在，请勿重复提交',
                ];
                return self::returnMsg('200', '人员证件信息已存在', $err_msg);
            }
        }
        if (empty($post['node_id'])) {
            $err_msg = [
                'result' => 'error',
                'message' => '请选择所在小区/网点',
            ];
            return self::returnMsg('200', '请选择所在小区/网点', $err_msg);
        } else {
            $level = Db::table('area')->where('id', $post['node_id'])->value('nest_depth');
            if ($level !== 5) {
                $err_msg = ['result' => 'error', 'message' => '请选择所在小区/网点'];
                return self::returnMsg('200', '请选择所在小区/网点', $err_msg);
            }
        }


        $post['state'] = '低风险';
        //*----------解决前端坑-----------*/
        if ($post['is_from_out'] == '是') {
            $post['from_at'] = date("Y-m-d H:i:s", time());
        } else {
            unset($post['from_at']);//来崆时间
            unset($post['from_go_province']);//来源地
            unset($post['from_go_city']);//来源地
            unset($post['from_go_county']);//来源地
            unset($post['train_type']);//交通工具
        }
        if ($post['is_jingwai'] == '否') {//是否来自境外
            unset($post['from_jingwai_area']);
            unset($post['from_jingwai_at']);
        }

        if ($post['is_go_warn'] == '否') {
            unset($post['warn_province']);
            unset($post['warn_city']);
            unset($post['warn_county']);
            unset($post['warn_link']);
        } else {
            if (empty($post['warn_link'])) {
                $err_msg = [
                    'result' => 'error',
                    'message' => '请选择风险地区',
                ];
                return self::returnMsg('200', '请选择风险地区', $err_msg);
            } else {
                $post['warn_province'] = explode('-', $post['warn_link'])[0];
                $post['warn_city'] = explode('-', $post['warn_link'])[1];
                $post['warn_county'] = explode('-', $post['warn_link'])[2];
                $sgid = Db::table('setting_area_warn')->where('title', $post['warn_province'])->value('id');
                $siid = Db::table('setting_area_warn')->where('title', $post['warn_city'])->where('parentid', $sgid)->value('id');
                $xian = Db::table('setting_area_warn')->where('title', $post['warn_county'])->where('parentid', $siid)->find();
                $post['state'] = $xian['warn_type'];
            }
        }

        try {
            Db::table('member')->where('openid', $mem['openid'])->update($post);
            $data['result'] = 'success';
            $data['data'] = $post;
            return self::returnMsg('200', '注册成功', $data);
        } catch (\Exception $e) {
            $data['result'] = 'error';
            $data['message'] = $e->getMessage();
            return self::returnMsg('200', '发生错误', $data);
        }

    }

    /**
     * 个人信息修改
     */
    public function mem_edit()
    {
        $post = request()->post();
        $mem = $this->decode_member_info();
        unset($post['rd_session']);
        $post['update_time'] = time();
        if ($mem['update_times'] >= 2) {
            $err_msg = [
                'result' => 'error',
                'message' => '人员信息修改次数已达上限',
            ];
            return self::returnMsg('200', '人员信息修改次数已达上限', $err_msg);
        }

        if (empty($post['node_id'])) {
            $err_msg = [
                'result' => 'error',
                'message' => '请选择所在小区/网点',
            ];
            return self::returnMsg('200', '请选择所在小区/网点', $err_msg);
        } else {
            $level = Db::table('area')->where('id', $post['node_id'])->value('nest_depth');
            if ($level !== 5) {
                $err_msg = ['result' => 'error', 'message' => '请选择所在小区/网点'];
                return self::returnMsg('200', '请选择所在小区/网点', $err_msg);
            }
        }

        try {
            Db::table('member')->where('openid', $mem['openid'])->update($post);
            Db::table('member')->where('openid', $mem['openid'])->setInc('update_times');
            $data['result'] = 'success';
            $data['data'] = $post;
            return self::returnMsg('200', '修改成功', $data);
        } catch (\Exception $e) {
            $data['result'] = 'error';
            $data['message'] = $e->getMessage();
            return self::returnMsg('200', '发生错误', $data);
        }
    }

    /**
     * 来返（崆）人员登记
     */
    public function return_mem_edit()
    {
        $post = request()->post();
        $mem = $this->decode_member_info();
        unset($post['rd_session']);
        unset($post['name'], $post['phone'], $post['sex'], $post['card_type'], $post['idcard'], $post['node_id']);
        $post['add_time'] = time();
        $post['member_id'] = $mem['id'];
        $post['idcard'] = $mem['idcard'];
        $post['node_id'] = $mem['node_id'];

        $post['state'] = '低风险';
        //*----------解决前端坑-----------*/
        if ($post['is_from_out'] == '是') {
            $post['from_at'] = date("Y-m-d H:i:s", time());
        } else {
            unset($post['from_at']);//来崆时间
            unset($post['from_go_province']);//来源地
            unset($post['from_go_city']);//来源地
            unset($post['from_go_county']);//来源地
            unset($post['train_type']);//交通工具
        }
        if ($post['is_jingwai'] == '否') {//是否来自境外
            unset($post['from_jingwai_area']);
            unset($post['from_jingwai_at']);
        }

        if ($post['is_go_warn'] == '否') {
            unset($post['warn_province']);
            unset($post['warn_city']);
            unset($post['warn_county']);
            unset($post['warn_link']);
        } else {
            if (empty($post['warn_link'])) {
                $err_msg = [
                    'result' => 'error',
                    'message' => '请选择风险地区',
                ];
                return self::returnMsg('200', '请选择风险地区', $err_msg);
            } else {
                $post['warn_province'] = explode('-', $post['warn_link'])[0];
                $post['warn_city'] = explode('-', $post['warn_link'])[1];
                $post['warn_county'] = explode('-', $post['warn_link'])[2];
                $sgid = Db::table('setting_area_warn')->where('title', $post['warn_province'])->value('id');
                $siid = Db::table('setting_area_warn')->where('title', $post['warn_city'])->where('parentid', $sgid)->value('id');
                $xian = Db::table('setting_area_warn')->where('title', $post['warn_county'])->where('parentid', $siid)->find();
                $post['state'] = $xian['warn_type'];
            }
        }
        //核酸检测
        if (!empty($post['is_detected'])) {
            if (empty($post['img_detected'])) {
                $err_msg = [
                    'result' => 'error',
                    'message' => '请上传核酸检测报告',
                ];
                return self::returnMsg('200', '请上传核酸检测报告', $err_msg);
            }
        } else {
            $err_msg = [
                'result' => 'error',
                'message' => '请选择7日内是否做过核酸检测',
            ];
            return self::returnMsg('200', '请选择7日内是否做过核酸检测', $err_msg);
        }

        if (empty($mem['state'])) {
            $mem['state'] = '低风险';
        }
        if ($mem['state'] == '低风险') {
            $up['state'] = $post['state'];
            $up['update_time'] = time();
        } elseif ($mem['state'] == '中风险') {

            if ($post['state'] == '低风险') {
                $up['state'] = $mem['state'];
                $up['update_time'] = time();
            } elseif ($post['state'] == '中风险') {
                $up['state'] = '高风险';
                $up['update_time'] = time();
            } elseif ($post['state'] == '高风险') {
                $up['state'] = $post['state'];
                $up['update_time'] = time();
            }
        } elseif ($mem['state'] == '高风险') {
            $up['state'] = '高风险';
            $up['update_time'] = time();
        }
        if (empty($up['state'])) {
            $err_msg = [
                'result' => 'error',
                'message' => '参数缺失，当前人员健康状态无法获取',
            ];
            return self::returnMsg('200', '参数缺失，当前人员健康状态无法获取', $err_msg);
        }


        Db::startTrans();
        try {
            Db::table('member_re')->insert($post);
            Db::table('member')->where('openid', $mem['openid'])->update($up);

            if ($mem['state'] !== $up['state']) {
                //码色变化记录
                $state_insert = [
                    'node_id' => $mem['node_id'],
                    'idcard' => $mem['idcard'],
                    'pre_state' => $mem['state'],
                    'state' => $up['state'],
                    'add_time' => time(),
                    'type' => '已注册人员来返中高风险区',
                    'reason' => '已注册人员来返中高风险区',
                ];
                Db::table('member_state')->insert($state_insert);
            }

            $data['result'] = 'success';
            $data['data'] = $post;
            Db::commit();
            return self::returnMsg('200', '登记成功', $data);
        } catch (\Exception $e) {
            Db::rollback();
            $data['result'] = 'error';
            $data['message'] = $e->getMessage();
            return self::returnMsg('200', '发生错误', $data);
        }
    }

    /**
     * 个人信息申诉
     * 三色码申诉
     */
    public function mem_appeal()
    {
        $post = request()->post();
        $mem = $this->decode_member_info();
        unset($post['rd_session']);
        $post['add_time'] = time();
        $idcard = $post['idcard'];
        //身份证校验
        $rule = '/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/';
        if (!preg_match($rule, $idcard)) {
            $err_msg = [
                'result' => 'error',
                'message' => '请输入正确的身份证号',
            ];
            return self::returnMsg('200', '证件信息无效', $err_msg);
        }
        if ($post['code_color'] == '黄色') {
            if ($mem['state'] !== '中风险') {
                $err_msg = [
                    'result' => 'error',
                    'message' => '请选择正确当前二维码颜色',
                ];
                return self::returnMsg('200', '请选择正确当前二维码颜色', $err_msg);
            }
        }

        if ($post['code_color'] == '红色') {
            if ($mem['state'] !== '高风险') {
                $err_msg = [
                    'result' => 'error',
                    'message' => '请选择正确当前二维码颜色',
                ];
                return self::returnMsg('200', '请选择正确当前二维码颜色', $err_msg);
            }
        }

        if ($post['name'] !== $mem['name']) {
            $err_msg = [
                'result' => 'error',
                'message' => '注册人员姓名和申诉姓名不一致',
            ];
            return self::returnMsg('200', '注册电话和申诉电话不一致', $err_msg);
        }

        if ($post['phone'] !== $mem['phone']) {
            $err_msg = [
                'result' => 'error',
                'message' => '注册电话和申诉电话不一致',
            ];
            return self::returnMsg('200', '注册电话和申诉电话不一致', $err_msg);
        }
        $post['idcard'] = encryption($idcard, '18829207752');
        if ($post['card_type'] !== $mem['card_type']) {
            $err_msg = [
                'result' => 'error',
                'message' => '注册证件和申诉证件类型不一致',
            ];
            return self::returnMsg('200', '注册证件号和申诉证件号不一致', $err_msg);
        }
        $post['node_id'] = $mem['node_id'];
        if (empty($post['travel_img'])) {
            $err_msg = [
                'result' => 'error',
                'message' => '请上传行程图',
            ];
            return self::returnMsg('200', '请上传行程图', $err_msg);
        } else {
            $post['travel_img'] = implode('#', $post['travel_img']);
        }
        $post['health'] = implode('#', $post['health']);

        $last_record = Db::table('member_appeal_records')
            ->where('idcard', $mem['idcard'])
            ->limit(1)->where('type', '三色码')
            ->order('id', 'desc')
            ->value('check_state');
        if (!empty($last_record)) {
            if (!in_array($last_record, ['防控办审核通过', '防控办审核不通过'])) {
                $err_msg = [
                    'result' => 'error',
                    'message' => '审核中...请勿重复提交',
                ];
                return self::returnMsg('200', '审核中...请勿重复提交', $err_msg);
            }
        }

        try {
            $post['check_state'] = '待街道/乡镇审核';
            $appeal_id = Db::table('member_appeal')->insertGetId($post);
            $insert = [
                'appealid' => $appeal_id,
                'check_state' => $post['check_state'],
                'idcard' => $mem['idcard'],
                'add_time' => time(),
                'type' => '三色码'
            ];
            Db::table('member_appeal_records')->insert($insert);
            $err_msg = [
                'result' => 'success',
                'message' => '申诉成功！',
            ];
            return self::returnMsg('200', '申诉成功!', $err_msg);
        } catch (\Exception $e) {
            $err_msg = [
                'result' => 'error',
                'message' => $e->getMessage(),
            ];
            return self::returnMsg('200', $e->getMessage(), $err_msg);
        }


    }

    /**
     * 个人基本信息
     */
    public function mem_info($rd_session)
    {
        $mem = $this->decode_member_info();
        $info = Db::table('member')->where('openid', $mem['openid'])->find();
        $info['node_info'] = node_info($info['node_id']);
        $info['idcard'] = decryption($info['idcard'], '18829207752');
        $info['add_time'] = date('Y-m-d H:i:s', $info['add_time']);
        $info['phone'] = dataDesensitization($mem['phone'], 3, mb_strlen($mem['phone'], "utf-8") - 5);
        $info['name'] = dataDesensitization($mem['name'], 1, mb_strlen($mem['name'], "utf-8") - 1);
        $info['idcard'] = dataDesensitization(decryption($mem['idcard'], '18829207752'), 1, mb_strlen(decryption($mem['idcard'], '18829207752'), "utf-8") - 3);
        if (empty($info['update_times'])) {
            $info['update_times'] = 0;
        }
        $ret['result'] = 'success';
        $ret['data'] = $info;
        return self::returnMsg('200', '人员信息', $ret);
    }

    /**
     * 电子码
     * @param $rd_session
     */
    public function mem_qrcode($rd_session)
    {
        $mem = $this->decode_member_info();
        $qrcode = new Qrcode();
        $state = empty($mem['state']) ? '低风险' : $mem['state'];
        $qr_code = $qrcode->qr_code_mem(decryption($mem['idcard'], '18829207752'), $state);

        if (!$qr_code) {
            $err_msg = ['result' => 'error', 'message' => '个人二维码生成失败'];
            return self::returnMsg('200', '人员信息', $err_msg);
        }
        $info['name'] = dataDesensitization($mem['name'], 1, mb_strlen($mem['name'], "utf-8") - 1);
        $info['idcard'] = dataDesensitization(decryption($mem['idcard'], '18829207752'), 1, mb_strlen(decryption($mem['idcard'], '18829207752'), "utf-8") - 3);
        $info['state'] = $state;
        $info['card_type'] = $mem['card_type'];
        $info['qr_code'] = $qr_code;
        $info['update_time'] = empty($mem['update_time']) ? date("Y-m-d H:i:s", time()) : date("Y-m-d H:i:s", $mem['update_time']);
        $ret['result'] = 'success';
        $ret['data'] = $info;
        $ret['weather'] = $this->weather();
        return self::returnMsg('200', '个人电子识别码', $ret);
    }

    /*
     * @name 扫码信息
     * */
    public function scan()
    {
        $mem = $this->decode_member_info();
        $node_id = input('node_id');
        $info = Db::table('area')->where('id', $node_id)->find();

        Db::table("ymt_wd_wg")->insertGetId([
            'longitude' => input('longitude'),
            'latitude' => input('latitude'),
            'report_type' => $info['type'],
            'node_id' => $node_id,
            'company_type' => $info['type'],
            'idcard' => $mem['idcard'],
            'add_time' => time()
        ]);
        $mem['name'] = dataDesensitization($mem['name'], 1, mb_strlen($mem['name'], "utf-8") - 1);
        $mem['idcard'] = dataDesensitization(decryption($mem['idcard'], '18829207752'), 1, mb_strlen(decryption($mem['idcard'], '18829207752'), "utf-8") - 3);
        $post_info['member'] = $mem;
        $post_info['area'] = node_info($node_id);
        $post_info['result'] = 'success';
        $post_info['time'] = date("Y-m-d H:i");
        return self::returnMsg('200', '扫码成功', $post_info);
    }

    /*
     *
     * 管理员扫码
     * */
    public function admin_scan()
    {
        /*print_r(encryption(input('idcard'), '18829207752'));die;*/
        $mem = Db::table('member')->where('idcard', encryption(input('idcard'), '18829207752'))->find();

        $node_id = $this->decode_admin_info();
        $info = Db::table('area')->where('id', $node_id['node_id'])->find();
        if ($info['nest_depth'] !== 5) {
            $err_msg = ['result' => 'error', 'message' => '当前人员不是点位管理员,不能进行扫码',];
            return self::returnMsg('200', '当前人员不是点位管理员,不能进行扫码', $err_msg);
        }

        Db::table("ymt_wd_wg")->insertGetId([
            'longitude' => input('longitude'),
            'latitude' => input('latitude'),
            'temperature' => input('temperature'),
            'report_type' => $info['type'],
            'node_id' => $node_id['node_id'],
            'company_type' => $info['type'],
            'idcard' => $mem['idcard'],
            'add_time' => time()
        ]);
        $mem['idcard'] = decryption($mem['idcard'], '18829207752');
        $post_info['member'] = $mem;
        $post_info['area'] = $info;
        $post_info['result'] = 'success';
        return self::returnMsg('200', '温度提交成功', $post_info);
    }

    /*
     * $name  人员信息
     * */
    public function ren_info()
    {
        /* print_r(encryption(input('idcard'), '18829207752'));
         die;*/
        $mem = Db::table('member')->where('idcard', encryption(input('idcard'), '18829207752'))->find();
        $mem['name'] = dataDesensitization($mem['name'], 1, mb_strlen($mem['name'], "utf-8") - 1);
        $mem['idcard'] = dataDesensitization(decryption($mem['idcard'], '18829207752'), 1, mb_strlen(decryption($mem['idcard'], '18829207752'), "utf-8") - 3);
        $post_info['member'] = $mem;
        $post_info['result'] = 'success';
        return self::returnMsg('200', '扫码成功', $post_info);
    }

    /**
     * 健康打卡
     */
    public function health()
    {
        $post = request()->post();
        $mem = $this->decode_member_info();
        unset($post['rd_session']);
        $post['add_time'] = time();

        $insert = [
            'node_id' => $mem['node_id'],
            'idcard' => $mem['idcard'],
            'longitude' => $post['longitude'],
            'latitude' => $post['latitude'],
            'temperature' => $post['temperature'],
            'is_abnormal' => $post['is_abnormal'],
            'add_time' => time()
        ];
        if ($post['temperature'] < 30 || $post['temperature'] > 50) {
            $err_msg = ['result' => 'error', 'message' => '请输入合法温度'];
            return self::returnMsg('200', '请输入合法温度', $err_msg);
        }

        if ($post['is_abnormal'] == '是') {
            if (empty($post['health'])) {
                $err_msg = ['result' => 'error', 'message' => '请选择当前健康状况'];
                return self::returnMsg('200', '请选择当前健康状况', $err_msg);
            }
            $insert['health'] = implode("#", $post['health']);
        }

        $err_msg = ['result' => 'success', 'message' => '打卡成功'];
        Db::table("ymt_wd_health")->insert($insert);
        return self::returnMsg('200', '打卡成功', $err_msg);
    }

    /**
     * 天气状况
     */
    public function weather()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
        $pub_url = $protocol . $_SERVER['HTTP_HOST'];
        $ip = $_SERVER['REMOTE_ADDR'];
        //根据ip获取地理位置
        $addr = $this->get('http://api.map.baidu.com/location/ip?ak=Gi1nULBk8PY6PdBVyqnrT8Aguht5L639&ip=' . $ip);
        $appid = '89282626';
        $appsecret = 'HZrew9Ue';
//        if (empty($ip)) {
        $url = 'https://tianqiapi.com/api?version=v6&appid=' . $appid . '&appsecret=' . $appsecret . "&city=" . '平凉';
        $cache_key = 'weather_city_' . '平凉';
//        }
//        else {
//            $cache_key = 'weather_ip_' . $ip;
//            $url = 'https://tianqiapi.com/api?version=v6&appid=' . $appid . '&appsecret=' . $appsecret . "&ip=" . $ip;
//        }
        if (!empty(cache($cache_key))) {
            $ret = cache($cache_key);
        } else {
            $w = $this->get($url);

            $ret['city'] = $w['city'];
            $ret['wea'] = $w['wea'];
            $ret['tem'] = $w['tem'];
            $ret['tem1'] = $w['tem1'];
            $ret['tem2'] = $w['tem2'];
            $ret['ip'] = $ip;
            $ret['wea_img'] = $pub_url . "/weather/icon/longan/" . $w['wea_img'] . ".png";
            cache($cache_key, $ret, 60 * 60);
        }

        return $ret;

    }

    public function get($url)
    {
        // 创建一个新 cURL 资源
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url); // 需要获取的 URL 地址，也可以在 curl_init() 初始化会话的时候。
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HEADER, false); // 启用时会将头文件的信息作为数据流输出。
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 在尝试连接时等待的秒数。设置为 0，则无限等待。
        curl_setopt($ch, CURLOPT_TIMEOUT, 6); // 允许 cURL 函数执行的最长秒数。
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // TRUE 将 curl_exec() 获取的信息以字符串返回，而不是直接输出。
        $ret = curl_exec($ch);
        curl_close($ch);
        return json_decode($ret, true);
    }


}
