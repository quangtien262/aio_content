<?php

declare(strict_types=1);

const CATEGORY_IDS = [
    'kien-thuc-cong-nghe' => 2,
    'cong-nghe' => 2,
    'huong-dan' => 5,
    'giai-phap' => 6,
    'lap-trinh' => 7,
    'he-dieu-hanh' => 8,
    'os' => 8,
];

const MAX_COVER_BYTES = 1024 * 1024;

main($argv);

function main(array $argv): void
{
    $options = parseArguments($argv);
    $file = realpath($options['file']);
    if ($file === false || ! is_file($file)) {
        fail('Không tìm thấy file bài viết: '.$options['file']);
    }

    $config = loadConfiguration(dirname(__DIR__).'/.publisher.env');
    $caBundle = trim($config['CONTENT_API_CA_BUNDLE'] ?? '');
    if ($caBundle !== '') {
        putenv('CONTENT_API_CA_BUNDLE='.$caBundle);
    }
    $article = parseArticle($file, $options['category']);
    $defaultStatus = $config['CONTENT_API_DEFAULT_STATUS'] ?? 'published';
    $article['status'] = $options['draft'] ? 'draft' : ($options['publish'] ? 'published' : $defaultStatus);
    $article['publish_at'] = $article['status'] === 'published'
        ? (new DateTimeImmutable('now', new DateTimeZone('Asia/Ho_Chi_Minh')))->format(DateTimeInterface::ATOM)
        : null;
    $englishFile = resolveEnglishFile($file, $options['english'], $options['vi_only']);
    $english = $englishFile === null ? null : translationPayload(parseArticle($englishFile, $article['category_id']));
    if ($english !== null) {
        $english['publish'] = $article['status'] === 'published';
        $english['is_machine_translated'] = true;
    }

    if ($options['dry_run']) {
        unset($article['cover_path']);
        echo json_encode([
            'source' => $article,
            'translations' => $english === null ? [] : ['en' => $english],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

        return;
    }

    $baseUrl = rtrim($config['CONTENT_API_BASE_URL'] ?? '', '/');
    $token = trim($config['CONTENT_API_TOKEN'] ?? '');
    if ($baseUrl === '' || $token === '') {
        fail('Thiếu CONTENT_API_BASE_URL hoặc CONTENT_API_TOKEN trong .publisher.env.');
    }

    if (! $options['english_only']) {
        $existing = requestGet(sprintf(
            '%s/api/v1/cms/posts/%s',
            $baseUrl,
            rawurlencode($article['external_id']),
        ), $token);
        $existingPublishAt = $existing['data']['publish_at'] ?? null;
        if (is_string($existingPublishAt) && trim($existingPublishAt) !== '') {
            $article['publish_at'] = $existingPublishAt;
        }
    }

    if (! $options['english_only'] && ! $options['no_image'] && $article['cover_path'] !== null) {
        $coverPath = $article['cover_path'];
        if (filesize($coverPath) > MAX_COVER_BYTES) {
            fail('Ảnh đại diện vượt quá 1 MB, hãy tối ưu trước khi đăng: '.basename($coverPath));
        }
        $media = requestMultipart($baseUrl.'/api/v1/cms/media', $token, [
            'external_id' => $article['external_id'].':featured',
            'payload_hash' => hash_file('sha256', $coverPath),
            'title' => $article['title'],
            'alt_text' => $article['title'],
            'file' => new CURLFile($coverPath, mime_content_type($coverPath) ?: 'image/jpeg', basename($coverPath)),
        ]);
        $article['featured_media_id'] = $media['data']['id'] ?? null;
    }

    $data = null;
    if (! $options['english_only']) {
        unset($article['cover_path']);
        $article['payload_hash'] = hash('sha256', json_encode(
            $article,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
        $response = requestJson($baseUrl.'/api/v1/cms/posts/upsert', $token, $article);
        $data = $response['data'] ?? [];
    }

    $translationData = null;
    if ($english !== null) {
        $translationResponse = requestJson(sprintf(
            '%s/api/v1/cms/posts/%s/translations/en/upsert',
            $baseUrl,
            rawurlencode($article['external_id']),
        ), $token, $english);
        $translationData = $translationResponse['data'] ?? [];
    }

    if ($data !== null) {
        echo sprintf(
            "Hoàn tất VI: #%s | %s | %s | category %s\n",
            $data['id'] ?? '?',
            $data['status'] ?? '?',
            $data['slug'] ?? $article['slug'],
            $data['category_id'] ?? $article['category_id'],
        );
    }
    if ($translationData !== null) {
        echo sprintf(
            "Hoàn tất EN: %s | %s | %s\n",
            $translationData['translation_status'] ?? '?',
            $translationData['slug'] ?? $english['slug'],
            $translationData['public_path'] ?? 'chưa có URL công khai',
        );
    }
}

function parseArguments(array $argv): array
{
    array_shift($argv);
    $file = null;
    $category = null;
    $publish = false;
    $draft = false;
    $dryRun = false;
    $noImage = false;
    $english = null;
    $viOnly = false;
    $englishOnly = false;

    foreach ($argv as $argument) {
        if ($argument === '--publish') {
            $publish = true;
        } elseif ($argument === '--draft') {
            $draft = true;
        } elseif ($argument === '--dry-run') {
            $dryRun = true;
        } elseif ($argument === '--no-image') {
            $noImage = true;
        } elseif ($argument === '--vi-only') {
            $viOnly = true;
        } elseif ($argument === '--english-only') {
            $englishOnly = true;
        } elseif (str_starts_with($argument, '--english=')) {
            $english = substr($argument, strlen('--english='));
        } elseif (str_starts_with($argument, '--category=')) {
            $category = (int) substr($argument, strlen('--category='));
        } elseif (! str_starts_with($argument, '--') && $file === null) {
            $file = $argument;
        } else {
            fail('Tham số không hợp lệ: '.$argument);
        }
    }

    if ($file === null) {
        fail('Cách dùng: php tools/publish-article.php articles/<file>.html [--english=articles/en/<file>.html] [--vi-only|--english-only] [--draft] [--dry-run] [--category=ID] [--no-image]');
    }

    if ($viOnly && $englishOnly) {
        fail('Không thể dùng đồng thời --vi-only và --english-only.');
    }

    return [
        'file' => $file,
        'category' => $category,
        'publish' => $publish,
        'draft' => $draft,
        'dry_run' => $dryRun,
        'no_image' => $noImage,
        'english' => $english,
        'vi_only' => $viOnly,
        'english_only' => $englishOnly,
    ];
}

function resolveEnglishFile(string $sourceFile, ?string $explicitFile, bool $viOnly): ?string
{
    if ($viOnly) {
        if ($explicitFile !== null) {
            fail('Không thể dùng đồng thời --english và --vi-only.');
        }

        return null;
    }

    $candidate = $explicitFile ?? dirname($sourceFile).DIRECTORY_SEPARATOR.'en'.DIRECTORY_SEPARATOR.basename($sourceFile);
    $resolved = realpath($candidate);
    if ($resolved === false || ! is_file($resolved)) {
        fail('Thiếu bản tiếng Anh: '.$candidate.'. Tạo file này, truyền --english=<file>, hoặc chủ động dùng --vi-only.');
    }

    return $resolved;
}

function loadConfiguration(string $path): array
{
    $values = [];
    if (is_file($path)) {
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || ! str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $values[trim($key)] = trim(trim($value), "\"'");
        }
    }

    foreach (['CONTENT_API_BASE_URL', 'CONTENT_API_TOKEN', 'CONTENT_API_DEFAULT_STATUS', 'CONTENT_API_CA_BUNDLE'] as $key) {
        if (($environmentValue = getenv($key)) !== false) {
            $values[$key] = $environmentValue;
        }
    }

    return $values;
}

function parseArticle(string $file, ?int $categoryOverride): array
{
    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $html = file_get_contents($file);
    if ($html === false || ! $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING)) {
        fail('Không đọc được HTML: '.$file);
    }
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $content = $xpath->query('//*[@id="article-content"]')->item(0);
    if (! $content instanceof DOMElement) {
        fail('Không tìm thấy #article-content trong '.$file);
    }

    $titleNode = $xpath->query('.//h1', $content)->item(0);
    $title = trim($titleNode?->textContent ?? '');
    if ($title === '') {
        fail('Bài viết không có H1.');
    }
    $fileKey = pathinfo($file, PATHINFO_FILENAME);
    $slug = fieldValue($xpath, 'article-slug') ?: $fileKey;
    $categoryId = $categoryOverride ?: categoryId($xpath, $slug);
    $coverNode = $xpath->query('.//img[1]', $content)->item(0);
    $coverPath = null;
    if ($coverNode instanceof DOMElement) {
        $src = html_entity_decode($coverNode->getAttribute('src'), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $resolved = realpath(dirname($file).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $src));
        $coverPath = $resolved !== false && is_file($resolved) ? $resolved : null;
    }

    $contentClone = $content->cloneNode(true);
    $cloneXpath = new DOMXPath($dom);
    $firstImage = $cloneXpath->query('.//img[1]', $contentClone)->item(0);
    if ($firstImage !== null) {
        $parent = $firstImage->parentNode;
        if ($parent?->nodeName === 'p') {
            $parent->parentNode?->removeChild($parent);
        } else {
            $firstImage->parentNode?->removeChild($firstImage);
        }
    }
    $firstHeading = $cloneXpath->query('.//h1[1]', $contentClone)->item(0);
    $firstHeading?->parentNode?->removeChild($firstHeading);
    foreach (iterator_to_array($cloneXpath->query('.//*[@class]', $contentClone)) as $node) {
        $node->removeAttribute('class');
    }

    $body = '';
    foreach ($contentClone->childNodes as $child) {
        $body .= $dom->saveHTML($child);
    }
    $body = trim($body);
    $firstParagraph = $cloneXpath->query('.//p[normalize-space()]', $contentClone)->item(0);
    $excerpt = trim($firstParagraph?->textContent ?? '');
    $excerpt = mb_substr(preg_replace('/\s+/u', ' ', $excerpt) ?: '', 0, 500);

    $metaTitle = fieldValue($xpath, 'meta-title') ?: $title;
    $metaKeywords = fieldValue($xpath, 'meta-keywords');
    $metaDescription = fieldValue($xpath, 'meta-description') ?: $excerpt;
    $tags = array_values(array_filter(array_map(
        static fn (string $tag): string => trim($tag),
        explode(',', fieldValue($xpath, 'article-tags')),
    )));

    return [
        'external_id' => 'tech-content:'.$fileKey,
        'category_id' => $categoryId,
        'title' => $title,
        'slug' => $slug,
        'excerpt' => $excerpt,
        'body' => $body,
        'meta_title' => $metaTitle,
        'meta_keywords' => $metaKeywords,
        'meta_description' => $metaDescription,
        'tags' => $tags,
        'is_highlight' => false,
        'cover_path' => $coverPath,
    ];
}

function translationPayload(array $article): array
{
    return array_intersect_key($article, array_flip([
        'title',
        'slug',
        'excerpt',
        'body',
        'meta_title',
        'meta_keywords',
        'meta_description',
    ]));
}

function fieldValue(DOMXPath $xpath, string $id): string
{
    return trim($xpath->query(sprintf('//textarea[@id="%s"]', $id))->item(0)?->textContent ?? '');
}

function categoryId(DOMXPath $xpath, string $slug): int
{
    $label = mb_strtolower(trim($xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " brand ")]//span')->item(0)?->textContent ?? ''));
    $haystack = $label.' '.$slug;
    $categorySlug = match (true) {
        str_contains($haystack, 'hướng dẫn'), str_contains($haystack, 'huong-dan') => 'huong-dan',
        str_contains($haystack, 'giải pháp'), str_contains($haystack, 'giai-phap') => 'giai-phap',
        str_contains($haystack, 'lập trình'), str_contains($haystack, 'lap-trinh') => 'lap-trinh',
        str_contains($haystack, 'hệ điều hành'), preg_match('/(^|-)os(-|$)/', $slug) === 1 => 'he-dieu-hanh',
        str_contains($haystack, 'công nghệ'), str_contains($haystack, 'cong-nghe'), str_contains($slug, 'gioi-thieu-ht-viet-nam-tech') => 'kien-thuc-cong-nghe',
        default => '',
    };

    if ($categorySlug === '' || ! isset(CATEGORY_IDS[$categorySlug])) {
        fail('Không xác định được danh mục. Hãy truyền --category=ID.');
    }

    return CATEGORY_IDS[$categorySlug];
}

function requestJson(string $url, string $token, array $payload): array
{
    return executeRequest($url, $token, json_encode(
        $payload,
        JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ), ['Content-Type: application/json']);
}

function requestMultipart(string $url, string $token, array $payload): array
{
    return executeRequest($url, $token, $payload, []);
}

function requestGet(string $url, string $token): ?array
{
    $handle = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        ],
    ];
    $caBundle = getenv('CONTENT_API_CA_BUNDLE');
    if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($handle, $options);
    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($raw === false) {
        fail('Không kết nối được API: '.$error);
    }
    if ($status === 404) {
        return null;
    }
    $decoded = json_decode($raw, true);
    if (! is_array($decoded) || $status < 200 || $status >= 300) {
        fail('Không đọc được bài hiện có từ API (HTTP '.$status.').');
    }

    return $decoded;
}

function executeRequest(string $url, string $token, string|array $payload, array $headers): array
{
    $handle = curl_init($url);
    $options = [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => array_merge([
            'Accept: application/json',
            'Authorization: Bearer '.$token,
        ], $headers),
    ];
    $caBundle = getenv('CONTENT_API_CA_BUNDLE');
    if (is_string($caBundle) && $caBundle !== '' && is_file($caBundle)) {
        $options[CURLOPT_CAINFO] = $caBundle;
    }
    curl_setopt_array($handle, $options);
    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = curl_error($handle);
    curl_close($handle);

    if ($raw === false) {
        fail('Không kết nối được API: '.$error);
    }
    $decoded = json_decode($raw, true);
    if (! is_array($decoded)) {
        fail('API trả về dữ liệu không hợp lệ (HTTP '.$status.').');
    }
    if ($status < 200 || $status >= 300) {
        $message = $decoded['message'] ?? 'API request thất bại.';
        $errors = isset($decoded['errors']) ? ' '.json_encode($decoded['errors'], JSON_UNESCAPED_UNICODE) : '';
        fail('HTTP '.$status.': '.$message.$errors);
    }

    return $decoded;
}

function fail(string $message): never
{
    fwrite(STDERR, $message.PHP_EOL);
    exit(1);
}
