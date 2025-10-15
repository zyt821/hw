<?php
// Logger.php - 日志系统类

class Logger {
    private $log_file;
    private $log_dir;
    
    // 日志级别
    const LEVEL_DEBUG = 'DEBUG';
    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';
    const LEVEL_CRITICAL = 'CRITICAL';
    
    // 日志类别
    const TYPE_UPLOAD = 'UPLOAD';
    const TYPE_PROCESS = 'PROCESS';
    const TYPE_DATABASE = 'DATABASE';
    const TYPE_SYSTEM = 'SYSTEM';
    const TYPE_USER = 'USER';
    const TYPE_SECURITY = 'SECURITY';
    
    public function __construct($log_dir = null) {
        // 设置日志目录
        $this->log_dir = $log_dir ?: __DIR__ . '/logs';
        
        // 创建日志目录
        if (!is_dir($this->log_dir)) {
            mkdir($this->log_dir, 0755, true);
        }
        
        // 设置当前日志文件
        $this->log_file = $this->log_dir . '/app_' . date('Y-m-d') . '.log';
    }
    
    /**
     * 记录日志
     */
    public function log($level, $type, $message, $data = null, $user_id = null) {
        $timestamp = date('Y-m-d H:i:s');
        $ip = $this->get_client_ip();
        
        // 构建日志记录
        $log_entry = [
            'timestamp' => $timestamp,
            'level' => $level,
            'type' => $type,
            'message' => $message,
            'data' => $data,
            'user_id' => $user_id,
            'ip' => $ip
        ];
        
        // 写入文件
        $this->write_log_file($log_entry);
        
        // 同时写入数据库（可选）
        $this->write_log_database($log_entry);
    }
    
    /**
     * 快速日志方法
     */
    public function debug($type, $message, $data = null, $user_id = null) {
        $this->log(self::LEVEL_DEBUG, $type, $message, $data, $user_id);
    }
    
    public function info($type, $message, $data = null, $user_id = null) {
        $this->log(self::LEVEL_INFO, $type, $message, $data, $user_id);
    }
    
    public function warning($type, $message, $data = null, $user_id = null) {
        $this->log(self::LEVEL_WARNING, $type, $message, $data, $user_id);
    }
    
    public function error($type, $message, $data = null, $user_id = null) {
        $this->log(self::LEVEL_ERROR, $type, $message, $data, $user_id);
    }
    
    public function critical($type, $message, $data = null, $user_id = null) {
        $this->log(self::LEVEL_CRITICAL, $type, $message, $data, $user_id);
    }
    
    /**
     * 写入日志文件
     */
    private function write_log_file($log_entry) {
        $line = $this->format_log_line($log_entry);
        file_put_contents($this->log_file, $line . PHP_EOL, FILE_APPEND);
    }
    
    /**
     * 写入数据库
     */
    private function write_log_database($log_entry) {
        try {
            // 确保数据库连接函数存在
            if (!function_exists('connectToDatabase')) {
                return;
            }
            
            $conn = connectToDatabase();
            
            // 创建日志表（如果不存在）
            $this->create_log_table($conn);
            
            $stmt = $conn->prepare(
                "INSERT INTO application_logs (timestamp, level, type, message, data, user_id, ip) 
                VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            
            $data_json = $log_entry['data'] ? json_encode($log_entry['data']) : null;
            $timestamp = $log_entry['timestamp'];
            $level = $log_entry['level'];
            $type = $log_entry['type'];
            $message = $log_entry['message'];
            $user_id = $log_entry['user_id'];
            $ip = $log_entry['ip'];
            
            $stmt->bind_param(
                "sssssss",
                $timestamp,
                $level,
                $type,
                $message,
                $data_json,
                $user_id,
                $ip
            );
            
            $stmt->execute();
            $stmt->close();
            $conn->close();
        } catch (Exception $e) {
            // 数据库写入失败，但不影响文件日志
            error_log("数据库日志写入失败: " . $e->getMessage());
        }
    }
    
    /**
     * 创建日志表
     */
    private function create_log_table($conn) {
        $sql = "CREATE TABLE IF NOT EXISTS application_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            timestamp DATETIME NOT NULL,
            level VARCHAR(20) NOT NULL,
            type VARCHAR(50) NOT NULL,
            message TEXT NOT NULL,
            data JSON,
            user_id VARCHAR(50),
            ip VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_timestamp (timestamp),
            INDEX idx_level (level),
            INDEX idx_type (type)
        )";
        
        $conn->query($sql);
    }
    
    /**
     * 格式化日志行
     */
    private function format_log_line($log_entry) {
        $timestamp = $log_entry['timestamp'];
        $level = $log_entry['level'];
        $type = $log_entry['type'];
        $message = $log_entry['message'];
        $ip = $log_entry['ip'];
        $user_id = $log_entry['user_id'] ?: 'N/A';
        
        $line = "[$timestamp] [$level] [$type] User:$user_id IP:$ip - $message";
        
        if ($log_entry['data']) {
            $line .= " | Data: " . json_encode($log_entry['data']);
        }
        
        return $line;
    }
    
    /**
     * 获取客户端IP
     */
    private function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        }
        return trim($ip);
    }
    
    /**
     * 读取日志文件
     */
    public function get_logs($date = null, $level = null, $type = null, $limit = 100) {
        if (!$date) {
            $date = date('Y-m-d');
        }
        
        $log_file = $this->log_dir . '/app_' . $date . '.log';
        
        if (!file_exists($log_file)) {
            return [];
        }
        
        $lines = file($log_file, FILE_IGNORE_NEW_LINES);
        $logs = [];
        
        foreach ($lines as $line) {
            $log = $this->parse_log_line($line);
            
            // 筛选
            if ($level && $log['level'] !== $level) continue;
            if ($type && $log['type'] !== $type) continue;
            
            $logs[] = $log;
        }
        
        // 返回最后N条
        return array_slice($logs, -$limit);
    }
    
    /**
     * 从数据库读取日志
     */
    public function get_logs_from_db($date = null, $level = null, $type = null, $limit = 100) {
        try {
            if (!function_exists('connectToDatabase')) {
                return [];
            }
            
            $conn = connectToDatabase();
            
            $sql = "SELECT * FROM application_logs WHERE 1=1";
            
            if ($date) {
                $sql .= " AND DATE(timestamp) = ?";
            }
            if ($level) {
                $sql .= " AND level = ?";
            }
            if ($type) {
                $sql .= " AND type = ?";
            }
            
            $sql .= " ORDER BY timestamp DESC LIMIT ?";
            
            $stmt = $conn->prepare($sql);
            
            // 绑定参数
            $params = [];
            $types = '';
            
            if ($date) {
                $params[] = $date;
                $types .= 's';
            }
            if ($level) {
                $params[] = $level;
                $types .= 's';
            }
            if ($type) {
                $params[] = $type;
                $types .= 's';
            }
            $params[] = $limit;
            $types .= 'i';
            
            if (!empty($types)) {
                $stmt->bind_param($types, ...$params);
            }
            
            $stmt->execute();
            $result = $stmt->get_result();
            
            $logs = [];
            while ($row = $result->fetch_assoc()) {
                $logs[] = $row;
            }
            
            $stmt->close();
            $conn->close();
            
            return $logs;
        } catch (Exception $e) {
            return [];
        }
    }
    
    /**
     * 解析日志行
     */
    private function parse_log_line($line) {
        preg_match(
            '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[(\w+)\] \[(\w+)\] User:([\w-]+) IP:([\d.]+) - (.+?)(?:\s+\| Data: (.+))?$/',
            $line,
            $matches
        );
        
        if (empty($matches)) {
            return [
                'timestamp' => 'N/A',
                'level' => 'UNKNOWN',
                'type' => 'UNKNOWN',
                'user_id' => 'N/A',
                'ip' => 'N/A',
                'message' => $line,
                'data' => null
            ];
        }
        
        return [
            'timestamp' => $matches[1],
            'level' => $matches[2],
            'type' => $matches[3],
            'user_id' => $matches[4],
            'ip' => $matches[5],
            'message' => $matches[6],
            'data' => isset($matches[7]) ? json_decode($matches[7], true) : null
        ];
    }
    
    /**
     * 获取可用的日期列表
     */
    public function get_available_dates() {
        $dates = [];
        $files = glob($this->log_dir . '/app_*.log');
        
        foreach ($files as $file) {
            $date = str_replace([$this->log_dir . '/app_', '.log'], '', $file);
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                $dates[] = $date;
            }
        }
        
        rsort($dates);
        return $dates;
    }
    
    /**
     * 清理旧日志
     */
    public function cleanup_old_logs($days = 30) {
        $cutoff_date = strtotime("-$days days");
        $files = glob($this->log_dir . '/app_*.log');
        $count = 0;
        
        foreach ($files as $file) {
            if (filemtime($file) < $cutoff_date) {
                unlink($file);
                $count++;
            }
        }
        
        return $count;
    }
}
?>
