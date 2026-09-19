<?php

return [
    'huong-dan-cai-dat-email-server.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài đặt email server trên Ubuntu với Postfix và Dovecot',
            'slug' => 'huong-dan-cai-dat-email-server',
            'image' => 'huong-dan-cai-dat-email-server.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Cài email server Postfix, Dovecot trên Ubuntu',
            'meta_keywords' => 'cài email server, Postfix, Dovecot, Rspamd, DKIM, SPF, DMARC, Ubuntu mail server',
            'meta_description' => 'Hướng dẫn dựng email server Ubuntu với Postfix, Dovecot, Rspamd, TLS, SPF, DKIM, DMARC, backup và kiểm tra chống open relay.',
            'tags' => ['Email Server', 'Postfix', 'Dovecot', 'Rspamd', 'DKIM', 'DMARC', 'Ubuntu Server'],
            'body' => <<<'HTML'
<p><strong>Tự vận hành email server không chỉ là cài SMTP và IMAP.</strong> Để thư đến đúng inbox, máy chủ cần IP có uy tín, reverse DNS, TLS, SPF, DKIM, DMARC, lọc spam, chống open relay, giám sát queue và backup có thể khôi phục. Bài này dựng một máy chủ cho một domain trên Ubuntu bằng Postfix, Dovecot và Rspamd.</p>
<blockquote>Nếu email là dịch vụ sống còn nhưng không có người trực vận hành, hãy dùng nhà cung cấp email được quản lý. Một cấu hình đúng hôm nay vẫn cần vá lỗi, theo dõi blacklist, xử lý spam và phục hồi dữ liệu về sau.</blockquote>

<h2>Kiến trúc sử dụng</h2>
<table><thead><tr><th>Thành phần</th><th>Vai trò</th></tr></thead><tbody><tr><td>Postfix</td><td>Nhận và gửi SMTP; cung cấp submission cho người dùng</td></tr><tr><td>Dovecot</td><td>Lưu mailbox dạng Maildir, IMAP và xác thực SMTP</td></tr><tr><td>Rspamd + Redis</td><td>Chấm điểm spam, tích hợp milter và ký DKIM</td></tr><tr><td>Let's Encrypt</td><td>Chứng chỉ TLS cho SMTP/IMAP</td></tr><tr><td>Fail2ban/UFW</td><td>Giảm brute force và giới hạn bề mặt mạng</td></tr></tbody></table>
<p>Ví dụ dùng domain <code>example.com</code>, hostname <code>mail.example.com</code> và IPv4 <code>203.0.113.10</code>. Hãy thay toàn bộ giá trị mẫu bằng thông tin thật.</p>

<h2>1. Kiểm tra điều kiện trước khi thuê VPS</h2>
<ul><li>Nhà cung cấp cho phép outbound TCP 25 và không NAT chung IP gửi thư.</li><li>Có thể đặt PTR/reverse DNS của IP thành <code>mail.example.com</code>.</li><li>IP chưa nằm trong blacklist lớn và không phải dải có uy tín xấu.</li><li>Máy có IP tĩnh, tối thiểu khoảng 2 GB RAM cho stack nhỏ.</li><li>Domain cho phép chỉnh A, MX và TXT.</li></ul>
<p>Nếu outbound 25 bị chặn, có thể dùng SMTP relay uy tín cho chiều gửi. Không tìm cách vượt chính sách nhà cung cấp.</p>

<h2>2. Tạo DNS nền tảng</h2>
<table><thead><tr><th>Loại</th><th>Tên</th><th>Giá trị mẫu</th></tr></thead><tbody><tr><td>A</td><td>mail</td><td>203.0.113.10</td></tr><tr><td>MX</td><td>@</td><td>10 mail.example.com.</td></tr><tr><td>TXT</td><td>@</td><td>v=spf1 mx -all</td></tr></tbody></table>
<p>Yêu cầu nhà cung cấp VPS đặt PTR của <code>203.0.113.10</code> về <code>mail.example.com</code>. Forward-confirmed reverse DNS phải khớp: hostname này cũng cần phân giải trở lại đúng IP.</p>
<p>Nếu chưa vận hành IPv6 đầy đủ và chưa có PTR IPv6, đừng tạo AAAA cho mail host và nên cấu hình Postfix chỉ dùng IPv4.</p>

<h2>3. Chuẩn bị Ubuntu và firewall</h2>
<pre><code>hostnamectl set-hostname mail.example.com
apt update &amp;&amp; apt full-upgrade -y
apt install -y postfix dovecot-imapd dovecot-lmtpd \
  rspamd redis-server certbot fail2ban swaks ufw</code></pre>
<p>Khi Postfix hỏi, chọn <strong>Internet Site</strong> và nhập <code>example.com</code> làm system mail name.</p>
<pre><code>ufw default deny incoming
ufw default allow outgoing
ufw allow from ADMIN_IP to any port 22 proto tcp
ufw allow 25/tcp
ufw allow 80/tcp
ufw allow 587/tcp
ufw allow 993/tcp
ufw enable</code></pre>
<p>Port 25 dành cho server-to-server, 587 cho người dùng gửi thư có xác thực, 993 cho IMAPS và 80 dùng cấp/gia hạn chứng chỉ. Không mở database, Redis hoặc cổng quản trị nội bộ ra Internet.</p>

<h2>4. Cấp chứng chỉ TLS</h2>
<p>Đảm bảo A record đã trỏ đúng và port 80 chưa bị chiếm:</p>
<pre><code>certbot certonly --standalone \
  -d mail.example.com \
  --agree-tos -m admin@example.com --no-eff-email</code></pre>
<p>Chứng chỉ nằm tại <code>/etc/letsencrypt/live/mail.example.com/</code>. Thêm deploy hook để reload dịch vụ sau gia hạn:</p>
<pre><code>cat &gt; /etc/letsencrypt/renewal-hooks/deploy/reload-mail.sh &lt;&lt;'SH'
#!/bin/sh
systemctl reload postfix
systemctl reload dovecot
SH
chmod 750 /etc/letsencrypt/renewal-hooks/deploy/reload-mail.sh
certbot renew --dry-run</code></pre>

<h2>5. Cấu hình Postfix</h2>
<p>Thiết lập identity, mailbox, TLS, Dovecot SASL và giới hạn relay:</p>
<pre><code>postconf -e 'myhostname = mail.example.com'
postconf -e 'mydomain = example.com'
postconf -e 'myorigin = $mydomain'
postconf -e 'mydestination = $myhostname, localhost.$mydomain, localhost, $mydomain'
postconf -e 'inet_interfaces = all'
postconf -e 'inet_protocols = ipv4'
postconf -e 'mynetworks = 127.0.0.0/8'
postconf -e 'home_mailbox = Maildir/'
postconf -e 'smtpd_tls_cert_file = /etc/letsencrypt/live/mail.example.com/fullchain.pem'
postconf -e 'smtpd_tls_key_file = /etc/letsencrypt/live/mail.example.com/privkey.pem'
postconf -e 'smtpd_tls_security_level = may'
postconf -e 'smtp_tls_security_level = may'
postconf -e 'smtpd_sasl_type = dovecot'
postconf -e 'smtpd_sasl_path = private/auth'
postconf -e 'smtpd_sasl_auth_enable = yes'
postconf -e 'smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination'</code></pre>
<p><code>mynetworks</code> chỉ tin localhost. Không thêm dải mạng rộng tùy tiện vì có thể biến máy thành open relay.</p>
<p>Trong <code>/etc/postfix/master.cf</code>, bật service submission và yêu cầu TLS:</p>
<pre><code>submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject</code></pre>

<h2>6. Cấu hình Dovecot</h2>
<p>Trong <code>/etc/dovecot/conf.d/10-mail.conf</code>:</p>
<pre><code>mail_location = maildir:~/Maildir</code></pre>
<p>Trong <code>10-auth.conf</code>:</p>
<pre><code>disable_plaintext_auth = yes
auth_mechanisms = plain login</code></pre>
<p>Trong block <code>service auth</code> của <code>10-master.conf</code>:</p>
<pre><code>unix_listener /var/spool/postfix/private/auth {
  mode = 0660
  user = postfix
  group = postfix
}</code></pre>
<p>Trong <code>10-ssl.conf</code> của Dovecot 2.3 trên Ubuntu:</p>
<pre><code>ssl = required
ssl_cert = &lt;/etc/letsencrypt/live/mail.example.com/fullchain.pem
ssl_key = &lt;/etc/letsencrypt/live/mail.example.com/privkey.pem</code></pre>
<p>Cú pháp Dovecot có thay đổi giữa các nhánh lớn. Luôn đối chiếu phiên bản cài đặt bằng <code>dovecot --version</code> và kiểm tra cấu hình bằng <code>doveconf -n</code>.</p>

<h2>7. Tạo mailbox đầu tiên</h2>
<p>Mô hình đơn giản dùng system user cho một domain:</p>
<pre><code>adduser alice
maildirmake.dovecot /home/alice/Maildir
chown -R alice:alice /home/alice/Maildir</code></pre>
<p>Tài khoản đăng nhập là <code>alice</code>; địa chỉ nhận là <code>alice@example.com</code>. Mô hình này dễ hiểu nhưng không phù hợp khi cần nhiều domain hoặc hàng trăm mailbox. Khi đó nên dùng virtual users với SQL/LDAP hoặc một mail suite được đóng gói.</p>

<h2>8. Tích hợp Rspamd với Postfix</h2>
<p>Rspamd thường lắng nghe milter ở <code>localhost:11332</code>. Xác minh bằng <code>ss -lntp</code>, sau đó:</p>
<pre><code>postconf -e 'milter_protocol = 6'
postconf -e 'milter_default_action = accept'
postconf -e 'smtpd_milters = inet:127.0.0.1:11332'
postconf -e 'non_smtpd_milters = inet:127.0.0.1:11332'</code></pre>
<p><code>milter_default_action=accept</code> giúp SMTP không ngừng hoàn toàn khi bộ lọc tạm lỗi, nhưng cần cảnh báo để không bỏ qua sự cố kéo dài.</p>

<h2>9. Tạo DKIM và xuất bản DNS</h2>
<pre><code>mkdir -p /var/lib/rspamd/dkim
rspamadm dkim_keygen -b 2048 \
  -s mail -d example.com \
  -k /var/lib/rspamd/dkim/example.com.mail.key \
  &gt; /root/example.com.mail.dkim.txt
chown -R _rspamd:_rspamd /var/lib/rspamd/dkim
chmod 600 /var/lib/rspamd/dkim/example.com.mail.key</code></pre>
<p>File TXT chứa public key cần đặt tại <code>mail._domainkey.example.com</code>. Cấu hình module <code>dkim_signing</code> của Rspamd dùng selector <code>mail</code>, domain <code>example.com</code> và private key trên. Tên user dịch vụ có thể khác theo package; kiểm tra bằng <code>systemctl cat rspamd</code>.</p>
<p>Khởi động lại và kiểm tra:</p>
<pre><code>postfix check
doveconf -n
rspamadm configtest
systemctl restart postfix dovecot rspamd
systemctl --no-pager --full status postfix dovecot rspamd</code></pre>

<h2>10. Thêm DMARC</h2>
<p>Bắt đầu ở chế độ quan sát:</p>
<pre><code>_dmarc.example.com. TXT
"v=DMARC1; p=none; rua=mailto:dmarc@example.com; adkim=s; aspf=s"</code></pre>
<p>Sau khi mọi nguồn gửi hợp pháp đều pass và align SPF/DKIM, nâng dần lên <code>quarantine</code> rồi <code>reject</code>. Không bật <code>p=reject</code> ngay nếu domain còn gửi qua CRM, hóa đơn hoặc dịch vụ marketing chưa được kiểm kê.</p>

<h2>11. Kiểm tra chống open relay và luồng thư</h2>
<pre><code>postconf -n
ss -lntp | grep -E ':25|:587|:993'
swaks --server mail.example.com \
  --from outside@example.net \
  --to user@another-domain.test</code></pre>
<p>Phép thử relay không xác thực tới domain bên ngoài phải bị từ chối. Tiếp theo, kiểm tra submission có xác thực và TLS bằng tài khoản thật, gửi thư đến Gmail/Outlook rồi xem header <code>Authentication-Results</code> để xác nhận SPF, DKIM và DMARC.</p>
<p>Cấu hình mail client:</p>
<table><thead><tr><th>Dịch vụ</th><th>Máy chủ</th><th>Cổng</th><th>Bảo mật</th></tr></thead><tbody><tr><td>IMAP</td><td>mail.example.com</td><td>993</td><td>TLS</td></tr><tr><td>SMTP submission</td><td>mail.example.com</td><td>587</td><td>STARTTLS, bắt buộc xác thực</td></tr></tbody></table>

<h2>12. Fail2ban, log và queue</h2>
<p>Bật jail phù hợp cho <code>postfix</code>, <code>postfix-sasl</code> và <code>dovecot</code>, nhưng kiểm tra tên log/journal của phiên bản Ubuntu. Theo dõi:</p>
<pre><code>journalctl -u postfix -u dovecot -u rspamd -f
postqueue -p
rspamc stat
fail2ban-client status</code></pre>
<p>Cảnh báo khi queue tăng, disk gần đầy, chứng chỉ sắp hết hạn, Rspamd dừng hoặc tỷ lệ gửi lỗi tăng. Không tự động retry vô hạn thư bị từ chối vĩnh viễn.</p>

<h2>13. Backup và khôi phục</h2>
<p>Sao lưu ít nhất:</p>
<ul><li><code>/home/*/Maildir</code> hoặc kho mailbox thực tế;</li><li><code>/etc/postfix</code>, <code>/etc/dovecot</code>, <code>/etc/rspamd</code>;</li><li>DKIM private keys và thông tin DNS;</li><li>user/group cần thiết hoặc database virtual users;</li><li>tài liệu firewall, PTR và quy trình cấp lại TLS.</li></ul>
<p>Mã hóa backup, lưu ngoài VPS và kiểm thử restore mailbox sang máy cô lập. DKIM key cần được bảo vệ như secret; nếu lộ, xoay selector và khóa.</p>

<h2>14. Những giới hạn cần biết</h2>
<ul><li>IP mới có thể bị hạn chế dù cấu hình đúng; reputation cần thời gian và lưu lượng hợp lệ.</li><li>Không dùng hệ thống này để gửi marketing hàng loạt nếu chưa có unsubscribe, quản lý complaint và warm-up phù hợp.</li><li>Một VPS đơn là single point of failure; MX phụ không thay thế mailbox backup.</li><li>Webmail như Roundcube là một ứng dụng riêng cần vá lỗi và bảo vệ.</li><li>Catch-all làm tăng spam; nên tạo địa chỉ cụ thể và alias có chủ đích.</li></ul>

<h2>Checklist production</h2>
<ul><li>A/PTR khớp hai chiều; HELO dùng đúng FQDN.</li><li>MX, SPF, DKIM và DMARC phân giải đúng.</li><li>Port 25 không open relay; 587 bắt buộc TLS và xác thực.</li><li>IMAP chỉ dùng TLS; certificate renewal đã dry-run thành công.</li><li>Database/Redis không public; SSH giới hạn theo IP và dùng key.</li><li>Queue, disk, blacklist, log, certificate và backup có cảnh báo.</li><li>Backup ngoài máy đã restore thử.</li><li><code>postmaster@</code>, <code>abuse@</code> và địa chỉ nhận DMARC được theo dõi.</li></ul>

<h2>Kết luận</h2>
<p>Một email server hoạt động được có thể dựng trong vài giờ; một email server đáng tin cậy cần vận hành liên tục. Postfix, Dovecot và Rspamd tạo nền tảng tốt cho quy mô nhỏ khi DNS, TLS, authentication, giám sát và backup được làm đầy đủ. Nếu không kiểm soát được PTR, outbound port 25 hoặc người trực sự cố, nên dùng dịch vụ email quản lý hoặc SMTP relay.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://www.postfix.org/BASIC_CONFIGURATION_README.html" target="_blank" rel="noopener noreferrer">Postfix: Basic Configuration</a></li><li><a href="https://doc.dovecot.org/main/core/config/ssl.html" target="_blank" rel="noopener noreferrer">Dovecot: SSL/TLS Configuration</a></li><li><a href="https://docs.rspamd.com/tutorials/integration/" target="_blank" rel="noopener noreferrer">Rspamd: MTA Integration</a></li><li><a href="https://support.google.com/mail/answer/81126" target="_blank" rel="noopener noreferrer">Google: Email sender guidelines</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Install an Email Server on Ubuntu with Postfix and Dovecot',
            'slug' => 'install-email-server-ubuntu-postfix-dovecot',
            'image' => 'huong-dan-cai-dat-email-server.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Install Postfix and Dovecot Mail Server on Ubuntu',
            'meta_keywords' => 'install email server, Postfix, Dovecot, Rspamd, DKIM, SPF, DMARC, Ubuntu mail server',
            'meta_description' => 'Build an Ubuntu email server with Postfix, Dovecot, Rspamd, TLS, SPF, DKIM, DMARC, backups, and open-relay testing.',
            'tags' => ['Email Server', 'Postfix', 'Dovecot', 'Rspamd', 'DKIM', 'DMARC', 'Ubuntu Server'],
            'body' => <<<'HTML'
<p><strong>Self-hosting email requires more than installing SMTP and IMAP.</strong> Reliable delivery depends on IP reputation, reverse DNS, TLS, SPF, DKIM, DMARC, spam filtering, open-relay prevention, queue monitoring, and recoverable backups. This guide builds a single-domain Ubuntu mail server with Postfix, Dovecot, and Rspamd.</p>
<blockquote>If email is business-critical and no one can operate it continuously, use a managed provider. A correct configuration still requires patching, blacklist monitoring, abuse handling, and recovery work.</blockquote>

<h2>Architecture</h2>
<table><thead><tr><th>Component</th><th>Role</th></tr></thead><tbody><tr><td>Postfix</td><td>Inbound/outbound SMTP and authenticated submission</td></tr><tr><td>Dovecot</td><td>Maildir storage, IMAP, and SMTP authentication</td></tr><tr><td>Rspamd + Redis</td><td>Spam scoring, milter integration, and DKIM signing</td></tr><tr><td>Let's Encrypt</td><td>SMTP and IMAP TLS certificate</td></tr><tr><td>Fail2ban/UFW</td><td>Brute-force mitigation and network exposure control</td></tr></tbody></table>
<p>Examples use <code>example.com</code>, <code>mail.example.com</code>, and <code>203.0.113.10</code>. Replace every placeholder.</p>

<h2>1. Verify VPS requirements</h2>
<ul><li>The provider allows outbound TCP 25 and does not use a shared sending IP.</li><li>You can set the IP's PTR to <code>mail.example.com</code>.</li><li>The IP is not on major blocklists or an obviously poor-reputation range.</li><li>The server has a static IP and roughly 2 GB RAM for a small stack.</li><li>You control A, MX, and TXT records.</li></ul>
<p>If outbound 25 is blocked, use a reputable SMTP relay. Do not bypass provider policy.</p>

<h2>2. Create foundational DNS</h2>
<table><thead><tr><th>Type</th><th>Name</th><th>Example value</th></tr></thead><tbody><tr><td>A</td><td>mail</td><td>203.0.113.10</td></tr><tr><td>MX</td><td>@</td><td>10 mail.example.com.</td></tr><tr><td>TXT</td><td>@</td><td>v=spf1 mx -all</td></tr></tbody></table>
<p>Ask the VPS provider to set <code>203.0.113.10</code> PTR to <code>mail.example.com</code>. Forward-confirmed reverse DNS means the hostname resolves back to the same address.</p>
<p>Until IPv6 and its PTR are correctly configured, avoid an AAAA record for the mail host and use IPv4 in Postfix.</p>

<h2>3. Prepare Ubuntu and the firewall</h2>
<pre><code>hostnamectl set-hostname mail.example.com
apt update &amp;&amp; apt full-upgrade -y
apt install -y postfix dovecot-imapd dovecot-lmtpd \
  rspamd redis-server certbot fail2ban swaks ufw</code></pre>
<p>Select <strong>Internet Site</strong> in the Postfix prompt and use <code>example.com</code> as the system mail name.</p>
<pre><code>ufw default deny incoming
ufw default allow outgoing
ufw allow from ADMIN_IP to any port 22 proto tcp
ufw allow 25/tcp
ufw allow 80/tcp
ufw allow 587/tcp
ufw allow 993/tcp
ufw enable</code></pre>
<p>Port 25 handles server SMTP, 587 authenticated submission, 993 IMAPS, and 80 certificate validation. Do not expose databases, Redis, or internal administration ports.</p>

<h2>4. Issue a TLS certificate</h2>
<pre><code>certbot certonly --standalone \
  -d mail.example.com \
  --agree-tos -m admin@example.com --no-eff-email</code></pre>
<p>Create a deploy hook that reloads Postfix and Dovecot after renewal, then run <code>certbot renew --dry-run</code>. The certificate lives under <code>/etc/letsencrypt/live/mail.example.com/</code>.</p>

<h2>5. Configure Postfix</h2>
<pre><code>postconf -e 'myhostname = mail.example.com'
postconf -e 'mydomain = example.com'
postconf -e 'myorigin = $mydomain'
postconf -e 'mydestination = $myhostname, localhost.$mydomain, localhost, $mydomain'
postconf -e 'inet_interfaces = all'
postconf -e 'inet_protocols = ipv4'
postconf -e 'mynetworks = 127.0.0.0/8'
postconf -e 'home_mailbox = Maildir/'
postconf -e 'smtpd_tls_cert_file = /etc/letsencrypt/live/mail.example.com/fullchain.pem'
postconf -e 'smtpd_tls_key_file = /etc/letsencrypt/live/mail.example.com/privkey.pem'
postconf -e 'smtpd_tls_security_level = may'
postconf -e 'smtp_tls_security_level = may'
postconf -e 'smtpd_sasl_type = dovecot'
postconf -e 'smtpd_sasl_path = private/auth'
postconf -e 'smtpd_sasl_auth_enable = yes'
postconf -e 'smtpd_recipient_restrictions = permit_mynetworks, permit_sasl_authenticated, reject_unauth_destination'</code></pre>
<p>Trust only localhost in <code>mynetworks</code>. Broad networks can turn the host into an open relay.</p>
<p>Enable submission in <code>/etc/postfix/master.cf</code>:</p>
<pre><code>submission inet n       -       y       -       -       smtpd
  -o syslog_name=postfix/submission
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_relay_restrictions=permit_sasl_authenticated,reject
  -o smtpd_recipient_restrictions=permit_sasl_authenticated,reject</code></pre>

<h2>6. Configure Dovecot</h2>
<p>Set <code>mail_location = maildir:~/Maildir</code> in <code>10-mail.conf</code>. In <code>10-auth.conf</code>, use:</p>
<pre><code>disable_plaintext_auth = yes
auth_mechanisms = plain login</code></pre>
<p>Add this listener inside <code>service auth</code> in <code>10-master.conf</code>:</p>
<pre><code>unix_listener /var/spool/postfix/private/auth {
  mode = 0660
  user = postfix
  group = postfix
}</code></pre>
<p>For Dovecot 2.3 on Ubuntu, configure <code>10-ssl.conf</code>:</p>
<pre><code>ssl = required
ssl_cert = &lt;/etc/letsencrypt/live/mail.example.com/fullchain.pem
ssl_key = &lt;/etc/letsencrypt/live/mail.example.com/privkey.pem</code></pre>
<p>Dovecot syntax changes across major releases. Check <code>dovecot --version</code> and validate with <code>doveconf -n</code>.</p>

<h2>7. Create the first mailbox</h2>
<pre><code>adduser alice
maildirmake.dovecot /home/alice/Maildir
chown -R alice:alice /home/alice/Maildir</code></pre>
<p>The login is <code>alice</code> and the address is <code>alice@example.com</code>. System users are simple for one domain, but virtual users backed by SQL/LDAP or a packaged mail suite are better for many domains or hundreds of mailboxes.</p>

<h2>8. Integrate Rspamd</h2>
<p>Verify the Rspamd milter on <code>127.0.0.1:11332</code>, then:</p>
<pre><code>postconf -e 'milter_protocol = 6'
postconf -e 'milter_default_action = accept'
postconf -e 'smtpd_milters = inet:127.0.0.1:11332'
postconf -e 'non_smtpd_milters = inet:127.0.0.1:11332'</code></pre>
<p>The accept fallback keeps SMTP available during a temporary filter failure, but monitoring must catch prolonged outages.</p>

<h2>9. Generate DKIM</h2>
<pre><code>mkdir -p /var/lib/rspamd/dkim
rspamadm dkim_keygen -b 2048 \
  -s mail -d example.com \
  -k /var/lib/rspamd/dkim/example.com.mail.key \
  &gt; /root/example.com.mail.dkim.txt
chown -R _rspamd:_rspamd /var/lib/rspamd/dkim
chmod 600 /var/lib/rspamd/dkim/example.com.mail.key</code></pre>
<p>Publish the TXT public key at <code>mail._domainkey.example.com</code>. Configure Rspamd's <code>dkim_signing</code> module with selector <code>mail</code>, the domain, and private-key path. Confirm the package service user before assigning ownership.</p>
<pre><code>postfix check
doveconf -n
rspamadm configtest
systemctl restart postfix dovecot rspamd</code></pre>

<h2>10. Add DMARC</h2>
<pre><code>_dmarc.example.com. TXT
"v=DMARC1; p=none; rua=mailto:dmarc@example.com; adkim=s; aspf=s"</code></pre>
<p>Begin with monitoring. After every legitimate sender passes and aligns SPF/DKIM, move gradually to <code>quarantine</code> and then <code>reject</code>. Do not enforce rejection before inventorying CRM, invoicing, and marketing senders.</p>

<h2>11. Test relay and delivery</h2>
<pre><code>postconf -n
ss -lntp | grep -E ':25|:587|:993'
swaks --server mail.example.com \
  --from outside@example.net \
  --to user@another-domain.test</code></pre>
<p>Unauthenticated relay to an external domain must be rejected. Test authenticated submission with TLS, deliver to Gmail or Outlook, and inspect <code>Authentication-Results</code> for SPF, DKIM, and DMARC.</p>
<table><thead><tr><th>Service</th><th>Server</th><th>Port</th><th>Security</th></tr></thead><tbody><tr><td>IMAP</td><td>mail.example.com</td><td>993</td><td>TLS</td></tr><tr><td>SMTP submission</td><td>mail.example.com</td><td>587</td><td>STARTTLS and authentication</td></tr></tbody></table>

<h2>12. Fail2ban, logs, and queue</h2>
<p>Enable suitable Postfix SASL and Dovecot jails, confirming Ubuntu's journal or log paths. Monitor:</p>
<pre><code>journalctl -u postfix -u dovecot -u rspamd -f
postqueue -p
rspamc stat
fail2ban-client status</code></pre>
<p>Alert on queue growth, disk pressure, certificate expiry, stopped filters, and delivery-error spikes. Do not retry permanently rejected mail forever.</p>

<h2>13. Backup and recovery</h2>
<p>Back up mailbox storage, Postfix/Dovecot/Rspamd configuration, DKIM private keys, DNS records, user data, and firewall/PTR documentation. Encrypt backups, store them outside the VPS, and test mailbox restoration in isolation. Treat DKIM keys as secrets and rotate the selector if one is exposed.</p>

<h2>14. Operational limits</h2>
<ul><li>A new IP can be throttled even with correct configuration; reputation takes time.</li><li>Do not use this server for bulk marketing without unsubscribe, complaints, and warm-up controls.</li><li>One VPS is a single point of failure; a secondary MX is not a mailbox backup.</li><li>Webmail such as Roundcube is another application to secure and patch.</li><li>Avoid catch-all mailboxes because they attract spam.</li></ul>

<h2>Production checklist</h2>
<ul><li>Forward and reverse DNS match; SMTP HELO uses the correct FQDN.</li><li>MX, SPF, DKIM, and DMARC resolve correctly.</li><li>Port 25 is not an open relay; port 587 requires TLS and authentication.</li><li>IMAP requires TLS and certificate renewal passes dry-run.</li><li>Database and Redis are private; SSH uses keys and source restrictions.</li><li>Queue, disk, blocklists, logs, certificates, and backups are monitored.</li><li>An off-host backup has passed a restore test.</li><li><code>postmaster@</code>, <code>abuse@</code>, and DMARC reports are monitored.</li></ul>

<h2>Conclusion</h2>
<p>A functional mail server can be built in hours; a trustworthy one requires continuous operations. Postfix, Dovecot, and Rspamd are a strong small-scale foundation when DNS, TLS, authentication, monitoring, and backups are complete. If PTR, outbound port 25, or on-call operations are unavailable, choose managed email or an SMTP relay.</p>

<h2>References</h2>
<ul><li><a href="https://www.postfix.org/BASIC_CONFIGURATION_README.html" target="_blank" rel="noopener noreferrer">Postfix: Basic Configuration</a></li><li><a href="https://doc.dovecot.org/main/core/config/ssl.html" target="_blank" rel="noopener noreferrer">Dovecot: SSL/TLS Configuration</a></li><li><a href="https://docs.rspamd.com/tutorials/integration/" target="_blank" rel="noopener noreferrer">Rspamd: MTA Integration</a></li><li><a href="https://support.google.com/mail/answer/81126" target="_blank" rel="noopener noreferrer">Google: Email sender guidelines</a></li></ul>
HTML,
        ],
    ],
];

