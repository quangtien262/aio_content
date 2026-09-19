<?php

return [
    'linux-disk-full-troubleshooting.html' => [
        'vi' => [
            'title' => 'Linux báo đầy ổ đĩa: Cách tìm nguyên nhân và giải phóng dung lượng an toàn',
            'slug' => 'linux-day-o-dia-xu-ly-an-toan',
            'image' => 'linux-disk-full-troubleshooting.jpg',
            'category_vi' => 'Linux', 'category_en' => 'Linux',
            'meta_title' => 'Linux đầy ổ đĩa: Chẩn đoán và xử lý an toàn',
            'meta_keywords' => 'Linux đầy ổ đĩa, df du, inode đầy, lsof deleted, journalctl vacuum, Docker disk usage, dọn dung lượng Linux',
            'meta_description' => 'Quy trình xử lý Linux đầy ổ đĩa bằng df, du, find, lsof, journalctl và Docker, kèm cách phân biệt block, inode và file đã xóa.',
            'tags' => ['Linux', 'Disk Space', 'System Administration', 'df', 'du', 'lsof', 'systemd', 'Docker'],
            'body' => <<<'HTML'
<p><strong>Khi Linux báo <code>No space left on device</code>, xóa ngay vài file lớn chưa chắc là cách đúng.</strong> Filesystem có thể hết block dữ liệu, hết inode, bị log tăng đột biến, chứa image Docker cũ hoặc vẫn giữ dung lượng của một file đã xóa. Quy trình an toàn là xác định đúng filesystem, đo nguyên nhân, giải phóng có kiểm soát rồi ngăn sự cố lặp lại.</p>

<h2>1. Xác định filesystem nào đang đầy</h2>
<p>Bắt đầu bằng hai góc nhìn: dung lượng và inode.</p>
<pre><code>df -hT
df -ih</code></pre>
<p><code>df -hT</code> hiển thị loại filesystem, tổng dung lượng, đã dùng, còn trống và mount point. <code>df -ih</code> kiểm tra inode. Nếu <code>IUse%</code> đạt 100%, hệ thống không thể tạo file mới dù vẫn còn GB trống; nguyên nhân thường là hàng triệu file nhỏ.</p>
<p>Đừng chỉ nhìn dòng <code>/dev/sda...</code>. Hãy xác định mount point chứa đường dẫn đang lỗi:</p>
<pre><code>df -hT /var/lib/mysql
findmnt -T /var/lib/mysql</code></pre>
<blockquote>Luôn dọn trên đúng filesystem. Xóa 10 GB trong <code>/home</code> không giúp gì nếu mount <code>/var</code> mới là nơi đã đầy.</blockquote>

<h2>2. Kiểm tra trước khi thay đổi</h2>
<p>Ghi lại trạng thái để có thể đối chiếu sau xử lý:</p>
<pre><code>date
df -hT
df -ih
lsblk -f
findmnt
sudo dmesg -T | tail -n 50</code></pre>
<p>Nếu kernel báo lỗi I/O, filesystem read-only hoặc thiết bị lưu trữ bất thường, ưu tiên bảo toàn dữ liệu và kiểm tra phần cứng. Dọn file không giải quyết được ổ đĩa đang hỏng.</p>

<h2>3. Tìm thư mục chiếm dung lượng</h2>
<p>Dùng <code>du</code> trên cùng filesystem để tránh đi vào mount mạng, volume container hoặc ổ khác:</p>
<pre><code>sudo du -xhd1 / | sort -h
sudo du -xhd1 /var | sort -h
sudo du -xhd1 /var/lib | sort -h</code></pre>
<p>Đi từ mount point xuống từng cấp cho đến khi thấy nhánh tăng bất thường. <code>-x</code> giữ phép đo trong một filesystem; <code>-d1</code> chỉ xem một cấp, tránh tạo đầu ra quá lớn. Trên máy đang tải cao, chạy <code>du</code> vào giờ thấp điểm vì quét nhiều inode có thể gây I/O đáng kể.</p>
<p>Tìm file lớn gần đây:</p>
<pre><code>sudo find /var -xdev -type f -size +500M \
  -printf '%s %TY-%Tm-%Td %TH:%TM %p\n' \
  | sort -n | tail -n 30</code></pre>
<p>Không xóa chỉ vì file lớn. Hãy xác định owner, tiến trình sử dụng, chính sách lưu giữ và khả năng khôi phục.</p>

<h2>4. Vì sao df và du không khớp?</h2>
<p><code>df</code> đọc mức sử dụng của toàn filesystem, còn <code>du</code> cộng dung lượng các file còn xuất hiện trong cây thư mục. Hai số có thể lệch vì reserved blocks, snapshot, hard link, mount bị che hoặc file đã bị unlink nhưng tiến trình vẫn mở.</p>
<p>Trường hợp phổ biến là log lớn đã được xóa nhưng web server hoặc database vẫn giữ file descriptor:</p>
<pre><code>sudo lsof +L1
sudo lsof +L1 /var</code></pre>
<p>Tìm các dòng có <code>NLINK</code> bằng 0 hoặc tên chứa <code>(deleted)</code>. Cách giải phóng đúng thường là yêu cầu ứng dụng reopen log hoặc restart đúng service sau khi đánh giá ảnh hưởng:</p>
<pre><code>sudo systemctl reload nginx
sudo systemctl restart my-application.service</code></pre>
<p>Không tự ý ghi rỗng qua <code>/proc/PID/fd/N</code> với database hoặc ứng dụng không rõ hành vi; thao tác này có thể làm hỏng dữ liệu hay che mất bằng chứng sự cố.</p>

<h2>5. Kiểm tra systemd journal và log ứng dụng</h2>
<p>Đo journal trước khi dọn:</p>
<pre><code>journalctl --disk-usage
sudo du -sh /var/log/* 2&gt;/dev/null | sort -h</code></pre>
<p>Có thể xóa journal đã archive theo dung lượng hoặc tuổi:</p>
<pre><code>sudo journalctl --rotate
sudo journalctl --vacuum-size=500M
# Hoặc:
sudo journalctl --vacuum-time=14days</code></pre>
<p><code>--vacuum-size</code> chỉ tác động trực tiếp đến journal đã archive, vì vậy <code>--rotate</code> giúp file active trở thành archive trước khi dọn. Sau đó cấu hình giới hạn bền vững trong <code>/etc/systemd/journald.conf</code>, chẳng hạn <code>SystemMaxUse</code> và <code>SystemKeepFree</code>, rồi kiểm tra cấu hình trước khi restart.</p>
<p>Với log trong <code>/var/log</code>, ưu tiên sửa <code>logrotate</code> hoặc cấu hình ứng dụng. Xóa log đang mở bằng <code>rm</code> có thể tạo chính tình huống <code>df</code> và <code>du</code> lệch nhau.</p>

<h2>6. Kiểm tra Docker và container runtime</h2>
<p>Docker có thể giữ image, layer, build cache, volume và JSON log:</p>
<pre><code>docker system df
docker system df -v
sudo du -sh /var/lib/docker/* 2&gt;/dev/null | sort -h</code></pre>
<p>Xem kỹ đối tượng nào đang được container sử dụng trước khi prune. Các lệnh <code>docker system prune</code> và đặc biệt <code>docker volume prune</code> có thể xóa dữ liệu không còn gắn với container hiện tại nhưng vẫn cần cho khôi phục hoặc lần chạy sau.</p>
<p>Đặt giới hạn log cho Docker daemon hoặc từng service, ví dụ dùng driver <code>local</code> hay cấu hình xoay <code>json-file</code>. Sau khi đổi daemon config, kiểm tra JSON hợp lệ và lên kế hoạch restart vì thay đổi không tự áp dụng cho mọi container cũ.</p>

<h2>7. Khi filesystem hết inode</h2>
<p>Nếu <code>df -i</code> đầy nhưng dung lượng byte còn nhiều, tìm thư mục có số file lớn:</p>
<pre><code>sudo find /var -xdev -printf '%h\n' \
  | sort | uniq -c | sort -n | tail -n 30</code></pre>
<p>Các thủ phạm thường gặp gồm session file, cache, mail queue, thumbnail, thư mục tạm và job tạo file nhưng không dọn. Không dùng wildcard xóa hàng triệu file trong một lệnh nếu shell có thể vượt giới hạn đối số. Dùng <code>find</code> với điều kiện tuổi và kiểm tra mẫu trước:</p>
<pre><code>find /path/to/cache -xdev -type f -mtime +7 -print | head
find /path/to/cache -xdev -type f -mtime +7 -delete</code></pre>
<p>Chỉ chạy lệnh xóa sau khi danh sách mẫu đúng và có xác nhận rằng dữ liệu là cache có thể tái tạo.</p>

<h2>8. Package cache, kernel cũ và file tạm</h2>
<p>Trên Debian/Ubuntu, có thể đo và dọn package cache bằng công cụ quản lý gói:</p>
<pre><code>sudo du -sh /var/cache/apt/archives
sudo apt clean
sudo apt autoremove --purge</code></pre>
<p>Đọc kỹ danh sách của <code>autoremove</code> trước khi đồng ý, đặc biệt trên máy có kernel, driver hoặc package được cài thủ công. Với <code>/tmp</code>, dùng chính sách <code>systemd-tmpfiles</code> hoặc điều kiện tuổi; không xóa toàn bộ trong khi ứng dụng đang hoạt động.</p>

<h2>9. Giải phóng khẩn cấp theo thứ tự rủi ro thấp</h2>
<ol><li>Tạm dừng tác vụ đang tạo dữ liệu nhanh nếu biết rõ nguồn.</li><li>Xoay và vacuum journal đã archive.</li><li>Dọn package cache và artifact có thể tải lại.</li><li>Xử lý log theo đúng cơ chế reload hoặc logrotate.</li><li>Thu hồi image/build cache container đã xác minh không dùng.</li><li>Restart service đang giữ file đã xóa, theo kế hoạch ảnh hưởng.</li><li>Mở rộng filesystem hoặc volume nếu dữ liệu hợp lệ thực sự tăng.</li></ol>
<p>Giữ một khoảng trống tối thiểu trước khi database hoặc service hoạt động lại. Việc giải phóng vài MB có thể chỉ đủ để hệ thống ghi thêm log rồi đầy ngay lần nữa.</p>

<h2>10. Mở rộng dung lượng khi dữ liệu tăng hợp lệ</h2>
<p>Nếu workload cần toàn bộ dữ liệu hiện có, dọn file chỉ trì hoãn sự cố. Xác định storage là partition, LVM, cloud volume hay filesystem độc lập. Quy trình thường gồm mở rộng thiết bị hoặc logical volume, sau đó mở rộng filesystem bằng công cụ phù hợp như <code>resize2fs</code> cho ext4 hoặc <code>xfs_growfs</code> cho XFS.</p>
<blockquote>Chụp backup hoặc snapshot đã kiểm thử, xác nhận đúng device và đọc tài liệu của nền tảng trước khi sửa partition hay volume. Không sao chép lệnh mở rộng từ một máy khác rồi chạy nguyên trạng.</blockquote>

<h2>11. Ngăn ổ đĩa đầy lần nữa</h2>
<ul><li>Cảnh báo theo cả phần trăm và dung lượng còn trống; 10% của ổ 10 TB khác 10% của ổ 20 GB.</li><li>Theo dõi inode, tốc độ tăng, thời gian dự kiến chạm ngưỡng và filesystem read-only.</li><li>Giới hạn journal, log ứng dụng và log container.</li><li>Đặt retention cho backup, artifact, upload, cache và snapshot.</li><li>Tách dữ liệu tăng nhanh khỏi root filesystem khi kiến trúc cho phép.</li><li>Kiểm thử cảnh báo và quy trình dọn trên staging.</li><li>Ghi lại nguyên nhân gốc, không chỉ dung lượng đã xóa.</li></ul>

<h2>Checklist xử lý sự cố</h2>
<ol><li>Dùng <code>df -hT</code> và <code>df -ih</code> để phân biệt block với inode.</li><li>Xác định đúng mount bằng <code>findmnt -T</code>.</li><li>Dùng <code>du -x</code> đi từng cấp, tránh vượt filesystem.</li><li>Nếu <code>df</code> lớn hơn nhiều so với <code>du</code>, kiểm tra <code>lsof +L1</code>.</li><li>Đo journal, log và container trước khi dọn.</li><li>Không xóa database, volume, snapshot hay log đang mở theo phỏng đoán.</li><li>Đo lại sau mỗi thay đổi và xác minh dịch vụ.</li><li>Thêm giới hạn, retention và cảnh báo để ngăn tái diễn.</li></ol>

<h2>Kết luận</h2>
<p>Sự cố đầy ổ đĩa trên Linux cần được xử lý như một bài toán chẩn đoán, không phải cuộc thi xóa file. <code>df</code> cho biết filesystem, <code>du</code> tìm dữ liệu trong cây thư mục, <code>lsof</code> phát hiện file đã xóa nhưng còn mở, còn công cụ journal và container giải thích các nguồn tăng thường gặp. Khi nguyên nhân đã rõ, hãy dọn có kiểm soát hoặc mở rộng storage và bổ sung cảnh báo để lần sau hệ thống báo sớm hơn.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/df-invocation.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: df</a></li><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/du-invocation.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: du</a></li><li><a href="https://man7.org/linux/man-pages/man8/lsof.8.html" target="_blank" rel="noopener noreferrer">Linux manual: lsof</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/journalctl.html" target="_blank" rel="noopener noreferrer">systemd: journalctl</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Linux Disk Full: How to Find the Cause and Free Space Safely',
            'slug' => 'linux-disk-full-find-cause-free-space-safely',
            'image' => 'linux-disk-full-troubleshooting.jpg',
            'category_vi' => 'Linux', 'category_en' => 'Linux',
            'meta_title' => 'Linux Disk Full: Diagnose and Recover Safely',
            'meta_keywords' => 'Linux disk full, df du, inode full, lsof deleted, journalctl vacuum, Docker disk usage, free Linux disk space',
            'meta_description' => 'Diagnose a full Linux disk with df, du, find, lsof, journalctl, and Docker, including inode exhaustion and open deleted files.',
            'tags' => ['Linux', 'Disk Space', 'System Administration', 'df', 'du', 'lsof', 'systemd', 'Docker'],
            'body' => <<<'HTML'
<p><strong>When Linux reports <code>No space left on device</code>, immediately deleting a few large files may not solve the real problem.</strong> The filesystem may be out of data blocks or inodes, logs may be growing rapidly, Docker may retain old layers, or a process may still hold space from a deleted file. A safe response identifies the filesystem, measures the cause, recovers space deliberately, and prevents recurrence.</p>

<h2>1. Identify the full filesystem</h2>
<p>Begin with both capacity and inode views:</p>
<pre><code>df -hT
df -ih</code></pre>
<p><code>df -hT</code> shows filesystem type, total, used, available, and mount point. <code>df -ih</code> checks inodes. At 100% <code>IUse%</code>, the system cannot create files even when gigabytes remain; millions of small files are a common cause.</p>
<p>Identify the mount that contains the failing path:</p>
<pre><code>df -hT /var/lib/mysql
findmnt -T /var/lib/mysql</code></pre>
<blockquote>Always recover space on the correct filesystem. Removing 10 GB from <code>/home</code> does nothing when a separate <code>/var</code> mount is full.</blockquote>

<h2>2. Capture evidence before changing anything</h2>
<pre><code>date
df -hT
df -ih
lsblk -f
findmnt
sudo dmesg -T | tail -n 50</code></pre>
<p>If the kernel reports I/O errors, a read-only filesystem, or unhealthy storage, prioritize data preservation and hardware investigation. File cleanup cannot repair a failing device.</p>

<h2>3. Find the directory consuming space</h2>
<p>Use <code>du</code> while staying on the same filesystem:</p>
<pre><code>sudo du -xhd1 / | sort -h
sudo du -xhd1 /var | sort -h
sudo du -xhd1 /var/lib | sort -h</code></pre>
<p>Walk down one level at a time until an unusual branch appears. <code>-x</code> stays within one filesystem and <code>-d1</code> limits output to one level. On a busy host, schedule broad scans carefully because traversing many inodes can create significant I/O.</p>
<p>Locate recent large files:</p>
<pre><code>sudo find /var -xdev -type f -size +500M \
  -printf '%s %TY-%Tm-%Td %TH:%TM %p\n' \
  | sort -n | tail -n 30</code></pre>
<p>Do not delete a file merely because it is large. Identify its owner, active process, retention policy, and recovery path.</p>

<h2>4. Why df and du disagree</h2>
<p><code>df</code> reads whole-filesystem allocation while <code>du</code> totals files visible in directory trees. They may differ because of reserved blocks, snapshots, hard links, hidden mounts, or unlinked files still open by a process.</p>
<p>A common case is a large log that was removed while a web server or database retained its file descriptor:</p>
<pre><code>sudo lsof +L1
sudo lsof +L1 /var</code></pre>
<p>Look for zero <code>NLINK</code> values or names containing <code>(deleted)</code>. The normal remedy is to ask the application to reopen logs or restart the correct service after assessing impact:</p>
<pre><code>sudo systemctl reload nginx
sudo systemctl restart my-application.service</code></pre>
<p>Do not blindly truncate <code>/proc/PID/fd/N</code> for a database or unknown process. It can corrupt data or destroy incident evidence.</p>

<h2>5. Inspect systemd journal and application logs</h2>
<pre><code>journalctl --disk-usage
sudo du -sh /var/log/* 2&gt;/dev/null | sort -h</code></pre>
<p>Archived journal files can be vacuumed by size or age:</p>
<pre><code>sudo journalctl --rotate
sudo journalctl --vacuum-size=500M
# Or:
sudo journalctl --vacuum-time=14days</code></pre>
<p><code>--vacuum-size</code> directly removes only archived journals, so rotating first makes active data eligible for archival cleanup. Then set durable limits such as <code>SystemMaxUse</code> and <code>SystemKeepFree</code> in <code>/etc/systemd/journald.conf</code>.</p>
<p>For files under <code>/var/log</code>, fix logrotate or application retention. Removing an open log with <code>rm</code> can create the exact <code>df</code>/<code>du</code> discrepancy described above.</p>

<h2>6. Inspect Docker and container storage</h2>
<pre><code>docker system df
docker system df -v
sudo du -sh /var/lib/docker/* 2&gt;/dev/null | sort -h</code></pre>
<p>Docker may retain images, layers, build cache, volumes, and JSON logs. Review what running containers use before pruning. <code>docker system prune</code>, especially <code>docker volume prune</code>, can remove data that is detached now but still needed for recovery or a future deployment.</p>
<p>Set container log limits with the <code>local</code> logging driver or rotation options for <code>json-file</code>. Validate daemon JSON and plan the restart; new settings may not automatically affect existing containers.</p>

<h2>7. Handle inode exhaustion</h2>
<p>If <code>df -i</code> is full while byte capacity remains, find directories with very high file counts:</p>
<pre><code>sudo find /var -xdev -printf '%h\n' \
  | sort | uniq -c | sort -n | tail -n 30</code></pre>
<p>Common sources include sessions, cache, mail queues, thumbnails, temporary files, and jobs without cleanup. Avoid a shell wildcard over millions of names. Use <code>find</code> with an age condition and inspect a sample first:</p>
<pre><code>find /path/to/cache -xdev -type f -mtime +7 -print | head
find /path/to/cache -xdev -type f -mtime +7 -delete</code></pre>
<p>Run deletion only after confirming that the sample is correct and that the data is reproducible cache.</p>

<h2>8. Package caches, old kernels, and temporary files</h2>
<p>On Debian and Ubuntu, inspect and clean package caches through the package manager:</p>
<pre><code>sudo du -sh /var/cache/apt/archives
sudo apt clean
sudo apt autoremove --purge</code></pre>
<p>Review the <code>autoremove</code> proposal before approving it, particularly on systems with manually installed kernels, drivers, or packages. Clean <code>/tmp</code> through <code>systemd-tmpfiles</code> policy or age conditions instead of emptying it while applications run.</p>

<h2>9. Emergency recovery in lower-risk order</h2>
<ol><li>Pause the known workload that is rapidly generating data.</li><li>Rotate and vacuum archived journals.</li><li>Remove package cache and reproducible artifacts.</li><li>Handle logs through reload or logrotate.</li><li>Remove verified-unused container images and build cache.</li><li>Restart services holding deleted files according to an impact plan.</li><li>Expand the filesystem or volume when valid data growth is the cause.</li></ol>
<p>Recover a meaningful safety margin before resuming databases and services. A few free megabytes may only allow another burst of logs before the host fills again.</p>

<h2>10. Expand capacity when growth is legitimate</h2>
<p>If the workload needs all current data, cleanup only delays failure. Determine whether storage is a partition, LVM logical volume, cloud disk, or standalone filesystem. A typical workflow expands the device or logical volume and then grows the filesystem with the appropriate tool, such as <code>resize2fs</code> for ext4 or <code>xfs_growfs</code> for XFS.</p>
<blockquote>Create a tested backup or snapshot, verify the exact device, and read platform documentation before changing partitions or volumes. Never copy a storage expansion command from another host and run it unchanged.</blockquote>

<h2>11. Prevent recurrence</h2>
<ul><li>Alert on both percentage and absolute free space.</li><li>Monitor inodes, growth rate, predicted time to threshold, and read-only state.</li><li>Limit journal, application, and container logs.</li><li>Define retention for backups, artifacts, uploads, caches, and snapshots.</li><li>Separate fast-growing data from the root filesystem when appropriate.</li><li>Test alerts and cleanup procedures in staging.</li><li>Record the root cause, not just the number of bytes removed.</li></ul>

<h2>Incident checklist</h2>
<ol><li>Use <code>df -hT</code> and <code>df -ih</code> to distinguish blocks from inodes.</li><li>Confirm the mount with <code>findmnt -T</code>.</li><li>Walk directories with <code>du -x</code> without crossing filesystems.</li><li>If <code>df</code> greatly exceeds <code>du</code>, inspect <code>lsof +L1</code>.</li><li>Measure journals, logs, and containers before cleanup.</li><li>Never guess-delete databases, volumes, snapshots, or open logs.</li><li>Measure after every change and verify services.</li><li>Add limits, retention, and alerts to prevent recurrence.</li></ol>

<h2>Conclusion</h2>
<p>A full Linux disk is a diagnostic problem, not a file-deletion contest. <code>df</code> identifies filesystem pressure, <code>du</code> locates visible data, <code>lsof</code> reveals open deleted files, and journal or container tools explain common growth sources. Once the cause is clear, clean up deliberately or expand storage, then add monitoring so the next warning arrives before the outage.</p>

<h2>References</h2>
<ul><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/df-invocation.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: df</a></li><li><a href="https://www.gnu.org/software/coreutils/manual/html_node/du-invocation.html" target="_blank" rel="noopener noreferrer">GNU Coreutils: du</a></li><li><a href="https://man7.org/linux/man-pages/man8/lsof.8.html" target="_blank" rel="noopener noreferrer">Linux manual: lsof</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/journalctl.html" target="_blank" rel="noopener noreferrer">systemd: journalctl</a></li></ul>
HTML,
        ],
    ],
];
