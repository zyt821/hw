<?php
/**
 * 简单的SAML 2.0处理器
 * 不依赖任何外部库，使用PHP原生功能
 */

class SimpleSAMLHandler {
    
    private $sp_entity_id;      // 服务提供者ID
    private $idp_sso_url;       // IdP登录地址
    private $idp_cert;          // IdP证书内容
    private $sp_acs_url;        // 断言消费服务URL
    
    public function __construct($config) {
        $this->sp_entity_id = $config['sp_entity_id'];
        $this->idp_sso_url = $config['idp_sso_url'];
        $this->idp_cert = $config['idp_cert'];
        $this->sp_acs_url = $config['sp_acs_url'];
    }
    
    /**
     * 生成SAML认证请求
     */
    public function createAuthRequest() {
        $request_id = 'id_' . bin2hex(random_bytes(16));
        $issue_instant = gmdate('Y-m-d\TH:i:s\Z');
        
        $saml_request = <<<XML
<?xml version="1.0" encoding="UTF-8"?>

<saml2p:AuthnRequest
xmlns:saml2p=“urn:oasis:names:tc:SAML:2.0:protocol”
xmlns:saml2=“urn:oasis:names:tc:SAML:2.0:assertion”
ID=”{$request_id}”
Version=“2.0”
IssueInstant=”{$issue_instant}”
Destination=”{$this->idp_sso_url}”
AssertionConsumerServiceURL=”{$this->sp_acs_url}”
ProtocolBinding=“urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST”>
<saml2:Issuer>{$this->sp_entity_id}</saml2:Issuer>
</saml2p:AuthnRequest>
XML;

```
    return base64_encode(gzdeflate($saml_request));
}

/**
 * 重定向到IdP进行认证
 */
public function redirectToIdP() {
    $saml_request = $this->createAuthRequest();
    $redirect_url = $this->idp_sso_url . '?SAMLRequest=' . urlencode($saml_request);
    
    header('Location: ' . $redirect_url);
    exit();
}

/**
 * 处理IdP返回的SAML响应
 */
public function handleResponse() {
    if (!isset($_POST['SAMLResponse'])) {
        throw new Exception('没有收到SAML响应');
    }
    
    $saml_response = base64_decode($_POST['SAMLResponse']);
    
    // 解析XML响应
    $dom = new DOMDocument();
    $dom->loadXML($saml_response);
    
    // 验证响应状态
    if (!$this->validateResponse($dom)) {
        throw new Exception('SAML响应验证失败');
    }
    
    // 提取用户属性
    $user_attributes = $this->extractUserAttributes($dom);
    
    return $user_attributes;
}

/**
 * 验证SAML响应
 */
private function validateResponse($dom) {
    // 检查状态码
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('saml2p', 'urn:oasis:names:tc:SAML:2.0:protocol');
    $xpath->registerNamespace('saml2', 'urn:oasis:names:tc:SAML:2.0:assertion');
    
    $status_nodes = $xpath->query('//saml2p:StatusCode');
    if ($status_nodes->length > 0) {
        $status_code = $status_nodes->item(0)->getAttribute('Value');
        if ($status_code !== 'urn:oasis:names:tc:SAML:2.0:status:Success') {
            return false;
        }
    }
    
    // 这里可以添加更多验证逻辑：
    // - 验证数字签名
    // - 检查时间戳
    // - 验证Audience
    
    return true;
}

/**
 * 提取用户属性
 */
private function extractUserAttributes($dom) {
    $xpath = new DOMXPath($dom);
    $xpath->registerNamespace('saml2', 'urn:oasis:names:tc:SAML:2.0:assertion');
    
    $attributes = [];
    
    // 提取NameID (用户唯一标识)
    $nameid_nodes = $xpath->query('//saml2:NameID');
    if ($nameid_nodes->length > 0) {
        $attributes['nameid'] = $nameid_nodes->item(0)->textContent;
    }
    
    // 提取属性声明
    $attr_nodes = $xpath->query('//saml2:Attribute');
    foreach ($attr_nodes as $attr_node) {
        $attr_name = $attr_node->getAttribute('Name');
        $attr_values = $xpath->query('.//saml2:AttributeValue', $attr_node);
        
        $values = [];
        foreach ($attr_values as $value_node) {
            $values[] = $value_node->textContent;
        }
        
        $attributes[$attr_name] = count($values) === 1 ? $values[0] : $values;
    }
    
    return $attributes;
}

/**
 * 验证数字签名（简化版）
 * 注意：这是简化实现，生产环境需要更严格的验证
 */
private function verifySignature($xml_string) {
    if (empty($this->idp_cert)) {
        return true; // 如果没有配置证书，跳过验证
    }
    
    // 这里应该实现完整的XML数字签名验证
    // 由于复杂性，这里只是示例框架
    return true;
}
```

}

/**

- 使用示例
  */

// SAML配置
$saml_config = [
‘sp_entity_id’ => ‘https://yourapp.com/metadata’,
‘idp_sso_url’ => ‘https://idp.example.com/sso’,
‘idp_cert’ => ‘’, // IdP的X.509证书内容
‘sp_acs_url’ => ‘https://yourapp.com/saml/acs’
];

$saml = new SimpleSAMLHandler($saml_config);

// 根据不同的请求处理
if (isset($_GET[‘action’])) {
switch ($_GET[‘action’]) {
case ‘login’:
// 开始SAML登录流程
$saml->redirectToIdP();
break;

```
    case 'acs':
        // 处理IdP返回的响应
        try {
            $user_attributes = $saml->handleResponse();
            
            // 登录成功，创建会话
            session_start();
            $_SESSION['user_id'] = $user_attributes['nameid'];
            $_SESSION['user_attributes'] = $user_attributes;
            
            echo "<h2>登录成功！</h2>";
            echo "<h3>用户信息：</h3>";
            echo "<pre>" . print_r($user_attributes, true) . "</pre>";
            
            // 重定向到应用主页
            // header('Location: /dashboard');
            
        } catch (Exception $e) {
            echo "<h2>登录失败：</h2>";
            echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
        }
        break;
}
```

} else {
// 显示登录页面
?>
<!DOCTYPE html>
<html>
<head>
<title>SAML SSO 登录</title>
<style>
body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; }
.login-box { border: 1px solid #ddd; padding: 20px; border-radius: 5px; }
.btn { background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px; }
</style>
</head>
<body>
<div class="login-box">
<h2>欢迎使用SAML SSO登录</h2>
<p>点击下面的按钮通过身份提供者登录：</p>
<a href="?action=login" class="btn">使用SAML登录</a>
</div>
</body>
</html>
<?php
}
?>
