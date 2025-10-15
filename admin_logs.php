<?php
session_start();

// 包含Logger类
require_once 'Logger.php';
require_once 'your_database_functions.php'; // 替换为你的实际文件名

// 简单的权限检查（可以根据需要修改）
$is_admin = isset($_SESSION['admin']) && $_SESSION['admin'] === true;

if (!$is_admin) {
    // 可以添加简单的密码验证
    if ($_POST['admin_password'] ?? null) {
        if ($_POST['admin_password'] === 'your_admin_password') { // 改成你的密码
            $_SESSION['admin'] = true;
            $is_admin = true;
        }
    }
}

if (!$is_admin) {
    // 显示登录表单
    ?>
    <!DOCTYPE html>
    <html lang="zh">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>管理员登录</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                margin: 0;
            }
            .login-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                width: 100%;
                max-width: 400px;
            }
            h2 {
                text-align: center;
                color: #333;
                margin-top: 0;
            }
            .form-group {
                margin-bottom: 20px;
            }
            label {
                display: block;
                margin-bottom: 5px;
                color: #555;
                font-weight: bold;
            }
            input[type="password"] {
                width: 100%;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 5px;
                font-size: 14px;
                box-sizing: border-box;
            }
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 5px rgba(102, 126, 234, 0.5);
            }
            .login-btn {
                width: 100%;
                padding: 10px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 5px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: opacity 0.3s;
            }
            .login-btn:hover {
                opacity: 0.9;
            }
        </style>
    </head>
    <body>
        <div class="login-container">
            <h2>🔐 管理员日志系统</h2>
            <form method="POST">
                <div class="form-group">
                    <label for="password">管理员密码:</label>
                    <input type="password" id="password" name="admin_password" required autofocus>
                </div>
                <button type="submit" class="login-btn">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// 初始化Logger
$logger = new Logger();

// 获取查询参数
$date = $_GET['date'] ?? date('Y-m-d');
$level = $_GET['level'] ?? null;
$type = $_GET['type'] ?? null;
$source = $_GET['source'] ?? 'file'; // file 或 database
$limit = $_GET['limit'] ?? 100;

// 获取日志数据
if ($source === 'database') {
    $logs = $logger->get_logs_from_db($date, $level, $type, $limit);
} else {
    $logs = $logger->get_logs($date, $level, $type, $limit);
}

// 获取可用日期
$available_dates = $logger->get_available_dates();

// 定义日志级别和类型的颜色
$level_colors = [
    'DEBUG' => '#6c757d',
    'INFO' => '#0d6efd',
    'WARNING' => '#ffc107',
    'ERROR' => '#dc3545',
    'CRITICAL' => '#dc3545'
];

$level_bg_colors = [
    'DEBUG' => '#e2e3e5',
    'INFO' => '#cfe2ff',
    'WARNING' => '#fff3cd',
    'ERROR' => '#f8d7da',
    'CRITICAL' => '#842029'
];

$type_colors = [
    'UPLOAD' => '#0dcaf0',
    'PROCESS' => '#6f42c1',
    'DATABASE' => '#fd7e14',
    'SYSTEM' => '#198754',
    'USER' => '#0d6efd',
    'SECURITY' => '#dc3545'
];

?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员日志系统</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            color: #333;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin-bottom: 10px;
            font-size: 28px;
        }
        .header-info {
            font-size: 14px;
            opacity: 0.9;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .control-group {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        .control-item {
            display: flex;
            flex-direction: column;
        }
        label {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
            color: #555;
        }
        select, input {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
        }
        select:focus, input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 5px rgba(102, 126, 234, 0.3);
        }
        .button-group {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5568d3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover {
            background: #5a6268;
        }
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        .btn-danger:hover {
            background: #c82333;
        }
        .logs-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .logs-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logs-count {
            font-weight: 600;
            color: #667eea;
        }
        .logs-list {
            max-height: 800px;
            overflow-y: auto;
        }
        .log-item {
            padding: 15px 20px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 15px;
            transition: background 0.2s;
        }
        .log-item:hover {
            background: #f8f9fa;
        }
        .log-level {
            padding: 5px 12px;
            border-radius: 5px;
            font-weight: 600;
            font-size: 12px;
            width: 80px;
            text-align: center;
            color: white;
            flex-shrink: 0;
        }
        .log-content {
            flex: 1;
            min-width: 0;
        }
        .log-header {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .log-type {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 4px;
            background-color: #f0f0f0;
            color: white;
            font-size: 12px;
            font-weight: 600;
        }
        .log-time {
            color: #6c757d;
            font-size: 13px;
            font-weight: 500;
        }
        .log-meta {
            color: #999;
            font-size: 12px;
        }
        .log-message {
            color: #333;
            line-height: 1.5;
            word-break: break-word;
        }
        .log-data {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-top: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            color: #555;
            overflow-x: auto;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
            margin: 10px 0;
        }
        .stat-label {
            font-size: 14px;
            color: #6c757d;
        }
        .empty-state {
            text-align: center;
            padding: 40px;
            color: #999;
        }
        .pagination {
            padding: 15px 20px;
            text-align: center;
            background: #f8f9fa;
            border-top: 1px solid #dee2e6;
        }
        .logout-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.3);
            color: white;
            padding: 8px 15px;
            border: 1px solid rgba(255,255,255,0.5);
            border-radius: 5px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: rgba(255,255,255,0.5);
        }
        @media (max-width: 768px) {
            .control-group {
                grid-template-columns: 1fr;
            }
            .button-group {
                flex-direction: column;
            }
            .log-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .logs-list {
                max-height: 600px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <button onclick="logout()" class="logout-btn">登出</button>
        <h1>📊 管理员日志系统</h1>
        <div class="header-info">
            实时查看系统日志、错误报告和关键操作
        </div>
    </div>

    <div class="container">
        <!-- 统计卡片 -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-label">总日志数</div>
                <div class="stat-value"><?php echo count($logs); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">错误数</div>
                <div class="stat-value" style="color: #dc3545;">
                    <?php echo count(array_filter($logs, fn($l) => $l['level'] === 'ERROR' || $l['level'] === 'CRITICAL')); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">警告数</div>
                <div class="stat-value" style="color: #ffc107;">
                    <?php echo count(array_filter($logs, fn($l) => $l['level'] === 'WARNING')); ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label">数据源</div>
                <div class="stat-value" style="color: #198754; font-size: 16px;">
                    <?php echo $source === 'database' ? '数据库' : '文件系统'; ?>
                </div>
            </div>
        </div>

        <!-- 控制面板 -->
        <div class="controls">
            <form method="GET" id="filterForm">
                <div class="control-group">
                    <div class="control-item">
                        <label for="date">日期:</label>
                        <select id="date" name="date" onchange="document.getElementById('filterForm').submit()">
                            <?php foreach ($available_dates as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $date === $d ? 'selected' : ''; ?>>
                                    <?php echo date('Y年m月d日', strtotime($d)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="control-item">
                        <label for="level">日志级别:</label>
                        <select id="level" name="level" onchange="document.getElementById('filterForm').submit()">
                            <option value="">全部</option>
                            <option value="DEBUG" <?php echo $level === 'DEBUG' ? 'selected' : ''; ?>>调试</option>
                            <option value="INFO" <?php echo $level === 'INFO' ? 'selected' : ''; ?>>信息</option>
                            <option value="WARNING" <?php echo $level === 'WARNING' ? 'selected' : ''; ?>>警告</option>
                            <option value="ERROR" <?php echo $level === 'ERROR' ? 'selected' : ''; ?>>错误</option>
                            <option value="CRITICAL" <?php echo $level === 'CRITICAL' ? 'selected' : ''; ?>>严重</option>
                        </select>
                    </div>
                    <div class="control-item">
                        <label for="type">操作类型:</label>
                        <select id="type" name="type" onchange="document.getElementById('filterForm').submit()">
                            <option value="">全部</option>
                            <option value="UPLOAD" <?php echo $type === 'UPLOAD' ? 'selected' : ''; ?>>上传</option>
                            <option value="PROCESS" <?php echo $type === 'PROCESS' ? 'selected' : ''; ?>>处理</option>
                            <option value="DATABASE" <?php echo $type === 'DATABASE' ? 'selected' : ''; ?>>数据库</option>
                            <option value="SYSTEM" <?php echo $type === 'SYSTEM' ? 'selected' : ''; ?>>系统</option>
                            <option value="USER" <?php echo $type === 'USER' ? 'selected' : ''; ?>>用户</option>
                            <option
