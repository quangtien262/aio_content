<?php

return [
    'cloudflare-dns-https-guide.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cấu hình Cloudflare DNS và HTTPS an toàn cho website',
            'slug' => 'huong-dan-cau-hinh-cloudflare-dns-https-an-toan',
            'image' => 'cloudflare-dns-https-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Cấu hình Cloudflare DNS và HTTPS an toàn',
            'meta_keywords' => 'Cloudflare DNS, HTTPS, Full strict, DNSSEC, Origin CA, bảo mật website, cấu hình SSL',
            'meta_description' => 'Hướng dẫn chuyển DNS sang Cloudflare, bật HTTPS Full (strict), DNSSEC, bảo vệ máy chủ gốc và xử lý lỗi SSL, email thường gặp.',
            'tags' => ['Cloudflare', 'DNS', 'HTTPS', 'TLS', 'DNSSEC', 'Origin Server', 'Website Security', 'DevOps'],
            'body' => <<<'HTML'
<p><strong>Cloudflare có thể cải thiện HTTPS, hiệu năng và khả năng chống tấn công cho website, nhưng một lần chuyển DNS vội vàng cũng có thể làm gián đoạn web, email hoặc API.</strong> Hướng dẫn này đi theo thứ tự an toàn: kiểm kê DNS, chuẩn bị chứng chỉ tại máy chủ gốc, chuyển nameserver, bật proxy, chọn Full (strict), kích hoạt DNSSEC và kiểm thử trước khi siết firewall.</p>

<h2>Cloudflare nằm ở đâu trong kiến trúc?</h2>
<p>Khi bản ghi web bật proxy, trình duyệt kết nối TLS tới Cloudflare; Cloudflare tạo một kết nối riêng tới máy chủ gốc. Vì có hai chặng, biểu tượng ổ khóa trên trình duyệt không tự chứng minh chặng Cloudflare–origin đã được mã hóa và xác thực đúng. Chế độ SSL/TLS quyết định chặng thứ hai này.</p>
<blockquote>Mục tiêu nên là HTTPS hợp lệ từ đầu đến cuối. Không dùng Flexible như giải pháp lâu dài vì lưu lượng từ Cloudflare tới origin không được mã hóa và cấu hình redirect rất dễ tạo vòng lặp.</blockquote>

<h2>1. Chuẩn bị trước khi đổi nameserver</h2>
<p>Đăng nhập được cả nhà đăng ký tên miền, hệ thống DNS hiện tại và máy chủ web. Xuất zone DNS hoặc chụp lại toàn bộ bản ghi. Ghi rõ IP origin, nhà cung cấp email, các subdomain, dịch vụ xác minh, API và webhook đang sử dụng.</p>
<ul><li>Kiểm tra website hoạt động trực tiếp tại origin và cổng 443 đang mở.</li><li>Đảm bảo origin có chứng chỉ TLS hợp lệ cho đúng hostname.</li><li>Ghi lại A, AAAA, CNAME, MX, TXT, CAA và SRV; chú ý SPF, DKIM, DMARC.</li><li>Giảm TTL trước khi chuyển nếu hệ thống DNS hiện tại cho phép.</li><li>Chuẩn bị cửa sổ theo dõi và cách quay lại cấu hình cũ.</li></ul>
<p>Không xóa zone cũ ngay sau khi thay nameserver. Giữ nó trong vài ngày để có dữ liệu đối chiếu trong thời gian cập nhật DNS.</p>

<h2>2. Thêm domain và rà soát bản ghi</h2>
<p>Thêm domain vào Cloudflare, chọn gói phù hợp và chờ hệ thống quét bản ghi. Kết quả quét chỉ là điểm khởi đầu; hãy so sánh từng dòng với zone gốc. Tạo lại những bản ghi bị thiếu trước khi đổi nameserver.</p>
<p>Với bản ghi A, AAAA hoặc CNAME phục vụ website, biểu tượng đám mây màu cam nghĩa là lưu lượng đi qua Cloudflare. DNS only chỉ trả địa chỉ đích và người dùng kết nối trực tiếp. MX và TXT không được proxy; máy chủ mail cũng nên để DNS only.</p>
<ul><li><strong>Nên proxy:</strong> website và subdomain HTTP/HTTPS muốn dùng CDN, WAF hoặc che IP origin.</li><li><strong>Nên để DNS only:</strong> mail, SSH, FTP, cổng không được hỗ trợ và dịch vụ cần kết nối trực tiếp.</li><li><strong>Cần cân nhắc:</strong> API, webhook và WebSocket; proxy được nếu giao thức, giới hạn tải lên và timeout phù hợp.</li></ul>

<h2>3. Đổi nameserver tại nhà đăng ký</h2>
<p>Cloudflare cung cấp hai nameserver. Thay nameserver hiện tại tại registrar bằng đúng hai giá trị đó, không thêm IP và không tạo chúng như bản ghi NS trong zone cũ. Chờ trạng thái zone chuyển sang Active rồi kiểm tra từ nhiều mạng.</p>
<pre><code>dig NS example.com
dig A example.com
dig MX example.com
dig TXT example.com</code></pre>
<p>Trên Windows có thể dùng <code>nslookup -type=ns example.com</code>. DNS có thể được cache theo TTL nên kết quả chưa đồng nhất ngay lập tức không nhất thiết là lỗi. Tuy nhiên, nếu web hoặc email mất bản ghi, hãy bổ sung tại Cloudflare thay vì chờ.</p>

<h2>4. Cài chứng chỉ TLS tại máy chủ gốc</h2>
<p>Ưu tiên chứng chỉ công khai như Let’s Encrypt nếu origin đôi lúc cần truy cập trực tiếp. Cloudflare Origin CA cũng phù hợp khi origin chỉ nhận lưu lượng qua proxy Cloudflare, nhưng trình duyệt không tin cậy chứng chỉ này nếu tắt proxy hoặc truy cập thẳng origin.</p>
<p>Chứng chỉ phải chưa hết hạn, bao phủ đúng hostname và đi kèm private key tương ứng. Cấu hình virtual host để trả đúng chứng chỉ bằng SNI. Với Origin CA, tự theo dõi ngày hết hạn vì Cloudflare hiện không gửi thông báo hết hạn cho loại chứng chỉ này.</p>
<pre><code>openssl s_client -connect 203.0.113.10:443 -servername example.com</code></pre>
<p>Lệnh trên giúp xem chuỗi chứng chỉ tại origin. Không đưa private key, Global API Key hoặc API token vào source code; dùng secret manager và token có quyền tối thiểu.</p>

<h2>5. Chọn Full (strict), tránh Flexible</h2>
<p>Trong SSL/TLS, chọn <strong>Full (strict)</strong> khi origin đã có chứng chỉ hợp lệ. Chế độ này mã hóa cả hai chặng và xác minh chứng chỉ origin còn hạn, đúng hostname, được cấp bởi CA công khai hoặc Cloudflare Origin CA. Đây là lựa chọn nên dùng cho production.</p>
<ul><li><strong>Flexible:</strong> Cloudflare dùng HTTP tới origin; không phù hợp với dữ liệu nhạy cảm và dễ gây redirect loop.</li><li><strong>Full:</strong> mã hóa tới origin nhưng không xác minh tính hợp lệ của chứng chỉ origin.</li><li><strong>Full (strict):</strong> mã hóa và xác minh origin; lỗi xác minh thường trả mã 526.</li></ul>
<p>Sau khi strict hoạt động, bật chuyển hướng HTTP sang HTTPS tại một nơi có chủ đích. Nếu ứng dụng đứng sau reverse proxy, cấu hình trusted proxy để ứng dụng hiểu header giao thức và không tự redirect vô hạn. Sửa mixed content bằng URL HTTPS thay vì tắt kiểm tra trình duyệt.</p>

<h2>6. Bật DNSSEC đúng trình tự</h2>
<p>DNSSEC giúp resolver phát hiện câu trả lời DNS bị giả mạo. Bật DNSSEC tại Cloudflare, lấy thông tin DS rồi thêm chính xác DS tại registrar. Chờ trạng thái xác nhận hoạt động và kiểm tra bằng công cụ DNSSEC.</p>
<blockquote>Không để bản ghi DS cũ khi đổi nhà cung cấp DNS. DS không khớp có thể khiến domain không phân giải được với các resolver xác thực.</blockquote>
<p>Nếu sau này chuyển nameserver khỏi Cloudflare, hãy thực hiện quy trình chuyển DNSSEC của hai nhà cung cấp hoặc xóa DS đúng thời điểm, thay vì chỉ đổi nameserver.</p>

<h2>7. Bảo vệ origin sau khi kiểm thử</h2>
<p>Proxy không che được origin nếu IP vẫn xuất hiện trong lịch sử DNS, subdomain DNS only hoặc máy chủ mail dùng chung IP. Tách mail khỏi web nếu có thể, rà soát bản ghi cũ và không công khai IP trong nội dung ứng dụng.</p>
<p>Sau khi xác nhận web ổn định, có thể giới hạn cổng web tại firewall chỉ nhận dải IP Cloudflare. Cập nhật danh sách này tự động từ nguồn chính thức và giữ đường quản trị riêng để tránh tự khóa mình. Authenticated Origin Pulls bổ sung xác thực rằng kết nối tới origin đến từ Cloudflare; Cloudflare Tunnel là lựa chọn khác khi muốn kết nối outbound và không mở cổng web công khai.</p>
<p>Chỉ siết firewall sau khi kiểm tra IPv4, IPv6, health check, cron, webhook và dịch vụ giám sát. Nếu dùng Tunnel thì không cần Authenticated Origin Pulls cho luồng Tunnel.</p>

<h2>8. Cấu hình cache và bảo mật có kiểm soát</h2>
<p>Cache asset tĩnh như ảnh, CSS và JavaScript trước. Không cache tùy tiện trang đăng nhập, giỏ hàng, dashboard, nội dung cá nhân hóa hoặc API ghi dữ liệu. Để ứng dụng gửi <code>Cache-Control</code> rõ ràng và purge cache sau khi triển khai nội dung cần cập nhật ngay.</p>
<p>Có thể đặt Minimum TLS Version, WAF và rate limiting tùy gói. Chỉ bật HSTS khi toàn bộ website và subdomain cần áp dụng đã chạy HTTPS ổn định; cấu hình <code>includeSubDomains</code> hoặc preload sai có thể khiến subdomain HTTP không thể truy cập trong thời gian dài.</p>

<h2>9. Kiểm tra web, email và luồng nghiệp vụ</h2>
<pre><code>curl -I http://example.com
curl -I https://example.com
curl --resolve example.com:443:203.0.113.10 https://example.com/ -I</code></pre>
<p>Kiểm tra HTTP chuyển đúng một lần sang HTTPS, hostname chuẩn không tạo chuỗi redirect, chứng chỉ đúng tên miền và nội dung không có mixed content. Sau đó thử đăng nhập, biểu mẫu, upload, thanh toán, API, webhook, WebSocket và các tác vụ nền.</p>
<p>Gửi và nhận email ở cả trong lẫn ngoài tổ chức. Xác nhận MX trỏ đúng, hostname mail không bật proxy và SPF chỉ có một bản ghi hợp lệ; kiểm tra DKIM và DMARC vẫn còn nguyên sau khi chuyển zone.</p>

<h2>10. Chẩn đoán lỗi thường gặp</h2>
<ul><li><strong>521 Web server is down:</strong> origin từ chối kết nối, dịch vụ web dừng hoặc firewall chặn Cloudflare.</li><li><strong>522 Connection timed out:</strong> Cloudflare không nhận phản hồi kịp thời; kiểm tra route, tải máy chủ và firewall.</li><li><strong>525 SSL handshake failed:</strong> bắt tay TLS với origin thất bại; kiểm tra giao thức, cipher, SNI và chứng chỉ.</li><li><strong>526 Invalid SSL certificate:</strong> Full (strict) không xác minh được chứng chỉ origin; kiểm tra hạn, hostname và chuỗi CA.</li><li><strong>Redirect loop:</strong> thường do Flexible kết hợp redirect HTTPS tại origin hoặc ứng dụng không tin proxy header.</li><li><strong>Email ngừng hoạt động:</strong> thường do thiếu MX/TXT, proxy nhầm hostname mail hoặc SPF/DKIM sai.</li></ul>

<h2>Checklist trước khi kết thúc</h2>
<ol><li>Cloudflare báo zone Active và nameserver đúng.</li><li>Tất cả bản ghi web, mail, xác minh và dịch vụ phụ đã được đối chiếu.</li><li>Origin có chứng chỉ hợp lệ, SSL/TLS đặt Full (strict).</li><li>HTTP chuyển sang HTTPS không có vòng lặp hoặc mixed content.</li><li>DNSSEC hoạt động và DS khớp.</li><li>Email gửi, nhận, SPF, DKIM và DMARC đều đạt.</li><li>Luồng đăng nhập, biểu mẫu, API, webhook và upload đã được thử.</li><li>Cache không lưu nội dung riêng tư; firewall chưa chặn nhầm dịch vụ.</li><li>Chứng chỉ, lỗi 5xx và ngày hết hạn được giám sát.</li></ol>

<h2>Kết luận</h2>
<p>Cấu hình Cloudflare an toàn không bắt đầu bằng việc bật thật nhiều tính năng. Trình tự đúng là bảo toàn DNS, chuẩn bị HTTPS tại origin, chuyển nameserver, xác minh dịch vụ, bật Full (strict) và DNSSEC, rồi mới giới hạn truy cập origin. Triển khai theo từng bước có điểm kiểm tra giúp website nhận được lợi ích của CDN và lớp bảo vệ biên mà không đánh đổi tính sẵn sàng.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/" target="_blank" rel="noopener noreferrer">Cloudflare: SSL/TLS encryption modes</a></li><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/" target="_blank" rel="noopener noreferrer">Cloudflare: Full (strict)</a></li><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/origin-ca/" target="_blank" rel="noopener noreferrer">Cloudflare: Origin CA certificates</a></li><li><a href="https://developers.cloudflare.com/dns/proxy-status/" target="_blank" rel="noopener noreferrer">Cloudflare: Proxy status</a></li><li><a href="https://developers.cloudflare.com/dns/dnssec/" target="_blank" rel="noopener noreferrer">Cloudflare: DNSSEC</a></li><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/authenticated-origin-pull/" target="_blank" rel="noopener noreferrer">Cloudflare: Authenticated Origin Pulls</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Configure Cloudflare DNS and HTTPS Securely',
            'slug' => 'configure-cloudflare-dns-https-securely',
            'image' => 'cloudflare-dns-https-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Configure Cloudflare DNS and HTTPS Securely',
            'meta_keywords' => 'Cloudflare DNS, HTTPS, Full strict, DNSSEC, Origin CA, website security, SSL configuration',
            'meta_description' => 'Move DNS to Cloudflare, enable Full (strict) HTTPS and DNSSEC, protect the origin, preserve email, and troubleshoot common SSL errors.',
            'tags' => ['Cloudflare', 'DNS', 'HTTPS', 'TLS', 'DNSSEC', 'Origin Server', 'Website Security', 'DevOps'],
            'body' => <<<'HTML'
<p><strong>Cloudflare can improve HTTPS, performance, and attack resistance, but a rushed DNS migration can interrupt the website, email, or APIs.</strong> This guide uses a safer sequence: inventory DNS, prepare the origin certificate, change nameservers, enable proxying, select Full (strict), activate DNSSEC, and validate services before tightening the firewall.</p>

<h2>Where Cloudflare sits in the architecture</h2>
<p>For a proxied web record, the browser establishes TLS with Cloudflare and Cloudflare creates a separate connection to the origin. Because there are two legs, a browser padlock alone does not prove that the Cloudflare-to-origin connection is encrypted and correctly authenticated. The SSL/TLS mode controls that second leg.</p>
<blockquote>The goal is valid end-to-end HTTPS. Do not use Flexible as a long-term fix: traffic to the origin remains unencrypted and redirect configurations commonly create loops.</blockquote>

<h2>1. Prepare before changing nameservers</h2>
<p>Confirm access to the domain registrar, current DNS provider, and web server. Export the zone or capture every existing record. Document the origin IP, email provider, subdomains, verification records, APIs, and webhooks.</p>
<ul><li>Test the site directly at the origin and confirm port 443 is open.</li><li>Install a valid origin TLS certificate for the correct hostnames.</li><li>Record A, AAAA, CNAME, MX, TXT, CAA, and SRV entries, especially SPF, DKIM, and DMARC.</li><li>Lower TTL in advance when the current provider allows it.</li><li>Define a monitoring window and rollback path.</li></ul>
<p>Do not immediately delete the old DNS zone. Retain it for several days as a reference while caches and delegation update.</p>

<h2>2. Add the domain and review every record</h2>
<p>Add the domain to Cloudflare, choose the appropriate plan, and let it scan records. Treat the scan as a starting point and compare every entry with the original zone. Recreate anything missing before changing nameservers.</p>
<p>For website A, AAAA, and CNAME records, an orange cloud means traffic passes through Cloudflare. DNS only exposes the destination and clients connect directly. MX and TXT records are not proxied, and mail-server hostnames should remain DNS only.</p>
<ul><li><strong>Usually proxy:</strong> HTTP/HTTPS sites that need CDN, WAF, or origin-IP protection.</li><li><strong>Usually DNS only:</strong> mail, SSH, FTP, unsupported ports, and services requiring direct connections.</li><li><strong>Evaluate first:</strong> APIs, webhooks, and WebSockets; proxy them only when protocol, upload, and timeout limits fit.</li></ul>

<h2>3. Change nameservers at the registrar</h2>
<p>Cloudflare assigns two nameservers. Replace the registrar's current nameservers with those exact values. Do not add IP addresses or create them merely as NS records in the old zone. Wait for the zone to become Active, then query it from multiple networks.</p>
<pre><code>dig NS example.com
dig A example.com
dig MX example.com
dig TXT example.com</code></pre>
<p>On Windows, use <code>nslookup -type=ns example.com</code>. Cached answers can differ until their TTL expires. A missing web or email record, however, must be corrected in Cloudflare rather than solved by waiting.</p>

<h2>4. Install TLS at the origin</h2>
<p>Prefer a publicly trusted certificate such as Let’s Encrypt when the origin may need direct access. Cloudflare Origin CA works well when the origin is reachable only through Cloudflare's proxy, but browsers do not trust it when proxying is disabled or users connect directly.</p>
<p>The certificate must be unexpired, cover the correct hostname, and match its private key. Configure the virtual host to return the right certificate through SNI. Monitor an Origin CA certificate's expiry yourself because Cloudflare currently does not send expiry notifications for it.</p>
<pre><code>openssl s_client -connect 203.0.113.10:443 -servername example.com</code></pre>
<p>This command inspects the origin certificate chain. Never commit private keys, a Global API Key, or API tokens; use a secret manager and least-privilege tokens.</p>

<h2>5. Select Full (strict) and avoid Flexible</h2>
<p>Choose <strong>Full (strict)</strong> after the origin has a valid certificate. It encrypts both legs and checks that the origin certificate is current, matches the hostname, and was issued by a public CA or Cloudflare Origin CA. It should be the production default.</p>
<ul><li><strong>Flexible:</strong> uses HTTP to the origin; unsuitable for sensitive traffic and prone to redirect loops.</li><li><strong>Full:</strong> encrypts the origin connection without validating its certificate.</li><li><strong>Full (strict):</strong> encrypts and validates the origin; failed validation commonly produces error 526.</li></ul>
<p>Once strict mode works, redirect HTTP to HTTPS in one intentional place. Configure trusted proxy headers when the application sits behind a reverse proxy so it does not redirect indefinitely. Repair mixed content by serving resources over HTTPS.</p>

<h2>6. Enable DNSSEC in the correct order</h2>
<p>DNSSEC helps resolvers detect forged DNS responses. Enable it in Cloudflare, copy the DS information, and add the exact DS record at the registrar. Wait for confirmation and validate the signed delegation with a DNSSEC checker.</p>
<blockquote>Do not leave an old DS record when changing authoritative DNS providers. A mismatched DS can make the domain fail for validating resolvers.</blockquote>
<p>When moving away from Cloudflare later, follow both providers' DNSSEC transition procedure or remove the DS at the correct time instead of only changing nameservers.</p>

<h2>7. Protect the origin after validation</h2>
<p>A proxy cannot hide an origin IP that remains in DNS history, DNS-only subdomains, or a mail server sharing the same address. Separate web and mail where practical, audit old records, and avoid publishing the IP in application content.</p>
<p>After the site is stable, the origin firewall can allow web traffic only from Cloudflare IP ranges. Update that list automatically from the official source and retain a separate administration path. Authenticated Origin Pulls adds assurance that origin connections come from Cloudflare. Cloudflare Tunnel is another option that uses an authenticated outbound connection without exposing a public web port.</p>
<p>Check IPv4, IPv6, health checks, cron jobs, webhooks, and monitoring before enforcing rules. Authenticated Origin Pulls is not required for traffic already carried by Cloudflare Tunnel.</p>

<h2>8. Apply caching and security carefully</h2>
<p>Start by caching static images, CSS, and JavaScript. Do not indiscriminately cache sign-in pages, carts, dashboards, personalized content, or write APIs. Send explicit <code>Cache-Control</code> headers and purge affected objects after urgent deployments.</p>
<p>Minimum TLS Version, WAF, and rate limiting can be configured as the plan permits. Enable HSTS only after every affected site and subdomain works reliably over HTTPS. Incorrect <code>includeSubDomains</code> or preload settings can make HTTP-only subdomains inaccessible for a long time.</p>

<h2>9. Test the website, email, and business flows</h2>
<pre><code>curl -I http://example.com
curl -I https://example.com
curl --resolve example.com:443:203.0.113.10 https://example.com/ -I</code></pre>
<p>Confirm that HTTP redirects once to HTTPS, canonical hostnames do not create chains, the certificate covers the domain, and the page has no mixed content. Test sign-in, forms, uploads, payments, APIs, webhooks, WebSockets, and background jobs.</p>
<p>Send and receive email both internally and externally. Verify MX destinations, ensure the mail hostname is not proxied, retain one valid SPF record, and confirm DKIM and DMARC survived the zone migration.</p>

<h2>10. Diagnose common errors</h2>
<ul><li><strong>521 Web server is down:</strong> the origin rejects connections, its web service is stopped, or a firewall blocks Cloudflare.</li><li><strong>522 Connection timed out:</strong> the origin did not respond in time; inspect routing, server load, and firewall rules.</li><li><strong>525 SSL handshake failed:</strong> origin TLS negotiation failed; inspect protocols, ciphers, SNI, and certificates.</li><li><strong>526 Invalid SSL certificate:</strong> Full (strict) cannot validate the origin certificate; check expiry, hostname, and CA chain.</li><li><strong>Redirect loop:</strong> commonly caused by Flexible plus an origin HTTPS redirect, or an application that does not trust proxy headers.</li><li><strong>Email stopped:</strong> usually a missing MX/TXT record, a proxied mail hostname, or invalid SPF/DKIM.</li></ul>

<h2>Completion checklist</h2>
<ol><li>The zone is Active and the registrar shows the assigned nameservers.</li><li>Website, email, verification, and service records match the original inventory.</li><li>The origin has a valid certificate and SSL/TLS uses Full (strict).</li><li>HTTP reaches HTTPS without loops or mixed content.</li><li>DNSSEC validates and the registrar's DS matches.</li><li>Sending, receiving, SPF, DKIM, and DMARC all pass.</li><li>Authentication, forms, APIs, webhooks, and uploads have been tested.</li><li>Caches do not store private content and firewall rules do not block required services.</li><li>Certificate expiry and 5xx errors are monitored.</li></ol>

<h2>Conclusion</h2>
<p>A secure Cloudflare deployment is not about enabling every feature at once. Preserve DNS, prepare HTTPS at the origin, change nameservers, validate services, turn on Full (strict) and DNSSEC, and only then restrict origin access. Checkpoints between stages provide edge protection and CDN benefits without sacrificing availability.</p>

<h2>References</h2>
<ul><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/" target="_blank" rel="noopener noreferrer">Cloudflare: SSL/TLS encryption modes</a></li><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/ssl-modes/full-strict/" target="_blank" rel="noopener noreferrer">Cloudflare: Full (strict)</a></li><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/origin-ca/" target="_blank" rel="noopener noreferrer">Cloudflare: Origin CA certificates</a></li><li><a href="https://developers.cloudflare.com/dns/proxy-status/" target="_blank" rel="noopener noreferrer">Cloudflare: Proxy status</a></li><li><a href="https://developers.cloudflare.com/dns/dnssec/" target="_blank" rel="noopener noreferrer">Cloudflare: DNSSEC</a></li><li><a href="https://developers.cloudflare.com/ssl/origin-configuration/authenticated-origin-pull/" target="_blank" rel="noopener noreferrer">Cloudflare: Authenticated Origin Pulls</a></li></ul>
HTML,
        ],
    ],
];
