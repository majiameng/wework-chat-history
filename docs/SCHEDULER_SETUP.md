# Laravel 定时任务调度器配置指南

## 快速配置

### 步骤 1：配置系统 Cron

编辑 crontab：
```bash
crontab -e
```

添加以下行（**请替换为你的实际项目路径**）：
```bash
* * * * * cd /var/www/wework && php artisan schedule:run >> /dev/null 2>&1
```

**重要说明**：
- `* * * * *` 表示每分钟执行一次（Laravel 调度器会判断哪些任务需要执行）
- `/var/www/wework` 需要替换为你的项目实际路径
- `>> /dev/null 2>&1` 表示将输出重定向到空设备（可选，也可以重定向到日志文件）

### 步骤 2：验证配置

#### 方法 1：查看已注册的任务
```bash
php artisan schedule:list
```

应该能看到类似输出：
```
0 0 1 * *  php artisan MessageOriginalTableRotate  .......... Next Due: 1 month from now
```

#### 方法 2：测试调度器
```bash
# 测试模式（不实际执行）
php artisan schedule:test

# 手动运行一次（执行到期的任务）
php artisan schedule:run
```

#### 方法 3：检查 Cron 日志
```bash
# Ubuntu/Debian
grep CRON /var/log/syslog | tail -20

# CentOS/RHEL
grep CRON /var/log/cron | tail -20
```

## 当前配置的定时任务

### 表轮转任务

- **命令**：`MessageOriginalTableRotate`
- **执行时间**：每月1号 00:00
- **功能**：
  - 将 `message_original` 表重命名为 `message_original_YYYYMM`（如 `message_original_202501`）
  - 创建新的空 `message_original` 表供下月使用
  - 自动检查并等待正在进行的查询完成，确保安全

#### 手动执行
```bash
# 正常执行（只在每月1号执行）
php artisan MessageOriginalTableRotate

# 强制执行（忽略日期检查，用于测试）
php artisan MessageOriginalTableRotate --force
```

#### 执行日志
日志文件位置：`storage/logs/command_MessageOriginalTableRotate.log`

## 常见问题

### Q1: 如何确认 Cron 是否正常工作？

**方法 1：查看调度器列表**
```bash
php artisan schedule:list
```

**方法 2：手动执行调度器**
```bash
php artisan schedule:run -v
```
`-v` 参数会显示详细输出，包括哪些任务被执行了。

**方法 3：查看系统 Cron 日志**
```bash
# Ubuntu/Debian
tail -f /var/log/syslog | grep CRON

# CentOS/RHEL  
tail -f /var/log/cron
```

### Q2: 任务没有执行怎么办？

1. **检查 Cron 服务是否运行**
   ```bash
   # Ubuntu/Debian
   systemctl status cron
   
   # CentOS/RHEL
   systemctl status crond
   ```

2. **检查项目路径是否正确**
   ```bash
   # 在 crontab 中确保路径正确
   cd /your/project/path && php artisan schedule:run
   ```

3. **检查 PHP 路径**
   ```bash
   # 在 crontab 中使用完整路径
   * * * * * cd /var/www/wework && /usr/bin/php artisan schedule:run
   ```

4. **检查文件权限**
   ```bash
   # 确保 artisan 文件有执行权限
   chmod +x artisan
   ```

### Q3: 如何测试表轮转任务？

```bash
# 使用 --force 参数强制执行（忽略日期检查）
php artisan MessageOriginalTableRotate --force
```

### Q4: 如何修改执行时间？

编辑 `app/Console/Kernel.php` 文件，修改 `monthlyOn()` 的参数：

```php
// 每月1号 02:00 执行
$schedule->command('MessageOriginalTableRotate')
    ->monthlyOn(1, '02:00')
    ->withoutOverlapping()
    ->runInBackground();
```

### Q5: 如何查看任务执行日志？

```bash
# 查看命令日志
tail -f storage/logs/command_MessageOriginalTableRotate.log

# 查看 Laravel 日志
tail -f storage/logs/laravel.log
```

## 安全建议

1. **执行时间**：已设置为每月1号 00:00（业务低峰期）
2. **监控**：建议配置日志监控，确保任务正常执行
3. **备份**：在执行表轮转前，建议先备份数据库
4. **测试**：首次使用前，建议在测试环境先执行 `--force` 测试

## 相关文件

- 任务定义：`app/Console/Kernel.php`
- 任务实现：`app/Console/Commands/MessageOriginalTableRotate.php`
- 日志目录：`storage/logs/`

