# WCU 服务器配置说明

更新时间：2026-04-12

这份文档用于说明 WCU 当前的双机部署结构、关键路径、启动方式、证书位置、验证命令和常见故障排查方法。内容以当前仓库配置和 2026-04-12 的线上核查结果为准。

## 1. 当前部署拓扑

当前环境采用前置公网机 + 后端应用机的拆分部署：

- 域名：`wcuedu.net`
- 前置公网机：`137.131.34.58`
- 后端应用机：`161.153.87.137`
- 前置职责：提供静态站点，并把 `/api` 和 `/admin` 反向代理到后端
- 后端职责：处理申请表提交接口和管理后台

仓库中的对应文件：

- 前置代理入口：`server/front_proxy.py`
- 后端 Python 服务：`server/python_backend.py`
- 后端配置模板：`server/config.python.example.json`
- SQLite 初始化脚本：`server/sql/schema.sqlite.sql`

## 2. 前置公网机配置

### 2.1 机器角色

前置公网机负责：

- 对外监听 `80` 和 `443`
- `80` 自动跳转到 `https`
- 从本地目录提供静态站点
- 将 `/api/` 和 `/admin/` 转发到 `161.153.87.137:80`

### 2.2 当前线上路径

已核查到前置机当前实际路径如下：

- 代理脚本：`/opt/wcu-front/front_proxy.py`
- 证书目录：`/opt/wcu-front/certs`
- 证书文件：`/opt/wcu-front/certs/wcuedu-origin.crt`
- 私钥文件：`/opt/wcu-front/certs/wcuedu-origin.key`
- 静态站点目录：`/var/www/wcu-site`

仓库根目录下的 `origincertificate.txt` 和 `privatekey.txt` 可以作为证书材料来源，但不要在服务器之外随意复制或提交到公共位置。

### 2.3 当前 systemd 服务

前置机当前服务名为 `wcu-front.service`，实际内容如下：

```ini
[Unit]
Description=WCU Front Proxy
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/wcu-front
Environment=WCU_STATIC_ROOT=/var/www/wcu-site
Environment=WCU_BACKEND_HOST=161.153.87.137
Environment=WCU_BACKEND_PORT=80
Environment=WCU_TLS_CERT=/opt/wcu-front/certs/wcuedu-origin.crt
Environment=WCU_TLS_KEY=/opt/wcu-front/certs/wcuedu-origin.key
ExecStart=/usr/bin/python3 /opt/wcu-front/front_proxy.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

建议保存在：

```text
/etc/systemd/system/wcu-front.service
```

### 2.4 前置机部署步骤

1. 将静态站点内容同步到 `/var/www/wcu-site`
2. 将仓库中的 `server/front_proxy.py` 复制到 `/opt/wcu-front/front_proxy.py`
3. 将源站证书复制为 `/opt/wcu-front/certs/wcuedu-origin.crt`
4. 将源站私钥复制为 `/opt/wcu-front/certs/wcuedu-origin.key`
5. 写入 `wcu-front.service`
6. 执行 `sudo systemctl daemon-reload`
7. 执行 `sudo systemctl enable --now wcu-front`

推荐权限：

- 证书：`0644`
- 私钥：`0600`

示例命令：

```bash
sudo mkdir -p /opt/wcu-front/certs /var/www/wcu-site
sudo cp front_proxy.py /opt/wcu-front/front_proxy.py
sudo cp wcuedu-origin.crt /opt/wcu-front/certs/wcuedu-origin.crt
sudo cp wcuedu-origin.key /opt/wcu-front/certs/wcuedu-origin.key
sudo chmod 644 /opt/wcu-front/certs/wcuedu-origin.crt
sudo chmod 600 /opt/wcu-front/certs/wcuedu-origin.key
sudo systemctl daemon-reload
sudo systemctl enable --now wcu-front
```

## 3. 后端应用机配置

### 3.1 机器角色

后端应用机当前对外提供：

- `GET /api/application.php`
- `POST /api/application.php`
- `/admin/`

在 2026-04-12 的线上核查中，`http://161.153.87.137/api/application.php` 能正常返回：

```json
{"ok": true, "service": "wcu-applications-api"}
```

响应头显示当前后端为 Python 版本的应用服务，服务标识为 `WCUBackend/1.0 Python/3.9.25`。

### 3.2 推荐目录结构

如果继续使用 Python 轻量后端，建议采用以下结构：

```text
/opt/wcu-api/
  python_backend.py
  config.python.json
  sql/
    schema.sqlite.sql

/var/lib/wcu-data/
  wcu.sqlite
```

### 3.3 推荐配置文件

可基于仓库中的 `server/config.python.example.json` 生成：

```json
{
  "cors": {
    "allowed_origins": [
      "https://wcuedu.net",
      "https://www.wcuedu.net",
      "http://137.131.34.58"
    ]
  },
  "database": {
    "path": "/var/lib/wcu-data/wcu.sqlite"
  },
  "admin": {
    "username": "admin",
    "password_hash": "sha256$replace-with-real-hash"
  }
}
```

### 3.4 推荐 systemd 服务示例

后端机可使用如下 unit 文件：

```ini
[Unit]
Description=WCU Python Backend
After=network.target

[Service]
Type=simple
WorkingDirectory=/opt/wcu-api
ExecStart=/usr/bin/python3 /opt/wcu-api/python_backend.py
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

建议服务名：

```text
/etc/systemd/system/wcu-backend.service
```

部署后执行：

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now wcu-backend
```

## 4. SSH 连接方式

当前已验证可登录前置机的私钥为：

```text
keys/ssh-key-2026-04-12.key
```

Windows PowerShell 连接前置机命令：

```powershell
ssh -i .\keys\ssh-key-2026-04-12.key opc@137.131.34.58
```

说明：

- `opc` 是当前可用的 Oracle Linux 登录用户
- `keys/ssh-key-2026-04-11 (2).key` 已验证不能登录前置机
- 后端机当前未确认与前置机使用同一把 SSH key，如需登录后端，请以实例初始化时绑定的 authorized key 为准

## 5. 发布与更新建议

### 5.1 更新前置机

前置机更新通常只涉及两个部分：

- 静态站点文件
- `front_proxy.py`

建议流程：

1. 备份旧文件
2. 上传新的静态站点到 `/var/www/wcu-site`
3. 上传新的 `/opt/wcu-front/front_proxy.py`
4. 执行 `sudo systemctl restart wcu-front`
5. 立即做本地和公网验证

### 5.2 更新后端机

后端机更新通常涉及：

- `python_backend.py`
- `config.python.json`
- `sql/schema.sqlite.sql`

建议流程：

1. 备份旧版本
2. 替换后端脚本和配置
3. 检查 `/var/lib/wcu-data` 是否可写
4. 执行 `sudo systemctl restart wcu-backend`
5. 验证 `/api/application.php` 和 `/admin/`

## 6. 验证命令

### 6.1 在前置机本机验证

```bash
curl -I http://127.0.0.1/
curl -k -I https://127.0.0.1/
curl -k https://127.0.0.1/api/application.php
sudo ss -ltnp | egrep ':80|:443'
sudo systemctl status wcu-front --no-pager
```

期望结果：

- `http://127.0.0.1/` 返回 `301`
- `https://127.0.0.1/` 返回 `200`
- `/api/application.php` 返回 JSON

### 6.2 在本地工作站验证

```powershell
curl.exe -I http://137.131.34.58/
curl.exe -k -I https://137.131.34.58/
curl.exe -k https://137.131.34.58/api/application.php
curl.exe -k -I --resolve wcuedu.net:443:137.131.34.58 https://wcuedu.net/
```

### 6.3 直接验证后端

```powershell
curl.exe http://161.153.87.137/api/application.php
```

## 7. 常见故障排查

### 7.1 公网访问超时，但前置机本地正常

先检查：

- 安全组或防火墙是否放通 `80/443`
- 域名是否仍指向正确的前置机
- CDN 或反向代理层是否仍在指向旧源站
- 前置机负载是否异常偏高

排查命令：

```bash
sudo ss -ltnp | egrep ':80|:443'
free -h
uptime
top
sudo journalctl -u wcu-front -n 200 --no-pager
```

### 7.2 `/api` 失败，但首页正常

先检查：

- `WCU_BACKEND_HOST` 是否仍为 `161.153.87.137`
- 后端机 `80` 端口是否可达
- 后端服务是否在运行

前置机上执行：

```bash
curl http://161.153.87.137/api/application.php
sudo systemctl status wcu-front --no-pager
```

### 7.3 SSH 提示 `Permission denied`

说明通常是：

- 使用了错误的私钥
- 使用了错误的登录用户
- 目标实例上的 `authorized_keys` 未包含当前公钥

当前已验证：

- `ssh-key-2026-04-12.key` 可登录前置机
- 其他 key 不一定可登录后端机

### 7.4 SSH 提示 `Connection closed by ... port 22`

这类报错不一定是 SSH 服务挂了，也可能是：

- 连接过多触发 `sshd` 的 `MaxStartups` 限制
- 外部扫描流量较多
- 尚未完成认证就被服务端主动断开

本次线上日志中曾出现过：

- `MaxStartups throttling`
- 多个来自公网的扫描或错误登录尝试

## 8. 安全注意事项

- 不要把 SSH 私钥、源站私钥和数据库文件提交到公共仓库
- `privatekey.txt` 和 `keys/` 目录应视为敏感内容
- 前置机证书私钥必须设置为 `0600`
- 管理后台密码必须替换为真实哈希
- 如条件允许，建议后续将后端机限制为只接受来自前置机的访问

## 9. 建议后续补充

当前文档已经覆盖现有双机部署的关键路径。后续建议继续补充：

- 后端机的实际 systemd 服务名与实际部署目录
- 域名解析和 CDN 配置截图或说明
- 一键发布脚本
- 定期备份 SQLite 数据库的流程
