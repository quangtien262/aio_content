<?php

return [
    'linux-memory-oom-troubleshooting.html' => [
        'vi' => [
            'title' => 'Linux dùng nhiều RAM và OOM Killer: Cách chẩn đoán, xử lý an toàn',
            'slug' => 'linux-dung-nhieu-ram-oom-killer',
            'image' => 'linux-memory-oom-troubleshooting.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Chẩn đoán Linux dùng nhiều RAM và OOM Killer',
            'meta_keywords' => 'Linux dùng nhiều RAM, OOM Killer, memory leak Linux, free available, vmstat, PSI memory, systemd memory limit',
            'meta_description' => 'Hướng dẫn đọc RAM Linux, phân biệt cache với memory pressure, tìm process rò bộ nhớ, phân tích OOM Killer và ngăn sự cố tái diễn.',
            'tags' => ['Linux', 'Memory', 'OOM Killer', 'Performance', 'Troubleshooting', 'systemd', 'cgroups', 'Sysadmin'],
            'body' => <<<'HTML'
<p><strong>Linux hiển thị RAM “used” cao chưa chắc là thiếu bộ nhớ.</strong> Kernel chủ động dùng RAM trống cho page cache và có thể thu hồi khi ứng dụng cần. Sự cố thật sự xuất hiện khi <code>MemAvailable</code> giảm kéo dài, swap hoạt động mạnh, tiến trình bị stall hoặc kernel phải dùng OOM Killer để giải phóng bộ nhớ.</p>

<h2>Hiểu đúng used, free và available</h2>
<pre><code>free -h
cat /proc/meminfo | head -20</code></pre>
<p>Trong <code>free</code>, hãy nhìn cột <strong>available</strong> trước cột <strong>free</strong>. <code>MemAvailable</code> là ước tính lượng bộ nhớ có thể cấp cho ứng dụng mới mà không cần swap, có tính tới phần page cache và slab có thể thu hồi. Cache lớn thường là tín hiệu Linux tận dụng RAM hiệu quả, không phải memory leak.</p>
<p>Cảnh báo đáng quan tâm là available liên tục đi xuống, swap-in/swap-out tăng, latency xấu và workload bị kill. Một snapshot đơn lẻ không đủ; cần chuỗi số liệu theo thời gian.</p>

<h2>1. Xác nhận memory pressure</h2>
<pre><code>free -h
vmstat 1 10
cat /proc/pressure/memory
swapon --show</code></pre>
<p>Trong <code>vmstat</code>, <code>si</code> và <code>so</code> cho biết swap-in/swap-out. Một ít swap đã dùng không đồng nghĩa đang thiếu RAM; hoạt động swap liên tục cùng latency cao mới đáng lo. PSI tại <code>/proc/pressure/memory</code> đo tỷ lệ thời gian task bị chặn do tranh chấp memory. Dòng <code>full</code> tăng kéo dài cho thấy toàn bộ workload không nhàn rỗi bị stall và hệ thống có nguy cơ thrashing/OOM.</p>

<h2>2. Tìm process sử dụng bộ nhớ</h2>
<pre><code>ps -eo pid,ppid,user,comm,rss,vsz,%mem --sort=-rss | head -20
top
systemd-cgtop</code></pre>
<p>RSS là phần memory resident của process, nhưng cộng RSS của nhiều process có thể đếm trùng shared memory. VSZ bao gồm không gian địa chỉ đã map và không đồng nghĩa RAM vật lý. Với process nghi vấn, xem chi tiết:</p>
<pre><code>cat /proc/PID/status
cat /proc/PID/smaps_rollup
pmap -x PID | tail -1</code></pre>
<p><code>smaps_rollup</code> giúp phân biệt private, shared, anonymous và file-backed memory. Trong container/systemd, kiểm tra cả cgroup vì OOM có thể xảy ra bên trong limit dù host còn RAM.</p>

<h2>3. Phân biệt tải cao, cache và memory leak</h2>
<ul><li><strong>Tải hợp lệ:</strong> memory tăng theo traffic rồi giảm khi tải hạ hoặc worker được recycle.</li><li><strong>Page cache:</strong> Cached cao nhưng MemAvailable vẫn tốt; kernel thu hồi khi cần.</li><li><strong>Memory leak:</strong> private/anonymous RSS của cùng process tăng dần qua nhiều chu kỳ tải và không trở lại baseline.</li><li><strong>Unbounded queue:</strong> backlog tăng làm process giữ ngày càng nhiều object; sửa throughput và backpressure.</li><li><strong>tmpfs/shmem:</strong> dữ liệu trong <code>/dev/shm</code> hoặc tmpfs tiêu thụ RAM dù nhìn giống file.</li><li><strong>Kernel/slab:</strong> process RSS không giải thích được tổng memory; kiểm tra <code>Slab</code>, <code>SReclaimable</code> và subsystem liên quan.</li></ul>
<p>Ghi RSS, request rate, queue depth và thời điểm deploy trên cùng biểu đồ. Tương quan theo thời gian hữu ích hơn việc restart rồi mất bằng chứng.</p>

<h2>4. Xác nhận OOM Killer đã hoạt động</h2>
<pre><code>journalctl -k -g 'Out of memory|Killed process|oom-kill' --since today
dmesg -T | grep -Ei 'out of memory|killed process|oom-kill'
journalctl -u systemd-oomd --since today
oomctl</code></pre>
<p>Kernel OOM Killer chọn task dựa trên heuristic, mức memory/swap và <code>oom_score_adj</code>. <code>systemd-oomd</code> là cơ chế userspace có thể hành động sớm dựa trên cgroup và memory pressure. Đọc log để biết global OOM hay cgroup OOM, process nào bị kill, mức memory và constraint liên quan.</p>
<p>Không kết luận process bị kill là thủ phạm duy nhất. Nó có thể chỉ là nạn nhân được chọn trong khi nguyên nhân là traffic tăng, limit sai, nhiều worker, leak ở dịch vụ khác hoặc host overcommit.</p>

<h2>5. Ổn định hệ thống đang gặp sự cố</h2>
<ol><li>Dừng hoặc giảm nguồn traffic/job không quan trọng.</li><li>Chụp số liệu <code>free</code>, <code>vmstat</code>, process, cgroup và OOM log.</li><li>Scale workload sang node khác hoặc giảm concurrency có kiểm soát.</li><li>Restart đúng service rò bộ nhớ nếu cần phục hồi tức thời.</li><li>Thêm swap tạm thời chỉ khi hiểu rủi ro latency và storage.</li><li>Giám sát sau thay đổi, không coi restart là sửa tận gốc.</li></ol>
<p>Tránh chạy <code>echo 3 &gt; /proc/sys/vm/drop_caches</code> như biện pháp chữa RAM. Xóa cache có thể làm I/O và latency xấu hơn; kernel vốn sẽ thu hồi cache khi cần.</p>

<h2>6. Swap: vùng đệm, không phải RAM miễn phí</h2>
<p>Swap có thể tạo thời gian phản ứng trước burst và đẩy page ít dùng ra khỏi RAM, nhưng storage chậm hơn RAM nhiều lần. Không có swap làm biên an toàn mỏng hơn; swap quá lớn không sửa được leak vô hạn.</p>
<pre><code>swapon --show
cat /proc/sys/vm/swappiness
vmstat 1</code></pre>
<p>Điều chỉnh <code>vm.swappiness</code> chỉ sau khi đo workload. Giá trị thấp không có nghĩa kernel tuyệt đối không swap. Với database, latency-sensitive service và Kubernetes, cần tuân theo hướng dẫn cụ thể của workload/orchestrator.</p>

<h2>7. Đặt giới hạn và headroom</h2>
<p>Với systemd service, có thể đặt giới hạn cgroup trong unit:</p>
<pre><code>[Service]
MemoryHigh=1500M
MemoryMax=2G
OOMPolicy=stop</code></pre>
<p><code>MemoryHigh</code> tạo pressure/throttling mềm; <code>MemoryMax</code> là trần cứng. Chọn mức từ dữ liệu peak hợp lệ, không tùy ý. Sau khi sửa unit:</p>
<pre><code>sudo systemctl daemon-reload
sudo systemctl restart example.service
systemctl show example.service -p MemoryCurrent -p MemoryPeak -p MemoryHigh -p MemoryMax</code></pre>
<p>Trong container, memory limit phải bao gồm heap, native allocation, page cache và sidecar. Giữ headroom cho kernel, SSH, monitoring và daemon nền; không phân bổ 100% RAM host cho workload.</p>

<h2>8. Điều chỉnh ứng dụng thay vì chỉ tăng RAM</h2>
<ul><li>Giới hạn worker/concurrency theo peak memory mỗi worker.</li><li>Dùng streaming/chunking thay vì nạp toàn bộ file hoặc query result.</li><li>Đặt giới hạn queue, timeout, payload và upload.</li><li>Cấu hình cache eviction và maximum memory.</li><li>Recycle worker sau số request hợp lý như biện pháp giảm rủi ro, không che leak mãi mãi.</li><li>Profile heap/native memory trong môi trường gần production.</li></ul>
<p>Tăng RAM hợp lý khi working set thật sự tăng; nếu đường memory vẫn tăng không giới hạn, RAM thêm chỉ trì hoãn OOM.</p>

<h2>9. Giám sát và cảnh báo sớm</h2>
<p>Theo dõi <code>MemAvailable</code>, working set theo cgroup, swap I/O, PSI memory, OOM events, restart count và queue depth. Cảnh báo dựa trên thời gian kéo dài và áp lực, không chỉ phần trăm RAM used.</p>
<p>Lưu metric sau deploy đủ lâu để nhận ra leak chậm. Alert nên dẫn tới runbook có lệnh thu thập bằng chứng, owner dịch vụ và điều kiện scale/restart rõ ràng.</p>

<h2>Sai lầm thường gặp</h2>
<ul><li>Thấy RAM used cao và kết luận leak mà không xem available/cache.</li><li>Cộng RSS mọi process và coi đó là tổng RAM chính xác.</li><li>Restart ngay trước khi lưu log và biểu đồ.</li><li>Tắt OOM Killer hoặc đặt <code>oom_score_adj=-1000</code> tràn lan.</li><li>Dùng drop_caches định kỳ như công cụ tối ưu.</li><li>Chỉ tăng swap/RAM trong khi queue hoặc concurrency không giới hạn.</li><li>Chỉ theo dõi host, bỏ qua cgroup/container OOM.</li></ul>

<h2>Checklist xử lý</h2>
<ol><li>Xác nhận MemAvailable, swap activity và PSI theo thời gian.</li><li>Xác định process/cgroup và loại memory đang tăng.</li><li>Đọc kernel log và systemd-oomd log để phân loại OOM.</li><li>Lưu bằng chứng trước khi restart hoặc kill.</li><li>Giảm tải và khôi phục dịch vụ theo ưu tiên.</li><li>Sửa leak, concurrency, queue hoặc cache policy.</li><li>Đặt MemoryHigh/MemoryMax và headroom phù hợp.</li><li>Load test và tạo cảnh báo trước ngưỡng OOM.</li></ol>

<h2>Kết luận</h2>
<p>Điều tra memory trên Linux bắt đầu từ pressure và khả năng thu hồi, không phải màu đỏ trong cột used. Hãy kết hợp MemAvailable, PSI, swap I/O, process/cgroup metrics và OOM log để tìm nguyên nhân. Restart có thể cứu dịch vụ, nhưng giới hạn hợp lý, backpressure và sửa ứng dụng mới ngăn sự cố quay lại.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://docs.kernel.org/filesystems/proc.html" target="_blank" rel="noopener noreferrer">Linux Kernel: The /proc filesystem</a></li><li><a href="https://docs.kernel.org/accounting/psi.html" target="_blank" rel="noopener noreferrer">Linux Kernel: Pressure Stall Information</a></li><li><a href="https://docs.kernel.org/admin-guide/sysctl/vm.html" target="_blank" rel="noopener noreferrer">Linux Kernel: VM sysctl documentation</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemd.resource-control.html" target="_blank" rel="noopener noreferrer">systemd: Resource control</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemd-oomd.service.html" target="_blank" rel="noopener noreferrer">systemd: systemd-oomd</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'High Memory Usage and the Linux OOM Killer: Safe Diagnosis and Recovery',
            'slug' => 'linux-high-memory-oom-killer-troubleshooting',
            'image' => 'linux-memory-oom-troubleshooting.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Diagnose Linux High Memory and OOM Killer Events',
            'meta_keywords' => 'Linux high memory, OOM Killer, Linux memory leak, free available, vmstat, PSI memory, systemd memory limit',
            'meta_description' => 'Read Linux memory correctly, distinguish cache from pressure, identify leaking processes, analyze OOM kills, recover safely, and prevent recurrence.',
            'tags' => ['Linux', 'Memory', 'OOM Killer', 'Performance', 'Troubleshooting', 'systemd', 'cgroups', 'Sysadmin'],
            'body' => <<<'HTML'
<p><strong>High “used” RAM on Linux does not automatically mean memory exhaustion.</strong> The kernel uses idle RAM for page cache and can reclaim it for applications. A real incident appears when <code>MemAvailable</code> remains low, swapping becomes active, processes stall, or the kernel invokes the OOM Killer.</p>

<h2>Understand used, free, and available</h2>
<pre><code>free -h
cat /proc/meminfo | head -20</code></pre>
<p>In <code>free</code>, examine <strong>available</strong> before <strong>free</strong>. <code>MemAvailable</code> estimates memory available to new applications without swapping, including reclaimable page cache and slab. A large cache often shows efficient RAM use rather than a leak.</p>
<p>Persistent declining availability, swap traffic, latency, and killed workloads are meaningful signals. One snapshot is insufficient; collect a time series.</p>

<h2>1. Confirm memory pressure</h2>
<pre><code>free -h
vmstat 1 10
cat /proc/pressure/memory
swapon --show</code></pre>
<p>In <code>vmstat</code>, <code>si</code> and <code>so</code> report swap-in and swap-out. Used swap alone is not proof of current pressure; continuous traffic with latency is concerning. PSI in <code>/proc/pressure/memory</code> measures time tasks are stalled by memory contention. Sustained <code>full</code> pressure means all non-idle workloads are stalled and the system risks thrashing or OOM.</p>

<h2>2. Find memory-consuming processes</h2>
<pre><code>ps -eo pid,ppid,user,comm,rss,vsz,%mem --sort=-rss | head -20
top
systemd-cgtop</code></pre>
<p>RSS is resident memory, but summing it may count shared pages more than once. VSZ includes mapped address space and is not physical RAM consumption. Inspect a candidate:</p>
<pre><code>cat /proc/PID/status
cat /proc/PID/smaps_rollup
pmap -x PID | tail -1</code></pre>
<p><code>smaps_rollup</code> distinguishes private, shared, anonymous, and file-backed memory. In containers or systemd, inspect cgroups because a cgroup OOM can occur while the host still has memory.</p>

<h2>3. Separate valid load, cache, and leaks</h2>
<ul><li><strong>Valid load:</strong> memory follows traffic and declines after load drops or workers recycle.</li><li><strong>Page cache:</strong> Cached is high while MemAvailable remains healthy.</li><li><strong>Memory leak:</strong> private or anonymous RSS grows across load cycles without returning to baseline.</li><li><strong>Unbounded queue:</strong> backlog retains objects; fix throughput and backpressure.</li><li><strong>tmpfs/shmem:</strong> files in <code>/dev/shm</code> or tmpfs consume RAM.</li><li><strong>Kernel/slab:</strong> process RSS does not explain usage; inspect <code>Slab</code>, <code>SReclaimable</code>, and relevant subsystems.</li></ul>
<p>Graph RSS, request rate, queue depth, and deployment times together. Time correlation is more useful than restarting and erasing evidence.</p>

<h2>4. Confirm an OOM kill</h2>
<pre><code>journalctl -k -g 'Out of memory|Killed process|oom-kill' --since today
dmesg -T | grep -Ei 'out of memory|killed process|oom-kill'
journalctl -u systemd-oomd --since today
oomctl</code></pre>
<p>The kernel OOM Killer selects a task using heuristics, memory and swap consumption, and <code>oom_score_adj</code>. <code>systemd-oomd</code> is a userspace mechanism that may act earlier using cgroup and pressure data. Logs reveal whether the event was global or cgroup-scoped, the victim, usage, and constraints.</p>
<p>The killed process is not necessarily the only cause. Traffic spikes, incorrect limits, worker counts, another leak, or host overcommit may have created the condition.</p>

<h2>5. Stabilize an active incident</h2>
<ol><li>Stop or reduce noncritical traffic and jobs.</li><li>Capture <code>free</code>, <code>vmstat</code>, process, cgroup, and OOM evidence.</li><li>Scale workloads elsewhere or reduce concurrency safely.</li><li>Restart the leaking service when immediate recovery is required.</li><li>Add temporary swap only with a clear latency and storage assessment.</li><li>Monitor afterward; a restart is not a root-cause fix.</li></ol>
<p>Do not treat <code>echo 3 &gt; /proc/sys/vm/drop_caches</code> as a RAM fix. Dropping useful cache can worsen I/O and latency; the kernel already reclaims cache when required.</p>

<h2>6. Swap is a buffer, not free RAM</h2>
<p>Swap can absorb bursts and move cold pages out of RAM, buying response time, but storage is far slower. No swap creates a thinner safety margin; large swap does not repair an infinite leak.</p>
<pre><code>swapon --show
cat /proc/sys/vm/swappiness
vmstat 1</code></pre>
<p>Tune <code>vm.swappiness</code> only after measuring the workload. A low value does not mean “never swap.” Databases, latency-sensitive services, and Kubernetes require workload-specific guidance.</p>

<h2>7. Set limits and preserve headroom</h2>
<p>A systemd service can use cgroup controls:</p>
<pre><code>[Service]
MemoryHigh=1500M
MemoryMax=2G
OOMPolicy=stop</code></pre>
<p><code>MemoryHigh</code> creates soft pressure and throttling; <code>MemoryMax</code> is a hard ceiling. Derive values from valid peak data. After editing the unit:</p>
<pre><code>sudo systemctl daemon-reload
sudo systemctl restart example.service
systemctl show example.service -p MemoryCurrent -p MemoryPeak -p MemoryHigh -p MemoryMax</code></pre>
<p>Container limits must include heap, native allocations, page cache, and sidecars. Reserve headroom for the kernel, SSH, monitoring, and background daemons; do not allocate 100% of host RAM.</p>

<h2>8. Fix the application instead of only adding RAM</h2>
<ul><li>Limit workers and concurrency using peak memory per worker.</li><li>Stream or chunk data instead of loading entire files or query results.</li><li>Bound queues, timeouts, payloads, and uploads.</li><li>Configure cache eviction and maximum memory.</li><li>Recycle workers after a measured request count as risk reduction, not a permanent leak mask.</li><li>Profile heap and native memory in a production-like environment.</li></ul>
<p>More RAM is valid when the working set truly grows. If the curve grows without bound, it only postpones OOM.</p>

<h2>9. Monitor and alert early</h2>
<p>Track <code>MemAvailable</code>, cgroup working set, swap I/O, memory PSI, OOM events, restart counts, and queue depth. Alert on sustained pressure and duration rather than RAM-used percentage alone.</p>
<p>Retain post-deployment metrics long enough to detect slow leaks. Alerts should link to a runbook with evidence commands, service ownership, and explicit scale or restart conditions.</p>

<h2>Common mistakes</h2>
<ul><li>Calling high used RAM a leak without checking available and cache.</li><li>Adding all RSS values as an exact system total.</li><li>Restarting before preserving logs and charts.</li><li>Disabling OOM behavior or setting <code>oom_score_adj=-1000</code> broadly.</li><li>Running drop_caches periodically as optimization.</li><li>Adding swap or RAM while queues and concurrency remain unbounded.</li><li>Monitoring only the host and missing container/cgroup OOM events.</li></ul>

<h2>Incident checklist</h2>
<ol><li>Confirm MemAvailable, swap activity, and PSI over time.</li><li>Identify the process/cgroup and memory type that is growing.</li><li>Read kernel and systemd-oomd logs to classify the OOM.</li><li>Preserve evidence before restart or termination.</li><li>Shed load and recover services by priority.</li><li>Fix leaks, concurrency, queue behavior, or cache policy.</li><li>Set appropriate MemoryHigh/MemoryMax and host headroom.</li><li>Load-test and alert before the OOM threshold.</li></ol>

<h2>Conclusion</h2>
<p>Linux memory investigation begins with pressure and reclaimability, not a red used-memory number. Combine MemAvailable, PSI, swap I/O, process and cgroup metrics, and OOM logs to find the cause. A restart may restore service, but limits, backpressure, and application fixes prevent recurrence.</p>

<h2>References</h2>
<ul><li><a href="https://docs.kernel.org/filesystems/proc.html" target="_blank" rel="noopener noreferrer">Linux Kernel: The /proc filesystem</a></li><li><a href="https://docs.kernel.org/accounting/psi.html" target="_blank" rel="noopener noreferrer">Linux Kernel: Pressure Stall Information</a></li><li><a href="https://docs.kernel.org/admin-guide/sysctl/vm.html" target="_blank" rel="noopener noreferrer">Linux Kernel: VM sysctl documentation</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemd.resource-control.html" target="_blank" rel="noopener noreferrer">systemd: Resource control</a></li><li><a href="https://www.freedesktop.org/software/systemd/man/latest/systemd-oomd.service.html" target="_blank" rel="noopener noreferrer">systemd: systemd-oomd</a></li></ul>
HTML,
        ],
    ],
];
