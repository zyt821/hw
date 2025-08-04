<!DOCTYPE html>

<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAML Response 查看器</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

```
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 100vh;
        padding: 20px;
    }
    
    .container {
        max-width: 1200px;
        margin: 0 auto;
        background: rgba(255, 255, 255, 0.95);
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        overflow: hidden;
    }
    
    .header {
        background: linear-gradient(135deg, #2c3e50, #3498db);
        color: white;
        padding: 30px;
        text-align: center;
    }
    
    .header h1 {
        font-size: 2.5em;
        margin-bottom: 10px;
        text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
    }
    
    .header p {
        opacity: 0.9;
        font-size: 1.1em;
    }
    
    .content {
        padding: 40px;
    }
    
    .input-section {
        margin-bottom: 30px;
    }
    
    .input-group {
        margin-bottom: 20px;
    }
    
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #2c3e50;
        font-size: 1.1em;
    }
    
    textarea, input {
        width: 100%;
        padding: 15px;
        border: 2px solid #e1e8ed;
        border-radius: 12px;
        font-family: 'Courier New', monospace;
        font-size: 14px;
        transition: all 0.3s ease;
        background: #f8f9fa;
    }
    
    textarea:focus, input:focus {
        outline: none;
        border-color: #3498db;
        box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        background: white;
    }
    
    textarea {
        min-height: 150px;
        resize: vertical;
    }
    
    .button-group {
        display: flex;
        gap: 15px;
        margin: 20px 0;
    }
    
    button {
        padding: 15px 30px;
        border: none;
        border-radius: 12px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        flex: 1;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #3498db, #2980b9);
        color: white;
        box-shadow: 0 4px 15px rgba(52, 152, 219, 0.3);
    }
    
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(52, 152, 219, 0.4);
    }
    
    .btn-secondary {
        background: linear-gradient(135deg, #95a5a6, #7f8c8d);
        color: white;
        box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
    }
    
    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(149, 165, 166, 0.4);
    }
    
    .result-section {
        margin-top: 30px;
    }
    
    .result-card {
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 20px;
        transition: all 0.3s ease;
    }
    
    .result-card:hover {
        border-color: #3498db;
        box-shadow: 0 5px 15px rgba(52, 152, 219, 0.1);
    }
    
    .result-card h3 {
        color: #2c3e50;
        margin-bottom: 15px;
        font-size: 1.3em;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .icon {
        width: 20px;
        height: 20px;
        background: #3498db;
        border-radius: 50%;
        display: inline-block;
    }
    
    .attribute-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    
    .attribute-item {
        background: white;
        padding: 15px;
        border-radius: 10px;
        border-left: 4px solid #3498db;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }
    
    .attribute-name {
        font-weight: 600;
        color: #2c3e50;
        margin-bottom: 5px;
    }
    
    .attribute-value {
        color: #555;
        font-family: 'Courier New', monospace;
        background: #f1f3f4;
        padding: 8px;
        border-radius: 6px;
        word-break: break-all;
    }
    
    .error {
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
        box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
    }
    
    .success {
        background: linear-gradient(135deg, #27ae60, #219a52);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
        box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
    }
    
    .xml-display {
        background: #2c3e50;
        color: #ecf0f1;
        padding: 20px;
        border-radius: 12px;
        font-family: 'Courier New', monospace;
        font-size: 12px;
        overflow-x: auto;
        white-space: pre;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .tab-container {
        margin-top: 20px;
    }
    
    .tab-buttons {
        display: flex;
        background: #e9ecef;
        border-radius: 12px 12px 0 0;
        overflow: hidden;
    }
    
    .tab-button {
        flex: 1;
        padding: 15px;
        background: none;
        border: none;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }
    
    .tab-button.active {
        background: #3498db;
        color: white;
    }
    
    .tab-content {
        display: none;
        background: white;
        border: 2px solid #e9ecef;
        border-top: none;
        border-radius: 0 0 12px 12px;
        padding: 20px;
    }
    
    .tab-content.active {
        display: block;
    }
</style>
```

</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 SAML Response 查看器</h1>
            <p>解析和查看SAML 2.0 IdP响应内容</p>
        </div>

```
    <div class="content">
        <div class="input-section">
            <div class="input-group">
                <label for="samlResponse">SAML Response (Base64编码):</label>
                <textarea id="samlResponse" placeholder="请粘贴Base64编码的SAML Response..."></textarea>
            </div>
            
            <div class="input-group">
                <label for="relayState">Relay State (可选):</label>
                <input type="text" id="relayState" placeholder="Relay State...">
            </div>
            
            <div class="button-group">
                <button class="btn-primary" onclick="parseSAMLResponse()">解析 SAML Response</button>
                <button class="btn-secondary" onclick="clearAll()">清空所有</button>
            </div>
        </div>
        
        <div id="result" class="result-section"></div>
    </div>
</div>

<script>
    function parseSAMLResponse() {
        const samlResponseB64 = document.getElementById('samlResponse').value.trim();
        const relayState = document.getElementById('relayState').value.trim();
        const resultDiv = document.getElementById('result');
        
        if (!samlResponseB64) {
            resultDiv.innerHTML = '<div class="error">请输入SAML Response</div>';
            return;
        }
        
        try {
            // Base64解码
            const decodedXML = atob(samlResponseB64);
            
            // 解析XML
            const parser = new DOMParser();
            const xmlDoc = parser.parseFromString(decodedXML, 'text/xml');
            
            // 检查解析错误
            const parseError = xmlDoc.querySelector('parsererror');
            if (parseError) {
                throw new Error('XML解析失败: ' + parseError.textContent);
            }
            
            // 提取SAML Response信息
            const samlInfo = extractSAMLInfo(xmlDoc);
            
            // 显示结果
            displayResults(samlInfo, decodedXML, relayState);
            
        } catch (error) {
            resultDiv.innerHTML = `<div class="error">解析错误: ${error.message}</div>`;
        }
    }
    
    function extractSAMLInfo(xmlDoc) {
        const info = {
            response: {},
            assertion: {},
            attributes: [],
            nameId: null,
            conditions: {},
            signature: null
        };
        
        // Response信息
        const response = xmlDoc.querySelector('Response');
        if (response) {
            info.response = {
                id: response.getAttribute('ID'),
                version: response.getAttribute('Version'),
                issueInstant: response.getAttribute('IssueInstant'),
                destination: response.getAttribute('Destination'),
                inResponseTo: response.getAttribute('InResponseTo')
            };
        }
        
        // Issuer信息
        const issuer = xmlDoc.querySelector('Issuer');
        if (issuer) {
            info.issuer = issuer.textContent;
        }
        
        // Status信息
        const statusCode = xmlDoc.querySelector('StatusCode');
        if (statusCode) {
            info.status = statusCode.getAttribute('Value');
        }
        
        // Assertion信息
        const assertion = xmlDoc.querySelector('Assertion');
        if (assertion) {
            info.assertion = {
                id: assertion.getAttribute('ID'),
                version: assertion.getAttribute('Version'),
                issueInstant: assertion.getAttribute('IssueInstant')
            };
        }
        
        // NameID
        const nameId = xmlDoc.querySelector('NameID');
        if (nameId) {
            info.nameId = {
                format: nameId.getAttribute('Format'),
                value: nameId.textContent
            };
        }
        
        // Subject Conditions
        const conditions = xmlDoc.querySelector('Conditions');
        if (conditions) {
            info.conditions = {
                notBefore: conditions.getAttribute('NotBefore'),
                notOnOrAfter: conditions.getAttribute('NotOnOrAfter')
            };
        }
        
        // Audience
        const audience = xmlDoc.querySelector('Audience');
        if (audience) {
            info.audience = audience.textContent;
        }
        
        // Attributes
        const attributes = xmlDoc.querySelectorAll('Attribute');
        attributes.forEach(attr => {
            const attributeValues = Array.from(attr.querySelectorAll('AttributeValue'))
                .map(val => val.textContent);
                
            info.attributes.push({
                name: attr.getAttribute('Name'),
                friendlyName: attr.getAttribute('FriendlyName'),
                values: attributeValues
            });
        });
        
        // 检查签名
        const signature = xmlDoc.querySelector('Signature');
        if (signature) {
            info.signature = '存在数字签名';
        }
        
        return info;
    }
    
    function displayResults(info, rawXML, relayState) {
        const resultDiv = document.getElementById('result');
        
        let html = '<div class="success">SAML Response 解析成功!</div>';
        
        // Tab容器
        html += `
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button active" onclick="switchTab('overview')">概览</button>
                    <button class="tab-button" onclick="switchTab('attributes')">属性</button>
                    <button class="tab-button" onclick="switchTab('raw')">原始XML</button>
                </div>
                
                <div id="overview" class="tab-content active">
                    ${generateOverviewHTML(info, relayState)}
                </div>
                
                <div id="attributes" class="tab-content">
                    ${generateAttributesHTML(info.attributes)}
                </div>
                
                <div id="raw" class="tab-content">
                    <div class="xml-display">${escapeHtml(formatXML(rawXML))}</div>
                </div>
            </div>
        `;
        
        resultDiv.innerHTML = html;
    }
    
    function generateOverviewHTML(info, relayState) {
        let html = '';
        
        // Response信息
        if (info.response && Object.keys(info.response).length > 0) {
            html += `
                <div class="result-card">
                    <h3><span class="icon"></span>Response 信息</h3>
                    <div class="attribute-grid">
                        ${Object.entries(info.response).map(([key, value]) => 
                            value ? `<div class="attribute-item">
                                <div class="attribute-name">${key}</div>
                                <div class="attribute-value">${value}</div>
                            </div>` : ''
                        ).join('')}
                    </div>
                </div>
            `;
        }
        
        // 基本信息
        html += `
            <div class="result-card">
                <h3><span class="icon"></span>基本信息</h3>
                <div class="attribute-grid">
                    ${info.issuer ? `<div class="attribute-item">
                        <div class="attribute-name">Issuer (IdP)</div>
                        <div class="attribute-value">${info.issuer}</div>
                    </div>` : ''}
                    ${info.status ? `<div class="attribute-item">
                        <div class="attribute-name">Status</div>
                        <div class="attribute-value">${info.status}</div>
                    </div>` : ''}
                    ${info.audience ? `<div class="attribute-item">
                        <div class="attribute-name">Audience</div>
                        <div class="attribute-value">${info.audience}</div>
                    </div>` : ''}
                    ${info.signature ? `<div class="attribute-item">
                        <div class="attribute-name">数字签名</div>
                        <div class="attribute-value">${info.signature}</div>
                    </div>` : ''}
                    ${relayState ? `<div class="attribute-item">
                        <div class="attribute-name">Relay State</div>
                        <div class="attribute-value">${relayState}</div>
                    </div>` : ''}
                </div>
            </div>
        `;
        
        // NameID信息
        if (info.nameId) {
            html += `
                <div class="result-card">
                    <h3><span class="icon"></span>用户标识 (NameID)</h3>
                    <div class="attribute-grid">
                        <div class="attribute-item">
                            <div class="attribute-name">Format</div>
                            <div class="attribute-value">${info.nameId.format || 'N/A'}</div>
                        </div>
                        <div class="attribute-item">
                            <div class="attribute-name">Value</div>
                            <div class="attribute-value">${info.nameId.value || 'N/A'}</div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Conditions信息
        if (info.conditions && (info.conditions.notBefore || info.conditions.notOnOrAfter)) {
            html += `
                <div class="result-card">
                    <h3><span class="icon"></span>有效期条件</h3>
                    <div class="attribute-grid">
                        ${info.conditions.notBefore ? `<div class="attribute-item">
                            <div class="attribute-name">Not Before</div>
                            <div class="attribute-value">${info.conditions.notBefore}</div>
                        </div>` : ''}
                        ${info.conditions.notOnOrAfter ? `<div class="attribute-item">
                            <div class="attribute-name">Not On Or After</div>
                            <div class="attribute-value">${info.conditions.notOnOrAfter}</div>
                        </div>` : ''}
                    </div>
                </div>
            `;
        }
        
        return html;
    }
    
    function generateAttributesHTML(attributes) {
        if (!attributes || attributes.length === 0) {
            return '<div class="result-card"><h3>未找到用户属性</h3></div>';
        }
        
        let html = `
            <div class="result-card">
                <h3><span class="icon"></span>用户属性 (${attributes.length} 个)</h3>
                <div class="attribute-grid">
        `;
        
        attributes.forEach(attr => {
            html += `
                <div class="attribute-item">
                    <div class="attribute-name">${attr.friendlyName || attr.name}</div>
                    <div class="attribute-value">${attr.values.join(', ')}</div>
                </div>
            `;
        });
        
        html += '</div></div>';
        return html;
    }
    
    function switchTab(tabName) {
        // 隐藏所有tab内容
        document.querySelectorAll('.tab-content').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // 移除所有按钮的active状态
        document.querySelectorAll('.tab-button').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // 显示选中的tab
        document.getElementById(tabName).classList.add('active');
        event.target.classList.add('active');
    }
    
    function clearAll() {
        document.getElementById('samlResponse').value = '';
        document.getElementById('relayState').value = '';
        document.getElementById('result').innerHTML = '';
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatXML(xml) {
        const PADDING = ' '.repeat(2);
        const reg = /(>)(<)(\/*)/g;
        let pad = 0;
        
        xml = xml.replace(reg, '$1\r\n$2$3');
        
        return xml.split('\r\n').map((node) => {
            let indent = 0;
            if (node.match(/.+<\/\w[^>]*>$/)) {
                indent = 0;
            } else if (node.match(/^<\/\w/)) {
                if (pad !== 0) {
                    pad -= 1;
                }
            } else if (node.match(/^<\w[^>]*[^\/]>.*$/)) {
                indent = 1;
            } else {
                indent = 0;
            }
            
            const padding = PADDING.repeat(pad);
            pad += indent;
            
            return padding + node;
        }).join('\r\n');
    }
    
    // 示例数据填充功能
    function loadSampleData() {
        const sampleSAML = `PHNhbWw6UmVzcG9uc2UgeG1sbnM6c2FtbD0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOmFzc2VydGlvbiI+CiAgICA8c2FtbDpJc3N1ZXI+aHR0cHM6Ly9pZHAuZXhhbXBsZS5jb208L3NhbWw6SXNzdWVyPgogICAgPHNhbWxwOlN0YXR1cz4KICAgICAgICA8c2FtbHA6U3RhdHVzQ29kZSBWYWx1ZT0idXJuOm9hc2lzOm5hbWVzOnRjOlNBTUw6Mi4wOnN0YXR1czpTdWNjZXNzIi8+CiAgICA8L3NhbWxwOlN0YXR1cz4KPC9zYW1sOlJlc3BvbnNlPg==`;
        
        document.getElementById('samlResponse').value = sampleSAML;
        document.getElementById('relayState').value = '/dashboard';
    }
</script>
```

</body>
</html>
