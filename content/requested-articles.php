<?php

return [
    'cai-dat-php-mysql-apache-tren-macos.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài đặt PHP, MySQL và Apache trên macOS bằng Homebrew',
            'slug' => 'cai-dat-php-mysql-apache-tren-macos',
            'image' => 'cai-dat-php-mysql-apache-macos.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Cài PHP, MySQL và Apache trên macOS',
            'meta_keywords' => 'cài PHP macOS, cài MySQL macOS, cài Apache macOS, Homebrew, PHP Apache macOS, môi trường PHP',
            'meta_description' => 'Hướng dẫn cài PHP, MySQL và Apache trên macOS bằng Homebrew, cấu hình PHP với Apache, Virtual Host và kiểm tra môi trường local.',
            'tags' => ['PHP', 'MySQL', 'Apache', 'macOS', 'Homebrew', 'Web Development'],
            'body' => <<<'HTML'
<p><strong>Homebrew giúp tách môi trường lập trình khỏi các thành phần hệ thống đi kèm macOS và làm cho việc nâng cấp, gỡ bỏ dễ kiểm soát hơn.</strong> Bài viết này dựng một bộ PHP, MySQL và Apache phục vụ phát triển local, áp dụng được cho cả máy Apple Silicon và Intel.</p>
<h2>Chuẩn bị Homebrew và Command Line Tools</h2>
<pre><code>xcode-select --install
brew --version
brew update</code></pre>
<p>Nếu chưa có Homebrew, cài theo lệnh chính thức tại <a href="https://brew.sh/" target="_blank" rel="noopener noreferrer">brew.sh</a>. Không hardcode đường dẫn <code>/opt/homebrew</code> hoặc <code>/usr/local</code>; dùng <code>brew --prefix</code> để cấu hình hoạt động trên cả hai kiến trúc.</p>
<h2>Cài PHP, MySQL và Apache</h2>
<pre><code>brew install php mysql httpd
php -v
mysql --version
$(brew --prefix httpd)/bin/httpd -v</code></pre>
<p>Homebrew cài Apache dưới tên formula <code>httpd</code>. Cấu hình mặc định dùng cổng 8080 để không cần quyền quản trị. Document root mặc định nằm tại <code>$(brew --prefix)/var/www</code>.</p>
<h2>Khởi động dịch vụ</h2>
<pre><code>brew services start php
brew services start mysql
brew services start httpd
brew services list</code></pre>
<p>Mở <code>http://localhost:8080</code> để kiểm tra Apache. Nếu chỉ muốn chạy trong phiên terminal hiện tại, có thể dùng lệnh trực tiếp thay cho <code>brew services</code>.</p>
<h2>Khởi tạo và bảo vệ MySQL local</h2>
<pre><code>mysql_secure_installation
mysql -u root -p</code></pre>
<p>Trong dự án thật, nên tạo database và tài khoản riêng thay vì để ứng dụng dùng root:</p>
<pre><code>CREATE DATABASE demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'demo'@'localhost' IDENTIFIED BY 'local_password';
GRANT ALL PRIVILEGES ON demo.* TO 'demo'@'localhost';
FLUSH PRIVILEGES;</code></pre>
<h2>Kết nối PHP với Apache</h2>
<p>Mở file <code>$(brew --prefix)/etc/httpd/httpd.conf</code>. Đường dẫn module PHP thay đổi theo phiên bản và kiến trúc, vì vậy hãy tìm chính xác bằng:</p>
<pre><code>brew --prefix php
find "$(brew --prefix php)" -name 'libphp.so'</code></pre>
<p>Nếu formula PHP hiện tại cung cấp module Apache, thêm dòng <code>LoadModule</code> theo đường dẫn vừa tìm được, rồi cấu hình xử lý tệp PHP:</p>
<pre><code>&lt;IfModule php_module&gt;
    DirectoryIndex index.php index.html
    AddType application/x-httpd-php .php
&lt;/IfModule&gt;</code></pre>
<p>Một số phiên bản Homebrew có thể ưu tiên PHP-FPM thay vì <code>libphp</code>. Khi không tìm thấy module, dùng PHP-FPM và cấu hình <code>proxy_fcgi</code>; không lấy đường dẫn từ một bài cũ rồi dán nguyên vào máy.</p>
<h2>Tạo Virtual Host cho dự án</h2>
<pre><code>&lt;VirtualHost *:8080&gt;
    ServerName demo.test
    DocumentRoot "/Users/yourname/Sites/demo/public"
    &lt;Directory "/Users/yourname/Sites/demo/public"&gt;
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
&lt;/VirtualHost&gt;</code></pre>
<p>Bật file virtual hosts trong <code>httpd.conf</code>, sau đó thêm <code>127.0.0.1 demo.test</code> vào <code>/etc/hosts</code>. Kiểm tra cấu hình trước khi restart:</p>
<pre><code>$(brew --prefix httpd)/bin/httpd -t
brew services restart httpd</code></pre>
<h2>Kiểm tra PHP và extension MySQL</h2>
<pre><code>php -m | grep -E 'mysqli|pdo_mysql'
php --ini
echo '&lt;?php phpinfo();' &gt; "$(brew --prefix)/var/www/info.php"</code></pre>
<p>Truy cập trang kiểm tra rồi xóa <code>info.php</code> ngay sau đó vì trang này tiết lộ nhiều thông tin cấu hình.</p>
<h2>Lỗi thường gặp</h2>
<ul><li><strong>Cổng 8080 bị chiếm:</strong> dùng <code>lsof -nP -iTCP:8080 -sTCP:LISTEN</code> hoặc đổi <code>Listen</code>.</li><li><strong>Apache tải tệp PHP thay vì chạy:</strong> kiểm tra module/FPM, <code>AddType</code> và log Apache.</li><li><strong>Lệnh PHP trỏ sai bản:</strong> kiểm tra <code>which php</code>, PATH và chạy <code>brew link --overwrite php</code> khi thật sự cần.</li><li><strong>MySQL không khởi động:</strong> xem <code>brew services list</code> và log trong thư mục data của formula.</li></ul>
<h2>Checklist hoàn tất</h2><ol><li>Apache trả về trang local trên đúng cổng.</li><li><code>php -v</code> và trang web dùng cùng phiên bản PHP.</li><li>Ứng dụng kết nối bằng tài khoản MySQL riêng.</li><li>Virtual Host trỏ đúng thư mục public.</li><li>Không đưa mật khẩu thật hoặc <code>phpinfo()</code> vào repository.</li></ol>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://formulae.brew.sh/formula/php" target="_blank" rel="noopener noreferrer">Homebrew PHP</a></li><li><a href="https://formulae.brew.sh/formula/mysql" target="_blank" rel="noopener noreferrer">Homebrew MySQL</a></li><li><a href="https://formulae.brew.sh/formula/httpd" target="_blank" rel="noopener noreferrer">Homebrew httpd</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Install PHP, MySQL, and Apache on macOS with Homebrew',
            'slug' => 'install-php-mysql-apache-macos-homebrew',
            'image' => 'cai-dat-php-mysql-apache-macos.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Install PHP, MySQL, and Apache on macOS',
            'meta_keywords' => 'install PHP macOS, install MySQL macOS, install Apache macOS, Homebrew, PHP Apache macOS, PHP environment',
            'meta_description' => 'Install PHP, MySQL, and Apache on macOS with Homebrew, connect PHP to Apache, configure a virtual host, and verify the local stack.',
            'tags' => ['PHP', 'MySQL', 'Apache', 'macOS', 'Homebrew', 'Web Development'],
            'body' => <<<'HTML'
<p><strong>Homebrew keeps development tools separate from the components bundled with macOS and makes upgrades easier to control.</strong> This guide builds a local PHP, MySQL, and Apache stack for both Apple Silicon and Intel Macs.</p>
<h2>Prepare Homebrew and command-line tools</h2><pre><code>xcode-select --install
brew --version
brew update</code></pre><p>Install Homebrew from <a href="https://brew.sh/" target="_blank" rel="noopener noreferrer">brew.sh</a> if needed. Use <code>brew --prefix</code> instead of hardcoding <code>/opt/homebrew</code> or <code>/usr/local</code>.</p>
<h2>Install the stack</h2><pre><code>brew install php mysql httpd
php -v
mysql --version
$(brew --prefix httpd)/bin/httpd -v</code></pre><p>Homebrew distributes Apache as <code>httpd</code>. Its default configuration uses port 8080 and serves files from <code>$(brew --prefix)/var/www</code>.</p>
<h2>Start services</h2><pre><code>brew services start php
brew services start mysql
brew services start httpd
brew services list</code></pre><p>Open <code>http://localhost:8080</code> to verify Apache.</p>
<h2>Initialize MySQL</h2><pre><code>mysql_secure_installation
mysql -u root -p</code></pre><p>Create a dedicated database user for the application:</p><pre><code>CREATE DATABASE demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'demo'@'localhost' IDENTIFIED BY 'local_password';
GRANT ALL PRIVILEGES ON demo.* TO 'demo'@'localhost';
FLUSH PRIVILEGES;</code></pre>
<h2>Connect PHP to Apache</h2><p>Edit <code>$(brew --prefix)/etc/httpd/httpd.conf</code>. Locate the installed module instead of copying a version-specific path:</p><pre><code>brew --prefix php
find "$(brew --prefix php)" -name 'libphp.so'</code></pre><p>If available, load that module and configure PHP files:</p><pre><code>&lt;IfModule php_module&gt;
    DirectoryIndex index.php index.html
    AddType application/x-httpd-php .php
&lt;/IfModule&gt;</code></pre><p>Some Homebrew PHP versions may favor PHP-FPM. If <code>libphp.so</code> is unavailable, configure <code>proxy_fcgi</code> and FPM rather than pasting an obsolete module path.</p>
<h2>Create a virtual host</h2><pre><code>&lt;VirtualHost *:8080&gt;
    ServerName demo.test
    DocumentRoot "/Users/yourname/Sites/demo/public"
    &lt;Directory "/Users/yourname/Sites/demo/public"&gt;
        AllowOverride All
        Require all granted
    &lt;/Directory&gt;
&lt;/VirtualHost&gt;</code></pre><p>Enable virtual hosts, add <code>127.0.0.1 demo.test</code> to <code>/etc/hosts</code>, then validate and restart:</p><pre><code>$(brew --prefix httpd)/bin/httpd -t
brew services restart httpd</code></pre>
<h2>Verify PHP and MySQL support</h2><pre><code>php -m | grep -E 'mysqli|pdo_mysql'
php --ini
echo '&lt;?php phpinfo();' &gt; "$(brew --prefix)/var/www/info.php"</code></pre><p>Remove the information page after testing because it exposes detailed configuration.</p>
<h2>Common problems</h2><ul><li>Find port conflicts with <code>lsof -nP -iTCP:8080 -sTCP:LISTEN</code>.</li><li>If PHP downloads as text, inspect the module or FPM configuration and Apache logs.</li><li>If the CLI uses the wrong PHP, inspect <code>which php</code> and your PATH.</li><li>If MySQL fails, inspect the service status and formula data logs.</li></ul>
<h2>Completion checklist</h2><ol><li>Apache serves the local site on the expected port.</li><li>The CLI and web server use the intended PHP version.</li><li>The application connects with a dedicated MySQL user.</li><li>The virtual host points to the public directory.</li><li>No real password or <code>phpinfo()</code> page is committed.</li></ol>
<h2>References</h2><ul><li><a href="https://formulae.brew.sh/formula/php" target="_blank" rel="noopener noreferrer">Homebrew PHP</a></li><li><a href="https://formulae.brew.sh/formula/mysql" target="_blank" rel="noopener noreferrer">Homebrew MySQL</a></li><li><a href="https://formulae.brew.sh/formula/httpd" target="_blank" rel="noopener noreferrer">Homebrew httpd</a></li></ul>
HTML,
        ],
    ],
    'nhung-tinh-nang-moi-tren-ubuntu-26-04.html' => [
        'vi' => [
            'title' => 'Những tính năng mới đáng chú ý trên Ubuntu 26.04 LTS',
            'slug' => 'nhung-tinh-nang-moi-tren-ubuntu-26-04',
            'image' => 'tinh-nang-moi-ubuntu-26-04.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Ubuntu 26.04 LTS có gì mới?',
            'meta_keywords' => 'Ubuntu 26.04, Ubuntu 26.04 LTS, Resolute Raccoon, GNOME 50, Wayland, tính năng Ubuntu mới',
            'meta_description' => 'Tổng hợp những điểm mới trên Ubuntu 26.04 LTS: GNOME 50, Wayland, ứng dụng mặc định mới, Snap portal, ARM64 và lưu ý nâng cấp.',
            'tags' => ['Ubuntu 26.04', 'Ubuntu LTS', 'GNOME 50', 'Linux Desktop', 'Wayland', 'Hệ điều hành'],
            'body' => <<<'HTML'
<p><strong>Ubuntu 26.04 LTS “Resolute Raccoon” được phát hành ngày 23/04/2026 và nhận cập nhật bảo mật tiêu chuẩn đến tháng 4/2031.</strong> Với người nâng cấp từ Ubuntu 24.04 LTS, đây là bước nhảy hai năm, bao gồm thay đổi từ các bản 24.10, 25.04, 25.10 và phần mới riêng của 26.04.</p>
<h2>GNOME 50 và desktop mượt hơn</h2><p>Ubuntu 26.04 sử dụng GNOME 50. Người dùng nhận được giao diện Files và Calendar tinh chỉnh, tùy chọn Reduced Motion, parental controls tốt hơn, cải thiện screen reader Orca, quản lý âm thanh rõ ràng hơn và khả năng chọn ngày đầu tuần.</p><p>Fractional scaling được bật mặc định với các mức tối ưu để giảm mờ. Variable Refresh Rate, color management, HiDPI và trải nghiệm NVIDIA trên Wayland cũng được cải thiện.</p>
<h2>Ubuntu Desktop chuyển hẳn sang Wayland</h2><p>Phiên Ubuntu Desktop mặc định chỉ chạy trên Wayland vì GNOME Shell không còn cung cấp phiên X.org. Ứng dụng X11 vẫn có thể chạy qua XWayland; các desktop khác như Xfce, MATE hoặc KDE vẫn có thể cung cấp lựa chọn X11 riêng.</p><blockquote>Trước khi nâng cấp máy dùng phần mềm điều khiển từ xa, capture màn hình, thiết bị chuyên dụng hoặc driver cũ, hãy kiểm tra khả năng tương thích Wayland trong môi trường thử nghiệm.</blockquote>
<h2>Tìm ứng dụng Snap và tìm web từ Overview</h2><p>Global Search của GNOME Shell có thể tìm ứng dụng Snap chưa cài và khởi tạo tìm kiếm web bằng trình duyệt mặc định. Cả hai search provider đều có thể tắt trong Settings nếu không phù hợp với cách làm việc.</p>
<h2>Snap tích hợp desktop tốt hơn</h2><p>Các ứng dụng Snap dùng XDG Desktop Portals có thể làm việc tự nhiên hơn với file, thư mục, camera, notification và USB. Quyền portal được quản lý trong GNOME Settings, giúp người dùng quan sát và điều chỉnh quyền truy cập rõ ràng hơn.</p>
<h2>Bộ ứng dụng mặc định thay đổi</h2><table><thead><tr><th>Nhóm</th><th>Ứng dụng mới</th><th>Điểm đáng chú ý</th></tr></thead><tbody><tr><td>PDF</td><td>Papers</td><td>Dựa trên Evince, chuyển sang GTK4 và một phần viết bằng Rust</td></tr><tr><td>Ảnh</td><td>Loupe</td><td>Thay Eye of GNOME, dùng Glycin</td></tr><tr><td>Terminal</td><td>Ptyxis</td><td>Tích hợp tốt với Podman, Toolbox, Distrobox và phục hồi session</td></tr><tr><td>Giám sát</td><td>Resources</td><td>Theo dõi CPU, RAM, GPU, mạng, lưu trữ và điện năng</td></tr></tbody></table>
<h2>HDR, remote desktop và khả năng tiếp cận</h2><p>GNOME qua các phiên bản 47–50 bổ sung HDR, screen sharing nội dung HDR, remote desktop tăng tốc phần cứng, extended virtual monitor, multi-touch và cải thiện NVIDIA. Installer cũng sửa nhiều vấn đề khiến screen reader không đọc được thông tin quan trọng.</p>
<h2>Hỗ trợ ARM64 và Raspberry Pi</h2><p>Ubuntu tiếp tục mở rộng image Desktop ARM64 chung cho nền tảng UEFI, máy ảo và một số thiết bị Windows on Arm. Raspberry Pi có layout boot mới: tài sản boot mới được thử trước khi xác nhận là bộ “known good”, giúp quá trình cập nhật đáng tin cậy hơn.</p>
<h2>Yêu cầu hệ thống và vòng đời</h2><ul><li>Desktop khuyến nghị CPU dual-core 2 GHz, RAM tối thiểu 6 GB và 25 GB lưu trữ.</li><li>Ubuntu Server có thể bắt đầu từ khoảng 1,5 GB RAM và 4 GB lưu trữ, tùy workload.</li><li>LTS nhận cập nhật tiêu chuẩn 5 năm; Ubuntu Pro mở rộng ESM đến 10 năm.</li></ul>
<h2>Lưu ý trước khi nâng cấp</h2><ol><li>Sao lưu dữ liệu và xác minh khả năng phục hồi.</li><li>Cập nhật hoàn toàn bản hiện tại và đọc known issues mới nhất.</li><li>Kiểm tra ứng dụng/driver phụ thuộc X11.</li><li>Gỡ hoặc vô hiệu hóa repository bên thứ ba không hỗ trợ 26.04.</li><li>Với máy sản xuất, chờ point release và thử trên thiết bị đại diện trước khi triển khai rộng.</li></ol>
<h2>Kết luận</h2><p>Ubuntu 26.04 LTS tập trung vào một desktop Wayland hiện đại, GNOME 50, khả năng tiếp cận, phần cứng mới và tích hợp ứng dụng sandbox tốt hơn. Giá trị lớn nhất với tổ chức vẫn là vòng đời LTS dài; nhưng kế hoạch nâng cấp nên dựa trên ứng dụng và thiết bị thực tế, không chỉ danh sách tính năng.</p>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://documentation.ubuntu.com/release-notes/26.04/" target="_blank" rel="noopener noreferrer">Ubuntu 26.04 LTS release notes</a></li><li><a href="https://documentation.ubuntu.com/release-notes/26.04/summary-for-lts-users/" target="_blank" rel="noopener noreferrer">Summary for LTS users</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'What Is New in Ubuntu 26.04 LTS',
            'slug' => 'whats-new-ubuntu-26-04-lts',
            'image' => 'tinh-nang-moi-ubuntu-26-04.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'What Is New in Ubuntu 26.04 LTS?',
            'meta_keywords' => 'Ubuntu 26.04, Ubuntu 26.04 LTS, Resolute Raccoon, GNOME 50, Wayland, Ubuntu new features',
            'meta_description' => 'Explore Ubuntu 26.04 LTS: GNOME 50, a Wayland-only desktop session, new default apps, improved Snap portals, ARM64 support, and upgrade notes.',
            'tags' => ['Ubuntu 26.04', 'Ubuntu LTS', 'GNOME 50', 'Linux Desktop', 'Wayland', 'Operating Systems'],
            'body' => <<<'HTML'
<p><strong>Ubuntu 26.04 LTS “Resolute Raccoon” was released on April 23, 2026 and receives standard security maintenance through April 2031.</strong> For users moving from Ubuntu 24.04 LTS, it combines two years of changes from the interim releases and 26.04 itself.</p>
<h2>GNOME 50 and a smoother desktop</h2><p>Ubuntu 26.04 ships GNOME 50, bringing refined Files and Calendar apps, Reduced Motion, stronger parental controls, major Orca improvements, clearer sound settings, and a configurable first day of the week.</p><p>Fractional scaling is enabled by default with optimized scale factors. Variable Refresh Rate, color management, HiDPI, and NVIDIA behavior on Wayland are also improved.</p>
<h2>The Ubuntu Desktop session is Wayland-only</h2><p>GNOME Shell no longer provides an X.org session, so Ubuntu Desktop now uses Wayland exclusively. X11 applications continue to run through XWayland, while other desktop environments may still offer their own X11 sessions.</p><blockquote>Test remote-control, screen-capture, specialist-device, and legacy-driver workflows before upgrading production workstations.</blockquote>
<h2>Search for Snap apps and the web</h2><p>GNOME Shell global search can discover available Snap applications and initiate a web search in the default browser. Both providers can be disabled in Settings.</p>
<h2>Better Snap desktop integration</h2><p>Snap applications using XDG Desktop Portals integrate more naturally with files, folders, cameras, notifications, and USB devices. Portal permissions can be managed from GNOME Settings.</p>
<h2>New default applications</h2><table><thead><tr><th>Area</th><th>New app</th><th>Highlight</th></tr></thead><tbody><tr><td>PDF</td><td>Papers</td><td>Based on Evince, modernized with GTK4 and some Rust</td></tr><tr><td>Images</td><td>Loupe</td><td>Replaces Eye of GNOME and uses Glycin</td></tr><tr><td>Terminal</td><td>Ptyxis</td><td>Container integration and session restoration</td></tr><tr><td>Monitoring</td><td>Resources</td><td>CPU, memory, GPU, network, storage, and power</td></tr></tbody></table>
<h2>HDR, remote desktop, and accessibility</h2><p>The GNOME 47–50 cycle adds HDR, HDR screen sharing, hardware-accelerated remote desktop, extended virtual monitors, multi-touch, and better NVIDIA stability. The desktop installer also fixes important screen-reader barriers.</p>
<h2>ARM64 and Raspberry Pi</h2><p>Ubuntu expands its generic ARM64 Desktop image for UEFI systems, virtual machines, and selected Windows-on-Arm devices. Raspberry Pi receives a more reliable boot layout that tests new boot assets before committing them as the known-good set.</p>
<h2>Requirements and support</h2><ul><li>Desktop recommends a 2 GHz dual-core CPU, at least 6 GB RAM, and 25 GB storage.</li><li>Server requirements can begin around 1.5 GB RAM and 4 GB storage, depending on workload.</li><li>Standard LTS maintenance lasts five years, with Ubuntu Pro extending ESM to ten.</li></ul>
<h2>Before upgrading</h2><ol><li>Back up data and verify recovery.</li><li>Fully update the current release and read current known issues.</li><li>Test software and drivers that depend on X11.</li><li>Disable unsupported third-party repositories.</li><li>For production fleets, validate a point release on representative hardware first.</li></ol>
<h2>Conclusion</h2><p>Ubuntu 26.04 LTS centers on a modern Wayland desktop, GNOME 50, accessibility, new hardware, and better sandboxed-app integration. Its long support window is the main organizational benefit, but upgrades should be driven by real application and device compatibility.</p>
<h2>References</h2><ul><li><a href="https://documentation.ubuntu.com/release-notes/26.04/" target="_blank" rel="noopener noreferrer">Ubuntu 26.04 LTS release notes</a></li><li><a href="https://documentation.ubuntu.com/release-notes/26.04/summary-for-lts-users/" target="_blank" rel="noopener noreferrer">Summary for LTS users</a></li></ul>
HTML,
        ],
    ],
    'cai-dat-moi-truong-cho-du-an-react-native.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài đặt môi trường cho dự án React Native',
            'slug' => 'cai-dat-moi-truong-cho-du-an-react-native',
            'image' => 'cai-dat-moi-truong-react-native.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Cài đặt môi trường React Native từ A đến Z',
            'meta_keywords' => 'cài React Native, môi trường React Native, Expo, Android Studio, Xcode, React Native CLI, Node.js',
            'meta_description' => 'Hướng dẫn chuẩn bị môi trường React Native với Expo hoặc native toolchain, Android Studio, Xcode, emulator và thiết bị thật.',
            'tags' => ['React Native', 'Expo', 'Android Studio', 'Xcode', 'Mobile Development', 'JavaScript'],
            'body' => <<<'HTML'
<p><strong>React Native có hai lộ trình cài đặt phổ biến: bắt đầu nhanh với Expo hoặc chuẩn bị đầy đủ native toolchain để tự build Android/iOS.</strong> Chọn đúng lộ trình ngay từ đầu giúp tránh cài hàng chục GB công cụ không cần thiết.</p>
<h2>Chọn Expo hay native toolchain?</h2><table><thead><tr><th>Lộ trình</th><th>Phù hợp</th><th>Cần cài</th></tr></thead><tbody><tr><td>Expo</td><td>Học, MVP, ứng dụng phổ thông, phát triển đa nền tảng nhanh</td><td>Node.js LTS và thiết bị/Expo Go; native IDE có thể bổ sung sau</td></tr><tr><td>Native toolchain</td><td>Module native riêng, tích hợp SDK đặc thù, kiểm soát build local</td><td>Node.js, JDK, Android Studio; Xcode và CocoaPods cho iOS</td></tr></tbody></table>
<h2>Cài Node.js LTS và công cụ cơ bản</h2><p>Nên dùng trình quản lý phiên bản như nvm, fnm hoặc Volta để mỗi dự án có phiên bản Node phù hợp.</p><pre><code>node --version
npm --version
git --version</code></pre><p>Không cài React Native CLI toàn cục; dùng <code>npx</code> để lấy CLI phù hợp với dự án.</p>
<h2>Lộ trình Expo</h2><pre><code>npx create-expo-app@latest my-mobile-app
cd my-mobile-app
npx expo start</code></pre><p>Quét QR bằng thiết bị cùng mạng, hoặc nhấn <code>a</code>/<code>i</code> để mở Android Emulator/iOS Simulator. Khi mạng nội bộ chặn kết nối, thử <code>npx expo start --tunnel</code>.</p>
<h2>Chuẩn bị Android native</h2><ol><li>Cài Android Studio và Android SDK theo tài liệu React Native.</li><li>Trong SDK Manager, cài platform, build-tools, platform-tools và emulator tương thích với dự án.</li><li>Tạo Android Virtual Device trong Device Manager.</li><li>Cấu hình <code>ANDROID_HOME</code> và thêm <code>platform-tools</code> vào PATH.</li><li>Cài JDK theo phiên bản React Native đang dùng; không đoán phiên bản từ hướng dẫn cũ.</li></ol><pre><code>adb --version
adb devices
java -version
npx react-native doctor</code></pre>
<h2>Chuẩn bị iOS trên macOS</h2><p>Build iOS local yêu cầu macOS và Xcode. Sau khi cài Xcode, mở một lần để chấp nhận license và tải component cần thiết. Cài CocoaPods nếu dự án yêu cầu, rồi cài pods:</p><pre><code>xcode-select -p
sudo gem install cocoapods
cd ios &amp;&amp; pod install &amp;&amp; cd ..</code></pre><p>Với máy quản lý Ruby bằng Bundler, ưu tiên <code>bundle exec pod install</code> để đồng nhất phiên bản CocoaPods.</p>
<h2>Tạo dự án không dùng Expo</h2><pre><code>npx @react-native-community/cli@latest init MyApp
cd MyApp
npm start
npm run android
npm run ios</code></pre><p>Kiểm tra tài liệu phiên bản React Native của dự án vì yêu cầu Node, JDK, Gradle, Xcode và CocoaPods thay đổi theo thời gian.</p>
<h2>Kết nối thiết bị thật</h2><ul><li>Android: bật Developer options và USB debugging, xác nhận thiết bị bằng <code>adb devices</code>.</li><li>iOS: chọn Development Team trong Xcode và tin cậy chứng chỉ trên thiết bị.</li><li>Đảm bảo máy và thiết bị truy cập được Metro bundler; firewall/VPN có thể chặn cổng phát triển.</li></ul>
<h2>Biến môi trường và dữ liệu nhạy cảm</h2><p>Expo tự nạp biến có tiền tố <code>EXPO_PUBLIC_</code> vào mã client. Bất kỳ giá trị nào nằm trong bundle mobile đều có thể bị đọc, vì vậy không đặt private API key hoặc secret backend trong ứng dụng.</p>
<h2>Lỗi thường gặp</h2><ul><li><strong>SDK location not found:</strong> kiểm tra <code>ANDROID_HOME</code> và <code>local.properties</code>.</li><li><strong>Không thấy thiết bị:</strong> kiểm tra cáp, quyền USB debugging và <code>adb kill-server</code>/<code>adb start-server</code>.</li><li><strong>Pod lỗi:</strong> xóa cache có chọn lọc, cập nhật repo khi cần và giữ phiên bản bằng Gemfile.</li><li><strong>Metro cache cũ:</strong> dùng <code>npx react-native start --reset-cache</code> hoặc tùy chọn tương ứng của Expo.</li></ul>
<h2>Checklist hoàn tất</h2><ol><li>Dự án cài dependency từ lock file thành công.</li><li>Metro khởi động và ứng dụng mở trên ít nhất một emulator hoặc thiết bị thật.</li><li><code>react-native doctor</code> không còn lỗi bắt buộc.</li><li>Phiên bản Node/JDK được ghi trong README hoặc file quản lý phiên bản.</li><li>Không có secret trong mã client hay repository.</li></ol>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://reactnative.dev/docs/set-up-your-environment" target="_blank" rel="noopener noreferrer">React Native environment setup</a></li><li><a href="https://docs.expo.dev/get-started/create-a-project/" target="_blank" rel="noopener noreferrer">Create an Expo project</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Set Up a React Native Development Environment',
            'slug' => 'set-up-react-native-development-environment',
            'image' => 'cai-dat-moi-truong-react-native.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Set Up a React Native Development Environment',
            'meta_keywords' => 'React Native setup, React Native environment, Expo, Android Studio, Xcode, React Native CLI, Node.js',
            'meta_description' => 'Prepare a React Native environment with Expo or the native toolchain, Android Studio, Xcode, emulators, and physical devices.',
            'tags' => ['React Native', 'Expo', 'Android Studio', 'Xcode', 'Mobile Development', 'JavaScript'],
            'body' => <<<'HTML'
<p><strong>React Native has two common setup paths: start quickly with Expo or install the complete native toolchain for local Android and iOS builds.</strong> Choosing early prevents unnecessary downloads and configuration.</p>
<h2>Expo or native toolchain?</h2><table><thead><tr><th>Path</th><th>Best for</th><th>Required tools</th></tr></thead><tbody><tr><td>Expo</td><td>Learning, MVPs, common app features, rapid cross-platform work</td><td>Node.js LTS and a device/Expo Go; native IDEs can come later</td></tr><tr><td>Native toolchain</td><td>Custom native modules, specialist SDKs, full local build control</td><td>Node.js, JDK, Android Studio; Xcode and CocoaPods for iOS</td></tr></tbody></table>
<h2>Install Node.js LTS</h2><p>Use nvm, fnm, or Volta so projects can pin compatible Node versions.</p><pre><code>node --version
npm --version
git --version</code></pre><p>Do not install a global React Native CLI; use <code>npx</code> to invoke the project-compatible tool.</p>
<h2>The Expo path</h2><pre><code>npx create-expo-app@latest my-mobile-app
cd my-mobile-app
npx expo start</code></pre><p>Scan the QR code on a device, or press <code>a</code>/<code>i</code> for an emulator or simulator. Use <code>npx expo start --tunnel</code> when the local network blocks discovery.</p>
<h2>Prepare Android</h2><ol><li>Install Android Studio and the SDK required by the React Native version.</li><li>Install matching platform, build tools, platform-tools, and emulator packages.</li><li>Create an Android Virtual Device.</li><li>Configure <code>ANDROID_HOME</code> and add <code>platform-tools</code> to PATH.</li><li>Install the JDK required by the project's React Native release.</li></ol><pre><code>adb --version
adb devices
java -version
npx react-native doctor</code></pre>
<h2>Prepare iOS on macOS</h2><p>Local iOS builds require macOS and Xcode. Open Xcode once to accept its license and install components, then install CocoaPods when required:</p><pre><code>xcode-select -p
sudo gem install cocoapods
cd ios &amp;&amp; pod install &amp;&amp; cd ..</code></pre><p>Projects using Bundler should prefer <code>bundle exec pod install</code>.</p>
<h2>Create a project without Expo</h2><pre><code>npx @react-native-community/cli@latest init MyApp
cd MyApp
npm start
npm run android
npm run ios</code></pre><p>Always check the documentation for the exact React Native release because Node, JDK, Gradle, Xcode, and CocoaPods requirements change.</p>
<h2>Connect physical devices</h2><ul><li>Android: enable Developer options and USB debugging, then verify with <code>adb devices</code>.</li><li>iOS: select a Development Team in Xcode and trust the certificate on the device.</li><li>Ensure the device can reach Metro; firewalls and VPNs may block development ports.</li></ul>
<h2>Environment variables and secrets</h2><p>Expo exposes variables prefixed with <code>EXPO_PUBLIC_</code> to client code. Anything bundled into a mobile app can be inspected, so never include private backend credentials.</p>
<h2>Common problems</h2><ul><li>For “SDK location not found,” inspect <code>ANDROID_HOME</code> and <code>local.properties</code>.</li><li>If a device is missing, check the cable, debugging authorization, and restart ADB.</li><li>For CocoaPods failures, use a pinned Gemfile and clear caches selectively.</li><li>For stale Metro state, reset its cache with the CLI's supported option.</li></ul>
<h2>Completion checklist</h2><ol><li>Dependencies install from the lock file.</li><li>Metro starts and the app opens on an emulator or physical device.</li><li><code>react-native doctor</code> reports no required-tool failures.</li><li>Node and JDK versions are documented or pinned.</li><li>No secret is committed or bundled in the client.</li></ol>
<h2>References</h2><ul><li><a href="https://reactnative.dev/docs/set-up-your-environment" target="_blank" rel="noopener noreferrer">React Native environment setup</a></li><li><a href="https://docs.expo.dev/get-started/create-a-project/" target="_blank" rel="noopener noreferrer">Create an Expo project</a></li></ul>
HTML,
        ],
    ],
    'cai-dat-moi-truong-cho-du-an-react-js.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài đặt môi trường cho dự án React JS hiện đại',
            'slug' => 'cai-dat-moi-truong-cho-du-an-react-js',
            'image' => 'cai-dat-moi-truong-react-js.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Cài đặt môi trường React JS hiện đại',
            'meta_keywords' => 'cài React JS, môi trường React, Vite React, Node.js, npm, React TypeScript, Create React App',
            'meta_description' => 'Hướng dẫn cài môi trường React JS với Node.js, Vite và TypeScript, cấu hình biến môi trường, lint, build và lựa chọn framework phù hợp.',
            'tags' => ['React', 'React JS', 'Vite', 'Node.js', 'TypeScript', 'Frontend Development'],
            'body' => <<<'HTML'
<p><strong>React hiện không còn khuyến nghị Create React App cho dự án mới.</strong> Tài liệu chính thức ưu tiên framework cho ứng dụng production; khi cần SPA client-only hoặc học React từ nền tảng, Vite là lựa chọn gọn để dựng môi trường.</p>
<h2>Chọn framework hay Vite</h2><table><thead><tr><th>Nhu cầu</th><th>Khởi đầu phù hợp</th></tr></thead><tbody><tr><td>Ứng dụng cần routing, data fetching, SSR/SSG và code splitting tích hợp</td><td>Framework được React khuyến nghị như Next.js hoặc React Router framework</td></tr><tr><td>SPA nội bộ, widget, bài học hoặc frontend có backend riêng</td><td>Vite với React</td></tr><tr><td>Thêm React vào website hiện có</td><td>Tích hợp dần và dùng build setup hiện hữu hoặc Vite</td></tr></tbody></table>
<h2>Cài Node.js LTS</h2><p>Dùng nvm, fnm hoặc Volta để quản lý phiên bản. Sau khi cài:</p><pre><code>node --version
npm --version
git --version</code></pre><p>Nên commit file lock và dùng cùng package manager trong cả nhóm. Có thể thêm <code>.nvmrc</code> hoặc trường <code>engines</code> để ghi rõ phiên bản.</p>
<h2>Tạo dự án React với Vite</h2><pre><code>npm create vite@latest my-react-app -- --template react-ts
cd my-react-app
npm install
npm run dev</code></pre><p>Template <code>react-ts</code> dùng TypeScript; đổi thành <code>react</code> nếu dự án chọn JavaScript. Terminal sẽ in URL local, thường là <code>http://localhost:5173</code>.</p>
<h2>Hiểu cấu trúc tối thiểu</h2><ul><li><code>src/main.tsx</code>: tạo React root và mount ứng dụng.</li><li><code>src/App.tsx</code>: component gốc mẫu.</li><li><code>public/</code>: tài sản được phục vụ nguyên trạng.</li><li><code>vite.config.ts</code>: plugin, alias, proxy và build configuration.</li><li><code>package.json</code>: scripts và dependency.</li></ul>
<h2>Biến môi trường</h2><p>Vite chỉ đưa biến có tiền tố <code>VITE_</code> vào mã client:</p><pre><code># .env.example
VITE_API_URL=https://api.example.test

// src/config.ts
export const apiUrl = import.meta.env.VITE_API_URL;</code></pre><blockquote>Mọi biến được đưa vào bundle frontend đều có thể bị người dùng đọc. Không lưu private key, mật khẩu database hoặc secret backend trong <code>VITE_*</code>.</blockquote>
<h2>Thiết lập chất lượng mã</h2><p>Giữ ESLint đi kèm template và bổ sung formatter theo convention của nhóm. Các lệnh tối thiểu nên chạy được trong CI:</p><pre><code>npm run lint
npm run build
npm run preview</code></pre><p><code>preview</code> chỉ dùng để kiểm tra build local, không phải production server.</p>
<h2>Proxy API khi phát triển</h2><pre><code>export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
});</code></pre><p>Proxy giúp tránh cấu hình CORS phức tạp ở local, nhưng production vẫn cần domain, reverse proxy và chính sách CORS đúng.</p>
<h2>Cài công cụ trình duyệt</h2><p>React Developer Tools hỗ trợ xem component tree, props, state và profiling. Bên cạnh đó, dùng tab Network và Performance của trình duyệt để kiểm tra request, bundle và render.</p>
<h2>Lỗi thường gặp</h2><ul><li><strong>Unsupported engine:</strong> Node không đúng phiên bản; chuyển phiên bản bằng trình quản lý rồi cài lại dependency.</li><li><strong>Port đã dùng:</strong> Vite chọn cổng khác hoặc truyền <code>--port</code>.</li><li><strong>Biến môi trường undefined:</strong> kiểm tra tiền tố <code>VITE_</code> và khởi động lại dev server.</li><li><strong>Trang trắng khi deploy subfolder:</strong> cấu hình <code>base</code> và route fallback phù hợp.</li><li><strong>Module resolution lỗi:</strong> tránh trộn nhiều lock file và xóa <code>node_modules</code> chỉ sau khi hiểu nguyên nhân.</li></ul>
<h2>Checklist trước khi bắt đầu code</h2><ol><li>Nhóm đã chọn framework hay SPA client-only dựa trên yêu cầu thật.</li><li>Node và package manager được ghi phiên bản.</li><li>Lock file được commit.</li><li>Lint và production build chạy thành công.</li><li><code>.env.example</code> không chứa secret.</li><li>README ghi lệnh cài, chạy, test và build.</li></ol>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://react.dev/learn/installation" target="_blank" rel="noopener noreferrer">React Installation</a></li><li><a href="https://react.dev/learn/build-a-react-app-from-scratch" target="_blank" rel="noopener noreferrer">Build a React app from scratch</a></li><li><a href="https://vite.dev/guide/" target="_blank" rel="noopener noreferrer">Vite Guide</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Set Up a Modern React JS Development Environment',
            'slug' => 'set-up-modern-react-js-development-environment',
            'image' => 'cai-dat-moi-truong-react-js.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Set Up a Modern React JS Environment',
            'meta_keywords' => 'React JS setup, React environment, Vite React, Node.js, npm, React TypeScript, Create React App',
            'meta_description' => 'Set up React JS with Node.js, Vite, and TypeScript; configure environment variables, linting, builds, API proxying, and framework choices.',
            'tags' => ['React', 'React JS', 'Vite', 'Node.js', 'TypeScript', 'Frontend Development'],
            'body' => <<<'HTML'
<p><strong>React no longer recommends Create React App for new projects.</strong> The official guidance favors frameworks for production applications; when a client-only SPA or a learning setup is appropriate, Vite provides a focused starting point.</p>
<h2>Choose a framework or Vite</h2><table><thead><tr><th>Requirement</th><th>Suitable start</th></tr></thead><tbody><tr><td>Integrated routing, data fetching, SSR/SSG, and code splitting</td><td>A recommended framework such as Next.js or React Router framework</td></tr><tr><td>Internal SPA, widget, learning project, or separate backend</td><td>Vite with React</td></tr><tr><td>Add React to an existing website</td><td>Adopt incrementally with the existing build setup or Vite</td></tr></tbody></table>
<h2>Install Node.js LTS</h2><p>Use nvm, fnm, or Volta for version management, then verify:</p><pre><code>node --version
npm --version
git --version</code></pre><p>Commit the lock file and use one package manager across the team. A <code>.nvmrc</code> or <code>engines</code> entry can document the supported Node release.</p>
<h2>Create a React project with Vite</h2><pre><code>npm create vite@latest my-react-app -- --template react-ts
cd my-react-app
npm install
npm run dev</code></pre><p>Use <code>react</code> instead of <code>react-ts</code> for JavaScript. Vite prints the local URL, commonly <code>http://localhost:5173</code>.</p>
<h2>Understand the basic structure</h2><ul><li><code>src/main.tsx</code> creates the React root.</li><li><code>src/App.tsx</code> is the sample root component.</li><li><code>public/</code> contains files served unchanged.</li><li><code>vite.config.ts</code> controls plugins, aliases, proxying, and builds.</li><li><code>package.json</code> defines scripts and dependencies.</li></ul>
<h2>Environment variables</h2><p>Vite only exposes client variables prefixed with <code>VITE_</code>:</p><pre><code># .env.example
VITE_API_URL=https://api.example.test

// src/config.ts
export const apiUrl = import.meta.env.VITE_API_URL;</code></pre><blockquote>Anything bundled into frontend code can be read by users. Never place private keys, database passwords, or backend secrets in <code>VITE_*</code>.</blockquote>
<h2>Code quality</h2><p>Keep the ESLint configuration from the template and add formatting according to the team's conventions. CI should at least run:</p><pre><code>npm run lint
npm run build
npm run preview</code></pre><p><code>preview</code> verifies a local production build; it is not a production server.</p>
<h2>Proxy an API during development</h2><pre><code>export default defineConfig({
  plugins: [react()],
  server: {
    proxy: {
      '/api': 'http://localhost:8000',
    },
  },
});</code></pre><p>A development proxy simplifies local CORS, while production still requires correct domains, reverse proxies, and security policy.</p>
<h2>Browser tooling</h2><p>React Developer Tools exposes the component tree, props, state, and profiler. Combine it with the browser Network and Performance panels for requests, bundles, and rendering behavior.</p>
<h2>Common problems</h2><ul><li><strong>Unsupported engine:</strong> switch to the project's Node version, then reinstall dependencies.</li><li><strong>Port occupied:</strong> allow Vite to choose another port or pass <code>--port</code>.</li><li><strong>Undefined environment variable:</strong> check the <code>VITE_</code> prefix and restart the server.</li><li><strong>Blank page under a subfolder:</strong> configure Vite's <code>base</code> and route fallback.</li><li><strong>Module resolution errors:</strong> do not mix lock files; understand the cause before deleting dependencies.</li></ul>
<h2>Readiness checklist</h2><ol><li>The framework-versus-SPA decision reflects real requirements.</li><li>Node and package-manager versions are documented.</li><li>The lock file is committed.</li><li>Lint and production builds pass.</li><li><code>.env.example</code> contains no secrets.</li><li>The README documents install, development, test, and build commands.</li></ol>
<h2>References</h2><ul><li><a href="https://react.dev/learn/installation" target="_blank" rel="noopener noreferrer">React Installation</a></li><li><a href="https://react.dev/learn/build-a-react-app-from-scratch" target="_blank" rel="noopener noreferrer">Build a React app from scratch</a></li><li><a href="https://vite.dev/guide/" target="_blank" rel="noopener noreferrer">Vite Guide</a></li></ul>
HTML,
        ],
    ],
];
