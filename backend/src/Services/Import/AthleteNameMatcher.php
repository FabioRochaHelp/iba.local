<?php

declare(strict_types=1);

namespace App\Services\Import;

/**
 * Casa nomes de atletas digitados de formas diferentes em planilhas
 * ("Ana Lú" × "Ana Lu Pereira Dias", "Fulano / Goleiro",
 * "Ana  Maria" × "ana maria").
 *
 * Regras, em ordem de confiança:
 *  1. exato        — nome normalizado idêntico (sem acento/pontuação/caixa)
 *  2. sem_espacos  — idêntico ignorando espaços ("silvasantos" × "silva santos")
 *  3. prefixo      — os nomes (sem "de/da/do...") de um começam o outro
 *  4. aproximado   — mesmos tokens, só um deles com 1 letra de diferença
 *  5. prefixo_aprox — prefixo com um token diferente por 1 letra ("Pereiras" × "Pereira Dias")
 * Com $strict = true só as regras 1 e 2 são usadas — rode primeiro em modo
 * estrito para todos e depois o completo nos que sobraram, para que um nome
 * curto ("Ana") não "roube" o par exato de outro registro ("Ana Souza").
 * Data de nascimento divergente descarta casamentos não exatos; mais de um
 * candidato na mesma regra resulta em "ambíguo" (não casa automaticamente).
 */
final class AthleteNameMatcher
{
    private const STOPWORDS = ['de', 'da', 'do', 'dos', 'das', 'e'];

    public static function normalize(string $name): string
    {
        $name = (string) preg_replace('#\s*/.*$#u', '', $name); // "Fulano / Goleiro" -> "Fulano"
        $ascii = strtr(mb_strtolower(trim($name)), [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i', 'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'ñ' => 'n',
        ]);
        $ascii = (string) preg_replace('/[^a-z0-9 ]+/', ' ', $ascii);

        return trim((string) preg_replace('/\s+/', ' ', $ascii));
    }

    /**
     * @param array<int|string, array{name: string, birth_date?: ?string}> $candidates
     * @return array{key: int|string|null, rule: ?string, ambiguous: list<int|string>}
     */
    public function match(string $name, ?string $birthDate, array $candidates, bool $strict = false): array
    {
        $target = self::normalize($name);
        if ($target === '') {
            return ['key' => null, 'rule' => null, 'ambiguous' => []];
        }
        $targetTokens = $this->tokens($target);

        $rules = [
            'exato' => fn (string $n): bool => $n === $target,
            'sem_espacos' => fn (string $n): bool => str_replace(' ', '', $n) === str_replace(' ', '', $target),
            'prefixo' => fn (string $n): bool => $this->isPrefix($this->tokens($n), $targetTokens) || $this->isPrefix($targetTokens, $this->tokens($n)),
            'aproximado' => fn (string $n): bool => $this->similarTokens($this->tokens($n), $targetTokens),
            'prefixo_aprox' => fn (string $n): bool => $this->similarPrefix($this->tokens($n), $targetTokens) || $this->similarPrefix($targetTokens, $this->tokens($n)),
        ];
        if ($strict) {
            $rules = array_slice($rules, 0, 2, true);
        }

        foreach ($rules as $rule => $test) {
            $hits = [];
            foreach ($candidates as $key => $candidate) {
                $normalized = self::normalize($candidate['name']);
                if ($normalized === '' || !$test($normalized)) {
                    continue;
                }
                $otherBirth = $candidate['birth_date'] ?? null;
                if ($rule !== 'exato' && $birthDate && $otherBirth && $birthDate !== $otherBirth) {
                    continue; // nome parecido, mas pessoa diferente
                }
                $hits[] = $key;
            }
            if (count($hits) > 1 && $birthDate) {
                $sameBirth = array_values(array_filter($hits, static fn ($k) => ($candidates[$k]['birth_date'] ?? null) === $birthDate));
                if (count($sameBirth) === 1) {
                    $hits = $sameBirth;
                }
            }
            if (count($hits) === 1) {
                return ['key' => $hits[0], 'rule' => $rule, 'ambiguous' => []];
            }
            if (count($hits) > 1) {
                return ['key' => null, 'rule' => $rule, 'ambiguous' => $hits];
            }
        }

        return ['key' => null, 'rule' => null, 'ambiguous' => []];
    }

    /** @return list<string> */
    private function tokens(string $normalized): array
    {
        return array_values(array_filter(explode(' ', $normalized), static fn ($t) => $t !== '' && !in_array($t, self::STOPWORDS, true)));
    }

    /**
     * @param list<string> $short
     * @param list<string> $long
     */
    private function isPrefix(array $short, array $long): bool
    {
        if ($short === [] || count($short) >= count($long)) {
            return false;
        }

        return array_slice($long, 0, count($short)) === $short;
    }

    /**
     * Mesmo número de tokens (2+), todos iguais exceto UM, que pode ter
     * 1 letra de diferença (tokens com 4+ letras) — cobre "Otavvio" × "Otavio".
     *
     * @param list<string> $a
     * @param list<string> $b
     */
    private function similarTokens(array $a, array $b): bool
    {
        if (count($a) < 2 || count($a) !== count($b)) {
            return false;
        }
        $different = 0;
        foreach ($a as $i => $token) {
            if ($token === $b[$i]) {
                continue;
            }
            if (++$different > 1 || min(strlen($token), strlen($b[$i])) < 4 || levenshtein($token, $b[$i]) > 1) {
                return false;
            }
        }

        return true;
    }

    /**
     * O nome menor (2+ tokens) é prefixo do maior com no máximo um token
     * diferente por 1 letra.
     *
     * @param list<string> $short
     * @param list<string> $long
     */
    private function similarPrefix(array $short, array $long): bool
    {
        if (count($short) < 2 || count($short) >= count($long)) {
            return false;
        }

        return $this->similarTokens($short, array_slice($long, 0, count($short)));
    }
}
