<?php

return [
    'zero-downtime-database-migrations.html' => [
        'vi' => [
            'title' => 'Database migration không downtime: Expand, Migrate, Contract từ A đến Z',
            'slug' => 'database-migration-khong-downtime-expand-migrate-contract',
            'image' => 'zero-downtime-database-migrations.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Database migration không downtime với Expand–Contract',
            'meta_keywords' => 'zero downtime migration, database migration, expand contract, PostgreSQL, backfill, dual write, schema migration',
            'meta_description' => 'Hướng dẫn migration database không downtime bằng Expand–Migrate–Contract: tương thích ngược, backfill theo lô, dual-write, index concurrently, constraint và rollback.',
            'tags' => ['Database', 'PostgreSQL', 'Migration', 'Zero Downtime', 'Backend', 'DevOps', 'SQL'],
            'body' => <<<'HTML'
<p><strong>Một câu <code>ALTER TABLE</code> chạy thành công chưa có nghĩa là deployment an toàn.</strong> Trong production, phiên bản ứng dụng cũ và mới thường cùng tồn tại trong vài phút hoặc lâu hơn. Một migration khóa bảng, đổi tên cột ngay lập tức hoặc backfill hàng triệu bản ghi trong một transaction có thể làm request chậm, cạn connection pool và khiến rollback trở nên bất khả thi.</p>
<p>Mô hình <strong>Expand–Migrate–Contract</strong> chia thay đổi schema thành nhiều lần triển khai tương thích ngược. Hệ thống tiếp tục phục vụ trong khi dữ liệu và code được chuyển dần sang cấu trúc mới.</p>

<h2>Vì sao migration gây downtime?</h2>
<p>Downtime thường không đến từ lỗi cú pháp mà từ lock và sự không tương thích giữa nhiều phiên bản:</p>
<ul>
<li>Ứng dụng cũ vẫn đọc cột vừa bị đổi tên hoặc xóa.</li>
<li><code>ALTER TABLE</code> chờ lock phía sau một transaction dài, rồi chặn các query mới.</li>
<li>Tạo index thông thường quét bảng lớn và chặn ghi.</li>
<li>Backfill khổng lồ tạo WAL, replication lag, table bloat và spike I/O.</li>
<li>Thêm constraint buộc database quét toàn bộ dữ liệu ngay lúc deploy.</li>
</ul>
<blockquote>Nguyên tắc cốt lõi: thay đổi database và ứng dụng theo từng bước mà mỗi bước đều chạy được với phiên bản đứng trước và đứng sau nó.</blockquote>

<h2>Ba giai đoạn Expand–Migrate–Contract</h2>
<ol>
<li><strong>Expand:</strong> thêm cấu trúc mới nhưng giữ nguyên cấu trúc cũ; code cũ vẫn hoạt động.</li>
<li><strong>Migrate:</strong> ứng dụng bắt đầu ghi/đọc cấu trúc mới, dữ liệu cũ được backfill và kiểm chứng.</li>
<li><strong>Contract:</strong> sau khi không còn consumer cũ, xóa code và schema đã lỗi thời.</li>
</ol>
<p>Ba giai đoạn nên là các deployment độc lập. Khoảng chờ giữa chúng có thể là vài giờ hoặc vài ngày tùy traffic, khả năng quan sát và chu kỳ phát hành.</p>

<h2>Tình huống mẫu: tách họ tên khách hàng</h2>
<p>Bảng <code>customers</code> đang có cột <code>full_name</code>. Hệ thống muốn chuyển sang <code>first_name</code> và <code>last_name</code>. Không nên đổi hoặc xóa <code>full_name</code> trong một migration duy nhất vì instance ứng dụng cũ vẫn cần nó.</p>

<h2>Giai đoạn 1: Expand schema</h2>
<pre><code>ALTER TABLE customers
    ADD COLUMN first_name text,
    ADD COLUMN last_name text;</code></pre>
<p>Hai cột mới cho phép <code>NULL</code>, vì dữ liệu cũ chưa được chuyển. Deploy này chỉ mở rộng schema, chưa đổi hành vi đọc. Trước khi chạy, đặt timeout ngắn để migration thất bại sớm thay vì xếp hàng rồi gây tắc nghẽn:</p>
<pre><code>SET lock_timeout = '2s';
SET statement_timeout = '15s';

ALTER TABLE customers ADD COLUMN first_name text;</code></pre>
<p>Nếu không lấy được lock trong giới hạn, pipeline nên dừng và thử lại ở thời điểm phù hợp. Đừng tăng timeout mù quáng; hãy tìm transaction giữ lock lâu.</p>

<h2>Giai đoạn 2: Deploy code tương thích hai schema</h2>
<p>Phiên bản chuyển tiếp có thể ghi cả cấu trúc cũ lẫn mới:</p>
<pre><code>UPDATE customers
SET full_name = :full_name,
    first_name = :first_name,
    last_name = :last_name
WHERE id = :id;</code></pre>
<p>Khi đọc, ưu tiên cột mới và fallback về cột cũ:</p>
<pre><code>SELECT
    id,
    COALESCE(first_name || ' ' || last_name, full_name) AS display_name
FROM customers
WHERE id = :id;</code></pre>
<p>Dual-write chỉ nên tồn tại tạm thời. Đặt metric cho số record thiếu cột mới và log mọi trường hợp hai biểu diễn không nhất quán. Nếu nhiều service cùng ghi bảng, tất cả writer phải được nâng cấp trước khi chuyển bước.</p>

<h2>Giai đoạn 3: Backfill theo lô</h2>
<p>Không update toàn bộ bảng trong một transaction. Hãy xử lý batch nhỏ theo khóa chính, commit giữa các batch và giới hạn tốc độ:</p>
<pre><code>UPDATE customers
SET first_name = split_part(full_name, ' ', 1),
    last_name = substring(full_name FROM position(' ' IN full_name) + 1)
WHERE id &gt; :last_id
  AND id &lt;= :next_id
  AND first_name IS NULL;</code></pre>
<p>Trong thực tế, việc tách tên cần rule nghiệp vụ tốt hơn ví dụ SQL trên. Điểm quan trọng là job phải <strong>idempotent</strong>: chạy lại không làm hỏng dữ liệu đã hoàn tất.</p>
<ul>
<li>Chọn batch theo primary key thay vì <code>OFFSET</code>.</li>
<li>Đo thời gian batch, row count, replication lag, CPU, I/O và WAL.</li>
<li>Giảm batch hoặc tạm dừng khi database chịu tải cao.</li>
<li>Lưu checkpoint để tiếp tục sau lỗi.</li>
<li>Không giữ transaction mở trong lúc sleep.</li>
</ul>

<h2>Kiểm chứng trước khi chuyển read path</h2>
<pre><code>SELECT count(*)
FROM customers
WHERE first_name IS NULL OR last_name IS NULL;</code></pre>
<p>Ngoài đếm <code>NULL</code>, hãy so sánh sample và invariant nghiệp vụ. Shadow read có thể đọc cả hai biểu diễn, trả kết quả mới cho một tỷ lệ traffic nhỏ và ghi metric sai khác mà chưa ảnh hưởng người dùng.</p>
<p>Khi dữ liệu đạt yêu cầu, deploy phiên bản đọc hoàn toàn từ cột mới nhưng vẫn tiếp tục ghi cột cũ trong một khoảng quan sát. Feature flag giúp chuyển lại read path nhanh mà không rollback schema.</p>

<h2>Thêm constraint mà không khóa lâu</h2>
<p>Với PostgreSQL, có thể dùng <code>CHECK ... NOT VALID</code> để áp dụng rule cho dữ liệu mới mà chưa quét ngay toàn bộ bảng, sau đó validate riêng:</p>
<pre><code>ALTER TABLE customers
ADD CONSTRAINT customers_first_name_present
CHECK (first_name IS NOT NULL) NOT VALID;

ALTER TABLE customers
VALIDATE CONSTRAINT customers_first_name_present;</code></pre>
<p>Sau khi validate, có thể chuyển thành <code>NOT NULL</code> theo khả năng của phiên bản PostgreSQL và kế hoạch đã thử nghiệm. <code>NOT VALID</code> chỉ áp dụng cho một số loại constraint như <code>CHECK</code> và foreign key; không giả định mọi constraint đều hỗ trợ.</p>

<h2>Tạo index trên bảng lớn</h2>
<pre><code>CREATE INDEX CONCURRENTLY idx_customers_last_name
ON customers (last_name);</code></pre>
<p><code>CONCURRENTLY</code> giảm việc chặn ghi nhưng chạy lâu hơn, dùng thêm tài nguyên và không được đặt trong transaction block thông thường. Nếu thất bại, PostgreSQL có thể để lại index <code>INVALID</code>; quy trình deploy phải phát hiện và xử lý trước khi chạy lại.</p>
<p>Không tạo index chỉ vì có cột mới. Xác nhận query thực tế bằng <code>EXPLAIN (ANALYZE, BUFFERS)</code> trên môi trường đại diện và theo dõi tác động sau phát hành.</p>

<h2>Đổi kiểu dữ liệu an toàn</h2>
<p>Đổi trực tiếp kiểu cột có thể rewrite bảng hoặc giữ lock lâu. Với bảng lớn, hãy tạo cột mới, dual-write, backfill rồi đổi read path:</p>
<pre><code>ALTER TABLE orders ADD COLUMN amount_cents bigint;

UPDATE orders
SET amount_cents = round(amount * 100)
WHERE amount_cents IS NULL
  AND id &gt; :last_id
  AND id &lt;= :next_id;</code></pre>
<p>Sau khi mọi reader và writer dùng <code>amount_cents</code>, cột <code>amount</code> mới được đưa vào giai đoạn Contract.</p>

<h2>Giai đoạn cuối: Contract có kiểm soát</h2>
<p>Chỉ xóa cấu trúc cũ khi telemetry chứng minh không còn truy cập. Thứ tự điển hình:</p>
<ol>
<li>Dừng dual-write vào cột cũ.</li>
<li>Theo dõi ít nhất một chu kỳ deploy đầy đủ và các worker chạy chậm.</li>
<li>Xóa code fallback, dashboard và job tạm.</li>
<li>Đặt lock timeout rồi xóa constraint/index/cột cũ trong deployment riêng.</li>
</ol>
<pre><code>SET lock_timeout = '2s';
ALTER TABLE customers DROP COLUMN full_name;</code></pre>
<p>Trong PostgreSQL, drop column thường đánh dấu cột là không còn nhìn thấy thay vì lập tức thu hồi toàn bộ dung lượng. Đừng chạy table rewrite chỉ để lấy lại disk nếu chưa đánh giá I/O, lock và cửa sổ bảo trì.</p>

<h2>Rollback không chỉ là chạy migration down</h2>
<p>Rollback code chỉ an toàn khi schema mới vẫn tương thích với phiên bản cũ. Vì vậy Expand giữ cột cũ và dual-write trong giai đoạn chuyển tiếp. Sau Contract, khôi phục thường khó hơn vì dữ liệu cũ đã ngừng được cập nhật hoặc đã bị xóa.</p>
<p>Mỗi kế hoạch cần trả lời: rollback code về phiên bản nào, dữ liệu nào sẽ mất, backfill có đảo ngược được không, và thời điểm nào được xem là “point of no return”. Backup không thay thế rollback nhanh; restore database lớn có thể lâu hơn SLA.</p>

<h2>Checklist trước production</h2>
<ul>
<li>Thử migration trên bản sao có kích thước và phân bố dữ liệu gần production.</li>
<li>Kiểm tra loại lock, thời gian giữ lock và khả năng table rewrite.</li>
<li>Đặt <code>lock_timeout</code> và <code>statement_timeout</code> phù hợp.</li>
<li>Bảo đảm code cũ và mới cùng chạy được trong giai đoạn rolling deploy.</li>
<li>Backfill idempotent, có checkpoint, throttle và dashboard tiến độ.</li>
<li>Theo dõi error rate, latency, connection pool, replication lag, WAL và disk.</li>
<li>Có feature flag cho read path và runbook dừng/tiếp tục.</li>
<li>Contract chỉ diễn ra sau khi xác nhận không còn consumer cũ.</li>
</ul>

<h2>Kết luận</h2>
<p>Database migration không downtime là bài toán về khả năng tương thích và vận hành, không chỉ là viết SQL. Expand tạo đường đi mới mà không phá đường cũ; Migrate chuyển code và dữ liệu có quan sát; Contract dọn cấu trúc cũ khi đã có bằng chứng an toàn. Chia nhỏ thay đổi giúp mỗi bước dễ dừng, dễ đo và dễ rollback hơn một migration “tất cả trong một”.</p>

<h2>Tài liệu tham khảo</h2>
<ul><li><a href="https://www.postgresql.org/docs/current/sql-altertable.html" target="_blank" rel="noopener noreferrer">PostgreSQL: ALTER TABLE</a></li><li><a href="https://www.postgresql.org/docs/current/ddl-alter.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Modifying Tables</a></li><li><a href="https://www.postgresql.org/docs/current/sql-createindex.html" target="_blank" rel="noopener noreferrer">PostgreSQL: CREATE INDEX</a></li></ul>
HTML,
        ],
        'en' => [
            'title' => 'Zero-Downtime Database Migrations: Expand, Migrate, Contract',
            'slug' => 'zero-downtime-database-migrations-expand-contract',
            'image' => 'zero-downtime-database-migrations.jpg',
            'category_vi' => 'Lập trình', 'category_en' => 'Programming',
            'meta_title' => 'Zero-Downtime Database Migrations with Expand–Contract',
            'meta_keywords' => 'zero downtime migration, database migration, expand contract, PostgreSQL, backfill, dual write, schema migration',
            'meta_description' => 'A practical guide to zero-downtime database migrations using Expand–Migrate–Contract, backward compatibility, batched backfills, concurrent indexes, constraints, and rollback planning.',
            'tags' => ['Database', 'PostgreSQL', 'Migration', 'Zero Downtime', 'Backend', 'DevOps', 'SQL'],
            'body' => <<<'HTML'
<p><strong>A successful <code>ALTER TABLE</code> does not automatically make a deployment safe.</strong> In production, old and new application versions commonly overlap for minutes or longer. A migration that locks a table, renames a column immediately, or backfills millions of rows in one transaction can increase latency, exhaust the connection pool, and make rollback impossible.</p>
<p>The <strong>Expand–Migrate–Contract</strong> pattern divides a schema change into several backward-compatible releases. The service remains available while code and data gradually move to the new structure.</p>

<h2>Why do migrations cause downtime?</h2>
<p>Downtime usually comes from locks and version incompatibility rather than SQL syntax:</p>
<ul>
<li>Old application instances still read a column that was renamed or removed.</li>
<li><code>ALTER TABLE</code> waits behind a long transaction and then blocks incoming queries.</li>
<li>A regular index build scans a large table while blocking writes.</li>
<li>A massive backfill creates WAL, replication lag, table bloat, and an I/O spike.</li>
<li>A new constraint forces the database to scan all existing data during deployment.</li>
</ul>
<blockquote>The core rule: evolve the database and application in steps where every step works with both the version before it and the version after it.</blockquote>

<h2>The three Expand–Migrate–Contract stages</h2>
<ol>
<li><strong>Expand:</strong> add the new structure while preserving the old one.</li>
<li><strong>Migrate:</strong> make the application read and write the new structure, backfill old data, and verify it.</li>
<li><strong>Contract:</strong> remove obsolete code and schema only after every old consumer is gone.</li>
</ol>
<p>These should be independent deployments. The observation period between them may last hours or days depending on traffic, observability, and the release cycle.</p>

<h2>Example: split a customer's full name</h2>
<p>The <code>customers</code> table currently stores <code>full_name</code>. We want separate <code>first_name</code> and <code>last_name</code> columns. Renaming or deleting <code>full_name</code> in one migration is unsafe because old application instances still depend on it.</p>

<h2>Stage 1: Expand the schema</h2>
<pre><code>ALTER TABLE customers
    ADD COLUMN first_name text,
    ADD COLUMN last_name text;</code></pre>
<p>The new columns initially allow <code>NULL</code> because historical rows have not been migrated. This deployment only expands the schema. Set short timeouts so the migration fails early instead of waiting in a lock queue and causing a pile-up:</p>
<pre><code>SET lock_timeout = '2s';
SET statement_timeout = '15s';

ALTER TABLE customers ADD COLUMN first_name text;</code></pre>
<p>If the lock cannot be acquired, stop and retry in a safer window. Do not blindly increase the timeout; identify the long-running transaction first.</p>

<h2>Stage 2: Deploy code compatible with both schemas</h2>
<p>The transition release can write the old and new representations:</p>
<pre><code>UPDATE customers
SET full_name = :full_name,
    first_name = :first_name,
    last_name = :last_name
WHERE id = :id;</code></pre>
<p>Reads prefer new columns and fall back to the old one:</p>
<pre><code>SELECT
    id,
    COALESCE(first_name || ' ' || last_name, full_name) AS display_name
FROM customers
WHERE id = :id;</code></pre>
<p>Dual-write should be temporary. Track records missing new values and log mismatches between representations. If several services write the table, every writer must be upgraded before proceeding.</p>

<h2>Stage 3: Backfill in batches</h2>
<p>Do not update the whole table in one transaction. Process small primary-key ranges, commit between batches, and throttle the job:</p>
<pre><code>UPDATE customers
SET first_name = split_part(full_name, ' ', 1),
    last_name = substring(full_name FROM position(' ' IN full_name) + 1)
WHERE id &gt; :last_id
  AND id &lt;= :next_id
  AND first_name IS NULL;</code></pre>
<p>Real name parsing requires better business rules than this simplified SQL. The important property is <strong>idempotency</strong>: rerunning the job must not corrupt completed rows.</p>
<ul>
<li>Batch by primary key instead of <code>OFFSET</code>.</li>
<li>Measure batch duration, row count, replication lag, CPU, I/O, and WAL.</li>
<li>Reduce the batch size or pause under database pressure.</li>
<li>Store checkpoints for recovery.</li>
<li>Never keep a transaction open while sleeping.</li>
</ul>

<h2>Verify before switching the read path</h2>
<pre><code>SELECT count(*)
FROM customers
WHERE first_name IS NULL OR last_name IS NULL;</code></pre>
<p>Beyond counting nulls, compare samples and business invariants. Shadow reads can evaluate both representations, expose the new result to a small traffic percentage, and record differences without affecting every user.</p>
<p>Once verification passes, deploy a version that reads only the new columns while continuing to write the old column during an observation window. A feature flag offers a quick read-path fallback without rolling back the schema.</p>

<h2>Add constraints without a long blocking scan</h2>
<p>PostgreSQL supports <code>CHECK ... NOT VALID</code>, which enforces the rule for new writes without immediately scanning all historical rows. Validate separately:</p>
<pre><code>ALTER TABLE customers
ADD CONSTRAINT customers_first_name_present
CHECK (first_name IS NOT NULL) NOT VALID;

ALTER TABLE customers
VALIDATE CONSTRAINT customers_first_name_present;</code></pre>
<p>After validation, convert it to <code>NOT NULL</code> according to your PostgreSQL version and tested plan. <code>NOT VALID</code> applies only to selected constraint types such as checks and foreign keys; do not assume universal support.</p>

<h2>Build an index on a large table</h2>
<pre><code>CREATE INDEX CONCURRENTLY idx_customers_last_name
ON customers (last_name);</code></pre>
<p><code>CONCURRENTLY</code> reduces write blocking but takes longer, consumes resources, and cannot run inside a regular transaction block. A failure may leave an <code>INVALID</code> index, so deployment automation must detect and resolve it before retrying.</p>
<p>Do not add an index merely because a column is new. Validate real queries with <code>EXPLAIN (ANALYZE, BUFFERS)</code> in a representative environment and monitor after release.</p>

<h2>Change a data type safely</h2>
<p>A direct type change may rewrite the table or hold a long lock. For large tables, create a new column, dual-write, backfill, and then switch reads:</p>
<pre><code>ALTER TABLE orders ADD COLUMN amount_cents bigint;

UPDATE orders
SET amount_cents = round(amount * 100)
WHERE amount_cents IS NULL
  AND id &gt; :last_id
  AND id &lt;= :next_id;</code></pre>
<p>Only after every reader and writer uses <code>amount_cents</code> should <code>amount</code> enter the Contract stage.</p>

<h2>The final Contract stage</h2>
<p>Remove old structures only when telemetry proves they are unused. A typical order is:</p>
<ol>
<li>Stop dual-writing the old column.</li>
<li>Observe at least one complete deployment cycle and slow background workers.</li>
<li>Remove fallback code, temporary dashboards, and migration jobs.</li>
<li>Set a lock timeout and remove old constraints, indexes, or columns in a separate deployment.</li>
</ol>
<pre><code>SET lock_timeout = '2s';
ALTER TABLE customers DROP COLUMN full_name;</code></pre>
<p>In PostgreSQL, dropping a column generally makes it invisible rather than immediately reclaiming all disk space. Do not trigger a table rewrite solely to recover space without assessing I/O, locks, and the maintenance window.</p>

<h2>Rollback is not simply running “down”</h2>
<p>A code rollback is safe only while the new schema remains compatible with the old application. That is why Expand preserves old columns and the transition release dual-writes them. After Contract, recovery becomes harder because old data may no longer be updated or may have been deleted.</p>
<p>Every plan should state which code version can be restored, what data could be lost, whether the backfill is reversible, and where the point of no return lies. A backup does not replace fast rollback; restoring a large database may exceed the service SLA.</p>

<h2>Production checklist</h2>
<ul>
<li>Test against a copy with production-like size and data distribution.</li>
<li>Identify the lock mode, lock duration, and whether a table rewrite occurs.</li>
<li>Set appropriate <code>lock_timeout</code> and <code>statement_timeout</code>.</li>
<li>Ensure old and new code can overlap during a rolling deployment.</li>
<li>Make the backfill idempotent, checkpointed, throttled, and observable.</li>
<li>Monitor errors, latency, connection pools, replication lag, WAL, and disk.</li>
<li>Use a feature flag for the read path and maintain a stop/resume runbook.</li>
<li>Contract only after confirming that no old consumer remains.</li>
</ul>

<h2>Conclusion</h2>
<p>Zero-downtime database migration is a compatibility and operations problem, not merely a SQL problem. Expand introduces the new path without breaking the old one; Migrate moves code and data with observation; Contract removes legacy structures after evidence says it is safe. Small reversible steps are easier to pause, measure, and roll back than one all-or-nothing migration.</p>

<h2>References</h2>
<ul><li><a href="https://www.postgresql.org/docs/current/sql-altertable.html" target="_blank" rel="noopener noreferrer">PostgreSQL: ALTER TABLE</a></li><li><a href="https://www.postgresql.org/docs/current/ddl-alter.html" target="_blank" rel="noopener noreferrer">PostgreSQL: Modifying Tables</a></li><li><a href="https://www.postgresql.org/docs/current/sql-createindex.html" target="_blank" rel="noopener noreferrer">PostgreSQL: CREATE INDEX</a></li></ul>
HTML,
        ],
    ],
];
