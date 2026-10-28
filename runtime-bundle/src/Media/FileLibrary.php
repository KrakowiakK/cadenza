<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Media;

use Cadenza\Runtime\Entity\SiteFile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Documents for visitors, stored by the site under /files/ (never hot-linked): PDF, Word, OpenDocument, PowerPoint,
 * plain text.
 */
final class FileLibrary
{
    public const EXTENSIONS = ['pdf', 'docx', 'doc', 'odt', 'pptx', 'txt'];
    private const MAX_BYTES = 50 * 1024 * 1024;

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HttpClientInterface $http,
        #[Autowire('%kernel.project_dir%/public/files')]
        private readonly string $directory,
    ) {
    }

    public function importUrl(string $url, string $name): SiteFile
    {
        if (!preg_match('~^https?://~', $url)) {
            throw new \InvalidArgumentException('The file must be given as an http(s) URL.');
        }
        $bytes = $this->http->request('GET', $url, ['timeout' => 120])->getContent();
        if (\strlen($bytes) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('The file is larger than 50 MB.');
        }
        $extension = strtolower(pathinfo($name, \PATHINFO_EXTENSION) ?: pathinfo((string) parse_url($url, \PHP_URL_PATH), \PATHINFO_EXTENSION));
        if (!\in_array($extension, self::EXTENSIONS, true)) {
            throw new \InvalidArgumentException('Documents can be '.implode(', ', self::EXTENSIONS).'.');
        }
        if ('pdf' === $extension && !str_starts_with($bytes, '%PDF-')) {
            throw new \InvalidArgumentException('This is not a PDF file.');
        }
        $base = (new AsciiSlugger())->slug(pathinfo($name, \PATHINFO_FILENAME))->lower()->toString() ?: 'document';
        $file = substr($base, 0, 80).'-'.substr(bin2hex(random_bytes(4)), 0, 6).'.'.$extension;
        if (!is_dir($this->directory)) {
            mkdir($this->directory, 0o775, true);
        }
        file_put_contents($this->directory.'/'.$file, $bytes);
        // Pages of a PDF: counted from its page objects, good enough to tell the visitor "4 pages".
        $pages = 'pdf' === $extension ? (preg_match_all('~/Type\s*/Page[^s]~', $bytes) ?: null) : null;

        $siteFile = new SiteFile('/files/'.$file, pathinfo($name, \PATHINFO_FILENAME) ?: $base, $extension, \strlen($bytes), $pages);
        $this->em->persist($siteFile);
        $this->em->flush();

        return $siteFile;
    }

    public function get(string $path): ?SiteFile
    {
        return $this->em->getRepository(SiteFile::class)->findOneBy(['path' => $path]);
    }

    /**
     * @return list<SiteFile>
     */
    public function recent(int $limit = 50): array
    {
        return $this->em->getRepository(SiteFile::class)->findBy([], ['id' => 'DESC'], $limit);
    }
}
