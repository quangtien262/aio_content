<?php

return [
    'linux-network-troubleshooting.html' => [
        'vi' => [
            'title' => 'Chẩn đoán lỗi mạng Linux từ A-Z với ip, ss, dig, curl và tcpdump',
            'slug' => 'chan-doan-loi-mang-linux-ip-ss-dig-curl-tcpdump',
            'image' => 'linux-network-troubleshooting.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Chẩn đoán lỗi mạng Linux từ link đến ứng dụng',
            'meta_keywords' => 'lỗi mạng Linux, ip command, ss, dig, curl, tcpdump, DNS Linux, routing Linux, firewall Linux',
            'meta_description' => 'Quy trình chẩn đoán mạng Linux theo từng lớp: interface, IP, route, ARP, DNS, socket, firewall, HTTP/TLS, MTU, packet capture và container namespace.',
            'tags' => ['Linux', 'Networking', 'Troubleshooting', 'DNS', 'TCP/IP', 'tcpdump', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>“Không vào được server” có thể là hàng chục lỗi khác nhau:</strong> interface down, sai IP, thiếu default route, DNS trả nhầm địa chỉ, service chỉ listen localhost, firewall drop packet, TLS sai SNI hoặc ứng dụng treo phía sau reverse proxy. Chạy ngẫu nhiên nhiều lệnh thường tạo thêm nhiễu.</p>
<p>Cách hiệu quả là chẩn đoán từ lớp thấp lên cao, kiểm tra từng giả thuyết và so sánh từ cả phía client lẫn server. Bài viết dùng các công cụ phổ biến trên Linux: <code>ip</code>, <code>ss</code>, <code>ping</code>, <code>dig</code>, <code>curl</code>, <code>openssl</code>, <code>traceroute</code> và <code>tcpdump</code>.</p>

<h2>Trước khi sửa: xác định phạm vi sự cố</h2>
<p>Ghi lại bốn thông tin: ai bị ảnh hưởng, dịch vụ nào, bắt đầu lúc nào và thay đổi gì vừa diễn ra. Sau đó kiểm tra:</p>
<ul>
<li>Một client hay mọi client?</li>
<li>Một hostname/port hay toàn bộ Internet?</li>
<li>IPv4, IPv6 hay cả hai?</li>
<li>Host vật lý, VM, container hay Kubernetes pod?</li>
<li>Chỉ từ mạng ngoài hay ngay cả localhost?</li>
</ul>
<p>Không restart mọi thứ ngay. Restart có thể xóa trạng thái, log và connection hữu ích. Chụp lại output, timestamp và cấu hình trước khi thay đổi.</p>

<h2>1. Kiểm tra interface và physical link</h2>
<pre><code>ip -br link
ip -br address
ip -s link show dev eth0</code></pre>
<p><code>UP</code> cho biết interface được bật về mặt quản trị; <code>LOWER_UP</code> cho biết kernel thấy carrier/link. Nếu thiếu <code>LOWER_UP</code>, kiểm tra cáp, switch port, virtual NIC, hypervisor hoặc Wi-Fi association.</p>
<p><code>ip -s link</code> hiển thị packet, error, drop và overrun. Counter error/drop tăng nhanh có thể chỉ ra duplex, driver, MTU, buffer hoặc congestion. So sánh hai lần cách nhau vài giây thay vì chỉ nhìn số tuyệt đối.</p>
<pre><code>sudo ethtool eth0
sudo ethtool -S eth0</code></pre>
<p>Với server vật lý, <code>ethtool</code> giúp xem link detected, speed, duplex và driver statistics. Trên VM/container, một số trường có thể không có ý nghĩa như phần cứng thật.</p>

<h2>2. Kiểm tra địa chỉ IP</h2>
<pre><code>ip address show
ip -4 address show dev eth0
ip -6 address show dev eth0</code></pre>
<p>Xác nhận IP, prefix length, interface và trạng thái <code>tentative</code>/<code>deprecated</code>. Sai prefix có thể khiến host tưởng một địa chỉ remote nằm cùng subnet và ARP sai thay vì gửi qua gateway.</p>
<p>Nếu dùng DHCP:</p>
<pre><code>networkctl status eth0
journalctl -u systemd-networkd --since '-15 min'
nmcli device show eth0</code></pre>
<p>Chọn lệnh phù hợp network manager của hệ thống. Ubuntu Server thường quản lý cấu hình bền vững qua Netplan, nhưng backend có thể là <code>systemd-networkd</code> hoặc NetworkManager.</p>

<h2>3. Kiểm tra route mà kernel thực sự chọn</h2>
<pre><code>ip route show
ip -6 route show
ip route get 1.1.1.1
ip route get 203.0.113.50 from 192.0.2.10</code></pre>
<p><code>ip route get</code> hữu ích hơn chỉ nhìn bảng route vì nó cho biết interface, gateway và source IP kernel sẽ dùng cho một destination cụ thể. Trên host nhiều NIC, policy routing có thể làm traffic đi khác dự kiến:</p>
<pre><code>ip rule show
ip route show table all</code></pre>
<p>Kiểm tra route trùng nhau, metric, default gateway và source-based rule. Asymmetric routing có thể khiến request vào một interface nhưng response ra interface khác và bị firewall/upstream drop.</p>

<h2>4. Kiểm tra neighbor/ARP và gateway</h2>
<pre><code>ip neigh show
ping -c 3 192.0.2.1
arping -I eth0 -c 3 192.0.2.1</code></pre>
<p>Trạng thái neighbor <code>FAILED</code> hoặc <code>INCOMPLETE</code> nghĩa host không phân giải được MAC của địa chỉ cùng link. Nguyên nhân có thể là sai VLAN, prefix, gateway, security group ở hypervisor hoặc thiết bị không cùng broadcast domain.</p>
<p>Ping bị chặn không chứng minh host down. ICMP là một tín hiệu; hãy tiếp tục thử đúng TCP port. Nhưng nếu ngay cả gateway cùng subnet không có ARP reply, lỗi nằm thấp hơn DNS/TLS.</p>

<h2>5. Tách lỗi mạng và lỗi DNS</h2>
<p>Thử kết nối bằng IP và hostname. Nếu IP hoạt động còn hostname không, tập trung vào resolver/DNS:</p>
<pre><code>resolvectl status
resolvectl query api.example.com
dig api.example.com A
dig api.example.com AAAA
dig @1.1.1.1 api.example.com A
getent ahosts api.example.com</code></pre>
<p><code>dig</code> hỏi DNS trực tiếp; <code>getent</code> đi qua Name Service Switch giống nhiều ứng dụng, nên còn phản ánh <code>/etc/hosts</code>, mDNS hoặc resolver khác. So sánh hai kết quả khi ứng dụng và <code>dig</code> không giống nhau.</p>
<p>Kiểm tra A và AAAA. Một record IPv6 sai có thể làm client ưu tiên đường không hoạt động. Dùng <code>curl -4</code> và <code>curl -6</code> để cô lập:</p>
<pre><code>curl -4 -v https://api.example.com/health
curl -6 -v https://api.example.com/health</code></pre>
<p>Không sửa trực tiếp <code>/etc/resolv.conf</code> trên hệ thống mà file này do systemd-resolved/NetworkManager sinh ra; thay đổi sẽ bị ghi đè. Sửa Netplan hoặc network manager đúng chỗ.</p>

<h2>6. Dịch vụ có listen đúng địa chỉ không?</h2>
<pre><code>sudo ss -lntup
sudo ss -lntp 'sport = :443'
sudo ss -lnup 'sport = :53'</code></pre>
<p>Phân biệt:</p>
<ul>
<li><code>127.0.0.1:8080</code>: chỉ truy cập từ local namespace.</li>
<li><code>0.0.0.0:8080</code>: listen mọi địa chỉ IPv4.</li>
<li><code>[::]:8080</code>: listen IPv6; hành vi dual-stack phụ thuộc cấu hình.</li>
</ul>
<p>Nếu không có socket listen, kiểm tra service và log:</p>
<pre><code>systemctl status nginx --no-pager
journalctl -u nginx --since '-15 min' --no-pager</code></pre>
<p>Service “active” chưa chắc listen port mong muốn; process có thể chạy nhưng bind sai IP, cấu hình upstream lỗi hoặc child worker chết.</p>

<h2>7. Hiểu connection refused, timeout và reset</h2>
<ul>
<li><strong>Connection refused:</strong> packet đến host và nhận TCP RST; thường không có listener hoặc firewall reject.</li>
<li><strong>Timeout:</strong> không nhận phản hồi; có thể route, firewall drop, security group, NAT hoặc host down.</li>
<li><strong>Connection reset:</strong> kết nối được tạo rồi một bên đóng cưỡng bức; xem application/proxy/TLS log.</li>
<li><strong>No route to host:</strong> kernel không có route hoặc nhận ICMP unreachable; thông báo đôi khi cũng đến từ firewall reject.</li>
</ul>
<pre><code>nc -vz -w 3 api.example.com 443
curl -v --connect-timeout 3 https://api.example.com/health</code></pre>
<p><code>curl -v</code> cho biết DNS, IP được chọn, thời gian connect, TLS và HTTP. Không dùng <code>-k</code> như cách “sửa” TLS; nó chỉ bỏ verify và che giấu lỗi chứng chỉ.</p>

<h2>8. Kiểm tra firewall ở mọi lớp</h2>
<pre><code>sudo ufw status verbose
sudo nft list ruleset
sudo iptables-save</code></pre>
<p>Hệ thống có thể dùng nftables trong khi công cụ compatibility vẫn hiển thị iptables. Ngoài host còn có cloud security group, network ACL, load balancer, firewall vật lý và policy Kubernetes.</p>
<p>Không flush firewall từ xa trên production. Thêm rule có scope nhỏ, time-box nếu có thể, và giữ session SSH dự phòng. Bật log có chọn lọc vì log mọi packet có thể gây I/O lớn:</p>
<pre><code>sudo ufw logging medium
journalctl -k --since '-10 min' | grep -i 'UFW'</code></pre>

<h2>9. Kiểm tra HTTP và TLS đúng hostname</h2>
<pre><code>curl -v https://api.example.com/health
curl --resolve api.example.com:443:203.0.113.20 \
  https://api.example.com/health

openssl s_client \
  -connect 203.0.113.20:443 \
  -servername api.example.com \
  -showcerts &lt;/dev/null</code></pre>
<p><code>--resolve</code> ép IP nhưng giữ hostname/SNI/Host header, rất hữu ích để test origin trước khi đổi DNS. Gọi trực tiếp <code>https://IP</code> có thể chọn sai virtual host và certificate.</p>
<p>Kiểm tra certificate chain, SAN, ngày hết hạn, SNI và redirect loop. Nếu reverse proxy trả 502/504, mạng client→proxy có thể hoàn toàn tốt; tiếp tục kiểm tra proxy→upstream bằng IP/port từ chính namespace proxy.</p>

<h2>10. Đo đường đi và packet loss đúng cách</h2>
<pre><code>tracepath api.example.com
traceroute -T -p 443 api.example.com
mtr -rwzc 50 api.example.com</code></pre>
<p>Router giữa đường có thể không trả ICMP nhưng vẫn forward traffic, nên dấu <code>*</code> không tự động là lỗi. Packet loss ở hop trung gian chỉ đáng lo nếu tiếp tục xuất hiện ở hop sau/destination. Ưu tiên TCP traceroute tới port thật khi ICMP bị lọc.</p>
<p>So sánh từ nhiều source. Một đường lỗi từ ISP A không chứng minh server lỗi toàn cục.</p>

<h2>11. MTU và Path MTU Discovery</h2>
<p>Dấu hiệu MTU mismatch: ping nhỏ được nhưng HTTPS/upload lớn treo, đặc biệt qua VPN, tunnel hoặc cloud overlay.</p>
<pre><code>ip link show dev eth0
tracepath api.example.com
ping -M do -s 1472 -c 3 api.example.com</code></pre>
<p>1472 byte + 28 byte IPv4 ICMP header tương ứng MTU 1500; với IPv6/header/tunnel con số khác. Giảm payload để tìm ngưỡng. Đừng đổi MTU vội nếu chưa xác nhận cả interface, tunnel và thiết bị trung gian. Firewall chặn ICMP “fragmentation needed” có thể làm PMTUD thất bại.</p>

<h2>12. tcpdump: xem packet có thực sự đi qua đâu</h2>
<pre><code>sudo tcpdump -ni any host 203.0.113.20 and port 443
sudo tcpdump -ni eth0 'tcp port 443 and (tcp[tcpflags] &amp; tcp-syn != 0)'
sudo tcpdump -ni any port 53
</code></pre>
<p>Đọc handshake:</p>
<ul>
<li>Chỉ thấy SYN đi, không có SYN-ACK: lỗi trên đường, firewall drop hoặc server không trả.</li>
<li>Thấy SYN rồi RST: port bị từ chối/no listener.</li>
<li>Không thấy SYN rời host: DNS, route, local firewall hoặc application chưa gửi.</li>
<li>Server thấy SYN và gửi SYN-ACK nhưng client không thấy: đường về/asymmetric routing/upstream firewall.</li>
</ul>
<p>Lưu capture ngắn để phân tích:</p>
<pre><code>sudo timeout 30 tcpdump -ni any -s 0 \
  -w /tmp/network-issue.pcap \
  'host 203.0.113.20 and port 443'</code></pre>
<p>PCAP có thể chứa credential, cookie và dữ liệu người dùng. Giới hạn filter/duration, bảo vệ file và xóa an toàn sau điều tra.</p>

<h2>13. Container và network namespace</h2>
<p>Kiểm tra từ host không đủ nếu ứng dụng chạy trong container/pod. Mỗi namespace có interface, route, DNS và firewall riêng:</p>
<pre><code>docker exec -it app sh
ip address
ip route
cat /etc/resolv.conf
getent hosts database
nc -vz database 5432</code></pre>
<p>Với Docker, <code>127.0.0.1</code> trong container là chính container, không phải host. Dùng service name trên network Compose. Kiểm tra network membership, published port và policy/NAT:</p>
<pre><code>docker network inspect app_default
docker port app
sudo nsenter -t PID -n ss -lntp</code></pre>
<p>Trong Kubernetes, kiểm tra pod DNS, Service selector/endpoints, NetworkPolicy và test từ debug pod cùng namespace.</p>

<h2>14. Connection tracking và cạn port</h2>
<p>Traffic cao có thể gặp conntrack full, ephemeral port exhaustion hoặc quá nhiều socket:</p>
<pre><code>ss -s
ss -ant state time-wait | wc -l
cat /proc/sys/net/ipv4/ip_local_port_range
sudo conntrack -S
dmesg | grep -i conntrack</code></pre>
<p>Không “sửa” bằng cách giảm TIME_WAIT hoặc tăng mọi sysctl theo bài blog. Tìm nguồn connection churn, thiếu keep-alive, NAT bottleneck hoặc connection pool sai trước; sau đó mới capacity-plan kernel limits.</p>

<h2>15. Thay đổi cấu hình mạng an toàn từ xa</h2>
<p>Sai Netplan có thể cắt chính SSH session. Trên Ubuntu, ưu tiên:</p>
<pre><code>sudo netplan generate
sudo netplan try</code></pre>
<p><code>netplan try</code> yêu cầu xác nhận và có thể rollback khi mất kết nối. Tuy vậy vẫn cần console/out-of-band access cho server quan trọng. Backup file cấu hình, kiểm tra YAML và thay đổi từng phần nhỏ.</p>

<h2>Playbook 10 phút</h2>
<ol>
<li><code>ip -br link</code> và <code>ip -br addr</code>: link/IP đúng chưa?</li>
<li><code>ip route get DEST</code>: route/source/interface nào được chọn?</li>
<li><code>ip neigh</code> và ping gateway: cùng link có hoạt động?</li>
<li><code>dig</code> + <code>getent</code>: DNS và resolver có đồng ý?</li>
<li><code>ss -lntup</code>: service có listen đúng IP/port?</li>
<li><code>nc</code>/<code>curl -v</code>: refused, timeout, TLS hay HTTP?</li>
<li>Kiểm tra host/cloud/container firewall.</li>
<li><code>curl --resolve</code>: tách DNS khỏi HTTPS/SNI.</li>
<li><code>tcpdump</code> ở client và server: packet dừng ở đâu?</li>
<li>Chỉ sửa sau khi có giả thuyết và cách rollback.</li>
</ol>

<h2>Kết luận</h2>
<p>Chẩn đoán mạng Linux hiệu quả là quá trình loại trừ có thứ tự: link, IP, route, neighbor, DNS, socket, firewall, transport, TLS rồi ứng dụng. Mỗi công cụ trả lời một câu hỏi khác nhau; không có lệnh duy nhất chứng minh “mạng tốt”. Khi kiểm tra từ đúng namespace, quan sát cả hai chiều và lưu bằng chứng trước khi sửa, những sự cố tưởng ngẫu nhiên thường trở thành một điểm đứt rõ ràng.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://ubuntu.com/server/docs/explanation/networking/networking-key-concepts/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Networking key concepts</a></li><li><a href="https://ubuntu.com/server/docs/explanation/networking/configuring-networks/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Configuring networks</a></li><li><a href="https://ubuntu.com/server/docs/how-to/networking/install-dns/" target="_blank" rel="noopener noreferrer">Ubuntu Server: DNS testing</a></li><li><a href="https://ubuntu.com/server/docs/how-to/security/firewalls/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Firewalls</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Linux Network Troubleshooting with ip, ss, dig, curl, and tcpdump',
            'slug' => 'linux-network-troubleshooting-ip-ss-dig-curl-tcpdump',
            'image' => 'linux-network-troubleshooting.jpg',
            'category_vi' => 'Hệ điều hành', 'category_en' => 'Operating Systems',
            'meta_title' => 'Linux Network Troubleshooting from Link to Application',
            'meta_keywords' => 'Linux network troubleshooting, ip command, ss, dig, curl, tcpdump, Linux DNS, Linux routing, Linux firewall',
            'meta_description' => 'A layer-by-layer Linux networking playbook covering interfaces, IP, routes, neighbors, DNS, sockets, firewalls, HTTP/TLS, MTU, packet captures, and container namespaces.',
            'tags' => ['Linux', 'Networking', 'Troubleshooting', 'DNS', 'TCP/IP', 'tcpdump', 'System Administration'],
            'body' => <<<'HTML'
<p><strong>“Cannot reach the server” can describe dozens of failures:</strong> a down interface, wrong address, missing default route, incorrect DNS, a service bound only to localhost, dropped packets, wrong TLS SNI, or a stalled upstream behind a proxy. Running random commands usually adds noise.</p>
<p>A better method starts at lower layers, tests one hypothesis at a time, and compares observations from both client and server. This guide uses common Linux tools: <code>ip</code>, <code>ss</code>, <code>ping</code>, <code>dig</code>, <code>curl</code>, <code>openssl</code>, <code>traceroute</code>, and <code>tcpdump</code>.</p>

<h2>Before changing anything: define the blast radius</h2>
<p>Record who is affected, which service, when it began, and what changed. Determine whether it affects one or all clients, one port or all Internet access, IPv4 or IPv6, a host/VM/container, and external traffic or localhost too.</p>
<p>Do not restart everything immediately. Restarts erase useful state, logs, and connections. Capture command output, timestamps, and configuration before modification.</p>

<h2>1. Check interfaces and physical link</h2>
<pre><code>ip -br link
ip -br address
ip -s link show dev eth0</code></pre>
<p><code>UP</code> means administratively enabled; <code>LOWER_UP</code> means the kernel sees carrier. Without it, inspect cable, switch port, virtual NIC, hypervisor, or wireless association.</p>
<p><code>ip -s link</code> exposes packet errors and drops. Counters rising quickly can indicate driver, duplex, MTU, buffering, or congestion problems. Compare two samples rather than one lifetime total.</p>
<pre><code>sudo ethtool eth0
sudo ethtool -S eth0</code></pre>
<p>On physical servers, <code>ethtool</code> provides carrier, speed, duplex, and driver statistics. Some values are less meaningful on virtual interfaces.</p>

<h2>2. Verify IP addressing</h2>
<pre><code>ip address show
ip -4 address show dev eth0
ip -6 address show dev eth0</code></pre>
<p>Confirm the address, prefix, interface, and tentative/deprecated status. A wrong prefix may make the host ARP for a remote destination instead of using its gateway.</p>
<pre><code>networkctl status eth0
journalctl -u systemd-networkd --since '-15 min'
nmcli device show eth0</code></pre>
<p>Use the commands matching the active network manager. Ubuntu Server commonly persists configuration through Netplan, backed by systemd-networkd or NetworkManager.</p>

<h2>3. Inspect the route the kernel will use</h2>
<pre><code>ip route show
ip -6 route show
ip route get 1.1.1.1
ip route get 203.0.113.50 from 192.0.2.10</code></pre>
<p><code>ip route get</code> shows the selected interface, gateway, and source address for a specific destination. Multi-homed systems may also use policy routing:</p>
<pre><code>ip rule show
ip route show table all</code></pre>
<p>Look for overlapping routes, metrics, default gateways, and source-based rules. Asymmetric routing can bring a request through one interface and send the response through another, where a firewall or upstream drops it.</p>

<h2>4. Inspect neighbors and the gateway</h2>
<pre><code>ip neigh show
ping -c 3 192.0.2.1
arping -I eth0 -c 3 192.0.2.1</code></pre>
<p><code>FAILED</code> or <code>INCOMPLETE</code> neighbor state means the host cannot resolve the MAC address on the local link. Causes include a wrong VLAN, prefix, gateway, hypervisor security group, or different broadcast domain.</p>
<p>Blocked ping does not prove a host is down. Continue with the actual TCP port. But if the local gateway gives no ARP response, the problem is below DNS and TLS.</p>

<h2>5. Separate networking from DNS</h2>
<pre><code>resolvectl status
resolvectl query api.example.com
dig api.example.com A
dig api.example.com AAAA
dig @1.1.1.1 api.example.com A
getent ahosts api.example.com</code></pre>
<p><code>dig</code> queries DNS directly, while <code>getent</code> follows the Name Service Switch used by many applications, including hosts files and other resolvers. Compare them when application behavior disagrees with <code>dig</code>.</p>
<p>Test both A and AAAA. Broken IPv6 can be isolated with:</p>
<pre><code>curl -4 -v https://api.example.com/health
curl -6 -v https://api.example.com/health</code></pre>
<p>Do not directly edit generated <code>/etc/resolv.conf</code>; fix Netplan or the active network manager.</p>

<h2>6. Is the service listening on the right address?</h2>
<pre><code>sudo ss -lntup
sudo ss -lntp 'sport = :443'
sudo ss -lnup 'sport = :53'</code></pre>
<ul>
<li><code>127.0.0.1:8080</code> is reachable only from that network namespace.</li>
<li><code>0.0.0.0:8080</code> listens on all IPv4 addresses.</li>
<li><code>[::]:8080</code> listens on IPv6; dual-stack behavior depends on configuration.</li>
</ul>
<pre><code>systemctl status nginx --no-pager
journalctl -u nginx --since '-15 min' --no-pager</code></pre>
<p>An active service may still bind the wrong address, have a failed upstream, or lose workers.</p>

<h2>7. Understand refused, timeout, and reset</h2>
<ul>
<li><strong>Connection refused:</strong> the host returned TCP RST, commonly due to no listener or firewall reject.</li>
<li><strong>Timeout:</strong> no response, possibly due to routing, packet drop, security group, NAT, or a down host.</li>
<li><strong>Connection reset:</strong> a peer forcibly closed an established connection; inspect application, proxy, and TLS logs.</li>
<li><strong>No route to host:</strong> no route or an unreachable response, sometimes generated by a firewall reject.</li>
</ul>
<pre><code>nc -vz -w 3 api.example.com 443
curl -v --connect-timeout 3 https://api.example.com/health</code></pre>
<p><code>curl -v</code> separates DNS, selected IP, connect, TLS, and HTTP phases. Do not use <code>-k</code> as a TLS fix; it only hides certificate validation failures.</p>

<h2>8. Check every firewall layer</h2>
<pre><code>sudo ufw status verbose
sudo nft list ruleset
sudo iptables-save</code></pre>
<p>Beyond the host, inspect cloud security groups, network ACLs, load balancers, physical firewalls, and Kubernetes policies. Never flush a production firewall over remote SSH. Make narrow changes and retain an out-of-band path.</p>
<pre><code>sudo ufw logging medium
journalctl -k --since '-10 min' | grep -i 'UFW'</code></pre>
<p>Log selectively; logging every packet can create serious I/O.</p>

<h2>9. Test HTTP and TLS with the correct hostname</h2>
<pre><code>curl -v https://api.example.com/health
curl --resolve api.example.com:443:203.0.113.20 \
  https://api.example.com/health

openssl s_client \
  -connect 203.0.113.20:443 \
  -servername api.example.com \
  -showcerts &lt;/dev/null</code></pre>
<p><code>--resolve</code> forces an IP while preserving SNI and Host, making it useful for testing an origin before DNS cutover. Calling <code>https://IP</code> can select the wrong certificate and virtual host.</p>
<p>Inspect chain, SAN, expiry, SNI, and redirects. A 502/504 means client-to-proxy networking may be healthy; test proxy-to-upstream from the proxy's own namespace.</p>

<h2>10. Measure path and packet loss carefully</h2>
<pre><code>tracepath api.example.com
traceroute -T -p 443 api.example.com
mtr -rwzc 50 api.example.com</code></pre>
<p>Intermediate routers may suppress ICMP while forwarding normally. An asterisk alone is not failure. Intermediate packet loss matters when it persists through later hops and the destination. Test the real TCP port and compare from multiple source networks.</p>

<h2>11. MTU and Path MTU Discovery</h2>
<p>An MTU mismatch can allow small pings while large HTTPS requests or uploads stall, especially across VPNs and overlays.</p>
<pre><code>ip link show dev eth0
tracepath api.example.com
ping -M do -s 1472 -c 3 api.example.com</code></pre>
<p>For IPv4, 1472 plus 28 bytes of headers corresponds to MTU 1500. IPv6 and tunnels differ. Reduce the payload to find the threshold, and verify the complete path before changing MTU. Blocking ICMP “fragmentation needed” can break PMTUD.</p>

<h2>12. Use tcpdump to locate the packet</h2>
<pre><code>sudo tcpdump -ni any host 203.0.113.20 and port 443
sudo tcpdump -ni eth0 'tcp port 443 and (tcp[tcpflags] &amp; tcp-syn != 0)'
sudo tcpdump -ni any port 53</code></pre>
<ul>
<li>SYN leaves with no SYN-ACK: path, drop, or nonresponsive server.</li>
<li>SYN followed by RST: rejected port or no listener.</li>
<li>No outgoing SYN: resolver, route, local firewall, or application issue.</li>
<li>Server sends SYN-ACK but client never sees it: return path, asymmetry, or upstream firewall.</li>
</ul>
<pre><code>sudo timeout 30 tcpdump -ni any -s 0 \
  -w /tmp/network-issue.pcap \
  'host 203.0.113.20 and port 443'</code></pre>
<p>Packet captures can contain credentials, cookies, and personal data. Limit filters and duration, protect the file, and delete it securely afterward.</p>

<h2>13. Containers and network namespaces</h2>
<p>Host tests are insufficient when the process runs in another namespace:</p>
<pre><code>docker exec -it app sh
ip address
ip route
cat /etc/resolv.conf
getent hosts database
nc -vz database 5432</code></pre>
<p>Inside Docker, <code>127.0.0.1</code> refers to the container itself. Use Compose service names. Inspect membership, published ports, and namespaces:</p>
<pre><code>docker network inspect app_default
docker port app
sudo nsenter -t PID -n ss -lntp</code></pre>
<p>For Kubernetes, inspect pod DNS, Service selectors/endpoints, NetworkPolicy, and test from a debug pod in the same namespace.</p>

<h2>14. Connection tracking and ephemeral ports</h2>
<pre><code>ss -s
ss -ant state time-wait | wc -l
cat /proc/sys/net/ipv4/ip_local_port_range
sudo conntrack -S
dmesg | grep -i conntrack</code></pre>
<p>High traffic can exhaust conntrack entries, ports, or sockets. Do not copy random TIME_WAIT/sysctl tweaks. First find connection churn, missing keep-alive, NAT bottlenecks, or incorrect pools; then capacity-plan limits.</p>

<h2>15. Change remote network configuration safely</h2>
<pre><code>sudo netplan generate
sudo netplan try</code></pre>
<p><code>netplan try</code> requests confirmation and can roll back on lost connectivity. Critical servers still need console or out-of-band access. Back up configuration, validate YAML, and make one small change at a time.</p>

<h2>Ten-minute playbook</h2>
<ol>
<li><code>ip -br link</code> and <code>ip -br addr</code>: link and address.</li>
<li><code>ip route get DEST</code>: selected route, source, and interface.</li>
<li><code>ip neigh</code> and gateway reachability.</li>
<li><code>dig</code> plus <code>getent</code>: DNS versus system resolver.</li>
<li><code>ss -lntup</code>: listener address and port.</li>
<li><code>nc</code>/<code>curl -v</code>: refused, timeout, TLS, or HTTP.</li>
<li>Host, cloud, and container firewalls.</li>
<li><code>curl --resolve</code>: isolate DNS from HTTPS/SNI.</li>
<li><code>tcpdump</code> on client and server: locate the stopping point.</li>
<li>Change only after forming a hypothesis and rollback plan.</li>
</ol>

<h2>Conclusion</h2>
<p>Effective Linux network troubleshooting is ordered elimination: link, IP, route, neighbor, DNS, socket, firewall, transport, TLS, then application. Each tool answers a different question; no single command proves that “the network is fine.” Testing from the correct namespace, observing both directions, and preserving evidence before changes turns apparently random incidents into a specific broken point.</p>

<h2>References</h2>
<ul><li><a href="https://ubuntu.com/server/docs/explanation/networking/networking-key-concepts/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Networking key concepts</a></li><li><a href="https://ubuntu.com/server/docs/explanation/networking/configuring-networks/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Configuring networks</a></li><li><a href="https://ubuntu.com/server/docs/how-to/networking/install-dns/" target="_blank" rel="noopener noreferrer">Ubuntu Server: DNS testing</a></li><li><a href="https://ubuntu.com/server/docs/how-to/security/firewalls/" target="_blank" rel="noopener noreferrer">Ubuntu Server: Firewalls</a></li></ul>
HTML,
        ],
    ],
];
