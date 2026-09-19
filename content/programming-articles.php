<?php

return [
    'docker-compose-moi-truong-lap-trinh.html' => [
        'vi' => [
            'title' => 'Docker Compose: Xây dựng môi trường lập trình đồng nhất cho cả nhóm',
            'slug' => 'docker-compose-moi-truong-lap-trinh',
            'image' => 'docker-compose-moi-truong-lap-trinh.jpg',
            'meta_title' => 'Docker Compose: Môi trường lập trình đồng nhất',
            'meta_keywords' => 'Docker Compose, môi trường lập trình, container, Docker cho developer, compose.yaml, local development',
            'meta_description' => 'Hướng dẫn dùng Docker Compose để chuẩn hóa môi trường lập trình, quản lý dịch vụ, dữ liệu, biến môi trường và quy trình làm việc nhóm hiệu quả.',
            'tags' => ['Docker Compose', 'Docker', 'Container', 'Môi trường lập trình', 'DevOps', 'Backend Development'],
            'body' => <<<'HTML'
<p><strong>“Máy em chạy được” thường là dấu hiệu môi trường phát triển chưa được mô tả đủ rõ.</strong> Khi mỗi thành viên tự cài PHP, cơ sở dữ liệu, bộ nhớ đệm và công cụ phụ trợ theo một cách khác nhau, lỗi phiên bản và cấu hình sẽ xuất hiện sớm hay muộn. Docker Compose giúp biến các phụ thuộc đó thành một cấu hình có thể lưu cùng mã nguồn và khởi động lại nhất quán.</p>
<h2>Docker Compose giải quyết vấn đề gì?</h2>
<p>Một ứng dụng web hiếm khi chỉ có mã nguồn. Nó có thể cần máy chủ ứng dụng, MySQL hoặc PostgreSQL, Redis, dịch vụ email giả lập và tiến trình chạy hàng đợi. Compose mô tả các thành phần này dưới dạng <em>service</em>, cùng mạng, ổ đĩa và biến môi trường cần thiết.</p>
<p>Thay vì viết tài liệu cài đặt dài và hy vọng mọi người làm giống nhau, nhóm có thể dùng một lệnh để tạo cùng một hệ thống dịch vụ. Cách làm này đặc biệt hữu ích khi tiếp nhận thành viên mới, chuyển máy hoặc tái hiện lỗi.</p>
<h2>Một cấu hình tối thiểu dễ hiểu</h2>
<pre><code>services:
  app:
    build: .
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: app
      MYSQL_USER: app
      MYSQL_PASSWORD: local_password
      MYSQL_ROOT_PASSWORD: local_root_password
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 10

  redis:
    image: redis:7-alpine

volumes:
  db_data:</code></pre>
<p><code>depends_on</code> thể hiện thứ tự phụ thuộc, còn <code>healthcheck</code> giúp Compose biết cơ sở dữ liệu đã sẵn sàng nhận kết nối hay chưa. Điều này đáng tin cậy hơn việc chỉ chờ container được khởi động.</p>
<h2>Phân biệt các thành phần chính</h2>
<table><thead><tr><th>Thành phần</th><th>Vai trò</th><th>Lưu ý</th></tr></thead><tbody><tr><td>Image</td><td>Mẫu chỉ đọc dùng để tạo container</td><td>Nên ghim phiên bản phù hợp thay vì phụ thuộc mơ hồ vào <code>latest</code></td></tr><tr><td>Container</td><td>Tiến trình đang chạy từ image</td><td>Có thể xóa và tạo lại; không dùng filesystem bên trong để lưu dữ liệu lâu dài</td></tr><tr><td>Volume</td><td>Lưu dữ liệu bền vững như database</td><td>Chỉ xóa khi chắc chắn không cần dữ liệu local</td></tr><tr><td>Network</td><td>Cho các service liên lạc bằng tên</td><td>Ứng dụng kết nối tới host <code>db</code> hoặc <code>redis</code>, không dùng <code>localhost</code></td></tr></tbody></table>
<h2>Quản lý biến môi trường và bí mật</h2>
<p>Compose hỗ trợ khai báo biến trực tiếp, nội suy từ shell hoặc đọc tệp môi trường. Tuy nhiên, tệp chứa mật khẩu thật không nên được commit. Hãy cung cấp <code>.env.example</code> chỉ gồm tên biến và giá trị mẫu an toàn; dữ liệu nhạy cảm cho staging hoặc production nên đi qua cơ chế secrets của nền tảng triển khai.</p>
<blockquote>Container hóa môi trường local không đồng nghĩa cấu hình local có thể được bê nguyên lên production. Production vẫn cần chiến lược riêng cho secrets, backup, giám sát, cập nhật và khả năng phục hồi.</blockquote>
<h2>Quy trình làm việc hằng ngày</h2>
<pre><code>docker compose up -d --build
docker compose ps
docker compose logs -f app
docker compose exec app php artisan migrate
docker compose down</code></pre>
<p>Chỉ thêm <code>-v</code> vào <code>docker compose down</code> khi chủ đích muốn xóa cả volume. Với database local có dữ liệu kiểm thử quan trọng, thao tác này có thể làm mất công sức chuẩn bị dữ liệu.</p>
<h2>Tối ưu trải nghiệm lập trình</h2>
<ul><li>Tạo <code>Dockerfile</code> riêng cho development nếu cần debugger hoặc công cụ chất lượng mã.</li><li>Dùng bind mount cho mã nguồn nhưng tránh mount đè thư mục dependency nếu hệ điều hành host gây chậm I/O.</li><li>Thêm healthcheck cho database và message broker mà ứng dụng phải chờ.</li><li>Ghim phiên bản image có chủ đích và lên lịch cập nhật.</li><li>Đặt các lệnh thường dùng trong script hoặc Makefile để người mới không phải nhớ chi tiết dài.</li></ul>
<h2>Các lỗi thường gặp</h2>
<h3>Ứng dụng không kết nối được database</h3><p>Bên trong network của Compose, <code>DB_HOST</code> phải là tên service như <code>db</code>, không phải <code>127.0.0.1</code>. Đồng thời kiểm tra cổng nội bộ, healthcheck và log của cả hai service.</p>
<h3>Sửa mã nhưng container không cập nhật</h3><p>Kiểm tra đường dẫn volume, working directory và cache của framework. Thay đổi dependency hoặc extension hệ thống cần build lại image; thay đổi mã nguồn thông thường chỉ cần bind mount đúng.</p>
<h3>Dữ liệu biến mất sau khi khởi động lại</h3><p>Database cần dùng named volume và mount đúng thư mục dữ liệu của image. Việc xóa volume hoặc đổi tên project Compose có thể khiến hệ thống tạo một vùng dữ liệu mới.</p>
<h2>Checklist trước khi đưa vào nhóm</h2>
<ol><li>Thành viên mới có thể khởi động dự án từ README mà không cần hướng dẫn miệng.</li><li>Các phiên bản image được ghi rõ và có kế hoạch cập nhật.</li><li>Không có mật khẩu thật, token hay dữ liệu khách hàng trong repository.</li><li>Database và dịch vụ phụ thuộc có healthcheck hợp lý.</li><li>Lệnh migrate, seed, test và xem log đều được tài liệu hóa.</li><li>Nhóm đã thử xóa container và dựng lại để xác nhận dữ liệu cần thiết vẫn được giữ.</li></ol>
<h2>Kết luận</h2>
<p>Docker Compose không loại bỏ mọi khác biệt giữa local và production, nhưng nó tạo ra một hợp đồng môi trường rõ ràng cho đội phát triển. Hãy bắt đầu từ vài service thật sự cần thiết, chuẩn hóa lệnh hằng ngày và kiểm tra khả năng dựng lại từ đầu.</p>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://docs.docker.com/compose/" target="_blank" rel="noopener noreferrer">Docker Compose documentation</a></li><li><a href="https://docs.docker.com/compose/how-tos/environment-variables/" target="_blank" rel="noopener noreferrer">Environment variables in Compose</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Docker Compose: Building a Consistent Development Environment',
            'slug' => 'docker-compose-consistent-development-environment',
            'image' => 'docker-compose-moi-truong-lap-trinh.jpg',
            'meta_title' => 'Docker Compose: A Consistent Dev Environment',
            'meta_keywords' => 'Docker Compose, development environment, containers, Docker for developers, compose.yaml, local development',
            'meta_description' => 'Learn how Docker Compose standardizes local development, services, persistent data, environment variables, and daily team workflows.',
            'tags' => ['Docker Compose', 'Docker', 'Containers', 'Development Environment', 'DevOps', 'Backend Development'],
            'body' => <<<'HTML'
<p><strong>“It works on my machine” usually means the development environment is not described clearly enough.</strong> When every team member installs PHP, a database, a cache, and supporting tools differently, version and configuration problems eventually follow. Docker Compose turns those dependencies into configuration that can live with the source code and be recreated consistently.</p>
<h2>What problem does Docker Compose solve?</h2>
<p>A web application is rarely just source code. It may require an application server, MySQL or PostgreSQL, Redis, a fake mail service, and a queue worker. Compose describes these components as services together with the networks, volumes, and environment variables they need.</p>
<p>Instead of maintaining a long setup guide and hoping everyone follows it identically, the team can create the same service topology with one command. This is especially useful when onboarding a developer, replacing a computer, or reproducing a bug.</p>
<h2>A small configuration that remains readable</h2>
<pre><code>services:
  app:
    build: .
    ports:
      - "8080:80"
    volumes:
      - ./:/var/www/html
    depends_on:
      db:
        condition: service_healthy

  db:
    image: mysql:8.4
    environment:
      MYSQL_DATABASE: app
      MYSQL_USER: app
      MYSQL_PASSWORD: local_password
      MYSQL_ROOT_PASSWORD: local_root_password
    volumes:
      - db_data:/var/lib/mysql
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "localhost"]
      interval: 5s
      timeout: 3s
      retries: 10

  redis:
    image: redis:7-alpine

volumes:
  db_data:</code></pre>
<p><code>depends_on</code> expresses startup dependencies, while <code>healthcheck</code> tells Compose when the database is ready to accept connections. That is more reliable than merely waiting for a container process to start.</p>
<h2>Understand the main components</h2>
<table><thead><tr><th>Component</th><th>Purpose</th><th>Practical note</th></tr></thead><tbody><tr><td>Image</td><td>A read-only template used to create containers</td><td>Pin an intentional version instead of depending vaguely on <code>latest</code></td></tr><tr><td>Container</td><td>A running process created from an image</td><td>It should be replaceable; do not use its internal filesystem for durable data</td></tr><tr><td>Volume</td><td>Persistent storage for data such as a database</td><td>Delete it only when local data is no longer needed</td></tr><tr><td>Network</td><td>Allows services to communicate by name</td><td>The app connects to <code>db</code> or <code>redis</code>, not <code>localhost</code></td></tr></tbody></table>
<h2>Environment variables and secrets</h2>
<p>Compose can declare variables directly, interpolate them from the shell, or read environment files. A file containing real passwords should not be committed. Provide a safe <code>.env.example</code> and deliver staging or production secrets through the deployment platform's secret-management mechanism.</p>
<blockquote>A containerized local environment is not a production architecture by itself. Production still needs deliberate secrets handling, backups, monitoring, upgrades, and recovery procedures.</blockquote>
<h2>The daily workflow</h2>
<pre><code>docker compose up -d --build
docker compose ps
docker compose logs -f app
docker compose exec app php artisan migrate
docker compose down</code></pre>
<p>Add <code>-v</code> to <code>docker compose down</code> only when you intentionally want to remove volumes. For a local database containing useful test data, that flag can erase substantial setup work.</p>
<h2>Improve the developer experience</h2>
<ul><li>Create a development-specific <code>Dockerfile</code> when the team needs a debugger or code-quality tools.</li><li>Use a bind mount for source code, but avoid dependency mounts that cause slow host filesystem I/O.</li><li>Add healthchecks to databases and message brokers the application must wait for.</li><li>Pin image versions intentionally and schedule upgrades.</li><li>Put frequent commands in a script or Makefile so new contributors do not memorize long command lines.</li></ul>
<h2>Common problems</h2>
<h3>The app cannot connect to the database</h3><p>Inside the Compose network, <code>DB_HOST</code> should be the service name, such as <code>db</code>, rather than <code>127.0.0.1</code>. Also inspect the internal port, health status, and logs from both services.</p>
<h3>Source changes do not appear</h3><p>Check the volume path, working directory, and framework caches. A dependency or operating-system extension change requires an image rebuild; ordinary source changes only require a correct bind mount.</p>
<h3>Data disappears after a restart</h3><p>The database needs a named volume mounted at the image's correct data directory. Removing that volume or changing the Compose project name can also make the system start with a new data store.</p>
<h2>Team adoption checklist</h2>
<ol><li>A new developer can start the project from the README without verbal instructions.</li><li>Image versions are explicit and have an upgrade plan.</li><li>The repository contains no real passwords, tokens, or customer data.</li><li>Databases and required dependencies have useful healthchecks.</li><li>Migration, seed, test, and log commands are documented.</li><li>The team has deleted containers and rebuilt them to confirm that required data persists.</li></ol>
<h2>Conclusion</h2>
<p>Docker Compose does not eliminate every difference between local and production, but it gives the development team a clear environment contract. Start with the services the application truly needs, standardize daily commands, and test a clean rebuild.</p>
<h2>References</h2><ul><li><a href="https://docs.docker.com/compose/" target="_blank" rel="noopener noreferrer">Docker Compose documentation</a></li><li><a href="https://docs.docker.com/compose/how-tos/environment-variables/" target="_blank" rel="noopener noreferrer">Environment variables in Compose</a></li></ul>
HTML,
        ],
    ],
    'kiem-thu-tu-dong-laravel-pest.html' => [
        'vi' => [
            'title' => 'Kiểm thử tự động Laravel với Pest: Từ test đầu tiên đến quy trình CI',
            'slug' => 'kiem-thu-tu-dong-laravel-pest',
            'image' => 'kiem-thu-tu-dong-laravel-pest.jpg',
            'meta_title' => 'Kiểm thử Laravel với Pest: Hướng dẫn thực hành',
            'meta_keywords' => 'kiểm thử Laravel, Pest PHP, feature test, unit test, Laravel testing, test tự động, CI Laravel',
            'meta_description' => 'Hướng dẫn xây dựng kiểm thử tự động Laravel với Pest: chọn test có giá trị, quản lý database, viết feature test và tích hợp vào CI.',
            'tags' => ['Laravel', 'Pest PHP', 'Automated Testing', 'Feature Test', 'PHP', 'CI/CD'],
            'body' => <<<'HTML'
<p><strong>Kiểm thử tự động không nhằm chứng minh phần mềm không có lỗi; nó tạo ra tín hiệu sớm khi hành vi quan trọng bị thay đổi ngoài ý muốn.</strong> Với Laravel và Pest, nhóm có thể bắt đầu từ các luồng nghiệp vụ nhỏ, viết test dễ đọc và dần xây dựng mạng lưới bảo vệ cho những phần rủi ro nhất của hệ thống.</p>
<h2>Nên bắt đầu từ đâu?</h2>
<p>Đừng đặt mục tiêu phủ toàn bộ code ngay lập tức. Hãy ưu tiên những hành vi nếu hỏng sẽ gây thiệt hại rõ ràng: đăng nhập, phân quyền, tạo đơn hàng, thanh toán, cập nhật tồn kho, API tích hợp và các lỗi từng xuất hiện trên production.</p>
<table><thead><tr><th>Loại test</th><th>Phù hợp với</th><th>Đặc điểm</th></tr></thead><tbody><tr><td>Unit</td><td>Hàm tính toán, quy tắc nghiệp vụ độc lập</td><td>Chạy nhanh, ít phụ thuộc framework</td></tr><tr><td>Feature</td><td>HTTP, database, middleware, quyền truy cập</td><td>Cho tín hiệu gần với hành vi thực tế</td></tr><tr><td>Browser</td><td>Luồng giao diện quan trọng</td><td>Giá trị cao nhưng chậm và cần chăm sóc nhiều hơn</td></tr><tr><td>Architecture</td><td>Quy tắc cấu trúc và dependency</td><td>Ngăn codebase lệch khỏi convention</td></tr></tbody></table>
<h2>Test đầu tiên bằng Pest</h2>
<pre><code>&lt;?php

it('redirects guests away from the dashboard', function () {
    $response = $this-&gt;get('/dashboard');

    $response-&gt;assertRedirect('/login');
});</code></pre>
<p>Tên test nên diễn đạt kết quả quan sát được. Tránh đặt tên theo method nội bộ, vì refactor có thể đổi method nhưng hành vi người dùng vẫn giữ nguyên.</p>
<h2>Kiểm tra một luồng có database</h2>
<pre><code>&lt;?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an active user to update their profile', function () {
    $user = User::factory()-&gt;create(['is_active' =&gt; true]);

    $response = $this-&gt;actingAs($user)-&gt;put('/profile', [
        'name' =&gt; 'Nguyen Minh An',
        'email' =&gt; 'an@example.test',
    ]);

    $response-&gt;assertRedirect('/profile');
    $this-&gt;assertDatabaseHas('users', [
        'id' =&gt; $user-&gt;id,
        'name' =&gt; 'Nguyen Minh An',
    ]);
});</code></pre>
<p><code>RefreshDatabase</code> giúp mỗi test bắt đầu từ trạng thái dữ liệu kiểm soát được. Factory tạo bản ghi rõ ý nghĩa hơn việc phụ thuộc vào database có sẵn trên máy người viết test.</p>
<h2>Test cả đường thành công và đường từ chối</h2>
<p>Chỉ kiểm tra trường hợp hợp lệ dễ bỏ sót lỗ hổng. Với endpoint cập nhật hồ sơ, nên thêm các tình huống như người dùng chưa đăng nhập, email sai định dạng, email trùng và tài khoản bị khóa. Với phân quyền, hãy kiểm tra cả người có quyền và người không có quyền.</p>
<blockquote>Test giá trị cao bảo vệ một cam kết nghiệp vụ. Nó không cần biết controller gọi bao nhiêu method; nó cần chứng minh người dùng đúng vai trò nhận được đúng kết quả.</blockquote>
<h2>Giữ test ổn định và dễ bảo trì</h2>
<ul><li>Dùng factory state như <code>active()</code> hoặc <code>admin()</code> để dữ liệu thể hiện rõ ý định.</li><li>Không gọi API bên thứ ba thật; dùng fake cho mail, queue, notification, storage và HTTP client.</li><li>Đóng băng thời gian khi logic liên quan đến hạn dùng hoặc lịch chạy.</li><li>Không phụ thuộc thứ tự test; mỗi test phải tự chuẩn bị trạng thái.</li><li>Ưu tiên assertion về kết quả nghiệp vụ, hạn chế khóa test vào HTML hoặc cấu trúc nội bộ.</li></ul>
<h2>Fake các tác vụ phụ</h2>
<pre><code>use Illuminate\Support\Facades\Queue;
use App\Jobs\SendWelcomeEmail;

it('queues a welcome email after registration', function () {
    Queue::fake();

    $this-&gt;post('/register', [
        'name' =&gt; 'Lan',
        'email' =&gt; 'lan@example.test',
        'password' =&gt; 'Strong-password-123',
        'password_confirmation' =&gt; 'Strong-password-123',
    ])-&gt;assertRedirect('/dashboard');

    Queue::assertPushed(SendWelcomeEmail::class);
});</code></pre>
<h2>Chạy nhanh trên máy lập trình</h2>
<pre><code>php artisan test
php artisan test --stop-on-failure
php artisan test --filter=profile
php artisan test --parallel --processes=4</code></pre>
<p>Chạy song song có thể rút ngắn thời gian, nhưng test phải cô lập tài nguyên. Tệp dùng chung, cổng mạng cố định hoặc dịch vụ bên ngoài có thể gây lỗi ngẫu nhiên giữa các process.</p>
<h2>Đưa test vào CI</h2>
<pre><code>name: tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env
      - run: php artisan key:generate
      - run: php artisan test</code></pre>
<p>Đây là khung khởi đầu. Dự án dùng MySQL, Redis hoặc trình duyệt cần thêm service và cấu hình tương ứng. Không dùng thông tin bí mật production cho CI và phải tách riêng database test.</p>
<h2>Dấu hiệu test đang thành gánh nặng</h2>
<ul><li>Test thất bại ngẫu nhiên khi code không đổi.</li><li>Một thay đổi giao diện nhỏ làm hỏng nhiều test không liên quan.</li><li>Fixture quá lớn khiến người đọc không biết dữ liệu nào quan trọng.</li><li>Test mock mọi lớp trung gian và chỉ xác nhận cách triển khai hiện tại.</li><li>Bộ test chậm đến mức nhóm bỏ qua trước khi push.</li></ul>
<p>Khi thấy các dấu hiệu này, hãy giảm dữ liệu, cô lập dịch vụ ngoài, chia test theo mức độ và giữ một nhóm smoke test chạy nhanh. Xóa test trùng lặp cũng là bảo trì có giá trị.</p>
<h2>Lộ trình áp dụng thực tế</h2>
<ol><li>Chọn ba đến năm luồng kinh doanh quan trọng nhất.</li><li>Viết feature test cho thành công, validation và phân quyền.</li><li>Thêm regression test mỗi khi sửa một lỗi production.</li><li>Chạy nhóm test nhanh trên mọi pull request.</li><li>Theo dõi thời gian chạy và lỗi không ổn định.</li><li>Mở rộng sang browser test hoặc architecture test khi giá trị đã rõ.</li></ol>
<h2>Kết luận</h2>
<p>Một bộ test hữu ích không được đo chỉ bằng phần trăm coverage. Nó được đo bằng khả năng phát hiện thay đổi nguy hiểm sớm, giải thích lỗi rõ và chạy đủ nhanh để đội ngũ sử dụng thường xuyên. Với Laravel và Pest, hãy bắt đầu bằng hành vi quan trọng, quản lý dữ liệu chặt chẽ và để CI biến các cam kết đó thành bước kiểm tra lặp lại được.</p>
<h2>Nguồn tham khảo</h2><ul><li><a href="https://laravel.com/docs/testing" target="_blank" rel="noopener noreferrer">Laravel Testing</a></li><li><a href="https://pestphp.com/docs/writing-tests" target="_blank" rel="noopener noreferrer">Writing Tests with Pest</a></li><li><a href="https://pestphp.com/docs/arch-testing" target="_blank" rel="noopener noreferrer">Pest Architecture Testing</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Automated Laravel Testing with Pest: From Your First Test to CI',
            'slug' => 'automated-laravel-testing-with-pest',
            'image' => 'kiem-thu-tu-dong-laravel-pest.jpg',
            'meta_title' => 'Automated Laravel Testing with Pest: A Practical Guide',
            'meta_keywords' => 'Laravel testing, Pest PHP, feature tests, unit tests, automated testing, Laravel CI',
            'meta_description' => 'Build useful automated tests for Laravel with Pest: prioritize valuable behavior, manage databases, write feature tests, and run them in CI.',
            'tags' => ['Laravel', 'Pest PHP', 'Automated Testing', 'Feature Testing', 'PHP', 'CI/CD'],
            'body' => <<<'HTML'
<p><strong>Automated testing does not prove that software contains no bugs; it provides an early signal when important behavior changes unexpectedly.</strong> With Laravel and Pest, a team can begin with a few business-critical flows, write readable tests, and gradually build protection around the riskiest parts of the system.</p>
<h2>Where should you start?</h2>
<p>Do not make complete code coverage the first target. Prioritize behavior whose failure creates clear damage: authentication, authorization, ordering, payments, inventory, integration APIs, and defects that have already reached production.</p>
<table><thead><tr><th>Test type</th><th>Best suited to</th><th>Characteristics</th></tr></thead><tbody><tr><td>Unit</td><td>Calculations and isolated business rules</td><td>Fast, with few framework dependencies</td></tr><tr><td>Feature</td><td>HTTP, databases, middleware, and access control</td><td>Signals behavior close to the real application</td></tr><tr><td>Browser</td><td>Critical interface journeys</td><td>High value, but slower to maintain</td></tr><tr><td>Architecture</td><td>Structure and dependency rules</td><td>Prevents drift from agreed conventions</td></tr></tbody></table>
<h2>Your first Pest test</h2>
<pre><code>&lt;?php

it('redirects guests away from the dashboard', function () {
    $response = $this-&gt;get('/dashboard');

    $response-&gt;assertRedirect('/login');
});</code></pre>
<p>A test name should state an observable result. Avoid naming it after an internal method, because refactoring may change the method while preserving user-facing behavior.</p>
<h2>Testing a database-backed flow</h2>
<pre><code>&lt;?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows an active user to update their profile', function () {
    $user = User::factory()-&gt;create(['is_active' =&gt; true]);

    $response = $this-&gt;actingAs($user)-&gt;put('/profile', [
        'name' =&gt; 'Alex Nguyen',
        'email' =&gt; 'alex@example.test',
    ]);

    $response-&gt;assertRedirect('/profile');
    $this-&gt;assertDatabaseHas('users', [
        'id' =&gt; $user-&gt;id,
        'name' =&gt; 'Alex Nguyen',
    ]);
});</code></pre>
<p><code>RefreshDatabase</code> lets every test start from controlled data. Factories create records whose intent is clearer than relying on data already present on a developer's computer.</p>
<h2>Test success and rejection paths</h2>
<p>Testing only valid input can leave serious gaps. For a profile endpoint, add cases for guests, invalid email, duplicate email, and suspended accounts. For authorization, verify both a role that has permission and one that does not.</p>
<blockquote>A high-value test protects a business promise. It does not need to know how many methods a controller calls; it needs to prove that the right user receives the right result.</blockquote>
<h2>Keep tests stable and maintainable</h2>
<ul><li>Use factory states such as <code>active()</code> or <code>admin()</code> so test data communicates intent.</li><li>Do not call real third-party APIs; fake mail, queues, notifications, storage, and HTTP clients.</li><li>Freeze time when logic involves expiry or schedules.</li><li>Never depend on test order; each test prepares its own state.</li><li>Assert business outcomes instead of fragile HTML or internal structure.</li></ul>
<h2>Fake secondary work</h2>
<pre><code>use Illuminate\Support\Facades\Queue;
use App\Jobs\SendWelcomeEmail;

it('queues a welcome email after registration', function () {
    Queue::fake();

    $this-&gt;post('/register', [
        'name' =&gt; 'Taylor',
        'email' =&gt; 'taylor@example.test',
        'password' =&gt; 'Strong-password-123',
        'password_confirmation' =&gt; 'Strong-password-123',
    ])-&gt;assertRedirect('/dashboard');

    Queue::assertPushed(SendWelcomeEmail::class);
});</code></pre>
<h2>Run tests efficiently</h2>
<pre><code>php artisan test
php artisan test --stop-on-failure
php artisan test --filter=profile
php artisan test --parallel --processes=4</code></pre>
<p>Parallel execution can reduce feedback time, but tests must isolate resources. Shared files, fixed network ports, or external services can create intermittent failures between processes.</p>
<h2>Put the suite in CI</h2>
<pre><code>name: tests
on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
      - run: composer install --no-interaction --prefer-dist
      - run: cp .env.example .env
      - run: php artisan key:generate
      - run: php artisan test</code></pre>
<p>This is a starting frame. Projects using MySQL, Redis, or browser testing need the corresponding services and configuration. Keep production secrets out of CI and use a separate test database.</p>
<h2>Signs tests are becoming a burden</h2>
<ul><li>Tests fail intermittently even when the code has not changed.</li><li>A small interface adjustment breaks many unrelated tests.</li><li>Fixtures are so large that readers cannot identify relevant data.</li><li>Tests mock every layer and merely verify the current implementation.</li><li>The suite is slow enough that developers skip it before pushing.</li></ul>
<p>When these signs appear, reduce data, isolate external services, divide tests by level, and preserve a fast smoke suite. Removing duplicate tests is valuable maintenance too.</p>
<h2>A practical adoption path</h2>
<ol><li>Select three to five business flows with the highest risk.</li><li>Write feature tests for success, validation, and authorization.</li><li>Add a regression test whenever a production defect is fixed.</li><li>Run the fast suite on every pull request.</li><li>Track execution time and flaky failures.</li><li>Add browser or architecture tests when their value is clear.</li></ol>
<h2>Conclusion</h2>
<p>A useful test suite is not measured only by a coverage percentage. Its real value is detecting dangerous changes early, explaining failures clearly, and running quickly enough that the team uses it every day. With Laravel and Pest, begin with important behavior, control test data carefully, and let CI turn those promises into a repeatable quality check.</p>
<h2>References</h2><ul><li><a href="https://laravel.com/docs/testing" target="_blank" rel="noopener noreferrer">Laravel Testing</a></li><li><a href="https://pestphp.com/docs/writing-tests" target="_blank" rel="noopener noreferrer">Writing Tests with Pest</a></li><li><a href="https://pestphp.com/docs/arch-testing" target="_blank" rel="noopener noreferrer">Pest Architecture Testing</a></li></ul>
HTML,
        ],
    ],
];
