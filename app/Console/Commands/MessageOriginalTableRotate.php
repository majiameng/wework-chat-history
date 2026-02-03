<?php
namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Input\InputOption;

class MessageOriginalTableRotate extends CommonCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'MessageOriginalTableRotate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '每月1号将message_original表重命名为月份表，并创建新表';

    /**
     * 获取命令选项
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, '强制执行，忽略日期检查'],
        ];
    }

    /**
     * 执行命令
     * @param array $params
     * @return void
     */
    public function main($params = [])
    {
        $today = Carbon::now();
        $force = $this->option('force');

        // 检查是否是每月1号（除非强制执行）
        if (!$force && $today->day != 1) {
            $this->writeln('今天不是1号，跳过表轮转操作（使用 --force 参数可强制执行）');
            return;
        }

        // 获取上个月的年月（格式：202501）
        $lastMonth = $today->copy()->subMonth();
        $yearMonth = $lastMonth->format('Ym');
        $newTableName = 'message_original_' . $yearMonth;
        $originalTableName = 'message_original';

        try {
            // 检查原表是否存在
            $tableExists = $this->tableExists($originalTableName);
            if (!$tableExists) {
                $this->writeln("表 {$originalTableName} 不存在，跳过轮转操作");
                return;
            }

            // 检查目标表是否已存在
            $targetExists = $this->tableExists($newTableName);
            if ($targetExists) {
                $this->writeln("目标表 {$newTableName} 已存在，跳过轮转操作");
                return;
            }

            $this->writeln("开始表轮转：{$originalTableName} -> {$newTableName}");

            // 获取数据库连接
            $connection = DB::connection();
            $databaseName = $connection->getDatabaseName();
            $prefix = $connection->getTablePrefix();

            // 检查是否有正在进行的查询/事务（等待它们完成）
            $this->waitForTableQueries($originalTableName, $prefix);

            // 先创建新表（从原表复制结构）
            $this->writeln("正在创建新表结构...");
            $tempNewTableName = $originalTableName . '_new';
            $this->createNewTable($tempNewTableName, $originalTableName, $prefix);

            // 原子性重命名：在一个语句中完成两个重命名操作（MySQL的RENAME TABLE是原子操作）
            // 这样可以最小化对正在运行的任务的影响
            $this->writeln("正在执行原子性表重命名操作（此操作会短暂锁定表）...");
            $sql = "RENAME TABLE
                    `{$prefix}{$originalTableName}` TO `{$prefix}{$newTableName}`,
                    `{$prefix}{$tempNewTableName}` TO `{$prefix}{$originalTableName}`";
            DB::statement($sql);
            $this->writeln("表重命名成功：{$originalTableName} -> {$newTableName}，并创建了新的 {$originalTableName} 表");


            $this->writeln("表轮转完成：{$originalTableName} 已重命名为 {$newTableName}，并创建了新的 {$originalTableName} 表");
            Log::info("表轮转成功", [
                'old_table' => $newTableName,
                'new_table' => $originalTableName,
                'year_month' => $yearMonth
            ]);

        } catch (\Exception $e) {
            $this->writeln("表轮转失败：" . $e->getMessage());
            Log::error("表轮转失败", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * 检查表是否存在
     * @param string $tableName
     * @return bool
     */
    private function tableExists($tableName)
    {
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();
        $prefix = $connection->getTablePrefix();
        $fullTableName = $prefix . $tableName;

        $sql = "SELECT COUNT(*) as count FROM information_schema.tables
                WHERE table_schema = ? AND table_name = ?";

        $result = DB::select($sql, [$databaseName, $fullTableName]);
        return $result[0]->count > 0;
    }

    /**
     * 等待表上的查询完成
     * @param string $tableName 表名
     * @param string $prefix 表前缀
     * @param int $maxWaitSeconds 最大等待时间（秒）
     * @return void
     */
    private function waitForTableQueries($tableName, $prefix, $maxWaitSeconds = 30)
    {
        $connection = DB::connection();
        $databaseName = $connection->getDatabaseName();
        $fullTableName = $prefix . $tableName;

        $startTime = time();
        $checkInterval = 2; // 每2秒检查一次

        while (true) {
            // 检查是否有正在进行的查询
            $sql = "SELECT COUNT(*) as count FROM information_schema.processlist
                    WHERE db = ? AND (info LIKE ? OR info LIKE ?) AND command != 'Sleep'";

            $result = DB::select($sql, [
                $databaseName,
                "%`{$fullTableName}`%",
                "%{$tableName}%"
            ]);

            $activeQueries = $result[0]->count ?? 0;

            if ($activeQueries == 1) {
                $this->writeln("表 {$tableName} 当前无活跃查询，可以安全执行轮转");
                break;
            }

            $elapsed = time() - $startTime;
            if ($elapsed >= $maxWaitSeconds) {
                $this->writeln("警告：等待超时（{$maxWaitSeconds}秒），仍有 {$activeQueries} 个活跃查询，继续执行可能影响正在运行的任务");
                $this->writeln("建议：等待业务低峰期再执行，或使用 --force 参数强制继续");
                if (!$this->option('force')) {
                    throw new \Exception("表 {$tableName} 仍有活跃查询，为安全起见已取消操作。使用 --force 可强制执行");
                }
                break;
            }

            $this->writeln("检测到 {$activeQueries} 个活跃查询，等待中... ({$elapsed}/{$maxWaitSeconds}秒)");
            sleep($checkInterval);
        }
    }

    /**
     * 创建新表（复制原表结构）
     * @param string $newTableName 新表名
     * @param string $sourceTableName 源表名
     * @param string $prefix 表前缀
     * @return void
     */
    private function createNewTable($newTableName, $sourceTableName, $prefix)
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            // MySQL: 使用 CREATE TABLE ... LIKE 复制表结构
            $sql = "CREATE TABLE `{$prefix}{$newTableName}` LIKE `{$prefix}{$sourceTableName}`";
            DB::statement($sql);
            $this->writeln("已创建新表：{$newTableName}（结构复制自 {$sourceTableName}）");
        } else {
            // 其他数据库：获取 CREATE TABLE 语句并修改表名
            $databaseName = $connection->getDatabaseName();
            $sql = "SHOW CREATE TABLE `{$prefix}{$sourceTableName}`";
            $result = DB::select($sql);

            if (!empty($result)) {
                $createTableSql = $result[0]->{'Create Table'};
                // 替换表名
                $createTableSql = str_replace(
                    "CREATE TABLE `{$prefix}{$sourceTableName}`",
                    "CREATE TABLE `{$prefix}{$newTableName}`",
                    $createTableSql
                );
                // 移除 AUTO_INCREMENT 值（让新表从1开始）
                $createTableSql = preg_replace('/AUTO_INCREMENT=\d+/', '', $createTableSql);

                DB::statement($createTableSql);
                $this->writeln("已创建新表：{$newTableName}（结构复制自 {$sourceTableName}）");
            } else {
                throw new \Exception("无法获取表 {$sourceTableName} 的结构");
            }
        }
    }
}

