<?php

return [
    'n8n-docker-postgresql-guide.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài n8n bằng Docker Compose, PostgreSQL và HTTPS',
            'slug' => 'cai-n8n-docker-compose-postgresql-https',
            'image' => 'n8n-docker-postgresql-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Cài n8n bằng Docker Compose, PostgreSQL và HTTPS',
            'meta_keywords' => 'cài n8n, n8n Docker Compose, n8n PostgreSQL, self-host n8n, n8n HTTPS, n8n webhook, workflow automation',
            'meta_description' => 'Hướng dẫn self-host n8n với Docker Compose, PostgreSQL, Nginx HTTPS, webhook URL, encryption key, backup, cập nhật, bảo mật và giám sát production.',
            'tags' => ['n8n', 'Docker Compose', 'PostgreSQL', 'Automation', 'Self-Hosted', 'HTTPS', 'Guides'],
            'body' => <<<'HTML'
<p><strong>n8n giúp kết nối API, webhook, lịch chạy và nhiều ứng dụng thành workflow tự động hóa.</strong> Self-host mang lại quyền kiểm soát dữ liệu và hạ tầng, nhưng cũng khiến bạn chịu trách nhiệm về secret, backup, HTTPS, cập nhật và khả năng khôi phục.</p>
<p>Hướng dẫn này triển khai một instance n8n đơn bằng Docker Compose, PostgreSQL và Nginx reverse proxy. Đây là cấu hình phù hợp cho nhóm nhỏ hoặc tải vừa; queue mode nhiều worker cần Redis và kiến trúc riêng.</p>

<h2>Kiến trúc sẽ triển khai</h2>
<ul>
<li>Nginx nhận HTTPS tại <code>automation.example.com</code>.</li>
<li>n8n chỉ bind cổng 5678 vào localhost.</li>
<li>PostgreSQL nằm trong network nội bộ, không public port.</li>
<li>Database và thư mục <code>/home/node/.n8n</code> có volume bền vững.</li>
<li>Secret nằm trong file env có quyền hạn chế, không commit Git.</li>
</ul>
<blockquote>Backup database mà mất <code>N8N_ENCRYPTION_KEY</code> có thể khiến credential đã mã hóa không còn sử dụng được. Hãy bảo vệ key như dữ liệu quan trọng nhất của hệ thống.</blockquote>

<h2>1. Chuẩn bị máy chủ và DNS</h2>
<p>Dùng một máy Linux được cập nhật, đã cài Docker Engine và Docker Compose plugin. Tạo record DNS cho <code>automation.example.com</code> trỏ tới IP máy chủ. Firewall chỉ mở SSH quản trị, HTTP và HTTPS; không mở 5432 hoặc 5678 ra Internet.</p>
<p>Trước khi triển khai, kiểm tra:</p>
<pre><code>docker --version
docker compose version
df -h
free -h</code></pre>
<p>Tách instance development và production nếu workflow có tác động thật như gửi email, tạo invoice hoặc xóa dữ liệu. Không thử node lạ trực tiếp trên production.</p>

<h2>2. Tạo thư mục dự án</h2>
<pre><code>sudo mkdir -p /opt/n8n
sudo chown "$USER":"$USER" /opt/n8n
cd /opt/n8n
mkdir -p n8n_data postgres_data backups</code></pre>
<p>Không đặt backup trong web root. Nếu dùng bind mount, kiểm tra UID/GID và quyền ghi của container; named volume có thể đơn giản hơn ở một số môi trường.</p>

<h2>3. Tạo secret và file môi trường</h2>
<p>Tạo password database và encryption key đủ dài:</p>
<pre><code>openssl rand -base64 36
openssl rand -hex 32</code></pre>
<p>Tạo file <code>.env</code>:</p>
<pre><code>N8N_VERSION=2.5.5
POSTGRES_PASSWORD=replace-with-a-long-random-password
N8N_ENCRYPTION_KEY=replace-with-a-stable-random-key
N8N_DOMAIN=automation.example.com
GENERIC_TIMEZONE=Asia/Ho_Chi_Minh</code></pre>
<pre><code>chmod 600 .env</code></pre>
<p>Phiên bản trên chỉ là ví dụ pin. Hãy chọn release đang được hỗ trợ, đọc release note và thử trước khi nâng. Không dùng <code>latest</code> cho production vì một lần pull có thể đưa thay đổi breaking không được kiểm soát.</p>

<h2>4. Tạo Docker Compose</h2>
<p>Tạo <code>compose.yaml</code>:</p>
<pre><code>services:
  postgres:
    image: postgres:17-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: n8n
      POSTGRES_USER: n8n
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - ./postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U n8n -d n8n"]
      interval: 10s
      timeout: 5s
      retries: 10
    networks: [backend]

  n8n:
    image: docker.n8n.io/n8nio/n8n:${N8N_VERSION}
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    ports:
      - "127.0.0.1:5678:5678"
    environment:
      DB_TYPE: postgresdb
      DB_POSTGRESDB_HOST: postgres
      DB_POSTGRESDB_PORT: 5432
      DB_POSTGRESDB_DATABASE: n8n
      DB_POSTGRESDB_USER: n8n
      DB_POSTGRESDB_PASSWORD: ${POSTGRES_PASSWORD}
      N8N_ENCRYPTION_KEY: ${N8N_ENCRYPTION_KEY}
      N8N_HOST: ${N8N_DOMAIN}
      N8N_PORT: 5678
      N8N_PROTOCOL: https
      N8N_PROXY_HOPS: 1
      WEBHOOK_URL: https://${N8N_DOMAIN}/
      N8N_EDITOR_BASE_URL: https://${N8N_DOMAIN}/
      GENERIC_TIMEZONE: ${GENERIC_TIMEZONE}
      TZ: ${GENERIC_TIMEZONE}
      EXECUTIONS_DATA_PRUNE: "true"
      EXECUTIONS_DATA_MAX_AGE: 336
    volumes:
      - ./n8n_data:/home/node/.n8n
    networks: [backend]

networks:
  backend:</code></pre>
<p>PostgreSQL không có <code>ports</code>, nên chỉ container trong network truy cập được. n8n bind localhost để Nginx là cổng công khai duy nhất. <code>WEBHOOK_URL</code> rất quan trọng: nó quyết định callback URL n8n hiển thị cho webhook và OAuth.</p>
<p><code>EXECUTIONS_DATA_MAX_AGE</code> cần điều chỉnh theo chính sách lưu trữ và nhu cầu điều tra. Lưu execution quá lâu có thể làm database phình to và giữ dữ liệu nhạy cảm.</p>

<h2>5. Khởi động và kiểm tra container</h2>
<pre><code>docker compose config
docker compose pull
docker compose up -d
docker compose ps
docker compose logs --tail=100 n8n</code></pre>
<p><code>docker compose config</code> giúp phát hiện biến thiếu, nhưng output có thể chứa secret đã resolve; không đăng nó vào ticket hoặc chat công khai. Kiểm tra local:</p>
<pre><code>curl -I http://127.0.0.1:5678</code></pre>
<p>Nếu n8n không kết nối database, kiểm tra healthcheck, password, quyền thư mục và log PostgreSQL. Không xóa volume để “thử lại” khi chưa có backup.</p>

<h2>6. Cấu hình Nginx reverse proxy</h2>
<pre><code>server {
    listen 80;
    server_name automation.example.com;

    location / {
        proxy_pass http://127.0.0.1:5678;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 300;
        proxy_send_timeout 300;
    }
}</code></pre>
<pre><code>sudo nginx -t
sudo systemctl reload nginx</code></pre>
<p>Cấp chứng chỉ TLS bằng Certbot hoặc giải pháp của nhà cung cấp, bật redirect HTTP sang HTTPS rồi truy cập domain thật. n8n dùng kết nối thời gian dài cho editor và execution view, nên header WebSocket cùng timeout proxy phải đúng.</p>
<p>Nếu còn thêm CDN/load balancer trước Nginx, <code>N8N_PROXY_HOPS</code> phải phản ánh đúng số proxy tin cậy. Cấu hình sai có thể làm n8n hiểu sai protocol/IP hoặc tin header do client giả mạo.</p>

<h2>7. Tạo owner và bảo vệ editor</h2>
<p>Ở lần mở đầu, tạo tài khoản owner bằng email quản trị và mật khẩu duy nhất. Bật 2FA nếu phiên bản/edition hỗ trợ. Không dùng chung owner account; cấp quyền theo vai trò và thu hồi ngay khi nhân sự rời nhóm.</p>
<p>Editor có credential để truy cập email, database, cloud và API. Nếu chỉ đội nội bộ sử dụng, nên đặt sau VPN, identity-aware proxy hoặc allowlist IP ngoài HTTPS. Public webhook vẫn cần truy cập từ Internet, do đó có thể tách chính sách đường dẫn ở reverse proxy hoặc dùng kiến trúc ingress phù hợp.</p>

<h2>8. Kiểm tra webhook từ đầu đến cuối</h2>
<ol>
<li>Tạo workflow với Webhook node và Respond to Webhook.</li>
<li>Dùng Test URL khi editor đang lắng nghe.</li>
<li>Activate workflow rồi chuyển sang Production URL.</li>
<li>Gửi request từ một máy bên ngoài, không chỉ localhost.</li>
<li>Kiểm tra execution, status code và response.</li>
</ol>
<pre><code>curl -X POST https://automation.example.com/webhook/health-test \
  -H 'Content-Type: application/json' \
  -d '{"source":"external-check"}'</code></pre>
<p>Bảo vệ webhook bằng secret header, chữ ký HMAC, OAuth hoặc cơ chế node hỗ trợ. URL khó đoán không phải authentication. Với webhook tạo side effect, thêm idempotency/dedup để provider retry không tạo tác vụ trùng.</p>

<h2>9. Quản lý credential và encryption key</h2>
<p>n8n mã hóa credential bằng encryption key. Giữ key ổn định qua restart, migration và restore. Nếu thay key tùy ý, credential cũ có thể không giải mã được.</p>
<ul>
<li>Không đặt key trong Compose được commit lên Git.</li>
<li>Lưu bản sao trong password manager/secret manager và backup bảo mật.</li>
<li>Không xuất workflow kèm credential hoặc chia sẻ screenshot secret.</li>
<li>Dùng API credential riêng cho n8n, scope tối thiểu và có rotation.</li>
<li>Ưu tiên OAuth/service account thay vì tài khoản cá nhân.</li>
</ul>

<h2>10. Xử lý file và dữ liệu execution</h2>
<p>Workflow có file lớn có thể nhanh chóng đầy disk. Giới hạn kích thước đầu vào, đặt execution retention và giám sát volume. Không lưu binary hoặc payload nhạy cảm lâu hơn nhu cầu nghiệp vụ.</p>
<p>Tránh đưa secret vào dữ liệu node vì execution log có thể lưu input/output. Dùng Credential store và environment/secret mechanism phù hợp. Với lưu lượng cao hoặc binary lớn, đánh giá tính năng external storage theo edition và tài liệu phiên bản đang dùng.</p>

<h2>11. Backup đúng ba thành phần</h2>
<p>Cần bảo vệ:</p>
<ol>
<li>PostgreSQL chứa workflow, execution và metadata.</li>
<li>Thư mục <code>n8n_data</code> chứa dữ liệu instance cục bộ.</li>
<li><code>N8N_ENCRYPTION_KEY</code> và cấu hình deployment.</li>
</ol>
<pre><code>cd /opt/n8n

docker compose exec -T postgres \
  pg_dump -U n8n -d n8n -Fc \
  &gt; backups/n8n-$(date +%F).dump

tar -czf backups/n8n-data-$(date +%F).tar.gz n8n_data</code></pre>
<p>Copy backup sang storage khác, mã hóa, đặt retention và kiểm tra restore trên máy thử. File nằm cùng VPS không bảo vệ khỏi mất disk hoặc bị xâm nhập.</p>

<h2>12. Cập nhật an toàn</h2>
<ol>
<li>Đọc release note và breaking changes.</li>
<li>Backup database, data directory và encryption key.</li>
<li>Thử phiên bản mới trên staging với workflow quan trọng.</li>
<li>Đổi <code>N8N_VERSION</code> sang phiên bản đã duyệt.</li>
<li>Pull và recreate container trong cửa sổ thay đổi.</li>
<li>Kiểm tra login, webhook, schedule, credential và execution.</li>
</ol>
<pre><code>docker compose pull
docker compose up -d
docker compose logs --tail=200 n8n</code></pre>
<p>Migration database có thể khiến rollback image không đơn giản. Xác minh tài liệu downgrade, giữ backup trước migration và không tự động cập nhật production không kiểm soát.</p>

<h2>13. Giám sát và cảnh báo</h2>
<ul>
<li>Container restart, health và log error.</li>
<li>CPU, RAM, disk, inode và volume growth.</li>
<li>PostgreSQL connection, size, slow query và backup age.</li>
<li>Workflow failure rate, duration và số execution chờ.</li>
<li>Webhook latency, 4xx/5xx và certificate expiry.</li>
<li>Schedule không chạy đúng hạn và credential hết hạn.</li>
</ul>
<p>Tạo một monitor bên ngoài gọi health/webhook không có side effect. Cảnh báo phải đi qua kênh không phụ thuộc hoàn toàn vào chính n8n, nếu không workflow cảnh báo cũng im lặng khi n8n down.</p>

<h2>14. Hardening bổ sung</h2>
<ul>
<li>Chỉ cài community node đã review; node có thể chạy code và truy cập credential.</li>
<li>Giới hạn Code/Execute Command và node rủi ro theo nhu cầu.</li>
<li>Chặn outbound network tới metadata/internal service nếu không cần.</li>
<li>Chạy security audit của n8n định kỳ.</li>
<li>Cập nhật host, Docker, image và dependency theo lịch.</li>
<li>Tách credential development và production.</li>
<li>Dùng least privilege cho database và mọi integration.</li>
</ul>

<h2>Checklist production</h2>
<ol>
<li>Image được pin phiên bản và đã thử trên staging.</li>
<li>PostgreSQL/5678 không public; editor chỉ qua HTTPS.</li>
<li><code>WEBHOOK_URL</code>, timezone và proxy hops đúng.</li>
<li>Encryption key cố định, được backup và không nằm trong Git.</li>
<li>Owner/2FA/quyền user được kiểm soát.</li>
<li>Webhook có authentication, validation và idempotency.</li>
<li>Execution/binary retention phù hợp và disk được giám sát.</li>
<li>Database + data + secret đã được restore thử.</li>
<li>Có monitor ngoài hệ thống và runbook rollback.</li>
</ol>

<h2>Kết luận</h2>
<p>Self-host n8n production không kết thúc ở lệnh <code>docker compose up</code>. Cấu hình tốt cần database bền vững, encryption key được bảo vệ, URL webhook đúng, reverse proxy HTTPS, credential tối thiểu và backup đã thử restore. Khi nền tảng được vận hành như một dịch vụ production, workflow tự động hóa mới thực sự đáng tin cậy.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://docs.n8n.io/hosting/installation/docker/" target="_blank" rel="noopener noreferrer">n8n Docs: Docker installation</a></li><li><a href="https://docs.n8n.io/hosting/configuration/configuration-examples/webhook-url/" target="_blank" rel="noopener noreferrer">n8n Docs: Webhook URL behind a reverse proxy</a></li><li><a href="https://docs.n8n.io/hosting/securing/security-audit/" target="_blank" rel="noopener noreferrer">n8n Docs: Security audit</a></li><li><a href="https://docs.n8n.io/hosting/installation/updating/" target="_blank" rel="noopener noreferrer">n8n Docs: Updating</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Install n8n with Docker Compose, PostgreSQL, and HTTPS',
            'slug' => 'install-n8n-docker-compose-postgresql-https',
            'image' => 'n8n-docker-postgresql-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Install n8n with Docker Compose, PostgreSQL, and HTTPS',
            'meta_keywords' => 'install n8n, n8n Docker Compose, n8n PostgreSQL, self-host n8n, n8n HTTPS, n8n webhook, workflow automation',
            'meta_description' => 'Self-host n8n with Docker Compose, PostgreSQL, Nginx HTTPS, correct webhook URLs, a stable encryption key, backups, safe updates, security, and production monitoring.',
            'tags' => ['n8n', 'Docker Compose', 'PostgreSQL', 'Automation', 'Self-Hosted', 'HTTPS', 'Guides'],
            'body' => <<<'HTML'
<p><strong>n8n connects APIs, webhooks, schedules, and applications into automated workflows.</strong> Self-hosting gives control over data and infrastructure, but it also makes you responsible for secrets, backups, HTTPS, upgrades, and recovery.</p>
<p>This guide deploys one n8n instance with Docker Compose, PostgreSQL, and an Nginx reverse proxy. It suits a small team or moderate workload; multi-worker queue mode requires Redis and a separate architecture.</p>

<h2>Target architecture</h2>
<ul>
<li>Nginx terminates HTTPS at <code>automation.example.com</code>.</li>
<li>n8n binds port 5678 to localhost only.</li>
<li>PostgreSQL stays on an internal network without a public port.</li>
<li>The database and <code>/home/node/.n8n</code> use persistent volumes.</li>
<li>Secrets live in a restricted environment file outside Git.</li>
</ul>
<blockquote>A database backup without <code>N8N_ENCRYPTION_KEY</code> may leave encrypted credentials unusable. Protect that key as one of the system's most important assets.</blockquote>

<h2>1. Prepare the server and DNS</h2>
<p>Use an updated Linux host with Docker Engine and the Compose plugin. Point <code>automation.example.com</code> at the server. Permit only administrative SSH, HTTP, and HTTPS through the firewall; never expose 5432 or 5678 publicly.</p>
<pre><code>docker --version
docker compose version
df -h
free -h</code></pre>
<p>Separate development and production when workflows send real email, create invoices, or delete data. Do not test unfamiliar nodes directly in production.</p>

<h2>2. Create the project directories</h2>
<pre><code>sudo mkdir -p /opt/n8n
sudo chown "$USER":"$USER" /opt/n8n
cd /opt/n8n
mkdir -p n8n_data postgres_data backups</code></pre>
<p>Keep backups outside the web root. For bind mounts, verify container UID/GID and write permissions; named volumes may be easier in some environments.</p>

<h2>3. Create secrets and the environment file</h2>
<pre><code>openssl rand -base64 36
openssl rand -hex 32</code></pre>
<p>Create <code>.env</code>:</p>
<pre><code>N8N_VERSION=2.5.5
POSTGRES_PASSWORD=replace-with-a-long-random-password
N8N_ENCRYPTION_KEY=replace-with-a-stable-random-key
N8N_DOMAIN=automation.example.com
GENERIC_TIMEZONE=Asia/Ho_Chi_Minh</code></pre>
<pre><code>chmod 600 .env</code></pre>
<p>The version is an example pin. Select a supported release, read its notes, and test before upgrading. Avoid <code>latest</code> in production because a pull can introduce uncontrolled breaking changes.</p>

<h2>4. Create the Docker Compose file</h2>
<pre><code>services:
  postgres:
    image: postgres:17-alpine
    restart: unless-stopped
    environment:
      POSTGRES_DB: n8n
      POSTGRES_USER: n8n
      POSTGRES_PASSWORD: ${POSTGRES_PASSWORD}
    volumes:
      - ./postgres_data:/var/lib/postgresql/data
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U n8n -d n8n"]
      interval: 10s
      timeout: 5s
      retries: 10
    networks: [backend]

  n8n:
    image: docker.n8n.io/n8nio/n8n:${N8N_VERSION}
    restart: unless-stopped
    depends_on:
      postgres:
        condition: service_healthy
    ports:
      - "127.0.0.1:5678:5678"
    environment:
      DB_TYPE: postgresdb
      DB_POSTGRESDB_HOST: postgres
      DB_POSTGRESDB_PORT: 5432
      DB_POSTGRESDB_DATABASE: n8n
      DB_POSTGRESDB_USER: n8n
      DB_POSTGRESDB_PASSWORD: ${POSTGRES_PASSWORD}
      N8N_ENCRYPTION_KEY: ${N8N_ENCRYPTION_KEY}
      N8N_HOST: ${N8N_DOMAIN}
      N8N_PORT: 5678
      N8N_PROTOCOL: https
      N8N_PROXY_HOPS: 1
      WEBHOOK_URL: https://${N8N_DOMAIN}/
      N8N_EDITOR_BASE_URL: https://${N8N_DOMAIN}/
      GENERIC_TIMEZONE: ${GENERIC_TIMEZONE}
      TZ: ${GENERIC_TIMEZONE}
      EXECUTIONS_DATA_PRUNE: "true"
      EXECUTIONS_DATA_MAX_AGE: 336
    volumes:
      - ./n8n_data:/home/node/.n8n
    networks: [backend]

networks:
  backend:</code></pre>
<p>PostgreSQL has no public <code>ports</code> mapping. n8n binds localhost so Nginx is the only public entrance. <code>WEBHOOK_URL</code> controls callback URLs presented to webhook and OAuth providers.</p>
<p>Tune execution retention to investigation and compliance requirements. Excess retention grows the database and may preserve sensitive payloads.</p>

<h2>5. Start and inspect the containers</h2>
<pre><code>docker compose config
docker compose pull
docker compose up -d
docker compose ps
docker compose logs --tail=100 n8n</code></pre>
<p>Resolved Compose output may expose secrets; never paste it into public tickets. Test locally with <code>curl -I http://127.0.0.1:5678</code>. If startup fails, inspect database health, credentials, directory permissions, and logs. Do not delete volumes without a backup.</p>

<h2>6. Configure Nginx reverse proxy</h2>
<pre><code>server {
    listen 80;
    server_name automation.example.com;

    location / {
        proxy_pass http://127.0.0.1:5678;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_read_timeout 300;
        proxy_send_timeout 300;
    }
}</code></pre>
<pre><code>sudo nginx -t
sudo systemctl reload nginx</code></pre>
<p>Issue a TLS certificate with Certbot or the provider's tooling, enable HTTP-to-HTTPS redirect, and use the real domain. Long editor and execution connections require correct WebSocket headers and proxy timeouts.</p>
<p>When another CDN or load balancer sits before Nginx, set <code>N8N_PROXY_HOPS</code> to the exact number of trusted proxies. Incorrect trust can produce wrong scheme/IP detection or accept spoofed headers.</p>

<h2>7. Create the owner and protect the editor</h2>
<p>Create the owner with a unique strong password and enable two-factor authentication when supported. Avoid shared owner accounts; grant role-based access and revoke it promptly during offboarding.</p>
<p>The editor holds credentials for databases, email, cloud, and APIs. Restrict it with a VPN, identity-aware proxy, or IP allowlist when only internal staff need access. Public webhooks may require separate path or ingress policy.</p>

<h2>8. Test a webhook end to end</h2>
<ol>
<li>Create a workflow with Webhook and Respond to Webhook nodes.</li>
<li>Use the Test URL while the editor is listening.</li>
<li>Activate the workflow and switch to the Production URL.</li>
<li>Send a request from an external machine.</li>
<li>Inspect the execution, response code, and body.</li>
</ol>
<pre><code>curl -X POST https://automation.example.com/webhook/health-test \
  -H 'Content-Type: application/json' \
  -d '{"source":"external-check"}'</code></pre>
<p>Protect webhooks with secret headers, HMAC signatures, OAuth, or node-supported authentication. An obscure URL is not authentication. Add idempotency or deduplication when provider retries could repeat side effects.</p>

<h2>9. Manage credentials and the encryption key</h2>
<p>n8n encrypts credentials using its encryption key. Keep it stable through restarts, migrations, and restores. An arbitrary replacement can make existing credentials unreadable.</p>
<ul>
<li>Do not commit the key in Compose.</li>
<li>Keep a protected copy in a password or secret manager.</li>
<li>Do not export workflows with credentials or share secret screenshots.</li>
<li>Create least-privilege, rotatable integration credentials.</li>
<li>Prefer service accounts over personal accounts.</li>
</ul>

<h2>10. Control execution and binary data</h2>
<p>Large files can fill disk quickly. Limit input size, define execution retention, and monitor volumes. Do not retain binary or sensitive payloads longer than necessary.</p>
<p>Avoid placing secrets in ordinary node data because execution logs may save inputs and outputs. Use credential and secret mechanisms. For high volume or large binaries, evaluate external-storage capabilities available to the installed edition and version.</p>

<h2>11. Back up three critical components</h2>
<ol>
<li>PostgreSQL workflows, executions, and metadata.</li>
<li>The <code>n8n_data</code> instance directory.</li>
<li><code>N8N_ENCRYPTION_KEY</code> and deployment configuration.</li>
</ol>
<pre><code>cd /opt/n8n

docker compose exec -T postgres \
  pg_dump -U n8n -d n8n -Fc \
  &gt; backups/n8n-$(date +%F).dump

tar -czf backups/n8n-data-$(date +%F).tar.gz n8n_data</code></pre>
<p>Copy backups off-host, encrypt them, enforce retention, and test restoration. Files on the same VPS do not protect against disk loss or compromise.</p>

<h2>12. Update safely</h2>
<ol>
<li>Read release notes and breaking changes.</li>
<li>Back up database, data directory, and encryption key.</li>
<li>Test critical workflows on staging.</li>
<li>Change <code>N8N_VERSION</code> to the approved release.</li>
<li>Pull and recreate in a change window.</li>
<li>Verify login, webhooks, schedules, credentials, and executions.</li>
</ol>
<pre><code>docker compose pull
docker compose up -d
docker compose logs --tail=200 n8n</code></pre>
<p>Database migrations can make image rollback nontrivial. Verify downgrade guidance, preserve a pre-migration backup, and avoid uncontrolled automatic production upgrades.</p>

<h2>13. Monitor and alert</h2>
<ul>
<li>Container restarts, health, and error logs.</li>
<li>CPU, RAM, disk, inode, and volume growth.</li>
<li>PostgreSQL connections, size, slow queries, and backup age.</li>
<li>Workflow failure rate, duration, and waiting executions.</li>
<li>Webhook latency, 4xx/5xx, and certificate expiry.</li>
<li>Missed schedules and expired integration credentials.</li>
</ul>
<p>Use an external monitor against a harmless health/webhook endpoint. Send alarms through a channel that does not depend entirely on n8n itself.</p>

<h2>14. Additional hardening</h2>
<ul>
<li>Install only reviewed community nodes.</li>
<li>Restrict Code, Execute Command, and risky nodes.</li>
<li>Block unnecessary outbound access to metadata and internal services.</li>
<li>Run the n8n security audit periodically.</li>
<li>Update the host, Docker, images, and dependencies on a schedule.</li>
<li>Separate development and production credentials.</li>
<li>Apply least privilege to PostgreSQL and every integration.</li>
</ul>

<h2>Production checklist</h2>
<ol>
<li>Pin and stage-test the image version.</li>
<li>Keep PostgreSQL and 5678 private; expose the editor only through HTTPS.</li>
<li>Set correct webhook URL, timezone, and proxy hops.</li>
<li>Keep a stable encryption key outside Git and back it up.</li>
<li>Control owner access, 2FA, and user roles.</li>
<li>Authenticate, validate, and deduplicate webhooks.</li>
<li>Set execution/binary retention and monitor disk.</li>
<li>Test restoration of database, data, and secrets.</li>
<li>Maintain external monitoring and a rollback runbook.</li>
</ol>

<h2>Conclusion</h2>
<p>Production self-hosting does not end with <code>docker compose up</code>. A reliable n8n deployment needs a persistent database, protected encryption key, correct webhook URL, HTTPS reverse proxy, least-privilege credentials, and tested backups. Treat the automation platform as a production service so its workflows can be trusted.</p>

<h2>References</h2>
<ul><li><a href="https://docs.n8n.io/hosting/installation/docker/" target="_blank" rel="noopener noreferrer">n8n Docs: Docker installation</a></li><li><a href="https://docs.n8n.io/hosting/configuration/configuration-examples/webhook-url/" target="_blank" rel="noopener noreferrer">n8n Docs: Webhook URL behind a reverse proxy</a></li><li><a href="https://docs.n8n.io/hosting/securing/security-audit/" target="_blank" rel="noopener noreferrer">n8n Docs: Security audit</a></li><li><a href="https://docs.n8n.io/hosting/installation/updating/" target="_blank" rel="noopener noreferrer">n8n Docs: Updating</a></li></ul>
HTML,
        ],
    ],
];
