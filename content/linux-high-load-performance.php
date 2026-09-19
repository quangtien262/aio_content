<?php

return [
    'linux-high-load-performance.html' => [
        'vi' => [
            'title' => 'Linux load cao và máy chủ chậm: Chẩn đoán CPU, I/O, RAM và process',
            'slug' => 'linux-load-cao-chan-doan-cpu-io-ram-process',
            'image' => 'linux-high-load-performance.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Chẩn đoán Linux load cao và máy chủ chậm',
            'meta_keywords' => 'Linux load cao, load average, top, pidstat, vmstat, iostat, PSI Linux, CPU high, iowait, Linux performance',
            'meta_description' => 'Quy trình chẩn đoán Linux load cao bằng uptime, top, pidstat, vmstat, iostat và PSI; phân biệt nghẽn CPU, I/O, RAM, steal time và process kẹt.',
            'tags' => ['Linux', 'Performance', 'Load Average', 'CPU', 'I/O', 'PSI', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>Load average 20 không tự động có nghĩa CPU đang chạy 100%.</strong> Trên Linux, load còn tính các task đang chạy/chờ CPU và task ở trạng thái uninterruptible sleep, thường là chờ I/O. Vì vậy một server “load cao” có thể nghẽn CPU, disk, NFS, memory reclaim, hypervisor hoặc một kernel path khác.</p>
<p>Bài viết này trình bày quy trình từ tín hiệu tổng quan đến đúng process/cgroup gây nghẽn bằng <code>uptime</code>, <code>top</code>, <code>vmstat</code>, <code>pidstat</code>, <code>iostat</code>, <code>sar</code> và Pressure Stall Information (PSI).</p>

<h2>Trước tiên: xác nhận người dùng đang thấy gì</h2>
<p>“Chậm” cần được gắn với một triệu chứng đo được:</p>
<ul>
<li>Latency API tăng hay request timeout?</li>
<li>SSH lag, shell đứng hay chỉ một service?</li>
<li>Throughput giảm dù CPU thấp?</li>
<li>Sự cố bắt đầu khi deploy, backup, cron hay traffic tăng?</li>
<li>Ảnh hưởng toàn host, một container hay một tenant?</li>
</ul>
<p>Ghi timestamp và timezone. Đừng restart ngay; restart xóa process state, queue và bằng chứng quan trọng. Nếu hệ thống đang mất dữ liệu hoặc cascade, giảm tải trước rồi vẫn lưu snapshot tối thiểu.</p>

<h2>1. Đọc load average đúng cách</h2>
<pre><code>uptime
cat /proc/loadavg
nproc
lscpu</code></pre>
<p>Ba số load là trung bình khoảng 1, 5 và 15 phút. Chúng phản ánh số task ở trạng thái runnable (<code>R</code>) hoặc chờ I/O không ngắt được (<code>D</code>), không phải phần trăm CPU.</p>
<p>So sánh load với số logical CPU chỉ là bước định hướng. Load 8 trên máy 8 CPU có thể bình thường nếu workload CPU-bound và latency đạt SLA; load 4 vẫn có thể nghiêm trọng nếu mọi request chờ một disk chậm. Xu hướng quan trọng hơn một ngưỡng cứng:</p>
<ul>
<li>1 phút cao hơn 5/15 phút: áp lực mới tăng.</li>
<li>1 phút thấp hơn 5/15 phút: hệ thống đang hồi phục.</li>
<li>Cả ba cao: áp lực kéo dài.</li>
</ul>

<h2>2. Snapshot tổng quan bằng top</h2>
<pre><code>top
top -H -p PID</code></pre>
<p>Các trường CPU thường gặp:</p>
<ul>
<li><code>us</code>: user-space code.</li>
<li><code>sy</code>: kernel/system time.</li>
<li><code>id</code>: idle.</li>
<li><code>wa</code>: CPU idle trong lúc hệ thống còn I/O outstanding; là tín hiệu, không phải “disk sử dụng bao nhiêu %”.</li>
<li><code>hi</code>/<code>si</code>: hardware/software interrupt.</li>
<li><code>st</code>: steal time, vCPU chờ hypervisor cấp CPU.</li>
</ul>
<p>Nhấn <code>1</code> để xem từng CPU, <code>P</code> để sort CPU và <code>M</code> để sort memory. Một process 400% CPU trên máy nhiều core có nghĩa nó dùng khoảng bốn logical CPU; không phải lỗi hiển thị.</p>
<p><code>top</code> dễ dùng nhưng snapshot có thể bỏ lỡ burst ngắn. Tiếp tục bằng công cụ sampling có interval.</p>

<h2>3. vmstat: phân biệt runnable, blocked, swap và CPU</h2>
<pre><code>vmstat 1 10
vmstat -y 1 10</code></pre>
<p>Dùng <code>-y</code> để bỏ dòng đầu tiên vốn là trung bình từ lúc boot. Tập trung:</p>
<ul>
<li><code>r</code>: task runnable/chờ CPU. Liên tục cao hơn số CPU gợi ý CPU queue.</li>
<li><code>b</code>: task bị block chờ I/O.</li>
<li><code>si</code>/<code>so</code>: swap in/out. Hoạt động liên tục cùng latency thường là memory pressure.</li>
<li><code>us</code>/<code>sy</code>/<code>id</code>/<code>wa</code>/<code>st</code>: phân bố CPU.</li>
<li><code>cs</code>: context switch; tăng cao cần đặt cạnh throughput và scheduler activity.</li>
</ul>
<p>Pattern thường gặp:</p>
<ul>
<li><code>r</code> cao, <code>id</code> gần 0, <code>wa</code> thấp: CPU saturation.</li>
<li><code>b</code> và <code>wa</code> cao: I/O path có vấn đề.</li>
<li><code>si/so</code> liên tục: RAM pressure hoặc working set vượt memory.</li>
<li><code>st</code> cao: tranh chấp CPU ở hypervisor/cloud host.</li>
</ul>

<h2>4. Tìm process bằng pidstat</h2>
<p>Cài package <code>sysstat</code> nếu chưa có. Lấy nhiều góc nhìn theo interval:</p>
<pre><code>pidstat -u -r -d -w -p ALL 1 10
pidstat -t -u -p PID 1 10</code></pre>
<ul>
<li><code>-u</code>: CPU user/system và CPU number.</li>
<li><code>-r</code>: page fault và memory.</li>
<li><code>-d</code>: read/write I/O theo process.</li>
<li><code>-w</code>: voluntary/non-voluntary context switch.</li>
<li><code>-t</code>: hiển thị thread.</li>
</ul>
<p>Thread view quan trọng với JVM, database, web server và runtime có thread pool. Một thread nóng có thể bị che trong tổng process. Ghi command line đầy đủ:</p>
<pre><code>ps -eo pid,ppid,stat,psr,ni,%cpu,%mem,wchan:24,comm,args \
  --sort=-%cpu | head -30</code></pre>

<h2>5. Process state và uninterruptible sleep</h2>
<p>Trong <code>ps</code>, các state chính gồm <code>R</code> runnable, <code>S</code> interruptible sleep, <code>D</code> uninterruptible sleep, <code>Z</code> zombie và <code>T</code> stopped. Nhiều task <code>D</code> thường làm load cao dù CPU rảnh.</p>
<pre><code>ps -eo state,pid,ppid,wchan:32,comm,args | awk '$1 ~ /^D/'
cat /proc/PID/stack
cat /proc/PID/wchan</code></pre>
<p><code>wchan</code>/kernel stack có thể chỉ ra chờ block I/O, NFS, filesystem, lock hoặc driver. Task <code>D</code> thường không thể kill ngay; signal chỉ được xử lý khi kernel operation trở lại. Reboot có thể là biện pháp cuối, nhưng trước đó cần xác định storage/network/kernel path để lỗi không lặp lại.</p>

<h2>6. iostat: disk nào đang nghẽn?</h2>
<pre><code>iostat -xz 1 10</code></pre>
<p>Các trường thay đổi theo phiên bản, nhưng thường chú ý:</p>
<ul>
<li><code>r/s</code>, <code>w/s</code>: IOPS.</li>
<li><code>rkB/s</code>, <code>wkB/s</code>: throughput.</li>
<li><code>await</code>: latency trung bình gồm queue + service.</li>
<li><code>aqu-sz</code>: độ dài queue trung bình.</li>
<li><code>%util</code>: thời gian device bận xử lý I/O.</li>
</ul>
<p>Không dùng một ngưỡng <code>%util=100</code> cho mọi storage. NVMe, RAID, SAN và virtual block device có concurrency khác disk đơn. Đánh giá latency theo SLA và baseline của chính workload, kết hợp queue, throughput và application latency.</p>
<p>Tìm process tạo I/O bằng <code>pidstat -d</code> hoặc <code>iotop -oPa</code>. Kiểm tra kernel/storage log:</p>
<pre><code>dmesg -T | grep -Ei 'error|timeout|reset|nvme|blk|ext4|xfs'
journalctl -k --since '-30 min'</code></pre>

<h2>7. Phân biệt memory usage và memory pressure</h2>
<pre><code>free -h
vmstat 1
cat /proc/meminfo
cat /proc/pressure/memory</code></pre>
<p>Linux dùng RAM trống cho page cache; <code>free</code> thấp không tự động là thiếu RAM. Xem <code>available</code>, swap activity, reclaim và PSI. Khi memory pressure cao, task bị stall để reclaim/compaction hoặc swap, khiến latency tăng trước cả OOM.</p>
<p>Tìm process/cgroup:</p>
<pre><code>ps -eo pid,ppid,%mem,rss,vsz,comm,args --sort=-rss | head -30
systemd-cgtop
cat /sys/fs/cgroup/system.slice/SERVICE.service/memory.events</code></pre>
<p>Bài toán memory leak/OOM cần phân tích riêng; đừng drop cache như giải pháp định kỳ. Nó có thể làm I/O tăng và chỉ che triệu chứng.</p>

<h2>8. PSI: đo thời gian workload thực sự bị stall</h2>
<pre><code>cat /proc/pressure/cpu
cat /proc/pressure/memory
cat /proc/pressure/io</code></pre>
<p>PSI báo tỷ lệ thời gian task bị trì hoãn vì thiếu CPU, memory hoặc I/O trong các cửa sổ 10, 60 và 300 giây:</p>
<ul>
<li><code>some</code>: ít nhất một task bị stall.</li>
<li><code>full</code>: mọi task non-idle đồng thời bị stall; memory/I/O full kéo dài thường nghiêm trọng.</li>
</ul>
<p>Load cho biết bao nhiêu task đang runnable/blocked; PSI cho biết workload mất bao nhiêu thời gian vì contention. Kết hợp hai tín hiệu tốt hơn chỉ alert load average.</p>
<p>Với cgroup v2, có thể đọc <code>cpu.pressure</code>, <code>memory.pressure</code> và <code>io.pressure</code> theo service/container để tránh một workload bị che trong metric toàn host.</p>

<h2>9. CPU saturation: user, system, interrupt hay steal?</h2>
<p>Nếu <code>us</code> cao, xác định function/code bằng profiler phù hợp; đừng chỉ kill process. Với native workload có thể bắt đầu:</p>
<pre><code>perf top
perf stat -p PID sleep 10</code></pre>
<p><code>sy</code> cao có thể liên quan syscall, network packet, filesystem, lock hoặc kernel work. <code>si</code> cao thường cần xem packet rate và NIC distribution:</p>
<pre><code>mpstat -P ALL 1 10
mpstat -I ALL 1 10
cat /proc/interrupts</code></pre>
<p><code>st</code> cao trên VM nghĩa hypervisor không cấp đủ CPU. Tối ưu code trong guest không giải quyết noisy neighbor; cần đổi instance/host hoặc làm việc với nhà cung cấp.</p>

<h2>10. Scheduler, run queue và context switch</h2>
<p>CPU 100% chưa chắc xấu nếu throughput tốt và latency đạt mục tiêu. Vấn đề là runnable queue và thời gian chờ CPU tăng. Dùng:</p>
<pre><code>mpstat -P ALL 1
pidstat -w -p ALL 1
sar -q 1 10</code></pre>
<p>Nhiều thread hơn CPU không tự động sai, nhưng thread explosion, lock contention hoặc spin loop có thể tạo context switch cao và throughput thấp. So sánh với deploy/config gần nhất: worker count, thread pool, connection pool, garbage collector và CPU quota.</p>

<h2>11. Container và cgroup: host rảnh nhưng app vẫn chậm</h2>
<p>Container có thể bị CPU throttling hoặc memory limit dù host còn tài nguyên:</p>
<pre><code>docker stats --no-stream
systemd-cgtop
cat /sys/fs/cgroup/CGROUP/cpu.stat
cat /sys/fs/cgroup/CGROUP/memory.current
cat /sys/fs/cgroup/CGROUP/memory.events
</code></pre>
<p>Trong <code>cpu.stat</code>, <code>nr_throttled</code> và <code>throttled_usec</code> tăng cho thấy workload chạm CPU quota. Với Kubernetes, so sánh request/limit, pod throttling và node pressure. Đừng tăng limit trước khi hiểu traffic và regression.</p>

<h2>12. Lịch sử quan trọng hơn snapshot</h2>
<p>Khi sự cố đã qua, <code>top</code> hiện tại không trả lời quá khứ. Bật <code>sysstat</code> hoặc monitoring agent từ trước:</p>
<pre><code>sar -u -f /var/log/sysstat/sa19
sar -q -f /var/log/sysstat/sa19
sar -r -f /var/log/sysstat/sa19
sar -d -p -f /var/log/sysstat/sa19</code></pre>
<p>Đối chiếu với deploy, cron, backup, traffic, database checkpoint và cloud event. Baseline theo cùng giờ/ngày giúp phân biệt spike bình thường và regression.</p>

<h2>13. Giảm tải an toàn khi đang sự cố</h2>
<p>Tùy nguyên nhân, ưu tiên biện pháp có thể rollback:</p>
<ul>
<li>Rate-limit/load shed request không quan trọng.</li>
<li>Tạm dừng batch, backup, index build hoặc worker priority thấp.</li>
<li>Scale out/vertical nếu đã xác nhận saturation và ứng dụng hỗ trợ.</li>
<li>Giảm concurrency khi dependency đang nghẽn; tăng worker có thể làm tệ hơn.</li>
<li>Rollback deploy/config vừa gây regression.</li>
<li>Dùng <code>renice</code>/<code>ionice</code> thận trọng cho batch, không thay thế sửa nguyên nhân.</li>
</ul>
<p>Không kill database, flush cache, drop cache hoặc reboot chỉ vì load cao. Chụp metrics, process list, stack/log và I/O state trước; đánh giá tác động dữ liệu và failover.</p>

<h2>14. Các kết luận sai thường gặp</h2>
<ul>
<li>“Load cao = CPU cao” — task D-state cũng tăng load.</li>
<li>“Free RAM thấp = thiếu RAM” — page cache là sử dụng hữu ích.</li>
<li>“iowait cao = disk chắc chắn hỏng” — cần iostat, process và storage path.</li>
<li>“%util 100 = mọi disk đã hết capacity” — thiết bị concurrent cần đọc khác.</li>
<li>“Một process 300% là bug” — có thể dùng ba core hợp lệ.</li>
<li>“Tăng worker sẽ tăng throughput” — có thể tăng queue và contention.</li>
<li>“Restart xong là đã sửa” — chỉ xóa triệu chứng và bằng chứng.</li>
</ul>

<h2>Playbook 15 phút</h2>
<ol>
<li>Ghi latency/error/throughput và timestamp.</li>
<li><code>uptime</code>, <code>nproc</code>: load trend và số CPU.</li>
<li><code>vmstat -y 1 10</code>: runnable, blocked, swap, wa, st.</li>
<li><code>cat /proc/pressure/*</code>: resource nào làm task stall.</li>
<li><code>pidstat -u -r -d -w -p ALL 1 10</code>: process nào gây áp lực.</li>
<li><code>iostat -xz 1 10</code>: device latency/queue/throughput.</li>
<li><code>ps</code> tìm D-state, thread nóng và command line.</li>
<li>Kiểm tra cgroup/container quota và throttling.</li>
<li>Đối chiếu log/deploy/cron/traffic.</li>
<li>Giảm tải có rollback, rồi xác minh SLA và viết postmortem.</li>
</ol>

<h2>Kết luận</h2>
<p>Load average là điểm bắt đầu, không phải chẩn đoán. Muốn hiểu Linux chậm, cần phân biệt task chờ CPU với task chờ I/O, đọc xu hướng theo interval, tìm process/thread/cgroup và đo thời gian stall bằng PSI. Khi kết hợp <code>vmstat</code>, <code>pidstat</code>, <code>iostat</code> và dữ liệu lịch sử, bạn có thể chuyển từ “server load cao” sang một nguyên nhân cụ thể, biện pháp giảm tải an toàn và thay đổi phòng ngừa có thể kiểm chứng.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://www.man7.org/linux/man-pages/man5/proc_loadavg.5.html" target="_blank" rel="noopener noreferrer">Linux man-pages: /proc/loadavg</a></li><li><a href="https://www.man7.org/linux/man-pages/man8/vmstat.8.html" target="_blank" rel="noopener noreferrer">procps-ng: vmstat</a></li><li><a href="https://man7.org/linux/man-pages/man1/pidstat.1.html" target="_blank" rel="noopener noreferrer">sysstat: pidstat</a></li><li><a href="https://docs.kernel.org/accounting/psi.html" target="_blank" rel="noopener noreferrer">Linux Kernel: Pressure Stall Information</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'High Linux Load and Slow Servers: Diagnose CPU, I/O, Memory, and Processes',
            'slug' => 'high-linux-load-diagnose-cpu-io-memory-processes',
            'image' => 'linux-high-load-performance.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Diagnosing High Linux Load and Slow Servers',
            'meta_keywords' => 'high Linux load, load average, top, pidstat, vmstat, iostat, Linux PSI, high CPU, iowait, Linux performance',
            'meta_description' => 'Diagnose high Linux load with uptime, top, pidstat, vmstat, iostat, and PSI; distinguish CPU saturation, I/O bottlenecks, memory pressure, steal time, and stuck tasks.',
            'tags' => ['Linux', 'Performance', 'Load Average', 'CPU', 'I/O', 'PSI', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>A load average of 20 does not automatically mean 100% CPU usage.</strong> Linux load includes tasks running or waiting for CPU and tasks in uninterruptible sleep, often waiting for I/O. A high-load server may be constrained by CPU, disks, NFS, memory reclaim, a hypervisor, or another kernel path.</p>
<p>This guide moves from system-wide signals to the exact process or cgroup causing pressure with <code>uptime</code>, <code>top</code>, <code>vmstat</code>, <code>pidstat</code>, <code>iostat</code>, <code>sar</code>, and Pressure Stall Information (PSI).</p>

<h2>First, define what users experience</h2>
<p>Attach “slow” to a measurable symptom: rising API latency, timeouts, lagging SSH, one slow service, reduced throughput, or impact limited to a container or tenant. Record the timestamp and timezone, and correlate with deployments, backups, cron, and traffic.</p>
<p>Do not restart immediately. It removes process state, queues, and evidence. When data loss or cascading failure is active, shed load first but preserve a minimal snapshot.</p>

<h2>1. Interpret load average correctly</h2>
<pre><code>uptime
cat /proc/loadavg
nproc
lscpu</code></pre>
<p>The three load values cover roughly 1, 5, and 15 minutes. They count runnable tasks (<code>R</code>) and tasks in uninterruptible I/O wait (<code>D</code>); they are not CPU percentages.</p>
<p>Comparing load with logical CPU count is only an orientation. Load 8 on eight CPUs may be healthy for a CPU-bound workload meeting its SLA; load 4 can be severe when every request waits on one slow disk. Trend matters: a one-minute value above longer windows means rising pressure, below them indicates recovery, and all three elevated indicates sustained pressure.</p>

<h2>2. Take a top-level snapshot</h2>
<pre><code>top
top -H -p PID</code></pre>
<ul>
<li><code>us</code>: user-space work.</li>
<li><code>sy</code>: kernel/system work.</li>
<li><code>id</code>: idle time.</li>
<li><code>wa</code>: CPU idle while I/O is outstanding; it is not disk utilization.</li>
<li><code>hi</code>/<code>si</code>: hardware/software interrupts.</li>
<li><code>st</code>: vCPU time taken by the hypervisor.</li>
</ul>
<p>Press <code>1</code> for per-CPU view, <code>P</code> for CPU sorting, and <code>M</code> for memory. A process at 400% can legitimately consume four logical CPUs. Because snapshots miss bursts, continue with interval sampling.</p>

<h2>3. Use vmstat to separate CPU, blocked tasks, and swapping</h2>
<pre><code>vmstat 1 10
vmstat -y 1 10</code></pre>
<p><code>-y</code> skips the first line, which averages since boot.</p>
<ul>
<li><code>r</code>: runnable tasks; sustained values above CPU count suggest a run queue.</li>
<li><code>b</code>: tasks blocked on I/O.</li>
<li><code>si</code>/<code>so</code>: swap activity.</li>
<li><code>us/sy/id/wa/st</code>: CPU distribution.</li>
<li><code>cs</code>: context switches, meaningful alongside throughput and scheduler load.</li>
</ul>
<p>High <code>r</code> with near-zero idle and low wait points toward CPU saturation. High <code>b</code> and wait points toward I/O. Continuous swap activity suggests memory pressure. High steal time indicates hypervisor contention.</p>

<h2>4. Attribute pressure with pidstat</h2>
<pre><code>pidstat -u -r -d -w -p ALL 1 10
pidstat -t -u -p PID 1 10</code></pre>
<p><code>-u</code> reports CPU, <code>-r</code> memory and faults, <code>-d</code> I/O, <code>-w</code> context switches, and <code>-t</code> threads. Thread view matters for JVMs, databases, web servers, and thread pools where one hot thread can hide inside a process total.</p>
<pre><code>ps -eo pid,ppid,stat,psr,ni,%cpu,%mem,wchan:24,comm,args \
  --sort=-%cpu | head -30</code></pre>

<h2>5. Find tasks in uninterruptible sleep</h2>
<p>Important states include <code>R</code> runnable, <code>S</code> interruptible sleep, <code>D</code> uninterruptible sleep, <code>Z</code> zombie, and <code>T</code> stopped. Many D-state tasks can raise load while CPU remains idle.</p>
<pre><code>ps -eo state,pid,ppid,wchan:32,comm,args | awk '$1 ~ /^D/'
cat /proc/PID/stack
cat /proc/PID/wchan</code></pre>
<p>The wait channel or kernel stack may expose block I/O, NFS, filesystem, locking, or driver waits. A D-state task usually cannot be killed immediately because signals are handled only when the kernel operation returns. Reboot may be a last resort, but identify the failing path first.</p>

<h2>6. Identify the constrained block device</h2>
<pre><code>iostat -xz 1 10</code></pre>
<ul>
<li><code>r/s</code>, <code>w/s</code>: IOPS.</li>
<li><code>rkB/s</code>, <code>wkB/s</code>: throughput.</li>
<li><code>await</code>: average queue plus service latency.</li>
<li><code>aqu-sz</code>: average queue depth.</li>
<li><code>%util</code>: time the device reports busy.</li>
</ul>
<p>Do not apply one “100% util” rule to every device. NVMe, RAID, SAN, and virtual disks support different concurrency. Judge latency against the workload SLA and its baseline, along with queue and throughput.</p>
<pre><code>dmesg -T | grep -Ei 'error|timeout|reset|nvme|blk|ext4|xfs'
journalctl -k --since '-30 min'</code></pre>

<h2>7. Distinguish memory usage from pressure</h2>
<pre><code>free -h
vmstat 1
cat /proc/meminfo
cat /proc/pressure/memory</code></pre>
<p>Linux uses otherwise idle RAM for page cache, so low <code>free</code> is not automatically bad. Inspect <code>available</code>, swap activity, reclaim behavior, and PSI. Tasks can stall on reclaim, compaction, or swap before an OOM event.</p>
<pre><code>ps -eo pid,ppid,%mem,rss,vsz,comm,args --sort=-rss | head -30
systemd-cgtop
cat /sys/fs/cgroup/system.slice/SERVICE.service/memory.events</code></pre>
<p>Do not schedule periodic cache dropping as a fix. It can increase I/O and hide the actual cause.</p>

<h2>8. Use PSI to measure lost work time</h2>
<pre><code>cat /proc/pressure/cpu
cat /proc/pressure/memory
cat /proc/pressure/io</code></pre>
<p>PSI reports the percentage of time tasks were delayed by CPU, memory, or I/O scarcity over 10, 60, and 300 seconds. <code>some</code> means at least one task was stalled; <code>full</code> means all non-idle tasks were stalled together. Sustained full memory or I/O pressure is especially serious.</p>
<p>Load tells how many tasks are runnable or blocked; PSI tells how much productive time contention took away. With cgroup v2, per-service <code>cpu.pressure</code>, <code>memory.pressure</code>, and <code>io.pressure</code> prevent one workload from disappearing inside host averages.</p>

<h2>9. Classify CPU saturation</h2>
<p>When user time dominates, profile functions rather than simply killing the process:</p>
<pre><code>perf top
perf stat -p PID sleep 10</code></pre>
<p>High system time can come from syscalls, networking, filesystems, locks, or kernel work. High softirq requires packet and NIC investigation:</p>
<pre><code>mpstat -P ALL 1 10
mpstat -I ALL 1 10
cat /proc/interrupts</code></pre>
<p>High steal time on a VM means the hypervisor is not scheduling enough vCPU. Guest code optimization cannot resolve a noisy host; change instance/host or contact the provider.</p>

<h2>10. Examine run queues and context switches</h2>
<pre><code>mpstat -P ALL 1
pidstat -w -p ALL 1
sar -q 1 10</code></pre>
<p>Full CPU usage is not necessarily bad when throughput and latency meet targets. A growing run queue and CPU wait are the concern. Thread explosion, lock contention, or spin loops can produce high context switching with poor throughput. Compare worker count, thread pools, connection pools, garbage collection, and CPU quotas with the last change.</p>

<h2>11. Containers and cgroups</h2>
<p>A container can be throttled while the host has spare resources:</p>
<pre><code>docker stats --no-stream
systemd-cgtop
cat /sys/fs/cgroup/CGROUP/cpu.stat
cat /sys/fs/cgroup/CGROUP/memory.current
cat /sys/fs/cgroup/CGROUP/memory.events</code></pre>
<p>Rising <code>nr_throttled</code> and <code>throttled_usec</code> in <code>cpu.stat</code> indicate CPU quota pressure. In Kubernetes, inspect pod limits, throttling, and node pressure. Do not raise limits before understanding traffic and regression.</p>

<h2>12. Historical evidence beats a snapshot</h2>
<pre><code>sar -u -f /var/log/sysstat/sa19
sar -q -f /var/log/sysstat/sa19
sar -r -f /var/log/sysstat/sa19
sar -d -p -f /var/log/sysstat/sa19</code></pre>
<p>When the incident has ended, current <code>top</code> cannot reconstruct it. Enable sysstat or a monitoring agent in advance. Correlate with deployments, cron, backups, traffic, database checkpoints, and cloud events. Compare the same hour and weekday to distinguish expected peaks from regressions.</p>

<h2>13. Mitigate safely during an incident</h2>
<ul>
<li>Rate-limit or shed noncritical requests.</li>
<li>Pause batch jobs, backups, index builds, or low-priority workers.</li>
<li>Scale after confirming saturation and application readiness.</li>
<li>Reduce concurrency when a dependency is overloaded; more workers may worsen it.</li>
<li>Roll back the deployment or configuration that caused regression.</li>
<li>Use <code>renice</code>/<code>ionice</code> cautiously for batch work, not as the permanent fix.</li>
</ul>
<p>Do not kill a database, flush caches, drop caches, or reboot solely because load is high. Capture metrics, process lists, stacks, logs, and I/O state first, and assess data and failover impact.</p>

<h2>14. Common false conclusions</h2>
<ul>
<li>High load always means high CPU: D-state tasks count too.</li>
<li>Low free memory means shortage: page cache is useful.</li>
<li>High iowait proves a failed disk: inspect devices, tasks, and the storage path.</li>
<li>100% util means every disk is saturated: concurrent devices differ.</li>
<li>A 300% process is a bug: it may legitimately use three cores.</li>
<li>More workers always increase throughput: contention and queues may grow.</li>
<li>A restart is a fix: it often erases symptoms and evidence.</li>
</ul>

<h2>Fifteen-minute playbook</h2>
<ol>
<li>Record latency, errors, throughput, and time.</li>
<li><code>uptime</code>, <code>nproc</code>: trend and CPU count.</li>
<li><code>vmstat -y 1 10</code>: runnable, blocked, swap, wait, steal.</li>
<li><code>cat /proc/pressure/*</code>: which resource stalls tasks.</li>
<li><code>pidstat -u -r -d -w -p ALL 1 10</code>: responsible tasks.</li>
<li><code>iostat -xz 1 10</code>: device latency, queue, throughput.</li>
<li><code>ps</code>: D-state tasks, hot threads, command lines.</li>
<li>Inspect cgroup quotas and throttling.</li>
<li>Correlate logs, deployments, cron, and traffic.</li>
<li>Apply reversible load reduction, verify the SLA, and write a postmortem.</li>
</ol>

<h2>Conclusion</h2>
<p>Load average is the starting signal, not a diagnosis. Understanding a slow Linux host requires separating CPU wait from I/O wait, sampling trends, attributing work to processes, threads, and cgroups, and measuring lost time with PSI. Combining <code>vmstat</code>, <code>pidstat</code>, <code>iostat</code>, and historical data turns “high load” into a specific cause, a reversible mitigation, and a verifiable prevention change.</p>

<h2>References</h2>
<ul><li><a href="https://www.man7.org/linux/man-pages/man5/proc_loadavg.5.html" target="_blank" rel="noopener noreferrer">Linux man-pages: /proc/loadavg</a></li><li><a href="https://www.man7.org/linux/man-pages/man8/vmstat.8.html" target="_blank" rel="noopener noreferrer">procps-ng: vmstat</a></li><li><a href="https://man7.org/linux/man-pages/man1/pidstat.1.html" target="_blank" rel="noopener noreferrer">sysstat: pidstat</a></li><li><a href="https://docs.kernel.org/accounting/psi.html" target="_blank" rel="noopener noreferrer">Linux Kernel: Pressure Stall Information</a></li></ul>
HTML,
        ],
    ],
];
