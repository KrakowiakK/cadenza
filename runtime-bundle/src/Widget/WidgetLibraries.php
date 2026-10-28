<?php

declare(strict_types=1);

namespace Cadenza\Runtime\Widget;

use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\ImportMapType;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The JavaScript libraries a widget may import: the packages of the site's own importmap, served from the site
 * itself (a widget cannot load anything from elsewhere). Add one with `bin/console importmap:require <package>`.
 */
final class WidgetLibraries
{
    /** Small modules made here, for the ways people usually import a library. */
    private const SHIMS = [
        'chart.js/auto' => "import { Chart, registerables } from 'chart.js';\nChart.register(...registerables);\nexport * from 'chart.js';\nexport default Chart;\n",
    ];

    /** Packages that are part of the site's own machinery, not something a widget needs. */
    private const HIDDEN = ['@hotwired/', '@symfony/', 'shadcn', 'tw-animate-css', 'app'];

    public function __construct(
        #[Autowire(service: 'asset_mapper.importmap.config_reader')]
        private readonly ImportMapConfigReader $importMap,
        #[Autowire(service: 'asset_mapper.importmap.remote_package_storage')]
        private readonly RemotePackageStorage $storage,
    ) {
    }

    /**
     * @return array<string, string> import name => version
     */
    public function all(): array
    {
        $libraries = [];
        foreach ($this->importMap->getEntries() as $entry) {
            if (ImportMapType::JS !== $entry->type || !$entry->isRemotePackage() || $this->hidden($entry->importName)) {
                continue;
            }
            $libraries[$entry->importName] = (string) $entry->version;
        }
        foreach (array_keys(self::SHIMS) as $shim) {
            if (isset($libraries[strtok($shim, '/')])) {
                $libraries[$shim] = $libraries[strtok($shim, '/')];
            }
        }
        ksort($libraries);

        return $libraries;
    }

    /**
     * Names a widget writes in its imports, for the libraries the site has: the name itself, and the older
     * "three/examples/jsm/…" spelling of three's add-ons.
     *
     * @return array<string, string> specifier => import name
     */
    public function specifiers(): array
    {
        $out = [];
        foreach (array_keys($this->all()) as $name) {
            $out[$name] = $name;
            if (str_starts_with($name, 'three/addons/')) {
                $out['three/examples/jsm/'.substr($name, \strlen('three/addons/'))] = $name;
            }
        }

        return $out;
    }

    public function source(string $name): ?string
    {
        if (isset(self::SHIMS[$name]) && isset($this->all()[$name])) {
            return self::SHIMS[$name];
        }
        $entry = $this->importMap->findRootImportMapEntry($name);
        if (null === $entry || !$entry->isRemotePackage() || ImportMapType::JS !== $entry->type || $this->hidden($name)) {
            return null;
        }
        $path = $this->storage->getDownloadPath($entry->packageModuleSpecifier, $entry->type);

        return is_file($path) ? (string) file_get_contents($path) : null;
    }

    private function hidden(string $name): bool
    {
        foreach (self::HIDDEN as $prefix) {
            if ($name === $prefix || str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
