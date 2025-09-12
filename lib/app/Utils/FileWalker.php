<?php
namespace App\Utils;

class FileWalker
{
    public static function collect(
        string $root,
        array $include = [],
        array $exclude = [],
        array $excludeFolders = []
    ): array {
        $files = [];
        $dirIt = new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS);

        $filter = new \RecursiveCallbackFilterIterator(
            $dirIt,
            function (\SplFileInfo $current) use ($root, $excludeFolders) {
                $rel = self::relPath($current->getPathname(), $root);
                if ($current->isDir()) {
                    return !self::isExcluded($rel, $excludeFolders);
                }
                return true;
            }
        );

        $it = new \RecursiveIteratorIterator($filter, \RecursiveIteratorIterator::SELF_FIRST);

        foreach ($it as $fileInfo) {
            if (!$fileInfo->isFile()) continue;
            $rel = self::relPath($fileInfo->getPathname(), $root);
            if (!empty($exclude) && self::isExcluded($rel, $exclude)) continue;
            if (!empty($include) && !self::isIncluded($rel, $include)) continue;

            $files[$fileInfo->getPathname()] = $rel;
        }

        return $files;
    }

    private static function relPath(string $full, string $root): string
    {
        return ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');
    }

    private static function isIncluded(string $rel, array $includes): bool
    {
        foreach ($includes as $pat) {
            if (self::matchGlob($rel, $pat)) return true;
        }
        return false;
    }

    private static function isExcluded(string $rel, array $excludes): bool
    {
        foreach ($excludes as $pat) {
            if (self::matchGlob($rel, $pat)) return true;
        }
        return false;
    }

    private static function matchGlob(string $rel, string $pattern): bool
    {
        $pattern = str_replace('\\', '/', $pattern);
        $rel = str_replace('\\', '/', $rel);
        $regex = '/^' . str_replace(['**','*','.','/'], ['.*','[^/]*','\.','\/'], ltrim($pattern, '/')) . '$/u';
        return (bool)preg_match($regex, $rel);
    }
}
