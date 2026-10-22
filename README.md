# 🌐 上网入口

一个使用 **PHP + MySQL** 编写的自建网址导航站，适合部署在云服务器上作为个人/小团队的上网入口。包含前台导航、站内搜索、用户登录注册、后台管理、访问统计等功能。

## 功能特性

- **前台导航**：按分类展示常用网站，卡片式布局，响应式适配手机
- **站内搜索**：按标题 / 描述 / 网址模糊搜索，支持多个关键词
- **用户系统**：注册 / 登录 / 退出，管理员与普通用户角色分离
- **后台管理**：
  - 分类管理：新增、编辑、启停、删除（有链接时禁止删除）
  - 链接管理：新增、编辑、启停、删除，按分类/关键词筛选，分页展示；图标支持 emoji 或图片上传（≤2MB，自动保存到 /assets/uploads/，前台以图片显示）
  - 用户管理：创建用户、重置密码、切换角色、禁用账号
- **访问统计**：今日/近 7 日/累计点击、独立访客 IP、近 30 日趋势图、分类点击分布、点击排行、最近点击明细
- **外观设置**（后台）：自定义主题色（预设色板或自定义取色）、背景样式（纯色 / 渐变 / 背景图上传，支持遮罩与视差）、**链接卡片透明度**、站点名称、首页副标题、页脚文字，实时预览
- **文件管理**（后台）：浏览 / 多文件上传 / 复制链接 / 一键设为背景图 / 删除上传目录中的文件，图片带缩略图预览
- **一键安装**：浏览器访问 `install.php` 即可完成建库建表与管理员创建，安装成功后自动把数据库配置写入 `app/config.php`

## 技术栈与环境要求

| 项目 | 要求 |
| --- | --- |
| PHP | 7.4 及以上（推荐 8.x），需 `pdo_mysql` 扩展 |
| MySQL | 5.7 及以上（推荐 8.0），字符集 utf8mb4 |
| Web 服务器 | Nginx / Apache 均可（项目使用普通 GET 参数，无需伪静态配置） |
| 依赖 | 无 Composer、无第三方包、无外部 CDN，纯原生实现 |

## 目录结构

```
上网入口/
├── app/                    # 应用核心（不直接对外）
│   ├── config.php          # 全局配置（数据库、站点信息、功能开关）
│   ├── init.php            # 应用初始化入口
│   ├── functions.php       # 公共函数（XSS 转义 / CSRF / 提示消息等）
│   ├── db.php              # PDO 数据库连接（预处理语句，防注入）
│   ├── auth.php            # 登录 / 注册 / 鉴权
│   └── settings.php        # 站点设置（key-value，含默认值与自动建表）
├── public/                 # ★ Web 站点根目录（部署时指向这里）
│   ├── index.php           # 前台导航首页
│   ├── search.php          # 站内搜索
│   ├── login.php           # 登录
│   ├── register.php        # 注册
│   ├── logout.php          # 退出
│   ├── go.php              # 点击跳转 + 访问统计
│   ├── install.php         # 一键安装向导（安装完成后请删除）
│   ├── assets/             # CSS / JS / 上传的背景图（uploads/）
│   ├── partials/           # 前台公共头部 / 底部
│   └── admin/              # 后台管理（概览/分类/链接/统计/外观/文件/用户）
├── database.sql            # 数据库初始化脚本（含示例数据）
├── Dockerfile              # 可选：容器化构建
└── docker-compose.yml      # 可选：容器化编排
```

## 部署到云服务器（宝塔面板为例）

### 方式 A：一键安装（推荐，无需手动导 SQL）

1. 将 `public/` 与 `app/` 上传到服务器站点目录（例如 `/www/wwwroot/nav.example.com/`）。
2. 宝塔「网站」→ 你的站点 →「设置」→「网站目录」→ 运行目录选择 **`/public`** → 保存。
3. 在宝塔「数据库」中新建一个 MySQL 数据库，记下库名、用户名、密码。
4. 打开浏览器访问 `http://你的域名/install.php`：
   - 填入第 3 步的数据库地址、端口、库名、用户名、密码；
   - 设置管理员用户名与密码；
   - 点击「开始安装」。安装成功后会把表单里的数据库配置**自动写入 app/config.php**（若目录不可写，页面上会给出需要手动填写的具体值），完成后**删除服务器上的 `install.php` 文件**。
5. 访问 `http://你的域名/` 即可看到导航首页，`/admin/` 进入后台。

> 不希望在页面输入数据库密码？可直接导入 `database.sql`（方式 B），并在 `app/config.php` 中填写数据库账号密码。

### 方式 B：导入 SQL + 改配置

1. 上传源码，站点运行目录指向 `/public`（同方式 A 第 2 步）。
2. 宝塔「数据库」新建数据库 → 在 phpMyAdmin 中导入本项目的 `database.sql`（或在服务器执行 `mysql -u 用户名 -p 库名 < database.sql`）。
3. 修改 `app/config.php`，把 `db` 部分的 host/name/user/pass 改成实际值。
4. 访问站点，默认管理员 **admin / admin123**，登录后请立即修改密码。

### 方式 C：Docker 部署（可选）

```bash
docker compose up -d --build
# 访问 http://服务器IP:8080/install.php 完成安装
```

数据库配置已通过环境变量注入（见 `docker-compose.yml`），默认库名 `net_portal`、用户 `portal` / 密码 `portalpass`。安装完成后同样建议删除容器内 `install.php`。

**Docker 构建常见问题**

- **报 `failed to resolve source metadata ... docker.fnnas.com ... 401 Unauthorized`**：报错里**仍出现 `docker.fnnas.com`** 说明镜像源没改成功或没重启（Docker 加速器配置重启后才生效）。先确认：
  1. 查看当前生效的加速器：`docker info` 里 `Registry Mirrors` 一栏，若仍显示 fnnas 说明配置没变；
  2. 修改加速器：Linux 编辑 `/etc/docker/daemon.json` 的 `registry-mirrors`（去掉 `docker.fnnas.com`，换成 `https://docker.1panel.live`、`https://docker.m.daocloud.io`、`https://docker.1ms.run`），飞牛/群晖等 NAS 在 Docker 设置的「镜像加速」里改；
  3. **改完必须重启 Docker**（`systemctl restart docker`，NAS 在设置里保存后通常自动重启）；
  4. 验证：`docker pull php:8.2-apache` 成功即生效。
  **绕开系统配置的备用方案（已内置在项目文件里）**：本次打包的 `Dockerfile` 与 `docker-compose.yml` 已直接把镜像名写成 `docker.1panel.live/library/...` 前缀，不依赖系统加速器配置，重新上传这两个文件再构建即可；想换其他源改回 `php:8.2-apache` / `mysql:8.0` 即可。
- **卡在拉取镜像 / 报 network timeout**：同上，先换镜像源再重试。
- **`condition: service_healthy` 报错 / 老版本 compose**：新版 `docker-compose.yml` 已改用普通 `depends_on`，任意 compose 版本可用；若仍报错请升级 compose（`apt install docker-compose-plugin` 或使用 `docker compose` 插件版）。
- **网页 404 / 白屏**：确认访问的是 `http://IP:8080` 且安装了 `install.php`（`8080` 端口映射见 compose）。
- **改代码后不生效**：`docker compose up -d --build` 重新构建（代码是打进镜像的）。

## 默认账号

| 账号 | 密码 | 角色 | 说明 |
| --- | --- | --- | --- |
| admin | admin123 | 管理员 | **首次登录后请立即在后台修改密码** |

## 常用配置项（app/config.php）

| 配置 | 说明 |
| --- | --- |
| `db.*` | 数据库连接信息（也可用环境变量 `DB_HOST/DB_PORT/DB_NAME/DB_USER/DB_PASS` 覆盖） |
| `site.name` | 站点名称，默认「上网入口」 |
| `site.base_url` | 站点完整地址；留空自动探测 |
| `app.registration_open` | 是否开放注册，默认开；设环境变量 `REG_OPEN=0` 关闭 |
| `app.debug` | 调试模式（显示 PHP 错误），生产环境保持关闭；调试用 `APP_DEBUG=1` |

## 安全说明

- 所有 SQL 均使用 **PDO 预处理语句**，杜绝注入；
- 密码使用 `password_hash()`（bcrypt）存储，永不落明文；
- 所有表单带 **CSRF Token** 校验；输出经 `htmlspecialchars` 转义防 XSS；
- 会话 Cookie 启用 `HttpOnly + SameSite=Lax`；登录成功会重置会话 ID；
- 登录有简单防爆破（同一会话连续失败 5 次锁定 15 分钟）；
- 链接只允许 `http/https` 协议，防止 `javascript:` 等危险协议注入；
- 上传文件：扩展名白名单（图片/ico/pdf/zip/txt/md/csv）、图片真实内容校验、文件名系统随机生成、单文件 10MB 上限；上传目录带 `.htaccess` 禁止执行脚本；
- 管理后台所有操作校验管理员角色，且不能停用/降权自己。

## 常见问题

**首页白屏 / 500**：先到宝塔把 PHP 错误显示打开，或看 `/www/wwwroot/站点/log` 下的 PHP 日志；多半是 `app/config.php` 数据库配置不对。

**数据库连接失败**：确认数据库地址不是 `localhost`（宝塔默认 `127.0.0.1`）、端口、账号权限；远程库需放行权限。

**中文乱码**：确认数据库、数据表字符集为 `utf8mb4`，且导入时选择 utf8mb4 编码。

**访问 `/admin/` 报 404**：站点运行目录未指向 `/public`（宝塔网站目录里设置）。

**install.php 提示「数据库已包含数据」或 `Duplicate entry 'admin' for key 'username'`**：目标数据库里已有数据（之前导入过 `database.sql`，或已经安装过一次）。此时安装向导会主动中止、不做任何写入。两种处理方式：
- 直接用已有账号登录（若数据来自 `database.sql`，默认管理员是 `admin / admin123`）；
- 确实要重装：在 phpMyAdmin 中清空 `users / categories / links / click_logs` 四个数据表（或直接删除这四个表），再访问 install.php；或者新建一个空数据库并同步修改 `app/config.php` 后重装。

**想关闭注册**：`app/config.php` 里设 `REG_OPEN=0`（环境变量）即可，前台注册入口与注册页都会失效。

**老版本升级**：直接把新版 `app/`、`public/` 覆盖上传即可。首次访问站点时会自动创建 `settings` 表并写入默认值（后台自动出现「外观设置」页），无需手动改数据库；若数据库账号没有建表权限，将静默使用内置默认外观，不影响其他功能。

## 开发计划（可选）

- [ ] 后台批量导入导出链接（CSV）
- [ ] 链接置顶 / 图标自动抓取（favicon）
- [ ] 点击量按天聚合表，提升大数据量下的统计性能
