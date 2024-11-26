# ProjecetManagerSystem_php

**魔改自[Pear，梨子项目管理系统](https://github.com/a54552239/pearProjectApi), 鸣谢**

**相关资料：https://www.yuque.com/bzsxmz**

**安装指南：https://www.yuque.com/bzsxmz/siuq1w/kggzna**

**基于PHP V7.3.9部署否则使用问题挺多的**

需要配合[前端项目](https://github.com/odayou/projecetManagerSystem_vue)使用
## 调试
在/.env中可以设置配置信息 如开启调试模式、cache系统的配置

### 登录 ###
账号：123456 密码：123456

### 魔改内容

#### 功能

- 工时增加了结束时间
- 工时的开始时间存储为时间戳，而不是格式化的字符串，便于计算查询
- 增加当日、本周工时统计api
- 自动根据工时的起止时间计算花费的工时

#### 数据库

- 任务工时表增加了结束时间、时间戳类型的开始时间字段，改变后的表如下

```sql
CREATE TABLE `pear_task_work_time` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `task_code` varchar(30) DEFAULT '0' COMMENT '任务ID',
  `member_code` varchar(30) DEFAULT '' COMMENT '成员id',
  `create_time` varchar(30) DEFAULT NULL,
  `content` varchar(500) DEFAULT NULL COMMENT '描述',
  `begin_time` varchar(30) DEFAULT NULL COMMENT '开始时间',
  `num` decimal(10,3) DEFAULT '0.000' COMMENT '工时',
  `code` varchar(30) DEFAULT NULL COMMENT 'id',
  `done_time` int(12) NOT NULL COMMENT '开始时间',
  `end_time` int(12) NOT NULL COMMENT '结束时间',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE,
  UNIQUE KEY `code` (`code`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 ROW_FORMAT=COMPACT COMMENT='任务工时表';
```
