<?php
namespace app\manage\admin;

use app\admin\controller\Admin;
use think\Db;
use think\Request;
use think\Validate;
use app\common\builder\ZBuilder;
use app\user\model\User as AdminModel;

class Adminuser extends Admin
{
	//账号列表
	public function index(){
        //获取筛选参数
        $filter=[
            'username'=>input('param.username',''),
            'nickname'=>input('param.nickname',''),
            'mobile'=>input('param.mobile',''),
        ];
        $filter=fortrim($filter);
        //整理筛选参数
        $map=[];
        if($filter['username']!==''){
            $map['a.username']=['like','%'.$filter['username'].'%'];
        }
        if($filter['nickname']!==''){
            $map['a.nickname']=['like','%'.$filter['nickname'].'%'];
        }
        if($filter['mobile']!==''){
            $map['a.mobile']=['like','%'.$filter['mobile'].'%'];
        }
        //车商
        $ismerchant=ismerchant();
        if($ismerchant){
            $admin_id=db('merchants')->where('id',$ismerchant)->value('admin_id');
            $where="(`b`.`merchant_id`='".$ismerchant."' || `a`.`id`='".$admin_id."')";
        }else{
            $where="";
        }
        $order=input('param.order','a.create_time desc');
        $order=str_replace('+', ' ', $order);
        //查出数据
        $object=db('admin_user')->alias('a')->field('a.id,a.username,a.nickname,a.job,a.mobile,a.create_time,a.status,a.role,b.name role_name,b.merchant_id')->join('admin_role b','a.role=b.id','LEFT')->where($where)->where('a.id','neq','1')->where($map)->order($order)->paginate(15);
        // 获取分页显示
        $page = $object->render();
        $data_all = json_decode(json_encode($object),TRUE);
        $data=$data_all['data'];
        //处理数据
        foreach ($data as $key => $value) {
            $data[$key]['create_time_str']=date('Y-m-d H:i',$value['create_time']);
            $merchant=db('merchants')->field('shop_name')->where('id',$value['merchant_id'])->find();
            if(!$ismerchant && $merchant){
                $data[$key]['merchant']='('.$merchant['shop_name'].')';
            }else{
                $data[$key]['merchant']='';
            }
        }
        //模板赋值
        $this->assign([
            'filter'=>$filter,
            'order'=>$order,
            'data'=>$data,
            'page'=>$page,
        ]);
        //渲染模板
        return $this->fetch();
	}
	//添加
	public function add(){
		$now=time(); 
		if ($this->request->isPost()) {
            $data = $this->request->post();
            $data=fortrim($data);
            if($data['username']===''){
                return json_return('F','1001','登录名必填');
            }
            if(db('admin_user')->where('username',$data['username'])->find()){
                return json_return('F','1001','登录名称已有');
            }
            if(!preg_match('/^[A-Za-z0-9@]{3,20}$/',$data['username'])) {
                return json_return('F','1001','登录名允许数字、大小写字母及@符号（邮箱时）的3-20个字符');
            }
            if($data['password0']===''){
                return json_return('F','1002','登录密码必填');
            }
            if(!preg_match('/^[A-Za-z0-9!@#$%^&*-,.?;]{8,16}$/',$data['password0'])) {
                return json_return('F','1002','登录密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
            }
            $a=preg_match('/[0-9]/', $data['password0']);
            $b=preg_match('/[a-zA-Z]/', $data['password0']);
            $c=preg_match('/[!@#$%^&*-,.?;]/', $data['password0']);
            if(!($a && $b && $c)){
                return json_return('F','1002','登录密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
            }
            if($data['password']===''){
                return json_return('F','1003','确认登录密码必填');
            }
            if($data['password0']!==$data['password']){
                return json_return('F','1003','两次密码输入不一致');
            }
            if($data['nickname']===''){
                return json_return('F','1004','姓名必填');
            }
            if(!preg_match('/^[\x{4e00}-\x{9fa5}]{2,8}$/u',$data['nickname'])) {
                return json_return('F','1004','姓名只允许输入汉字，限制2-8个字符');
            }
            if($data['job']===''){
                return json_return('F','1005','职位必填');
            }
            if(mb_strlen($data['job'],'utf8')<2 || mb_strlen($data['job'],'utf8')>8) {
                return json_return('F','1005','职位限制2-8个字符');
            }
            if($data['mobile']===''){
                return json_return('F','1006','手机号必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['mobile'])) {
                return json_return('F','1006','手机号只允许输入11位数字');
            }
            if($data['role']===''){
                return json_return('F','1007','角色必选');
            }
            $ismerchant=ismerchant();
            if($ismerchant){
                $roles=db('admin_role')->field('id')->where('merchant_id',$ismerchant)->select();
            }else{
                $roles=db('admin_role')->field('id')->where('id','not in','1,2')->select();
            }
            $new_roles=[];
            foreach ($roles as $k => $v) {
                $new_roles[]=$v['id'];
            }
            if(!in_array($data['role'], $new_roles)){
                return json_return('F','1007','角色不存在');
            }
            $insert=[];
            $insert['username']=$data['username'];
            $insert['password']=$data['password'];
            $insert['nickname']=$data['nickname'];
            $insert['job']=$data['job'];
            $insert['mobile']=$data['mobile'];
            $insert['mobile_bind']='1';
            $insert['role']=$data['role'];
            $insert['status']='1';
            $admin=AdminModel::create($insert);
            record_log(request()->module(),request()->controller(),'添加账号');
            return json_return('T','200','添加成功');
        } 
        //查出角色
        $ismerchant=ismerchant();
        if($ismerchant){
            $roles=db('admin_role')->field('id,name')->where('merchant_id',$ismerchant)->select();
        }else{
            $roles=db('admin_role')->field('id,name,merchant_id')->where('id','not in','1,2')->select();
            foreach ($roles as $key => $value) {
                $merchant=db('merchants')->field('shop_name')->where('id',$value['merchant_id'])->find();
                if(!$ismerchant && $merchant){
                    $roles[$key]['name']=$value['name'].'('.$merchant['shop_name'].')';
                }else{
                    $roles[$key]['name']=$value['name'];
                }
            }
        }
        //模板赋值
        $this->assign([
            'roles'=>$roles,
        ]);
        //渲染模板
        return $this->fetch();
	}
    //修改
    public function edit($id=''){
        $now=time(); 
        if ($this->request->isPost()) {
            $data = $this->request->post();
            $data=fortrim($data);
            $ismerchant=ismerchant();
            if($ismerchant){
                $admin_id=db('merchants')->where('id',$ismerchant)->value('admin_id');
                $where="(`b`.`merchant_id`='".$ismerchant."' || `a`.`id`='".$admin_id."')";
            }else{
                $where="";
            }
            $admin_user=db('admin_user')->alias('a')->field('a.id,a.username,a.nickname,a.job,a.mobile,a.create_time,a.status,a.role,b.name role_name')->join('admin_role b','a.role=b.id','LEFT')->where($where)->where('a.id','not in','1,2')->where('a.role','neq','2')->where('a.id',$data['id'])->find();
            if(!$admin_user){
                return json_return('F','1000','请求错误');
            }
            if($data['username']===''){
                return json_return('F','1001','登录名必填');
            }
            if(!preg_match('/^[A-Za-z0-9@]{3,20}$/',$data['username'])) {
                return json_return('F','1001','登录名允许数字、大小写字母及@符号（邮箱时）的3-20个字符');
            }
            if(db('admin_user')->where('username',$data['username'])->where('id','neq',$data['id'])->find()){
                return json_return('F','1001','登录名称已有');
            }
            if($data['password0']!==''){
                if(!preg_match('/^[A-Za-z0-9!@#$%^&*-,.?;]{8,16}$/',$data['password0'])) {
                    return json_return('F','1002','登录密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
                }
                $a=preg_match('/[0-9]/', $data['password0']);
                $b=preg_match('/[a-zA-Z]/', $data['password0']);
                $c=preg_match('/[!@#$%^&*-,.?;]/', $data['password0']);
                if(!($a && $b && $c)){
                    return json_return('F','1002','登录密码允许大小写数字、字母、特殊字符的8-16个字符，必须有其中三项组成');
                }
                if($data['password']===''){
                    return json_return('F','1003','确认登录密码必填');
                }
                if($data['password0']!==$data['password']){
                    return json_return('F','1003','两次密码输入不一致');
                }
            } 
            if($data['nickname']===''){
                return json_return('F','1004','姓名必填');
            }
            if(!preg_match('/^[\x{4e00}-\x{9fa5}]{2,8}$/u',$data['nickname'])) {
                return json_return('F','1004','姓名只允许输入汉字，限制2-8个字符');
            }
            if($data['job']===''){
                return json_return('F','1005','职位必填');
            }
            if(mb_strlen($data['job'],'utf8')<2 || mb_strlen($data['job'],'utf8')>8) {
                return json_return('F','1005','职位限制2-8个字符');
            }
            if($data['mobile']===''){
                return json_return('F','1006','手机号必填');
            }
            if(!preg_match('/^[0-9]{11}$/',$data['mobile'])) {
                return json_return('F','1006','手机号只允许输入11位数字');
            }
            if($data['role']===''){
                return json_return('F','1007','角色必选');
            }
            $ismerchant=ismerchant();
            if($ismerchant){
                $roles=db('admin_role')->field('id')->where('merchant_id',$ismerchant)->select();
            }else{
                $roles=db('admin_role')->field('id')->where('id','not in','1,2')->select();
            }
            $new_roles=[];
            foreach ($roles as $k => $v) {
                $new_roles[]=$v['id'];
            }
            if(!in_array($data['role'], $new_roles)){
                return json_return('F','1000','角色不存在');
            }
            $update=[];
            $update['id']=$data['id'];
            $update['username']=$data['username'];
            if($data['password0']!==''){
                $update['password']=$data['password'];
            }
            $update['nickname']=$data['nickname'];
            $update['job']=$data['job'];
            $update['mobile']=$data['mobile'];
            $update['role']=$data['role'];
            AdminModel::update($update);
            if($data['password0']===''){
                if($data['username']!=$admin_user['username'] || $data['nickname']!=$admin_user['nickname'] || $data['job']!=$admin_user['job'] || $data['mobile']!=$admin_user['mobile'] || $data['role']!=$admin_user['role']){
                    record_log(request()->module(),request()->controller(),'修改账号');
                }
            }else{
                record_log(request()->module(),request()->controller(),'修改账号');
            }
            return json_return('T','200','修改成功');
        } 
        //查出账号
        $ismerchant=ismerchant();
        if($ismerchant){
            $admin_id=db('merchants')->where('id',$ismerchant)->value('admin_id');
            $where="(`b`.`merchant_id`='".$ismerchant."' || `a`.`id`='".$admin_id."')";
        }else{
            $where="";
        }
        $admin_user=db('admin_user')->alias('a')->field('a.id,a.username,a.nickname,a.job,a.mobile,a.create_time,a.status,a.role,b.name role_name')->join('admin_role b','a.role=b.id','LEFT')->where($where)->where('a.id','not in','1,2')->where('a.role','neq','2')->where('a.id',$id)->find();
        if(!$admin_user){
            return $this->error('请求错误');
        }
        //查出角色
        if($ismerchant){
            $roles=db('admin_role')->field('id,name')->where('merchant_id',$ismerchant)->select();
        }else{
            $roles=db('admin_role')->field('id,name,merchant_id')->where('id','not in','1,2')->select();
            foreach ($roles as $key => $value) {
                $merchant=db('merchants')->field('shop_name')->where('id',$value['merchant_id'])->find();
                if(!$ismerchant && $merchant){
                    $roles[$key]['name']=$value['name'].'('.$merchant['shop_name'].')';
                }else{
                    $roles[$key]['name']=$value['name'];
                }
            }
        }
        //模板赋值
        $this->assign([
            'roles'=>$roles,
            'admin_user'=>$admin_user,
        ]);
        //渲染模板
        return $this->fetch();
    }
    //开通
    public function enable($id=''){
        $id=input('param.id'); 
        $ismerchant=ismerchant();
        if($ismerchant){
            $admin_id=db('merchants')->where('id',$ismerchant)->value('admin_id');
            $where="(`b`.`merchant_id`='".$ismerchant."' || `a`.`id`='".$admin_id."')";
        }else{
            $where="";
        }
        $admin_user=db('admin_user')->alias('a')->field('a.id,a.username,a.nickname,a.job,a.mobile,a.create_time,a.status,a.role,b.name role_name')->join('admin_role b','a.role=b.id','LEFT')->where($where)->where('a.id','not in','1,2')->where('a.role','neq','2')->where('a.id',$id)->find();
        if(!$admin_user){
            return json_return('F','500','请求错误');
        }
        $rt=db('admin_user')->where('id',$id)->update(['status'=>'1']);
        if($rt!==false){
            if($rt>0){
                db('admin_user')->where('id',$id)->update(['update_time'=>time()]);
                record_log(request()->module(),request()->controller(),'开通账号');
            }
            return json_return('T','200','开通账号成功');
        }else{
            return json_return('F','500','开通账号失败');
        }
    }
    //冻结
    public function disable($id=''){
        $id=input('param.id'); 
        $ismerchant=ismerchant();
        if($ismerchant){
            $admin_id=db('merchants')->where('id',$ismerchant)->value('admin_id');
            $where="(`b`.`merchant_id`='".$ismerchant."' || `a`.`id`='".$admin_id."')";
        }else{
            $where="";
        }
        $admin_user=db('admin_user')->alias('a')->field('a.id,a.username,a.nickname,a.job,a.mobile,a.create_time,a.status,a.role,b.name role_name')->join('admin_role b','a.role=b.id','LEFT')->where($where)->where('a.id','not in','1,2')->where('a.role','neq','2')->where('a.id',$id)->find();
        if(!$admin_user){
            return json_return('F','500','请求错误');
        }
        $rt=db('admin_user')->where('id',$id)->update(['status'=>'0']);
        if($rt!==false){
            if($rt>0){
                db('admin_user')->where('id',$id)->update(['update_time'=>time()]);
                record_log(request()->module(),request()->controller(),'冻结账号');
            }
            return json_return('T','200','冻结账号成功');
        }else{
            return json_return('F','500','冻结账号失败');
        }
    }
}