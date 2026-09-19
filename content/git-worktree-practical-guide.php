<?php

return [
    'git-worktree-practical-guide.html' => [
        'vi' => [
            'title' => 'Git worktree thực chiến: Làm nhiều nhánh song song không cần stash',
            'slug' => 'git-worktree-lam-nhieu-nhanh-song-song',
            'image' => 'git-worktree-practical-guide.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Git worktree thực chiến cho lập trình viên',
            'meta_keywords' => 'Git worktree, Git branch, làm nhiều nhánh song song, git stash, hotfix, linked worktree, Git workflow',
            'meta_description' => 'Hướng dẫn Git worktree từ cơ bản đến thực chiến: tạo feature và hotfix song song, quản lý branch, dọn dẹp, lock, move, repair và tránh lỗi thường gặp.',
            'tags' => ['Git', 'Git Worktree', 'Version Control', 'Developer Tools', 'Workflow', 'Programming'],
            'body' => <<<'HTML'
<p><strong>Bạn đang sửa một tính năng dở dang thì production cần hotfix ngay.</strong> Cách quen thuộc là stash thay đổi, chuyển nhánh, sửa lỗi rồi quay lại. Nhưng khi workspace có nhiều file chưa commit, dependency đang chạy và IDE đã mở đúng ngữ cảnh, chuỗi thao tác đó vừa mất thời gian vừa dễ nhầm. <code>git worktree</code> giải quyết bài toán bằng cách cho một repository có nhiều thư mục làm việc, mỗi thư mục checkout một nhánh riêng.</p>

<h2>Git worktree là gì?</h2>
<p>Một repository thông thường có một working tree: thư mục chứa source code bạn đang chỉnh sửa. Git worktree cho phép gắn thêm các <em>linked worktree</em> vào cùng repository. Chúng dùng chung object database và lịch sử Git, nhưng mỗi nơi có <code>HEAD</code>, index và file làm việc riêng.</p>
<p>Vì dùng chung dữ liệu Git, worktree nhẹ hơn clone lại toàn bộ repository. Commit tạo ở worktree feature xuất hiện ngay trong repository chính; bạn không cần fetch giữa các thư mục trên cùng máy.</p>
<blockquote>Worktree không phải bản sao tạm thời. Mỗi worktree là một workspace Git thực thụ, có branch và trạng thái file độc lập.</blockquote>

<h2>Khi nào nên dùng?</h2>
<ul>
<li>Đang làm feature dài ngày nhưng cần xử lý hotfix khẩn cấp.</li>
<li>Muốn chạy hai phiên bản ứng dụng cạnh nhau để so sánh hành vi.</li>
<li>Review pull request mà không làm xáo trộn nhánh đang phát triển.</li>
<li>Chạy test hoặc migration trên một nhánh trong khi vẫn viết code ở nhánh khác.</li>
<li>Làm việc với nhiều tác vụ hoặc coding agent song song, mỗi tác vụ có workspace riêng.</li>
</ul>
<p>Nếu chỉ cần xem nhanh một file cũ hoặc đổi nhánh khi workspace đang sạch, <code>git switch</code> vẫn đơn giản hơn. Worktree phát huy giá trị khi các ngữ cảnh cần tồn tại đồng thời.</p>

<h2>1. Tạo worktree cho một feature mới</h2>
<p>Giả sử repository chính nằm trong thư mục <code>shop</code> và đang ở nhánh <code>main</code>:</p>
<pre><code>cd shop
git fetch origin
git worktree add -b feature/checkout ../shop-checkout origin/main</code></pre>
<p>Lệnh trên tạo nhánh <code>feature/checkout</code> từ <code>origin/main</code>, đồng thời checkout nhánh đó vào thư mục ngang cấp <code>shop-checkout</code>. Sau đó mở IDE hoặc terminal mới:</p>
<pre><code>cd ../shop-checkout
git status
git branch --show-current</code></pre>
<p>Mọi commit trong thư mục này thuộc nhánh feature. Repository chính vẫn giữ nguyên branch và toàn bộ thay đổi chưa commit ban đầu.</p>

<h2>2. Tạo hotfix trong lúc feature còn dang dở</h2>
<pre><code>git fetch origin
git worktree add -b hotfix/payment-timeout ../shop-hotfix origin/main

cd ../shop-hotfix
# sửa lỗi, chạy test
git add .
git commit -m "Fix payment timeout handling"
git push -u origin hotfix/payment-timeout</code></pre>
<p>Feature không cần stash và dev server của feature có thể tiếp tục chạy. Sau khi hotfix được merge, xóa worktree đúng cách:</p>
<pre><code>cd ../shop
git worktree remove ../shop-hotfix
git branch -d hotfix/payment-timeout</code></pre>
<p><code>worktree remove</code> chỉ xóa worktree sạch. Nếu còn file sửa hoặc file chưa được theo dõi, Git sẽ từ chối để bảo vệ dữ liệu. Hãy kiểm tra và commit hoặc sao lưu phần cần thiết trước khi cân nhắc <code>--force</code>.</p>

<h2>3. Checkout một nhánh đã tồn tại</h2>
<pre><code>git fetch origin
git worktree add ../shop-release release/2.4</code></pre>
<p>Một branch thông thường không thể được checkout đồng thời ở hai worktree. Ràng buộc này ngăn hai thư mục cùng âm thầm thay đổi một branch. Nếu nhánh đang được dùng, <code>git worktree list</code> cho biết nó nằm ở đâu.</p>
<p>Để xem một commit mà không gắn với branch, chẳng hạn phục vụ điều tra hoặc benchmark, dùng detached HEAD:</p>
<pre><code>git worktree add --detach ../shop-benchmark v2.3.1</code></pre>
<p>Nếu cần giữ commit tạo trong chế độ detached, hãy tạo branch trước khi dọn worktree.</p>

<h2>4. Quản lý danh sách worktree</h2>
<pre><code>git worktree list
git worktree list --porcelain</code></pre>
<p>Dạng mặc định phù hợp cho con người; <code>--porcelain</code> có định dạng ổn định để script xử lý. Không nên tự đọc hoặc chỉnh các file quản trị trong <code>.git/worktrees</code>.</p>
<p>Một quy ước thư mục dễ quản lý là đặt linked worktree cạnh repository chính:</p>
<pre><code>projects/
├── shop/              # main worktree
├── shop-feature-cart/
├── shop-hotfix-auth/
└── shop-review-482/</code></pre>
<p>Tên thư mục nên thể hiện mục đích và số issue/PR. Không đặt worktree bên trong repository chính vì công cụ tìm kiếm, watcher hoặc Docker build context có thể quét lẫn source của nhánh khác.</p>

<h2>5. Dependency, biến môi trường và cổng dịch vụ</h2>
<p>Các worktree chia sẻ Git object nhưng không chia sẻ file bị ignore. Mỗi thư mục thường cần cài dependency riêng như <code>node_modules</code>, <code>vendor</code> hoặc virtual environment. Đây là điều tốt về tính cô lập nhưng tốn thêm dung lượng.</p>
<ul>
<li>Tạo <code>.env</code> riêng cho từng worktree và không commit secret.</li>
<li>Dùng database/schema hoặc container name khác nếu hai phiên bản chạy đồng thời.</li>
<li>Đặt port khác nhau, ví dụ ứng dụng chính ở 3000 và feature ở 3001.</li>
<li>Không dùng chung thư mục build/cache nếu công cụ không bảo đảm concurrency.</li>
</ul>
<p>Có thể viết script bootstrap của dự án để copy <code>.env.example</code>, cài dependency và in ra port đề xuất ngay sau khi tạo worktree.</p>

<h2>6. Di chuyển, khóa và sửa worktree</h2>
<p>Muốn đổi vị trí linked worktree, ưu tiên lệnh Git thay vì kéo thả thư mục:</p>
<pre><code>git worktree move ../shop-feature-cart ../archive/shop-cart</code></pre>
<p>Nếu worktree nằm trên ổ rời hoặc network share đôi khi không được mount, hãy khóa để metadata không bị dọn:</p>
<pre><code>git worktree lock --reason "External SSD" ../archive/shop-cart
git worktree unlock ../archive/shop-cart</code></pre>
<p>Nếu bạn đã di chuyển thư mục thủ công hoặc đường dẫn repository chính thay đổi, dùng:</p>
<pre><code>git worktree repair ../archive/shop-cart</code></pre>
<p>Worktree có submodule có thêm giới hạn khi move/remove; luôn kiểm tra phiên bản Git và tài liệu hiện hành trước khi tự động hóa quy trình này.</p>

<h2>7. Dọn metadata bị sót</h2>
<p>Nếu ai đó xóa thẳng thư mục linked worktree, metadata có thể còn lại. Hãy xem trước rồi mới dọn:</p>
<pre><code>git worktree prune --dry-run --verbose
git worktree prune --verbose</code></pre>
<p><code>prune</code> dọn thông tin của worktree không còn tồn tại; nó không phải lệnh dọn branch đã merge. Việc xóa branch vẫn thực hiện riêng bằng <code>git branch -d</code>.</p>

<h2>8. Workflow review pull request an toàn</h2>
<pre><code>git fetch origin pull/482/head:review/pr-482
git worktree add ../shop-review-482 review/pr-482

cd ../shop-review-482
# cài dependency, chạy test, đọc code
</code></pre>
<p>Khi review xong:</p>
<pre><code>cd ../shop
git worktree remove ../shop-review-482
git branch -D review/pr-482</code></pre>
<p>Workflow này giữ nguyên workspace feature và giúp review đúng code thực tế. Với nền tảng không cung cấp ref <code>pull/.../head</code>, fetch branch remote tương ứng rồi tạo worktree từ remote-tracking branch.</p>

<h2>9. Những lỗi thường gặp</h2>
<ul>
<li><strong>“branch is already checked out”:</strong> branch đang nằm trong worktree khác; xem <code>git worktree list</code> thay vì ép checkout.</li>
<li><strong>Xóa thư mục bằng trình quản lý file:</strong> dùng <code>git worktree remove</code> để dọn cả thư mục lẫn metadata.</li>
<li><strong>Nhầm rằng stash dùng chung theo workspace:</strong> stash thuộc repository chung, nên nội dung stash có thể nhìn thấy từ mọi worktree.</li>
<li><strong>Hai server tranh cùng port:</strong> cấp port, database và tên container riêng.</li>
<li><strong>Force remove khi chưa kiểm tra:</strong> có thể mất file untracked; luôn chạy <code>git status</code> trong đúng worktree.</li>
<li><strong>Tạo quá nhiều worktree bỏ quên:</strong> định kỳ xem danh sách, tuổi branch và trạng thái PR.</li>
</ul>

<h2>Checklist áp dụng cho đội nhóm</h2>
<ol>
<li>Đặt linked worktree ngoài thư mục repository chính.</li>
<li>Mỗi task dùng một branch và một worktree rõ tên.</li>
<li>Tách port, database, cache và biến môi trường khi chạy song song.</li>
<li>Commit hoặc lưu dữ liệu cần thiết trước khi remove.</li>
<li>Dùng <code>remove</code>, <code>move</code> và <code>repair</code> thay cho thao tác file thủ công.</li>
<li>Dọn worktree sau khi PR merge và xóa branch theo chính sách của đội.</li>
</ol>

<h2>Kết luận</h2>
<p><code>git worktree</code> không thay thế branch, commit hay stash; nó bổ sung khả năng giữ nhiều ngữ cảnh làm việc cùng lúc. Với feature dài ngày, hotfix khẩn cấp và review song song, worktree giúp giảm chuyển đổi ngữ cảnh mà vẫn dựa trên cơ chế bảo vệ dữ liệu của Git. Hãy bắt đầu bằng một worktree hotfix nhỏ, đặt quy ước thư mục rõ ràng và dọn nó ngay khi công việc hoàn tất.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://git-scm.com/docs/git-worktree" target="_blank" rel="noopener noreferrer">Tài liệu chính thức git-worktree</a></li><li><a href="https://git-scm.com/docs/git-branch" target="_blank" rel="noopener noreferrer">Tài liệu chính thức git-branch</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Git Worktree in Practice: Work on Multiple Branches Without Stashing',
            'slug' => 'git-worktree-multiple-branches-without-stashing',
            'image' => 'git-worktree-practical-guide.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'A Practical Git Worktree Guide for Developers',
            'meta_keywords' => 'Git worktree, Git branch, parallel branches, git stash, hotfix, linked worktree, Git workflow',
            'meta_description' => 'Learn Git worktree in practice: run feature and hotfix branches side by side, manage linked worktrees, clean metadata, lock, move, repair, and avoid common mistakes.',
            'tags' => ['Git', 'Git Worktree', 'Version Control', 'Developer Tools', 'Workflow', 'Programming'],
            'body' => <<<'HTML'
<p><strong>You are halfway through a feature when production suddenly needs a hotfix.</strong> The familiar response is to stash your changes, switch branches, fix the issue, and restore the original workspace. That becomes awkward when many files are uncommitted, dependencies are running, and the IDE already holds valuable context. <code>git worktree</code> solves this by giving one repository multiple working directories, each with its own checked-out branch.</p>

<h2>What is Git worktree?</h2>
<p>A conventional repository has one working tree: the directory containing the files you edit. Git worktree can attach additional <em>linked worktrees</em> to that repository. They share the Git object database and history while keeping their own <code>HEAD</code>, index, and working files.</p>
<p>Because Git data is shared, a worktree is lighter than cloning the entire repository again. A commit created in a feature worktree is immediately visible from the main repository; there is no need to fetch between local directories.</p>
<blockquote>A worktree is not a disposable copy. It is a real Git workspace with an independent branch and file state.</blockquote>

<h2>When should you use it?</h2>
<ul>
<li>A long-running feature is in progress when an urgent hotfix arrives.</li>
<li>You need two application versions running side by side for comparison.</li>
<li>You want to review a pull request without disturbing current development.</li>
<li>Tests or migrations must run on one branch while you code on another.</li>
<li>Several tasks or coding agents need isolated workspaces in parallel.</li>
</ul>
<p>If you only need to inspect an old file or switch from a clean workspace, <code>git switch</code> remains simpler. Worktrees are most valuable when contexts must stay alive at the same time.</p>

<h2>1. Create a worktree for a new feature</h2>
<p>Assume the main repository is in <code>shop</code> on the <code>main</code> branch:</p>
<pre><code>cd shop
git fetch origin
git worktree add -b feature/checkout ../shop-checkout origin/main</code></pre>
<p>This creates <code>feature/checkout</code> from <code>origin/main</code> and checks it out into the sibling directory <code>shop-checkout</code>. Open a new IDE window or terminal:</p>
<pre><code>cd ../shop-checkout
git status
git branch --show-current</code></pre>
<p>Every commit here belongs to the feature branch. The main repository keeps its current branch and all original uncommitted changes untouched.</p>

<h2>2. Create a hotfix while the feature stays open</h2>
<pre><code>git fetch origin
git worktree add -b hotfix/payment-timeout ../shop-hotfix origin/main

cd ../shop-hotfix
# fix the issue and run tests
git add .
git commit -m "Fix payment timeout handling"
git push -u origin hotfix/payment-timeout</code></pre>
<p>The feature requires no stash, and its development server can keep running. Once the hotfix is merged, remove the worktree properly:</p>
<pre><code>cd ../shop
git worktree remove ../shop-hotfix
git branch -d hotfix/payment-timeout</code></pre>
<p><code>worktree remove</code> removes only a clean worktree. Git refuses when tracked changes or untracked files remain, protecting your data. Inspect, commit, or back up anything important before considering <code>--force</code>.</p>

<h2>3. Check out an existing branch</h2>
<pre><code>git fetch origin
git worktree add ../shop-release release/2.4</code></pre>
<p>A normal branch cannot be checked out in two worktrees at once. This restriction prevents two directories from silently mutating the same branch. If a branch is already in use, <code>git worktree list</code> shows where it lives.</p>
<p>To inspect a commit without attaching a branch, for example during an investigation or benchmark, use detached HEAD:</p>
<pre><code>git worktree add --detach ../shop-benchmark v2.3.1</code></pre>
<p>Create a branch before cleanup if you need to preserve commits made in detached mode.</p>

<h2>4. Manage the worktree inventory</h2>
<pre><code>git worktree list
git worktree list --porcelain</code></pre>
<p>The default output is human-friendly. The stable <code>--porcelain</code> format is intended for scripts. Avoid reading or modifying administrative files in <code>.git/worktrees</code> yourself.</p>
<p>A practical convention is to keep linked worktrees beside the main repository:</p>
<pre><code>projects/
├── shop/              # main worktree
├── shop-feature-cart/
├── shop-hotfix-auth/
└── shop-review-482/</code></pre>
<p>Name directories after their purpose and issue or PR number. Do not place a worktree inside the main repository: search tools, file watchers, or Docker build contexts may accidentally scan another branch's source.</p>

<h2>5. Dependencies, environment variables, and ports</h2>
<p>Worktrees share Git objects, not ignored files. Each directory usually needs its own <code>node_modules</code>, <code>vendor</code>, or virtual environment. That isolation is useful, though it consumes extra disk space.</p>
<ul>
<li>Create a separate <code>.env</code> for each worktree and never commit secrets.</li>
<li>Use distinct databases, schemas, or container names when versions run concurrently.</li>
<li>Assign different ports, such as 3000 for the main app and 3001 for the feature.</li>
<li>Do not share build or cache directories unless the tool explicitly supports concurrency.</li>
</ul>
<p>A project bootstrap script can copy <code>.env.example</code>, install dependencies, and print a suggested port after a worktree is created.</p>

<h2>6. Move, lock, and repair worktrees</h2>
<p>Use Git when relocating a linked worktree instead of dragging the directory:</p>
<pre><code>git worktree move ../shop-feature-cart ../archive/shop-cart</code></pre>
<p>If a worktree lives on removable storage or a network share that is not always mounted, lock it so its metadata is not pruned:</p>
<pre><code>git worktree lock --reason "External SSD" ../archive/shop-cart
git worktree unlock ../archive/shop-cart</code></pre>
<p>If the directory was moved manually or the main repository path changed, run:</p>
<pre><code>git worktree repair ../archive/shop-cart</code></pre>
<p>Worktrees containing submodules have additional move and removal limitations. Check your Git version and current documentation before automating that workflow.</p>

<h2>7. Clean up stale metadata</h2>
<p>If someone deletes a linked directory directly, its administrative metadata may remain. Preview the cleanup first:</p>
<pre><code>git worktree prune --dry-run --verbose
git worktree prune --verbose</code></pre>
<p><code>prune</code> removes metadata for missing worktrees; it does not delete merged branches. Branch cleanup remains a separate <code>git branch -d</code> operation.</p>

<h2>8. A safe pull request review workflow</h2>
<pre><code>git fetch origin pull/482/head:review/pr-482
git worktree add ../shop-review-482 review/pr-482

cd ../shop-review-482
# install dependencies, run tests, inspect code
</code></pre>
<p>After the review:</p>
<pre><code>cd ../shop
git worktree remove ../shop-review-482
git branch -D review/pr-482</code></pre>
<p>This workflow preserves the feature workspace while letting you test the actual proposed code. On platforms that do not expose a <code>pull/.../head</code> ref, fetch the corresponding remote branch and create the worktree from that remote-tracking branch.</p>

<h2>9. Common mistakes</h2>
<ul>
<li><strong>“branch is already checked out”:</strong> the branch belongs to another worktree; use <code>git worktree list</code> instead of forcing checkout.</li>
<li><strong>Deleting a directory in a file manager:</strong> use <code>git worktree remove</code> to remove both the directory and metadata.</li>
<li><strong>Assuming stash belongs to one workspace:</strong> stashes belong to the shared repository and are visible from every worktree.</li>
<li><strong>Two servers compete for one port:</strong> assign separate ports, databases, and container names.</li>
<li><strong>Force-removing without inspection:</strong> untracked files can be lost; always run <code>git status</code> in the correct worktree.</li>
<li><strong>Accumulating abandoned worktrees:</strong> periodically review their branches, ages, and PR states.</li>
</ul>

<h2>Team adoption checklist</h2>
<ol>
<li>Keep linked worktrees outside the main repository directory.</li>
<li>Give every task one clearly named branch and worktree.</li>
<li>Separate ports, databases, caches, and environment variables for concurrent runs.</li>
<li>Commit or preserve important data before removal.</li>
<li>Use <code>remove</code>, <code>move</code>, and <code>repair</code> instead of manual file operations.</li>
<li>Clean up the worktree after merge and delete the branch according to team policy.</li>
</ol>

<h2>Conclusion</h2>
<p><code>git worktree</code> does not replace branches, commits, or stashes. It adds the ability to keep multiple development contexts active at once. For long-running features, urgent fixes, and parallel reviews, worktrees reduce context switching while retaining Git's data safeguards. Start with one small hotfix worktree, establish a clear directory convention, and remove it as soon as the task is complete.</p>

<h2>References</h2>
<ul><li><a href="https://git-scm.com/docs/git-worktree" target="_blank" rel="noopener noreferrer">Official git-worktree documentation</a></li><li><a href="https://git-scm.com/docs/git-branch" target="_blank" rel="noopener noreferrer">Official git-branch documentation</a></li></ul>
HTML,
        ],
    ],
];
