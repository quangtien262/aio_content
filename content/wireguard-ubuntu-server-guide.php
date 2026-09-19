<?php

return [
    'wireguard-ubuntu-server-guide.html' => [
        'vi' => [
            'title' => 'Hướng dẫn cài WireGuard VPN trên Ubuntu Server an toàn từ A-Z',
            'slug' => 'huong-dan-cai-wireguard-vpn-ubuntu-server',
            'image' => 'wireguard-ubuntu-server-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Cài WireGuard VPN trên Ubuntu Server an toàn',
            'meta_keywords' => 'cài WireGuard Ubuntu, WireGuard VPN server, cấu hình wg0, UFW WireGuard, VPN truy cập từ xa, split tunnel',
            'meta_description' => 'Hướng dẫn cài WireGuard trên Ubuntu Server, tạo khóa, cấu hình peer, UFW, NAT, split/full tunnel, điện thoại và xử lý lỗi thường gặp.',
            'tags' => ['WireGuard', 'Ubuntu Server', 'VPN', 'UFW', 'Remote Access', 'Network Security', 'Linux', 'Sysadmin'],
            'body' => <<<'HTML'
<p><strong>WireGuard là một VPN hiện đại, gọn và đa nền tảng, phù hợp để nhân viên truy cập an toàn vào server hoặc mạng nội bộ từ xa.</strong> Bài này xây dựng mô hình peer-to-site trên Ubuntu Server: mỗi thiết bị có một cặp khóa riêng, server định tuyến lưu lượng VPN và quyền được giới hạn bằng firewall.</p>
<blockquote>VPN không thay thế phân quyền ứng dụng. Người dùng kết nối được vào mạng vẫn chỉ nên truy cập đúng dịch vụ cần thiết.</blockquote>

<h2>Mô hình và thông số ví dụ</h2>
<ul><li>Ubuntu Server có IP public hoặc được router NAT cổng UDP tới.</li><li>Interface Internet của server: <code>eth0</code> (hãy thay bằng tên thực tế).</li><li>Mạng WireGuard: <code>10.66.66.0/24</code>.</li><li>Server VPN: <code>10.66.66.1</code>; client đầu tiên: <code>10.66.66.2</code>.</li><li>Cổng UDP: <code>51820</code>.</li></ul>
<p>Kiểm tra interface bằng <code>ip route get 1.1.1.1</code>. Nếu server nằm sau router, chuyển tiếp UDP 51820 tới IP LAN của server và nên cố định IP đó bằng DHCP reservation. Với CGNAT, port forwarding thông thường sẽ không hoạt động; cần public IP, IPv6 phù hợp, VPS trung gian hoặc tunnel khác.</p>

<h2>1. Cập nhật hệ thống và cài WireGuard</h2>
<pre><code>sudo apt update
sudo apt upgrade
sudo apt install wireguard qrencode
sudo mkdir -p /etc/wireguard
sudo chmod 700 /etc/wireguard</code></pre>
<p><code>qrencode</code> chỉ cần nếu muốn thêm cấu hình vào điện thoại bằng QR. Trước khi sửa firewall từ xa, giữ một phiên SSH thứ hai đang mở hoặc có console của nhà cung cấp để tránh tự khóa quyền quản trị.</p>

<h2>2. Tạo khóa riêng cho server</h2>
<pre><code>sudo sh -c 'umask 077; wg genkey > /etc/wireguard/server.key'
sudo sh -c 'wg pubkey < /etc/wireguard/server.key > /etc/wireguard/server.pub'
sudo cat /etc/wireguard/server.pub</code></pre>
<p>Private key phải chỉ root đọc được và không được gửi qua chat, email hoặc commit vào Git. Public key có thể chia sẻ cho peer. Mỗi client cần một cặp khóa riêng để có thể thu hồi từng thiết bị mà không ảnh hưởng toàn hệ thống.</p>

<h2>3. Bật IP forwarding</h2>
<p>Nếu client chỉ truy cập dịch vụ chạy ngay trên server, forwarding có thể không cần. Nếu server đóng vai trò gateway tới Internet hoặc mạng LAN, tạo cấu hình:</p>
<pre><code>sudo tee /etc/sysctl.d/70-wireguard-routing.conf &gt; /dev/null &lt;&lt;'EOF'
net.ipv4.ip_forward = 1
EOF
sudo sysctl -p /etc/sysctl.d/70-wireguard-routing.conf</code></pre>
<p>Xác nhận bằng <code>sysctl net.ipv4.ip_forward</code>; kết quả phải là <code>1</code>. Chỉ bật IPv6 forwarding khi đã thiết kế địa chỉ và firewall IPv6 đầy đủ, không sao chép máy móc cấu hình IPv4.</p>

<h2>4. Tạo cấu hình server</h2>
<p>Mở <code>/etc/wireguard/wg0.conf</code> và điền:</p>
<pre><code>[Interface]
Address = 10.66.66.1/24
ListenPort = 51820
PrivateKey = SERVER_PRIVATE_KEY

# Client laptop
[Peer]
PublicKey = CLIENT_PUBLIC_KEY
AllowedIPs = 10.66.66.2/32</code></pre>
<p>Thay placeholder bằng nội dung khóa thực tế, sau đó chạy <code>sudo chmod 600 /etc/wireguard/wg0.conf</code>. Trong WireGuard, <code>AllowedIPs</code> vừa chọn peer cho lưu lượng đi ra, vừa giới hạn địa chỉ nguồn peer được phép dùng khi nhận. Không gán cùng một IP <code>/32</code> cho hai client.</p>
<p>Có thể tránh đặt private key trực tiếp trong file chính bằng cách dùng <code>PostUp = wg set %i private-key /etc/wireguard/server.key</code>, như tài liệu Ubuntu minh họa. Dù chọn cách nào, hãy bảo vệ file khóa và cấu hình bằng quyền hệ thống tệp.</p>

<h2>5. Cấu hình UFW và NAT</h2>
<p>Cho phép SSH trước, rồi mở cổng WireGuard:</p>
<pre><code>sudo ufw allow OpenSSH
sudo ufw allow 51820/udp
sudo ufw route allow in on wg0 out on eth0 from 10.66.66.0/24
sudo ufw route allow in on eth0 out on wg0 to 10.66.66.0/24</code></pre>
<p>Để client dùng server làm cổng ra Internet, thêm khối NAT ở đầu <code>/etc/ufw/before.rules</code>, trước các bảng <code>*filter</code> hiện có:</p>
<pre><code>*nat
:POSTROUTING ACCEPT [0:0]
-A POSTROUTING -s 10.66.66.0/24 -o eth0 -j MASQUERADE
COMMIT</code></pre>
<p>Trong <code>/etc/default/ufw</code>, đặt <code>DEFAULT_FORWARD_POLICY="ACCEPT"</code>, sau đó:</p>
<pre><code>sudo ufw enable
sudo ufw reload
sudo ufw status verbose</code></pre>
<p>Thay <code>eth0</code> bằng interface Internet thật. Nếu chỉ cần truy cập mạng nội bộ và router LAN có route quay về <code>10.66.66.0/24</code>, định tuyến thuần không NAT giúp log giữ được IP client; cấu hình route và firewall theo kiến trúc thực tế.</p>

<h2>6. Tạo client đầu tiên</h2>
<p>Tạo khóa trên chính client là lựa chọn tốt nhất vì private key không rời thiết bị. Trên Linux:</p>
<pre><code>umask 077
wg genkey &gt; client.key
wg pubkey &lt; client.key &gt; client.pub</code></pre>
<p>Thêm public key của client vào block <code>[Peer]</code> trên server. File cấu hình client:</p>
<pre><code>[Interface]
PrivateKey = CLIENT_PRIVATE_KEY
Address = 10.66.66.2/32
DNS = 1.1.1.1

[Peer]
PublicKey = SERVER_PUBLIC_KEY
Endpoint = vpn.example.com:51820
AllowedIPs = 10.66.66.0/24
PersistentKeepalive = 25</code></pre>
<p><code>PersistentKeepalive = 25</code> hữu ích khi client nằm sau NAT và cần giữ ánh xạ, nhưng không bắt buộc cho mọi peer. Dùng hostname động nếu IP public của server thay đổi.</p>

<h2>7. Chọn split tunnel hoặc full tunnel</h2>
<p>Cấu hình trên là <strong>split tunnel</strong>: chỉ mạng <code>10.66.66.0/24</code> đi qua VPN. Nếu cần truy cập LAN, thêm subnet LAN, ví dụ <code>192.168.10.0/24</code>, vào <code>AllowedIPs</code> của client và mở đúng rule forwarding.</p>
<p>Với <strong>full tunnel</strong>, đặt <code>AllowedIPs = 0.0.0.0/0</code> (và <code>::/0</code> chỉ khi đã cấu hình IPv6). Toàn bộ Internet của client sẽ đi qua server, vì vậy phải có forwarding, NAT, DNS và đủ băng thông. Full tunnel bảo vệ lưu lượng trên Wi-Fi công cộng nhưng tăng tải, độ trễ và trách nhiệm vận hành.</p>

<h2>8. Khởi động và kiểm tra</h2>
<pre><code>sudo wg-quick up wg0
sudo wg show
ip address show wg0
sudo systemctl enable wg-quick@wg0</code></pre>
<p>Kết nối client rồi kiểm tra <code>ping 10.66.66.1</code>, truy cập dịch vụ nội bộ và quan sát <code>latest handshake</code>, <code>transfer</code> trong <code>sudo wg show</code>. Nếu dùng full tunnel, kiểm tra IP public và DNS của client để chắc chắn lưu lượng đi đúng đường.</p>
<p>Để nạp thêm peer mà ít gián đoạn, có thể dùng <code>sudo systemctl reload wg-quick@wg0</code>. Một số thay đổi về địa chỉ, route hoặc hook vẫn cần restart đầy đủ.</p>

<h2>9. Thêm điện thoại bằng QR an toàn</h2>
<pre><code>qrencode -t ansiutf8 &lt; phone.conf</code></pre>
<p>Trong ứng dụng WireGuard trên điện thoại, chọn tạo tunnel từ QR rồi quét trực tiếp trong phiên quản trị riêng tư. QR chứa private key nên phải được xem như mật khẩu: không chụp màn hình, không lưu vào ticket và xóa file tạm an toàn sau khi cấp thiết bị.</p>

<h2>10. Thu hồi một thiết bị</h2>
<p>Xóa block <code>[Peer]</code> tương ứng khỏi server và reload dịch vụ. Không tái sử dụng khóa cho thiết bị thay thế. Nếu server private key bị lộ, tạo cặp khóa server mới và cập nhật public key trên tất cả client; chỉ đổi một peer khi private key của peer đó bị lộ.</p>

<h2>Xử lý lỗi thường gặp</h2>
<ul><li><strong>Không có handshake:</strong> kiểm tra UDP 51820, port forwarding, public IP/CGNAT, Endpoint và cặp public key.</li><li><strong>Có handshake nhưng không ping:</strong> kiểm tra <code>AllowedIPs</code>, IP trùng, UFW và route bằng <code>ip route</code>.</li><li><strong>Truy cập server được nhưng không vào LAN/Internet:</strong> kiểm tra IP forwarding, rule FORWARD, NAT hoặc route quay về.</li><li><strong>Truy cập IP được nhưng tên miền lỗi:</strong> kiểm tra DNS trong client và resolver có thể đi qua tunnel.</li><li><strong>Một số website treo:</strong> thử giảm MTU, chẳng hạn <code>MTU = 1380</code>, sau khi xác nhận có vấn đề path MTU.</li><li><strong>Kết nối di động không ổn định:</strong> dùng <code>PersistentKeepalive = 25</code> ở peer nằm sau NAT.</li></ul>
<pre><code>sudo wg show
sudo journalctl -u wg-quick@wg0
ip route
sudo ufw status verbose
sudo ss -lunp | grep 51820</code></pre>

<h2>Checklist bảo mật và vận hành</h2>
<ol><li>Mỗi thiết bị có khóa và địa chỉ VPN riêng.</li><li>Private key và file cấu hình có quyền hạn chế.</li><li>Firewall chỉ cho phép subnet VPN tới đúng dịch vụ cần dùng.</li><li>Không mở SSH public nếu có thể quản trị qua VPN.</li><li>Ghi lại chủ sở hữu peer, ngày cấp và ngày thu hồi.</li><li>Sao lưu cấu hình server dưới dạng mã hóa, tách khỏi máy chủ.</li><li>Cập nhật Ubuntu định kỳ và theo dõi handshake bất thường.</li><li>Kiểm thử thu hồi peer và khôi phục cấu hình.</li></ol>

<h2>Kết luận</h2>
<p>Một WireGuard VPN tốt không chỉ là tunnel có handshake. Cấu hình cần khóa riêng cho từng thiết bị, <code>AllowedIPs</code> chính xác, firewall giới hạn quyền, routing/NAT có chủ đích và quy trình thu hồi rõ ràng. Hãy bắt đầu bằng split tunnel nhỏ, xác minh từng luồng rồi mới mở rộng sang LAN hoặc full tunnel.</p>

<h2>Nguồn tham khảo</h2>
<ul><li><a href="https://ubuntu.com/server/docs/how-to/wireguard-vpn/" target="_blank" rel="noopener noreferrer">Ubuntu Server: WireGuard VPN</a></li><li><a href="https://ubuntu.com/server/docs/explanation/intro-to/wireguard-vpn/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Introduction to WireGuard</a></li><li><a href="https://ubuntu.com/server/docs/how-to/wireguard-vpn/common-tasks/" target="_blank" rel="noopener noreferrer">Ubuntu Server: WireGuard common tasks</a></li><li><a href="https://ubuntu.com/server/docs/how-to/wireguard-vpn/troubleshooting/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Troubleshooting WireGuard</a></li><li><a href="https://ubuntu.com/server/docs/how-to/security/firewalls/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Firewalls and IP masquerading</a></li><li><a href="https://www.wireguard.com/quickstart/" target="_blank" rel="noopener noreferrer">WireGuard: Quick Start</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'How to Install a Secure WireGuard VPN on Ubuntu Server',
            'slug' => 'install-secure-wireguard-vpn-ubuntu-server',
            'image' => 'wireguard-ubuntu-server-guide.jpg',
            'category_vi' => 'Hướng dẫn', 'category_en' => 'Guides',
            'meta_title' => 'Install WireGuard VPN Securely on Ubuntu Server',
            'meta_keywords' => 'install WireGuard Ubuntu, WireGuard VPN server, wg0 configuration, UFW WireGuard, remote access VPN, split tunnel',
            'meta_description' => 'Install WireGuard on Ubuntu Server, create keys and peers, configure UFW and NAT, choose split or full tunnel, add phones, and troubleshoot.',
            'tags' => ['WireGuard', 'Ubuntu Server', 'VPN', 'UFW', 'Remote Access', 'Network Security', 'Linux', 'Sysadmin'],
            'body' => <<<'HTML'
<p><strong>WireGuard is a compact, modern, cross-platform VPN suited to secure remote access to servers and private networks.</strong> This guide builds a peer-to-site deployment on Ubuntu Server: every device receives a unique key pair, the server routes VPN traffic, and the firewall limits access.</p>
<blockquote>A VPN does not replace application authorization. A user connected to the network should still reach only the services they need.</blockquote>

<h2>Example architecture and values</h2>
<ul><li>The Ubuntu Server has a public IP or receives a forwarded UDP port.</li><li>Its Internet interface is <code>eth0</code>; replace this with the actual name.</li><li>WireGuard network: <code>10.66.66.0/24</code>.</li><li>VPN server: <code>10.66.66.1</code>; first client: <code>10.66.66.2</code>.</li><li>UDP port: <code>51820</code>.</li></ul>
<p>Find the outgoing interface with <code>ip route get 1.1.1.1</code>. If the server is behind a router, forward UDP 51820 to its LAN address and reserve that address in DHCP. Ordinary port forwarding does not work behind CGNAT; obtain a public IP, suitable IPv6, an intermediary VPS, or another tunnel design.</p>

<h2>1. Update Ubuntu and install WireGuard</h2>
<pre><code>sudo apt update
sudo apt upgrade
sudo apt install wireguard qrencode
sudo mkdir -p /etc/wireguard
sudo chmod 700 /etc/wireguard</code></pre>
<p><code>qrencode</code> is optional and used for phone enrollment. Before changing a remote firewall, keep a second SSH session open or ensure provider-console access so a mistake does not lock out administration.</p>

<h2>2. Generate server keys</h2>
<pre><code>sudo sh -c 'umask 077; wg genkey > /etc/wireguard/server.key'
sudo sh -c 'wg pubkey < /etc/wireguard/server.key > /etc/wireguard/server.pub'
sudo cat /etc/wireguard/server.pub</code></pre>
<p>Only root should read the private key. Never send it through chat or email or commit it to Git. The public key can be shared with peers. Give every client its own pair so one device can be revoked independently.</p>

<h2>3. Enable IP forwarding</h2>
<p>Forwarding may not be needed when clients access only services on the VPN server itself. When the server is a gateway to the Internet or LAN, create:</p>
<pre><code>sudo tee /etc/sysctl.d/70-wireguard-routing.conf &gt; /dev/null &lt;&lt;'EOF'
net.ipv4.ip_forward = 1
EOF
sudo sysctl -p /etc/sysctl.d/70-wireguard-routing.conf</code></pre>
<p>Run <code>sysctl net.ipv4.ip_forward</code> and expect <code>1</code>. Enable IPv6 forwarding only after designing IPv6 addressing and firewall rules rather than copying IPv4 assumptions.</p>

<h2>4. Create the server configuration</h2>
<p>Create <code>/etc/wireguard/wg0.conf</code>:</p>
<pre><code>[Interface]
Address = 10.66.66.1/24
ListenPort = 51820
PrivateKey = SERVER_PRIVATE_KEY

# Client laptop
[Peer]
PublicKey = CLIENT_PUBLIC_KEY
AllowedIPs = 10.66.66.2/32</code></pre>
<p>Replace placeholders with real key values, then run <code>sudo chmod 600 /etc/wireguard/wg0.conf</code>. In WireGuard, <code>AllowedIPs</code> selects the peer for outbound traffic and acts as a source-address ACL on receipt. Never assign the same <code>/32</code> to two clients.</p>
<p>To avoid embedding the server private key in the primary config, Ubuntu documentation demonstrates <code>PostUp = wg set %i private-key /etc/wireguard/server.key</code>. Either way, protect both key and configuration files with filesystem permissions.</p>

<h2>5. Configure UFW and NAT</h2>
<p>Allow SSH first and then open WireGuard:</p>
<pre><code>sudo ufw allow OpenSSH
sudo ufw allow 51820/udp
sudo ufw route allow in on wg0 out on eth0 from 10.66.66.0/24
sudo ufw route allow in on eth0 out on wg0 to 10.66.66.0/24</code></pre>
<p>For clients to use the server as an Internet gateway, add this NAT block near the top of <code>/etc/ufw/before.rules</code>, before existing <code>*filter</code> tables:</p>
<pre><code>*nat
:POSTROUTING ACCEPT [0:0]
-A POSTROUTING -s 10.66.66.0/24 -o eth0 -j MASQUERADE
COMMIT</code></pre>
<p>Set <code>DEFAULT_FORWARD_POLICY="ACCEPT"</code> in <code>/etc/default/ufw</code>, then:</p>
<pre><code>sudo ufw enable
sudo ufw reload
sudo ufw status verbose</code></pre>
<p>Replace <code>eth0</code> with the actual Internet interface. For LAN-only access where the LAN router has a return route to <code>10.66.66.0/24</code>, routing without NAT preserves client addresses in logs; adapt routes and firewall rules to the real topology.</p>

<h2>6. Create the first client</h2>
<p>Generating keys on the client is preferable because its private key never leaves the device. On Linux:</p>
<pre><code>umask 077
wg genkey &gt; client.key
wg pubkey &lt; client.key &gt; client.pub</code></pre>
<p>Add the client public key to its server <code>[Peer]</code> block. The client configuration is:</p>
<pre><code>[Interface]
PrivateKey = CLIENT_PRIVATE_KEY
Address = 10.66.66.2/32
DNS = 1.1.1.1

[Peer]
PublicKey = SERVER_PUBLIC_KEY
Endpoint = vpn.example.com:51820
AllowedIPs = 10.66.66.0/24
PersistentKeepalive = 25</code></pre>
<p><code>PersistentKeepalive = 25</code> helps a client behind NAT maintain its mapping but is not necessary on every peer. Use dynamic DNS when the server public address changes.</p>

<h2>7. Choose split tunnel or full tunnel</h2>
<p>The configuration above is a <strong>split tunnel</strong>: only <code>10.66.66.0/24</code> uses the VPN. To reach a LAN, add its subnet, such as <code>192.168.10.0/24</code>, to client <code>AllowedIPs</code> and permit the exact forwarding flow.</p>
<p>For a <strong>full tunnel</strong>, use <code>AllowedIPs = 0.0.0.0/0</code>, adding <code>::/0</code> only after IPv6 is configured. All client Internet traffic then crosses the server, requiring forwarding, NAT, DNS, and adequate capacity. It protects traffic on public Wi-Fi but adds load, latency, and operational responsibility.</p>

<h2>8. Start and test the tunnel</h2>
<pre><code>sudo wg-quick up wg0
sudo wg show
ip address show wg0
sudo systemctl enable wg-quick@wg0</code></pre>
<p>Connect the client, run <code>ping 10.66.66.1</code>, test private services, and inspect <code>latest handshake</code> and <code>transfer</code> in <code>sudo wg show</code>. With a full tunnel, verify the client's public IP and DNS path.</p>
<p>Use <code>sudo systemctl reload wg-quick@wg0</code> to add peers with less disruption. Address, route, and hook changes may still require a full restart.</p>

<h2>9. Add a phone securely with a QR code</h2>
<pre><code>qrencode -t ansiutf8 &lt; phone.conf</code></pre>
<p>In the mobile WireGuard application, create a tunnel from QR and scan it in a private administrative session. The QR contains the private key, so treat it as a password: do not screenshot it, attach it to a ticket, or retain temporary files unnecessarily.</p>

<h2>10. Revoke a device</h2>
<p>Remove the device's <code>[Peer]</code> block from the server and reload the service. Do not reuse keys for replacement hardware. If a server private key is exposed, create a new server pair and update every client; rotate only one peer when that peer's private key is compromised.</p>

<h2>Troubleshooting</h2>
<ul><li><strong>No handshake:</strong> inspect UDP 51820, port forwarding, public IP or CGNAT, Endpoint, and public keys.</li><li><strong>Handshake but no ping:</strong> check <code>AllowedIPs</code>, duplicate addresses, UFW, and <code>ip route</code>.</li><li><strong>Server reachable but no LAN or Internet:</strong> check IP forwarding, FORWARD rules, NAT, and return routes.</li><li><strong>IP works but names fail:</strong> inspect client DNS and resolver reachability through the tunnel.</li><li><strong>Some sites hang:</strong> test a lower MTU such as <code>MTU = 1380</code> after confirming a path-MTU issue.</li><li><strong>Mobile connection drops:</strong> apply <code>PersistentKeepalive = 25</code> to the peer behind NAT.</li></ul>
<pre><code>sudo wg show
sudo journalctl -u wg-quick@wg0
ip route
sudo ufw status verbose
sudo ss -lunp | grep 51820</code></pre>

<h2>Security and operations checklist</h2>
<ol><li>Every device has a unique key and VPN address.</li><li>Private keys and configurations have restricted permissions.</li><li>Firewall rules expose only necessary services to the VPN subnet.</li><li>Public SSH is removed where administration can use the VPN.</li><li>Peer owner, issue date, and revocation date are recorded.</li><li>An encrypted server configuration backup exists off-host.</li><li>Ubuntu is patched and unexpected handshakes are monitored.</li><li>Peer revocation and configuration recovery are tested.</li></ol>

<h2>Conclusion</h2>
<p>A sound WireGuard VPN is more than a successful handshake. It needs per-device keys, precise <code>AllowedIPs</code>, restrictive firewall rules, intentional routing or NAT, and a clear revocation process. Start with a small split tunnel, verify each flow, and expand to LAN or full-tunnel access only when required.</p>

<h2>References</h2>
<ul><li><a href="https://ubuntu.com/server/docs/how-to/wireguard-vpn/" target="_blank" rel="noopener noreferrer">Ubuntu Server: WireGuard VPN</a></li><li><a href="https://ubuntu.com/server/docs/explanation/intro-to/wireguard-vpn/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Introduction to WireGuard</a></li><li><a href="https://ubuntu.com/server/docs/how-to/wireguard-vpn/common-tasks/" target="_blank" rel="noopener noreferrer">Ubuntu Server: WireGuard common tasks</a></li><li><a href="https://ubuntu.com/server/docs/how-to/wireguard-vpn/troubleshooting/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Troubleshooting WireGuard</a></li><li><a href="https://ubuntu.com/server/docs/how-to/security/firewalls/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Firewalls and IP masquerading</a></li><li><a href="https://www.wireguard.com/quickstart/" target="_blank" rel="noopener noreferrer">WireGuard: Quick Start</a></li></ul>
HTML,
        ],
    ],
];
