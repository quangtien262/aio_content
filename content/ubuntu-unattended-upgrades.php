<?php

return [
    'ubuntu-unattended-upgrades.html' => [
        'vi' => [
            'title' => 'Cập nhật bảo mật Ubuntu Server tự động với unattended-upgrades',
            'slug' => 'cap-nhat-bao-mat-ubuntu-server-tu-dong-unattended-upgrades',
            'image' => 'ubuntu-unattended-upgrades.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Cấu hình unattended-upgrades an toàn trên Ubuntu Server',
            'meta_keywords' => 'unattended-upgrades, cập nhật bảo mật Ubuntu, Ubuntu Server, apt-daily-upgrade, tự động cập nhật Linux, automatic reboot Ubuntu',
            'meta_description' => 'Hướng dẫn cấu hình unattended-upgrades trên Ubuntu Server: chỉ nhận bản vá bảo mật, thử dry-run, kiểm tra timer và log, quản lý reboot an toàn.',
            'tags' => ['Ubuntu', 'Linux', 'Security Updates', 'unattended-upgrades', 'APT', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>Máy chủ không được cập nhật là rủi ro bảo mật; cập nhật thiếu kiểm soát lại có thể gây gián đoạn.</strong> <code>unattended-upgrades</code> giúp Ubuntu Server tự cài bản vá theo chính sách, nhưng production vẫn cần giới hạn nguồn package, kiểm tra trước, giám sát kết quả và tách việc reboot thành một quyết định vận hành.</p>
<p>Bài viết này xây dựng cấu hình thực tế cho Ubuntu Server: ưu tiên security update, không tự khởi động lại, dùng tệp drop-in dễ quản lý, thử bằng <code>--dry-run</code> và triển khai theo từng nhóm máy.</p>

<h2>unattended-upgrades làm gì?</h2>
<p><code>unattended-upgrades</code> là backend của cơ chế APT periodic. Trên các bản Ubuntu hiện đại, systemd kích hoạt công việc qua <code>apt-daily.timer</code> và <code>apt-daily-upgrade.timer</code>. Công cụ chỉ cài package từ những nguồn được phép trong cấu hình; nó không thay thế việc nâng cấp phiên bản Ubuntu bằng <code>do-release-upgrade</code>.</p>
<p>Ubuntu đã cài và bật cơ chế này mặc định trên Desktop và Server từ 18.04 LTS. Tuy vậy, quản trị viên vẫn nên kiểm tra trạng thái thực tế vì image cloud, template nội bộ hoặc công cụ cấu hình có thể đã thay đổi mặc định.</p>

<h2>1. Kiểm tra phiên bản và trạng thái hiện tại</h2>
<pre><code>lsb_release -a
apt-cache policy unattended-upgrades
systemctl status apt-daily.timer apt-daily-upgrade.timer
systemctl list-timers 'apt-*'</code></pre>
<p>Timer ở trạng thái <code>active (waiting)</code> nghĩa là systemd đang chờ lần chạy tiếp theo. Thời điểm có thể có độ trễ ngẫu nhiên để nhiều máy không đồng loạt tải update.</p>
<p>Kiểm tra APT periodic đang được bật hay chưa:</p>
<pre><code>apt-config dump | grep -iE 'APT::Periodic|Unattended-Upgrade'</code></pre>

<h2>2. Cài và bật dịch vụ</h2>
<pre><code>sudo apt update
sudo apt install unattended-upgrades
sudo dpkg-reconfigure unattended-upgrades</code></pre>
<p>Lệnh cuối tạo hoặc kích hoạt cấu hình periodic. Có thể kiểm tra tệp <code>/etc/apt/apt.conf.d/20auto-upgrades</code>:</p>
<pre><code>APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";</code></pre>
<p>Giá trị <code>1</code> biểu thị chu kỳ theo ngày. Timer systemd quyết định thời điểm chạy thực tế.</p>

<h2>3. Dùng tệp drop-in, không sửa cấu hình gốc</h2>
<p>Ubuntu khuyến nghị không chỉnh trực tiếp <code>50unattended-upgrades</code>, vì package update có thể tạo xung đột cấu hình. Hãy tạo tệp có thứ tự cao hơn, ví dụ:</p>
<pre><code>sudo nano /etc/apt/apt.conf.d/60local-unattended-upgrades</code></pre>
<p>Cấu hình production thận trọng:</p>
<pre><code>Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
};

Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";
Unattended-Upgrade::Remove-New-Unused-Dependencies "true";
Unattended-Upgrade::SyslogEnable "true";</code></pre>
<p><code>${distro_id}</code> và <code>${distro_codename}</code> được APT thay bằng giá trị của máy. Chỉ cho phép pocket <code>-security</code> giúp phạm vi thay đổi dễ dự đoán. Không nên tự thêm PPA hoặc repository bên thứ ba vào danh sách nếu chưa có quy trình kiểm thử và rollback riêng.</p>

<h2>4. Có nên tự động reboot?</h2>
<p>Kernel, libc và một số thành phần nền có thể cần reboot để dùng bản vá mới. Nhưng tự reboot một máy production đơn lẻ có thể làm rớt request, ngắt job hoặc mất quorum.</p>
<p>Cấu hình ở trên để <code>Automatic-Reboot "false"</code>. Sau cập nhật, kiểm tra:</p>
<pre><code>test -f /var/run/reboot-required &amp;&amp; cat /var/run/reboot-required
cat /var/run/reboot-required.pkgs 2&gt;/dev/null</code></pre>
<p>Nếu hệ thống có load balancer và nhiều node, quy trình tốt hơn là: rút một node khỏi traffic, chờ kết nối cạn, reboot, health check, đưa node trở lại rồi mới chuyển sang node kế tiếp.</p>
<p>Chỉ bật tự reboot khi workload chịu được và đã có maintenance window:</p>
<pre><code>Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-WithUsers "false";
Unattended-Upgrade::Automatic-Reboot-Time "03:30";</code></pre>
<blockquote>Giờ reboot dùng timezone của hệ điều hành. Kiểm tra bằng <code>timedatectl</code>, đặc biệt với cloud image mặc định UTC.</blockquote>

<h2>5. Chặn package nhạy cảm khi cần</h2>
<p>Blacklist chỉ nên là biện pháp tạm thời, có ticket và ngày hết hạn. Giữ bản vá bảo mật quá lâu có thể tạo lỗ hổng lớn hơn rủi ro tương thích.</p>
<pre><code>Unattended-Upgrade::Package-Blacklist {
    // "mysql-server";
    // "docker-ce";
};</code></pre>
<p>Chuỗi được hiểu như biểu thức chính quy. Vì vậy cần xem trước danh sách package thực tế, không dùng pattern quá rộng.</p>

<h2>6. Kiểm tra cấu hình trước khi chạy thật</h2>
<pre><code>sudo unattended-upgrade --dry-run --debug
sudo apt-get -s upgrade
apt-config dump | less</code></pre>
<p><code>--dry-run</code> mô phỏng lựa chọn package nhưng không cài đặt. Đọc kỹ origin, package bị giữ lại, dependency và lỗi cấu hình. Sau khi kiểm tra, có thể chạy thủ công một lần:</p>
<pre><code>sudo unattended-upgrade --verbose</code></pre>
<p>Không chạy đồng thời với <code>apt</code>, <code>dpkg</code> hoặc pipeline provisioning khác. Nếu gặp lỗi lock, hãy tìm tiến trình đang giữ lock thay vì xóa file lock.</p>

<h2>7. Theo dõi timer, journal và log</h2>
<pre><code>systemctl list-timers 'apt-*'
systemctl status apt-daily-upgrade.service
journalctl -u apt-daily-upgrade.service --since today
sudo tail -n 200 /var/log/unattended-upgrades/unattended-upgrades.log
sudo tail -n 200 /var/log/unattended-upgrades/unattended-upgrades-dpkg.log</code></pre>
<p>Log chính ghi package được xét và kết quả; log <code>dpkg</code> ghi chi tiết quá trình cài. Nên cảnh báo khi timer không chạy trong thời gian mong đợi, update thất bại, còn bản vá security quá hạn hoặc có <code>reboot-required</code> kéo dài.</p>

<h2>8. Thiết kế rollout cho nhiều máy chủ</h2>
<p>Không nên áp cấu hình mới đồng loạt lên toàn bộ fleet. Một quy trình bền vững:</p>
<ol>
<li><strong>Canary:</strong> chọn một máy ít quan trọng nhưng có workload đại diện.</li>
<li><strong>Quan sát:</strong> theo dõi service health, error rate, latency và log sau cập nhật.</li>
<li><strong>Theo nhóm:</strong> mở rộng sang staging, một phần production rồi toàn bộ fleet.</li>
<li><strong>Reboot cuốn chiếu:</strong> duy trì capacity và quorum trong suốt maintenance.</li>
<li><strong>Báo cáo:</strong> lưu package/version đã đổi, máy thất bại và thời điểm hoàn tất.</li>
</ol>
<p>Snapshot VM có thể hỗ trợ phục hồi nhưng không thay thế backup đã kiểm chứng. Với database, cần bảo đảm consistency và thử restore trước khi xem snapshot là phương án rollback.</p>

<h2>9. Những lỗi cấu hình thường gặp</h2>
<ul>
<li><strong>Tưởng timer chạy đúng giờ tuyệt đối:</strong> APT có thể thêm randomized delay.</li>
<li><strong>Bật reboot trên máy duy nhất:</strong> bản vá thành downtime không báo trước.</li>
<li><strong>Cho phép mọi repository:</strong> package bên thứ ba có vòng đời và chất lượng khác archive Ubuntu.</li>
<li><strong>Sửa tệp 50unattended-upgrades:</strong> khó quản lý khi package nâng cấp; dùng drop-in riêng.</li>
<li><strong>Không đọc log:</strong> “đã bật” không đồng nghĩa “đã cập nhật thành công”.</li>
<li><strong>Nhầm package update với release upgrade:</strong> công cụ này không nâng Ubuntu 22.04 lên 24.04.</li>
</ul>

<h2>Checklist production</h2>
<ul>
<li>Backup và quy trình restore đã được kiểm tra.</li>
<li>Nguồn package được giới hạn, PPA được kiểm kê.</li>
<li>Dry-run sạch trên canary có workload đại diện.</li>
<li>Tự reboot mặc định tắt hoặc có maintenance window rõ ràng.</li>
<li>Có giám sát timer, log, bản vá tồn đọng và reboot-required.</li>
<li>Cụm nhiều node được cập nhật cuốn chiếu, giữ quorum và capacity.</li>
<li>Có người sở hữu blacklist và ngày gỡ bỏ ngoại lệ.</li>
</ul>

<h2>Kết luận</h2>
<p><code>unattended-upgrades</code> hiệu quả nhất khi là một phần của quy trình quản trị bản vá, không phải công tắc “bật rồi quên”. Hãy giới hạn origin, cấu hình bằng drop-in, thử trên canary, tách reboot khỏi cài package và biến log thành cảnh báo có người chịu trách nhiệm. Khi đó bản vá đến đủ nhanh mà production vẫn giữ được khả năng kiểm soát.</p>

<h2>Tài liệu tham khảo</h2>
<ul>
<li><a href="https://documentation.ubuntu.com/security/security-updates/" target="_blank" rel="noopener noreferrer">Ubuntu Security Documentation: Security updates</a></li>
<li><a href="https://manpages.ubuntu.com/manpages/jammy/man8/unattended-upgrade.8.html" target="_blank" rel="noopener noreferrer">Ubuntu manpage: unattended-upgrade</a></li>
<li><a href="https://documentation.ubuntu.com/server/explanation/software/about-apt-upgrade-and-phased-updates/" target="_blank" rel="noopener noreferrer">Ubuntu Server: APT upgrade and phased updates</a></li>
</ul>
HTML,
        ],
        'en' => [
            'title' => 'Automatic Ubuntu Server Security Updates with unattended-upgrades',
            'slug' => 'automatic-ubuntu-server-security-updates-unattended-upgrades',
            'image' => 'ubuntu-unattended-upgrades.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Configure unattended-upgrades Safely on Ubuntu Server',
            'meta_keywords' => 'unattended-upgrades, Ubuntu security updates, Ubuntu Server, apt-daily-upgrade, automatic Linux updates, Ubuntu automatic reboot',
            'meta_description' => 'Configure unattended-upgrades on Ubuntu Server for security-only patches, dry-run validation, timer and log monitoring, and controlled reboots.',
            'tags' => ['Ubuntu', 'Linux', 'Security Updates', 'unattended-upgrades', 'APT', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>An unpatched server is a security risk, but uncontrolled updates can cause an outage.</strong> <code>unattended-upgrades</code> lets Ubuntu Server install patches according to policy. Production environments still need restricted package sources, preflight validation, outcome monitoring, and a deliberate reboot process.</p>
<p>This guide builds a practical Ubuntu Server policy: prioritize security updates, avoid automatic reboots, keep customization in a manageable drop-in file, test with <code>--dry-run</code>, and roll out changes in stages.</p>

<h2>What does unattended-upgrades do?</h2>
<p><code>unattended-upgrades</code> is the backend for APT periodic updates. On modern Ubuntu systems, systemd schedules the work through <code>apt-daily.timer</code> and <code>apt-daily-upgrade.timer</code>. It installs packages only from origins allowed by policy. It does not perform an Ubuntu release upgrade, which is a separate <code>do-release-upgrade</code> workflow.</p>
<p>Ubuntu has included and enabled automatic security updates by default on Desktop and Server since 18.04 LTS. Still, verify the actual state because cloud images, internal templates, or configuration management may change the defaults.</p>

<h2>1. Inspect the current state</h2>
<pre><code>lsb_release -a
apt-cache policy unattended-upgrades
systemctl status apt-daily.timer apt-daily-upgrade.timer
systemctl list-timers 'apt-*'</code></pre>
<p>A timer shown as <code>active (waiting)</code> is waiting for its next run. A randomized delay may be added so a fleet does not download updates at the same moment.</p>
<pre><code>apt-config dump | grep -iE 'APT::Periodic|Unattended-Upgrade'</code></pre>

<h2>2. Install and enable it</h2>
<pre><code>sudo apt update
sudo apt install unattended-upgrades
sudo dpkg-reconfigure unattended-upgrades</code></pre>
<p>The last command creates or enables the periodic configuration. Check <code>/etc/apt/apt.conf.d/20auto-upgrades</code>:</p>
<pre><code>APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";</code></pre>
<p>The value <code>1</code> represents a daily interval; systemd timers determine the effective execution time.</p>

<h2>3. Use a drop-in instead of editing the packaged file</h2>
<p>Ubuntu advises against modifying <code>50unattended-upgrades</code> directly because package updates can create configuration conflicts. Create a higher-priority file:</p>
<pre><code>sudo nano /etc/apt/apt.conf.d/60local-unattended-upgrades</code></pre>
<p>A conservative production policy:</p>
<pre><code>Unattended-Upgrade::Allowed-Origins {
    "${distro_id}:${distro_codename}-security";
};

Unattended-Upgrade::Automatic-Reboot "false";
Unattended-Upgrade::Remove-Unused-Kernel-Packages "true";
Unattended-Upgrade::Remove-New-Unused-Dependencies "true";
Unattended-Upgrade::SyslogEnable "true";</code></pre>
<p>APT expands <code>${distro_id}</code> and <code>${distro_codename}</code> for the host. Limiting updates to the <code>-security</code> pocket keeps the scope predictable. Do not automatically allow PPAs or third-party repositories without their own test and rollback process.</p>

<h2>4. Should the server reboot automatically?</h2>
<p>Kernel, libc, and other foundational updates may require a reboot before the running system uses the fix. An automatic reboot on a single production server, however, can drop requests, interrupt jobs, or break quorum.</p>
<p>The policy above keeps <code>Automatic-Reboot "false"</code>. Detect pending reboots with:</p>
<pre><code>test -f /var/run/reboot-required &amp;&amp; cat /var/run/reboot-required
cat /var/run/reboot-required.pkgs 2&gt;/dev/null</code></pre>
<p>For a load-balanced service, drain one node, wait for connections to finish, reboot, pass health checks, return it to service, and only then proceed to the next node.</p>
<p>Enable automatic reboot only when the workload tolerates it and a maintenance window exists:</p>
<pre><code>Unattended-Upgrade::Automatic-Reboot "true";
Unattended-Upgrade::Automatic-Reboot-WithUsers "false";
Unattended-Upgrade::Automatic-Reboot-Time "03:30";</code></pre>
<blockquote>The reboot time uses the operating system timezone. Verify it with <code>timedatectl</code>, especially on cloud images that default to UTC.</blockquote>

<h2>5. Temporarily block sensitive packages</h2>
<p>A blacklist should be temporary, owned, and have an expiry date. Deferring a security fix indefinitely can create more risk than a compatibility issue.</p>
<pre><code>Unattended-Upgrade::Package-Blacklist {
    // "mysql-server";
    // "docker-ce";
};</code></pre>
<p>Entries are interpreted as regular expressions, so inspect the package selection and avoid overly broad patterns.</p>

<h2>6. Validate before applying changes</h2>
<pre><code>sudo unattended-upgrade --dry-run --debug
sudo apt-get -s upgrade
apt-config dump | less</code></pre>
<p><code>--dry-run</code> simulates package selection without installing anything. Review origins, held packages, dependencies, and configuration errors. After a clean test, trigger one observed run:</p>
<pre><code>sudo unattended-upgrade --verbose</code></pre>
<p>Do not run it alongside another <code>apt</code>, <code>dpkg</code>, or provisioning process. If a lock error occurs, identify the process holding the lock rather than deleting lock files.</p>

<h2>7. Monitor timers, the journal, and logs</h2>
<pre><code>systemctl list-timers 'apt-*'
systemctl status apt-daily-upgrade.service
journalctl -u apt-daily-upgrade.service --since today
sudo tail -n 200 /var/log/unattended-upgrades/unattended-upgrades.log
sudo tail -n 200 /var/log/unattended-upgrades/unattended-upgrades-dpkg.log</code></pre>
<p>The main log records package selection and results; the dpkg log records installation details. Alert when the timer has not run within the expected interval, an upgrade fails, overdue security patches remain, or a reboot requirement persists.</p>

<h2>8. Design a fleet rollout</h2>
<p>Do not deploy a new update policy to every server at once. Use a staged process:</p>
<ol>
<li><strong>Canary:</strong> select a low-impact host with a representative workload.</li>
<li><strong>Observe:</strong> monitor service health, error rate, latency, and logs.</li>
<li><strong>Expand in cohorts:</strong> proceed through staging, part of production, and then the fleet.</li>
<li><strong>Rolling reboot:</strong> preserve capacity and quorum throughout maintenance.</li>
<li><strong>Report:</strong> record changed package versions, failed hosts, and completion times.</li>
</ol>
<p>A VM snapshot can support recovery but does not replace a verified backup. For databases, confirm consistency and test restoration before treating a snapshot as a rollback plan.</p>

<h2>9. Common configuration mistakes</h2>
<ul>
<li><strong>Expecting an exact timer:</strong> APT may apply a randomized delay.</li>
<li><strong>Auto-rebooting a single server:</strong> a patch becomes an unannounced outage.</li>
<li><strong>Allowing every repository:</strong> third-party packages have different release and support practices.</li>
<li><strong>Editing 50unattended-upgrades:</strong> package upgrades become harder to manage; use a drop-in.</li>
<li><strong>Ignoring logs:</strong> enabled does not mean successfully updated.</li>
<li><strong>Confusing package and release upgrades:</strong> this tool does not move Ubuntu 22.04 to 24.04.</li>
</ul>

<h2>Production checklist</h2>
<ul>
<li>Backups and the restore process have been tested.</li>
<li>Package origins are restricted and PPAs are inventoried.</li>
<li>The dry run succeeds on a representative canary.</li>
<li>Automatic reboot is disabled by default or has an explicit maintenance window.</li>
<li>Timers, logs, pending security fixes, and reboot requirements are monitored.</li>
<li>Multi-node systems update in rolling cohorts while preserving quorum and capacity.</li>
<li>Every blacklist exception has an owner and removal date.</li>
</ul>

<h2>Conclusion</h2>
<p><code>unattended-upgrades</code> works best as one part of a patch-management process, not a switch to turn on and forget. Restrict origins, use a drop-in, validate on a canary, separate reboot decisions from package installation, and turn logs into owned alerts. This delivers security patches promptly while keeping production controlled.</p>

<h2>References</h2>
<ul>
<li><a href="https://documentation.ubuntu.com/security/security-updates/" target="_blank" rel="noopener noreferrer">Ubuntu Security Documentation: Security updates</a></li>
<li><a href="https://manpages.ubuntu.com/manpages/jammy/man8/unattended-upgrade.8.html" target="_blank" rel="noopener noreferrer">Ubuntu manpage: unattended-upgrade</a></li>
<li><a href="https://documentation.ubuntu.com/server/explanation/software/about-apt-upgrade-and-phased-updates/" target="_blank" rel="noopener noreferrer">Ubuntu Server: APT upgrade and phased updates</a></li>
</ul>
HTML,
        ],
    ],
];
