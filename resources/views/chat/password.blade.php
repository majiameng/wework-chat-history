<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>密码验证 - 企业微信聊天记录</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="/static/layui-v2.13.2/css/layui.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .password-container {
            background-color: #fff;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        
        .password-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .password-header h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .password-header p {
            color: #999;
            font-size: 14px;
        }
        
        .password-form {
            margin-top: 30px;
        }
        
        .password-input-group {
            margin-bottom: 20px;
        }
        
        .password-input-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .password-input-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            font-size: 16px;
            transition: border-color 0.3s;
        }
        
        .password-input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .password-submit {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            cursor: pointer;
            transition: opacity 0.3s;
        }
        
        .password-submit:hover {
            opacity: 0.9;
        }
        
        .password-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .password-error {
            margin-top: 15px;
            padding: 10px;
            background-color: #fee;
            border: 1px solid #fcc;
            border-radius: 6px;
            color: #c33;
            font-size: 14px;
            display: none;
        }
        
        .password-error.show {
            display: block;
        }
        
        .password-icon {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .password-icon i {
            font-size: 64px;
            color: #667eea;
        }
    </style>
</head>
<body>
    <div class="password-container">
        <div class="password-header">
            <div class="password-icon">
                <i class="layui-icon layui-icon-password"></i>
            </div>
            <h2>密码验证</h2>
            <p>请输入密码以访问聊天记录</p>
        </div>
        
        <form class="password-form" id="password-form">
            <div class="password-input-group">
                <label for="password">密码</label>
                <input type="password" id="password" name="password" placeholder="请输入访问密码" required autofocus>
            </div>
            
            <button type="submit" class="password-submit" id="submit-btn">
                <i class="layui-icon layui-icon-ok"></i> 验证
            </button>
            
            <div class="password-error" id="error-message"></div>
        </form>
    </div>
    
    <script src="/static/layui-v2.13.2/layui.js"></script>
    <script>
        layui.use(['layer'], function(){
            var layer = layui.layer;
            
            document.getElementById('password-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const password = document.getElementById('password').value;
                const submitBtn = document.getElementById('submit-btn');
                const errorMsg = document.getElementById('error-message');
                
                if (!password) {
                    errorMsg.textContent = '请输入密码';
                    errorMsg.classList.add('show');
                    return;
                }
                
                // 禁用按钮
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="layui-icon layui-icon-loading layui-anim layui-anim-rotate layui-anim-loop"></i> 验证中...';
                errorMsg.classList.remove('show');
                
                // 发送验证请求
                // 使用 FormData 发送，以便 Laravel 自动处理 CSRF token
                const formData = new FormData();
                formData.append('password', password);
                formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                
                fetch('/chat/verify-password', {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 验证成功，跳转到聊天页面
                        window.location.href = '/chat';
                    } else {
                        // 验证失败
                        errorMsg.textContent = data.message || '密码错误';
                        errorMsg.classList.add('show');
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="layui-icon layui-icon-ok"></i> 验证';
                        document.getElementById('password').value = '';
                        document.getElementById('password').focus();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    errorMsg.textContent = '验证失败，请重试';
                    errorMsg.classList.add('show');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="layui-icon layui-icon-ok"></i> 验证';
                });
            });
        });
    </script>
</body>
</html>

