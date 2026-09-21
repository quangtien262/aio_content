# tech.htvietnam.vn Content Workspace

Tai lieu van hanh bat buoc cho AI/session moi:
`docs/content-production-runbook.md`.

Thu muc nay dung de tao va review noi dung HTML cho website tech.htvietnam.vn.

## Quy uoc moi bai viet

- Moi bai viet nen la mot file `.html` doc lap.
- File review can co:
  - Anh dai dien.
  - Khoi SEO gom Meta title, keyword, description va tags bai viet.
  - Noi dung bai viet toi uu de copy sang CKEditor.
  - Nut copy nhanh cho SEO va HTML noi dung.

## Cau truc de xuat

- `templates/`: template nen cho bai viet.
- `articles/`: cac bai viet da tao.
- `articles/en/`: ban tieng Anh cung ten file voi bai nguon.
- `assets/`: anh dai dien hoac hinh minh hoa neu can luu local.
- `content/english-articles.php`: du lieu nguon cho 13 ban dich cu.
- `content/linux-articles.php`: du lieu nguon cho 3 bai Linux song ngu.
- `tools/generate-english-articles.php`: sinh lai 13 file tieng Anh.
- `tools/generate-bilingual-articles.php`: sinh lai 3 cap file Linux VI/EN.

Generator se ghi de cac file HTML tuong ung. Voi hai batch tren, can sua file
du lieu trong `content/` truoc roi moi sinh lai HTML.

## Luu y noi dung

- Giong van: ro rang, huu ich, than thien voi nguoi hoc/cong dong cong nghe.
- Co the long ghep nang luc cua CONG TY CP CONG NGHE VA TRUYEN THONG HT VIET NAM khi phu hop, nhung tranh bien bai chia se ky thuat thanh bai quang cao.
- Noi dung trong khoi CKEditor nen uu tien HTML don gian: `h2`, `h3`, `p`, `ul`, `ol`, `strong`, `blockquote`, `table`, `img`.
- Moi bai viet can co tu 5 den 8 tags ngan gon, uu tien chu de chinh, cong nghe va doi tuong lien quan; khong tao tags gan nhu trung nghia.
- Anh dai dien chi dung de review va tai len rieng; nut copy HTML phai tu dong loai anh dai dien dau bai de tranh dua duong dan local vao CKEditor.
- Anh dai dien nen dung WebP hoac JPEG toi uu, rong khoang 1280-1600 px va muc tieu duoi 250 KB khi chat luong cho phep; tranh dung PNG dung luong lon cho anh cover.

## Dang bai qua API

1. Sao chep `.publisher.env.example` thanh `.publisher.env`, sau do dien URL va token do may chu AIO cap.
2. Kiem tra payload ma khong gui len API:
   `php tools/publish-article.php articles/ten-bai.html --dry-run`
3. Tao hoac cap nhat va xuat ban bai viet:
   `php tools/publish-article.php articles/ten-bai.html`
4. Khi can luu ban nhap, them tuy chon `--draft`.

Mac dinh can co `articles/en/ten-bai.html`; dien slug tieng Anh vao
`article-slug`. Cong cu dang bai nguon truoc, sau do tu dong publish ban tieng
Anh. Chi dung `--vi-only` khi chu dong dang rieng tieng Viet. Khi chi bo sung
ban dich cho bai da co mapping, dung `--english-only`; che do nay khong upload
hoac thay doi anh va noi dung tieng Viet.

Cong cu tu dong bo anh dai dien, loai anh cover va H1 khoi body, giu SEO/tags, va dung ten file nguon lam `external_id` de khong tao bai trung khi chay lai.

## Tu dong hoa theo lich

Windows Task Scheduler co the goi Codex CLI de tao va publish noi dung theo lich.
Extension VS Code khong tu quan ly scheduled task. Cau hinh, worker, quality gate
va cach bat/tat nam tai `docs/content-automation.md`.

Luu y: idempotency chi dung khi bai da co mapping `external_id`. Bai legacy tao
truoc API phai backfill mapping truoc khi chay publisher; xem runbook.

Checkpoint production 2026-09-19: 19 bai song ngu do workspace quan ly, gom ID
`1-11`, `13`, `14`, `22-24`, `80-82`. File
`gioi-thieu-ht-viet-nam-tech.html` thuoc luong dang khac va khong nam trong batch
nay.
