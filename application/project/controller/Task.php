<?php

namespace app\project\controller;

use app\common\Model\CommonModel;
use app\common\Model\Member;
use app\common\Model\ProjectLog;
use app\common\Model\TaskTag;
use app\common\Model\TaskToTag;
use app\common\Model\TaskWorkTime;
use app\common\Model\Project;
use controller\BasicApi;
use Exception;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\facade\Request;

/**
 */
class Task extends BasicApi
{
    public function __construct()
    {
        parent::__construct();
        if (!$this->model) {
            $this->model = new \app\common\Model\Task();
        }
    }

    /**
     * 显示资源列表
     * @return void
     * @throws DbException
     */
    public function index()
    {
        $where = [];
        $params = Request::only('stageCode,pcode,keyword,order,projectCode,deleted');
        foreach (['stageCode', 'pcode', 'deleted', 'projectCode'] as $key) {
            if ($key == 'projectCode') {
                (isset($params[$key]) && $params[$key] !== '') && $where[] = ['project_code', '=', $params[$key]];
                continue;
            }
            (isset($params[$key]) && $params[$key] !== '') && $where[] = [$key, '=', $params[$key]];
        }
        if (isset($params['keyword'])) {
            $where[] = ['name', 'like', "%{$params['keyword']}%"];
        }
        $order = 'sort asc,id asc';
        if (isset($params['order'])) {
            $order = $params['order'];
        }
        $list = $this->model->_list($where, $order);
        if ($list['list']) {
            foreach ($list['list'] as &$task) {
                $task['executor'] = Member::where(['code' => $task['assign_to']])->field('name,avatar')->find();
            }
        }
        $this->success('', $list);
    }

    /**
     * 项目时间段任务统计
     */
    public function dateTotalForProject()
    {
        $projectCode = Request::post('projectCode');
        $list = $this->model->dateTotalForProject($projectCode);
        $this->success('', $list);
    }

    /**
     * 获取自己的任务
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function selfList()
    {
        $taskType = Request::post('taskType', 1);
        $type = Request::post('type', 0);
        $memberCode = Request::post('memberCode', '');
        if (!$memberCode) {
            $member = getCurrentMember();
        } else {
            $member = Member::where(['code' => $memberCode])->find();
        }
        $done = 1;
        if (!$type) {
            $done = 0;
        }
        $type == -1 && $done = $type;
        $list = $this->model->getMemberTasks($member['code'], $done, $taskType, Request::post('page'), Request::post('pageSize'));
        $status = [0 => '普通', 1 => '紧急', 2 => '非常紧急'];
        if ($list['list']) {
            foreach ($list['list'] as &$task) {
                $taskInfo = \app\common\Model\Task::get($task['id']);
                $task['parentDone'] = $taskInfo['parentDone'];
                $task['hasUnDone'] = $taskInfo['hasUnDone'];
                $task['priText'] = $status[$task['pri']];
                $task['executor'] = Member::where(['code' => $task['assign_to']])->field('name,avatar')->find();
                $task['projectInfo'] = \app\common\Model\Project::where(['code' => $task['project_code']])->field('name,code')->find();
            }
        }
        $this->success('', $list);
    }

    public function taskSources()
    {
        $code = Request::post('taskCode');
        try {
            $list = $this->model->taskSources($code);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
        $this->success('', $list);
    }

    public function getListByTaskTag()
    {
        $taskTagCode = Request::param('taskTagCode');
        $page = Request::param('page', 1);
        $pageSize = Request::param('pageSize', cookie('pageSize'));
        $prefix = config('database.prefix');
        $sql = "select *,t.id as id,t.code as code from {$prefix}task_to_tag as tt join {$prefix}task as t on tt.task_code = t.code where tt.tag_code = '{$taskTagCode}' order by t.id desc";
        $list = CommonModel::limitByQuery($sql, $page, $pageSize);
        if ($list['list']) {
            foreach ($list['list'] as &$task) {
                $task['tags'] = TaskToTag::where(['task_code' => $task['code']])->field('id', true)->order('id asc')->select()->toArray();
                $task['executor'] = null;
                if ($task['assign_to']) {
                    $task['executor'] = Member::where(['code' => $task['assign_to']])->field('name,code,avatar')->find();
                }
            }
        }
        $this->success('', $list);
    }

    public function read(Request $request)
    {
        //todo 隐私模式阅读权限
        $data = $request::only('taskCode');
        try {
            $result = $this->model->read($data['taskCode']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());
        }
        if ($result) {
            $this->success('', $result);
        }
    }

    /**
     * 新增
     */
    public function save(Request $request)
    {
        $data = $request::only('name,stage_code,project_code,assign_to,pcode');
        if (!isset($data['assign_to'])) {
            $data['assign_to'] = '';
        }
        if (!isset($data['pcode'])) {
            $data['pcode'] = '';
        }
        if (!$request::post('name')) {
            $this->error("请填写任务标题");
        }
        $member = getCurrentMember();
        if ($data['pcode']) {
            $parentTask = $this->model->where(['code' => $data['pcode']])->find();
            if (!$parentTask) {
                $this->error('父任务无效', 5);
            }
            if ($parentTask['deleted']) {
                $this->error('父任务在回收站中无法编辑', 6);
            }
            $data['project_code'] = $parentTask['project_code'];
            $data['stage_code'] = $parentTask['stage_code'];
        }
        $result = $this->model->createTask($data['stage_code'], $data['project_code'], $data['name'], $member['code'], $data['assign_to'], $data['pcode']);
        if (!isError($result)) {
            $this->success('', $result);
        }
        $this->error($result['msg']);
    }

    /**
     * 执行任务
     * @param Request $request
     */
    public function taskDone(Request $request)
    {
        $data = $request::only('taskCode,done');
        if (!$request::post('taskCode')) {
            $this->error("请选择任务");
        }
        try {
            $result = $this->model->taskDone($data['taskCode'], $data['done']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 指派任务
     * @param Request $request
     */
    public function assignTask(Request $request)
    {
        $data = $request::only('taskCode,executorCode');
        if (!$request::post('taskCode')) {
            $this->error("请选择任务");
        }
        try {
            $result = $this->model->assignTask($data['taskCode'], $data['executorCode']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }


    /**
     * 批量
     * 指派任务
     * @param Request $request
     */
    public function batchAssignTask(Request $request)
    {
        $taskCodes = $request::param('taskCodes');
        $executorCode = $request::param('executorCode');
        if ($taskCodes) {
            $result = $this->model->batchAssignTask(json_decode($taskCodes), $executorCode);
            if (isError($result)) {
                $this->error($result['msg'], $result['errno']);
            }
        }
        $this->success();
    }

    /**
     * 排序
     * @param Request $request
     */
    public function sort(Request $request)
    {
        $data = $request::only('preTaskCode,nextTaskCode,toStageCode');
        if (!$request::post('preTaskCode')) {
            $this->error("参数有误");
        }
        try {
            $this->model->sort($data['preTaskCode'], $data['nextTaskCode'], $data['toStageCode']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success();
    }

    /**
     * 发表评论
     * @param Request $request
     */
    public function createComment(Request $request)
    {
        $data = $request::only('taskCode,comment,mentions');
        if (!$request::post('taskCode')) {
            $this->error("请选择任务");
        }
        if (isset($data['mentions'])) {
            $data['mentions'] = json_decode($data['mentions']);
        }
        try {
            $result = $this->model->createComment($data['taskCode'], $data['comment'], $data['mentions']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        if ($result) {
            $this->success('', $result);
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 保存
     * @param Request $request
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function edit(Request $request)
    {
        $data = $request::only('name,sort,end_time,begin_time,pri,description,work_time,status');
        $code = $request::post('taskCode');
        if (!$code) {
            $this->error("请选择一个任务");
        }
        $template = $this->model->where(['code' => $code])->field('id')->find();
        if (!$template) {
            $this->error("该任务已失效");
        }
        try {
            $result = $this->model->edit($code, $data);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;

        }
        if ($result) {
            $this->success();
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 设置隐私模式
     * @param Request $request
     * @throws Exception
     */
    public function setPrivate(Request $request)
    {
        $private = intval($request::post('private', 0));
        $code = $request::post('taskCode');
        if ($private === 0 || $private === 1) {
            $result = $this->model->edit($code, ['private' => $private]);
            if ($result) {
                $this->success();
            }
            $this->error("操作失败，请稍候再试！");
        }
        $this->success();
    }

    /**
     * 点赞
     * @param Request $request
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function like(Request $request)
    {
        $data = $request::only('like');
        $code = $request::post('taskCode');
        if (!$code) {
            $this->error("请选择一个任务");
        }
        $template = $this->model->where(['code' => $code])->field('id')->find();
        if (!$template) {
            $this->error("该任务已失效");
        }
        try {
            $result = $this->model->like($code, $data['like']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;

        }
        if ($result) {
            $this->success();
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * 任务标签列表
     * @param Request $request
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function taskToTags(Request $request)
    {
        $taskCode = $request::param('taskCode');
        $tags = TaskToTag::where(['task_code' => $taskCode])->field('id', true)->select()->toArray();
        $this->success('', $tags);
    }

    /**
     * 设置标签
     * @param Request $request
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function setTag(Request $request)
    {
        $tagCode = $request::param('tagCode');
        $taskCode = $request::param('taskCode');
        if (!$taskCode) {
            $this->error("请选择一个任务");
        }
        if (!$tagCode) {
            $this->error("请选择一个标签");
        }
        TaskTag::setTag($tagCode, $taskCode);
        $this->success();
    }

    /**
     * 收藏
     * @param Request $request
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function star(Request $request)
    {
        $data = $request::only('star');
        $code = $request::post('taskCode');
        if (!$code) {
            $this->error("请选择一个任务");
        }
        $task = $this->model->where(['code' => $code])->field('id')->find();
        if (!$task) {
            $this->notFound();
        }
        try {
            $result = $this->model->star($code, $data['star']);
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;

        }
        if ($result) {
            $this->success();
        }
        $this->error("操作失败，请稍候再试！");
    }

    /**
     * @throws DbException
     */
    public function taskLog()
    {
        $taskCode = Request::post('taskCode');
        $showAll = Request::post('all', 0);
        $onlyComment = Request::post('comment', 0);
        $where = [];
        $where[] = ['source_code', '=', $taskCode];
        $where[] = ['action_type', '=', 'task'];
        if ($onlyComment) {
            $where[] = ['is_comment', '=', 1];
        }
        $projectLogModel = new ProjectLog();
        if ($showAll) {
            $list = [];
            $list['list'] = $projectLogModel->where($where)->order('id asc')->select()->toArray();
            $list['total'] = count($list['list']);
        } else {
            $list = $projectLogModel->_list($where, 'id asc');
            if ($list['list']) {
                $list['list'] = array_reverse($list['list']);
            }
        }
        if ($list['list']) {
            foreach ($list['list'] as &$item) {
                if ($item['is_robot'] && $item['type'] != 'claim') {
                    $item['member'] = ['name' => 'PP Robot'];
                    continue;
                }
                $member = Member::where(['code' => $item['member_code']])->field('id,name,avatar,code')->find();
                !$member && $member = [];
                $item['member'] = $member;
            }
        }
        $this->success('', $list);
    }

    /**
     * 工时间录
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function _taskWorkTimeList()
    {
        $taskCode = Request::param('taskCode');
        $workTimeList = TaskWorkTime::where(['task_code' => $taskCode])->order('id desc')->select()->toArray();
        if ($workTimeList) {
            foreach ($workTimeList as &$workTime) {
                $member = Member::where(['code' => $workTime['member_code']])->field('avatar,name')->find();
                $workTime['member'] = $member;
            }
        }
        $this->success('', $workTimeList);
    }

    /**
     * 记录工时
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function saveTaskWorkTime()
    {
        $param = Request::only('beginTime,num,content,taskCode,endTime');
        $result = TaskWorkTime::createData($param['taskCode'], getCurrentMember()['code'], $param['num'], $param['beginTime'], $param['endTime'], $param['content']);
        if (isError($result)) {
            $this->error($result['msg'], $result['errno']);
        }
        $this->success();
    }

    /**
     * 修改工时
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function editTaskWorkTime()
    {
        $param = Request::only('beginTime,endTime,num,content');
        $code = Request::param('code');
        if ($code) {
            $workTime = TaskWorkTime::where(['code' => $code])->find();
            if (!$workTime) {
                return error(1, '该记录已失效');
            }
        }
        if (isset($param['beginTime'])) {
            $param['begin_time'] = $param['beginTime'];
            $param['done_time'] = strtotime($param['beginTime']);
            $param['end_time'] = $param['endTime'];
       
            if ( $param['end_time'] < $param['done_time']) {
                return error(7, '结束时间不能小于开始时间');
            }
            if ($param['end_time']) {
            // 根据$doneTimeValue，$endTimeValue 计算消耗了几个小时
                $param['num'] = diffHours($param['done_time'], $param['end_time']);
            }
            unset($param['beginTime']);
            unset($param['endTime']);
        }
        $result = TaskWorkTime::update($param, ['code' => $code]);
        $this->success();
    }

    /**
     * 获取本周的工时
     */
    public function _getThisWeekWorkTime()
    {
        // 根据接收到的参数workTimeRangeType决定查询哪个时间范围的数据，枚举有：本周、本月、上周+本周、近7天、近30天
        $workTimeRangeType = Request::param('workTimeRangeType');
        $workTimeListResult = [];
        if ($workTimeRangeType == '本周') {
            // 获取本周的工时
            $workTimeListResult = TaskWorkTime::whereTime('done_time', 'w')->select()->toArray();
        } elseif ($workTimeRangeType == '本月') {
            // 获取本月的工时
            $workTimeListResult = TaskWorkTime::whereTime('done_time', 'm')->select()->toArray();
        } elseif ($workTimeRangeType == '上周+本周') {
            // 获取本周及上周工时
            $workTimeListResult = TaskWorkTime::whereTime('done_time', 'between', [strtotime('last week monday'), strtotime('next monday')])->select()->toArray();
        } elseif ($workTimeRangeType == '近7天')
        {
            // 获取近7天的工时
            $workTimeListResult = TaskWorkTime::whereTime('done_time', 'between', [strtotime('-7 days'), time()])->select()->toArray();
        } 
        elseif ($workTimeRangeType = '近30天') {
            // 获取近30天的工时
            $workTimeListResult = TaskWorkTime::whereTime('done_time', 'between', [strtotime('-30 days'), time()])->select()->toArray();
        }else {
            // 查询工时大于0的
            $workTimeListResult = TaskWorkTime::whereTime('done_time', '>0')->select()->toArray();
        }


        // 按任务分组后查询出task_code的详细信息和其所属的项目
     
        // 按日分组
        $workTimeListByDay = array_reduce($workTimeListResult, function ($result, $current) {
            $result[date("Y-m-d",$current['done_time'])][] = $current;
            return $result;
        }, []);
        if (empty($workTimeListByDay)) {
            $this->success('', []);
        }
        $taskList = $this->model->where([['code', 'in', array_unique(array_column($workTimeListResult, 'task_code'))]])->select()->toArray();
        $parentTaskList = $this->model->where([['code', 'in', array_unique(array_column($taskList, 'pcode'))]])->select()->toArray();
        // 以$parentTaskList的code作为key，生成map
        $parentTaskMap = array_reduce($parentTaskList, function ($result, $current) {
            $result[$current['code']] = $current;
            return $result;
        }, []);
        $projectList = Project::where([['code', 'in', array_unique(array_column($taskList, 'project_code'))]])->select()->toArray();
        $result = [];
        foreach ($workTimeListByDay as $key => $workTimeList) {
            // 按任务分组
            // task_code相同时合并为同一个但是num加总
            $workTimeList = array_reduce($workTimeList, function ($result, $current) {
                if (isset($result[$current['task_code']])) {
                    $result[$current['task_code']]['num'] += $current['num'];
                } else {
                    $result[$current['task_code']] = $current;
                }
                // 工时明细
                $result[$current['task_code']]['time_detail'][] = array('start_time' => $current['done_time'], 'end_time' => $current['end_time']);
                return $result;
            }, []);
            // $workTimeList中所有num加总
            $totalNum = array_reduce($workTimeList, function ($result, $current) {
                $result += $current['num'];
                return $result;
            }, 0);
            $result[$key]['totalNum'] = $totalNum;
            foreach ($workTimeList as $key1 => $value) {
                foreach ($taskList as $k2 => $v) {
                    if ($value['task_code'] == $v['code']) {
                        foreach ($projectList as $p => $pv) {
                            if ($v['project_code'] == $pv['code']) {
                                $parentTask = $parentTaskMap[$v['pcode']] ?? [];
                                $result[$key]['list'][] = [
                                    'task_code' => $value['task_code'],
                                    'name' => $v['name'],
                                    'project_code' => $v['project_code'],
                                    'project_name' => $pv['name'],
                                    'done_time' => $value['done_time'],
                                    'num' => $value['num'],
                                    'content' => $value['content'],
                                    'parentTaskCode' => $parentTask['code'] ?? "",
                                    'parentTaskName' => $parentTask['name'] ?? "",
                                    'parentTaskProjectCode' => $parentTask['project_code'] ?? "",
                                    'time_detail' => $value['time_detail'],
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        $this->success('', $result);
    }

    /**
     * 按任务获取今天的工时总计，并附带任务id、所属项目id
     */
    public function _getTodayWorkTime()
    {
        // 按任务分组后查询出task_code的详细信息和其所属的项目
        $workTimeList = TaskWorkTime::whereTime('done_time', 'd')->select()->toArray();
        // task_code相同时合并为同一个但是num加总
        $result = [];
        $workTimeList = array_reduce($workTimeList, function ($result, $current) {
            if (isset($result[$current['task_code']])) {
                $result[$current['task_code']]['num'] += $current['num'];
            } else {
                $result[$current['task_code']] = $current;
            }
            return $result;
        }, []);
        $taskList = $this->model->where([['code', 'in', array_unique(array_column($workTimeList, 'task_code'))]])->select()->toArray();
        $projectList = Project::where([['code', 'in', array_unique(array_column($taskList, 'project_code'))]])->select()->toArray();
        foreach ($workTimeList as $key => $value) {
            foreach ($taskList as $k => $v) {
                if ($value['task_code'] == $v['code']) {
                    foreach ($projectList as $p => $pv) {
                        if ($v['project_code'] == $pv['code']) {
                            $result[] = [
                                'task_code' => $value['task_code'],
                                'name' => $v['name'],
                                'project_code' => $v['project_code'],
                                'project_name' => $pv['name'],
                                'done_time' => $value['done_time'],
                                'num' => $value['num'],
                                'content' => $value['content']
                            ];
                        }
                    }
                }
            }
        }
        $this->success('', $result);
    }


    /**
     * 删除工时
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function delTaskWorkTime()
    {
        $code = Request::param('code');
        if ($code) {
            $workTime = TaskWorkTime::where(['code' => $code])->find();
            if (!$workTime) {
                return error(1, '该记录已失效');
            }
        }
        $result = TaskWorkTime::destroy(['code' => $code]);
        $this->success();
    }


    /**
     * 下载导入任务模板
     */
    public function _downloadTemplate()
    {
        return download(env('root_path') . 'data/template/importTask.xlsx', '批量导入任务模板.xlsx');
    }

    /**
     * 上传文件
     */
    public function uploadFile()
    {
        $projectCode = Request::param('projectCode');
        $count = $this->model->uploadFile(Request::file('file'), $projectCode, getCurrentMember()['code']);
        if (isError($count)) {
            $this->error($count['msg']);
        }
        $this->success('', $count);
    }

    /**
     * 批量放入回收站
     */
    public function recycleBatch()
    {
        try {
            $this->model->recycleBatch(Request::post('stageCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

    /**
     * 放入回收站
     */
    public function recycle()
    {
        try {
            $this->model->recycle(Request::post('taskCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

    /**
     * 恢复
     */
    public function recovery()
    {
        try {
            $this->model->recovery(Request::post('taskCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }

    /**
     * 彻底删除
     */
    public function delete()
    {
        try {
            $this->model->del(Request::post('taskCode'));
        } catch (Exception $e) {
            $this->error($e->getMessage(), $e->getCode());;
        }
        $this->success('');
    }
}
