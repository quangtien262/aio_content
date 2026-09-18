<?php

return [
    'quyen-file-linux-chmod-chown-acl.html' => [
        'vi' => [
            'title' => 'Quyền file trong Linux: Hiểu đúng chmod, chown và ACL',
            'slug' => 'quyen-file-linux-chmod-chown-acl',
            'image' => 'linux-file-permissions.jpg',
            'meta_title' => 'Quyền file Linux: chmod, chown và ACL dễ hiểu',
            'meta_keywords' => 'quyền file Linux, chmod, chown, chgrp, ACL Linux, setfacl, umask, quản trị Linux',
            'meta_description' => 'Hướng dẫn hiểu và quản lý quyền file Linux bằng chmod, chown, chgrp, umask và ACL, kèm ví dụ an toàn cho file và thư mục.',
            'tags' => ['Linux', 'Linux Permissions', 'chmod', 'chown', 'ACL', 'Quản trị hệ thống'],
            'body' => <<<'HTML'
<p><strong>Quyền file là một trong những cơ chế bảo vệ nền tảng của Linux.</strong> Hiểu sai một ký tự trong chuỗi <code>rwx</code> có thể khiến ứng dụng không chạy, người dùng không truy cập được dữ liệu hoặc tệ hơn là mở quyền ghi cho tất cả mọi người.</p>
<p>Bài viết này giải thích mô hình owner-group-others, cách dùng <code>chmod</code>, <code>chown</code>, <code>chgrp</code>, <code>umask</code> và khi nào nên dùng ACL thay vì liên tục thay đổi group.</p>
<h2>Đọc quyền file bằng ls -l</h2>
<pre><code>ls -l /var/www/example
-rw-r----- 1 deploy www-data 2840 Sep 18 10:30 config.php</code></pre>
<p>Ký tự đầu cho biết loại đối tượng: <code>-</code> là file thường, <code>d</code> là thư mục và <code>l</code> là symbolic link. Chín ký tự tiếp theo chia thành ba nhóm:</p>
<ul><li><code>rw-</code>: quyền của owner <code>deploy</code>.</li><li><code>r--</code>: quyền của group <code>www-data</code>.</li><li><code>---</code>: quyền của những người dùng còn lại.</li></ul>
<h2>r, w, x có ý nghĩa khác nhau trên thư mục</h2>
<table><thead><tr><th>Quyền</th><th>Trên file</th><th>Trên thư mục</th></tr></thead><tbody><tr><td><code>r</code></td><td>Đọc nội dung</td><td>Liệt kê tên bên trong</td></tr><tr><td><code>w</code></td><td>Thay đổi nội dung</td><td>Tạo, xóa hoặc đổi tên mục con</td></tr><tr><td><code>x</code></td><td>Thực thi file</td><td>Đi xuyên qua thư mục để truy cập mục con</td></tr></tbody></table>
<p>Một người dùng có thể biết tên file nhờ quyền đọc thư mục nhưng vẫn không mở được file nếu thiếu quyền <code>x</code> trên đường dẫn.</p>
<h2>Dùng chmod dạng ký hiệu</h2>
<p>Dạng ký hiệu thường dễ review hơn vì thể hiện rõ đối tượng và thay đổi:</p>
<pre><code>chmod u+x deploy.sh
chmod g-w config.php
chmod o= private.txt
chmod a+r README.md</code></pre>
<p><code>u</code>, <code>g</code>, <code>o</code>, <code>a</code> lần lượt là owner, group, others và tất cả; <code>+</code> thêm quyền, <code>-</code> bỏ quyền, còn <code>=</code> đặt chính xác quyền mới.</p>
<h2>Dùng chmod dạng số</h2>
<p>Mỗi quyền có giá trị: đọc là 4, ghi là 2, thực thi là 1. Cộng theo từng nhóm owner-group-others:</p>
<ul><li><code>640</code>: owner đọc/ghi, group chỉ đọc, others không có quyền.</li><li><code>750</code>: owner đầy đủ, group đọc/thực thi, others không có quyền.</li><li><code>644</code>: thường dùng cho file công khai chỉ owner được sửa.</li><li><code>755</code>: thường dùng cho thư mục hoặc chương trình cần mọi người truy cập/thực thi.</li></ul>
<pre><code>chmod 640 config.php
chmod 750 scripts</code></pre>
<blockquote>Không dùng <code>chmod -R 777</code> như một cách chữa lỗi quyền. Nó che giấu nguyên nhân và trao quyền ghi/thực thi quá rộng.</blockquote>
<h2>Đổi owner và group với chown, chgrp</h2>
<pre><code>sudo chown deploy app.log
sudo chown deploy:www-data app.log
sudo chgrp www-data storage
sudo chown -R deploy:www-data /var/www/example</code></pre>
<p>Hãy kiểm tra kỹ đường dẫn trước lệnh đệ quy. Với ứng dụng web, chỉ những thư mục thực sự cần ghi như cache hoặc upload mới nên có quyền ghi cho tiến trình web.</p>
<h2>Đặt group kế thừa bằng setgid</h2>
<p>Trên thư mục cộng tác, setgid giúp file mới kế thừa group của thư mục:</p>
<pre><code>sudo chgrp developers /srv/project
sudo chmod 2775 /srv/project</code></pre>
<p>Chữ số <code>2</code> đầu bật setgid. Đây thường là lựa chọn rõ ràng hơn việc sửa group thủ công sau mỗi lần tạo file.</p>
<h2>umask quyết định quyền mặc định</h2>
<pre><code>umask
umask 027</code></pre>
<p><code>umask</code> loại bớt quyền khỏi mức mặc định của file và thư mục. Với <code>027</code>, file thường có xu hướng thành <code>640</code>, thư mục thành <code>750</code>. Cấu hình thực tế còn phụ thuộc shell, service manager và ứng dụng.</p>
<h2>Khi nào cần ACL?</h2>
<p>Mô hình owner-group-others không tiện khi cần cấp riêng cho một người dùng mà không muốn thay owner hoặc group chính. ACL giải quyết trường hợp này:</p>
<pre><code>getfacl report.csv
setfacl -m u:analyst:r report.csv
setfacl -m d:g:developers:rwx /srv/project
setfacl -x u:analyst report.csv</code></pre>
<p>ACL mặc định trên thư mục có thể truyền quyền cho mục mới. Hãy đọc dòng <code>mask</code> trong <code>getfacl</code>, vì mask giới hạn quyền hiệu lực của named users và groups.</p>
<h2>Checklist xử lý lỗi Permission denied</h2>
<ol><li>Dùng <code>namei -l /duong/dan/file</code> để kiểm tra mọi thư mục cha.</li><li>Xác nhận user và group của tiến trình bằng <code>ps</code> hoặc cấu hình service.</li><li>Kiểm tra quyền cơ bản bằng <code>ls -ld</code>.</li><li>Kiểm tra ACL với <code>getfacl</code>.</li><li>Xem mount có cờ <code>ro</code> hoặc <code>noexec</code> hay không.</li><li>Kiểm tra AppArmor/SELinux nếu quyền Unix đã đúng.</li></ol>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/Setting-Permissions.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: Setting Permissions</a></li><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/chmod-invocation.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: chmod</a></li><li><a href="https://man7.org/linux/man-pages/man1/setfacl.1.html" target="_blank" rel="noopener noreferrer">Linux manual: setfacl</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Linux File Permissions: Understanding chmod, chown, and ACLs',
            'slug' => 'linux-file-permissions-chmod-chown-acls',
            'image' => 'linux-file-permissions.jpg',
            'meta_title' => 'Linux File Permissions: chmod, chown, and ACLs',
            'meta_keywords' => 'Linux file permissions, chmod, chown, chgrp, Linux ACL, setfacl, umask, Linux administration',
            'meta_description' => 'Learn how Linux file permissions work and manage them safely with chmod, chown, chgrp, umask, and access control lists.',
            'tags' => ['Linux', 'File Permissions', 'chmod', 'chown', 'ACL', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>File permissions are a foundational Linux security control.</strong> Misreading one character in an <code>rwx</code> string can stop an application, block legitimate users, or expose write access much more broadly than intended.</p>
<h2>Read permissions with ls -l</h2>
<pre><code>ls -l /var/www/example
-rw-r----- 1 deploy www-data 2840 Sep 18 10:30 config.php</code></pre>
<p>The first character describes the object type. The next nine characters are split into permissions for the owner, group, and everyone else.</p>
<h2>Permissions on files and directories</h2>
<table><thead><tr><th>Permission</th><th>Regular file</th><th>Directory</th></tr></thead><tbody><tr><td><code>r</code></td><td>Read content</td><td>List names</td></tr><tr><td><code>w</code></td><td>Modify content</td><td>Create, remove, or rename children</td></tr><tr><td><code>x</code></td><td>Execute</td><td>Traverse the directory</td></tr></tbody></table>
<h2>Use symbolic chmod modes</h2>
<pre><code>chmod u+x deploy.sh
chmod g-w config.php
chmod o= private.txt
chmod a+r README.md</code></pre>
<p>Symbolic modes are easy to review: <code>u</code>, <code>g</code>, <code>o</code>, and <code>a</code> select users; <code>+</code>, <code>-</code>, and <code>=</code> add, remove, or set permissions.</p>
<h2>Use numeric modes</h2>
<p>Read is 4, write is 2, and execute is 1. Values are added for owner, group, and others:</p>
<ul><li><code>640</code>: owner reads/writes, group reads, others have no access.</li><li><code>750</code>: owner has full access, group reads/traverses, others have no access.</li><li><code>644</code>: common for public files writable only by their owner.</li><li><code>755</code>: common for directories and public executables.</li></ul>
<blockquote>Do not use <code>chmod -R 777</code> as a generic permission fix. It hides the underlying ownership problem and grants excessive access.</blockquote>
<h2>Change ownership with chown and chgrp</h2>
<pre><code>sudo chown deploy app.log
sudo chown deploy:www-data app.log
sudo chgrp www-data storage
sudo chown -R deploy:www-data /var/www/example</code></pre>
<p>Review paths before recursive changes. A web process should receive write access only where the application genuinely needs it.</p>
<h2>Inherit a group with setgid</h2>
<pre><code>sudo chgrp developers /srv/project
sudo chmod 2775 /srv/project</code></pre>
<p>The leading <code>2</code> enables setgid so new entries inherit the directory group.</p>
<h2>Use umask for defaults</h2>
<pre><code>umask
umask 027</code></pre>
<p>A umask removes permissions from newly created objects. A mask of <code>027</code> commonly results in files using <code>640</code> and directories using <code>750</code>, although the creating application also matters.</p>
<h2>When should you use ACLs?</h2>
<p>ACLs help when one additional user or group needs access without changing primary ownership:</p>
<pre><code>getfacl report.csv
setfacl -m u:analyst:r report.csv
setfacl -m d:g:developers:rwx /srv/project
setfacl -x u:analyst report.csv</code></pre>
<p>Inspect the ACL mask because it limits the effective permissions of named users and groups.</p>
<h2>Troubleshooting Permission denied</h2>
<ol><li>Inspect every parent directory with <code>namei -l</code>.</li><li>Confirm the process user and groups.</li><li>Review mode bits and ownership.</li><li>Inspect ACLs with <code>getfacl</code>.</li><li>Check for read-only or <code>noexec</code> mounts.</li><li>Review AppArmor or SELinux policy when Unix permissions look correct.</li></ol>
<h2>References</h2><ul><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/Setting-Permissions.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: Setting Permissions</a></li><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/chmod-invocation.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: chmod</a></li><li><a href="https://man7.org/linux/man-pages/man1/setfacl.1.html" target="_blank" rel="noopener noreferrer">Linux manual: setfacl</a></li></ul>
HTML,
        ],
    ],
    'quan-ly-dich-vu-log-linux-systemd-journalctl.html' => [
        'vi' => [
            'title' => 'Quản lý dịch vụ và đọc log Linux với systemd, systemctl, journalctl',
            'slug' => 'quan-ly-dich-vu-log-linux-systemd-journalctl',
            'image' => 'linux-systemd-journalctl.jpg',
            'meta_title' => 'Quản lý dịch vụ Linux với systemd và journalctl',
            'meta_keywords' => 'systemd, systemctl, journalctl, quản lý dịch vụ Linux, log Linux, unit file, Linux server',
            'meta_description' => 'Hướng dẫn quản lý dịch vụ Linux bằng systemctl, đọc và lọc log với journalctl, tạo unit file và xử lý lỗi service thực tế.',
            'tags' => ['Linux', 'systemd', 'systemctl', 'journalctl', 'Linux Server', 'Quản trị hệ thống'],
            'body' => <<<'HTML'
<p><strong>Trên nhiều bản phân phối Linux hiện đại, systemd chịu trách nhiệm khởi động hệ thống, quản lý dịch vụ và thu thập log.</strong> Nắm vững <code>systemctl</code> và <code>journalctl</code> giúp quản trị viên trả lời nhanh ba câu hỏi: dịch vụ có chạy không, vì sao nó dừng và chuyện gì xảy ra trước lỗi.</p>
<h2>Unit trong systemd là gì?</h2>
<p>Systemd quản lý nhiều loại unit. Phổ biến nhất là <code>.service</code> cho dịch vụ, <code>.socket</code> cho socket activation, <code>.timer</code> cho lịch chạy và <code>.mount</code> cho điểm gắn kết. Tên đầy đủ giúp tránh nhầm khi hệ thống có nhiều unit liên quan.</p>
<h2>Kiểm tra trạng thái dịch vụ</h2>
<pre><code>systemctl status nginx.service
systemctl is-active nginx.service
systemctl is-enabled nginx.service
systemctl list-units --type=service --state=failed</code></pre>
<p><code>active</code> cho biết trạng thái hiện tại; <code>enabled</code> cho biết unit có được gắn vào luồng khởi động hay không. Một service có thể đang chạy nhưng chưa enabled, hoặc enabled nhưng đang lỗi.</p>
<h2>Start, stop, restart và reload</h2>
<pre><code>sudo systemctl start nginx
sudo systemctl stop nginx
sudo systemctl restart nginx
sudo systemctl reload nginx</code></pre>
<p><code>restart</code> dừng rồi chạy lại tiến trình, có thể gây gián đoạn. <code>reload</code> yêu cầu dịch vụ nạp cấu hình mới mà không dừng hoàn toàn, nhưng chỉ hoạt động nếu service hỗ trợ.</p>
<h2>Enable không đồng nghĩa với start</h2>
<pre><code>sudo systemctl enable nginx
sudo systemctl enable --now nginx
sudo systemctl disable nginx</code></pre>
<p><code>enable --now</code> vừa cấu hình tự khởi động vừa chạy ngay. Trước khi enable một service mới, cần kiểm tra cấu hình, port lắng nghe và quyền truy cập.</p>
<h2>Đọc log bằng journalctl</h2>
<pre><code>journalctl -u nginx.service
journalctl -u nginx.service -n 100
journalctl -u nginx.service -f
journalctl -u nginx.service --since "today"
journalctl -u nginx.service --since "1 hour ago"</code></pre>
<p><code>-u</code> lọc theo unit, <code>-n</code> lấy số dòng cuối và <code>-f</code> theo dõi log mới. Mốc thời gian giúp thu hẹp log đúng khoảng xảy ra sự cố.</p>
<h2>Lọc theo mức ưu tiên và lần khởi động</h2>
<pre><code>journalctl -p warning..alert
journalctl -b
journalctl -b -1
journalctl --disk-usage</code></pre>
<p><code>-b</code> xem boot hiện tại, <code>-b -1</code> xem boot trước. Đây là cách hữu ích khi máy vừa reboot sau sự cố.</p>
<h2>Quy trình xử lý một service không khởi động</h2>
<ol><li>Chạy <code>systemctl status ten.service</code> để lấy lỗi ngắn và exit code.</li><li>Đọc log đầy đủ với <code>journalctl -u ten.service -b</code>.</li><li>Kiểm tra cấu hình bằng lệnh test riêng của ứng dụng.</li><li>Xác minh user, quyền file, biến môi trường, port và dependency.</li><li>Sửa cấu hình, dùng <code>daemon-reload</code> nếu unit file thay đổi, rồi restart.</li></ol>
<h2>Tạo một service đơn giản</h2>
<pre><code>[Unit]
Description=Example worker
After=network-online.target

[Service]
Type=simple
User=app
WorkingDirectory=/srv/example
ExecStart=/usr/bin/php /srv/example/artisan queue:work
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target</code></pre>
<p>Lưu tại <code>/etc/systemd/system/example-worker.service</code>, sau đó:</p>
<pre><code>sudo systemctl daemon-reload
sudo systemctl enable --now example-worker
systemctl status example-worker</code></pre>
<h2>Override thay vì sửa unit của package</h2>
<pre><code>sudo systemctl edit example-worker.service
systemctl cat example-worker.service</code></pre>
<p>Drop-in override dễ theo dõi và không bị package update ghi đè. Tránh đưa secret trực tiếp vào unit có thể được nhiều người đọc; nên dùng cơ chế quản lý secret và quyền file phù hợp.</p>
<h2>Quản lý dung lượng journal</h2>
<pre><code>journalctl --disk-usage
sudo journalctl --vacuum-time=14d
sudo journalctl --vacuum-size=500M</code></pre>
<p>Đặt chính sách lưu giữ dựa trên nhu cầu điều tra và dung lượng, thay vì xóa log tùy tiện trong lúc đang xử lý sự cố.</p>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemctl.html" target="_blank" rel="noopener noreferrer">systemd: systemctl</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/journalctl.html" target="_blank" rel="noopener noreferrer">systemd: journalctl</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemd.service.html" target="_blank" rel="noopener noreferrer">systemd.service</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Managing Linux Services and Logs with systemd and journalctl',
            'slug' => 'managing-linux-services-and-logs-with-systemd-journalctl',
            'image' => 'linux-systemd-journalctl.jpg',
            'meta_title' => 'Manage Linux Services with systemd and journalctl',
            'meta_keywords' => 'systemd, systemctl, journalctl, Linux service management, Linux logs, unit file, Linux server',
            'meta_description' => 'Learn to manage Linux services with systemctl, filter logs with journalctl, create unit files, and troubleshoot service failures.',
            'tags' => ['Linux', 'systemd', 'systemctl', 'journalctl', 'Linux Server', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>On many modern Linux distributions, systemd starts the system, manages services, and collects logs.</strong> Understanding <code>systemctl</code> and <code>journalctl</code> helps you determine whether a service is running, why it stopped, and what happened before a failure.</p>
<h2>What is a systemd unit?</h2><p>Systemd manages units such as <code>.service</code>, <code>.socket</code>, <code>.timer</code>, and <code>.mount</code>. Service units are the most familiar, but the complete unit name is useful when several activation mechanisms are involved.</p>
<h2>Check service status</h2>
<pre><code>systemctl status nginx.service
systemctl is-active nginx.service
systemctl is-enabled nginx.service
systemctl list-units --type=service --state=failed</code></pre>
<p>Active describes current runtime state, while enabled describes whether the unit participates in startup. These are independent states.</p>
<h2>Start, stop, restart, and reload</h2>
<pre><code>sudo systemctl start nginx
sudo systemctl stop nginx
sudo systemctl restart nginx
sudo systemctl reload nginx</code></pre>
<p>A restart may interrupt requests. Reload asks a service to apply new configuration without a full stop, but only works when the service supports it.</p>
<h2>Enable and start</h2>
<pre><code>sudo systemctl enable nginx
sudo systemctl enable --now nginx
sudo systemctl disable nginx</code></pre>
<p>Before enabling a new service, validate its configuration, listening ports, and access requirements.</p>
<h2>Read logs with journalctl</h2>
<pre><code>journalctl -u nginx.service
journalctl -u nginx.service -n 100
journalctl -u nginx.service -f
journalctl -u nginx.service --since "1 hour ago"</code></pre>
<p>Filter by unit, limit output, follow new events, and narrow the time range around an incident.</p>
<h2>Filter by priority and boot</h2>
<pre><code>journalctl -p warning..alert
journalctl -b
journalctl -b -1
journalctl --disk-usage</code></pre>
<p>The previous boot is particularly useful when a machine restarted after a failure.</p>
<h2>Troubleshoot a failed service</h2>
<ol><li>Read the short status and exit code.</li><li>Inspect the unit journal for the current boot.</li><li>Run the application's configuration test.</li><li>Verify user, permissions, environment, ports, and dependencies.</li><li>Reload systemd after unit changes, then restart and monitor.</li></ol>
<h2>Create a simple service</h2>
<pre><code>[Unit]
Description=Example worker
After=network-online.target

[Service]
Type=simple
User=app
WorkingDirectory=/srv/example
ExecStart=/usr/bin/php /srv/example/artisan queue:work
Restart=on-failure
RestartSec=5

[Install]
WantedBy=multi-user.target</code></pre>
<pre><code>sudo systemctl daemon-reload
sudo systemctl enable --now example-worker
systemctl status example-worker</code></pre>
<h2>Use overrides for packaged units</h2>
<pre><code>sudo systemctl edit example-worker.service
systemctl cat example-worker.service</code></pre>
<p>Drop-in overrides survive package updates more cleanly than editing vendor files. Keep secrets out of broadly readable unit definitions.</p>
<h2>Control journal storage</h2>
<pre><code>journalctl --disk-usage
sudo journalctl --vacuum-time=14d
sudo journalctl --vacuum-size=500M</code></pre>
<p>Choose retention according to investigation needs and available storage instead of deleting logs reactively during an incident.</p>
<h2>References</h2><ul><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemctl.html" target="_blank" rel="noopener noreferrer">systemd: systemctl</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/journalctl.html" target="_blank" rel="noopener noreferrer">systemd: journalctl</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemd.service.html" target="_blank" rel="noopener noreferrer">systemd.service</a></li></ul>
HTML,
        ],
    ],
    'checklist-bao-mat-ubuntu-server.html' => [
        'vi' => [
            'title' => 'Checklist bảo mật Ubuntu Server sau khi cài đặt',
            'slug' => 'checklist-bao-mat-ubuntu-server',
            'image' => 'ubuntu-server-hardening.jpg',
            'meta_title' => 'Checklist bảo mật Ubuntu Server sau cài đặt',
            'meta_keywords' => 'bảo mật Ubuntu Server, hardening Linux, SSH key, UFW firewall, unattended upgrades, AppArmor, audit server',
            'meta_description' => 'Checklist thực tế để tăng cường bảo mật Ubuntu Server: cập nhật, tài khoản, SSH, firewall, dịch vụ, AppArmor, log, backup và kiểm thử.',
            'tags' => ['Ubuntu Server', 'Linux Security', 'Server Hardening', 'SSH', 'Firewall', 'Quản trị hệ thống'],
            'body' => <<<'HTML'
<p><strong>Cài đặt xong hệ điều hành mới chỉ là điểm bắt đầu.</strong> Một Ubuntu Server an toàn cần được cấu hình theo workload, bề mặt mạng, dữ liệu và khả năng vận hành của tổ chức. Hardening luôn có đánh đổi; thay đổi quá mạnh mà không kiểm thử có thể tự khóa quyền quản trị hoặc làm ứng dụng ngừng hoạt động.</p>
<blockquote>Thực hiện qua console hoặc giữ một phiên SSH dự phòng khi thay đổi SSH/firewall. Kiểm tra đăng nhập ở phiên mới trước khi đóng phiên quản trị hiện tại.</blockquote>
<h2>1. Cập nhật hệ thống và xác định vòng đời hỗ trợ</h2>
<pre><code>sudo apt update
sudo apt full-upgrade
sudo reboot</code></pre>
<p>Ưu tiên bản Ubuntu LTS còn được hỗ trợ. Theo dõi thông báo reboot, cập nhật kernel và security notices. Không thêm repository bên thứ ba nếu chưa đánh giá nguồn, khóa ký và nhu cầu duy trì.</p>
<h2>2. Tạo tài khoản quản trị riêng</h2>
<pre><code>sudo adduser deploy
sudo usermod -aG sudo deploy
id deploy</code></pre>
<p>Ubuntu mặc định vô hiệu hóa đăng nhập trực tiếp bằng mật khẩu root và dùng <code>sudo</code> để nâng quyền có kiểm soát. Mỗi quản trị viên nên có tài khoản riêng để audit rõ ràng.</p>
<h2>3. Dùng SSH key và kiểm tra trước khi tắt mật khẩu</h2>
<pre><code>ssh-keygen -t ed25519
ssh-copy-id deploy@server
ssh deploy@server</code></pre>
<p>Sau khi xác nhận key hoạt động, tạo drop-in cấu hình phù hợp trong <code>/etc/ssh/sshd_config.d/</code>:</p>
<pre><code>PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes</code></pre>
<pre><code>sudo sshd -t
sudo systemctl reload ssh</code></pre>
<p>Chỉ tắt mật khẩu khi tất cả quản trị viên có key và có phương án console/recovery. Bảo vệ private key bằng passphrase và chính sách thiết bị.</p>
<h2>4. Chỉ mở port cần thiết bằng firewall</h2>
<pre><code>sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose</code></pre>
<p>Điều chỉnh theo workload. Database nội bộ thường không nên mở ra Internet; hãy giới hạn source network hoặc dùng private network/VPN.</p>
<h2>5. Giảm bề mặt tấn công</h2>
<pre><code>systemctl list-unit-files --state=enabled
ss -lntup
sudo apt autoremove</code></pre>
<p>Vô hiệu hóa dịch vụ không dùng, gỡ package không cần và xác minh từng port lắng nghe có owner rõ ràng. Không dừng service chỉ vì tên lạ; cần hiểu dependency trước.</p>
<h2>6. Bật cập nhật bảo mật tự động có giám sát</h2>
<pre><code>sudo apt install unattended-upgrades
sudo dpkg-reconfigure unattended-upgrades</code></pre>
<p>Tự động cập nhật giúp giảm thời gian phơi nhiễm nhưng vẫn cần theo dõi lỗi, yêu cầu reboot và kiểm thử trên môi trường staging với hệ thống quan trọng.</p>
<h2>7. Giữ AppArmor hoạt động</h2>
<pre><code>sudo aa-status
systemctl status apparmor</code></pre>
<p>AppArmor giới hạn hành vi của ứng dụng bằng profile. Khi gặp lỗi, đọc log và sửa profile theo nhu cầu thực tế thay vì tắt toàn bộ lớp bảo vệ.</p>
<h2>8. Bảo vệ file, secret và dữ liệu</h2>
<ul><li>Dùng quyền tối thiểu cho file cấu hình và private key.</li><li>Không lưu secret trong repository hoặc command history.</li><li>Mã hóa dữ liệu nhạy cảm và backup.</li><li>Tách tài khoản chạy ứng dụng khỏi tài khoản quản trị.</li><li>Đặt owner/group rõ ràng cho thư mục upload, log và cache.</li></ul>
<h2>9. Thiết lập log, thời gian và giám sát</h2>
<pre><code>timedatectl status
journalctl -p warning..alert -b
last
lastb</code></pre>
<p>Đồng bộ thời gian là điều kiện để điều tra log chính xác. Theo dõi đăng nhập thất bại, thay đổi tài khoản, service lỗi, dung lượng đĩa, tải hệ thống và certificate sắp hết hạn. Với hệ thống quan trọng, chuyển log sang nơi lưu trữ tách biệt.</p>
<h2>10. Backup và diễn tập khôi phục</h2>
<p>Backup phải bao gồm dữ liệu, cấu hình, khóa cần thiết và hướng dẫn phục hồi. Giữ ít nhất một bản tách khỏi credential production, kiểm tra checksum và khôi phục thử định kỳ.</p>
<h2>11. Kiểm tra lại sau hardening</h2>
<ul><li>Đăng nhập được bằng tài khoản/key dự kiến.</li><li>Chỉ port cần thiết có thể truy cập từ đúng mạng.</li><li>Ứng dụng, cron, queue và backup vẫn hoạt động.</li><li>Update, log rotation, cảnh báo và reboot được kiểm soát.</li><li>Có tài liệu thay đổi và đường rollback.</li></ul>
<h2>Hardening là một quá trình</h2>
<p>Checklist ban đầu không thay thế quản lý lỗ hổng, review định kỳ và ứng phó sự cố. Hãy xác định baseline phù hợp, tự động kiểm tra cấu hình và cập nhật nó khi workload hoặc mối đe dọa thay đổi.</p>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://ubuntu.com/server/docs/how-to/security/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Security</a></li><li><a href="https://ubuntu.com/server/docs/how-to/security/user-management/" target="_blank" rel="noopener noreferrer">Ubuntu Server: User management</a></li><li><a href="https://documentation.ubuntu.com/security/" target="_blank" rel="noopener noreferrer">Ubuntu Security documentation</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Ubuntu Server Security Checklist After Installation',
            'slug' => 'ubuntu-server-security-checklist-after-installation',
            'image' => 'ubuntu-server-hardening.jpg',
            'meta_title' => 'Ubuntu Server Security Checklist After Installation',
            'meta_keywords' => 'Ubuntu Server security, Linux hardening, SSH keys, UFW firewall, unattended upgrades, AppArmor, server audit',
            'meta_description' => 'A practical Ubuntu Server hardening checklist covering updates, accounts, SSH, firewall, services, AppArmor, logging, backups, and validation.',
            'tags' => ['Ubuntu Server', 'Linux Security', 'Server Hardening', 'SSH', 'Firewall', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>Installing the operating system is only the beginning.</strong> A secure Ubuntu Server must be configured for its workload, network exposure, data, and operating model. Hardening involves tradeoffs; untested changes can lock out administrators or stop applications.</p>
<blockquote>Keep console access or an existing SSH session while changing SSH and firewall settings. Confirm a new session works before closing the current one.</blockquote>
<h2>1. Update and verify the support lifecycle</h2>
<pre><code>sudo apt update
sudo apt full-upgrade
sudo reboot</code></pre>
<p>Use a supported Ubuntu LTS release where appropriate. Track kernel updates, reboot requirements, and security notices. Add third-party repositories only after assessing their source, signing keys, and maintenance plan.</p>
<h2>2. Create individual administrator accounts</h2>
<pre><code>sudo adduser deploy
sudo usermod -aG sudo deploy
id deploy</code></pre>
<p>Ubuntu disables direct password login for root by default and uses <code>sudo</code> for controlled elevation. Separate accounts improve accountability.</p>
<h2>3. Use SSH keys before disabling passwords</h2>
<pre><code>ssh-keygen -t ed25519
ssh-copy-id deploy@server
ssh deploy@server</code></pre>
<p>After testing key access, use an SSH configuration drop-in:</p>
<pre><code>PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes</code></pre>
<pre><code>sudo sshd -t
sudo systemctl reload ssh</code></pre>
<p>Disable passwords only when every administrator has working keys and a recovery path. Protect private keys with passphrases and device controls.</p>
<h2>4. Allow only required network traffic</h2>
<pre><code>sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status verbose</code></pre>
<p>Adapt these rules to the workload. Internal databases should normally be restricted to private networks or explicit source ranges.</p>
<h2>5. Reduce attack surface</h2>
<pre><code>systemctl list-unit-files --state=enabled
ss -lntup
sudo apt autoremove</code></pre>
<p>Disable unused services, remove unnecessary packages, and assign ownership for every listening port. Understand dependencies before changing unfamiliar services.</p>
<h2>6. Configure monitored security updates</h2>
<pre><code>sudo apt install unattended-upgrades
sudo dpkg-reconfigure unattended-upgrades</code></pre>
<p>Automation reduces exposure, but monitor failures and reboot requirements. Test important updates in staging for critical systems.</p>
<h2>7. Keep AppArmor enabled</h2>
<pre><code>sudo aa-status
systemctl status apparmor</code></pre>
<p>AppArmor confines applications with profiles. Diagnose denials and refine a profile rather than disabling the protection globally.</p>
<h2>8. Protect files, secrets, and data</h2>
<ul><li>Apply least privilege to configuration files and private keys.</li><li>Keep secrets out of repositories and shell history.</li><li>Encrypt sensitive data and backups.</li><li>Separate application identities from administrators.</li><li>Define ownership for upload, log, and cache directories.</li></ul>
<h2>9. Configure logs, time, and monitoring</h2>
<pre><code>timedatectl status
journalctl -p warning..alert -b
last
lastb</code></pre>
<p>Accurate time is essential for investigations. Monitor failed logins, account changes, service failures, disk capacity, load, and certificate expiry. Important systems should forward logs to separate storage.</p>
<h2>10. Back up and rehearse recovery</h2>
<p>Back up data, configuration, required keys, and recovery instructions. Keep at least one copy isolated from production credentials, verify checksums, and perform regular restore exercises.</p>
<h2>11. Validate after hardening</h2>
<ul><li>Expected users can log in with their keys.</li><li>Only required ports are reachable from approved networks.</li><li>Applications, scheduled jobs, queues, and backups still work.</li><li>Updates, log rotation, alerts, and reboots are controlled.</li><li>Changes and rollback steps are documented.</li></ul>
<h2>Hardening is continuous</h2>
<p>An initial checklist does not replace vulnerability management, periodic review, and incident response. Define a suitable baseline, test it automatically, and update it as workloads and threats change.</p>
<h2>References</h2><ul><li><a href="https://ubuntu.com/server/docs/how-to/security/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Security</a></li><li><a href="https://ubuntu.com/server/docs/how-to/security/user-management/" target="_blank" rel="noopener noreferrer">Ubuntu Server: User management</a></li><li><a href="https://documentation.ubuntu.com/security/" target="_blank" rel="noopener noreferrer">Ubuntu Security documentation</a></li></ul>
HTML,
        ],
    ],
];
