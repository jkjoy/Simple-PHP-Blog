# Stack for SBlog

作者：**jkjoy**。原作者：**Jimmy Cai（CaiJimmy）**。

本主题移植自 [Hugo Theme Stack](https://github.com/CaiJimmy/hugo-theme-stack)，原版为 Jimmy Cai 设计的卡片式 Hugo 主题。布局、Sass、SVG 图标沿用原作，在 SBlog 中重新实现数据查询、主题设置与交互。原作基于 GPL-3.0-only 发布，本移植保留该许可，完整条款见 `LICENSE`。

移植基准：Stack 4.0.3，原仓库提交 `3e123a30b79b5d52a3a8e88a9dd678fcfd28e418`。上游后续更新不会自动覆盖本目录。

## 使用

要求 SBlog 1.14.30 或以上，PHP 启用 DOM 扩展。主题目录为 `themes/stack/`，在“后台 → 主题管理”预览或启用；在“更多 → 主题设置”配置。安装主题不会自动切换现有主题。

本主题与 `plugins/stack-markdown/` 配套插件随主体仓库提供。完整 Markdown 功能需要在“后台 → 插件管理”启用 **Stack Markdown 增强**，无需修改核心。停用插件后恢复核心基础解析。

## 原版样式与页面

- 保留 Stack 原版三栏布局、响应式断点、字体、卡片、阴影、配色、归档和分类标签列表；桌面详情右栏为目录，手机详情提供可展开目录。
- 首页文章保留 SBlog 的置顶优先级，归档保持时间顺序。列表摘要使用文章摘要，封面默认提取公开正文第一张图片；没有封面的文章使用原版纯文字卡片。
- 独立页面、友情链接、分页、错误页面及插件输出继续使用 SBlog。详情正文保留核心的权限检查；配套插件启用时再次核验权限后增强正文。原生评论表单、CSRF、审核、反垃圾、回复、身份和评论元信息钩子均保留。
- 公式、Mermaid、代码高亮、字体等资源在主题本地提供，相关脚本按实际内容按需加载。图片查看器使用浏览器原生 dialog，支持键盘、切换、关闭和焦点返回。

## 设置对应关系

| 原版配置 | SBlog 主题设置 |
| --- | --- |
| `sidebar` | 头像、Emoji、副标题、紧凑布局 |
| `menu.main` / `menu.social` | 主导航、社交链接 JSON |
| `colorScheme` | 默认浅色 / 深色 / 跟随系统，以及前台切换 |
| `widgets.homepage` / `widgets.page` | 有序组件列表、归档 / 分类 / 标签数量 |
| `article.toc` / `headingAnchor` / `readingTime` | 目录、标题锚点、阅读时长及阅读速度 |
| `article.list.showTags` | 列表标签 |
| `article.license` / `dateFormat` | 版权说明、版权链接、发布时间与更新时间格式 |
| `article.math` / `article.mermaid` | KaTeX 公式、Mermaid 风格与深浅色图表主题 |
| `article.alertIcon` | 五种提示引用块的图标 |
| `markup.highlight` | 代码行号开关、围栏行号与重点行参数 |
| `imageProcessing.content` | 本地响应式图片处理开关、缩放宽度列表 |
| `opengraph.twitter` | Twitter 账号与分享卡片类型 |
| `footer` | 起始年份、文字、备案与署名显示 |
| `comments` | 原生评论开关、标题、输入提示 |

另提供封面、摘要长度、相关文章、代码复制、图片放大、自定义 CSS / JavaScript 等设置。站点标题、描述、favicon、分页数量、文章分类、标签和独立页面使用博客已有设置。

常用 Hugo shortcode 与本地响应式图片在 PHP 中实现对应功能；多语言内容翻译、任意 Go 模板和 Hugo 构建管线不在此运行。RSS 由 SBlog 核心生成，增强解析器仅处理前台文章与独立页面。评论始终写入 SBlog 本站数据库。

## 配置示例

主导航填写 JSON 数组；留空或 `[]` 使用内置导航。可使用原版图标名，如 `home`、`user`、`archives`、`search`、`link`、`brand-github`、`brand-twitter`、`rss`。`new_tab` 是布尔值。

```json
[
  {"name": "主页", "url": "/", "icon": "home"},
  {"name": "关于", "url": "/index.php?a=page&slug=about", "icon": "user"},
  {"name": "GitHub", "url": "https://github.com/jkjoy", "icon": "brand-github", "new_tab": true}
]
```

社交链接使用相同的对象结构。启用伪静态或博客安装在子目录时，请按实际页面地址填写自定义导航。默认导航会自动适配。

组件填写逗号分隔列表，顺序即显示顺序；留空隐藏侧栏。支持 `search,archives,categories,tag-cloud,toc`，其中 `toc` 仅用于详情页。

单篇文章配置以 ID 或 slug 为键；布尔字段填写 `true` 或 `false`，明确设置 `cover` 为空字符串可隐藏某篇封面。设置中的封面和副标题视为公开资料。密码正文和回复后可见的正文区块不会用于摘要、自动封面或搜索索引。

```json
{
  "hello-world": {
    "cover": "/uploads/cover.webp",
    "subtitle": "一篇文章的副标题",
    "toc": true,
    "reading_time": true,
    "math": true,
    "mermaid": true,
    "license": "CC BY 4.0",
    "license_url": "https://creativecommons.org/licenses/by/4.0/"
  }
}
```

分类颜色以分类 slug 或名称为键：

```json
{"life": {"background": "#9b6142", "color": "#ffffff"}}
```

提示引用块使用 Markdown 的 `> [!NOTE]`、`> [!TIP]`、`> [!IMPORTANT]`、`> [!WARNING]`、`> [!CAUTION]`。公式需要开启公式设置；Mermaid 使用语言标记为 `mermaid` 的代码块。

## Markdown 支持范围

启用配套的 **Stack Markdown 增强** 插件后，支持：

- H1–H6、Setext 标题、星号与下划线强调、删除线、直接及引用式链接、图片和链接 title。
- 有序与无序嵌套列表、起始序号、只读任务复选框、脚注与返回链接。嵌套列表每层使用四个空格，遵循 Markdown Extra 的规则。
- 反引号、波浪线、多反引号及四空格缩进代码块，标准分隔线。
- 安全的语义 HTML，例如 `kbd`、`mark`、`sub`、`sup`、`abbr`、`details` 与 `summary`；脚本、事件、表单和危险 URL 会被清除。
- 公式、Mermaid、目录锚点、代码复制、五种提示块及自定义标题；宽表格横向滚动，独立图片段落转换为画廊与图注。

解析器与依赖均在插件目录内。插件先核验访问密码，再拆分回复隐藏区块；只将获准查看的片段交给解析器和主题短代码。原生评论与审核继续由核心处理。列表摘要、搜索和 RSS 仍使用核心公开内容规则。

代码行号默认开启，可在主题设置关闭，或单独指定：

````markdown
```php {linenos=true,linenostart=10,hl_lines=["1-2","4"]}
<?php echo "Stack";
```
````

`linenos=false` 关闭该代码块行号，`linenostart` 设置起始号，`hl_lines` 按相对行数标记重点行。复制按钮只复制原始代码。Mermaid 支持展开、缩放、拖动、还原与适应屏幕。

## Hugo 短代码

配套插件启用时，支持以下写法，也支持 `{{% ... %}}` 分隔符：

```text
{{< quote author="Jimmy Cai" source="Stack" url="https://jimmycai.com" >}}
支持 **Markdown** 的引用正文。
{{< /quote >}}

{{< youtube dQw4w9WgXcQ >}}
{{< bilibili BV1xx411c7mD 1 >}}
{{< tencent id="x1234567890" >}}
{{< video src="/uploads/demo.mp4" poster="/uploads/poster.jpg" muted="true" >}}
{{< gitlab 123456 >}}
{{< figure src="/uploads/photo.jpg" alt="图片说明" caption="图注" >}}

{{< highlight php "linenos=table,linenostart=5,hl_lines=1-2" >}}
<?php echo "Stack";
{{< /highlight >}}

{{< gallery >}}
![第一张](/uploads/one.jpg) ![第二张](/uploads/two.jpg)
{{< /gallery >}}
```

未知短代码保留为文本，代码块和行内代码中的示例不会执行。GitLab 片段脚本在隔离的 iframe 中运行；视频与 GitLab 实际展示依赖对应服务。普通媒体链接自动转换使用 SBlog 现有媒体接口，并兼容多媒体解析插件。

## 本地响应式图片

“本地上传图片响应式处理”默认开启，默认宽度为 `480,800,1024,1600`，可在主题设置修改。文章图片及封面首次访问时通过 GD 生成较小的 WebP（不可用时退回 JPEG/PNG），存放在 `uploads/stack-images/`，按文件摘要复用，输出 `srcset`、`sizes` 与图片尺寸。

原图保留，不放大、不下载远程图片。GIF、动画 WebP/PNG、超过处理大小或内存限制的图片保留原图；没有 GD 或目录不可写时也显示原图。这是运行时按需处理，不执行 Hugo 的任意图片指令或 Go 模板。PhotoSwipe 的全部界面未引入，图片查看继续使用本站原生 dialog。

## 本地开发

运行主题不需要 Node.js 或 Hugo。原版 Sass 保存在 `assets/scss/`，SBlog 的补充样式集中在 `assets/scss/sblog.scss`。修改后在该目录运行：

```powershell
npm install
npm run build
```

构建脚本编译原版 Sass，并将选择器限定在 `html.stack-html` / `body.stack-theme`，不会影响后台或其他主题。提交源码和编译后的 `style.css`，不要提交 `node_modules`。

第三方本地资源均保留许可：Lato（SIL OFL）、KaTeX（MIT）、Mermaid（MIT）、Highlight.js（BSD-3-Clause）。版本、来源与文件 SHA-256 记录在 `assets/vendor/manifest.json`。原版样式中包含 Normalize.css 与 Hamburgers 的署名注释。
