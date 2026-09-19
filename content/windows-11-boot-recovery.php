<?php

return [
    'windows-11-boot-recovery.html' => [
        'vi' => [
            'title' => 'Windows 11 không khởi động: Hướng dẫn phục hồi an toàn với WinRE',
            'slug' => 'windows-11-khong-khoi-dong-phuc-hoi-winre',
            'image' => 'windows-11-boot-recovery.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Phục hồi Windows 11 không khởi động bằng WinRE',
            'meta_keywords' => 'Windows 11 không khởi động, WinRE, Startup Repair, Safe Mode, System Restore, DISM SFC, Reset this PC',
            'meta_description' => 'Quy trình phục hồi Windows 11 không khởi động bằng WinRE, Startup Repair, Safe Mode, gỡ update, System Restore, DISM/SFC và Reset an toàn.',
            'tags' => ['Windows 11', 'WinRE', 'Startup Repair', 'Safe Mode', 'System Restore', 'DISM', 'BitLocker', 'Recovery'],
            'body' => <<<'HTML'
<p><strong>Khi Windows 11 mắc kẹt ở logo, màn hình đen, màn hình xanh hoặc vòng lặp khởi động, đừng vội cài lại hệ điều hành.</strong> Windows Recovery Environment (WinRE) cung cấp nhiều lớp phục hồi từ ít tác động đến mạnh hơn. Đi đúng thứ tự giúp sửa lỗi mà giảm nguy cơ mất dữ liệu và ứng dụng.</p>
<blockquote>Trước mọi thao tác Reset hoặc cài lại, hãy sao lưu dữ liệu nếu còn khả năng truy cập ổ đĩa và chuẩn bị khóa khôi phục BitLocker.</blockquote>

<h2>Trước tiên: phân biệt lỗi Windows và lỗi phần cứng</h2>
<p>Nếu máy không lên nguồn, không vào được BIOS/UEFI, ổ đĩa không xuất hiện, phát tiếng bất thường hoặc tự tắt do nhiệt, WinRE không giải quyết được nguyên nhân. Ngắt thiết bị USB không cần thiết, kiểm tra nguồn, màn hình, RAM và trạng thái ổ lưu trữ trước.</p>
<p>Nếu BIOS nhận ổ và Windows bắt đầu tải rồi thất bại, khả năng cao quy trình bên dưới phù hợp. Ghi lại mã lỗi, thời điểm bắt đầu và thay đổi gần nhất như update, driver, phần mềm bảo mật hoặc thay phần cứng.</p>

<h2>Chuẩn bị dữ liệu và khóa BitLocker</h2>
<p>Nhiều công cụ WinRE yêu cầu khóa BitLocker 48 chữ số khi ổ hệ thống được mã hóa. Tìm khóa trong tài khoản Microsoft, tài khoản cơ quan hoặc bản in/lưu trữ đã tạo trước đó. Không đăng khóa lên diễn đàn hay gửi trong ảnh chụp.</p>
<p>Nếu dữ liệu chưa được backup và ổ có dấu hiệu hỏng, hạn chế ghi thêm dữ liệu. Ưu tiên tạo image hoặc nhờ kỹ thuật viên phục hồi. Reset, CHKDSK sửa lỗi hay cài lại đều có thể làm tình hình khó cứu hơn trên ổ vật lý đang hỏng.</p>

<h2>1. Vào Windows Recovery Environment</h2>
<p>Nếu còn vào Windows: mở <strong>Settings &gt; System &gt; Recovery &gt; Advanced startup &gt; Restart now</strong>. Nếu không khởi động được, Windows thường tự vào WinRE sau nhiều lần boot thất bại. Có thể boot từ USB cài đặt Windows chính thức rồi chọn <strong>Repair your computer</strong>, không chọn Install ngay.</p>
<p>Việc cố ý ngắt nguồn khi Windows đang boot chỉ nên dùng như phương án cuối để kích hoạt Automatic Repair; lặp lại không đúng lúc có thể làm hỏng dữ liệu đang ghi. Laptop cần cắm nguồn trong suốt quá trình phục hồi.</p>

<h2>2. Chạy Startup Repair</h2>
<p>Chọn <strong>Troubleshoot &gt; Advanced options &gt; Startup Repair</strong>. Công cụ kiểm tra một số vấn đề ngăn Windows tải và là bước ít tác động nên thử đầu tiên.</p>
<p>Nếu Startup Repair báo không thể sửa, không chạy lặp lại vô hạn. Ghi lại đường dẫn log nếu được hiển thị rồi chuyển sang bước dựa trên thay đổi gần nhất.</p>

<h2>3. Khởi động Safe Mode</h2>
<p>Chọn <strong>Advanced options &gt; Startup Settings &gt; Restart</strong>, sau đó chọn Safe Mode hoặc Safe Mode with Networking. Chế độ này tải tập driver và dịch vụ tối thiểu.</p>
<ul><li>Gỡ driver hoặc phần mềm vừa cài trước khi lỗi.</li><li>Trong Device Manager, rollback driver đồ họa, storage hoặc network nếu lỗi xuất hiện sau cập nhật.</li><li>Tắt startup app không cần thiết và kiểm tra Event Viewer.</li><li>Quét malware bằng Windows Security nếu nghi ngờ nhiễm mã độc.</li></ul>
<p>Nếu Safe Mode hoạt động ổn định, nguyên nhân thường nằm ở driver, dịch vụ, phần mềm hoặc cấu hình thay vì bootloader cơ bản.</p>

<h2>4. Gỡ bản cập nhật gần nhất</h2>
<p>Nếu lỗi bắt đầu ngay sau Windows Update, vào <strong>Advanced options &gt; Uninstall Updates</strong>. Thử gỡ quality update gần nhất trước vì phạm vi thay đổi nhỏ hơn. Chỉ gỡ feature update khi có căn cứ và tùy chọn còn khả dụng.</p>
<p>Sau khi máy khởi động lại, tạm dừng update trong thời gian ngắn để kiểm tra thông tin sự cố và driver tương thích; không tắt cập nhật bảo mật vĩnh viễn.</p>

<h2>5. Dùng System Restore</h2>
<p>Chọn <strong>Advanced options &gt; System Restore</strong>, mở danh sách restore point trước thời điểm lỗi và dùng <strong>Scan for affected programs</strong>. System Restore hoàn tác system file, registry, driver và ứng dụng đã thay đổi nhưng không nhằm xóa tài liệu cá nhân.</p>
<p>Các ứng dụng hoặc driver cài sau restore point có thể bị gỡ. Không tắt nguồn khi quá trình đang chạy. Tính năng chỉ dùng được khi máy đã có restore point.</p>

<h2>6. Sửa component store và system file</h2>
<p>Nếu Windows vẫn vào được Safe Mode, mở Terminal/Command Prompt với quyền Administrator và chạy:</p>
<pre><code>DISM.exe /Online /Cleanup-Image /RestoreHealth
sfc /scannow</code></pre>
<p>DISM sửa component store mà SFC dùng làm nguồn, vì vậy chạy DISM trước SFC. Khởi động lại sau khi hoàn tất. Nếu DISM báo thiếu source, cần image Windows cùng phiên bản/build hoặc nguồn sửa chữa phù hợp; không tải file DLL rời từ website không tin cậy.</p>
<p>Trong Command Prompt của WinRE, ký tự ổ Windows có thể không phải <code>C:</code>. Xác định đúng volume trước khi chạy lệnh offline; câu lệnh <code>/Online</code> ở WinRE áp dụng cho chính môi trường recovery, không phải bản Windows đang hỏng.</p>

<h2>7. Kiểm tra ổ đĩa có chủ đích</h2>
<p>Dùng công cụ chẩn đoán SMART của nhà sản xuất khi nghi ngờ ổ lưu trữ. <code>chkdsk</code> kiểm tra filesystem, không xác nhận toàn bộ sức khỏe phần cứng. Một lượt read-only có thể dùng để đánh giá:</p>
<pre><code>chkdsk C:</code></pre>
<p>Không vội dùng <code>/r</code> trên ổ đang hỏng vì quá trình đọc toàn bộ có thể kéo dài và gây thêm áp lực. Sao lưu hoặc clone trước khi sửa lỗi nặng.</p>

<h2>8. Reinstall bằng Windows Update khi máy còn vào được</h2>
<p>Trên Windows 11 được hỗ trợ, <strong>Settings &gt; System &gt; Recovery &gt; Fix problems using Windows Update</strong> có thể cài lại phiên bản hiện tại trong khi giữ file, ứng dụng và cài đặt. Đây là lựa chọn ít phá hủy hơn Reset khi Windows vẫn khởi động nhưng thành phần hệ thống lỗi.</p>
<p>Tùy chọn có thể không xuất hiện trên thiết bị do tổ chức quản lý hoặc phiên bản Windows cũ. Vẫn cần backup trước vì không có thao tác phục hồi nào hoàn toàn không rủi ro.</p>

<h2>9. Reset this PC: hiểu đúng hai lựa chọn</h2>
<p>Trong WinRE, chọn <strong>Troubleshoot &gt; Reset this PC</strong> khi các bước trên thất bại:</p>
<ul><li><strong>Keep my files:</strong> giữ file cá nhân nhưng gỡ ứng dụng và đặt lại cài đặt.</li><li><strong>Remove everything:</strong> xóa file, ứng dụng và cài đặt; phù hợp khi chuyển giao máy hoặc cần làm sạch sâu sau khi đã backup.</li></ul>
<p><strong>Cloud download</strong> tải image mới và cần Internet ổn định; <strong>Local reinstall</strong> dùng file có sẵn nhưng có thể thất bại nếu nguồn local hỏng. Đọc kỹ danh sách ổ bị ảnh hưởng, đặc biệt trên máy có nhiều ổ.</p>

<h2>10. Cài lại bằng USB khi không còn lựa chọn khác</h2>
<p>Dùng một máy đáng tin cậy tải công cụ tạo installation media từ Microsoft và USB ít nhất 8 GB. Ưu tiên recovery image của hãng máy nếu cần driver hoặc tiện ích phần cứng đặc thù.</p>
<p>Clean install có thể xóa toàn bộ file, ứng dụng và cài đặt. Xác nhận backup, BitLocker key, license phần mềm, dữ liệu trình duyệt và tài khoản đồng bộ trước. Ngắt các ổ không cần thiết để giảm nguy cơ chọn nhầm đích.</p>

<h2>Trường hợp không nên tiếp tục tự sửa</h2>
<ul><li>Ổ đĩa mất khỏi BIOS, phát tiếng lạ hoặc SMART báo lỗi nghiêm trọng.</li><li>Dữ liệu chưa backup có giá trị cao.</li><li>Máy dùng BitLocker nhưng không có recovery key.</li><li>Thiết bị thuộc doanh nghiệp, có MDM hoặc chính sách bảo mật.</li><li>Lỗi quay lại sau clean install, gợi ý RAM, SSD, nguồn hoặc mainboard.</li></ul>

<h2>Checklist phòng ngừa</h2>
<ol><li>Bật Windows Backup hoặc giải pháp backup độc lập.</li><li>Lưu khóa BitLocker ở ít nhất một nơi an toàn ngoài máy.</li><li>Tạo Recovery Drive khi máy đang hoạt động tốt.</li><li>Duy trì restore point trước thay đổi driver/hệ thống lớn.</li><li>Giữ USB cài đặt và thông tin license/phần mềm quan trọng.</li><li>Theo dõi sức khỏe SSD và dung lượng trống.</li><li>Không bỏ qua firmware, driver và update bảo mật từ nguồn chính thức.</li></ol>

<h2>Kết luận</h2>
<p>Phục hồi Windows 11 nên đi theo bậc thang: Startup Repair, Safe Mode, gỡ update, System Restore, sửa system file, cài lại giữ nguyên dữ liệu, rồi mới Reset hoặc clean install. Thứ tự này bảo toàn lựa chọn và giảm thay đổi không cần thiết. Backup và khóa BitLocker được chuẩn bị trước sự cố vẫn là hai công cụ phục hồi quan trọng nhất.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://support.microsoft.com/en-us/windows/experience/backup-recovery/recovery-options-in-windows" target="_blank" rel="noopener noreferrer">Microsoft Support: Recovery options in Windows</a></li><li><a href="https://support.microsoft.com/en-us/windows/experience/backup-recovery/system-restore" target="_blank" rel="noopener noreferrer">Microsoft Support: System Restore</a></li><li><a href="https://support.microsoft.com/en-us/windows/deployment/install-upgrade/fix-issues-by-reinstalling-the-current-version-of-windows" target="_blank" rel="noopener noreferrer">Microsoft Support: Reinstall the current version using Windows Update</a></li><li><a href="https://learn.microsoft.com/en-us/troubleshoot/windows-client/installing-updates-features-roles/common-windows-update-errors" target="_blank" rel="noopener noreferrer">Microsoft Learn: DISM and SFC for update errors</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Windows 11 Will Not Boot: A Safe Recovery Guide Using WinRE',
            'slug' => 'windows-11-will-not-boot-winre-recovery',
            'image' => 'windows-11-boot-recovery.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Recover Windows 11 Boot Failures with WinRE',
            'meta_keywords' => 'Windows 11 not booting, WinRE, Startup Repair, Safe Mode, System Restore, DISM SFC, Reset this PC',
            'meta_description' => 'Recover a Windows 11 boot failure using WinRE, Startup Repair, Safe Mode, update removal, System Restore, DISM/SFC, and safe reset options.',
            'tags' => ['Windows 11', 'WinRE', 'Startup Repair', 'Safe Mode', 'System Restore', 'DISM', 'BitLocker', 'Recovery'],
            'body' => <<<'HTML'
<p><strong>When Windows 11 is stuck at its logo, a black or blue screen, or a restart loop, do not rush to reinstall it.</strong> Windows Recovery Environment (WinRE) offers recovery layers from minimally disruptive to destructive. Using them in order reduces the risk to applications and data.</p>
<blockquote>Before Reset or reinstall, back up accessible data and obtain the BitLocker recovery key.</blockquote>

<h2>First distinguish Windows failure from hardware failure</h2>
<p>If the PC does not power on, cannot enter BIOS/UEFI, no longer detects its drive, makes unusual storage noises, or shuts down thermally, WinRE cannot fix the cause. Disconnect unnecessary USB devices and inspect power, display, memory, and storage detection first.</p>
<p>If firmware sees the drive and Windows starts loading before failure, the process below is appropriate. Record the stop code, onset time, and latest change, such as an update, driver, security application, or hardware replacement.</p>

<h2>Prepare data and the BitLocker key</h2>
<p>Many WinRE operations request the 48-digit recovery key when the system drive is encrypted. Retrieve it from the Microsoft account, work account, printout, or storage location prepared earlier. Never post the key publicly or include it in screenshots.</p>
<p>If data is not backed up and the drive appears unhealthy, minimize writes. Create an image or seek professional recovery. Reset, repair-mode CHKDSK, and reinstallation can make physical-drive recovery harder.</p>

<h2>1. Enter Windows Recovery Environment</h2>
<p>From working Windows, open <strong>Settings &gt; System &gt; Recovery &gt; Advanced startup &gt; Restart now</strong>. Windows normally enters WinRE after repeated boot failures. Alternatively, boot official Windows installation media and select <strong>Repair your computer</strong> instead of Install.</p>
<p>Interrupting power during startup should be a last resort for triggering Automatic Repair; poorly timed repeated shutdowns can corrupt active writes. Keep a laptop connected to power during recovery.</p>

<h2>2. Run Startup Repair</h2>
<p>Select <strong>Troubleshoot &gt; Advanced options &gt; Startup Repair</strong>. It checks several problems that prevent Windows from loading and is the first low-impact tool to try.</p>
<p>If it cannot repair the PC, do not repeat it indefinitely. Record any displayed log path and choose the next step based on the latest system change.</p>

<h2>3. Boot Safe Mode</h2>
<p>Select <strong>Advanced options &gt; Startup Settings &gt; Restart</strong>, then choose Safe Mode or Safe Mode with Networking. This loads a minimal set of drivers and services.</p>
<ul><li>Remove software or drivers installed immediately before the failure.</li><li>Use Device Manager to roll back graphics, storage, or network drivers.</li><li>Disable unnecessary startup applications and inspect Event Viewer.</li><li>Run Windows Security if malware is suspected.</li></ul>
<p>If Safe Mode is stable, the cause is more likely a driver, service, application, or configuration than the basic boot path.</p>

<h2>4. Uninstall the latest update</h2>
<p>If failure began immediately after Windows Update, open <strong>Advanced options &gt; Uninstall Updates</strong>. Remove the latest quality update first because it has a smaller scope. Remove a feature update only with good reason and while the option remains available.</p>
<p>After recovery, pause updates briefly to investigate the incident and compatible drivers, but do not disable security updates permanently.</p>

<h2>5. Use System Restore</h2>
<p>Select <strong>Advanced options &gt; System Restore</strong>, choose a restore point before the incident, and use <strong>Scan for affected programs</strong>. System Restore rolls back system files, registry, drivers, and installed applications without targeting personal documents.</p>
<p>Applications and drivers installed after the restore point may be removed. Do not power off during restoration. This option requires an existing restore point.</p>

<h2>6. Repair the component store and system files</h2>
<p>If Safe Mode works, open an elevated Terminal or Command Prompt:</p>
<pre><code>DISM.exe /Online /Cleanup-Image /RestoreHealth
sfc /scannow</code></pre>
<p>DISM repairs the component store used by SFC, so run DISM first. Restart afterward. If DISM reports a missing source, use a matching Windows image/build or valid repair source; never download loose DLLs from untrusted websites.</p>
<p>Inside WinRE Command Prompt, the Windows volume may not be <code>C:</code>. Identify it before offline commands. The <code>/Online</code> target in WinRE refers to the recovery environment itself, not automatically to the broken Windows installation.</p>

<h2>7. Check storage deliberately</h2>
<p>Use the manufacturer's SMART diagnostics when storage failure is suspected. <code>chkdsk</code> examines the filesystem and does not prove complete device health. A read-only pass can help assess it:</p>
<pre><code>chkdsk C:</code></pre>
<p>Do not immediately use <code>/r</code> on a failing drive; its full-surface read is lengthy and stressful. Back up or clone important data before aggressive repair.</p>

<h2>8. Reinstall through Windows Update when Windows still starts</h2>
<p>On supported Windows 11 systems, <strong>Settings &gt; System &gt; Recovery &gt; Fix problems using Windows Update</strong> can reinstall the current version while preserving files, applications, and settings. It is less disruptive than Reset when Windows starts but system components are damaged.</p>
<p>The option may be unavailable on managed devices or older Windows releases. Back up first because no recovery process is entirely risk-free.</p>

<h2>9. Understand Reset this PC</h2>
<p>In WinRE, use <strong>Troubleshoot &gt; Reset this PC</strong> after earlier steps fail:</p>
<ul><li><strong>Keep my files:</strong> retains personal files but removes applications and resets settings.</li><li><strong>Remove everything:</strong> removes files, applications, and settings; use after backup for disposal or deeper cleanup.</li></ul>
<p><strong>Cloud download</strong> retrieves a fresh image and needs stable Internet. <strong>Local reinstall</strong> uses existing files but may fail when they are damaged. Review every affected drive, especially on multi-drive systems.</p>

<h2>10. Reinstall from USB as the last resort</h2>
<p>Use a trusted PC to obtain Microsoft's installation-media tool and an 8 GB or larger USB drive. Consider the manufacturer's recovery image when model-specific drivers and utilities matter.</p>
<p>A clean installation can remove all files, applications, and settings. Confirm backups, BitLocker keys, software licenses, browser data, and synchronized accounts. Disconnect unnecessary drives to reduce the risk of selecting the wrong destination.</p>

<h2>When to stop self-repair</h2>
<ul><li>The drive disappears from firmware, makes noise, or reports critical SMART errors.</li><li>Valuable data has no backup.</li><li>BitLocker is enabled but no recovery key exists.</li><li>The device is company-managed with MDM or security policy.</li><li>Failure returns after a clean installation, suggesting RAM, SSD, power, or motherboard trouble.</li></ul>

<h2>Prevention checklist</h2>
<ol><li>Enable Windows Backup or an independent backup solution.</li><li>Store the BitLocker key securely in at least one off-device location.</li><li>Create a Recovery Drive while the system is healthy.</li><li>Maintain restore points before major driver or system changes.</li><li>Keep installation media and important software/license information.</li><li>Monitor SSD health and free space.</li><li>Install supported firmware, drivers, and security updates from official sources.</li></ol>

<h2>Conclusion</h2>
<p>Windows 11 recovery should follow a ladder: Startup Repair, Safe Mode, update removal, System Restore, system-file repair, a preserving reinstall, and only then Reset or clean installation. This order preserves options and limits unnecessary change. A current backup and accessible BitLocker key remain the most valuable recovery tools.</p>

<h2>References</h2>
<ul><li><a href="https://support.microsoft.com/en-us/windows/experience/backup-recovery/recovery-options-in-windows" target="_blank" rel="noopener noreferrer">Microsoft Support: Recovery options in Windows</a></li><li><a href="https://support.microsoft.com/en-us/windows/experience/backup-recovery/system-restore" target="_blank" rel="noopener noreferrer">Microsoft Support: System Restore</a></li><li><a href="https://support.microsoft.com/en-us/windows/deployment/install-upgrade/fix-issues-by-reinstalling-the-current-version-of-windows" target="_blank" rel="noopener noreferrer">Microsoft Support: Reinstall the current version using Windows Update</a></li><li><a href="https://learn.microsoft.com/en-us/troubleshoot/windows-client/installing-updates-features-roles/common-windows-update-errors" target="_blank" rel="noopener noreferrer">Microsoft Learn: DISM and SFC for update errors</a></li></ul>
HTML,
        ],
    ],
];
