<?php

return [
    'wordpress-hosting-migration-guide.html' => [
        'vi' => [
            'title' => 'Hướng dẫn chuyển WordPress sang hosting mới không downtime',
            'slug' => 'chuyen-wordpress-sang-hosting-moi-khong-downtime',
            'image' => 'wordpress-hosting-migration-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Chuyển WordPress sang hosting mới không downtime',
            'meta_keywords' => 'chuyển hosting WordPress, migrate WordPress, không downtime, WP-CLI, chuyển website, DNS, backup WordPress',
            'meta_description' => 'Quy trình chuyển WordPress sang hosting mới an toàn: backup, giảm TTL, đồng bộ file và database, kiểm thử bằng hosts, chuyển DNS, SSL và rollback.',
            'tags' => ['WordPress', 'Hosting', 'Website Migration', 'WP-CLI', 'DNS', 'Backup', 'Guides'],
            'body' => <<<'HTML'
<p><strong>Chuyển hosting WordPress không khó ở thao tác copy file, mà khó ở việc không mất đơn hàng, bình luận hoặc dữ liệu phát sinh trong lúc chuyển.</strong> Một quy trình tốt phải chuẩn bị đường lui, kiểm thử máy chủ mới trước khi đổi DNS và kiểm soát khoảng thời gian hai máy chủ cùng tồn tại.</p>
<p>Hướng dẫn này áp dụng cho website WordPress đơn lẻ giữ nguyên tên miền. Multisite, WooCommerce lưu lượng lớn hoặc hệ thống có nhiều dịch vụ ghi dữ liệu cần kế hoạch riêng và nên diễn tập trên staging.</p>

<h2>Mục tiêu và nguyên tắc</h2>
<ul>
<li>Website công khai vẫn truy cập được trong phần lớn quá trình.</li>
<li>Bản sao trên máy chủ mới được kiểm thử trước khi nhận traffic thật.</li>
<li>Khoảng đóng băng ghi dữ liệu cuối cùng ngắn và có thông báo rõ ràng.</li>
<li>Máy chủ cũ được giữ nguyên đủ lâu để rollback.</li>
<li>Mọi secret, backup và tài khoản tạm được dọn sau khi hoàn tất.</li>
</ul>
<blockquote>DNS không chuyển người dùng đồng loạt. Trong thời gian cache, một số truy cập có thể đến máy chủ cũ và số khác đến máy chủ mới.</blockquote>

<h2>1. Kiểm kê trước khi di chuyển</h2>
<p>Ghi lại PHP, MySQL/MariaDB, WordPress, web server, extension PHP, cron, dung lượng file/database và cấu hình email. Kiểm tra plugin cache, security, backup, object cache, CDN và các tích hợp thanh toán hoặc webhook.</p>
<pre><code>wp core version
wp plugin list
wp theme list
wp db size
wp cron event list</code></pre>
<p>Xác nhận hosting mới hỗ trợ phiên bản PHP và extension cần dùng. Kiểm tra giới hạn upload, memory, execution time, cron, SSL, HTTP/2 hoặc HTTP/3, quyền file và khả năng gửi mail. Không nâng cấp WordPress, PHP và plugin cùng lúc với chuyển hosting; quá nhiều thay đổi làm khó xác định nguyên nhân khi có lỗi.</p>

<h2>2. Giảm TTL DNS trước ngày chuyển</h2>
<p>Trước 24–48 giờ, giảm TTL của record web xuống khoảng 300 giây nếu DNS provider cho phép. Việc này không làm cache cũ biến mất ngay; phải chờ TTL trước đó hết hạn.</p>
<p>Kiểm kê toàn bộ record trước khi thay nameserver hoặc zone: <code>A</code>, <code>AAAA</code>, <code>CNAME</code>, <code>MX</code>, SPF, DKIM, DMARC và record xác minh. Nếu chỉ chuyển website, không thay đổi record email. Một record <code>AAAA</code> cũ có thể khiến người dùng IPv6 tiếp tục vào máy chủ cũ dù record <code>A</code> đã đổi.</p>

<h2>3. Tạo và kiểm tra backup</h2>
<p>Cần tối thiểu hai phần: toàn bộ file WordPress và database. Không quên <code>wp-content/uploads</code>, plugin, theme, mu-plugin, <code>.htaccess</code>, cấu hình Nginx/Apache và cron bên ngoài WordPress.</p>
<pre><code>wp db export ~/backup/site-pre-migration.sql
tar -czf ~/backup/site-files.tar.gz \
  --exclude='wp-content/cache' \
  /var/www/example.com</code></pre>
<p>Lưu backup ngoài máy chủ nguồn, mã hóa nếu chứa dữ liệu nhạy cảm và kiểm tra file có đọc được. Tốt nhất hãy thử restore vào môi trường tạm. Một file backup chưa từng được khôi phục chỉ là giả định.</p>

<h2>4. Chuẩn bị hosting mới</h2>
<p>Tạo virtual host, database và database user với quyền tối thiểu. Cài đúng runtime, bật HTTPS và cấu hình redirect nhưng chưa đổi DNS. Nếu dùng Let's Encrypt mà domain chưa trỏ sang máy mới, dùng DNS challenge hoặc chứng chỉ tạm theo khả năng nhà cung cấp.</p>
<p>Copy file bằng công cụ giữ timestamp và permission. Với SSH, <code>rsync</code> thuận tiện cho nhiều lần đồng bộ:</p>
<pre><code>rsync -aH --delete \
  --exclude='wp-content/cache/' \
  user@old-server:/var/www/example.com/ \
  /var/www/example.com/</code></pre>
<p>Thận trọng với <code>--delete</code>: chạy dry-run và kiểm tra đúng source/destination trước. Không copy cache, session hoặc backup cũ nếu không cần.</p>

<h2>5. Import database và cập nhật cấu hình</h2>
<pre><code>wp db import ~/backup/site-pre-migration.sql
wp db check</code></pre>
<p>Cập nhật <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code> và <code>DB_HOST</code> trong <code>wp-config.php</code>. Không đưa file cấu hình hoặc SQL dump vào thư mục public có thể tải qua web.</p>
<p>Nếu giữ nguyên domain và đường dẫn, thường không cần search-replace URL. Nếu domain hoặc giao thức thay đổi, không dùng lệnh SQL <code>REPLACE()</code> mù quáng vì WordPress lưu nhiều giá trị serialized. WP-CLI xử lý serialized data:</p>
<pre><code>wp search-replace 'http://old.example' 'https://new.example' \
  --skip-columns=guid --dry-run

wp search-replace 'http://old.example' 'https://new.example' \
  --skip-columns=guid</code></pre>
<p>Luôn chạy <code>--dry-run</code> trước. Với Multisite, cần tùy chọn và quy trình riêng; không áp dụng nguyên xi ví dụ của single site.</p>

<h2>6. Kiểm thử trước khi đổi DNS</h2>
<p>Cách chính xác nhất là sửa file <code>hosts</code> trên máy kiểm thử để domain thật trỏ tạm đến IP hosting mới. Trình duyệt gửi đúng hostname, WordPress dùng đúng URL và HTTPS/SNI được kiểm tra gần giống production.</p>
<pre><code>203.0.113.20 example.com www.example.com</code></pre>
<p>Sau khi lưu, xóa DNS cache nếu cần và mở cửa sổ riêng tư. Chỉ máy đã sửa hosts nhìn thấy hosting mới; người dùng vẫn ở máy cũ.</p>
<p>Kiểm tra tối thiểu:</p>
<ul>
<li>Trang chủ, bài viết, category, tìm kiếm và URL 404.</li>
<li>Đăng nhập/đăng xuất quản trị, upload ảnh và chỉnh sửa bài.</li>
<li>Form liên hệ, email giao dịch, CAPTCHA và webhook.</li>
<li>Giỏ hàng, checkout, callback thanh toán trên môi trường test phù hợp.</li>
<li>Permalink, redirect, robots.txt, sitemap và canonical URL.</li>
<li>Ảnh, CSS, JavaScript, font và mixed content.</li>
<li>WP-Cron, cron hệ thống, queue và tác vụ backup.</li>
</ul>
<pre><code>wp core verify-checksums
wp plugin status
wp rewrite flush
wp cache flush</code></pre>
<p><code>wp rewrite flush</code> chỉ chạy khi cần và sau khi web server đã có rule permalink đúng. Xem PHP error log, web server log và console trình duyệt thay vì chỉ nhìn trang chủ.</p>

<h2>7. Đồng bộ lần cuối mà không mất dữ liệu</h2>
<p>Website nội dung ít thay đổi có thể bật maintenance/read-only ngắn trên máy cũ, dừng cron/queue, export database lần cuối và rsync phần upload phát sinh. Với WooCommerce hoặc membership, đây là bước quan trọng nhất.</p>
<ol>
<li>Thông báo cửa sổ bảo trì ngắn và tạm chặn thao tác ghi.</li>
<li>Dừng worker, WP-Cron hoặc webhook consumer trên máy cũ.</li>
<li>Export database cuối cùng.</li>
<li>Đồng bộ lại <code>wp-content/uploads</code> và file phát sinh.</li>
<li>Import database cuối vào máy mới.</li>
<li>Xóa cache, chạy smoke test và mở ghi trên máy mới.</li>
</ol>
<p>Không để cron và queue chạy trên cả hai máy cùng lúc vì email, subscription hoặc job có thể bị xử lý hai lần. Với website không chấp nhận maintenance window, cần replication hoặc cơ chế đồng bộ cấp ứng dụng phức tạp hơn.</p>

<h2>8. Chuyển DNS</h2>
<p>Đổi record <code>A</code>/<code>AAAA</code> sang IP mới, hoặc cập nhật load balancer theo kiến trúc. Không xóa cấu hình cũ. Theo dõi access log ở cả hai máy để thấy traffic dịch chuyển.</p>
<pre><code>dig +short example.com A
dig +short example.com AAAA
curl -I https://example.com</code></pre>
<p>Kiểm tra từ nhiều mạng hoặc resolver, không chỉ máy đã chỉnh hosts. Nếu dùng CDN/proxy, purge cache có kiểm soát và xác nhận origin mới. Giữ maintenance/read-only trên máy cũ trong giai đoạn DNS chuyển tiếp để không phát sinh dữ liệu phân mảnh.</p>

<h2>9. Theo dõi sau chuyển đổi</h2>
<p>Trong 24–72 giờ đầu, theo dõi:</p>
<ul>
<li>HTTP 4xx/5xx, PHP fatal error và slow query.</li>
<li>CPU, RAM, disk, inode, I/O và connection database.</li>
<li>Đăng nhập, form, đơn hàng, thanh toán và email.</li>
<li>WP-Cron, queue, backup và webhook.</li>
<li>SSL chain, redirect HTTP→HTTPS và mixed content.</li>
<li>Traffic còn đến IP cũ và bot dùng DNS cache dài.</li>
</ul>
<p>Khi ổn định, tăng TTL về mức vận hành thông thường. Tạo backup mới từ hosting mới và thử một thao tác restore nhỏ.</p>

<h2>10. Kế hoạch rollback</h2>
<p>Nếu lỗi nghiêm trọng trước khi có dữ liệu mới trên hosting mới, đổi DNS về IP cũ và mở lại dịch vụ cũ. Nếu máy mới đã nhận đơn hàng hoặc nội dung, rollback không còn là đổi DNS đơn giản: phải quyết định cách hợp nhất dữ liệu phát sinh.</p>
<p>Đặt ngưỡng rollback trước khi chuyển, ví dụ tỷ lệ lỗi, checkout thất bại hoặc latency. Ghi rõ người ra quyết định, thời gian chờ DNS và cách xử lý dữ liệu mới. Giữ máy cũ, database và backup nguyên trạng ít nhất vài ngày hoặc theo yêu cầu tuân thủ.</p>

<h2>Dọn dẹp an toàn</h2>
<p>Sau thời gian quan sát và khi không còn traffic hợp lệ tới máy cũ:</p>
<ul>
<li>Thu hồi SSH key, tài khoản và token di chuyển tạm.</li>
<li>Xóa SQL dump khỏi web root và thư mục tạm.</li>
<li>Tắt cron/worker cũ, sau đó mới hủy hosting cũ.</li>
<li>Cập nhật tài liệu hạ tầng, monitoring, backup và danh sách IP.</li>
<li>Kiểm tra lại quyền file: thư mục thường 755, file 644, tránh 777.</li>
<li>Đổi secret nếu chúng từng được chia sẻ qua kênh không an toàn.</li>
</ul>

<h2>Checklist nhanh</h2>
<ol>
<li>Kiểm kê runtime, plugin, cron và tích hợp.</li>
<li>Giảm TTL trước 24–48 giờ.</li>
<li>Backup file và database, kiểm tra khả năng restore.</li>
<li>Dựng hosting mới, copy và import bản đầu.</li>
<li>Kiểm thử bằng hosts với domain thật.</li>
<li>Đóng băng ghi ngắn, đồng bộ database/file lần cuối.</li>
<li>Chuyển DNS và theo dõi cả hai máy.</li>
<li>Giữ máy cũ cho rollback, sau đó dọn secret và backup tạm.</li>
</ol>

<h2>Kết luận</h2>
<p>Một lần chuyển WordPress an toàn cần nhiều vòng đồng bộ và kiểm chứng, không phải một cú copy rồi đổi DNS. Chuẩn bị backup có thể restore, thử hosting mới bằng hosts, kiểm soát thao tác ghi trong lần đồng bộ cuối và giữ đường rollback sẽ giảm đáng kể rủi ro mất dữ liệu hoặc gián đoạn dịch vụ.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://developer.wordpress.org/advanced-administration/upgrade/migrating/" target="_blank" rel="noopener noreferrer">WordPress: Migrating WordPress</a></li><li><a href="https://developer.wordpress.org/cli/commands/db/" target="_blank" rel="noopener noreferrer">WP-CLI database commands</a></li><li><a href="https://developer.wordpress.org/cli/commands/search-replace/" target="_blank" rel="noopener noreferrer">WP-CLI search-replace</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Migrate WordPress to New Hosting Without Downtime',
            'slug' => 'migrate-wordpress-new-hosting-without-downtime',
            'image' => 'wordpress-hosting-migration-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Migrate WordPress Hosting Without Downtime',
            'meta_keywords' => 'WordPress hosting migration, migrate WordPress, zero downtime, WP-CLI, website migration, DNS, WordPress backup',
            'meta_description' => 'A safe WordPress hosting migration workflow covering backups, lower DNS TTL, file and database sync, hosts-file testing, cutover, SSL, monitoring, and rollback.',
            'tags' => ['WordPress', 'Hosting', 'Website Migration', 'WP-CLI', 'DNS', 'Backup', 'Guides'],
            'body' => <<<'HTML'
<p><strong>The difficult part of moving WordPress is not copying files; it is avoiding lost orders, comments, or content created during the move.</strong> A reliable migration needs a rollback path, tests on the new server before DNS changes, and control over the period when both servers exist.</p>
<p>This guide covers a single WordPress site that keeps the same domain. Multisite, high-traffic WooCommerce, or systems with several writers need a tailored plan and a staging rehearsal.</p>

<h2>Goals and principles</h2>
<ul>
<li>The public site remains reachable through most of the process.</li>
<li>The new server is tested before receiving real traffic.</li>
<li>The final write freeze is short and clearly communicated.</li>
<li>The old server remains intact long enough for rollback.</li>
<li>Temporary secrets, backups, and accounts are removed afterward.</li>
</ul>
<blockquote>DNS does not move every visitor at once. During cache expiry, some requests may reach the old server while others reach the new one.</blockquote>

<h2>1. Inventory the current site</h2>
<p>Record PHP, MySQL/MariaDB, WordPress, web server, PHP extensions, cron jobs, file/database size, and email configuration. Identify cache, security, backup, object-cache, CDN, payment, and webhook integrations.</p>
<pre><code>wp core version
wp plugin list
wp theme list
wp db size
wp cron event list</code></pre>
<p>Confirm that the destination supports the required PHP version and extensions. Check upload, memory and execution limits, cron, SSL, file permissions, and outbound mail. Avoid upgrading WordPress, PHP, and plugins during the hosting migration; too many simultaneous changes make failures harder to diagnose.</p>

<h2>2. Lower DNS TTL before cutover day</h2>
<p>Lower web-record TTL to about 300 seconds 24–48 hours beforehand if the DNS provider permits it. Existing caches do not disappear immediately; the previous TTL must expire first.</p>
<p>Inventory the complete zone before changing nameservers or records: <code>A</code>, <code>AAAA</code>, <code>CNAME</code>, <code>MX</code>, SPF, DKIM, DMARC, and verification records. If only the website moves, preserve email records. A stale <code>AAAA</code> record can keep IPv6 users on the old server after the <code>A</code> record changes.</p>

<h2>3. Create and verify backups</h2>
<p>Back up both the database and all WordPress files. Include uploads, plugins, themes, mu-plugins, <code>.htaccess</code>, Nginx/Apache configuration, and cron jobs outside WordPress.</p>
<pre><code>wp db export ~/backup/site-pre-migration.sql
tar -czf ~/backup/site-files.tar.gz \
  --exclude='wp-content/cache' \
  /var/www/example.com</code></pre>
<p>Store backups away from the source server, encrypt sensitive data, and verify that archives can be read. Ideally, restore them into a temporary environment. An untested backup is only an assumption.</p>

<h2>4. Prepare the new hosting environment</h2>
<p>Create the virtual host, database, and least-privileged database user. Install the matching runtime, enable HTTPS, and configure redirects without changing DNS yet. When the domain does not point to the destination, use a DNS challenge or a temporary certificate according to provider capabilities.</p>
<p>Copy files with a tool that preserves timestamps and permissions. Over SSH, <code>rsync</code> supports efficient repeated synchronization:</p>
<pre><code>rsync -aH --delete \
  --exclude='wp-content/cache/' \
  user@old-server:/var/www/example.com/ \
  /var/www/example.com/</code></pre>
<p>Treat <code>--delete</code> carefully: perform a dry run and verify source and destination first. Do not copy cache, sessions, or old backups unless needed.</p>

<h2>5. Import the database and update configuration</h2>
<pre><code>wp db import ~/backup/site-pre-migration.sql
wp db check</code></pre>
<p>Update <code>DB_NAME</code>, <code>DB_USER</code>, <code>DB_PASSWORD</code>, and <code>DB_HOST</code> in <code>wp-config.php</code>. Never leave configuration files or SQL dumps in a web-accessible directory.</p>
<p>Keeping the same domain and path usually requires no URL replacement. When the domain or protocol changes, avoid blind SQL <code>REPLACE()</code> because WordPress stores serialized values. WP-CLI understands serialized data:</p>
<pre><code>wp search-replace 'http://old.example' 'https://new.example' \
  --skip-columns=guid --dry-run

wp search-replace 'http://old.example' 'https://new.example' \
  --skip-columns=guid</code></pre>
<p>Always run <code>--dry-run</code> first. Multisite requires different options and procedures; do not apply a single-site example unchanged.</p>

<h2>6. Test before changing DNS</h2>
<p>Edit the tester's <code>hosts</code> file so the real domain temporarily resolves to the destination IP. The browser sends the correct hostname, WordPress uses its real URL, and HTTPS/SNI behavior closely matches production.</p>
<pre><code>203.0.113.20 example.com www.example.com</code></pre>
<p>Flush the local DNS cache if necessary and use a private browser window. Only the modified computer sees the new host; visitors stay on the old server.</p>
<p>At minimum, test:</p>
<ul>
<li>Home, posts, categories, search, and 404 pages.</li>
<li>Admin login/logout, media uploads, and post editing.</li>
<li>Contact forms, transactional email, CAPTCHA, and webhooks.</li>
<li>Cart, checkout, and payment callbacks in an appropriate test mode.</li>
<li>Permalinks, redirects, robots.txt, sitemap, and canonical URLs.</li>
<li>Images, CSS, JavaScript, fonts, and mixed content.</li>
<li>WP-Cron, system cron, queues, and backup jobs.</li>
</ul>
<pre><code>wp core verify-checksums
wp plugin status
wp rewrite flush
wp cache flush</code></pre>
<p>Run <code>wp rewrite flush</code> only when needed and after web-server permalink rules are correct. Inspect PHP errors, web-server logs, and the browser console instead of judging only the home page.</p>

<h2>7. Perform the final synchronization without data loss</h2>
<p>For a site with occasional writes, enable a brief maintenance or read-only mode on the old server, stop cron and queues, export the final database, and synchronize recent uploads. For WooCommerce or memberships, this is the most important step.</p>
<ol>
<li>Announce a short maintenance window and temporarily block writes.</li>
<li>Stop workers, WP-Cron, or webhook consumers on the old host.</li>
<li>Export the final database.</li>
<li>Synchronize new uploads and generated files.</li>
<li>Import the final database on the new host.</li>
<li>Clear caches, run smoke tests, and enable writes on the destination.</li>
</ol>
<p>Do not run cron and queues on both servers simultaneously; emails, subscriptions, or jobs may execute twice. A site that cannot accept a maintenance window needs replication or a more advanced application-level synchronization design.</p>

<h2>8. Switch DNS</h2>
<p>Change <code>A</code>/<code>AAAA</code> records to the new IP, or update the load balancer according to the architecture. Keep the old configuration intact. Watch access logs on both hosts as traffic shifts.</p>
<pre><code>dig +short example.com A
dig +short example.com AAAA
curl -I https://example.com</code></pre>
<p>Check from several networks or resolvers, not just the hosts-file test computer. If a CDN or proxy is present, purge it carefully and confirm the new origin. Keep the old server read-only during DNS transition to avoid split data.</p>

<h2>9. Monitor after cutover</h2>
<p>For the first 24–72 hours, watch:</p>
<ul>
<li>HTTP 4xx/5xx responses, PHP fatal errors, and slow queries.</li>
<li>CPU, RAM, disk, inodes, I/O, and database connections.</li>
<li>Logins, forms, orders, payments, and email delivery.</li>
<li>WP-Cron, queues, backups, and webhooks.</li>
<li>Certificate chains, HTTP-to-HTTPS redirects, and mixed content.</li>
<li>Traffic still reaching the old IP through long-lived DNS caches.</li>
</ul>
<p>Once stable, restore the normal DNS TTL. Create a fresh backup from the new host and perform a small restoration test.</p>

<h2>10. Plan the rollback</h2>
<p>If a severe issue appears before the new host receives fresh data, point DNS back and reopen the old service. Once the destination has accepted orders or content, rollback is no longer a simple DNS change; newly created data must be reconciled.</p>
<p>Define rollback thresholds before cutover, such as error rate, failed checkout rate, or latency. Document the decision owner, DNS wait time, and treatment of new records. Keep the old server, database, and backups intact for several days or as compliance requires.</p>

<h2>Clean up safely</h2>
<p>After the observation period and once legitimate traffic no longer reaches the old host:</p>
<ul>
<li>Revoke temporary SSH keys, accounts, and migration tokens.</li>
<li>Delete SQL dumps from web roots and temporary directories.</li>
<li>Disable old cron jobs and workers before canceling the old host.</li>
<li>Update infrastructure, monitoring, backup, and IP documentation.</li>
<li>Recheck file permissions: commonly 755 for directories and 644 for files, never blanket 777.</li>
<li>Rotate secrets if they passed through an unsafe channel.</li>
</ul>

<h2>Quick checklist</h2>
<ol>
<li>Inventory runtimes, plugins, cron jobs, and integrations.</li>
<li>Lower TTL 24–48 hours in advance.</li>
<li>Back up files and database, then verify restoration.</li>
<li>Build the destination and perform the initial copy/import.</li>
<li>Test through the hosts file with the real domain.</li>
<li>Briefly freeze writes and perform the final database/file sync.</li>
<li>Switch DNS and monitor both servers.</li>
<li>Keep the old host for rollback, then clean temporary secrets and backups.</li>
</ol>

<h2>Conclusion</h2>
<p>A safe WordPress migration requires repeated synchronization and verification, not one copy followed by a DNS switch. A tested backup, hosts-file validation, controlled writes during the final sync, and a preserved rollback path substantially reduce the risk of data loss and service interruption.</p>

<h2>References</h2>
<ul><li><a href="https://developer.wordpress.org/advanced-administration/upgrade/migrating/" target="_blank" rel="noopener noreferrer">WordPress: Migrating WordPress</a></li><li><a href="https://developer.wordpress.org/cli/commands/db/" target="_blank" rel="noopener noreferrer">WP-CLI database commands</a></li><li><a href="https://developer.wordpress.org/cli/commands/search-replace/" target="_blank" rel="noopener noreferrer">WP-CLI search-replace</a></li></ul>
HTML,
        ],
    ],
];
