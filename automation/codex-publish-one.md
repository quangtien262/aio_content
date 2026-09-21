Bạn đang chạy một job tự động cho workspace nội dung tech.htvietnam.vn.

Mục tiêu: tạo và publish chính xác MỘT bài viết song ngữ mới lên production,
sau đó trả về JSON đúng schema đã được cung cấp.

Thông tin job:

- Ngày vận hành: {{DATE}}
- Job ID: {{JOB_ID}}
- Các category được phép: {{ALLOWED_CATEGORIES}}
- Các external ID đã hoàn tất hôm nay: {{TODAY_EXTERNAL_IDS}}

Quy tắc bắt buộc:

1. Đọc đầy đủ `AGENTS.md`, `docs/content-production-runbook.md`, `README.md`,
   `templates/article-template.html` và `aio/docs/content-publishing-api.md`
   trước khi tạo hoặc publish.
2. Không đọc, in, sao chép hoặc sửa token. Chỉ gọi publisher hiện có; tuyệt đối
   không hiển thị `.publisher.env` hay bất kỳ giá trị bí mật nào.
3. Chọn một chủ đề hữu ích, cụ thể, không trùng hoặc gần trùng tiêu đề/ý định
   tìm kiếm với bài hiện có trong `articles/`. Chỉ dùng category được phép.
4. Nếu chủ đề phụ thuộc thông tin hiện hành, phải kiểm chứng bằng nguồn chính
   thức hoặc nguồn gốc; đưa liên kết tham khảo vào cuối bài. Không bịa phiên bản,
   benchmark, tính năng hoặc câu trích dẫn.
5. Tạo một file nguồn mới `content/<filename>.php` chứa cả `vi` và `en`, theo
   schema các file nguồn gần nhất. Không sửa bài cũ, generator, publisher,
   runbook, README hoặc file cấu hình.
6. Bản Việt và Anh phải đầy đủ, không phải bản tóm tắt; mỗi body tối thiểu
   {{MIN_VI_BODY}} và {{MIN_EN_BODY}} ký tự, có 5-8 tags, SEO đầy đủ, HTML đơn
   giản tương thích CKEditor và slug tiếng Anh riêng.
7. Tạo cover bằng lệnh:
   `php tools/generate-automation-cover.php <filename> <category-slug>`.
   Cover phải là JPEG 1440x810, dưới 250 KB và không có chữ/logo/watermark.
8. Sinh HTML bằng:
   `php tools/generate-programming-articles.php content/<filename>.php`.
9. Chạy PHP lint, kiểm tra cover, `git diff --check` và publisher `--dry-run`.
   Phải xác nhận category, status `published`, 5-8 tags và có translation `en`.
10. Publish duy nhất bài vừa tạo bằng `tools/publish-article.php`. Không dùng
    `publish-all.ps1`, không publish lại bài khác và không chạy song song.
11. Đọc lại API nguồn và bản dịch; kiểm tra URL VI, URL EN và URL ảnh đều HTTP
    200, ảnh đúng MIME và dưới 1 MB. Nếu bất kỳ postcondition nào sai, đặt
    `success=false` và mô tả ngắn trong `error`.
12. Không commit, push, xóa hay sửa dữ liệu production ngoài bài vừa tạo.

Khi thành công, JSON cuối phải có đầy đủ external ID, post ID, category ID, ba
URL đã verify và tiêu đề VI/EN. Khi thất bại, không tuyên bố thành công; trả
`success=false`, giữ các field chưa có là chuỗi rỗng hoặc 0.
