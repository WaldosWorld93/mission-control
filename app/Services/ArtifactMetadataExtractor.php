<?php

namespace App\Services;

class ArtifactMetadataExtractor
{
    /** @var array<string, string> */
    private const EXTENSION_LANGUAGE_MAP = [
        'php' => 'php',
        'js' => 'javascript',
        'ts' => 'typescript',
        'jsx' => 'javascript',
        'tsx' => 'typescript',
        'py' => 'python',
        'rb' => 'ruby',
        'go' => 'go',
        'rs' => 'rust',
        'java' => 'java',
        'kt' => 'kotlin',
        'swift' => 'swift',
        'cs' => 'csharp',
        'cpp' => 'cpp',
        'c' => 'c',
        'h' => 'c',
        'css' => 'css',
        'scss' => 'scss',
        'html' => 'html',
        'vue' => 'vue',
        'svelte' => 'svelte',
        'json' => 'json',
        'yaml' => 'yaml',
        'yml' => 'yaml',
        'xml' => 'xml',
        'sql' => 'sql',
        'sh' => 'shell',
        'bash' => 'shell',
        'md' => 'markdown',
        'txt' => 'text',
    ];

    /**
     * Extract metadata from a filename and optional content.
     *
     * @return array<string, mixed>
     */
    public function extract(string $filename, ?string $content = null): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $metadata = [];

        if (isset(self::EXTENSION_LANGUAGE_MAP[$extension])) {
            $metadata['language'] = self::EXTENSION_LANGUAGE_MAP[$extension];
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'svg', 'webp', 'bmp', 'ico'])) {
            $metadata['image_type'] = $extension;
        }

        if ($content !== null && $content !== '') {
            $metadata['line_count'] = substr_count($content, "\n") + 1;
            $metadata['word_count'] = str_word_count($content);
        }

        return $metadata;
    }
}
