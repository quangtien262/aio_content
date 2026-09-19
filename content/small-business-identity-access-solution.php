<?php

return [
    'small-business-identity-access-solution.html' => [
        'vi' => [
            'title' => 'Giải pháp quản lý tài khoản cho doanh nghiệp nhỏ: Password manager, MFA, SSO và offboarding',
            'slug' => 'giai-phap-quan-ly-tai-khoan-doanh-nghiep-nho',
            'image' => 'small-business-identity-access-solution.jpg',
            'category_vi' => 'Giải pháp', 'category_en' => 'Solutions',
            'meta_title' => 'Giải pháp quản lý tài khoản cho doanh nghiệp nhỏ',
            'meta_keywords' => 'quản lý tài khoản doanh nghiệp, password manager, MFA, SSO, passkey, offboarding, quản lý truy cập',
            'meta_description' => 'Xây dựng giải pháp quản lý tài khoản cho doanh nghiệp nhỏ với password manager, MFA, SSO, phân quyền và quy trình offboarding an toàn.',
            'tags' => ['Identity Management', 'Password Manager', 'MFA', 'SSO', 'Passkey', 'Access Control', 'Offboarding', 'SME Security'],
            'body' => <<<'HTML'
<p><strong>Rủi ro tài khoản trong doanh nghiệp nhỏ thường không bắt đầu từ kỹ thuật phức tạp, mà từ mật khẩu dùng chung, quyền không được thu hồi và tài khoản quan trọng chỉ một người biết.</strong> Một giải pháp quản lý danh tính tốt phải giúp nhân viên đăng nhập thuận tiện hơn, đồng thời cho doanh nghiệp biết ai có quyền gì và có thể thu hồi quyền nhanh khi cần.</p>

<h2>Bốn vấn đề cần giải quyết</h2>
<ul><li><strong>Mật khẩu phân tán:</strong> nằm trong trình duyệt cá nhân, bảng tính, chat hoặc trí nhớ.</li><li><strong>Tài khoản dùng chung:</strong> không biết ai đã đăng nhập hoặc thay đổi dữ liệu.</li><li><strong>MFA thiếu nhất quán:</strong> tài khoản quan trọng vẫn chỉ có mật khẩu hoặc SMS.</li><li><strong>Offboarding không đầy đủ:</strong> nhân sự nghỉ việc nhưng token, API key, email chuyển tiếp và quyền ứng dụng vẫn còn.</li></ul>
<p>Mục tiêu không phải mua càng nhiều công cụ càng tốt. Mục tiêu là tạo một nguồn danh tính đáng tin cậy, giảm số lần nhập mật khẩu, áp dụng xác thực mạnh và quản lý toàn bộ vòng đời truy cập.</p>

<h2>Kiến trúc phù hợp cho doanh nghiệp nhỏ</h2>
<p>Một mô hình thực dụng có bốn lớp:</p>
<ol><li><strong>Directory/Identity Provider:</strong> nơi quản lý tài khoản nhân viên và nhóm.</li><li><strong>SSO:</strong> dùng một danh tính doanh nghiệp để truy cập các ứng dụng hỗ trợ.</li><li><strong>Password manager doanh nghiệp:</strong> lưu credential cho hệ thống chưa hỗ trợ SSO và chia sẻ qua vault, không gửi mật khẩu.</li><li><strong>MFA hoặc passkey:</strong> bảo vệ đăng nhập, ưu tiên phương thức chống phishing cho tài khoản quan trọng.</li></ol>
<blockquote>SSO không loại bỏ nhu cầu password manager. Nhiều website, thiết bị, tài khoản hạ tầng và secret khẩn cấp vẫn chưa hỗ trợ federation.</blockquote>

<h2>1. Kiểm kê tài khoản trước khi chọn công cụ</h2>
<p>Tạo danh sách tối thiểu gồm ứng dụng, chủ sở hữu nghiệp vụ, admin, hình thức đăng nhập, MFA, dữ liệu chứa bên trong, chi phí và cách thu hồi. Đặc biệt tìm:</p>
<ul><li>Tài khoản tạo bằng email cá nhân.</li><li>Tài khoản admin chung hoặc không rõ chủ.</li><li>Ứng dụng tự gia hạn nhưng không còn người dùng.</li><li>API key, SSH key và service account tồn tại lâu.</li><li>Email alias hoặc forwarding ra ngoài tổ chức.</li><li>Tài khoản nhà cung cấp có thể truy cập dữ liệu khách hàng.</li></ul>
<p>Xếp hạng theo tác động: email và identity provider đứng đầu vì có thể reset nhiều tài khoản khác; tiếp theo là tài chính, domain/DNS, cloud, source code, CRM và backup.</p>

<h2>2. Chọn password manager doanh nghiệp</h2>
<p>Không dùng một vault cá nhân rồi chia master password cho cả nhóm. Sản phẩm dành cho doanh nghiệp nên có:</p>
<ul><li>Mỗi người có tài khoản riêng và MFA.</li><li>Vault hoặc collection theo phòng ban, dự án và mức nhạy cảm.</li><li>Phân quyền xem, dùng, sửa và quản trị.</li><li>Audit log, cảnh báo mật khẩu yếu hoặc tái sử dụng.</li><li>Thu hồi thiết bị và session khi nhân sự nghỉ việc.</li><li>Khôi phục quản trị có kiểm soát, không phụ thuộc một người.</li><li>Export/backup và kế hoạch chuyển đổi nhà cung cấp.</li><li>Hỗ trợ passkey, extension và nền tảng mà doanh nghiệp sử dụng.</li></ul>
<p>NIST ghi nhận password manager giúp tạo mật khẩu dài, ngẫu nhiên và khác nhau cho từng dịch vụ. Điểm tập trung này cũng khiến vault trở thành mục tiêu giá trị cao, vì vậy master passphrase phải mạnh và tài khoản vault phải bật MFA chống phishing khi có thể.</p>

<h2>3. Chính sách mật khẩu thực tế</h2>
<p>Không ép nhân viên đổi mật khẩu định kỳ nếu không có dấu hiệu lộ lọt, và không dựa vào quy tắc hình thức như bắt buộc đổi chữ hoa, số và ký tự theo chu kỳ. Thay vào đó:</p>
<ul><li>Dùng mật khẩu sinh ngẫu nhiên, dài và duy nhất từ password manager.</li><li>Với master password phải nhớ, dùng passphrase dài và không tái sử dụng.</li><li>Chặn mật khẩu phổ biến hoặc đã xuất hiện trong dữ liệu rò rỉ.</li><li>Cho phép paste và autofill để hỗ trợ password manager.</li><li>Rate-limit đăng nhập và cảnh báo hành vi bất thường.</li><li>Đổi credential ngay khi có bằng chứng compromise hoặc nhân sự liên quan rời tổ chức.</li></ul>

<h2>4. MFA: ưu tiên chống phishing</h2>
<p>Không phải MFA nào cũng có sức bảo vệ như nhau. SMS và mã OTP vẫn tốt hơn chỉ dùng mật khẩu nhưng có thể bị phishing hoặc chuyển SIM. NIST mô tả WebAuthn/FIDO2 là phương thức chống phishing vì credential được ràng buộc với domain hợp lệ.</p>
<p>Thứ tự ưu tiên thực tế:</p>
<ol><li>Passkey hoặc security key cho email, IdP, cloud, domain và tài khoản admin.</li><li>Authenticator app với TOTP cho dịch vụ chưa hỗ trợ phương thức chống phishing.</li><li>Push MFA có number matching và context, nếu được cấu hình tốt.</li><li>SMS chỉ như phương án chuyển tiếp hoặc recovery có kiểm soát.</li></ol>
<p>Tài khoản admin nên có hai authenticator vật lý hoặc hai thiết bị độc lập. Một thiết bị được dùng hằng ngày, thiết bị dự phòng niêm phong ở vị trí an toàn.</p>

<h2>5. Dùng SSO ở nơi tạo ra giá trị</h2>
<p>SSO giúp vô hiệu hóa một danh tính trung tâm để cắt quyền trên nhiều ứng dụng, giảm số mật khẩu và cho phép áp chính sách đăng nhập thống nhất. Ưu tiên kết nối SSO cho ứng dụng chứa dữ liệu nhạy cảm, nhiều người dùng hoặc có thay đổi nhân sự thường xuyên.</p>
<p>Trước khi mua gói cao hơn chỉ để có SSO, tính tổng chi phí: phí license, thời gian cấp/thu hồi thủ công, rủi ro tài khoản sót và chi phí audit. Với ứng dụng ít rủi ro, password manager cùng quy trình offboarding có thể đủ; với email, CRM, source code hoặc cloud, SSO thường đáng giá hơn.</p>

<h2>6. Phân quyền theo nhóm, không theo từng người</h2>
<p>Tạo nhóm theo vai trò như Finance, Sales, Engineering và External Contractors. Gán quyền ứng dụng hoặc vault cho nhóm, sau đó thêm người vào nhóm. Cách này giảm quyền sót và làm review dễ hơn.</p>
<p>Áp dụng least privilege: quyền admin chỉ dùng khi cần; tài khoản thường dùng cho công việc hằng ngày. Với hệ thống quan trọng, tách tài khoản admin khỏi tài khoản email thông thường và ghi audit log cho thay đổi quyền.</p>

<h2>7. Onboarding có thể lặp lại</h2>
<ol><li>Quản lý xác nhận vai trò, ngày bắt đầu và ứng dụng cần dùng.</li><li>Tạo tài khoản bằng email doanh nghiệp, không dùng email cá nhân.</li><li>Thêm vào nhóm quyền chuẩn, tránh cấp từng quyền ngẫu hứng.</li><li>Đăng ký ít nhất hai phương thức recovery phù hợp.</li><li>Hướng dẫn password manager, MFA, nhận biết phishing và cách báo mất thiết bị.</li><li>Yêu cầu đổi secret tạm và xác minh đăng nhập.</li><li>Lưu người phê duyệt, thời gian và quyền đã cấp.</li></ol>

<h2>8. Offboarding trong ngày nhân sự rời đi</h2>
<p>Offboarding phải có owner và thời điểm rõ ràng giữa HR, quản lý và IT:</p>
<ol><li>Vô hiệu hóa tài khoản IdP/email và thu hồi session.</li><li>Thu hồi thiết bị, security key, VPN và certificate.</li><li>Xóa khỏi nhóm, vault, repository, cloud và công cụ tài chính.</li><li>Chuyển ownership của file, lịch, automation, dashboard và tài khoản quảng cáo.</li><li>Xoay mật khẩu dùng chung, API key, SSH key và webhook mà người đó từng tiếp cận.</li><li>Kiểm tra forwarding rule, OAuth grant và personal access token.</li><li>Giữ dữ liệu theo chính sách pháp lý, sau đó xóa đúng hạn.</li><li>Ghi lại người thực hiện và bằng chứng hoàn tất.</li></ol>
<blockquote>Đổi mật khẩu email không thu hồi mọi quyền. Session, OAuth token, API key và SSH key có vòng đời riêng.</blockquote>

<h2>9. Tài khoản khẩn cấp và recovery</h2>
<p>Tạo tối thiểu hai tài khoản break-glass cho identity provider hoặc cloud quan trọng. Không dùng chúng hằng ngày, không gắn vào email của một nhân viên, bảo vệ bằng security key riêng và cảnh báo mỗi lần đăng nhập.</p>
<p>Lưu recovery code mã hóa và một bản offline được kiểm soát. Mỗi quý, xác minh tài khoản còn hoạt động, credential dự phòng còn truy cập được và danh sách người được phép sử dụng vẫn đúng. Recovery quá dễ có thể trở thành đường vòng bỏ qua MFA.</p>

<h2>10. Tài khoản dịch vụ và secret máy-máy</h2>
<p>Không dùng tài khoản nhân viên cho automation. Service account cần tên mô tả mục đích, owner, quyền tối thiểu, ngày hết hạn hoặc review, và secret nằm trong secret manager hay CI/CD vault thay vì source code.</p>
<p>Khi nền tảng hỗ trợ workload identity hoặc credential ngắn hạn, ưu tiên chúng thay cho API key sống lâu. Theo dõi lần sử dụng cuối để phát hiện secret bỏ quên và xoay key theo rủi ro, không chỉ theo lịch máy móc.</p>

<h2>11. Review quyền định kỳ</h2>
<p>Mỗi quý, gửi danh sách quyền cho manager và owner ứng dụng xác nhận. Tập trung vào admin, tài khoản không đăng nhập, người chuyển phòng ban, contractor hết hợp đồng và ứng dụng không còn owner. Review phải dẫn tới thu hồi thực tế, không chỉ ký xác nhận.</p>
<p>Theo dõi các chỉ số đơn giản: tỷ lệ MFA, tỷ lệ phương thức chống phishing, số tài khoản admin, thời gian offboarding, số tài khoản không owner và số secret quá hạn review.</p>

<h2>Lộ trình triển khai 30 ngày</h2>
<ol><li><strong>Tuần 1:</strong> kiểm kê ứng dụng, admin, account chung và rủi ro.</li><li><strong>Tuần 2:</strong> triển khai password manager, nhập vault theo nhóm và bật MFA.</li><li><strong>Tuần 3:</strong> kết nối SSO cho ứng dụng quan trọng, chuẩn hóa nhóm quyền.</li><li><strong>Tuần 4:</strong> chạy thử onboarding/offboarding, kiểm tra break-glass và sửa khoảng trống.</li></ol>
<p>Bắt đầu với email, IdP, domain, cloud và tài chính. Không chờ kiểm kê hoàn hảo mới bảo vệ các tài khoản có khả năng reset hoặc chiếm quyền toàn hệ thống.</p>

<h2>Checklist nghiệm thu</h2>
<ol><li>Mỗi nhân viên có danh tính riêng; không chia sẻ tài khoản cá nhân.</li><li>Password manager doanh nghiệp có vault theo nhóm và audit log.</li><li>Email, IdP, cloud và domain dùng MFA chống phishing khi hỗ trợ.</li><li>Quyền được gán qua nhóm và có owner phê duyệt.</li><li>Onboarding và offboarding có checklist, SLA và bằng chứng.</li><li>Session, OAuth token, API key và SSH key nằm trong phạm vi thu hồi.</li><li>Có hai đường recovery hoặc break-glass được kiểm thử.</li><li>Service account không phụ thuộc tài khoản nhân viên.</li><li>Review quyền được thực hiện ít nhất mỗi quý.</li></ol>

<h2>Kết luận</h2>
<p>Giải pháp quản lý tài khoản hiệu quả không chỉ là một password manager hay nút bật MFA. Nó là vòng đời hoàn chỉnh từ cấp quyền, sử dụng, review đến thu hồi. Với doanh nghiệp nhỏ, kết hợp directory, SSO có chọn lọc, vault theo nhóm và xác thực chống phishing tạo ra mức bảo vệ cao mà vẫn giữ vận hành gọn nhẹ.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://pages.nist.gov/800-63-4/sp800-63b.html" target="_blank" rel="noopener noreferrer">NIST SP 800-63B-4: Authentication and Authenticator Management</a></li><li><a href="https://pages.nist.gov/800-63-4/sp800-63b/authenticators/" target="_blank" rel="noopener noreferrer">NIST: Authenticator Requirements</a></li><li><a href="https://pages.nist.gov/800-63-FAQ/" target="_blank" rel="noopener noreferrer">NIST Digital Identity Guidelines FAQ</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Account Management for Small Businesses: Password Managers, MFA, SSO, and Offboarding',
            'slug' => 'small-business-account-management-passwords-mfa-sso-offboarding',
            'image' => 'small-business-identity-access-solution.jpg',
            'category_vi' => 'Giải pháp', 'category_en' => 'Solutions',
            'meta_title' => 'Account Management Solutions for Small Businesses',
            'meta_keywords' => 'business account management, password manager, MFA, SSO, passkeys, offboarding, access management',
            'meta_description' => 'Build small-business account management with a password manager, MFA, SSO, group permissions, recovery, and secure offboarding.',
            'tags' => ['Identity Management', 'Password Manager', 'MFA', 'SSO', 'Passkey', 'Access Control', 'Offboarding', 'SME Security'],
            'body' => <<<'HTML'
<p><strong>Account risk in a small business rarely begins with advanced technology; it begins with shared passwords, access that was never revoked, and critical accounts known by only one person.</strong> A sound identity solution makes sign-in easier for staff while letting the business know who has access and revoke it quickly.</p>

<h2>The four problems to solve</h2>
<ul><li><strong>Scattered passwords:</strong> stored in personal browsers, spreadsheets, chats, or memory.</li><li><strong>Shared accounts:</strong> no reliable record of who signed in or changed data.</li><li><strong>Inconsistent MFA:</strong> important accounts still rely on passwords or SMS.</li><li><strong>Incomplete offboarding:</strong> sessions, tokens, API keys, forwarding rules, and application access remain after departure.</li></ul>
<p>The goal is not to buy the most tools. It is to establish a trustworthy identity source, reduce password entry, apply strong authentication, and manage the entire access lifecycle.</p>

<h2>A practical small-business architecture</h2>
<ol><li><strong>Directory or identity provider:</strong> manages employee accounts and groups.</li><li><strong>SSO:</strong> uses one business identity for supported applications.</li><li><strong>Business password manager:</strong> stores credentials for systems without SSO and shares through vaults rather than messages.</li><li><strong>MFA or passkeys:</strong> protects sign-in, prioritizing phishing-resistant methods for critical accounts.</li></ol>
<blockquote>SSO does not eliminate the password manager. Many websites, devices, infrastructure accounts, and emergency secrets do not support federation.</blockquote>

<h2>1. Inventory accounts before choosing tools</h2>
<p>Record the application, business owner, administrators, login method, MFA, stored data, cost, and revocation process. Pay particular attention to:</p>
<ul><li>Accounts created with personal email addresses.</li><li>Shared or ownerless administrator accounts.</li><li>Auto-renewing applications with no active users.</li><li>Long-lived API keys, SSH keys, and service accounts.</li><li>Email aliases or forwarding outside the organization.</li><li>Vendor accounts that can access customer data.</li></ul>
<p>Rank by impact. Email and the identity provider come first because they can reset many other accounts, followed by finance, domains and DNS, cloud, source code, CRM, and backups.</p>

<h2>2. Select a business password manager</h2>
<p>Do not share one personal vault and its master password with a team. A business product should provide:</p>
<ul><li>Individual accounts with MFA.</li><li>Vaults or collections by department, project, and sensitivity.</li><li>Permissions for use, viewing, editing, and administration.</li><li>Audit logs and weak or reused password reporting.</li><li>Device and session revocation during offboarding.</li><li>Controlled administrative recovery without one-person dependency.</li><li>Export, backup, and provider-exit planning.</li><li>Passkey, browser, and platform support that matches the organization.</li></ul>
<p>NIST notes that password managers support long, random, unique passwords for every service. This concentration also makes the vault a high-value target, so use a strong master passphrase and phishing-resistant MFA where available.</p>

<h2>3. Use a practical password policy</h2>
<p>Do not force periodic password changes without evidence of compromise, and do not rely on rotating composition rules. Instead:</p>
<ul><li>Generate long, random, unique passwords with the manager.</li><li>Use a long, unique passphrase for a memorized master password.</li><li>Block common and known-compromised passwords.</li><li>Allow paste and autofill.</li><li>Rate-limit sign-in and alert on unusual behavior.</li><li>Rotate immediately after compromise or a relevant employee departure.</li></ul>

<h2>4. Prioritize phishing-resistant MFA</h2>
<p>MFA methods do not offer equal protection. SMS and manually entered OTP codes are better than passwords alone but can still be phished. NIST identifies WebAuthn and FIDO2 as phishing-resistant because credentials are bound to the legitimate domain.</p>
<ol><li>Passkeys or security keys for email, identity, cloud, domain, and admin accounts.</li><li>TOTP authenticator apps where phishing-resistant methods are unavailable.</li><li>Push MFA with number matching and context when configured well.</li><li>SMS only as a transitional or controlled recovery method.</li></ol>
<p>Administrator accounts should have two independent authenticators. Use one daily and seal the backup in a controlled location.</p>

<h2>5. Deploy SSO where it creates value</h2>
<p>SSO lets one central identity disable access across applications, reduces password count, and applies consistent sign-in policy. Prioritize applications with sensitive data, many users, or frequent staffing changes.</p>
<p>Before upgrading a plan solely for SSO, calculate subscription cost, manual provisioning time, orphan-account risk, and audit cost. A low-risk application may be adequately controlled through a password manager and offboarding checklist, while email, CRM, source code, and cloud generally justify SSO sooner.</p>

<h2>6. Assign access through groups</h2>
<p>Create role groups such as Finance, Sales, Engineering, and External Contractors. Assign application and vault access to groups, then place people in those groups. This reduces orphaned permissions and simplifies reviews.</p>
<p>Apply least privilege. Use administrator rights only when necessary and standard accounts for daily work. For critical systems, separate administrative accounts from ordinary email identities and audit permission changes.</p>

<h2>7. Repeatable onboarding</h2>
<ol><li>The manager confirms role, start date, and required applications.</li><li>Create the account with a business email, never a personal address.</li><li>Add standard access groups instead of granting ad hoc permissions.</li><li>Register at least two suitable recovery methods.</li><li>Train on the password manager, MFA, phishing, and lost-device reporting.</li><li>Replace temporary secrets and verify sign-in.</li><li>Record approver, time, and granted access.</li></ol>

<h2>8. Same-day offboarding</h2>
<ol><li>Disable identity and email accounts and revoke sessions.</li><li>Recover devices, security keys, VPN profiles, and certificates.</li><li>Remove access to groups, vaults, repositories, cloud, and finance tools.</li><li>Transfer ownership of files, calendars, automations, dashboards, and advertising accounts.</li><li>Rotate shared passwords, API keys, SSH keys, and webhooks the person could access.</li><li>Inspect forwarding rules, OAuth grants, and personal access tokens.</li><li>Retain and delete data according to legal policy.</li><li>Record completion evidence and the person responsible.</li></ol>
<blockquote>Changing an email password does not revoke every form of access. Sessions, OAuth tokens, API keys, and SSH keys have separate lifecycles.</blockquote>

<h2>9. Emergency access and recovery</h2>
<p>Create at least two break-glass accounts for critical identity or cloud platforms. Do not use them daily or tie them to one employee's email. Protect them with separate security keys and alert on every sign-in.</p>
<p>Store encrypted recovery codes plus a controlled offline copy. Quarterly, verify that emergency accounts work, backup credentials remain available, and authorized users are still correct. An overly easy recovery path can bypass MFA.</p>

<h2>10. Service accounts and machine secrets</h2>
<p>Do not run automation under employee accounts. Each service account needs a purpose, owner, minimum permissions, review or expiration date, and secrets in a secret manager or CI/CD vault rather than source code.</p>
<p>Prefer workload identity or short-lived credentials over long-lived API keys where supported. Track last use to find abandoned secrets and rotate according to risk, not merely a calendar.</p>

<h2>11. Review access regularly</h2>
<p>Each quarter, have managers and application owners confirm access lists. Focus on administrators, inactive accounts, transfers, expired contractors, and applications without owners. Reviews must result in actual revocation, not only a signed report.</p>
<p>Track simple measures: MFA coverage, phishing-resistant coverage, administrator count, offboarding time, ownerless accounts, and secrets overdue for review.</p>

<h2>A 30-day rollout</h2>
<ol><li><strong>Week 1:</strong> inventory applications, administrators, shared accounts, and risk.</li><li><strong>Week 2:</strong> deploy the password manager, build group vaults, and enable MFA.</li><li><strong>Week 3:</strong> connect SSO for critical applications and standardize permission groups.</li><li><strong>Week 4:</strong> test onboarding and offboarding, validate break-glass access, and close gaps.</li></ol>
<p>Start with email, identity, domains, cloud, and finance. Do not wait for a perfect inventory before protecting accounts that can reset or take over the wider environment.</p>

<h2>Acceptance checklist</h2>
<ol><li>Every employee has an individual identity; personal accounts are not shared.</li><li>The business password manager uses group vaults and audit logs.</li><li>Email, identity, cloud, and domain accounts use phishing-resistant MFA where supported.</li><li>Access is assigned through groups with an approving owner.</li><li>Onboarding and offboarding have checklists, service levels, and evidence.</li><li>Session, OAuth token, API key, and SSH key revocation is in scope.</li><li>Two tested recovery or break-glass paths exist.</li><li>Service accounts do not depend on employee identities.</li><li>Access is reviewed at least quarterly.</li></ol>

<h2>Conclusion</h2>
<p>Effective account management is not just a password manager or an MFA switch. It is a complete lifecycle from provisioning and use through review and revocation. For a small business, a directory, selective SSO, group vaults, and phishing-resistant authentication provide strong protection while keeping operations manageable.</p>

<h2>References</h2>
<ul><li><a href="https://pages.nist.gov/800-63-4/sp800-63b.html" target="_blank" rel="noopener noreferrer">NIST SP 800-63B-4: Authentication and Authenticator Management</a></li><li><a href="https://pages.nist.gov/800-63-4/sp800-63b/authenticators/" target="_blank" rel="noopener noreferrer">NIST: Authenticator Requirements</a></li><li><a href="https://pages.nist.gov/800-63-FAQ/" target="_blank" rel="noopener noreferrer">NIST Digital Identity Guidelines FAQ</a></li></ul>
HTML,
        ],
    ],
];
