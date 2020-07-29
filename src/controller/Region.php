<?php

namespace app\api\controller;

use app\api\controller\Api;
use Think\Db;

/**
 * Class CouponController
 * @package Home\Controller
 * @name 区域解析
 */
class Region extends Api
{

    use Send;

    /**
     * 不需要鉴权方法
     * index、save不需要鉴权
     * ['index','save']
     * 所有方法都不需要鉴权
     * [*]
     */
    protected $noAuth = ['next', 'get_xq_by_sq', 'warn', 'warnstr'];

    public function next($parentid)
    {
        $options = Db::table('area')->where('parentid', $parentid)->whereNull('type')->select();
        $msg = '区域信息';
        switch ($options[0]['nest_depth']) {

            case 0:
                $msg = '市';
                break;
            case 1:
                $msg = '区/县';
                break;
            case 2:
                $msg = '街道/镇';
                break;
            case 3:
                $msg = '社区/村';
                break;
            case 4:
                $msg = '网格';
                break;
            case 5:
                $msg = '小区/网点';
                break;
        }
        $data = ['result' => 'success', 'data' => $options];
        return self::returnMsg('200', $msg, $data);
    }

    /**
     * @param 通过社区id获取小区
     */
    public function get_xq_by_sq($sqid)
    {
        $wg_ids = Db::table('area')->where('parentid', $sqid)->whereNull('type')->column('id');
        $msg = '小区';
        $options = Db::table('area')->whereIn('parentid', $wg_ids)->whereNull('type')->where('nest_depth', 5)->select();
        $data = ['result' => 'success', 'data' => $options];
        return self::returnMsg('200', $msg, $data);
    }

    /**
     * 风险地区
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function warn()
    {
        $link = [];
        $shengs = Db::table('setting_area_warn')->where('parentid', 0)->select();
        if (!empty($shengs)) {
            foreach ($shengs as $sk => $sv) {
                $shis = Db::table('setting_area_warn')->where('parentid', $sv['id'])->select();
                if (!empty($shis)) {
                    foreach ($shis as $svvk => $svvv) {
                        $xians = Db::table('setting_area_warn')->where('parentid', $svvv['id'])->select();
                        if (!empty($xians)) {
                            foreach ($xians as $xk => $xv) {
                                $link[] = $sv['title'] . '-' . $svvv['title'] . '-' . $xv['title'];
                            }
                        }
                    }
                }

            }
        }
        $msg = '中风险地区';
        $data = ['result' => 'success', 'data' => $link];
        return self::returnMsg('200', $msg, $data);

    }

    public function warnstr()
    {
        $str = '';
        $link = [];
        $shengs = Db::table('setting_area_warn')->where('parentid', 0)->select();
        if (!empty($shengs)) {
            foreach ($shengs as $sk => $sv) {
                $shis = Db::table('setting_area_warn')->where('parentid', $sv['id'])->select();
                if (!empty($shis)) {
                    foreach ($shis as $svvk => $svvv) {
                        $xians = Db::table('setting_area_warn')->where('parentid', $svvv['id'])->select();
                        if (!empty($xians)) {
                            foreach ($xians as $xk => $xv) {
                                $link[] = $sv['title'] . $svvv['title'] . $xv['title'];
                            }
                        }
                    }
                }

            }
        }
        $str = implode(';', $link);
        $msg = '中风险地区字符串';
        $data = ['result' => 'success', 'data' => $str];
        return self::returnMsg('200', $msg, $data);

    }


    public function city_options()
    {
        $user = $this->user;
        $node_id = $this->node_id;

        if (!empty($node_id)) {
            $ids = [];
            if ($user['role_id'] == 7) {//县
                $ids = Db::table("area")->where('id', parent_id_area($node_id))->value('id');
            } elseif ($user['role_id'] == 12) {//镇
                $ids = Db::table("area")->where('id', parent_id_area(parent_id_area($node_id)))->value('id');
            } elseif ($user['role_id'] == 13) {//村
                $ids = Db::table("area")->where('id', parent_id_area(parent_id_area(parent_id_area($node_id))))->value('id');
            } elseif ($user['role_id'] == 14) {//网
                $ids = Db::table("area")->where('id', parent_id_area(parent_id_area(parent_id_area(parent_id_area($node_id)))))->value('id');
            } elseif ($user['role_id'] == 31) {//市
                $ids = $node_id;
            }
            if (!empty($ids)) $map[] = ['id', 'in', $ids];
        }
        $map[] = ['parentid', '=', 1];
        $ret = Db::table('area')->where('nest_depth', 1)->select();
        return_json(['result' => 'success', 'data' => $ret]);
    }


    public function county_options()
    {
        $city_id = $this->post['city_id'];
        $user = $this->user;
        $node_id = $this->node_id;

        if (!empty($node_id)) {
            $ids = [];
            if ($user['role_id'] == 7) {//县
                $ids = $node_id;
            } elseif ($user['role_id'] == 12) {//镇
                $ids = Db::table("area")->where('id', parent_id_area($node_id))->value('id');
            } elseif ($user['role_id'] == 13) {//村
                $ids = Db::table("area")->where('id', parent_id_area(parent_id_area($node_id)))->value('id');
            } elseif ($user['role_id'] == 14) {//网
                $ids = Db::table("area")->where('id', parent_id_area(parent_id_area(parent_id_area($node_id))))->value('id');
            }
            if (!empty($ids)) $map[] = ['id', 'in', $ids];
        }
        $map[] = ['parentid', '=', $city_id];
        $ret = Db::table('area')->where('nest_depth', 2)->where($map)->select();
        return_json(['result' => 'success', 'data' => $ret]);
    }

    public function town_options()
    {
        $county_id = $this->post['county_id'];
        $user = $this->user;
        $node_id = $this->node_id;
        if (!empty($node_id)) {
            $ids = [];
            if ($user['role_id'] == 12) {//镇
                $ids = $node_id;
            } elseif ($user['role_id'] == 13) {//村
                $ids = Db::table("area")->where('id', parent_id_area($node_id))->value('id');
            } elseif ($user['role_id'] == 14) {//网
                $ids = Db::table("area")->where('id', parent_id_area(parent_id_area($node_id)))->value('id');
            }
            if (!empty($ids)) $map[] = ['id', 'in', $ids];
        }
        $map[] = ['parentid', '=', $county_id];
        $ret = Db::table('area')->where('nest_depth', 3)->where($map)->select();
        return_json(['result' => 'success', 'data' => $ret]);
    }

    public function vil_options()
    {
        $town_id = $this->post['town_id'];
        $user = $this->user;
        $node_id = $this->node_id;
        if (!empty($node_id)) {
            $ids = [];
            if ($user['role_id'] == 13) {//村
                $ids = $node_id;
            } elseif ($user['role_id'] == 14) {//网
                $ids = Db::table("area")->where('id', parent_id_area($node_id))->value('id');
            }
            if (!empty($ids)) $map[] = ['id', 'in', $ids];
        }
        $map[] = ['parentid', '=', $town_id];
        $ret = Db::table('area')->where('nest_depth', 4)->where($map)->select();
        return_json(['result' => 'success', 'data' => $ret]);
    }

    public function wang_options()
    {
        $vil_id = $this->post['vil_id'];
        $user = $this->user;
        $node_id = $this->node_id;
        if (!empty($node_id)) {
            $ids = [];
            if ($user['role_id'] == 14) {//网
                $ids = $node_id;
            }
            if (!empty($ids)) $map[] = ['id', 'in', $ids];
        }
        $map[] = ['parentid', '=', $vil_id];
        $ret = Db::table('area')->where('nest_depth', 5)->where($map)->select();
        return_json(['result' => 'success', 'data' => $ret]);
    }


}
