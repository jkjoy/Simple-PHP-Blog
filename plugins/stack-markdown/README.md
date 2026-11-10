# Stack Markdown 增强

作者：**jkjoy**。SBlog 的可选配套插件，运行时不修改核心文件，不需要 Composer、Hugo 或联网下载解析器。

将完整的 `stack-markdown` 目录放入 `plugins/`，在“后台 → 插件管理”启用 **Stack Markdown 增强**。随主体仓库提供时只需启用；请与 `themes/stack/` 一并保留。PHP 需启用 DOM 扩展。

插件通过现有 `content` 主题过滤器，在主题调整布局前增强文章和独立页面的正文。支持六级与 Setext 标题、嵌套及任务列表、脚注、引用链接、下划线强调、代码围栏及安全语义 HTML。搭配 Stack 主题可使用短代码、代码行号和响应式图片，详细写法见 [Stack 说明](../../themes/stack/README.md)。

每层嵌套列表使用四个空格。语义 HTML 采用白名单，脚本、事件属性、危险链接与表单不会输出。密码未解锁时保留核心原有表单及提示；回复隐藏内容沿用核心的评论权限检查。只有获准查看的片段进入解析器和主题扩展。

插件仅增强前台文章及独立页面。编辑器保存校验、列表摘要、搜索脱敏和 RSS 继续使用核心行为，不接管评论。停用插件即恢复核心 Markdown 展示，原文和数据库结构保持不变。

解析器基于 [Michel Fortin 的 PHP Markdown Extra 2.0.0](https://github.com/michelf/php-markdown/tree/2.0.0)，本地副本位于 `vendor/`，保留 BSD 许可及署名，命名空间隔离以避免其他插件冲突。SBlog 适配包含任务列表、安全 HTML、公式保护、扩展围栏与主题钩子。

在项目根目录运行 `php plugins/stack-markdown/tests/run.php` 可执行 55 项解析与内容保护断言；同时安装 Stack 主题时另执行 16 项短代码集成断言。测试仅支持 CLI，不启动核心路由、不连接数据库、不更改设置；HTTP 访问测试文件返回 404。
