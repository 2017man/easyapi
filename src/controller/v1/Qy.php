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

/**
 * 一码通社区+企业员工健康数据
 * Class Ymt
 * @package app\api\controller\v1
 */
class Qy extends Api
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
    protected $noAuth = [];

    public function __construct(Request $request)
    {
        //检测登陆
        $exp = ['mem_info',];
        $this->check_online_action($exp);
    }


    /**
     * 注册
     */
    public function reg()
    {
        $post = input('post.');
        $mem = $this->decode_member_info();
        if (empty($mem)) return self::returnMsg('200', '人员未授权', ['result' => 'error', 'message' => '人员未授权']);
        $info = Db::table('ymt_qy')->where(['type' => input('type'), 'openid' => $mem['openid']])->find();
        if (!empty($info)) return self::returnMsg('200', '该企业已注册', ['result' => 'error', 'message' => '该企业已注册']);
        $has_type = Db::table('area')->where('parentid', $post['zhen_id'])->where('type', $post['company_type'])->find();

        if(empty($post['comany_name'])){
            $err_msg = ['result' => 'error', 'message' => '请填写企业名称'];
            return self::returnMsg('200', '请请填写企业名称', $err_msg);
        }

        //节点归属到社区下面
        if (!empty($post['zhen_id'])) {
            $level = Db::table('area')->where('id', $post['zhen_id'])->value('nest_depth');
            if ($level !== 3) {
                $err_msg = ['result' => 'error', 'message' => '请选择社区/村级'];
                return self::returnMsg('200', '请选择社区/村级', $err_msg);
            }
        } else {
            $err_msg = ['result' => 'error', 'message' => '请选择社区/村级'];
            return self::returnMsg('200', '请选择社区/村级', $err_msg);
        }
        //创建企业父节点
        try {
            if (empty($has_type)) {
                $parentid = Db::table('area')->insertGetId([
                    'title' => $post['company_type'],
                    'parentid' => $post['zhen_id'],
                    'nest_depth' => 4,
                    'type' => $post['company_type']
                ]);
            } else {
                $parentid = Db::table('area')->where([
                    'title' => $post['company_type'],
                    'parentid' => $post['zhen_id'],
                    'nest_depth' => 4,
                    'type' => $post['company_type']
                ])->value('id');
            }
            if (empty($parentid)) {
                $err_msg = ['result' => 'error', 'message' => '当前行业没有归属节点'];
                return self::returnMsg('200', '当前行业没有归属节点', $err_msg);
            }
            $id = Db::table("area")->insertGetId([
                'title' => $post['comany_name'],
                'parentid' => $parentid,
                'nest_depth' => 5,
                'type' => $post['company_type'],
                'is_wangge' => '是',
            ]);
            $data['company_type'] = $post['company_type'];
            $data['comany_name'] = $post['comany_name'];
            $data['company_idcard'] = $post['company_idcard'];
            $data['company_addr'] = $post['company_addr'];
            $data['company_addr_title'] = $post['company_addr_title'];
            $data['type'] = $post['type'];
            $data['openid'] = $mem['openid'];
            $data['node_id'] = $id;
            $data['add_time'] = time();
            $qrcode = new Qrcode();
            $qr_code = $qrcode->qr_code_qy($id, '');
            if ($qrcode) {
                $data['qrcode'] = $qr_code;
                $data['rework_code'] = substr(md5(uniqid(md5(microtime(true)), true)), 0, 6);
                $qy_id = Db::table('ymt_qy')->insertGetId($data);
                Db::table("member")->where("openid", $mem['openid'])->update(['qy_id' => $qy_id]);
                $data['member_info'] = $mem;
                $data['result'] = 'success';
                $data['data'] = $post;
                return self::returnMsg('200', '注册成功', $data);
            }

        } catch (\Exception $e) {
            $data['result'] = 'error';
            $data['data'] = $e->getMessage();
            return self::returnMsg('200', '错误', $data);
        }

    }

    /*
     * @name  企业信息
     * */
    public function qiye_info()
    {
        $mem = $this->decode_member_info();
        $info = Db::table('ymt_qy')->where(['type' => input('type'), 'openid' => $mem['openid']])->find();
        $data['result'] = 'success';
        $data['data'] = $info;
        return self::returnMsg('200', '查询成功', $data);
    }


}
