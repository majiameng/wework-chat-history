# 企业微信聊天记录(图形化)

### 功能明细（开源免费）
* 聊天记录自动拉取
* 聊天记录图片自动下载并保存至【阿里云OSS、腾讯云COS、七牛云】云存储
* 聊天记录自动分析聊天到群组
* 聊天记录展示功能
* 数据同步、异步分析
* 多组织企业微信

## 截图
![登录认证](images/1.png)
![聊天记录](images/2.png)
![媒体文件](images/3.png)

## 软件架构
* 使用语言 PHP 8.2
* 基于 Laravel 8.7 框架
* 项目基于 [tinymeng/wework-finance-sdk](https://github.com/majiameng/wework-finance-sdk-php)  扩展

## 环境要求
- 独立服务器
- CentOS 7.0+ 或 Ubuntu 20.04+
- PHP 7.3+
- MySQL 5.7+
- Apache httpd 2.4+ 或者 Nginx 1.10+

### 相关开源
- [wework-chat-history](https://github.com/majiameng/wework-chat-history)	(php版，含数据入库，图形化)
- [wework-msgaudit](https://github.com/majiameng/wework-msgaudit)	(Java版，含数据入库，无图形化)


## 企微聊天获取服务
```
# 守护进程模式运行
php artisan wework --daemon

# 或者使用简写
php artisan wework -d

# 自定义间隔时间
php artisan wework --daemon --interval=30

# 单次执行（不带 --daemon）
php artisan wework

# 在另一个终端停止服务
php artisan wework --stop
```

异步队列服务
```
php artisan queue:work
# 守护进程模式运行
php artisan queue:work -d	
```

聊天记录表定期备份服务
```
# 正常执行（只在1号执行）
php artisan MessageOriginalTableRotate

# 强制执行（忽略日期检查，用于测试）
php artisan MessageOriginalTableRotate --force
```

聊天记录访问：
```
http://127.0.0.1:8080/chat
密码： 123456
密码修改.env文件里的CHAT_PASSWORD
```

### 扩展或项目部署
加下面合作微信，备注来意。

## CRM CHAT付费版（请具有开发能力）
* 自动匹配企业员工
* 自动匹配企业客户
* CRM项目自动挂载数据
* 快捷查看聊天以及群聊消息，群名称修改，用户名称修改等快捷操作。
![产品规划参考](images/introduction.png)


#### 合作联系：
<img src="images/wx.jpg" alt="微信" style="width: 200px">
