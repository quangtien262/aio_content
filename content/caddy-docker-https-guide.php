<?php

return [
    'caddy-docker-https-guide.html' => [
        'vi' => [
            'title' => 'Triển khai ứng dụng Docker với Caddy và HTTPS tự động',
            'slug' => 'trien-khai-docker-caddy-https-tu-dong',
            'image' => 'caddy-docker-https-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Hướng dẫn Docker, Caddy và HTTPS tự động',
            'meta_keywords' => 'Caddy Docker, HTTPS tự động, reverse proxy, Docker Compose, TLS, triển khai ứng dụng, Caddyfile',
            'meta_description' => 'Hướng dẫn đưa ứng dụng Docker lên Internet qua Caddy reverse proxy, cấp và gia hạn HTTPS tự động, cấu hình Compose, DNS, firewall, reload và xử lý lỗi.',
            'tags' => ['Caddy', 'Docker', 'HTTPS', 'Reverse Proxy', 'Docker Compose', 'DevOps', 'TLS'],
            'body' => <<<'HTML'
<p><strong>Một ứng dụng chạy tốt trong container chưa đồng nghĩa đã sẵn sàng phục vụ Internet.</strong> Bạn còn cần domain, TLS, chuyển hướng HTTP sang HTTPS, lưu chứng thư bền vững và một reverse proxy đứng trước ứng dụng.</p>
<p>Trong hướng dẫn này, Caddy nhận traffic công khai trên cổng 80/443, tự quản lý chứng thư HTTPS cho domain và chuyển request tới ứng dụng qua mạng nội bộ Docker Compose. Kết quả là chỉ Caddy lộ ra Internet; cổng ứng dụng không cần publish trực tiếp lên máy chủ.</p>

<h2>Mô hình triển khai</h2>
<pre><code>Internet
   |
   |  HTTP :80 / HTTPS :443
   v
Caddy container
   |
   |  Docker network: http://app:8080
   v
Application container</code></pre>
<p>Caddy hỗ trợ Automatic HTTPS khi site address là một hostname hợp lệ. Với domain công khai, DNS phải trỏ đúng máy chủ và cổng 80/443 phải tới được Caddy để quá trình cấp chứng thư và phục vụ traffic hoạt động.</p>

<h2>1. Điều kiện chuẩn bị</h2>
<ul>
<li>Một máy chủ Linux có Docker Engine và Docker Compose plugin.</li>
<li>Một domain hoặc subdomain bạn quản lý, ví dụ <code>app.example.com</code>.</li>
<li>Quyền chỉnh DNS và firewall/security group.</li>
<li>Ứng dụng có image container và lắng nghe HTTP trên <code>0.0.0.0:8080</code>.</li>
</ul>
<p>Kiểm tra Docker:</p>
<pre><code>docker version
docker compose version</code></pre>
<p>Ví dụ dùng Linux shell. Trên Windows hoặc macOS, cú pháp đường dẫn/quyền file có thể khác, nhưng nguyên tắc network và volume của Compose vẫn giống nhau.</p>

<h2>2. Trỏ DNS về máy chủ</h2>
<p>Tạo record <code>A</code> cho IPv4 và <code>AAAA</code> chỉ khi máy chủ thực sự phục vụ IPv6:</p>
<pre><code>app.example.com  A     203.0.113.10
app.example.com  AAAA  2001:db8::10</code></pre>
<p>Các địa chỉ trên chỉ là dải ví dụ, phải thay bằng IP thật. Sau khi DNS cập nhật, xác minh từ một mạng bên ngoài:</p>
<pre><code>dig +short app.example.com A
dig +short app.example.com AAAA</code></pre>
<p>Một record AAAA trỏ sai thường gây hiện tượng máy dùng IPv6 không truy cập được dù IPv4 hoạt động. Nếu chưa cấu hình IPv6 end-to-end, đừng tạo AAAA chỉ để “đủ bộ”.</p>

<h2>3. Mở cổng mạng cần thiết</h2>
<p>Cho phép inbound TCP 80 và TCP 443. Nếu muốn HTTP/3, cho phép thêm UDP 443. Giữ SSH giới hạn theo IP quản trị khi có thể.</p>
<pre><code># Ví dụ với UFW; kiểm tra chính sách hiện tại trước khi áp dụng
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp
sudo ufw status</code></pre>
<p>Cloud firewall/security group và firewall trong hệ điều hành là hai lớp riêng. Router/NAT cũng phải forward đúng nếu máy chủ nằm sau mạng riêng. Không tắt firewall toàn bộ để xử lý nhanh lỗi cấp chứng thư.</p>

<h2>4. Tạo cấu trúc dự án</h2>
<pre><code>mkdir -p caddy-docker/caddy
cd caddy-docker
touch compose.yaml caddy/Caddyfile .env</code></pre>
<p>Đặt domain vào <code>.env</code>:</p>
<pre><code>APP_DOMAIN=app.example.com</code></pre>
<p><code>.env</code> trong ví dụ không cần chứa bí mật, nhưng thói quen tốt là không commit file môi trường production. Nếu ứng dụng cần secret, dùng secret manager hoặc cơ chế secret phù hợp thay vì nhúng vào image hay Caddyfile.</p>

<h2>5. Viết compose.yaml</h2>
<pre><code>services:
  app:
    image: registry.example.com/my-app:&lt;approved-version&gt;
    restart: unless-stopped
    expose:
      - "8080"
    networks:
      - web

  caddy:
    image: caddy:&lt;approved-version&gt;-alpine
    restart: unless-stopped
    depends_on:
      - app
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    environment:
      APP_DOMAIN: ${APP_DOMAIN:?APP_DOMAIN is required}
    volumes:
      - ./caddy:/etc/caddy:ro
      - caddy_data:/data
      - caddy_config:/config
    networks:
      - web

networks:
  web:

volumes:
  caddy_data:
  caddy_config:</code></pre>
<p>Thay <code>&lt;approved-version&gt;</code> bằng version hoặc digest đã được kiểm thử; không để tag <code>latest</code> trôi tự do trên production. Image ứng dụng phải lắng nghe cổng 8080 bên trong container.</p>
<p><code>expose</code> mô tả cổng nội bộ nhưng không publish nó lên host. Hai service cùng network <code>web</code> có thể tìm nhau bằng service name, nên Caddy gọi <code>app:8080</code>, không gọi IP container và không dùng <code>localhost:8080</code>.</p>
<p>Volume <code>caddy_data</code> đặc biệt quan trọng vì chứa chứng thư, private key và state cần thiết. Không coi nó là cache và không xóa khi chưa hiểu tác động.</p>

<h2>6. Viết Caddyfile</h2>
<pre><code>{$APP_DOMAIN} {
    encode zstd gzip

    reverse_proxy app:8080

    log {
        output stdout
        format console
    }
}</code></pre>
<p>Chỉ cần hostname trong site block, Caddy sẽ kích hoạt Automatic HTTPS, lấy/gia hạn chứng thư và thiết lập chuyển hướng HTTP sang HTTPS. <code>reverse_proxy</code> giữ method và URI trừ khi có rewrite.</p>
<p>Không thêm <code>tls_insecure_skip_verify</code> để “sửa nhanh” upstream HTTPS. Trong mô hình một Docker network riêng, HTTP từ Caddy tới app thường đủ. Nếu traffic upstream đi qua mạng không tin cậy, cấu hình TLS/mTLS đúng cách và trust CA cụ thể.</p>

<h2>7. Kiểm tra cấu hình trước khi chạy</h2>
<p>Compose có thể render cấu hình cuối cùng và phát hiện biến thiếu:</p>
<pre><code>docker compose config</code></pre>
<p>Kiểm tra Caddyfile bằng chính image đã chọn:</p>
<pre><code>docker compose run --rm --no-deps caddy \
  caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile</code></pre>
<p>Nếu dùng ACME staging trong giai đoạn thử nghiệm, nhớ bỏ endpoint staging trước khi phục vụ thật; chứng thư staging không được trình duyệt tin cậy. Tránh lặp đi lặp lại thao tác xin chứng thư production khi DNS/firewall còn sai vì CA có rate limit.</p>

<h2>8. Khởi động và theo dõi log</h2>
<pre><code>docker compose up -d
docker compose ps
docker compose logs --tail=100 caddy
docker compose logs --tail=100 app</code></pre>
<p>Trong log Caddy, tìm thông tin lấy chứng thư hoặc lỗi challenge. Trong log app, xác nhận service đã lắng nghe ở <code>0.0.0.0:8080</code>, không chỉ <code>127.0.0.1</code> bên trong container.</p>
<p><code>depends_on</code> chỉ sắp xếp thứ tự khởi động cơ bản, không chứng minh ứng dụng đã sẵn sàng. Ở hệ thống quan trọng, thêm healthcheck cho app và thiết kế app khởi động/reconnect an toàn. Khi upstream tạm chưa sẵn sàng, Caddy có thể trả 502 rồi hoạt động lại khi app lên.</p>

<h2>9. Xác minh từ bên ngoài</h2>
<pre><code>curl -I http://app.example.com
curl -I https://app.example.com
curl -v https://app.example.com/health</code></pre>
<p>Kỳ vọng request HTTP được redirect sang HTTPS, chứng thư khớp hostname và endpoint ứng dụng trả đúng status. Kiểm tra thêm bằng trình duyệt trên một thiết bị/mạng khác để tránh kết quả bị ảnh hưởng bởi DNS cache hoặc file hosts cục bộ.</p>
<p>Không dùng <code>curl -k</code> trong bước xác minh production; tùy chọn đó bỏ qua lỗi chứng thư mà bạn đang cần phát hiện.</p>

<h2>10. Reload cấu hình không dừng container</h2>
<p>Sau khi sửa Caddyfile, validate rồi graceful reload:</p>
<pre><code>docker compose exec -w /etc/caddy caddy \
  caddy validate --config Caddyfile --adapter caddyfile

docker compose exec -w /etc/caddy caddy caddy reload</code></pre>
<p>Mount cả thư mục <code>./caddy</code> vào <code>/etc/caddy</code> giúp tránh một số editor thay inode khiến bind mount một file đơn không thấy nội dung mới. Reload tốt hơn restart vì Caddy có thể áp dụng config mà không chủ động làm gián đoạn kết nối đang phục vụ.</p>

<h2>11. Cập nhật image có kiểm soát</h2>
<ol>
<li>Đọc release notes và chọn version/digest mới trong môi trường test.</li>
<li>Pull image, validate config và chạy smoke test.</li>
<li>Cập nhật production theo cửa sổ thay đổi.</li>
<li>Xác minh HTTPS, health endpoint và log sau cập nhật.</li>
</ol>
<pre><code>docker compose pull
docker compose up -d
docker compose ps
docker compose logs --since=10m caddy</code></pre>
<p><code>docker compose down</code> không xóa named volume mặc định, nhưng <code>down -v</code> sẽ xóa volume. Không dùng <code>-v</code> trên production nếu chưa có chủ đích và backup.</p>

<h2>12. Sao lưu những gì?</h2>
<ul>
<li><code>compose.yaml</code>, Caddyfile và tài liệu DNS/firewall.</li>
<li>Dữ liệu ứng dụng/database theo quy trình backup riêng.</li>
<li>Volume <code>caddy_data</code> nếu muốn giữ state/certificates khi di chuyển máy.</li>
</ul>
<p>Private key nằm trong dữ liệu Caddy, vì vậy backup phải được mã hóa và giới hạn quyền. Nếu mất volume, Caddy thường có thể xin chứng thư lại khi DNS/cổng đúng, nhưng việc xin lại hàng loạt có thể gặp rate limit và không thay thế chiến lược backup.</p>

<h2>13. Xử lý lỗi thường gặp</h2>
<h3>Không lấy được chứng thư</h3>
<ul>
<li>DNS chưa trỏ đúng IP hoặc còn record AAAA sai.</li>
<li>Cổng 80/443 bị chặn bởi cloud firewall, UFW hoặc router.</li>
<li>Một service khác đang chiếm cổng.</li>
<li>Proxy/CDN phía trước cấu hình chế độ TLS/DNS chưa phù hợp.</li>
</ul>
<pre><code>docker compose logs caddy
sudo ss -lntup | grep -E ':(80|443)\b'</code></pre>

<h3>HTTPS hoạt động nhưng trả 502</h3>
<ul>
<li>App chưa sẵn sàng hoặc crash loop.</li>
<li>App chỉ bind <code>127.0.0.1</code> trong container.</li>
<li>Sai service name/cổng trong <code>reverse_proxy</code>.</li>
<li>Caddy và app không cùng Docker network.</li>
</ul>
<pre><code>docker compose ps
docker compose logs app
docker compose exec caddy wget -qO- http://app:8080/health</code></pre>
<p>Image Caddy tối giản có thể không chứa mọi công cụ debug. Nếu lệnh kiểm tra không tồn tại, dùng một container chẩn đoán tạm thời gắn cùng network thay vì cài công cụ vào container production đang chạy.</p>

<h3>Domain đúng nhưng app redirect loop</h3>
<p>Ứng dụng có thể chưa tin proxy headers hoặc tự ép HTTPS sai cách. Caddy gửi các header forward phổ biến; cấu hình framework chỉ tin proxy thực tế, không tin mọi địa chỉ Internet. Tránh chạy đồng thời nhiều lớp redirect mâu thuẫn.</p>

<h2>14. Checklist hardening</h2>
<ul>
<li>Chỉ publish 80/443 của Caddy; database, cache và app ở network nội bộ.</li>
<li>Pin image version/digest và có lịch cập nhật.</li>
<li>Không lưu secret trong image, Git hoặc Caddyfile.</li>
<li>Giữ <code>caddy_data</code> bền vững, backup được bảo vệ.</li>
<li>Giới hạn SSH và theo dõi log, disk, certificate/endpoint health.</li>
<li>Đặt upload/body limit và timeout phù hợp tại app hoặc proxy khi cần.</li>
<li>Chỉ thêm security header sau khi hiểu ứng dụng; HSTS sai có thể gây tác động dài hạn.</li>
<li>Không tắt TLS verification để che lỗi trust của upstream.</li>
</ul>

<h2>Kết luận</h2>
<p>Caddy giúp rút ngắn phần TLS của một triển khai Docker, nhưng vận hành an toàn vẫn phụ thuộc vào DNS đúng, cổng mạng rõ ràng, volume bền vững, service discovery bằng tên và quy trình validate/reload. Với cấu trúc trên, ứng dụng được cô lập sau reverse proxy, HTTPS được tự động quản lý và cấu hình đủ đơn giản để kiểm tra, backup và nâng cấp có kiểm soát.</p>

<h2>Tài liệu tham khảo</h2>
<ul>
<li><a href="https://caddyserver.com/docs/automatic-https" target="_blank" rel="noopener noreferrer">Caddy: Automatic HTTPS</a></li>
<li><a href="https://caddyserver.com/docs/caddyfile/directives/reverse_proxy" target="_blank" rel="noopener noreferrer">Caddy: reverse_proxy</a></li>
<li><a href="https://hub.docker.com/_/caddy" target="_blank" rel="noopener noreferrer">Docker Official Image: Caddy</a></li>
<li><a href="https://docs.docker.com/compose/how-tos/networking/" target="_blank" rel="noopener noreferrer">Docker Compose networking</a></li>
</ul>
HTML,
        ],
        'en' => [
            'title' => 'Deploy a Docker Application with Caddy and Automatic HTTPS',
            'slug' => 'deploy-docker-application-caddy-automatic-https',
            'image' => 'caddy-docker-https-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Docker, Caddy, and Automatic HTTPS Guide',
            'meta_keywords' => 'Caddy Docker, automatic HTTPS, reverse proxy, Docker Compose, TLS, application deployment, Caddyfile',
            'meta_description' => 'Deploy a Docker application behind Caddy: automatic HTTPS, Compose networking, DNS and firewall setup, persistent certificates, validation, reloads, and troubleshooting.',
            'tags' => ['Caddy', 'Docker', 'HTTPS', 'Reverse Proxy', 'Docker Compose', 'DevOps', 'TLS'],
            'body' => <<<'HTML'
<p><strong>An application working inside a container is not automatically ready for the public Internet.</strong> It still needs a domain, TLS, HTTP-to-HTTPS redirects, persistent certificate storage, and a reverse proxy in front.</p>
<p>In this guide, Caddy accepts public traffic on ports 80 and 443, automatically manages HTTPS certificates for a domain, and proxies requests to an application across an internal Docker Compose network. Only Caddy is exposed; the application port does not need to be published on the host.</p>

<h2>Deployment model</h2>
<pre><code>Internet
   |
   |  HTTP :80 / HTTPS :443
   v
Caddy container
   |
   |  Docker network: http://app:8080
   v
Application container</code></pre>
<p>Caddy enables Automatic HTTPS when a site address contains a valid hostname. For a public domain, DNS must point to the server and ports 80 and 443 must reach Caddy so certificate issuance and traffic serving can work.</p>

<h2>1. Prerequisites</h2>
<ul>
<li>A Linux server with Docker Engine and the Docker Compose plugin.</li>
<li>A domain or subdomain you control, such as <code>app.example.com</code>.</li>
<li>Permission to change DNS and firewall/security-group rules.</li>
<li>A container image whose HTTP server listens on <code>0.0.0.0:8080</code>.</li>
</ul>
<pre><code>docker version
docker compose version</code></pre>
<p>The commands use a Linux shell. Path and file-permission syntax differs on Windows and macOS, but Compose networking and volume principles remain the same.</p>

<h2>2. Point DNS to the server</h2>
<p>Create an <code>A</code> record for IPv4 and an <code>AAAA</code> record only when the server genuinely serves IPv6:</p>
<pre><code>app.example.com  A     203.0.113.10
app.example.com  AAAA  2001:db8::10</code></pre>
<p>These are documentation-only addresses; replace them with the real server IP. Verify propagation from an external network:</p>
<pre><code>dig +short app.example.com A
dig +short app.example.com AAAA</code></pre>
<p>An incorrect AAAA record often makes the site fail only for IPv6-capable users. If IPv6 is not configured end to end, do not add AAAA merely for completeness.</p>

<h2>3. Open the required network ports</h2>
<p>Allow inbound TCP 80 and TCP 443. Add UDP 443 if you want HTTP/3. Restrict SSH to administration addresses where possible.</p>
<pre><code># UFW example; inspect the current policy before applying it
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp
sudo ufw status</code></pre>
<p>Cloud security groups and the operating-system firewall are separate layers. A router must also forward these ports if the server sits behind NAT. Do not disable the entire firewall as a shortcut for certificate troubleshooting.</p>

<h2>4. Create the project structure</h2>
<pre><code>mkdir -p caddy-docker/caddy
cd caddy-docker
touch compose.yaml caddy/Caddyfile .env</code></pre>
<p>Put the domain in <code>.env</code>:</p>
<pre><code>APP_DOMAIN=app.example.com</code></pre>
<p>This example value is not secret, but production environment files should generally remain outside version control. Use a secret manager or an appropriate secrets mechanism for application credentials rather than baking them into an image or Caddyfile.</p>

<h2>5. Write compose.yaml</h2>
<pre><code>services:
  app:
    image: registry.example.com/my-app:&lt;approved-version&gt;
    restart: unless-stopped
    expose:
      - "8080"
    networks:
      - web

  caddy:
    image: caddy:&lt;approved-version&gt;-alpine
    restart: unless-stopped
    depends_on:
      - app
    ports:
      - "80:80"
      - "443:443"
      - "443:443/udp"
    environment:
      APP_DOMAIN: ${APP_DOMAIN:?APP_DOMAIN is required}
    volumes:
      - ./caddy:/etc/caddy:ro
      - caddy_data:/data
      - caddy_config:/config
    networks:
      - web

networks:
  web:

volumes:
  caddy_data:
  caddy_config:</code></pre>
<p>Replace <code>&lt;approved-version&gt;</code> with a tested version or digest; do not let a floating <code>latest</code> tag control production changes. The application image must listen on container port 8080.</p>
<p><code>expose</code> documents the internal port without publishing it on the host. Services sharing the <code>web</code> network discover one another by service name, so Caddy connects to <code>app:8080</code>, not a container IP or <code>localhost:8080</code>.</p>
<p>The <code>caddy_data</code> volume is especially important because it contains certificates, private keys, and operational state. It is not a disposable cache.</p>

<h2>6. Write the Caddyfile</h2>
<pre><code>{$APP_DOMAIN} {
    encode zstd gzip

    reverse_proxy app:8080

    log {
        output stdout
        format console
    }
}</code></pre>
<p>A hostname in the site block activates Automatic HTTPS. Caddy obtains and renews certificates and configures HTTP-to-HTTPS redirects. <code>reverse_proxy</code> preserves the method and URI unless a rewrite changes them.</p>
<p>Do not add <code>tls_insecure_skip_verify</code> as a quick fix for an HTTPS upstream. Plain HTTP from Caddy to the app is commonly appropriate on a private Docker network. If upstream traffic crosses an untrusted network, configure TLS or mTLS with an explicit trust chain.</p>

<h2>7. Validate before starting</h2>
<p>Render the final Compose model and catch missing variables:</p>
<pre><code>docker compose config</code></pre>
<p>Validate the Caddyfile with the selected image:</p>
<pre><code>docker compose run --rm --no-deps caddy \
  caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile</code></pre>
<p>If you use an ACME staging endpoint during testing, remove it before going live because browsers do not trust staging certificates. Avoid repeatedly requesting production certificates while DNS or firewall rules are wrong; certificate authorities enforce rate limits.</p>

<h2>8. Start the stack and watch logs</h2>
<pre><code>docker compose up -d
docker compose ps
docker compose logs --tail=100 caddy
docker compose logs --tail=100 app</code></pre>
<p>Look for certificate issuance or challenge errors in Caddy logs. Confirm in application logs that the server listens on <code>0.0.0.0:8080</code>, not only <code>127.0.0.1</code> inside the container.</p>
<p><code>depends_on</code> provides basic startup ordering; it does not prove readiness. Add an application healthcheck for important deployments and make startup and reconnection resilient. Caddy may return 502 while the upstream is unavailable and recover once the app starts.</p>

<h2>9. Verify from outside</h2>
<pre><code>curl -I http://app.example.com
curl -I https://app.example.com
curl -v https://app.example.com/health</code></pre>
<p>Expect HTTP to redirect to HTTPS, the certificate to match the hostname, and the application endpoint to return its intended status. Also test from a browser on another device or network to avoid local DNS cache or hosts-file effects.</p>
<p>Do not use <code>curl -k</code> for production verification. It suppresses the certificate failures this step is meant to detect.</p>

<h2>10. Reload configuration without stopping the container</h2>
<p>After editing the Caddyfile, validate it and perform a graceful reload:</p>
<pre><code>docker compose exec -w /etc/caddy caddy \
  caddy validate --config Caddyfile --adapter caddyfile

docker compose exec -w /etc/caddy caddy caddy reload</code></pre>
<p>Mounting the <code>./caddy</code> directory to <code>/etc/caddy</code> avoids problems with editors that replace a single file's inode and leave a file bind mount seeing old content. Reloading is preferable to restarting because Caddy can apply configuration without intentionally interrupting active traffic.</p>

<h2>11. Update images deliberately</h2>
<ol>
<li>Read release notes and test a chosen version or digest.</li>
<li>Pull images, validate configuration, and run smoke tests.</li>
<li>Update production during a controlled change window.</li>
<li>Verify HTTPS, health endpoints, and logs afterward.</li>
</ol>
<pre><code>docker compose pull
docker compose up -d
docker compose ps
docker compose logs --since=10m caddy</code></pre>
<p><code>docker compose down</code> keeps named volumes by default, but <code>down -v</code> deletes them. Do not use <code>-v</code> in production without an explicit plan and backup.</p>

<h2>12. What should be backed up?</h2>
<ul>
<li><code>compose.yaml</code>, the Caddyfile, and DNS/firewall documentation.</li>
<li>Application and database data through their own backup procedures.</li>
<li>The <code>caddy_data</code> volume when preserving state and certificates during migration matters.</li>
</ul>
<p>Caddy data contains private keys, so backups must be encrypted and access-controlled. Caddy can often obtain certificates again when DNS and ports remain correct, but bulk reissuance may encounter rate limits and is not a backup strategy.</p>

<h2>13. Troubleshoot common failures</h2>
<h3>Certificate issuance fails</h3>
<ul>
<li>DNS points to the wrong address or an incorrect AAAA record remains.</li>
<li>Cloud firewall, UFW, or the router blocks ports 80/443.</li>
<li>Another service already owns a port.</li>
<li>An upstream proxy or CDN has an incompatible DNS/TLS mode.</li>
</ul>
<pre><code>docker compose logs caddy
sudo ss -lntup | grep -E ':(80|443)\b'</code></pre>

<h3>HTTPS works but returns 502</h3>
<ul>
<li>The app is not ready or is crash-looping.</li>
<li>The app binds only to <code>127.0.0.1</code> in its container.</li>
<li>The <code>reverse_proxy</code> service name or port is wrong.</li>
<li>Caddy and the app do not share a Docker network.</li>
</ul>
<pre><code>docker compose ps
docker compose logs app
docker compose exec caddy wget -qO- http://app:8080/health</code></pre>
<p>Minimal Caddy images may omit debugging tools. If a diagnostic command is unavailable, attach a temporary troubleshooting container to the same network rather than installing tools inside a running production container.</p>

<h3>The domain works but the application redirects forever</h3>
<p>The framework may not trust proxy headers or may enforce HTTPS incorrectly. Caddy sets common forwarding headers; configure the framework to trust only the actual proxy, not every Internet address. Avoid conflicting redirect logic across several layers.</p>

<h2>14. Hardening checklist</h2>
<ul>
<li>Publish only Caddy's 80/443 ports; keep apps, databases, and caches internal.</li>
<li>Pin image versions or digests and maintain an update schedule.</li>
<li>Store no secrets in images, Git, or the Caddyfile.</li>
<li>Persist <code>caddy_data</code> and protect its backups.</li>
<li>Restrict SSH and monitor logs, disk, certificates, and endpoint health.</li>
<li>Apply upload/body limits and timeouts where the application requires them.</li>
<li>Add security headers only after understanding the application; incorrect HSTS has long-lived effects.</li>
<li>Never disable TLS verification to hide an upstream trust failure.</li>
</ul>

<h2>Conclusion</h2>
<p>Caddy simplifies the TLS portion of a Docker deployment, but safe operations still depend on correct DNS, explicit network access, persistent volumes, name-based service discovery, and validate/reload procedures. With this structure, the application stays isolated behind a reverse proxy, HTTPS is managed automatically, and the configuration remains small enough to inspect, back up, and upgrade deliberately.</p>

<h2>References</h2>
<ul>
<li><a href="https://caddyserver.com/docs/automatic-https" target="_blank" rel="noopener noreferrer">Caddy: Automatic HTTPS</a></li>
<li><a href="https://caddyserver.com/docs/caddyfile/directives/reverse_proxy" target="_blank" rel="noopener noreferrer">Caddy: reverse_proxy</a></li>
<li><a href="https://hub.docker.com/_/caddy" target="_blank" rel="noopener noreferrer">Docker Official Image: Caddy</a></li>
<li><a href="https://docs.docker.com/compose/how-tos/networking/" target="_blank" rel="noopener noreferrer">Docker Compose networking</a></li>
</ul>
HTML,
        ],
    ],
];
