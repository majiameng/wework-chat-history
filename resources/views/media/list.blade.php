<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>媒体文件列表</title>
    <link href="/static/layui-v2.13.2/css/layui.css" rel="stylesheet">
    <style>
        body {
            background-color: #f5f5f5;
            padding: 20px;
        }
        .media-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .media-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        .media-header h2 {
            margin: 0;
            font-size: 20px;
            color: #333;
        }
        .media-filter {
            margin-bottom: 15px;
        }
        .media-preview {
            max-width: 100px;
            max-height: 100px;
            border-radius: 4px;
            cursor: pointer;
        }
        .media-preview:hover {
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="media-container">
        <div class="media-header">
            <h2><i class="layui-icon layui-icon-file"></i> 媒体文件列表</h2>
        </div>

        <div class="media-filter">
            <form class="layui-form" lay-filter="mediaFilter">
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">状态筛选</label>
                        <div class="layui-input-inline">
                            <select name="status">
                                <option value="">全部</option>
                                <option value="1">正常</option>
                                <option value="0">异常</option>
                            </select>
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">文件大小</label>
                        <div class="layui-input-inline" style="width: 100px;">
                            <input type="number" name="filesize_min" placeholder="最小值(字节)" autocomplete="off" class="layui-input">
                        </div>
                        <div class="layui-form-mid">-</div>
                        <div class="layui-input-inline" style="width: 100px;">
                            <input type="number" name="filesize_max" placeholder="最大值(字节)" autocomplete="off" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <label class="layui-form-label">MD5</label>
                        <div class="layui-input-inline">
                            <input type="text" name="md5sum" placeholder="输入MD5值" autocomplete="off" class="layui-input">
                        </div>
                    </div>
                </div>
                <div class="layui-form-item">
                    <div class="layui-inline">
                        <label class="layui-form-label">创建时间</label>
                        <div class="layui-input-inline">
                            <input type="text" name="date_range" id="dateRange" placeholder="选择日期范围" autocomplete="off" class="layui-input">
                        </div>
                    </div>
                    <div class="layui-inline">
                        <button type="button" class="layui-btn" id="searchBtn">
                            <i class="layui-icon layui-icon-search"></i> 搜索
                        </button>
                        <button type="reset" class="layui-btn layui-btn-primary" id="resetBtn">
                            <i class="layui-icon layui-icon-refresh"></i> 重置
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <table id="mediaTable" lay-filter="mediaTable"></table>
    </div>

    <!-- 图片预览模板 -->
    <script type="text/html" id="previewTpl">
        @verbatim
        {{# if(d.file_url && (d.file_type === '图片')) { }}
            <img src="{{ d.file_url }}" class="media-preview" onclick="previewImage('{{ d.file_url }}')" />
        {{# } else { }}
            <span style="color: #999;">-</span>
        {{# } }}
        @endverbatim
    </script>

    <!-- 操作列模板 -->
    <script type="text/html" id="actionTpl">
        @verbatim
        {{# if(d.file_url) { }}
            <a class="layui-btn layui-btn-xs" href="{{ d.file_url }}" target="_blank">
                <i class="layui-icon layui-icon-download-circle"></i> 下载
            </a>
            {{# if(d.file_type === '图片') { }}
                <a class="layui-btn layui-btn-xs layui-btn-normal" href="javascript:;" onclick="previewImage('{{ d.file_url }}')">
                    <i class="layui-icon layui-icon-picture"></i> 预览
                </a>
            {{# } }}
        {{# } else { }}
            <span style="color: #999;">无文件</span>
        {{# } }}
        @endverbatim
    </script>

    <!-- 图片预览遮罩 -->
    <div id="image-preview" class="image-preview" onclick="this.style.display='none'">
        <img src="" alt="预览" />
    </div>

    <script src="/static/layui-v2.13.2/layui.js"></script>
    <script>
        layui.use(['table', 'form', 'laydate'], function(){
            var table = layui.table;
            var form = layui.form;
            var laydate = layui.laydate;
            var $ = layui.$;

            // 日期范围选择器
            laydate.render({
                elem: '#dateRange',
                type: 'date',
                range: true,
                format: 'yyyy-MM-dd'
            });

            // 渲染表格
            var tableIns = table.render({
                elem: '#mediaTable',
                url: '/media/list',
                page: true,
                limit: 20,
                limits: [10, 20, 50, 100],
                cols: [[
                    {field: 'media_id', title: 'ID', width: 80, sort: true},
                    {field: 'preview', title: '预览', width: 120, templet: '#previewTpl', align: 'center'},
                    {field: 'file_type', title: '文件类型', width: 100, align: 'center'},
                    {field: 'file_extension', title: '文件后缀', width: 100, align: 'center'},
                    {field: 'filesize_text', title: '文件大小', width: 120, align: 'center'},
                    {field: 'file_url', title: '文件地址', minWidth: 200, templet: function(d){
                        if (d.file_url) {
                            return '<a href="' + d.file_url + '" target="_blank" style="color: #1E9FFF;">' +
                                   (d.file_url.length > 50 ? d.file_url.substring(0, 50) + '...' : d.file_url) +
                                   '</a>';
                        }
                        return '<span style="color: #999;">-</span>';
                    }},
                    {field: 'md5sum', title: 'MD5', width: 200, templet: function(d){
                        return d.md5sum || '<span style="color: #999;">-</span>';
                    }},
                    {field: 'status_text', title: '状态', width: 100, align: 'center', templet: function(d){
                        var color = d.status == 1 ? '#5FB878' : '#FF5722';
                        return '<span style="color: ' + color + ';">' + d.status_text + '</span>';
                    }},
                    {field: 'created_at', title: '创建时间', width: 180, align: 'center'},
                    {field: 'updated_at', title: '更新时间', width: 180, align: 'center'},
                    {title: '操作', width: 180, align: 'center', fixed: 'right', templet: '#actionTpl'}
                ]],
                done: function(res, curr, count){
                    // 表格渲染完成后的回调
                }
            });

            // 搜索按钮
            $('#searchBtn').on('click', function(){
                var status = $('select[name="status"]').val();
                var filesizeMin = $('input[name="filesize_min"]').val();
                var filesizeMax = $('input[name="filesize_max"]').val();
                var md5sum = $('input[name="md5sum"]').val();
                var dateRange = $('input[name="date_range"]').val();

                var where = {};
                if (status !== '') {
                    where.status = status;
                }
                if (filesizeMin !== '') {
                    where.filesize_min = filesizeMin;
                }
                if (filesizeMax !== '') {
                    where.filesize_max = filesizeMax;
                }
                if (md5sum !== '') {
                    where.md5sum = md5sum;
                }
                if (dateRange !== '') {
                    where.date_range = dateRange;
                }

                table.reload('mediaTable', {
                    where: where,
                    page: {
                        curr: 1
                    }
                });
            });

            // 重置按钮
            $('#resetBtn').on('click', function(){
                table.reload('mediaTable', {
                    where: {},
                    page: {
                        curr: 1
                    }
                });
            });

            // 预览图片
            window.previewImage = function(url) {
                var $preview = $('#image-preview');
                $preview.find('img').attr('src', url);
                $preview.show();
            };
        });
    </script>

    <style>
        .image-preview {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            cursor: pointer;
        }
        .image-preview img {
            max-width: 90%;
            max-height: 90%;
        }
    </style>
</body>
</html>

