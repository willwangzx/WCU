# Cloudflare Tunnel 部署说明

更新时间：2026-05-17

> 当前生产环境已经改为 DNS A 记录直连新服务器 `178.238.234.111`
>（Cloudflare 代理开启，SSL/TLS 使用 Full strict）。旧 `cloudflared`
> systemd token unit 已从新服务器移除。本文保留为历史方案和回退参考；当前状态请优先看
> [`docs/current-deployment.md`](current-deployment.md)。

## 当前生产状态摘要

- 服务器：`178.238.234.111`
- DNS：`wcuedu.net`、`www.wcuedu.net`、`api.wcuedu.net` 均为 Cloudflare
  proxied A 记录，指向 `178.238.234.111`
- 前端：`/srv/wcu-site`
- 后端：`/opt/wcu-backend`
- 数据：`/var/lib/wcu-data/wcu.sqlite`
- 服务：`nginx` 和 `wcu-backend` 为 active/enabled
- `cloudflared`：inactive/not-found

## 历史 Tunnel 方案

这份文档针对当前最推荐的轻量方案：让 `161.153.87.137` 这一台机器同时提供静态站点、`/api` 和 `/admin`，再由 `cloudflared` 通过 **Cloudflare Tunnel** 把流量接入公网。

这种方案适合现在这台小规格 Oracle VM，因为它只有两个常驻进程：

- `wcu-backend.service`
- `cloudflared`

而且 Tunnel 是 **出站连接**，不需要让服务器直接暴露公网 `80/443`，比继续让小机器裸扛 HTTPS 稳定得多。

## 1. 当前目标架构

```text
Browser
  -> Cloudflare
  -> Cloudflare Tunnel
  -> cloudflared on 161.153.87.137
  -> http://127.0.0.1:8080
  -> wcu-backend.service
```

当前仓库中相关文件：

- Tunnel 配置示例：`server/config/cloudflared-front.yml.example`
- `wcu-backend` 的 systemd override 示例：`server/config/wcu-backend-cloudflare.override.conf.example`
- `cloudflared` 安装脚本：`server/scripts/install-cloudflared.sh`
- 统一服务脚本：`server/python_backend.py`

## 2. 推荐切换顺序

为了避免上线过程中把当前站点切断，建议分两步：

1. 先在保持现有服务可用的前提下，把 Tunnel 接起来。
2. Tunnel 稳定后，再把 `wcu-backend` 改成只监听 `127.0.0.1:8080`。

也就是说，**不要一上来就改监听端口**。

## 3. 第一步：先安装 cloudflared

对当前这台只有约 `503 MiB` 内存的小机器，**优先推荐单二进制安装**，尽量不要先走 `dnf install`。

在 `161.153.87.137` 上执行：

```bash
sudo bash /path/to/repo/server/scripts/install-cloudflared-binary.sh
```

如果你后面想改回 RPM 仓库安装，仓库里也保留了旧脚本：

```bash
sudo bash /path/to/repo/server/scripts/install-cloudflared.sh
```

二进制版本只做这几件事：

- 下载官方 `cloudflared` Linux 二进制
- 直接安装到 `/usr/local/bin/cloudflared`
- 不触发 `dnf` 依赖解析和 RPM 安装过程

它不会替你直接启用 Tunnel，也不会修改当前站点监听端口。

## 4. 第二步：在 Cloudflare 后台创建 Tunnel

推荐使用 **remotely-managed tunnel**，因为它只需要一个 token 就能运行，服务器端最简单。

在 Cloudflare Dashboard 中：

1. 先确保 `wcuedu.net` 已经接入 Cloudflare。
2. 进入 `Networking` -> `Tunnels`。
3. 创建一个新的 Tunnel。
4. 在 `Published applications` 里先加这些域名：

- `wcuedu.net` -> `http://localhost:80`
- `www.wcuedu.net` -> `http://localhost:80`
- 可选：`api.wcuedu.net` -> `http://localhost:80`

这里第一轮先用 `localhost:80`，是为了和当前线上服务兼容，先把 Tunnel 跑通。

然后在 Tunnel 页面：

1. 选择 `Add a replica`
2. 复制 Cloudflare 生成的安装命令
3. 不要整条照搬，提取其中的 token
4. 在服务器上执行：

```bash
sudo cloudflared service install <TUNNEL_TOKEN>
```

## 5. 第三步：验证 Tunnel 已经接通

服务器上先看服务：

```bash
sudo systemctl status cloudflared --no-pager
sudo journalctl -u cloudflared -n 100 --no-pager
```

再看本机应用仍然可访问：

```bash
curl -I http://127.0.0.1/
curl http://127.0.0.1/api/application.php
```

外部验证：

```powershell
curl.exe -I https://wcuedu.net/
curl.exe https://wcuedu.net/api/application.php
```

如果 `wcuedu.net` 已经能正常出首页和 API，再进行下一步收口。

## 6. 第四步：把后端收口到 127.0.0.1:8080

Tunnel 稳定后，再把 `wcu-backend` 变成只监听本地。

在服务器上创建 override 目录：

```bash
sudo mkdir -p /etc/systemd/system/wcu-backend.service.d
```

把仓库里的示例部署为：

```text
/etc/systemd/system/wcu-backend.service.d/override.conf
```

示例内容见：

```text
server/config/wcu-backend-cloudflare.override.conf.example
```

它的作用是把服务改成：

- 监听地址：`127.0.0.1`
- 监听端口：`8080`
- 静态目录：`/srv/wcu-site`

部署后执行：

```bash
sudo systemctl daemon-reload
sudo systemctl restart wcu-backend
```

验证：

```bash
curl -I http://127.0.0.1:8080/
curl http://127.0.0.1:8080/api/application.php
```

然后回到 Cloudflare Tunnel 后台，把刚才的服务 URL 从 `http://localhost:80` 改成：

- `wcuedu.net` -> `http://localhost:8080`
- `www.wcuedu.net` -> `http://localhost:8080`
- 可选：`api.wcuedu.net` -> `http://localhost:8080`

## 7. 第五步：关闭公网 80/443

当 Tunnel 和本地 `127.0.0.1:8080` 都稳定后，再做收尾：

- 在 OCI Security List / NSG 里关闭入站 `80`
- 在 OCI Security List / NSG 里关闭入站 `443`
- 只保留 `22`，并尽量限制到你自己的固定 IP

这样公网就无法直接扫描你的 Web 入口了。

## 8. 如果你想用本地配置文件而不是后台托管

虽然推荐 remotely-managed tunnel，但如果你更希望把 ingress 规则写在服务器上，也可以使用：

```text
server/config/cloudflared-front.yml.example
```

将它部署到：

```text
/etc/cloudflared/config.yml
```

这时示例里的 `service` 已经按当前单机架构指向：

```text
http://127.0.0.1:8080
```

## 9. 最小排障命令

```bash
sudo systemctl status wcu-backend cloudflared --no-pager
sudo journalctl -u wcu-backend -u cloudflared -n 100 --no-pager
curl -I http://127.0.0.1:8080/
curl http://127.0.0.1:8080/api/application.php
```

如果 Tunnel 正常，但站点打不开，优先看：

- Cloudflare Tunnel 里填写的服务 URL 是否还是旧的 `localhost:80`
- `wcu-backend` 是否已经切到了 `127.0.0.1:8080`
- OCI 是否已经提前关掉了 `22`

## 10. 这套方案为什么更稳

和之前的前置机方案相比，它减少了这些风险：

- 不再需要前端机单独暴露 `80/443`
- 不再需要 Python 自己扛公网 TLS
- 不再需要双机之间再做一层转发
- 小机器只处理应用本身和一个 Tunnel 进程

对于当前这台 `161.153.87.137`，这基本就是最省资源、也最不容易被公网噪音拖死的方案。
