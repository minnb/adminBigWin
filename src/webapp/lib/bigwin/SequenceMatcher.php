<?php
declare(strict_types=1);

// Port difflib.SequenceMatcher (CPython 3.13) với isjunk=None, autojunk=False — cách BigWIN luôn gọi. THUẦN.
// Phần tử so bằng ===; chỉ mục b2j dùng khoá "k:<phần tử>" để token chuỗi số ("05") không bị PHP ép về int (QĐ4).

final class SequenceMatcher
{
    /** @var list<string> */
    private array $a;
    /** @var list<string> */
    private array $b;
    /** @var array<string, list<int>> */
    private array $b2j = [];
    private ?array $matchingBlocks = null;

    /** Chuỗi → tách theo ký tự UTF-8 (như list(str) Python); mảng → giữ nguyên thứ tự phần tử. */
    public function __construct(array|string $a, array|string $b)
    {
        $this->a = is_string($a) ? mb_str_split($a, 1, 'UTF-8') : array_values($a);
        $this->b = is_string($b) ? mb_str_split($b, 1, 'UTF-8') : array_values($b);
        foreach ($this->b as $j => $x) {
            $this->b2j["k:$x"][] = $j;
        }
    }

    /** [i, j, k]: khối khớp dài nhất trong a[alo:ahi] / b[blo:bhi]; hoà thì i nhỏ nhất, rồi j nhỏ nhất (như difflib). */
    public function findLongestMatch(int $alo, int $ahi, int $blo, int $bhi): array
    {
        [$besti, $bestj, $bestsize] = [$alo, $blo, 0];
        $j2len = [];
        for ($i = $alo; $i < $ahi; $i++) {
            $newj2len = [];
            foreach ($this->b2j['k:' . $this->a[$i]] ?? [] as $j) {
                if ($j < $blo) {
                    continue;
                }
                if ($j >= $bhi) {
                    break;
                }
                $k = $newj2len[$j] = ($j2len[$j - 1] ?? 0) + 1;
                if ($k > $bestsize) {
                    [$besti, $bestj, $bestsize] = [$i - $k + 1, $j - $k + 1, $k];
                }
            }
            $j2len = $newj2len;
        }
        // Không có phần tử junk/popular (autojunk=False) → hai vòng nới khối của difflib chỉ còn dạng "không junk".
        while ($besti > $alo && $bestj > $blo && $this->a[$besti - 1] === $this->b[$bestj - 1]) {
            [$besti, $bestj, $bestsize] = [$besti - 1, $bestj - 1, $bestsize + 1];
        }
        while ($besti + $bestsize < $ahi && $bestj + $bestsize < $bhi
            && $this->a[$besti + $bestsize] === $this->b[$bestj + $bestsize]) {
            $bestsize++;
        }
        return [$besti, $bestj, $bestsize];
    }

    /** list<[i, j, n]> đã gộp khối liền kề, kết thúc bằng [len(a), len(b), 0]. */
    public function getMatchingBlocks(): array
    {
        if ($this->matchingBlocks !== null) {
            return $this->matchingBlocks;
        }
        $la = count($this->a);
        $lb = count($this->b);
        $queue = [[0, $la, 0, $lb]];
        $blocks = [];
        while ($queue) {
            [$alo, $ahi, $blo, $bhi] = array_pop($queue);
            [$i, $j, $k] = $x = $this->findLongestMatch($alo, $ahi, $blo, $bhi);
            if ($k) {
                $blocks[] = $x;
                if ($alo < $i && $blo < $j) {
                    $queue[] = [$alo, $i, $blo, $j];
                }
                if ($i + $k < $ahi && $j + $k < $bhi) {
                    $queue[] = [$i + $k, $ahi, $j + $k, $bhi];
                }
            }
        }
        sort($blocks); // mảng int cùng độ dài → so từng phần tử như tuple Python
        [$i1, $j1, $k1] = [0, 0, 0];
        $kq = [];
        foreach ($blocks as [$i2, $j2, $k2]) {
            if ($i1 + $k1 === $i2 && $j1 + $k1 === $j2) {
                $k1 += $k2;
                continue;
            }
            if ($k1) {
                $kq[] = [$i1, $j1, $k1];
            }
            [$i1, $j1, $k1] = [$i2, $j2, $k2];
        }
        if ($k1) {
            $kq[] = [$i1, $j1, $k1];
        }
        $kq[] = [$la, $lb, 0];
        return $this->matchingBlocks = $kq;
    }

    /** list<[tag, i1, i2, j1, j2]>, tag ∈ replace|delete|insert|equal. */
    public function getOpcodes(): array
    {
        $i = $j = 0;
        $kq = [];
        foreach ($this->getMatchingBlocks() as [$ai, $bj, $size]) {
            $tag = match (true) {
                $i < $ai && $j < $bj => 'replace',
                $i < $ai => 'delete',
                $j < $bj => 'insert',
                default => '',
            };
            if ($tag !== '') {
                $kq[] = [$tag, $i, $ai, $j, $bj];
            }
            [$i, $j] = [$ai + $size, $bj + $size];
            if ($size) {
                $kq[] = ['equal', $ai, $i, $bj, $j];
            }
        }
        return $kq;
    }

    public function ratio(): float
    {
        $tong = count($this->a) + count($this->b);
        $khop = array_sum(array_column($this->getMatchingBlocks(), 2));
        return $tong ? 2.0 * $khop / $tong : 1.0;
    }
}
