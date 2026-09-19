<?php

return [
    'macos-time-machine-backup.html' => [
        'vi' => [
            'title' => 'Sao lưu và khôi phục macOS với Time Machine: Hướng dẫn an toàn từ A-Z',
            'slug' => 'sao-luu-khoi-phuc-macos-time-machine',
            'image' => 'macos-time-machine-backup.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Sao lưu và khôi phục macOS bằng Time Machine',
            'meta_keywords' => 'Time Machine macOS, sao lưu MacBook, khôi phục macOS, backup Mac, Migration Assistant, APFS Encrypted',
            'meta_description' => 'Hướng dẫn thiết lập Time Machine, mã hóa ổ backup, khôi phục file, chuyển dữ liệu sang Mac mới và kiểm thử bản sao lưu macOS.',
            'tags' => ['macOS', 'Time Machine', 'Backup', 'Data Recovery', 'MacBook', 'Migration Assistant', 'APFS', 'Security'],
            'body' => <<<'HTML'
<p><strong>Time Machine là công cụ sao lưu tích hợp trên macOS, có thể lưu nhiều phiên bản của tài liệu, ứng dụng, tài khoản và cài đặt.</strong> Tuy nhiên, cắm ổ ngoài rồi thấy biểu tượng sao lưu chưa đủ để bảo đảm dữ liệu có thể phục hồi. Một cấu hình tốt cần ổ đĩa phù hợp, mã hóa, lịch chạy thực tế, kiểm tra định kỳ và thêm một bản sao nằm ngoài máy.</p>

<h2>iCloud không hoàn toàn thay thế backup</h2>
<p>iCloud Drive chủ yếu đồng bộ dữ liệu giữa thiết bị. Nếu một file bị xóa hoặc thay đổi ngoài ý muốn, thay đổi đó có thể được đồng bộ sang các thiết bị khác. Time Machine lưu lịch sử theo thời điểm, giúp lấy lại phiên bản cũ hoặc dữ liệu đã xóa. Hai dịch vụ bổ sung cho nhau nhưng giải quyết hai bài toán khác nhau.</p>
<p>Với dữ liệu quan trọng, nên áp dụng nguyên tắc 3-2-1: có ít nhất ba bản dữ liệu, trên hai loại phương tiện, trong đó một bản nằm ngoài địa điểm hoặc ngoài thiết bị chính. Time Machine trên một ổ đặt cạnh Mac vẫn có thể mất cùng máy khi trộm cắp, hỏa hoạn hoặc sự cố điện.</p>

<h2>1. Chọn ổ sao lưu phù hợp</h2>
<p>Apple khuyến nghị ổ Time Machine có dung lượng ít nhất gấp đôi lượng dữ liệu cần sao lưu. Ví dụ Mac dùng khoảng 700 GB thì ổ 2 TB hợp lý hơn ổ 1 TB vì Time Machine cần không gian giữ nhiều phiên bản.</p>
<ul><li><strong>Ổ gắn trực tiếp:</strong> USB hoặc Thunderbolt, dễ thiết lập và thường nhanh nhất.</li><li><strong>NAS hỗ trợ Time Machine:</strong> tiện sao lưu tự động trong mạng; ưu tiên SMB nếu thiết bị cho lựa chọn.</li><li><strong>Nhiều ổ luân phiên:</strong> giảm rủi ro một ổ hỏng và cho phép cất một ổ ở vị trí khác.</li></ul>
<p>APFS hoặc APFS Encrypted là định dạng được ưu tiên cho ổ Time Machine mới. Nếu ổ đang chứa dữ liệu, hãy sao chép dữ liệu đó sang nơi an toàn trước khi format vì thao tác Erase trong Disk Utility sẽ xóa toàn bộ ổ.</p>

<h2>2. Chuẩn bị và mã hóa ổ backup</h2>
<p>Kết nối ổ, mở <strong>Disk Utility</strong>, chọn đúng thiết bị vật lý và dùng <strong>Erase</strong> nếu cần. Chọn scheme GUID Partition Map và APFS. Nếu muốn dùng cùng ổ cho file thông thường, hãy tạo một APFS volume riêng thay vì trộn file vào volume Time Machine.</p>
<blockquote>Mã hóa backup là mặc định nên dùng. Ổ sao lưu chứa lịch sử tài liệu, email và dữ liệu tài khoản; người cầm ổ không nên đọc được chúng nếu thiếu mật khẩu.</blockquote>
<p>Lưu mật khẩu mã hóa trong password manager hoặc nơi khôi phục được. Nếu mất mật khẩu, Apple không thể mở khóa bản backup thay bạn. Với ổ mạng, mật khẩu chia sẻ NAS và mật khẩu mã hóa backup là hai lớp khác nhau.</p>

<h2>3. Thiết lập Time Machine</h2>
<ol><li>Mở <strong>Apple menu &gt; System Settings &gt; General &gt; Time Machine</strong>.</li><li>Chọn <strong>Add Backup Disk</strong>.</li><li>Chọn ổ ngoài hoặc đích mạng được hỗ trợ.</li><li>Bật mã hóa, đặt mật khẩu mạnh và lưu mật khẩu an toàn.</li><li>Chờ lần sao lưu đầu tiên hoàn tất trước khi tháo ổ.</li></ol>
<p>Lần đầu có thể mất nhiều giờ vì phải sao chép phần lớn dữ liệu. Các lần sau thường nhanh hơn vì Time Machine chỉ sao lưu thay đổi. Với MacBook, nên cắm nguồn trong lần đầu và kết nối ổ trực tiếp thay vì qua hub không ổn định.</p>

<h2>4. Chọn tần suất và danh sách loại trừ</h2>
<p>Trong <strong>Time Machine &gt; Options</strong>, macOS cho phép chọn sao lưu thủ công hoặc tự động theo tần suất được hỗ trợ. Lịch tốt nhất là lịch diễn ra mà không phụ thuộc vào trí nhớ. Ổ luôn kết nối hoặc NAS phù hợp hơn nếu người dùng thường quên cắm ổ.</p>
<p>Chỉ loại trừ dữ liệu có thể tái tạo hoặc đã được bảo vệ bằng hệ thống khác, chẳng hạn cache lớn, máy ảo thử nghiệm hay thư mục build. Không loại trừ <code>Documents</code>, thư viện ảnh hoặc source code chưa có remote chỉ để lần backup đầu chạy nhanh hơn.</p>
<p>Apple lưu ý rằng mục bị loại trừ vẫn có thể xuất hiện trong local snapshots. Local snapshot giúp phục hồi ngắn hạn khi ổ backup chưa kết nối, nhưng nó nằm trên chính Mac nên không phải bản sao độc lập.</p>

<h2>5. Theo dõi và kiểm tra bản sao lưu</h2>
<p>Sau lần chạy, mở Time Machine settings và xác nhận thời gian backup gần nhất. Thỉnh thoảng mở giao diện Time Machine, chọn một file thử nghiệm từ mốc cũ và khôi phục vào thư mục tạm. Kiểm tra file có mở được và nội dung đúng.</p>
<p>Với backup mạng đã từng chạy thành công, có thể giữ phím Option khi mở menu Time Machine để chọn <strong>Verify Backups</strong> trên các phiên bản macOS hỗ trợ. Với ổ gắn trực tiếp, dùng <strong>First Aid</strong> trong Disk Utility khi nghi ngờ lỗi filesystem, nhưng vẫn cần thử khôi phục file thực tế.</p>
<blockquote>“Backup completed” chỉ xác nhận tác vụ đã kết thúc. Một lần restore thử mới xác nhận quy trình phục hồi hoạt động.</blockquote>

<h2>6. Khôi phục một file hoặc phiên bản cũ</h2>
<ol><li>Kết nối ổ Time Machine.</li><li>Mở Finder tại thư mục từng chứa file.</li><li>Mở Time Machine từ menu hoặc Spotlight.</li><li>Di chuyển tới thời điểm cần lấy lại.</li><li>Chọn file hoặc thư mục và bấm <strong>Restore</strong>.</li></ol>
<p>Nếu file hiện tại vẫn tồn tại, macOS có thể hỏi giữ bản gốc, thay thế hoặc giữ cả hai. Với tài liệu quan trọng, nên khôi phục sang vị trí tạm và so sánh trước khi ghi đè.</p>

<h2>7. Khôi phục toàn bộ hoặc chuyển sang Mac mới</h2>
<p>Time Machine không đơn giản chép nguyên hệ điều hành cũ đè lên máy mới. Nếu ổ khởi động hỏng, trước tiên sửa hoặc thay ổ và cài lại macOS. Sau đó dùng <strong>Migration Assistant</strong> để chuyển tài khoản, ứng dụng, tài liệu và cài đặt từ bản Time Machine.</p>
<p>Trên máy đã thiết lập, mở <strong>Applications &gt; Utilities &gt; Migration Assistant</strong>, chọn chuyển từ Mac, Time Machine hoặc startup disk, chọn bản backup và các nhóm dữ liệu. Nếu tên tài khoản đã tồn tại, đọc kỹ lựa chọn thay thế hay đổi tên để tránh ghi đè ngoài ý muốn.</p>
<p>Sau khi chuyển xong, kiểm tra đăng nhập, thư viện ảnh, email, ứng dụng có license, project lập trình, SSH key và dữ liệu cloud chưa tải về. Một số ứng dụng cần đăng nhập hoặc cấp quyền lại.</p>

<h2>8. Xử lý Time Machine chạy chậm hoặc thất bại</h2>
<ul><li><strong>Preparing kéo dài:</strong> chờ indexing, kiểm tra antivirus và file lớn thay đổi thường xuyên.</li><li><strong>Ổ không xuất hiện:</strong> kết nối trực tiếp, đổi cáp hoặc cổng, kiểm tra Finder và Disk Utility.</li><li><strong>Ổ read-only:</strong> chạy First Aid, kiểm tra filesystem và quyền truy cập.</li><li><strong>Không đủ dung lượng:</strong> dùng ổ lớn hơn hoặc loại trừ dữ liệu có thể tái tạo; Apple đề xuất dung lượng lý tưởng ít nhất gấp đôi dữ liệu cần backup.</li><li><strong>Backup mạng lỗi:</strong> kiểm tra Wi-Fi/Ethernet, SMB, firmware NAS, VPN và firewall bên thứ ba.</li><li><strong>Mac gần đầy:</strong> giải phóng dung lượng ổ trong vì Time Machine cần không gian tạm và local snapshot.</li></ul>
<p>Không xóa thủ công cấu trúc thư mục bên trong backup để lấy chỗ. Time Machine tự quản lý lịch sử và loại bỏ bản cũ khi cần. Nếu phải bắt đầu lại, chỉ erase ổ sau khi đã xác nhận còn bản sao khác của dữ liệu quan trọng.</p>

<h2>9. Những dữ liệu cần chiến lược riêng</h2>
<p>Database đang chạy, máy ảo, thư viện video lớn và dữ liệu ứng dụng chuyên dụng có thể thay đổi trong lúc backup. Hãy dùng cơ chế export hoặc snapshot nhất quán của ứng dụng, rồi để Time Machine sao lưu artifact đó. Developer cũng nên push Git repository lên remote; Time Machine bảo vệ working tree, còn remote Git tạo thêm lịch sử và bản sao ngoài máy.</p>
<p>Thư viện ảnh dùng iCloud Photos với tùy chọn Optimize Mac Storage có thể không giữ toàn bộ bản gốc trên Mac. Hãy hiểu dữ liệu nào thực sự có trên ổ trong và cân nhắc tải bản gốc hoặc có phương án xuất độc lập.</p>

<h2>10. Checklist backup macOS đáng tin cậy</h2>
<ol><li>Ổ backup có dung lượng tối thiểu khoảng gấp đôi dữ liệu đang dùng.</li><li>Backup được mã hóa và mật khẩu nằm trong password manager.</li><li>Lịch chạy phù hợp với tần suất thay đổi dữ liệu.</li><li>Danh sách loại trừ chỉ chứa dữ liệu có thể tái tạo.</li><li>Thời gian backup gần nhất được kiểm tra định kỳ.</li><li>Mỗi tháng thử khôi phục ít nhất một file.</li><li>Có thêm một bản sao ngoài máy hoặc ngoài địa điểm.</li><li>Dữ liệu đặc thù như database và máy ảo có quy trình nhất quán riêng.</li><li>Quy trình Migration Assistant được ghi lại trước khi có sự cố.</li></ol>

<h2>Kết luận</h2>
<p>Time Machine cung cấp một nền tảng backup thuận tiện cho macOS, nhưng độ tin cậy đến từ cách vận hành: chọn ổ đủ lớn, bật mã hóa, sao lưu tự động, giữ thêm bản ngoài máy và thử restore định kỳ. Khi những bước này trở thành thói quen, Time Machine không chỉ là lịch sử file mà là một kế hoạch phục hồi thực sự.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://support.apple.com/mac-backup" target="_blank" rel="noopener noreferrer">Apple Support: Back up your Mac</a></li><li><a href="https://support.apple.com/guide/mac-help/back-up-files-mh35860/mac" target="_blank" rel="noopener noreferrer">Apple macOS User Guide: Back up files with Time Machine</a></li><li><a href="https://support.apple.com/102551" target="_blank" rel="noopener noreferrer">Apple Support: Restore your Mac from a backup</a></li><li><a href="https://support.apple.com/102220" target="_blank" rel="noopener noreferrer">Apple Support: Time Machine troubleshooting</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Back Up and Restore macOS with Time Machine: A Complete Safe Guide',
            'slug' => 'back-up-restore-macos-time-machine-safely',
            'image' => 'macos-time-machine-backup.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Back Up and Restore macOS with Time Machine',
            'meta_keywords' => 'Time Machine macOS, MacBook backup, restore macOS, Mac backup, Migration Assistant, APFS Encrypted',
            'meta_description' => 'Set up Time Machine, encrypt the backup disk, restore files, migrate to a new Mac, troubleshoot failures, and test recovery.',
            'tags' => ['macOS', 'Time Machine', 'Backup', 'Data Recovery', 'MacBook', 'Migration Assistant', 'APFS', 'Security'],
            'body' => <<<'HTML'
<p><strong>Time Machine is macOS's built-in backup tool for keeping historical versions of documents, applications, accounts, and settings.</strong> However, connecting an external drive and seeing a successful status is not enough to guarantee recovery. A dependable setup needs appropriate storage, encryption, a practical schedule, regular validation, and another copy away from the Mac.</p>

<h2>iCloud is not a complete backup replacement</h2>
<p>iCloud Drive primarily synchronizes data between devices. If a file is deleted or changed accidentally, that change may synchronize elsewhere. Time Machine preserves points in time, making it possible to retrieve deleted files or older versions. The services complement each other but solve different problems.</p>
<p>Important data should follow the 3-2-1 principle: at least three copies, on two types of media, with one copy off-site or away from the primary device. A Time Machine drive sitting beside the Mac can be lost with it to theft, fire, or electrical damage.</p>

<h2>1. Choose an appropriate backup disk</h2>
<p>Apple recommends a Time Machine disk with at least twice the storage capacity of the data being backed up. If a Mac uses about 700 GB, a 2 TB disk is more practical than 1 TB because Time Machine needs room for multiple historical versions.</p>
<ul><li><strong>Direct-attached drive:</strong> USB or Thunderbolt is simple and usually fastest.</li><li><strong>Time Machine-compatible NAS:</strong> convenient for automatic network backups; prefer SMB when offered a choice.</li><li><strong>Rotating drives:</strong> reduces single-drive risk and lets one copy stay elsewhere.</li></ul>
<p>APFS or APFS Encrypted is preferred for a new Time Machine disk. Copy existing data elsewhere before formatting because Erase in Disk Utility removes everything on the drive.</p>

<h2>2. Prepare and encrypt the backup disk</h2>
<p>Connect the drive, open <strong>Disk Utility</strong>, select the correct physical device, and use <strong>Erase</strong> if necessary. Choose GUID Partition Map and APFS. To keep ordinary files on the same physical drive, create a separate APFS volume instead of mixing them into the Time Machine volume.</p>
<blockquote>Encryption should be the normal choice. A backup contains historical documents, email, and account data; possession of the drive should not provide access without a password.</blockquote>
<p>Store the encryption password in a password manager or another recoverable location. Apple cannot unlock the backup if the password is lost. For network storage, the NAS share password and backup encryption password are separate security layers.</p>

<h2>3. Set up Time Machine</h2>
<ol><li>Open <strong>Apple menu &gt; System Settings &gt; General &gt; Time Machine</strong>.</li><li>Select <strong>Add Backup Disk</strong>.</li><li>Choose the external drive or supported network destination.</li><li>Enable encryption, create a strong password, and store it safely.</li><li>Let the first backup complete before disconnecting the drive.</li></ol>
<p>The initial backup can take hours because it copies most data. Later runs are generally faster because Time Machine saves changes. Keep a MacBook connected to power for the first run and attach the drive directly instead of through an unreliable hub.</p>

<h2>4. Choose frequency and exclusions</h2>
<p>Under <strong>Time Machine &gt; Options</strong>, macOS offers manual backup and supported automatic frequencies. The best schedule runs without relying on memory. An always-connected drive or NAS is useful when users often forget to attach storage.</p>
<p>Exclude only reproducible data or information already protected elsewhere, such as large caches, disposable virtual machines, or build directories. Do not exclude Documents, photo libraries, or source code without a remote merely to make the first backup faster.</p>
<p>Apple notes that excluded items may still appear in local snapshots. Local snapshots can provide short-term recovery while the backup disk is disconnected, but they remain on the Mac and are not an independent copy.</p>

<h2>5. Monitor and test the backup</h2>
<p>After a run, open Time Machine settings and confirm the latest backup time. Periodically enter Time Machine, select a test file from an earlier point, and restore it to a temporary folder. Verify that the file opens and contains the expected data.</p>
<p>For a network backup that has completed before, holding Option while opening the Time Machine menu may expose <strong>Verify Backups</strong> on supported macOS versions. For a direct-attached disk, Disk Utility's <strong>First Aid</strong> can check suspected filesystem issues, but neither replaces an actual restore test.</p>
<blockquote>“Backup completed” confirms that a task ended. A successful restore test confirms that recovery works.</blockquote>

<h2>6. Restore one file or an older version</h2>
<ol><li>Connect the Time Machine disk.</li><li>Open Finder at the folder where the file belonged.</li><li>Open Time Machine from the menu or Spotlight.</li><li>Navigate to the required point in time.</li><li>Select the file or folder and click <strong>Restore</strong>.</li></ol>
<p>If a current file already exists, macOS may offer to keep the original, replace it, or keep both. Restore important documents to a temporary location and compare them before overwriting.</p>

<h2>7. Recover everything or migrate to a new Mac</h2>
<p>Time Machine does not simply overwrite a new Mac with an old operating system. If a startup disk fails, repair or replace it and reinstall macOS first. Then use <strong>Migration Assistant</strong> to move accounts, applications, documents, and settings from the Time Machine backup.</p>
<p>On an already configured Mac, open <strong>Applications &gt; Utilities &gt; Migration Assistant</strong>, choose transfer from a Mac, Time Machine, or startup disk, and select the backup and data categories. If an account name already exists, carefully review whether to replace or rename it.</p>
<p>After migration, verify login, photo libraries, email, licensed applications, development projects, SSH keys, and cloud files that may not have been downloaded. Some applications require authentication or permissions again.</p>

<h2>8. Troubleshoot slow or failed backups</h2>
<ul><li><strong>Preparing for a long time:</strong> allow indexing to finish and inspect antivirus activity or large, frequently changing files.</li><li><strong>Disk is missing:</strong> connect it directly, change the cable or port, and inspect Finder and Disk Utility.</li><li><strong>Disk is read-only:</strong> run First Aid and check its filesystem and access.</li><li><strong>Not enough space:</strong> use a larger drive or exclude reproducible data; Apple recommends ideally twice the backup data size.</li><li><strong>Network failure:</strong> inspect Wi-Fi or Ethernet, SMB, NAS firmware, VPN, and third-party firewall software.</li><li><strong>The Mac is nearly full:</strong> recover internal capacity because Time Machine needs space for temporary data and local snapshots.</li></ul>
<p>Do not manually delete folders inside a backup to recover space. Time Machine manages history and removes old backups when required. Erase and restart only after confirming another copy of important data exists.</p>

<h2>9. Data that needs a separate strategy</h2>
<p>Running databases, virtual machines, large video libraries, and specialized application data can change during backup. Use the application's consistent export or snapshot mechanism and let Time Machine protect the resulting artifact. Developers should also push Git repositories to a remote: Time Machine protects the working tree while remote Git adds history and an off-device copy.</p>
<p>An iCloud Photos library using Optimize Mac Storage may not keep every original locally. Understand which data is physically present and consider downloading originals or maintaining an independent export.</p>

<h2>10. Reliable macOS backup checklist</h2>
<ol><li>The backup disk is at least about twice the size of currently used data.</li><li>The backup is encrypted and its password is in a password manager.</li><li>The schedule matches how frequently data changes.</li><li>Exclusions contain only reproducible data.</li><li>The latest backup time is reviewed regularly.</li><li>At least one file is test-restored every month.</li><li>Another copy exists away from the Mac or off-site.</li><li>Databases and virtual machines use an application-consistent process.</li><li>The Migration Assistant recovery procedure is documented before an incident.</li></ol>

<h2>Conclusion</h2>
<p>Time Machine provides a convenient macOS backup foundation, but reliability comes from operation: choose sufficient storage, enable encryption, automate the schedule, maintain another off-device copy, and test restores. With those habits, Time Machine becomes a recovery plan rather than merely a history of files.</p>

<h2>References</h2>
<ul><li><a href="https://support.apple.com/mac-backup" target="_blank" rel="noopener noreferrer">Apple Support: Back up your Mac</a></li><li><a href="https://support.apple.com/guide/mac-help/back-up-files-mh35860/mac" target="_blank" rel="noopener noreferrer">Apple macOS User Guide: Back up files with Time Machine</a></li><li><a href="https://support.apple.com/102551" target="_blank" rel="noopener noreferrer">Apple Support: Restore your Mac from a backup</a></li><li><a href="https://support.apple.com/102220" target="_blank" rel="noopener noreferrer">Apple Support: Time Machine troubleshooting</a></li></ul>
HTML,
        ],
    ],
];
