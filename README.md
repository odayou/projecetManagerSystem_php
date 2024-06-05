# PearProject

**魔改自"Pear，梨子项目管理系统"**

**相关资料：https://www.yuque.com/bzsxmz**

**安装指南：https://www.yuque.com/bzsxmz/siuq1w/kggzna**

**采用Php V3.3.9**

需要配合[前端项目](https://github.com/a54552239/pearProject)使用，链接：https://github.com/a54552239/pearProject

有不明白的地方的可以加群：275264059，或者联系我，QQ：545522390
### 演示地址
> [https://home.vilson.xyz](https://home.vilson.xyz)

### 登录 ###
账号：123456 密码：123456

### 友情链接 ###

**JAVA版本：https://gitee.com/wulon/mk-teamwork-server**

### 界面截图 ###
![1](https://static.vilson.xyz/overview/1.png)

![1](https://static.vilson.xyz/overview/2.png)

![1](https://static.vilson.xyz/overview/3.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568905177-dfaae477-7edd-4862-8b73-04af5aa2c174.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568918658-c51079e5-5995-45ad-a073-b89f6919aee0.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568949579-f01eeaca-2052-44d6-be7d-eb58011732f3.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562568992455-a8ccee61-3757-42b4-9ffb-0be73ce94d96.png)

![1](https://static.vilson.xyz/overview/8.png)

![1](https://static.vilson.xyz/overview/9.png)

![1](https://static.vilson.xyz/overview/10.png)

![1](https://static.vilson.xyz/overview/11.png)

![1](https://static.vilson.xyz/overview/12.png)

![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562569075060-d41ae959-fca4-460e-a123-2ccff6ac6208.png)

### 功能设计 ###
![1](https://cdn.nlark.com/yuque/0/2019/png/196196/1562467192538-6a4a949a-1dad-411e-af9f-ddec3f553276.png)
        
### 鼓励一下 ###
<img src="https://static.vilson.xyz/pay/wechat.png" alt="Sample"  width="150" height="150">

<img src="https://static.vilson.xyz/pay/alipay2.png" alt="Sample"  width="150" height="150">

### 魔改内容

#### 功能

- 工时增加了结束时间
- 工时的开始时间存储为时间戳，而不是格式化的字符串，便于计算查询
- 增加当日、本周工时统计api
- 首页增加当日、本周工时统计板块
- 自动根据工时的起止时间，计算工时的时间花费

#### 数据库

- 任务工时表增加了结束时间、时间戳类型的开始时间

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
