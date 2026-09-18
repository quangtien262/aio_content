<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$articles = require $root.'/content/english-articles.php';
$outputDirectory = $root.'/articles/en';

if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0777, true) && ! is_dir($outputDirectory)) {
    throw new RuntimeException('Cannot create '.$outputDirectory);
}

foreach ($articles as $filename => $article) {
    $source = $root.'/articles/'.$filename;
    if (! is_file($source)) {
        throw new RuntimeException('Missing source article: '.$filename);
    }

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8">'.file_get_contents($source), LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    foreach ([
        'meta-title' => $article['meta_title'],
        'meta-keywords' => $article['meta_keywords'],
        'article-tags' => implode(', ', $article['tags']),
        'meta-description' => $article['meta_description'],
    ] as $id => $value) {
        $node = $xpath->query(sprintf('//*[@id="%s"]', $id))->item(0);
        if ($node instanceof DOMElement) {
            $node->textContent = $value;
        }
    }

    $slugNode = $xpath->query('//*[@id="article-slug"]')->item(0);
    if (! $slugNode instanceof DOMElement) {
        $slugNode = $dom->createElement('textarea');
        $slugNode->setAttribute('id', 'article-slug');
        $slugNode->setAttribute('class', 'hidden-source');
        $dom->getElementsByTagName('body')->item(0)?->appendChild($slugNode);
    }
    $slugNode->textContent = $article['slug'];

    $content = $xpath->query('//*[@id="article-content"]')->item(0);
    if (! $content instanceof DOMElement) {
        throw new RuntimeException('Missing #article-content in '.$filename);
    }
    $sourceImage = $xpath->query('.//img[1]', $content)->item(0);
    $imageFilename = $sourceImage instanceof DOMElement
        ? basename(html_entity_decode($sourceImage->getAttribute('src'), ENT_QUOTES | ENT_HTML5, 'UTF-8'))
        : '';
    $cover = $imageFilename === '' ? '' : sprintf(
        '<p><img src="../../assets/%s" alt="%s" width="1440" height="810"></p>',
        htmlspecialchars($imageFilename, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        htmlspecialchars($article['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
    );
    replaceInnerHtml(
        $dom,
        $content,
        $cover.'<h1>'.htmlspecialchars($article['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>'.$article['body'],
    );

    $html = $dom->saveHTML();
    $html = preg_replace('/^<\?xml encoding="UTF-8"\>\s*/', '', $html) ?? $html;
    file_put_contents($outputDirectory.'/'.$filename, $html);
    echo 'Generated articles/en/'.$filename.PHP_EOL;
}

function replaceInnerHtml(DOMDocument $target, DOMElement $element, string $html): void
{
    while ($element->firstChild !== null) {
        $element->removeChild($element->firstChild);
    }

    $fragmentDocument = new DOMDocument('1.0', 'UTF-8');
    $fragmentDocument->loadHTML(
        '<?xml encoding="UTF-8"><body>'.$html.'</body>',
        LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
    );
    $body = $fragmentDocument->getElementsByTagName('body')->item(0);
    foreach (iterator_to_array($body?->childNodes ?? []) as $child) {
        $element->appendChild($target->importNode($child, true));
    }
}
