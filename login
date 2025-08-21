<?php
// login.php

// 启动会话
session_start();

// 检查会话中是否有 SSO 状态信息
if (isset($_SESSION['sso_status'])) {
    // 根据状态设置样式
    $messageClass = ($_SESSION['sso_status'] === 'success') ? 'alert-success' : 'alert-danger';
    
    // 显示消息
    echo '<div class="' . $messageClass . '">';
    echo htmlspecialchars($_SESSION['sso_message']);
    echo '</div>';
    
    // 清除会话变量
    unset($_SESSION['sso_status']);
    unset($_SESSION['sso_message']);
}
?>

<!DOCTYPE html>
