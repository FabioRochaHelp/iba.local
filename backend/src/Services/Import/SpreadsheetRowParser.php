<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Support\Money;
use App\Support\PhoneNormalizer;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Converte as linhas da planilha do Google Forms em registros
 * normalizados, anotando inconsistências (para o relatório --dry-run).
 */
final class SpreadsheetRowParser
{
    private const MONTHS = [
        'janeiro' => 1, 'fevereiro' => 2, 'marco' => 3, 'abril' => 4, 'maio' => 5, 'junho' => 6,
        'julho' => 7, 'agosto' => 8, 'setembro' => 9, 'outubro' => 10, 'novembro' => 11, 'dezembro' => 12,
    ];

    private const POSITION_ALIASES = [
        'goleiro' => 'Goleiro', 'gol' => 'Goleiro',
        'zagueiro' => 'Zagueiro', 'zaga' => 'Zagueiro',
        'lateral direito' => 'Lateral Direito', 'lateral direita' => 'Lateral Direito', 'ld' => 'Lateral Direito',
        'lateral esquerdo' => 'Lateral Esquerdo', 'lateral esquerda' => 'Lateral Esquerdo', 'le' => 'Lateral Esquerdo',
        'volante' => 'Volante',
        'meia' => 'Meia', 'meio campo' => 'Meia', 'meia central' => 'Meia',
        'meia direita' => 'Meia Direita', 'meia direito' => 'Meia Direita',
        'meia esquerda' => 'Meia Esquerda', 'meia esquerdo' => 'Meia Esquerda',
        'atacante' => 'Atacante', 'ataque' => 'Atacante', 'centroavante' => 'Atacante', 'ponta' => 'Atacante',
    ];

    public function __construct(private PhoneNormalizer $phones)
    {
    }

    /**
     * Identifica as colunas pelo texto do cabeçalho (tolerante a variações).
     *
     * @param list<string> $headers
     * @return array<string, string> campo => cabeçalho original
     */
    public function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $header) {
            $h = $this->simplify($header);
            $field = match (true) {
                str_contains($h, 'carimbo') || str_contains($h, 'data/hora') => 'timestamp',
                str_contains($h, 'nome do atleta') || $h === 'atleta' || $h === 'nome' => 'name',
                str_contains($h, 'nascimento') => 'birth_date',
                str_contains($h, 'telefone') || str_contains($h, 'celular') || str_contains($h, 'whatsapp') => 'phone',
                str_contains($h, 'responsavel') => 'guardian',
                str_contains($h, 'posicao') => 'positions',
                str_contains($h, 'saude') || str_contains($h, 'condicao') => 'health',
                str_contains($h, 'plano') => 'plan',
                str_contains($h, 'uniforme') => 'uniform',
                str_contains($h, 'mensalidade') => 'monthly',
                str_contains($h, 'patrocin') => 'sponsorship',
                default => null,
            };
            if ($field !== null && !isset($map[$field])) {
                $map[$field] = $header;
            }
        }

        return $map;
    }

    /** Mês citado no cabeçalho da mensalidade ("Mensalidade Abril" => 4). */
    public function monthFromHeader(string $header): ?int
    {
        $h = $this->simplify($header);
        foreach (self::MONTHS as $name => $number) {
            if (str_contains($h, $name)) {
                return $number;
            }
        }

        return null;
    }

    /**
     * @param array<string, string> $row
     * @param array<string, string> $map
     * @return array<string, mixed>
     */
    public function parse(array $row, array $map, int $line): array
    {
        $get = static fn (string $field): string => isset($map[$field]) ? trim((string) ($row[$map[$field]] ?? '')) : '';
        $issues = [];
        $warn = static function (string $message) use (&$issues): void {
            $issues[] = $message;
        };

        $name = $this->titleCase($get('name'));
        if ($name === '') {
            return ['line' => $line, 'skip' => true, 'issues' => ['Linha sem nome do atleta — ignorada.']];
        }

        $birth = $this->parseDate($get('birth_date'));
        if ($get('birth_date') === '') {
            $warn('Data de nascimento não informada.');
        } elseif ($birth === null) {
            $warn("Data de nascimento inválida: \"{$get('birth_date')}\".");
        }

        $guardian = $this->titleCase($get('guardian'));
        if ($guardian === '') {
            $guardian = 'Responsável de ' . $name;
            $warn('Responsável não informado.');
        }
        $phones = $this->phones->parseMany($get('phone'));
        foreach ($phones['invalid'] as $bad) {
            $warn("Telefone inválido ignorado: \"{$bad}\".");
        }
        if ($phones['valid'] === []) {
            $warn('Nenhum telefone válido.');
        }

        [$positions, $unknownPositions] = $this->parsePositions($get('positions'));
        foreach ($unknownPositions as $p) {
            $warn("Posição não reconhecida: \"{$p}\".");
        }

        $health = $this->parseHealth($get('health'));

        $planDays = null;
        if (preg_match('/(\d)\s*dias?/u', $get('plan'), $m)) {
            $planDays = (int) $m[1];
        } elseif ($get('plan') !== '') {
            $warn("Plano não reconhecido: \"{$get('plan')}\".");
        } else {
            $warn('Plano não informado.');
        }

        $monthly = $this->parsePayment($get('monthly'), $warn, 'mensalidade');
        $uniform = $this->parsePayment($get('uniform'), $warn, 'uniforme');
        if ($uniform !== null && $uniform['amount'] === null && $uniform['type'] === 'paid') {
            $warn('Uniforme marcado como pago sem valor — informe o valor manualmente.');
        }

        $sponsorship = null;
        if ($get('sponsorship') !== '') {
            try {
                $sponsorship = Money::of($get('sponsorship'))->toDecimal();
            } catch (InvalidArgumentException) {
                $warn("Valor de patrocínio inválido: \"{$get('sponsorship')}\".");
            }
        }

        return [
            'line' => $line,
            'skip' => false,
            'name' => $name,
            'birth_date' => $birth,
            'guardian' => $guardian,
            'phones' => $phones['valid'],
            'positions' => $positions,
            'health' => $health,
            'plan_days' => $planDays,
            'monthly' => $monthly,
            'uniform' => $uniform,
            'sponsorship' => $sponsorship,
            'enrollment_date' => $this->parseDate(substr($get('timestamp'), 0, 10)),
            'issues' => $issues,
        ];
    }

    /**
     * "Pago" | "Pago 60,00" | "60,00" | "CORTEZIA" | "Cortesia" | "Bolsista"
     *
     * @return array{type: string, amount: ?string}|null
     */
    private function parsePayment(string $raw, callable $warn, string $label): ?array
    {
        if ($raw === '') {
            return null;
        }
        $s = $this->simplify($raw);
        if (preg_match('/^(corte[sz]ia|bolsista|isento|gratis)/', $s)) {
            return ['type' => 'courtesy', 'amount' => null];
        }
        if (preg_match('/^(pago|pg|ok|quitado)?\s*(r\$)?\s*([\d.,]+)?$/u', $s, $m) && ($m[1] ?? '') . ($m[3] ?? '') !== '') {
            $amount = null;
            if (!empty($m[3])) {
                try {
                    $amount = Money::of($m[3])->toDecimal();
                } catch (InvalidArgumentException) {
                    $warn("Valor de {$label} inválido: \"{$raw}\".");
                    return null;
                }
            }

            return ['type' => 'paid', 'amount' => $amount];
        }
        $warn("Situação de {$label} não reconhecida: \"{$raw}\".");

        return null;
    }

    /** @return array{0: list<string>, 1: list<string>} [posições reconhecidas, não reconhecidas] */
    private function parsePositions(string $raw): array
    {
        $found = [];
        $unknown = [];
        foreach (preg_split('#\s*(?:,|/|;|\be\b)\s*#iu', $raw) ?: [] as $part) {
            $key = $this->simplify($part);
            if ($key === '') {
                continue;
            }
            if (isset(self::POSITION_ALIASES[$key])) {
                $found[] = self::POSITION_ALIASES[$key];
            } else {
                $unknown[] = trim($part);
            }
        }

        return [array_values(array_unique($found)), $unknown];
    }

    private function parseHealth(string $raw): ?string
    {
        $s = $this->simplify($raw);
        if ($s === '' || preg_match('/^(nao|n|nenhuma?|nada|-|sem)\.?$/', $s)) {
            return null;
        }
        // "Sim Asma" -> "Asma"
        $clean = trim((string) preg_replace('/^sim[\s,.:-]*/iu', '', trim($raw)));

        return $clean === '' ? 'Sim (não especificada)' : mb_strtoupper(mb_substr($clean, 0, 1)) . mb_substr($clean, 1);
    }

    private function parseDate(string $raw): ?string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        foreach (['!d/m/Y', '!j/n/Y', '!Y-m-d', '!d-m-Y', '!d/m/y'] as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $raw);
            $errors = DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                $year = (int) $date->format('Y');
                if ($year >= 1990 && $date <= new DateTimeImmutable('today')) {
                    return $date->format('Y-m-d');
                }
            }
        }

        return null;
    }

    /** Padroniza nomes: "pedro henrique da silva" -> "Pedro Henrique da Silva" (preposições em minúsculo). */
    private function titleCase(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', strip_tags($name)));
        if ($name === '') {
            return '';
        }
        $small = ['da', 'de', 'do', 'das', 'dos', 'e'];
        $words = array_map(static function (string $w, int $i) use ($small): string {
            $lower = mb_strtolower($w);
            if ($i > 0 && in_array($lower, $small, true)) {
                return $lower;
            }

            return mb_strtoupper(mb_substr($lower, 0, 1)) . mb_substr($lower, 1);
        }, explode(' ', $name), array_keys(explode(' ', $name)));

        return mb_substr(implode(' ', $words), 0, 120);
    }

    private function simplify(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = strtr($text, ['á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);

        return trim((string) preg_replace('/\s+/', ' ', rtrim($text, ':?.')));
    }
}
