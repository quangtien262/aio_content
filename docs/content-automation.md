# Tự động tạo và đăng nội dung

## Phạm vi

Automation dùng Windows Task Scheduler để gọi `codex exec` trong workspace.
Extension Codex của VS Code không có giao diện quản lý Scheduled Tasks; CLI được
dùng làm worker không tương tác.

Mỗi phiên Codex chỉ tạo và publish một bài. PowerShell worker chạy tuần tự, khóa
chống chồng phiên, kiểm tra URL production và chỉ ghi ledger khi trang VI, trang
EN và ảnh đều đạt postcondition.

## Cấu hình

File `automation/content-automation.json` quy định:

- bật/tắt worker;
- mục tiêu số bài mỗi ngày;
- batch thường và batch catch-up tối đa;
- khung giờ, tần suất;
- category được phép;
- độ dài nội dung tối thiểu.

Sau khi đổi lịch, chạy lại:

```powershell
powershell -File tools\install-content-automation.ps1
```

Tắt logic nhưng giữ task:

```json
"enabled": false
```

Gỡ task:

```powershell
powershell -File tools\uninstall-content-automation.ps1
```

## Chạy thử có kiểm soát

Tạo tối đa một bài ngay lập tức:

```powershell
powershell -File tools\run-content-automation.ps1 -Force -OneArticle
```

Đây là lệnh ghi production nếu agent hoàn tất toàn bộ quality gate. Không dùng
để thử trên máy chưa có token production hoặc chưa đăng nhập Codex CLI.

## Trạng thái và log

- `automation/state/YYYY-MM-DD.json`: các bài đã được worker xác minh trong ngày.
- `automation/logs/YYYY-MM-DD.log`: kết quả rút gọn, không lưu token.
- `automation/tmp/`: kết quả JSON tạm từ Codex.

Ba thư mục runtime này được `.gitignore` bỏ qua.

## Giới hạn vận hành

- Máy phải bật, người dùng phải đăng nhập Windows và Codex CLI còn phiên đăng
  nhập hợp lệ.
- Mục tiêu 20 bài/ngày là best-effort; mất điện, mất mạng, hết quota, lỗi nguồn
  hoặc lỗi production có thể làm thiếu chỉ tiêu.
- Task chạy trong working tree chính. Không sửa cùng file nội dung trong khung
  giờ automation nếu chưa tắt worker.
- Kiểm tra log và chất lượng các bài đầu tiên trước khi để automation chạy dài.
