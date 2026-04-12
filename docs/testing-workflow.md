# WCU 测试工作流

## 目标

这个仓库目前以静态页面为主，动态后端主要集中在 `pages/apply.php`。这台机器上现在有 Python，但没有 PHP、Node 和 npm，所以本地测试工作流分成三层：

- 用 Python 立即可跑的自动化冒烟测试
- 本地预览下的手工回归测试
- 进入带 PHP 的预发环境后再做后端提交流程验证

## 本地命令

运行自动化冒烟测试：

```powershell
.\scripts\run-tests.ps1
```

启动本地预览服务：

```powershell
.\scripts\serve-site.ps1
```

自定义端口启动：

```powershell
.\scripts\serve-site.ps1 -Port 9000
```

启动后，在浏览器中打开 `http://127.0.0.1:8000/`，或者你指定的端口。

## 自动化测试覆盖内容

`tests/test_site.py` 目前会检查：

- 关键页面是否存在
- 公共 CSS 和 JS 资源是否存在
- HTML 中本地 `href`、`src`、`action` 引用是否都能解析到真实文件
- `pages/apply.html` 是否正确指向分步申请流程
- `pages/apply-basic.html` 是否保留关键字段
- `pages/apply-writing.html` 是否保留隐藏字段和提交字段
- `pages/apply.php` 是否存在

它主要用于尽早发现这些常见问题：

- 页面移动后相对路径失效
- 资源文件丢失
- 必填表单字段被误删
- 申请流程入口或页面连接断开

## 推荐日常流程

每次做完新功能，建议按下面顺序走一遍：

1. 运行 `.\scripts\run-tests.ps1`
2. 修复路径、资源、页面结构相关问题
3. 运行 `.\scripts\serve-site.ps1`
4. 在浏览器中检查你改动涉及的页面
5. 按下面的手工回归清单过一遍关键流程

## 手工回归清单

### 全站检查

- 打开首页，确认样式加载正常，没有裸 HTML
- 打开 `pages/about.html`、`pages/academics.html`、`pages/campus.html`、`pages/admissions.html`、`pages/research.html`、`pages/news.html`
- 确认各页面顶部导航都能正常跳转
- 确认移动端菜单按钮可以展开和收起
- 打开浏览器控制台，确认变更页面没有新的 JavaScript 报错

### 申请流程检查

- 打开 `pages/apply.html`，确认 Start Application 会进入 `pages/apply-basic.html`
- 在 `pages/apply-basic.html` 不填必填项直接提交，确认浏览器校验会阻止继续
- 正常填写基础信息并进入 `pages/apply-writing.html`
- 刷新 writing 页面，确认自动保存和分步流程数据仍然存在
- 返回 basic 页面，确认之前输入的内容没有丢
- 在 `pages/apply-writing.html` 确认个人陈述、作品链接、确认勾选框都存在

### 视觉检查

- 确认加载动画会在页面载入后消失
- 确认 reveal 动画不会导致内容永久不可见
- 确认窄屏下标题、按钮、表单仍然可读、可点

## 当前本地环境的测试边界

本地 Python 预览服务不会执行 PHP，所以现在这台机器还不能完整验证 `pages/apply.php`。上线前仍然必须在带 PHP 的环境中补测这些内容：

- `GET /pages/apply.php?csrf=1` 是否能拿到 CSRF token
- `POST /pages/apply.php` 是否能成功提交
- 后端校验失败时是否返回正确错误信息
- 数据库写入是否成功
- 确认邮件是否成功发送
- 服务端日志是否能记录失败原因

## 上线前后端补测清单

等 PHP 环境准备好后，在预发环境执行：

1. 提交一份合法申请，确认返回成功 JSON
2. 确认记录已写入预发数据库
3. 确认测试邮箱收到确认邮件
4. 提交非法数据，确认返回的错误信息可读且符合预期
5. 提交缺失或错误的 CSRF token，确认请求被拒绝
6. 直接访问 `pages/apply.php`，确认会跳转而不是暴露原始接口内容

## 下一步建议

等这台机器补齐 PHP 后，下一步可以把 `pages/apply.php` 的集成测试也接进来，做到提交接口、数据库和邮件链路的自动化验证。
