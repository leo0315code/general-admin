# QA 独立验收报告：general-admin UI 现代化重构（T01–T05）

> 验收人：QA 工程师 严过关（独立验收，不采信工程师自测结论）
> 日期：2026-09-17
> 范围：docs/prd-ui-refactor.md §6 验收标准 + docs/design-ui-refactor.md §13 验收命令
> 环境：Laravel 13 / PHP 8.3 / MySQL（本地 127.0.0.1:3306 general_admin 可连）/ sqlite in-memory 测试

---

## 0. 验收结论（TL;DR）

| 项 | 结论 |
| --- | --- |
| 全量回归 | ✅ **197 passed（634 assertions）**（基线 181 → 工程师 194 → 验收 197，含 QA 新增 3 个守卫测试） |
| 新增测试有效性 | ✅ 4 个新测试均为**真断言**，无假绿；QA 补强 1 个测试文件（SeedLargeDatasetTest） |
| 安全验收 SEC-1/2/4 | ✅ ConfirmModal 未削弱后端校验；XSS 审计通过；安全响应头实测通过 |
| 性能验收 PERF | ✅ ListQuery 白名单/非法回退通过；索引迁移纯增量且 MySQL 实测生效；造数命令**发现 2 个源码缺陷** |
| 智能路由 | ⚠️ **转工程师修复 2 个问题**（均在 `app/Console/Commands/SeedLargeDataset.php`），其余通过 |

---

## 1. 全量回归结果

- 命令：`APP_KEY='base64:...' php artisan test`（sqlite in-memory）
- 结果：**Tests: 197 passed (634 assertions)，Duration 13.27s，0 失败**
- 与工程师汇报（194 passed）一致，且新增了 QA 补的 3 个测试后仍全绿。
- `npm run build` ✅ 成功（vite 148ms，CSS 76.95 kB / JS 56.48 kB）
- `php artisan view:cache` ✅ Blade 模板全部编译通过（13 个新组件可解析）

---

## 2. 新增测试有效性审查（防"假绿"专项）

| 测试文件 | 断言方式 | 有效性结论 |
| --- | --- | --- |
| `ListQueryTest.php`（7 用例） | 逐值断言 per_page 白名单/非法回退、sort 白名单/注入回退、sort_dir 二值化、数组输入不抛错 | ✅ 真断言；覆盖 `evil;drop--`、`created_at);drop--`、`id asc` 等注入样本 |
| `QueryCountTest.php`（2 用例） | users/posts 列表 SQL 数 ≤8（预载 RBAC 权限集后测量） | ✅ 真断言；10 行数据下若引入 N+1（+10 查询）必然破界；测量方法合理 |
| `SecurityHeadersTest.php`（3 用例） | assertHeader 精确值 + 生产 env 下 CSP/HSTS 内容 contains | ✅ 真断言；含 guest 页与生产分支 |
| `ConfirmModalGrepTest.php`（1 用例） | 递归扫描 resources/views 中 `confirm(` = 0 | ✅ 真断言；与 QA 独立 grep 结果一致（0 命中） |
| `SeedLargeDatasetTest.php`（QA 新增，3 用例） | testing 拒绝执行 / production 无 --force 拒绝 / production + --force 小规模成功 | ✅ 真断言；补上造数命令守卫回归缺口 |

**未发现弱断言/空断言/恒真断言。** 唯一覆盖缺口是 SeedLargeDataset 命令本身（无任何既有测试），QA 已补守卫测试；命令的 2 个业务缺陷见 §5。

---

## 3. 安全验收（SEC-1 / SEC-2 / SEC-4）

### 3.1 安全响应头（SEC-4）— ✅ 通过
- 注册：`bootstrap/app.php` web 组 append `SecurityHeaders::class`（在 LogOperation 之后）✅
- 实测头（SecurityHeadersTest 通过 + 代码核对）：
  - `X-Frame-Options: SAMEORIGIN`（恒有）
  - `X-Content-Type-Options: nosniff`（恒有）
  - `Referrer-Policy: strict-origin-when-cross-origin`（恒有）
  - 生产（APP_ENV=production）追加：
    - `Strict-Transport-Security: max-age=31536000; includeSubDomains`
    - `Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self' https://fonts.bunny.net; connect-src 'self'`
  - CSP 保留 `'unsafe-inline'` 与设计一致（兼容 Alpine 内联表达式 + 防闪烁脚本）。

### 3.2 ConfirmModal 后端校验未削弱（SEC-1）— ✅ 通过
逐一审计破坏性表单（users/index、users/trash、posts/index、posts/trash、bulk-actions 组件）：

| 检查点 | 结果 |
| --- | --- |
| 行内删除/停用/还原/彻底删除表单仍为**真实 `<form>`** 提交到原控制器路由 | ✅（users.destroy / users.toggle-status / users.restore / users.force-destroy / posts.destroy / posts.toggle-status / posts.bulk-delete / users.bulk-delete / users.bulk-toggle-status） |
| `@csrf` + `@method('DELETE'/'PATCH')` 保留 | ✅ 全部保留 |
| 后端 Gate/Policy 仍在 | ✅ UserController: destroy→`users.destroy`、toggleStatus/bulkDestroy/bulkToggleStatus→`user.manage`、restore/forceDestroy→`user.manage`；PostController: destroy/toggle→`authorize('delete')`、bulkDestroy→`post.manage` + 逐 id 复用 delete 策略 |
| 自我保护 + 最后 admin 保护在批量路径生效 | ✅ bulkDestroy/bulkToggleStatus 均含 skip 自身 / last-active-admin 逻辑 |
| 批量 ids[] 清洗 | ✅ `validatedIds()` 仅保留正整数、去重、非数组返回空 |
| ConfirmModal 仅为 UI 层 | ✅ Alpine store `confirmModal.submit()` → `form.submit()`，不伪造请求 |
| `confirm(` 残留 | ✅ grep 全仓 resources/views = **0**；`onsubmit` = 0 |
| 破坏性操作表单挂载确认弹窗 | ✅ 删除/停用/彻底删除均带 data-confirm-title/message + `@click.prevent="Alpine.store('confirmModal').open($el.closest('form'))"` |

### 3.3 XSS 审计（SEC-2）— ✅ 通过
- `grep -rn "{!!" resources/views/` → 仅 `vendor/pagination/*` 内的框架静态翻译串（`{!! __('...') !!}`），**非用户内容**，无业务视图使用 `{!! !!}` 渲染变量。
- `grep -rn "v-html" resources/views/ resources/js/` → **0 命中**。
- 抽查 users/index、users/trash、posts/index、users/create、layouts/app、sidebar：用户可控字段（name/email/title/description/IP）全部 `{{ }}` 转义输出；Alpine 动态文本一律 `x-text`。
- 排序链接由白名单列名 + `http_build_query` 生成，且服务端 ListQuery 二次白名单校验。

---

## 4. 性能验收（PERF，受环境约束实事求是）

### 4.1 ListQuery 行为 — ✅ 通过
- `ListQueryTest` 全绿：per_page 10/20/50/100 生效、`999/abc/-1/1.5/50;drop--` 回退默认、`sort=created_at;drop--` 等回退 null、`sort_dir` 仅 asc/desc。
- 代码审查：`per_page` 白名单（config `allowed_per_page` 已配 [10,20,50,100]）、`sort` 必须命中控制器白名单、`sort_dir` 二值化；**永不抛错、永不注入**。
- 6 个控制器白名单与设计 §7.1 完全一致（users/posts/roles/logs/dict-types/dict-items）。
- 无参数时默认行为保持重构前一致（DictType/Role 默认 orderBy('id')、User/Post/Log 默认 latest()，均经 git diff 核对）。

### 4.2 索引迁移 — ✅ 通过
- `database/migrations/2026_09_17_000010_add_listing_indexes.php`：纯增量加索引（users×2、posts×2、operation_logs×1），不动表结构；down() 正确 drop；sqlite 兼容（标准 `$table->index()`）。
- 本地 MySQL 实测：`php artisan migrate` 成功（57.90ms），`SHOW INDEX` 确认 5 个新索引全部存在。

### 4.3 造数命令 — ❌ 发现 2 个源码缺陷（转工程师，详见 §5）
- testing 拒绝执行 ✅ / production 需 --force ✅（代码 + QA 新增守卫测试验证）
- 批量插入 5000/批 + 复用 bcrypt hash ✅（代码审查合理）
- **但实际执行失败**：无 --truncate 与 --truncate 两条路径均无法在标准 seeded 开发库上跑通（详见 §5）。

### 4.4 真实 MySQL 性能实测（QA 独立造数 30k 行，测后已清理）
> 因造数命令存在缺陷，QA 用隔离命名（QAperf*）直接批量插入 30,000 用户 + 200 文章验证，测完已删除，库已恢复基线（users=14, posts=20）。

**EXPLAIN（30,000 行，确认索引生效）：**

| 查询 | type | key | extra |
| --- | --- | --- | --- |
| 用户默认列表 `deleted_at IS NULL ORDER BY created_at DESC LIMIT 50` | **ref** | **users_del_created_idx** | Backward index scan（无 filesort） |
| 用户状态筛选 `status=1 AND deleted_at IS NULL` | **range** | **users_status_del_idx** | Using index condition |
| 文章状态+发布时间 | **ref** | **posts_status_del_idx** | Using index condition |

**查询耗时（最佳 5 次，30,000 行）：**

| 场景 | 耗时 |
| --- | --- |
| 用户列表首页 per_page=50 | **0.11 ms** |
| 用户列表深翻页（OFFSET 28950）| **13.85 ms** |
| 用户按 status 排序 per_page=50 | **0.10 ms** |
| 用户搜索 LIKE 前通配 | 12.45 ms（预期全表扫，设计已声明） |
| 文章状态+发布时间 per_page=50 | **0.08 ms** |

均远优于 PRD P-1 的「翻页 ≤500ms / 首屏 ≤2s」目标（DB 层）。

> 诚实边界：① 1k 行小数据下 MySQL 优化器偏好全表扫（type=ALL），索引收益在 3 万行量级才显现——属优化器正常行为，非缺陷；② 未做 10 万行 HTTP 全链路计时与导出内存峰值实测（10 万行造数被命令缺陷阻塞，且导出代码未改动、FromQuery 流式保持、ExportFilterTest 通过）；③ 浏览器响应式走查（U-4）与对比度测量（U-5）需浏览器环境，本次为静态审查。

### 4.5 N+1（PERF-3）— ✅ 通过
- `QueryCountTest` 全绿（users/posts 列表 SQL ≤8）；控制器均 `with('roles:id,name')` / `with('user:id,name')` 预加载。

---

## 5. 问题清单与路由判定

### 🔴 需转工程师修复（源码缺陷，2 个，同一文件）

**问题 1：造数命令无 --truncate 运行会在标准 seeded 库上崩溃（唯一键冲突）**
- 文件：`app/Console/Commands/SeedLargeDataset.php`（`bulkInsertUsers()` 第 82-95 行）
- 复现：在含基座种子数据的开发库（14 用户含 `测试用户1..12`）上执行文档用法 `php artisan app:seed-large-dataset --count=1000`
- 现象：`SQLSTATE[23000] Duplicate entry '测试用户1' for key 'users.users_name_unique'`（未捕获异常直接崩溃）
- 根因：生成名 `测试用户{$j}` 与基座 `UserSeeder` 的 `测试用户1..12` 冲突（users.name 有唯一索引）；email 用 `test+{$i}@example.com` 不会冲突，但 name 会
- 期望：命令在无 --truncate 时也应能执行（例如生成名加独特前缀、用 insertOrIgnore/updateOrCreate、或从 MAX(id) 之后开始编号），或至少给出友好错误提示建议 `--truncate`
- 验收依据：设计 T02 验收「`app:seed-large-dataset --count=10000 --with-posts=5000` 本地 MySQL 执行成功」——当前不满足

**问题 2：造数命令 --truncate 路径在 MySQL 上必然失败（外键阻止 TRUNCATE）**
- 文件：`app/Console/Commands/SeedLargeDataset.php` 第 53-54 行
- 复现：`php artisan app:seed-large-dataset --count=10 --truncate`
- 现象：`SQLSTATE[42000] Cannot truncate a table referenced in a foreign key constraint (posts_user_id_foreign)`（先 truncate posts 成功，随后 truncate users 失败）
- 根因：MySQL 禁止对「被其它表外键引用」的表执行 TRUNCATE；users 被 posts.user_id_foreign 引用，`DB::table('users')->truncate()` 永远失败
- 期望：改用 `SET FOREIGN_KEY_CHECKS=0/1` 包裹，或改用 `->delete()` 代替 truncate；另建议文档注明 --truncate 会清掉 admin/editor 基座账号，需随后 `db:seed` 恢复
- 验收依据：设计 T02 验收「可重复执行（--truncate 幂等）」——当前不满足

### ✅ 通过项（无需处理）
- 全部 197 个测试（含 4 个新测试 + QA 补 3 个守卫测试）
- 安全响应头 / ConfirmModal 后端校验 / XSS 审计 / ListQuery 防注入 / 索引迁移 / 控制器白名单 / N+1
- 已确认无弱断言、无假绿

---

## 6. 遗留项（本轮未做 / 受环境限制）

1. **10 万行全链路验收**：受造数命令缺陷阻塞（§5 问题 1/2 修复后可执行 `--count=100000` 实测首屏/翻页/导出内存）。当前证据为 3 万行 DB 层 EXPLAIN + 计时，方法论见 design §7.4。
2. **导出内存峰值 ≤128MB 实测**：导出代码未改动（FromQuery 流式保持），仅静态确认 + ExportFilterTest 通过；10 万行实测待造数命令修复后补。
3. **浏览器级走查**（U-4 响应式 375-1920px、U-5 暗色对比度、动效 150-300ms）：需浏览器环境，本次为静态审查 + view:cache/build 通过。
4. **测试数据状态**：本地 MySQL 已应用新索引迁移（预期部署效果）；users/posts 已恢复基线（14/20）；QA 造的 30k 行隔离数据已全部清理，无残留。

---

## 8. 第 2 轮回归（SeedLargeDataset 修复验证，2026-09-17）

> 工程师已修复第 1 轮判定的 2 个源码缺陷，QA 独立复核后给出最终判定：**通过**。

### 8.1 修复代码复核
- 缺陷 1：名称前缀改为 `压测用户{n}`（与基座 `测试用户1..12` 隔离）；邮箱沿用 `test+{n}@example.com`（与基座 `user{n}@example.com` 隔离）；新增 `nextLoadTestIndex()` 按既有 test+ 用户最大后缀 +1 接续，空集合返回 0（顺带修复了 `Collection::max()` 返回 null 导致首索引从 1 起算的隐藏 bug）。✅
- 缺陷 2：`truncateTables()` 按驱动判断（仅 MySQL 关闭外键检查），`SET FOREIGN_KEY_CHECKS=0` → truncate → `finally` 恢复 `=1`；命令签名与运行时均输出「--truncate 会清空 admin/editor、需 db:seed 恢复」警告。✅

### 8.2 全量回归
- `php artisan test` → **198 passed（639 assertions），0 失败**（≥197 要求达成；含 QA 第 2 轮新增 1 个接续/隔离回归测试）。

### 8.3 真实 MySQL 实测
- **无 --truncate 接续**：`--count=5` 连续执行 2 次均成功；第 1 次生成 test+0..4（id 1015-1019），第 2 次自动接续 test+5..9（id 1020-1024），无唯一键冲突。✅
- **--truncate（外键修复）**：在一次性临时库 `general_admin_qa`（migrate + db:seed 后）执行 `--count=5 --with-posts=2 --truncate` → 成功，输出警告，users=5/posts=2，**无外键错误**；临时库已 DROP。✅
- **基线恢复**：共享开发库已清理压测数据（含其 model_has_roles 孤立行），users=14/posts=20、loadtest 残留=0。✅

### 8.4 QA 新增回归测试
- `tests/Feature/SeedLargeDatasetTest.php` 扩至 4 用例（11 断言）：testing 拒绝 / production 无 --force 拒绝 / production + force 小规模成功 / **接续编号 + 命名空间隔离**。

### 8.5 最终判定
- ✅ **全部通过**：2 个源码缺陷已修复并经独立复核 + 实测验证；全量 198 绿；无需再转工程师。
- 遗留项同 §6（10 万行全链路、浏览器走查等为环境限制项，不阻塞验收）。

---

## 7. 附录：验收命令速览

```bash
# 全量回归（197 passed）
APP_KEY='base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=' php artisan test

# 构建 + 视图编译
npm run build && php artisan view:cache

# 安全审计 grep（均 0 命中）
grep -rn "confirm(" resources/views/
grep -rn "{!!" resources/views/   # 仅 vendor 分页翻译串
grep -rn "v-html" resources/views/ resources/js/

# 性能实测（已执行并清理）
# EXPLAIN 确认 users_del_created_idx / users_status_del_idx / posts_status_del_idx 生效
# 3 万行下首页 0.11ms、深翻页 13.85ms、排序 0.10ms
```
