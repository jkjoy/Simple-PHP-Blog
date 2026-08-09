<?php

declare(strict_types=1);

if (!defined('PLUGINS_DIR')) {
    http_response_code(403);
    exit;
}

function sblog_english_language_map(): array
{
    return [
        '媒体库' => 'Media library',
        '上传与文件管理' => 'Uploads and file management',
        '上传媒体' => 'Upload media',
        '图片、PDF、文本和 ZIP 文件，每个最大 30M。' => 'Images, PDFs, text, and ZIP files up to 30 MB each.',
        '选择或拖入媒体文件' => 'Choose or drop media files',
        '上传完成后会自动加入媒体库。' => 'Uploaded files are added to the media library automatically.',
        '媒体资料：' => 'Media items:',
        '搜索媒体' => 'Search media',
        '媒体类型' => 'Media type',
        '全部媒体' => 'All media',
        '文件' => 'Files',
        '编辑媒体' => 'Edit media',
        '替代文本' => 'Alternative text',
        '说明文字' => 'Caption',
        '保存媒体' => 'Save media',
        '打开文件' => 'Open file',
        '打开' => 'Open',
        '删除此媒体资料？文件将被永久删除。' => 'Delete this media item? The file will be permanently removed.',
        '没有匹配的媒体资料。' => 'No matching media found.',
        '暂无媒体资料。' => 'No media has been uploaded yet.',
        '媒体资料管理' => 'Media management',
        '媒体资料已更新。' => 'Media item updated.',
        '媒体标题不能为空。' => 'The media title cannot be empty.',
        '媒体资料已删除。' => 'Media item deleted.',
        '找不到媒体资料。' => 'Media item not found.',
        '删除媒体资料失败。' => 'Could not delete the media item.',
        '媒体资料登记失败。' => 'Could not add the file to the media library.',
        '当前存储插件不可用，无法删除远端文件。' => 'The storage plugin is unavailable, so the remote file cannot be deleted.',
        '删除存储文件失败。' => 'Could not delete the stored file.',
        '媒体文件路径无效，已停止删除。' => 'The media file path is invalid. Deletion was stopped.',
        '服务器无法删除本地媒体文件。' => 'The server could not delete the local media file.',
        '媒体资料缺少 S3 对象键，无法安全删除。' => 'The media item has no S3 object key and cannot be deleted safely.',
        'S3 配置不完整，无法删除对象。' => 'The S3 configuration is incomplete, so the object cannot be deleted.',
        '服务器缺少 cURL 扩展，无法删除 S3 对象。' => 'The server does not have the cURL extension required to delete S3 objects.',
        '没有收到附件。' => 'No attachment was received.',
        '文件超过服务器允许的大小。' => 'The file exceeds the server size limit.',
        '文件只上传了一部分。' => 'The file was only partially uploaded.',
        '没有选择文件。' => 'No file was selected.',
        '服务器缺少临时目录。' => 'The server is missing its temporary directory.',
        '服务器无法写入文件。' => 'The server could not write the file.',
        '上传被服务器扩展拦截。' => 'A server extension blocked the upload.',
        '上传失败。' => 'Upload failed.',
        '临时文件无效。' => 'The temporary upload file is invalid.',
        '文件类型不在允许列表中。' => 'This file type is not allowed.',
        '保存附件失败。' => 'Could not save the attachment.',
        '附件存储插件处理失败。' => 'The attachment storage plugin failed.',
        'S3 配置不完整。' => 'The S3 configuration is incomplete.',
        '服务器缺少 cURL 扩展，无法上传到 S3。' => 'The server does not have the cURL extension required to upload to S3.',
        '无法读取待上传文件。' => 'Could not read the file being uploaded.',
        '博客尚未安装或数据库配置无效。' => 'The blog is not installed or its database configuration is invalid.',
        '如果账号存在，重置链接已经生成。请检查管理员邮箱；若服务器未配置发信，请查看 cache 目录中的 password-reset 文件。' => 'If the account exists, a reset link has been generated. Check the administrator email, or the password-reset file in the cache directory when email is not configured.',
        '启用 S3 时，请填写有效的 Endpoint、Region、Bucket 和访问密钥，并确认服务器已启用 cURL。' => 'To enable S3, enter a valid endpoint, region, bucket, and credentials, and make sure cURL is enabled.',
        'API 地址必须使用 HTTPS 并解析到公网地址，同时请填写模型名称和提示词。' => 'The API URL must use HTTPS and resolve to a public address. Also enter the model name and prompts.',
        '所选标签已移除，文章内容保持不变。' => 'The selected tags were removed. Post content was not changed.',
        '该分类下仍有文章，请先将文章移动到其他分类。' => 'This category still contains posts. Move them to another category first.',
        '用户已删除，其文章已转移给当前管理员。' => 'The user was deleted and their posts were assigned to the current administrator.',
        '重置链接无效或已过期，请重新申请。' => 'The reset link is invalid or expired. Request a new one.',
        '评论表单已失效，请刷新文章后重试。' => 'The comment form has expired. Refresh the post and try again.',
        '评论已提交，审核通过后会显示。' => 'Your comment was submitted and will appear after approval.',
        '站点数据、上传文件和其他自定义主题不受影响。' => 'Site data, uploads, and other custom themes are not affected.',
        '更新会自动备份并覆盖程序与内置主题文件，' => 'The update will back up and replace application and bundled theme files. ',
        '开启后文章链接会变成 `/archive/slug`，需要服务器 rewrite 支持。' => 'When enabled, post URLs use `/archive/slug` and require server rewrite support.',
        '使用英文逗号分隔，页面将输出为 SEO keywords 元信息。' => 'Separate values with commas. They are emitted as the SEO keywords meta tag.',
        'RSS 会优先使用这里的绝对地址，子目录部署时请带上完整路径。' => 'RSS prefers this absolute URL. Include the complete path for a subdirectory installation.',
        '默认使用项目根目录的 logo.png，也可以填写完整图片 URL 或站内绝对路径。' => 'Defaults to logo.png in the project root. A full image URL or site-absolute path is also supported.',
        '访客首次留言需审核后展示（按邮箱判断）' => 'Hold a visitor\'s first comment for approval (matched by email)',
        '原样插入前台页面的 &lt;/head&gt; 前，可用于统计脚本、meta 或 style；请仅使用可信代码。' => 'Inserted unchanged before &lt;/head&gt; on public pages. Use only trusted analytics, meta, or style code.',
        '当前程序版本完整，但发布包中的内置主题或插件尚未同步。' => 'The application is current, but bundled themes or plugins from the release still need to be synchronized.',
        '更新会自动备份并覆盖程序、内置主题和内置插件文件，站点数据、上传文件及其他自定义主题和插件不受影响。' => 'The update backs up and replaces the application, bundled themes, and bundled plugins. Site data, uploads, and other custom themes and plugins are not affected.',
        '发布文件需要补全' => 'Release files need repair',
        '确定从当前发布包补全内置主题和插件吗？' => 'Restore bundled themes and plugins from the current release?',
        '同步发布文件' => 'Sync release files',
        '内置主题和插件已同步。' => 'Bundled themes and plugins synchronized.',
        '当前版本已是最新，但内置主题或插件需要补全。' => 'The application is current, but bundled themes or plugins need repair.',
        '启用后，新上传的附件将由 S3 接管；密钥不会写入配置缓存。' => 'When enabled, new attachments are handled by S3. Secrets are not written to the settings cache.',
        '启用 SMTP 后优先通过 SMTP 发送；关闭时回退到服务器 PHP mail。' => 'When enabled, messages are sent through SMTP. When disabled, the server falls back to PHP mail.',
        '预览已安装主题，并为博客前台启用新的外观。' => 'Preview installed themes and choose the public appearance of your blog.',
        '博客前台主题管理' => 'Public theme management',
        '填写服务地址，不要包含 Bucket、查询参数或具体对象路径；生产环境建议使用 HTTPS。' => 'Enter the service URL without a bucket, query string, or object path. HTTPS is recommended in production.',
        '填写包含 http:// 或 https:// 的完整 CDN 地址；附件 URL 将使用此地址拼接对象键。留空时使用 S3 Endpoint。' => 'Enter a complete CDN URL including http:// or https://. Leave blank to use the S3 endpoint.',
        '实际对象键会追加年份和随机文件名；可留空。' => 'The year and a random filename are appended to the object key. This can be blank.',
        '用于发送站点通知邮件，配置不会写入缓存文件。' => 'Used for site notification email. This configuration is not written to the settings cache.',
        '留空时可使用管理员账号邮箱作为通知收件人。' => 'Leave blank to use the administrator account email as the recipient.',
        '启用可信插件，为博客增加功能或语言支持。' => 'Enable trusted plugins to add features or language support.',
        'AI 助手' => 'AI Assistant',
        '为文章提供 Slug 生成、摘要生成和正文润色功能。' => 'Adds AI slug generation, summaries, and content polishing.',
        '通过 SMTP 或 PHP mail 发送密码重置和评论通知邮件。' => 'Sends password reset and comment notification emails through SMTP or PHP mail.',
        '将编辑器新上传的附件保存到 Amazon S3 或兼容的对象存储。' => 'Uploads new editor attachments to Amazon S3 or compatible object storage.',
        '英文语言包' => 'English Language',
        '将博客前台、登录页面和后台管理界面翻译为英文。' => 'Translates the public site, sign-in screens, and administration interface into English.',
        '俄语语言包' => 'Russian Language',
        '将博客前台、登录页面和后台管理界面翻译为俄语。' => 'Translates the public site, sign-in screens, and administration interface into Russian.',
        '公开文章与页面累计' => 'Public posts and pages',
        '前台可访问内容' => 'Publicly accessible content',
        '包含所有审核状态' => 'Includes all moderation states',
        '只显示访问和内容统计数据。' => 'Shows only traffic and content statistics.',
        '博客数据预览' => 'Blog data overview',
        '分类数' => 'Categories',
        '已发布文章' => 'Published posts',
        '平均浏览' => 'Average views',
        '待审核评论' => 'Pending comments',
        '总浏览量' => 'Total views',
        '按文章数粗略计算' => 'Approximate per-post average',
        '文章分类总数' => 'Total post categories',
        '评论总数' => 'Total comments',
        '需要管理员处理' => 'Needs administrator attention',
        '分类列表' => 'Category list',
        '分类描述' => 'Category description',
        '分类用于组织文章，不影响独立页面。' => 'Categories organize posts and do not affect standalone pages.',
        '名称、URL 标识和排序。' => 'Name, URL slug, and order.',
        '安装时自动创建的默认文章分类。' => 'The default post category created during installation.',
        '排序权重' => 'Order weight',
        '链接列表' => 'Link list',
        '排序数字越小越靠前。' => 'Lower order values appear first.',
        '简短描述' => 'Short description',
        '网站图标地址' => 'Site icon URL',
        '还没有友情链接。' => 'No links yet.',
        '标签列表' => 'Tag list',
        '标签名称' => 'Tag name',
        '修改标签' => 'Edit tag',
        '原标签' => 'Current tag',
        '仅使用小写字母、数字和连字符。' => 'Use only lowercase letters, numbers, and hyphens.',
        '文章数' => 'Posts',
        '管理员账号' => 'Administrator accounts',
        '系统至少保留一个管理员。' => 'At least one administrator account must remain.',
        '用户' => 'User',
        '个人签名档' => 'Profile signature',
        '头像地址' => 'Avatar URL',
        '邮箱地址' => 'Email address',
        '哔哩哔哩' => 'Bilibili',
        '微信' => 'WeChat',
        '微博' => 'Weibo',
        '内容与文章' => 'Content and post',
        '提交时间' => 'Submitted',
        '搜索' => 'Search',
        '撤下' => 'Move to pending',
        '标记垃圾' => 'Mark as spam',
        '评论者' => 'Commenter',
        '请选择' => 'Select an option',
        '转待审核' => 'Move to pending',
        '选择评论' => 'Select comment',
        '全选' => 'Select all',
        '全选评论' => 'Select all comments',
        '支持基础 Markdown，可创建文章或独立页面。' => 'Supports basic Markdown for posts and standalone pages.',
        '返回后台' => 'Back to dashboard',
        '独立页面' => 'Standalone page',
        '请选择分类' => 'Select a category',
        '发布后优先显示在前端文章列表顶部，仅对文章生效。' => 'After publishing, show this at the top of the public post list. Applies only to posts.',
        '如果发布时间晚于当前时间，前台会按定时发布处理。' => 'A future publication time schedules the content for public release.',
        '独立页面可以留空，文章会用这些标签生成聚合页。' => 'Optional for standalone pages. Post tags generate archive pages.',
        '可同时上传多个附件，每个最大 30M；图片上传完成后显示缩略图并插入 Markdown。' => 'Upload multiple attachments up to 30 MB each. Uploaded images show a thumbnail and are inserted as Markdown.',
        '选择或拖入附件' => 'Choose or drop attachments',
        '支持 Markdown；将网易云音乐、哔哩哔哩、YouTube 或豆瓣链接单独放在一段可自动解析。' => 'Supports Markdown. NetEase Music, Bilibili, YouTube, and Douban links on their own line are embedded automatically.',
        'API 地址' => 'API URL',
        'API 密钥' => 'API key',
        '模型接口' => 'Model API',
        '兼容 OpenAI Chat Completions 格式的服务。' => 'Use a service compatible with the OpenAI Chat Completions format.',
        '可以填写服务根地址或完整的 /chat/completions 地址。' => 'Enter either the service root URL or the complete /chat/completions URL.',
        '密钥仅保存在服务器 SQLite 中，不会发送到浏览器前端。' => 'The key is stored only in server-side SQLite and is never sent to the browser.',
        '弹窗中填写的具体要求会追加到这条系统提示词之后。' => 'Instructions entered in the dialog are appended to this system prompt.',
        '留空时使用管理员账号邮箱作为评论通知收件人。' => 'Leave blank to use the administrator account email for comment notifications.',
        '附件 URL 将使用此地址拼接对象键，留空时使用 S3 Endpoint。' => 'Attachment URLs use this address with the object key. Leave blank to use the S3 endpoint.',
        'Markdown 格式工具栏' => 'Markdown formatting toolbar',
        'S3 附件上传设置' => 'S3 attachment upload settings',
        'SMTP 邮件通知设置' => 'SMTP email notification settings',
        '作者、邮箱、正文或文章' => 'Author, email, content, or post',
        '用逗号分隔，例如 PHP, SQLite, 随笔' => 'Separate with commas, for example PHP, SQLite, Notes',
        '留空将自动生成' => 'Leave blank to generate automatically',
        '留空自动生成' => 'Leave blank to generate automatically',
        '留空将自动从正文截取' => 'Leave blank to generate from the content',
        '一句话介绍自己' => 'A short introduction',
        '例如：修正语病，保持 Markdown 格式；补充一段实际使用示例；将内容改得更简洁。' => 'For example: fix grammar while preserving Markdown, add a practical example, or make the content more concise.',
        '评论筛选' => 'Comment filters',
        '图片' => 'Image',
        '表格' => 'Table',
        '加粗 (Ctrl/Cmd+B)' => 'Bold (Ctrl/Cmd+B)',
        '斜体 (Ctrl/Cmd+I)' => 'Italic (Ctrl/Cmd+I)',
        '链接 (Ctrl/Cmd+K)' => 'Link (Ctrl/Cmd+K)',
        'PHP, SQLite, 博客' => 'PHP, SQLite, Blog',
        '京 ICP 备 12345678 号' => 'ICP 12345678',
        '博客后台概览' => 'Blog dashboard',
        '博客文章管理' => 'Blog post management',
        '博客评论管理' => 'Blog comment management',
        '博客分类管理' => 'Blog category management',
        '友情链接管理' => 'Link management',
        '博客文章编辑器' => 'Blog post editor',
        '博客站点设置' => 'Blog site settings',
        '作者：' => 'Author: ',
        '若博客安装在子目录，请把' => 'If the blog is installed in a subdirectory, change',
        '改为包含子目录的入口路径，例如' => 'to an entry path containing the subdirectory, for example',
        '，并为当前目录设置' => ', and set the following for the current directory:',
        '。项目根目录已有可直接使用的' => '. The project root already includes a ready-to-use',
        'Aqua Glass 液态玻璃' => 'Aqua Glass',
        'HammerOS 锤伴' => 'HammerOS',
        'Nebula 星云' => 'Nebula',
        '明亮、通透的苹果风格阅读主题。支持深浅模式、响应式导航、文章封面、玻璃质感控件与完整内容页面。' => 'A bright, translucent Apple-inspired reading theme with light and dark modes, responsive navigation, post covers, glass controls, and complete content pages.',
        '拟人化内容主题：瓷白机身、实体键感、系统管家与安静的阅读工作台。' => 'A personable content theme with a porcelain-white shell, tactile controls, a system companion, and a quiet reading workspace.',
        '深空极光 · 玻璃拟态 · 暗色优先。星空粒子背景、渐变封面卡片、时间轴归档与标签云，支持亮暗主题切换。' => 'Deep-space aurora, glassmorphism, and dark-first styling with a starfield, cover cards, timeline archives, tag clouds, and light/dark modes.',
        '演示样式覆盖、head action 与 body_class filter 的入门主题。' => 'A starter theme demonstrating style overrides, the head action, and the body_class filter.',
        '移植自 Halo Theme Ying 的白色极简内容主题，适配当前博客的文章、评论、归档、标签与友链。' => 'A minimal white content theme adapted from Halo Theme Ying for posts, comments, archives, tags, and links.',
        '确定永久删除这条评论吗？' => 'Permanently delete this comment?',
        '确定永久删除选中的评论吗？' => 'Permanently delete the selected comments?',
        '确定删除这个空分类吗？' => 'Delete this empty category?',
        '确定删除这个链接吗？' => 'Delete this link?',
        '确定删除选中的标签吗？文章本身不会被删除。' => 'Delete the selected tags? Posts will not be deleted.',
        '确定删除这个管理员吗？' => 'Delete this administrator?',
        '确定删除这篇文章吗？' => 'Delete this post?',
        '没有发现有效插件。请将插件放入 ' => 'No valid plugins were found. Place plugins in ',
        '主题只影响前台页面。' => 'Themes affect public pages only.',
        '将自定义主题放入 ' => 'Place custom themes in ',
        '，刷新页面后即可选择。' => ', then refresh this page to select one. ',
        '发布时间晚于当前时间会按定时发布处理。' => 'A future publication time schedules the content.',
        '独立页面可以不填标签。' => 'Tags are optional for standalone pages.',
        '每个附件最大 30M。' => 'Maximum attachment size: 30 MB.',
        '仅对独立页面生效。' => 'Applies only to standalone pages.',
        '名称、地址、首页展示与伪静态配置。' => 'Name, URL, homepage display, and pretty URL settings.',
        '管理文章、独立页面、分类、状态和浏览量。' => 'Manage posts, standalone pages, categories, status, and views.',
        '插件不存在或操作无效。' => 'The plugin does not exist or the operation is invalid.',
        '所选主题不存在或 theme.json 无效。' => 'The selected theme does not exist or has an invalid theme.json.',
        '重置请求过于频繁，请 15 分钟后再试。' => 'Too many reset requests. Try again in 15 minutes.',
        '登录尝试过多，请 15 分钟后再试。' => 'Too many sign-in attempts. Try again in 15 minutes.',
        '用户名或密码不正确。' => 'The username or password is incorrect.',
        '密码已更新，请使用新密码登录。' => 'The password was updated. Sign in with the new password.',
        '两次输入的密码不一致。' => 'The passwords do not match.',
        '新密码至少需要 8 个字符。' => 'The new password must contain at least 8 characters.',
        '密码至少需要 8 个字符。' => 'The password must contain at least 8 characters.',
        '请求已失效' => 'Request expired',
        '请刷新页面后重试。' => 'Refresh the page and try again.',
        '你访问的地址没有匹配到任何页面。' => 'The requested address does not match any page.',
        '可能还未发布，或者链接已经失效。' => 'It may not be published yet, or the link may have expired.',
        '这篇文章当前无法接收评论。' => 'This post cannot accept comments right now.',
        '评论功能当前已关闭。' => 'Comments are currently disabled.',
        '提交过于频繁，请稍后再试。' => 'You are submitting too frequently. Try again later.',
        '提交过快，请稍后再试。' => 'The form was submitted too quickly. Try again later.',
        '回复目标不存在或当前不可用。' => 'The comment you are replying to is unavailable.',
        '回复目标已不可用，请重新选择。' => 'The reply target is no longer available. Choose another comment.',
        '这条评论已经提交过了。' => 'This comment has already been submitted.',
        '请填写有效的邮箱地址。' => 'Enter a valid email address.',
        '网站地址必须是有效的 HTTP 或 HTTPS 链接。' => 'The website must be a valid HTTP or HTTPS URL.',
        '评论内容不能超过 3000 个字符。' => 'Comments cannot exceed 3,000 characters.',
        '请填写评论内容。' => 'Enter a comment.',
        '请填写昵称。' => 'Enter a display name.',
        '昵称不能超过 50 个字符。' => 'The display name cannot exceed 50 characters.',
        '站点设置已更新。' => 'Site settings updated.',
        '主题已启用。' => 'Theme enabled.',
        '插件已启用。' => 'Plugin enabled.',
        '插件已停用。' => 'Plugin disabled.',
        'AI 设置已保存。' => 'AI settings saved.',
        '邮件通知设置已保存。' => 'Email notification settings saved.',
        'S3 上传设置已保存。' => 'S3 upload settings saved.',
        '分类已保存。' => 'Category saved.',
        '分类已创建。' => 'Category created.',
        '分类已删除。' => 'Category deleted.',
        '链接已更新。' => 'Link updated.',
        '链接已添加。' => 'Link added.',
        '链接已删除。' => 'Link deleted.',
        '标签名称和 Slug 已更新。' => 'Tag name and slug updated.',
        '用户已更新。' => 'User updated.',
        '用户已添加。' => 'User added.',
        '文章已创建。' => 'Post created.',
        '文章已保存。' => 'Post saved.',
        '文章已发布。' => 'Post published.',
        '文章已转为草稿。' => 'Post moved to drafts.',
        '文章已删除。' => 'Post deleted.',
        '评论已发布。' => 'Comment published.',
        '已登录后台。' => 'Signed in to the admin panel.',
        '所有评论通知已标为已读。' => 'All comment notifications were marked as read.',
        '当前没有未读评论。' => 'There are no unread comments.',
        '请先选择评论。' => 'Select at least one comment.',
        '未知的评论操作。' => 'Unknown comment action.',
        '分类名称不能为空。' => 'The category name is required.',
        '标题不能为空。' => 'The title is required.',
        '正文不能为空。' => 'The content is required.',
        '文章必须选择一个分类。' => 'Select a category for the post.',
        '发布时间格式不正确。' => 'The publication time is invalid.',
        '用户名不能为空。' => 'The username is required.',
        '昵称不能为空。' => 'The display name is required.',
        '用户名已存在。' => 'The username already exists.',
        '不能删除当前登录账号。' => 'You cannot delete the account currently signed in.',
        '系统必须保留至少一个管理员。' => 'At least one administrator account is required.',
        '请填写网站名称。' => 'Enter a site name.',
        '请填写有效的 HTTP 或 HTTPS 地址。' => 'Enter a valid HTTP or HTTPS URL.',
        '网站图标地址格式不正确。' => 'The site icon URL is invalid.',
        '原标签和新标签不能为空。' => 'The current and new tag names are required.',
        '新标签不能包含逗号。' => 'The new tag cannot contain commas.',
        'Slug 格式不正确。' => 'The slug format is invalid.',
        'Slug 已被其他标签使用。' => 'The slug is already used by another tag.',
        '请先选择需要删除的标签。' => 'Select tags to remove.',
        '找不到需要编辑的文章。' => 'The post to edit was not found.',
        '找不到需要变更状态的文章。' => 'The post was not found.',
        '请先登录后台。' => 'Sign in to the admin panel first.',
        '博客概览' => 'Overview',
        '后台概览' => 'Dashboard',
        '撰写文章' => 'Write',
        '写新文章' => 'New Post',
        '编辑文章' => 'Edit Post',
        '编辑内容' => 'Edit Content',
        '文章管理' => 'Posts',
        '评论管理' => 'Comments',
        '分类管理' => 'Categories',
        '标签管理' => 'Tags',
        '友情链接' => 'Links',
        '用户管理' => 'Users',
        'AI 设置' => 'AI Settings',
        '邮件通知' => 'Email Notifications',
        'S3 存储' => 'S3 Storage',
        '主题管理' => 'Themes',
        '站点设置' => 'Site Settings',
        '插件管理' => 'Plugins',
        '管理导航' => 'Administration',
        '浏览与统计' => 'Browse and statistics',
        '发布文章或页面' => 'Publish posts or pages',
        '列表与发布' => 'List and publishing',
        '审核与通知' => 'Moderation and alerts',
        '分类与排序' => 'Categories and ordering',
        '重命名与清理' => 'Rename and clean up',
        '添加、排序与维护' => 'Add, order, and maintain',
        '模型与接口' => 'Model and API',
        'SMTP 设置' => 'SMTP settings',
        '附件上传设置' => 'Attachment upload settings',
        '预览与切换' => 'Preview and switch',
        '扩展与语言包' => 'Extensions and languages',
        '基础配置' => 'Basic configuration',
        '控制台 /' => 'Dashboard /',
        '打开后台菜单' => 'Open admin menu',
        '关闭后台菜单' => 'Close admin menu',
        '切换到深色模式' => 'Switch to dark mode',
        '切换到浅色模式' => 'Switch to light mode',
        '用户设置' => 'User settings',
        '退出登录' => 'Sign out',
        '退出' => 'Sign out',
        '后台导航' => 'Admin navigation',
        '检测更新' => 'Check for updates',
        '评论通知' => 'Comment notifications',
        '暂无未读评论' => 'No unread comments',
        '网站首页' => 'View site',
        '文章' => 'Post',
        '页面' => 'Page',
        '标题' => 'Title',
        '正文' => 'Content',
        '摘要' => 'Excerpt',
        '分类' => 'Category',
        '标签' => 'Tags',
        '状态' => 'Status',
        '操作' => 'Actions',
        '类型' => 'Type',
        '更新时间' => 'Updated',
        '发布时间' => 'Publish at',
        '创建时间' => 'Created',
        '浏览量' => 'Views',
        '草稿' => 'Draft',
        '已发布' => 'Published',
        '定时' => 'Scheduled',
        '待审核' => 'Pending',
        '已通过' => 'Approved',
        '垃圾评论' => 'Spam',
        '垃圾' => 'Spam',
        '未读' => 'Unread',
        '全部' => 'All',
        '发布' => 'Publish',
        '转草稿' => 'Move to drafts',
        '查看' => 'View',
        '编辑' => 'Edit',
        '修改' => 'Edit',
        '删除' => 'Delete',
        '保存修改' => 'Save changes',
        '创建文章' => 'Create post',
        '保存设置' => 'Save settings',
        '设置' => 'Settings',
        '启用' => 'Enable',
        '停用' => 'Disable',
        '已启用' => 'Enabled',
        '未启用' => 'Disabled',
        '等待加载' => 'Waiting to load',
        '加载失败' => 'Load failed',
        '插件' => 'Plugin',
        '版本' => 'Version',
        '作者' => 'Author',
        '说明' => 'Information',
        '登录' => 'Sign in',
        '登录后台' => 'Sign in',
        '管理后台' => 'Admin panel',
        '博客后台登录' => 'Blog admin sign-in',
        '博客插件管理' => 'Blog plugin management',
        '用户名' => 'Username',
        '密码' => 'Password',
        '找回密码' => 'Forgot password',
        '忘记密码？' => 'Forgot password?',
        '显示密码' => 'Show password',
        '隐藏密码' => 'Hide password',
        '设置新密码' => 'Set new password',
        '确认新密码' => 'Confirm new password',
        '返回登录' => 'Back to sign in',
        '邮箱' => 'Email',
        '昵称' => 'Display name',
        '网站地址' => 'Website',
        '评论内容' => 'Comment',
        '发表评论' => 'Post comment',
        '回复' => 'Reply',
        '取消回复' => 'Cancel reply',
        '评论' => 'Comments',
        '归档' => 'Archives',
        '链接' => 'Links',
        '首页' => 'Home',
        '关于' => 'About',
        '上一页' => 'Previous',
        '下一页' => 'Next',
        '暂无内容。' => 'No content yet.',
        '还没有文章。' => 'No posts yet.',
        '还没有评论。' => 'No comments yet.',
        '还没有标签。' => 'No tags yet.',
        '还没有添加友情链接。' => 'No links have been added yet.',
        '文章不存在' => 'Post not found',
        '页面不存在' => 'Page not found',
        '标签不存在' => 'Tag not found',
        '分类不存在' => 'Category not found',
        '标签索引' => 'Tag index',
        '分类文章' => 'Category posts',
        '已发布文章归档' => 'Published post archive',
        '搜索评论' => 'Search comments',
        '筛选' => 'Filter',
        '应用' => 'Apply',
        '批量操作' => 'Bulk action',
        '标为已读' => 'Mark as read',
        '通过' => 'Approve',
        '标为垃圾' => 'Mark as spam',
        '批量删除' => 'Delete selected',
        '新建分类' => 'New category',
        '编辑分类' => 'Edit category',
        '创建分类' => 'Create category',
        '保存分类' => 'Save category',
        '分类名称' => 'Category name',
        '排序' => 'Order',
        '描述' => 'Description',
        '添加链接' => 'Add link',
        '编辑链接' => 'Edit link',
        '网站名称' => 'Site name',
        '网址' => 'URL',
        '网站图标' => 'Site icon',
        '添加用户' => 'Add user',
        '编辑用户' => 'Edit user',
        '站点名称' => 'Site name',
        '首页副标题' => 'Homepage tagline',
        '站点描述' => 'Site description',
        '站点关键字' => 'Site keywords',
        '站点地址' => 'Site URL',
        '前台主题' => 'Frontend theme',
        '当前主题' => 'Current theme',
        '打开预览' => 'Open preview',
        '作者未注明' => 'Author not specified',
        '该主题没有提供说明。' => 'No description was provided for this theme.',
        'Favicon 地址' => 'Favicon URL',
        '备案号' => 'Registration number',
        '首页每页文章数' => 'Posts per page',
        '评论设置' => 'Comment settings',
        '允许访客提交评论' => 'Allow visitor comments',
        '新评论显示后台提醒' => 'Show admin alerts for new comments',
        '伪静态 URL' => 'Pretty URLs',
        '关闭' => 'Off',
        '关闭窗口' => 'Close',
        '开启' => 'On',
        '页脚文案' => 'Footer text',
        '支持 {year} 占位符' => 'Supports the {year} placeholder',
        'Head 自定义代码' => 'Custom head code',
        '内容类型' => 'Content type',
        '文章分类' => 'Post category',
        '置顶文章' => 'Pin post',
        '显示评论' => 'Show comments',
        '上传附件' => 'Upload attachment',
        '选择文件或拖到这里' => 'Choose a file or drop it here',
        'AI 生成' => 'Generate with AI',
        'AI 摘要' => 'AI excerpt',
        'AI 润色' => 'Polish with AI',
        'AI 润色正文' => 'Polish content with AI',
        '润色或生成要求' => 'Editing or generation instructions',
        '取消' => 'Cancel',
        '确定并填入正文' => 'Apply to content',
        '标题级别' => 'Heading level',
        '一级标题' => 'Heading 1',
        '二级标题' => 'Heading 2',
        '三级标题' => 'Heading 3',
        '加粗' => 'Bold',
        '斜体' => 'Italic',
        '删除线' => 'Strikethrough',
        '行内代码' => 'Inline code',
        '引用' => 'Quote',
        '无序列表' => 'Bulleted list',
        '有序列表' => 'Numbered list',
        '任务列表' => 'Task list',
        '插入链接' => 'Insert link',
        '插入图片' => 'Insert image',
        '插入表格' => 'Insert table',
        '代码块' => 'Code block',
        '分隔线' => 'Horizontal rule',
        '字符' => 'characters',
        'AI 模型设置' => 'AI model settings',
        '接口地址' => 'API URL',
        '模型名称' => 'Model name',
        'Slug 提示词' => 'Slug prompt',
        '摘要提示词' => 'Excerpt prompt',
        '润色提示词' => 'Editing prompt',
        '保存 AI 设置' => 'Save AI settings',
        '启用 SMTP 邮件通知' => 'Enable SMTP email notifications',
        'SMTP 主机' => 'SMTP host',
        '端口' => 'Port',
        '加密方式' => 'Encryption',
        '无' => 'None',
        'SMTP 账号' => 'SMTP username',
        'SMTP 密码' => 'SMTP password',
        '发件邮箱' => 'Sender email',
        '发件名称' => 'Sender name',
        '通知收件邮箱' => 'Notification recipient',
        '保存邮件设置' => 'Save email settings',
        'S3 上传设置' => 'S3 upload settings',
        '启用 S3 上传' => 'Enable S3 uploads',
        '在本地保留上传备份' => 'Keep a local upload backup',
        '使用 Path-style 地址（MinIO 等兼容服务常用）' => 'Use path-style URLs (common for MinIO and compatible services)',
        '对象路径前缀' => 'Object path prefix',
        'CDN 域名' => 'CDN URL',
        '保存 S3 设置' => 'Save S3 settings',
        '内置终端主题' => 'Built-in terminal theme',
        '程序自带的终端风格前台主题。' => 'The built-in terminal-style frontend theme.',
        '默认分类' => 'Default category',
        '未分类' => 'Uncategorized',
        '系统默认文章分类。' => 'Default post category.',
        '（当前）' => ' (current)',
        '（留空则不修改）' => ' (leave blank to keep unchanged)',
        '已保存，留空则不修改' => 'Saved; leave blank to keep unchanged',
        '授权码或密码' => 'App password or password',
        '主导航' => 'Main navigation',
        '切换深浅模式' => 'Toggle color theme',
        '打开菜单' => 'Open menu',
        '系统管家' => 'System companion',
        '管理员当前离线' => 'Administrator offline',
        '管理员当前在线' => 'Administrator online',
        '和系统管家打个招呼' => 'Say hello to the system companion',
        '打个招呼' => 'Say hello',
        '下午好，我是这里的系统管家。' => 'Good afternoon. I am your system companion.',
        '上午好，我是这里的系统管家。' => 'Good morning. I am your system companion.',
        '晚上好，我是这里的系统管家。' => 'Good evening. I am your system companion.',
        '内容已经替你整理好了。' => 'Your reading list is ready.',
        '站点数据' => 'Site statistics',
        '天' => 'days',
        '今天，也有一些值得读的事' => 'Something worth reading today',
        '继续阅读' => 'Continue reading',
        '回到顶部' => 'Back to top',
        '朋友' => 'Friends',
    ];
}

function sblog_english_client_translations(): array
{
    return [
        'switch_to_light' => 'Switch to light mode',
        'switch_to_dark' => 'Switch to dark mode',
        'hide_password' => 'Hide password',
        'show_password' => 'Show password',
        'close_admin_menu' => 'Close admin menu',
        'open_admin_menu' => 'Open admin menu',
        'file_too_large' => 'File exceeds 30 MB',
        'waiting_to_upload' => 'Waiting to upload',
        'uploading' => 'Uploading...',
        'upload_failed' => 'Upload failed',
        'uploaded_and_inserted' => 'Uploaded and inserted as Markdown',
        'upload_complete' => 'Upload complete',
        'character_count' => '{count} characters',
        'bold_text' => 'bold text',
        'italic_text' => 'italic text',
        'strikethrough_text' => 'strikethrough text',
        'code' => 'code',
        'link_text' => 'link text',
        'image_description' => 'image description',
        'column_1' => 'Column 1',
        'column_2' => 'Column 2',
        'column_3' => 'Column 3',
        'table_content' => 'Content',
        'enter_code_here' => 'Enter code here',
        'ai_invalid_response' => 'The AI service returned an unreadable response.',
        'ai_generation_failed' => 'AI generation failed.',
        'generating' => 'Generating...',
        'ai_processing_content' => 'AI is processing the content...',
        'cancel_reply_to' => 'Cancel reply to @{author}',
        'cancel_reply' => 'Cancel reply',
    ];
}

function sblog_english_translate_phrase(string $phrase): string
{
    $translations = sblog_english_language_map();
    if (isset($translations[$phrase])) {
        return (string)$translations[$phrase];
    }

    if (preg_match('/^([\s\S]+?)\s*·\s*([\s\S]+)$/u', $phrase, $titleParts)) {
        $label = trim((string)$titleParts[1]);
        if (isset($translations[$label])) {
            return (string)$translations[$label] . ' · ' . trim((string)$titleParts[2]);
        }
    }
    if (preg_match('/^([\s\S]+?)\s*·$/u', $phrase, $titleParts)) {
        $label = trim((string)$titleParts[1]);
        if (isset($translations[$label])) {
            return (string)$translations[$label] . ' ·';
        }
    }
    if (preg_match('/^(.+)，(\d+) 条未读评论$/u', $phrase, $labelParts)) {
        $label = (string)($translations[$labelParts[1]] ?? $labelParts[1]);
        return $label . ', ' . $labelParts[2] . ' unread comments';
    }
    if (preg_match('/^预览主题 (.+)$/u', $phrase, $previewParts)) {
        $theme = (string)($translations[$previewParts[1]] ?? $previewParts[1]);
        return 'Preview theme ' . $theme;
    }

    $patterns = [
        '/^选择 (.+) 的评论$/u' => 'Select $1\'s comment',
        '/^回复 @(.+)$/u' => 'Reply to @$1',
        '/^(.+)（当前）$/u' => '$1 (current)',
        '/^(\d+) 字符$/u' => '$1 characters',
        '/^当前筛选 (\d+) 条，审核状态与未读通知独立管理。$/u' => '$1 results in this filter. Moderation status and unread alerts are managed separately.',
        '/^标签来自文章内容，共 (\d+) 个。$/u' => '$1 tags found in post content.',
        '/^确定更新到 (.+) 吗？更新期间请勿关闭页面。$/u' => 'Update to $1? Do not close this page during the update.',
        '/^(\d+)\s*条未读评论$/u' => '$1 unread comments',
        '/^(\d+)\s*条评论$/u' => '$1 comments',
        '/^(\d+)\s*个主题$/u' => '$1 themes',
        '/^作者：(.+)$/u' => 'By $1',
        '/^第\s*(\d+)\s*页$/u' => 'Page $1',
        '/^(\d{4})年(\d{1,2})月(\d{1,2})日$/u' => '$1-$2-$3',
        '/^(\d{4})年(\d{1,2})月$/u' => '$1-$2',
        '/^阅读《(.+)》$/u' => 'Read "$1"',
        '/^继续阅读《(.+)》$/u' => 'Continue reading "$1"',
        '/^打开用户菜单：(.+)$/u' => 'Open user menu: $1',
        '/^登录 · (.+)$/u' => 'Sign in · $1',
        '/^找回密码 · (.+)$/u' => 'Forgot password · $1',
        '/^设置新密码 · (.+)$/u' => 'Set new password · $1',
        '/^选择 (.+)$/u' => 'Select $1',
        '/^已删除 (\d+) 条评论。$/u' => 'Deleted $1 comments.',
        '/^已将 (\d+) 条评论标为已读。$/u' => 'Marked $1 comments as read.',
        '/^已通过 (\d+) 条评论。$/u' => 'Approved $1 comments.',
        '/^已将 (\d+) 条评论标记为垃圾。$/u' => 'Marked $1 comments as spam.',
        '/^已将 (\d+) 条评论转为待审核。$/u' => 'Moved $1 comments to pending.',
        '/^已更新到 (.+)，并已同步内置主题和插件。$/u' => 'Updated to $1, with bundled themes and plugins synchronized.',
        '/^程序已更新，但内置主题和插件同步失败：(.+)$/u' => 'The application was updated, but bundled theme and plugin synchronization failed: $1',
        '/^连接 S3 失败：(.+)$/u' => 'Could not connect to S3: $1',
        '/^S3 删除对象失败（HTTP (\d+)）。$/u' => 'Failed to delete the S3 object (HTTP $1).',
        '/^S3 返回异常（HTTP (\d+)）。(.*)$/u' => 'S3 returned an error (HTTP $1).$2',
    ];
    foreach ($patterns as $pattern => $replacement) {
        if (preg_match($pattern, $phrase)) {
            return (string)preg_replace($pattern, $replacement, $phrase);
        }
    }
    return $phrase;
}

function sblog_english_translate_text_node(string $text): string
{
    if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $text)) {
        return $text;
    }
    if (!preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $parts)) {
        return $text;
    }
    return $parts[1] . sblog_english_translate_phrase($parts[2]) . $parts[3];
}

function sblog_english_translate_tag(string $tag): string
{
    if (!preg_match('/[\x{4e00}-\x{9fff}]/u', $tag)) {
        return $tag;
    }
    $tag = preg_replace_callback(
        '/\b(aria-label|title|placeholder|data-confirm|content)=("|\')(.*?)\2/su',
        static function (array $matches): string {
            $value = html_entity_decode((string)$matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $translated = sblog_english_translate_phrase($value);
            if ($translated === $value) {
                return (string)$matches[0];
            }
            return $matches[1] . '=' . $matches[2] . htmlspecialchars($translated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $matches[2];
        },
        $tag
    ) ?? $tag;

    return preg_replace_callback(
        '/\b(onclick|onsubmit)=("|\')(.*?)\2/su',
        static function (array $matches): string {
            $code = html_entity_decode((string)$matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $translated = preg_replace_callback(
                '/\bconfirm\(("|\')(.*?)\1\)/su',
                static function (array $confirmMatches): string {
                    $message = sblog_english_translate_phrase((string)$confirmMatches[2]);
                    return 'confirm(' . $confirmMatches[1] . $message . $confirmMatches[1] . ')';
                },
                $code
            ) ?? $code;
            if ($translated === $code) {
                return (string)$matches[0];
            }
            return $matches[1] . '=' . $matches[2] . htmlspecialchars($translated, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . $matches[2];
        },
        $tag
    ) ?? $tag;
}

function sblog_english_translate_json_value(mixed $value): mixed
{
    if (is_string($value)) {
        return sblog_english_translate_phrase($value);
    }
    if (is_array($value)) {
        foreach ($value as $key => $item) {
            $value[$key] = sblog_english_translate_json_value($item);
        }
    }
    return $value;
}

add_plugin_filter('output_html', static function (string $html, array $context): string {
    if (stripos($html, '<html') === false) {
        if ((string)($context['action'] ?? '') === 'upload_attachment') {
            $payload = json_decode($html, true);
            if (is_array($payload)) {
                $translated = json_encode(sblog_english_translate_json_value($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                return is_string($translated) ? $translated : $html;
            }
        }
        return $html;
    }

    $html = str_replace(['lang="zh-CN"', "lang='zh-CN'"], ['lang="en"', "lang='en'"], $html);
    $clientTranslations = json_encode(
        sblog_english_client_translations(),
        JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
    );
    if (is_string($clientTranslations)) {
        $script = '<script>window.sblogI18n=Object.assign({},window.sblogI18n||{},' . $clientTranslations . ');</script>';
        $html = preg_replace('/<\/head>/i', $script . '</head>', $html, 1) ?? $html;
    }
    $tokens = preg_split('/(<[^>]+>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    if (is_array($tokens)) {
        $rawElement = '';
        foreach ($tokens as $index => $token) {
            if (str_starts_with($token, '<')) {
                if (preg_match('/^<(script|style|textarea)\b/i', $token, $matches)) {
                    $rawElement = strtolower((string)$matches[1]);
                } elseif ($rawElement !== '' && preg_match('#^</' . preg_quote($rawElement, '#') . '\s*>#i', $token)) {
                    $rawElement = '';
                }
                $tokens[$index] = sblog_english_translate_tag($token);
            } elseif ($rawElement === '') {
                $tokens[$index] = sblog_english_translate_text_node($token);
            }
        }
        $html = implode('', $tokens);
    }

    if (!headers_sent()) {
        header('Content-Language: en');
    }
    return $html;
}, 10);
