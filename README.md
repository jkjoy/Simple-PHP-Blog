# Simple PHP Blog

一个单入口实现思路做的轻量博客：

- 主程序集中在 `index.php`
- 安装流程集中在 `install.php`
- SQLite 存储
- 内置轻量默认主题，其他主题和插件与核心独立发布

## 功能

- 首页文章列表
- 文章详情页
- 文章评论列表、访客/登录用户评论表单与评论回复
- 独立页面
- 归档页
- 标签聚合页
- 分类聚合页
- 访客文章点赞
- RSS 输出
- 管理员登录
- 后台文章管理
- 后台评论审核、未读通知与删除管理
- 草稿、发布、定时发布
- 站点基础设置
- 可扩展前台主题、主题资源与 action/filter 钩子
- 可启停插件、插件 action/filter 钩子与后台插件管理
- 独立主题管理，可预览并切换前台主题
- 后台扩展商店，可远程获取、校验、安装和更新主题与插件
- 后台自动检查 GitHub Release 并一键更新程序
- 可选伪静态 URL
- 基础 Markdown 渲染
- 网易云音乐、哔哩哔哩、YouTube 和豆瓣媒体链接自动解析

## 媒体链接解析

在文章或独立页面中把受支持的媒体链接单独放在一段，发布后会自动转换：

- 网易云音乐单曲、歌单和专辑链接转换为播放器
- 哔哩哔哩和 YouTube 链接转换为响应式视频播放器
- 豆瓣电影、音乐或图书的 subject 链接转换为资料跳转卡片

链接必须使用完整的 `http://` 或 `https://` 地址。链接与其他文字写在同一段时会保留为普通链接。

## 环境要求

- PHP 8.0+
- `pdo_sqlite` 扩展
- `fileinfo` 扩展
- `curl` 与 `zip` 扩展（扩展商店远程安装）
- Apache / Nginx / Caddy / PHP 内置服务器

## 安装

### 单文件自动安装

1. 只上传 `installer.php` 到准备安装博客的空目录。
2. 在浏览器访问 `installer.php`。
3. 环境检查通过后，点击“下载并部署”。安装器会从官方 GitHub Release 下载最新稳定版。
4. 部署完成后继续初始化站点，并从服务器删除 `installer.php`。

安装器要求 PHP 8.0+、`curl`、`zip`、`pdo_sqlite` 和 `fileinfo` 扩展。它不会覆盖目录中的同名文件，写入失败时会回滚本次已创建的程序文件。

### 手动安装

1. 把项目放到 Web 根目录或子目录。
2. 确保 `data/` 和 `cache/` 可写。
3. 访问 `install.php`。
4. 选择中文或 English 安装提示，填写站点信息和管理员账号。安装器会自动创建 `Hello World` 作为第一篇文章。
5. 安装完成后进入后台继续配置。

## 目录

```text
index.php      主入口
install.php    安装页
installer.php  单文件自动部署器
assets/index.css 公共与通用样式
assets/admin.css 后台管理样式
assets/index.js  前台交互
assets/admin.js 后台管理交互
.htaccess      Apache 重写和目录保护
data/          SQLite、安装锁、配置
cache/         设置缓存
uploads/       本地上传文件及可选的 S3 备份
themes/        自定义前台主题
plugins/       功能插件与语言插件
store/         扩展商店索引协议说明（不部署到站点）
```

## 配置与缓存

- `settings` 表保存站点基础设置。
- `cache/settings.php` 是站点基础设置缓存，用于减少常规页面读取数据库。
- `ai_settings` 表保存 AI 接口、模型、提示词和 API Key，不写入缓存文件。
- `mail_settings` 表保存 SMTP 主机、账号、密码、发件人和通知收件人，不写入缓存文件。
- `s3_settings` 表保存 S3 Endpoint、Bucket、访问密钥和上传选项，不写入缓存文件。
- 后台保存站点基础设置会刷新 `cache/settings.php`。
- 后台保存 AI、邮件通知或 S3 设置只更新对应独立数据表。

## 自定义主题

- 每个主题放在 `themes/<主题目录>/`，并提供 `theme.json`。
- 可通过 `style.css` 覆盖前台样式，通过 `functions.php` 注册 action/filter 钩子，也可用 `layout.php` 接管完整前台布局。
- 可从后台“扩展商店”远程安装主题，再到“主题管理”预览和启用；无效或被删除的主题会回退到默认主题。
- “主题管理”会对比商店版本，发现新版本时可直接在主题卡片中升级。
- 主题开发接口与完整钩子列表见 `themes/README.md`。
- 主题独立于核心发布；一键更新只覆盖核心程序文件，不会覆盖或删除已安装主题。

## 插件

- 每个插件放在 `plugins/<插件目录>/`，并提供 `plugin.json` 和 `plugin.php`。
- 可从后台“扩展商店”远程安装插件，再到“插件管理”启用、停用和设置。
- “插件管理”会显示商店中的新版本，并可在插件列表中直接升级。
- 插件可使用带优先级的 action/filter 扩展请求、文章保存、评论创建、后台菜单和最终 HTML 输出。
- 插件开发接口与完整钩子列表见 `plugins/README.md`。
- 插件 PHP 是服务器端可信代码，只安装来源可信的插件。

## 扩展商店

- 默认索引来自独立仓库 [`jkjoy/SBlog-Extensions`](https://github.com/jkjoy/SBlog-Extensions) 的 `catalog` 分支，GitHub Raw 不可用时自动回退到 jsDelivr，列表缓存 6 小时。
- 可通过服务器环境变量 `SBLOG_EXTENSION_STORE_URL` 切换到自建仓库或 CDN 索引；缓存与索引 URL 绑定，切换源后不会复用旧源数据。
- 官方仓库的 GitHub Actions 会校验扩展清单和版本，为每个主题、插件生成独立 ZIP 与 Release，并在资产校验通过后发布 `catalog.json`。
- 安装器限制下载大小、文件数量与解压体积，拒绝绝对路径、目录穿越和符号链接，并强制校验 ZIP 的 SHA-256。
- 更新已安装扩展前会将旧目录移动到 `cache/extension-backup-*`，安装失败时自动回滚。
- 索引协议与独立部署方法见 `store/README.md`。

## S3 上传

启用 `s3-storage` 插件并在后台“S3 存储”中开启后：

- 新附件会通过 AWS Signature V4 上传到 S3 或兼容服务。
- 对象键格式为“路径前缀/年份/随机文件名”。
- 可填写包含协议的完整 CDN 域名；附件 URL 会使用该域名拼接对象键，留空时使用 Endpoint 生成地址。
- MinIO 等需要 Path-style 地址的服务可开启对应选项。
- 开启“在本地保留上传备份”时，同一文件也会写入 `uploads/年份/`；关闭时不在站点目录落盘。
- S3 上传失败时不会插入附件链接；启用本地备份时，本次失败产生的本地文件也会删除。

## 邮件通知

启用 `email-notifications` 插件后，可选择使用 SMTP；关闭 SMTP 时会尝试服务器 PHP `mail()`：

- 忘记密码邮件会优先通过 SMTP 发送。
- 新评论提交成功后，如站点设置里开启“新评论显示后台提醒”，会发送新评论通知邮件。
- 通知收件邮箱优先使用邮件通知设置中的收件邮箱；留空时使用第一个管理员邮箱。
- SMTP 发送失败不会阻止评论提交。
- 密码重置仍会在 `cache/password-reset-*.txt` 写入兜底链接。

## 本地运行

如果本机有 PHP：

```bash
php -S 127.0.0.1:8000
```

然后访问：

```text
http://127.0.0.1:8000/install.php
```

## 伪静态 URL

后台开启后，公开页面和主要管理页面会使用这类路径：

- `/`
- `/page/2`
- `/archives`
- `/tags`
- `/tag/php`
- `/archive/your-slug`
- `/about`
- `/rss.xml`
- `/login`
- `/admin`
- `/write`
- `/edit/12`

Apache 已可直接使用仓库里的 `.htaccess`。

如果你用 Nginx，可以参考：

```nginx
location ^~ /data/ { deny all; }
location ^~ /cache/ { deny all; }
location ~* ^/themes/.+\.(?:php|json)$ { deny all; }
location ~* ^/plugins/.+\.(?:php|json|md)$ { deny all; }

location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php-fpm.sock;
}
```

## 注意

- `data/` 和 `cache/` 不应该被公网直接访问
- `ai_settings`、`mail_settings` 和 `s3_settings` 中包含后端密钥类配置，请只通过后台修改
- 如果要重装，先删除 `data/install.lock`
- 更新程序后如涉及数据库结构变更，请先登录后台，再访问 `update.php` 执行升级
- 一键更新只覆盖核心程序文件并保留 `data/`、`cache/`、`uploads/`、`themes/` 与 `plugins/`；主题和插件通过扩展商店独立更新
