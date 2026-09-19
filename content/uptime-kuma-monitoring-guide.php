<?php

return [
    'uptime-kuma-monitoring-guide.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài Uptime Kuma để giám sát website, API và SSL',
            'slug' => 'cai-uptime-kuma-giam-sat-website-api-ssl',
            'image' => 'uptime-kuma-monitoring-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Cài Uptime Kuma giám sát website, API và SSL',
            'meta_keywords' => 'Uptime Kuma, giám sát website, uptime monitoring, Docker Compose, SSL monitoring, API monitoring, status page',
            'meta_description' => 'Hướng dẫn triển khai Uptime Kuma bằng Docker Compose, cấu hình HTTPS reverse proxy, monitor website/API/SSL, cảnh báo, status page, backup và cập nhật.',
            'tags' => ['Uptime Kuma', 'Monitoring', 'Docker', 'Website', 'API', 'SSL', 'Guides'],
            'body' => <<<'HTML'
<p><strong>Website hoạt động khi bạn mở thử không có nghĩa là nó ổn định suốt ngày.</strong> Một lỗi DNS theo khu vực, chứng chỉ SSL sắp hết hạn, API trả nội dung sai hoặc máy chủ chập chờn có thể kéo dài hàng giờ nếu không có giám sát chủ động. Uptime Kuma là công cụ self-hosted giúp kiểm tra dịch vụ định kỳ, gửi cảnh báo và tạo status page mà không cần xây cả hệ thống observability phức tạp.</p>
<p>Hướng dẫn này triển khai Uptime Kuma bằng Docker Compose trên Ubuntu/Debian, đặt sau Nginx và HTTPS. Ví dụ dùng nhánh image major <code>2</code>; trước production hãy xem release note và thử bản cập nhật trên backup.</p>

<h2>Uptime Kuma phù hợp với nhu cầu nào?</h2>
<p>Công cụ phù hợp để kiểm tra từ bên ngoài ứng dụng:</p>
<ul>
<li>HTTP/HTTPS, keyword hoặc JSON response của website và API.</li>
<li>TCP port, ping, DNS và một số dịch vụ hạ tầng.</li>
<li>Ngày hết hạn chứng chỉ TLS.</li>
<li>Heartbeat/push monitor cho cron job hoặc backup job.</li>
<li>Status page công khai hoặc nội bộ.</li>
<li>Cảnh báo qua email, webhook và nhiều nền tảng nhắn tin.</li>
</ul>
<p>Uptime Kuma không thay thế log, metric, trace hay APM. Nó trả lời “dịch vụ có dùng được từ điểm quan sát này không?”, còn nguyên nhân CPU, query hoặc code nào gây lỗi vẫn cần công cụ khác.</p>

<h2>1. Chọn vị trí đặt máy giám sát</h2>
<p>Không nên đặt Uptime Kuma trên cùng máy chủ với website duy nhất cần theo dõi. Nếu máy đó mất nguồn hoặc mất mạng, cả dịch vụ và công cụ cảnh báo đều biến mất. Tốt hơn là dùng VPS nhỏ ở nhà cung cấp hoặc vùng mạng khác.</p>
<p>Chuẩn bị:</p>
<ul>
<li>Máy Linux có Docker Engine và Docker Compose plugin.</li>
<li>Một subdomain riêng, ví dụ <code>status.example.com</code>.</li>
<li>Firewall chỉ mở SSH quản trị, HTTP và HTTPS khi cần.</li>
<li>Nơi lưu backup ngoài máy giám sát.</li>
</ul>
<p>Không dùng đường dẫn con như <code>example.com/uptime</code>; tài liệu dự án khuyến nghị domain hoặc subdomain riêng.</p>

<h2>2. Tạo cấu trúc thư mục</h2>
<pre><code>sudo mkdir -p /opt/uptime-kuma/data
cd /opt/uptime-kuma</code></pre>
<p>Thư mục <code>data</code> giữ database, cấu hình monitor và các dữ liệu bền vững. Hãy giới hạn quyền truy cập cho tài khoản quản trị phù hợp và không đưa nó vào Git.</p>

<h2>3. Tạo Docker Compose</h2>
<p>Tạo file <code>compose.yaml</code>:</p>
<pre><code>services:
  uptime-kuma:
    image: louislam/uptime-kuma:2
    container_name: uptime-kuma
    restart: unless-stopped
    ports:
      - "127.0.0.1:3001:3001"
    volumes:
      - ./data:/app/data
    security_opt:
      - no-new-privileges:true</code></pre>
<p>Binding <code>127.0.0.1</code> ngăn cổng 3001 lộ trực tiếp ra Internet; người dùng truy cập qua reverse proxy HTTPS. Volume <code>/app/data</code> là phần phải backup. Không gắn Docker socket vào container nếu không dùng chức năng cần đến nó, vì socket có quyền rất mạnh trên host.</p>
<pre><code>docker compose pull
docker compose up -d
docker compose ps
docker compose logs --tail=100 uptime-kuma</code></pre>
<p>Trên chính server, kiểm tra <code>curl -I http://127.0.0.1:3001</code>. Nếu container restart liên tục, xem log và quyền ghi thư mục data trước khi cấu hình proxy.</p>

<h2>4. Cấu hình Nginx reverse proxy và WebSocket</h2>
<p>Uptime Kuma dùng WebSocket, vì vậy proxy phải chuyển các header nâng cấp kết nối:</p>
<pre><code>server {
    listen 80;
    server_name status.example.com;

    location / {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 300;
    }
}</code></pre>
<pre><code>sudo nginx -t
sudo systemctl reload nginx</code></pre>
<p>Tạo record DNS cho subdomain trỏ về VPS, sau đó cấp chứng chỉ bằng Certbot hoặc công cụ quản lý TLS phù hợp. Chỉ mở trang quản trị qua HTTPS. Nếu đặt sau Cloudflare hay proxy khác, xác nhận WebSocket được bật và kiểm tra IP nguồn trong log.</p>

<h2>5. Khởi tạo tài khoản quản trị an toàn</h2>
<p>Mở <code>https://status.example.com</code>, tạo tài khoản quản trị với mật khẩu dài và duy nhất. Bật xác thực hai bước nếu phiên bản đang dùng hỗ trợ, lưu recovery code ngoài server và không chia sẻ chung tài khoản.</p>
<p>Trang quản trị chứa URL nội bộ, token, webhook và lịch sử sự cố. Nếu không cần truy cập công khai, hãy hạn chế bằng VPN, allowlist IP hoặc lớp xác thực bổ sung. Status page công khai nên tách khỏi quyền quản trị.</p>

<h2>6. Tạo monitor website đúng cách</h2>
<p>Với website, monitor HTTPS tới một endpoint nhẹ nhưng kiểm tra được dependency quan trọng. Trang chủ có thể trả 200 từ cache ngay cả khi database hỏng. Một health endpoint tốt nên phản ánh khả năng phục vụ thật nhưng không tiết lộ secret.</p>
<ul>
<li><strong>URL:</strong> dùng HTTPS và hostname thật.</li>
<li><strong>Interval:</strong> thường 60 giây là đủ; kiểm tra quá dày gây tải và nhiễu.</li>
<li><strong>Retries:</strong> 2–3 lần giúp tránh cảnh báo vì packet loss thoáng qua.</li>
<li><strong>Timeout:</strong> thấp hơn interval và phù hợp SLA.</li>
<li><strong>Accepted status:</strong> chỉ chấp nhận code phản ánh thành công thực sự.</li>
<li><strong>Certificate expiry:</strong> bật cảnh báo sớm để có thời gian sửa renewal.</li>
</ul>
<p>Đừng tự động coi mọi redirect là thành công. Redirect loop hoặc chuyển sang trang đăng nhập có thể che giấu lỗi endpoint.</p>

<h2>7. Giám sát API bằng nội dung phản hồi</h2>
<p>Một API có thể trả HTTP 200 nhưng body chứa lỗi. Dùng keyword hoặc JSON query để kiểm tra tín hiệu nghiệp vụ ổn định, ví dụ trường <code>status</code> bằng <code>ok</code>.</p>
<pre><code>GET https://api.example.com/health

{
  "status": "ok",
  "database": "ok"
}</code></pre>
<p>Health endpoint không nên gọi dịch vụ đắt tiền mỗi phút hoặc trả thông tin phiên bản, stack trace và credential. Nếu cần header xác thực, dùng token chỉ đọc, phạm vi tối thiểu và có kế hoạch rotation.</p>

<h2>8. Monitor cron và backup bằng Push</h2>
<p>Với job chạy theo lịch, monitor Push hữu ích hơn ping máy chủ. Job gọi URL được cấp sau khi hoàn thành thành công:</p>
<pre><code>#!/usr/bin/env sh
set -eu

/usr/local/bin/run-backup
curl --fail --retry 3 \
  "https://status.example.com/api/push/REDACTED?status=up&amp;msg=OK"</code></pre>
<p>Đặt expected interval và grace period lớn hơn thời gian chạy thông thường. URL push là secret: không ghi vào log công khai, ảnh chụp hoặc repository. Chỉ gửi tín hiệu thành công sau khi đã kiểm tra backup thực sự hoàn tất.</p>

<h2>9. Cấu hình cảnh báo có khả năng hành động</h2>
<p>Tạo notification channel rồi dùng nút test trước khi gắn vào monitor. Nên có ít nhất một kênh không phụ thuộc hạ tầng đang được giám sát. Ví dụ, nếu giám sát mail server nội bộ thì đừng chỉ gửi cảnh báo qua chính mail server đó.</p>
<p>Giảm alert fatigue bằng cách:</p>
<ul>
<li>Retry trước khi chuyển trạng thái DOWN.</li>
<li>Đặt maintenance window cho thời gian triển khai có kế hoạch.</li>
<li>Phân nhóm theo production, staging, internal và mức độ quan trọng.</li>
<li>Chỉ đánh thức người trực với dịch vụ có runbook và tác động rõ.</li>
<li>Kiểm thử cảnh báo DOWN và RECOVERY định kỳ.</li>
</ul>

<h2>10. Tạo status page</h2>
<p>Status page nên trình bày dịch vụ theo ngôn ngữ người dùng hiểu, chẳng hạn Website, API, Thanh toán, Email, thay vì hostname nội bộ. Chỉ công khai monitor phù hợp; đừng để lộ IP, tên server hoặc endpoint quản trị.</p>
<p>Trạng thái tự động không thay thế cập nhật sự cố. Khi có incident, đăng thông báo ngắn về phạm vi ảnh hưởng, thời điểm bắt đầu và lần cập nhật tiếp theo. Không suy đoán nguyên nhân khi chưa xác minh.</p>

<h2>11. Backup dữ liệu Uptime Kuma</h2>
<p>Backup thư mục được mount vào <code>/app/data</code>. Để có bản sao nhất quán, dừng container ngắn trước khi sao chép:</p>
<pre><code>cd /opt/uptime-kuma
docker compose stop uptime-kuma
tar -czf /srv/backups/uptime-kuma-$(date +%F).tar.gz data
docker compose start uptime-kuma</code></pre>
<p>Chuyển backup sang máy khác, mã hóa và áp dụng retention. Định kỳ restore vào máy thử nghiệm để xác nhận file dùng được. Backup giám sát cũng chứa cấu hình notification và token, nên cần bảo vệ như secret.</p>

<h2>12. Cập nhật có rollback</h2>
<p>Đọc release note, đặc biệt khi đổi major version. Tạo backup trước mỗi lần cập nhật:</p>
<pre><code>cd /opt/uptime-kuma
docker compose pull
docker compose up -d
docker compose logs --tail=100 uptime-kuma</code></pre>
<p>Kiểm tra login, monitor, notification và status page sau cập nhật. Tránh dùng tag không cố định trong môi trường cần thay đổi có kiểm soát; có thể pin phiên bản đã kiểm thử rồi nâng theo lịch. Nếu phải rollback image, xác nhận database/data format vẫn tương thích với phiên bản cũ.</p>

<h2>13. Các lỗi thường gặp</h2>
<ul>
<li><strong>Dashboard mất kết nối:</strong> reverse proxy thiếu header WebSocket hoặc timeout quá ngắn.</li>
<li><strong>Monitor báo DOWN nhưng trình duyệt mở được:</strong> kiểm tra DNS, firewall và route từ bên trong container, không phải từ laptop.</li>
<li><strong>Chỉ lỗi IPv6:</strong> Docker network chưa bật IPv6 hoặc DNS trả AAAA không dùng được.</li>
<li><strong>SSL sắp hết hạn dù đã renew:</strong> proxy/CDN vẫn phục vụ certificate cũ hoặc monitor đi qua endpoint khác.</li>
<li><strong>Không có cảnh báo:</strong> channel chưa được test, token hết hạn hoặc monitor chưa gắn notification.</li>
<li><strong>Mất cấu hình sau khi tạo lại container:</strong> volume chưa mount đúng vào <code>/app/data</code>.</li>
</ul>

<h2>Checklist production</h2>
<ol>
<li>Đặt Uptime Kuma ngoài hạ tầng chính cần giám sát.</li>
<li>Bind cổng ứng dụng vào localhost và dùng HTTPS reverse proxy.</li>
<li>Bảo vệ admin bằng mật khẩu mạnh, 2FA, VPN hoặc allowlist phù hợp.</li>
<li>Dùng retry, timeout và interval theo SLA để giảm false positive.</li>
<li>Test cảnh báo bằng một sự cố có kiểm soát.</li>
<li>Backup <code>/app/data</code> ra hệ thống khác và thử restore.</li>
<li>Đọc release note, pin phiên bản đã kiểm thử và chuẩn bị rollback.</li>
<li>Giám sát chính Uptime Kuma từ một dịch vụ độc lập nếu uptime là quan trọng.</li>
</ol>

<h2>Kết luận</h2>
<p>Uptime Kuma giúp xây dựng lớp giám sát bên ngoài gọn nhẹ cho website, API, SSL và job định kỳ. Giá trị không chỉ nằm ở dashboard xanh, mà ở việc điểm giám sát độc lập, cảnh báo đến đúng người, có runbook xử lý và bản thân hệ thống giám sát cũng được backup, cập nhật và kiểm thử như một dịch vụ production.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://github.com/louislam/uptime-kuma/wiki/%F0%9F%94%A7-How-to-Install" target="_blank" rel="noopener noreferrer">Uptime Kuma: How to Install</a></li><li><a href="https://github.com/louislam/uptime-kuma/wiki/Reverse-Proxy" target="_blank" rel="noopener noreferrer">Uptime Kuma: Reverse Proxy</a></li><li><a href="https://github.com/louislam/uptime-kuma/releases" target="_blank" rel="noopener noreferrer">Uptime Kuma releases</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Install Uptime Kuma for Website, API, and SSL Monitoring',
            'slug' => 'install-uptime-kuma-website-api-ssl-monitoring',
            'image' => 'uptime-kuma-monitoring-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Install Uptime Kuma for Website, API, and SSL Monitoring',
            'meta_keywords' => 'Uptime Kuma, website monitoring, uptime monitoring, Docker Compose, SSL monitoring, API monitoring, status page',
            'meta_description' => 'Deploy Uptime Kuma with Docker Compose, secure it behind an HTTPS reverse proxy, monitor websites, APIs and SSL, configure alerts and status pages, back up data, and update safely.',
            'tags' => ['Uptime Kuma', 'Monitoring', 'Docker', 'Website', 'API', 'SSL', 'Guides'],
            'body' => <<<'HTML'
<p><strong>A website working when you manually open it does not mean it has been healthy all day.</strong> Regional DNS failures, expiring TLS certificates, incorrect API responses, or intermittent servers can continue for hours without proactive monitoring. Uptime Kuma is a self-hosted tool for periodic service checks, notifications, and status pages without building a full observability platform.</p>
<p>This guide deploys Uptime Kuma with Docker Compose on Ubuntu or Debian behind Nginx and HTTPS. The example follows the major image tag <code>2</code>; review release notes and test updates against a backup before production.</p>

<h2>What is Uptime Kuma good for?</h2>
<ul>
<li>HTTP/HTTPS, keyword, or JSON checks for websites and APIs.</li>
<li>TCP ports, ping, DNS, and selected infrastructure services.</li>
<li>TLS certificate expiration monitoring.</li>
<li>Heartbeat/push monitors for cron and backup jobs.</li>
<li>Public or private status pages.</li>
<li>Notifications through email, webhooks, and messaging platforms.</li>
</ul>
<p>It does not replace logs, metrics, traces, or APM. Uptime monitoring answers whether a service works from a particular observation point; another tool is still needed to explain which query, resource, or code path caused a failure.</p>

<h2>1. Choose the monitoring location</h2>
<p>Do not place Uptime Kuma on the only server it monitors. If that host loses power or network connectivity, both the service and its alarm disappear. Prefer a small VPS in another provider or network region.</p>
<p>Prepare a Linux host with Docker Engine and the Compose plugin, a dedicated subdomain such as <code>status.example.com</code>, a restrictive firewall, and off-host backup storage. Use a domain or subdomain rather than a path such as <code>example.com/uptime</code>, which the project documentation does not support.</p>

<h2>2. Create the directory structure</h2>
<pre><code>sudo mkdir -p /opt/uptime-kuma/data
cd /opt/uptime-kuma</code></pre>
<p>The <code>data</code> directory holds the database, monitors, and persistent settings. Restrict it to the appropriate administrator and never commit it to Git.</p>

<h2>3. Create the Docker Compose configuration</h2>
<p>Create <code>compose.yaml</code>:</p>
<pre><code>services:
  uptime-kuma:
    image: louislam/uptime-kuma:2
    container_name: uptime-kuma
    restart: unless-stopped
    ports:
      - "127.0.0.1:3001:3001"
    volumes:
      - ./data:/app/data
    security_opt:
      - no-new-privileges:true</code></pre>
<p>Binding to <code>127.0.0.1</code> prevents port 3001 from being directly exposed; users connect through the HTTPS proxy. The <code>/app/data</code> volume is the critical backup target. Do not mount the Docker socket unless a required feature needs it, because the socket carries powerful host privileges.</p>
<pre><code>docker compose pull
docker compose up -d
docker compose ps
docker compose logs --tail=100 uptime-kuma</code></pre>
<p>On the server, test <code>curl -I http://127.0.0.1:3001</code>. If the container keeps restarting, inspect logs and data-directory permissions before configuring the proxy.</p>

<h2>4. Configure Nginx and WebSocket proxying</h2>
<p>Uptime Kuma uses WebSocket, so the reverse proxy must forward upgrade headers:</p>
<pre><code>server {
    listen 80;
    server_name status.example.com;

    location / {
        proxy_pass http://127.0.0.1:3001;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 300;
    }
}</code></pre>
<pre><code>sudo nginx -t
sudo systemctl reload nginx</code></pre>
<p>Point the subdomain at the VPS, then issue a certificate with Certbot or another TLS manager. Expose administration only over HTTPS. When using Cloudflare or another proxy, ensure WebSockets are enabled and verify source-IP handling.</p>

<h2>5. Secure the administrator account</h2>
<p>Open <code>https://status.example.com</code> and create an administrator with a long unique password. Enable two-factor authentication when supported by your installed version, and store recovery codes away from the host.</p>
<p>The dashboard contains internal URLs, tokens, webhooks, and incident history. If public access is unnecessary, restrict it through a VPN, IP allowlist, or an additional authentication layer. Keep public status pages separate from administrative access.</p>

<h2>6. Configure a useful website monitor</h2>
<p>Monitor an HTTPS endpoint that is lightweight but verifies an important dependency. A cached home page may return 200 even when the database is unavailable. A health endpoint should represent actual serviceability without exposing secrets.</p>
<ul>
<li><strong>URL:</strong> use HTTPS and the real hostname.</li>
<li><strong>Interval:</strong> 60 seconds is often sufficient.</li>
<li><strong>Retries:</strong> 2–3 attempts reduce transient packet-loss alerts.</li>
<li><strong>Timeout:</strong> keep it below the interval and aligned with the SLA.</li>
<li><strong>Accepted status:</strong> accept only codes that mean real success.</li>
<li><strong>Certificate expiry:</strong> alert early enough to repair renewal.</li>
</ul>
<p>Do not automatically treat every redirect as healthy. A redirect loop or unexpected login redirect can hide an endpoint failure.</p>

<h2>7. Validate API response content</h2>
<p>An API can return HTTP 200 with an error in its body. Use a keyword or JSON query to verify a stable business signal such as <code>status</code> equal to <code>ok</code>.</p>
<pre><code>GET https://api.example.com/health

{
  "status": "ok",
  "database": "ok"
}</code></pre>
<p>The health endpoint should not call expensive services every minute or expose versions, stack traces, or credentials. If authentication is needed, use a read-only least-privilege token with a rotation plan.</p>

<h2>8. Monitor cron and backup jobs with Push</h2>
<p>A Push monitor is more useful than server ping for scheduled jobs. Call its unique URL only after the job succeeds:</p>
<pre><code>#!/usr/bin/env sh
set -eu

/usr/local/bin/run-backup
curl --fail --retry 3 \
  "https://status.example.com/api/push/REDACTED?status=up&amp;msg=OK"</code></pre>
<p>Set the expected interval and grace period above normal runtime. Treat the push URL as a secret and keep it out of public logs, screenshots, and repositories. Send success only after verifying the backup completed.</p>

<h2>9. Build actionable notifications</h2>
<p>Create a notification channel and use its test button before attaching it to monitors. Maintain at least one channel outside the monitored infrastructure. If an internal mail server is monitored, do not rely exclusively on that same server for alerts.</p>
<ul>
<li>Retry before declaring DOWN.</li>
<li>Use maintenance windows for planned deployments.</li>
<li>Group production, staging, internal, and critical monitors.</li>
<li>Page on-call staff only for services with clear impact and a runbook.</li>
<li>Test both DOWN and RECOVERY notifications regularly.</li>
</ul>

<h2>10. Publish a useful status page</h2>
<p>Use user-facing service names such as Website, API, Payments, and Email instead of internal hostnames. Publish only appropriate monitors; never expose management endpoints, private IPs, or server names.</p>
<p>Automated status does not replace incident communication. Post a brief update with impact, start time, and next update during an incident. Avoid publishing an unverified cause.</p>

<h2>11. Back up Uptime Kuma</h2>
<p>Back up the host directory mounted at <code>/app/data</code>. Stop the container briefly for a consistent copy:</p>
<pre><code>cd /opt/uptime-kuma
docker compose stop uptime-kuma
tar -czf /srv/backups/uptime-kuma-$(date +%F).tar.gz data
docker compose start uptime-kuma</code></pre>
<p>Copy archives to another machine, encrypt them, and apply retention. Periodically restore into a test host. Monitoring backups may contain notification settings and tokens, so protect them as secrets.</p>

<h2>12. Update with a rollback plan</h2>
<p>Read release notes, especially before a major upgrade, and create a backup first:</p>
<pre><code>cd /opt/uptime-kuma
docker compose pull
docker compose up -d
docker compose logs --tail=100 uptime-kuma</code></pre>
<p>Test login, monitors, notifications, and status pages afterward. Environments that require controlled change can pin a tested version and upgrade on schedule. Before rolling an image back, confirm that its data format remains compatible.</p>

<h2>13. Common problems</h2>
<ul>
<li><strong>Dashboard disconnects:</strong> WebSocket headers are missing or the proxy timeout is too short.</li>
<li><strong>DOWN while a browser works:</strong> test DNS, routing, and firewalls from inside the container, not from a laptop.</li>
<li><strong>IPv6-only failure:</strong> the Docker network lacks IPv6 or DNS publishes an unusable AAAA record.</li>
<li><strong>Old certificate after renewal:</strong> a proxy/CDN still serves the old chain or the monitor reaches another endpoint.</li>
<li><strong>No notification:</strong> the channel was not tested, its token expired, or it was not attached to the monitor.</li>
<li><strong>Configuration disappears:</strong> the persistent volume was not mounted at <code>/app/data</code>.</li>
</ul>

<h2>Production checklist</h2>
<ol>
<li>Host Uptime Kuma outside the primary monitored infrastructure.</li>
<li>Bind the application port to localhost and use an HTTPS reverse proxy.</li>
<li>Protect administration with a strong password, 2FA, VPN, or suitable allowlist.</li>
<li>Tune retries, timeouts, and intervals to the service SLA.</li>
<li>Test alerts through a controlled failure.</li>
<li>Back up <code>/app/data</code> off-host and test restoration.</li>
<li>Review releases, pin tested versions, and prepare rollback.</li>
<li>Monitor Uptime Kuma itself from an independent service when availability is critical.</li>
</ol>

<h2>Conclusion</h2>
<p>Uptime Kuma provides a compact external-monitoring layer for websites, APIs, TLS certificates, and scheduled jobs. Its value is not merely a green dashboard: the observation point must be independent, alerts must reach the right person, incidents need runbooks, and the monitoring system itself must be backed up, updated, and tested like any production service.</p>

<h2>References</h2>
<ul><li><a href="https://github.com/louislam/uptime-kuma/wiki/%F0%9F%94%A7-How-to-Install" target="_blank" rel="noopener noreferrer">Uptime Kuma: How to Install</a></li><li><a href="https://github.com/louislam/uptime-kuma/wiki/Reverse-Proxy" target="_blank" rel="noopener noreferrer">Uptime Kuma: Reverse Proxy</a></li><li><a href="https://github.com/louislam/uptime-kuma/releases" target="_blank" rel="noopener noreferrer">Uptime Kuma releases</a></li></ul>
HTML,
        ],
    ],
];
