<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\String\Resources;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\String\Exception\RuntimeException;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @internal
 */
final class WcswidthDataGenerator
{
    private string $outDir;
    private $client;

    public function __construct(string $outDir)
    {
        $this->outDir = $outDir;

        $this->client = HttpClient::createForBaseUri('https://www.unicode.org/Public/UNIDATA/');
    }

    public function generate(): void
    {
        $this->writeWideWidthData();

        $this->writeZeroWidthData();
    }

    private function writeWideWidthData(): void
    {
        if (!preg_match('/^# EastAsianWidth-(\d+\.\d+\.\d+)\.txt/', $content = $this->client->request('GET', 'EastAsianWidth.txt')->getContent(), $matches)) {
            throw new RuntimeException('The Unicode version could not be determined.');
        }

        $version = $matches[1];

        if (!preg_match_all('/^([A-H\d]{4,})(?:\.\.([A-H\d]{4,}))?;[W|F]/m', $content, $matches, \PREG_SET_ORDER)) {
            throw new RuntimeException('The wide width pattern did not match anything.');
        }

        $this->write('wcswidth_table_wide.php', $version, $matches);
    }

    private function writeZeroWidthData(): void
    {
        if (!preg_match('/^# DerivedGeneralCategory-(\d+\.\d+\.\d+)\.txt/', $content = $this->client->request('GET', 'extracted/DerivedGeneralCategory.txt')->getContent(), $matches)) {
            throw new RuntimeException('The Unicode version could not be determined.');
        }

        $version = $matches[1];

        if (!preg_match_all('/^([A-H\d]{4,})(?:\.\.([A-H\d]{4,}))? *; (?:Me|Mn)/m', $content, $matches, \PREG_SET_ORDER)) {
            throw new RuntimeException('The zero width pattern did not match anything.');
        }

        $this->write('wcswidth_table_zero.php', $version, $matches);
    }

    private function write(string $fileName, string $version, array $rawData): void
    {
        $content = $this->getHeader($version).'return '.VarExporter::export($this->format($rawData)).";\n";

        if (!file_put_contents($this->outDir.'/'.$fileName, $content)) {
            throw new RuntimeException(sprintf('The "%s" file could not be written.', $fileName));
        }
    }

    private function getHeader(string $version): string
    {
        $date = (new \DateTimeImmutable())->format('c');

        return <<<EOT
<?php

/*
 * This file has been auto-generated by the Symfony String Component for internal use.
 *
 * Unicode version: $version
 * Date: $date
 */


EOT;
    }

    private function format(array $rawData): array
    {
        $data = array_map(static function (array $row): array {
            $start = $row[1];
            $end = $row[2] ?? $start;

            return [hexdec($start), hexdec($end)];
        }, $rawData);

        usort($data, static function (array $a, array $b): int {
            return $a[0] - $b[0];
        });

        return $data;
    }
}
