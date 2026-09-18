<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$articles = require $root.'/content/linux-articles.php';
$template = file_get_contents($root.'/templates/article-template.html');

if ($template === false) {
    throw new RuntimeException('Cannot read article template.');
}

foreach ($articles as $filename => $locales) {
    foreach ($locales as $locale => $article) {
        $directory = $root.'/articles'.($locale === 'en' ? '/en' : '');
        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException('Cannot create '.$directory);
        }

        libxml_use_internal_errors(true);
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML('<?xml encoding="UTF-8">'.$template, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);

        foreach ([
            'article-slug' => $locale === 'en' ? $article['slug'] : '',
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

        $brand = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " brand ")]//span')->item(0);
        if ($brand instanceof DOMElement) {
            $brand->textContent = $locale === 'en' ? 'Operating Systems' : 'Hệ điều hành';
        }

        $content = $xpath->query('//*[@id="article-content"]')->item(0);
        if (! $content instanceof DOMElement) {
            throw new RuntimeException('Template has no #article-content.');
        }
        $imagePrefix = $locale === 'en' ? '../../assets/' : '../assets/';
        $cover = sprintf(
            '<p><img src="%s%s" alt="%s" width="1440" height="810"></p>',
            $imagePrefix,
            htmlspecialchars($article['image'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            htmlspecialchars($article['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        );
        replaceArticleHtml(
            $dom,
            $content,
            $cover.'<h1>'.htmlspecialchars($article['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8').'</h1>'.$article['body'],
        );

        $html = $dom->saveHTML();
        $html = preg_replace('/^<\?xml encoding="UTF-8"\>\s*/', '', $html) ?? $html;
        file_put_contents($directory.'/'.$filename, $html);
        echo sprintf("Generated %s/%s\n", $locale, $filename);
    }
}

function replaceArticleHtml(DOMDocument $target, DOMElement $element, string $html): void
{
    while ($element->firstChild !== null) {
        $element->removeChild($element->firstChild);
    }

    $fragment = new DOMDocument('1.0', 'UTF-8');
    $fragment->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>', LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    $body = $fragment->getElementsByTagName('body')->item(0);
    foreach (iterator_to_array($body?->childNodes ?? []) as $child) {
        $element->appendChild($target->importNode($child, true));
    }
}
