<?php
/**
 * PESCMS for PHP 5.4+
 *
 * Copyright (c) 2014 PESCMS (http://www.pescms.com)
 *
 * For the full copyright and license information, please view
 * the file LICENSE.md that was distributed with this source code.
 * @core version 2.6
 * @version 2.0
 */
namespace App\Team\GET;

/**
 * 部门方法
 */
class Task extends Content {

    private $statusMark, $sidebar = ['search'];

    public function __init() {
        parent::__init();
        //任务状态，因为切片已经赋值了模板状态变量，此处直接从模板变量借来的
        $this->statusMark = \Core\Func\CoreFunc::$param['statusMark'];
        if (empty($_GET['status'])) {
            $_GET['status'] = '0';
        }
        if (in_array($_GET['status'], array_keys($this->statusMark))) {
            \Model\Task::$condtion .= ' AND t.task_status = :task_status ';
            \Model\Task::$param['task_status'] = $_GET['status'];
        }
        //状态为666的，表示任务逾期的
        if ($_GET['status'] == '666') {
            \Model\Task::$condtion .= ' AND t.task_end_time < :time AND t.task_status < 2';
            \Model\Task::$param['time'] = time();
        }

        if (!empty($_GET['k'])) {
            \Model\Task::$condtion .= ' AND (t.task_title LIKE :task_title OR t.task_id LIKE :task_id)';
            \Model\Task::$param['task_title'] = \Model\Task::$param['task_id'] = '%' . $this->g('k') . '%';
        }

        $this->assign('sidebar', $this->sidebar);

        $this->assign('title_icon', \Model\Menu::getTitleWithMenu()['menu_icon']);

    }

    /**
     * 任务列表
     * @param string $theme 调用的模板
     */
    public function index() {
        $result = \Model\Task::getTaskList();

        $this->sidebar[] = 'bulletin';

        //状态为所有时，将显示时效图
        if ($_GET['status'] == '233') {
            $this->sidebar[] = 'aging';
            $this->assign('aging', \Model\Task::taskAgingGapFigureLineChart());
        }

        $this->assign('bulletin', \Model\Content::listContent(['table' => 'bulletin', 'order' => 'bulletin_listsort ASC, bulletin_id DESC', 'limit' => '5']));

        $this->assign('sidebar', $this->sidebar);


        $this->assign('list', $result['list']);
        $this->assign('page', $result['page']);
        $this->layout('Task_index');
    }

    /**
     * 个人任务
     */
    public function my() {
        \Model\Task::getUserTask($_SESSION['team']['user_id']);
        $this->index();
    }

    /**
     * 查看指定项目的任务列表
     */
    public function project(){
        $id = $this->isG('id', '请选择您要查看的项目');
        $project = \Model\Content::findContent('project', $id, 'project_id');
        if(empty($project)){
            $this->error('该项目不存在');
        }
        $this->sidebar[] = 'project';
        $this->assign($project);
        \Model\Task::$condtion .= ' AND task_project_id = :task_project_id';
        \Model\Task::$param['task_project_id'] = $id;
        $this->assign('title', "{$project['project_title']}的项目信息");
        $this->index();
    }


    /**
     * 任务看板
     */
    public function myCard() {
        //@todo 默认输出9999条，详细应该没人达到这么可怕的地步吧？
        \Model\Task::$page = 9999;
        $list = [];
        foreach ($this->statusMark as $statusid => $value) {

            \Model\Task::$condtion = 'WHERE t.task_status = :task_status';
            \Model\Task::$param = ['task_status' => $statusid];

            //完成状态的任务看板，仅列出当天完成的。
            if ($statusid == '3') {
                \Model\Task::$condtion .= ' AND task_complete_time >= :today';
                \Model\Task::$param['today'] = strtotime(date('Y-m-d 00:00:00'));
            }

            //@todo 排序需要进一步优化
            \Model\Task::$oder = 'ORDER BY task_submit_time DESC';

            \Model\Task::getUserTask($_SESSION['team']['user_id']);
            $list[$statusid] = $value;
            $list[$statusid]['task'] = \Model\Task::getTaskList()['list'];
        }

        $this->assign('list', $list);

        $this->layout();
    }

    /**
     * 等待审核的任务列表
     */
    public function check() {
        \Model\Task::$condtion = '';
        \Model\Task::$join = " LEFT JOIN {$this->prefix}task_user AS tu ON tu.task_id = t.task_id";
        \Model\Task::$condtion = 'WHERE t.task_status = 2 AND tu.user_id = :user_id AND tu.task_user_type = 1';
        \Model\Task::$param = ['user_id' => $_SESSION['team']['user_id']];

        $result = \Model\Task::getTaskList();
        $this->assign('list', $result['list']);
        $this->assign('page', $result['page']);
        $this->layout('Task_index');
    }

    /**
     * 部门指派列表
     */
    public function department() {
        $department = \Model\Content::findContent('department', $_SESSION['team']['user_department_id'], 'department_id');

        if (empty($department['department_header'])) {
            $this->error('您的部门还没有指派负责人，联系管理员进行设置');
        }

        if (!in_array($_SESSION['team']['user_id'], explode(',', $department['department_header']))) {
            $this->error('您不是本部门的负责人，无法访问本页面');
        }

        \Model\Task::$condtion = '';
        \Model\Task::$join = " LEFT JOIN {$this->prefix}task_user AS tu ON tu.task_id = t.task_id";
        \Model\Task::$condtion = 'WHERE tu.user_id = :department AND tu.task_user_type = 3';
        \Model\Task::$param = ['department' => $_SESSION['team']['user_department_id']];
        $result = \Model\Task::getTaskList();
        $this->assign('list', $result['list']);
        $this->assign('page', $result['page']);
        $this->layout('Task_index');
    }

    /**
     * 查看任务
     */
    public function view() {
        $taskid = $this->isG('id', '请选择您要查看的任务ID');
        $task = \Model\Content::findContent('task', $taskid, 'task_id');
        if (empty($task)) {
            $this->error('任务不存在');
        }

        //验证权限
        $actionAuth = \Model\Task::actionAuth($taskid);
        if ($task['task_read_permission'] == '1' && $actionAuth['check'] == false && $actionAuth['action'] == false && $actionAuth['department'] == false) {
            $this->error('当前任务您没有查阅的权限');
        }

        $param['task_id'] = $taskid;

        //任务追加内容
        $supplement = \Model\Content::listContent(['table' => 'task_supplement', 'condition' => 'task_supplement_task_id = :task_id', 'param' => $param]);

        //任务条目
        $taskList = \Model\Content::listContent(['table' => 'task_list', 'condition' => 'task_id = :task_id', 'param' => $param]);

        //任务动态
        $dynamice = \Model\Content::listContent(['table' => 'task_dynamic', 'condition' => 'task_dynamic_task_id = :task_id', 'order' => 'task_dynamic_createtime DESC', 'param' => $param]);

        $this->assign('supplement', $supplement);
        $this->assign('dynamice', $dynamice);
        $this->assign('taskList', $taskList);
        $this->assign('userAccessList', \Model\Content::listContent([
            'table' => 'task_user',
            'condition' => 'task_id = :task_id',
            'param' => $param
        ]));
        $this->assign('actionAuth', $actionAuth);

        $this->assign('department', \Model\Content::listContent([
            'table' => 'user',
            'condition' => 'user_department_id = :department_id',
            'param' => [
                'department_id' => $_SESSION['team']['user_department_id']
            ]
        ]));
        $this->assign($task);
        $this->layout();
    }


    /**
     * 发表任务
     */
    public function action() {
        //任务发表后，不允许进入编辑页面
        if (!empty($_GET['id'])) {
            header('Location:' . $this->url('Team-Task-action'));
        }
        $userList = \Model\Content::listContent(['table' => 'user', 'field' => 'user_id,user_name,user_department_id', 'condition' => 'user_status = 1']);
        $user = [];
        foreach ($userList as $value) {
            $user['list'][$value['user_id']] = $value['user_name'];
            if ($value['user_department_id'] == $_SESSION['team']['user_department_id']) {
                $user['department'][$value['user_id']] = $value['user_name'];
            }
        }

        $this->assign('user', $user);

        $department = \Model\Content::listContent(['table' => 'department', 'order' => 'department_listsort ASC, department_id DESC']);
        $this->assign('department', $department);

        parent::action();
    }


}
