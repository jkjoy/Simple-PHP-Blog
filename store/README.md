# SBlog 扩展商店索引

后台扩展商店从远程 `catalog.json` 获取主题和插件。官方索引由独立仓库 [`jkjoy/SBlog-Extensions`](https://github.com/jkjoy/SBlog-Extensions) 的 GitHub Actions 自动构建，地址为：

`https://raw.githubusercontent.com/jkjoy/SBlog-Extensions/catalog/catalog.json`

官方 GitHub Raw 地址不可用时，主程序会回退到同一分支的 jsDelivr 镜像。也可以按相同协议发布自建仓库或静态 CDN，并通过服务器环境变量 `SBLOG_EXTENSION_STORE_URL` 指向新的索引地址；自定义源不会使用官方镜像回退。主程序会将缓存与完整索引 URL 绑定，切换地址后不会继续使用旧源缓存。

## 索引格式

- `schema`：当前固定为 `1`。
- `updated_at`：索引更新时间，仅用于展示与维护。
- `package_defaults`：可选的公共 `download_url` 与 `sha256`。
- `extensions`：主题和插件条目列表。
- `type`：`theme` 或 `plugin`。
- `slug`：安装目录名，只能包含小写字母、数字、连字符和下划线。
- `version`：必须与扩展自身清单中的版本一致。
- `download_url`：HTTPS ZIP 地址，可覆盖公共下载地址。
- `sha256`：ZIP 的 SHA-256，小写十六进制，可覆盖公共校验值。
- `archive_path`：扩展位于 ZIP 内的相对目录。独立扩展包可省略，此时 ZIP 中必须存在唯一的 `<slug>/theme.json` 或 `<slug>/plugin.json`。
- `requires`：最低 SBlog 版本。
- `tested`：已测试的 SBlog 版本。

商店只接受 HTTPS 下载地址；为本地开发测试，允许 `http://localhost`、`http://127.0.0.1` 和 `http://[::1]`。主题必须包含 `theme.json`，插件必须包含 `plugin.json` 与 `plugin.php`。

官方仓库按扩展目录独立打包，并使用不可变的版本 Release 作为下载地址；所有 Release 资产上传并校验成功后，工作流才会更新 `catalog` 分支。第三方索引也应采用不可变下载地址，并在发布后保持相同版本号对应的 ZIP 内容不变。
