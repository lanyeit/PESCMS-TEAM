<?php
/**
 * PESCMS for PHP 5.4+
 *
 * Copyright (c) 2016 PESCMS (http://www.pescms.com)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 */


namespace App\Team\PUT;

class User extends Content {

    /**
     * 更新个人设置
     */
    public function setting() {
        $data['user_name'] = $this->isP('name', '请填写名称');
        $data['user_mail'] = $this->isP('mail', '请填写邮箱地址');
        $data['user_phone'] = $this->p('phone');
        $data['user_home'] = $this->p('home');
        if (!empty($_POST['password'])) {
            $data['user_password'] = \Core\Func\CoreFunc::generatePwd($_SESSION['team']['user_account'] . $this->p('password'));
        }
        $data['noset']['user_id'] = $_SESSION['team']['user_id'];


        $result = $this->db('user')->where('user_id = :user_id')->update($data);
        if ($result !== false) {
            $_SESSION['team'] = \Model\Content::findContent('user', $_SESSION['team']['user_id'], 'user_id');
        }

        $this->success('更新信息成功!');
    }

    /**
     * 更新头像
     */
    public function head() {
        $_GET['action'] = 'uploadimage';
        $result = (new \Expand\UEupload\UEController())->action();
        $info = json_decode($result, true);
        if ($info['state'] != 'SUCCESS') {
            $this->error($info['state']);
        } else {
            $this->db('user')->where('user_id = :user_id')->update([
                'user_head' => $info['url'],
                'noset' => [
                    'user_id' => $_SESSION['team']['user_id']
                ]
            ]);
            $_SESSION['team'] = \Model\Content::findContent('user', $_SESSION['team']['user_id'], 'user_id');
            $this->jump($this->url('Team-User-setting'));
        }
    }

}