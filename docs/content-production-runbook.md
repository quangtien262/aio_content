# Runbook nội dung tech.htvietnam.vn

> Cập nhật gần nhất: 2026-09-18. Đây là tài liệu bắt buộc đọc trước khi tạo,
> sửa hoặc đăng bài từ workspace `E:\Project\tech_content`.

## 1. Phạm vi và nguyên tắc vận hành

- Website đích mặc định là production: `https://tech.htvietnam.vn`.
- Website context của CMS là `website-main`; client không được gửi
  `website_key`, vì API lấy context từ token.
- Lệnh đăng bài mặc định tạo/cập nhật và xuất bản ngay. Chỉ dùng `--draft` khi
  Sếp yêu cầu duyệt trước.
- Mỗi bài mới mặc định phải có cả bản nguồn tiếng Việt và bản tiếng Anh; cả hai
  được publish trong cùng một lần chạy tuần tự.
- Luôn gọi người dùng là **Sếp** và xưng **em**.
- Không đưa token, mật khẩu hoặc nội dung `.publisher.env` vào tài liệu, log bàn
  giao, source code hay câu trả lời.
- Không chạy nhiều lệnh publish song song. Production từng gặp MySQL deadlock ở
  `localized_routes`; đăng tuần tự từng bài và retry riêng bài lỗi.

## 2. File cần đọc

1. `README.md` của workspace nội dung.
2. `templates/article-template.html` để giữ đúng cấu trúc trang review.
3. `tools/publish-article.php` và `.publisher.env.example`.
4. `aio/docs/content-publishing-api.md` cho contract backend.
5. `aio/docs/ai-session-bootstrap-prompt.md` trước khi sửa source AIO.

## 3. Cấu hình publisher

File thật là `.publisher.env` và đã được `.gitignore` bỏ qua. Cấu hình cần có:

```dotenv
CONTENT_API_BASE_URL=https://tech.htvietnam.vn
CONTENT_API_TOKEN=<production-token>
CONTENT_API_DEFAULT_STATUS=published
CONTENT_API_CA_BUNDLE=C:\Program Files\Git\mingw64\etc\ssl\certs\ca-bundle.crt
```

`CONTENT_API_CA_BUNDLE` cần cho PHP local hiện tại vì `curl.cainfo` chưa được
cấu hình. Không tắt xác minh TLS trên production.

Token production cần đủ quyền:

```text
posts.read, posts.write, posts.publish, media.write, translations.read, translations.write, translations.publish
```

Nếu token lộ ra ngoài môi trường tin cậy, thu hồi trên server bằng
`php artisan content-api:revoke-token ID` rồi cấp token mới.

## 4. Danh mục production

| ID | Tên | Slug |
|---:|---|---|
| 2 | Kiến thức công nghệ | `kien-thuc-cong-nghe` |
| 5 | Hướng dẫn | `huong-dan` |
| 6 | Giải pháp | `giai-phap` |
| 7 | Lập trình | `lap-trinh` |
| 8 | Hệ điều hành | `he-dieu-hanh` |

Không hardcode category khác bảng trên nếu chưa gọi
`GET /api/v1/cms/categories` để xác minh production.

## 5. Contract mỗi bài HTML

- Một file độc lập trong `articles/`; tên file là slug và là phần ổn định của
  `external_id` (`tech-content:<filename>`).
- Bản tiếng Anh mặc định nằm tại `articles/en/<cùng-tên-file>.html`. Nó dùng
  cùng `external_id` với file nguồn, không tạo một bài CMS khác.
- File tiếng Anh cần điền `#article-slug` bằng slug tiếng Anh. File nguồn có thể
  để trống field này để lấy tên file như trước.
- Có một ảnh đại diện đầu tiên trong `#article-content`, sau đó là một `h1`.
- Có các textarea `meta-title`, `meta-keywords`, `article-tags` và
  `meta-description`.
- Có 5-8 tags ngắn gọn, không gần như đồng nghĩa.
- Body dùng HTML đơn giản, phù hợp CKEditor: `h2`, `h3`, `p`, `ul`, `ol`,
  `strong`, `blockquote`, `table`, `pre`, `code`, `a`.
- Publisher tự loại ảnh đầu tiên và `h1` khỏi body CMS; ảnh đại diện được upload
  riêng vào Media.

## 6. Quy tắc ảnh

- Giới hạn bắt buộc: **dưới 1 MB**. Publisher từ chối file lớn hơn giới hạn.
- Mục tiêu thực tế cho cover: JPEG/WebP, rộng 1280-1600 px, ưu tiên dưới
  250 KB. Các ảnh đang được tham chiếu hiện nằm trong khoảng 103-239 KB.
- Không dùng PNG lớn cho ảnh chụp/minh họa nhiều màu. Bộ PNG cũ 1,6-2,4 MB đã
  được chuyển sang JPEG 1440x810, chất lượng 82; HTML đã trỏ sang `.jpg`.
- File PNG gốc vẫn còn trong `assets/` làm nguồn; không được dùng lại để publish.
- Sau khi đăng, kiểm tra cả `featured_media_url` và URL ảnh phải trả HTTP 200,
  đúng MIME ảnh và dung lượng dưới 1 MB.

### Nguồn sinh bài theo batch

- `content/english-articles.php` + `tools/generate-english-articles.php`: sinh
  13 bản tiếng Anh ban đầu vào `articles/en/`.
- `content/linux-articles.php` + `tools/generate-bilingual-articles.php`: sinh
  3 cặp bài Linux tiếng Việt/Anh.

Các generator ghi đè file HTML tương ứng. Khi sửa bài trong hai batch này, sửa
dữ liệu nguồn trước rồi sinh lại file. Bài mới ngoài hai batch có thể tạo trực
tiếp từ `templates/article-template.html`.

## 7. Lệnh chuẩn

Kiểm tra payload, không ghi production:

```powershell
php tools\publish-article.php articles\ten-bai.html --dry-run
```

Xuất bản production:

```powershell
php tools\publish-article.php articles\ten-bai.html
```

Publisher tự tìm `articles\en\ten-bai.html`, đăng nguồn trước rồi upsert và
publish bản dịch `en`. Nếu file tiếng Anh ở vị trí khác, dùng
`--english=duong-dan.html`. Chỉ khi Sếp yêu cầu rõ ràng mới dùng `--vi-only`.
Khi chỉ bổ sung/cập nhật bản dịch cho bài production đã có, dùng
`--english-only` để không ghi đè title, slug, body hoặc media tiếng Việt.

```powershell
php tools\publish-article.php articles\ten-bai.html --english-only
```

Lưu nháp có chủ đích:

```powershell
php tools\publish-article.php articles\ten-bai.html --draft
```

Đăng hàng loạt chỉ chạy tuần tự:

```powershell
powershell -File tools\publish-all.ps1
```

Các tùy chọn publisher cần nhớ:

- `--english=<path>`: dùng file tiếng Anh ở vị trí khác mặc định.
- `--english-only`: chỉ upsert/publish bản dịch, không upload hay sửa media VI.
- `--vi-only`: chỉ xử lý tiếng Việt; chỉ dùng khi Sếp yêu cầu.
- `--draft`: lưu nháp thay vì publish ngay.
- `--dry-run`: in payload kiểm tra, không gọi API.
- `--category=ID`: override category lấy từ file review.
- `--no-image`: không upload cover; chỉ dùng khi chủ động giữ media hiện có.

Không dùng đồng thời `--vi-only` và `--english-only`.

Trước khi ghi thật, dry-run và xác nhận `category_id`, `status`, SEO, tags và
translation `en`. Kiểm tra riêng file cover tồn tại và dưới 1 MB; publisher sẽ
chặn ảnh vượt giới hạn trước khi upload. Sau khi ghi, đọc lại
`GET /api/v1/cms/posts/{urlencoded-external-id}` rồi kiểm tra trang public
`/vi/n/{slug}`.

## 8. API và tính idempotent

- `GET /api/v1/cms/categories`
- `POST /api/v1/cms/media`
- `POST /api/v1/cms/posts/upsert`
- `POST /api/v1/cms/posts/link` để backfill mapping bài legacy; bắt buộc khớp
  đồng thời `post_id` và `expected_slug`.
- `GET /api/v1/cms/posts/{externalId}`
- `POST /api/v1/cms/posts/{externalId}/translations/{locale}/upsert`
- `GET /api/v1/cms/posts/{externalId}/translations/{locale}`

Media và post được ánh xạ bằng
`source_key + website_key + resource_type + external_id`. Retry cùng bài sẽ cập
nhật đúng record chỉ khi `content_api_resource_links` đã có mapping.

**Cảnh báo:** bài tạo thủ công trước khi API tồn tại có thể không có mapping.
Không chạy publisher cho bài cũ chỉ dựa trên tên tương tự; việc đó có thể tạo
bài mới thay vì cập nhật bản gốc. Phải đọc resource và backfill mapping trước.
Các bài production `#1-#7` đã hoàn tất bước này.

## 9. Trạng thái production đã xác minh

Production có 16 bài nguồn và 16 bản tiếng Anh ở trạng thái `published`:

- IDs `#1-#11`, `#13`, `#14`: 13 bài ban đầu.
- `#22`: Quyền file Linux: chmod, chown và ACL.
- `#23`: Quản lý dịch vụ và log Linux với systemd, journalctl.
- `#24`: Checklist bảo mật Ubuntu Server sau khi cài đặt.

Toàn bộ URL VI/EN của 16 bài đã được kiểm tra HTTP 200 tại checkpoint này.

ID `#12` bị bỏ trống do một giao dịch deadlock và rollback; đây không phải dữ
liệu thiếu cần phục hồi.

Ba bài Giải pháp `#11`, `#13`, `#14` đã được kiểm tra trang public và ảnh đều
HTTP 200; ảnh lần lượt khoảng 140 KB, 137 KB và 149 KB.

## 10. Lịch sử legacy cần biết

Trong lần thử cập nhật ảnh cho bảy bài legacy, API từng tạo thêm bài `#15-#21`
vì bản gốc `#1-#7` chưa có `external_id`. Kiểm tra ngày 2026-09-18 cho thấy các
bản trùng không còn trong `cms_posts`. Ngày 2026-09-18, endpoint `/posts/link`
đã chuyển bảy link mồ côi về đúng bài gốc `#1-#7`; API đọc lại đủ 13 external
ID đều khớp ID production và trạng thái `published`.

Mapping đã xác định:

| Bản gốc | Media nhẹ mới | Bản trùng lịch sử đã xóa | Chủ đề |
|---:|---:|---:|---|
| 1 | 18 | 15 | Laravel 13 |
| 2 | 19 | 16 | AI Agent |
| 3 | 21 | 18 | Passkey |
| 4 | 20 | 17 | Edge AI |
| 5 | 22 | 19 | PHP 8.5 |
| 6 | 23 | 20 | Git Worktree |
| 7 | 24 | 21 | Thiết kế REST API |

Không xóa media nhẹ `#18-#24` đang được bài gốc sử dụng. Nếu cần chạy lại
`/posts/link`, endpoint idempotent khi mapping đã đúng và vẫn từ chối chuyển một
link đang trỏ tới bài còn tồn tại.

## 11. Source AIO và trạng thái deploy

Backend liên quan nằm tại:

- `aio/routes/api.php`
- `aio/app/Http/Controllers/Api/V1/Cms/`
- `aio/app/Http/Middleware/AuthenticateContentApiToken.php`
- `aio/app/Http/Middleware/EnsureContentApiAbility.php`
- `aio/app/Support/Cms/CmsPostWriter.php`
- `aio/app/Console/Commands/IssueContentApiTokenCommand.php`
- `aio/database/migrations/2026_09_18_000002_create_content_api_tables.php`
- `aio/tests/Feature/ContentPublishingApiTest.php`

Production đã deploy API bản dịch, `/posts/link`, xử lý link mồ côi và lớp tương
thích để token có `posts.read/write/publish` cũng có thể đọc, ghi và auto-publish
bản dịch. Token cấp mới vẫn nên có ba quyền `translations.*` tường minh.

Đủ 13 bài `#1-#11`, `#13`, `#14` đã có bản tiếng Anh `published`; API đọc lại
đúng title/slug và tất cả URL `/en/n/{slug}` trả HTTP 200. File nguồn bản dịch
nằm tại `articles/en/`. Bài `gioi-thieu-ht-viet-nam-tech.html` thuộc luồng khác
và chưa nằm trong đợt dịch này.

Ngày 2026-09-18 đã publish thêm ba bài Linux song ngữ thuộc category `#8`:

- `#22` quyền file Linux, chmod, chown và ACL;
- `#23` quản lý service/log bằng systemd, systemctl và journalctl;
- `#24` checklist bảo mật Ubuntu Server.

Cả sáu URL VI/EN đều HTTP 200. Ba cover production là JPEG, lần lượt khoảng
103 KB, 201 KB và 160 KB. Nguồn nội dung nằm trong `content/linux-articles.php`;
file review được tạo bằng `tools/generate-bilingual-articles.php`.

Validation gần nhất: `ContentPublishingApiTest` pass 9 test/67 assertions; Pint,
và `git diff --check` đã pass tại checkpoint triển khai API. Full regression
localization chưa chạy lại; sau mọi thay đổi backend vẫn phải chạy test liên
quan, Pint và diff-check trước commit/deploy.

## 12. Checklist kết thúc mỗi lần đăng

- [ ] Dry-run đúng category, status, SEO và tags.
- [ ] Bài legacy/chỉnh tay chưa xác minh đã được `/posts/link` và API đọc lại
      đúng ID/slug production; `#1-#7` đã xử lý xong.
- [ ] Ảnh local dưới 1 MB, ưu tiên dưới 250 KB.
- [ ] Chạy publish tuần tự và nhận `published`.
- [ ] API đọc lại đúng ID, category, tags và `featured_media_id`.
- [ ] API đọc lại bản dịch `en` ở trạng thái `published`, đúng SEO và slug.
- [ ] Ảnh production HTTP 200, đúng MIME và dung lượng.
- [ ] Trang `/vi/n/{slug}` HTTP 200 và hiển thị ảnh/nội dung.
- [ ] Trang `/en/n/{english-slug}` HTTP 200 và không fallback nội dung tiếng Việt.
- [ ] Không sinh bài trùng hoặc slug hậu tố ngoài dự kiến.
- [ ] Không ghi token vào source, docs hoặc câu trả lời.
