<?php
namespace App\Console\Commands;

use App\Services\WeWorkServices;
use Symfony\Component\Console\Input\InputOption;

class WeWork extends CommonCommand
{

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'wework';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = '企业微信定时任务';

    /**
     * 是否持续运行
     * @var bool
     */
    protected $running = true;

    /**
     * 停止文件路径
     * @var string
     */
    protected $stopFile;

    /**
     * 获取命令选项
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['daemon', 'd', InputOption::VALUE_NONE, '以守护进程模式运行，持续执行'],
            ['interval', 'i', InputOption::VALUE_OPTIONAL, '执行间隔时间（秒），默认60秒', 60],
            ['stop', null, InputOption::VALUE_NONE, '停止正在运行的守护进程'],
        ];
    }

    /**
     * @return void
     */
    public function main($params = [])
    {
        // 获取命令选项
        $stop = $this->option('stop');
        $daemon = $this->option('daemon');
        $interval = (int)($this->option('interval') ?? 60); // 默认60秒间隔

        // 停止命令
        if ($stop) {
            $this->stopDaemon();
            return;
        }

        // 注册信号处理器，用于优雅退出（仅 Linux/Unix 系统）
        if (function_exists('pcntl_signal') && PHP_OS_FAMILY !== 'Windows') {
            pcntl_signal(SIGTERM, [$this, 'signalHandler']);
            pcntl_signal(SIGINT, [$this, 'signalHandler']);
        }

        $this->writeln('企业微信服务启动，间隔时间：' . $interval . '秒');

        // 持续运行模式
        if ($daemon) {
            // 设置停止文件路径
            $this->stopFile = storage_path('app/wework_stop.lock');
            // 删除旧的停止文件（如果存在）
            if (file_exists($this->stopFile)) {
                @unlink($this->stopFile);
            }
            $this->writeln('提示：要停止服务，请按 Ctrl+C 或运行: php artisan wework --stop');
            $this->runDaemon($interval);
        } else {
            // 单次执行模式
            $this->runOnce();
        }
    }

    /**
     * 单次执行
     * @return void
     */
    protected function runOnce()
    {
        try {
            (new WeWorkServices())->index();
            $this->writeln('执行成功');
        } catch (\Exception $e) {
            $this->errorToEmail($e);
        }
    }

    /**
     * 守护进程模式，持续运行
     * @param int $interval 执行间隔（秒）
     * @return void
     */
    protected function runDaemon($interval = 60)
    {
        $iteration = 0;

        while ($this->running) {
            // 在执行任务前检查停止条件
            if ($this->checkStopSignal()) {
                break;
            }

            $iteration++;
            $this->writeln("开始第 {$iteration} 次执行");

            try {
                (new WeWorkServices())->index();
                
                // 任务完成后再次检查停止条件
                if ($this->checkStopSignal()) {
                    $this->writeln("任务执行完成，收到停止信号，正在退出...");
                    break;
                }
                
                $this->writeln("第 {$iteration} 次执行成功");
            } catch (\Exception $e) {
                $this->errorToEmail($e);
                $this->writeln("第 {$iteration} 次执行失败: " . $e->getMessage());
            }

            // 如果还在运行状态，则等待指定时间（分段等待以便检查停止条件）
            if ($this->running) {
                $this->writeln("等待 {$interval} 秒后继续...");
                $this->sleepWithCheck($interval);
            }
        }

        $this->writeln('服务已停止');
    }

    /**
     * 信号处理器，用于优雅退出
     * @param int $signal
     * @return void
     */
    public function signalHandler($signal)
    {
        $this->writeln('收到退出信号，正在停止服务...');
        $this->running = false;
    }

    /**
     * 停止守护进程
     * @return void
     */
    protected function stopDaemon()
    {
        $stopFile = storage_path('app/wework_stop.lock');

        // 创建停止文件
        if (!file_exists($stopFile)) {
            file_put_contents($stopFile, date('Y-m-d H:i:s'));
            $this->writeln('已发送停止信号，守护进程将在0.5秒内检测到并停止');
            $this->writeln('停止文件路径: ' . $stopFile);
            $this->writeln('注意：如果任务正在执行，将等待当前任务完成后停止');
        } else {
            $this->writeln('停止信号已存在，守护进程应该正在停止中...');
        }
    }

    /**
     * 检查停止信号
     * @return bool 如果收到停止信号返回 true
     */
    protected function checkStopSignal()
    {
        // 检查停止文件（Windows 和 Linux 都支持）
        if ($this->stopFile && file_exists($this->stopFile)) {
            $this->writeln('检测到停止文件，正在停止服务...');
            @unlink($this->stopFile); // 删除停止文件
            $this->running = false;
            return true;
        }

        // 处理信号（用于优雅退出，仅 Linux/Unix 系统）
        if (function_exists('pcntl_signal_dispatch') && PHP_OS_FAMILY !== 'Windows') {
            pcntl_signal_dispatch();
        }

        return false;
    }

    /**
     * 分段睡眠并检查停止条件
     * @param int $seconds 总睡眠时间（秒）
     * @return void
     */
    protected function sleepWithCheck($seconds)
    {
        $chunk = 0.5; // 每次检查间隔0.5秒，提高响应速度
        $elapsed = 0;

        while ($elapsed < $seconds && $this->running) {
            usleep((int)($chunk * 1000000)); // 使用微秒级睡眠
            $elapsed += $chunk;

            // 检查停止信号
            if ($this->checkStopSignal()) {
                break;
            }
        }
    }

}
