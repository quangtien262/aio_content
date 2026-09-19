<?php

return [
    'huong-dan-cai-dat-aapanel.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài đặt aaPanel trên Ubuntu và cấu hình an toàn',
            'slug' => 'huong-dan-cai-dat-aapanel',
            'image' => 'huong-dan-cai-dat-aapanel.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Cài đặt aaPanel trên Ubuntu an toàn từ A-Z',
            'meta_keywords' => 'cài đặt aaPanel, aaPanel Ubuntu, quản lý VPS, cài LEMP, aaPanel SSL, bảo mật aaPanel',
            'meta_description' => 'Hướng dẫn cài aaPanel trên Ubuntu, hardening panel, dựng LEMP, thêm website, SSL, backup ngoài máy và kiểm tra vận hành.',
            'tags' => ['aaPanel', 'Ubuntu Server', 'VPS', 'LEMP', 'Web Hosting', 'Server Security', 'SSL'],
            'body' => <<<'HTML'
<p><strong>aaPanel giúp quản lý website, PHP, database, SSL, cron, backup và tài nguyên VPS qua trình duyệt.</strong> Bài hướng dẫn này cài aaPanel trên một Ubuntu Server mới, sau đó khóa giao diện quản trị trước khi đưa website vào hoạt động.</p>
<blockquote>aaPanel cần quyền root và thay đổi nhiều thành phần hệ thống. Hãy dùng VPS mới hoặc snapshot có thể phục hồi; không cài trực tiếp lên máy đang chạy stack production được cấu hình thủ công.</blockquote>

<h2>1. Cấu hình và thông tin cần chuẩn bị</h2>
<ul><li>VPS có IP public, tối thiểu 1 vCPU và 1 GB RAM cho thử nghiệm; production nên sizing theo website và database.</li><li>Ubuntu 22.04 hoặc phiên bản đang được aaPanel hỗ trợ; tài liệu chính thức hiện khuyến nghị Ubuntu 22.04.</li><li>Tài khoản root hoặc tài khoản có quyền <code>sudo</code>, đăng nhập bằng SSH key.</li><li>Một domain cho website; nên có thêm subdomain riêng cho panel.</li><li>Nơi lưu backup ngoài VPS, chẳng hạn object storage hoặc máy chủ khác.</li></ul>
<p>Không dùng máy đã cài sẵn Nginx, Apache, MySQL hoặc control panel khác nếu chưa hiểu xung đột. Trước khi bắt đầu, tạo snapshot tại nhà cung cấp VPS.</p>

<h2>2. Kết nối SSH và cập nhật hệ điều hành</h2>
<pre><code>ssh -i ~/.ssh/server_key root@SERVER_IP

apt update
apt full-upgrade -y
apt install -y curl ca-certificates
reboot</code></pre>
<p>Sau khi máy khởi động lại, kết nối SSH lần nữa. Kiểm tra hệ điều hành, dung lượng và RAM:</p>
<pre><code>cat /etc/os-release
df -h
free -h</code></pre>

<h2>3. Cấu hình firewall ở nhà cung cấp</h2>
<p>Ban đầu chỉ cho phép:</p>
<table><thead><tr><th>Cổng</th><th>Mục đích</th><th>Nguồn nên cho phép</th></tr></thead><tbody><tr><td>22/TCP</td><td>SSH</td><td>IP quản trị hoặc VPN</td></tr><tr><td>80/TCP</td><td>HTTP và xác minh chứng chỉ</td><td>Internet</td></tr><tr><td>443/TCP</td><td>HTTPS website</td><td>Internet</td></tr><tr><td>Cổng panel</td><td>Quản trị aaPanel</td><td>Chỉ IP quản trị hoặc VPN</td></tr></tbody></table>
<p>Chưa mở MySQL 3306, Redis 6379, FTP hoặc cổng panel cho toàn Internet. Sau khi cài, aaPanel in URL và cổng quản trị thực tế; khi đó mới thêm rule giới hạn nguồn.</p>

<h2>4. Tải và kiểm tra script cài đặt</h2>
<p>Tài liệu aaPanel cung cấp script chính thức tại tên miền <code>aapanel.com</code>. Thay vì tải với tùy chọn bỏ qua xác minh chứng chỉ, hãy dùng TLS bình thường:</p>
<pre><code>cd /root
curl --fail --location --proto '=https' --tlsv1.2 \
  --output install_panel_en.sh \
  https://www.aapanel.com/script/install_panel_en.sh

ls -lh install_panel_en.sh
less install_panel_en.sh</code></pre>
<p>Kiểm tra URL nguồn, các package và đường dẫn mà script sẽ thay đổi. Nếu TLS báo lỗi, hãy sửa thời gian hệ thống hoặc CA certificate; không dùng <code>-k</code> để bỏ xác minh trên máy production.</p>
<p>aaPanel chưa công bố checksum cố định ngay trên trang Quick Start cho script động, vì vậy việc tải từ đúng HTTPS, xem script và lưu bản đã dùng trong hồ sơ thay đổi là quan trọng.</p>

<h2>5. Chạy cài đặt aaPanel</h2>
<pre><code>bash install_panel_en.sh forum</code></pre>
<p>Xác nhận thư mục cài đặt khi script hỏi. Quá trình sẽ cài panel và in ra:</p>
<ul><li>URL truy cập bên ngoài và nội bộ;</li><li>cổng panel;</li><li>security entrance dạng đường dẫn ngẫu nhiên;</li><li>username và password ban đầu.</li></ul>
<p>Lưu thông tin vào password manager, không gửi qua chat hoặc email. Giữ phiên SSH mở cho đến khi đăng nhập panel thành công.</p>

<h2>6. Mở đúng cổng panel và đăng nhập lần đầu</h2>
<p>Thêm cổng panel vào security group nhưng chỉ cho phép IP quản trị hoặc dải VPN. Truy cập chính xác URL được script in ra, bao gồm HTTPS, cổng và security entrance.</p>
<p>aaPanel có thể dùng chứng chỉ tự ký ban đầu nên trình duyệt hiển thị cảnh báo. Hãy xác minh IP/URL đúng với phiên cài đặt trước khi tiếp tục; sau đó thay bằng chứng chỉ tin cậy.</p>
<p>Nếu quên thông tin, tài liệu chính thức nêu các lệnh:</p>
<pre><code>bt default   # xem thông tin truy cập mặc định
bt 5         # đổi mật khẩu panel
bt           # mở menu quản lý aaPanel</code></pre>

<h2>7. Hardening ngay sau đăng nhập</h2>
<ol><li>Đổi username và mật khẩu panel thành giá trị duy nhất.</li><li>Bật <strong>Panel SSL</strong> với domain và chứng chỉ tin cậy.</li><li>Bật <strong>Google Authenticator</strong>; lưu mã khôi phục an toàn.</li><li>Bật <strong>BasicAuth</strong> nếu phù hợp để thêm một lớp trước trang đăng nhập.</li><li>Bind domain panel để chặn truy cập trực tiếp bằng IP.</li><li>Dùng <strong>Authorized IP</strong> hoặc VPN để chỉ máy quản trị truy cập.</li><li>Giữ security entrance khó đoán, nhưng không coi đây là lớp bảo mật chính.</li><li>Đặt timeout phiên ngắn và bật cảnh báo thay đổi tài khoản/log.</li><li>Kiểm tra host firewall trong mục Security; chính sách mặc định nên từ chối cổng không cần.</li></ol>
<blockquote>Đổi cổng panel chỉ giảm nhiễu từ bot quét. MFA, giới hạn IP, cập nhật và backup mới là các lớp bảo vệ quan trọng.</blockquote>

<h2>8. Chọn LEMP hay LAMP</h2>
<table><thead><tr><th>Stack</th><th>Nên chọn khi</th></tr></thead><tbody><tr><td>LEMP: Nginx + PHP-FPM</td><td>Website mới, cần hiệu năng tốt và cấu hình reverse proxy hiện đại</td></tr><tr><td>LAMP: Apache + PHP</td><td>Ứng dụng phụ thuộc <code>.htaccess</code> hoặc module Apache cụ thể</td></tr></tbody></table>
<p>Trong lần khởi tạo, chỉ cài web server, PHP, database và cache thực sự cần. Chọn phiên bản PHP/database còn được hỗ trợ bởi ứng dụng. Không cài mail server, FTP, Docker hoặc nhiều database engine “để sẵn”.</p>
<p>Sau khi cài stack, kiểm tra service và tài nguyên. Giữ đủ RAM trống cho database; trên VPS nhỏ, quá nhiều service sẽ gây swap và chậm I/O.</p>

<h2>9. Thêm website đầu tiên</h2>
<ol><li>Trỏ bản ghi A/AAAA của domain về IP máy chủ.</li><li>Trong <strong>Website</strong>, chọn loại project và thêm domain.</li><li>Đặt document root đúng; với Laravel thường là thư mục <code>public</code>.</li><li>Tạo database và user riêng cho website, dùng mật khẩu sinh ngẫu nhiên.</li><li>Upload hoặc deploy mã nguồn bằng quy trình có kiểm soát.</li><li>Thiết lập rewrite, PHP version và extension theo tài liệu ứng dụng.</li><li>Kiểm tra website qua HTTP trước khi cấp SSL.</li></ol>
<p>Không dùng tài khoản database root trong ứng dụng. File cấu hình chứa secret phải nằm ngoài public root hoặc bị web server chặn truy cập.</p>

<h2>10. Cấp SSL và chuyển sang HTTPS</h2>
<p>Sau khi DNS đã trỏ đúng và cổng 80/443 mở, vào cấu hình website, yêu cầu chứng chỉ Let's Encrypt rồi bật chuyển hướng HTTPS. Kiểm tra:</p>
<ul><li>domain và <code>www</code> cần thiết đều nằm trong chứng chỉ;</li><li>không có mixed content;</li><li>tự động gia hạn hoạt động;</li><li>website vẫn truy cập sau khi reload web server.</li></ul>

<h2>11. Thiết lập backup ngoài máy</h2>
<p>Tạo lịch backup riêng cho file website và database. Không dừng ở thư mục <code>/www/backup</code> trên cùng VPS. Đồng bộ bản sao sang storage hoặc tài khoản độc lập, mã hóa và đặt retention phù hợp.</p>
<ol><li>Chạy một bản backup thủ công.</li><li>Tải hoặc chuyển bản backup ra ngoài máy.</li><li>Dựng database/site thử từ bản sao.</li><li>Ghi lại thời gian và các bước khôi phục.</li></ol>
<p>Snapshot VPS hữu ích nhưng không thay thế backup cấp ứng dụng, vì snapshot có thể cùng tài khoản cloud và cùng miền sự cố.</p>

<h2>12. Cập nhật, monitoring và vận hành</h2>
<ul><li>Bật cảnh báo CPU, RAM, disk, service, SSL và backup.</li><li>Kiểm tra bản cập nhật aaPanel/plugin định kỳ; thử trên staging khi có thể.</li><li>Cập nhật Ubuntu và lên lịch reboot cho kernel.</li><li>Đọc log web server, PHP và panel khi có lỗi, không chỉ restart dịch vụ.</li><li>Kiểm tra dung lượng trước khi backup hoặc nâng cấp database.</li><li>Lưu tài liệu domain, port, stack, phiên bản, backup và người chịu trách nhiệm.</li></ul>

<h2>Lỗi thường gặp</h2>
<table><thead><tr><th>Hiện tượng</th><th>Kiểm tra</th></tr></thead><tbody><tr><td>Không mở được panel</td><td>Security group, host firewall, đúng cổng và security entrance</td></tr><tr><td>Cảnh báo chứng chỉ</td><td>Ban đầu có thể là self-signed; cấu hình domain và Panel SSL tin cậy</td></tr><tr><td>Website không hiện</td><td>DNS, domain trong site, document root, HTTP/HTTPS và log web server</td></tr><tr><td>SSL cấp thất bại</td><td>DNS đã propagate, cổng 80, proxy/CDN và bản ghi AAAA</td></tr><tr><td>502 Bad Gateway</td><td>PHP-FPM/runtime đang chạy, socket/port, phiên bản và log lỗi</td></tr><tr><td>VPS chậm sau cài</td><td>RAM, swap, disk I/O và các service/plugin không cần thiết</td></tr></tbody></table>

<h2>Checklist trước khi đưa vào production</h2>
<ul><li>Panel chỉ truy cập qua HTTPS và IP/VPN được phép.</li><li>MFA hoạt động và mã khôi phục đã được lưu.</li><li>SSH dùng key; đăng nhập root bằng mật khẩu bị hạn chế theo chính sách.</li><li>Chỉ mở 22, 80, 443 và cổng thật sự cần.</li><li>Database/cache không public.</li><li>Website chạy HTTPS và tự gia hạn chứng chỉ.</li><li>Backup file/database nằm ngoài VPS và đã restore thử.</li><li>Cảnh báo tài nguyên, SSL và backup đã được kiểm tra.</li><li>Có snapshot hoặc kế hoạch rollback trước mỗi nâng cấp lớn.</li></ul>

<h2>Kết luận</h2>
<p>Cài aaPanel chỉ mất ít phút, nhưng một máy chủ có thể vận hành an toàn cần nhiều bước hơn: giới hạn đường quản trị, bật MFA, cài stack tối thiểu, tách secret, backup ngoài máy và kiểm thử phục hồi. Nếu không có người chịu trách nhiệm cập nhật và xử lý sự cố Linux, managed hosting vẫn là lựa chọn phù hợp hơn tự quản lý VPS.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://www.aapanel.com/docs/guide/quickstart.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Quick Start</a></li><li><a href="https://www.aapanel.com/docs/Function/Settings.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Settings</a></li><li><a href="https://www.aapanel.com/docs/Function/Security.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Security</a></li><li><a href="https://www.aapanel.com/docs/Function/Website.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Website Basics</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Install aaPanel on Ubuntu and Configure It Securely',
            'slug' => 'install-aapanel-ubuntu-securely',
            'image' => 'huong-dan-cai-dat-aapanel.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Install aaPanel Securely on Ubuntu: Full Guide',
            'meta_keywords' => 'install aaPanel, aaPanel Ubuntu, VPS management, install LEMP, aaPanel SSL, secure aaPanel',
            'meta_description' => 'Install aaPanel on Ubuntu, harden the panel, build LEMP, add a website and SSL, configure off-host backups, and validate operations.',
            'tags' => ['aaPanel', 'Ubuntu Server', 'VPS', 'LEMP', 'Web Hosting', 'Server Security', 'SSL'],
            'body' => <<<'HTML'
<p><strong>aaPanel provides browser-based management for websites, PHP, databases, SSL, cron, backups, and VPS resources.</strong> This guide installs aaPanel on a fresh Ubuntu Server and secures its administration interface before hosting a website.</p>
<blockquote>aaPanel requires root privileges and changes many system components. Use a fresh VPS or a recoverable snapshot; do not install it directly over a manually configured production stack.</blockquote>

<h2>1. Requirements</h2>
<ul><li>A public VPS with at least 1 vCPU and 1 GB RAM for testing; size production for the application and database.</li><li>Ubuntu 22.04 or another currently supported release; official documentation presently recommends Ubuntu 22.04.</li><li>Root or sudo access using an SSH key.</li><li>A website domain and preferably a separate panel subdomain.</li><li>Off-host storage for backups.</li></ul>
<p>Avoid a host that already runs Nginx, Apache, MySQL, or another control panel unless you understand the conflicts. Create a provider snapshot first.</p>

<h2>2. Connect and update Ubuntu</h2>
<pre><code>ssh -i ~/.ssh/server_key root@SERVER_IP

apt update
apt full-upgrade -y
apt install -y curl ca-certificates
reboot</code></pre>
<p>Reconnect and inspect the system:</p>
<pre><code>cat /etc/os-release
df -h
free -h</code></pre>

<h2>3. Configure the cloud firewall</h2>
<table><thead><tr><th>Port</th><th>Purpose</th><th>Recommended source</th></tr></thead><tbody><tr><td>22/TCP</td><td>SSH</td><td>Administrator IP or VPN</td></tr><tr><td>80/TCP</td><td>HTTP and certificate validation</td><td>Internet</td></tr><tr><td>443/TCP</td><td>Website HTTPS</td><td>Internet</td></tr><tr><td>Panel port</td><td>aaPanel administration</td><td>Administrator IP or VPN only</td></tr></tbody></table>
<p>Do not expose MySQL 3306, Redis 6379, FTP, or an unknown panel port. The installer prints the actual administration port; add a source-restricted rule afterward.</p>

<h2>4. Download and inspect the installer</h2>
<p>The official documentation serves the installer from <code>aapanel.com</code>. Download it with normal certificate verification instead of disabling TLS checks:</p>
<pre><code>cd /root
curl --fail --location --proto '=https' --tlsv1.2 \
  --output install_panel_en.sh \
  https://www.aapanel.com/script/install_panel_en.sh

ls -lh install_panel_en.sh
less install_panel_en.sh</code></pre>
<p>Review the source URL, packages, and paths. If TLS fails, fix system time or CA certificates; do not use <code>-k</code> to bypass verification on production.</p>
<p>The Quick Start page does not publish a fixed checksum beside this changing script, making the verified HTTPS source, manual review, and retaining the exact script in change records especially useful.</p>

<h2>5. Run the installation</h2>
<pre><code>bash install_panel_en.sh forum</code></pre>
<p>Confirm the installation directory when prompted. The installer reports the external and internal URLs, panel port, random security entrance, initial username, and password.</p>
<p>Store these in a password manager, not chat or email. Keep the SSH session open until panel access is confirmed.</p>

<h2>6. Allow the panel port and sign in</h2>
<p>Add the reported panel port to the cloud security group, restricted to the administrator IP or VPN. Visit the exact URL including HTTPS, port, and security entrance.</p>
<p>The initial panel may use a self-signed certificate. Verify that the IP and URL match the installation session before continuing, then replace it with a trusted certificate.</p>
<pre><code>bt default   # show default access information
bt 5         # change the panel password
bt           # open the aaPanel management menu</code></pre>

<h2>7. Harden the panel immediately</h2>
<ol><li>Replace the panel username and password with unique values.</li><li>Enable <strong>Panel SSL</strong> with a trusted domain and certificate.</li><li>Enable <strong>Google Authenticator</strong> and store recovery information.</li><li>Add <strong>BasicAuth</strong> when appropriate.</li><li>Bind a panel domain to block direct IP access.</li><li>Use <strong>Authorized IP</strong> or a VPN.</li><li>Keep the random security entrance, but do not treat it as primary security.</li><li>Use a short session timeout and enable security alerts.</li><li>Review the host firewall and deny unnecessary inbound ports.</li></ol>
<blockquote>Changing the port mainly reduces automated noise. MFA, IP restriction, patching, and backups provide the meaningful protection.</blockquote>

<h2>8. Choose LEMP or LAMP</h2>
<table><thead><tr><th>Stack</th><th>Choose it when</th></tr></thead><tbody><tr><td>LEMP: Nginx + PHP-FPM</td><td>Building a new site that benefits from a modern high-performance web stack</td></tr><tr><td>LAMP: Apache + PHP</td><td>The application depends on <code>.htaccess</code> or specific Apache modules</td></tr></tbody></table>
<p>Install only the web server, PHP, database, and cache you need. Select application-supported versions. Do not preinstall mail, FTP, Docker, or several database engines merely because they are available.</p>

<h2>9. Add the first website</h2>
<ol><li>Point the domain A/AAAA records to the server.</li><li>Open <strong>Website</strong>, select the project type, and add the domain.</li><li>Set the correct document root; Laravel commonly uses <code>public</code>.</li><li>Create a dedicated database and user with a generated password.</li><li>Upload or deploy through a controlled process.</li><li>Set rewrite rules, PHP version, and extensions required by the application.</li><li>Test over HTTP before issuing SSL.</li></ol>
<p>Never use the database root account in the application. Keep secret-bearing configuration outside the public root or explicitly blocked by the web server.</p>

<h2>10. Issue SSL and enforce HTTPS</h2>
<p>Once DNS is correct and ports 80/443 are open, request a Let's Encrypt certificate in the site settings and enable HTTPS redirection. Verify all required names, mixed content, automatic renewal, and site availability after a web-server reload.</p>

<h2>11. Configure off-host backups</h2>
<p>Schedule separate site-file and database backups. Do not rely on <code>/www/backup</code> on the same VPS. Transfer copies to an independent account or provider, encrypt them, and define retention.</p>
<ol><li>Create a manual backup.</li><li>Move or download it off-host.</li><li>Restore a test site and database from the copy.</li><li>Record the steps and actual recovery time.</li></ol>
<p>Provider snapshots are useful but do not replace application-level backups because they can share the cloud account and failure domain.</p>

<h2>12. Updates, monitoring, and operations</h2>
<ul><li>Alert on CPU, memory, disk, services, SSL, and backup failure.</li><li>Review aaPanel and plugin updates; test in staging when possible.</li><li>Patch Ubuntu and schedule kernel reboots.</li><li>Read web-server, PHP, and panel logs instead of merely restarting services.</li><li>Check free space before backups and database upgrades.</li><li>Document domains, ports, stack versions, backup locations, and ownership.</li></ul>

<h2>Common problems</h2>
<table><thead><tr><th>Symptom</th><th>Check</th></tr></thead><tbody><tr><td>Panel does not open</td><td>Security group, host firewall, actual port, and security entrance</td></tr><tr><td>Certificate warning</td><td>Initial self-signed certificate; configure a trusted panel domain and SSL</td></tr><tr><td>Website not found</td><td>DNS, site domain, document root, protocol, and web-server logs</td></tr><tr><td>SSL issuance fails</td><td>DNS propagation, port 80, proxy/CDN, and AAAA records</td></tr><tr><td>502 Bad Gateway</td><td>PHP-FPM/runtime status, socket or port, version, and logs</td></tr><tr><td>Slow VPS</td><td>Memory, swap, disk I/O, and unnecessary services or plugins</td></tr></tbody></table>

<h2>Production checklist</h2>
<ul><li>The panel is reachable only through HTTPS and authorized IPs or VPN.</li><li>MFA works and recovery information is stored.</li><li>SSH uses keys and password-based root login follows a restrictive policy.</li><li>Only 22, 80, 443, and required ports are open.</li><li>Databases and caches are not public.</li><li>The site uses HTTPS and certificate renewal works.</li><li>File and database backups exist off-host and have passed a restore test.</li><li>Resource, certificate, and backup alerts have been tested.</li><li>A snapshot or rollback plan exists before major upgrades.</li></ul>

<h2>Conclusion</h2>
<p>Installing aaPanel takes only a few minutes, but a production-ready server requires more: restricted administration, MFA, a minimal stack, protected secrets, off-host backups, and tested recovery. If no one owns Linux patching and incident response, managed hosting remains a better choice than a self-managed VPS.</p>

<h2>References</h2>
<ul><li><a href="https://www.aapanel.com/docs/guide/quickstart.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Quick Start</a></li><li><a href="https://www.aapanel.com/docs/Function/Settings.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Settings</a></li><li><a href="https://www.aapanel.com/docs/Function/Security.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Security</a></li><li><a href="https://www.aapanel.com/docs/Function/Website.html" target="_blank" rel="noopener noreferrer">aaPanel Documentation: Website Basics</a></li></ul>
HTML,
        ],
    ],
];

