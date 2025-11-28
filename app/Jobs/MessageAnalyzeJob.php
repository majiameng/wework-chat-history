<?php

namespace App\Jobs;

use App\Models\GroupModel;
use App\Models\MediaModel;
use App\Models\MessageModel;
use App\Models\MessageOriginalModel;
use App\Models\UserModel;
use Illuminate\Support\Facades\Log;

class MessageAnalyzeJob extends CommonJob
{
    public $msgidList;
    public $companyId = 1;
    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($msgidList)
    {
//        Log::info('MessageAnalyzeJob: ', [
//            'msgidList' => json_encode($msgidList),
//        ]);
        $this->msgidList = array_unique($msgidList);
    }

    /**
     * 分析消息并保存到分析表
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if(empty($this->msgidList)){
            Log::info('MessageAnalyzeJob: msgidList 为空');
            return;
        }

        // 获取原始消息列表
        $list = MessageOriginalModel::whereIn('msgid', $this->msgidList)
            ->get()->toArray();
        if(empty($list)){
            Log::info('MessageAnalyzeJob: 未找到原始消息数据', ['msgidList' => $this->msgidList]);
            return;
        }

        $md5sunArray = $roomidArray = $userName = [];// 列表
        foreach ($list as &$original) {
            if($original['action'] != 'send'){
                continue;
            }
            if(!empty($original['md5sum'])) $md5sunArray[] = $original['md5sum'];
            list($roomId,$roomName) = $this->getRoomId($original['roomid'],$original['msgfrom'],$original['tolist']);
            // 用户名
            if(!empty($original['msgfrom']))$userName[] = $original['msgfrom'];
            $original['tolist'] = explode(',',$original['tolist']);
            if(!empty($original['tolist'])){
                $userName = array_merge($userName, $original['tolist']);
            }
            // 群组
            $original['roomid'] = $roomId;
            $roomidArray[$roomId] = [
                'group_name'=>$roomName,
                'list'=>implode(',',array_merge([$original['msgfrom']],$original['tolist'])),
            ];
        }
        unset($original);

        $userList = UserModel::getListByName( array_unique($userName),$this->companyId);
        $groupList = GroupModel::getListByName( $roomidArray,$this->companyId);
        $mediaList = MediaModel::getListByMd5sum( array_unique($md5sunArray));


        $dataList = [];
        foreach ($list as $original) {
            if($original['action'] != 'send'){
                continue;
            }
            // 检查是否已存在（避免重复插入）
            $exists = MessageModel::where('seq', $original['seq'])->first();
            if ($exists) {
                continue;
            }

            // 准备保存的数据
            $data = [
                'company_id' => $original['company_id'],
                'original_message_id' => $original['id'],
                'seq' => $original['seq'],
                'action' => $original['action'],
                'from_user_id' => $userList[$original['msgfrom']]??0,
                'msgfrom' => $original['msgfrom'],
                'group_id' => $groupList[$original['roomid']]??0,
                'msgtype' => $original['msgtype'],
                'msgtime' => $original['msgtime'],
                'text' => mb_substr($original['text'] ?? '', 0, 4000), // 限制长度
                'status' => 1,
                'media_id' => $mediaList[$original['md5sum']]??0,
            ];

            $dataList[] = $data;
        }

        // 批量插入分析后的消息数据
        if (!empty($dataList)) {
            try {
                MessageModel::insert($dataList);
                Log::info('MessageAnalyzeJob: 成功保存 ' . count($dataList) . ' 条分析消息');
            } catch (\Exception $e) {
                Log::error('MessageAnalyzeJob: 批量保存失败', [
                    'count' => count($dataList),
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        } else {
            Log::info('MessageAnalyzeJob: 没有需要保存的数据（可能已存在）');
        }

        Log::info('MessageAnalyzeJob 执行完成');
        return;
    }

    /**
     * @param $roomid
     * @param $msgfrom
     * @param $tolist
     * @return array
     */
    public function getRoomId($roomid,$msgfrom,$tolist)
    {
        if(!empty($roomid)){
            return [$roomid,$roomid];
        }
        $list = [$msgfrom,$tolist];
        sort($list);
        return [md5(implode('|',$list)),implode('|',$list)];
    }

}
